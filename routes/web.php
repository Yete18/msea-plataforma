<?php

use App\Http\Controllers\AdminCursoController;
use App\Http\Controllers\AdminGestionController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CursoEstudianteController;
use App\Http\Controllers\DashboardAdminController;
use App\Http\Controllers\DashboardEstudianteController;
use App\Http\Controllers\DashboardPadreController;
use App\Http\Controllers\DashboardProfesorController;
use App\Http\Controllers\DigitalizadorController;
use App\Http\Controllers\EscalaController;
use App\Http\Controllers\MensajeController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PublicoController;
use Illuminate\Support\Facades\Route;

Route::pattern('idTarea', '[0-9]+');
Route::pattern('idEntrega', '[0-9]+');
Route::pattern('idEstudiante', '[0-9]+');
Route::pattern('idPractica', '[0-9]+');
Route::pattern('idTeoria', '[0-9]+');
Route::pattern('idCurso', '[0-9]+');
Route::pattern('idModulo', '[0-9]+');
Route::pattern('idLeccion', '[0-9]+');
Route::pattern('idUsuario', '[0-9]+');
Route::pattern('idPadre', '[0-9]+');
Route::pattern('idElenco', '[0-9]+');
Route::pattern('id', '[0-9]+');

Route::view('/', 'index');
Route::redirect('/index', '/');

// Textos legales, sitemap y robots (publicos, indexables).
Route::get('/aviso-legal', [PublicoController::class, 'avisoLegal']);
Route::get('/politica-de-privacidad', [PublicoController::class, 'privacidad']);
Route::get('/politica-de-cookies', [PublicoController::class, 'cookies']);
Route::get('/sitemap.xml', [PublicoController::class, 'sitemap']);
Route::get('/robots.txt', [PublicoController::class, 'robots']);
Route::view('/login', 'login')->middleware('invitado');
Route::post('/login', [AuthController::class, 'login'])->middleware(['throttle:login', 'antispam']);
Route::post('/auth/google', [AuthController::class, 'loginGoogle'])->middleware('throttle:login');
Route::view('/registro', 'registro')->middleware('invitado');

