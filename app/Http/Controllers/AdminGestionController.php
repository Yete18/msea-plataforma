<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardService;
use App\Services\AuthService;
use App\Services\GestionUsuariosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Paginas de gestion del director: usuarios, padres, catalogos,
 * comunicados y bitacora.
 */
class AdminGestionController extends Controller
{
    public function usuarios(AdminDashboardService $admin, GestionUsuariosService $gestion)
    {
        return view('admin.usuarios', [
            'estudiantes' => $admin->estudiantesData(),
            'padres' => $gestion->padres(),
            'instrumentos' => DB::table('instrumentos')->orderBy('nombre')->get(),
            'secciones' => DB::table('secciones')->orderBy('id_seccion')->get(),
        ]);
    }

    public function updateUsuario(Request $request, int $idUsuario, GestionUsuariosService $gestion)
    {
        $request->merge(['correo' => AuthService::normalizarCorreo($request->input('correo'))]);
        $datos = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['nullable', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:100', Rule::unique('usuarios', 'correo')->ignore($idUsuario, 'id_usuario')],
            'celular' => ['nullable', 'string', 'max:20'],
            'ci' => ['nullable', 'string', 'max:20', Rule::unique('usuarios', 'ci')->ignore($idUsuario, 'id_usuario')],
            'direccion' => ['nullable', 'string', 'max:500'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'instrumento' => ['nullable', 'integer', 'exists:instrumentos,id_instrumento'],
            'id_seccion' => ['nullable', 'integer', 'exists:secciones,id_seccion'],
        ]);

        abort_unless(DB::table('usuarios')->where('id_usuario', $idUsuario)->exists(), 404);
        $gestion->editarUsuario((int) session('usuario_id'), $idUsuario, $datos);

        return back()->with('success', 'Datos actualizados.');
    }

    public function estadoUsuario(Request $request, int $idUsuario, GestionUsuariosService $gestion)
    {
        $datos = $request->validate(['activo' => ['required', 'boolean']]);
        abort_unless(DB::table('usuarios')->where('id_usuario', $idUsuario)->exists(), 404);

        $gestion->cambiarEstado((int) session('usuario_id'), $idUsuario, (bool) $datos['activo']);

        return back()->with('success', $datos['activo'] ? 'Cuenta reactivada.' : 'Cuenta desactivada. Su historial se conserva.');
    }

    public function recuperacionUsuario(int $idUsuario, GestionUsuariosService $gestion)
    {
        abort_unless(DB::table('usuarios')->where('id_usuario', $idUsuario)->exists(), 404);
        $gestion->enviarRecuperacion((int) session('usuario_id'), $idUsuario);

        return back()->with('success', 'Se envió un código de recuperación al correo del usuario.');
    }

    public function storePadre(Request $request, GestionUsuariosService $gestion)
    {
        $request->merge(['correo' => AuthService::normalizarCorreo($request->input('correo'))]);
        $datos = $request->validate([
            'nombres' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['nullable', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:100', 'unique:usuarios,correo'],
            'celular' => ['nullable', 'string', 'max:20'],
            'ci' => ['nullable', 'string', 'max:20', 'unique:usuarios,ci'],
            'parentesco' => ['nullable', 'string', 'max:50'],
            'contrasena' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'estudiantes' => ['nullable', 'array', 'max:10'],
            'estudiantes.*' => ['integer', 'exists:estudiantes,id_estudiante'],
        ]);

        $gestion->crearPadre((int) session('usuario_id'), $datos);

        return redirect('/dashboard-admin/usuarios#padres')->with('success', 'Cuenta de padre o tutor creada.');
    }

    public function vincularPadre(Request $request, int $idPadre, GestionUsuariosService $gestion)
    {
        $datos = $request->validate(['id_estudiante' => ['required', 'integer', 'exists:estudiantes,id_estudiante']]);
        $gestion->vincular((int) session('usuario_id'), $idPadre, (int) $datos['id_estudiante']);

        return back()->with('success', 'Estudiante vinculado.');
    }

    public function desvincularPadre(int $idPadre, int $idEstudiante, GestionUsuariosService $gestion)
    {
        $gestion->desvincular((int) session('usuario_id'), $idPadre, $idEstudiante);

        return back()->with('success', 'Vinculo eliminado.');
    }

    // --- Catalogos ---------------------------------------------------------

    public function catalogos()
    {
        return view('admin.catalogos', [
            'instrumentos' => DB::table('instrumentos as i')
                ->leftJoin('usuario_instrumento as ui', 'ui.id_instrumento', '=', 'i.id_instrumento')
                ->groupBy('i.id_instrumento', 'i.nombre')->orderBy('i.nombre')
                ->get(['i.id_instrumento', 'i.nombre', DB::raw('count(ui.id) as usos')]),
            'secciones' => DB::table('secciones as s')
                ->leftJoin('estudiantes as e', 'e.id_seccion', '=', 's.id_seccion')
                ->groupBy('s.id_seccion', 's.nombre')->orderBy('s.id_seccion')
                ->get(['s.id_seccion', 's.nombre', DB::raw('count(e.id_estudiante) as estudiantes')]),
            'logros' => DB::table('logros')->orderBy('id_logro')->get(),
        ]);
    }

    public function guardarInstrumento(Request $request, ?int $id = null)
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:50', Rule::unique('instrumentos', 'nombre')->ignore($id, 'id_instrumento')]]);
        $id ? DB::table('instrumentos')->where('id_instrumento', $id)->update($datos) : DB::table('instrumentos')->insert($datos);

        return redirect('/dashboard-admin/catalogos#instrumentos')->with('success', 'Instrumento guardado.');
    }

