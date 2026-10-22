<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>{{ $tarea->titulo ?? 'Escala de '.$ejercicio['escala']['nombre'] }} - MSEA</title>
  @include('partials.panel-assets')
  <link rel="stylesheet" href="{{ asset('css/escala-practica.css') }}" />
</head>
<body class="escala-body">
@php($cfg = $ejercicio['config'])
@php($inst = $ejercicio['instrumento'])
@php($nombresClave = ['sol' => 'Clave de Sol', 'do' => 'Clave de Do', 'fa' => 'Clave de Fa'])
@php($esTecnica = ($ejercicio['tipo'] ?? 'escala') === 'tecnica')
@php($palabra = $esTecnica ? 'el ejercicio' : 'la escala')
<main class="escala-page" id="contenido">
  <div class="escala-topbar">
    <a class="btn-msea secondary small" href="{{ $volverUrl }}"><svg class="icon"><use href="{{ $iconos }}#i-flecha-izquierda"/></svg> {{ $modo === 'tarea' ? 'Volver a mis tareas' : 'Volver a la biblioteca' }}</a>
    <button class="theme-toggle" type="button" data-theme-toggle aria-label="Activar modo oscuro" aria-pressed="false"></button>
  </div>

  @if($modo === 'prueba')
    <p class="escala-aviso" role="note"><svg class="icon"><use href="{{ $iconos }}#i-ver"/></svg> Vista previa: así lo verá el estudiante. No se guarda ni se entrega nada.</p>
  @endif

  <header class="card escala-cabecera">
    <span class="icon-badge"><svg class="icon icon-lg"><use href="{{ $iconos }}#i-{{ $inst['clave'] }}"/></svg></span>
    <div class="ec-texto">
      <p class="eyebrow">{{ $esTecnica ? 'Técnica' : 'Afinación' }} · {{ $inst['nombre'] }} · {{ $ejercicio['escala']['dificultad'] }}</p>
      <h1>{{ $tarea->titulo ?? 'Escala de '.$ejercicio['escala']['nombre'] }}</h1>
      @if(($ejercicio['tipo'] ?? 'escala') === 'tecnica')
        @php($vel = $ejercicio['velocidad'])
        <ul class="ec-chips" aria-label="Indicaciones del profesor">
          <li>{{ $ejercicio['escala']['categoria'] }}</li>
          <li>{{ $vel['modo'] === 'velocidad' ? 'Velocidad: de '.$vel['bpm_inicial'].' a '.$vel['bpm_objetivo'].' (sube '.$vel['paso'].')' : 'Afinación nota por nota' }}</li>
          <li>{{ count($ejercicio['notas']) }} notas · compás {{ $cfg['compas'] }}</li>
          <li>Tolerancia ±{{ $cfg['tolerancia'] }} cents</li>
          @if($ejercicio['escala']['armadura']['cantidad'])<li>{{ $ejercicio['escala']['armadura']['texto'] }}</li>@endif
          <li>{{ $nombresClave[$inst['clef']] }}</li>
          @if($inst['escribe_octava_arriba'])<li>Se escribe una octava más aguda de lo que suena</li>@endif
          @if(!empty($ejercicio['escala']['fuente']['pagina']))<li>{{ $ejercicio['escala']['fuente']['metodo'] ?: $ejercicio['escala']['fuente']['titulo'] }}, pág. {{ $ejercicio['escala']['fuente']['pagina'] }}</li>@endif
        </ul>
      @else
      <ul class="ec-chips" aria-label="Indicaciones del profesor">
          <li>{{ $cfg['octavas'] }} {{ $cfg['octavas'] === 1 ? 'octava' : 'octavas' }}</li>
          <li>{{ $cfg['direccion'] === 'subir' ? 'Solo sube' : 'Sube y baja' }}</li>
          <li>{{ ucfirst($ejercicio['ritmo_texto']) }}</li>
          <li>{{ ucfirst($ejercicio['unidad_pulso']) }} = {{ $cfg['bpm'] }} · compás {{ $cfg['compas'] }}</li>
          <li>Tolerancia ±{{ $cfg['tolerancia'] }} cents</li>
          <li>{{ $ejercicio['escala']['armadura']['texto'] }}</li>
          <li>{{ $nombresClave[$inst['clef']] }}</li>
          @if($inst['escribe_octava_arriba'])<li>Se escribe una octava más aguda de lo que suena</li>@endif
        </ul>
      @endif
      @if(($ejercicio['octavas_pedidas'] ?? 0) > ($cfg['octavas'] ?? 0))
        <p class="ec-devuelta">Tu profesor pidió {{ $ejercicio['octavas_pedidas'] }} octavas; en {{ mb_strtolower($inst['nombre']) }} esta escala llega a {{ $cfg['octavas'] }}, así que la tocas en {{ $cfg['octavas'] }}.</p>
      @endif
      @if(count($instrumentos ?? []) > 1)
        <nav class="ec-instrumentos" aria-label="Instrumento con el que tocas">
          <span>Tocar en:</span>
          @foreach($instrumentos as $i)
            <a class="btn-msea small {{ $i === $inst['clave'] ? '' : 'secondary' }}" href="{{ $cambiarUrl }}?instrumento={{ $i }}" @if($i === $inst['clave']) aria-current="true" @endif><svg class="icon"><use href="{{ $iconos }}#i-{{ $i }}"/></svg> {{ \App\Services\EscalaService::INSTRUMENTOS[$i]['nombre'] }}</a>
          @endforeach
        </nav>
      @endif
      @if($tarea && $tarea->descripcion)
        <p class="ec-instrucciones"><strong>Indicaciones:</strong> {{ $tarea->descripcion }}</p>
      @endif
      @if($entrega && $entrega->estado === 'devuelta' && $entrega->comentario_profesor)
        <p class="ec-devuelta"><strong>Tu profesor pidió corregir:</strong> {{ $entrega->comentario_profesor }}</p>
      @endif
    </div>
  </header>

  <div class="escala-grid">
    <section class="escala-principal">
      <article class="card objetivo-card" id="objetivo-card" aria-live="polite">
        <div class="objetivo-cabecera">
          <p class="objetivo-paso"><span id="paso-actual">Nota 1</span> de <span id="paso-total">{{ count($ejercicio['notas']) }}</span></p>
          <div class="objetivo-puntos" id="objetivo-puntos" aria-hidden="true"></div>
        </div>

        <div class="objetivo-cuerpo">
          <div class="objetivo-nota">
            <p class="objetivo-etiqueta">Nota objetivo</p>
            <p class="objetivo-nombre" id="objetivo-nombre">-</p>
            <p class="objetivo-hz"><span id="objetivo-hz">-</span> Hz</p>
            <p class="objetivo-digitacion" id="objetivo-digitacion"></p>
            <button class="btn-msea secondary small" id="btn-escuchar" type="button"><svg class="icon"><use href="{{ $iconos }}#i-auriculares"/></svg> Escuchar la nota</button>
            <div class="objetivo-ritmo">
              <p class="objetivo-etiqueta">Ritmo en esta nota</p>
              <div class="ritmo-svg" id="ritmo-svg" role="img" aria-label="Ritmo de la nota"></div>
              <p class="ritmo-texto" id="ritmo-texto"></p>
            </div>
          </div>

          {{-- Afinador cromático: aguja de ±50 cents, nota detectada, frecuencia e historial de afinación. --}}
          <div class="afinador" id="afinador" data-estado="quieto">
            <svg class="dial" id="dial" viewBox="0 0 320 186" role="img" aria-label="Aguja de afinación de −50 a +50 cents"></svg>
            <div class="af-lectura" aria-live="off">
              <p class="af-nota" id="af-nota">--</p>
              <p class="af-cents"><span id="af-cents">0</span> <small>cents</small></p>
              <p class="af-hz"><span id="af-hz">--</span> Hz</p>
            </div>
            <p class="lectura" id="lectura" aria-live="polite">Activa el micrófono para empezar.</p>
            <canvas class="historial" id="historial" height="96" aria-label="Historial de afinación de los últimos segundos" role="img"></canvas>
            <div class="af-pie">
              <span>La4 = 440 Hz</span>
              <span class="af-nivel" title="Volumen que llega al micrófono"><span id="af-nivel"></span></span>
              <span>Lectura al cent</span>
            </div>
          </div>
        </div>

        <div class="sostener" aria-hidden="true"><span id="sostener-barra"></span></div>

        <div class="objetivo-acciones">
          <button class="btn-msea" id="btn-microfono" type="button"><svg class="icon"><use href="{{ $iconos }}#i-microfono"/></svg> Activar micrófono y empezar</button>
          <button class="btn-msea secondary" id="btn-reiniciar" type="button"><svg class="icon"><use href="{{ $iconos }}#i-refrescar"/></svg> Reiniciar</button>
          @if($modo === 'prueba')
            <button class="btn-msea secondary" id="btn-simular" type="button" title="Solo en la vista previa"><svg class="icon"><use href="{{ $iconos }}#i-flecha-derecha"/></svg> Simular nota afinada</button>
          @endif
        </div>
      </article>

      <article class="card pentagrama-card">
        <div class="card-header">
          <h2 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-musica"/></svg> Pentagrama</h2>
          <label class="interruptor"><input type="checkbox" id="ver-completa" /> <span>Ver la escala completa</span></label>
        </div>
        <div class="pentagrama-scroll" id="pentagrama-scroll">
          <div id="pentagrama" role="img" aria-label="Notas de la escala en el pentagrama"></div>
        </div>
      </article>

      <article class="card resultado-card hidden" id="resultado-card" tabindex="-1">
        <h2 class="card-title"><svg class="icon icon-gold"><use href="{{ $iconos }}#i-logro"/></svg> {{ $esTecnica ? 'Ejercicio completado' : 'Escala completada' }}</h2>
        <div class="velocidad-resultado hidden" id="velocidad-resultado"></div>
        <div class="resultado-stats" id="resultado-stats"></div>
        <p class="hint"><svg class="icon"><use href="{{ $iconos }}#i-info"/></svg> Medido con el micrófono en tu navegador: sirve para practicar y tu profesor lo revisa; no es una evaluación oficial.</p>
        @if($modo === 'tarea' && (! $entrega || $entrega->estado !== 'calificada'))
          <form class="msea-form" id="form-entregar" novalidate>
            <label>Comentario para tu profesor (opcional) <textarea name="comentario" maxlength="1000" placeholder="Por ejemplo: el Sol sostenido me costó."></textarea></label>
            <div class="resultado-acciones">
              <button class="btn-msea" id="btn-entregar" type="submit"><svg class="icon"><use href="{{ $iconos }}#i-enviar"/></svg> Entregar al profesor</button>
              <button class="btn-msea secondary" type="button" data-repetir><svg class="icon"><use href="{{ $iconos }}#i-refrescar"/></svg> Repetir {{ $palabra }}</button>
            </div>
          </form>
        @else
          <div class="resultado-acciones">
            <button class="btn-msea secondary" type="button" data-repetir><svg class="icon"><use href="{{ $iconos }}#i-refrescar"/></svg> Repetir {{ $palabra }}</button>
          </div>
        @endif
      </article>
    </section>

    <aside class="escala-lateral">
      <article class="card metro-card" id="metro-card">
        <h2 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-ritmo"/></svg> Metrónomo</h2>
        <div class="metro-bpm">
          <button class="metro-paso" type="button" data-bpm="-1" aria-label="Bajar un pulso">−</button>
          <p><output id="metro-bpm-valor">{{ $cfg['bpm'] }}</output><span>pulsos por minuto</span></p>
          <button class="metro-paso" type="button" data-bpm="1" aria-label="Subir un pulso">+</button>
        </div>
        <label class="sr-only" for="metro-bpm">Tempo</label>
        <input type="range" id="metro-bpm" min="30" max="240" value="{{ $cfg['bpm'] }}" />
        <div class="metro-fila">
          <label>Compás
            <select id="metro-compas">
              @foreach(\App\Services\EscalaService::COMPASES as $c)
                <option value="{{ $c }}" @selected($c === $cfg['compas'])>{{ $c }}</option>
              @endforeach
            </select>
          </label>
          <button class="btn-msea secondary small" id="metro-tap" type="button" title="Toca varias veces al tempo que quieres">Marcar tempo</button>
        </div>
        <p class="hint" id="metro-unidad">Cada clic es una {{ $ejercicio['unidad_pulso'] }}.</p>
        <div class="metro-pulsos" id="metro-pulsos" aria-hidden="true"></div>
        <label class="interruptor"><input type="checkbox" id="metro-acento" checked /> <span>Acentuar el primer tiempo</span></label>
        <button class="btn-msea metro-iniciar" id="metro-iniciar" type="button"><svg class="icon"><use href="{{ $iconos }}#i-play"/></svg> Iniciar metrónomo</button>
        @if($cfg['bpm'])
          <p class="hint">Tempo indicado por tu profesor: {{ $ejercicio['unidad_pulso'] }} = {{ $cfg['bpm'] }}. <button class="enlace" type="button" id="metro-restaurar">Volver a ese tempo</button></p>
        @endif
      </article>

      <article class="card ayuda-card">
        <h2 class="card-title"><svg class="icon"><use href="{{ $iconos }}#i-info"/></svg> Cómo funciona</h2>
        <ol>
          <li>Activa el micrófono y acércate a él con {{ $inst['clave'] === 'viola' ? 'la viola' : 'el '.mb_strtolower($inst['nombre']) }}. Mejor si usas audífonos para el metrónomo.</li>
          @if($esTecnica && ($ejercicio['velocidad']['modo'] ?? '') === 'velocidad')
            <li>El metrónomo arranca solo. Toca las notas a su tempo: cada una avanza apenas suena afinada (±{{ $cfg['tolerancia'] }} cents).</li>
            <li>Si terminas a tempo, sube el metrónomo de a {{ $ejercicio['velocidad']['paso'] }} hasta llegar a {{ $ejercicio['velocidad']['bpm_objetivo'] }}.</li>
          @else
            <li>Toca la nota objetivo. Cuando quede dentro de ±{{ $cfg['tolerancia'] }} cents durante medio segundo, aparece la siguiente.</li>
            <li>Repite cada nota con el ritmo indicado, al tempo del metrónomo.</li>
          @endif
        </ol>
        <p class="hint">Afinación de referencia: La4 = 440 Hz. Las frecuencias son exactas (temperamento igual), no aproximadas.</p>
      </article>
    </aside>
  </div>
</main>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script nonce="{{ $cspNonce ?? '' }}">
  window.MSEA_ESCALA = @json($ejercicio);
  window.MSEA_ESCALA_MODO = @json($modo);
  window.MSEA_ESCALA_ENTREGAR = @json($entregarUrl);
</script>
<script src="{{ asset('js/msea-ui.js') }}"></script>
<script src="{{ asset('js/afinador-yin.js') }}"></script>
<script src="{{ asset('js/escala-practica.js') }}"></script>
</body>
</html>
