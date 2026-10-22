<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  @php($seoTitulo = 'Iniciar sesión | MSEA')
  @php($seoDescripcion = 'Entra a tu cuenta de MSEA para practicar, ver tus tareas y seguir tu progreso musical.')
  @include('partials.seo')
  @include('partials.auth-assets')
</head>
<body>

  <div class="login-page">

    <!-- ============================
         PANEL IZQUIERDO (decorativo)
    ============================= -->
    @include('partials.auth-brand-panel')

    <!-- ============================
         PANEL DERECHO (formulario)
    ============================= -->
    <main class="login-form-section">

      <a href="{{ url('/') }}" class="back-link">
        <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-flecha-izquierda"/></svg> Volver
      </a>

      <div class="form-container">

        <!-- Encabezado del formulario -->
        <div class="form-header">
          <h1 class="form-title">Iniciar sesión</h1>
          <p class="form-subtitle">Ingresa tus datos para continuar</p>
        </div>

        <!-- FORMULARIO -->
        @include('partials.flash-messages')

        <form class="login-form" id="login-form" novalidate action="/login" method="POST">
          @csrf
          @include('partials.antispam', ['idForm' => 'login'])
          <!-- Campo usuario -->
          <div class="field-group">
            <label class="field-label" for="correo">
              <svg class="icon label-icon" aria-hidden="true"><use href="{{ $iconos }}#i-correo"/></svg> Correo electrónico
            </label>
            <div class="input-wrapper">
              <input
                type="email"
                id="correo"
                name="correo"
                class="field-input"
                placeholder="Tu correo"
                autocomplete="email"
                value="{{ old('correo') }}"
                aria-describedby="error-correo"
                required
              />
              <span class="input-status" id="status-correo"></span>
            </div>
            <p class="field-error" id="error-correo"></p>
          </div>

          <!-- Campo contraseña -->
          <div class="field-group">
            <label class="field-label" for="contrasena">
              <svg class="icon label-icon" aria-hidden="true"><use href="{{ $iconos }}#i-candado"/></svg> Contraseña
            </label>
            <div class="input-wrapper">
              <input
                type="password"
                id="contrasena"
                name="contrasena"
                class="field-input"
                placeholder="Tu contraseña"
                autocomplete="current-password"
                aria-describedby="error-password"
                required
              />
              <!-- Toggle mostrar/ocultar contraseña -->
              <button type="button" class="toggle-password" id="toggle-password" aria-label="Mostrar contraseña">
                <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-ver"/></svg>
              </button>
            </div>
            <p class="field-error" id="error-password"></p>
          </div>

          <!-- Olvidé mi contraseña -->
          <div class="form-options">
            <a href="{{ url('/forgot-password') }}" class="forgot-link" id="forgot-link">¿Olvidaste tu contraseña?</a>
          </div>
          
          <!-- Botón ingresar -->
          <button type="submit" class="btn-login" id="btn-login">
            <span class="btn-text"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-entrar"/></svg> Ingresar</span>
            <span class="btn-loader" id="btn-loader" hidden>Ingresando…</span>
          </button>


        </form>

        @if(config('services.google.client_id'))
          <div class="login-google" id="login-google" data-client-id="{{ config('services.google.client_id') }}">
            <div class="login-separador"><span>o</span></div>
            <div id="google-boton" class="google-boton" aria-label="Continuar con Google"></div>
            <p class="login-google-nota">Usa la cuenta de Google con el mismo correo registrado en MSEA.</p>
            <div class="form-alert" id="google-alert" role="alert" hidden></div>
          </div>
        @endif

        <!-- Pie del formulario -->
        <div class="form-footer">
          <p>¿Eres nuevo? <a href="{{ url('/registro') }}" class="register-link">Regístrate aquí</a></p>
        </div>
        @include('partials.enlaces-legales')

      </div>
    </main>

  </div><!-- fin .login-page -->

  @include('partials.cookies')
  <script src="{{ asset('js/msea-ui.js') }}"></script>
  <script src="{{ asset('js/login.js') }}"></script>
  @if(config('services.google.client_id'))
    <script src="{{ asset('js/google-login.js') }}"></script>
    <script src="https://accounts.google.com/gsi/client" async defer nonce="{{ $cspNonce ?? '' }}"></script>
  @endif
</body>
</html>
