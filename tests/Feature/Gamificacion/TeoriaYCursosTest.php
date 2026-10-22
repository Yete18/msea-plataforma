<?php

namespace Tests\Feature\Gamificacion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/** L4 y flujo academico: teoria evaluada en servidor, cursos y lecciones. */
class TeoriaYCursosTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
    }

    private function pregunta(?int $idLeccion = null, int $xp = 10): int
    {
        return DB::table('teoria_contenidos')->insertGetId([
            'id_leccion' => $idLeccion, 'titulo' => 'Clave de sol', 'pregunta' => 'Que nota esta en la segunda linea?',
            'opciones' => json_encode(['Sol', 'La', 'Si']), 'respuesta_correcta' => 'Sol', 'xp' => $xp, 'activo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ], 'id_teoria');
    }

    public function test_la_respuesta_correcta_no_llega_al_navegador(): void
    {
        $this->pregunta();
        $alumno = $this->crearEstudiante();

        $respuesta = $this->sesionDe($alumno['usuario'])->get('/dashboard-estudiante')->assertOk();
        $teoria = $respuesta->viewData('teoriaData');

        $this->assertCount(1, $teoria);
        $this->assertArrayNotHasKey('respuesta', $teoria[0]);
        $this->assertArrayNotHasKey('respuesta_correcta', $teoria[0]);
        $this->assertStringNotContainsString('respuesta_correcta', $respuesta->getContent());
    }

    public function test_responder_evalua_en_servidor_y_da_xp_una_vez(): void
    {
        $id = $this->pregunta(null, 15);
        $alumno = $this->crearEstudiante();
        $url = "/dashboard-estudiante/teoria/{$id}/responder";

        $this->sesionDe($alumno['usuario'])->postJson($url, ['respuesta' => 'La'])
            ->assertOk()->assertJsonPath('correcta', false)->assertJsonPath('xp', 0)->assertJsonPath('respuesta_correcta', 'Sol');
        // SEC-05: después de ver la respuesta, acertar da solo un cuarto del XP.
        $this->sesionDe($alumno['usuario'])->postJson($url, ['respuesta' => 'Sol'])
            ->assertOk()->assertJsonPath('correcta', true)->assertJsonPath('xp', 3)->assertJsonPath('primer_intento', false);
        $this->sesionDe($alumno['usuario'])->postJson($url, ['respuesta' => 'Sol'])
            ->assertOk()->assertJsonPath('correcta', true)->assertJsonPath('xp', 0)->assertJsonPath('ya_resuelta', true);

        $this->assertSame(3, $this->xpDe($alumno['estudiante']));

        // Quien acierta al primer intento recibe el XP completo.
        $otro = $this->crearEstudiante();
        $this->sesionDe($otro['usuario'])->postJson($url, ['respuesta' => 'Sol'])
            ->assertOk()->assertJsonPath('xp', 15)->assertJsonPath('primer_intento', true);
        $this->assertSame(15, $this->xpDe($otro['estudiante']));
        $this->assertSame(4, DB::table('teoria_intentos')->count());
    }

    public function test_respuesta_manipulada_fuera_de_opciones_se_rechaza(): void
    {
        $id = $this->pregunta();
        $alumno = $this->crearEstudiante();

        $this->sesionDe($alumno['usuario'])->postJson("/dashboard-estudiante/teoria/{$id}/responder", ['respuesta' => 'cualquier cosa'])->assertUnprocessable();
        $this->assertSame(0, DB::table('teoria_intentos')->count());
    }

    public function test_curso_completo_inscripcion_leccion_y_progreso(): void
    {
        $director = $this->crearUsuario('director');
        $elenco = $this->crearElenco();
        $alumno = $this->crearEstudiante(null, $elenco);
        $ajeno = $this->crearEstudiante();

        // CRUD del director
        $this->sesionDe($director)->post('/dashboard-admin/cursos', ['titulo' => 'Violin Inicial', 'nivel' => 'Inicial', 'instrumento' => 'Violin'])
            ->assertRedirect();
        $idCurso = (int) DB::table('cursos')->value('id_curso');
        $this->sesionDe($director)->post("/dashboard-admin/cursos/{$idCurso}/modulos", ['titulo' => 'Postura'])->assertRedirect();
        $idModulo = (int) DB::table('modulos')->value('id_modulo');
        $this->sesionDe($director)->post("/dashboard-admin/cursos/{$idCurso}/modulos/{$idModulo}/lecciones", ['titulo' => 'Como sostener el arco', 'tipo' => 'tecnica', 'xp' => 20])->assertRedirect();
        $idLeccion = (int) DB::table('lecciones')->value('id_leccion');
        $this->sesionDe($director)->post('/dashboard-admin/preguntas', [
            'id_leccion' => $idLeccion, 'titulo' => 'Arco', 'pregunta' => 'Que dedo va debajo?', 'opciones' => ['Pulgar', 'Indice', ''], 'respuesta_correcta' => 'Pulgar', 'xp' => 5,
        ])->assertRedirect();
        $idPregunta = (int) DB::table('teoria_contenidos')->value('id_teoria');
        $this->sesionDe($director)->put("/dashboard-admin/cursos/{$idCurso}", ['titulo' => 'Violin Inicial MSEA', 'nivel' => 'Inicial'])->assertRedirect();
        $this->assertDatabaseHas('cursos', ['titulo' => 'Violin Inicial MSEA']);

        // Inscripcion por elenco
        $this->sesionDe($director)->post("/dashboard-admin/cursos/{$idCurso}/inscripciones", ['id_elenco' => $elenco])->assertRedirect();
        $this->assertDatabaseHas('curso_estudiante', ['id_curso' => $idCurso, 'id_estudiante' => $alumno['estudiante']]);

        // El no inscrito no ve el curso ni su pregunta
        $this->sesionDe($ajeno['usuario'])->get("/dashboard-estudiante/cursos/{$idCurso}")->assertNotFound();
        $this->sesionDe($ajeno['usuario'])->postJson("/dashboard-estudiante/teoria/{$idPregunta}/responder", ['respuesta' => 'Pulgar'])->assertUnprocessable();
        $this->sesionDe($ajeno['usuario'])->postJson("/dashboard-estudiante/lecciones/{$idLeccion}/completar")->assertNotFound();

        // El inscrito debe responder bien antes de completar
        $this->sesionDe($alumno['usuario'])->get("/dashboard-estudiante/cursos/{$idCurso}")->assertOk()->assertSee('Como sostener el arco');
        $this->sesionDe($alumno['usuario'])->postJson("/dashboard-estudiante/lecciones/{$idLeccion}/completar")->assertUnprocessable();
        $this->sesionDe($alumno['usuario'])->postJson("/dashboard-estudiante/teoria/{$idPregunta}/responder", ['respuesta' => 'Pulgar'])->assertJsonPath('xp', 5);
        $this->sesionDe($alumno['usuario'])->postJson("/dashboard-estudiante/lecciones/{$idLeccion}/completar")->assertOk()->assertJsonPath('xp', 20);
        $this->sesionDe($alumno['usuario'])->postJson("/dashboard-estudiante/lecciones/{$idLeccion}/completar")->assertOk()->assertJsonPath('xp', 0);

        $cursos = $this->sesionDe($alumno['usuario'])->get('/dashboard-estudiante')->viewData('cursosData');
        $this->assertSame(100, $cursos[0]['progreso']);
        $this->assertSame(25, $this->xpDe($alumno['estudiante']));

        // Leccion con avance: al "eliminar" se despublica para conservar historial
        $this->sesionDe($director)->delete("/dashboard-admin/cursos/{$idCurso}/modulos/{$idModulo}/lecciones/{$idLeccion}")->assertRedirect();
        $this->assertDatabaseHas('lecciones', ['id_leccion' => $idLeccion, 'publicado' => false]);
    }

    public function test_pregunta_con_respuesta_que_no_es_opcion_se_rechaza(): void
    {
        $director = $this->crearUsuario('director');

        $this->sesionDe($director)->post('/dashboard-admin/preguntas', [
            'titulo' => 'x', 'pregunta' => 'y', 'opciones' => ['A', 'B'], 'respuesta_correcta' => 'C',
        ])->assertSessionHasErrors('respuesta_correcta');
    }

    public function test_solo_el_director_gestiona_cursos(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante();

        $this->sesionDe($prof['usuario'])->post('/dashboard-admin/cursos', ['titulo' => 'X'])->assertRedirect('/login');
        $this->sesionDe($alumno['usuario'])->post('/dashboard-admin/cursos', ['titulo' => 'X'])->assertRedirect('/login');
        $this->assertDatabaseCount('cursos', 0);
    }
}
