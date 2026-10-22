<?php

namespace Tests\Feature\Seguridad;

use App\Services\CsvExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/** S8 (formulas CSV) y S3 (CSP + datos en JSON escapados). */
class CsvYCabecerasTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
    }

    public function test_neutraliza_formulas_y_respeta_numeros_negativos(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://x\")", CsvExporter::neutralizar('=HYPERLINK("http://x")'));
        $this->assertSame("'+1+1", CsvExporter::neutralizar('+1+1'));
        $this->assertSame("'@SUM(A1)", CsvExporter::neutralizar('@SUM(A1)'));
        $this->assertSame("'-cmd", CsvExporter::neutralizar('-cmd'));
        $this->assertSame('-12.5', CsvExporter::neutralizar('-12.5'));
        $this->assertSame('Ana', CsvExporter::neutralizar('Ana'));
    }

    public function test_reporte_de_estudiantes_neutraliza_y_conserva_el_dato_original(): void
    {
        $director = $this->crearUsuario('director');
        $this->crearEstudiante(null, null, ['nombres' => '=cmd|"/C calc"!A0', 'apellido_paterno' => 'Coma, "comillas"'."\nsalto"]);

        $csv = $this->sesionDe($director)->get('/dashboard-admin/reportes/estudiantes')->assertOk()->streamedContent();

        $this->assertStringContainsString("\"'=cmd|\"\"/C calc\"\"!A0", $csv);
        $this->assertStringContainsString('Coma, ""comillas""'."\nsalto", $csv);
        $this->assertDatabaseHas('usuarios', ['nombres' => '=cmd|"/C calc"!A0']);
        $this->assertDatabaseHas('auditoria', ['accion' => 'reporte_descargado']);
    }

    public function test_csp_con_nonce_en_paginas_y_scripts_en_linea(): void
    {
        $alumno = $this->crearEstudiante();
        $respuesta = $this->sesionDe($alumno['usuario'])->get('/dashboard-estudiante')->assertOk();

        $csp = $respuesta->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString("'unsafe-inline' https://cdn.jsdelivr.net;", explode('style-src', $csp)[0]);
        preg_match("/'nonce-([A-Za-z0-9]+)'/", $csp, $m);
        $this->assertNotEmpty($m[1] ?? null);

        // Todo <script> en linea lleva el nonce de la peticion.
        preg_match_all('/<script(?![^>]*\bsrc=)([^>]*)>/', $respuesta->getContent(), $inline);
        foreach ($inline[1] as $atributos) {
            $this->assertStringContainsString('nonce="'.$m[1].'"', $atributos);
        }
    }

    public function test_nombre_con_html_llega_escapado_a_la_pagina(): void
    {
        $prof = $this->crearProfesor();
        $this->crearEstudiante($prof['profesor'], null, ['nombres' => '<img src=x onerror=alert(1)>']);
        $alumno = $this->crearEstudiante($prof['profesor']);

        foreach ([[$alumno['usuario'], '/dashboard-estudiante'], [$prof['usuario'], '/dashboard-profesor']] as [$usuario, $url]) {
            $html = $this->sesionDe($usuario)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('<img src=x onerror', $html, $url);
        }
    }
}
