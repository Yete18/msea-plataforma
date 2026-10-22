<?php

namespace Tests\Unit;

use App\Services\AdminDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsMseaCatalogs;
use Tests\TestCase;

class AdminDashboardServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsMseaCatalogs;

    private AdminDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMseaCatalogs();
        $this->service = app(AdminDashboardService::class);
    }

    public function test_crea_profesor_con_usuario_hash_y_especialidad(): void
    {
        $resultado = $this->service->storeProfesor([
            'correo' => 'profesor@msea.test',
            'contrasena' => 'secret123',
            'nombres' => 'Mario',
            'apellido_paterno' => 'Paz',
            'apellido_materno' => 'Luna',
            'especialidad' => 'chelo',
        ]);

        $this->assertTrue($resultado);

        $usuario = DB::table('usuarios')->where('correo', 'profesor@msea.test')->first();

        $this->assertNotNull($usuario);
        $this->assertTrue(Hash::check('secret123', $usuario->contrasena));
        $this->assertDatabaseHas('profesores', [
            'id_usuario' => $usuario->id_usuario,
        ]);
        $this->assertDatabaseHas('instrumentos', [
            'nombre' => 'Violonchelo', // 'chelo' se normaliza al nombre oficial
        ]);
        $this->assertDatabaseHas('usuario_instrumento', [
            'id_usuario' => $usuario->id_usuario,
        ]);
    }

    public function test_crear_profesor_retorna_false_si_no_existe_rol_profesor(): void
    {
        DB::table('roles')->where('nombre', 'profesor')->delete();

        $resultado = $this->service->storeProfesor([
            'correo' => 'sin-rol-profesor@msea.test',
            'contrasena' => 'secret123',
            'nombres' => 'Sin',
            'apellido_paterno' => 'Rol',
        ]);

        $this->assertFalse($resultado);
        $this->assertDatabaseMissing('usuarios', [
            'correo' => 'sin-rol-profesor@msea.test',
        ]);
    }

    public function test_crea_actualiza_y_elimina_elenco(): void
    {
        $this->assertTrue($this->service->storeElenco([
            'nombre' => 'Coro Juvenil',
            'tipo' => 'Coro',
        ]));

        $elencoId = DB::table('elencos')->where('nombre', 'Coro Juvenil')->value('id_elenco');

        $this->assertNotNull($elencoId);
        $this->assertDatabaseHas('tipos_elencos', [
            'nombre' => 'Coro',
        ]);

        $this->service->updateElenco($elencoId, [
            'nombre' => 'Orquesta Juvenil',
            'tipo' => 'Orquesta',
        ]);

        $this->assertDatabaseHas('elencos', [
            'id_elenco' => $elencoId,
            'nombre' => 'Orquesta Juvenil',
        ]);
        $this->assertDatabaseHas('tipos_elencos', [
            'nombre' => 'Orquesta',
        ]);

        $this->service->destroyElenco($elencoId);

        $this->assertDatabaseMissing('elencos', [
            'id_elenco' => $elencoId,
        ]);
    }

    public function test_asigna_estudiante_a_profesor_y_elenco(): void
    {
        $profesorId = $this->crearProfesor();
        $estudianteId = $this->crearEstudiante();

        $this->service->storeElenco([
            'nombre' => 'Ensamble A',
            'tipo' => 'Ensamble',
        ]);
        $elencoId = DB::table('elencos')->where('nombre', 'Ensamble A')->value('id_elenco');

        $this->assertTrue($this->service->assignStudentToProfesor($estudianteId, $profesorId));
        $this->assertTrue($this->service->assignStudentToElenco($estudianteId, $elencoId));
        $this->assertDatabaseHas('estudiantes', [
            'id_estudiante' => $estudianteId,
            'id_profesor' => $profesorId,
            'id_elenco' => $elencoId,
        ]);
    }

    private function crearProfesor(): int
    {
        $idUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'profesor-asignado@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Profesor',
            'apellido_paterno' => 'Asignado',
            'id_rol' => DB::table('roles')->where('nombre', 'profesor')->value('id_rol'),
        ], 'id_usuario');

        return DB::table('profesores')->insertGetId([
            'id_usuario' => $idUsuario,
        ], 'id_profesor');
    }

    private function crearEstudiante(): int
    {
        $idUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'estudiante-asignado@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Estudiante',
            'apellido_paterno' => 'Asignado',
            'id_rol' => DB::table('roles')->where('nombre', 'estudiante')->value('id_rol'),
        ], 'id_usuario');

        return DB::table('estudiantes')->insertGetId([
            'id_usuario' => $idUsuario,
            'fecha_ingreso' => now()->toDateString(),
            'id_seccion' => DB::table('secciones')->where('nombre', 'General')->value('id_seccion'),
        ], 'id_estudiante');
    }
}
