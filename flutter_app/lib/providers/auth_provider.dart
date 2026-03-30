import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import '../models/agent.dart';
import '../services/api_service.dart';
import '../core/constants.dart';

class AuthProvider extends ChangeNotifier {
  Agent? _agent;
  bool _isLoading = true;
  String? _error;

  Agent? get agent   => _agent;
  bool get isLoggedIn => _agent != null;
  bool get isLoading  => _isLoading;
  String? get error   => _error;

  /// Llamar al arrancar la app: restaura sesión si hay token guardado.
  Future<void> init() async {
    await ApiService.init();
    if (ApiService.hasToken) {
      final agentData = await ApiService.loadAgent();
      if (agentData != null) {
        // Verificar que el token aún sea válido
        final res = await ApiService.get(
          ApiConstants.conversationsUrl,
          params: {'limit': '1'},
        );
        if (res['success'] == true) {
          _agent = Agent.fromJson(agentData);
          _registerFcmToken(); // re-registrar en cada arranque de app
        } else {
          await ApiService.clearAll();
        }
      } else {
        await ApiService.clearAll();
      }
    }
    _isLoading = false;
    notifyListeners();
  }

  /// Inicia sesión con usuario y contraseña.
  Future<bool> login(String username, String password) async {
    _error = null;
    notifyListeners();

    final res = await ApiService.post(ApiConstants.loginUrl, {
      'username': username.trim(),
      'password': password,
    });

    if (res['success'] == true) {
      await ApiService.setToken(res['token'] as String);
      final agentJson = res['agent'] as Map<String, dynamic>;
      await ApiService.saveAgent(agentJson);
      _agent = Agent.fromJson(agentJson);
      notifyListeners();
      _registerFcmToken(); // sin await — no bloquea el login
      return true;
    }

    _error = res['error'] as String? ?? 'Error al iniciar sesión.';
    notifyListeners();
    return false;
  }

  /// Registra el token FCM del dispositivo en el servidor.
  Future<void> _registerFcmToken() async {
    try {
      final messaging = FirebaseMessaging.instance;

      final settings = await messaging.requestPermission();
      debugPrint('[FCM] Permiso: ${settings.authorizationStatus}');

      final fcmToken = await messaging.getToken();
      debugPrint('[FCM] Token: $fcmToken');

      if (fcmToken == null) {
        debugPrint('[FCM] getToken() devolvió null — verifica que el package name '
            'en Firebase coincide con el del APK y que google-services.json es correcto.');
        return;
      }

      final res = await ApiService.post(
          ApiConstants.fcmTokenUrl, {'fcm_token': fcmToken});
      debugPrint('[FCM] Registro en servidor: $res');

      messaging.onTokenRefresh.listen((newToken) {
        debugPrint('[FCM] Token renovado: $newToken');
        ApiService.post(ApiConstants.fcmTokenUrl, {'fcm_token': newToken});
      });
    } catch (e, st) {
      debugPrint('[FCM] Error al registrar token: $e\n$st');
    }
  }

  /// Cierra sesión (invalida el token en el servidor).
  Future<void> logout() async {
    await ApiService.post(ApiConstants.logoutUrl, {});
    await ApiService.clearAll();
    _agent = null;
    notifyListeners();
  }
}
