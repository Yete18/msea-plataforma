@extends('layouts.admin', ['activo' => 'panel'])
@section('titulo', 'Centralizador general')
@section('eyebrow', 'Dirección · Panel administrativo')
@section('acciones')
  <a href="{{ url('/dashboard-admin/profesores#nuevo-profesor') }}" class="btn"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-mas"/></svg> Registrar profesor</a>
@endsection

@section('contenido')
@php($pulso = $pulsoData)

{{-- 1. Pulso de la semana ------------------------------------------------ --}}
<section class="bloque pulso" aria-labelledby="pulso-titulo">
  <div class="pulso__kpis">
    <h2 id="pulso-titulo" class="sr-only">Pulso de la semana</h2>
    <article class="kpi kpi--destacado">
      <p class="kpi__etiqueta">Practicaron esta semana</p>
      <p class="kpi__valor">{{ $pulso['practicaronSemana'] }}<small>de {{ $pulso['estudiantesActivos'] }}</small></p>
      <div class="barra" role="progressbar" aria-label="Participación de la semana" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pulso['porcentajeActivos'] }}"><span data-ancho="{{ $pulso['porcentajeActivos'] }}"></span></div>
      <p class="kpi__nota">{{ $pulso['porcentajeActivos'] }} % de participación en los últimos 7 días</p>
    </article>
    <article class="kpi">
      <p class="kpi__etiqueta">Minutos de práctica</p>
      <p class="kpi__valor">{{ number_format($pulso['minutosSemana'], 0, ',', '.') }}</p>
      <p class="kpi__nota">Últimos 7 días, medidos por el servidor</p>
    </article>
    <article class="kpi">
      <p class="kpi__etiqueta">Entregas por revisar</p>
      <p class="kpi__valor">{{ $pulso['entregasPorRevisar'] }}</p>
      <p class="kpi__nota">De todos los profesores</p>
    </article>
  </div>

  <div class="tarjeta pendientes">
    <h2 class="pendientes__titulo"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-lista"/></svg> Pendientes para ti</h2>
    @forelse($pulso['pendientes'] as $p)
      <a class="pendiente pendiente--{{ $p['nivel'] }}" href="{{ $p['url'] }}">
        <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-{{ $p['icono'] }}"/></svg>
        <span>{{ $p['texto'] }}</span>
        <svg class="icon pendiente__flecha" aria-hidden="true"><use href="{{ $iconos }}#i-chevron"/></svg>
      </a>
    @empty
      <p class="pendientes__vacio"><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-check-circulo"/></svg> Todo en orden: no hay pendientes.</p>
    @endforelse
  </div>

  <dl class="cifras" aria-label="La academia en números">
    <div><dt>Profesores</dt><dd>{{ $adminData['totalProfesores'] }}</dd></div>
    <div><dt>Estudiantes</dt><dd>{{ $adminData['totalEstudiantes'] }}</dd></div>
    <div><dt>Elencos y orquestas</dt><dd>{{ $adminData['totalElencos'] }}</dd></div>
    <div><dt>Tareas enviadas</dt><dd>{{ $adminData['totalTareas'] }}</dd></div>
    <div><dt>Prácticas registradas</dt><dd>{{ $adminData['totalPracticas'] }}</dd></div>
    <div><dt>Cursos activos</dt><dd>{{ $adminData['totalCursos'] }}</dd></div>
  </dl>
</section>