// Token CSRF fresco para formularios abiertos mucho tiempo (msea-ui.js lo usa al recibir un 419).
Route::get('/csrf-token', fn () => response()->json(['token' => csrf_token()]))->middleware('throttle:60,1');
Route::post('/registro', [AuthController::class, 'register'])->middleware(['throttle:login', 'antispam']);

Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm']);
Route::post('/forgot-password', [PasswordResetController::class, 'sendCode'])->middleware(['throttle:password-reset', 'antispam']);
Route::get('/verify-code', [PasswordResetController::class, 'showCodeForm']);
Route::post('/verify-code', [PasswordResetController::class, 'verifyCode'])->middleware('throttle:reset-code');
Route::get('/reset-password', [PasswordResetController::class, 'showResetForm']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:reset-code');

Route::middleware('auth.session')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Archivos privados: el controlador verifica la relacion con la entrega o tarea.
    Route::get('/archivos/entregas/{idEntrega}', [ArchivoController::class, 'entrega']);
    Route::get('/archivos/tareas/{idTarea}', [ArchivoController::class, 'materialTarea']);
    Route::get('/archivos/fotos/{idUsuario}', [ArchivoController::class, 'foto']);

    // Mensajes internos (permisos en MensajeService).
    Route::middleware('role:estudiante,profesor,padre,director')->group(function () {
        Route::get('/mensajes', [MensajeController::class, 'index']);
        Route::post('/mensajes', [MensajeController::class, 'store'])->middleware('throttle:sensible');
        Route::post('/mensajes/leidos', [MensajeController::class, 'markRead']);
    });

    Route::middleware('role:estudiante')->group(function () {
        Route::get('/dashboard-estudiante', [DashboardEstudianteController::class, 'show']);
        Route::post('/dashboard-estudiante/perfil', [DashboardEstudianteController::class, 'updateProfile']);
        Route::post('/dashboard-estudiante/contrasena', [DashboardEstudianteController::class, 'changePassword'])->middleware('throttle:sensible');
        Route::post('/dashboard-estudiante/practicas', [DashboardEstudianteController::class, 'startPractice']);
        Route::post('/dashboard-estudiante/practicas/{idPractica}/finalizar', [DashboardEstudianteController::class, 'finishPractice']);
        Route::post('/dashboard-estudiante/teoria/{idTeoria}/responder', [DashboardEstudianteController::class, 'answerTheory']);
        Route::post('/dashboard-estudiante/tareas/{idTarea}/entregar', [DashboardEstudianteController::class, 'submitTask']);
        Route::get('/dashboard-estudiante/tareas/{idTarea}/escala', [EscalaController::class, 'practicar']);
        Route::post('/dashboard-estudiante/tareas/{idTarea}/escala', [EscalaController::class, 'entregar'])->middleware('throttle:20,1');
        Route::post('/dashboard-estudiante/meta-diaria', [DashboardEstudianteController::class, 'guardarMetaDiaria']);
        Route::post('/dashboard-estudiante/notificaciones/leidas', [DashboardEstudianteController::class, 'markNotificationsRead']);
        Route::get('/dashboard-estudiante/cursos/{idCurso}', [CursoEstudianteController::class, 'show']);
        Route::post('/dashboard-estudiante/lecciones/{idLeccion}/completar', [CursoEstudianteController::class, 'completeLesson']);
    });

    Route::middleware('role:profesor')->group(function () {
        Route::get('/dashboard-profesor', [DashboardProfesorController::class, 'show']);
        Route::post('/dashboard-profesor/perfil', [DashboardProfesorController::class, 'updateProfile']);
        Route::post('/dashboard-profesor/contrasena', [DashboardProfesorController::class, 'changePassword'])->middleware('throttle:sensible');
        Route::post('/dashboard-profesor/tareas', [DashboardProfesorController::class, 'storeTask']);
        Route::post('/dashboard-profesor/tareas/{idTarea}', [DashboardProfesorController::class, 'updateTask']);
        Route::delete('/dashboard-profesor/tareas/{idTarea}', [DashboardProfesorController::class, 'destroyTask']);
        Route::get('/dashboard-profesor/tareas/{idTarea}/entregas', [DashboardProfesorController::class, 'taskSubmissions']);
        Route::post('/dashboard-profesor/entregas/{idEntrega}/calificar', [DashboardProfesorController::class, 'gradeTask']);
        Route::post('/dashboard-profesor/entregas/{idEntrega}/devolver', [DashboardProfesorController::class, 'returnTask']);
        Route::get('/dashboard-profesor/alumnos/{idEstudiante}/progreso', [DashboardProfesorController::class, 'studentProgress']);
    });

    // Biblioteca técnica: solo profesores y director (los estudiantes la ven a través de sus tareas).
    Route::middleware('role:profesor,director')->group(function () {
        Route::get('/biblioteca/escalas/{id}/probar', [EscalaController::class, 'probar']);
        Route::post('/biblioteca/escalas/{id}/vista-previa', [EscalaController::class, 'vistaPrevia']);
        Route::get('/biblioteca/ejercicios/{id}/probar', [EscalaController::class, 'probarTecnica']);
    });

    Route::middleware('role:padre')->group(function () {
        Route::get('/dashboard-padre', [DashboardPadreController::class, 'show']);
        Route::get('/dashboard-padre/hijos/{idEstudiante}/reporte', [DashboardPadreController::class, 'report']);
    });

    Route::middleware('role:director')->prefix('dashboard-admin')->group(function () {
        Route::get('/', [DashboardAdminController::class, 'show']);
        Route::get('/reportes/{tipo}', [DashboardAdminController::class, 'downloadReport'])->where('tipo', '[a-z]+');
        Route::get('/perfil', [DashboardAdminController::class, 'perfil']);
        Route::post('/perfil', [DashboardAdminController::class, 'updateProfile']);
        Route::get('/profesores', [DashboardAdminController::class, 'profesores']);
        Route::post('/profesores', [DashboardAdminController::class, 'storeProfesor']);
        Route::post('/elencos', [DashboardAdminController::class, 'storeElenco']);
        Route::put('/elencos/{idElenco}', [DashboardAdminController::class, 'updateElenco']);
        Route::delete('/elencos/{idElenco}', [DashboardAdminController::class, 'destroyElenco']);
        Route::post('/elencos/asignar-estudiante', [DashboardAdminController::class, 'assignStudentToElenco']);
        Route::post('/profesores/asignar-estudiante', [DashboardAdminController::class, 'assignStudentToProfesor']);

        // Usuarios, padres y vinculos
        Route::get('/usuarios', [AdminGestionController::class, 'usuarios']);
        Route::put('/usuarios/{idUsuario}', [AdminGestionController::class, 'updateUsuario']);
        Route::post('/usuarios/{idUsuario}/estado', [AdminGestionController::class, 'estadoUsuario']);
        Route::post('/usuarios/{idUsuario}/recuperacion', [AdminGestionController::class, 'recuperacionUsuario'])->middleware('throttle:sensible');
        Route::post('/padres', [AdminGestionController::class, 'storePadre']);
        Route::post('/padres/{idPadre}/vincular', [AdminGestionController::class, 'vincularPadre']);
        Route::delete('/padres/{idPadre}/estudiantes/{idEstudiante}', [AdminGestionController::class, 'desvincularPadre']);

        // Catalogos
        Route::get('/catalogos', [AdminGestionController::class, 'catalogos']);
        Route::post('/instrumentos/{id?}', [AdminGestionController::class, 'guardarInstrumento']);
        Route::post('/niveles/{id?}', [AdminGestionController::class, 'guardarSeccion']);
        Route::post('/logros/{id?}', [AdminGestionController::class, 'guardarLogro']);

        // Comunicados y bitacora
        Route::get('/comunicados', [AdminGestionController::class, 'comunicados']);
        Route::post('/comunicados', [AdminGestionController::class, 'enviarComunicado']);
        Route::get('/auditoria', [AdminGestionController::class, 'auditoria']);

        // Biblioteca técnica (escalas de violín)
        Route::get('/biblioteca', [EscalaController::class, 'biblioteca']);
        Route::post('/biblioteca/escalas/{id?}', [EscalaController::class, 'guardar']);
        Route::post('/biblioteca/escalas/{id}/estado', [EscalaController::class, 'estado']);

        // Digitalizador de métodos en PDF
        Route::get('/digitalizador', [DigitalizadorController::class, 'index']);
        Route::post('/digitalizador', [DigitalizadorController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/digitalizador/{id}', [DigitalizadorController::class, 'show']);
        Route::get('/digitalizador/{id}/pdf', [DigitalizadorController::class, 'pdf']);
        Route::get('/digitalizador/{id}/paginas/{pagina}/imagen', [DigitalizadorController::class, 'imagen'])->whereNumber('pagina');
        Route::post('/digitalizador/{id}/paginas/{idPagina}/ejercicios/{indice}', [DigitalizadorController::class, 'guardarEjercicio'])->whereNumber(['idPagina', 'indice']);
        Route::post('/digitalizador/{id}/paginas/{idPagina}/ejercicios', [DigitalizadorController::class, 'agregarEjercicio'])->whereNumber('idPagina');
        Route::post('/digitalizador/{id}/reprocesar', [DigitalizadorController::class, 'reprocesar']);
        Route::delete('/digitalizador/{id}', [DigitalizadorController::class, 'destroy']);

        // Cursos -> modulos -> lecciones -> preguntas
        Route::get('/cursos', [AdminCursoController::class, 'index']);
        Route::post('/cursos', [AdminCursoController::class, 'store']);
        Route::get('/cursos/{idCurso}', [AdminCursoController::class, 'show']);
        Route::put('/cursos/{idCurso}', [AdminCursoController::class, 'update']);
        Route::post('/cursos/{idCurso}/modulos', [AdminCursoController::class, 'storeModulo']);
        Route::put('/cursos/{idCurso}/modulos/{idModulo}', [AdminCursoController::class, 'updateModulo']);
        Route::delete('/cursos/{idCurso}/modulos/{idModulo}', [AdminCursoController::class, 'destroyModulo']);
        Route::post('/cursos/{idCurso}/modulos/{idModulo}/lecciones', [AdminCursoController::class, 'storeLeccion']);
        Route::put('/cursos/{idCurso}/modulos/{idModulo}/lecciones/{idLeccion}', [AdminCursoController::class, 'updateLeccion']);
        Route::delete('/cursos/{idCurso}/modulos/{idModulo}/lecciones/{idLeccion}', [AdminCursoController::class, 'destroyLeccion']);
        Route::post('/preguntas/{idTeoria?}', [AdminCursoController::class, 'storePregunta']);
        Route::post('/cursos/{idCurso}/inscripciones', [AdminCursoController::class, 'inscribir']);
        Route::delete('/cursos/{idCurso}/inscripciones/{idEstudiante}', [AdminCursoController::class, 'desinscribir']);
    });
});
