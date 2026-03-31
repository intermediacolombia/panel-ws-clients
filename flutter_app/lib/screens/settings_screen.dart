import 'dart:io';
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../providers/theme_provider.dart';
import '../services/quick_reply_service.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  List<String> _quickReplies = [];
  bool _loadingQR = true;
  final _newReplyCtrl = TextEditingController();

  bool _notifGranted   = false;
  bool _batteryIgnored = false;

  @override
  void initState() {
    super.initState();
    _loadQuickReplies();
    _checkNotifPermission();
    _checkBatteryOpt();
  }

  Future<void> _checkNotifPermission() async {
    final status = await Permission.notification.status;
    if (mounted) setState(() => _notifGranted = status.isGranted);
  }

  Future<void> _toggleNotifPermission() async {
    if (_notifGranted) {
      await openAppSettings();
    } else {
      final status = await Permission.notification.request();
      if (mounted) setState(() => _notifGranted = status.isGranted);
    }
  }

  Future<void> _checkBatteryOpt() async {
    if (!Platform.isAndroid) return;
    final status = await Permission.ignoreBatteryOptimizations.status;
    if (mounted) setState(() => _batteryIgnored = status.isGranted);
  }

  Future<void> _requestBatteryOpt() async {
    if (!Platform.isAndroid) return;
    if (_batteryIgnored) {
      // Ya está activo — llevar a ajustes por si quiere revertirlo
      await openAppSettings();
    } else {
      final status = await Permission.ignoreBatteryOptimizations.request();
      if (mounted) setState(() => _batteryIgnored = status.isGranted);
    }
  }

  @override
  void dispose() {
    _newReplyCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadQuickReplies() async {
    final replies = await QuickReplyService.getAll();
    if (mounted) setState(() { _quickReplies = replies; _loadingQR = false; });
  }

  Future<void> _addReply() async {
    final text = _newReplyCtrl.text.trim();
    if (text.isEmpty) return;
    await QuickReplyService.add(text);
    _newReplyCtrl.clear();
    await _loadQuickReplies();
  }

  Future<void> _removeReply(int index) async {
    await QuickReplyService.remove(index);
    await _loadQuickReplies();
  }

  Future<void> _resetReplies() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Restaurar respuestas'),
        content: const Text('Se reemplazarán tus respuestas rápidas con las predeterminadas.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancelar')),
          TextButton(onPressed: () => Navigator.pop(context, true),  child: const Text('Restaurar')),
        ],
      ),
    );
    if (ok == true) {
      await QuickReplyService.resetToDefaults();
      await _loadQuickReplies();
    }
  }

  @override
  Widget build(BuildContext context) {
    final agent = context.read<AuthProvider>().agent;
    final themeProvider = context.watch<ThemeProvider>();
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      appBar: AppBar(title: const Text('Configuración')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // ── Perfil ───────────────────────────────────────────
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 28,
                    backgroundColor: Theme.of(context).colorScheme.primary.withOpacity(0.15),
                    child: Text(
                      agent?.name.isNotEmpty == true
                          ? agent!.name[0].toUpperCase()
                          : '?',
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.primary,
                        fontSize: 22,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(agent?.name ?? '',
                            style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600)),
                        Text('@${agent?.username ?? ''}',
                            style: TextStyle(color: Theme.of(context).colorScheme.primary)),
                        Text(
                          agent?.isSupervisor == true ? 'Supervisor' : 'Agente',
                          style: TextStyle(
                            color: isDark ? Colors.grey[400] : Colors.grey[600],
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),

          // ── Tema ─────────────────────────────────────────────
          _SectionHeader(title: 'Apariencia', icon: Icons.palette_outlined),
          const SizedBox(height: 8),
          Card(
            child: Column(
              children: [
                _ThemeTile(
                  title: 'Claro',
                  icon: Icons.light_mode_outlined,
                  mode: ThemeMode.light,
                  current: themeProvider.mode,
                  onSelect: themeProvider.setMode,
                ),
                const Divider(height: 1, indent: 56),
                _ThemeTile(
                  title: 'Oscuro',
                  icon: Icons.dark_mode_outlined,
                  mode: ThemeMode.dark,
                  current: themeProvider.mode,
                  onSelect: themeProvider.setMode,
                ),
                const Divider(height: 1, indent: 56),
                _ThemeTile(
                  title: 'Según el sistema',
                  icon: Icons.brightness_auto_outlined,
                  mode: ThemeMode.system,
                  current: themeProvider.mode,
                  onSelect: themeProvider.setMode,
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),

          // ── Notificaciones ───────────────────────────────────
          _SectionHeader(title: 'Notificaciones', icon: Icons.notifications_outlined),
          const SizedBox(height: 8),
          Card(
            child: ListTile(
              leading: Icon(
                _notifGranted ? Icons.notifications_active_outlined : Icons.notifications_off_outlined,
                color: _notifGranted ? Colors.green : Colors.red,
              ),
              title: const Text('Notificaciones de mensajes'),
              subtitle: Text(_notifGranted ? 'Activas' : 'Inactivas — toca para activar'),
              trailing: Switch(
                value: _notifGranted,
                onChanged: (_) => _toggleNotifPermission(),
                activeColor: Colors.green,
              ),
            ),
          ),
          if (!_notifGranted)
            Padding(
              padding: const EdgeInsets.only(left: 4, top: 4, bottom: 8),
              child: Text(
                'Para desactivarlas ve a Ajustes del sistema → Apps → Panel de Agentes.',
                style: TextStyle(fontSize: 11, color: Colors.grey[500]),
              ),
            ),
          const SizedBox(height: 24),

          // ── Optimización de batería (solo Android) ───────────
          if (Platform.isAndroid) ...[
            _SectionHeader(title: 'Batería y segundo plano', icon: Icons.battery_charging_full_outlined),
            const SizedBox(height: 8),
            Card(
              child: ListTile(
                leading: Icon(
                  _batteryIgnored ? Icons.battery_full : Icons.battery_alert_outlined,
                  color: _batteryIgnored ? Colors.green : Colors.orange,
                ),
                title: const Text('Ignorar optimización de batería'),
                subtitle: Text(
                  _batteryIgnored
                      ? 'Activo — la app puede recibir notificaciones en segundo plano'
                      : 'Inactivo — Android puede suspender la app y bloquear notificaciones',
                ),
                trailing: Switch(
                  value: _batteryIgnored,
                  onChanged: (_) => _requestBatteryOpt(),
                  activeColor: Colors.green,
                ),
              ),
            ),
            if (!_batteryIgnored)
              Padding(
                padding: const EdgeInsets.only(left: 4, top: 4, bottom: 8),
                child: Text(
                  'Recomendado para recibir mensajes aunque la app esté cerrada.',
                  style: TextStyle(fontSize: 11, color: Colors.grey[500]),
                ),
              ),
            const SizedBox(height: 24),
          ],

          // ── Respuestas rápidas ───────────────────────────────
          Row(
            children: [
              Expanded(child: _SectionHeader(title: 'Respuestas Rápidas', icon: Icons.bolt_outlined)),
              TextButton.icon(
                onPressed: _resetReplies,
                icon: const Icon(Icons.restart_alt, size: 16),
                label: const Text('Restaurar', style: TextStyle(fontSize: 12)),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            'Úsalas desde el chat con el botón ⚡ para insertar respuestas frecuentes.',
            style: TextStyle(
              fontSize: 12,
              color: isDark ? Colors.grey[400] : Colors.grey[600],
            ),
          ),
          const SizedBox(height: 10),

          // Agregar nueva respuesta rápida
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _newReplyCtrl,
                      decoration: const InputDecoration(
                        hintText: 'Nueva respuesta rápida...',
                        border: InputBorder.none,
                        isDense: true,
                        contentPadding: EdgeInsets.zero,
                      ),
                      maxLines: 2,
                      minLines: 1,
                      textInputAction: TextInputAction.done,
                      onSubmitted: (_) => _addReply(),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.add_circle_outline),
                    color: Theme.of(context).colorScheme.primary,
                    onPressed: _addReply,
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 8),

          // Lista de respuestas rápidas
          if (_loadingQR)
            const Center(child: CircularProgressIndicator())
          else
            ..._quickReplies.asMap().entries.map((entry) => Card(
              margin: const EdgeInsets.only(bottom: 6),
              child: ListTile(
                leading: const Icon(Icons.bolt, color: Colors.amber, size: 20),
                title: Text(entry.value, style: const TextStyle(fontSize: 14)),
                trailing: IconButton(
                  icon: const Icon(Icons.delete_outline, size: 20, color: Colors.red),
                  onPressed: () => _removeReply(entry.key),
                ),
                dense: true,
              ),
            )),
        ],
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  final String title;
  final IconData icon;
  const _SectionHeader({required this.title, required this.icon});

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon, size: 18, color: Theme.of(context).colorScheme.primary),
      const SizedBox(width: 8),
      Text(
        title,
        style: TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w700,
          color: Theme.of(context).colorScheme.primary,
          letterSpacing: 0.5,
        ),
      ),
    ],
  );
}

class _ThemeTile extends StatelessWidget {
  final String title;
  final IconData icon;
  final ThemeMode mode;
  final ThemeMode current;
  final void Function(ThemeMode) onSelect;

  const _ThemeTile({
    required this.title,
    required this.icon,
    required this.mode,
    required this.current,
    required this.onSelect,
  });

  @override
  Widget build(BuildContext context) => ListTile(
    leading: Icon(icon, color: Theme.of(context).colorScheme.primary),
    title: Text(title),
    trailing: current == mode
        ? Icon(Icons.check_circle, color: Theme.of(context).colorScheme.primary)
        : const Icon(Icons.radio_button_unchecked, color: Colors.grey),
    onTap: () => onSelect(mode),
  );
}
