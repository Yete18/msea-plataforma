@extends('layouts.publico', [
  'seoTitulo' => 'Aviso legal | MSEA',
  'seoDescripcion' => 'Quién es el titular de la plataforma MSEA, condiciones de uso, propiedad intelectual y responsabilidad.',
  'seoIndexar' => true,
  'seoJsonLd' => [\App\Support\DatosEstructurados::migas(['Inicio' => url('/'), 'Aviso legal' => url('/aviso-legal')])],
])

@section('contenido')
  <h1>Aviso legal</h1>
  <p class="pub-actualizado">Última actualización: {{ \Illuminate\Support\Carbon::parse(config('msea.sitio.legal_actualizado'))->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</p>

  <h2>1. Titular de la plataforma</h2>
  <p>Esta plataforma es operada por la fundación que se indica a continuación:</p>
  @include('legal.partials.contacto')

  <h2>2. Qué es MSEA y para qué sirve</h2>
  <p>MSEA es una plataforma educativa de práctica musical del Movimiento Sinfónico de El Alto. Permite a estudiantes de violín,
    viola, violonchelo y contrabajo practicar con un afinador y un metrónomo, recibir tareas de su profesor, entregar grabaciones,
    ganar puntos y logros, y a las familias y a la dirección seguir el avance.</p>
  <p>El acceso a los paneles requiere una cuenta. Las cuentas son personales: cada persona usa la suya.</p>

  <h2>3. Condiciones de uso</h2>
  <ul>
    <li>Usa la plataforma solo para fines educativos y musicales relacionados con la fundación.</li>
    <li>Mantén tu contraseña en secreto y no la compartas. Si crees que alguien la conoce, cámbiala desde tu panel o usa
      la opción «Olvidé mi contraseña».</li>
    <li>No te hagas pasar por otra persona, no intentes entrar a cuentas ajenas y no intentes dañar, saturar o vulnerar la plataforma.</li>
    <li>No subas contenido ofensivo, ilegal o que pertenezca a otra persona sin permiso.</li>
    <li>Las niñas, niños y adolescentes deben registrarse con el conocimiento y el acompañamiento de su madre, padre o tutor.</li>
    <li>La dirección puede suspender o desactivar una cuenta que incumpla estas condiciones o que ponga en riesgo a otras personas.</li>
  </ul>

  <h2>4. Contenido que subes</h2>
  <p>Eres responsable de las grabaciones, archivos y mensajes que envías. Esos contenidos solo los ven las personas autorizadas
    de la fundación (tu profesor y la dirección) y, cuando corresponde, la familia vinculada a tu cuenta. La fundación puede retirar
    contenido que no sea adecuado.</p>

  <h2>5. Propiedad intelectual</h2>
  <ul>
    <li>El nombre, el logotipo, las imágenes y los textos de MSEA pertenecen a la fundación o se usan con autorización. No pueden
      copiarse ni usarse para otros fines sin permiso.</li>
    <li>Los métodos, partituras y ejercicios que la dirección sube a la biblioteca deben ser de dominio público o contar con
      autorización de quien tenga sus derechos.</li>
    <li>La plataforma usa software y tipografías libres: Nunito y Baloo 2 (licencia SIL OFL), Bravura (SIL OFL), Lucide (ISC) y
      Bootstrap (MIT). Cada una conserva su licencia.</li>
  </ul>

  <h2>6. Las mediciones son una ayuda, no un examen</h2>
  <p>El afinador, el metrónomo y la evaluación de escalas son herramientas de apoyo para practicar. Dependen del micrófono, del
    ruido del lugar y del instrumento, por lo que sus resultados son orientativos. No sustituyen la guía de tu profesor.</p>

  <h2>7. Disponibilidad y responsabilidad</h2>
  <p>Trabajamos para que la plataforma esté disponible y segura, pero no podemos garantizar que funcione sin interrupciones ni errores.
    La fundación no responde por fallos ajenos a su control (internet, energía, dispositivos) ni por el uso indebido de una cuenta
    cuya contraseña se haya compartido.</p>

  <h2>8. Enlaces a otros sitios</h2>
  <p>Algunos botones (por ejemplo, WhatsApp o «Continuar con Google») llevan a servicios de terceros, que tienen sus propias condiciones
    y políticas. La fundación no controla esos servicios.</p>

  <h2>9. Cambios en este aviso</h2>
  <p>Podemos actualizar este aviso. Publicaremos la versión vigente en esta página con su fecha de actualización.</p>

  <h2>10. Ley aplicable</h2>
  <p>Este aviso se rige por las leyes del Estado Plurinacional de Bolivia. Cualquier controversia se someterá a los tribunales
    competentes de Bolivia.</p>

  <p class="pub-aviso">Consulta también la <a href="{{ url('/politica-de-privacidad') }}">política de privacidad</a> y la
    <a href="{{ url('/politica-de-cookies') }}">política de cookies</a>.</p>
@endsection
