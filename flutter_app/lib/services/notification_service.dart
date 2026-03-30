import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:permission_handler/permission_handler.dart';

/// Gestiona notificaciones locales (foreground).
/// Para notificaciones en background/closed se requiere FCM (Firebase).
class NotificationService {
  static final _plugin = FlutterLocalNotificationsPlugin();
  static bool _initialized = false;

  // IDs de notificaciones ya mostradas para evitar duplicados
  static final Set<String> _shownKeys = {};

  static Future<void> init() async {
    if (_initialized) return;

    const android = AndroidInitializationSettings('@mipmap/ic_launcher');
    const ios = DarwinInitializationSettings(
      requestSoundPermission: true,
      requestBadgePermission: true,
      requestAlertPermission: true,
    );

    await _plugin.initialize(
      const InitializationSettings(android: android, iOS: ios),
    );

    // Crear canal de notificaciones en Android
    const channel = AndroidNotificationChannel(
      'panel_agentes_messages',
      'Mensajes',
      description: 'Nuevos mensajes de conversaciones WhatsApp',
      importance: Importance.high,
      playSound: true,
    );

    await _plugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    _initialized = true;
  }

  /// Solicita permiso de notificaciones (Android 13+, iOS).
  static Future<void> requestPermission() async {
    await Permission.notification.request();
  }

  /// Muestra una notificación de nuevo mensaje.
  /// [convId] se usa como ID de notificación (agrupa por conversación).
  /// [msgKey] identifica el mensaje específico para no repetirlo.
  static Future<void> showNewMessage({
    required int convId,
    required String contact,
    required String message,
    required String msgKey,
  }) async {
    if (_shownKeys.contains(msgKey)) return;
    _shownKeys.add(msgKey);

    // Limpiar keys antiguas para no crecer indefinidamente
    if (_shownKeys.length > 500) _shownKeys.clear();

    const androidDetails = AndroidNotificationDetails(
      'panel_agentes_messages',
      'Mensajes',
      channelDescription: 'Nuevos mensajes de conversaciones WhatsApp',
      importance: Importance.high,
      priority: Priority.high,
      icon: '@mipmap/ic_launcher',
    );

    const iosDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentSound: true,
      presentBadge: true,
    );

    await _plugin.show(
      convId,
      contact,
      message,
      const NotificationDetails(android: androidDetails, iOS: iosDetails),
    );
  }
}
