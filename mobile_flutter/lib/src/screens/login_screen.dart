import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../api/msea_api.dart';
import '../auth/google_auth.dart';
import '../auth/google_boton.dart';
import '../config.dart';
import '../theme/msea_theme.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.api, required this.onLoggedIn, this.aviso});

  final MseaApi api;
  final ValueChanged<SessionData> onLoggedIn;

  /// Mensaje opcional (por ejemplo, "Tu sesion expiro").
  final String? aviso;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _form = GlobalKey<FormState>();
  // Sin credenciales precargadas: cada persona escribe las suyas.
  final _correo = TextEditingController();
  final _contrasena = TextEditingController();
  bool _cargando = false;
  bool _ocultar = true;
  String? _error;

  StreamSubscription<String>? _googleSub;
  bool _googleListo = false;

  @override
  void initState() {
    super.initState();
    _error = MseaConfig.errorConfiguracion ?? widget.aviso;
    if (GoogleAuth.disponible) _prepararGoogle();
  }

  Future<void> _prepararGoogle() async {
    try {
      await GoogleAuth.iniciar();
      _googleSub = GoogleAuth.idTokens.listen(
        _entrarConGoogle,
        onError: (Object _) {
          if (mounted) setState(() => _error = 'No se pudo completar el inicio de sesión con Google.');
        },
      );
      if (mounted) setState(() => _googleListo = true);
    } catch (_) {
      if (mounted) setState(() => _error = 'No se pudo cargar el inicio de sesión con Google.');
    }
  }

  Future<void> _entrarConGoogle(String idToken) async {
    if (_cargando) return;
    setState(() {
      _cargando = true;
      _error = null;
    });
    try {
      final sesion = await widget.api.loginGoogle(idToken);
      widget.onLoggedIn(sesion);
    } on ApiException catch (e) {
      await GoogleAuth.cerrar(); // permite probar con otra cuenta
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  Future<void> _botonGooglePropio() async {
    try {
      await GoogleAuth.autenticar(); // el id_token llega por GoogleAuth.idTokens
    } catch (_) {
      // La persona cerro la ventana de Google: no es un error.
    }
  }

  @override
  void dispose() {
    _googleSub?.cancel();
    _correo.dispose();
    _contrasena.dispose();
    super.dispose();
  }

  Widget _seccionGoogle() {
    if (!GoogleAuth.disponible || !_googleListo) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 16),
        const Row(children: [
          Expanded(child: Divider()),
          Padding(padding: EdgeInsets.symmetric(horizontal: 10), child: Text('o', style: TextStyle(color: MseaColors.muted, fontWeight: FontWeight.w700))),
          Expanded(child: Divider()),
        ]),
        const SizedBox(height: 12),
        if (kIsWeb || !GoogleAuth.usaBotonPropio)
          Center(child: botonGoogleWeb())
        else
          OutlinedButton.icon(
            onPressed: _cargando ? null : _botonGooglePropio,
            icon: const Icon(Icons.account_circle_outlined),
            label: const Text('Continuar con Google'),
            style: OutlinedButton.styleFrom(minimumSize: const Size(48, 48)),
          ),
        const SizedBox(height: 6),
        const Text(
          'Usa la cuenta de Google con el mismo correo registrado en MSEA.',
          textAlign: TextAlign.center,
          style: TextStyle(color: MseaColors.muted, fontSize: 12),
        ),
      ],
    );
  }

  Future<void> _entrar() async {
    if (_cargando || !(_form.currentState?.validate() ?? false)) return;
    setState(() {
      _cargando = true;
      _error = null;
    });
    try {
      final sesion = await widget.api.login(email: _correo.text, password: _contrasena.text);
      _contrasena.clear();
      widget.onLoggedIn(sesion);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 460),
            child: ListView(
              padding: const EdgeInsets.all(24),
              shrinkWrap: true,
              children: [
                Container(
                  padding: const EdgeInsets.all(22),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(colors: [MseaColors.greenDark, MseaColors.text]),
                    borderRadius: BorderRadius.circular(22),
                  ),
                  child: const Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(Icons.music_note_rounded, color: MseaColors.greenLight, size: 36),
                      SizedBox(height: 12),
                      Text('MSEA', style: TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900)),
                      Text('Movimiento Sinfónico de El Alto', style: TextStyle(color: Colors.white70, fontWeight: FontWeight.w700)),
                    ],
                  ),
                ),
                const SizedBox(height: 24),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(20),
                    child: Form(
                      key: _form,
                      child: AutofillGroup(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            const Text('Iniciar sesión', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900)),
                            const SizedBox(height: 16),
                            TextFormField(
                              controller: _correo,
                              keyboardType: TextInputType.emailAddress,
                              autofillHints: const [AutofillHints.email],
                              textInputAction: TextInputAction.next,
                              decoration: const InputDecoration(labelText: 'Correo electrónico', prefixIcon: Icon(Icons.mail_outline)),
                              validator: (v) {
                                final t = (v ?? '').trim();
                                if (t.isEmpty) return 'Escribe tu correo electrónico.';
                                if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(t)) return 'El correo no es válido.';
                                return null;
                              },
                            ),
                            const SizedBox(height: 12),
                            TextFormField(
                              controller: _contrasena,
                              obscureText: _ocultar,
                              autofillHints: const [AutofillHints.password],
                              onFieldSubmitted: (_) => _entrar(),
                              decoration: InputDecoration(
                                labelText: 'Contraseña',
                                prefixIcon: const Icon(Icons.lock_outline),
                                suffixIcon: IconButton(
                                  tooltip: _ocultar ? 'Mostrar contraseña' : 'Ocultar contraseña',
                                  icon: Icon(_ocultar ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                                  onPressed: () => setState(() => _ocultar = !_ocultar),
                                ),
                              ),
                              validator: (v) => (v ?? '').isEmpty ? 'Escribe tu contraseña.' : null,
                            ),
                            if (_error != null) ...[
                              const SizedBox(height: 14),
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: MseaColors.red.withValues(alpha: .1),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Text(_error!, style: const TextStyle(color: MseaColors.red, fontWeight: FontWeight.w700)),
                              ),
                            ],
                            const SizedBox(height: 18),
                            FilledButton.icon(
                              onPressed: _cargando || MseaConfig.errorConfiguracion != null ? null : _entrar,
                              icon: _cargando
                                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                                  : const Icon(Icons.login),
                              label: Text(_cargando ? 'Ingresando...' : 'Ingresar'),
                            ),
                            _seccionGoogle(),
                            const SizedBox(height: 10),
                            const Text(
                              '¿Olvidaste tu contraseña? Recupérala desde la página web de MSEA.',
                              style: TextStyle(color: MseaColors.muted),
                              textAlign: TextAlign.center,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
