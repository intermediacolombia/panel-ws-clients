import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:provider/provider.dart';
import 'core/theme.dart';
import 'providers/auth_provider.dart';
import 'providers/chat_provider.dart';
import 'providers/theme_provider.dart';
import 'services/background_service.dart';
import 'services/notification_service.dart';
import 'screens/login_screen.dart';
import 'screens/conversations_screen.dart';

/// Handler para mensajes FCM cuando la app está en background/cerrada.
/// Debe ser una función de nivel superior (no método de clase).
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  final title = message.notification?.title ?? message.data['contact'] ?? 'Nuevo mensaje';
  final body  = message.notification?.body  ?? message.data['message'] ?? '';
  final convId = int.tryParse(message.data['conv_id'] ?? '0') ?? 0;
  if (body.isNotEmpty) {
    await NotificationService.init();
    await NotificationService.showNewMessage(
      convId:  convId,
      contact: title,
      message: body,
      msgKey:  '${convId}_fcm_${message.messageId}',
    );
  }
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await NotificationService.init();
  await NotificationService.requestPermission();

  // Servicio en background persistente (notificaciones aunque la app esté cerrada)
  if (Platform.isAndroid) {
    // Pedir exención de optimización de batería para polling confiable
    final batteryStatus = await Permission.ignoreBatteryOptimizations.status;
    if (!batteryStatus.isGranted) {
      await Permission.ignoreBatteryOptimizations.request();
    }
    await initializeBackgroundService();
  }

  // Firebase es opcional — si falla (ej: sin google-services.json) la app sigue abriendo
  try {
    await Firebase.initializeApp();

    FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      final title  = message.notification?.title ?? message.data['contact'] ?? 'Nuevo mensaje';
      final body   = message.notification?.body  ?? message.data['message'] ?? '';
      final convId = int.tryParse(message.data['conv_id'] ?? '0') ?? 0;
      if (body.isNotEmpty) {
        NotificationService.showNewMessage(
          convId:  convId,
          contact: title,
          message: body,
          msgKey:  '${convId}_fcm_${message.messageId}',
        );
      }
    });
  } catch (e) {
    // Firebase no disponible — las notificaciones locales por polling siguen activas
    debugPrint('[Firebase] init omitido: $e');
  }

  runApp(const PanelAgentesApp());
}

class PanelAgentesApp extends StatelessWidget {
  const PanelAgentesApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => ThemeProvider()..init()),
        ChangeNotifierProvider(create: (_) => AuthProvider()..init()),
        ChangeNotifierProvider(create: (_) => ChatProvider()),
      ],
      child: Consumer<ThemeProvider>(
        builder: (_, themeProvider, __) => MaterialApp(
          title: 'Panel de Agentes',
          theme:      AppTheme.light,
          darkTheme:  AppTheme.dark,
          themeMode:  themeProvider.mode,
          debugShowCheckedModeBanner: false,
          home: const _AuthGate(),
        ),
      ),
    );
  }
}

class _AuthGate extends StatelessWidget {
  const _AuthGate();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    if (auth.isLoading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }
    return auth.isLoggedIn ? const ConversationsScreen() : const LoginScreen();
  }
}
