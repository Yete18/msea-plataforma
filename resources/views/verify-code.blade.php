<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  @php($seoTitulo = 'Verificar código | MSEA')
  @include('partials.seo')
  @include('partials.auth-assets')
</head>
<body>
  <main class="auth-flow-layout">
    @include('partials.auth-brand-panel')
    <section class="auth-flow-form">
      <div class="reset-panel">
      <a href="{{ url('/forgot-password') }}" class="auth-back-button"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-flecha-izquierda"/></svg> Cambiar correo</a>

      <div class="reset-header">
        <img src="{{ asset('assets/img/logo-320.webp') }}" width="72" height="72" alt="" class="reset-logo" />
        <h1>Código de verificación</h1>
        <p>Escribe el código de 6 dígitos enviado a {{ $correo }}.</p>
      </div>

      @include('partials.flash-messages')

      <form action="{{ url('/verify-code') }}" method="POST" class="reset-form">
        @csrf
        <label for="codigo">Código</label>
        <input
          type="text"
          id="codigo"
          name="codigo"
          inputmode="numeric"
          pattern="[0-9]{6}"
          maxlength="6"
          placeholder="123456"
          autocomplete="one-time-code"
          required
        />

        <button type="submit">Verificar código</button>
      </form>
      </div>
    </section>
  </main>
  @include('partials.cookies')
</body>
</html>
