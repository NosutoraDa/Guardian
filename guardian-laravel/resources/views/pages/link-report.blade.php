@extends('layouts.app')

@section('title', 'Laporan Keamanan Link')
@section('active', 'links')
@section('robot-mode', 'link-report')
@section('robot-message', 'Guardian menampilkan laporan analisis VirusTotal.')

@section('content')
<section class="glass-card p-6 sm:p-8 tool-prototype-card">
    <div class="flex flex-wrap items-start justify-between gap-4 border-b border-purple-100 pb-4">
        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-purple-600">Laporan VirusTotal</p>
            <h1 class="mt-1 text-xl font-extrabold text-purple-950">Detail analisis tautan</h1>
            <p class="mt-1 break-all text-sm text-gray-600">{{ $report['url'] ?? 'ID laporan: '.$urlId }}</p>
        </div>
        <a href="{{ route('links') }}" class="prototype-secondary">Kembali ke pemindai</a>
    </div>

    @if(isset($report['error']))
        <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="status">{{ $report['error'] }}</div>
    @else
        @php
            $verdict = $report['verdict'] ?? 'no_detections';
            $stats = $report['stats'] ?? [];
            $categoryLabels = ['malicious' => 'Berbahaya', 'suspicious' => 'Mencurigakan', 'harmless' => 'Aman menurut vendor', 'undetected' => 'Tidak terdeteksi', 'timeout' => 'Waktu habis', 'failure' => 'Gagal', 'type-unsupported' => 'Tipe tidak didukung', 'confirmed-timeout' => 'Waktu habis terkonfirmasi'];
        @endphp
        <div class="mt-5 rounded-xl border p-4 {{ $verdict === 'malicious' ? 'border-red-200 bg-red-50 text-red-900' : ($verdict === 'suspicious' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-emerald-200 bg-emerald-50 text-emerald-900') }}">
            <strong>{{ $verdict === 'malicious' ? 'Indikasi berbahaya ditemukan' : ($verdict === 'suspicious' ? 'Ada indikasi mencurigakan' : 'Tidak ada deteksi berbahaya saat ini') }}</strong>
            <p class="mt-1 text-sm">Hasil bersih bukan jaminan bahwa tautan aman. Periksa tujuan dan konteks tautan sebelum membukanya.</p>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach(['malicious', 'suspicious', 'harmless', 'undetected'] as $key)
                <div class="rounded-xl border border-purple-100 bg-white/70 p-4"><div class="text-xs text-gray-500">{{ $categoryLabels[$key] }}</div><div class="mt-1 text-2xl font-bold text-purple-950">{{ (int) ($stats[$key] ?? 0) }}</div></div>
            @endforeach
        </div>

        <div class="mt-5 grid gap-3 text-sm text-gray-600 sm:grid-cols-2">
            <p><strong class="text-gray-800">Reputasi komunitas:</strong> {{ $report['reputation'] ?? 'Tidak tersedia' }}</p>
            <p><strong class="text-gray-800">Analisis terakhir:</strong> {{ !empty($report['last_analysis_date']) ? \Illuminate\Support\Carbon::createFromTimestamp($report['last_analysis_date'])->toDayDateTimeString() : 'Tidak tersedia' }}</p>
            <p class="sm:col-span-2"><strong class="text-gray-800">Kategori:</strong> {{ count($report['categories'] ?? []) ? implode(', ', $report['categories']) : 'Belum dikategorikan' }}</p>
        </div>

        <div class="mt-7">
            <div class="flex items-center justify-between border-b border-purple-100 pb-3"><h2 class="font-bold text-purple-950">Hasil per vendor keamanan</h2><span class="text-xs text-gray-500">{{ count($report['vendors'] ?? []) }} vendor</span></div>
            @if(empty($report['vendors']))
                <p class="py-5 text-sm text-gray-500">Belum tersedia hasil per vendor.</p>
            @else
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-white/95 text-xs uppercase text-gray-500"><tr><th class="px-3 py-2">Vendor</th><th class="px-3 py-2">Hasil</th><th class="px-3 py-2">Keterangan</th></tr></thead>
                        <tbody>
                            @foreach($report['vendors'] as $vendor)
                                <tr class="border-t border-purple-50"><td class="px-3 py-2 font-semibold text-gray-800">{{ $vendor['name'] }}</td><td class="px-3 py-2">{{ $categoryLabels[$vendor['category']] ?? $vendor['category'] }}</td><td class="px-3 py-2 text-gray-600">{{ $vendor['result'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</section>
@endsection
