<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>Mi panel · MSEA</title>
  @include('partials.panel-assets')
  <link rel="stylesheet" href="{{ asset('css/estudiante.css') }}" />
</head>
<body>

<x-panel.layout
  titulo="Mi panel"
  eyebrow="Estudiante"
  rol="Estudiante"
  :nombre="trim(session('nombre').' '.session('apellido_paterno'))"
  :detalle="($dashboardData['instrumento'] ?? '') ?: 'MSEA'"
  inicio="/dashboard-estudiante"
  nav-etiqueta="Secciones del estudiante">
  <x-slot:lateral>
    <div class="lateral-xp">
      <div class="lateral-xp__fila">
        <span>XP del nivel</span>
        <span class="lateral-xp__racha" title="Días seguidos practicando"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-racha"/></svg> <span id="s-streak-num">0</span></span>
      </div>
      <div class="s-xp-track" role="progressbar" aria-label="XP del nivel actual" aria-valuemin="0" aria-valuemax="100"><div class="s-xp-fill" id="s-xp-fill"></div></div>
      <div class="lateral-xp__fila"><span id="s-role"></span><strong id="s-xp-text">0 / 500</strong></div>
    </div>
  </x-slot:lateral>
  <x-slot:nav>
    <a href="#inicio" class="nav-item active" data-page="inicio" aria-current="page"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-inicio"/></svg> Inicio</a>
    <a href="#tareas" class="nav-item" data-page="tareas"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-tareas"/></svg> Mis tareas <span class="nav-badge hidden" id="badge-tareas">0</span></a>
    <a href="#cursos" class="nav-item" data-page="cursos"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-cursos"/></svg> Mis cursos</a>
    <a href="#ejercicios" class="nav-item" data-page="ejercicios"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-ejercicios"/></svg> Practicar</a>
    <a href="#progreso" class="nav-item" data-page="progreso"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-progreso"/></svg> Mi progreso</a>
    <a href="#ranking" class="nav-item" data-page="ranking"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-ranking"/></svg> Ranking</a>
    <a href="#logros" class="nav-item" data-page="logros"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-logro"/></svg> Logros</a>
    <a href="#mensajes" class="nav-item" data-page="mensajes"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-mensaje"/></svg> Mensajes</a>
    <a href="#perfil" class="nav-item" data-page="perfil"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-perfil"/></svg> Mi perfil</a>
  </x-slot:nav>
  <x-slot:acciones>
    <div class="notif-wrapper" id="notif-wrapper">
      <button class="btn btn--sec btn--icono notif-btn" id="notif-btn" aria-label="Notificaciones" aria-expanded="false" aria-controls="notif-panel" type="button">
        <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-campana"/></svg>
        <span class="notif-dot hidden" id="notif-dot"></span>
      </button>
      <div class="notif-panel" id="notif-panel" role="region" aria-label="Notificaciones">
        <div class="notif-panel-header">
          <span class="notif-panel-title">Notificaciones</span>
          <button class="notif-clear-btn" id="notif-clear-btn" type="button">Marcar leídas</button>
        </div>
        <ul class="notif-list" id="notif-list"></ul>
      </div>
    </div>
    @include('partials.menu-usuario', [
          'nombre' => trim(session('nombre').' '.session('apellido_paterno')),
          'detalle' => session('correo'),
          'avatarId' => 'header-avatar',
          'enlaces' => [['#perfil', 'perfil', 'Mi perfil', 'perfil'], ['#progreso', 'progreso', 'Mi progreso', 'progreso'], ['#logros', 'logro', 'Mis logros', 'logros']],
        ])
  </x-slot:acciones>

  <div class="saludo">
    <h2 class="saludo__titulo" id="greeting-title">Hola</h2>
    <p class="saludo__texto" id="greeting-sub"></p>
  </div>


      {{-- INICIO --}}
      <section class="page-view active" id="view-inicio">
        {{-- Aviso de racha en riesgo (lo muestra el JS solo si aplica) --}}
        <div class="aviso-racha hidden" id="aviso-racha" role="status">
          <span class="aviso-racha-icono"><svg class="icon"><use href="{{ $iconos }}#i-racha"/></svg></span>
          <div><strong id="aviso-racha-titulo">¿Unos minutos de música hoy?</strong><p id="aviso-racha-texto">Con poco tiempo ya cuenta.</p></div>
          <a href="#ejercicios" class="btn-msea nav-link-btn" data-page="ejercicios">Practicar ahora</a>
        </div>

        <div class="inicio-top">
          {{-- Nivel y XP --}}
          <div class="card tarjeta-nivel">
            <div class="tn-cabecera">
              <div>
                <p class="tn-eyebrow">Tu progreso</p>
                <p class="tn-nivel"><span id="tn-nivel">Nv. 1</span> <span class="tn-titulo" id="tn-titulo">Semilla musical</span></p>
              </div>
              <span class="tn-racha" id="tn-racha" title="Días seguidos practicando. Un día de descanso no la corta."><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-racha"/></svg> <span id="tn-racha-num">0</span> días</span>
            </div>
            <div class="tn-barra" role="progressbar" aria-label="XP del nivel" aria-valuemin="0" aria-valuemax="100" id="tn-barra"><div class="tn-relleno" id="tn-relleno"></div></div>
            <div class="tn-pie"><span id="tn-xp">0 / 500 XP</span><span id="tn-faltan">Próximo nivel en 500 XP</span></div>
            <div class="tn-mini">
              <div><strong data-contar="0" id="tn-total">0</strong><span>XP total</span></div>
              <div><strong id="tn-ranking">-</strong><span>Ranking</span></div>
              <div><strong data-contar="0" id="tn-practicas">0</strong><span>Prácticas</span></div>
            </div>
          </div>

          {{-- Meta diaria con anillo y semana --}}
          <div class="card tarjeta-meta">
            <div class="tm-cabecera">
              <p class="tn-eyebrow">Meta de hoy</p>
              <button type="button" class="tm-cambiar" id="tm-cambiar"><svg class="icon"><use href="{{ $iconos }}#i-editar"/></svg> Cambiar</button>
            </div>
            <div class="tm-cuerpo">
              <svg class="tm-anillo" viewBox="0 0 120 120" aria-hidden="true">
                <circle class="tm-anillo-fondo" cx="60" cy="60" r="50"/>
                <circle class="tm-anillo-valor" id="tm-anillo" cx="60" cy="60" r="50" pathLength="100" stroke-dasharray="0 100"/>
              </svg>
              <div class="tm-centro"><strong id="tm-minutos">0</strong><span id="tm-de">de 15 min</span></div>
              <div class="tm-texto">
                <p class="tm-estado" id="tm-estado">Practica 15 minutos para cumplir tu meta.</p>
                <p class="tm-sub" id="tm-sub"></p>
              </div>
            </div>
            <div class="tm-semana" id="tm-semana" aria-label="Práctica de esta semana"></div>
          </div>
        </div>

        {{-- Siguiente paso: la accion mas util ahora mismo --}}
        <div class="card banner-card siguiente-paso" id="siguiente-paso">
          <div class="banner-text">
            <p class="banner-tag"><svg class="icon"><use href="{{ $iconos }}#i-chispa"/></svg> <span id="sp-etiqueta">Tu siguiente paso</span></p>
            <h2 class="banner-title" id="sp-titulo">Practica hoy y suma XP</h2>
            <p class="sp-detalle" id="sp-detalle"></p>
            <a href="#ejercicios" class="btn-primary-sm nav-link-btn" data-page="ejercicios" id="sp-boton"><span id="sp-boton-texto">Empezar a practicar</span> <svg class="icon"><use href="{{ $iconos }}#i-flecha-derecha"/></svg></a>
          </div>
          <div class="banner-illustration">
            <img src="{{ asset('assets/img/paganini.webp') }}" width="512" height="512" alt="" class="banner-img" loading="lazy" decoding="async" />
          </div>
        </div>

        {{-- Accesos rapidos a la practica --}}
        <div class="modulos-rapidos">
          <button type="button" class="modulo-rapido azul" data-practicar="afinacion"><span class="mr-icono"><svg class="icon"><use href="{{ $iconos }}#i-afinacion"/></svg></span><strong>Afinación</strong><small>Ajusta tu entonación</small></button>
          <button type="button" class="modulo-rapido naranja" data-practicar="ritmo"><span class="mr-icono"><svg class="icon"><use href="{{ $iconos }}#i-ritmo"/></svg></span><strong>Ritmo</strong><small>Metrónomo y pulso</small></button>
          <button type="button" class="modulo-rapido verde" data-practicar="tecnica"><span class="mr-icono"><svg class="icon"><use href="{{ $iconos }}#i-tecnica"/></svg></span><strong>Técnica</strong><small>Sesión cronometrada</small></button>
          <button type="button" class="modulo-rapido oscuro" data-practicar="teoria"><span class="mr-icono"><svg class="icon"><use href="{{ $iconos }}#i-teoria"/></svg></span><strong>Teoría</strong><small>Preguntas con XP</small></button>
        </div>

        <div class="row-bottom">
          <div class="card tareas-card">
            <div class="card-header">
              <h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-tareas"/></svg> Tareas pendientes</h3>
              <a href="#tareas" class="card-link nav-link-btn" data-page="tareas">Ver todas</a>
            </div>
            <ul class="tareas-list" id="tareas-list"></ul>
          </div>

          <div class="card ejercicio-card">
            <div class="card-header">
              <h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-cursos"/></svg> Continúa tu curso</h3>
            </div>
            <div class="ejercicio-body" id="curso-destacado"></div>
          </div>

          <div class="card ranking-card">
            <div class="card-header">
              <h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-ranking"/></svg> Ranking</h3>
              <a href="#ranking" class="card-link nav-link-btn" data-page="ranking">Ver completo</a>
            </div>
            <ul class="ranking-list" id="ranking-list"></ul>
          </div>
        </div>

        <div class="row-logros">
          <div class="card logros-card">
            <div class="card-header">
              <h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-logro"/></svg> Mis logros</h3>
              <a href="#logros" class="card-link nav-link-btn" data-page="logros">Ver todos</a>
            </div>
            <div class="logros-grid" id="logros-grid"></div>
          </div>
        </div>
      </section>

      {{-- TAREAS --}}
      <section class="page-view" id="view-tareas">
        <div class="page-header-inner">
          <h2 class="page-inner-title">Mis tareas</h2>
          <p class="page-inner-sub">Tareas de tu profesor, para ti o para tu elenco. Entrega tu grabación y revisa la calificación.</p>
        </div>
        <div class="ejercicios-filtros" role="group" aria-label="Filtrar tareas">
          <button class="filtro-btn active" data-filtro-tarea="todas" type="button" aria-pressed="true">Todas</button>
          <button class="filtro-btn" data-filtro-tarea="pendiente" type="button" aria-pressed="false">Pendientes</button>
          <button class="filtro-btn" data-filtro-tarea="devuelta" type="button" aria-pressed="false">Para corregir</button>
          <button class="filtro-btn" data-filtro-tarea="entregada" type="button" aria-pressed="false">Entregadas</button>
          <button class="filtro-btn" data-filtro-tarea="calificada" type="button" aria-pressed="false">Calificadas</button>
        </div>
        <div class="tareas-full-grid" id="tareas-full-grid"></div>
      </section>

      {{-- CURSOS --}}
      <section class="page-view" id="view-cursos">
        <div class="page-header-inner">
          <h2 class="page-inner-title">Mis cursos</h2>
          <p class="page-inner-sub">Avanza lección por lección. Tu progreso se guarda y puedes retomarlo cuando quieras.</p>
        </div>
        <div class="cursos-grid" id="cursos-grid"></div>
      </section>

      {{-- PRACTICAR --}}
      <section class="page-view" id="view-ejercicios">
        <div class="page-header-inner">
          <h2 class="page-inner-title">Practicar</h2>
          <p class="page-inner-sub">El tiempo de práctica lo mide el servidor. Hoy puedes ganar hasta <strong id="tope-diario">0</strong> XP practicando.</p>
        </div>

        <div class="msea-tools-grid">
          <section class="card msea-tool-card" aria-labelledby="titulo-afinador">
            <div class="card-header">
              <h3 class="card-title" id="titulo-afinador"><svg class="icon"><use href="{{ $iconos }}#i-afinacion"/></svg> Afinador</h3>
              <select id="tuner-instrumento" aria-label="Instrumento a afinar" class="mini-select">
                <option value="violin">Violín</option>
                <option value="viola">Viola</option>
                <option value="violonchelo">Violonchelo</option>
                <option value="contrabajo">Contrabajo</option>
              </select>
            </div>
            <div class="tuner-display">
              <strong id="tuner-note">--</strong>
              <span id="tuner-frequency">Pulsa iniciar y permite el uso del micrófono.</span>
              <div class="tuner-meter" aria-hidden="true"><div id="tuner-needle"></div></div>
              <div class="tuner-cuerdas" id="tuner-cuerdas"></div>
            </div>
            <p class="nota-verificacion"><svg class="icon"><use href="{{ $iconos }}#i-info"/></svg> El navegador estima la nota con el micrófono. Es una guía de práctica, no una evaluación oficial.</p>
            <button class="btn-ejercicio" id="btn-tuner" type="button">Iniciar afinador</button>
          </section>

          <section class="card msea-tool-card" aria-labelledby="titulo-ritmo">
            <div class="card-header">
              <h3 class="card-title" id="titulo-ritmo"><svg class="icon"><use href="{{ $iconos }}#i-ritmo"/></svg> Metrónomo y ritmo</h3>
              <span class="ecf-chip">4/4</span>
            </div>
            <div class="metronome-box">
              <label class="sr-only" for="metro-bpm">Tempo en BPM</label>
              <input type="range" min="40" max="180" value="80" id="metro-bpm" />
              <strong><span id="metro-bpm-text">80</span> BPM</strong>
              <div class="beat-dots" id="beat-dots" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
              <button type="button" class="btn-msea secondary" id="btn-golpe" disabled>Toca aquí (o la barra espaciadora) en cada pulso</button>
              <p class="tool-muted" id="ritmo-resultado">Inicia el metrónomo y marca los pulsos para medir tu precisión.</p>
            </div>
            <button class="btn-ejercicio" id="btn-metro" type="button">Iniciar metrónomo</button>
          </section>

          <section class="card msea-tool-card" aria-labelledby="titulo-teoria">
            <div class="card-header">
              <h3 class="card-title" id="titulo-teoria"><svg class="icon"><use href="{{ $iconos }}#i-teoria"/></svg> Teoría musical</h3>
              <span class="ecf-chip" id="teoria-contador"></span>
            </div>
            <div id="theory-box"></div>
          </section>

          <section class="card msea-tool-card" aria-labelledby="titulo-sesion">
            <div class="card-header">
              <h3 class="card-title" id="titulo-sesion"><svg class="icon"><use href="{{ $iconos }}#i-reloj"/></svg> Sesión de práctica</h3>
            </div>
            <div class="practica-libre">
              <label class="sr-only" for="practica-tipo">Tipo de práctica</label>
              <select id="practica-tipo" class="mini-select">
                <option value="tecnica">Técnica (escalas, arco)</option>
                <option value="libre">Repertorio libre</option>
              </select>
              <strong class="practica-reloj" id="practica-reloj">00:00</strong>
              <p class="tool-muted">Toca tu instrumento y detén el reloj al terminar. Mínimo 30 segundos para sumar XP.</p>
            </div>
            <button class="btn-ejercicio" id="btn-practica" type="button">Iniciar práctica</button>
          </section>
        </div>

        <h3 class="subtitulo-seccion">Ejercicios de la biblioteca</h3>
        <div class="ejercicios-grid" id="ejercicios-grid"></div>
      </section>

      {{-- PROGRESO --}}
      <section class="page-view" id="view-progreso">
        <div class="page-header-inner">
          <h2 class="page-inner-title">Mi progreso</h2>
          <p class="page-inner-sub">Todo tu XP sale de un único registro: lo mismo que ven tu profesor y tu familia.</p>
        </div>
        <div class="progreso-grid">
          <div class="card progreso-stats-card">
            <div class="card-header"><h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-grafico"/></svg> Mis estadísticas</h3></div>
            <div class="progreso-stats-list" id="progreso-stats"></div>
          </div>
        </div>
        <div class="card actividad-card">
          <div class="card-header"><h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-actividad"/></svg> Historial de XP</h3></div>
          <ul class="actividad-list" id="actividad-list"></ul>
        </div>
      </section>

      {{-- RANKING --}}
      <section class="page-view" id="view-ranking">
        <div class="page-header-inner">
          <h2 class="page-inner-title">Ranking</h2>
          <p class="page-inner-sub">Tus compañeros de elenco, ordenados por XP. Cada práctica suma.</p>
        </div>
        <div class="ranking-full-wrap">
          <div class="podio" id="podio"></div>
          <div class="card ranking-full-card"><ul class="ranking-full-list" id="ranking-full-list"></ul></div>
        </div>
      </section>

      {{-- LOGROS --}}
      <section class="page-view" id="view-logros">
        <div class="page-header-inner">
          <h2 class="page-inner-title">Mis logros</h2>
          <p class="page-inner-sub">Cada logro se desbloquea una sola vez y su bono de XP también.</p>
        </div>
        <div class="logros-stats-row">
          <div class="card logro-stat-mini"><span class="lsm-emoji"><svg class="icon icon-gold"><use href="{{ $iconos }}#i-logro"/></svg></span><p class="lsm-val" id="logros-desbloqueados">0</p><p class="lsm-label">Desbloqueados</p></div>
          <div class="card logro-stat-mini"><span class="lsm-emoji"><svg class="icon icon-muted"><use href="{{ $iconos }}#i-candado"/></svg></span><p class="lsm-val" id="logros-bloqueados">0</p><p class="lsm-label">Por desbloquear</p></div>
          <div class="card logro-stat-mini"><span class="lsm-emoji"><svg class="icon"><use href="{{ $iconos }}#i-grafico"/></svg></span><p class="lsm-val" id="logros-porcentaje">0%</p><p class="lsm-label">Completado</p></div>
        </div>
        <div class="card"><div class="logros-grid-full" id="logros-grid-full"></div></div>
      </section>

      {{-- MENSAJES --}}
      <section class="page-view" id="view-mensajes">
        <div class="page-header-inner">
          <h2 class="page-inner-title">Mensajes</h2>
          <p class="page-inner-sub">Escribe a tu profesor. Solo puedes enviar mensajes a tu profesor asignado.</p>
        </div>
        <div class="mensajes-layout">
          <form class="card msea-form" id="form-mensaje">
            <h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-enviar"/></svg> Nuevo mensaje</h3>
            <label>Para
              <select name="id_destinatario" id="mensaje-destino" required></select>
            </label>
            <label>Asunto
              <input type="text" name="asunto" maxlength="150" />
            </label>
            <label>Mensaje
              <textarea name="contenido" required minlength="2" maxlength="3000"></textarea>
            </label>
            <button class="btn-msea" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-enviar"/></svg> Enviar</button>
          </form>
          <div class="card">
            <h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-mensaje"/></svg> Conversaciones</h3>
            <ul class="mensajes-lista" id="mensajes-lista"></ul>
          </div>
        </div>
      </section>

      {{-- PERFIL --}}
      <section class="page-view" id="view-perfil">
        <div class="page-header-inner">
          <h2 class="page-inner-title">Mi perfil</h2>
          <p class="page-inner-sub">Tu información, tu foto y tu contraseña.</p>
        </div>

        <div class="perfil-layout">
          <div class="card perfil-avatar-card">
            <div class="perfil-avatar-wrap">
              <div class="perfil-avatar-circle" id="perfil-avatar-circle">
                <img hidden src="" alt="Foto de perfil" class="perfil-avatar-img" id="perfil-avatar-img" />
                <div class="perfil-avatar-fallback" id="perfil-avatar-fallback"><svg class="icon icon-xl"><use href="{{ $iconos }}#i-perfil"/></svg></div>
                <button class="perfil-avatar-overlay" id="perfil-avatar-overlay" title="Cambiar foto" type="button">
                  <span class="pao-icon"><svg class="icon"><use href="{{ $iconos }}#i-camara"/></svg></span>
                  <span class="pao-text">Cambiar foto</span>
                </button>
              </div>
              <input type="file" id="avatar-file-input" accept="image/png,image/jpeg,image/webp" hidden />
            </div>

            <h3 class="perfil-nombre-big" id="perfil-nombre-big"></h3>
            <p class="perfil-rol-big" id="perfil-rol-big"></p>

            <div class="perfil-badges-row">
              <div class="perfil-badge-item naranja"><span class="pbi-val" id="perfil-racha-val">0</span><span class="pbi-label">Racha</span></div>
              <div class="perfil-badge-item verde"><span class="pbi-val" id="perfil-pts-val">0</span><span class="pbi-label">XP</span></div>
              <div class="perfil-badge-item azul"><span class="pbi-val" id="perfil-rank-val">-</span><span class="pbi-label">Ranking</span></div>
            </div>

            <div class="perfil-xp-wrap">
              <div class="perfil-xp-labels">
                <span id="perfil-nivel-actual">Nivel 1</span>
                <span id="perfil-xp-text">0 / 500 XP</span>
                <span id="perfil-nivel-siguiente">Nivel 2</span>
              </div>
              <div class="perfil-xp-track"><div class="perfil-xp-fill" id="perfil-xp-fill"></div></div>
              <p class="perfil-xp-hint" id="perfil-xp-hint"></p>
            </div>
          </div>

          <div class="perfil-info-col">
            <div class="card perfil-info-card">
              <div class="card-header">
                <h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-perfil"/></svg> Información personal</h3>
                <button class="btn-edit-info" id="btn-edit-info" type="button"><svg class="icon"><use href="{{ $iconos }}#i-editar"/></svg> Editar</button>
              </div>

              <div class="info-view" id="info-view">
                <div class="info-row"><span class="info-label">Nombre completo</span><span class="info-val" id="info-nombre"></span></div>
                <div class="info-row"><span class="info-label">Correo electrónico</span><span class="info-val" id="info-email"></span></div>
                <div class="info-row"><span class="info-label">Carnet de identidad</span><span class="info-val" id="info-ci"></span></div>
                <div class="info-row"><span class="info-label">Celular</span><span class="info-val" id="info-celular"></span></div>
                <div class="info-row"><span class="info-label">Dirección</span><span class="info-val" id="info-direccion"></span></div>
                <div class="info-row"><span class="info-label">Fecha de nacimiento</span><span class="info-val" id="info-fecha-nacimiento"></span></div>
                <div class="info-row"><span class="info-label">Instrumento</span><span class="info-val" id="info-instrumento"></span></div>
                <div class="info-row"><span class="info-label">Nivel formativo</span><span class="info-val" id="info-nivel"></span></div>
                <div class="info-row"><span class="info-label">Elenco</span><span class="info-val" id="info-sede"></span></div>
                <div class="info-row"><span class="info-label">Profesor asignado</span><span class="info-val" id="info-profesor"></span></div>
                <div class="info-row"><span class="info-label">Miembro desde</span><span class="info-val" id="info-miembro-desde"></span></div>
              </div>

              <form class="info-edit msea-form hidden" id="info-edit" novalidate>
                <label>Nombres <input type="text" name="nombres" id="edit-nombres" required maxlength="100" /></label>
                <label>Apellido paterno <input type="text" name="apellido_paterno" id="edit-apellido-paterno" maxlength="100" /></label>
                <label>Apellido materno <input type="text" name="apellido_materno" id="edit-apellido-materno" maxlength="100" /></label>
                <label>Correo electrónico <input type="email" name="correo" id="edit-email" required maxlength="100" /></label>
                <label class="hidden" id="campo-contrasena-actual">Contraseña actual <span class="hint">Necesaria para cambiar el correo.</span>
                  <input type="password" name="contrasena_actual" id="edit-contrasena-actual" autocomplete="current-password" />
                </label>
                <label>Celular <input type="tel" name="celular" id="edit-celular" maxlength="20" autocomplete="tel" /></label>
                <p class="hint">El carnet, la dirección y la fecha de nacimiento los corrige la dirección de MSEA. Si alguno está mal, avisa a tu profesor o a tu familia.</p>
                <div class="edit-actions">
                  <button class="btn-msea" id="btn-save-info" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-guardar"/></svg> Guardar cambios</button>
                  <button class="btn-msea secondary" id="btn-cancel-info" type="button">Cancelar</button>
                </div>
              </form>
            </div>

            <form class="card msea-form" id="form-contrasena" novalidate>
              <h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-candado"/></svg> Cambiar contraseña</h3>
              <label>Contraseña actual <input type="password" name="contrasena_actual" autocomplete="current-password" required /></label>
              <label>Nueva contraseña <span class="hint">Mínimo 8 caracteres, con letras y números.</span>
                <input type="password" name="contrasena" autocomplete="new-password" required minlength="8" />
              </label>
              <label>Repite la nueva contraseña <input type="password" name="contrasena_confirmation" autocomplete="new-password" required /></label>
              <button class="btn-msea" type="submit">Actualizar contraseña</button>
              <p class="hint">Al cambiarla se cerrarán tus sesiones en otros dispositivos.</p>
            </form>
          </div>
        </div>
      </section>

