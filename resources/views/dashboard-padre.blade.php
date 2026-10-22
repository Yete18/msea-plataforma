<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>Seguimiento familiar · MSEA</title>
  @include('partials.panel-assets')
  <link rel="stylesheet" href="{{ asset('css/padre.css') }}" />
</head>
<body>
@php
  $estados = ['pendiente' => 'Pendiente', 'entregada' => 'Entregada', 'devuelta' => 'Para corregir', 'calificada' => 'Calificada'];
  $primerHijo = $estudiantes->first()['id'] ?? null;
  $tareasPendientes = $estudiantes->sum(fn ($h) => collect($h['tareas'])->whereIn('estado', ['pendiente', 'devuelta'])->count());
@endphp
<x-panel.layout
  titulo="Seguimiento familiar"
  eyebrow="Familia MSEA"
  rol="Familia"
  :nombre="trim(($padre->nombres ?? 'Familia').' '.($padre->apellido_paterno ?? ''))"
  :detalle="$estudiantes->count().' '.($estudiantes->count() === 1 ? 'estudiante vinculado' : 'estudiantes vinculados')"
  inicio="/dashboard-padre"
  nav-etiqueta="Secciones del seguimiento">
  <x-slot:nav>
    <a href="#resumen-{{ $primerHijo }}" data-ancla="resumen" aria-current="page"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-inicio"/></svg> Resumen</a>
    <a href="#practica-{{ $primerHijo }}" data-ancla="practica"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-musica"/></svg> Práctica</a>
    <a href="#tareas-{{ $primerHijo }}" data-ancla="tareas"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-tareas"/></svg> Tareas @if($tareasPendientes)<span class="nav-badge" aria-label="pendientes">{{ $tareasPendientes }}</span>@endif</a>
    <a href="#logros-{{ $primerHijo }}" data-ancla="logros"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-logro"/></svg> Logros</a>
    <a href="#avisos"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-campana"/></svg> Avisos</a>
    <a href="#mensajes"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-mensaje"/></svg> Mensajes</a>
  </x-slot:nav>

  @if($estudiantes->count() > 1)
    <div class="hijos-selector" role="tablist" aria-label="Elegir estudiante">
      @foreach($estudiantes as $index => $hijo)
        <button class="hijo-pill" id="tab-hijo-{{ $hijo['id'] }}" data-hijo-id="{{ $hijo['id'] }}" type="button" role="tab"
          aria-selected="{{ $index === 0 ? 'true' : 'false' }}" aria-controls="hijo-section-{{ $hijo['id'] }}" tabindex="{{ $index === 0 ? '0' : '-1' }}">
          {{ explode(' ', $hijo['nombre'])[0] }}
        </button>
      @endforeach
    </div>
  @endif

  @forelse($estudiantes as $index => $hijo)
    @php
      $diasActivos = collect($hijo['semana_practica'])->where('minutos', '>', 0)->count();
      $minSemana = (int) round(collect($hijo['semana_practica'])->sum('minutos'));
      $primerNombre = explode(' ', $hijo['nombre'])[0];
      $porEntregar = collect($hijo['tareas'])->whereIn('estado', ['pendiente', 'devuelta']);
      $devuelta = $porEntregar->firstWhere('estado', 'devuelta');
      $proxima = $porEntregar->where('estado', 'pendiente')->sortBy(fn ($t) => $t['limite'] === 'Sin fecha' ? '9999' : implode('-', array_reverse(explode('/', $t['limite']))))->first();
      if ($devuelta) {
          $consejo = 'El profesor pidió corregir «'.$devuelta['titulo'].'». Revise la observación junto con '.$primerNombre.': puede volver a entregarla.';
      } elseif ($proxima && $proxima['limite'] !== 'Sin fecha') {
          $consejo = 'Recuérdele entregar «'.$proxima['titulo'].'» antes del '.$proxima['limite'].'.';
      } elseif ($diasActivos < 3) {
          $consejo = 'Acompáñelo de 10 a 15 minutos al día: en música, la constancia vale más que las sesiones largas.';
      } else {
          $consejo = '¡Excelente constancia! Un elogio por su esfuerzo lo motiva a seguir.';
      }
      $maximo = max(1, collect($hijo['semana_practica'])->max('minutos'));
    @endphp
    <section class="hijo" id="hijo-section-{{ $hijo['id'] }}" @if($estudiantes->count() > 1) role="tabpanel" aria-labelledby="tab-hijo-{{ $hijo['id'] }}" @endif @if($index > 0) hidden @endif>
      <div class="hijo__grid">
        <aside class="panel ficha" id="resumen-{{ $hijo['id'] }}" aria-label="Datos de {{ $primerNombre }}">
          <div class="ficha__foto">
            @if($hijo['foto'])
              <img src="{{ $hijo['foto'] }}" alt="" />
            @else
              <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-perfil"/></svg>
            @endif
          </div>
          <h2 class="ficha__nombre">{{ $hijo['nombre'] }}</h2>
          <p class="ficha__dato">{{ $hijo['instrumentos'] }}</p>
          <p class="ficha__dato">{{ $hijo['nivel_formativo'] ?: 'Sin nivel' }}{{ $hijo['elenco'] ? ' · '.$hijo['elenco'] : '' }}</p>
          <p class="ficha__dato">Profesor: {{ $hijo['profesor'] ?: 'sin asignar' }}</p>

          <dl class="ficha__cifras">
            <div><dt>Días de racha</dt><dd>{{ $hijo['racha'] }}</dd></div>
            <div><dt>Puesto en su elenco</dt><dd>{{ $hijo['posicion'] ? $hijo['posicion'].'.º' : '–' }}</dd></div>
            <div><dt>Nivel ({{ $hijo['xp_total'] }} XP)</dt><dd>{{ $hijo['nivel'] }}</dd></div>
            <div><dt>Minutos de práctica</dt><dd>{{ $hijo['total_minutos'] }}</dd></div>
          </dl>
          <p class="ficha__dato">{{ $hijo['lecciones_completadas'] }} {{ $hijo['lecciones_completadas'] === 1 ? 'lección completada' : 'lecciones completadas' }}</p>
          <a class="btn btn--sec btn--sm" href="{{ url('/dashboard-padre/hijos/'.$hijo['id'].'/reporte') }}"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-descargar"/></svg> Descargar reporte (CSV)</a>
        </aside>

        <div class="hijo__informe">
          <section class="panel semana" aria-labelledby="semana-{{ $hijo['id'] }}">
            <div class="semana__texto">
              <p class="eyebrow">Resumen de la semana</p>
              <h3 id="semana-{{ $hijo['id'] }}">
                @if($diasActivos)
                  {{ $primerNombre }} practicó {{ $diasActivos }} {{ $diasActivos === 1 ? 'día' : 'días' }} y sumó {{ $minSemana }} minutos.
                @else
                  {{ $primerNombre }} aún no registró práctica en los últimos 7 días.
                @endif
              </h3>
              <p class="semana__consejo"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-info"/></svg> {{ $consejo }}</p>
            </div>
            <ol class="semana__dias" aria-label="Días con práctica esta semana">
              @foreach($hijo['semana_practica'] as $dia)
                <li class="{{ $dia['minutos'] > 0 ? 'activo' : '' }}">
                  <span class="semana__marca" aria-hidden="true">@if($dia['minutos'] > 0)<svg class="icon"><use href="{{ $iconos }}#i-check"/></svg>@endif</span>
                  <span class="semana__dia" aria-hidden="true">{{ mb_substr($dia['dia'], 0, 1) }}</span>
                  <span class="sr-only">{{ $dia['dia'] }}: {{ $dia['minutos'] > 0 ? $dia['minutos'].' minutos' : 'sin práctica' }}</span>
                </li>
              @endforeach
            </ol>
          </section>

          <section class="panel" id="practica-{{ $hijo['id'] }}" aria-labelledby="practica-titulo-{{ $hijo['id'] }}">
            <h3 class="card-title" id="practica-titulo-{{ $hijo['id'] }}"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-grafico"/></svg> Minutos practicados en los últimos 7 días</h3>
            <div class="barras" role="img" aria-label="Minutos de práctica por día: {{ collect($hijo['semana_practica'])->map(fn ($d) => $d['dia'].' '.$d['minutos'])->implode(', ') }}">
              @foreach($hijo['semana_practica'] as $dia)
                <div class="barras__col">
                  <span class="barras__valor">{{ $dia['minutos'] > 0 ? $dia['minutos'].' min' : '' }}</span>
                  <div class="barras__pista"><span data-alto="{{ max(3, round($dia['minutos'] * 100 / $maximo)) }}"></span></div>
                  <span class="barras__dia">{{ $dia['dia'] }}</span>
                </div>
              @endforeach
            </div>
            <p class="hint">El tiempo lo mide el servidor entre el inicio y el fin de cada práctica.</p>
          </section>

          <section class="panel" id="tareas-{{ $hijo['id'] }}" aria-labelledby="tareas-titulo-{{ $hijo['id'] }}">
            <h3 class="card-title" id="tareas-titulo-{{ $hijo['id'] }}"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-tareas"/></svg> Tareas y calificaciones</h3>
            <ul class="tareas">
              @forelse($hijo['tareas'] as $tarea)
                <li class="tarea">
                  <div class="tarea__cabecera">
                    <strong>{{ $tarea['titulo'] }}</strong>
                    @if($tarea['estado'] === 'calificada')
                      <span class="tarea__nota">{{ $tarea['calificacion'] }}<small>/100</small></span>
                    @else
                      <span class="estado-tag {{ $tarea['estado'] }}">{{ $estados[$tarea['estado']] ?? $tarea['estado'] }}</span>
                    @endif
                  </div>
                  <p class="tarea__meta">Vence: {{ $tarea['limite'] }} · +{{ $tarea['xp'] }} XP</p>
                  @if($tarea['comentario_profesor'])
                    <p class="tarea__comentario"><span>Observación del profesor</span>{{ $tarea['comentario_profesor'] }}</p>
                  @endif
                </li>
              @empty
                <li class="empty-state"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-tareas"/></svg><strong>Aún no tiene tareas asignadas.</strong></li>
              @endforelse
            </ul>
          </section>

          <section class="panel" id="logros-{{ $hijo['id'] }}" aria-labelledby="logros-titulo-{{ $hijo['id'] }}">
            <h3 class="card-title" id="logros-titulo-{{ $hijo['id'] }}"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-logro"/></svg> Logros desbloqueados</h3>
            @if(count($hijo['logros']))
              <ul class="logros">
                @foreach($hijo['logros'] as $logro)
                  <li class="logro">
                    <span class="logro__icono" aria-hidden="true"><svg class="icon"><use href="{{ $iconos }}#i-logro"/></svg></span>
                    <strong>{{ $logro['nombre'] }}</strong>
                    <small>{{ $logro['desbloqueado_at'] }}</small>
                  </li>
                @endforeach
              </ul>
            @else
              <div class="empty-state"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-logro"/></svg><strong>Aún no desbloqueó logros.</strong><p>Se ganan practicando seguido y completando tareas.</p></div>
            @endif
          </section>
        </div>
      </div>
    </section>
  @empty
    <section class="panel">
      <div class="empty-state">
        <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-usuarios"/></svg>
        <h3>Sin estudiantes vinculados</h3>
        <p>Solicite a la dirección de MSEA que vincule a su hijo o hija con esta cuenta.</p>
      </div>
    </section>
  @endforelse

  <div class="comunicacion">
    <section class="panel" id="avisos" aria-labelledby="avisos-titulo">
      <h3 class="card-title" id="avisos-titulo"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-campana"/></svg> Avisos</h3>
      <ul class="avisos">
        @forelse($notificaciones as $n)
          <li class="{{ $n->leida_at ? 'leida' : 'nueva' }}">
            <strong>{{ $n->titulo }}</strong>
            <p>{{ $n->mensaje }}</p>
            <time>{{ \Illuminate\Support\Carbon::parse($n->created_at)->format('d/m/Y H:i') }}</time>
          </li>
        @empty
          <li class="empty-state"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-campana"/></svg><strong>No hay avisos nuevos.</strong></li>
        @endforelse
      </ul>
    </section>

    <section class="panel" id="mensajes" aria-labelledby="mensajes-titulo">
      <h3 class="card-title" id="mensajes-titulo"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-mensaje"/></svg> Mensajes con el profesor</h3>
      @if(count($contactos))
        <form action="{{ url('/mensajes') }}" method="POST" class="msea-form mensaje-form">
          @csrf
          <label>Para
            <select name="id_destinatario" required>
              @foreach($contactos as $c)<option value="{{ $c['id'] }}">{{ $c['nombre'] }}</option>@endforeach
            </select>
          </label>
          <label>Asunto <input type="text" name="asunto" maxlength="150" value="{{ old('asunto') }}" /></label>
          <label>Mensaje <textarea name="contenido" required minlength="2" maxlength="3000">{{ old('contenido') }}</textarea></label>
          <div class="form-actions"><button class="btn" type="submit"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-enviar"/></svg> Enviar mensaje</button></div>
        </form>
      @else
        <p class="hint">Cuando su hijo tenga un profesor asignado podrá escribirle desde aquí.</p>
      @endif
      @if(count($mensajes))
        <ul class="conversacion">
          @foreach($mensajes as $m)
            <li class="{{ $m['enviado'] ? 'enviado' : 'recibido' }}">
              <span class="conversacion__meta">{{ $m['enviado'] ? 'Para' : 'De' }} {{ $m['con'] }} · {{ \Illuminate\Support\Carbon::parse($m['fecha'])->format('d/m/Y H:i') }}</span>
              @if($m['asunto'])<strong>{{ $m['asunto'] }}</strong>@endif
              <p>{{ $m['contenido'] }}</p>
            </li>
          @endforeach
        </ul>
      @endif
    </section>
  </div>
</x-panel.layout>
<script defer src="{{ asset('js/dashboard-padre.js') }}"></script>
</body>
</html>
