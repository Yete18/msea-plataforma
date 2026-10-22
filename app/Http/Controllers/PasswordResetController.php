<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\VerifyResetCodeRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;

class PasswordResetController extends Controller
{
    public function showRequestForm()
    {
        return view('forgot-password');
    }

    public function sendCode(ForgotPasswordRequest $request, PasswordResetService $passwordResetService)
    {
        $validated = $request->validated();

        $correo = strtolower($validated['correo']);

        $request->session()->put('password_reset_correo', $correo);
        $request->session()->forget(['password_reset_verified', 'password_reset_code_id']);

        $passwordResetService->enviarCodigoSiUsuarioExiste($correo);

        return redirect('/verify-code')
            ->with('success', 'Si el correo existe, enviamos un código de verificación.');
    }

    public function showCodeForm(Request $request)
    {
        if (! $request->session()->has('password_reset_correo')) {
            return redirect('/forgot-password');
        }

        return view('verify-code', [
            'correo' => $request->session()->get('password_reset_correo'),
        ]);
    }

    public function verifyCode(VerifyResetCodeRequest $request, PasswordResetService $passwordResetService)
    {
        $validated = $request->validated();

        $correo = $request->session()->get('password_reset_correo');

        if (! $correo) {
            return redirect('/forgot-password');
        }

        $codigoId = $passwordResetService->verificarCodigo($correo, $validated['codigo']);

        if ($codigoId) {
            $request->session()->put('password_reset_verified', true);
            $request->session()->put('password_reset_code_id', $codigoId);

            return redirect('/reset-password');
        }

        return back()->with('error', 'El código no es válido o ya expiró.');
    }

    public function showResetForm(Request $request)
    {
        if (! $request->session()->get('password_reset_verified')) {
            return redirect('/forgot-password');
        }

        return view('reset-password');
    }

    public function resetPassword(ResetPasswordRequest $request, PasswordResetService $passwordResetService)
    {
        if (! $request->session()->get('password_reset_verified')) {
            return redirect('/forgot-password');
        }

        $validated = $request->validated();

        $correo = $request->session()->get('password_reset_correo');
        $codigoId = $request->session()->get('password_reset_code_id');

        if (! $passwordResetService->resetPassword($correo, (int) $codigoId, $validated['contrasena'])) {
            return redirect('/forgot-password')->with('error', 'El código no es válido o ya fue usado.');
        }

        $request->session()->forget([
            'password_reset_correo',
            'password_reset_verified',
            'password_reset_code_id',
        ]);

        return redirect('/login')->with('success', 'Contraseña actualizada. Ya puedes iniciar sesión.');
    }
}
