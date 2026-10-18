# JagoKita — MVP portal peserta

Portal informasi peserta berbahasa Indonesia, Laravel **13.37.0** (dikunci di `composer.lock`), Blade dan CSS lokal. Tidak membutuhkan Node/npm. Nama JagoKita mengikuti draft v2; nama repositori KitaJago tidak mengubah branding aplikasi.

## Alur yang tersedia

1. Pengunjung mengisi nama, email, persetujuan pemakaian data, dan WhatsApp opsional. Email dinormalisasi menjadi huruf kecil. Data disimpan sebagai **lead**, bukan akun. Pendaftaran ulang tidak menimpa data sebelumnya.
2. Halaman terima kasih membuka WhatsApp **nomor admin yang dikonfigurasi**. Tidak ada nomor bawaan, pengiriman pesan otomatis, harga, atau pengakuan pembayaran otomatis.
3. Admin memeriksa pembayaran di luar portal, lalu memilih status pembayaran. Aktor, waktu UTC, dan perubahan status dicatat dalam `audit_events`.
4. **Secara terpisah**, admin menekan “Siapkan / aktifkan akun peserta”. Hanya pembayaran terverifikasi yang dapat diproses. Pemanggilan ulang tidak menggandakan akun. Email admin/teacher atau akun lain tidak dapat diambil alih.
5. Peserta yang akunnya telah disiapkan, aktif, dan pembayarannya valid meminta OTP melalui email. Peserta tidak menggunakan password.
6. Dashboard privat berisi tautan grup WhatsApp, Zoom, dan materi eksternal opsional yang dipelihara admin. Saat ini satu program/angkatan bersama, bukan sistem multi-kelas.

Pembatalan pembayaran atau penonaktifan akses mencabut akses portal. Mengaktifkan kembali akun tidak menghidupkan sesi lama; peserta harus masuk ulang. Status pembayaran terverifikasi kembali tidak otomatis mengaktifkan akun.

## Prasyarat

- PHP **8.4 atau lebih baru yang kompatibel dengan lock**, Composer 2, SQLite. Pengujian Linux dilakukan dengan PHP 8.4.1; gunakan rilis patch PHP yang terpelihara untuk lingkungan operasional.
- Aktifkan ekstensi yang dibutuhkan Composer dan aplikasi: PDO/pdo_sqlite, sqlite3, openssl, mbstring, tokenizer, fileinfo, dom, xml, xmlwriter, ctype, filter, session, iconv. `composer check-platform-reqs` memeriksa kebutuhan paket.
- Ekstensi ZIP atau utilitas unzip membantu instalasi Composer. Koneksi internet diperlukan untuk mengunduh dependensi pertama kali.
- Python 3 hanya opsional untuk smoke test HTTP/SMTP; tidak diperlukan aplikasi.

## Instalasi Windows PowerShell

Jalankan dari folder sumber setelah ekstraksi ZIP. Pastikan `php -v` menunjuk PHP 8.4+, bukan PHP lama dari PATH/XAMPP.

```powershell
php -v
composer install
composer check-platform-reqs
if (!(Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
if (!(Test-Path database/database.sqlite)) { New-Item -ItemType File database/database.sqlite | Out-Null }
php artisan migrate
php artisan portal:admin
php artisan serve --host=127.0.0.1 --port=8000
```

Buka `http://127.0.0.1:8000`. Admin masuk di `/admin/login`.

Perintah pembuatan admin meminta nama, email, password minimal 12 karakter dan konfirmasinya secara interaktif. Input password disembunyikan. **Tidak ada admin, password bersama, atau akun contoh yang di-seed.** Jangan memasukkan password melalui argumen shell. Jika terminal tidak mendukung input tersembunyi, gunakan terminal interaktif biasa. Email yang telah digunakan ditolak, bukan ditimpa.

`key:generate` untuk instalasi **baru**; jangan rotasi kunci sembarangan pada instalasi berisi data/sesi. Perintah pembuatan SQLite di atas tidak menimpa file yang sudah ada. Jangan menjalankan `migrate:fresh` pada data operasional.

### Linux/macOS

```bash
composer install
composer check-platform-reqs
test -f .env || cp .env.example .env
php artisan key:generate
test -f database/database.sqlite || touch database/database.sqlite
php artisan migrate
php artisan portal:admin
php artisan serve --host=127.0.0.1 --port=8000
```

