@extends('admin.layout', ['title' => 'Entrar', 'page' => 'login'])

@section('content')
    <section class="login-card" aria-labelledby="login-title">
        <header class="login-brand">
            <img src="{{ asset('assets/images/Logotipo-admin.png') }}" alt="Cidade PVC — Centro Nacional de Fabricação de Caixilharia">
            <h1 id="login-title">Área de administração</h1>
            <p>Monitores de prémios</p>
        </header>

        <form method="POST" action="{{ route('admin.login.store') }}" novalidate>
            @csrf

            <div class="login-field">
                <label class="visually-hidden" for="username">Utilizador</label>
                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M20 21a8 8 0 0 0-16 0M12 13a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"/></svg>
                <input id="username" name="username" type="text" value="{{ old('username') }}" autocomplete="username" maxlength="64" placeholder="Utilizador" required autofocus>
            </div>

            <div class="login-field">
                <label class="visually-hidden" for="password">Password</label>
                <svg aria-hidden="true" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <input id="password" name="password" type="password" autocomplete="current-password" maxlength="255" placeholder="Password" required>
                <button class="password-toggle" type="button" aria-label="Mostrar password" aria-pressed="false">
                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                </button>
            </div>

            @if ($errors->any())
                <p class="form-error" role="alert">{{ $errors->first() }}</p>
            @endif

            <button class="login-submit" type="submit">Entrar</button>
        </form>
    </section>

    <script>
        document.querySelector('.password-toggle')?.addEventListener('click', function () {
            const password = document.querySelector('#password');
            const isVisible = password.type === 'text';

            password.type = isVisible ? 'password' : 'text';
            this.setAttribute('aria-pressed', String(!isVisible));
            this.setAttribute('aria-label', isVisible ? 'Mostrar password' : 'Ocultar password');
        });
    </script>
@endsection
