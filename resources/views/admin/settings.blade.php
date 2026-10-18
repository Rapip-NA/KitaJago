@extends('layout')
@section('content')
<a href="/admin/leads">← Daftar peserta</a><h1>Tautan program</h1><p>Satu program / angkatan awal. Tautan hanya ditampilkan kepada peserta yang berhak. Tautan eksternal tetap dapat dibagikan oleh penerima.</p><section class="card"><form method="post" action="/admin/settings">@csrf
@foreach(['whatsapp_url'=>'Grup WhatsApp','zoom_url'=>'Pertemuan Zoom','material_url'=>'Materi eksternal (opsional)'] as $key=>$label)
<label>{{ $label }}<input type="url" name="{{ $key }}" value="{{ old($key,$settings?->$key) }}" maxlength="2048" placeholder="https://..."></label>
@endforeach
<p class="muted">Hanya http/https. Kosongkan untuk menyembunyikan tautan yang belum tersedia.</p><button>Simpan tautan</button></form></section>
@endsection
