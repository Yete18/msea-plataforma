<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsMseaCatalogs;
use Tests\TestCase;

class DashboardAdminTest extends TestCase
{
    use RefreshDatabase;
    use SeedsMseaCatalogs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMseaCatalogs();
    }

    public function test_director_puede_ver_panel_admin(): void
    {
        $idDirector = DB::table('usuarios')->insertGetId([
            'correo' => 'director.panel.'.time().'@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Director',
            'apellido_paterno' => 'Panel',
            'id_rol' => DB::table('roles')->where('nombre', 'director')->value('id_rol'),
        ], 'id_usuario');

        $this->withSession([
            'usuario_id' => $idDirector,
            'rol' => 'director',
        ])->get('/dashboard-admin')
            ->assertOk()
            ->assertSee('Centralizador general')
            ->assertSee('Elencos y orquestas')
            ->assertSee('Reportes')
            ->assertSee('Bitácora de seguridad')
            ->assertSee('Pendientes para ti')
            ->assertSee('Actividad reciente')
            ->assertSee('Alarmas')
            ->assertSee('Registrar profesor');

        // Profesores y perfil tienen su propia pagina (UX-08).
        $this->withSession(['usuario_id' => $idDirector, 'rol' => 'director'])->get('/dashboard-admin/profesores')
            ->assertOk()->assertSee('Equipo docente')->assertSee('Registrar profesor');
        $this->withSession(['usuario_id' => $idDirector, 'rol' => 'director'])->get('/dashboard-admin/perfil')
            ->assertOk()->assertSee('Datos del director')->assertSee('Guía rápida');
    }

    public function test_director_puede_descargar_reporte_de_estudiantes(): void
    {
        $idDirector = DB::table('usuarios')->insertGetId([
            'correo' => 'director.reportes.'.time().'@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Director',
            'apellido_paterno' => 'Reportes',
            'id_rol' => DB::table('roles')->where('nombre', 'director')->value('id_rol'),
        ], 'id_usuario');

        $idEstudianteUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'estudiante.reporte.'.time().'@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Luis',
            'apellido_paterno' => 'Mamani',
            'id_rol' => DB::table('roles')->where('nombre', 'estudiante')->value('id_rol'),
        ], 'id_usuario');

        DB::table('estudiantes')->insert([
            'id_usuario' => $idEstudianteUsuario,
            'id_seccion' => DB::table('secciones')->where('nombre', 'General')->value('id_seccion'),
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $response = $this->withSession([
            'usuario_id' => $idDirector,
            'rol' => 'director',
        ])->get('/dashboard-admin/reportes/estudiantes');

        $response->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Luis Mamani', $csv);
        $this->assertStringContainsString('Sin elenco', $csv);
    }

    public function test_director_puede_registrar_profesor(): void
    {
        $idDirector = DB::table('usuarios')->insertGetId([
            'correo' => 'director.crea.'.time().'@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Director',
            'apellido_paterno' => 'Creador',
            'id_rol' => DB::table('roles')->where('nombre', 'director')->value('id_rol'),
        ], 'id_usuario');

        $correoProfesor = 'prof.nuevo.'.time().'@msea.test';

        $this->withSession([
            'usuario_id' => $idDirector,
            'rol' => 'director',
        ])->post('/dashboard-admin/profesores', [
            'nombres' => 'Ana',
            'apellido_paterno' => 'Lopez',
            'apellido_materno' => 'Rojas',
            'correo' => $correoProfesor,
            'contrasena' => 'secret123',
            'contrasena_confirmation' => 'secret123',
            'ci' => '998877',
            'celular' => '70000000',
            'especialidad' => 'violin',
        ])->assertRedirect('/dashboard-admin/profesores');

        $usuario = DB::table('usuarios')->where('correo', $correoProfesor)->first();

        $this->assertNotNull($usuario);
        $this->assertTrue(Hash::check('secret123', $usuario->contrasena));
        $this->assertDatabaseHas('profesores', [
            'id_usuario' => $usuario->id_usuario,
        ]);
        $this->assertDatabaseHas('usuario_instrumento', [
            'id_usuario' => $usuario->id_usuario,
        ]);
    }

    public function test_estudiante_no_puede_ver_panel_admin(): void
    {
        $this->withSession([
            'usuario_id' => 1,
            'rol' => 'estudiante',
        ])->get('/dashboard-admin')
            ->assertRedirect('/login');
    }
}
