<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>Panel del profesor · MSEA</title>
  @include('partials.panel-assets')
  <link rel="stylesheet" href="{{ asset('css/biblioteca.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/profesor.css') }}" />
</head>
<body>
@php($prof = $profesorData ?? [])
<x-panel.layout
  titulo="Panel del profesor"
  eyebrow="Docencia"
  rol="Profesor"
  :nombre="$prof['nombre'] ?? ''"
  :detalle="$prof['especialidad'] ?? ''"
  inicio="/dashboard-profesor"
  nav-etiqueta="Secciones del profesor">
  <x-slot:nav>
    <a href="#inicio" class="nav-item active" data-page="inicio" aria-current="page"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-inicio"/></svg> Inicio</a>
    <a href="#alumnos" class="nav-item" data-page="alumnos"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-usuarios"/></svg> Mis alumnos</a>
    <a href="#tareas" class="nav-item" data-page="tareas"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-tareas"/></svg> Tareas <span class="nav-badge hidden" id="badge-tareas" aria-label="entregas por revisar">0</span></a>
    <a href="#biblioteca" class="nav-item" data-page="biblioteca"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-afinacion"/></svg> Biblioteca técnica</a>
    <a href="#ejercicios" class="nav-item" data-page="ejercicios"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-ejercicios"/></svg> Mis ejercicios</a>
    <a href="#progreso" class="nav-item" data-page="progreso"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-progreso"/></svg> Progreso grupal</a>
    <a href="#mensajes" class="nav-item" data-page="mensajes"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-mensaje"/></svg> Mensajes</a>
  </x-slot:nav>
  <x-slot:acciones>
    <button class="btn" id="btn-nueva-tarea" type="button"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-mas"/></svg> Nueva tarea</button>
    <div class="notif-wrapper">
      <button class="btn btn--sec btn--icono notif-btn" id="notif-btn" aria-label="Notificaciones" aria-expanded="false" aria-controls="notif-panel" type="button"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-campana"/></svg><span class="notif-dot hidden" id="notif-dot"></span></button>
      <div class="notif-panel" id="notif-panel">
        <div class="notif-panel-header"><span class="notif-panel-title">Notificaciones</span></div>
        <ul class="notif-list" id="notif-list"></ul>
      </div>
    </div>
    <button class="header-avatar-btn" id="header-avatar-btn" aria-label="Mi perfil" type="button">
      <img hidden src="" alt="" class="header-avatar-img" id="header-avatar" />
      <span class="header-avatar-fallback"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-perfil"/></svg></span>
    </button>
  </x-slot:acciones>

    <section class="page-content active" id="page-inicio">
      <div class="dash-main">
        <div class="saludo">
          <h2 class="saludo__titulo" id="greeting-title">Bienvenido</h2>
          <p class="saludo__texto" id="greeting-sub"></p>
        </div>
        <div class="stats-row">
          <div class="card stat-card"><div class="stat-icon-wrap bg-verde"><svg class="icon icon-lg"><use href="{{ $iconos }}#i-usuarios"/></svg></div><div class="stat-info"><p class="stat-value" id="st-alumnos">0</p><p class="stat-label">Alumnos activos</p></div></div>
          <div class="card stat-card"><div class="stat-icon-wrap bg-naranja"><svg class="icon icon-lg"><use href="{{ $iconos }}#i-tareas"/></svg></div><div class="stat-info"><p class="stat-value" id="st-por-revisar">0</p><p class="stat-label">Entregas por revisar</p></div></div>
          <div class="card stat-card"><div class="stat-icon-wrap bg-azul"><svg class="icon icon-lg"><use href="{{ $iconos }}#i-check-circulo"/></svg></div><div class="stat-info"><p class="stat-value" id="st-completadas">0</p><p class="stat-label">Entregas calificadas</p></div></div>
          <div class="card stat-card"><div class="stat-icon-wrap bg-morado"><svg class="icon icon-lg"><use href="{{ $iconos }}#i-grupo"/></svg></div><div class="stat-info"><p class="stat-value" id="st-elencos">0</p><p class="stat-label">Elencos con alumnos tuyos</p></div></div>
        </div>

        <div class="row-mid">
          <div class="card entregas-card">
            <div class="card-header"><h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-subir"/></svg> Entregas por revisar</h3><a href="#tareas" class="card-link nav-trigger" data-page="tareas">Ver tareas</a></div>
            <ul class="entregas-list" id="entregas-list"></ul>
          </div>
          <div class="card atencion-card">
            <div class="card-header"><h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-alerta"/></svg> Requieren tu atención</h3><a href="#alumnos" class="card-link nav-trigger" data-page="alumnos">Ver alumnos</a></div>
            <p class="card-ayuda">Alumnos sin practicar hace 5 días o más. Un mensaje a tiempo los ayuda a volver.</p>
            <ul class="atencion-list" id="atencion-list"></ul>
          </div>
        </div>

        <div class="row-mid">
          <div class="card grupo-card">
            <div class="card-header"><h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-grafico"/></svg> Práctica de tu grupo (7 días)</h3><span class="card-dato" id="grupo-total"></span></div>
            <div class="grupo-barras" id="grupo-barras" role="img" aria-label="Minutos practicados por el grupo cada día"></div>
          </div>
          <div class="card alumnos-top-card">
            <div class="card-header"><h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-racha"/></svg> Más constantes esta semana</h3><a href="#alumnos" class="card-link nav-trigger" data-page="alumnos">Ver todos</a></div>
            <ul class="alumnos-top-list" id="alumnos-top-list"></ul>
          </div>
        </div>
      </div>
    </section>

    <section class="page-content" id="page-alumnos">
      <div class="dash-main">
        <div class="page-header"><h2 class="page-title">Mis alumnos</h2><p class="page-desc">Consulta el avance, la práctica y las dificultades de cada estudiante.</p></div>
        <div class="search-bar-wrap"><label class="sr-only" for="search-alumnos">Buscar alumno</label><input type="search" id="search-alumnos" class="search-bar" placeholder="Buscar por nombre, instrumento o elenco" /></div>
        <div class="alumnos-grid" id="alumnos-grid"></div>
      </div>
    </section>

    <section class="page-content" id="page-tareas">
      <div class="dash-main">
        <div class="page-header-row">
          <div><h2 class="page-title">Gestión de tareas</h2><p class="page-desc">Asigna tareas a un alumno o a un elenco, revisa las grabaciones y califica.</p></div>
          <button class="btn-msea" id="btn-nueva-tarea-lg" type="button"><svg class="icon"><use href="{{ $iconos }}#i-mas"/></svg> Nueva tarea</button>
        </div>
        <div class="filtros-tareas">
          <button class="filtro-btn active" data-filtro="todas" type="button">Todas</button>
          <button class="filtro-btn" data-filtro="por_revisar" type="button">Con entregas por revisar</button>
          <button class="filtro-btn" data-filtro="estudiante" type="button">Individuales</button>
          <button class="filtro-btn" data-filtro="elenco" type="button">De elenco</button>
        </div>
        <div class="tareas-tabla-wrap card">
          <table class="tareas-tabla" id="tareas-tabla">
            <thead><tr><th>Tarea</th><th>Asignada a</th><th>Fecha límite</th><th>Entregas</th><th>Acciones</th></tr></thead>
            <tbody id="tareas-tbody"></tbody>
          </table>
        </div>
      </div>
    </section>

    <section class="page-content" id="page-biblioteca">
      <div class="dash-main">
        <div class="page-header-row">
          <div><h2 class="page-title">Biblioteca técnica</h2><p class="page-desc">Escalas, ejercicios de técnica y métodos digitalizados, listos para asignar en violín, viola, violonchelo y contrabajo. Elige una, indica cómo tocarla y el estudiante la practica con la nota objetivo, el pentagrama y el metrónomo.</p></div>
        </div>
        <div class="filtros-tareas" id="biblioteca-categorias" aria-label="Tipo de ejercicio">
          <button class="filtro-btn active" data-categoria="escala" type="button"><svg class="icon"><use href="{{ $iconos }}#i-afinacion"/></svg> Escalas</button>
          <button class="filtro-btn" data-categoria="tecnica_mano_izquierda" type="button"><svg class="icon"><use href="{{ $iconos }}#i-tecnica"/></svg> Técnica de mano izquierda</button>
          <button class="filtro-btn" data-categoria="metodo" type="button"><svg class="icon"><use href="{{ $iconos }}#i-archivo"/></svg> Métodos digitalizados</button>
        </div>
        <div class="filtros-tareas" id="biblioteca-instrumentos" aria-label="Instrumento">
          <button class="filtro-btn active" data-instrumento="violin" type="button"><svg class="icon"><use href="{{ $iconos }}#i-violin"/></svg> Violín</button>
          <button class="filtro-btn" data-instrumento="viola" type="button"><svg class="icon"><use href="{{ $iconos }}#i-viola"/></svg> Viola</button>
          <button class="filtro-btn" data-instrumento="violonchelo" type="button"><svg class="icon"><use href="{{ $iconos }}#i-violonchelo"/></svg> Violonchelo</button>
          <button class="filtro-btn" data-instrumento="contrabajo" type="button"><svg class="icon"><use href="{{ $iconos }}#i-contrabajo"/></svg> Contrabajo</button>
        </div>
        <div class="filtros-tareas" id="biblioteca-filtros">
          <button class="filtro-btn active" data-dificultad="todas" type="button">Todas</button>
          <button class="filtro-btn" data-dificultad="facil" type="button">Fácil</button>
          <button class="filtro-btn" data-dificultad="intermedio" type="button">Intermedio</button>
          <button class="filtro-btn" data-dificultad="dificil" type="button">Difícil</button>
        </div>
        <div class="biblioteca-grid" id="biblioteca-grid"></div>
      </div>
    </section>

    <section class="page-content" id="page-ejercicios">
      <div class="dash-main">
        <div class="page-header"><h2 class="page-title">Mis ejercicios</h2><p class="page-desc">Los ejercicios que creaste al asignar tareas.</p></div>
        <div class="ejercicios-grid" id="ejercicios-grid"></div>
      </div>
    </section>

    <section class="page-content" id="page-progreso">
      <div class="dash-main">
        <div class="page-header"><h2 class="page-title">Progreso grupal</h2><p class="page-desc">XP promedio por instrumento, calculado con el mismo registro que ve cada estudiante.</p></div>
        <div class="progreso-full-grid" id="progreso-full-grid"></div>
      </div>
    </section>

    <section class="page-content" id="page-mensajes">
      <div class="dash-main">
        <div class="page-header"><h2 class="page-title">Mensajes</h2><p class="page-desc">Conversa con tus alumnos y con sus familias.</p></div>
        <div class="mensajes-layout">
          <form class="card msea-form" id="form-mensaje">
            <h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-enviar"/></svg> Nuevo mensaje</h3>
            <label>Para <select name="id_destinatario" id="mensaje-destino" required></select></label>
            <label>Asunto <input type="text" name="asunto" maxlength="150" /></label>
            <label>Mensaje <textarea name="contenido" required minlength="2" maxlength="3000"></textarea></label>
            <button class="btn-msea" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-enviar"/></svg> Enviar</button>
          </form>
          <div class="card"><h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-mensaje"/></svg> Conversaciones</h3><ul class="mensajes-lista" id="mensajes-lista"></ul></div>
        </div>
      </div>
    </section>
