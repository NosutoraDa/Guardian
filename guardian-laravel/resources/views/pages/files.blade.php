@extends('layouts.app')

@section('title', 'Cek File')
@section('active', 'files')
@section('robot-mode', 'file')
@section('robot-message', 'Pilih atau unggah berkas untuk dipindai oleh Guardian.')

@section('content')
<section class="glass-card p-6 sm:p-8 file-tool-panel">
    <div class="flex items-center justify-between pb-3 border-b border-purple-100">
        <div>
            <h2 class="text-xl font-extrabold text-purple-950 font-['Space_Grotesk']">Pemindai Keamanan File</h2>
            <p class="text-xs text-gray-500">Evaluasi ekstensi &amp; potensi malware pada berkas aplikasi maupun dokumen.</p>
        </div>
    </div>
    <p class="text-xs text-gray-500 mt-4">File dikirim ke VirusTotal untuk dipindai. File yang dikirim melalui API publik dapat ditambahkan ke dataset dan dibagikan kepada komunitas VirusTotal. Jangan unggah dokumen pribadi, rahasia, atau file milik orang lain.</p>

    <form id="file-form" class="mt-4">
        <label for="file-input" class="text-xs font-bold text-gray-700">Pilih file (maksimal 10 MB)</label>
        <label id="file-drop" class="neon-border-pulse rounded-2xl p-5 text-center bg-purple-50/40 hover:bg-purple-50/70 transition-colors cursor-pointer group file-drop" for="file-input">
            <input type="file" id="file-input" name="file" class="hidden" required>
            <span class="file-drop-icon"><i data-lucide="file-text" class="w-5 h-5"></i></span>
            <strong id="file-name" class="text-xs sm:text-sm font-bold text-purple-950">Pilih Berkas untuk Diperiksa</strong>
            <span class="text-[11px] text-gray-500 mt-0.5">Dukung dokumen PDF, PNG, DOCX, maupun berkas APK / Executable</span>
        </label>

        <div class="flex flex-wrap gap-1.5 justify-center file-samples">
            <button type="button" data-file-example="Surat_Undangan_Digital.apk" class="sample-danger">ϟ Tes APK Undangan</button>
            <button type="button" data-file-example="Lacak_Resi_Kurir.apk" class="sample-danger">ϟ Tes APK Resi</button>
            <button type="button" data-file-example="Dokumen_Tagihan.pdf.exe" class="sample-danger">ϟ Tes Double Ext (.exe)</button>
            <button type="button" data-file-example="Laporan_Kerja_2026.pdf" class="sample-normal">ϟ Tes PDF Standar</button>
        </div>

        <div id="file-progress" class="hidden mt-3 rounded-xl border border-purple-200 bg-purple-50 p-4" role="status">
            <div class="flex items-center justify-between text-xs font-semibold text-purple-900"><span id="file-progress-label">Mengirim berkas untuk dipindai...</span><span class="typing-dots"><i></i><i></i><i></i></span></div>
            <div class="file-progress-track"><span></span></div>
        </div>

        <div class="flex gap-2 mt-3">
            <button id="file-submit" class="file-scan-button" type="submit"><i data-lucide="scan-search" class="w-4 h-4"></i> Pindai file</button>
            <button id="file-clear" class="file-clear-button" type="reset">Bersihkan</button>
        </div>
        <button id="file-refresh" class="prototype-secondary mt-2 hidden" type="button">Perbarui status pemindaian</button>
    </form>

    <p class="text-[11px] text-gray-500 mt-3">Hasil pemindaian dapat keliru atau belum mengenali ancaman baru. Jangan membuka file hanya karena hasilnya bersih.</p>

    <div class="mt-5 pt-1">
        <div class="flex items-center justify-between"><div><h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Riwayat Pemindaian File</h3><p class="mt-0.5 text-[10px] text-gray-400">Tersimpan di browser ini</p></div><button id="clear-file-history" type="button" class="text-xs text-gray-400 hover:text-purple-700">Bersihkan</button></div>
        <p id="file-history-empty" class="text-xs text-gray-400 mt-2">Belum ada berkas yang dipindai.</p>
        <div id="fileList" class="space-y-2.5 pr-1 mt-2" aria-live="polite"></div>
    </div>
