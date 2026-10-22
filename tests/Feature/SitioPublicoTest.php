<?php

namespace Tests\Feature;

use App\Http\Middleware\ProteccionSpam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Web publica: legal, SEO, sitemap, robots, 404, HTTPS, anti-spam, WhatsApp, analitica. */
class SitioPublicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginas_legales_responden_con_titulo_y_datos_estructurados(): void
    {
        foreach (['/aviso-legal' => 'Aviso legal', '/politica-de-privacidad' => 'Política de privacidad', '/politica-de-cookies' => 'Política de cookies'] as $ruta => $titulo) {
            $r = $this->get($ruta)->assertOk();
            $r->assertSee('<title>'.$titulo.' | MSEA</title>', false);
            $r->assertSee('<meta name="description"', false);
            $r->assertSee('rel="canonical"', false);
            $r->assertSee('application/ld+json', false);
            $r->assertSee('index, follow', false);
        }
    }

    public function test_portada_tiene_metas_y_datos_de_organizacion(): void
    {
        $r = $this->get('/')->assertOk();
        $r->assertSee('og:title', false);
        $r->assertSee('"@type":["EducationalOrganization","NGO"]', false);
        $r->assertSee('"@type":"WebSite"', false);
        $r->assertSee('rel="icon"', false);
    }

    public function test_paginas_privadas_no_se_indexan(): void
    {
        foreach (['/login', '/registro', '/forgot-password'] as $ruta) {
            $r = $this->get($ruta)->assertOk();
            $r->assertSee('content="noindex, nofollow"', false);
            $this->assertStringContainsString('noindex', (string) $r->headers->get('X-Robots-Tag'));
        }
        $this->assertNull($this->get('/')->headers->get('X-Robots-Tag'));
    }

    public function test_sitemap_y_robots(): void
    {
        $s = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('application/xml', $s->headers->get('Content-Type'));
        $s->assertSee('/politica-de-privacidad', false);
        $s->assertDontSee('/login', false);

        $r = $this->get('/robots.txt')->assertOk();
        $r->assertSee('Sitemap: '.url('/sitemap.xml'), false);
        $r->assertSee('Disallow: /dashboard-', false);
    }

    public function test_pagina_404_personalizada(): void
    {
        $this->get('/ruta-que-no-existe')->assertNotFound()
            ->assertSee('no existe', false)
            ->assertSee('Volver al inicio', false)
            ->assertSee('noindex', false);
    }

    public function test_https_se_fuerza_solo_cuando_esta_activo(): void
    {
        config(['msea.sitio.forzar_https' => false]);
        $this->get('/')->assertOk();

        config(['msea.sitio.forzar_https' => true]);
        $this->get('http://msea.test/')->assertStatus(301)->assertRedirect('https://msea.test/');
        $this->post('http://msea.test/login', [])->assertStatus(308);
        $this->get('https://msea.test/')->assertOk()->assertHeader('Strict-Transport-Security');
        $this->get('http://msea.test/up')->assertOk();
    }

    public function test_antispam_bloquea_campo_trampa_y_envios_demasiado_rapidos(): void
    {
        config(['msea.sitio.antispam.activo' => true]);
        $datos = ['correo' => 'a@b.com', 'contrasena' => 'x', 'rol' => 'estudiante'];

        $this->post('/login', $datos)->assertSessionHas('error');

        $this->post('/login', $datos + [ProteccionSpam::CAMPO_TRAMPA => 'http://spam', ProteccionSpam::CAMPO_HORA => ProteccionSpam::horaFirmada()])
            ->assertSessionHas('error', 'No pudimos enviar el formulario. Intenta de nuevo.');

        $this->post('/login', $datos + [ProteccionSpam::CAMPO_HORA => ProteccionSpam::horaFirmada()])
            ->assertSessionHas('error', 'Fue muy rápido. Espera un momento y vuelve a enviar.');

        $this->travel(5)->seconds();
        $hora = ProteccionSpam::horaFirmada();
        $this->travel(5)->seconds();
        $this->post('/login', $datos + [ProteccionSpam::CAMPO_HORA => $hora])
            ->assertSessionMissing('error', 'Fue muy rápido. Espera un momento y vuelve a enviar.');
    }

    public function test_formularios_publicos_llevan_campos_antispam(): void
    {
        foreach (['/login', '/registro', '/forgot-password'] as $ruta) {
            $this->get($ruta)->assertSee('name="'.ProteccionSpam::CAMPO_TRAMPA.'"', false)
                ->assertSee('name="'.ProteccionSpam::CAMPO_HORA.'"', false);
        }
    }

    public function test_whatsapp_solo_aparece_si_esta_configurado(): void
    {
        config(['msea.sitio.whatsapp' => '']);
        $this->get('/')->assertDontSee('boton-whatsapp', false);

        config(['msea.sitio.whatsapp' => '59170000000']);
        $this->get('/')->assertSee('https://wa.me/59170000000?text=', false)->assertSee('boton-whatsapp', false);
    }

    public function test_analitica_apagada_por_defecto_y_solo_con_consentimiento_en_cliente(): void
    {
        $this->get('/')->assertDontSee('googletagmanager', false)->assertSee('Entendido', false);

        config(['msea.sitio.analitica.ga4' => 'G-TEST12345']);
        $r = $this->get('/')->assertSee('Aceptar analítica', false)->assertSee('data-ga4="G-TEST12345"', false);
        // El script de Google no va en el HTML: lo carga cookies.js solo tras aceptar.
        $r->assertDontSee('googletagmanager.com/gtag', false);
        $this->assertStringContainsString('https://www.googletagmanager.com', $r->headers->get('Content-Security-Policy'));
    }

    public function test_registro_exige_aceptar_la_politica_de_privacidad(): void
    {
        $this->get('/registro')->assertSee('acepta_privacidad', false)->assertSee('/politica-de-privacidad', false);

        $this->post('/registro', [
            'rol' => 'estudiante', 'nombres' => 'Ana', 'apellido_paterno' => 'Quispe',
            'correo' => 'ana.privacidad@msea.test', 'contrasena' => 'secret123', 'contrasena_confirmation' => 'secret123',
        ])->assertSessionHasErrors(['acepta_privacidad']);

        $this->assertDatabaseMissing('usuarios', ['correo' => 'ana.privacidad@msea.test']);
    }

    public function test_el_aviso_de_cookies_esta_en_las_paginas_publicas_y_no_en_los_paneles(): void
    {
        foreach (['/', '/login', '/registro', '/aviso-legal'] as $ruta) {
            $this->get($ruta)->assertSee('id="cookie-banner"', false)->assertSee('politica-de-cookies', false);
        }
    }
}
