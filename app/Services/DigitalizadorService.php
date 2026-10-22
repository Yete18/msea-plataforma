<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Digitalizador de métodos en PDF.
 *
 * 1. El director sube el PDF completo e indica qué páginas quiere (por ejemplo "4, 6").
 * 2. Cada página elegida pasa por el reconocimiento óptico de música (Audiveris, si está
 *    instalado), que produce MusicXML; se lee y se divide en ejercicios por las dobles barras.
 * 3. El director compara la imagen de la página con lo reconocido, corrige y publica: cada
 *    ejercicio aprobado queda en la biblioteca (categoría "metodo") para todos los profesores.
 *
 * El reconocimiento nunca es perfecto, por eso nada se publica sin revisión.
 * Sin Audiveris, las páginas quedan para escribir las notas a mano en la revisión.
 */
class DigitalizadorService
{
    public function __construct(
        private readonly ArchivoPrivadoService $archivos,
        private readonly MusicXmlLector $lector,
        private readonly TecnicaService $tecnica,
    ) {}

    /** "4, 6" o "4-6" → [4, 6] / [4, 5, 6]. */
    public function paginasDesdeTexto(string $texto, ?int $total): array
    {
        $paginas = [];
        foreach (preg_split('/[\s,;]+/', trim($texto), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $parte) {
            if (preg_match('/^(\d{1,4})\s*-\s*(\d{1,4})$/', $parte, $m)) {
                [$a, $b] = [(int) $m[1], (int) $m[2]];
                if ($a > $b || $b - $a > 200) {
                    throw ValidationException::withMessages(['paginas' => "El rango «{$parte}» no es válido."]);
                }
                $paginas = array_merge($paginas, range($a, $b));
            } elseif (ctype_digit($parte)) {
                $paginas[] = (int) $parte;
            } else {
                throw ValidationException::withMessages(['paginas' => "No entiendo «{$parte}». Escribe números separados por comas o rangos, por ejemplo 4, 6 o 4-6."]);
            }
        }
        $paginas = array_values(array_unique(array_filter($paginas, fn ($p) => $p >= 1)));
        sort($paginas);

        if (! $paginas) {
            throw ValidationException::withMessages(['paginas' => 'Indica al menos una página.']);
        }
        if (count($paginas) > (int) config('msea.digitalizador.max_paginas_por_vez', 20)) {
            throw ValidationException::withMessages(['paginas' => 'Puedes digitalizar hasta '.config('msea.digitalizador.max_paginas_por_vez', 20).' páginas por vez.']);
        }
        if ($total && max($paginas) > $total) {
            throw ValidationException::withMessages(['paginas' => "El PDF tiene {$total} páginas; la página ".max($paginas).' no existe.']);
        }

        return $paginas;
    }

    /** Cantidad de páginas del PDF (pdfinfo si existe; si no, conteo de objetos /Page). */
    public function contarPaginas(string $rutaAbsoluta): ?int
    {
        try {
            $r = Process::timeout(20)->run(['pdfinfo', $rutaAbsoluta]);
            if ($r->successful() && preg_match('/^Pages:\s+(\d+)/m', $r->output(), $m)) {
                return (int) $m[1];
            }
        } catch (\Throwable) {
            // pdfinfo no está instalado (habitual en Windows)
        }
        $contenido = (string) @file_get_contents($rutaAbsoluta);
        $n = preg_match_all('#/Type\s*/Page(?![s\w])#', $contenido);
        $cuenta = preg_match('#/Type\s*/Pages\b[^>]*?/Count\s+(\d+)#s', $contenido, $m) ? (int) $m[1] : 0;

        return max($n, $cuenta) ?: null;
    }

    public function motorDisponible(): ?string
    {
        $ruta = trim((string) config('msea.digitalizador.audiveris'));

        return $ruta !== '' && is_file($ruta) ? $ruta : null;
    }

    public function crear(UploadedFile $pdf, array $datos, int $idUsuario): object
    {
        $guardado = $this->archivos->guardarPdfMetodo($pdf);
        try {
            $total = $this->contarPaginas($this->archivos->rutaAbsoluta($guardado['path']));
            $paginas = $this->paginasDesdeTexto((string) $datos['paginas'], $total);
        } catch (\Throwable $e) {
            $this->archivos->eliminar($guardado['path']);
            throw $e;
        }

        $id = DB::transaction(function () use ($datos, $guardado, $total, $paginas, $idUsuario) {
            $id = DB::table('digitalizaciones')->insertGetId([
                'titulo' => $datos['titulo'],
                'metodo' => $datos['metodo'] ?? null,
                'instrumento' => $datos['instrumento'],
                'archivo_path' => $guardado['path'],
                'archivo_nombre' => $guardado['nombre'],
                'archivo_tamano' => $guardado['tamano'],
                'paginas_total' => $total,
                'paginas' => json_encode($paginas),
                'estado' => 'pendiente',
                'motor' => $this->motorDisponible() ? 'audiveris' : 'manual',
                'creado_por' => $idUsuario,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'id_digitalizacion');
            foreach ($paginas as $p) {
                DB::table('digitalizacion_paginas')->insert([
                    'id_digitalizacion' => $id, 'pagina' => $p, 'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $id;
        });

        return DB::table('digitalizaciones')->where('id_digitalizacion', $id)->first();
    }

    /** Reconoce todas las páginas pendientes. Puede tardar: se llama desde la cola o con tiempo ilimitado. */
    public function procesar(int $id): void
    {
        $dig = DB::table('digitalizaciones')->where('id_digitalizacion', $id)->first();
        if (! $dig) {
            return;
        }
        DB::table('digitalizaciones')->where('id_digitalizacion', $id)->update(['estado' => 'procesando', 'updated_at' => now()]);
        $pdf = $this->archivos->rutaAbsoluta($dig->archivo_path);
        $motor = $this->motorDisponible();

        foreach (DB::table('digitalizacion_paginas')->where('id_digitalizacion', $id)->whereIn('estado', ['pendiente', 'error'])->orderBy('pagina')->get() as $pagina) {
            if ($motor) {
                $this->reconocerPagina($dig, $pagina, $pdf, $motor);
            } else {
                $this->prepararManual($dig, $pagina, $pdf);
            }
        }

        $estados = DB::table('digitalizacion_paginas')->where('id_digitalizacion', $id)->pluck('estado');
        $todoError = $estados->every(fn ($e) => $e === 'error');
        DB::table('digitalizaciones')->where('id_digitalizacion', $id)->update([
            'estado' => $todoError ? 'error' : 'revisar',
            'motor' => $motor ? 'audiveris' : 'manual',
            'mensaje' => $todoError ? 'No se pudo reconocer ninguna página. Revisa el mensaje de cada una.' : null,
            'updated_at' => now(),
        ]);
    }

    private function reconocerPagina(object $dig, object $pagina, string $pdf, string $motor): void
    {
        $tmp = storage_path('app/omr-tmp/'.Str::uuid());
        File::ensureDirectoryExists($tmp);
        try {
            $r = Process::timeout(max(30, (int) config('msea.digitalizador.tiempo_por_pagina', 300)))->env($this->entornoMotor())->run([
                $motor, '-batch', '-export',
                '-option', 'org.audiveris.omr.sheet.BookManager.useCompression=false',
                '-sheets', (string) $pagina->pagina,
                '-output', $tmp, '--', $pdf,
            ]);

            $xmls = array_values(array_filter(glob($tmp.'/*.xml') ?: [], fn ($f) => basename($f) !== 'book.xml'));
            $mxls = glob($tmp.'/*.mxl') ?: [];
            $archivo = $xmls[0] ?? $mxls[0] ?? null;
            $xml = $archivo ? $this->lector->leerArchivo($archivo) : null;
            $imagen = $this->imagenDesdeOmr(glob($tmp.'/*.omr')[0] ?? null, (int) $pagina->pagina);

            $base = 'digitalizacion/'.$dig->id_digitalizacion.'/pagina-'.$pagina->pagina;
            if ($imagen) {
                $this->archivos->guardarContenido($base.'.png', $imagen);
            }

            if (! $xml) {
                $this->marcarPagina($pagina->id_pagina, 'error', [
                    'imagen_path' => $imagen ? $base.'.png' : null,
                    'mensaje' => $r->successful()
                        ? 'El reconocimiento no encontró música en esta página. Puedes escribir las notas a mano.'
                        : 'El programa de reconocimiento falló en esta página ('.Str::limit(trim($r->errorOutput() ?: $r->output()), 300).').',
                    'ejercicios' => json_encode([]),
                ]);

                return;
            }

            $this->archivos->guardarContenido($base.'.xml', $xml);
            $ejercicios = array_map(fn ($e) => $this->paraRevision($dig, (int) $pagina->pagina, $e), $this->lector->ejercicios($xml));

            $this->marcarPagina($pagina->id_pagina, $ejercicios ? 'reconocida' : 'manual', [
                'imagen_path' => $imagen ? $base.'.png' : $this->imagenConPdftoppm($pdf, $dig, (int) $pagina->pagina),
                'musicxml_path' => $base.'.xml',
                'ejercicios' => json_encode($ejercicios, JSON_UNESCAPED_UNICODE),
                'mensaje' => $ejercicios ? null : 'No se reconocieron notas. Puedes escribirlas a mano.',
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->marcarPagina($pagina->id_pagina, 'error', ['mensaje' => 'Error al reconocer la página: '.Str::limit($e->getMessage(), 300)]);
        } finally {
            File::deleteDirectory($tmp);
        }
    }

    /**
     * Variables de entorno para Audiveris. El servidor web no siempre las pasa (con
     * "php artisan serve" falta HOME y Audiveris no arranca); se usa una carpeta propia.
     */
    private function entornoMotor(): array
    {
        $casa = storage_path('app/omr-home');
        File::ensureDirectoryExists($casa);
        $entorno = ['HOME' => getenv('HOME') ?: $casa];
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['USERPROFILE', 'APPDATA', 'LOCALAPPDATA'] as $var) {
                $entorno[$var] = getenv($var) ?: $casa;
            }
            foreach (['SystemRoot', 'TEMP', 'TMP', 'PATH'] as $var) {
                if (getenv($var)) {
                    $entorno[$var] = getenv($var);
                }
            }
        }

        return $entorno;
    }

    private function prepararManual(object $dig, object $pagina, string $pdf): void
    {
        $this->marcarPagina($pagina->id_pagina, 'manual', [
            'imagen_path' => $this->imagenConPdftoppm($pdf, $dig, (int) $pagina->pagina),
            'ejercicios' => json_encode([$this->paraRevision($dig, (int) $pagina->pagina, ['numero' => 1, 'compas' => '4/4', 'fifths' => 0, 'clave' => 'sol', 'notas' => [], 'compases' => 0])], JSON_UNESCAPED_UNICODE),
            'mensaje' => 'Sin reconocimiento automático (Audiveris no está configurado): escribe las notas mirando la página.',
        ]);
    }

    private function paraRevision(object $dig, int $pagina, array $e): array
    {
        return [
            'numero' => $e['numero'],
            'nombre' => trim(($dig->metodo ?: $dig->titulo).' · pág. '.$pagina.' · ejercicio '.$e['numero']),
            'compas' => in_array($e['compas'], EscalaService::COMPASES, true) ? $e['compas'] : '4/4',
            'fifths' => max(-7, min(7, (int) $e['fifths'])),
            'clave' => $e['clave'],
            'texto' => NotacionTexto::aTexto($e['notas']),
            'reconocido' => NotacionTexto::aTexto($e['notas']),
            'dificultad' => 'intermedio',
            'publicado_id' => null,
        ];
    }

    private function imagenDesdeOmr(?string $omr, int $pagina): ?string
    {
        if (! $omr || ! class_exists(\ZipArchive::class)) {
            return null;
        }
        $zip = new \ZipArchive;
        if ($zip->open($omr) !== true) {
            return null;
        }
        $png = $zip->getFromName('sheet#'.$pagina.'/BINARY.png') ?: null;
        $zip->close();

        return $png;
    }

    private function imagenConPdftoppm(string $pdf, object $dig, int $pagina): ?string
    {
        try {
            $tmp = storage_path('app/omr-tmp/'.Str::uuid());
            File::ensureDirectoryExists($tmp);
            $r = Process::timeout(60)->run(['pdftoppm', '-f', (string) $pagina, '-l', (string) $pagina, '-r', '110', '-png', '-singlefile', $pdf, $tmp.'/p']);
            $ruta = 'digitalizacion/'.$dig->id_digitalizacion.'/pagina-'.$pagina.'.png';
            if ($r->successful() && is_file($tmp.'/p.png')) {
                $this->archivos->guardarContenido($ruta, (string) file_get_contents($tmp.'/p.png'));

                return $ruta;
            }
        } catch (\Throwable) {
            // pdftoppm no está instalado: la revisión ofrece abrir el PDF en esa página
        } finally {
            if (isset($tmp)) {
                File::deleteDirectory($tmp);
            }
        }

        return null;
    }

    private function marcarPagina(int $idPagina, string $estado, array $extra): void
    {
        DB::table('digitalizacion_paginas')->where('id_pagina', $idPagina)->update(['estado' => $estado, 'updated_at' => now()] + $extra);
    }

    // ------------------------------------------------------------------
    // Revisión y publicación
    // ------------------------------------------------------------------

    public function ejerciciosDePagina(object $pagina): array
    {
        return json_decode((string) $pagina->ejercicios, true) ?: [];
    }

    /** Agrega un ejercicio vacío (cuando el reconocimiento no separó bien o hay que escribirlo). */
    public function agregarEjercicio(object $dig, object $pagina): void
    {
        $lista = $this->ejerciciosDePagina($pagina);
        $lista[] = $this->paraRevision($dig, (int) $pagina->pagina, ['numero' => count($lista) + 1, 'compas' => '4/4', 'fifths' => $lista[0]['fifths'] ?? 0, 'clave' => 'sol', 'notas' => []]);
        DB::table('digitalizacion_paginas')->where('id_pagina', $pagina->id_pagina)->update(['ejercicios' => json_encode($lista, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
    }

    /**
     * Guarda la corrección de un ejercicio y, si se pide, lo publica en la biblioteca.
     *
     * @return int|null id en la biblioteca cuando se publica
     */
    public function guardarEjercicio(object $dig, object $pagina, int $indice, array $datos, bool $publicar, int $idUsuario): ?int
    {
        $lista = $this->ejerciciosDePagina($pagina);
        abort_unless(isset($lista[$indice]), 404);

        $notas = NotacionTexto::aNotas((string) $datos['texto'], 'texto');
        $ej = array_merge($lista[$indice], [
            'nombre' => trim((string) $datos['nombre']),
            'compas' => $datos['compas'],
            'fifths' => (int) $datos['fifths'],
            'dificultad' => $datos['dificultad'],
            'texto' => NotacionTexto::aTexto($notas),
        ]);

        $idBiblioteca = $ej['publicado_id'] ?? null;
        if ($publicar) {
            $fila = [
                'nombre' => mb_substr($ej['nombre'], 0, 120),
                'dificultad' => $ej['dificultad'],
                'descripcion' => trim(($dig->metodo ?: $dig->titulo).', página '.$pagina->pagina.'. Digitalizado y revisado en MSEA.'),
                'datos' => json_encode([
                    'tipo' => 'secuencia',
                    'notas' => $notas,
                    'compas' => $ej['compas'],
                    'fifths' => $ej['fifths'],
                    'clave' => $ej['clave'] ?? 'sol',
                    'fuente' => [
                        'titulo' => $dig->titulo,
                        'metodo' => $dig->metodo,
                        'pagina' => (int) $pagina->pagina,
                        'ejercicio' => (int) ($ej['numero'] ?? $indice + 1),
                        'id_digitalizacion' => (int) $dig->id_digitalizacion,
                    ],
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ];

            $duplicado = DB::table('biblioteca_ejercicios')->where('categoria', 'metodo')->where('instrumento', $dig->instrumento)
                ->where('nombre', $fila['nombre'])->when($idBiblioteca, fn ($q) => $q->where('id_biblioteca', '!=', $idBiblioteca))->exists();
            if ($duplicado) {
                throw ValidationException::withMessages(['nombre' => 'Ya hay un ejercicio publicado con ese nombre. Cambia el nombre.']);
            }

            if ($idBiblioteca && DB::table('biblioteca_ejercicios')->where('id_biblioteca', $idBiblioteca)->exists()) {
                DB::table('biblioteca_ejercicios')->where('id_biblioteca', $idBiblioteca)->update($fila);
            } else {
                $idBiblioteca = DB::table('biblioteca_ejercicios')->insertGetId($fila + [
                    'categoria' => 'metodo',
                    'instrumento' => $dig->instrumento,
                    'activo' => true,
                    'orden' => (int) $pagina->pagina * 100 + $indice,
                    'creado_por' => $idUsuario,
                    'created_at' => now(),
                ], 'id_biblioteca');
            }
            $ej['publicado_id'] = (int) $idBiblioteca;
        }

        $lista[$indice] = $ej;
        DB::table('digitalizacion_paginas')->where('id_pagina', $pagina->id_pagina)->update(['ejercicios' => json_encode($lista, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);

        return $publicar ? (int) $idBiblioteca : null;
    }
}
