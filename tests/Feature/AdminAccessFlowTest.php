<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsMseaCatalogs;
use Tests\TestCase;

class AdminAccessFlowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsMseaCatalogs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMseaCatalogs();
    }

    public function test_director_puede_ingresar_desde_login_con_selector_admin(): void
    {
        DB::table('usuarios')->insert([
            'correo' => 'director.login@msea.test',
            'contrasena' => Hash::make('director123'),
            'nombres' => 'Director',
            'apellido_paterno' => 'Login',
            'id_rol' => DB::table('roles')->where('nombre', 'director')->value('id_rol'),
        ]);

        $this->post('/login', [
            'rol' => 'admin',
            'correo' => 'director.login@msea.test',
            'contrasena' => 'director123',
        ])->assertRedirect('/dashboard-admin');

        $this->assertSame('director', session('rol'));
    }

    public function test_director_autenticado_ve_panel_y_registra_profesor(): void
    {
        $idDirector = DB::table('usuarios')->insertGetId([
            'correo' => 'director.panel@msea.test',
            'contrasena' => Hash::make('director123'),
            'nombres' => 'Directora',
            'apellido_paterno' => 'Principal',
            'id_rol' => DB::table('roles')->where('nombre', 'director')->value('id_rol'),
        ], 'id_usuario');

        $this->withSession([
            'usuario_id' => $idDirector,
            'rol' => 'director',
        ])->get('/dashboard-admin')
            ->assertOk()
            ->assertSee('Centralizador general')
            ->assertSee('Elencos y orquestas')
            ->assertSee('Registrar profesor');

        $this->withSession([
            'usuario_id' => $idDirector,
            'rol' => 'director',
        ])->post('/dashboard-admin/profesores', [
            'nombres' => 'Ana',
            'apellido_paterno' => 'Lopez',
            'apellido_materno' => 'Rojas',
            'correo' => 'ana.prof@msea.test',
            'contrasena' => 'secret123',
            'contrasena_confirmation' => 'secret123',
            'ci' => '998877',
            'celular' => '70000000',
            'especialidad' => 'violin',
        ])->assertRedirect('/dashboard-admin/profesores');

        $profesor = DB::table('usuarios')->where('correo', 'ana.prof@msea.test')->first();

        $this->assertNotNull($profesor);
        $this->assertTrue(Hash::check('secret123', $profesor->contrasena));
        $this->assertDatabaseHas('profesores', ['id_usuario' => $profesor->id_usuario]);
        $this->assertDatabaseHas('usuario_instrumento', ['id_usuario' => $profesor->id_usuario]);
    }

    public function test_director_puede_registrar_elenco(): void
    {
        $idDirector = DB::table('usuarios')->insertGetId([
            'correo' => 'director.elenco@msea.test',
            'contrasena' => Hash::make('director123'),
            'nombres' => 'Director',
            'apellido_paterno' => 'Elencos',
            'id_rol' => DB::table('roles')->where('nombre', 'director')->value('id_rol'),
        ], 'id_usuario');

        $this->withSession([
            'usuario_id' => $idDirector,
            'rol' => 'director',
        ])->post('/dashboard-admin/elencos', [
            'nombre' => 'Juvenil Test',
            'tipo' => 'Orquesta',
        ])->assertRedirect('/dashboard-admin#elencos');

        $idTipo = DB::table('tipos_elencos')->where('nombre', 'Orquesta')->value('id_tipo');

        $this->assertDatabaseHas('elencos', [
            'nombre' => 'Juvenil Test',
            'id_tipo' => $idTipo,
        ]);
    }

    public function test_director_puede_editar_borrar_y_asignar_estudiante_a_elenco(): void
    {
        $idDirector = $this->crearDirector('director.gestion.elencos@msea.test');
        $idEstudiante = $this->crearEstudiante('estudiante.elenco@msea.test');
        $idTipo = DB::table('tipos_elencos')->where('nombre', 'Orquesta')->value('id_tipo');
        $idElenco = DB::table('elencos')->insertGetId([
            'nombre' => 'Inicial',
            'id_tipo' => $idTipo,
        ], 'id_elenco');

        $this->withSession([
            'usuario_id' => $idDirector,
            'rol' => 'director',
        ])->post('/dashboard-admin/elencos/asignar-estudiante', [
            'id_estudiante' => $idEstudiante,
            'id_elenco' => $idElenco,
        ])->assertRedirect('/dashboard-admin#asignaciones');

        $this->assertDatabaseHas('estudiantes', [
            'id_estudiante' => $idEstudiante,
            'id_elenco' => $idElenco,
        ]);

        $this->withSession([
            'usuario_id' => $idDirector,
            'rol' => 'director',
        ])->put('/dashboard-admin/elencos/'.$idElenco, [
            'nombre' => 'Inicial Actualizado',
            'tipo' => 'Elenco',
        ])->assertRedirect('/dashboard-admin#elencos');

        $idTipoElenco = DB::table('tipos_elencos')->where('nombre', 'Elenco')->value('id_tipo');
        $this->assertDatabaseHas('elencos', [
            'id_elenco' => $idElenco,
            'nombre' => 'Inicial Actualizado',
            'id_tipo' => $idTipoElenco,
        ]);

        $this->withSession([
            'usuario_id' => $idDirector,
            'rol' => 'director',
        ])->delete('/dashboard-admin/elencos/'.$idElenco)
            ->assertRedirect('/dashboard-admin#elencos');

        $this->assertDatabaseMissing('elencos', ['id_elenco' => $idElenco]);
        $this->assertDatabaseHas('estudiantes', [
            'id_estudiante' => $idEstudiante,
            'id_elenco' => null,
        ]);
    }

    public function test_director_puede_actualizar_su_perfil(): void
    {
        $idDirector = $this->crearDirector('director.perfil@msea.test');

        $this->withSession([
            'usuario_id' => $idDirector,
            'rol' => 'director',
        ])->post('/dashboard-admin/perfil', [
            'nombres' => 'Directora',
            'apellido_paterno' => 'Actualizada',
            'apellido_materno' => 'MSEA',
            'correo' => 'directora.actualizada@msea.test',
            'ci' => '123456',
            'celular' => '76543210',
            'direccion' => 'El Alto',
            'fecha_nacimiento' => '1985-01-15',
            'trayectoria' => 'Trayectoria pendiente de columna.',
            // S7: cambiar el correo exige la contrasena actual.
            'contrasena_actual' => 'director123',
        ])->assertRedirect('/dashboard-admin/perfil');

        $this->assertDatabaseHas('usuarios', [
            'id_usuario' => $idDirector,
            'nombres' => 'Directora',
            'apellido_paterno' => 'Actualizada',
            'correo' => 'directora.actualizada@msea.test',
            'celular' => '76543210',
        ]);
    }

    public function test_director_puede_asignar_profesor_a_estudiante(): void
    {
        $idDirector = $this->crearDirector('director.asigna.profesor@msea.test');
        $idEstudiante = $this->crearEstudiante('estudiante.profesor@msea.test');
        $idProfesorUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'profesor.asignado@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Profesor',
            'apellido_paterno' => 'Asignado',
            'id_rol' => DB::table('roles')->where('nombre', 'profesor')->value('id_rol'),
        ], 'id_usuario');
        $idProfesor = DB::table('profesores')->insertGetId([
            'id_usuario' => $idProfesorUsuario,
        ], 'id_profesor');

        $this->withSession([
            'usuario_id' => $idDirector,
            'rol' => 'director',
        ])->post('/dashboard-admin/profesores/asignar-estudiante', [
            'id_estudiante' => $idEstudiante,
            'id_profesor' => $idProfesor,
        ])->assertRedirect('/dashboard-admin#asignaciones');

        $this->assertDatabaseHas('estudiantes', [
            'id_estudiante' => $idEstudiante,
            'id_profesor' => $idProfesor,
        ]);
    }

    private function crearDirector(string $correo): int
    {
        return DB::table('usuarios')->insertGetId([
            'correo' => $correo,
            'contrasena' => Hash::make('director123'),
            'nombres' => 'Director',
            'apellido_paterno' => 'Test',
            'id_rol' => DB::table('roles')->where('nombre', 'director')->value('id_rol'),
        ], 'id_usuario');
    }

    private function crearEstudiante(string $correo): int
    {
        $idUsuario = DB::table('usuarios')->insertGetId([
            'correo' => $correo,
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Estudiante',
            'apellido_paterno' => 'Elenco',
            'id_rol' => DB::table('roles')->where('nombre', 'estudiante')->value('id_rol'),
        ], 'id_usuario');

        return DB::table('estudiantes')->insertGetId([
            'id_usuario' => $idUsuario,
            'id_seccion' => DB::table('secciones')->where('nombre', 'General')->value('id_seccion'),
            'fecha_ingreso' => now()->toDateString(),
        ], 'id_estudiante');
    }
}
