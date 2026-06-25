@extends('layouts.boleo', ['title' => 'Boleo | Acceso', 'bodyClass' => 'auth-shell'])

@section('content')
    <main class="auth-layout">
        <section class="auth-brand">
            <div class="auth-brand__glow"></div>
            <div class="auth-brand__network"></div>

            <div class="auth-brand__content">
                <div class="brand-mark">
                    <img class="brand-mark__logo" src="{{ asset('img/brand/logo-negative.png') }}" alt="Boleo Administradora">
                </div>

                <div class="auth-copy">
                    <h2>La excelencia en la administración de su comunidad.</h2>
                    <p>Gestionamos el bienestar de miles de familias con tecnología intuitiva, transparencia total y una operación centralizada.</p>
                </div>

                <div class="auth-metrics">
                    <div>
                        <strong>2.5k+</strong>
                        <span>Condominios</span>
                    </div>
                    <div>
                        <strong>99.9%</strong>
                        <span>Uptime</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card__header">
                    <p class="eyebrow">Bienvenido de nuevo</p>
                    <h2>Portal de Administración</h2>
                    <p>Ingrese sus credenciales para acceder al entorno operativo de Boleo.</p>
                </div>

                <form id="loginForm" class="auth-form" method="POST" action="{{ route('authenticate') }}" autocomplete="on">
                    @csrf

                    @if ($errors->any())
                        <div class="alert alert--error">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <label class="field" for="email">
                        <span>Correo electrónico</span>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            placeholder="Ingresa tu correo"
                            value="{{ old('email') }}"
                            autocomplete="username"
                            autocapitalize="none"
                            spellcheck="false"
                            required
                        >
                    </label>

                    <label class="field" for="password">
                        <span>Contraseña</span>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            placeholder="Ingresa tu contraseña"
                            autocomplete="current-password"
                            required
                        >
                    </label>

                    <div class="auth-form__row">
                        <label class="checkbox">
                            <input
                                id="remember"
                                type="checkbox"
                                name="remember"
                                value="1"
                                {{ old('remember') ? 'checked' : '' }}
                            >
                            <span>Recordarme</span>
                        </label>

                        <a href="{{ route('password.request') }}">Recuperar contraseña</a>
                    </div>

                    <button class="button button--primary" type="submit">Entrar</button>
                </form>

                <div class="auth-secondary-action">
                    <span>¿Aún no tienes cuenta?</span>
                    <a class="button button--ghost" href="{{ route('register') }}">Crear cuenta</a>
                </div>

                <div class="auth-card__footer">
                    <p>¿Necesita asistencia técnica? <a href="{{ route('password.request') }}">Iniciar recuperación</a></p>
                </div>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('loginForm');
            const emailInput = document.getElementById('email');
            const rememberInput = document.getElementById('remember');

            const savedEmail = localStorage.getItem('boleo_remember_email');

            if (savedEmail) {
                emailInput.value = savedEmail;
                rememberInput.checked = true;
            }

            form.addEventListener('submit', function () {
                if (rememberInput.checked) {
                    localStorage.setItem('boleo_remember_email', emailInput.value);
                } else {
                    localStorage.removeItem('boleo_remember_email');
                }
            });
        });
    </script>
@endsection
