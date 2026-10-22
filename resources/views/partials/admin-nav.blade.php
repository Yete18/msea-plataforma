{{-- Navegacion del director: una sola lista para todas sus paginas (sin anclas de una pagina larga). --}}
@php
  $activo = $activo ?? 'panel';
  $grupos = [
      null => [
          ['panel', '/dashboard-admin', 'inicio', 'Centralizador'],
      ],
      'Personas' => [
          ['profesores', '/dashboard-admin/profesores', 'grupo', 'Profesores'],
          ['usuarios', '/dashboard-admin/usuarios', 'usuarios', 'Estudiantes y familias'],
      ],
      'Enseñanza' => [
          ['cursos', '/dashboard-admin/cursos', 'cursos', 'Cursos y lecciones'],
          ['biblioteca', '/dashboard-admin/biblioteca', 'afinacion', 'Biblioteca técnica'],
          ['digitalizador', '/dashboard-admin/digitalizador', 'archivo', 'Digitalizador de métodos'],
          ['catalogos', '/dashboard-admin/catalogos', 'capas', 'Instrumentos, niveles y logros'],
      ],
      'Institución' => [
          ['comunicados', '/dashboard-admin/comunicados', 'campana', 'Comunicados'],
          ['auditoria', '/dashboard-admin/auditoria', 'escudo', 'Bitácora de seguridad'],
          ['perfil', '/dashboard-admin/perfil', 'perfil', 'Mi perfil'],
      ],
  ];
@endphp
@foreach($grupos as $titulo => $enlaces)
  @if($titulo)<span class="app-nav__sep">{{ $titulo }}</span>@endif
  @foreach($enlaces as [$clave, $ruta, $icono, $texto])
    <a href="{{ url($ruta) }}" @if($activo === $clave) aria-current="page" @endif><svg class="icon" aria-hidden="true"><use href="{{ $iconos }}#i-{{ $icono }}"/></svg> {{ $texto }}</a>
  @endforeach
@endforeach
