<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/** Paginas publicas: textos legales, sitemap.xml y robots.txt. */
class PublicoController extends Controller
{
    /** Paginas que buscadores pueden indexar (portada y textos legales). */
    private const INDEXABLES = [
        ['ruta' => '/', 'cambio' => 'monthly', 'prioridad' => '1.0'],
        ['ruta' => '/aviso-legal', 'cambio' => 'yearly', 'prioridad' => '0.3'],
        ['ruta' => '/politica-de-privacidad', 'cambio' => 'yearly', 'prioridad' => '0.3'],
        ['ruta' => '/politica-de-cookies', 'cambio' => 'yearly', 'prioridad' => '0.3'],
    ];

    public function avisoLegal()
    {
        return view('legal.aviso-legal');
    }

    public function privacidad()
    {
        return view('legal.privacidad');
    }

    public function cookies()
    {
        return view('legal.cookies');
    }

    public function sitemap(): Response
    {
        $fecha = config('msea.sitio.legal_actualizado');
        $urls = array_map(fn (array $p) => [
            'loc' => url($p['ruta']),
            'lastmod' => $fecha,
            'changefreq' => $p['cambio'],
            'priority' => $p['prioridad'],
        ], self::INDEXABLES);

        return response()->view('legal.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lineas = [
            'User-agent: *',
            'Allow: /$',
            'Allow: /aviso-legal',
            'Allow: /politica-de-privacidad',
            'Allow: /politica-de-cookies',
            // Zonas privadas. El resto de rutas internas ya responde con noindex.
            'Disallow: /login',
            'Disallow: /registro',
            'Disallow: /forgot-password',
            'Disallow: /verify-code',
            'Disallow: /reset-password',
            'Disallow: /dashboard-',
            'Disallow: /archivos/',
            'Disallow: /api/',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ];

        return response(implode("\n", $lineas), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
