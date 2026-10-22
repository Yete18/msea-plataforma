<?php

use App\Http\Controllers\Api\MobileController;
use Illuminate\Support\Facades\Route;

Route::prefix('mobile')->group(function () {
    Route::post('/login', [MobileController::class, 'login'])->middleware('throttle:mobile-login');
    Route::post('/login/google', [MobileController::class, 'loginGoogle'])->middleware('throttle:mobile-login');

    Route::middleware(['auth.mobile', 'throttle:mobile-api'])->group(function () {
        Route::get('/me', [MobileController::class, 'me']);
        Route::get('/notificaciones', [MobileController::class, 'notifications']);
        Route::post('/notificaciones/leidas', [MobileController::class, 'markNotificationsRead']);
        Route::get('/sesiones', [MobileController::class, 'sessions']);
        Route::delete('/sesiones/{idToken}', [MobileController::class, 'revokeSession'])->whereNumber('idToken');
        Route::post('/logout', [MobileController::class, 'logout']);
        Route::post('/logout-todo', [MobileController::class, 'logoutAll']);

        Route::middleware('auth.mobile:estudiante')->group(function () {
            Route::get('/tareas', [MobileController::class, 'tasks']);
            Route::post('/tareas/{idTarea}/entregar', [MobileController::class, 'submitTask'])->whereNumber('idTarea');
            Route::post('/practicas', [MobileController::class, 'startPractice']);
            Route::post('/practicas/{idPractica}/finalizar', [MobileController::class, 'finishPractice'])->whereNumber('idPractica');
        });
    });
});
