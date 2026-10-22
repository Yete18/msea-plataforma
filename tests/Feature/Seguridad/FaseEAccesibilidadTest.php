<?php

namespace Tests\Feature\Seguridad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/**
 * Fase E: CSP sin 'unsafe-inline' en estilos (FE-09), sprite de iconos externo
 * (FE-08), tema oscuro antes de pintar (FE-06) y errores asociados a su campo
 * (A11Y-02).
 */
class FaseEAccesibilidadTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
    }

    /** @return array<string, string> ruta => HTML */
    private function paginas(): array
    {
        $profesor = $this->crearProfesor();
        $alumno = $this->crearEstudiante($profesor['profesor']);
        $padre = $this->crearPadre([$alumno['estudiante']]);
        $director = $this->crearUsuario('director');

        $paginas = [];
        foreach (['/login', '/registro', '/forgot-password'] as $ruta) {
            $paginas[$ruta] = $this->get($ruta)->assertOk()->getContent();
        }
        $paginas['/dashboard-estudiante'] = $this->sesionDe($alumno['usuario'])->get('/dashboard-estudiante')->assertOk()->getContent();
        $paginas['/dashboard-profesor'] = $this->sesionDe($profesor['usuario'])->get('/dashboard-profesor')->assertOk()->getContent();
        $paginas['/dashboard-padre'] = $this->sesionDe($padre['usuario'])->get('/dashboard-padre')->assertOk()->getContent();
        foreach (['/dashboard-admin', '/dashboard-admin/usuarios', '/dashboard-admin/profesores', '/dashboard-admin/catalogos'] as $ruta) {
            $paginas[$ruta] = $this->sesionDe($director)->get($ruta)->assertOk()->getContent();
        }

        return $paginas;
    }

    public function test_la_csp_no_permite_estilos_en_linea(): void
    {
        $csp = $this->get('/login')->headers->get('Content-Security-Policy');
        preg_match('/style-src([^;]*)/', $csp, $m);

        $this->assertNotEmpty($m[1] ?? null);
        $this->assertStringNotContainsString('unsafe-inline', $m[1]);
    }

    public function test_ninguna_pagina_usa_atributos_style_ni_sprite_en_linea(): void
    {
        foreach ($this->paginas() as $ruta => $html) {
            $this->assertDoesNotMatchRegularExpression('/\sstyle\s*=/i', $html, "$ruta tiene un atributo style");
            $this->assertStringNotContainsString('<symbol', $html, "$ruta incrusta el sprite");
            $this->assertMatchesRegularExpression('#assets/img/iconos\.svg\?v=[0-9a-f]{8}\#i-#', $html, "$ruta no usa el sprite externo");
            $this->assertStringContainsString('name="msea-iconos"', $html, "$ruta no publica la URL del sprite para el JS");
        }
    }

    public function test_cada_icono_usado_existe_en_el_sprite(): void
    {
        $sprite = file_get_contents(public_path('assets/img/iconos.svg'));
        preg_match_all('/<symbol id="(i-[a-z0-9-]+)"/', $sprite, $m);
        $disponibles = array_flip($m[1]);

        $usados = [];
        $archivos = array_merge(
            glob(resource_path('views/**/*.blade.php')) ?: [],
            glob(resource_path('views/*.blade.php')) ?: [],
            glob(resource_path('views/*/*/*.blade.php')) ?: [],
            glob(public_path('js/*.js')) ?: [],
        );
        foreach ($archivos as $archivo) {
            if (str_ends_with($archivo, 'index.blade.php')) {
                continue; // La portada tiene su propio sprite de cuatro instrumentos.
            }
            preg_match_all('/#(i-[a-z0-9-]+)["\'\s\/]/', file_get_contents($archivo), $r);
            foreach ($r[1] as $id) {
                $usados[$id][] = basename($archivo);
            }
            // Llamadas literales a icon('nombre') en el JS.
            preg_match_all("/\\bicon\\('([a-z0-9-]+)'/", file_get_contents($archivo), $r);
            foreach ($r[1] as $nombre) {
                $usados['i-'.$nombre][] = basename($archivo);
            }
        }

        $faltan = array_diff_key($usados, $disponibles);
        $this->assertSame([], array_map(fn ($a) => array_values(array_unique($a)), $faltan), 'Iconos usados que no estan en el sprite');
        $this->assertGreaterThan(50, count($disponibles));
    }

    public function test_el_tema_se_aplica_en_el_head_con_nonce(): void
    {
        $respuesta = $this->get('/login');
        preg_match("/'nonce-([A-Za-z0-9]+)'/", $respuesta->headers->get('Content-Security-Policy'), $n);
        $head = explode('</head>', $respuesta->getContent())[0];

        $this->assertStringContainsString('<script nonce="'.$n[1].'">', $head);
        $this->assertStringContainsString('prefers-color-scheme: dark', $head);
        $this->assertStringContainsString("classList.add('theme-dark')", $head);
    }

    public function test_los_errores_del_servidor_viajan_por_campo(): void
    {
        $this->from('/login')->post('/login', ['correo' => 'no-es-correo', 'contrasena' => ''])->assertRedirect('/login');

        $html = $this->get('/login')->getContent();
        $this->assertStringContainsString('id="msea-errores-campos"', $html);
        preg_match('#<script type="application/json" id="msea-errores-campos"[^>]*>(.*?)</script>#s', $html, $m);
        $errores = json_decode($m[1] ?? '', true);
        $this->assertArrayHasKey('correo', $errores);
        $this->assertArrayHasKey('contrasena', $errores);
    }

    public function test_los_campos_del_login_y_registro_se_describen_con_su_error(): void
    {
        $login = $this->get('/login')->getContent();
        $this->assertStringContainsString('aria-describedby="error-correo"', $login);
        $this->assertStringContainsString('aria-describedby="error-password"', $login);

        $registro = $this->get('/registro')->getContent();
        foreach (['error-nombre', 'error-apellido', 'error-email', 'error-instrumento', 'error-nivel', 'error-confirm', 'error-privacidad'] as $id) {
            $this->assertMatchesRegularExpression('/aria-describedby="[^"]*'.$id.'/', $registro, $id);
        }
    }

    public function test_el_nombre_del_sprite_cambia_con_el_archivo(): void
    {
        $html = $this->get('/login')->getContent();
        preg_match('#iconos\.svg\?v=([0-9a-f]{8})#', $html, $m);
        $ruta = public_path('assets/img/iconos.svg');

        $this->assertSame(substr(md5((string) filemtime($ruta).filesize($ruta)), 0, 8), $m[1] ?? null);
    }
}