</x-panel.layout>

<div class="modal-overlay" id="modal-overlay"></div>

{{-- Nueva / editar tarea --}}
<div class="modal modal-ancho" id="modal-tarea" role="dialog" aria-modal="true" aria-labelledby="modal-tarea-titulo">
  <div class="modal-header"><h3 class="modal-title" id="modal-tarea-titulo">Nueva tarea</h3><button class="modal-close" data-cerrar type="button" aria-label="Cerrar"><svg class="icon"><use href="{{ $iconos }}#i-cerrar"/></svg></button></div>
  <form class="modal-body msea-form" id="form-tarea" novalidate>
    <fieldset class="tipo-tarea" id="tipo-tarea">
      <legend>Tipo de tarea</legend>
      <label class="tt-opcion"><input type="radio" name="clase_tarea" value="libre" checked /> <span><svg class="icon"><use href="{{ $iconos }}#i-tareas"/></svg> Tarea libre</span></label>
      <label class="tt-opcion"><input type="radio" name="clase_tarea" value="escala" /> <span><svg class="icon"><use href="{{ $iconos }}#i-afinacion"/></svg> Escala</span></label>
      <label class="tt-opcion"><input type="radio" name="clase_tarea" value="tecnica" /> <span><svg class="icon"><use href="{{ $iconos }}#i-tecnica"/></svg> Técnica o método</span></label>
    </fieldset>

    <div class="escala-config hidden" id="escala-config">
      <label>Escala
        <select id="esc-escala" name="id_biblioteca"></select>
      </label>
      <p class="hint" id="esc-instrumento"></p>
      <p class="hint" id="esc-info"></p>

      <div class="field-row-2">
        <fieldset class="esc-grupo"><legend>Octavas</legend><div class="esc-opciones" id="esc-octavas"></div></fieldset>
        <fieldset class="esc-grupo"><legend>Dirección</legend>
          <div class="esc-opciones">
            <label class="inline"><input type="radio" name="esc_direccion" value="subir_bajar" checked /> Sube y baja</label>
            <label class="inline"><input type="radio" name="esc_direccion" value="subir" /> Solo sube</label>
          </div>
        </fieldset>
      </div>

      <fieldset class="esc-grupo"><legend>Ritmo de cada nota</legend>
        <div class="esc-opciones">
          <label class="inline"><input type="radio" name="esc_ritmo_modo" value="uniforme" checked /> Igual en todas las notas</label>
          <label class="inline"><input type="radio" name="esc_ritmo_modo" value="patron" /> Ritmo propio</label>
        </div>
        <div class="field-row-2" id="esc-uniforme">
          <label>Repeticiones por nota <input type="number" id="esc-repeticiones" min="1" max="16" value="4" /></label>
          <label>Figura <select id="esc-figura"></select></label>
        </div>
        <div class="esc-presets" id="esc-presets" aria-label="Ritmos frecuentes"></div>
        <div class="esc-patron hidden" id="esc-patron">
          <p class="hint">Cada célula es el ritmo de una nota: la célula 1 va en la primera nota, la 2 en la segunda... Si hay menos células que notas, se repiten en orden.</p>
          <ol class="celdas" id="esc-celdas"></ol>
          <div class="paleta" id="esc-paleta" aria-label="Agregar figura a la célula elegida"></div>
          <div class="paleta" id="esc-celulas-rapidas" aria-label="Células frecuentes"></div>
          <button class="btn-msea secondary small" type="button" id="esc-agregar-celda"><svg class="icon"><use href="{{ $iconos }}#i-mas"/></svg> Agregar célula</button>
        </div>
      </fieldset>

      <div class="field-row-3">
        <label>Tempo (pulsos por minuto) <input type="number" id="esc-bpm" min="30" max="240" value="60" /></label>
        <label>Compás <select id="esc-compas"><option>2/4</option><option>3/4</option><option selected>4/4</option><option>2/2</option><option>6/8</option></select></label>
        <label>Tolerancia de afinación <select id="esc-tolerancia"><option value="5">±5 cents (exigente)</option><option value="10">±10 cents</option><option value="15">±15 cents</option><option value="20">±20 cents</option><option value="25">±25 cents (inicial)</option></select></label>
      </div>
      <label class="inline" id="esc-digitacion-campo"><input type="checkbox" id="esc-digitacion" checked /> Mostrar cuerda y dedo (primera posición)</label>

      <div class="esc-resumen">
        <p id="esc-resumen"></p>
        <button class="btn-msea secondary small" type="button" id="esc-probar"><svg class="icon"><use href="{{ $iconos }}#i-ver"/></svg> Probar como estudiante</button>
      </div>
    </div>

    <div class="escala-config hidden" id="tecnica-config">
      <label>Ejercicio
        <select id="tec-ejercicio"></select>
      </label>
      <p class="hint" id="tec-instrumento"></p>
      <p class="hint" id="tec-info"></p>
      <fieldset class="esc-grupo"><legend>Cómo se practica</legend>
        <div class="esc-opciones">
          <label class="inline"><input type="radio" name="tec_modo" value="velocidad" checked /> Velocidad: al tempo, subiendo de a poco</label>
          <label class="inline"><input type="radio" name="tec_modo" value="afinacion" /> Afinación: nota por nota, sin apuro</label>
        </div>
      </fieldset>
      <div class="field-row-3">
        <label>Tempo inicial <input type="number" id="tec-bpm" min="30" max="240" value="60" /></label>
        <label id="tec-objetivo-campo">Meta de tempo <input type="number" id="tec-objetivo" min="30" max="240" value="100" /></label>
        <label id="tec-paso-campo">Sube de a <input type="number" id="tec-paso" min="1" max="20" value="4" /></label>
      </div>
      <div class="field-row-3">
        <label id="tec-figura-campo">Figuras <select id="tec-figura"><option value="">Como está escrito</option><option value="negra">Negras</option><option value="corchea">Corcheas</option><option value="semicorchea">Semicorcheas</option></select></label>
        <label>Compás <select id="tec-compas"><option>2/4</option><option>3/4</option><option selected>4/4</option><option>2/2</option><option>6/8</option></select></label>
        <label>Tolerancia <select id="tec-tolerancia"><option value="5">±5 cents</option><option value="10" selected>±10 cents</option><option value="15">±15 cents</option><option value="20">±20 cents</option><option value="25">±25 cents</option></select></label>
      </div>
      <div class="esc-resumen">
        <p id="tec-resumen"></p>
        <button class="btn-msea secondary small" type="button" id="tec-probar"><svg class="icon"><use href="{{ $iconos }}#i-ver"/></svg> Probar como estudiante</button>
      </div>
    </div>

    <label id="campo-titulo">Título <span class="hint" id="titulo-hint"></span><input type="text" name="titulo" maxlength="150" placeholder="Ej: Estudio n.º 3 de Wohlfahrt" /></label>
    <fieldset class="asignacion">
      <legend>Asignar a</legend>
      <label class="inline"><input type="radio" name="destino" value="estudiante" checked /> Un alumno</label>
      <label class="inline"><input type="radio" name="destino" value="elenco" /> Un elenco completo</label>
    </fieldset>
    <label id="campo-alumno">Alumno <select name="id_estudiante" id="tarea-alumno"></select></label>
    <label id="campo-elenco" class="hidden">Elenco <select name="id_elenco" id="tarea-elenco"></select></label>
    <div class="field-row-2">
      <label id="campo-tipo">Tipo <select name="tipo" id="tarea-tipo"></select></label>
      <label>XP de recompensa <input type="number" name="xp_recompensa" min="5" max="200" value="30" /></label>
    </div>
    <label>Fecha límite <input type="date" name="fecha_limite" id="tarea-fecha" /></label>
    <label>Instrucciones <textarea name="descripcion" maxlength="2000" placeholder="Qué debe practicar y cómo grabarlo."></textarea></label>
    <label>Partitura, audio o material <span class="hint">PDF, imagen o audio. Máximo {{ (int) (config('msea.archivos.material_max_kb') / 1024) }} MB.</span>
      <input type="file" name="material" accept=".pdf,.png,.jpg,.jpeg,.webp,.mp3,.wav,.ogg,.m4a" />
    </label>
    <div class="upload-progress hidden" id="tarea-progreso"><span></span></div>
    <div class="modal-footer">
      <button class="btn-msea secondary" data-cerrar type="button">Cancelar</button>
      <button class="btn-msea" id="btn-guardar-tarea" type="submit">Guardar tarea</button>
    </div>
  </form>
