<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/** S1: un estudiante solo puede entregar/ver tareas propias o de SU elenco. */
class TareasAjenasTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        Storage::fake('local');
    }

    public function test_estudiante_sin_elenco_no_puede_entregar_tarea_de_otro(): void
    {
        $prof = $this->crearProfesor();
        $victima = $this->crearEstudiante($prof['profesor']);
        $atacante = $this->crearEstudiante(null, null);
        $idTarea = $this->crearTarea($prof['profesor'], $victima['estudiante']);

        $this->sesionDe($atacante['usuario'])
            ->postJson("/dashboard-estudiante/tareas/{$idTarea}/entregar", ['comentario_estudiante' => 'no es mia'])
            ->assertNotFound();

        $this->assertDatabaseCount('entregas_tareas', 0);
        $this->assertSame('pendiente', DB::table('tareas')->where('id_tarea', $idTarea)->value('estado'));
    }

    public function test_estudiante_de_otro_elenco_no_puede_entregar_tarea_grupal(): void
    {
        $prof = $this->crearProfesor();
        $elencoA = $this->crearElenco('A');
        $elencoB = $this->crearElenco('B');
        $this->crearEstudiante($prof['profesor'], $elencoA);
        $intruso = $this->crearEstudiante(null, $elencoB);
        $idTarea = $this->crearTarea($prof['profesor'], null, $elencoA);

        $this->sesionDe($intruso['usuario'])
            ->postJson("/dashboard-estudiante/tareas/{$idTarea}/entregar", ['comentario_estudiante' => 'x'])
            ->assertNotFound();
    }

    public function test_estudiante_del_elenco_si_puede_entregar_tarea_grupal(): void
    {
        $prof = $this->crearProfesor();
        $elenco = $this->crearElenco();
        $alumno = $this->crearEstudiante($prof['profesor'], $elenco);
        $idTarea = $this->crearTarea($prof['profesor'], null, $elenco);

        $this->sesionDe($alumno['usuario'])
            ->post("/dashboard-estudiante/tareas/{$idTarea}/entregar", ['comentario_estudiante' => 'listo', 'archivo' => $this->audioWav()], ['Accept' => 'application/json'])
            ->assertOk();

        $entrega = DB::table('entregas_tareas')->first();
        $this->assertSame($alumno['estudiante'], (int) $entrega->id_estudiante);
        $this->assertSame('audio/x-wav', $entrega->archivo_mime === 'audio/wav' ? 'audio/x-wav' : $entrega->archivo_mime);
        Storage::disk('local')->assertExists($entrega->archivo_path);
    }

    public function test_no_se_puede_reentregar_una_tarea_calificada(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $idTarea = $this->crearTarea($prof['profesor'], $alumno['estudiante']);
        DB::table('entregas_tareas')->insert([
            'id_tarea' => $idTarea, 'id_estudiante' => $alumno['estudiante'], 'estado' => 'calificada',
            'calificacion' => 90, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->sesionDe($alumno['usuario'])
            ->postJson("/dashboard-estudiante/tareas/{$idTarea}/entregar", ['comentario_estudiante' => 'otra vez'])
            ->assertUnprocessable();

        $this->assertSame('calificada', DB::table('entregas_tareas')->value('estado'));
    }

    public function test_profesor_no_puede_calificar_ni_ver_entregas_de_otro_profesor(): void
    {
        $profA = $this->crearProfesor();
        $profB = $this->crearProfesor();
        $alumno = $this->crearEstudiante($profA['profesor']);
        $idTarea = $this->crearTarea($profA['profesor'], $alumno['estudiante']);
        $idEntrega = DB::table('entregas_tareas')->insertGetId([
            'id_tarea' => $idTarea, 'id_estudiante' => $alumno['estudiante'], 'estado' => 'entregada', 'created_at' => now(), 'updated_at' => now(),
        ], 'id_entrega');

        $this->sesionDe($profB['usuario'])->postJson("/dashboard-profesor/entregas/{$idEntrega}/calificar", ['calificacion' => 100])->assertNotFound();
        $this->sesionDe($profB['usuario'])->getJson("/dashboard-profesor/tareas/{$idTarea}/entregas")->assertNotFound();
        $this->sesionDe($profB['usuario'])->deleteJson("/dashboard-profesor/tareas/{$idTarea}")->assertNotFound();
        $this->sesionDe($profB['usuario'])->getJson("/dashboard-profesor/alumnos/{$alumno['estudiante']}/progreso")->assertNotFound();
        $this->assertSame('entregada', DB::table('entregas_tareas')->value('estado'));
    }

    public function test_profesor_no_puede_asignar_tarea_a_estudiante_ajeno(): void
    {
        $profA = $this->crearProfesor();
        $profB = $this->crearProfesor();
        $alumnoA = $this->crearEstudiante($profA['profesor']);

        $this->sesionDe($profB['usuario'])
            ->postJson('/dashboard-profesor/tareas', ['titulo' => 'X', 'id_estudiante' => $alumnoA['estudiante']])
            ->assertUnprocessable();

        $this->assertDatabaseCount('tareas', 0);
    }

    public function test_grabacion_privada_solo_para_duenio_profesor_y_padre_vinculado(): void
    {
        $prof = $this->crearProfesor();
        $otroProf = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $otroAlumno = $this->crearEstudiante($prof['profesor']);
        $padre = $this->crearPadre([$alumno['estudiante']]);
        $otroPadre = $this->crearPadre([$otroAlumno['estudiante']]);
        $idTarea = $this->crearTarea($prof['profesor'], $alumno['estudiante']);

        $this->sesionDe($alumno['usuario'])
            ->post("/dashboard-estudiante/tareas/{$idTarea}/entregar", ['archivo' => $this->audioWav()], ['Accept' => 'application/json'])
            ->assertOk();
        $idEntrega = (int) DB::table('entregas_tareas')->value('id_entrega');
        $url = "/archivos/entregas/{$idEntrega}";

        $this->sesionDe($alumno['usuario'])->get($url)->assertOk();
        $this->sesionDe($prof['usuario'])->get($url)->assertOk();
        $this->sesionDe($padre['usuario'])->get($url)->assertOk();

        $this->sesionDe($otroAlumno['usuario'])->get($url)->assertNotFound();
        $this->sesionDe($otroProf['usuario'])->get($url)->assertNotFound();
        $this->sesionDe($otroPadre['usuario'])->get($url)->assertNotFound();
        $this->flushSession();
        $this->get($url)->assertRedirect('/login');

        // El archivo no esta en el disco publico.
        $this->assertStringNotContainsString('public', (string) DB::table('entregas_tareas')->value('archivo_path'));
    }

    public function test_archivo_que_no_es_audio_se_rechaza_aunque_diga_mp3(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $idTarea = $this->crearTarea($prof['profesor'], $alumno['estudiante']);
        $falso = UploadedFile::fake()->createWithContent('cancion.mp3', '<?php echo "hola"; ?>');

        $this->sesionDe($alumno['usuario'])
            ->post("/dashboard-estudiante/tareas/{$idTarea}/entregar", ['archivo' => $falso], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('entregas_tareas', 0);
    }

    public function test_padre_no_ve_reporte_de_estudiante_no_vinculado(): void
    {
        $hijo = $this->crearEstudiante();
        $ajeno = $this->crearEstudiante();
        $padre = $this->crearPadre([$hijo['estudiante']]);

        $this->sesionDe($padre['usuario'])->get("/dashboard-padre/hijos/{$hijo['estudiante']}/reporte")->assertOk();
        $this->sesionDe($padre['usuario'])->get("/dashboard-padre/hijos/{$ajeno['estudiante']}/reporte")->assertNotFound();
    }
}
