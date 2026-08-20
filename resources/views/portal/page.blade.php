@extends('layouts.boleo', ['title' => 'Boleo | Portal'])

@section('content')
    <div class="portal-shell">
        <aside class="sidebar">
            <div class="sidebar__brand">
                <img class="sidebar__logo-image" src="{{ asset('img/brand/logo-positive-compact.png') }}?v={{ filemtime(public_path('img/brand/logo-positive-compact.png')) }}" alt="Boleo Administradora">
            </div>

            <nav class="sidebar__nav">
                @foreach ($navigation as $group)
                    <section class="sidebar__section">
                        <div class="sidebar__section-header">{{ $group['section'] }}</div>
                        <div class="sidebar__section-links">
                            @foreach ($group['items'] as $item)
                                @php($isActiveNavigationItem = $page === $item['key'])
                                <a href="{{ route($item['route']) }}" class="nav-link {{ $isActiveNavigationItem ? 'is-active' : '' }}">
                                    <div class="nav-link__content">
                                        <span class="nav-link__label">
                                            {{ $item['label'] }}
                                            @if (array_key_exists('badge', $item))
                                                <span class="nav-link__dot" data-nav-dot="{{ $item['key'] }}" data-count="{{ $item['badge'] ?? 0 }}" @if (($item['badge'] ?? 0) <= 0) hidden @endif></span>
                                            @endif
                                        </span>
                                        <small class="nav-link__description">{{ $item['description'] }}</small>
                                    </div>
                                </a>
                                @if ($isActiveNavigationItem && ! empty($item['children']))
                                    <div class="nav-link__children" aria-label="Atajos de {{ $item['label'] }}">
                                        @foreach ($item['children'] as $child)
                                            <a href="{{ $child['href'] }}" class="nav-link__subitem">{{ $child['label'] }}</a>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="logout-form" data-logout-form>
                @csrf
                <button type="submit" class="sidebar__logout" data-logout-button>Cerrar sesión</button>
            </form>
        </aside>

        <main class="portal-main">
            <nav class="mobile-nav" aria-label="Navegación principal">
                <div class="mobile-nav__brand">
                    <img class="mobile-nav__logo-image" src="{{ asset('img/brand/logo-positive-compact.png') }}?v={{ filemtime(public_path('img/brand/logo-positive-compact.png')) }}" alt="Boleo Administradora">
                </div>
                <div class="mobile-nav__links">
                    @foreach ($navigation as $group)
                        @foreach ($group['items'] as $item)
                            @php($isActiveNavigationItem = $page === $item['key'])
                            <a href="{{ route($item['route']) }}" class="mobile-nav__link {{ $isActiveNavigationItem ? 'is-active' : '' }}">
                                {{ $item['label'] }}
                                @if (array_key_exists('badge', $item))
                                    <span class="nav-link__dot" data-nav-dot-mobile="{{ $item['key'] }}" data-count="{{ $item['badge'] ?? 0 }}" @if (($item['badge'] ?? 0) <= 0) hidden @endif></span>
                                @endif
                            </a>
                            @if ($isActiveNavigationItem && ! empty($item['children']))
                                @foreach ($item['children'] as $child)
                                    <a href="{{ $child['href'] }}" class="mobile-nav__link mobile-nav__link--sub">
                                        {{ $child['label'] }}
                                    </a>
                                @endforeach
                            @endif
                        @endforeach
                    @endforeach
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mobile-nav__logout-form" data-logout-form>
                    @csrf
                    <button type="submit" class="mobile-nav__logout" data-logout-button>Cerrar sesión</button>
                </form>
            </nav>

            <header class="topbar">
                <div>
                    <p class="eyebrow">Boleo Suite</p>
                    <h2>{{ $headline }}</h2>
                    <p>{{ $subheadline }}</p>
                </div>

                <div class="topbar__actions">
                    <form method="GET" action="{{ url()->current() }}" class="search-form">
                        @if (request()->has('unit'))
                            <input type="hidden" name="unit" value="{{ request('unit') }}">
                        @endif
                        @if (request()->has('edit_user'))
                            <input type="hidden" name="edit_user" value="{{ request('edit_user') }}">
                        @endif
                        <input class="search-pill search-pill--input" type="search" name="q" value="{{ $searchQuery }}" placeholder="Buscar unidades, pagos o reportes...">
                    </form>
                    <div class="user-pill">{{ $currentUser?->name }} · {{ $currentUser?->roleLabel() ?? ($canManage ? 'Administrador' : 'Auxiliar') }}</div>
                </div>
            </header>

            @includeIf('portal.partials.' . $page)

            @if (session('status'))
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Listo!',
                            text: @json(session('status')),
                            confirmButtonColor: '#1f5c4f',
                            timer: 3200,
                            timerProgressBar: true,
                        });
                    });
                </script>
            @endif

            @if ($errors->any())
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Hubo un problema',
                            text: @json($errors->first()),
                            confirmButtonColor: '#1f5c4f',
                        });
                    });
                </script>
            @endif

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const dots = document.querySelectorAll('[data-nav-dot="quote-requests"], [data-nav-dot-mobile="quote-requests"]');

                    if (!dots.length) {
                        return;
                    }

                    let lastCount = parseInt(dots[0].dataset.count || '0', 10);
                    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    const audioContext = AudioContextClass ? new AudioContextClass() : null;

                    const resumeAudio = () => {
                        if (audioContext && audioContext.state === 'suspended') {
                            audioContext.resume().catch(() => {});
                        }
                    };

                    ['click', 'keydown', 'touchstart', 'scroll'].forEach((eventName) => {
                        document.addEventListener(eventName, resumeAudio, {once: true, passive: true});
                    });

                    document.addEventListener('visibilitychange', () => {
                        if (document.visibilityState === 'visible') {
                            resumeAudio();
                        }
                    });

                    const playNotificationSound = () => {
                        if (!audioContext) {
                            return;
                        }

                        resumeAudio();

                        const now = audioContext.currentTime;

                        [[880, now, 0.18], [1320, now + 0.16, 0.22]].forEach(([frequency, start, duration]) => {
                            const oscillator = audioContext.createOscillator();
                            const gain = audioContext.createGain();
                            oscillator.type = 'sine';
                            oscillator.frequency.value = frequency;
                            gain.gain.setValueAtTime(0.0001, start);
                            gain.gain.exponentialRampToValueAtTime(0.22, start + 0.02);
                            gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);
                            oscillator.connect(gain);
                            gain.connect(audioContext.destination);
                            oscillator.start(start);
                            oscillator.stop(start + duration);
                        });
                    };

                    const updateDots = (count) => {
                        dots.forEach((dot) => {
                            dot.hidden = count <= 0;
                            dot.dataset.count = String(count);
                        });
                    };

                    const checkForNewQuoteRequests = () => {
                        fetch('{{ route('quote-requests.pending-count') }}', {headers: {Accept: 'application/json'}})
                            .then((response) => (response.ok ? response.json() : null))
                            .then((data) => {
                                if (!data) {
                                    return;
                                }

                                const count = Number(data.count) || 0;

                                if (count > lastCount) {
                                    playNotificationSound();
                                }

                                lastCount = count;
                                updateDots(count);
                            })
                            .catch(() => {});
                    };

                    setInterval(checkForNewQuoteRequests, 30000);
                });
            </script>
        </main>
    </div>
@endsection
