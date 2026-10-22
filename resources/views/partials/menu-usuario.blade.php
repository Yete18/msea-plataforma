{{--
  Menu del usuario en la cabecera: siempre deja "Cerrar sesion" a un clic,
  tambien en movil (donde la barra lateral esta oculta).
  Parametros: $nombre, $detalle, $enlaces = [[href, icono, texto, data-page?]], $avatarId (opcional)
--}}
@php($enlaces = $enlaces ?? [])
<div class="menu-usuario" data-menu-usuario>
  <button class="header-avatar-btn" type="button" aria-haspopup="menu" aria-expanded="false" data-menu-usuario-boton title="Mi cuenta">
    @if(!empty($avatarId))
      <img hidden src="" alt="" class="header-avatar-img" id="{{ $avatarId }}-img" />
      <span class="header-avatar-fallback" id="{{ $avatarId }}-fallback"><svg class="icon"><use href="{{ $iconos }}#i-perfil"/></svg></span>
    @else
      <span class="header-avatar-fallback"><svg class="icon"><use href="{{ $iconos }}#i-perfil"/></svg></span>
    @endif
  </button>
  <div class="menu-usuario-panel" role="menu">
    <div class="menu-usuario-cabecera">
      <strong>{{ $nombre ?? 'Mi cuenta' }}</strong>
      <span>{{ $detalle ?? '' }}</span>
    </div>
    @foreach($enlaces as $e)
      <a role="menuitem" href="{{ $e[0] }}" @if(!empty($e[3])) data-page="{{ $e[3] }}" class="nav-link-btn" @endif><svg class="icon"><use href="{{ $iconos }}#i-{{ $e[1] }}"/></svg> {{ $e[2] }}</a>
    @endforeach
    <form action="{{ url('/logout') }}" method="POST">
      @csrf
      <button type="submit" role="menuitem" class="salir"><svg class="icon"><use href="{{ $iconos }}#i-salir"/></svg> Cerrar sesión</button>
    </form>
  </div>
</div>
