<?php

namespace Tests\Feature\Seguridad;

use App\Services\GoogleAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/** Inicio de sesion con Google (web y API movil). Google se simula con Http::fake. */
class GoogleLoginTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    private const CLIENTE = 'cliente-web.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        config([
            'services.google.client_id' => self::CLIENTE,
            'services.google.client_ids' => [self::CLIENTE],
        ]);
    }

    private function googleResponde(array $claims, int $status = 200): void
    {
        Http::fake(['oauth2.googleapis.com/*' => Http::response(array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::CLIENTE,
            'sub' => '1122334455',
            'email' => 'ana@gmail.com',
            'email_verified' => 'true',
            'exp' => (string) (time() + 3600),
            'name' => 'Ana',
        ], $claims), $status)]);
    }

    public function test_entra_con_google_si_el_correo_existe_y_vincula_la_cuenta(): void
    {
        $alumno = $this->crearEstudiante(null, null, ['correo' => 'ana@gmail.com']);
        $this->googleResponde(['email' => 'ANA@gmail.com']);

        $this->postJson('/auth/google', ['credential' => 'token-de-prueba'])
            ->assertOk()->assertJsonPath('redirect', url('/dashboard-estudiante'));

        $this->assertSame((int) $alumno['usuario'], (int) session('usuario_id'));
        $this->assertDatabaseHas('usuarios', ['id_usuario' => $alumno['usuario'], 'google_sub' => '1122334455']);
        $this->assertDatabaseHas('auditoria', ['accion' => 'login_google']);
    }

    public function test_sin_cuenta_en_msea_no_entra_ni_crea_usuarios(): void
    {
        $this->googleResponde(['email' => 'desconocido@gmail.com']);
        $antes = DB::table('usuarios')->count();

        $this->postJson('/auth/google', ['credential' => 'x'])->assertStatus(422)
            ->assertJsonPath('message', GoogleAuthService::SIN_CUENTA);

        $this->assertSame($antes, DB::table('usuarios')->count());
        $this->assertNull(session('usuario_id'));
    }

    public function test_rechaza_tokens_para_otra_app_sin_verificar_o_vencidos(): void
    {
        $this->crearEstudiante(null, null, ['correo' => 'ana@gmail.com']);

        foreach ([['aud' => 'otra-app.apps.googleusercontent.com'], ['email_verified' => 'false'], ['exp' => (string) (time() - 10)], ['iss' => 'https://malicioso.example']] as $claims) {
            $this->googleResponde($claims);
            $this->postJson('/auth/google', ['credential' => 'x'])->assertStatus(422);
        }

        $this->googleResponde([], 400); // Google dice que el token no es valido
        $this->postJson('/auth/google', ['credential' => 'x'])->assertStatus(422);
        $this->assertNull(session('usuario_id'));
    }

    public function test_cuenta_desactivada_o_vinculada_a_otro_google_no_entra(): void
    {
        $this->crearEstudiante(null, null, ['correo' => 'ana@gmail.com', 'activo' => false]);
        $this->googleResponde([]);
        $this->postJson('/auth/google', ['credential' => 'x'])->assertStatus(422);

        $otro = $this->crearEstudiante(null, null, ['correo' => 'beto@gmail.com']);
        DB::table('usuarios')->where('id_usuario', $otro['usuario'])->update(['google_sub' => 'otro-sub']);
        $this->googleResponde(['email' => 'beto@gmail.com', 'sub' => 'sub-distinto']);
        $this->postJson('/auth/google', ['credential' => 'x'])->assertStatus(422);
    }

    public function test_api_movil_emite_token_con_google(): void
    {
        $profe = $this->crearProfesor();
        DB::table('usuarios')->where('id_usuario', $profe['usuario'])->update(['correo' => 'profe@gmail.com']);
        $this->googleResponde(['email' => 'profe@gmail.com', 'sub' => '99']);

        $r = $this->postJson('/api/mobile/login/google', ['id_token' => 'x', 'device_name' => 'Pixel'])
            ->assertOk()->assertJsonPath('usuario.rol', 'profesor');

        $this->getJson('/api/mobile/me', ['Authorization' => 'Bearer '.$r->json('token')])->assertOk();
    }

    public function test_sin_configurar_no_se_muestra_ni_funciona(): void
    {
        config(['services.google.client_id' => null, 'services.google.client_ids' => []]);

        $this->get('/login')->assertOk()->assertDontSee('google-boton', false);
        $this->postJson('/auth/google', ['credential' => 'x'])->assertStatus(422);
        $this->assertStringNotContainsString('accounts.google.com', $this->get('/login')->headers->get('Content-Security-Policy'));
    }

    public function test_configurado_muestra_el_boton_y_permite_google_en_la_csp(): void
    {
        $r = $this->get('/login')->assertOk()->assertSee('google-boton', false);

        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://accounts.google.com/gsi/client', $csp);
        $this->assertStringContainsString('frame-src https://accounts.google.com/gsi/', $csp);
    }
}
