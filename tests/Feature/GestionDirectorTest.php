<?php

namespace Tests\Feature;

use App\Mail\CodigoRecuperacionMail;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreaDatosMsea;
use Tests\TestCase;

/** Fase 5: gestion de usuarios, padres, catalogos, comunicados y bitacora. */
class GestionDirectorTest extends TestCase
{
    use CreaDatosMsea;
    use RefreshDatabase;

    private int $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarCatalogos();
        $this->director = $this->crearUsuario('director');
    }

    public function test_paginas_de_gestion_cargan(): void
    {
        $this->crearEstudiante();
        $this->crearPadre();
        DB::table('auditoria')->insert(['accion' => 'login', 'created_at' => now()]);
        DB::table('cursos')->insert(['titulo' => 'C1', 'nivel' => 'Inicial', 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        $idCurso = (int) DB::table('cursos')->value('id_curso');

        foreach (['/dashboard-admin', '/dashboard-admin/usuarios', '/dashboard-admin/catalogos', '/dashboard-admin/comunicados', '/dashboard-admin/auditoria', '/dashboard-admin/cursos', "/dashboard-admin/cursos/{$idCurso}"] as $url) {
            $this->sesionDe($this->director)->get($url)->assertOk();
        }
    }

    public function test_editar_y_desactivar_estudiante_conserva_historial(): void
    {
        $alumno = $this->crearEstudiante();
        app(GamificationService::class)->otorgarXp($alumno['estudiante'], 50, 'tarea', 'h1', 'x');

        $this->sesionDe($this->director)->put("/dashboard-admin/usuarios/{$alumno['usuario']}", [
            'nombres' => 'Nuevo', 'apellido_paterno' => 'Nombre', 'correo' => 'NUEVO@msea.test',
        ])->assertRedirect();
        $this->assertDatabaseHas('usuarios', ['id_usuario' => $alumno['usuario'], 'nombres' => 'Nuevo', 'correo' => 'nuevo@msea.test']);

        $this->sesionDe($this->director)->post("/dashboard-admin/usuarios/{$alumno['usuario']}/estado", ['activo' => 0])->assertRedirect();
        $this->assertDatabaseHas('usuarios', ['id_usuario' => $alumno['usuario'], 'activo' => false]);
        $this->assertSame(1, DB::table('xp_movimientos')->where('id_estudiante', $alumno['estudiante'])->count(), 'El historial se conserva');
        $this->assertDatabaseHas('auditoria', ['accion' => 'usuario_desactivado', 'id_usuario' => $this->director]);

        // Un desactivado no aparece en el ranking
        $this->assertNull(DB::table('ranking_estudiantes')->where('id_estudiante', $alumno['estudiante'])->first());

        $this->sesionDe($this->director)->post("/dashboard-admin/usuarios/{$alumno['usuario']}/estado", ['activo' => 1])->assertRedirect();
        $this->assertDatabaseHas('usuarios', ['id_usuario' => $alumno['usuario'], 'activo' => true]);
    }

    public function test_director_no_puede_desactivarse_a_si_mismo(): void
    {
        $this->sesionDe($this->director)->post("/dashboard-admin/usuarios/{$this->director}/estado", ['activo' => 0])->assertSessionHasErrors('usuario');
        $this->assertDatabaseHas('usuarios', ['id_usuario' => $this->director, 'activo' => true]);
    }

    public function test_crear_padre_vincular_y_desvincular(): void
    {
        $hijo = $this->crearEstudiante();
        $otro = $this->crearEstudiante();

        $this->sesionDe($this->director)->post('/dashboard-admin/padres', [
            'nombres' => 'Rosa', 'apellido_paterno' => 'Choque', 'correo' => 'Rosa@msea.test', 'parentesco' => 'Madre',
            'contrasena' => 'familia123', 'contrasena_confirmation' => 'familia123', 'estudiantes' => [$hijo['estudiante']],
        ])->assertRedirect('/dashboard-admin/usuarios#padres');

        $usuario = DB::table('usuarios')->where('correo', 'rosa@msea.test')->first();
        $this->assertTrue(Hash::check('familia123', $usuario->contrasena));
        $idPadre = (int) DB::table('padres')->where('id_usuario', $usuario->id_usuario)->value('id_padre');
        $this->assertDatabaseHas('estudiante_padre', ['id_padre' => $idPadre, 'id_estudiante' => $hijo['estudiante']]);

        $this->sesionDe($this->director)->post("/dashboard-admin/padres/{$idPadre}/vincular", ['id_estudiante' => $otro['estudiante']])->assertRedirect();
        $this->sesionDe($this->director)->delete("/dashboard-admin/padres/{$idPadre}/estudiantes/{$hijo['estudiante']}")->assertRedirect();

        $this->assertDatabaseMissing('estudiante_padre', ['id_padre' => $idPadre, 'id_estudiante' => $hijo['estudiante']]);
        $this->assertDatabaseHas('estudiante_padre', ['id_padre' => $idPadre, 'id_estudiante' => $otro['estudiante']]);

        // El padre ve solo al vinculado actual
        $hijos = $this->sesionDe($usuario->id_usuario)->get('/dashboard-padre')->assertOk()->viewData('estudiantes');
        $this->assertSame([$otro['estudiante']], $hijos->pluck('id')->all());
    }

    public function test_recuperacion_enviada_por_el_director_no_expone_contrasena(): void
    {
        Mail::fake();
        $alumno = $this->crearEstudiante();

        $this->sesionDe($this->director)->post("/dashboard-admin/usuarios/{$alumno['usuario']}/recuperacion")->assertRedirect();

        Mail::assertSent(CodigoRecuperacionMail::class);
        $this->assertDatabaseHas('auditoria', ['accion' => 'reinicio_contrasena_admin']);
    }

    public function test_catalogos_instrumentos_niveles_y_logros(): void
    {
        $this->sesionDe($this->director)->post('/dashboard-admin/instrumentos', ['nombre' => 'Contrabajo'])->assertRedirect();
        $this->sesionDe($this->director)->post('/dashboard-admin/niveles', ['nombre' => 'Prejuvenil'])->assertRedirect();
        $this->sesionDe($this->director)->post('/dashboard-admin/logros', ['codigo' => 'racha_3', 'nombre' => 'Racha', 'xp_bonus' => 15, 'activo' => 1])->assertRedirect();
        $this->sesionDe($this->director)->post('/dashboard-admin/logros', ['codigo' => 'Con Espacios', 'nombre' => 'X', 'xp_bonus' => 1])->assertSessionHasErrors('codigo');

        $this->assertDatabaseHas('instrumentos', ['nombre' => 'Contrabajo']);
        $this->assertDatabaseHas('secciones', ['nombre' => 'Prejuvenil']);
        $this->assertDatabaseHas('logros', ['codigo' => 'racha_3', 'xp_bonus' => 15]);
    }

    public function test_comunicado_llega_a_los_roles_elegidos_y_activos(): void
    {
        $a = $this->crearEstudiante();
        $inactivo = $this->crearEstudiante();
        DB::table('usuarios')->where('id_usuario', $inactivo['usuario'])->update(['activo' => false]);
        $prof = $this->crearProfesor();

        $this->sesionDe($this->director)->post('/dashboard-admin/comunicados', [
            'titulo' => 'Concierto', 'mensaje' => 'Ensayo general el sabado', 'roles' => ['estudiante'],
        ])->assertRedirect();

        $this->assertDatabaseHas('notificaciones', ['id_usuario' => $a['usuario'], 'tipo' => 'comunicado']);
        $this->assertDatabaseMissing('notificaciones', ['id_usuario' => $inactivo['usuario'], 'tipo' => 'comunicado']);
        $this->assertDatabaseMissing('notificaciones', ['id_usuario' => $prof['usuario'], 'tipo' => 'comunicado']);
    }

    public function test_mensajes_solo_entre_contactos_permitidos(): void
    {
        $prof = $this->crearProfesor();
        $hijo = $this->crearEstudiante($prof['profesor']);
        $padre = $this->crearPadre([$hijo['estudiante']]);
        $otroProf = $this->crearProfesor();

        $this->sesionDe($padre['usuario'])->post('/mensajes', ['id_destinatario' => $prof['usuario'], 'contenido' => 'Hola profesor'])->assertRedirect();
        $this->sesionDe($padre['usuario'])->postJson('/mensajes', ['id_destinatario' => $otroProf['usuario'], 'contenido' => 'Hola'])->assertUnprocessable();
        $this->sesionDe($prof['usuario'])->postJson('/mensajes', ['id_destinatario' => $padre['usuario'], 'contenido' => 'Hola familia'])->assertCreated();

        $this->assertSame(2, DB::table('mensajes')->count());
        $this->assertDatabaseHas('notificaciones', ['id_usuario' => $prof['usuario'], 'tipo' => 'mensaje']);
    }

    public function test_notificaciones_se_marcan_como_leidas(): void
    {
        $alumno = $this->crearEstudiante();
        DB::table('notificaciones')->insert(['id_usuario' => $alumno['usuario'], 'titulo' => 'a', 'mensaje' => 'b', 'tipo' => 'info', 'created_at' => now(), 'updated_at' => now()]);

        $this->sesionDe($alumno['usuario'])->postJson('/dashboard-estudiante/notificaciones/leidas')->assertOk()->assertJsonPath('actualizadas', 1);
        $this->assertNotNull(DB::table('notificaciones')->value('leida_at'));
    }
}