</div>

{{-- Entregas de una tarea --}}
<div class="modal modal-ancho" id="modal-entregas" role="dialog" aria-modal="true" aria-labelledby="entregas-titulo">
  <div class="modal-header"><h3 class="modal-title" id="entregas-titulo">Entregas</h3><button class="modal-close" data-cerrar type="button" aria-label="Cerrar"><svg class="icon"><use href="{{ $iconos }}#i-cerrar"/></svg></button></div>
  <div class="modal-body"><ul class="entregas-detalle" id="entregas-detalle"></ul></div>
</div>

{{-- Progreso de un alumno --}}
<div class="modal" id="modal-progreso-alumno" role="dialog" aria-modal="true" aria-labelledby="progreso-alumno-nombre">
  <div class="modal-header"><h3 class="modal-title" id="progreso-alumno-nombre">Progreso</h3><button class="modal-close" data-cerrar type="button" aria-label="Cerrar"><svg class="icon"><use href="{{ $iconos }}#i-cerrar"/></svg></button></div>
  <div class="modal-body">
    <div class="progreso-mini-grid" id="progreso-resumen"></div>
    <h4 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-actividad"/></svg> Historial reciente</h4>
    <ul class="notif-list" id="progreso-historial-lista"></ul>
    <p class="nota-verificacion"><svg class="icon"><use href="{{ $iconos }}#i-info"/></svg> Afinador y ritmo se miden en el navegador del alumno; son datos de práctica no verificados.</p>
  </div>
