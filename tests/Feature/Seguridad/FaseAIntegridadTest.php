<?php

namespace Tests\Feature\Seguridad;

use App\Services\GamificationService;
use App\Services\PasswordResetService;
use App\Support\ReversionSegura;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/**
 * Fase A de la auditoria 2026-10: seguridad e integridad.
 * SEC-01 (elencos compartidos), SEC-04 (ranking), BD-01/02/09 (borrados),
 * SEC-02 (Google y registro publico), SEC-03 (recuperacion), ARQ-03 (fotos), BD-03.
 */
class FaseAIntegridadTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLhGQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        Storage::fake('local');
        Storage::fake('public');
    }

    /** Elenco con un alumno del profesor A y otro del profesor B; A crea una tarea para el elenco. */
    private function elencoCompartido(): array
    {
        $profA = $this->crearProfesor();
        $profB = $this->crearProfesor();
        $elenco = $this->crearElenco();
        $alumnoA = $this->crearEstudiante($profA['profesor'], $elenco);
        $alumnoB = $this->crearEstudiante($profB['profesor'], $elenco);
        $idTarea = $this->crearTarea($profA['profesor'], null, $elenco);

        return compact('profA', 'profB', 'elenco', 'alumnoA', 'alumnoB', 'idTarea');
    }

    // --- SEC-01: tareas de elenco, opcion 2 --------------------------------

    public function test_la_tarea_de_elenco_solo_llega_a_los_alumnos_del_profesor_que_la_crea(): void
    {
        $d = $this->elencoCompartido();

        $this->sesionDe($d['alumnoB']['usuario'])
            ->postJson("/dashboard-estudiante/tareas/{$d['idTarea']}/entregar", ['comentario_estudiante' => 'x'])
            ->assertNotFound();

        $this->sesionDe($d['alumnoA']['usuario'])
            ->post("/dashboard-estudiante/tareas/{$d['idTarea']}/entregar", ['comentario_estudiante' => 'listo', 'archivo' => $this->audioWav()], ['Accept' => 'application/json'])
            ->assertOk();
    }

    public function test_el_profesor_no_ve_descarga_ni_califica_entregas_de_alumnos_ajenos(): void
    {
        $d = $this->elencoCompartido();
        // Entrega antigua de un alumno ajeno (creada antes de la regla).
        Storage::disk('local')->put('entregas/2026/10/11111111-2222-4333-8444-555566667777.wav', 'audio');
        $idAjena = DB::table('entregas_tareas')->insertGetId([
            'id_tarea' => $d['idTarea'], 'id_estudiante' => $d['alumnoB']['estudiante'], 'estado' => 'entregada',
            'archivo_path' => 'entregas/2026/10/11111111-2222-4333-8444-555566667777.wav', 'archivo_nombre' => 'b.wav',
            'archivo_mime' => 'audio/wav', 'entregado_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ], 'id_entrega');

        $lista = $this->sesionDe($d['profA']['usuario'])->getJson("/dashboard-profesor/tareas/{$d['idTarea']}/entregas")->assertOk();
        $this->assertNotContains($idAjena, collect($lista->json('entregas') ?? $lista->json())->pluck('id_entrega')->all());

        $this->sesionDe($d['profA']['usuario'])->get("/archivos/entregas/{$idAjena}")->assertNotFound();
        $this->sesionDe($d['profA']['usuario'])
            ->postJson("/dashboard-profesor/entregas/{$idAjena}/calificar", ['calificacion' => 100, 'comentario_profesor' => 'x'])
            ->assertNotFound();
        $this->assertSame('entregada', DB::table('entregas_tareas')->where('id_entrega', $idAjena)->value('estado'));
    }

    public function test_solo_se_notifica_a_los_alumnos_propios_del_elenco(): void
    {
        $profA = $this->crearProfesor();
        $profB = $this->crearProfesor();
        $elenco = $this->crearElenco();
        $alumnoA = $this->crearEstudiante($profA['profesor'], $elenco);
        $alumnoB = $this->crearEstudiante($profB['profesor'], $elenco);

        $this->sesionDe($profA['usuario'])->postJson('/dashboard-profesor/tareas', [
            'titulo' => 'Escala de grupo', 'descripcion' => 'Practicar', 'id_elenco' => $elenco, 'xp_recompensa' => 50,
        ])->assertSuccessful();

        $this->assertDatabaseHas('notificaciones', ['id_usuario' => $alumnoA['usuario'], 'tipo' => 'tarea']);
        $this->assertDatabaseMissing('notificaciones', ['id_usuario' => $alumnoB['usuario'], 'tipo' => 'tarea']);
    }

    // --- SEC-04: ranking por elenco con nombre de pila ---------------------

    public function test_el_ranking_es_del_elenco_y_solo_con_nombre_de_pila(): void
    {
        $prof = $this->crearProfesor();
        $elenco = $this->crearElenco();
        $otro = $this->crearElenco('Otro');
        $yo = $this->crearEstudiante($prof['profesor'], $elenco, ['nombres' => 'Carla Andrea', 'apellido_paterno' => 'Mamani']);
        $companero = $this->crearEstudiante($prof['profesor'], $elenco, ['nombres' => 'Luis Fernando', 'apellido_paterno' => 'Quispe']);
        $ajeno = $this->crearEstudiante($prof['profesor'], $otro, ['nombres' => 'Rosa', 'apellido_paterno' => 'Condori']);
        DB::table('gamificacion_perfiles')->insert([
            ['id_estudiante' => $companero['estudiante'], 'xp_total' => 300],
            ['id_estudiante' => $ajeno['estudiante'], 'xp_total' => 900],
            ['id_estudiante' => $yo['estudiante'], 'xp_total' => 100],
        ]);

        $html = $this->sesionDe($yo['usuario'])->get('/dashboard-estudiante')->assertOk()->getContent();

        $this->assertStringContainsString('"nombre":"Luis"', $html);
        $this->assertStringNotContainsString('Quispe', $html);
        $this->assertStringNotContainsString('Rosa', $html, 'No aparece nadie de otro elenco');
        $this->assertStringNotContainsString('"instrumento":', explode('MSEA_RANKING', $html)[1] ?? '');

        $resumen = app(GamificationService::class)->resumen($yo['estudiante']);
        $this->assertSame(2, $resumen['posicion'], 'Posicion dentro de su elenco, no de toda la academia');
    }

    // --- BD-01 / BD-09: borrados que perdian trabajo -----------------------

    public function test_no_se_elimina_un_elenco_con_tareas(): void
    {
        $prof = $this->crearProfesor();
        $elenco = $this->crearElenco();
        $this->crearEstudiante($prof['profesor'], $elenco);
        $this->crearTarea($prof['profesor'], null, $elenco);
        $director = $this->crearUsuario('director');

        $this->sesionDe($director)->delete("/dashboard-admin/elencos/{$elenco}")
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('elencos', ['id_elenco' => $elenco]);
    }

    public function test_un_elenco_sin_tareas_si_se_elimina(): void
    {
        $elenco = $this->crearElenco();
        $director = $this->crearUsuario('director');

        $this->sesionDe($director)->delete("/dashboard-admin/elencos/{$elenco}")->assertSessionHas('success');
        $this->assertDatabaseMissing('elencos', ['id_elenco' => $elenco]);
    }

    public function test_no_se_elimina_una_tarea_con_entregas_sin_calificar(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $idTarea = $this->crearTarea($prof['profesor'], $alumno['estudiante']);
        DB::table('entregas_tareas')->insert([
            'id_tarea' => $idTarea, 'id_estudiante' => $alumno['estudiante'], 'estado' => 'entregada',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->sesionDe($prof['usuario'])->deleteJson("/dashboard-profesor/tareas/{$idTarea}")->assertStatus(422);
        $this->assertDatabaseHas('tareas', ['id_tarea' => $idTarea]);
        $this->assertDatabaseCount('entregas_tareas', 1);
    }

    /** BD-02: en PostgreSQL las claves son RESTRICT; un borrado fisico no arrastra el historial. */
    public function test_postgres_impide_borrar_profesor_con_tareas(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('La restriccion RESTRICT solo se aplica en PostgreSQL (phpunit.pgsql.xml).');
        }

        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $this->crearTarea($prof['profesor'], $alumno['estudiante']);

        $this->expectException(QueryException::class);
        DB::table('usuarios')->where('id_usuario', $prof['usuario'])->delete();
    }

    // --- SEC-02: cuenta del registro publico reclamada con Google ----------

    public function test_google_anula_la_contrasena_de_una_cuenta_del_registro_publico(): void
    {
        config(['services.google.client_id' => 'cliente.apps.googleusercontent.com', 'services.google.client_ids' => ['cliente.apps.googleusercontent.com']]);

        $this->post('/registro', [
            'rol' => 'estudiante', 'nombres' => 'Ana', 'apellido_paterno' => 'Rojas', 'correo' => 'ana@gmail.com',
            'contrasena' => 'secret123', 'contrasena_confirmation' => 'secret123', 'acepta_privacidad' => '1',
        ])->assertRedirect('/dashboard-estudiante');
        $usuario = DB::table('usuarios')->where('correo', 'ana@gmail.com')->first();
        $this->assertSame('registro', $usuario->origen);
        $this->flushSession();

        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'iss' => 'https://accounts.google.com', 'aud' => 'cliente.apps.googleusercontent.com', 'sub' => '998877',
            'email' => 'ana@gmail.com', 'email_verified' => 'true', 'exp' => (string) (time() + 3600),
        ])]);
        $this->postJson('/auth/google', ['credential' => 'token'])->assertOk();

        $despues = DB::table('usuarios')->where('id_usuario', $usuario->id_usuario)->first();
        $this->assertFalse(Hash::check('secret123', $despues->contrasena), 'La contrasena del registro ya no sirve');
        $this->assertNotNull($despues->correo_verificado_at);
        $this->assertDatabaseHas('auditoria', ['accion' => 'google_reclama_cuenta']);
    }

    public function test_cuenta_creada_por_la_direccion_conserva_su_contrasena_al_vincular_google(): void
    {
        config(['services.google.client_id' => 'c.apps.googleusercontent.com', 'services.google.client_ids' => ['c.apps.googleusercontent.com']]);
        $alumno = $this->crearEstudiante(null, null, ['correo' => 'luis@gmail.com']);
        Http::fake(['oauth2.googleapis.com/*' => Http::response([
            'iss' => 'https://accounts.google.com', 'aud' => 'c.apps.googleusercontent.com', 'sub' => '5544',
            'email' => 'luis@gmail.com', 'email_verified' => 'true', 'exp' => (string) (time() + 3600),
        ])]);

        $this->postJson('/auth/google', ['credential' => 'token'])->assertOk();

        $this->assertTrue(Hash::check('secret123', DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('contrasena')));
    }

    // --- SEC-03: tope diario de intentos de recuperacion -------------------

    public function test_recuperacion_se_bloquea_tras_20_fallos_en_24_horas(): void
    {
        Mail::fake();
        $alumno = $this->crearEstudiante();
        $correo = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('correo');
        $servicio = app(PasswordResetService::class);

        for ($ciclo = 0; $ciclo < 4; $ciclo++) {
            $servicio->enviarCodigoSiUsuarioExiste($correo);
            for ($i = 0; $i < 5; $i++) {
                $this->assertNull($servicio->verificarCodigo($correo, '000000'));
            }
        }

        $this->assertTrue($servicio->bloqueadaPorIntentos($correo));
        $codigos = DB::table('password_reset_codes')->where('correo', $correo)->count();
        $servicio->enviarCodigoSiUsuarioExiste($correo);
        $this->assertSame($codigos, DB::table('password_reset_codes')->where('correo', $correo)->count(), 'No se emite un codigo nuevo');
        $this->assertDatabaseHas('auditoria', ['accion' => 'recuperacion_bloqueada']);
    }

    // --- ARQ-03 / SEC-10: fotos de perfil privadas -------------------------

    public function test_las_fotos_solo_las_ven_personas_con_relacion(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $extrano = $this->crearEstudiante();
        $padre = $this->crearPadre([$alumno['estudiante']]);
        $otroPadre = $this->crearPadre();

        $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/perfil', [
            'nombres' => 'Ana', 'apellido_paterno' => 'Prueba',
            'correo' => DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('correo'),
            'foto' => self::PNG,
        ])->assertOk();
        $path = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('foto');
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);

        $url = "/archivos/fotos/{$alumno['usuario']}";
        $this->sesionDe($alumno['usuario'])->get($url)->assertOk();
        $this->sesionDe($prof['usuario'])->get($url)->assertOk();
        $this->sesionDe($padre['usuario'])->get($url)->assertOk();
        $this->sesionDe($extrano['usuario'])->get($url)->assertNotFound();
        $this->sesionDe($otroPadre['usuario'])->get($url)->assertNotFound();
        $this->flushSession();
        $this->get($url)->assertRedirect();
    }

    public function test_el_disco_privado_no_publica_una_ruta_storage(): void
    {
        $this->assertFalse((bool) config('filesystems.disks.local.serve'));
    }

    // --- BD-03: reversiones destructivas -----------------------------------

    public function test_la_reversion_destructiva_se_bloquea_en_produccion(): void
    {
        ReversionSegura::verificar('prueba'); // en testing no bloquea

        $this->app['env'] = 'production';
        try {
            $this->expectException(RuntimeException::class);
            ReversionSegura::verificar('2026_10_10_000001_msea_seguridad_y_gamificacion');
        } finally {
            $this->app['env'] = 'testing';
        }
    }
}
