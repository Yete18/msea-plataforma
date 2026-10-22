<?php

namespace Tests\Feature\Seguridad;

use App\Services\CuentaSeguridadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/** S4: limite de intentos, expiracion, revocacion y permisos de la API movil. */
class ApiMovilTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
    }

    private function login(int $idUsuario, string $rol, string $contrasena = 'secret123')
    {
        return $this->postJson('/api/mobile/login', [
            'correo' => DB::table('usuarios')->where('id_usuario', $idUsuario)->value('correo'),
            'contrasena' => $contrasena,
            'rol' => $rol,
        ]);
    }

    public function test_login_movil_limita_intentos_y_responde_generico(): void
    {
        $alumno = $this->crearEstudiante();

        for ($i = 0; $i < 5; $i++) {
            $this->login($alumno['usuario'], 'estudiante', 'mala'.$i)
                ->assertStatus(422)
                ->assertJsonPath('message', 'Correo electrónico o contraseña incorrectos.');
        }

        $this->login($alumno['usuario'], 'estudiante')->assertStatus(429);
    }

    public function test_la_app_entra_sin_rol_y_un_rol_viejo_se_ignora(): void
    {
        $alumno = $this->crearEstudiante();
        $correo = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('correo');

        // UX-07: sin rol, el servidor usa el de la cuenta.
        $this->postJson('/api/mobile/login', ['correo' => $correo, 'contrasena' => 'secret123'])
            ->assertOk()->assertJsonPath('usuario.rol', 'estudiante');

        // Una version anterior de la app que aun envia otro rol sigue entrando con el suyo.
        $this->login($alumno['usuario'], 'profesor')->assertOk()->assertJsonPath('usuario.rol', 'estudiante');

        // Un rol desconocido sigue siendo un dato invalido.
        $this->login($alumno['usuario'], 'administrador')->assertStatus(422)->assertJsonValidationErrors('rol');
    }

    public function test_token_se_guarda_hasheado_y_expira(): void
    {
        $alumno = $this->crearEstudiante();
        $token = $this->login($alumno['usuario'], 'estudiante')->assertOk()->json('token');

        $this->assertDatabaseMissing('mobile_api_tokens', ['token_hash' => $token]);
        $this->assertDatabaseHas('mobile_api_tokens', ['token_hash' => hash('sha256', $token)]);

        $this->withToken($token)->getJson('/api/mobile/me')->assertOk()->assertJsonPath('usuario.rol', 'estudiante');

        DB::table('mobile_api_tokens')->update(['expires_at' => now()->subMinute()]);
        $this->withToken($token)->getJson('/api/mobile/me')->assertUnauthorized();
    }

    public function test_logout_y_cambio_de_contrasena_revocan_tokens(): void
    {
        $alumno = $this->crearEstudiante();
        $t1 = $this->login($alumno['usuario'], 'estudiante')->json('token');
        $t2 = $this->login($alumno['usuario'], 'estudiante')->json('token');

        $this->withToken($t1)->postJson('/api/mobile/logout')->assertOk();
        $this->withToken($t1)->getJson('/api/mobile/me')->assertUnauthorized();
        $this->withToken($t2)->getJson('/api/mobile/me')->assertOk();

        app(CuentaSeguridadService::class)->establecerContrasena($alumno['usuario'], 'otra12345', 'cambio_contrasena');

        $this->withToken($t2)->getJson('/api/mobile/me')->assertUnauthorized();
    }

    public function test_usuario_desactivado_pierde_acceso_api(): void
    {
        $alumno = $this->crearEstudiante();
        $token = $this->login($alumno['usuario'], 'estudiante')->json('token');

        DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->update(['activo' => false]);

        $this->withToken($token)->getJson('/api/mobile/me')->assertUnauthorized();
    }

    public function test_sin_token_o_token_falso_responde_401(): void
    {
        $this->getJson('/api/mobile/me')->assertUnauthorized();
        $this->withToken('falso')->getJson('/api/mobile/me')->assertUnauthorized();
        $this->withToken('falso')->postJson('/api/mobile/practicas', ['tipo' => 'ritmo'])->assertUnauthorized();
    }

    public function test_padre_no_puede_usar_endpoints_de_estudiante(): void
    {
        $padre = $this->crearPadre();
        $token = $this->login($padre['usuario'], 'padre')->json('token');

        $this->withToken($token)->postJson('/api/mobile/practicas', ['tipo' => 'ritmo'])->assertForbidden();
        $this->withToken($token)->getJson('/api/mobile/tareas')->assertForbidden();
    }

    public function test_limite_de_sesiones_moviles_y_revocacion_por_id(): void
    {
        config(['msea.api.max_sesiones_por_usuario' => 2]);
        $alumno = $this->crearEstudiante();

        $primero = $this->login($alumno['usuario'], 'estudiante')->json('token');
        $this->travel(1)->seconds();
        $this->login($alumno['usuario'], 'estudiante');
        $this->travel(1)->seconds();
        $ultimo = $this->login($alumno['usuario'], 'estudiante')->json('token');

        $this->assertSame(2, DB::table('mobile_api_tokens')->where('id_usuario', $alumno['usuario'])->count());
        $this->withToken($primero)->getJson('/api/mobile/me')->assertUnauthorized();

        $sesiones = $this->withToken($ultimo)->getJson('/api/mobile/sesiones')->assertOk()->json('sesiones');
        $otra = collect($sesiones)->firstWhere('actual', false);
        $this->withToken($ultimo)->deleteJson('/api/mobile/sesiones/'.$otra['id'])->assertOk();
        $this->assertSame(1, DB::table('mobile_api_tokens')->where('id_usuario', $alumno['usuario'])->count());
    }

    public function test_api_no_permite_entregar_tarea_ajena(): void
    {
        $prof = $this->crearProfesor();
        $victima = $this->crearEstudiante($prof['profesor']);
        $atacante = $this->crearEstudiante();
        $idTarea = $this->crearTarea($prof['profesor'], $victima['estudiante']);
        $token = $this->login($atacante['usuario'], 'estudiante')->json('token');

        $this->withToken($token)->postJson("/api/mobile/tareas/{$idTarea}/entregar", ['comentario_estudiante' => 'x'])->assertNotFound();
        $this->assertCount(0, $this->withToken($token)->getJson('/api/mobile/tareas')->json('tareas'));
    }
}
