@php($s = config('msea.sitio'))
<ul>
  <li><strong>Titular:</strong> {{ $s['razon_social'] }}, {{ $s['ciudad'] }}, {{ $s['pais'] }}.</li>
  @if($s['nit'])<li><strong>NIT:</strong> {{ $s['nit'] }}</li>@endif
  @if($s['direccion'])<li><strong>Dirección:</strong> {{ $s['direccion'] }}</li>@endif
  @if($s['correo'])<li><strong>Correo de contacto:</strong> <a href="mailto:{{ $s['correo'] }}">{{ $s['correo'] }}</a></li>@endif
  @if($s['telefono'])<li><strong>Teléfono:</strong> {{ $s['telefono'] }}</li>@endif
  @if($s['whatsapp'])<li><strong>WhatsApp:</strong> <a href="https://wa.me/{{ $s['whatsapp'] }}" rel="noopener noreferrer" target="_blank">escribir por WhatsApp</a></li>@endif
  @if(! $s['correo'] && ! $s['telefono'] && ! $s['whatsapp'])
    <li>Puedes comunicarte con la dirección de la fundación a través de tu profesor o de la dirección de la academia.</li>
  @endif
</ul>
