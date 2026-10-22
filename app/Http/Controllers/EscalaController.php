<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\EscalaService;
use App\Services\TareaService;
use App\Services\TecnicaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Biblioteca técnica de escalas.
 *  - Estudiante: solo la escala de una tarea que le asignaron (práctica guiada y entrega).
 *  - Profesor: consulta la biblioteca, prueba una escala y la asigna desde "Nueva tarea".
 *  - Director: agrega, edita y desactiva escalas.
 */
class EscalaController extends Controller
{
    public function __construct(private readonly EscalaService $escalas) {}

    // ------------------------------------------------------------ estudiante

    public function practicar(Request $request, int $idTarea, TareaService $tareas)
    {
        $estudiante = $tareas->estudianteDeUsuario((int) session('usuario_id'));
        abort_unless($estudiante, 404);
        $info = $tareas->ejercicioEscalaParaEstudiante($estudiante, $idTarea, $request->query('instrumento'));

        return view('escala-practica', [
            'modo' => 'tarea',
            'tarea' => $info['tarea'],
            'entrega' => $info['entrega'],
            'ejercicio' => $info['ejercicio'],
            'volverUrl' => url('/dashboard-estudiante#tareas'),
            'entregarUrl' => url('/dashboard-estudiante/tareas/'.$idTarea.'/escala'),
            'instrumentos' => $info['instrumentos'],
            'cambiarUrl' => url('/dashboard-estudiante/tareas/'.$idTarea.'/escala'),
        ]);
    }

    public function entregar(Request $request, int $idTarea, TareaService $tareas)
    {
        $datos = $request->validate([
            'resultado' => ['required', 'array'],
            'resultado.notas' => ['required', 'array', 'max:400'],
            'resultado.notas.*.cents' => ['required', 'numeric'],
            'resultado.notas.*.ms' => ['required', 'integer', 'min:0'],
            'resultado.duracion_s' => ['required', 'integer', 'min:0'],
            'resultado.bpm' => ['nullable', 'integer', 'min:0', 'max:300'],
            'comentario' => ['nullable', 'string', 'max:1000'],
            'instrumento' => ['nullable', 'string', Rule::in(array_keys(EscalaService::INSTRUMENTOS))],
        ]);
        $estudiante = $tareas->estudianteDeUsuario((int) session('usuario_id'));
        abort_unless($estudiante, 404);

        $r = $tareas->entregarEscala($estudiante, $idTarea, $datos['resultado'], $datos['comentario'] ?? null, $datos['instrumento'] ?? null);

        return response()->json(['message' => 'Escala entregada a tu profesor.', 'resumen' => $r['texto'], 'resultado' => $r['resultado']]);
    }

    // ------------------------------------------------------------ profesor y director

    /** Vista previa: el profesor o el director tocan la escala con la configuración elegida, sin entregar. */
    public function probar(Request $request, int $id)
    {
        $escala = $this->escalas->escala($id, soloActivas: ! $this->esDirector());
        abort_unless($escala, 404);

        $entrada = $request->query('config');
        $config = $this->escalas->normalizarConfig(
            $entrada ?: ['octavas' => 1, 'tolerancia' => EscalaService::TOLERANCIA_POR_DIFICULTAD[$escala->dificultad] ?? 10],
            $escala
        );

        return view('escala-practica', [
            'modo' => 'prueba',
            'tarea' => null,
            'entrega' => null,
            'ejercicio' => $this->escalas->ejercicio($escala, $config),
            'volverUrl' => $this->esDirector() ? url('/dashboard-admin/biblioteca') : url('/dashboard-profesor#biblioteca'),
            'entregarUrl' => null,
        ]);
    }

    /** Vista previa de un ejercicio técnico o de un método digitalizado. */
    public function probarTecnica(Request $request, int $id, TecnicaService $tecnica)
    {
        $fila = $tecnica->ejercicio($id, soloActivos: ! $this->esDirector());
        abort_unless($fila, 404);
        $config = $tecnica->normalizarConfig($request->query('config') ?: [], $fila);

        return view('escala-practica', [
            'modo' => 'prueba',
            'tarea' => null,
            'entrega' => null,
            'ejercicio' => $tecnica->ejercicioPractica($fila, $config),
            'volverUrl' => $this->esDirector() ? url('/dashboard-admin/digitalizador') : url('/dashboard-profesor#biblioteca'),
            'entregarUrl' => null,
        ]);
    }

    /** Resumen (texto y notas) para la vista previa del formulario de tarea. */
    public function vistaPrevia(Request $request, int $id)
    {
        $escala = $this->escalas->escala($id);
        abort_unless($escala, 404);
        $config = $this->escalas->normalizarConfig($request->input('config_escala'), $escala);

        return response()->json([
            'titulo' => $this->escalas->titulo($escala, $config),
            'resumen' => $this->escalas->describir($escala, $config),
            'notas' => array_column($this->escalas->notas($escala->datos, $config['octavas'], $config['direccion']), 'etiqueta'),
        ]);
    }

    // ------------------------------------------------------------ director

