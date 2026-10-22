@extends('layouts.publico', [
  'seoTitulo' => 'Política de privacidad | MSEA',
  'seoDescripcion' => 'Qué datos guarda la plataforma MSEA, para qué se usan, quién puede verlos y cómo ejercer tus derechos.',
  'seoIndexar' => true,
  'seoJsonLd' => [\App\Support\DatosEstructurados::migas(['Inicio' => url('/'), 'Política de privacidad' => url('/politica-de-privacidad')])],
])

@section('contenido')
  @php($s = config('msea.sitio'))
  <h1>Política de privacidad</h1>
  <p class="pub-actualizado">Última actualización: {{ \Illuminate\Support\Carbon::parse($s['legal_actualizado'])->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</p>

  <p>En MSEA cuidamos los datos de quienes usan la plataforma, sobre todo los de niñas, niños y adolescentes. Aquí explicamos,
    con palabras sencillas, qué guardamos, para qué, quién lo ve y qué puedes pedirnos.</p>

  <h2>1. Responsable</h2>
  @include('legal.partials.contacto')

  <h2>2. Qué datos guardamos</h2>
  <div class="pub-tabla-envoltura" tabindex="0" role="region" aria-label="Tabla de datos que guardamos">
    <table class="pub-tabla">
      <thead><tr><th scope="col">Dato</th><th scope="col">Detalle</th></tr></thead>
      <tbody>
        <tr><th scope="row">Cuenta</th><td>Nombres, apellidos, correo electrónico y contraseña. La contraseña se guarda cifrada: nadie, ni la
          dirección, puede verla.</td></tr>
        <tr><th scope="row">Perfil</th><td>Instrumento, nivel y, si la persona lo decide, una foto de perfil.</td></tr>
        <tr><th scope="row">Actividad de práctica</th><td>Sesiones de práctica, tiempo practicado, puntos, nivel, logros, rachas, tareas
          entregadas y calificaciones.</td></tr>
        <tr><th scope="row">Contenido que envías</th><td>Grabaciones de audio y archivos de tus entregas, y mensajes internos con tu profesor o la dirección.</td></tr>
        <tr><th scope="row">Seguridad</th><td>Registro de acciones importantes (inicios de sesión, cambios de cuenta) y, para proteger tu cuenta,
          la dirección IP y el navegador desde los que se inicia sesión.</td></tr>
        <tr><th scope="row">Continuar con Google</th><td>Si eliges este inicio de sesión, recibimos de Google tu nombre y tu correo verificado. No
          recibimos tu contraseña de Google.</td></tr>
      </tbody>
    </table>
  </div>
  <p><strong>El micrófono:</strong> el afinador y la evaluación de escalas escuchan tu instrumento, pero el sonido se analiza en tu propio
    dispositivo y no se envía a ningún servidor. Solo se guarda una grabación cuando tú eliges grabar y entregar una tarea.</p>

  <h2>3. Para qué los usamos</h2>
  <ul>
    <li>Darte acceso a tu cuenta y prestar el servicio educativo (tareas, práctica, puntos y clasificación).</li>
    <li>Que tu profesor y la dirección acompañen tu progreso, y que tu familia pueda seguirlo.</li>
    <li>Proteger la plataforma y las cuentas, y enviarte códigos de recuperación de contraseña por correo.</li>
    <li>Obtener estadísticas generales para mejorar la enseñanza.</li>
  </ul>
  <p>No vendemos datos, no hacemos publicidad y no creamos perfiles comerciales.</p>

  <h2>4. Quién puede ver tus datos</h2>
  <ul>
    <li><strong>Tu profesor y la dirección:</strong> ven tu actividad, tus entregas y tus calificaciones.</li>
    <li><strong>Tu madre, padre o tutor:</strong> si su cuenta está vinculada a la tuya, ve tu avance (práctica, tareas y notas).</li>
    <li><strong>Otros estudiantes:</strong> en la clasificación se muestran nombre, primer apellido, instrumento y puntos de los primeros lugares.
      No ven tus grabaciones, tu correo ni tus mensajes.</li>
    <li><strong>Proveedores técnicos:</strong> el servicio de alojamiento y de envío de correo que hacen funcionar la plataforma, y Google solo si
      usas «Continuar con Google».</li>
  </ul>
  <p>Solo compartimos datos con autoridades cuando una ley lo exige.</p>

  <h2>5. Niñas, niños y adolescentes</h2>
  <p>Pedimos únicamente los datos necesarios para estudiar música en la plataforma. Recomendamos que el registro se haga con el acompañamiento
    de la madre, el padre o el tutor, quien puede pedirnos ver, corregir o eliminar los datos de la cuenta.</p>

  <h2>6. Cuánto tiempo los guardamos</h2>
  <p>Mientras la cuenta esté activa y sea necesario para el seguimiento educativo. Si una cuenta se desactiva, conservamos el historial de
    estudio. Puedes pedir que eliminemos tus datos o los anonimicemos, salvo lo que debamos conservar por obligación legal o por seguridad.
    Las copias de seguridad se renuevan periódicamente.</p>

  <h2>7. Tus derechos</h2>
  <p>Puedes pedir en cualquier momento: saber qué datos tenemos de ti, corregirlos, eliminarlos u oponerte a algún uso. Para hacerlo, escribe a
    @if($s['correo']) <a href="mailto:{{ $s['correo'] }}">{{ $s['correo'] }}</a> @else la dirección de la fundación (a través de tu profesor o de la dirección) @endif
    indicando tu nombre y el correo de tu cuenta. Responderemos lo antes posible. Esta política respeta el derecho a la privacidad que reconoce la
    Constitución Política del Estado y la protección especial de niñas, niños y adolescentes del Código Niña, Niño y Adolescente (Ley 548).</p>

  <h2>8. Cómo protegemos la información</h2>
  <ul>
    <li>Conexión cifrada (HTTPS) y contraseñas guardadas cifradas.</li>
    <li>Límites de intentos de ingreso y protección contra envíos automáticos.</li>
    <li>Archivos privados: las grabaciones y los materiales solo se abren con permiso de la persona correspondiente.</li>
    <li>Registro de acciones importantes para detectar usos indebidos.</li>
  </ul>
  <p>Ninguna medida es perfecta. Si detectas algo extraño en tu cuenta, avísanos de inmediato.</p>

  <h2>9. Cookies</h2>
  <p>Usamos solo las cookies necesarias para que la plataforma funcione. Más detalles en la <a href="{{ url('/politica-de-cookies') }}">política de cookies</a>.</p>

  <h2>10. Cambios en esta política</h2>
  <p>Si cambiamos esta política, publicaremos aquí la nueva versión con su fecha de actualización.</p>
@endsection