</div>

{{-- Perfil del profesor --}}
<div class="perfil-overlay" id="perfil-overlay"></div>
<aside class="perfil-drawer" id="perfil-drawer" role="dialog" aria-modal="true" aria-labelledby="perfil-drawer-titulo">
  <div class="perfil-drawer-header"><h2 class="perfil-drawer-title" id="perfil-drawer-titulo">Mi perfil</h2><button class="perfil-close" id="perfil-close" type="button" aria-label="Cerrar"><svg class="icon"><use href="{{ $iconos }}#i-cerrar"/></svg></button></div>
  <div class="perfil-avatar-section">
    <div class="perfil-avatar-wrap">
      <img hidden src="" alt="Foto de perfil" class="perfil-avatar-img" id="perfil-avatar" />
      <div class="perfil-avatar-fallback" id="perfil-avatar-fallback"><svg class="icon icon-xl"><use href="{{ $iconos }}#i-perfil"/></svg></div>
      <label class="perfil-avatar-change" for="avatar-input" title="Cambiar foto"><svg class="icon"><use href="{{ $iconos }}#i-camara"/></svg></label>
      <input type="file" id="avatar-input" accept="image/png,image/jpeg,image/webp" />
    </div>
  </div>
  <form class="msea-form perfil-form" id="form-perfil" novalidate>
    <label>Nombres <input type="text" name="nombres" required maxlength="100" /></label>
    <label>Apellido paterno <input type="text" name="apellido_paterno" maxlength="100" /></label>
    <label>Apellido materno <input type="text" name="apellido_materno" maxlength="100" /></label>
    <label>Correo <input type="email" name="correo" required maxlength="100" /></label>
    <label class="hidden" id="campo-contrasena-actual">Contraseña actual <span class="hint">Necesaria para cambiar el correo.</span><input type="password" name="contrasena_actual" autocomplete="current-password" /></label>
    <label>Celular <input type="text" name="celular" maxlength="20" /></label>
    <p class="hint" id="pi-especialidad"></p>
    <button class="btn-msea" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-guardar"/></svg> Guardar perfil</button>
  </form>
  <form class="msea-form perfil-form" id="form-contrasena" novalidate>
    <h3 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-candado"/></svg> Cambiar contraseña</h3>
    <label>Contraseña actual <input type="password" name="contrasena_actual" required autocomplete="current-password" /></label>
    <label>Nueva contraseña <input type="password" name="contrasena" required minlength="8" autocomplete="new-password" /></label>
    <label>Repite la nueva contraseña <input type="password" name="contrasena_confirmation" required autocomplete="new-password" /></label>
    <button class="btn-msea secondary" type="submit">Actualizar contraseña</button>
  </form>
  <form action="{{ url('/logout') }}" method="POST" class="perfil-logout-form">@csrf<button type="submit" class="perfil-logout-btn"><svg class="icon"><use href="{{ $iconos }}#i-salir"/></svg> Cerrar sesión</button></form>
</aside>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script nonce="{{ $cspNonce ?? '' }}">
  window.MSEA_PROFESOR = @json($profesorData ?? []);
  window.MSEA_ALUMNOS = @json($alumnosData ?? []);
  window.MSEA_TAREAS = @json($tareasData ?? []);
  window.MSEA_ELENCOS = @json($elencosData ?? []);
  window.MSEA_EJERCICIOS = @json($ejerciciosData ?? []);
  window.MSEA_TIPOS = @json($tiposEjercicio ?? []);
  window.MSEA_ESCALAS = @json($bibliotecaEscalas ?? []);
  window.MSEA_TECNICA = @json($bibliotecaTecnica ?? []);
  window.MSEA_NOTIFICACIONES = @json($notificacionesData ?? []);
  window.MSEA_MENSAJES = @json($mensajesData ?? []);
  window.MSEA_PRACTICA_GRUPO = @json($practicaGrupoData ?? []);
</script>
<script src="{{ asset('js/msea-ui.js') }}"></script>
<script src="{{ asset('js/dashboard.profesor.js') }}"></script>
</body>
</html>
