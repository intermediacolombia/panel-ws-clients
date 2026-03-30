import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import '../core/constants.dart';

class ApiService {
  static String? _token;

  /// Carga el token desde disco al iniciar la app.
  static Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString('agent_token');
  }

  /// Guarda el token en memoria y disco.
  static Future<void> setToken(String token) async {
    _token = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('agent_token', token);
  }

  /// Persiste los datos del agente.
  static Future<void> saveAgent(Map<String, dynamic> agentJson) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('agent_data', jsonEncode(agentJson));
  }

  /// Recupera los datos del agente guardados.
  static Future<Map<String, dynamic>?> loadAgent() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString('agent_data');
    if (raw == null) return null;
    try {
      return jsonDecode(raw) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  /// Borra token y datos locales.
  static Future<void> clearAll() async {
    _token = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('agent_token');
    await prefs.remove('agent_data');
  }

  static bool   get hasToken => _token != null && _token!.isNotEmpty;
  static String? get token   => _token;

  static Map<String, String> get headers => {
    'Content-Type': 'application/json; charset=utf-8',
    if (_token != null) 'Authorization': 'Bearer $_token',
  };

  static Future<Map<String, dynamic>> get(
    String url, {
    Map<String, String>? params,
  }) async {
    Uri uri = Uri.parse(url);
    if (params != null) uri = uri.replace(queryParameters: params);
    try {
      final res = await http
          .get(uri, headers: headers)
          .timeout(ApiConstants.requestTimeout);
      return _parse(res);
    } catch (e) {
      return {'success': false, 'error': 'Error de conexión: $e'};
    }
  }

  static Future<Map<String, dynamic>> post(
    String url,
    Map<String, dynamic> body,
  ) async {
    try {
      final res = await http
          .post(Uri.parse(url), headers: headers, body: jsonEncode(body))
          .timeout(ApiConstants.requestTimeout);
      return _parse(res);
    } catch (e) {
      return {'success': false, 'error': 'Error de conexión: $e'};
    }
  }

  static Map<String, dynamic> _parse(http.Response res) {
    try {
      return jsonDecode(utf8.decode(res.bodyBytes)) as Map<String, dynamic>;
    } catch (_) {
      return {'success': false, 'error': 'Respuesta inválida (${res.statusCode})'};
    }
  }
}
