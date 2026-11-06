@props(['message' => '', 'title' => 'Guardian siap membantu'])

<div id="externalRobotStage" class="external-center-robot-stage active">
    <div class="w-full flex justify-end items-center mb-1 px-2">
        <button id="guardian-sound-toggle" class="btn-control px-2.5 py-1 rounded-xl text-[11px] font-semibold flex items-center gap-1" type="button" aria-pressed="true">
            <i data-lucide="volume-2" id="soundIcon" class="w-3 h-3"></i><span id="soundText">Suara Aktif</span>
        </button>
    </div>
    <div id="guardian-avatar-wrap" class="relative flex items-center justify-center p-1">
        <canvas id="guardian-particles" class="particle-canvas"></canvas>
        <button id="mascot-toggle-menu" class="float-anim cursor-pointer relative transition-transform duration-150 ease-out hover:scale-105" type="button" aria-label="Klik Guardian untuk reaksi">
            <svg id="robot-svg" class="guardian-avatar-svg w-36 h-36 sm:w-44 sm:h-44" viewBox="0 0 400 400" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Maskot robot Guardian">
                <path d="M 66 110 C 66 45, 136 45, 136 110 Z" fill="#b066fe" transform="rotate(-16 101 77)"/>
                <path d="M 264 110 C 264 45, 334 45, 334 110 Z" fill="#b066fe" transform="rotate(16 299 77)"/>
                <rect x="50" y="80" width="300" height="270" rx="90" fill="#e2d5f8"/>
                <rect x="68" y="98" width="264" height="234" rx="72" fill="#fff"/>
                <rect id="guardian-screen" x="88" y="118" width="224" height="194" rx="55" fill="#231340"/>
                <g id="guardian-face" class="faceFeaturesGroup">
                    <g id="left-eye" class="leftEyeShape"><rect x="135" y="175" width="32" height="60" rx="16" fill="#c38eff"/></g>
                    <g id="right-eye" class="rightEyeShape"><rect x="233" y="175" width="32" height="60" rx="16" fill="#c38eff"/></g>
                    <path id="smooth-mouth" class="mouthPath" d="M 155 260 Q 200 285 245 260" stroke="#c38eff" stroke-width="9" stroke-linecap="round" fill="none"/>
                </g>
            </svg>
        </button>
    </div>
    <h4 id="robot-title" data-idle-title="{{ $title }}" class="guardian-status-pill guardian-status-idle">{{ $title }}</h4>
    <p id="speech-text" class="text-[10px] text-purple-800 max-w-xs mt-0.5 font-medium bg-white/70 px-2.5 py-0.5 rounded-lg">{{ $message }}</p>
</div>