    public function biblioteca(Request $request)
    {
        $instrumento = array_key_exists((string) $request->query('instrumento'), EscalaService::INSTRUMENTOS) ? (string) $request->query('instrumento') : 'violin';
        $escalas = $this->escalas->catalogo($instrumento, incluirInactivas: true);
        $usos = DB::table('tareas')->whereNotNull('id_biblioteca')->groupBy('id_biblioteca')
            ->selectRaw('id_biblioteca, count(*) as total')->pluck('total', 'id_biblioteca');

        return view('admin.biblioteca', [
            'escalas' => array_map(fn ($e) => $e + ['usos' => (int) ($usos[$e['id']] ?? 0)], $escalas),
            'modos' => collect(EscalaService::MODOS)->map(fn ($m) => $m['nombre'])->all(),
            'dificultades' => EscalaService::DIFICULTADES,
            'instrumento' => $instrumento,
            'instrumentos' => collect(EscalaService::INSTRUMENTOS)->map(fn ($i) => $i['nombre'])->all(),
        ]);
    }

    public function guardar(Request $request, AuditLogger $audit, ?int $id = null)
    {
        $valid = $request->validate([
            'instrumento' => ['nullable', Rule::in(array_keys(EscalaService::INSTRUMENTOS))],
            'tonica' => ['required', Rule::in(['C', 'D', 'E', 'F', 'G', 'A', 'B'])],
            'alteracion' => ['required', 'integer', Rule::in([-1, 0, 1])],
            'modo' => ['required', Rule::in(array_keys(EscalaService::MODOS))],
            'dificultad' => ['required', Rule::in(array_keys(EscalaService::DIFICULTADES))],
            'nombre' => ['nullable', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'inicio' => ['required', 'array'],
            'inicio.*' => ['nullable', 'integer', 'min:1', 'max:7'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [], ['inicio' => 'octava inicial', 'tonica' => 'tónica']);

        // Octavas disponibles: deben ser consecutivas desde 1 (1; 1 y 2; 1, 2 y 3...).
        $inicio = [];
        foreach ([1, 2, 3, 4] as $n) {
            $v = $valid['inicio'][$n] ?? $valid['inicio'][(string) $n] ?? null;
            if ($v === null || $v === '') {
                break;
            }
            $inicio[(string) $n] = (int) $v;
        }
        if (! $inicio) {
            throw ValidationException::withMessages(['inicio' => 'Indica al menos la octava inicial para tocarla en una octava.']);
        }

        $previa = $id ? DB::table('biblioteca_ejercicios')->where('id_biblioteca', $id)->where('categoria', 'escala')->first() : null;
        abort_if($id && ! $previa, 404);
        $instrumento = $previa->instrumento ?? ($valid['instrumento'] ?? 'violin');

        $datos = [
            'tonica' => $valid['tonica'],
            'alteracion' => (int) $valid['alteracion'],
            'modo' => $valid['modo'],
            'inicio' => $inicio,
            'octavas_max' => count($inicio),
            'instrumento' => $instrumento,
        ];

        // La cuerda y el dedo en primera posición se calculan solos (EscalaService::digitacion).

        if ($error = $this->escalas->validarRango($datos)) {
            throw ValidationException::withMessages(['inicio' => $error]);
        }

        $nombre = trim((string) ($valid['nombre'] ?? ''))
            ?: EscalaService::nombreTonica($datos['tonica'], $datos['alteracion']).' '.EscalaService::MODOS[$datos['modo']]['nombre'];

        $duplicada = DB::table('biblioteca_ejercicios')->where('categoria', 'escala')->where('instrumento', $instrumento)
            ->where('nombre', $nombre)->when($id, fn ($q) => $q->where('id_biblioteca', '!=', $id))->exists();
        if ($duplicada) {
            throw ValidationException::withMessages(['nombre' => "Ya existe la escala \"{$nombre}\"."]);
        }

        $fila = [
            'nombre' => $nombre,
            'dificultad' => $valid['dificultad'],
            'descripcion' => $valid['descripcion'] ?? null,
            'datos' => json_encode($datos, JSON_UNESCAPED_UNICODE),
            'orden' => (int) ($valid['orden'] ?? 0),
            'updated_at' => now(),
        ];

        if ($id) {
            DB::table('biblioteca_ejercicios')->where('id_biblioteca', $id)->update($fila);
        } else {
            $id = DB::table('biblioteca_ejercicios')->insertGetId($fila + [
                'categoria' => 'escala',
                'instrumento' => $instrumento,
                'activo' => true,
                'creado_por' => (int) session('usuario_id'),
                'created_at' => now(),
            ], 'id_biblioteca');
        }

        $audit->registrar('biblioteca_escala_guardada', (int) session('usuario_id'), ['id_biblioteca' => $id, 'nombre' => $nombre]);

        return redirect('/dashboard-admin/biblioteca?instrumento='.$instrumento.'#escala-'.$id)->with('success', "Escala \"{$nombre}\" guardada.");
    }

    public function estado(Request $request, int $id, AuditLogger $audit)
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        $afectadas = DB::table('biblioteca_ejercicios')->where('id_biblioteca', $id)->where('categoria', 'escala')
            ->update(['activo' => (bool) $datos['activo'], 'updated_at' => now()]);
        abort_unless($afectadas, 404);
        $audit->registrar('biblioteca_escala_estado', (int) session('usuario_id'), ['id_biblioteca' => $id, 'activo' => (bool) $datos['activo']]);

        return back()->with('success', $datos['activo']
            ? 'La escala vuelve a estar disponible para los profesores.'
            : 'Escala desactivada: los profesores ya no la ven al crear tareas. Las tareas ya asignadas siguen funcionando.');
    }

    private function esDirector(): bool
    {
        return in_array(strtolower((string) session('rol')), ['director', 'admin'], true);
    }
}
