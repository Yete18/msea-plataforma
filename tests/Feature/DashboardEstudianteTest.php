<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SeedsMseaCatalogs;
use Tests\TestCase;

class DashboardEstudianteTest extends TestCase
{
    use RefreshDatabase;
    use SeedsMseaCatalogs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMseaCatalogs();
    }

    public function test_dashboard_estudiante_renderiza_datos_de_base_de_datos(): void
    {
        $rol = DB::table('roles')->where('nombre', 'estudiante')->first();
        $seccionId = DB::table('secciones')->insertGetId([
            'nombre' => 'Prueba Dashboard '.time(),
        ], 'id_seccion');

        $idUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'dashboard.'.time().'@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Dashboard',
            'apellido_paterno' => 'Estudiante',
            'id_rol' => $rol->id_rol,
        ], 'id_usuario');

        $idEstudiante = DB::table('estudiantes')->insertGetId([
            'id_usuario' => $idUsuario,
            'id_seccion' => $seccionId,
            'fecha_ingreso' => now()->toDateString(),
        ], 'id_estudiante');

        $idInstrumento = DB::table('instrumentos')->insertGetId([
            'nombre' => 'Instrumento Test '.time(),
        ], 'id_instrumento');

        DB::table('usuario_instrumento')->insert([
            'id_usuario' => $idUsuario,
            'id_instrumento' => $idInstrumento,
        ]);

        $idTipo = DB::table('tipos_ejercicio')->insertGetId([
            'nombre' => 'Tipo Test '.time(),
        ], 'id_tipo');

        $idProfesorUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'prof.dashboard.'.time().'@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Profesor',
            'apellido_paterno' => 'Prueba',
            'id_rol' => DB::table('roles')->where('nombre', 'profesor')->value('id_rol'),
        ], 'id_usuario');

        $idProfesor = DB::table('profesores')->insertGetId([
            'id_usuario' => $idProfesorUsuario,
        ], 'id_profesor');

        $idEjercicio = DB::table('ejercicios')->insertGetId([
            'id_tipo' => $idTipo,
            'descripcion' => 'Ejercicio test',
            'creado_por' => $idProfesor,
        ], 'id_ejercicio');

        DB::table('progreso')->insert([
            'id_estudiante' => $idEstudiante,
            'id_ejercicio' => $idEjercicio,
            'puntaje' => 120,
            'estado' => 'completado',
        ]);

        $this->withSession([
            'usuario_id' => $idUsuario,
            'rol' => 'estudiante',
        ])->get('/dashboard-estudiante')
            ->assertOk()
            ->assertSee('MSEA_ESTUDIANTE')
            ->assertSee('Dashboard')
            ->assertSee('Instrumento Test', false);
    }

    public function test_estudiante_puede_actualizar_perfil_sin_cambiar_contrasena(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $rol = DB::table('roles')->where('nombre', 'estudiante')->first();
        $seccionId = DB::table('secciones')->insertGetId([
            'nombre' => 'Perfil Test '.time(),
        ], 'id_seccion');

        $password = Hash::make('secret123');
        $idUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'perfil.'.time().'@msea.test',
            'contrasena' => $password,
            'nombres' => 'Nombre',
            'apellido_paterno' => 'Anterior',
            'id_rol' => $rol->id_rol,
        ], 'id_usuario');

        DB::table('estudiantes')->insert([
            'id_usuario' => $idUsuario,
            'id_seccion' => $seccionId,
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $this->withSession([
            'usuario_id' => $idUsuario,
            'rol' => 'estudiante',
        ])->postJson('/dashboard-estudiante/perfil', [
            'nombres' => 'Maria Elena',
            'apellido_paterno' => 'Quispe',
            'apellido_materno' => 'Choque',
            'correo' => 'perfil.actualizado.'.time().'@msea.test',
            'ci' => '1234567',
            'celular' => '76543210',
            'direccion' => 'Av. Siempre Viva 123',
            'fecha_nacimiento' => '2008-05-10',
            'contrasena' => 'no-debe-guardarse',
            // S7: cambiar el correo exige la contrasena actual.
            'contrasena_actual' => 'secret123',
            'foto' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLhGQAAAABJRU5ErkJggg==',
        ])->assertOk()
            ->assertJsonPath('estudiante.nombre', 'Maria Elena')
            ->assertJsonPath('estudiante.apellidoMaterno', 'Choque')
            ->assertJsonMissingPath('estudiante.ci');

        $usuario = DB::table('usuarios')->where('id_usuario', $idUsuario)->first();

        $this->assertSame('Maria Elena', $usuario->nombres);
        $this->assertSame('Quispe', $usuario->apellido_paterno);
        $this->assertSame('Choque', $usuario->apellido_materno);
        // UX-09: el estudiante no cambia su CI, su dirección ni su fecha de nacimiento.
        $this->assertNull($usuario->ci);
        $this->assertNull($usuario->direccion);
        $this->assertNull($usuario->fecha_nacimiento);
        $this->assertSame('76543210', $usuario->celular);
        $this->assertStringStartsWith('avatars/', $usuario->foto);
        Storage::disk('local')->assertExists($usuario->foto);
        Storage::disk('public')->assertMissing($usuario->foto);
        $this->assertSame($password, $usuario->contrasena);
    }

    public function test_foto_base64_antigua_sigue_renderizando_en_dashboard(): void
    {
        $rol = DB::table('roles')->where('nombre', 'estudiante')->first();
        $fotoBase64 = 'data:image/jpeg;base64,'.base64_encode('foto-antigua');

        $idUsuario = DB::table('usuarios')->insertGetId([
            'correo' => 'foto.antigua.'.time().'@msea.test',
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Foto',
            'apellido_paterno' => 'Antigua',
            'foto' => $fotoBase64,
            'id_rol' => $rol->id_rol,
        ], 'id_usuario');

        DB::table('estudiantes')->insert([
            'id_usuario' => $idUsuario,
            'id_seccion' => DB::table('secciones')->where('nombre', 'General')->value('id_seccion'),
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $this->withSession([
            'usuario_id' => $idUsuario,
            'rol' => 'estudiante',
        ])->get('/dashboard-estudiante')
            ->assertOk()
            ->assertSee(str_replace('/', '\\/', $fotoBase64), false);
    }
}
