@extends('layouts.app')

@section('title', 'Cek Pesan AI')
@section('active', 'tanya')

@section('content')
<section class="glass-card p-6 sm:p-8 chat-prototype-card">
    <div class="flex items-center justify-between pb-3 border-b border-purple-100 mb-3">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-full bg-purple-900 flex items-center justify-center border border-purple-400 shadow-md"><i data-lucide="bot" class="w-4 h-4 text-purple-300"></i></div>
            <div><h2 class="font-bold text-purple-950 text-xs sm:text-sm">Evaluasi Pesan AI</h2><p class="text-[10px] text-purple-600 font-semibold">Cek Indikasi Risiko &amp; Kejujuran Pesan</p></div>
        </div>
        <button id="chat-clear" type="button" class="text-xs text-gray-400 hover:text-purple-600 font-medium">Bersihkan Obrolan</button>
    </div>
    <p class="text-[11px] text-gray-500 mb-3">Jawaban dibuat oleh Google Gemini. Hanya untuk pengguna berusia 18 tahun ke atas. Jangan kirim data pribadi, OTP, PIN, atau kata sandi.</p>
    <div id="chat-log" class="chat-prototype-log custom-scroll" aria-live="polite">
        <div class="chat-line bot-line"><span class="chat-avatar">🤖</span><div class="chat-bubble bot-bubble">Halo! Saya <strong>Guardian AI Assistant</strong>. Tempelkan isi pesan yang ingin diperiksa apakah aman atau berpotensi penipuan.</div></div>
    </div>
    <div id="chat-typing" class="hidden flex items-center gap-2 text-xs text-purple-600 py-1.5 px-2"><span>Guardian menganalisis pesan</span><span class="typing-dots"><i></i><i></i><i></i></span></div>
    <div class="chat-sample-strip">
        <button type="button" data-prompt="Selamat Anda menang hadiah Rp 50 Juta dari Bank BCA! Hubungi WhatsApp bit.ly/promo-bca-kupon" class="sample-danger">Tes SMS Hadiah Bank</button>
        <button type="button" data-prompt="Halo bro, silakan unduh berkas foto undangan pernikahan kami Surat_Undangan.apk" class="sample-danger">Tes WA File Undangan .APK</button>
        <button type="button" data-prompt="Halo Budi, besok rapat tim dijadwalkan jam 10 pagi ya. Terima kasih." class="sample-normal">Tes Pesan Rapat Normal</button>
    </div>
    <form id="chat-form" class="chat-prototype-form">
        <input id="chat-input" type="text" placeholder="Ketik atau tempel isi pesan di sini..." autocomplete="off" maxlength="500" required>
        <button id="chat-submit" type="submit" aria-label="Kirim pesan"><i data-lucide="arrow-right" class="w-4 h-4"></i></button>
    </form>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const log = document.getElementById('chat-log');
    const input = document.getElementById('chat-input');
    const submit = document.getElementById('chat-submit');
    const typing = document.getElementById('chat-typing');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    function addMsg(text, who) {
        const row = document.createElement('div'); row.className = `chat-line ${who === 'user' ? 'user-line' : 'bot-line'}`;
        if (who !== 'user') { const avatar = document.createElement('span'); avatar.className = 'chat-avatar'; avatar.textContent = '🤖'; row.appendChild(avatar); }
        const bubble = document.createElement('div'); bubble.className = `chat-bubble ${who === 'user' ? 'user-bubble' : 'bot-bubble'}`; bubble.textContent = text;
        row.appendChild(bubble); log.appendChild(row); log.scrollTop = log.scrollHeight;
    }
    document.querySelectorAll('[data-prompt]').forEach(button => button.addEventListener('click', () => { input.value = button.dataset.prompt; document.getElementById('chat-form').requestSubmit(); }));
    document.getElementById('chat-clear').addEventListener('click', () => {
        log.replaceChildren(); addMsg('Halo! Tempelkan isi pesan yang ingin diperiksa.', 'bot'); input.value = ''; input.focus();
    });
    document.getElementById('chat-form').addEventListener('submit', async event => {
        event.preventDefault(); const question = input.value.trim(); if (!question || submit.disabled) return;
        addMsg(question, 'user'); input.value = ''; submit.disabled = true; typing.classList.remove('hidden'); Robot.pulse();
        try {
            const response = await fetch('{{ route('api.tanya') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ question }) });
            const data = await response.json(); if (!response.ok) throw new Error(data.error || 'Chatbot tidak dapat menjawab saat ini.'); addMsg(data.answer, 'bot');
        } catch (error) { addMsg(error.message || 'Maaf, jawaban belum bisa diambil. Coba lagi.', 'bot'); }
        finally { submit.disabled = false; typing.classList.add('hidden'); input.focus(); }
    });
    window.lucide?.createIcons();
})();
</script>
@endpush