    public function guardarSeccion(Request $request, ?int $id = null)
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:50', Rule::unique('secciones', 'nombre')->ignore($id, 'id_seccion')]]);
        $id ? DB::table('secciones')->where('id_seccion', $id)->update($datos) : DB::table('secciones')->insert($datos);

        return redirect('/dashboard-admin/catalogos#niveles')->with('success', 'Nivel guardado.');
    }

    public function guardarLogro(Request $request, ?int $id = null)
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', Rule::unique('logros', 'codigo')->ignore($id, 'id_logro')],
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'xp_bonus' => ['required', 'integer', 'min:0', 'max:500'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $datos['activo'] = $request->boolean('activo', true);
        $datos['updated_at'] = now();

        if ($id) {
            DB::table('logros')->where('id_logro', $id)->update($datos);
        } else {
            DB::table('logros')->insert($datos + ['created_at' => now()]);
        }

        return redirect('/dashboard-admin/catalogos#logros')->with('success', 'Logro guardado. El bono se otorga una sola vez por estudiante.');
    }

    // --- Comunicados y bitacora -----------------------------------------

    public function comunicados()
    {
        return view('admin.comunicados', [
            'elencos' => DB::table('elencos')->orderBy('nombre')->get(['id_elenco', 'nombre']),
            'recientes' => DB::table('auditoria')->where('accion', 'comunicado_enviado')->orderByDesc('created_at')->limit(10)->get(),
        ]);
    }

    public function enviarComunicado(Request $request, GestionUsuariosService $gestion)
    {
        $datos = $request->validate([
            'titulo' => ['required', 'string', 'max:150'],
            'mensaje' => ['required', 'string', 'max:2000'],
            'roles' => ['required_without:id_elenco', 'array'],
            'roles.*' => ['in:estudiante,profesor,padre,director'],
            'id_elenco' => ['nullable', 'integer', 'exists:elencos,id_elenco'],
        ]);

        $total = $gestion->enviarComunicado((int) session('usuario_id'), $datos['titulo'], $datos['mensaje'], $datos['roles'] ?? [], $datos['id_elenco'] ?? null);

        return back()->with('success', "Comunicado enviado a {$total} usuario(s).");
    }

    public function auditoria(Request $request)
    {
        $accion = $request->query('accion');

        return view('admin.auditoria', [
            'registros' => DB::table('auditoria as a')
                ->leftJoin('usuarios as u', 'u.id_usuario', '=', 'a.id_usuario')
                ->when($accion, fn ($q) => $q->where('a.accion', $accion))
                ->orderByDesc('a.created_at')->orderByDesc('a.id')
                ->paginate(40, ['a.*', 'u.nombres', 'u.apellido_paterno', 'u.correo'])
                ->withQueryString(),
            'acciones' => DB::table('auditoria')->distinct()->orderBy('accion')->pluck('accion'),
            'accion' => $accion,
        ]);
    }
}
