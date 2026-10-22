{{-- Metadatos de la pagina. Variables (todas opcionales): $seoTitulo, $seoDescripcion,
     $seoIndexar (true = la pagina se puede encontrar en Google), $seoJsonLd (array de bloques). --}}
@php
  $sitio = config('msea.sitio');
  $seoTitulo = $seoTitulo ?? $sitio['nombre_corto'];
  $seoDescripcion = $seoDescripcion ?? $sitio['descripcion'];
  $seoIndexar = $seoIndexar ?? false;
  $seoCanonical = url()->current();
  $seoImagen = asset('assets/img/compartir.jpg');
@endphp
<title>{{ $seoTitulo }}</title>
<meta name="description" content="{{ $seoDescripcion }}" />
<meta name="robots" content="{{ $seoIndexar ? 'index, follow, max-image-preview:large' : 'noindex, nofollow' }}" />
@if($seoIndexar)
<link rel="canonical" href="{{ $seoCanonical }}" />
<meta property="og:type" content="website" />
<meta property="og:locale" content="es_BO" />
<meta property="og:site_name" content="{{ $sitio['nombre_corto'] }} · Movimiento Sinfónico de El Alto" />
<meta property="og:title" content="{{ $seoTitulo }}" />
<meta property="og:description" content="{{ $seoDescripcion }}" />
<meta property="og:url" content="{{ $seoCanonical }}" />
<meta property="og:image" content="{{ $seoImagen }}" />
<meta property="og:image:width" content="1200" />
<meta property="og:image:height" content="630" />
<meta property="og:image:alt" content="Orquesta infantil de la Fundación MSEA reunida en una sala, con niños y jóvenes con sus instrumentos de cuerda" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $seoTitulo }}" />
<meta name="twitter:description" content="{{ $seoDescripcion }}" />
<meta name="twitter:image" content="{{ $seoImagen }}" />
@endif
@if(!empty($seoJsonLd))
<script type="application/ld+json" nonce="{!! $cspNonce ?? '' !!}">{!! \App\Support\DatosEstructurados::json(...$seoJsonLd) !!}</script>
@endif
