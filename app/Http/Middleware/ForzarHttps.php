<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lleva todo el trafico a HTTPS.
 *
 * - Activo por defecto solo en produccion (APP_ENV=production). Se puede forzar con
 *   MSEA_FORZAR_HTTPS=true/false.
 * - Detras de un proxy (Nginx, Cloudflare...) hay que declararlo en MSEA_PROXIES para
 *   que Laravel sepa que la peticion original ya era segura; si no, habria un bucle.
 * - GET/HEAD se redirigen con 301; el resto con 308 para no perder el cuerpo del envio.
 * - /up (salud del servidor) queda fuera para que los monitores funcionen.
 */
class ForzarHttps
{
    public static function activo(): bool
    {
        $valor = config('msea.sitio.forzar_https');

        if ($valor === null || $valor === '') {
            return app()->environment('production');
        }

        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::activo()) {
            return $next($request);
        }

        URL::forceScheme('https');

        if ($request->isSecure() || $request->is('up')) {
            return $next($request);
        }

        $destino = 'https://'.$request->getHttpHost().$request->getRequestUri();

        return redirect()->to($destino, $request->isMethodSafe() ? 301 : 308);
    }
}
