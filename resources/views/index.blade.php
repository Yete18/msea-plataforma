<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  @php
    $seoTitulo = 'MSEA | Orquesta y escuela de música para niños y jóvenes de El Alto';
    $seoDescripcion = 'El Movimiento Sinfónico de El Alto forma a niñas, niños y jóvenes en violín, viola, violonchelo y contrabajo, con orquesta, conciertos y práctica guiada en casa.';
    $seoIndexar = true;
    $seoJsonLd = [\App\Support\DatosEstructurados::organizacion(), \App\Support\DatosEstructurados::sitioWeb()];
  @endphp
  @include('partials.seo')
  <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48" />
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}" />
  <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" />
  <link rel="manifest" href="{{ asset('site.webmanifest') }}" />
  <meta name="theme-color" content="#315C3A" />
  <link rel="preload" as="image" type="image/webp" href="{{ asset('assets/img/portada_3.webp') }}" />
  <link rel="stylesheet" href="{{ asset('css/portada.css') }}" />
  <link rel="stylesheet" href="{{ asset('css/publico-comun.css') }}" />
</head>
<body class="portada">
  {{-- Solo los cuatro instrumentos que usa esta pagina. --}}
  <svg xmlns="http://www.w3.org/2000/svg" class="sprite-oculto" aria-hidden="true">
  <symbol id="i-violin" viewBox="0 0 24 24"><path d="M12 9.6V4"/><circle cx="12" cy="3.3" r="0.9"/><path d="M10.8 5h2.4"/><path d="M12 9.6 C9.87 9.6 8.96 11.09 8.96 12.08 C8.96 13.32 9.91 13.82 9.91 15.06 C9.91 16.3 8.2 17.29 8.2 18.78 C8.2 21.26 9.91 22 12 22 C14.09 22 15.8 21.26 15.8 18.78 C15.8 17.29 14.09 16.3 14.09 15.06 C14.09 13.82 15.04 13.32 15.04 12.08 C15.04 11.09 14.13 9.6 12 9.6Z"/><path d="M10.4 16.05v2.48M13.6 16.05v2.48"/></symbol>
  <symbol id="i-viola" viewBox="0 0 24 24"><path d="M12 7.6V2.4"/><circle cx="12" cy="1.7" r="0.9"/><path d="M10.8 3.4h2.4"/><path d="M12 7.6 C9.37 7.6 8.24 9.33 8.24 10.48 C8.24 11.92 9.41 12.5 9.41 13.94 C9.41 15.38 7.3 16.53 7.3 18.26 C7.3 21.14 9.41 22 12 22 C14.59 22 16.7 21.14 16.7 18.26 C16.7 16.53 14.59 15.38 14.59 13.94 C14.59 12.5 15.76 11.92 15.76 10.48 C15.76 9.33 14.63 7.6 12 7.6Z"/><path d="M10.03 15.09v2.88M13.97 15.09v2.88"/></symbol>
  <symbol id="i-violonchelo" viewBox="0 0 24 24"><path d="M12 6.4V2.2"/><circle cx="12" cy="1.5" r="0.9"/><path d="M10.8 3.2h2.4"/><path d="M12 6.4 C8.86 6.4 7.52 8.15 7.52 9.32 C7.52 10.78 8.92 11.36 8.92 12.82 C8.92 14.28 6.4 15.45 6.4 17.2 C6.4 20.12 8.92 21 12 21 C15.08 21 17.6 20.12 17.6 17.2 C17.6 15.45 15.08 14.28 15.08 12.82 C15.08 11.36 16.48 10.78 16.48 9.32 C16.48 8.15 15.14 6.4 12 6.4Z"/><path d="M9.65 13.99v2.92M14.35 13.99v2.92"/><path d="M12 21v2.2"/></symbol>
  <symbol id="i-contrabajo" viewBox="0 0 24 24"><path d="M12 5.6V2"/><circle cx="12" cy="1.3" r="0.9"/><path d="M10.8 3h2.4"/><path d="M12 5.6 C9.25 5.6 8.07 7.4 8.07 8.6 C8.07 10.1 8.54 10.7 8.54 12.2 C8.54 13.7 5.7 14.9 5.7 16.7 C5.7 19.7 8.54 20.6 12 20.6 C15.46 20.6 18.3 19.7 18.3 16.7 C18.3 14.9 15.46 13.7 15.46 12.2 C15.46 10.7 15.93 10.1 15.93 8.6 C15.93 7.4 14.75 5.6 12 5.6Z"/><path d="M9.35 13.4v3M14.65 13.4v3"/><path d="M12 20.6v2.4"/></symbol>
  </svg>

  <a class="saltar" href="#contenido">Saltar al contenido</a>

  <header class="p-cabecera">
      <a class="p-marca" href="{{ url('/') }}">
        <img src="{{ asset('assets/img/logo-320.webp') }}" width="52" height="52" alt="" />
        <span><strong>MSEA</strong> Movimiento Sinfónico de El Alto</span>
      </a>
  </header>

  <main id="contenido">
      <section class="p-hero" aria-labelledby="titulo">
        <div class="p-pentagrama" aria-hidden="true"></div>
        <div class="p-hero-texto">
          <h1 id="titulo">¡Formando jóvenes y niños con valores!</h1>
          <p class="p-bajada">Somos la orquesta y escuela de cuerdas de El Alto. Niñas, niños y jóvenes aprenden violín, viola,
            violonchelo y contrabajo, tocan juntos en orquesta y siguen practicando en casa con esta plataforma.</p>
          {{-- Una sola llamada a la accion. Quien no tiene cuenta usa el enlace de texto. --}}
          <a href="{{ url('/login') }}" class="p-cta" id="btn-login">Entrar a mi cuenta</a>
          <p class="p-registro">¿Primera vez? <a href="{{ url('/registro') }}" id="btn-register">Crea tu cuenta de estudiante</a></p>
        </div>

        {{-- La foto principal, recortada con la silueta de un violin (mastil, cuerdas y cuerpo). --}}
        <svg class="p-violin" viewBox="-4 -34 108 204" role="img" aria-labelledby="violin-desc">
          <title id="violin-desc">Niñas y niños de la orquesta MSEA tocando el violín sobre un escenario</title>
          <defs>
            <clipPath id="silueta-violin">
              <path d="M50,0 C70,0 90,8 90,33 C90,49 80,54 76,59 C70,66 70,86 76,92 C82,99 100,106 100,132 C100,157 78,165 50,165 C22,165 0,157 0,132 C0,106 18,99 24,92 C30,86 30,66 24,59 C20,54 10,49 10,33 C10,8 30,0 50,0 Z" />
            </clipPath>
          </defs>
          <rect class="p-violin-mastil" x="44.5" y="-24" width="11" height="28" rx="3" />
          <circle class="p-violin-voluta" cx="50" cy="-27" r="5.5" />
          <image href="{{ asset('assets/img/portada_3.webp') }}" x="0" y="0" width="100" height="165" preserveAspectRatio="xMidYMid slice" clip-path="url(#silueta-violin)" />
          <path class="p-violin-borde" d="M50,0 C70,0 90,8 90,33 C90,49 80,54 76,59 C70,66 70,86 76,92 C82,99 100,106 100,132 C100,157 78,165 50,165 C22,165 0,157 0,132 C0,106 18,99 24,92 C30,86 30,66 24,59 C20,54 10,49 10,33 C10,8 30,0 50,0 Z" />
          <g class="p-violin-cuerdas"><path d="M47,-22V128M49,-22V128M51,-22V128M53,-22V128" /></g>
          <rect class="p-violin-puente" x="41" y="104" width="18" height="3" rx="1.5" />
        </svg>
      </section>

  <section class="p-hacemos" aria-labelledby="hacemos-titulo">
    <h2 id="hacemos-titulo">Lo que hace la fundación</h2>
    <p class="p-intro">Desde El Alto, la música como camino para crecer: aprender un instrumento, tocar en grupo y compartirlo con la comunidad.</p>

    <div class="p-mosaico">
      <article class="p-pieza p-pieza-orquesta">
        <img src="{{ asset('assets/img/portada.webp') }}" width="1200" height="800" loading="lazy" decoding="async"
             alt="Orquesta infantil de MSEA reunida en una sala, con niños y jóvenes sosteniendo violines y violonchelos" />
        <h3>Orquesta infantil y juvenil</h3>
        <p>Niñas, niños y jóvenes aprenden a tocar juntos: cada uno escucha a los demás y suma su parte al sonido de la orquesta.</p>
      </article>

      <article class="p-pieza p-pieza-clases">
        <img src="{{ asset('assets/img/img_registro_1.webp') }}" width="1200" height="800" loading="lazy" decoding="async"
             alt="Jóvenes músicos de la orquesta MSEA con sus violines y violonchelos" />
        <h3>Clases de cuerdas</h3>
        <p>Desde las primeras notas hasta las obras de orquesta, con profesores que acompañan a cada estudiante.</p>
        <ul class="p-instrumentos">
          <li><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#i-violin" /></svg>Violín</li>
          <li><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#i-viola" /></svg>Viola</li>
          <li><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#i-violonchelo" /></svg>Violonchelo</li>
          <li><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#i-contrabajo" /></svg>Contrabajo</li>
        </ul>
      </article>

      <article class="p-pieza p-pieza-conciertos">
        <img src="{{ asset('assets/img/portada_2.webp') }}" width="1200" height="800" loading="lazy" decoding="async"
             alt="Concierto de la orquesta infantil MSEA frente a un público numeroso" />
        <h3>Conciertos para la comunidad</h3>
        <p>Lo aprendido se comparte en presentaciones abiertas a las familias y al barrio.</p>
      </article>

      <article class="p-pieza p-pieza-valores">
        <img src="{{ asset('assets/img/img_login_1.webp') }}" width="1200" height="800" loading="lazy" decoding="async"
             alt="Niñas con vestidos de fiesta sostienen sus arcos entre los músicos de la orquesta" />
        <h3>Valores a través de la música</h3>
        <p>Constancia, respeto y trabajo en equipo: tocar en una orquesta se aprende ensayando juntos, semana tras semana.</p>
      </article>
    </div>
  </section>

  <section class="p-casa" aria-labelledby="casa-titulo">
    <div class="p-casa-dentro">
      <div class="p-casa-texto">
        <h2 id="casa-titulo">La práctica sigue en casa</h2>
        <p>Con su cuenta, cada estudiante practica entre clase y clase. El afinador escucha el instrumento y dice, nota por nota,
          si está afinado.</p>
        <ul>
          <li><strong>Afinador y escalas</strong> en la clave de cada instrumento.</li>
          <li><strong>Tareas del profesor</strong> con grabación, comentarios y calificación.</li>
          <li><strong>Puntos y logros</strong> por practicar, y seguimiento para las familias.</li>
        </ul>
      </div>
      <figure class="p-casa-captura">
        <img src="{{ asset('assets/img/app-afinador.webp') }}" width="1200" height="876" loading="lazy" decoding="async"
             alt="Pantalla del afinador de MSEA: la nota objetivo es Re4 y la aguja indica que el sonido está un poco agudo" />
        <figcaption>El afinador de la plataforma practicando una escala.</figcaption>
      </figure>
    </div>
  </section>
  </main>

  <footer class="p-pie">
    @include('partials.enlaces-legales')
    <p>© {{ now()->year }} {{ config('msea.sitio.razon_social') }}. Todos los derechos reservados.</p>
  </footer>

  @include('partials.whatsapp')
  @include('partials.cookies')
</body>
</html>
