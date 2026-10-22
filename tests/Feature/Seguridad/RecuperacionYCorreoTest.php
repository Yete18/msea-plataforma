<?php

namespace Tests\Feature\Seguridad;

use App\Mail\CodigoRecuperacionMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/** S6, S7, S10 y L7. */
class RecuperacionYCorreoTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
    }

    public function test_codigo_se_invalida_tras_cinco_fallos_aunque_cambie_la_ip(): void
    {
        $correo = 'fuerza@msea.test';
        $this->crearUsuario('estudiante', 'secret123', ['correo' => $correo]);
        DB::table('password_reset_codes')->insert([
            'correo' => $correo, 'codigo' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10), 'created_at' => now(),
        ]);

        foreach (range(1, 5) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->withSession(['password_reset_correo' => $correo])
                ->post('/verify-code', ['codigo' => '00000'.$i]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->withSession(['password_reset_correo' => $correo])
            ->post('/verify-code', ['codigo' => '123456'])
            ->assertSessionHas('error');

        $this->assertNotNull(DB::table('password_reset_codes')->value('used_at'));
        $this->assertSame(5, (int) DB::table('password_reset_codes')->value('intentos'));
    }

    public function test_correo_con_mayusculas_recibe_codigo(): void
    {
        Mail::fake();
        $this->post('/registro', [
            'correo' => 'Juan.Perez@Msea.Test', 'contrasena' => 'secret123', 'contrasena_confirmation' => 'secret123',
            'nombres' => 'Juan', 'apellido_paterno' => 'Perez', 'rol' => 'estudiante', 'acepta_privacidad' => '1',
        ])->assertRedirect('/dashboard-estudiante');

        $this->assertDatabaseHas('usuarios', ['correo' => 'juan.perez@msea.test']);

        $this->flushSession();
        $this->post('/forgot-password', ['correo' => 'JUAN.PEREZ@msea.test'])->assertRedirect('/verify-code');
        Mail::assertSent(CodigoRecuperacionMail::class, fn ($m) => $m->hasTo('juan.perez@msea.test'));
    }

    public function test_no_se_puede_registrar_duplicado_por_mayusculas(): void
    {
        $this->crearUsuario('estudiante', 'secret123', ['correo' => 'ana@msea.test']);

        $this->post('/registro', [
            'correo' => 'ANA@msea.test', 'contrasena' => 'secret123', 'contrasena_confirmation' => 'secret123',
            'nombres' => 'Ana', 'apellido_paterno' => 'Dos', 'rol' => 'estudiante', 'acepta_privacidad' => '1',
        ])->assertSessionHasErrors('correo');

        $this->assertSame(1, DB::table('usuarios')->whereRaw('lower(correo) = ?', ['ana@msea.test'])->count());
    }

    public function test_falla_del_proveedor_de_correo_no_da_500_ni_revela_la_cuenta(): void
    {
        $this->crearUsuario('estudiante', 'secret123', ['correo' => 'existe@msea.test']);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caido'));

        $existe = $this->post('/forgot-password', ['correo' => 'existe@msea.test']);
        $this->flushSession();
        $noExiste = $this->post('/forgot-password', ['correo' => 'no-existe@msea.test']);

        $existe->assertRedirect('/verify-code');
        $noExiste->assertRedirect('/verify-code');
        $this->assertSame($existe->getSession()->get('success'), $noExiste->getSession()->get('success'));
    }

    public function test_reset_completo_revoca_tokens_y_cierra_sesiones(): void
    {
        $id = $this->crearUsuario('estudiante', 'secret123', ['correo' => 'reset@msea.test']);
        DB::table('mobile_api_tokens')->insert(['id_usuario' => $id, 'token_hash' => hash('sha256', 'abc'), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        $idCodigo = DB::table('password_reset_codes')->insertGetId([
            'correo' => 'reset@msea.test', 'codigo' => Hash::make('654321'), 'expires_at' => now()->addMinutes(10), 'created_at' => now(),
        ], 'id');

        $this->withSession(['password_reset_correo' => 'reset@msea.test', 'password_reset_verified' => true, 'password_reset_code_id' => $idCodigo])
            ->post('/reset-password', ['contrasena' => 'nueva12345', 'contrasena_confirmation' => 'nueva12345'])
            ->assertRedirect('/login');

        $this->assertDatabaseCount('mobile_api_tokens', 0);
        $this->assertDatabaseHas('auditoria', ['accion' => 'recuperacion_completada', 'id_usuario' => $id]);
    }

    public function test_cambiar_correo_exige_contrasena_actual_y_revoca_tokens(): void
    {
        $alumno = $this->crearEstudiante();
        DB::table('mobile_api_tokens')->insert(['id_usuario' => $alumno['usuario'], 'token_hash' => hash('sha256', 'xyz'), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        $datos = ['nombres' => 'Nuevo', 'correo' => 'nuevo.correo@msea.test'];

        $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/perfil', $datos)
            ->assertUnprocessable()->assertJsonValidationErrors('contrasena_actual');
        $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/perfil', $datos + ['contrasena_actual' => 'mala'])
            ->assertUnprocessable();
        $this->assertDatabaseMissing('usuarios', ['correo' => 'nuevo.correo@msea.test']);

        $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/perfil', $datos + ['contrasena_actual' => 'secret123'])->assertOk();
        $this->assertDatabaseHas('usuarios', ['correo' => 'nuevo.correo@msea.test']);
        $this->assertDatabaseCount('mobile_api_tokens', 0);
    }

    public function test_editar_perfil_sin_cambiar_correo_no_pide_contrasena(): void
    {
        $alumno = $this->crearEstudiante();
        $correo = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('correo');

        $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/perfil', ['nombres' => 'Otro', 'correo' => strtoupper($correo)])->assertOk();
    }
}