</section>
<div id="toastContainer" aria-live="polite"></div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('file-form');
    const input = document.getElementById('file-input');
    const drop = document.getElementById('file-drop');
    const fileName = document.getElementById('file-name');
    const submit = document.getElementById('file-submit');
    const progress = document.getElementById('file-progress');
    const refresh = document.getElementById('file-refresh');
    const list = document.getElementById('fileList');
    const empty = document.getElementById('file-history-empty');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const fileReportTemplate = @json(route('files.report', ['fileHash' => '__FILE_HASH__']));
    const historyStorageKey = 'guardian:file-history:v1';
    let approvedFile = null;
    let activeHistory = null;
    let autoPollTimer = null;
    let pollAttempt = 0;

    function readSavedHistory() {
        try {
            const records = JSON.parse(localStorage.getItem(historyStorageKey) || '[]');
            return Array.isArray(records) ? records.filter(record => record && typeof record.name === 'string' && typeof record.message === 'string').slice(0, 25) : [];
        } catch (_) { return []; }
    }
    function saveLocalHistory(name, message, kind, status, fileHash = null) {
        try {
            const records = readSavedHistory();
            const id = fileHash || `scan-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
            const record = { id, name, message, kind, status, file_hash: fileHash, savedAt: Date.now() };
            localStorage.setItem(historyStorageKey, JSON.stringify([record, ...records.filter(item => item.id !== id)].slice(0, 25)));
            return id;
        } catch (_) { return fileHash || null; }
    }

    function notify(message) {
        const toast = document.createElement('div');
        toast.className = 'toast-msg'; toast.textContent = message;
        document.getElementById('toastContainer').appendChild(toast);
        window.setTimeout(() => toast.remove(), 3200);
    }
    function addHistory(name, message, kind, demo = false, fileHash = null, status = 'complete', persist = true) {
        empty.hidden = true;
        const item = document.createElement('article');
        item.className = `file-history-item ${kind === 'danger' ? 'is-danger' : 'is-neutral'}`;
        const info = document.createElement('div'); info.className = 'file-history-info';
        const nameEl = document.createElement('strong'); nameEl.textContent = name;
        const messageEl = document.createElement('p'); messageEl.textContent = `${message}${demo ? ' · Contoh lokal, tidak dikirim ke VirusTotal' : ''}`;
        const badge = document.createElement('span'); badge.className = `file-history-badge ${demo ? 'is-demo' : ''}`; badge.textContent = demo ? 'CONTOH' : status === 'pending' ? 'MEMINDAI' : status === 'rate_limited' ? 'MENUNGGU' : (kind === 'danger' ? 'PERIKSA' : 'HASIL');
        info.append(nameEl, messageEl); item.append(info, badge); list.prepend(item);
        if (fileHash && !demo && status === 'complete') {
            const link = document.createElement('a');
            link.className = 'file-report-link';
            link.href = fileReportTemplate.replace('__FILE_HASH__', encodeURIComponent(fileHash));
            link.textContent = 'Laporan';
            item.append(link);
        }
        const entry = { item, messageEl, badge, name, fileHash, storageId: null };
        if (!demo && persist) entry.storageId = saveLocalHistory(name, message, kind, status, fileHash);
        return entry;
    }
    function updateHistory(entry, message, kind, fileHash = null, status = 'complete') {
        if (!entry) return;
        entry.messageEl.textContent = message;
        entry.name = entry.name || 'File yang dipindai';
        entry.fileHash = fileHash || entry.fileHash;
        entry.item.className = `file-history-item ${kind === 'danger' ? 'is-danger' : 'is-neutral'}`;
        entry.badge.textContent = status === 'pending' ? 'MEMINDAI' : status === 'rate_limited' ? 'MENUNGGU' : kind === 'danger' ? 'PERIKSA' : 'HASIL';
        if (entry.fileHash && status === 'complete' && !entry.item.querySelector('.file-report-link')) {
            const link = document.createElement('a');
            link.className = 'file-report-link';
            link.href = fileReportTemplate.replace('__FILE_HASH__', encodeURIComponent(entry.fileHash));
            link.textContent = 'Laporan';
            entry.item.append(link);
        }
        entry.storageId = saveLocalHistory(entry.name, message, kind, status, entry.fileHash);
    }
    function clearAutoPoll() {
        if (autoPollTimer !== null) window.clearTimeout(autoPollTimer);
        autoPollTimer = null;
    }
    function scheduleAutoPoll(delay = 20000) {
        clearAutoPoll();
        autoPollTimer = window.setTimeout(() => refreshAnalysis(false), delay);
    }
    async function refreshAnalysis(manual = true) {
        if (refresh.disabled) return;
        clearAutoPoll();
        refresh.disabled = true;
        try {
            const response = await fetch('{{ route('api.files.analysis') }}', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } });
            const data = await response.json();
            if (response.status === 404) {
                const message = 'Sesi pemindaian ini sudah berakhir. Pilih file lagi untuk memulai pemindaian baru.';
                updateHistory(activeHistory, message, 'neutral', null, 'unavailable');
                activeHistory = null; refresh.classList.add('hidden');
                Robot.setState('idle', message);
                return;
            }
            if (!response.ok) throw new Error(data.error || 'Status pemindaian tidak dapat diperbarui.');
            const detected = data.verdict === 'detected' || data.verdict === 'suspicious';
            const detail = data.detections !== undefined ? `${data.message} Deteksi: ${data.detections} dari ${data.engines} mesin.` : data.message;
            const kind = detected ? 'danger' : 'neutral';
            updateHistory(activeHistory, detail, kind, data.file_hash, data.status);
            refresh.classList.toggle('hidden', !['pending', 'rate_limited'].includes(data.status));
            Robot.setState(detected ? 'danger' : data.status === 'pending' ? 'scanning' : data.status === 'complete' ? 'safe' : 'idle', data.message);

            if (data.status === 'pending') {
                pollAttempt += 1;
                scheduleAutoPoll(pollAttempt === 1 ? 20000 : 25000);
            } else if (data.status === 'rate_limited') {
                scheduleAutoPoll(60000);
            } else {
                activeHistory = null;
                if (data.status === 'complete') notify(data.message);
            }
        } catch (error) {
            if (manual) notify(error.message || 'Status pemindaian tidak dapat diperbarui.');
            else scheduleAutoPoll(30000);
        } finally {
            refresh.disabled = false;
        }
    }
    function updateSelectedFile() {
        fileName.textContent = input.files[0]?.name || 'Pilih Berkas untuk Diperiksa';
    }
    document.getElementById('clear-file-history').addEventListener('click', () => {
        clearAutoPoll(); activeHistory = null; refresh.classList.add('hidden');
        list.replaceChildren(); empty.hidden = false;
        try { localStorage.removeItem(historyStorageKey); } catch (_) { }
    });

    const savedRecords = readSavedHistory();
    const restoredEntries = new Map();
    savedRecords.slice().reverse().forEach(record => {
        const entry = addHistory(record.name, record.message, record.kind === 'danger' ? 'danger' : 'neutral', false, record.file_hash, record.status, false);
        entry.storageId = record.id; restoredEntries.set(record.id, entry);
    });
    const savedPending = savedRecords[0];
    if (savedPending && savedPending.file_hash && ['pending', 'rate_limited'].includes(savedPending.status)) {
        activeHistory = restoredEntries.get(savedPending.id) || null;
        refresh.classList.remove('hidden');
        scheduleAutoPoll(savedPending.status === 'rate_limited' ? 60000 : 3000);
    }
    input.addEventListener('change', () => { approvedFile = null; updateSelectedFile(); });
    ['dragenter','dragover'].forEach(type => drop.addEventListener(type, event => { event.preventDefault(); drop.classList.add('is-dragging'); }));
    ['dragleave','drop'].forEach(type => drop.addEventListener(type, event => { event.preventDefault(); drop.classList.remove('is-dragging'); }));
    drop.addEventListener('drop', event => {
        if (!event.dataTransfer.files.length) return;
        input.files = event.dataTransfer.files; approvedFile = null; updateSelectedFile();
    });

    document.querySelectorAll('[data-file-example]').forEach(button => button.addEventListener('click', () => {
        const name = button.dataset.fileExample;
        const extensionRisk = /\.(apk|exe|scr|vbs|bat|cmd|ps1)$/i.test(name) || /\.(pdf|docx|jpg)\.(apk|exe)$/i.test(name);
        addHistory(name, extensionRisk ? 'Ekstensi executable berisiko perlu diperiksa.' : 'Ekstensi dokumen umum; ini bukan hasil pemindaian.', extensionRisk ? 'danger' : 'neutral', true);
        notify('Contoh nama file ditambahkan. Unggah file asli untuk pemindaian VirusTotal.');
        Robot.setState(extensionRisk ? 'danger' : 'safe', `Contoh lokal file: ${name}. Ini bukan hasil VirusTotal; unggah file asli untuk pemindaian.`);
    }));

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (submit.disabled || !form.reportValidity()) return;
        const file = input.files[0];
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) { notify('Ukuran file melebihi batas 10 MB.'); return; }
        if (approvedFile !== file) {
            submit.disabled = true;
            const accepted = await window.GuardianConsent.request({
                title: 'Kirim file ke VirusTotal untuk dipindai?',
                description: `File "${file.name}" akan dikirim ke VirusTotal. File yang dikirim lewat API publik dapat ditambahkan ke dataset dan dibagikan kepada komunitas VirusTotal. Jangan kirim file pribadi atau rahasia.`,
                label: 'Saya setuju mengirim file ini ke VirusTotal dan memahami file dapat dibagikan kepada komunitas.',
            });
            submit.disabled = false;
            if (!accepted) { Robot.setState('idle', 'Pemindaian dibatalkan. File belum dikirim.'); return; }
            approvedFile = file;
        }
        clearAutoPoll(); activeHistory = null; pollAttempt = 0;
        submit.disabled = true; progress.classList.remove('hidden'); refresh.classList.add('hidden');
        Robot.setState('scanning', 'File sedang dikirim dengan persetujuanmu untuk dipindai oleh VirusTotal.');
        const body = new FormData(form); body.set('share_file_with_virustotal', '1');
        try {
            const response = await fetch('{{ route('api.files') }}', { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Pemindaian file gagal.');
            const detected = data.verdict === 'detected' || data.verdict === 'suspicious';
            const detail = data.detections !== undefined ? `${data.message} Deteksi: ${data.detections} dari ${data.engines} mesin.` : data.message;
            activeHistory = addHistory(file.name, detail, detected ? 'danger' : 'neutral', false, data.file_hash, data.status);
            refresh.classList.toggle('hidden', data.status !== 'pending');
            Robot.setState(detected ? 'danger' : data.status === 'complete' ? 'safe' : data.status === 'pending' ? 'scanning' : 'idle', data.message);
            notify(data.message);
            if (data.status === 'pending') scheduleAutoPoll(5000);
        } catch (error) {
            addHistory(file.name, error.message || 'Pemindaian file gagal.', 'danger', false, null, 'unavailable');
            Robot.setState('idle', error.message || 'Pemindaian file gagal.');
            notify(error.message || 'Pemindaian file gagal.');
        } finally {
            submit.disabled = false; progress.classList.add('hidden');
        }
    });

    refresh.addEventListener('click', () => refreshAnalysis(true));

    form.addEventListener('reset', () => {
        clearAutoPoll(); activeHistory = null;
        refresh.classList.add('hidden');
        approvedFile = null;
        window.setTimeout(() => { updateSelectedFile(); Robot.setState('idle', 'Pilih atau unggah berkas untuk dipindai oleh Guardian.'); }, 0);
    });
    window.lucide?.createIcons();
})();
</script>
@endpush
