"""Local-only real HTTP/CSRF/SMTP smoke. Python 3 stdlib; no external delivery.
Run: python tests/http_smtp_smoke.py --php /path/to/php
Uses an isolated temporary SQLite DB and loopback SMTP sink; never prints codes.
"""
import argparse
import http.cookiejar
import json
import os
from pathlib import Path
import queue
import re
import secrets
import socket
import socketserver
import sqlite3
import subprocess
import tempfile
import threading
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
messages = queue.Queue()


class SMTP(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(b'220 localhost smoke sink\r\n')
        while True:
            line = self.rfile.readline()
            if not line:
                return
            verb = line.split(b' ', 1)[0].strip().upper()
            if verb in (b'EHLO', b'HELO'):
                self.wfile.write(b'250 localhost\r\n')
            elif verb == b'DATA':
                self.wfile.write(b'354 End with dot\r\n')
                body = []
                while True:
                    data = self.rfile.readline()
                    if data in (b'.\r\n', b''):
                        break
                    body.append(data)
                messages.put(b''.join(body))
                self.wfile.write(b'250 Accepted locally\r\n')
            elif verb == b'QUIT':
                self.wfile.write(b'221 Bye\r\n')
                return
            else:
                self.wfile.write(b'250 OK\r\n')


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


class Client:
    def __init__(self, base):
        self.base = base
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

    def request(self, path, data=None):
        body = None if data is None else urllib.parse.urlencode(data).encode()
        try:
            response = self.opener.open(self.base + path, body, timeout=15)
        except urllib.error.HTTPError as error:
            response = error
        return response.code, response.headers, response.read().decode()

    def token(self, path):
        status, _, body = self.request(path)
        assert status == 200, 'Form unavailable'
        match = re.search(r'name="_token" value="([^"]+)"', body)
        assert match, 'CSRF token missing'
        return match.group(1)

    def post(self, path, form_path, **data):
        return self.request(path, {'_token': self.token(form_path), **data})


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--php', default='php')
    args = parser.parse_args()
    smtp = socketserver.ThreadingTCPServer(('127.0.0.1', 0), SMTP)
    smtp.daemon_threads = True
    thread = threading.Thread(target=smtp.serve_forever, daemon=True)
    thread.start()
    proc = None
    try:
        with tempfile.TemporaryDirectory(prefix='jagokita-smoke-') as tmp:
            db = Path(tmp) / 'smoke.sqlite'
            db.touch()
            with socket.socket() as sock:
                sock.bind(('127.0.0.1', 0))
                port = sock.getsockname()[1]
            env = dict(os.environ, APP_ENV='local', APP_DEBUG='false',
                       APP_KEY='base64:' + __import__('base64').b64encode(secrets.token_bytes(32)).decode(),
                       APP_URL=f'http://127.0.0.1:{port}', DB_CONNECTION='sqlite', DB_DATABASE=str(db),
                       SESSION_DRIVER='database', SESSION_ENCRYPT='true', SESSION_SECURE_COOKIE='false',
                       CACHE_STORE='database', MAIL_MAILER='smtp', MAIL_SCHEME='smtp',
                       MAIL_URL='null', MAIL_HOST='127.0.0.1', MAIL_PORT=str(smtp.server_address[1]),
                       MAIL_USERNAME='', MAIL_PASSWORD='', MAIL_FROM_ADDRESS='portal@example.test',
                       PORTAL_SMTP_READY='true', SMOKE_ADMIN_PASSWORD=secrets.token_urlsafe(24))
            assert not (ROOT / 'bootstrap/cache/config.php').exists(), 'Clear config cache before smoke'
            subprocess.run([args.php, 'artisan', 'migrate', '--force'], cwd=ROOT, env=env,
                           check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
            with (Path(tmp) / 'http.log').open('w') as log:
                proc = subprocess.Popen([args.php, '-S', f'127.0.0.1:{port}', '-t', str(ROOT / 'public'),
                    str(ROOT / 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],
                    cwd=ROOT / 'public', env=env, stdout=log, stderr=log)
                base = f'http://127.0.0.1:{port}'
                guest = Client(base)
                for _ in range(100):
                    try:
                        if guest.request('/')[0] == 200:
                            break
                    except (OSError, urllib.error.URLError):
                        pass
                    time.sleep(.1)
                else:
                    raise AssertionError('HTTP server did not become ready')
                assert guest.request('/portal.css')[0] == 200
                dashboard_status = guest.request('/dashboard')[0]
                assert dashboard_status == 302, f'Guest dashboard status: {dashboard_status}'
                assert guest.request('/admin/leads')[0] == 302
                lead_data = dict(name='Smoke Participant', email='SMOKE@EXAMPLE.TEST', consent='1')
                assert guest.request('/daftar', lead_data)[0] == 419, 'Missing CSRF accepted'
                assert guest.post('/daftar', '/', **lead_data)[0] == 302
                con = sqlite3.connect(db)
                assert con.execute('SELECT count(*) FROM users').fetchone()[0] == 0
                lead = con.execute('SELECT id,payment_status,email FROM leads').fetchone()
                assert lead[1:] == ('pending', 'smoke@example.test')
                fixture = '''<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$u=new App\\Models\\User;
$u->name='Smoke Operator';$u->email='operator@example.test';
$u->password=getenv('SMOKE_ADMIN_PASSWORD');$u->role='admin';$u->active=true;$u->save();
'''
                subprocess.run([args.php], input=fixture, text=True, cwd=ROOT, env=env,
                               check=True, stdout=subprocess.DEVNULL, stderr=subprocess.PIPE)
                admin = Client(base)
                assert admin.post('/admin/login', '/admin/login', email='operator@example.test',
                                  password=env['SMOKE_ADMIN_PASSWORD'])[0] == 302
                assert admin.request('/admin/leads')[0] == 200
                path = f'/admin/leads/{lead[0]}'
                assert admin.post(path + '/provision', path)[0] == 422
                assert admin.post(path + '/payment', path, status='verified')[0] == 302
                assert con.execute('SELECT count(*) FROM users').fetchone()[0] == 1
                assert admin.post(path + '/provision', path)[0] == 302
                assert admin.post(path + '/provision', path)[0] == 302
                assert con.execute('SELECT count(*) FROM users').fetchone()[0] == 2
                assert con.execute('SELECT count(*) FROM audit_events').fetchone()[0] == 2
                private_url = 'https://example.test/private-class-room'
                assert admin.post('/admin/settings', '/admin/settings', zoom_url=private_url)[0] == 302
                assert private_url not in guest.request('/')[2]
                participant = Client(base)
                probe = fixture.split("$u=new App")[0] + "try { Illuminate\\Support\\Facades\\Mail::raw('Local transport probe', function($m){$m->to('probe@example.test')->subject('Probe');}); } catch (Throwable $e) { fwrite(STDERR, get_class($e).': '.$e->getMessage()); exit(1); }"
                result = subprocess.run([args.php], input=probe, text=True, cwd=ROOT, env=env, capture_output=True)
                assert result.returncode == 0, 'SMTP probe failed: ' + result.stderr
                messages.get(timeout=5)
                assert participant.post('/otp/request', '/login', email='smoke@example.test')[0] == 302
                from email import policy
                from email.parser import BytesParser
                message = BytesParser(policy=policy.default).parsebytes(messages.get(timeout=10))
                assert message['To'] == 'smoke@example.test'
                match = re.search(r'(?<!\d)(\d{6})(?!\d)', message.get_content())
                assert match, 'SMTP message lacked an OTP'
                code = match.group(1)
                row = con.execute('SELECT code_hash FROM otp_challenges').fetchone()
                assert row and row[0] != code and row[0].startswith('$2')
                assert code not in participant.request('/login')[2]
                # Verify session rotation through database session IDs, not encrypted cookie bytes.
                before = {r[0] for r in con.execute('SELECT id FROM sessions')}
                assert participant.post('/otp/verify', '/login', email='smoke@example.test', code=code)[0] == 302
                after = {r[0] for r in con.execute('SELECT id FROM sessions')}
                assert before - after and after - before, 'Login session did not rotate'
                status, headers, html = participant.request('/dashboard')
                assert status == 200 and private_url in html and code not in html
                assert 'no-store' in headers['Cache-Control']
                assert participant.request('/admin/leads')[0] == 403
                assert participant.post('/logout', '/dashboard')[0] == 302
                assert participant.request('/dashboard')[0] == 302
                participant.post('/otp/verify', '/login', email='smoke@example.test', code=code)
                assert participant.request('/dashboard')[0] == 302, 'OTP replay accepted'
                # Force resend eligibility in isolated fixture DB, never in live data.
                con.execute("UPDATE otp_challenges SET sent_at=datetime('now','-2 minutes')")
                con.commit()
                participant.post('/otp/request', '/login', email='smoke@example.test')
                message2 = BytesParser(policy=policy.default).parsebytes(messages.get(timeout=10))
                code2 = re.search(r'(?<!\d)(\d{6})(?!\d)', message2.get_content()).group(1)
                participant.post('/otp/verify', '/login', email='smoke@example.test', code=code2)
                assert participant.request('/dashboard')[0] == 200
                assert admin.post(path + '/access', path, active='0')[0] == 302
                assert participant.request('/dashboard')[0] == 403
                assert admin.post(path + '/access', path, active='1')[0] == 302
                assert participant.request('/dashboard')[0] == 403, 'Revoked session revived'
                assert admin.post('/logout', '/admin/leads')[0] == 302
                assert admin.request('/admin/leads')[0] == 302
                logfile = ROOT / 'storage/logs/laravel.log'
                if logfile.exists():
                    text = logfile.read_text(errors='replace')
                    assert code not in text and code2 not in text, 'OTP leaked into application log'
                con.close()
                print(json.dumps({'result':'passed','http_csrf':True,'registration_no_account':True,
                    'payment_separate_provision':True,'provision_idempotent':True,'local_smtp_messages':3,'otp_messages':2,
                    'otp_hashed':True,'session_rotation':True,'otp_replay_denied':True,
                    'private_links':True,'role_gate':True,'revocation':True,'logout':True,
                    'external_email_sent':False,'codes_logged':False}))
    finally:
        if proc:
            proc.terminate()
            proc.wait(timeout=10)
        smtp.shutdown()
        smtp.server_close()


if __name__ == '__main__':
    main()
