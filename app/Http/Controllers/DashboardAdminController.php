<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignStudentToElencoRequest;
use App\Http\Requests\AssignStudentToProfesorRequest;
use App\Http\Requests\StoreElencoRequest;
use App\Http\Requests\StoreProfesorRequest;
use App\Http\Requests\UpdateDirectorProfileRequest;
use App\Http\Requests\UpdateElencoRequest;
use App\Models\Usuario;
use App\Services\AdminDashboardService;
use App\Services\AuditLogger;
use App\Services\CsvExporter;
use App\Services\CuentaSeguridadService;
use Illuminate\Validation\ValidationException;

class DashboardAdminController extends Controller
{
    public function show(AdminDashboardService $adminDashboardService)
    {
        return view('dashboard-admin', $adminDashboardService->viewData((int) session('usuario_id')));
    }

    public function profesores(AdminDashboardService $admin)
    {
        return view('admin.profesores', $admin->profesoresPagina());
    }

    public function perfil(AdminDashboardService $admin)
    {
        return view('admin.perfil', $admin->perfilPagina((int) session('usuario_id')));
    }

    public function downloadReport(string $tipo, AdminDashboardService $admin, CsvExporter $csv, AuditLogger $audit)
    {
        $report = $admin->reportData($tipo);

        abort_if($report === null, 404);
        $audit->registrar('reporte_descargado', (int) session('usuario_id'), ['tipo' => $tipo]);

        return $csv->descargar($report['filename'], $report['headers'], $report['rows']);
    }

    public function updateProfile(UpdateDirectorProfileRequest $request, AdminDashboardService $admin, CuentaSeguridadService $cuenta)
    {
        $idUsuario = (int) session('usuario_id');
        $validated = $request->validated();
        $anterior = (string) Usuario::query()->whereKey($idUsuario)->value('correo');
        $cambia = $cuenta->exigirReautenticacionSiCambiaCorreo($idUsuario, $validated['correo'], $validated['contrasena_actual'] ?? null);

        $admin->updateProfile($idUsuario, $validated);

        if ($cambia) {
            $cuenta->despuesDeCambiarCorreo($idUsuario, $anterior, $validated['correo']);
        }

        return redirect('/dashboard-admin/perfil')->with('success', 'Perfil del director actualizado correctamente.');
    }

    public function storeProfesor(StoreProfesorRequest $request, AdminDashboardService $admin, AuditLogger $audit)
    {
        if (! $admin->storeProfesor($request->validated())) {
            return back()->withInput()->with('error', 'No existe el rol profesor en la base de datos.');
        }

        $audit->registrar('profesor_creado', (int) session('usuario_id'), ['correo' => AuditLogger::enmascararCorreo($request->validated('correo'))]);

        return redirect('/dashboard-admin/profesores')->with('success', 'Profesor registrado correctamente.');
    }

    public function storeElenco(StoreElencoRequest $request, AdminDashboardService $admin)
    {
        $admin->storeElenco($request->validated());

        return redirect('/dashboard-admin#elencos')->with('success', 'Elenco registrado correctamente.');
    }

    public function updateElenco(UpdateElencoRequest $request, int $idElenco, AdminDashboardService $admin)
    {
        $admin->updateElenco($idElenco, $request->validated());

        return redirect('/dashboard-admin#elencos')->with('success', 'Elenco actualizado correctamente.');
    }

    public function destroyElenco(int $idElenco, AdminDashboardService $admin)
    {
        try {
            $admin->destroyElenco($idElenco);
        } catch (ValidationException $e) {
            return redirect('/dashboard-admin#elencos')->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect('/dashboard-admin#elencos')->with('success', 'Elenco eliminado. Sus estudiantes quedaron sin elenco asignado.');
    }

    public function assignStudentToElenco(AssignStudentToElencoRequest $request, AdminDashboardService $admin)
    {
        $validated = $request->validated();
        $admin->assignStudentToElenco((int) $validated['id_estudiante'], $validated['id_elenco'] ?? null);

        return redirect('/dashboard-admin#asignaciones')->with('success', 'Estudiante asignado al elenco.');
    }

    public function assignStudentToProfesor(AssignStudentToProfesorRequest $request, AdminDashboardService $admin)
    {
        $validated = $request->validated();
        $admin->assignStudentToProfesor((int) $validated['id_estudiante'], $validated['id_profesor'] ?? null);

        return redirect('/dashboard-admin#asignaciones')->with('success', 'Profesor asignado correctamente.');
    }
}
