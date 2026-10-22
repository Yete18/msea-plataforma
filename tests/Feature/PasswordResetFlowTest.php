<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsMseaCatalogs;
use Tests\TestCase;

class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsMseaCatalogs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMseaCatalogs();
    }

    public function test_estudiante_puede_cambiar_contrasena_con_codigo_valido(): void
    {
        $correo = 'reset.test.'.time().'@msea.test';
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
            'contrasena' => Hash::make('vieja123'),
            'nombres' => 'Reset',
            'apellido_paterno' => 'Prueba',
            'id_rol' => $rol->id_rol,
        ], 'id_usuario');

        DB::table('estudiantes')->insert([
            'id_usuario' => $idUsuario,
            'id_seccion' => $idSeccion,
            'fecha_ingreso' => now()->toDateString(),
        ]);

        $codigoId = DB::table('password_reset_codes')->insertGetId([
            'correo' => $correo,
            'codigo' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
        ], 'id');

        $this->withSession(['password_reset_correo' => $correo])
            ->post('/verify-code', ['codigo' => '654321'])
            ->assertRedirect('/reset-password');

        $this->withSession([
            'password_reset_correo' => $correo,
            'password_reset_verified' => true,
            'password_reset_code_id' => $codigoId,
        ])->post('/reset-password', [
            'contrasena' => 'nueva123',
            'contrasena_confirmation' => 'nueva123',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check(
            'nueva123',
            DB::table('usuarios')->where('id_usuario', $idUsuario)->value('contrasena')
        ));

        $this->assertNotNull(
            DB::table('password_reset_codes')->where('id', $codigoId)->value('used_at')
        );

        $this->post('/login', [
            'rol' => 'estudiante',
            'correo' => $correo,
            'contrasena' => 'nueva123',
        ])->assertRedirect('/dashboard-estudiante');
    }
}
