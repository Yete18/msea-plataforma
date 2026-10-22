<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsMseaCatalogs;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsMseaCatalogs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMseaCatalogs();
    }

    public function test_estudiante_puede_registrarse_e_ingresar_al_dashboard(): void
    {
        $correo = 'estudiante.test.'.time().'@msea.test';

        $this->post('/registro', [
            'acepta_privacidad' => '1',
            'rol' => 'estudiante',
            'nombres' => 'Estudiante',
            'apellido_paterno' => 'Prueba',
            'usuario' => 'estudiante_prueba',
            'correo' => $correo,
            'instrumento' => 'violin',
            'nivel' => 'General', // debe ser un nivel existente (tabla secciones)
            'contrasena' => 'secret123',
            'contrasena_confirmation' => 'secret123',
        ])->assertRedirect('/dashboard-estudiante');

        $this->assertDatabaseHas('usuarios', [
            'correo' => $correo,
            'nombres' => 'Estudiante',
            'apellido_paterno' => 'Prueba',
        ]);

        $usuario = DB::table('usuarios')->where('correo', $correo)->first();

        $this->assertDatabaseHas('estudiantes', [
            'id_usuario' => $usuario->id_usuario,
        ]);

        $this->assertDatabaseHas('instrumentos', [
            'nombre' => 'Violin',
        ]);

        $this->assertDatabaseHas('usuario_instrumento', [
            'id_usuario' => $usuario->id_usuario,
        ]);

        $this->post('/logout')->assertRedirect('/login');

        $this->post('/login', [
            'rol' => 'estudiante',
            'correo' => $correo,
            'contrasena' => 'secret123',
        ])->assertRedirect('/dashboard-estudiante');
    }

    public function test_estudiante_con_contrasena_antigua_entra_tras_la_migracion_de_hash(): void
    {
        $correo = 'legacy.test.'.time().'@msea.test';
        $rol = DB::table('roles')->where('nombre', 'estudiante')->first();
        $seccion = DB::table('secciones')->where('nombre', 'General')->first();

        if (! $seccion) {
            $idSeccion = DB::table('secciones')->insertGetId([
                'nombre' => 'General',
            ], 'id_seccion');
        } else {
            $idSeccion = $seccion->id_seccion;
        }

        $idUsuario = DB::table('usuarios')->insertGetId([
            'correo' => $correo,
            'contrasena' => '1234',
            'nombres' => 'Legacy',
            'apellido_paterno' => 'Prueba',
            'id_rol' => $rol->id_rol,
        ], 'id_usuario');

        DB::table('estudiantes')->insert([
            'id_usuario' => $idUsuario,
            'id_seccion' => $idSeccion,
            'fecha_ingreso' => now()->toDateString(),
        ]);

        (require database_path('migrations/2026_10_17_000003_msea_hashear_contrasenas_antiguas.php'))->up();

        $this->post('/login', [
            'rol' => 'estudiante',
            'correo' => $correo,
            'contrasena' => '1234',
        ])->assertRedirect('/dashboard-estudiante');

        $contrasenaActualizada = DB::table('usuarios')
            ->where('id_usuario', $idUsuario)
            ->value('contrasena');

        $this->assertNotSame('1234', $contrasenaActualizada);
        $this->assertTrue(Hash::check('1234', $contrasenaActualizada));
    }

    public function test_login_rechaza_credenciales_invalidas(): void
    {
        $this->post('/login', [
            'rol' => 'estudiante',
            'correo' => 'no-existe@msea.test',
            'contrasena' => 'incorrecta',
        ])->assertRedirect('/')
            ->assertSessionHas('error');
    }

    public function test_registro_publico_no_permite_crear_profesores(): void
    {
        $correo = 'profesor.publico.'.time().'@msea.test';

        $this->post('/registro', [
            'rol' => 'profesor',
            'nombres' => 'Profesor',
            'apellido_paterno' => 'Publico',
            'correo' => $correo,
            'contrasena' => 'secret123',
            'contrasena_confirmation' => 'secret123',
        ])->assertSessionHasErrors('rol');

        $this->assertDatabaseMissing('usuarios', [
            'correo' => $correo,
        ]);
    }
}
