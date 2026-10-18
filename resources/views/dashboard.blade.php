@extends('layout')
@section('content')
<p class="eyebrow">AREA PESERTA</p><h1>Selamat datang, {{ auth()->user()->name }}.</h1><p>Informasi dan tautan program Anda tersedia di sini. Jangan membagikan tautan privat.</p><div class="grid">
@foreach(['whatsapp_url'=>'Grup WhatsApp','zoom_url'=>'Pertemuan Zoom','material_url'=>'Materi eksternal'] as $key=>$label)
<section class="card"><h2>{{ $label }}</h2>
@if($settings?->$key)
<a class="button" href="{{ $settings->$key }}" target="_blank" rel="noopener noreferrer">Buka {{ $label }} ↗</a>
@else
<p class="muted">Belum tersedia. Silakan menunggu informasi admin.</p>
@endif
</section>
@endforeach
</div>
@endsection
