<?php

namespace App\Providers;

use App\Services\AuthBrandStatsService;
use App\Services\AuthService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // SEC-07: con APP_DEBUG=true un error muestra rutas, consultas y variables.
        // En produccion el sitio web no arranca asi (artisan si, para poder corregirlo).
        if ($this->app->isProduction() && config('app.debug') && ! $this->app->runningInConsole()) {
            throw new \RuntimeException('APP_DEBUG=true no se permite en produccion. Pon APP_DEBUG=false en .env y ejecuta php artisan config:cache.');
        }

        // FE-08: sprite de iconos como archivo aparte (se guarda en la cache del
        // navegador). La version cambia con el archivo para no servir uno viejo.
        View::share('iconos', $this->urlSpriteIconos());

        View::composer(['login', 'registro', 'forgot-password', 'verify-code', 'reset-password'], function ($view) {
            $view->with('authMetrics', app(AuthBrandStatsService::class)->metrics());
        });

        View::composer('registro', function ($view) {
            $view->with('nivelesRegistro', DB::table('secciones')
                ->where('nombre', '<>', 'General')->orderBy('id_seccion')->pluck('nombre')->all());
        });

        $correo = fn (Request $request) => AuthService::normalizarCorreo((string) $request->input('correo'));
        $mensaje = fn (string $texto) => fn (Request $request, array $headers) => $request->expectsJson()
            ? response()->json(['message' => $texto], 429, $headers)
            : back()->withInput($request->only('correo', 'rol'))->with('error', $texto);

        // Login web y movil: por cuenta+IP y por IP (para quien prueba muchas cuentas).
        foreach (['login', 'mobile-login'] as $nombre) {
            RateLimiter::for($nombre, fn (Request $request) => [
                Limit::perMinute(5)->by($nombre.'|'.$correo($request).'|'.$request->ip())
                    ->response($mensaje('Demasiados intentos. Espera un minuto e intenta de nuevo.')),
                Limit::perMinute(20)->by($nombre.'|ip|'.$request->ip())
                    ->response($mensaje('Demasiados intentos desde esta conexión. Espera un minuto.')),
            ]);
        }

        RateLimiter::for('mobile-api', fn (Request $request) => Limit::perMinute(120)
            ->by('api|'.sha1((string) $request->bearerToken()).'|'.$request->ip()));

        // Recuperacion: por correo (cambiar de IP no sirve) y por IP.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by('reset|'.$correo($request))->response($mensaje('Ya enviamos un código hace poco. Espera unos minutos.')),
            Limit::perMinutes(10, 10)->by('reset|ip|'.$request->ip())->response($mensaje('Demasiadas solicitudes. Espera unos minutos.')),
        ]);

        RateLimiter::for('reset-code', fn (Request $request) => [
            Limit::perMinutes(10, 10)->by('code|'.(string) $request->session()->get('password_reset_correo'))
                ->response($mensaje('Demasiados intentos. Solicita un código nuevo.')),
            Limit::perMinutes(10, 20)->by('code|ip|'.$request->ip())->response($mensaje('Demasiados intentos. Espera unos minutos.')),
        ]);

        RateLimiter::for('sensible', fn (Request $request) => Limit::perMinute(10)
            ->by('sens|'.$request->session()->get('usuario_id').'|'.$request->ip()));
    }

    private function urlSpriteIconos(): string
    {
        $ruta = public_path('assets/img/iconos.svg');
        $version = is_file($ruta) ? substr(md5((string) filemtime($ruta).filesize($ruta)), 0, 8) : '0';

        return asset('assets/img/iconos.svg').'?v='.$version;
    }
}
