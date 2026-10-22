<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsMseaCatalogs;
use Tests\TestCase;

class DashboardProfesorTest extends TestCase
{
    use RefreshDatabase;
    use SeedsMseaCatalogs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMseaCatalogs();
    }

    public function test_dashboard_profesor_renderiza_datos_de_base_de_datos(): void
    {
        $idProfesorUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'profesor.dashboard.'.time().'@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Profesor',
            'apellido_paterno' => 'Dashboard',
            'id_rol' => DB::table('roles')->where('nombre', 'profesor')->value('id_rol'),
        ], 'id_usuario');

        $idProfesor = DB::table('profesores')->insertGetId([
            'id_usuario' => $idProfesorUsuario,
        ], 'id_profesor');

        $idSeccion = DB::table('secciones')->insertGetId([
            'nombre' => 'Profesor Test '.time(),
        ], 'id_seccion');

        $idAlumnoUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'alumno.profesor.'.time().'@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Alumno',
            'apellido_paterno' => 'Asignado',
            'id_rol' => DB::table('roles')->where('nombre', 'estudiante')->value('id_rol'),
        ], 'id_usuario');

        DB::table('estudiantes')->insert([
            'id_usuario' => $idAlumnoUsuario,
            'id_profesor' => $idProfesor,
            'id_seccion' => $idSeccion,
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $this->withSession([
            'usuario_id' => $idProfesorUsuario,
            'rol' => 'profesor',
        ])->get('/dashboard-profesor')
            ->assertOk()
            ->assertSee('MSEA_PROFESOR')
            ->assertSee('Profesor Dashboard')
            ->assertSee('Alumno Asignado');
    }
}
