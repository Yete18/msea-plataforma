<?php

use App\Services\ProfilePhotoService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

// ARQ-07: limpieza diaria de datos que vencen. No toca entregas, XP, practicas
// terminadas, mensajes ni la bitacora de auditoria.
Artisan::command('msea:limpiar', function () {
    $tokens = DB::table('mobile_api_tokens')->where('expires_at', '<', now()->subDays(7))->delete();
    $codigos = DB::table('password_reset_codes')->where('created_at', '<', now()->subDays(7))->delete();
    $avisos = DB::table('notificaciones')->whereNotNull('leida_at')->where('leida_at', '<', now()->subDays(180))->delete();
    $practicas = DB::table('practicas_autonomas')->where('estado', 'en_curso')
        ->where('iniciada_at', '<', now()->subMinutes((int) config('msea.practica.sesion_caduca_minutos', 120)))
        ->update(['estado' => 'caducada', 'updated_at' => now()]);
    $this->info("Tokens moviles vencidos: {$tokens}. Codigos de recuperacion: {$codigos}. Avisos leidos antiguos: {$avisos}. Practicas abandonadas: {$practicas}.");
})->purpose('Purga tokens vencidos, codigos de recuperacion y avisos leidos antiguos');

Schedule::command('msea:limpiar')->dailyAt('03:15')->withoutOverlapping();

// Mueve al disco privado las fotos de perfil que quedaron en el disco publico
// (antes de la auditoria 2026-10). Idempotente: se puede ejecutar varias veces.
Artisan::command('msea:fotos-privadas', function (ProfilePhotoService $fotos) {
    $movidas = 0;
    DB::table('usuarios')->whereNotNull('foto')->orderBy('id_usuario')
        ->each(function ($usuario) use ($fotos, &$movidas) {
            $antes = Storage::disk('public')->exists(ltrim(preg_replace('#^/?storage/#', '', (string) $usuario->foto), '/'));
            if ($fotos->rutaPrivada($usuario->foto) !== null && $antes) {
                $movidas++;
            }
        });
    $this->info("Fotos movidas al disco privado: {$movidas}");
})->purpose('Mueve las fotos de perfil al disco privado');
