<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad + Content-Security-Policy.
 *
 * - Scripts y fuentes: solo archivos propios (Nunito y Baloo 2 estan en
 *   public/fonts), mas los <script>
 *   en linea que lleven el nonce de la peticion ($cspNonce en Blade). Un
 *   <script> o un onerror="" inyectado por XSS NO se ejecuta.
 * - Estilos: solo hojas propias (sin 'unsafe-inline', FE-09). Las barras de
 *   progreso reciben su medida por data-ancho/data-alto y CSSOM (panel-layout.js),
 *   asi un style="" inyectado no se aplica.
 * - Microfono permitido solo para el propio sitio (afinador y grabaciones).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);
        View::share('cspNonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(self)');
        // same-origin-allow-popups: necesario para la ventana de "Continuar con Google".
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');

        // Google Identity Services solo se permite si esta configurado (GOOGLE_CLIENT_ID).
        $g = config('services.google.client_id') ? ' https://accounts.google.com/gsi/' : '';

        // Analitica opcional (apagada por defecto): se permite solo el servicio configurado.
        $ga = config('msea.sitio.analitica.ga4') ? ' https://www.googletagmanager.com' : '';
        $gaConexion = config('msea.sitio.analitica.ga4')
            ? ' https://*.google-analytics.com https://*.analytics.google.com https://www.googletagmanager.com' : '';
        $pl = config('msea.sitio.analitica.plausible_dominio')
            ? ' '.(parse_url((string) config('msea.sitio.analitica.plausible_script'), PHP_URL_SCHEME) ?: 'https').'://'
                .(parse_url((string) config('msea.sitio.analitica.plausible_script'), PHP_URL_HOST) ?: 'plausible.io') : '';

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', implode('; ', array_filter([
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'".($g ? ' https://accounts.google.com/gsi/client' : '').$ga.$pl,
                "style-src 'self'".($g ? ' https://accounts.google.com/gsi/style' : ''),
                "font-src 'self' data:",
                "img-src 'self' data: blob:".($g ? ' https://*.googleusercontent.com' : '').($ga ? ' https://*.google-analytics.com https://*.googletagmanager.com' : ''),
                "media-src 'self' blob:",
                "connect-src 'self'".$g.$gaConexion.$pl,
                $g ? 'frame-src'.$g : null,
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
            ])));
        }

        // Buscadores: solo la portada y los textos legales se indexan. Paneles, login,
        // registro y recuperacion de contrasena llevan noindex.
        if (! $request->is('/', 'aviso-legal', 'politica-de-privacidad', 'politica-de-cookies', 'sitemap.xml', 'robots.txt')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // Las paginas HTML no se guardan en cache: al volver con la flecha del
        // navegador tras cerrar sesion se pide de nuevo al servidor (y redirige al
        // login) en lugar de mostrar un panel viejo con un token CSRF vencido.
        if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            || ($request->hasSession() && $request->session()->has('usuario_id'))) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
