<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

/**
 * Proteccion basica contra robots en los formularios publicos (sin captcha de terceros,
 * sin cookies nuevas y sin enviar datos a otros servicios):
 *
 * 1. Campo trampa ("sitio_web"): las personas no lo ven; los robots lo rellenan.
 * 2. Tiempo minimo: el formulario lleva la hora de carga firmada (cifrada). Un envio
 *    que llega antes de "segundos_minimos" no lo hizo una persona.
 *
 * Se combina con los limites de intentos (throttle) que ya tiene cada ruta.
 */
class ProteccionSpam
{
    public const CAMPO_TRAMPA = 'sitio_web';

    public const CAMPO_HORA = 'formulario_t';

    public static function horaFirmada(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('msea.sitio.antispam.activo', true)) {
            return $next($request);
        }

        if (filled($request->input(self::CAMPO_TRAMPA))) {
            return $this->rechazar($request, 'No pudimos enviar el formulario. Intenta de nuevo.');
        }

        try {
            $hora = (int) Crypt::decryptString((string) $request->input(self::CAMPO_HORA));
        } catch (DecryptException) {
            return $this->rechazar($request, 'El formulario venció. Recarga la página e intenta de nuevo.');
        }

        $segundos = now()->getTimestamp() - $hora;

        if ($segundos < (int) config('msea.sitio.antispam.segundos_minimos', 2)) {
            return $this->rechazar($request, 'Fue muy rápido. Espera un momento y vuelve a enviar.');
        }

        if ($segundos > 86400) {
            return $this->rechazar($request, 'El formulario venció. Recarga la página e intenta de nuevo.');
        }

        return $next($request);
    }

    private function rechazar(Request $request, string $mensaje): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $mensaje], 422);
        }

        return back()->withInput($request->except([
            'contrasena', 'contrasena_confirmation', 'password', 'password_confirmation',
            self::CAMPO_TRAMPA, self::CAMPO_HORA,
        ]))->with('error', $mensaje);
    }
}
