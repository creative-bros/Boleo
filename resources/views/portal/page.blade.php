@extends('layouts.boleo', ['title' => 'Boleo | Portal'])

@section('content')
    <div class="portal-shell">
        <aside class="sidebar">
            <div class="sidebar__brand">
                <div class="sidebar__logo">B</div>
                <div>
                    <h1>Boleo</h1>
                    <p>Portal Conserje</p>
                </div>
            </div>

            <nav class="sidebar__nav">
                @foreach ($navigation as $item)
                    <a href="{{ route($item['route']) }}" class="nav-link {{ $page === $item['key'] ? 'is-active' : '' }}">
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <form method="POST" action="{{ route('logout') }}" class="logout-form" data-logout-form>
                @csrf
                <button type="submit" class="sidebar__logout" data-logout-button>Cerrar sesion</button>
            </form>
        </aside>

        <main class="portal-main">
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
                    <div class="user-pill">{{ $currentUser?->name }} · {{ $canManage ? 'Administrador' : 'Usuario' }}</div>
                </div>
            </header>

            @if (session('status'))
                <div class="alert alert--success">{{ session('status') }}</div>
            @endif

            @includeIf('portal.partials.' . $page)
        </main>
    </div>
@endsection
