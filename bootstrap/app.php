<?php

use App\Http\Controllers\AuthController;
use App\Http\Middleware\AuthenticateMobileToken;
use App\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\ForzarHttps;
use App\Http\Middleware\ProteccionSpam;
use App\Http\Middleware\RedirigirSiAutenticado;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Proxies de confianza (para saber si la peticion original era HTTPS).
        $proxies = trim((string) env('MSEA_PROXIES', ''));
        if ($proxies !== '') {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        // SEC-09: solo se aceptan peticiones dirigidas al dominio de APP_URL (y
        // sus subdominios) o a los de MSEA_HOSTS. Un Host falso responde 400.
        // Laravel no lo aplica en local ni en las pruebas.
        $middleware->trustHosts(at: fn () => array_values(array_map(
            fn (string $h) => '^'.preg_quote($h).'$',
            array_filter(array_map('trim', explode(',', (string) env('MSEA_HOSTS', ''))))
        )), subdomains: true);
        $middleware->prependToGroup('web', ForzarHttps::class);
        $middleware->prependToGroup('api', ForzarHttps::class);
        $middleware->appendToGroup('web', SecurityHeaders::class);
        $middleware->appendToGroup('api', SecurityHeaders::class);
        $middleware->alias([
            'auth.session' => AuthenticateSession::class,
            'role' => RoleMiddleware::class,
            'auth.mobile' => AuthenticateMobileToken::class,
            'invitado' => RedirigirSiAutenticado::class,
            'antispam' => ProteccionSpam::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 (token CSRF vencido): pasa al volver con la flecha del navegador a una
        // pagina vieja o al dejar el panel abierto mucho tiempo. En lugar de la
        // pagina de error se lleva a la persona a un lugar util con un aviso.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'La página estaba desactualizada. Vuelve a intentarlo.',
                    'csrf_vencido' => true,
                ], 419);
            }

            $session = $request->session();

            // Cerrar sesion con un formulario viejo: se cierra igual.
            if ($request->is('logout')) {
                $session->invalidate();
                $session->regenerateToken();

                return redirect('/login')->with('success', 'Sesión cerrada correctamente.');
            }

            $sinClaves = $request->except(['contrasena', 'contrasena_confirmation', 'contrasena_actual', 'nueva_contrasena', 'nueva_contrasena_confirmation', 'password', 'password_confirmation', '_token']);

            if ($session->has('usuario_id') && $session->has('rol')) {
                $destino = url()->previous();
                if (! $destino || $destino === $request->fullUrl()) {
                    $destino = AuthController::rutaInicio($session->get('rol'));
                }

                return redirect($destino)->withInput($sinClaves)
                    ->with('error', 'La página estaba desactualizada. Revisa los datos y vuelve a intentarlo.');
            }

            return redirect('/login')->withInput($sinClaves)
                ->with('error', 'Tu sesión expiró o la página estaba desactualizada. Inicia sesión nuevamente.');
        });
    })->create();
