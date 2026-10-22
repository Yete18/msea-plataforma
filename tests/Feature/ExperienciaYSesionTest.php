<?php

namespace Tests\Feature;

use App\Services\GamificationService;
use App\Services\HabitosService;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/**
 * Cerrar sesion / flecha atras (error 419) y los detalles de experiencia:
 * meta diaria, semana, racha vigente, atencion del profesor y pulso del director.
 */
class ExperienciaYSesionTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
    }

    /* ------------------------------------------------------------------ sesion */

    public function test_con_sesion_abierta_login_y_registro_llevan_al_panel(): void
    {
        $alumno = $this->crearEstudiante();

        $this->sesionDe($alumno['usuario'])->get('/login')->assertRedirect('/dashboard-estudiante');
        $this->sesionDe($alumno['usuario'])->get('/registro')->assertRedirect('/dashboard-estudiante');
        $this->flushSession();
        $this->get('/login')->assertOk();
    }

    public function test_paginas_con_sesion_no_se_guardan_en_cache_del_navegador(): void
    {
        $alumno = $this->crearEstudiante();

        $cache = $this->sesionDe($alumno['usuario'])->get('/dashboard-estudiante')->assertOk()->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cache);
        $this->assertStringContainsString('private', $cache);
    }

    public function test_token_csrf_vencido_en_cerrar_sesion_cierra_igual_y_lleva_al_login(): void
    {
        $alumno = $this->crearEstudiante();
        $this->sesionDe($alumno['usuario'])->get('/dashboard-estudiante');

        $request = Request::create('/logout', 'POST');
        $request->setLaravelSession($this->app['session.store']);

        $respuesta = $this->app->make(ExceptionHandler::class)->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(302, $respuesta->getStatusCode());
        $this->assertStringEndsWith('/login', $respuesta->headers->get('Location'));
        $this->assertFalse($this->app['session.store']->has('usuario_id'));
    }

    public function test_token_csrf_vencido_no_muestra_la_pagina_419(): void
    {
        Route::middleware('web')->post('/prueba-419', fn () => throw new TokenMismatchException('CSRF token mismatch.'));

        // Sin sesion: al login con aviso.
        $this->post('/prueba-419', ['correo' => 'a@b.pe', 'contrasena' => 'no-se-reenvia'])
            ->assertRedirect('/login')
            ->assertSessionHas('error');
        $this->assertNull(session()->getOldInput('contrasena'));

        // Con sesion: vuelve a la pagina anterior con aviso (no cierra la sesion).
        $alumno = $this->crearEstudiante();
        $this->sesionDe($alumno['usuario'])->from('/dashboard-estudiante')->post('/prueba-419')
            ->assertRedirect('/dashboard-estudiante')
            ->assertSessionHas('error');

        // Peticiones JSON: 419 con mensaje legible para que msea-ui.js renueve el token.
        $this->postJson('/prueba-419')->assertStatus(419)->assertJsonPath('csrf_vencido', true);
    }

    public function test_csrf_token_fresco_para_paneles_abiertos_mucho_tiempo(): void
    {
        $this->getJson('/csrf-token')->assertOk()->assertJsonStructure(['token']);
    }

    /* ------------------------------------------------------------------ estudiante */

    public function test_meta_diaria_se_guarda_y_valida(): void
    {
        $alumno = $this->crearEstudiante();

        $panel = $this->sesionDe($alumno['usuario'])->get('/dashboard-estudiante')->assertOk();
        $this->assertNull($panel->viewData('dashboardData')['metaDiaria']);
        $this->assertCount(7, $panel->viewData('dashboardData')['semana']);

        $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/meta-diaria', ['minutos' => 25])->assertStatus(422);
        $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/meta-diaria', ['minutos' => 20])
            ->assertOk()->assertJsonPath('meta', 20);

        $this->assertDatabaseHas('estudiantes', ['id_estudiante' => $alumno['estudiante'], 'meta_diaria_minutos' => 20]);

        // Otro rol no puede tocar la meta.
        $profe = $this->crearProfesor();
        $this->sesionDe($profe['usuario'])->postJson('/dashboard-estudiante/meta-diaria', ['minutos' => 20])->assertForbidden();
    }

    public function test_finalizar_practica_devuelve_minutos_de_hoy_y_novedades(): void
    {
        $alumno = $this->crearEstudiante();
        $inicio = $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/practicas', ['tipo' => 'tecnica'])->assertCreated();
        $this->travel(120)->seconds();

        $r = $this->sesionDe($alumno['usuario'])
            ->postJson('/dashboard-estudiante/practicas/'.$inicio->json('id_practica').'/finalizar')
            ->assertOk()->json();

        $this->assertEquals(2, $r['minutos_hoy']);
        $this->assertSame(1, $r['racha']);
        $this->assertArrayHasKey('subio_nivel', $r);
        $this->assertIsArray($r['logros_nuevos']);
    }

    public function test_la_racha_vencida_se_muestra_en_cero(): void
    {
        $alumno = $this->crearEstudiante();
        DB::table('gamificacion_perfiles')->insert([
            'id_estudiante' => $alumno['estudiante'], 'xp_total' => 0, 'nivel' => 1,
            'racha_actual' => 5, 'mejor_racha' => 5, 'ultima_practica' => now()->subDays(3)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resumen = app(GamificationService::class)->resumen($alumno['estudiante']);

        $this->assertSame(0, $resumen['racha_actual']);
        $this->assertSame(5, $resumen['mejor_racha']);
    }

    public function test_titulos_de_nivel(): void
    {
        $habitos = app(HabitosService::class);

        $this->assertSame('Semilla musical', $habitos->tituloNivel(1));
        $this->assertSame('Joven músico', $habitos->tituloNivel(5));
        $this->assertSame('Maestro de la orquesta', $habitos->tituloNivel(40));
    }

    /* ------------------------------------------------------------------ profesor, padre y director */

    public function test_profesor_ve_alumnos_que_requieren_atencion_y_practica_del_grupo(): void
    {
        $profe = $this->crearProfesor();
        $alumno = $this->crearEstudiante($profe['profesor']);
        DB::table('gamificacion_perfiles')->insert([
            'id_estudiante' => $alumno['estudiante'], 'xp_total' => 0, 'nivel' => 1, 'racha_actual' => 2, 'mejor_racha' => 2,
            'ultima_practica' => now()->subDays(6)->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $vista = $this->sesionDe($profe['usuario'])->get('/dashboard-profesor')->assertOk();
        $fila = collect($vista->viewData('alumnosData'))->firstWhere('id', $alumno['estudiante']);

        $this->assertSame(6, $fila['diasSinPracticar']);
        $this->assertSame(0, $fila['racha']);
        $this->assertSame($alumno['usuario'], $fila['idUsuario']);
        $this->assertCount(7, $vista->viewData('practicaGrupoData'));
    }

    public function test_padre_ve_resumen_semanal_con_consejo(): void
    {
        $alumno = $this->crearEstudiante(null, null, ['nombres' => 'Lucia']);
        $padre = $this->crearPadre([$alumno['estudiante']]);

        $this->sesionDe($padre['usuario'])->get('/dashboard-padre')->assertOk()
            ->assertSee('Resumen de la semana')
            ->assertSee('Lucia aún no registró práctica');
    }

    public function test_director_ve_pulso_y_pendientes_accionables(): void
    {
        $this->crearEstudiante(); // sin profesor, sin elenco y sin familia
        $director = $this->crearUsuario('director');

        $vista = $this->sesionDe($director)->get('/dashboard-admin')->assertOk();
        $pulso = $vista->viewData('pulsoData');

        $this->assertSame(1, $pulso['estudiantesActivos']);
        $this->assertSame(0, $pulso['practicaronSemana']);
        $textos = collect($pulso['pendientes'])->pluck('texto')->implode(' | ');
        $this->assertStringContainsString('sin profesor asignado', $textos);
        $this->assertStringContainsString('sin familia vinculada', $textos);
        $vista->assertSee('Pendientes para ti');
    }

    public function test_todas_las_paginas_usan_fuentes_locales_sin_bootstrap_ni_lucide(): void
    {
        $alumno = $this->crearEstudiante();
        $html = $this->sesionDe($alumno['usuario'])->get('/dashboard-estudiante')->getContent();
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringContainsString('css/fuentes.css', $html);

        $this->flushSession();
        $login = $this->get('/login');
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $login->getContent());
        $this->assertStringNotContainsString('jsdelivr', $login->headers->get('Content-Security-Policy'));
        $this->assertFileExists(public_path('fonts/nunito-latin-700-normal.woff2'));
        // Fase C: un solo sistema de diseño (tokens + componentes + sprite SVG).
        foreach ([$html, $login->getContent()] as $pagina) {
            $this->assertStringNotContainsString('bootstrap', $pagina);
            $this->assertStringNotContainsString('data-lucide', $pagina);
            $this->assertStringNotContainsString('js/vendor/lucide', $pagina);
            $this->assertStringContainsString('css/tokens.css', $pagina);
        }
    }
}
