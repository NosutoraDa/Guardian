@extends('layouts.app')

@section('title', 'Cek Link')
@section('active', 'links')
@section('robot-mode', 'link')
@section('robot-message', 'Guardian siap menganalisis keamanan tautan Anda.')

@section('content')
<section class="glass-card p-6 sm:p-8 tool-prototype-card">
    <div class="space-y-5">
        <div class="pb-3 border-b border-purple-100">
            <h2 class="text-xl font-extrabold text-purple-950 font-['Space_Grotesk']">Pemindai Keamanan Link</h2>
            <p class="text-xs text-gray-500">Periksa apakah suatu tautan aman atau berpotensi bahaya &amp; penipuan.</p>
        </div>
        <div class="space-y-2">
            <label for="link-input" class="text-xs font-bold text-gray-700">Masukkan Alamat Web (URL):</label>
            <div class="flex gap-2 link-form-row">
                <input type="url" id="link-input" maxlength="2048" placeholder="Contoh: https://promo-bca-kupon.xyz" class="flex-1 bg-white/80 border border-purple-200 rounded-xl px-4 py-2.5 text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-purple-500" autocomplete="url" required>
                <button type="button" id="check-btn" class="prototype-primary">🔍 Pindai Link</button>
            </div>
        </div>
        <div class="link-demo-row">
            <span>Latihan keamanan</span>
            <button type="button" id="simulate-phishing" class="sample-danger">Lihat simulasi peringatan phishing</button>
            <small>Contoh latihan lokal; tidak membuka atau mengirim tautan.</small>
        </div>
        <div class="link-history-wrap">
            <div class="flex items-center justify-between"><div><h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Riwayat Analisis Link</h3><p class="mt-0.5 text-[10px] text-gray-400">Tersimpan di browser ini</p></div><button id="clear-link-history" type="button" class="text-xs text-gray-400 hover:text-purple-700">Bersihkan</button></div>
            <p id="link-history-empty" class="text-xs text-gray-400 mt-2">Belum ada tautan yang diperiksa.</p>
            <div id="linkNodeList" class="space-y-2.5 max-h-44 overflow-y-auto custom-scroll pr-1 mt-2" aria-live="polite"></div>
        </div>
        <p class="text-[11px] text-gray-500">VirusTotal membandingkan tautan dengan berbagai pemindai. Hasil tanpa deteksi bukan jaminan keamanan.</p>
    </div>
