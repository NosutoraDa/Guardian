@extends('layouts.app')

@section('title', 'Chatbot')
@section('active', 'tanya')
@section('robot-mode', 'chat')
@section('robot-message', 'Tanyakan apa saja seputar penipuan digital dan keamanan siber.')

@section('content')
    <div class="card">
        <div class="tool-heading">
            <span class="tool-icon"><i class="fas fa-comment-dots" aria-hidden="true"></i></span>
            <div>
                <h2>Chatbot keamanan digital</h2>
                <p>Tanyakan cara mengenali dan menghadapi penipuan digital.</p>
            </div>
        </div>
        <p class="warn-note">Jawaban dibuat oleh Google Gemini. Hanya untuk pengguna berusia 18 tahun ke atas. Pada layanan gratis, Google dapat menggunakan pertanyaan untuk meningkatkan produknya; jangan kirim data pribadi, OTP, PIN, atau kata sandi.</p>

        <div id="chat-log" class="chat-log">
            <div class="msg bot">Halo! Tanyakan apa saja seputar penipuan digital, misalnya "apa itu phishing?"</div>
        </div>

        <form id="chat-form" class="row">
            <input id="chat-input" type="text" placeholder="Tulis pertanyaanmu..." autocomplete="off" maxlength="500" required>
            <button id="chat-submit" class="btn-primary" type="submit">Kirim</button>
        </form>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const log = document.getElementById('chat-log');
    const input = document.getElementById('chat-input');
    const submit = document.getElementById('chat-submit');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    function addMsg(text, who) {
        const div = document.createElement('div');
        div.className = 'msg ' + who;
        div.textContent = text;
        log.appendChild(div);
        log.scrollTop = log.scrollHeight;
    }

    document.getElementById('chat-form').addEventListener('submit', async event => {
        event.preventDefault();
        const question = input.value.trim();
        if (!question) return;

        addMsg(question, 'user');
        input.value = '';
        submit.disabled = true;
        Robot.pulse(); // robot "types" while the answer loads

        try {
            const response = await fetch('{{ route('api.tanya') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ question }),
            });

            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'Chatbot tidak dapat menjawab saat ini.');
            }
            addMsg(data.answer, 'bot');
        } catch (error) {
            addMsg(error.message || 'Maaf, jawaban belum bisa diambil. Coba lagi sebentar lagi.', 'bot');
        } finally {
            submit.disabled = false;
            input.focus();
        }
    });
})();
</script>
@endpush
