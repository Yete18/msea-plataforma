<?php

namespace App\Http\Controllers;

use App\Services\MensajeService;
use Illuminate\Http\Request;

class MensajeController extends Controller
{
    public function index(MensajeService $mensajes)
    {
        $id = (int) session('usuario_id');
        $rol = (string) session('rol');

        return response()->json([
            'mensajes' => $mensajes->bandeja($id),
            'contactos' => $mensajes->contactos($id, $rol),
        ]);
    }

    public function store(Request $request, MensajeService $mensajes)
    {
        $validated = $request->validate([
            'id_destinatario' => ['required', 'integer'],
            'asunto' => ['nullable', 'string', 'max:150'],
            'contenido' => ['required', 'string', 'min:2', 'max:3000'],
        ]);

        $mensaje = $mensajes->enviar((int) session('usuario_id'), (string) session('rol'), (int) $validated['id_destinatario'], $validated['asunto'] ?? null, $validated['contenido']);

        if (! $request->expectsJson()) {
            return back()->with('success', 'Mensaje enviado.');
        }

        return response()->json(['message' => 'Mensaje enviado', 'id' => $mensaje->id_mensaje], 201);
    }

    public function markRead(MensajeService $mensajes)
    {
        return response()->json(['actualizados' => $mensajes->marcarLeidos((int) session('usuario_id'))]);
    }
}
