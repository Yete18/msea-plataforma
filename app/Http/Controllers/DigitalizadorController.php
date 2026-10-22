<?php

namespace App\Http\Controllers;

use App\Jobs\DigitalizarPartitura;
use App\Services\ArchivoPrivadoService;
use App\Services\AuditLogger;
use App\Services\DigitalizadorService;
use App\Services\EscalaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Digitalizador de métodos en PDF (solo el director). */
class DigitalizadorController extends Controller
{
    public function __construct(
        private readonly DigitalizadorService $digitalizador,
        private readonly ArchivoPrivadoService $archivos,
    ) {}

    public function index()
    {
        return view('admin.digitalizador', [
            'digitalizaciones' => DB::table('digitalizaciones')->orderByDesc('id_digitalizacion')->limit(50)->get()
                ->map(function ($d) {
                    $d->paginas_lista = json_decode($d->paginas, true) ?: [];
                    $d->publicados = DB::table('digitalizacion_paginas')->where('id_digitalizacion', $d->id_digitalizacion)->pluck('ejercicios')
                        ->sum(fn ($j) => collect(json_decode((string) $j, true) ?: [])->whereNotNull('publicado_id')->count());

                    return $d;
                }),
            'motor' => $this->digitalizador->motorDisponible(),
            'instrumentos' => collect(EscalaService::INSTRUMENTOS)->map(fn ($i) => $i['nombre'])->all(),
            'maxMb' => (int) (config('msea.digitalizador.pdf_max_kb') / 1024),
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $datos = $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:'.(int) config('msea.digitalizador.pdf_max_kb')],
            'titulo' => ['required', 'string', 'max:150'],
            'metodo' => ['nullable', 'string', 'max:150'],
            'instrumento' => ['required', Rule::in(array_keys(EscalaService::INSTRUMENTOS))],
            'paginas' => ['required', 'string', 'max:200'],
            'derechos' => ['accepted'],
        ], [
            'derechos.accepted' => 'Confirma que el método es de dominio público o que la fundación tiene permiso para usarlo.',
        ], ['pdf' => 'PDF', 'paginas' => 'páginas']);

        $dig = $this->digitalizador->crear($request->file('pdf'), $datos, (int) session('usuario_id'));
        $audit->registrar('digitalizacion_creada', (int) session('usuario_id'), ['id' => $dig->id_digitalizacion, 'paginas' => $dig->paginas]);

        if (config('msea.digitalizador.usar_cola')) {
            DigitalizarPartitura::dispatch((int) $dig->id_digitalizacion);
            $mensaje = 'PDF recibido. Las páginas se están reconociendo; recarga en unos minutos.';
        } else {
            @set_time_limit(0);
            $this->digitalizador->procesar((int) $dig->id_digitalizacion);
            $mensaje = 'Listo. Revisa cada ejercicio, corrige lo que haga falta y publícalo.';
        }

        return redirect('/dashboard-admin/digitalizador/'.$dig->id_digitalizacion)->with('success', $mensaje);
    }

    public function show(int $id)
    {
        $dig = $this->digitalizacion($id);
        $paginas = DB::table('digitalizacion_paginas')->where('id_digitalizacion', $id)->orderBy('pagina')->get()
            ->map(function ($p) {
                $p->lista = $this->digitalizador->ejerciciosDePagina($p);

                return $p;
            });

        return view('admin.digitalizacion', [
            'dig' => $dig,
            'paginas' => $paginas,
            'motor' => $this->digitalizador->motorDisponible(),
            'dificultades' => EscalaService::DIFICULTADES,
            'compases' => EscalaService::COMPASES,
            'instrumento' => EscalaService::INSTRUMENTOS[$dig->instrumento] ?? EscalaService::INSTRUMENTOS['violin'],
        ]);
    }

    public function pdf(Request $request, int $id)
    {
        $dig = $this->digitalizacion($id);

        return $this->archivos->respuesta($dig->archivo_path, $dig->archivo_nombre, 'application/pdf', $request->boolean('descargar'));
    }

    public function imagen(int $id, int $pagina)
    {
        $p = DB::table('digitalizacion_paginas')->where('id_digitalizacion', $id)->where('pagina', $pagina)->first();
        abort_unless($p && $p->imagen_path, 404);

        return $this->archivos->respuesta($p->imagen_path, 'pagina-'.$pagina.'.png', 'image/png');
    }

    public function guardarEjercicio(Request $request, int $id, int $idPagina, int $indice, AuditLogger $audit)
    {
        $dig = $this->digitalizacion($id);
        $pagina = DB::table('digitalizacion_paginas')->where('id_pagina', $idPagina)->where('id_digitalizacion', $id)->first();
        abort_unless($pagina, 404);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'compas' => ['required', Rule::in(EscalaService::COMPASES)],
            'fifths' => ['required', 'integer', 'between:-7,7'],
            'dificultad' => ['required', Rule::in(array_keys(EscalaService::DIFICULTADES))],
            'texto' => ['required', 'string', 'max:12000'],
            'accion' => ['required', Rule::in(['guardar', 'publicar'])],
        ]);

        $publicado = $this->digitalizador->guardarEjercicio($dig, $pagina, $indice, $datos, $datos['accion'] === 'publicar', (int) session('usuario_id'));
        if ($publicado) {
            $audit->registrar('ejercicio_metodo_publicado', (int) session('usuario_id'), ['id_biblioteca' => $publicado, 'id_digitalizacion' => $id]);
        }

        return redirect('/dashboard-admin/digitalizador/'.$id.'#ej-'.$idPagina.'-'.$indice)->with('success', $publicado
            ? 'Publicado en la biblioteca: los profesores ya pueden asignarlo.'
            : 'Corrección guardada.');
    }

    public function agregarEjercicio(int $id, int $idPagina)
    {
        $dig = $this->digitalizacion($id);
        $pagina = DB::table('digitalizacion_paginas')->where('id_pagina', $idPagina)->where('id_digitalizacion', $id)->first();
        abort_unless($pagina, 404);
        $this->digitalizador->agregarEjercicio($dig, $pagina);

        return redirect('/dashboard-admin/digitalizador/'.$id.'#pagina-'.$pagina->pagina)->with('success', 'Ejercicio vacío agregado: escribe sus notas.');
    }

    public function reprocesar(int $id)
    {
        $this->digitalizacion($id);
        @set_time_limit(0);
        if (config('msea.digitalizador.usar_cola')) {
            DigitalizarPartitura::dispatch($id);
        } else {
            $this->digitalizador->procesar($id);
        }

        return back()->with('success', 'Se volvieron a reconocer las páginas con error.');
    }

    public function destroy(int $id, AuditLogger $audit)
    {
        $dig = $this->digitalizacion($id);
        foreach (DB::table('digitalizacion_paginas')->where('id_digitalizacion', $id)->get() as $p) {
            $this->archivos->eliminar($p->imagen_path);
            $this->archivos->eliminar($p->musicxml_path);
        }
        $this->archivos->eliminar($dig->archivo_path);
        DB::table('digitalizaciones')->where('id_digitalizacion', $id)->delete();
        $audit->registrar('digitalizacion_eliminada', (int) session('usuario_id'), ['id' => $id]);

        return redirect('/dashboard-admin/digitalizador')->with('success', 'Digitalización eliminada. Los ejercicios ya publicados siguen en la biblioteca.');
    }

    private function digitalizacion(int $id): object
    {
        $dig = DB::table('digitalizaciones')->where('id_digitalizacion', $id)->first();
        abort_unless($dig, 404);

        return $dig;
    }
}
