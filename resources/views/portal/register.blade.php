@extends('layouts.boleo', ['title' => 'Boleo | Crear cuenta', 'bodyClass' => 'auth-shell'])

@section('content')
    <main class="auth-layout">
        <section class="auth-brand">
            <div class="auth-brand__glow"></div>
            <div class="auth-brand__network"></div>

            <div class="auth-brand__content">
                <div class="brand-mark">
                    <span class="brand-mark__icon">B</span>
                    <div>
                        <p class="brand-mark__eyebrow">Nuevo acceso</p>
                        <h1>Boleo</h1>
                    </div>
                </div>

                <div class="auth-copy">
                    <h2>Crea tu cuenta para administrar tu comunidad.</h2>
                    <p>Registra tus datos básicos y empieza a usar la plataforma de Boleo con acceso seguro y recuperación integrada.</p>
                </div>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card__header">
                    <p class="eyebrow">Crear cuenta</p>
                    <h2>Registro de usuario</h2>
                    <p>Ingresa tu información para generar un acceso nuevo.</p>
                </div>

                <form class="auth-form" method="POST" action="{{ route('register.store') }}">
                    @csrf

                    @if ($errors->any())
                        <div class="alert alert--error">{{ $errors->first() }}</div>
                    @endif

                    <label class="field">
                        <span>Nombre completo</span>
                        <input type="text" name="name" placeholder="Ingresa tu nombre completo" value="{{ old('name') }}" required>
                    </label>

                    <label class="field">
                        <span>Correo electrónico</span>
                        <input type="email" name="email" placeholder="Ingresa tu correo" value="{{ old('email') }}" required>
                    </label>

                    <label class="field">
                        <span>Número telefónico</span>
                        <input type="text" name="phone" placeholder="Ingresa tu número telefónico" value="{{ old('phone') }}" required>
                    </label>

                    <label class="field">
                        <span>Contraseña</span>
                        <input type="password" name="password" placeholder="Crea tu contraseña" required>
                    </label>

                    <label class="field">
                        <span>Confirmar contraseña</span>
                        <input type="password" name="password_confirmation" placeholder="Confirma tu contraseña" required>
                    </label>

                    <button class="button button--primary" type="submit">Crear cuenta</button>
                </form>

                <div class="auth-card__footer">
                    <p>¿Ya tienes cuenta? <a href="{{ route('login') }}">Iniciar sesión</a></p>
                </div>
            </div>
        </section>
    </main>
@endsection