{{-- 2. Elencos, orquestas y asignaciones ----------------------------------- --}}
<section class="panel" id="elencos" aria-labelledby="elencos-titulo">
  <div class="section-head">
    <div>
      <h2 id="elencos-titulo">Elencos y orquestas</h2>
      <p>Cada agrupación con sus estudiantes. Desde aquí se crean grupos y se asigna elenco y profesor a cada estudiante.</p>
    </div>
  </div>

  <div class="elencos">
    <div class="elencos__lista">
      @forelse($elencosData as $elenco)
        <article class="elenco">
          <header class="elenco__cabecera">
            <span class="elenco__icono" aria-hidden="true"><svg class="icon"><use href="{{ $iconos }}#i-{{ strtolower($elenco['tipo']) === 'orquesta' ? 'musica' : 'grupo' }}"/></svg></span>
            <div class="elenco__nombre">
              <h3>{{ $elenco['nombre'] }}</h3>
              <p><span class="pill">{{ $elenco['tipo'] }}</span> {{ $elenco['estudiantes'] }} {{ $elenco['estudiantes'] === 1 ? 'estudiante' : 'estudiantes' }}</p>
            </div>
          </header>

          <details class="elenco__alumnos">
            <summary>Ver estudiantes</summary>
            @forelse($elenco['alumnos'] as $alumno)
              <div class="fila-persona">
                <span><strong>{{ $alumno['nombre'] }}</strong><small>{{ $alumno['seccion'] }}</small></span>
                <span class="fila-persona__dato">{{ $alumno['profesor'] }}</span>
              </div>
            @empty
              <p class="hint">Todavía no hay estudiantes en este grupo.</p>
            @endforelse
          </details>

          <details class="inline-editor">
            <summary>Editar o eliminar</summary>
            <form action="{{ url('/dashboard-admin/elencos/'.$elenco['id']) }}" method="POST" class="compact-form">
              @csrf
              @method('PUT')
              <label>Nombre <input type="text" name="nombre" value="{{ $elenco['nombre'] }}" required maxlength="100" /></label>
              <label>Tipo
                <select name="tipo" required>
                  <option value="Orquesta" @selected($elenco['tipo'] === 'Orquesta')>Orquesta</option>
                  <option value="Elenco" @selected($elenco['tipo'] === 'Elenco')>Elenco</option>
                </select>
              </label>
              <div class="elenco__acciones">
                <button type="submit" class="btn btn--sec btn--sm">Guardar cambios</button>
              </div>
            </form>
            <form action="{{ url('/dashboard-admin/elencos/'.$elenco['id']) }}" method="POST">
              @csrf
              @method('DELETE')
              <button type="submit" class="btn btn--peligro btn--sm" data-confirmar="¿Eliminar el elenco «{{ $elenco['nombre'] }}»? Sus estudiantes quedarán sin elenco. Esta acción no se puede deshacer."><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-borrar"/></svg> Eliminar elenco</button>
            </form>
          </details>
        </article>
      @empty
        <div class="empty-state">
          <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-grupo"/></svg>
          <h3>Todavía no hay elencos</h3>
          <p>Crea una orquesta o un elenco para empezar a organizar a los estudiantes.</p>
        </div>
      @endforelse
    </div>

    <div class="elencos__formularios" id="asignaciones">
      <form action="{{ url('/dashboard-admin/elencos') }}" method="POST" class="card-form compact-form">
        @csrf
        <h3>Crear agrupación</h3>
        <label>Nombre <input type="text" name="nombre" value="{{ old('nombre') }}" placeholder="Ej.: Orquesta Juvenil" required maxlength="100" /></label>
        <label>Tipo
          <select name="tipo" required>
            <option value="Orquesta" @selected(old('tipo') === 'Orquesta')>Orquesta</option>
            <option value="Elenco" @selected(old('tipo') === 'Elenco')>Elenco</option>
          </select>
        </label>
        <button type="submit" class="btn">Crear agrupación</button>
      </form>

      <form action="{{ url('/dashboard-admin/elencos/asignar-estudiante') }}" method="POST" class="card-form compact-form">
        @csrf
        <h3>Asignar elenco</h3>
        <label>Estudiante
          <select name="id_estudiante" required>
            <option value="">Elige un estudiante</option>
            @foreach($estudiantesData as $estudiante)
              <option value="{{ $estudiante['id'] }}">{{ $estudiante['nombre'] }} · {{ $estudiante['elenco'] }}</option>
            @endforeach
          </select>
        </label>
        <label>Elenco u orquesta
          <select name="id_elenco">
            <option value="">Sin elenco</option>
            @foreach($elencosData as $elenco)
              <option value="{{ $elenco['id'] }}">{{ $elenco['nombre'] }} ({{ $elenco['tipo'] }})</option>
            @endforeach
          </select>
        </label>
        <button type="submit" class="btn btn--sec">Asignar elenco</button>
      </form>

      <form action="{{ url('/dashboard-admin/profesores/asignar-estudiante') }}" method="POST" class="card-form compact-form">
        @csrf
        <h3>Asignar profesor</h3>
        <label>Estudiante
          <select name="id_estudiante" required>
            <option value="">Elige un estudiante</option>
            @foreach($estudiantesData as $estudiante)
              <option value="{{ $estudiante['id'] }}">{{ $estudiante['nombre'] }} · {{ $estudiante['profesor'] }}</option>
            @endforeach
          </select>
        </label>
        <label>Profesor
          <select name="id_profesor">
            <option value="">Sin profesor</option>
            @foreach($profesoresData as $profesor)
              <option value="{{ $profesor['id'] }}">{{ $profesor['nombre'] }} ({{ $profesor['especialidad'] }})</option>
            @endforeach
          </select>
        </label>
        <button type="submit" class="btn btn--sec">Asignar profesor</button>
      </form>
    </div>
  </div>
