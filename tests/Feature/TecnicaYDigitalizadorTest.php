<?php

namespace Tests\Feature;

use App\Services\DigitalizadorService;
use App\Services\MusicXmlLector;
use App\Services\NotacionTexto;
use App\Services\TecnicaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/** Ejercicios de técnica de mano izquierda y digitalizador de métodos en PDF. */
class TecnicaYDigitalizadorTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    private TecnicaService $tecnica;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        Storage::fake('local');
        $this->tecnica = app(TecnicaService::class);
    }

    private function tecnicaDe(string $instrumento, string $nombre): object
    {
        return $this->tecnica->ejercicio((int) DB::table('biblioteca_ejercicios')->where('categoria', 'tecnica_mano_izquierda')
            ->where('instrumento', $instrumento)->where('nombre', $nombre)->value('id_biblioteca'));
    }

    /** PDF mínimo válido con N páginas (sin música) para probar la subida. */
    private function pdf(int $paginas = 7): UploadedFile
    {
        $objetos = ['<< /Type /Catalog /Pages 2 0 R >>'];
        $kids = [];
        for ($i = 0; $i < $paginas; $i++) {
            $kids[] = (3 + $i).' 0 R';
        }
        $objetos[] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$paginas.' >>';
        for ($i = 0; $i < $paginas; $i++) {
            $objetos[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>';
        }
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objetos as $n => $o) {
            $offsets[] = strlen($pdf);
            $pdf .= ($n + 1)." 0 obj\n{$o}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= 'trailer << /Size '.(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        $ruta = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($ruta, $pdf);

        return new UploadedFile($ruta, 'metodo.pdf', 'application/pdf', null, true);
    }

    // ------------------------------------------------------------ técnica

    public function test_ejercicios_de_dedos_para_los_cuatro_instrumentos(): void
    {
        foreach (['violin' => 32, 'viola' => 32, 'violonchelo' => 16, 'contrabajo' => 12] as $instrumento => $cantidad) {
            $this->assertSame($cantidad, DB::table('biblioteca_ejercicios')->where('categoria', 'tecnica_mano_izquierda')->where('instrumento', $instrumento)->count(), $instrumento);
        }

        $notas = $this->tecnica->notas($this->tecnicaDe('violin', 'Dedos 1-2-3-4 · 2 alto')->datos, 'violin');
        $this->assertCount(64, $notas); // 4 notas x 4 repeticiones x 4 cuerdas
        $this->assertSame(['Si4', 'Do♯5', 'Re5', 'Mi5'], array_column(array_slice($notas, 32, 4), 'etiqueta'));
        $this->assertSame(['La', 1], [$notas[32]['cuerda'], $notas[32]['dedo']]);
        $this->assertSame(554.37, $notas[33]['frecuencia']);

        $bajo = $this->tecnica->notas($this->tecnicaDe('violin', 'Dedos 1-2-3-4 · 1 bajo')->datos, 'violin');
        $this->assertSame(['Si♭4', 'Do5', 'Re5', 'Mi♭5'], array_column(array_slice($bajo, 32, 4), 'etiqueta'));

        $chelo = $this->tecnica->notas($this->tecnicaDe('violonchelo', 'Dedos 1-2-3-4 · primera posición')->datos, 'violonchelo');
        $this->assertSame(['Re2', 'Mi♭2', 'Mi2', 'Fa2'], array_column(array_slice($chelo, 0, 4), 'etiqueta'));

        $contrabajo = $this->tecnica->notas($this->tecnicaDe('contrabajo', 'Dedos 1-2-4 · primera posición')->datos, 'contrabajo');
        $this->assertSame(['Fa♯1', 'Sol1', 'Sol♯1', 'Sol1'], array_column(array_slice($contrabajo, 0, 4), 'etiqueta'));
        $this->assertSame(46.25, $contrabajo[0]['frecuencia']);
    }

    public function test_profesor_asigna_tecnica_en_modo_velocidad_y_estudiante_la_practica(): void
    {
        $prof = $this->crearProfesor();
        $alumno = $this->crearEstudiante($prof['profesor']);
        $ej = $this->tecnicaDe('violin', 'Trino 1-2 · 2 alto');

        $this->sesionDe($prof['usuario'])->postJson('/dashboard-profesor/tareas', [
            'id_estudiante' => $alumno['estudiante'],
            'id_biblioteca' => $ej->id_biblioteca,
            'config_escala' => json_encode(['modo' => 'velocidad', 'bpm' => 60, 'bpm_objetivo' => 96, 'paso' => 6, 'figura' => 'semicorchea', 'compas' => '4/4', 'tolerancia' => 15]),
        ])->assertSuccessful();
        $tarea = DB::table('tareas')->latest('id_tarea')->first();
        $this->assertSame('Trino 1-2 · 2 alto - velocidad', $tarea->titulo);

        $vista = $this->sesionDe($alumno['usuario'])->get("/dashboard-estudiante/tareas/{$tarea->id_tarea}/escala")
            ->assertOk()->assertSee('Velocidad: de 60 a 96 (sube 6)');
        $ej = $vista->viewData('ejercicio');
        $this->assertSame('tecnica', $ej['tipo']);
        $this->assertSame(['semicorchea'], $ej['ritmo'][0]);
        $total = count($ej['notas']);

        $this->sesionDe($alumno['usuario'])->postJson("/dashboard-estudiante/tareas/{$tarea->id_tarea}/escala", [
            'resultado' => ['notas' => array_fill(0, $total, ['cents' => 3, 'ms' => 200]), 'duracion_s' => 40, 'bpm' => 72],
        ])->assertOk();
        $resultado = json_decode(DB::table('entregas_tareas')->value('resultado_escala'), true);
        $this->assertSame(72, $resultado['bpm']);
        $this->assertStringContainsString('a tempo 72', DB::table('entregas_tareas')->value('comentario_estudiante'));
    }

    public function test_configuracion_de_tecnica_invalida(): void
    {
        $ej = $this->tecnicaDe('violin', 'Trino 1-2 · 2 alto');
        $this->expectException(ValidationException::class);
        $this->tecnica->normalizarConfig(['modo' => 'velocidad', 'bpm' => 120, 'bpm_objetivo' => 80], $ej);
    }

    // ------------------------------------------------------------ notación de texto y MusicXML

    public function test_notacion_de_texto_ida_y_vuelta(): void
    {
        $notas = NotacionTexto::aNotas('Si4:c/1 Do#5:c | Re5:n. -:c Mib5:s Sol♯4:b');
        $this->assertSame(['letra' => 'B', 'alteracion' => 0, 'octava' => 4, 'figura' => 'corchea', 'dedo' => 1], $notas[0]);
        $this->assertSame('negra_puntillo', $notas[2]['figura']);
        $this->assertTrue($notas[3]['silencio']);
        $this->assertSame(-1, $notas[4]['alteracion']);
        $this->assertSame('Si4:c/1 Do#5:c Re5:n. -:c Mib5:s Sol#4:b', NotacionTexto::aTexto($notas));

        $this->expectException(ValidationException::class);
        NotacionTexto::aNotas('La4:c Xx9:c');
    }

    public function test_musicxml_reconocido_se_divide_en_ejercicios_por_doble_barra(): void
    {
        $lector = new MusicXmlLector;
        $ejercicios = $lector->ejercicios((string) file_get_contents(base_path('tests/fixtures/omr/pagina-4.xml')));

        $this->assertCount(2, $ejercicios);
        $this->assertSame(3, $ejercicios[0]['fifths']);
        $this->assertSame('4/4', $ejercicios[0]['compas']);
        $this->assertSame('B', $ejercicios[0]['notas'][0]['letra']);
        $this->assertSame(['C', 1, 5], [$ejercicios[0]['notas'][1]['letra'], $ejercicios[0]['notas'][1]['alteracion'], $ejercicios[0]['notas'][1]['octava']]);
        $this->assertSame('A', $ejercicios[1]['notas'][0]['letra']);
        $this->assertGreaterThan(25, count($ejercicios[1]['notas']));
    }

    public function test_paginas_elegidas(): void
    {
        $d = app(DigitalizadorService::class);
        $this->assertSame([4, 6], $d->paginasDesdeTexto('4, 6', 7));
        $this->assertSame([4, 5, 6], $d->paginasDesdeTexto('6, 4-5', 7));
        $this->expectException(ValidationException::class);
        $d->paginasDesdeTexto('4, 9', 7);
    }

    // ------------------------------------------------------------ digitalizador

    public function test_solo_el_director_usa_el_digitalizador(): void
    {
        $prof = $this->crearProfesor();
        $this->sesionDe($prof['usuario'])->get('/dashboard-admin/digitalizador')->assertRedirect('/login');
        $director = $this->crearUsuario('director');
        $this->sesionDe($director)->get('/dashboard-admin/digitalizador')->assertOk()->assertSee('Subir un método en PDF');
    }

    public function test_digitalizacion_sin_omr_deja_paginas_para_escribir_y_publica_en_la_biblioteca(): void
    {
        config(['msea.digitalizador.audiveris' => '']);
        $director = $this->crearUsuario('director');

        $this->sesionDe($director)->post('/dashboard-admin/digitalizador', [
            'pdf' => $this->pdf(7), 'titulo' => 'Método de prueba', 'metodo' => 'Método de prueba', 'instrumento' => 'violin', 'paginas' => '4, 6', 'derechos' => '1',
        ])->assertRedirect();

        $dig = DB::table('digitalizaciones')->first();
        $this->assertSame(7, (int) $dig->paginas_total);
        $this->assertSame([4, 6], json_decode($dig->paginas, true));
        $this->assertSame('revisar', $dig->estado);
        $paginas = DB::table('digitalizacion_paginas')->orderBy('pagina')->get();
        $this->assertSame(['manual', 'manual'], $paginas->pluck('estado')->all());

        // Página inexistente
        $this->sesionDe($director)->post('/dashboard-admin/digitalizador', [
            'pdf' => $this->pdf(3), 'titulo' => 'Corto', 'instrumento' => 'violin', 'paginas' => '4', 'derechos' => '1',
        ])->assertSessionHasErrors('paginas');

        // El director escribe las notas de la página 4 y publica
        $pagina = $paginas[0];
        $this->sesionDe($director)->post("/dashboard-admin/digitalizador/{$dig->id_digitalizacion}/paginas/{$pagina->id_pagina}/ejercicios/0", [
            'nombre' => 'Método de prueba · pág. 4 · n.º 1', 'compas' => '4/4', 'fifths' => 3, 'dificultad' => 'intermedio',
            'texto' => 'Si4:c/1 Do#5:c/2 Si4:c Do#5:c | Si4:c Do#5:c Si4:c Do#5:c', 'accion' => 'publicar',
        ])->assertRedirect();

        $fila = DB::table('biblioteca_ejercicios')->where('categoria', 'metodo')->first();
        $this->assertSame('Método de prueba · pág. 4 · n.º 1', $fila->nombre);
        $datos = json_decode($fila->datos, true);
        $this->assertSame(4, $datos['fuente']['pagina']);
        $this->assertCount(8, $datos['notas']);
        $this->assertSame(1, $datos['notas'][0]['dedo']);

        // Lo ven los profesores y se puede probar
        $prof = $this->crearProfesor();
        $vista = $this->sesionDe($prof['usuario'])->get('/dashboard-profesor')->assertOk();
        $this->assertNotEmpty(collect($vista->viewData('bibliotecaTecnica')['violin'])->firstWhere('categoria', 'metodo'));
        $this->sesionDe($prof['usuario'])->get("/biblioteca/ejercicios/{$fila->id_biblioteca}/probar")->assertOk()->assertSee('Métodos digitalizados')->assertSee('pág. 4');

        // Notación con error: no se publica
        $this->sesionDe($director)->post("/dashboard-admin/digitalizador/{$dig->id_digitalizacion}/paginas/{$paginas[1]->id_pagina}/ejercicios/0", [
            'nombre' => 'Otro', 'compas' => '4/4', 'fifths' => 0, 'dificultad' => 'facil', 'texto' => 'La4:z', 'accion' => 'publicar',
        ])->assertSessionHasErrors('texto');
        $this->assertSame(1, DB::table('biblioteca_ejercicios')->where('categoria', 'metodo')->count());
    }

    public function test_con_motor_de_reconocimiento_lee_las_paginas_elegidas(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('El motor simulado es un script de shell.');
        }
        // Motor simulado: copia el MusicXML de prueba como si lo hubiera reconocido y registra la página pedida.
        $script = tempnam(sys_get_temp_dir(), 'omr');
        $registro = $script.'.log';
        file_put_contents($script, "#!/bin/sh\nwhile [ \"\$1\" != \"\" ]; do case \"\$1\" in -sheets) shift; echo \"\$1\" >> {$registro};; -output) shift; OUT=\"\$1\";; esac; shift; done\ncp ".base_path('tests/fixtures/omr/pagina-4.xml')." \"\$OUT/metodo.xml\"\n");
        chmod($script, 0755);
        config(['msea.digitalizador.audiveris' => $script]);

        $director = $this->crearUsuario('director');
        $this->sesionDe($director)->post('/dashboard-admin/digitalizador', [
            'pdf' => $this->pdf(7), 'titulo' => 'Con OMR', 'instrumento' => 'violin', 'paginas' => '4,6', 'derechos' => '1',
        ])->assertRedirect();

        $this->assertSame("4\n6\n", file_get_contents($registro), 'Solo se reconocen las páginas pedidas');
        $paginas = DB::table('digitalizacion_paginas')->orderBy('pagina')->get();
        $this->assertSame(['reconocida', 'reconocida'], $paginas->pluck('estado')->all());
        $ejercicios = json_decode($paginas[0]->ejercicios, true);
        $this->assertCount(2, $ejercicios);
        $this->assertStringStartsWith('Si4:c Do#5:c', $ejercicios[0]['texto']);

        $vista = $this->sesionDe($director)->get('/dashboard-admin/digitalizador/'.$paginas[0]->id_digitalizacion)->assertOk();
        $vista->assertSee('Reconocida: revisar')->assertSee('Publicar en la biblioteca');
        @unlink($script);
        @unlink($registro);
    }
}
