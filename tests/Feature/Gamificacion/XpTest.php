<?php

namespace Tests\Feature\Gamificacion;

use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/** L1, L2, L3, L8: XP calculado por el servidor, idempotente y consistente. */
class XpTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
    }

    private function practicar(int $idUsuario, string $tipo, int $segundos, array $extra = []): array
    {
        $inicio = $this->sesionDe($idUsuario)->postJson('/dashboard-estudiante/practicas', ['tipo' => $tipo])->assertCreated();
        $this->travel($segundos)->seconds();

        return $this->sesionDe($idUsuario)
            ->postJson('/dashboard-estudiante/practicas/'.$inicio->json('id_practica').'/finalizar', $extra)
            ->assertOk()->json() + ['id' => $inicio->json('id_practica')];
    }

    public function test_el_navegador_no_decide_el_xp_ni_la_duracion(): void
    {
        $alumno = $this->crearEstudiante();

        // El cliente "dice" 2 horas y 100% de precision; el servidor midio 120 s.
        $r = $this->practicar($alumno['usuario'], 'ritmo', 120, ['precision' => 100, 'xp' => 9999, 'duracion_segundos' => 7200]);

        $this->assertSame(20 + 2, $r['xp']);      // base ritmo + 2 min de bono
        $this->assertSame(120, $r['duracion_segundos']);
        $this->assertFalse($r['metricas_verificadas']);
        $this->assertSame(22, $this->xpDe($alumno['estudiante']));
    }

    public function test_finalizar_dos_veces_no_duplica_xp(): void
    {
        $alumno = $this->crearEstudiante();
        $r = $this->practicar($alumno['usuario'], 'afinacion', 60);

        $repetida = $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/practicas/'.$r['id'].'/finalizar')->assertOk();

        $this->assertSame(0, $repetida->json('xp'));
        $this->assertSame($r['xp'], $this->xpDe($alumno['estudiante']));
    }

    public function test_practica_muy_corta_no_da_xp(): void
    {
        $alumno = $this->crearEstudiante();
        $r = $this->practicar($alumno['usuario'], 'ritmo', 5);

        $this->assertSame(0, $r['xp']);
        $this->assertSame(0, $this->xpDe($alumno['estudiante']));
    }

    public function test_tope_diario_de_xp_por_practica(): void
    {
        config(['msea.xp.tope_diario_practica' => 50]);
        $alumno = $this->crearEstudiante();

        $total = 0;
        foreach (range(1, 5) as $_) {
            $total += $this->practicar($alumno['usuario'], 'ritmo', 60)['xp'];
        }

        $this->assertSame(50, $total);
        $this->assertSame(50, $this->xpDe($alumno['estudiante']));
        $this->assertSame(5, DB::table('practicas_autonomas')->where('estado', 'finalizada')->count(), 'La practica se registra aunque no de XP');
    }

    public function test_no_se_puede_finalizar_la_practica_de_otro(): void
    {
        $a = $this->crearEstudiante();
        $b = $this->crearEstudiante();
        $id = $this->sesionDe($a['usuario'])->postJson('/dashboard-estudiante/practicas', ['tipo' => 'ritmo'])->json('id_practica');

        $this->sesionDe($b['usuario'])->postJson("/dashboard-estudiante/practicas/{$id}/finalizar")->assertNotFound();
    }

    public function test_tipo_o_ejercicio_inexistente_da_error_controlado(): void
    {
        $alumno = $this->crearEstudiante();

        $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/practicas', ['tipo' => 'hackeo'])->assertUnprocessable();
        $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/practicas', ['tipo' => 'ritmo', 'id_ejercicio' => 99999])->assertUnprocessable();
        $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/practicas/99999/finalizar')->assertNotFound();
    }

    public function test_tarea_calificada_dos_veces_da_xp_una_vez(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $idTarea = $this->crearTarea($prof['profesor'], $alumno['estudiante'], null, 100);

        $this->sesionDe($alumno['usuario'])->postJson("/dashboard-estudiante/tareas/{$idTarea}/entregar", ['comentario_estudiante' => 'hecho'])->assertOk();
        $idEntrega = DB::table('entregas_tareas')->value('id_entrega');

        $this->sesionDe($prof['usuario'])->postJson("/dashboard-profesor/entregas/{$idEntrega}/calificar", ['calificacion' => 80])
            ->assertOk()->assertJsonPath('xp_otorgado', 100);
        $this->sesionDe($prof['usuario'])->postJson("/dashboard-profesor/entregas/{$idEntrega}/calificar", ['calificacion' => 95])
            ->assertOk()->assertJsonPath('xp_otorgado', 0);

        $this->assertSame(100, $this->xpDe($alumno['estudiante']));
        $this->assertEquals(95, DB::table('entregas_tareas')->value('calificacion'));
    }

    public function test_devolver_y_reentregar_no_duplica_xp(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $idTarea = $this->crearTarea($prof['profesor'], $alumno['estudiante'], null, 40);
        $entregar = fn () => $this->sesionDe($alumno['usuario'])->postJson("/dashboard-estudiante/tareas/{$idTarea}/entregar", ['comentario_estudiante' => 'v'])->assertOk();

        $entregar();
        $idEntrega = DB::table('entregas_tareas')->value('id_entrega');
        $this->sesionDe($prof['usuario'])->postJson("/dashboard-profesor/entregas/{$idEntrega}/devolver", ['comentario_profesor' => 'Revisa el compas 4'])->assertOk();
        $this->assertSame('devuelta', DB::table('entregas_tareas')->value('estado'));

        $entregar();
        $this->sesionDe($prof['usuario'])->postJson("/dashboard-profesor/entregas/{$idEntrega}/calificar", ['calificacion' => 90])->assertOk();

        $this->assertSame(1, DB::table('entregas_tareas')->count());
        $this->assertSame(40, $this->xpDe($alumno['estudiante']));
    }

    public function test_misma_clave_de_xp_es_idempotente_aunque_se_repita(): void
    {
        $alumno = $this->crearEstudiante();
        $servicio = app(GamificationService::class);

        $otorgados = array_map(fn () => $servicio->otorgarXp($alumno['estudiante'], 30, 'tarea', 'tarea:42', 'x'), range(1, 5));

        $this->assertSame([30, 0, 0, 0, 0], $otorgados);
        $this->assertSame(1, DB::table('xp_movimientos')->where('clave', 'tarea:42')->count());
    }

    public function test_logro_y_su_bono_se_otorgan_una_sola_vez(): void
    {
        DB::table('logros')->insert(['codigo' => '500_xp', 'nombre' => '500 XP', 'xp_bonus' => 25, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        $alumno = $this->crearEstudiante();
        $servicio = app(GamificationService::class);

        $servicio->otorgarXp($alumno['estudiante'], 500, 'tarea', 'a', 'a');
        $servicio->otorgarXp($alumno['estudiante'], 10, 'tarea', 'b', 'b');

        $this->assertSame(1, DB::table('estudiante_logro')->count());
        $this->assertSame(1, DB::table('xp_movimientos')->where('fuente', 'logro')->count());
        $this->assertSame(535, $this->xpDe($alumno['estudiante']));
    }

    public function test_ranking_perfil_profesor_padre_y_director_muestran_el_mismo_xp(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $otro = $this->crearEstudiante($prof['profesor']);
        $padre = $this->crearPadre([$alumno['estudiante']]);
        $director = $this->crearUsuario('director');
        $servicio = app(GamificationService::class);
        $servicio->otorgarXp($alumno['estudiante'], 730, 'tarea', 't1', 'x');
        $servicio->otorgarXp($otro['estudiante'], 100, 'tarea', 't2', 'x');

        $ranking = DB::table('ranking_estudiantes')->where('id_estudiante', $alumno['estudiante'])->first();
        $this->assertSame(730, (int) $ranking->puntaje_total);
        $this->assertSame(1, (int) $ranking->posicion);

        $estudiante = $this->sesionDe($alumno['usuario'])->get('/dashboard-estudiante')->viewData('dashboardData');
        $this->assertSame(730, $estudiante['puntos']);
        $this->assertSame(2, $estudiante['nivel']);
        $this->assertSame(1, $estudiante['rankingPos']);

        $alumnos = collect($this->sesionDe($prof['usuario'])->get('/dashboard-profesor')->viewData('alumnosData'));
        $this->assertSame(730, $alumnos->firstWhere('id', $alumno['estudiante'])['puntos']);
        $this->assertSame('Nivel 2', $alumnos->firstWhere('id', $alumno['estudiante'])['nivel']);

        $hijo = $this->sesionDe($padre['usuario'])->get('/dashboard-padre')->viewData('estudiantes')->first();
        $this->assertSame(730, $hijo['xp_total']);
        $this->assertSame(2, $hijo['nivel']);
        $this->assertSame(1, $hijo['posicion']);

        $admin = collect($this->sesionDe($director)->get('/dashboard-admin')->viewData('estudiantesData'));
        $this->assertSame(730, $admin->firstWhere('id', $alumno['estudiante'])['xp']);
    }
}
