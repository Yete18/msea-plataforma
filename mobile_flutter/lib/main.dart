import 'package:flutter/material.dart';

import 'src/api/msea_api.dart';
import 'src/auth/google_auth.dart';
import 'src/screens/dashboard_screen.dart';
import 'src/screens/login_screen.dart';
import 'src/theme/msea_theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(MseaMobileApp(api: MseaApi()));
}

class MseaMobileApp extends StatefulWidget {
  const MseaMobileApp({super.key, required this.api});

  final MseaApi api;

  @override
  State<MseaMobileApp> createState() => _MseaMobileAppState();
}

class _MseaMobileAppState extends State<MseaMobileApp> {
  SessionData? _sesion;
  bool _restaurando = true;
  String? _aviso;

  @override
  void initState() {
    super.initState();
    _restaurar();
  }

  Future<void> _restaurar() async {
    try {
      final s = await widget.api.restaurarSesion();
      if (mounted) setState(() => _sesion = s);
    } on ApiException catch (e) {
      // Sin red: se pide iniciar sesion de nuevo, el token guardado sigue valido.
      if (mounted) setState(() => _aviso = e.message);
    } finally {
      if (mounted) setState(() => _restaurando = false);
    }
  }

  void _entro(SessionData s) => setState(() {
        _sesion = s;
        _aviso = null;
      });

  Future<void> _salir({bool todas = false, String? aviso}) async {
    await widget.api.logout(todas: todas);
    await GoogleAuth.cerrar();
    if (mounted) {
      setState(() {
        _sesion = null;
        _aviso = aviso;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    Widget home;
    if (_restaurando) {
      home = const Scaffold(body: Center(child: CircularProgressIndicator()));
    } else if (_sesion == null) {
      home = LoginScreen(key: ValueKey(_aviso), api: widget.api, onLoggedIn: _entro, aviso: _aviso);
    } else {
      home = DashboardScreen(
        api: widget.api,
        session: _sesion!,
        onLogout: ({bool todas = false}) => _salir(todas: todas),
        onSessionExpired: () => _salir(aviso: 'Tu sesión expiró. Inicia sesión nuevamente.'),
      );
    }

    return MaterialApp(
      title: 'MSEA',
      debugShowCheckedModeBanner: false,
      theme: MseaTheme.light(),
      home: home,
    );
  }
}