Langkah PowerShell/macOS disediakan sebagai panduan; eksekusi pengujian paket dilakukan di Linux, bukan Windows/macOS.

## Konfigurasi `.env`

### Identitas dan WhatsApp

- `APP_NAME=JagoKita`; `APP_URL` sesuai alamat lokal/operasional.
- `ADMIN_WHATSAPP=` diisi nomor admin asli dalam format internasional, **digit saja tanpa +, spasi, atau strip** (8–15 digit). Jangan gunakan nomor contoh orang lain. Jika kosong/tidak valid, tombol WhatsApp tidak ditampilkan dan halaman menjelaskan kontak belum dikonfigurasi.
- WhatsApp yang diisi peserta bersifat opsional; bukan nomor tujuan pembayaran.

### SMTP email OTP

Isi berdasarkan penyedia SMTP Anda, tidak ada kredensial bawaan:

```dotenv
PORTAL_SMTP_READY=false
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"
```

Set host, port, username, password dan pengirim yang diizinkan penyedia. Gunakan `smtp` dengan STARTTLS untuk layanan port 587, atau `smtps` untuk implicit TLS bila diwajibkan penyedia (sering port 465). Jangan menonaktifkan verifikasi sertifikat untuk mengatasi kegagalan koneksi. Jangan menambah `MAIL_URL=` kosong; hapus variabel itu bila memakai field terpisah. SMTP relay lokal dapat tidak memerlukan username/password; ini hanya untuk pengujian lokal.

Set `PORTAL_SMTP_READY=true` **setelah konfigurasi selesai**. Jalankan `php artisan config:clear` setiap selesai mengubah konfigurasi lokal. Pengiriman sinkron: tidak perlu worker queue.

Bila belum dikonfigurasi, halaman menampilkan layanan email belum siap. Hanya mailer `smtp` diizinkan untuk OTP; `log`, `array`, atau failover tidak dipakai agar kode tidak masuk log. Kegagalan transport mengonsumsi tantangan dan menghasilkan peringatan umum tanpa isi pesan/diagnostik SMTP. Balasan ke pemohon tetap generik agar tidak mengungkap status akun. Periksa konfigurasi/koneksi SMTP melalui kanal aman, bukan dengan mencatat OTP.

**Tidak ada kredensial SMTP nyata yang diberikan dan tidak ada email dikirim ke penerima nyata dalam pengujian.** Smoke test hanya menggunakan server penangkap SMTP loopback yang menyimpan pesan sementara dalam memori.

### Batas OTP dan sesi

| Variabel | Default | Fungsi |
|---|---:|---|
| `OTP_MINUTES` | 10 | Masa berlaku kode |
| `OTP_RESEND_SECONDS` | 60 | Jarak minimal kirim ulang |
| `OTP_MAX_ATTEMPTS` | 5 | Maksimal percobaan per tantangan |
| `OTP_SEND_EMAIL_LIMIT` | 5 | Permintaan per email per jam |
| `OTP_SEND_IP_LIMIT` | 20 | Permintaan per IP per jam |
| `OTP_VERIFY_EMAIL_LIMIT` | 30 | Verifikasi per email per 5 menit |
| `OTP_VERIFY_IP_LIMIT` | 30 | Verifikasi per IP per 5 menit |

Ada throttle tambahan per rute: kirim 30/menit, verifikasi 60/menit, daftar 10/menit, login admin 20/menit per IP. Login admin juga dibatasi 5 percobaan per pasangan email/IP dalam 5 menit. Permintaan kirim selama cooldown tetap menghitung batas permintaan.

Kode acak kriptografis enam digit disimpan hanya sebagai hash, diganti saat kirim ulang, dan dikonsumsi atomik sekali pakai. Lock cache bersama dan compare-and-set database mencegah replay. Kode tidak dimasukkan ke HTML, respons API, old input, atau log aplikasi. Pesan untuk email tidak dikenal, belum aktif, teacher, atau belum berhak sama; tidak ada pembuatan akun dari OTP.

