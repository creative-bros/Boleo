@extends('layouts.boleo', ['title' => 'Boleo | Recuperar contraseña', 'bodyClass' => 'auth-shell'])

@section('content')
    <main class="auth-layout">
        <section class="auth-brand">
            <div class="auth-brand__glow"></div>
            <div class="auth-brand__network"></div>

            <div class="auth-brand__content">
                <div class="brand-mark">
                    <span class="brand-mark__icon">B</span>
                    <div>
                        <p class="brand-mark__eyebrow">Recuperación Segura</p>
                        <h1>Boleo</h1>
                    </div>
                </div>

                <div class="auth-copy">
                    <h2>Recupera el acceso a tu portal.</h2>
                    <p>Confirma tu correo y número telefónico. Si coinciden con tu cuenta, te enviaremos un mensaje con el enlace para restablecer tu contraseña.</p>
                </div>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card__header">
                    <p class="eyebrow">Recuperar contraseña</p>
                    <h2>Validación de identidad</h2>
                    <p>Usa los datos registrados en tu cuenta de Boleo.</p>
                </div>

                @if (session('status'))
                    <div class="alert alert--success">{{ session('status') }}</div>
                @endif

                <form class="auth-form" method="POST" action="{{ route('password.email') }}">
                    @csrf

                    @if ($errors->any())
                        <div class="alert alert--error">{{ $errors->first() }}</div>
                    @endif

                    <label class="field">
                        <span>Correo electrónico</span>
                        <input type="email" name="email" placeholder="Ingresa tu correo" value="{{ old('email') }}" required>
                    </label>

                    <label class="field">
                        <span>Número telefónico</span>
                        <input type="text" name="phone" placeholder="Ingresa tu número telefónico" value="{{ old('phone') }}" required>
                    </label>

                    <button class="button button--primary" type="submit">Enviar recuperación</button>
                </form>

                <div class="auth-card__footer">
                    <p><a href="{{ route('login') }}">Volver al acceso principal</a></p>
                </div>
            </div>
        </section>
    </main>
@endsection
