<?php

namespace App\Support;

/**
 * Datos estructurados (JSON-LD, schema.org) para buscadores.
 * Solo incluye datos reales: telefono, correo, direccion y redes salen del .env y
 * se omiten si estan vacios.
 */
class DatosEstructurados
{
    public static function organizacion(): array
    {
        $s = config('msea.sitio');

        $direccion = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $s['direccion'] ?: null,
            'addressLocality' => $s['ciudad'],
            'addressCountry' => $s['codigo_pais'],
        ]);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => ['EducationalOrganization', 'NGO'],
            '@id' => url('/').'#organizacion',
            'name' => $s['razon_social'],
            'alternateName' => ['MSEA', 'Movimiento Sinfónico de El Alto'],
            'url' => url('/'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset('icon-512.png'),
                'width' => 512,
                'height' => 512,
            ],
            'image' => asset('assets/img/compartir.jpg'),
            'description' => $s['descripcion'],
            'address' => $direccion,
            'areaServed' => ['@type' => 'City', 'name' => $s['ciudad']],
            'knowsAbout' => ['Violín', 'Viola', 'Violonchelo', 'Contrabajo', 'Educación musical infantil'],
            'telephone' => $s['telefono'] ?: null,
            'email' => $s['correo'] ?: null,
            'vatID' => $s['nit'] ?: null,
            'sameAs' => $s['redes'] ?: null,
        ]);
    }

    public static function sitioWeb(): array
    {
        $s = config('msea.sitio');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => url('/').'#sitio',
            'url' => url('/'),
            'name' => $s['nombre_corto'].' · Movimiento Sinfónico de El Alto',
            'inLanguage' => 'es-BO',
            'publisher' => ['@id' => url('/').'#organizacion'],
        ];
    }

    /** @param  array<string,string>  $migas  nombre => url */
    public static function migas(array $migas): array
    {
        $items = [];
        $i = 1;
        foreach ($migas as $nombre => $url) {
            $items[] = ['@type' => 'ListItem', 'position' => $i++, 'name' => $nombre, 'item' => $url];
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /** JSON seguro para incrustar dentro de <script type="application/ld+json">. */
    public static function json(array ...$bloques): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP;
        $datos = count($bloques) === 1 ? $bloques[0] : $bloques;

        return json_encode($datos, $flags);
    }
}