Sesi diregenerasi saat login, dihancurkan saat logout, dan hak peserta diperiksa pada setiap permintaan dashboard termasuk versi pencabutan akses. Sesi dan cache memakai database secara default. Jangan mengganti cache menjadi `array` untuk aplikasi berjalan; pada multi-instance gunakan database/cache bersama dan lakukan pengujian konkurensi tambahan. Default sesi kedaluwarsa setelah 120 menit tidak aktif.

## Operasional admin

- `/admin/leads`: cari nama/email, filter pembayaran, pagination.
- Detail peserta: konfirmasi pembayaran → penyiapan akun → aktif/nonaktif → riwayat tindakan. Label status internal: `pending`, `verified`, `rejected`.
- `/admin/settings`: simpan/kosongkan tiga tautan. Hanya URL http/https; JavaScript/data URL ditolak dan output Blade di-escape. Gunakan HTTPS untuk tautan nyata dan pastikan tujuannya tepercaya.
- Akun siap diberitahukan secara manual lewat kanal yang disepakati; MVP tidak mengirim email aktivasi otomatis.
- Tidak ada UI koreksi email, hapus data, reset password admin, atau manajemen teacher. Prosedur koreksi/retensi/pemulihan admin harus ditetapkan sebelum penggunaan operasional. Jangan mengubah email peserta lewat SQL tanpa meninjau relasi pembayaran dan pencabutan sesi.

## Pengujian

```powershell
php artisan test
php vendor/bin/pint --test
composer validate --strict
composer audit
# Opsional: Python 3 stdlib; hanya localhost, tanpa SMTP eksternal
python tests/http_smtp_smoke.py --php php
```

Feature test memakai SQLite in-memory dan tidak mengirim email nyata. Meliputi pendaftaran tanpa akun, batas role, pembayaran terpisah, idempotensi, collision email, OTP salah/kedaluwarsa/replay/limit, pencabutan sesi, validasi tautan, login/logout admin, pembuatan admin, dan tidak adanya akun seed. Feature test Laravel melewati CSRF, sehingga **smoke HTTP terpisah diperlukan**.

Smoke membuat database sementara, server HTTP loopback, dan SMTP sink loopback. Menguji token CSRF nyata (tanpa token ditolak 419), alur admin/peserta, hash OTP, regenerasi sesi, replay, akses privat, pencabutan dan logout. Mengirim tiga pesan lokal (satu probe + dua OTP); tidak mencetak kode/password atau menyimpan tangkapan email. Database/server dibersihkan setelah selesai. Jangan menjalankan saat konfigurasi tercache; `php artisan config:clear` dahulu. Jangan gunakan server uji untuk jaringan publik.

## Batas MVP dan sebelum penggunaan publik

- **Bukan klaim siap produksi.** Belum ada deployment, pengujian beban, audit penetrasi independen, konfigurasi backup/restore, pemantauan mail delivery, atau pengujian TLS SMTP penyedia nyata.
- Login admin berbasis password adalah **implementasi sementara**; metode autentikasi admin final masih perlu persetujuan, termasuk MFA dan pemulihan akun.
- Peran **teacher diakui** pada model data tetapi tidak memiliki izin admin/peserta atau alur login/modul tersendiri. Kewenangannya belum dikonfirmasi; jangan menganggap modul teacher sudah diimplementasikan.
- Tidak ada payment gateway, checkout, LMS, kuis, video hosting, DRM, maupun materi tertanam. Tautan eksternal dapat disalin penerima; portal tidak mencegah berbagi tautan.
- Konfigurasi publik memerlukan HTTPS, document root `public/`, `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, izin file minimal, rahasia di luar Git, backup terenkripsi, cache bersama, kebijakan data, validasi proxy tepercaya dan kebijakan pembatasan akses. Jangan gunakan `artisan serve` sebagai server produksi.
- Rate limiter mengurangi penyalahgunaan tetapi bukan pengganti perlindungan DDoS, pengawasan spam, atau audit konkurensi distribusi. Respons generik tidak menjanjikan ketahanan terhadap semua analisis timing.

## Isi paket

Sumber, migration, tests, CSS lokal, `composer.json`, `composer.lock`, `.env.example`, dan README. Tidak menyertakan `.env`, `vendor`, `node_modules`, `.git`, database SQLite berisi data, log, cache runtime, sesi, kredensial, atau email tangkapan. Jalankan `composer install`, bukan menyalin vendor lama. Jangan commit data operasional.
