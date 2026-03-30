import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import '../core/constants.dart';
import '../core/theme.dart';
import '../models/conversation.dart';
import '../providers/chat_provider.dart';
import '../services/api_service.dart';
import 'chat_screen.dart';

class NewConversationScreen extends StatefulWidget {
  const NewConversationScreen({super.key});

  @override
  State<NewConversationScreen> createState() => _NewConversationScreenState();
}

class _NewConversationScreenState extends State<NewConversationScreen> {
  final _phoneCtrl   = TextEditingController();
  final _nameCtrl    = TextEditingController();
  final _messageCtrl = TextEditingController();

  final FocusNode _phoneFocus = FocusNode();
  final FocusNode _nameFocus = FocusNode();
  final FocusNode _messageFocus = FocusNode();

  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _phoneCtrl.dispose();
    _nameCtrl.dispose();
    _messageCtrl.dispose();

    _phoneFocus.dispose();
    _nameFocus.dispose();
    _messageFocus.dispose();

    super.dispose();
  }

  String _normalizePhone(String raw) {
    final digits = raw.replaceAll(RegExp(r'\D'), '');
    if (digits.length == 10 && !digits.startsWith('57')) {
      return '57$digits';
    }
    return digits;
  }

  Future<void> _start() async {
    final phone   = _normalizePhone(_phoneCtrl.text.trim());
    final message = _messageCtrl.text.trim();

    if (phone.length < 10) {
      setState(() => _error = 'Ingresa un número válido (mínimo 10 dígitos).');
      return;
    }
    if (message.isEmpty) {
      setState(() => _error = 'Escribe un primer mensaje para enviar.');
      return;
    }

    setState(() { _loading = true; _error = null; });

    final res = await ApiService.post(ApiConstants.startConvUrl, {
      'phone':   phone,
      'message': message,
      if (_nameCtrl.text.trim().isNotEmpty) 'name': _nameCtrl.text.trim(),
    });

    setState(() => _loading = false);

    if (res['success'] == true) {
      Conversation? conv;
      if (res['conversation'] != null) {
        conv = Conversation.fromJson(res['conversation'] as Map<String, dynamic>);
      }

      if (conv == null) {
        final convId = res['conversationId'] as int?;
        if (convId != null) {
          if (!mounted) return;
          await context.read<ChatProvider>().openConversation(convId);
          if (!mounted) return;
          final chat = context.read<ChatProvider>();
          conv = chat.activeConversation;
        }
      }

      if (!mounted) return;

      if (conv != null) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => ChatScreen(conversation: conv!)),
        );
      } else {
        Navigator.pop(context);
      }
    } else {
      final existingId = res['conversationId'] as int?;
      if (existingId != null && res['error'] != null &&
          (res['error'] as String).contains('activa')) {
        setState(() => _error = res['error'] as String);
        _showOpenExisting(existingId);
      } else {
        setState(() =>
            _error = res['error'] as String? ?? 'Error al iniciar conversación.');
      }
    }
  }

  void _showOpenExisting(int convId) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        title: const Text('Conversación existente'),
        content: const Text(
            'Ya existe una conversación activa con ese número. ¿Quieres abrirla?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () async {
              Navigator.pop(context);
              if (!mounted) return;
              await context.read<ChatProvider>().openConversation(convId);
              if (!mounted) return;
              final conv = context.read<ChatProvider>().activeConversation;
              if (conv != null && mounted) {
                Navigator.pushReplacement(
                  context,
                  MaterialPageRoute(
                      builder: (_) => ChatScreen(conversation: conv)),
                );
              }
            },
            child: const Text('Abrir'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Nueva conversación'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          const Text(
            'Inicia una conversación de WhatsApp desde el panel.',
            style: TextStyle(color: AppTheme.textMuted),
          ),
          const SizedBox(height: 24),

          // Teléfono
          TextField(
            controller: _phoneCtrl,
            focusNode: _phoneFocus,
            keyboardType: TextInputType.phone,
            inputFormatters: [
              FilteringTextInputFormatter.allow(RegExp(r'[\d\s\+\-\(\)]'))
            ],
            decoration: const InputDecoration(
              labelText: 'Número de WhatsApp *',
              hintText: 'Ej: 3001234567 o +573001234567',
              prefixIcon: Icon(Icons.phone_outlined),
              border: OutlineInputBorder(),
            ),
            onSubmitted: (_) {
              FocusScope.of(context).requestFocus(_nameFocus);
            },
          ),
          const SizedBox(height: 16),

          // Nombre
          TextField(
            controller: _nameCtrl,
            focusNode: _nameFocus,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(
              labelText: 'Nombre del contacto (opcional)',
              hintText: 'Ej: Juan Pérez',
              prefixIcon: Icon(Icons.person_outline),
              border: OutlineInputBorder(),
            ),
            onSubmitted: (_) {
              FocusScope.of(context).requestFocus(_messageFocus);
            },
          ),
          const SizedBox(height: 16),

          // Mensaje
          TextField(
            controller: _messageCtrl,
            focusNode: _messageFocus,
            textCapitalization: TextCapitalization.sentences,
            minLines: 3,
            maxLines: 6,
            decoration: const InputDecoration(
              labelText: 'Primer mensaje *',
              hintText: 'Hola, ¿en qué te puedo ayudar?',
              alignLabelWithHint: true,
              border: OutlineInputBorder(),
            ),
          ),

          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(
                _error!,
                style: const TextStyle(color: Colors.red, fontSize: 13),
              ),
            ),

          const SizedBox(height: 24),

          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: _loading ? null : _start,
              icon: _loading
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.send_outlined),
              label: const Text('Enviar y abrir chat'),
            ),
          ),

          const SizedBox(height: 32),
          const Divider(),
          const SizedBox(height: 12),

          const Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.info_outline, size: 16, color: AppTheme.textMuted),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'El mensaje se envía inmediatamente por WhatsApp. '
                  'La conversación queda asignada a ti.',
                  style: TextStyle(color: AppTheme.textMuted, fontSize: 12),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
