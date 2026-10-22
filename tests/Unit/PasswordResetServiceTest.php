<?php

namespace Tests\Unit;

use App\Mail\CodigoRecuperacionMail;
use App\Services\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SeedsMseaCatalogs;
use Tests\TestCase;

class PasswordResetServiceTest extends TestCase
{
    use RefreshDatabase;
    use SeedsMseaCatalogs;

    private PasswordResetService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedMseaCatalogs();
        $this->service = app(PasswordResetService::class);
    }

    public function test_envia_codigo_solo_a_usuario_existente_e_invalida_codigos_previos(): void
    {
        $correo = 'reset@msea.test';
        $this->crearUsuario($correo);
        Mail::fake();

        $codigoAnterior = DB::table('password_reset_codes')->insertGetId([
            'correo' => $correo,
            'codigo' => Hash::make('111111'),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now()->subMinute(),
        ], 'id');

        $this->service->enviarCodigoSiUsuarioExiste($correo);

        $this->assertNotNull(DB::table('password_reset_codes')->where('id', $codigoAnterior)->value('used_at'));
        $this->assertSame(1, DB::table('password_reset_codes')->where('correo', $correo)->whereNull('used_at')->count());
        $this->assertDatabaseHas('password_reset_codes', [
            'correo' => $correo,
        ]);
        Mail::assertSent(CodigoRecuperacionMail::class, fn ($mail) => $mail->hasTo($correo) && preg_match('/^\d{6}$/', $mail->codigo));
    }

    public function test_no_crea_codigo_si_el_usuario_no_existe(): void
    {
        Mail::fake();

        $this->service->enviarCodigoSiUsuarioExiste('nadie@msea.test');

        $this->assertDatabaseCount('password_reset_codes', 0);
        Mail::assertNothingSent();
    }

    public function test_verifica_codigo_vigente_y_rechaza_codigo_incorrecto_o_expirado(): void
    {
        $correo = 'codigo@msea.test';
        $codigoVigente = DB::table('password_reset_codes')->insertGetId([
            'correo' => $correo,
            'codigo' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
        ], 'id');

        DB::table('password_reset_codes')->insert([
            'correo' => $correo,
            'codigo' => Hash::make('654321'),
            'expires_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(2),
        ]);

        $this->assertSame($codigoVigente, $this->service->verificarCodigo($correo, '123456'));
        $this->assertNull($this->service->verificarCodigo($correo, '000000'));
        $this->assertNull($this->service->verificarCodigo($correo, '654321'));
    }

    public function test_restablece_contrasena_y_marca_codigo_como_usado(): void
    {
        $correo = 'reset-final@msea.test';
        $this->crearUsuario($correo);

        $codigoId = DB::table('password_reset_codes')->insertGetId([
            'correo' => $correo,
            'codigo' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
        ], 'id');

        $this->assertTrue($this->service->resetPassword($correo, $codigoId, 'nueva123'));
        $this->assertNotNull(DB::table('password_reset_codes')->where('id', $codigoId)->value('used_at'));
        $this->assertTrue(Hash::check('nueva123', DB::table('usuarios')->where('correo', $correo)->value('contrasena')));
        $this->assertFalse($this->service->resetPassword($correo, $codigoId, 'otra123'));
    }

    private function crearUsuario(string $correo): void
    {
        DB::table('usuarios')->insert([
            'correo' => $correo,
            'contrasena' => Hash::make('secret123'),
            'nombres' => 'Usuario',
            'apellido_paterno' => 'Reset',
            'id_rol' => DB::table('roles')->where('nombre', 'estudiante')->value('id_rol'),
        ]);
    }
}
