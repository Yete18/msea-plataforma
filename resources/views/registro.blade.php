<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  @php($seoTitulo = 'Crear cuenta de estudiante | MSEA')
  @php($seoDescripcion = 'Crea tu cuenta de estudiante en MSEA: elige tu instrumento y empieza a practicar y ganar puntos.')
  @include('partials.seo')
  @include('partials.auth-assets')
</head>
<body>

<div class="registro-page">

  <!-- ============================
       PANEL IZQUIERDO (decorativo)
  ============================= -->
  <aside class="reg-panel">
    <div class="panel-particles" id="panel-particles"></div>

    <div class="panel-content">

      <a href="{{ url('/') }}" class="panel-logo">
        <img src="{{ asset('assets/img/logo-320.webp') }}" width="64" height="64" alt="" class="panel-logo-img" />
        <span class="panel-logo-text"><strong>Academia de Música MSEA</strong><small>Movimiento Sinfónico de El Alto</small></span>
      </a>

      <!-- Ilustración -->
      <div class="panel-illustration">
        <picture>
          <source type="image/webp" srcset="{{ asset('assets/img/img_registro_1.webp') }}" />
          <img src="{{ asset('assets/img/img_registro_1.jpg') }}" width="1200" height="800" alt="Jóvenes músicos de la orquesta MSEA con sus violines y violonchelos" class="panel-img" loading="lazy" decoding="async" />
        </picture>
        <div class="panel-img-fallback"><svg class="icon icon-xl" aria-hidden="true"><use href="{{ $iconos }}#i-musica"/></svg></div>
      </div>

      <!-- Pasos visuales en el panel -->
      <div class="panel-steps">
        <div class="panel-step active" id="pstep-1">
          <span class="ps-number">1</span>
          <span class="ps-label">Tus datos</span>
        </div>
        <div class="panel-step-line"></div>
        <div class="panel-step" id="pstep-2">
          <span class="ps-number">2</span>
          <span class="ps-label">Tu instrumento</span>
        </div>
      </div>

      <p class="panel-subtitle">¡Únete a la familia MSEA!</p>

      <!-- Íconos flotantes -->
      <span class="p-float pf-1"><svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-violin"/></svg></span>
      <span class="p-float pf-2"><svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-estrella"/></svg></span>
      <span class="p-float pf-3"><svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-musica"/></svg></span>
      <span class="p-float pf-4"><svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-violonchelo"/></svg></span>

    </div>
  </aside>

  <!-- ============================
       PANEL DERECHO — Formulario
  ============================= -->
  <main class="reg-form-section">

    <!-- Botón volver (móvil) -->
    <a href="{{ url('/login') }}" class="back-link"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-flecha-izquierda"/></svg> Volver</a>

    <div class="form-container">

      <!-- Encabezado + barra de progreso -->
      <div class="form-header">
        <h1 class="form-title" id="form-title">Crear cuenta</h1>
        <p class="form-subtitle" id="form-subtitle">Paso 1 de 2 — Cuéntanos quién eres</p>

        <!-- Barra de progreso -->
        <div class="progress-bar">
          <div class="progress-fill" id="progress-fill"></div>
        </div>
      </div>

      <!-- ══════════════════════════
           PASO 1: Datos personales
      ══════════════════════════ -->
      @include('partials.flash-messages')

      <form action="{{ url('/registro') }}" method="POST" id="registro-form" novalidate>
        @csrf
        @include('partials.antispam', ['idForm' => 'registro'])
        <input type="hidden" name="rol" value="estudiante" />

      <div class="form-step active" id="step-1">

        <!-- Nombre y apellido en fila -->
        <div class="field-row">
          <div class="field-group">
            <label class="field-label" for="nombre">
              <svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-perfil"/></svg> Nombre
            </label>
            <div class="input-wrapper">
              <input type="text" id="nombre" name="nombres"
                class="field-input" placeholder="Tu nombre"
                autocomplete="given-name" value="{{ old('nombres') }}" aria-describedby="error-nombre" required />
              <span class="input-status" id="status-nombre"></span>
            </div>
            <p class="field-error" id="error-nombre"></p>
          </div>

          <div class="field-group">
            <label class="field-label" for="apellido">
              <svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-perfil"/></svg> Apellido
            </label>
            <div class="input-wrapper">
              <input type="text" id="apellido" name="apellido_paterno"
                class="field-input" placeholder="Tu apellido"
                autocomplete="family-name" value="{{ old('apellido_paterno') }}" aria-describedby="error-apellido" required />
              <span class="input-status" id="status-apellido"></span>
            </div>
            <p class="field-error" id="error-apellido"></p>
          </div>
        </div>

        <!-- Email -->
        <div class="field-group">
          <label class="field-label" for="email">
            <svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-correo"/></svg> Correo electrónico
          </label>
          <div class="input-wrapper">
            <input type="email" id="email" name="correo"
              class="field-input" placeholder="correo@ejemplo.com"
              autocomplete="email" value="{{ old('correo') }}" aria-describedby="error-email" required />
            <span class="input-status" id="status-email"></span>
          </div>
          <p class="field-error" id="error-email"></p>
        </div>

        <!-- Botón siguiente -->
        <button type="button" class="btn-next" id="btn-next-1">
          Siguiente <svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-flecha-derecha"/></svg>
        </button>

      </div>
      <!-- FIN PASO 1 -->

      <!-- ══════════════════════════
           PASO 2: Instrumento y contraseña
      ══════════════════════════ -->
      <div class="form-step" id="step-2">

        <!-- Selector de instrumento -->
        <div class="field-group">
          <p class="field-label" id="instrumento-etiqueta">
            <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-musica"/></svg> ¿Qué instrumento tocas?
          </p>
          <div class="instrument-selector" id="instrument-selector" role="group" aria-labelledby="instrumento-etiqueta" aria-describedby="error-instrumento">
            <button type="button" class="instrument-btn" data-value="violin">
              <span class="inst-emoji"><svg class="icon icon-xl" aria-hidden="true"><use href="{{ $iconos }}#i-violin"/></svg></span>
              <span class="inst-name">Violín</span>
            </button>
            <button type="button" class="instrument-btn" data-value="viola">
              <span class="inst-emoji"><svg class="icon icon-xl" aria-hidden="true"><use href="{{ $iconos }}#i-viola"/></svg></span>
              <span class="inst-name">Viola</span>
            </button>
            <button type="button" class="instrument-btn" data-value="violonchelo">
              <span class="inst-emoji"><svg class="icon icon-xl" aria-hidden="true"><use href="{{ $iconos }}#i-violonchelo"/></svg></span>
              <span class="inst-name">Violonchelo</span>
            </button>
            <button type="button" class="instrument-btn" data-value="contrabajo">
              <span class="inst-emoji"><svg class="icon icon-xl" aria-hidden="true"><use href="{{ $iconos }}#i-contrabajo"/></svg></span>
              <span class="inst-name">Contrabajo</span>
            </button>
          </div>
          <input type="hidden" id="instrumento" name="instrumento" value="{{ old('instrumento') }}" />
          <p class="field-error" id="error-instrumento"></p>
        </div>

        <!-- Nivel -->
        <div class="field-group">
          <p class="field-label" id="nivel-etiqueta">
            <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-progreso"/></svg> ¿En qué nivel estás? <small>(la dirección lo confirma)</small>
          </p>
          <div class="level-selector" id="level-selector" role="group" aria-labelledby="nivel-etiqueta" aria-describedby="error-nivel">
            @foreach(($nivelesRegistro ?? []) as $nivelNombre)
              <button type="button" class="level-btn" data-value="{{ $nivelNombre }}">
                <span class="level-icon"><svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-graduacion"/></svg></span>
                <span class="level-name">{{ $nivelNombre }}</span>
              </button>
            @endforeach
          </div>
          <input type="hidden" id="nivel" name="nivel" value="{{ old('nivel') }}" />
          <p class="field-error" id="error-nivel"></p>
        </div>

        <!-- Contraseña -->
        <div class="field-group">
          <label class="field-label" for="password">
            <svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-candado"/></svg> Contraseña
          </label>
          <div class="input-wrapper">
            <input type="password" id="password" name="contrasena"
              class="field-input" placeholder="Mínimo 8 caracteres, letras y números"
              autocomplete="new-password" aria-describedby="strength-label error-password" required />
            <button type="button" class="toggle-password" id="toggle-pass1" aria-label="Mostrar contraseña"><svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-ver"/></svg></button>
          </div>
          <!-- Barra de fortaleza -->
          <div class="strength-bar" aria-hidden="true">
            <div class="strength-fill" id="strength-fill"></div>
          </div>
          <p class="strength-label" id="strength-label"></p>
          <p class="field-error" id="error-password"></p>
        </div>

        <!-- Confirmar contraseña -->
        <div class="field-group">
          <label class="field-label" for="confirm-password">
            <svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-candado"/></svg> Confirmar contraseña
          </label>
          <div class="input-wrapper">
            <input type="password" id="confirm-password" name="contrasena_confirmation"
              class="field-input" placeholder="Repite tu contraseña"
              autocomplete="new-password" aria-describedby="error-confirm" required />
            <button type="button" class="toggle-password" id="toggle-pass2" aria-label="Mostrar contraseña"><svg class="icon " aria-hidden="true"><use href="{{ $iconos }}#i-ver"/></svg></button>
          </div>
          <p class="field-error" id="error-confirm"></p>
        </div>

        <!-- Aceptación de la política de privacidad (menores: con acompañamiento de madre, padre o tutor) -->
        <div class="field-group consentimiento">
          <label class="consentimiento-label" for="acepta-privacidad">
            <input type="checkbox" id="acepta-privacidad" name="acepta_privacidad" value="1" aria-describedby="error-privacidad" required {{ old('acepta_privacidad') ? 'checked' : '' }} />
            <span>He leído la <a href="{{ url('/politica-de-privacidad') }}" target="_blank" rel="noopener">política de privacidad</a>
              y el <a href="{{ url('/aviso-legal') }}" target="_blank" rel="noopener">aviso legal</a>. Si soy menor de 18 años,
              me registro con el conocimiento de mi madre, padre o tutor.</span>
          </label>
          <p class="field-error" id="error-privacidad"></p>
        </div>

        <!-- Botones: atrás + registrar -->
        <div class="btn-row">
          <button type="button" class="btn-back" id="btn-back-2"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-flecha-izquierda"/></svg> Atrás</button>
          <button type="submit" class="btn-submit" id="btn-submit">
            <span class="btn-text">Crear mi cuenta</span>
            <span class="btn-loader" id="btn-loader" hidden>Creando…</span>
          </button>
        </div>


      </div>
      <!-- FIN PASO 2 -->

      </form>

      <!-- ══════════════════════════
           PASO 3: Éxito
      ══════════════════════════ -->
      <div class="form-step" id="step-success">
        <div class="success-screen">
          <div class="success-emoji"><svg class="icon icon-xl icon-green" aria-hidden="true"><use href="{{ $iconos }}#i-check-circulo"/></svg></div>
          <h2 class="success-title">¡Cuenta creada!</h2>
          <p class="success-msg">
            Te damos la bienvenida a MSEA, <strong id="success-name"></strong>.<br/>
            Ya puedes empezar a tocar y ganar puntos.
          </p>
          <a href="{{ url('/login') }}" class="btn-goto-login">Ir al inicio de sesión</a>
        </div>
      </div>
      <!-- FIN PASO ÉXITO -->

      <!-- Pie del formulario -->
      <div class="form-footer" id="form-footer">
        <p>¿Ya tienes cuenta? <a href="{{ url('/login') }}" class="login-link">Inicia sesión aquí</a></p>
      </div>
      @include('partials.enlaces-legales')

    </div>
  </main>

</div>

@include('partials.cookies')
<script src="{{ asset('js/msea-ui.js') }}"></script>
<script src="{{ asset('js/registro.js') }}"></script>
</body>
</html>
