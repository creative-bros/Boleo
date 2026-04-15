@extends('layouts.boleo', ['title' => 'Boleo | Restablecer contraseña', 'bodyClass' => 'auth-shell'])

@section('content')
    <main class="auth-layout">
        <section class="auth-brand">
            <div class="auth-brand__glow"></div>
            <div class="auth-brand__network"></div>

            <div class="auth-brand__content">
                <div class="brand-mark">
                    <span class="brand-mark__icon">B</span>
                    <div>
                        <p class="brand-mark__eyebrow">Nueva contraseña</p>
                        <h1>Boleo</h1>
                    </div>
                </div>

                <div class="auth-copy">
                    <h2>Define una nueva contraseña segura.</h2>
                    <p>El enlace de recuperación se validará al guardar los cambios. Después podrás iniciar sesión normalmente.</p>
                </div>
            </div>
        </section>

        <section class="auth-panel">
            <div class="auth-card">
                <div class="auth-card__header">
                    <p class="eyebrow">Restablecer contraseña</p>
                    <h2>Actualizar acceso</h2>
                    <p>Ingresa una nueva contraseña para tu cuenta.</p>
                </div>

                <form class="auth-form" method="POST" action="{{ route('password.update') }}">
                    @csrf

                    @if ($errors->any())
                        <div class="alert alert--error">{{ $errors->first() }}</div>
                    @endif

                    <input type="hidden" name="token" value="{{ $token }}">

                    <label class="field">
                        <span>Correo electrónico</span>
                        <input type="email" name="email" value="{{ old('email', $email) }}" required>
                    </label>

                    <label class="field">
                        <span>Nueva contraseña</span>
                        <input type="password" name="password" placeholder="Ingresa la nueva contraseña" required>
                    </label>

                    <label class="field">
                        <span>Confirmar contraseña</span>
                        <input type="password" name="password_confirmation" placeholder="Confirma la nueva contraseña" required>
                    </label>

                    <button class="button button--primary" type="submit">Guardar nueva contraseña</button>
                </form>

                <div class="auth-card__footer">
                    <p><a href="{{ route('login') }}">Volver al acceso principal</a></p>
                </div>
            </div>
        </section>
    </main>
@endsection
