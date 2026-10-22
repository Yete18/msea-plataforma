<?php

namespace Tests\Feature;

use App\Services\EscalaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/** Biblioteca técnica: escalas con frecuencias exactas, tareas de escala, práctica guiada y gestión del director. */
class BibliotecaEscalasTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    private EscalaService $escalas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->escalas = app(EscalaService::class);
    }

    private function laMayor(): object
    {
        return $this->escalas->escala((int) DB::table('biblioteca_ejercicios')->where('instrumento', 'violin')->where('nombre', 'La mayor')->value('id_biblioteca'));
    }

    private function config(array $extra = []): array
    {
        return array_merge([
            'octavas' => 1,
            'direccion' => 'subir_bajar',
            'ritmo' => ['modo' => 'uniforme', 'figura' => 'negra', 'repeticiones' => 4],
            'bpm' => 60,
            'compas' => '4/4',
            'tolerancia' => 15,
        ], $extra);
    }

    // ------------------------------------------------------------ teoría

    public function test_la_mayor_una_octava_con_frecuencias_exactas_a_440(): void
    {
        $notas = $this->escalas->notas($this->laMayor()->datos, 1, 'subir');

        $this->assertSame(['La4', 'Si4', 'Do♯5', 'Re5', 'Mi5', 'Fa♯5', 'Sol♯5', 'La5'], array_column($notas, 'etiqueta'));
        $this->assertSame([440.0, 493.88, 554.37, 587.33, 659.26, 739.99, 830.61, 880.0], array_column($notas, 'frecuencia'));
        // Cuerda La al aire y Mi con el tercer dedo
        $this->assertSame(['La', 0], [$notas[0]['cuerda'], $notas[0]['dedo']]);
        $this->assertSame(['Mi', 3], [$notas[7]['cuerda'], $notas[7]['dedo']]);
    }

    public function test_sube_y_baja_no_repite_la_nota_mas_aguda_y_dos_o_tres_octavas_empiezan_en_la3(): void
    {
        $escala = $this->laMayor();
        $una = $this->escalas->notas($escala->datos, 1, 'subir_bajar');
        $this->assertCount(15, $una);
        $this->assertSame('La4', end($una)['etiqueta']);

        $dos = $this->escalas->notas($escala->datos, 2, 'subir');
        $this->assertSame('La3', $dos[0]['etiqueta']);
        $this->assertSame(220.0, $dos[0]['frecuencia']);
        $this->assertSame('La5', end($dos)['etiqueta']);

        $tres = $this->escalas->notas($escala->datos, 3, 'subir');
        $this->assertSame(1760.0, end($tres)['frecuencia']);
        $this->assertArrayNotHasKey('cuerda', $tres[0], 'En 3 octavas no se inventa digitación.');
    }

    public function test_ortografia_y_armadura_de_otras_tonalidades(): void
    {
        $siBemol = ['tonica' => 'B', 'alteracion' => -1, 'modo' => 'mayor', 'inicio' => ['1' => 4]];
        $this->assertSame(['Si♭4', 'Do5', 'Re5', 'Mi♭5', 'Fa5', 'Sol5', 'La5', 'Si♭5'], array_column($this->escalas->notas($siBemol, 1, 'subir'), 'etiqueta'));
        $this->assertSame(['tipo' => 'bemoles', 'cantidad' => 2], array_intersect_key($this->escalas->armadura($siBemol), ['tipo' => 1, 'cantidad' => 1]));

        $laMayor = $this->escalas->armadura($this->laMayor()->datos);
        $this->assertSame(['F', 'C', 'G'], $laMayor['letras']);

        $reArmonica = ['tonica' => 'D', 'alteracion' => 0, 'modo' => 'menor_armonica', 'inicio' => ['1' => 4]];
        $etiquetas = array_column($this->escalas->notas($reArmonica, 1, 'subir'), 'etiqueta');
        $this->assertSame('Do♯5', $etiquetas[6]);
        $this->assertSame(1, $this->escalas->armadura($reArmonica)['cantidad']);
    }

    public function test_biblioteca_trae_12_mayores_y_12_menores_en_tres_formas_dentro_del_violin(): void
    {
        $filas = DB::table('biblioteca_ejercicios')->where('categoria', 'escala')->where('instrumento', 'violin')->get();
        $this->assertCount(48, $filas);
        $this->assertSame(12, $filas->filter(fn ($f) => str_ends_with($f->nombre, ' mayor'))->count());
        foreach (['menor natural', 'menor armónica', 'menor melódica'] as $modo) {
            $this->assertSame(12, $filas->filter(fn ($f) => str_ends_with($f->nombre, $modo))->count(), $modo);
        }
        foreach (DB::table('biblioteca_ejercicios')->where('categoria', 'escala')->get() as $f) {
            $this->assertNull($this->escalas->validarRango($this->escalas->decodificar($f->datos) + ['instrumento' => $f->instrumento]), $f->instrumento.': '.$f->nombre);
        }
        foreach (['viola', 'violonchelo', 'contrabajo'] as $instrumento) {
            $this->assertSame(48, DB::table('biblioteca_ejercicios')->where('categoria', 'escala')->where('instrumento', $instrumento)->count(), $instrumento);
        }

        $sol = $this->escalas->escala((int) DB::table('biblioteca_ejercicios')->where('instrumento', 'violin')->where('nombre', 'Sol♯ menor armónica')->value('id_biblioteca'));
        $this->assertSame('Fa𝄪4', $this->escalas->notas($sol->datos, 1, 'subir')[6]['etiqueta']);
        $this->assertSame(5, $this->escalas->armadura($sol->datos)['cantidad']);
    }

    public function test_digitacion_en_primera_posicion_se_calcula_sola(): void
    {
        $siBemol = $this->escalas->escala((int) DB::table('biblioteca_ejercicios')->where('instrumento', 'violin')->where('nombre', 'Si♭ mayor')->value('id_biblioteca'));
        $notas = $this->escalas->notas($siBemol->datos, 2, 'subir');
        $resumen = fn ($n) => $n['cuerda'].' '.$n['dedo'].($n['variante'] ? ' '.$n['variante'] : '');

        $this->assertSame('Sol 2 bajo', $resumen($notas[0]));      // Si♭3
        $this->assertSame('Re 0', $resumen($notas[2]));            // Re4 al aire
        $this->assertSame('La 4 bajo', $resumen($notas[10]));      // Mi♭5
        $this->assertSame('Mi 4 bajo', $resumen($notas[14]));      // Si♭5

        $la = $this->escalas->notas($this->laMayor()->datos, 2, 'subir');
        $this->assertSame('Sol 3 alto', $resumen($la[2]));         // Do♯4
        $this->assertArrayNotHasKey('cuerda', $this->escalas->notas($this->laMayor()->datos, 3, 'subir')[0]);
    }

    private function escalaDe(string $instrumento, string $nombre): object
    {
        return $this->escalas->escala((int) DB::table('biblioteca_ejercicios')->where('instrumento', $instrumento)->where('nombre', $nombre)->value('id_biblioteca'));
    }

    public function test_registro_y_digitacion_de_viola_violonchelo_y_contrabajo(): void
    {
        $resumen = fn ($n) => $n['etiqueta'].' '.$n['cuerda'].' '.$n['dedo'].($n['variante'] ? ' '.$n['variante'] : '');

        $viola = $this->escalas->notas($this->escalaDe('viola', 'Do mayor')->datos, 1, 'subir');
        $this->assertSame(['Do3 Do 0', 'Re3 Do 1', 'Mi3 Do 2 alto', 'Fa3 Do 3', 'Sol3 Sol 0'], array_map($resumen, array_slice($viola, 0, 5)));
        $this->assertSame(130.81, $viola[0]['frecuencia']);

        $chelo = $this->escalas->notas($this->escalaDe('violonchelo', 'Do mayor')->datos, 1, 'subir');
        $this->assertSame(['Do2 Do 0', 'Re2 Do 1', 'Mi2 Do 3', 'Fa2 Do 4', 'Sol2 Sol 0'], array_map($resumen, array_slice($chelo, 0, 5)));
        $this->assertSame(65.41, $chelo[0]['frecuencia']);

        $bajo = $this->escalas->notas($this->escalaDe('contrabajo', 'Sol mayor')->datos, 1, 'subir');
        $this->assertSame(['Sol1 Mi 2', 'La1 La 0', 'Si1 La 1', 'Do2 La 2', 'Re2 Re 0', 'Mi2 Re 1', 'Fa♯2 Re 4', 'Sol2 Sol 0'], array_map($resumen, $bajo));
        $this->assertSame(49.0, $bajo[0]['frecuencia']);

        // Registro: el contrabajo no hace Re mayor en 3 octavas (pasaría Sol4).
        $this->assertSame(2, $this->escalaDe('contrabajo', 'Re mayor')->datos['octavas_max']);
        $this->assertStringContainsString('de la viola', (string) $this->escalas->validarRango(['tonica' => 'A', 'alteracion' => 0, 'modo' => 'mayor', 'inicio' => ['1' => 2], 'instrumento' => 'viola']));
    }

    public function test_cada_estudiante_toca_la_tarea_en_su_instrumento_y_clave(): void
    {
        $prof = $this->crearProfesor();
        $elenco = $this->crearElenco();
        $violista = $this->crearEstudiante($prof['profesor'], $elenco);
        $bajista = $this->crearEstudiante($prof['profesor'], $elenco);
        $this->asignarInstrumento($violista['usuario'], 'Viola');
        $this->asignarInstrumento($bajista['usuario'], 'Contrabajo');
        $idTarea = $this->crearTarea($prof['profesor'], null, $elenco);
        DB::table('tareas')->where('id_tarea', $idTarea)->update([
            'id_biblioteca' => $this->escalaDe('violin', 'Re mayor')->id_biblioteca,
            'config_escala' => json_encode($this->config(['octavas' => 3])),
        ]);

        $vista = $this->sesionDe($violista['usuario'])->get("/dashboard-estudiante/tareas/{$idTarea}/escala")->assertOk()->assertSee('Clave de Do');
        $ej = $vista->viewData('ejercicio');
        $this->assertSame('viola', $ej['instrumento']['clave']);
        $this->assertSame('do', $ej['instrumento']['clef']);
        $this->assertSame('Re3', $ej['notas'][0]['etiqueta']);

        $vista = $this->sesionDe($bajista['usuario'])->get("/dashboard-estudiante/tareas/{$idTarea}/escala")->assertOk()->assertSee('Clave de Fa');
        $ej = $vista->viewData('ejercicio');
        $this->assertSame('fa', $ej['instrumento']['clef']);
        $this->assertSame(2, $ej['config']['octavas'], 'Se ajusta a las octavas que tiene el contrabajo');
        $this->assertSame(3, $ej['octavas_pedidas']);
        $this->assertSame('Re2', $ej['notas'][0]['etiqueta']);
        $this->assertSame(73.42, $ej['notas'][0]['frecuencia']);
        $this->assertSame(3, $ej['notas'][0]['octava_escrita'], 'El contrabajo se escribe una octava más aguda');

        // Entrega con el número de notas del contrabajo (2 octavas, sube y baja = 29).
        $this->sesionDe($bajista['usuario'])->postJson("/dashboard-estudiante/tareas/{$idTarea}/escala", [
            'resultado' => ['notas' => array_fill(0, 29, ['cents' => 2, 'ms' => 900]), 'duracion_s' => 60],
            'instrumento' => 'contrabajo',
        ])->assertOk();
        $this->assertSame('contrabajo', json_decode(DB::table('entregas_tareas')->where('id_estudiante', $bajista['estudiante'])->value('resultado_escala'), true)['instrumento']);
    }

    private function asignarInstrumento(int $idUsuario, string $nombre): void
    {
        $id = DB::table('instrumentos')->where('nombre', $nombre)->value('id_instrumento')
            ?: DB::table('instrumentos')->insertGetId(['nombre' => $nombre], 'id_instrumento');
        DB::table('usuario_instrumento')->insert(['id_usuario' => $idUsuario, 'id_instrumento' => $id]);
    }

    public function test_ritmo_uniforme_y_patron_ciclico(): void
    {
        $uniforme = $this->escalas->ritmoPorNota(['modo' => 'uniforme', 'figura' => 'corchea', 'repeticiones' => 8], 3);
        $this->assertSame(array_fill(0, 8, 'corchea'), $uniforme[2]);

        $patron = $this->escalas->ritmoPorNota(['modo' => 'patron', 'celdas' => [['negra_puntillo', 'corchea'], ['blanca']]], 3);
        $this->assertSame([['negra_puntillo', 'corchea'], ['blanca'], ['negra_puntillo', 'corchea']], $patron);
    }

    public function test_configuracion_invalida_se_rechaza(): void
    {
        $escala = $this->laMayor();

        foreach ([
            ['octavas' => 4],
            ['bpm' => 10],
            ['compas' => '7/8'],
            ['tolerancia' => 3],
            ['ritmo' => ['modo' => 'uniforme', 'figura' => 'fusa', 'repeticiones' => 4]],
            ['ritmo' => ['modo' => 'patron', 'celdas' => [['redonda', 'redonda', 'redonda', 'redonda', 'redonda']]]],
        ] as $malo) {
            try {
                $this->escalas->normalizarConfig($this->config($malo), $escala);
                $this->fail('Debió rechazar: '.json_encode($malo));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ------------------------------------------------------------ profesor

    public function test_profesor_asigna_escala_con_ritmo_propio_y_titulo_automatico(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $escala = $this->laMayor();

        $this->sesionDe($prof['usuario'])->postJson('/dashboard-profesor/tareas', [
            'id_estudiante' => $alumno['estudiante'],
            'id_biblioteca' => $escala->id_biblioteca,
            'config_escala' => json_encode($this->config([
                'ritmo' => ['modo' => 'patron', 'celdas' => [['negra_puntillo', 'corchea'], ['corchea', 'semicorchea', 'semicorchea']]],
                'tolerancia' => 10,
            ])),
        ])->assertSuccessful();

        $tarea = DB::table('tareas')->latest('id_tarea')->first();
        $this->assertSame('Escala de La mayor - 1 octava', $tarea->titulo);
        $this->assertSame((int) $escala->id_biblioteca, (int) $tarea->id_biblioteca);
        $config = json_decode($tarea->config_escala, true);
        $this->assertSame('patron', $config['ritmo']['modo']);
        $this->assertSame(10, $config['tolerancia']);
        $this->assertSame('Escala', DB::table('tarea_ejercicio as te')->join('ejercicios as e', 'e.id_ejercicio', '=', 'te.id_ejercicio')
            ->join('tipos_ejercicio as t', 't.id_tipo', '=', 'e.id_tipo')->where('te.id_tarea', $tarea->id_tarea)->value('t.nombre'));
    }

    public function test_escala_desactivada_o_config_invalida_no_se_asigna(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $escala = $this->laMayor();

        $this->sesionDe($prof['usuario'])->postJson('/dashboard-profesor/tareas', [
            'id_estudiante' => $alumno['estudiante'],
            'id_biblioteca' => $escala->id_biblioteca,
            'config_escala' => json_encode($this->config(['octavas' => 5])),
        ])->assertStatus(422)->assertJsonValidationErrors('config_escala.octavas');

        DB::table('biblioteca_ejercicios')->update(['activo' => false]);
        $this->sesionDe($prof['usuario'])->postJson('/dashboard-profesor/tareas', [
            'id_estudiante' => $alumno['estudiante'],
            'id_biblioteca' => $escala->id_biblioteca,
            'config_escala' => json_encode($this->config()),
        ])->assertStatus(422)->assertJsonValidationErrors('id_biblioteca');

        $this->assertDatabaseCount('tareas', 0);
    }

    public function test_tarea_libre_sigue_exigiendo_titulo(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);

        $this->sesionDe($prof['usuario'])->postJson('/dashboard-profesor/tareas', ['id_estudiante' => $alumno['estudiante']])
            ->assertStatus(422)->assertJsonValidationErrors('titulo');
    }

    public function test_biblioteca_visible_para_profesor_y_director_pero_no_para_estudiante_ni_padre(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $padre = $this->crearPadre([$alumno['estudiante']]);
        $director = $this->crearUsuario('director');
        $id = $this->laMayor()->id_biblioteca;

        $this->sesionDe($prof['usuario'])->get("/biblioteca/escalas/{$id}/probar")->assertOk()->assertSee('Vista previa')->assertSee('440');
        $this->sesionDe($prof['usuario'])->get('/dashboard-profesor')->assertOk()->assertSee('Biblioteca técnica');
        $this->sesionDe($director)->get('/dashboard-admin/biblioteca')->assertOk()->assertSee('La mayor');
        $this->sesionDe($alumno['usuario'])->get("/biblioteca/escalas/{$id}/probar")->assertRedirect('/login');
        $this->sesionDe($padre['usuario'])->get("/biblioteca/escalas/{$id}/probar")->assertRedirect('/login');
        $this->sesionDe($alumno['usuario'])->get('/dashboard-admin/biblioteca')->assertRedirect('/login');
    }

    // ------------------------------------------------------------ estudiante

    private function tareaEscala(int $idProfesor, int $idEstudiante, array $config = []): int
    {
        $idTarea = $this->crearTarea($idProfesor, $idEstudiante);
        DB::table('tareas')->where('id_tarea', $idTarea)->update([
            'id_biblioteca' => $this->laMayor()->id_biblioteca,
            'config_escala' => json_encode($this->config($config)),
        ]);

        return $idTarea;
    }

    public function test_estudiante_ve_su_escala_con_nota_objetivo_y_no_la_de_otro(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $otro = $this->crearEstudiante($prof['profesor']);
        $idTarea = $this->tareaEscala($prof['profesor'], $alumno['estudiante']);

        $this->sesionDe($alumno['usuario'])->get("/dashboard-estudiante/tareas/{$idTarea}/escala")
            ->assertOk()
            ->assertSee('Nota objetivo')
            ->assertSee('"frecuencia":440', false)
            ->assertSee('"frecuencia":554.37', false)
            ->assertSee('Metrónomo');

        $this->sesionDe($otro['usuario'])->get("/dashboard-estudiante/tareas/{$idTarea}/escala")->assertNotFound();

        $tareas = collect($this->sesionDe($alumno['usuario'])->get('/dashboard-estudiante')->viewData('tareasData'));
        $this->assertStringEndsWith("/dashboard-estudiante/tareas/{$idTarea}/escala", $tareas->firstWhere('id', $idTarea)['escalaUrl']);
    }

    public function test_entregar_escala_completa_guarda_resultado_no_verificado_y_avisa_al_profesor(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $idTarea = $this->tareaEscala($prof['profesor'], $alumno['estudiante']);
        $notas = array_map(fn ($i) => ['cents' => $i % 2 ? 4.5 : -3, 'ms' => 1200], range(1, 15));

        $this->sesionDe($alumno['usuario'])->postJson("/dashboard-estudiante/tareas/{$idTarea}/escala", [
            'resultado' => ['notas' => $notas, 'duracion_s' => 75],
            'comentario' => 'El Sol sostenido me costó',
        ])->assertOk()->assertJsonPath('resultado.completada', true);

        $entrega = DB::table('entregas_tareas')->where('id_tarea', $idTarea)->first();
        $this->assertSame('entregada', $entrega->estado);
        $resultado = json_decode($entrega->resultado_escala, true);
        $this->assertFalse($resultado['verificado']);
        $this->assertSame(15, $resultado['notas_afinadas']);
        $this->assertStringContainsString('El Sol sostenido me costó', $entrega->comentario_estudiante);
        $this->assertStringContainsString('1 min 15 s', $entrega->comentario_estudiante);
        $this->assertTrue(DB::table('notificaciones')->where('id_usuario', $prof['usuario'])->where('tipo', 'tarea')->exists());

        $entregas = $this->sesionDe($prof['usuario'])->getJson("/dashboard-profesor/tareas/{$idTarea}/entregas")->assertOk()->json('entregas');
        $this->assertSame(15, $entregas[0]['resultado_escala']['notas_total']);
    }

    public function test_escala_incompleta_no_se_entrega_y_valores_se_acotan(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $idTarea = $this->tareaEscala($prof['profesor'], $alumno['estudiante']);

        $this->sesionDe($alumno['usuario'])->postJson("/dashboard-estudiante/tareas/{$idTarea}/escala", [
            'resultado' => ['notas' => [['cents' => 1, 'ms' => 100]], 'duracion_s' => 5],
        ])->assertStatus(422)->assertJsonValidationErrors('resultado');
        $this->assertDatabaseCount('entregas_tareas', 0);

        $limpio = $this->escalas->normalizarResultado(['notas' => array_fill(0, 20, ['cents' => 9999, 'ms' => -5]), 'duracion_s' => 10], 15);
        $this->assertCount(15, $limpio['notas']);
        $this->assertSame(100.0, $limpio['notas'][0]['cents']);
        $this->assertSame(0, $limpio['notas'][0]['ms']);
    }

    // ------------------------------------------------------------ director

    public function test_director_agrega_edita_y_desactiva_escalas(): void
    {
        $director = $this->crearUsuario('director');

        $this->sesionDe($director)->post('/dashboard-admin/biblioteca/escalas', [
            'tonica' => 'D', 'alteracion' => 0, 'modo' => 'mayor', 'dificultad' => 'facil',
            'inicio' => [1 => 4, 2 => 4], 'nombre' => 'Re mayor desde Re4',
        ])->assertRedirect();

        $fila = DB::table('biblioteca_ejercicios')->where('instrumento', 'violin')->where('nombre', 'Re mayor desde Re4')->first();
        $this->assertNotNull($fila);
        $datos = json_decode($fila->datos, true);
        $this->assertSame(2, $datos['octavas_max']);
        $this->assertSame('Fa♯4', $this->escalas->notas($datos, 1, 'subir')[2]['etiqueta']);

        // Fuera del registro del violín (Re3 está por debajo de Sol3)
        $this->sesionDe($director)->post('/dashboard-admin/biblioteca/escalas', [
            'tonica' => 'D', 'alteracion' => 0, 'modo' => 'menor_natural', 'dificultad' => 'facil', 'inicio' => [1 => 3],
        ])->assertSessionHasErrors('inicio');

        // Duplicada (Re mayor ya viene en la biblioteca)
        $this->sesionDe($director)->post('/dashboard-admin/biblioteca/escalas', [
            'tonica' => 'D', 'alteracion' => 0, 'modo' => 'mayor', 'dificultad' => 'intermedio', 'inicio' => [1 => 4],
        ])->assertSessionHasErrors('nombre');

        // Editar La mayor: la cuerda y el dedo se siguen calculando en 1 y 2 octavas
        $la = $this->laMayor();
        $this->sesionDe($director)->post('/dashboard-admin/biblioteca/escalas/'.$la->id_biblioteca, [
            'tonica' => 'A', 'alteracion' => 0, 'modo' => 'mayor', 'dificultad' => 'facil',
            'inicio' => [1 => 4, 2 => 3, 3 => 3], 'descripcion' => 'Primera escala del método',
        ])->assertRedirect();
        $this->assertSame(['1', '2'], $this->escalas->resumen($this->laMayor())['tiene_digitacion']);

        $this->sesionDe($director)->post('/dashboard-admin/biblioteca/escalas/'.$fila->id_biblioteca.'/estado', ['activo' => 0])->assertRedirect();
        $this->assertNull($this->escalas->escala((int) $fila->id_biblioteca));
        $this->assertTrue(DB::table('auditoria')->where('accion', 'biblioteca_escala_estado')->exists());
    }
}