</x-panel.layout>

{{-- Navegacion inferior en movil: lo importante a un toque y el menu completo en "Mas". --}}
<nav class="nav-inferior" aria-label="Navegación rápida">
  <a href="#inicio" data-page="inicio" class="nav-link-btn activo"><svg class="icon"><use href="{{ $iconos }}#i-inicio"/></svg>Inicio</a>
  <a href="#tareas" data-page="tareas" class="nav-link-btn"><svg class="icon"><use href="{{ $iconos }}#i-tareas"/></svg>Tareas<span class="burbuja hidden" id="burbuja-tareas"></span></a>
  <a href="#ejercicios" data-page="ejercicios" class="nav-link-btn"><svg class="icon"><use href="{{ $iconos }}#i-ejercicios"/></svg>Practicar</a>
  <a href="#logros" data-page="logros" class="nav-link-btn"><svg class="icon"><use href="{{ $iconos }}#i-logro"/></svg>Logros</a>
  <button type="button" id="nav-inferior-mas" aria-label="Abrir menú completo"><svg class="icon"><use href="{{ $iconos }}#i-lista"/></svg>Más</button>
</nav>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<div class="dialogo-fondo hidden" id="modal-foto">
  <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="titulo-modal-foto">
    <p class="modal-title" id="titulo-modal-foto">¿Usar esta foto?</p>
    <img src="" alt="Vista previa" class="modal-preview" id="modal-preview" />
    <div class="modal-actions">
      <button class="btn-msea" id="btn-foto-confirm" type="button"><svg class="icon"><use href="{{ $iconos }}#i-check"/></svg> Usar esta foto</button>
      <button class="btn-msea secondary" id="btn-foto-cancel" type="button">Cancelar</button>
    </div>
  </div>
