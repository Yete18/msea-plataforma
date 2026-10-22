<?php

namespace Tests\Feature\Seguridad;

use App\Services\GamificationService;
use App\Services\TeoriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/**
 * Fase D: inicio de sesion sin elegir rol (UX-07), datos de identidad que solo
 * edita el director (UX-09), XP de teoria (SEC-05) y racha con dia de
 * descanso (UX-06).
 */
class FaseDAcademicoTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
    }

    /* --------------------------------------------------------------- UX-07 */

    public function test_cada_rol_inicia_sesion_sin_elegir_rol(): void
    {
        $paneles = [
            'estudiante' => '/dashboard-estudiante',
            'profesor' => '/dashboard-profesor',
            'director' => '/dashboard-admin',
            'padre' => '/dashboard-padre',
        ];

        foreach ($paneles as $rol => $panel) {
            $idUsuario = match ($rol) {
                'estudiante' => $this->crearEstudiante()['usuario'],
                'profesor' => $this->crearProfesor()['usuario'],
                'padre' => $this->crearPadre()['usuario'],
                default => $this->crearUsuario('director'),
            };
            $correo = DB::table('usuarios')->where('id_usuario', $idUsuario)->value('correo');

            $this->post('/login', ['correo' => $correo, 'contrasena' => 'secret123'])
                ->assertRedirect($panel);
            $this->assertSame($rol, session('rol'));
            $this->post('/logout');
            $this->flushSession();
        }
    }

    public function test_un_rol_enviado_por_un_formulario_viejo_se_ignora(): void
    {
        $alumno = $this->crearEstudiante();
        $correo = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('correo');

        // Antes esto devolvia "el rol no coincide"; ahora manda el rol de la cuenta.
        $this->post('/login', ['rol' => 'profesor', 'correo' => $correo, 'contrasena' => 'secret123'])
            ->assertRedirect('/dashboard-estudiante');
        $this->assertSame('estudiante', session('rol'));
    }

    public function test_un_rol_desconocido_sigue_siendo_invalido(): void
    {
        $alumno = $this->crearEstudiante();
        $correo = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('correo');

        $this->from('/login')->post('/login', ['rol' => 'administrador', 'correo' => $correo, 'contrasena' => 'secret123'])
            ->assertRedirect('/login')->assertSessionHasErrors('rol');
    }

    public function test_el_login_ya_no_muestra_selector_de_rol(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('data-role', false)->assertDontSee('name="rol"', false);
    }

    /* --------------------------------------------------------------- UX-09 */

    public function test_el_estudiante_no_cambia_ci_direccion_ni_fecha_de_nacimiento(): void
    {
        $alumno = $this->crearEstudiante(null, null, ['ci' => '111', 'direccion' => 'Calle 1', 'fecha_nacimiento' => '2010-01-01']);
        $correo = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('correo');

        $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/perfil', [
            'nombres' => 'Ana',
            'apellido_paterno' => 'Prueba',
            'correo' => $correo,
            'celular' => '70000000',
            'ci' => '999',
            'direccion' => 'Otra calle',
            'fecha_nacimiento' => '2000-01-01',
        ])->assertOk()->assertJsonMissingPath('estudiante.ci');

        $fila = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->first();
        $this->assertSame('111', $fila->ci);
        $this->assertSame('Calle 1', $fila->direccion);
        $this->assertSame('2010-01-01', substr((string) $fila->fecha_nacimiento, 0, 10));
        $this->assertSame('70000000', $fila->celular);
    }

    public function test_el_director_si_corrige_esos_datos(): void
    {
        $director = $this->crearUsuario('director');
        $alumno = $this->crearEstudiante();
        $correo = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('correo');

        $this->sesionDe($director)->put('/dashboard-admin/usuarios/'.$alumno['usuario'], [
            'nombres' => 'Ana',
            'correo' => $correo,
            'ci' => '555',
            'direccion' => 'Av. Nueva 10',
            'fecha_nacimiento' => '2011-03-04',
        ])->assertSessionHasNoErrors();

        $fila = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->first();
        $this->assertSame('555', $fila->ci);
        $this->assertSame('Av. Nueva 10', $fila->direccion);
        $this->assertSame('2011-03-04', substr((string) $fila->fecha_nacimiento, 0, 10));
    }

    public function test_la_fecha_de_nacimiento_no_puede_ser_futura(): void
    {
        $director = $this->crearUsuario('director');
        $alumno = $this->crearEstudiante();
        $correo = DB::table('usuarios')->where('id_usuario', $alumno['usuario'])->value('correo');

        $this->sesionDe($director)->put('/dashboard-admin/usuarios/'.$alumno['usuario'], [
            'nombres' => 'Ana', 'correo' => $correo, 'fecha_nacimiento' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('fecha_nacimiento');
    }

    /* --------------------------------------------------------------- SEC-05 */

    public function test_xp_de_teoria_tras_un_error(): void
    {
        $this->assertSame(15, TeoriaService::xpTrasError(60));
        $this->assertSame(3, TeoriaService::xpTrasError(15));
        $this->assertSame(1, TeoriaService::xpTrasError(2));
        $this->assertSame(0, TeoriaService::xpTrasError(0));
    }

    /* --------------------------------------------------------------- UX-06 */

    private function perfilConRacha(int $idEstudiante, int $racha, int $diasAtras): void
    {
        DB::table('gamificacion_perfiles')->insert([
            'id_estudiante' => $idEstudiante, 'xp_total' => 0, 'nivel' => 1,
            'racha_actual' => $racha, 'mejor_racha' => $racha,
            'ultima_practica' => now()->subDays($diasAtras)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_un_dia_de_descanso_no_corta_la_racha(): void
    {
        $alumno = $this->crearEstudiante();
        $this->perfilConRacha($alumno['estudiante'], 4, 2);
        $servicio = app(GamificationService::class);

        $this->assertSame(4, $servicio->resumen($alumno['estudiante'])['racha_actual']);

        $servicio->registrarActividadDiaria($alumno['estudiante']);
        $this->assertSame(5, $servicio->resumen($alumno['estudiante'])['racha_actual']);
    }

    public function test_dos_dias_sin_practicar_reinician_la_racha(): void
    {
        $alumno = $this->crearEstudiante();
        $this->perfilConRacha($alumno['estudiante'], 4, 3);
        $servicio = app(GamificationService::class);

        $this->assertSame(0, $servicio->resumen($alumno['estudiante'])['racha_actual']);

        $servicio->registrarActividadDiaria($alumno['estudiante']);
        $resumen = $servicio->resumen($alumno['estudiante']);
        $this->assertSame(1, $resumen['racha_actual']);
        $this->assertSame(4, $resumen['mejor_racha']);
    }

    public function test_el_dia_de_descanso_se_puede_desactivar(): void
    {
        config(['msea.racha.dias_descanso' => 0]);
        $alumno = $this->crearEstudiante();
        $this->perfilConRacha($alumno['estudiante'], 4, 2);

        $this->assertSame(0, app(GamificationService::class)->resumen($alumno['estudiante'])['racha_actual']);
    }

    public function test_el_inicio_del_estudiante_no_repite_racha_ni_xp_en_la_barra(): void
    {
        $alumno = $this->crearEstudiante();

        $this->sesionDe($alumno['usuario'])->get('/dashboard-estudiante')->assertOk()
            ->assertDontSee('streak-pill', false)
            ->assertDontSee('points-pill', false)
            ->assertDontSee('banner-progress', false)
            ->assertDontSee('en riesgo');
    }
}
