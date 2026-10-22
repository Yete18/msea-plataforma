<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  @php($seoTitulo = 'Recuperar contraseña | MSEA')
  @include('partials.seo')
  @include('partials.auth-assets')
</head>
<body>
  <main class="auth-flow-layout">
    @include('partials.auth-brand-panel')
    <section class="auth-flow-form">
      <div class="reset-panel">
      <a href="{{ url('/login') }}" class="auth-back-button"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-flecha-izquierda"/></svg> Volver al inicio de sesión</a>

      <div class="reset-header">
        <img src="{{ asset('assets/img/logo-320.webp') }}" width="72" height="72" alt="" class="reset-logo" />
        <h1>Recuperar contraseña</h1>
        <p>Ingresa tu correo y te enviaremos un código de verificación.</p>
      </div>

      @include('partials.flash-messages')

      <form action="{{ url('/forgot-password') }}" method="POST" class="reset-form">
        @csrf
        @include('partials.antispam', ['idForm' => 'recuperar'])
        <label for="correo">Correo electrónico</label>
        <input
          type="email"
          id="correo"
          name="correo"
          value="{{ old('correo') }}"
          placeholder="correo@ejemplo.com"
          autocomplete="email"
          required
        />

        <button type="submit">Enviar código</button>
      </form>
      </div>
    </section>
  </main>
  @include('partials.cookies')
</body>
</html>
