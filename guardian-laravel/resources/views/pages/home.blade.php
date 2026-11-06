@extends('layouts.app')

@section('title', 'Guardian Safety Checker')
@section('active', 'home')

@section('content')
<section class="glass-card p-6 sm:p-8 home-prototype-card">
    <div class="flex flex-col items-center text-center space-y-6">
        <button type="button" class="hero-robot-glow my-2 border-0 bg-transparent" id="heroRobotContainer" title="Klik untuk reaksi Guardian" aria-label="Animasi Guardian">
            <svg id="heroRobotSvg" class="w-28 h-28 sm:w-36 sm:h-36 transition-transform duration-300 hover:scale-105 active:scale-95" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="5.5" y="3.5" width="6.5" height="9" rx="3.25" fill="#c084fc"/><rect x="24" y="3.5" width="6.5" height="9" rx="3.25" fill="#c084fc"/>
                <rect x="3" y="7" width="30" height="26" rx="9" fill="#fff" stroke="#e9d5ff" stroke-width="1.8"/><rect x="6" y="10" width="24" height="20" rx="6" fill="#1e0542"/>
                <rect id="heroEyeLeft" x="11" y="14.5" width="4.5" height="8.5" rx="2.25" fill="#d8b4fe"/><rect id="heroEyeRight" x="20.5" y="14.5" width="4.5" height="8.5" rx="2.25" fill="#d8b4fe"/>
                <path id="heroSmile" d="M 12.5 24 Q 18 28 23.5 24" stroke="#c084fc" stroke-width="2.2" stroke-linecap="round" fill="none"/>
            </svg>
        </button>
        <div class="space-y-2 max-w-lg">
            <span class="inline-block px-3.5 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-700 tracking-wider uppercase border border-purple-200">Platform Keamanan Pintar</span>
            <h1 class="text-3xl sm:text-4xl font-extrabold text-purple-950 font-['Space_Grotesk'] leading-tight">Guardian Safety Checker</h1>
            <p class="text-gray-600 text-sm sm:text-base leading-relaxed">Periksa tautan web, berkas berkategori mencurigakan, dan analisis isi pesan secara mudah untuk memastikan keamanan digital Anda.</p>
        </div>
        <div id="robotStatusSpeech" class="bg-purple-50/90 border border-purple-200 text-purple-900 px-4 py-3 rounded-2xl text-xs sm:text-sm font-medium shadow-sm max-w-md">🤖 “Halo! Saya Guardian. Pilih salah satu pemeriksaan di bawah ini untuk memulai.”</div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 w-full pt-2 text-left">
            <a href="{{ route('links') }}" class="home-shortcut group"><span class="home-shortcut-icon"><i data-lucide="link-2" class="w-5 h-5"></i></span><strong>Cek Link</strong><span>Uji alamat URL untuk mendeteksi risiko phishing &amp; domain mencurigakan.</span></a>
            <a href="{{ route('files') }}" class="home-shortcut group"><span class="home-shortcut-icon"><i data-lucide="file-text" class="w-5 h-5"></i></span><strong>Cek File</strong><span>Pindai berkas APK, executable, atau dokumen dari potensi bahaya.</span></a>
            <a href="{{ route('tanya') }}" class="home-shortcut group"><span class="home-shortcut-icon"><i data-lucide="message-square-more" class="w-5 h-5"></i></span><strong>Cek Pesan AI</strong><span>Evaluasi teks SMS, WhatsApp, atau penawaran yang mencurigakan.</span></a>
        </div>
    </div>
</section>
@endsection