</div>

<div class="dialogo-fondo hidden" id="modal-entrega">
  <div class="modal-box modal-entrega" role="dialog" aria-modal="true" aria-labelledby="entrega-tarea-titulo">
    <p class="modal-title"><svg class="icon"><use href="{{ $iconos }}#i-subir"/></svg> Entregar tarea</p>
    <h3 id="entrega-tarea-titulo"></h3>
    <p class="hint" id="entrega-devuelta"></p>
    <form class="msea-form" id="form-entrega" novalidate>
      <div class="recorder" id="recorder">
        <div class="recorder-row">
          <button type="button" class="btn-msea secondary" id="btn-grabar"><svg class="icon"><use href="{{ $iconos }}#i-microfono"/></svg> Grabar desde el micrófono</button>
          <span class="recorder-time" id="recorder-time">00:00</span>
        </div>
        <audio id="recorder-audio" controls hidden></audio>
        <p class="hint">O sube un archivo de audio (mp3, wav, ogg, webm o m4a; máximo {{ (int) (config('msea.archivos.audio_max_kb') / 1024) }} MB).</p>
        <input type="file" name="archivo" id="entrega-archivo" accept="audio/*" />
      </div>
      <label>Mensaje para tu profesor
        <textarea name="comentario_estudiante" id="entrega-comentario" maxlength="1000" placeholder="Cuéntale cómo te fue practicando."></textarea>
      </label>
      <div class="upload-progress hidden" id="entrega-progreso" aria-hidden="true"><span></span></div>
      <div class="modal-actions">
        <button class="btn-msea" id="btn-entrega-submit" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-enviar"/></svg> Enviar entrega</button>
        <button class="btn-msea secondary" id="btn-entrega-cancel" type="button">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
  window.MSEA_ESTUDIANTE = @json($dashboardData ?? []);
  window.MSEA_RANKING = @json($rankingData ?? []);
  window.MSEA_TAREAS = @json($tareasData ?? []);
  window.MSEA_EJERCICIOS = @json($ejerciciosData ?? []);
  window.MSEA_LOGROS = @json($logrosData ?? []);
  window.MSEA_ACTIVIDAD = @json($actividadData ?? []);
  window.MSEA_NOTIFICACIONES = @json($notificacionesData ?? []);
  window.MSEA_CURSOS = @json($cursosData ?? []);
  window.MSEA_TEORIA = @json($teoriaData ?? []);
  window.MSEA_MENSAJES = @json($mensajesData ?? []);
  window.MSEA_CONTACTOS = @json($contactosData ?? []);
</script>
<script src="{{ asset('js/msea-ui.js') }}"></script>
<script src="{{ asset('js/dashboard-estudiante.js') }}"></script>
</body>
</html>
