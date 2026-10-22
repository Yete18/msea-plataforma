<?php

namespace Tests\Unit;

use App\Models\Usuario;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsMseaCatalogs;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsMseaCatalogs;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMseaCatalogs();
        $this->service = app(AuthService::class);
    }

    public function test_registra_estudiante_con_contrasena_cifrada_seccion_e_instrumento(): void
    {
        $idUsuario = $this->service->registrarEstudiante([
            'rol' => 'estudiante',
            'correo' => 'ana@msea.test',
            'contrasena' => 'secret123',
            'nombres' => 'Ana',
            'apellido_paterno' => 'Lopez',
            'apellido_materno' => 'Rojas',
            'instrumento' => 'violin',
        ]);

        $this->assertIsInt($idUsuario);

        $usuario = Usuario::query()->findOrFail($idUsuario);

        $this->assertSame('ana@msea.test', $usuario->correo);
        $this->assertTrue(Hash::check('secret123', $usuario->contrasena));
        $this->assertDatabaseHas('estudiantes', [
            'id_usuario' => $idUsuario,
        ]);
        $this->assertDatabaseHas('secciones', [
            'nombre' => 'General',
        ]);
        $this->assertDatabaseHas('instrumentos', [
            'nombre' => 'Violin',
        ]);
        $this->assertDatabaseHas('usuario_instrumento', [
            'id_usuario' => $idUsuario,
        ]);
    }

    public function test_registro_retorna_false_si_el_rol_no_existe(): void
    {
        $resultado = $this->service->registrarEstudiante([
            'rol' => 'inexistente',
            'correo' => 'sin-rol@msea.test',
            'contrasena' => 'secret123',
            'nombres' => 'Sin',
            'apellido_paterno' => 'Rol',
        ]);

        $this->assertFalse($resultado);
        $this->assertDatabaseMissing('usuarios', [
            'correo' => 'sin-rol@msea.test',
        ]);
    }

    public function test_contrasena_en_texto_plano_ya_no_se_acepta_y_la_migracion_la_hashea(): void
    {
        $idUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'legacy@msea.test',
            'contrasena' => '1234',
            'nombres' => 'Legacy',
            'apellido_paterno' => 'User',
            'id_rol' => DB::table('roles')->where('nombre', 'estudiante')->value('id_rol'),
        ], 'id_usuario');

        $usuario = Usuario::query()->findOrFail($idUsuario);

        $this->assertFalse($this->service->contrasenaValida('1234', $usuario));

        (require database_path('migrations/2026_10_17_000003_msea_hashear_contrasenas_antiguas.php'))->up();
        $contrasenaActualizada = Usuario::query()->findOrFail($idUsuario)->contrasena;
        $this->assertTrue($this->service->contrasenaValida('1234', Usuario::query()->findOrFail($idUsuario)));

        $this->assertNotSame('1234', $contrasenaActualizada);
        $this->assertTrue(Hash::check('1234', $contrasenaActualizada));
    }

    public function test_busca_usuario_con_nombre_de_rol(): void
    {
        $idUsuario = $this->service->registrarEstudiante([
            'rol' => 'estudiante',
            'correo' => 'rol@msea.test',
            'contrasena' => 'secret123',
            'nombres' => 'Rol',
            'apellido_paterno' => 'Visible',
        ]);

        $usuario = $this->service->findUsuarioConRol('rol@msea.test');

        $this->assertSame($idUsuario, $usuario->id_usuario);
        $this->assertSame('estudiante', $usuario->rol_nombre);
    }
}