</section>
<div id="toastContainer" aria-live="polite"></div>
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('link-input');
    const button = document.getElementById('check-btn');
    const history = document.getElementById('linkNodeList');
    const empty = document.getElementById('link-history-empty');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let approvedUrl = null;
    const reportPageTemplate = @json(route('links.report', ['urlId' => '__URL_ID__']));
    const historyStorageKey = 'guardian:link-history:v1';
    function readSavedHistory() {
        try {
            const records = JSON.parse(localStorage.getItem(historyStorageKey) || '[]');
            return Array.isArray(records) ? records.filter(record => record && typeof record.url === 'string' && record.report && typeof record.report.message === 'string').slice(0, 25) : [];
        } catch (_) { return []; }
    }
    function saveHistory(url, report) {
        try {
            const saved = readSavedHistory();
            const id = report.url_id || `${url}|${report.analysis_id || Date.now()}`;
            const record = {
                id, url,
                report: {
                    status: report.status, verdict: report.verdict ?? null, message: report.message,
                    stats: report.stats ?? null, analysis_id: report.analysis_id ?? null, url_id: report.url_id ?? null,
                },
                savedAt: Date.now(),
            };
            localStorage.setItem(historyStorageKey, JSON.stringify([record, ...saved.filter(item => item.id !== id)].slice(0, 25)));
        } catch (_) { /* Browser storage can be disabled or full. */ }
    }
    function toast(message) {
        const node = document.createElement('div'); node.className = 'toast-msg'; node.textContent = message;
        document.getElementById('toastContainer').appendChild(node); setTimeout(() => node.remove(), 3200);
    }
    function addEntry(url, report, demo = false) {
        empty.hidden = true;
        const danger = report.verdict === 'malicious';
        const item = document.createElement('article'); item.className = `link-history-item ${danger ? 'is-danger' : 'is-neutral'}`;
        const info = document.createElement('div'); info.className = 'link-history-info';
        const heading = document.createElement('strong'); heading.textContent = url;
        const detail = document.createElement('p'); detail.textContent = `${report.message}${demo ? ' · Simulasi lokal, tidak dikirim ke VirusTotal' : ''}`;
        const badge = document.createElement('span'); badge.className = `link-history-badge ${demo ? 'is-demo' : danger ? 'is-risk' : ''}`; badge.textContent = demo ? 'CONTOH' : danger ? 'WASPADA' : 'HASIL';
        info.append(heading, detail); item.append(info, badge);
        if (report.url_id && !demo) { const a = document.createElement('a'); a.href = reportPageTemplate.replace('__URL_ID__', encodeURIComponent(report.url_id)); a.textContent = 'Laporan'; item.append(a); }
        history.prepend(item);
        return item;
    }
    function robotStateFor(report) {
        if (report.verdict === 'malicious' || report.verdict === 'suspicious') return 'danger';
        if (report.verdict === 'no_detections') return 'safe';
        if (report.status === 'pending') return 'scanning';
        return 'idle';
    }
    input.addEventListener('input', () => { approvedUrl = null; });
    document.getElementById('simulate-phishing').addEventListener('click', () => {
        const sampleUrl = 'https://verifikasi-hadiah.example';
        const sampleReport = {
            status: 'complete',
            verdict: 'malicious',
            message: 'SIMULASI: pola domain hadiah dan verifikasi akun sering dipakai untuk mencuri data. Ini contoh edukasi, bukan hasil VirusTotal.',
        };
        addEntry(sampleUrl, sampleReport, true);
        Robot.setState('danger', sampleReport.message);
    });
    document.getElementById('clear-link-history').addEventListener('click', () => {
        history.replaceChildren(); empty.hidden = false;
        try { localStorage.removeItem(historyStorageKey); } catch (_) { }
    });
    async function request(url, body) {
        const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) });
        const data = await response.json(); if (!response.ok) throw new Error(data.error || 'Pemeriksaan VirusTotal gagal.'); return data;
    }
    const savedRecords = readSavedHistory();
    savedRecords.slice().reverse().forEach(record => {
        addEntry(record.url, record.report);
        if (record.report.status === 'pending' && record.report.analysis_id && record.report.url_id) {
            addRefreshControl(record.url, { analysis_id: record.report.analysis_id, url_id: record.report.url_id });
        }
    });
    button.addEventListener('click', async () => {
        if (button.disabled) return;
        Robot.react();
        if (!input.reportValidity()) { Robot.setState('idle', 'Masukkan tautan HTTP atau HTTPS yang ingin diperiksa.'); return; }
        const url = input.value.trim();
        if (approvedUrl !== url) {
            button.disabled = true;
            const accepted = await window.GuardianConsent.request({
                title: 'Pindai tautan dengan VirusTotal?',
                description: `Tautan ${url} akan dikirim ke VirusTotal untuk diperiksa. Tautan yang dikirim dapat disimpan atau dibagikan oleh layanan tersebut. Jangan gunakan tautan pribadi, rahasia, atau sekali pakai.`,
                label: 'Saya setuju mengirim tautan ini ke VirusTotal dan memahami tautan dapat disimpan oleh layanan tersebut.',
            });
            button.disabled = false;
            if (!accepted) { Robot.setState('idle', 'Pemindaian dibatalkan. Tautan belum dikirim.'); return; }
            approvedUrl = url;
        }
        button.disabled = true; Robot.setState('scanning', 'Tautan sedang diperiksa oleh VirusTotal...');
        try {
            const data = await request('{{ route('api.links') }}', { text: url, share_url_with_virustotal: true });
            const report = data.virustotal; saveHistory(url, report); addEntry(url, report);
            Robot.setState(robotStateFor(report), report.message);
            if (report.status === 'pending' && report.analysis_id && report.url_id) {
                addRefreshControl(url, { analysis_id: report.analysis_id, url_id: report.url_id });
            }
        } catch (error) { toast(error.message || 'Pemeriksaan VirusTotal gagal.'); Robot.setState('idle', error.message); }
        finally { button.disabled = false; }
    });
    function addRefreshControl(url, initialPending) {
        let rowPending = initialPending;
        const row = document.createElement('button'); row.type = 'button'; row.className = 'prototype-secondary mt-2'; row.textContent = 'Perbarui status pemindaian';
        row.addEventListener('click', async () => {
            if (!rowPending || row.disabled) return; row.disabled = true;
            try {
                const data = await request('{{ route('api.links.analysis') }}', rowPending);
                saveHistory(url, data);
                addEntry(url, data);
                rowPending = data.status === 'pending' ? rowPending : null;
                Robot.setState(robotStateFor(data), data.message);
                if (!rowPending) row.remove();
            }
            catch (error) { toast(error.message || 'Status pemindaian tidak dapat diperbarui.'); }
            finally { row.disabled = false; }
        });
        history.prepend(row);
    }
})();
</script>
@endpush
