<?php

namespace Tests\Unit;

use App\Services\GamificationService;
use App\Services\StudentDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsMseaCatalogs;
use Tests\TestCase;

class StudentDashboardServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsMseaCatalogs;

    private StudentDashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMseaCatalogs();
        $this->service = app(StudentDashboardService::class);
    }

    public function test_dashboard_payload_calcula_datos_principales_del_estudiante(): void
    {
        $ids = $this->crearEstudianteConProgreso();

        $payload = $this->service->dashboardPayload($ids['usuario']);

        $this->assertNotNull($payload);
        $this->assertSame($ids['estudiante'], $payload['dashboardData']['idEstudiante']);
        $this->assertSame('Ana', $payload['dashboardData']['nombre']);
        $this->assertSame('Ana Lopez Rojas', $payload['dashboardData']['nombreCompleto']);
        $this->assertSame('Violin', $payload['dashboardData']['instrumento']);
        $this->assertSame(2, $payload['dashboardData']['nivel']);
        // 750 XP + logros «Primer paso» (10) y «500 XP» (25), que ahora existen por CatalogosSeeder.
        $this->assertSame(285, $payload['dashboardData']['xp']);
        $this->assertSame(785, $payload['dashboardData']['puntos']);
        $this->assertSame(1, $payload['dashboardData']['ejerciciosHechos']);
        $this->assertSame(1, $payload['dashboardData']['rankingPos']);
        $this->assertSame('General', $payload['dashboardData']['seccion']);
        $this->assertCount(1, $payload['rankingData']);
        $this->assertTrue($payload['rankingData'][0]['esYo']);
    }

    public function test_dashboard_payload_retorna_null_si_no_existe_estudiante(): void
    {
        $this->assertNull($this->service->dashboardPayload(999));
        $this->assertFalse($this->service->estudianteExiste(999));
    }

    public function test_update_profile_actualiza_usuario_y_retorna_payload_publico(): void
    {
        $ids = $this->crearEstudianteConProgreso();

        $payload = $this->service->updateProfile($ids['usuario'], [
            'nombres' => 'Ana Maria',
            'apellido_paterno' => 'Lopez',
            'apellido_materno' => 'Rojas',
            'correo' => 'ana.maria@msea.test',
            'ci' => '123456',
            'celular' => '76543210',
            'direccion' => 'Calle 1',
            'fecha_nacimiento' => '2005-04-01',
            'foto' => '/storage/avatars/ana.png',
        ]);

        $this->assertSame('Ana Maria', $payload['nombres']);
        // Las fotos se sirven por la ruta autorizada, nunca por /storage (fotos de menores).
        $this->assertStringContainsString('/archivos/fotos/'.$ids['usuario'], $payload['foto']);
        $this->assertDatabaseHas('usuarios', [
            'id_usuario' => $ids['usuario'],
            'correo' => 'ana.maria@msea.test',
            'celular' => '76543210',
        ]);
    }

    private function crearEstudianteConProgreso(): array
    {
        $idUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'ana-dashboard@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Ana',
            'apellido_paterno' => 'Lopez',
            'apellido_materno' => 'Rojas',
            'id_rol' => DB::table('roles')->where('nombre', 'estudiante')->value('id_rol'),
        ], 'id_usuario');

        $idEstudiante = DB::table('estudiantes')->insertGetId([
            'id_usuario' => $idUsuario,
            'fecha_ingreso' => '2026-05-01',
            'id_seccion' => DB::table('secciones')->where('nombre', 'General')->value('id_seccion'),
        ], 'id_estudiante');

        $idInstrumento = DB::table('instrumentos')->where('nombre', 'Violin')->value('id_instrumento');

        DB::table('usuario_instrumento')->insert([
            'id_usuario' => $idUsuario,
            'id_instrumento' => $idInstrumento,
        ]);

        $idProfesorUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'profesor-dashboard@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Mario',
            'apellido_paterno' => 'Paz',
            'id_rol' => DB::table('roles')->where('nombre', 'profesor')->value('id_rol'),
        ], 'id_usuario');

        $idProfesor = DB::table('profesores')->insertGetId([
            'id_usuario' => $idProfesorUsuario,
        ], 'id_profesor');

        DB::table('estudiantes')->where('id_estudiante', $idEstudiante)->update([
            'id_profesor' => $idProfesor,
        ]);

        $idTipoEjercicio = DB::table('tipos_ejercicio')->where('nombre', 'Ritmo')->value('id_tipo');

        $idEjercicio = DB::table('ejercicios')->insertGetId([
            'id_tipo' => $idTipoEjercicio,
            'descripcion' => 'Ejercicio de ritmo',
            'creado_por' => $idProfesor,
        ], 'id_ejercicio');

        // Fuente unica de XP: el libro xp_movimientos (no progreso.puntaje).
        app(GamificationService::class)->otorgarXp($idEstudiante, 750, 'practica', 'test:750', 'XP de prueba');

        DB::table('practicas_autonomas')->insert([
            'id_estudiante' => $idEstudiante,
            'id_ejercicio' => $idEjercicio,
            'tipo' => 'ritmo',
            'estado' => 'finalizada',
            'duracion_segundos' => 300,
            'xp_ganado' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'usuario' => $idUsuario,
            'estudiante' => $idEstudiante,
        ];
    }
}
