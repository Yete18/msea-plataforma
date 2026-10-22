<?php

use App\Services\GamificationService;

// Datos con cargas XSS para tests/e2e/xss.cjs. Ejecutar SOLO en una base de pruebas.
$prof = DB::table('profesores')->value('id_profesor');
$rol = DB::table('roles')->where('nombre', 'estudiante')->value('id_rol');
$u = DB::table('usuarios')->insertGetId(['correo' => 'xss@msea.test', 'contrasena' => Hash::make('secret123'), 'nombres' => '<img src=x onerror="window.__xss=1">', 'apellido_paterno' => '<svg onload=window.__xss=2>', 'id_rol' => $rol], 'id_usuario');
$e = DB::table('estudiantes')->insertGetId(['id_usuario' => $u, 'id_profesor' => $prof, 'id_seccion' => DB::table('secciones')->value('id_seccion')], 'id_estudiante');
app(GamificationService::class)->otorgarXp($e, 999, 'tarea', 'xss:1', '<img src=x onerror=window.__xss=3>');
$t = DB::table('tareas')->insertGetId(['titulo' => '<script>window.__xss=4</script>Tarea', 'id_profesor' => $prof, 'id_estudiante' => $e, 'xp_recompensa' => 10, 'estado' => 'pendiente', 'fecha_creacion' => now()], 'id_tarea');
DB::table('entregas_tareas')->insert(['id_tarea' => $t, 'id_estudiante' => $e, 'estado' => 'entregada', 'comentario_estudiante' => '<img src=x onerror=window.__xss=5>', 'created_at' => now(), 'updated_at' => now()]);
DB::table('notificaciones')->insert(['id_usuario' => DB::table('profesores')->value('id_usuario'), 'titulo' => '<b onmouseover=x>t</b>', 'mensaje' => '<img src=x onerror=window.__xss=6>', 'tipo' => 'tarea', 'created_at' => now(), 'updated_at' => now()]);
echo "Datos XSS creados\n";
