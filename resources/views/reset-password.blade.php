<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  @php($seoTitulo = 'Nueva contraseña | MSEA')
  @include('partials.seo')
  @include('partials.auth-assets')
</head>
<body>
  <main class="auth-flow-layout">
    @include('partials.auth-brand-panel')
    <section class="auth-flow-form">
      <div class="reset-panel">
      <a href="{{ url('/forgot-password') }}" class="auth-back-button"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-flecha-izquierda"/></svg> Volver</a>
      <div class="reset-header">
        <img src="{{ asset('assets/img/logo-320.webp') }}" width="72" height="72" alt="" class="reset-logo" />
        <h1>Nueva contraseña</h1>
        <p>Crea una contraseña nueva para volver a ingresar a tu cuenta.</p>
      </div>

      @include('partials.flash-messages')

      <form action="{{ url('/reset-password') }}" method="POST" class="reset-form">
        @csrf
        <label for="contrasena">Nueva contraseña</label>
        <input
          type="password"
          id="contrasena"
          name="contrasena"
          placeholder="Mínimo 8 caracteres, letras y números"
          autocomplete="new-password"
          required
        />

        <label for="contrasena_confirmation">Confirmar contraseña</label>
        <input
          type="password"
          id="contrasena_confirmation"
          name="contrasena_confirmation"
          placeholder="Repite tu nueva contraseña"
          autocomplete="new-password"
          required
        />

        <button type="submit">Cambiar contraseña</button>
      </form>
      </div>
    </section>
  </main>
  @include('partials.cookies')
</body>
</html>
