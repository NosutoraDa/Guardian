<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="view-transition" content="same-origin">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Guardian - @yield('title')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css?v={{ filemtime(public_path('css/style.css')) }}">
    <link rel="stylesheet" href="/css/guardian-prototype.css?v={{ filemtime(public_path('css/guardian-prototype.css')) }}">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-robot="{{ $__env->yieldContent('robot-mode', 'idle') }}">
<header class="top-navbar">
    <button class="hamburger-btn" id="hamburgerBtn" type="button" aria-label="Buka menu" aria-controls="mobileMenu" aria-expanded="false"><span class="bar"></span><span class="bar"></span><span class="bar"></span></button>
    <a href="{{ route('home') }}" class="brand-logo" aria-label="Guardian beranda">
        <svg class="brand-robot-icon" viewBox="0 0 36 36" fill="none" aria-hidden="true"><rect x="5.5" y="3.5" width="6.5" height="9" rx="3.25" fill="#d8b4fe"/><rect x="24" y="3.5" width="6.5" height="9" rx="3.25" fill="#d8b4fe"/><rect x="3" y="7" width="30" height="26" rx="9" fill="#fff" stroke="#e9d5ff" stroke-width="1.5"/><rect x="6" y="10" width="24" height="20" rx="6" fill="#2e0d5a"/><rect x="11" y="15" width="4.5" height="7.5" rx="2.25" fill="#c084fc"/><rect x="20.5" y="15" width="4.5" height="7.5" rx="2.25" fill="#c084fc"/><path d="M13.5 24.5Q18 27.5 22.5 24.5" stroke="#c084fc" stroke-width="2" stroke-linecap="round"/></svg>
        <span>Guardian</span>
    </a>
    <div class="nav-spacer" aria-hidden="true"></div>
</header>

<nav class="mobile-menu-overlay" id="mobileMenu" aria-label="Navigasi utama" hidden>
    <ul class="mobile-menu-links">
        <li><a href="{{ route('home') }}" class="{{ $__env->yieldContent('active') === 'home' ? 'active' : '' }}"><span><i data-lucide="house" class="w-5 h-5 text-purple-600"></i> Beranda</span><span class="menu-badge">Utama</span></a></li>
        <li><a href="{{ route('links') }}" class="{{ $__env->yieldContent('active') === 'links' ? 'active' : '' }}"><span><i data-lucide="link-2" class="w-5 h-5 text-purple-600"></i> Cek Link</span><span class="menu-badge">URL Safety</span></a></li>
        <li><a href="{{ route('files') }}" class="{{ $__env->yieldContent('active') === 'files' ? 'active' : '' }}"><span><i data-lucide="file-text" class="w-5 h-5 text-purple-600"></i> Cek File</span><span class="menu-badge">Malware Safety</span></a></li>
        <li><a href="{{ route('tanya') }}" class="{{ $__env->yieldContent('active') === 'tanya' ? 'active' : '' }}"><span><i data-lucide="message-square" class="w-5 h-5 text-purple-600"></i> Cek Pesan AI</span><span class="menu-badge">Deteksi Pesan</span></a></li>
    </ul>
    <details class="menu-disclaimer">
        <summary aria-label="Informasi keamanan dan privasi"><span class="menu-help-icon">?</span><span>Informasi keamanan &amp; privasi</span></summary>
        <p>Guardian tidak menjamin keamanan 100%. Link dan file yang kamu setujui dikirim ke VirusTotal dan dapat ditambahkan ke dataset komunitas. Pertanyaan chatbot dikirim ke Gemini. Jangan kirim data pribadi atau rahasia.</p>
    </details>
</nav>

<main class="stage-container">
    @if(in_array($__env->yieldContent('active'), ['links', 'files'], true))
        <x-guardian.robot
            :message="$__env->yieldContent('robot-message')"
            :title="request()->routeIs('files') ? 'Belum Ada File Yang Dipindai' : 'Belum Ada Link Yang Dipindai'"
        />
    @endif

    @yield('content')
</main>

<div id="consent-modal" class="consent-backdrop hidden" aria-hidden="true">
    <section class="consent-dialog" role="dialog" aria-modal="true" aria-labelledby="consent-title" aria-describedby="consent-description consent-scan-warning">
        <button id="consent-close" class="consent-close" type="button" aria-label="Tutup">×</button>
        <span class="consent-icon"><i data-lucide="shield-check" class="w-6 h-6"></i></span>
        <p class="consent-kicker">Persetujuan pemeriksaan eksternal</p>
        <h2 id="consent-title">Konfirmasi sebelum memindai</h2>
        <p id="consent-description" class="consent-copy"></p>
        <section class="consent-scan-steps" aria-label="Cara kerja pemindaian">
            <strong>Cara kerja</strong>
            <ol>
                <li>Guardian mengirim tautan atau file yang kamu pilih ke VirusTotal.</li>
                <li>VirusTotal memeriksanya dengan berbagai vendor keamanan dan mengembalikan hasil ke Guardian.</li>
                <li>Pemindaian bisa memakan waktu yang lama. Guardian akan memperbarui status otomatis saat analisis berjalan.</li>
            </ol>
        </section>
        <aside id="consent-scan-warning" class="consent-scan-warning" role="note">
            <strong>Peringatan: pemindaian ini tidak privat.</strong>
            <p>Tautan dan file yang dikirim melalui API publik VirusTotal dapat ditambahkan ke dataset dan dibagikan kepada komunitas. Jangan kirim data pribadi, file rahasia, tautan privat, token, atau informasi sensitif.</p>
            <p>Hasil bisa keliru atau belum mendeteksi ancaman. Tidak ada deteksi bukan jaminan aman.</p>
        </aside>
        <label class="consent-choice"><input id="consent-check" type="checkbox"><span id="consent-label"></span></label>
        <div class="consent-actions">
            <button id="consent-cancel" type="button" class="consent-cancel">Batal</button>
            <button id="consent-accept" type="button" class="consent-accept" disabled>Setuju &amp; langsung pindai</button>
        </div>
    </section>
</div>

<canvas id="waveCanvas" aria-hidden="true"></canvas>
<script src="{{ asset('js/robot.js') }}?v={{ filemtime(public_path('js/robot.js')) }}"></script>
<script src="{{ asset('js/guardian-ui.js') }}?v={{ filemtime(public_path('js/guardian-ui.js')) }}"></script>
@stack('scripts')
</body>
</html>
