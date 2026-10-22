<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>{{ $curso['titulo'] }} · MSEA</title>
  @include('partials.panel-assets')
  <link rel="stylesheet" href="{{ asset('css/estudiante.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/curso.css') }}" />
</head>
<body>
<main class="curso-page" id="contenido">
  <div class="curso-barra">
    <a class="btn btn--sec btn--sm" href="{{ url('/dashboard-estudiante#cursos') }}"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-flecha-izquierda"/></svg> Volver a mis cursos</a>
    <button class="theme-toggle" type="button" data-theme-toggle aria-label="Activar modo oscuro" aria-pressed="false"></button>
  </div>

  <header class="curso-head card">
    <span class="icon-badge"><svg class="icon icon-lg"><use href="{{ $iconos }}#i-cursos"/></svg></span>
    <div>
      <p class="eyebrow">{{ $curso['instrumento'] ?: 'Todos los instrumentos' }} · {{ $curso['nivel'] }}</p>
      <h1>{{ $curso['titulo'] }}</h1>
      @if($curso['descripcion'])<p>{{ $curso['descripcion'] }}</p>@endif
      <div class="upload-progress" role="progressbar" aria-label="Progreso del curso" aria-valuenow="{{ $progreso }}" aria-valuemin="0" aria-valuemax="100"><span data-ancho="{{ $progreso }}"></span></div>
      <p class="hint"><span id="curso-completadas">{{ $completadas }}</span> de {{ $total }} lecciones completadas</p>
    </div>
  </header>

  @php($siguiente = collect($modulos)->flatMap(fn ($m) => $m['lecciones'])->firstWhere('estado', '!=', 'completada'))

  @forelse($modulos as $modulo)
    <section class="modulo card">
      <h2>{{ $modulo['titulo'] }}</h2>
      @if($modulo['descripcion'])<p class="hint">{{ $modulo['descripcion'] }}</p>@endif

      @forelse($modulo['lecciones'] as $leccion)
        <details class="leccion {{ $leccion['estado'] === 'completada' ? 'completada' : '' }}" id="leccion-{{ $leccion['id'] }}" @if($siguiente && $siguiente['id'] === $leccion['id']) open @endif>
          <summary>
            <svg class="icon {{ $leccion['estado'] === 'completada' ? 'icon-green' : 'icon-muted' }}"><use href="{{ $iconos }}#i-{{ $leccion['estado'] === 'completada' ? 'check-circulo' : ($leccion['tipo'] === 'ritmo' ? 'ritmo' : ($leccion['tipo'] === 'afinacion' ? 'afinacion' : ($leccion['tipo'] === 'tecnica' ? 'tecnica' : 'teoria'))) }}"/></svg>
            <span>{{ $leccion['titulo'] }}</span>
            <span class="estado-tag {{ $leccion['estado'] === 'completada' ? 'calificada' : 'pendiente' }}">{{ $leccion['estado'] === 'completada' ? 'Completada' : '+'.$leccion['xp'].' XP' }}</span>
          </summary>

          <div class="leccion-cuerpo">
            @if($leccion['contenido'])
              <div class="leccion-texto">{!! nl2br(e($leccion['contenido'])) !!}</div>
            @endif

            @foreach($leccion['preguntas'] as $pregunta)
              <div class="pregunta" data-id="{{ $pregunta['id'] }}" data-resuelta="{{ $pregunta['resuelta'] ? '1' : '0' }}">
                <p class="tool-question">{{ $pregunta['pregunta'] }}</p>
                <div class="theory-options">
                  @foreach($pregunta['opciones'] as $opcion)
                    <button type="button" data-respuesta="{{ $opcion }}" @disabled($pregunta['resuelta'])>{{ $opcion }}</button>
                  @endforeach
                </div>
                <p class="hint pregunta-estado">{{ $pregunta['resuelta'] ? 'Respondida correctamente.' : '' }}</p>
              </div>
            @endforeach

            @if($leccion['estado'] !== 'completada')
              <button class="btn-msea btn-completar" type="button" data-id="{{ $leccion['id'] }}"><svg class="icon"><use href="{{ $iconos }}#i-check"/></svg> Marcar como completada</button>
              @if(count($leccion['preguntas']))<p class="hint">Responde bien todas las preguntas para completar la lección.</p>@endif
            @endif
          </div>
        </details>
      @empty
        <div class="empty-state"><svg class="icon"><use href="{{ $iconos }}#i-info"/></svg><strong>Este módulo aún no tiene lecciones.</strong></div>
      @endforelse
    </section>
  @empty
    <div class="empty-state"><svg class="icon"><use href="{{ $iconos }}#i-cursos"/></svg><strong>El curso todavía no tiene módulos publicados.</strong></div>
  @endforelse
</main>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="{{ asset('js/msea-ui.js') }}"></script>
<script src="{{ asset('js/curso-estudiante.js') }}"></script>
</body>
</html>
