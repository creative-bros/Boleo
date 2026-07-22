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
                                        <span class="nav-link__label">{{ $item['label'] }}</span>
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
        </main>
    </div>
@endsection
