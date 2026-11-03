<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Guardian - @yield('title')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
</head>
<body data-robot="{{ $__env->yieldContent('robot-mode', 'idle') }}">
<div class="background-scene" aria-hidden="true"></div>
<div id="canvas-container" aria-hidden="true"></div>

<header class="topbar">
    <div class="topbar-inner glass-panel">
        <a class="brand" href="{{ route('links') }}">
            <div class="brand-mark"><i class="fas fa-shield-halved"></i></div>
            <div>
                <h1>Si Guardian</h1>
                <p>Asisten keamanan digital</p>
            </div>
        </a>
        <div class="status-badge" role="status" aria-live="polite">
            <span class="status-ping" aria-hidden="true"></span>
            <span id="status-text">Mode Siaga</span>
        </div>
        <div class="header-tools">
            <button id="toggle-radial-btn" class="hud-button" type="button" aria-label="Toggle Radial Menu" aria-controls="radial-dial-container" aria-expanded="true">
                <i class="fas fa-compass" aria-hidden="true"></i><span class="button-label">Radial Menu</span>
            </button>
            <button id="toggle-quick-tips-btn" class="hud-icon-button" type="button" aria-label="Bantuan Quick Tips" aria-controls="quick-tips-panel" aria-expanded="false">
                <i class="far fa-circle-question" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</header>

<main class="layout">
    <section id="quick-tips-panel" class="quick-tips glass-panel" hidden aria-labelledby="quick-tips-title">
        <div class="quick-tips-heading">
            <h2 id="quick-tips-title"><i class="fas fa-lightbulb" aria-hidden="true"></i> Panduan Deteksi Siber</h2>
            <button id="close-quick-tips-btn" class="hud-icon-button" type="button" aria-label="Tutup Quick Tips">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
        <ul>
            <li><i class="fas fa-check" aria-hidden="true"></i><span><strong>Links:</strong> Periksa satu tautan yang boleh dibagikan dengan VirusTotal. Tautan yang dikirim dapat masuk ke basis data VirusTotal.</span></li>
            <li><i class="fas fa-check" aria-hidden="true"></i><span><strong>Files:</strong> File hingga 10 MB dikirim ke OPSWAT untuk pemindaian. Jangan unggah dokumen sensitif.</span></li>
            <li><i class="fas fa-check" aria-hidden="true"></i><span><strong>Chatbot:</strong> Pertanyaan diproses oleh Gemini. Pada layanan gratis, konten dapat digunakan untuk meningkatkan produk Google; jangan sertakan data pribadi, OTP, PIN, atau kata sandi. Hanya untuk pengguna berusia 18 tahun ke atas.</span></li>
        </ul>
    </section>

    <x-guardian.robot :message="$__env->yieldContent('robot-message')" />

    <section class="content">
        @yield('content')
    </section>
</main>

<footer class="footer">
    Guardian tidak menjamin keamanan 100%. Link yang kamu setujui dikirim ke VirusTotal, file yang kamu setujui dikirim ke OPSWAT, dan pertanyaan chatbot dikirim ke Gemini. Jangan kirim data pribadi atau rahasia.
</footer>

<script src="{{ asset('js/robot.js') }}?v={{ filemtime(public_path('js/robot.js')) }}"></script>
@stack('scripts')
</body>
</html>
