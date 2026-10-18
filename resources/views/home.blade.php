@extends('layout')
@section('content')
<div class="hero"><p class="eyebrow">PORTAL PESERTA</p><h1>Mulai langkah belajar<br>bersama {{ config('app.name') }}.</h1><p>Daftarkan minat Anda, koordinasikan pembayaran dengan admin, lalu akses informasi kelas dalam satu tempat.</p><a class="button" href="#daftar">Daftarkan minat</a></div>
<div class="steps"><article><b>01 · Daftar</b><p>Isi data untuk ditindaklanjuti admin.</p></article><article><b>02 · Koordinasi</b><p>Pembayaran dan verifikasi melalui admin WhatsApp.</p></article><article><b>03 · Akses portal</b><p>Setelah akun disiapkan, masuk menggunakan OTP email.</p></article></div>
<section class="card narrow" id="daftar"><p class="eyebrow">PENDAFTARAN MINAT</p><h2>Kenalan terlebih dahulu</h2><p>Pendaftaran ini belum membuat akun dan bukan bukti pembayaran.</p><form method="post" action="/daftar">@csrf
<label>Nama lengkap<input name="name" required maxlength="120" autocomplete="name" value="{{ old('name') }}"></label>
<label>Email peserta<input name="email" type="email" required maxlength="254" autocomplete="email" value="{{ old('email') }}"></label>
<label>Nomor WhatsApp <small>(opsional)</small><input name="whatsapp" type="tel" maxlength="32" autocomplete="tel" value="{{ old('whatsapp') }}"></label>
<label class="check"><input type="checkbox" name="consent" value="1" required> Saya setuju data nama, email, dan WhatsApp (bila diisi) digunakan admin untuk tindak lanjut pendaftaran dan akses portal.</label><button>Kirim pendaftaran</button></form></section>
@endsection
