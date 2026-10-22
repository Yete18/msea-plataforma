<?php

namespace Tests\Feature\Seguridad;

use App\Jobs\DigitalizarPartitura;
use App\Services\PasswordResetService;
use App\Services\ProfesorDashboardService;
use Database\Seeders\CatalogosSeeder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/**
 * Fase B de la auditoria 2026-10: datos, rendimiento y operacion.
 * BD-04/05/06/07/10/15, ARQ-01/02/07/08, UX-02, SEC-07/09/11.
 */
class FaseBDatosTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogosSeeder::class);
    }

    // --- BD-04 -------------------------------------------------------------

    public function test_catalogos_de_produccion_sin_cuentas_e_idempotentes(): void
    {
        $this->seed(CatalogosSeeder::class);

        $this->assertSame(4, DB::table('instrumentos')->count());
        $this->assertSame(6, DB::table('secciones')->count());
        $this->assertSame(5, DB::table('logros')->count());
        $this->assertSame(4, DB::table('roles')->count());
        $this->assertSame(0, DB::table('usuarios')->count());
    }

    public function test_el_seeder_no_pisa_logros_editados_ni_duplica_por_mayusculas(): void
    {
        DB::table('logros')->where('codigo', 'racha_3')->update(['xp_bonus' => 99]);
        DB::table('instrumentos')->where('nombre', 'Viola')->update(['nombre' => 'viola']);

        $this->seed(CatalogosSeeder::class);

        $this->assertSame(99, (int) DB::table('logros')->where('codigo', 'racha_3')->value('xp_bonus'));
        $this->assertSame(4, DB::table('instrumentos')->count());
    }

    // --- BD-05 -------------------------------------------------------------

    public function test_el_registro_publico_no_crea_instrumentos(): void
    {
        $datos = [
            'rol' => 'estudiante', 'correo' => 'nuevo@msea.test', 'contrasena' => 'clave1234', 'contrasena_confirmation' => 'clave1234',
            'nombres' => 'Nuevo', 'apellido_paterno' => 'Alumno', 'acepta_privacidad' => '1',
        ];

        $this->post('/registro', $datos + ['instrumento' => 'Kazoo'])->assertSessionHasErrors('instrumento');
        $this->assertSame(4, DB::table('instrumentos')->count());
        $this->assertDatabaseMissing('usuarios', ['correo' => 'nuevo@msea.test']);

        $this->post('/registro', $datos + ['instrumento' => 'chelo'])->assertSessionHasNoErrors();
        $this->assertSame(4, DB::table('instrumentos')->count());
        $idUsuario = DB::table('usuarios')->where('correo', 'nuevo@msea.test')->value('id_usuario');
        $this->assertSame(
            DB::table('instrumentos')->where('nombre', 'Violonchelo')->value('id_instrumento'),
            DB::table('usuario_instrumento')->where('id_usuario', $idUsuario)->value('id_instrumento')
        );
    }

    // --- BD-10 -------------------------------------------------------------

    public function test_solo_una_practica_en_curso_a_la_vez(): void
    {
        $alumno = $this->crearEstudiante();

        $primera = $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/practicas', ['tipo' => 'ritmo'])->assertCreated()->json('id_practica');
        $this->travel(5)->minutes();
        $segunda = $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/practicas', ['tipo' => 'afinacion'])->assertCreated()->json('id_practica');

        $this->assertSame('finalizada', DB::table('practicas_autonomas')->where('id_practica_autonoma', $primera)->value('estado'));
        $this->assertSame(300, (int) DB::table('practicas_autonomas')->where('id_practica_autonoma', $primera)->value('duracion_segundos'));
        $this->assertSame('en_curso', DB::table('practicas_autonomas')->where('id_practica_autonoma', $segunda)->value('estado'));
        $this->assertSame(1, DB::table('practicas_autonomas')->where('id_estudiante', $alumno['estudiante'])->where('estado', 'en_curso')->count());
    }

    // --- SEC-11 y ARQ-08 ---------------------------------------------------

    public function test_un_director_no_cambia_el_correo_de_otro_director(): void
    {
        $director = $this->crearUsuario('director');
        $otro = $this->crearUsuario('director');
        $correoOriginal = DB::table('usuarios')->where('id_usuario', $otro)->value('correo');

        $this->sesionDe($director)->put("/dashboard-admin/usuarios/{$otro}", [
            'nombres' => 'Otro', 'correo' => 'atacante@msea.test',
        ])->assertSessionHasErrors('correo');
        $this->assertSame($correoOriginal, DB::table('usuarios')->where('id_usuario', $otro)->value('correo'));

        // Su nombre si puede corregirlo, y el correo de un estudiante tambien.
        $this->sesionDe($director)->put("/dashboard-admin/usuarios/{$otro}", ['nombres' => 'Corregido', 'correo' => $correoOriginal])->assertSessionHasNoErrors();
        $this->assertSame('Corregido', DB::table('usuarios')->where('id_usuario', $otro)->value('nombres'));

        $alumno = $this->crearEstudiante();
        $this->sesionDe($director)->put("/dashboard-admin/usuarios/{$alumno['usuario']}", ['nombres' => 'Ana', 'correo' => 'ana.nueva@msea.test'])->assertSessionHasNoErrors();
        $this->assertSame('ana.nueva@msea.test', DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('correo'));
    }

    // --- UX-02 -------------------------------------------------------------

    public function test_eliminar_elenco_pide_confirmacion(): void
    {
        $director = $this->crearUsuario('director');
        $this->crearElenco('Cuerdas');

        $html = $this->sesionDe($director)->get('/dashboard-admin')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-confirmar="[^"]*Cuerdas[^"]*"[^>]*>.*?Eliminar elenco/s', $html);
        $this->assertStringContainsString('js/admin-gestion.js', $html);
    }

    public function test_no_se_repiten_nombres_de_elenco(): void
    {
        $director = $this->crearUsuario('director');
        DB::table('elencos')->insert(['nombre' => 'Orquesta Juvenil', 'id_tipo' => DB::table('tipos_elencos')->value('id_tipo')]);

        $this->sesionDe($director)->post('/dashboard-admin/elencos', ['nombre' => 'orquesta juvenil', 'tipo' => 'Orquesta'])
            ->assertSessionHasErrors('nombre');
        $this->assertSame(1, DB::table('elencos')->whereRaw('lower(nombre) = ?', ['orquesta juvenil'])->count());
    }

    // --- ARQ-01 ------------------------------------------------------------

    public function test_el_panel_del_profesor_no_hace_una_consulta_por_tarea_ni_por_alumno(): void
    {
        $prof = $this->crearProfesor();
        $servicio = app(ProfesorDashboardService::class);

        $consultas = function () use ($servicio, $prof): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $servicio->tareas($prof['profesor']);
            $servicio->alumnos($prof['profesor']);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        for ($i = 0; $i < 2; $i++) {
            $alumno = $this->crearEstudiante($prof['profesor']);
            $this->crearTarea($prof['profesor'], $alumno['estudiante']);
        }
        $pocas = $consultas();

        for ($i = 0; $i < 10; $i++) {
            $alumno = $this->crearEstudiante($prof['profesor']);
            $this->crearTarea($prof['profesor'], $alumno['estudiante']);
        }

        $this->assertSame($pocas, $consultas());
    }

    // --- ARQ-02 ------------------------------------------------------------

    public function test_el_digitalizador_usa_una_cola_larga_y_no_se_duplica(): void
    {
        $job = new DigitalizarPartitura(7);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('7', $job->uniqueId());
        $this->assertSame('database-largo', $job->connection);
        $this->assertGreaterThan($job->timeout, (int) config('queue.connections.database-largo.retry_after'));
    }

    // --- ARQ-07 ------------------------------------------------------------

    public function test_la_limpieza_diaria_borra_solo_lo_vencido(): void
    {
        $alumno = $this->crearEstudiante();
        $token = fn (string $nombre, $expira) => DB::table('mobile_api_tokens')->insert([
            'id_usuario' => $alumno['usuario'], 'device_name' => $nombre, 'token_hash' => hash('sha256', $nombre),
            'expires_at' => $expira, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $token('viejo', now()->subDays(30));
        $token('vigente', now()->addDays(10));
        DB::table('password_reset_codes')->insert([
            ['correo' => 'a@msea.test', 'codigo' => 'x', 'expires_at' => now()->subDays(9), 'created_at' => now()->subDays(10), 'intentos' => 0],
            ['correo' => 'b@msea.test', 'codigo' => 'y', 'expires_at' => now()->addMinutes(5), 'created_at' => now(), 'intentos' => 0],
        ]);

        $this->artisan('msea:limpiar')->assertSuccessful();

        $this->assertSame(['vigente'], DB::table('mobile_api_tokens')->pluck('device_name')->all());
        $this->assertSame(['b@msea.test'], DB::table('password_reset_codes')->pluck('correo')->all());
    }

    // --- SEC-07 ------------------------------------------------------------

    public function test_en_produccion_no_se_envia_el_codigo_por_el_log(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $alumno = $this->crearEstudiante();
        $correo = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('correo');

        $this->app['env'] = 'production';
        try {
            app(PasswordResetService::class)->enviarCodigoSiUsuarioExiste($correo);
        } finally {
            $this->app['env'] = 'testing';
        }

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertDatabaseHas('auditoria', ['accion' => 'recuperacion_sin_correo']);
    }

    // --- ARQ-04 ------------------------------------------------------------

    public function test_la_migracion_no_deja_contrasenas_en_texto_plano(): void
    {
        $id = $this->crearUsuario('estudiante', 'x', ['contrasena' => 'plana-123']);

        (require database_path('migrations/2026_10_17_000003_msea_hashear_contrasenas_antiguas.php'))->up();

        $guardada = (string) DB::table('usuarios')->where('id_usuario', $id)->value('contrasena');
        $this->assertNotSame('plana-123', $guardada);
        $this->assertTrue(password_verify('plana-123', $guardada));
    }

    // --- BD-15 -------------------------------------------------------------

    public function test_la_semilla_de_tecnica_esta_congelada(): void
    {
        $semilla = json_decode((string) file_get_contents(database_path('data/2026_10_16_tecnica_mano_izquierda.json')), true);

        $this->assertCount(92, $semilla);
        $this->assertSame(92, DB::table('biblioteca_ejercicios')->where('categoria', 'tecnica_mano_izquierda')->count());
        $migracion = (string) file_get_contents(database_path('migrations/2026_10_16_000001_msea_tecnica_y_digitalizador.php'));
        $this->assertStringNotContainsString('TecnicaService::', $migracion);
    }

    // --- BD-07 (solo PostgreSQL) ---------------------------------------------

    public function test_postgres_rechaza_estados_y_notas_invalidas(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Las restricciones CHECK se crean solo en PostgreSQL.');
        }

        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $idTarea = $this->crearTarea($prof['profesor'], $alumno['estudiante']);

        foreach ([['estado' => 'inventado'], ['estado' => 'calificada', 'calificacion' => 150]] as $fila) {
            try {
                DB::transaction(fn () => DB::table('entregas_tareas')->insert($fila + ['id_tarea' => $idTarea, 'id_estudiante' => $alumno['estudiante']]));
                $this->fail('La base acepto una entrega invalida.');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->getCode());
            }
        }
    }
}