</section>

{{-- 3. Actividad y alarmas ------------------------------------------------ --}}
<section class="actividad" id="actividad" aria-label="Actividad y alarmas">
  <div class="panel">
    <div class="section-head">
      <div>
        <h2>Actividad reciente</h2>
        <p>Tareas enviadas, entregas y prácticas, de la más nueva a la más antigua.</p>
      </div>
    </div>
    @if(count($actividadData['items']))
      <ol class="linea-tiempo">
        @foreach(array_slice($actividadData['items'], 0, 8) as $actividad)
          <li>
            <span class="pill">{{ $actividad['tipo'] }}</span>
            <div>
              <strong>{{ $actividad['titulo'] }}</strong>
              <p>{{ $actividad['detalle'] }}</p>
            </div>
            <time>{{ $actividad['fecha'] }}</time>
          </li>
        @endforeach
      </ol>
    @else
      <div class="empty-state">
        <svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-actividad"/></svg>
        <h3>Sin actividad todavía</h3>
        <p>Cuando haya tareas, entregas o prácticas, aparecerán aquí.</p>
      </div>
    @endif
  </div>

  <div class="panel" id="alarmas">
    <div class="section-head">
      <div>
        <h2>Alarmas</h2>
        <p>Calculadas con las tareas, las entregas y los ingresos.</p>
      </div>
    </div>
    <div class="alarmas">
      @forelse($alarmasData as $alarma)
        @php($tipoAlarma = ['Alta' => 'error', 'Media' => 'aviso', 'Baja' => 'info'][$alarma['nivel']] ?? 'exito')
        <x-alerta :tipo="$tipoAlarma">
          <strong>{{ $alarma['titulo'] }}</strong>
          @if($tipoAlarma !== 'exito') · <span class="alarma__nivel">Prioridad {{ mb_strtolower($alarma['nivel']) }}</span>@endif
          <br>{{ $alarma['detalle'] }}
        </x-alerta>
      @empty
        <x-alerta tipo="exito">No hay alarmas activas.</x-alerta>
      @endforelse
    </div>
  </div>
</section>

{{-- 4. Reportes ------------------------------------------------------------ --}}
<section class="panel" id="reportes" aria-labelledby="reportes-titulo">
  <div class="section-head">
    <div>
      <h2 id="reportes-titulo">Reportes</h2>
      <p>Descarga la información en CSV para abrirla en Excel o Google Sheets. Las celdas que podrían ejecutar fórmulas se neutralizan.</p>
    </div>
  </div>
  <div class="reportes">
    @foreach($reportesData['descargas'] as $reporte)
      <a class="reporte" href="{{ url('/dashboard-admin/reportes/'.$reporte['tipo']) }}">
        <span class="reporte__icono" aria-hidden="true"><svg class="icon"><use href="{{ $iconos }}#i-descargar"/></svg></span>
        <span><strong>{{ $reporte['titulo'] }}</strong><small>{{ $reporte['detalle'] }}</small></span>
      </a>
    @endforeach
  </div>
</section>
@endsection
