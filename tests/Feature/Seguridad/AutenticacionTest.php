<?php

namespace Tests\Feature\Seguridad;

use App\Http\Controllers\AuthController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/** S2, S5, S9: hash como contrasena, enumeracion, sesiones y desactivacion. */
class AutenticacionTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
    }

    private function correoDe(int $idUsuario): string
    {
        return (string) DB::table('usuarios')->where('id_usuario', $idUsuario)->value('correo');
    }

    public function test_el_hash_guardado_no_sirve_como_contrasena(): void
    {
        $alumno = $this->crearEstudiante();
        $hash = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('contrasena');

        $this->post('/login', ['correo' => $this->correoDe($alumno['usuario']), 'contrasena' => $hash, 'rol' => 'estudiante'])
            ->assertSessionHas('error', AuthController::MENSAJE_CREDENCIALES);

        $this->assertSame($hash, DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('contrasena'), 'El hash no debe reescribirse');
    }

    public function test_contrasena_correcta_e_incorrecta(): void
    {
        $alumno = $this->crearEstudiante();
        $correo = $this->correoDe($alumno['usuario']);

        $this->post('/login', ['correo' => $correo, 'contrasena' => 'mala123', 'rol' => 'estudiante'])
            ->assertSessionHas('error', AuthController::MENSAJE_CREDENCIALES);

        $this->post('/login', ['correo' => strtoupper($correo), 'contrasena' => 'secret123', 'rol' => 'estudiante'])
            ->assertRedirect('/dashboard-estudiante');

        $this->assertDatabaseHas('auditoria', ['accion' => 'login', 'id_usuario' => $alumno['usuario']]);
        $this->assertDatabaseHas('auditoria', ['accion' => 'login_fallido']);
    }

    public function test_contrasena_en_texto_plano_no_inicia_sesion_hasta_hashearla(): void
    {
        $id = $this->crearUsuario('estudiante', 'x', ['contrasena' => 'clave-antigua-1']);
        DB::table('estudiantes')->insert(['id_usuario' => $id, 'id_seccion' => DB::table('secciones')->value('id_seccion')]);

        $this->post('/login', ['correo' => $this->correoDe($id), 'contrasena' => 'clave-antigua-1', 'rol' => 'estudiante'])
            ->assertSessionHas('error', AuthController::MENSAJE_CREDENCIALES);
        $this->assertSame('clave-antigua-1', DB::table('usuarios')->where('id_usuario', $id)->value('contrasena'));

        (require database_path('migrations/2026_10_17_000003_msea_hashear_contrasenas_antiguas.php'))->up();
        $this->flushSession();
        $this->post('/login', ['correo' => $this->correoDe($id), 'contrasena' => 'clave-antigua-1', 'rol' => 'estudiante'])
            ->assertRedirect('/dashboard-estudiante');

        $guardada = DB::table('usuarios')->where('id_usuario', $id)->value('contrasena');
        $this->assertTrue(Hash::check('clave-antigua-1', $guardada));
    }

    public function test_mensaje_identico_para_correo_inexistente_y_contrasena_mala(): void
    {
        $alumno = $this->crearEstudiante();

        $a = $this->post('/login', ['correo' => 'no-existe@msea.test', 'contrasena' => 'x', 'rol' => 'estudiante']);
        $b = $this->post('/login', ['correo' => $this->correoDe($alumno['usuario']), 'contrasena' => 'x', 'rol' => 'estudiante']);

        $this->assertSame($a->getSession()->get('error'), $b->getSession()->get('error'));
        $this->assertSame(AuthController::MENSAJE_CREDENCIALES, $a->getSession()->get('error'));
    }

    public function test_usuario_desactivado_no_inicia_sesion_y_pierde_la_sesion_abierta(): void
    {
        $alumno = $this->crearEstudiante();
        $correo = $this->correoDe($alumno['usuario']);

        $this->post('/login', ['correo' => $correo, 'contrasena' => 'secret123', 'rol' => 'estudiante'])->assertRedirect('/dashboard-estudiante');
        $this->get('/dashboard-estudiante')->assertOk();

        DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->update(['activo' => false]);

        $this->get('/dashboard-estudiante')->assertRedirect('/login');
        $this->post('/login', ['correo' => $correo, 'contrasena' => 'secret123', 'rol' => 'estudiante'])
            ->assertSessionHas('error', AuthController::MENSAJE_CREDENCIALES);
    }

    public function test_cambio_de_rol_cierra_la_sesion(): void
    {
        $alumno = $this->crearEstudiante();
        $this->post('/login', ['correo' => $this->correoDe($alumno['usuario']), 'contrasena' => 'secret123', 'rol' => 'estudiante']);

        DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->update(['id_rol' => DB::table('roles')->where('nombre', 'profesor')->value('id_rol')]);

        $this->get('/dashboard-estudiante')->assertRedirect('/login');
    }

    public function test_cambio_de_contrasena_cierra_otras_sesiones(): void
    {
        $alumno = $this->crearEstudiante();
        $this->post('/login', ['correo' => $this->correoDe($alumno['usuario']), 'contrasena' => 'secret123', 'rol' => 'estudiante']);
        $this->get('/dashboard-estudiante')->assertOk();

        // Otra sesion (otro dispositivo) cambia la contrasena.
        DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->update(['contrasena' => Hash::make('nueva12345')]);

        $this->get('/dashboard-estudiante')->assertRedirect('/login');
    }

    public function test_estudiante_cambia_su_contrasena_con_la_actual(): void
    {
        $alumno = $this->crearEstudiante();
        $this->post('/login', ['correo' => $this->correoDe($alumno['usuario']), 'contrasena' => 'secret123', 'rol' => 'estudiante']);

        $this->postJson('/dashboard-estudiante/contrasena', [
            'contrasena_actual' => 'equivocada1', 'contrasena' => 'nueva12345', 'contrasena_confirmation' => 'nueva12345',
        ])->assertUnprocessable();

        $this->postJson('/dashboard-estudiante/contrasena', [
            'contrasena_actual' => 'secret123', 'contrasena' => 'nueva12345', 'contrasena_confirmation' => 'nueva12345',
        ])->assertOk();

        // La sesion actual sigue viva y la contrasena cambio.
        $this->get('/dashboard-estudiante')->assertOk();
        $this->assertTrue(Hash::check('nueva12345', DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('contrasena')));
        $this->assertDatabaseHas('auditoria', ['accion' => 'cambio_contrasena', 'id_usuario' => $alumno['usuario']]);
    }

    public function test_login_web_limita_intentos(): void
    {
        $alumno = $this->crearEstudiante();
        $correo = $this->correoDe($alumno['usuario']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['correo' => $correo, 'contrasena' => 'mal'.$i, 'rol' => 'estudiante']);
        }

        $this->post('/login', ['correo' => $correo, 'contrasena' => 'secret123', 'rol' => 'estudiante'])->assertStatus(302)
            ->assertSessionHas('error', 'Demasiados intentos. Espera un minuto e intenta de nuevo.');
    }

    public function test_bitacora_no_guarda_contrasenas(): void
    {
        $this->post('/login', ['correo' => 'nadie@msea.test', 'contrasena' => 'SuperSecreta99', 'rol' => 'estudiante']);

        $todo = json_encode(DB::table('auditoria')->get());
        $this->assertStringNotContainsString('SuperSecreta99', $todo);
        $this->assertStringNotContainsString('nadie@msea.test', $todo);
    }
}
