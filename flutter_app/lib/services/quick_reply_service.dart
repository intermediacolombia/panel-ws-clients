import 'package:shared_preferences/shared_preferences.dart';

/// Gestiona las respuestas rápidas guardadas localmente.
class QuickReplyService {
  static const _key = 'quick_replies';

  static const List<String> _defaults = [
    'Hola, ¿en qué te puedo ayudar? 😊',
    'Un momento por favor, ya te atiendo.',
    'Claro, con mucho gusto te ayudo.',
    'Gracias por contactarnos.',
    '¿Tienes alguna otra consulta?',
    'Revisamos tu caso y te respondemos en breve.',
    'Perfecto, queda registrado. ✅',
  ];

  static Future<List<String>> getAll() async {
    final prefs = await SharedPreferences.getInstance();
    final stored = prefs.getStringList(_key);
    return stored ?? List<String>.from(_defaults);
  }

  static Future<void> save(List<String> replies) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setStringList(_key, replies);
  }

  static Future<void> add(String reply) async {
    final current = await getAll();
    current.insert(0, reply.trim());
    await save(current);
  }

  static Future<void> remove(int index) async {
    final current = await getAll();
    if (index >= 0 && index < current.length) {
      current.removeAt(index);
      await save(current);
    }
  }

  static Future<void> resetToDefaults() async {
    await save(_defaults);
  }
}
