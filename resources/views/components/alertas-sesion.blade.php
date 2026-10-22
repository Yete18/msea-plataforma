{{-- Mensajes de la sesion (exito, error) y errores de validacion, con el mismo componente. --}}
@if(session('success'))
  <x-alerta tipo="exito" cerrable>{{ session('success') }}</x-alerta>
@endif
@if(session('error'))
  <x-alerta tipo="error" cerrable>{{ session('error') }}</x-alerta>
@endif
@if($errors->any())
  <x-alerta tipo="error">
    <strong>Revisa los datos:</strong>
    @if($errors->count() === 1)
      {{ $errors->first() }}
    @else
      <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    @endif
  </x-alerta>
  {{-- Para asociar cada error a su campo (A11Y-02). No se ejecuta: es solo JSON. --}}
  <script type="application/json" id="msea-errores-campos" nonce="{{ $cspNonce ?? '' }}">@json($errors->toArray())</script>
@endif
