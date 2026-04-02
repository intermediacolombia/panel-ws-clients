import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/gestures.dart';
import 'package:audioplayers/audioplayers.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:emoji_picker_flutter/emoji_picker_flutter.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../providers/chat_provider.dart';
import '../models/conversation.dart';
import '../models/message.dart';
import '../services/api_service.dart';
import '../services/quick_reply_service.dart';
import '../core/constants.dart';
import '../core/theme.dart';

class ChatScreen extends StatefulWidget {
  final Conversation conversation;

  const ChatScreen({super.key, required this.conversation});

  @override
  State<ChatScreen> createState() => _ChatScreenState();
}

class _ChatScreenState extends State<ChatScreen> {
  final _inputCtrl  = TextEditingController();
  final _scrollCtrl = ScrollController();
  final _focusNode  = FocusNode();
  Timer? _pollTimer;
  bool _loaded      = false;
  bool _showEmoji   = false;
  bool _showQR      = false;
  List<String> _quickReplies = [];

  @override
  void initState() {
    super.initState();
    _focusNode.addListener(() {
      if (_focusNode.hasFocus && _showEmoji) {
        setState(() => _showEmoji = false);
      }
    });
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await context.read<ChatProvider>().openConversation(widget.conversation.id);
      _loaded = true;
      _scrollToBottom();
      _startPolling();
      _loadQuickReplies();
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _inputCtrl.dispose();
    _scrollCtrl.dispose();
    _focusNode.dispose();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<ChatProvider>().clearActive();
    });
    super.dispose();
  }

  Future<void> _loadQuickReplies() async {
    final replies = await QuickReplyService.getAll();
    if (mounted) setState(() => _quickReplies = replies);
  }

  void _startPolling() {
    _pollTimer = Timer.periodic(ApiConstants.pollMessages, (_) async {
      final prev = context.read<ChatProvider>().messages.length;
      await context.read<ChatProvider>().refreshMessages(widget.conversation.id);
      final curr = context.read<ChatProvider>().messages.length;
      if (curr > prev) _scrollToBottom();
    });
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(
          _scrollCtrl.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  void _toggleEmoji() {
    if (_showEmoji) {
      _focusNode.requestFocus();
    } else {
      _focusNode.unfocus();
    }
    setState(() { _showEmoji = !_showEmoji; _showQR = false; });
  }

  void _toggleQR() {
    setState(() { _showQR = !_showQR; _showEmoji = false; });
  }

  Future<void> _send() async {
    final text = _inputCtrl.text.trim();
    if (text.isEmpty) return;
    _inputCtrl.clear();

    await context.read<ChatProvider>().sendMessage(widget.conversation.id, text);
    _scrollToBottom(); // muestra el mensaje enviado o el fallido en rojo
  }

  Future<void> _resend(Message msg) async {
    await context.read<ChatProvider>().resendMessage(msg);
    _scrollToBottom();
  }

  void _showError(String msg) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(msg), backgroundColor: Colors.red.shade700),
    );
  }

  // ── Adjuntos ────────────────────────────────────────────────

  void _showAttachmentSheet() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (_) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40, height: 4,
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 16),
              _AttachOption(
                icon: Icons.camera_alt_outlined,
                label: 'Cámara',
                color: Colors.purple,
                onTap: () { Navigator.pop(context); _pickImage(ImageSource.camera); },
              ),
              _AttachOption(
                icon: Icons.photo_library_outlined,
                label: 'Galería',
                color: Colors.blue,
                onTap: () { Navigator.pop(context); _pickImage(ImageSource.gallery); },
              ),
              _AttachOption(
                icon: Icons.insert_drive_file_outlined,
                label: 'Documento',
                color: Colors.orange,
                onTap: () { Navigator.pop(context); _pickDocument(); },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final picker = ImagePicker();
      final XFile? file = await picker.pickImage(
        source: source,
        imageQuality: 80,
        maxWidth: 1920,
      );
      if (file == null) return;

      final bytes    = await file.readAsBytes();
      final b64      = base64Encode(bytes);
      final mimeType = _mimeFromExtension(file.name);

      await _sendFile(b64, file.name, mimeType);
    } catch (e) {
      _showError('No se pudo seleccionar la imagen.');
    }
  }

  Future<void> _pickDocument() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['pdf','doc','docx','xls','xlsx','txt','zip'],
        withData: true,
      );
      if (result == null || result.files.isEmpty) return;
      final file = result.files.first;
      if (file.bytes == null) return;

      final b64      = base64Encode(file.bytes!);
      final mimeType = _mimeFromExtension(file.name);

      await _sendFile(b64, file.name, mimeType);
    } catch (e) {
      _showError('No se pudo seleccionar el documento.');
    }
  }

  Future<void> _sendFile(String b64, String name, String mime) async {
    final ok = await context.read<ChatProvider>().sendFile(
      convId:     widget.conversation.id,
      fileBase64: b64,
      fileName:   name,
      mimeType:   mime,
    );
    if (ok) {
      _scrollToBottom();
    } else if (mounted) {
      _showError(context.read<ChatProvider>().sendError ?? 'Error al enviar archivo.');
    }
  }

  String _mimeFromExtension(String filename) {
    final ext = filename.split('.').last.toLowerCase();
    const map = {
      'jpg': 'image/jpeg', 'jpeg': 'image/jpeg',
      'png': 'image/png',  'gif': 'image/gif',
      'webp': 'image/webp',
      'pdf': 'application/pdf',
      'doc': 'application/msword',
      'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'xls': 'application/vnd.ms-excel',
      'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'txt': 'text/plain',
      'zip': 'application/zip',
    };
    return map[ext] ?? 'application/octet-stream';
  }

  // ── Acciones ─────────────────────────────────────────────────

  void _showActionsSheet() {
    final chat = context.read<ChatProvider>();
    final conv = chat.activeConversation ?? widget.conversation;
    final me   = context.read<AuthProvider>().agent;
    final isAssigned  = conv.agentId == me?.id;
    final isSupervisor = me?.isSupervisor ?? false;

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => _ActionsSheet(
        conversation: conv,
        isAssigned: isAssigned,
        isSupervisor: isSupervisor,
        onAction: (action) async {
          Navigator.pop(ctx);
          String? error;
          switch (action) {
            case _ConvAction.assign:
              error = await chat.assignToMe(conv.id);
              break;
            case _ConvAction.release:
              error = await chat.releaseToBot(conv.id);
              if (error == null && mounted) Navigator.pop(context);
              break;
            case _ConvAction.resolve:
              error = await chat.resolve(conv.id);
              if (error == null && mounted) Navigator.pop(context);
              break;
            case _ConvAction.transfer:
              _showTransferSheet();
              break;
            case _ConvAction.reopen:
              error = await chat.reopen(conv.id);
              break;
            case _ConvAction.rename:
              _showRenameDialog(conv);
              break;
          }
          if (error != null && mounted) _showError(error);
        },
      ),
    );
  }

  void _showRenameDialog(Conversation conv) {
    final ctrl = TextEditingController(text: conv.contactName);
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Editar nombre'),
        content: TextField(
          controller: ctrl,
          autofocus: true,
          maxLength: 100,
          decoration: const InputDecoration(
            labelText: 'Nombre del contacto',
            border: OutlineInputBorder(),
          ),
          textCapitalization: TextCapitalization.words,
          onSubmitted: (_) => _doRename(ctx, conv.id, ctrl),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancelar'),
          ),
          FilledButton(
            onPressed: () => _doRename(ctx, conv.id, ctrl),
            child: const Text('Guardar'),
          ),
        ],
      ),
    );
  }

  Future<void> _doRename(BuildContext ctx, int convId, TextEditingController ctrl) async {
    final name = ctrl.text.trim();
    if (name.isEmpty) return;
    Navigator.pop(ctx);
    final error = await context.read<ChatProvider>().updateContactName(convId, name);
    if (error != null && mounted) _showError(error);
  }

  void _showTransferSheet() async {
    await context.read<ChatProvider>().loadOnlineAgents();
    if (!mounted) return;
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => _TransferSheet(
        excludeAgentId: context.read<AuthProvider>().agent?.id,
        onSelect: (agentId, agentName) async {
          Navigator.pop(ctx);
          final error = await context
              .read<ChatProvider>()
              .transferTo(widget.conversation.id, agentId);
          if (error != null) {
            _showError(error);
          } else if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('Transferido a $agentName')),
            );
            Navigator.pop(context);
          }
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final conv = widget.conversation;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      appBar: AppBar(
        leadingWidth: 40,
        title: Row(
          children: [
            _ProfilePicSmall(phone: conv.phone, initials: conv.initials),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Consumer<ChatProvider>(
                    builder: (_, chat, __) => Text(
                      chat.activeConversation?.contactName ?? conv.contactName,
                      style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Consumer<ChatProvider>(
                    builder: (_, chat, __) {
                      final active = chat.activeConversation ?? conv;
                      final parts  = [conv.phone];
                      if (active.deptName != null) parts.add(active.deptName!);
                      parts.add(active.statusLabel);
                      return Text(
                        parts.join(' · '),
                        style: const TextStyle(fontSize: 11, color: Colors.white70),
                        overflow: TextOverflow.ellipsis,
                      );
                    },
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.more_vert),
            onPressed: _showActionsSheet,
          ),
        ],
      ),
      backgroundColor: isDark ? AppTheme.bgChatDark : AppTheme.bgChat,
      body: Column(
        children: [
          // Lista de mensajes
          Expanded(child: _MessageList(scrollCtrl: _scrollCtrl, loaded: _loaded, onResend: _resend)),

          // Panel de respuestas rápidas
          if (_showQR) _QuickRepliesPanel(
            replies: _quickReplies,
            onSelected: (text) {
              _inputCtrl.text = text;
              _inputCtrl.selection = TextSelection.fromPosition(
                TextPosition(offset: text.length),
              );
              setState(() => _showQR = false);
              _focusNode.requestFocus();
            },
          ),

          // Barra de entrada — usa activeConversation para reflejar cambios de estado en tiempo real
          Consumer<ChatProvider>(
            builder: (_, chat, __) => _InputBar(
              conv: chat.activeConversation ?? conv,
              inputCtrl: _inputCtrl,
              focusNode: _focusNode,
              showEmoji: _showEmoji,
              onSend: _send,
              onToggleEmoji: _toggleEmoji,
              onToggleQR: _toggleQR,
              onAttach: _showAttachmentSheet,
            ),
          ),

          // Emoji picker
          Offstage(
            offstage: !_showEmoji,
            child: SizedBox(
              height: 280,
              child: EmojiPicker(
                textEditingController: _inputCtrl,
                onBackspacePressed: () {
                  final text = _inputCtrl.text;
                  if (text.isEmpty) return;
                  _inputCtrl.text =
                      text.characters.skipLast(1).toString();
                  _inputCtrl.selection = TextSelection.fromPosition(
                    TextPosition(offset: _inputCtrl.text.length),
                  );
                },
                config: Config(
                  height: 280,
                  checkPlatformCompatibility: true,
                  emojiViewConfig: EmojiViewConfig(
                    emojiSizeMax: 28 * (Platform.isIOS ? 1.20 : 1.0),
                  ),
                  bottomActionBarConfig: BottomActionBarConfig(
                    backgroundColor:
                        Theme.of(context).scaffoldBackgroundColor,
                    buttonColor:
                        Theme.of(context).colorScheme.primary,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Widgets internos ─────────────────────────────────────────────────────────

class _ProfilePicSmall extends StatelessWidget {
  final String phone;
  final String initials;
  const _ProfilePicSmall({required this.phone, required this.initials});

  void _showModal(BuildContext context) {
    final url   = '${ApiConstants.baseUrl}/api/profile_picture.php?phone=$phone';
    final token = ApiService.token;
    showGeneralDialog(
      context: context,
      barrierDismissible: true,
      barrierLabel: 'Cerrar',
      barrierColor: Colors.black87,
      transitionDuration: const Duration(milliseconds: 250),
      pageBuilder: (ctx, _, __) => Center(
        child: GestureDetector(
          onTap: () => Navigator.pop(ctx),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: CachedNetworkImage(
              imageUrl: url,
              httpHeaders: token != null ? {'Authorization': 'Bearer $token'} : {},
              imageBuilder: (_, p) => Image(image: p, fit: BoxFit.contain,
                  width: 300, height: 300),
              placeholder: (_, __) => Container(
                width: 280, height: 280,
                color: Colors.white24,
                alignment: Alignment.center,
                child: Text(initials,
                    style: const TextStyle(color: Colors.white, fontSize: 72, fontWeight: FontWeight.bold)),
              ),
              errorWidget: (_, __, ___) => Container(
                width: 280, height: 280,
                color: Colors.white24,
                alignment: Alignment.center,
                child: Text(initials,
                    style: const TextStyle(color: Colors.white, fontSize: 72, fontWeight: FontWeight.bold)),
              ),
            ),
          ),
        ),
      ),
      transitionBuilder: (_, anim, __, child) => FadeTransition(
        opacity: anim,
        child: ScaleTransition(
          scale: Tween<double>(begin: 0.82, end: 1.0).animate(
            CurvedAnimation(parent: anim, curve: Curves.easeOutCubic),
          ),
          child: child,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final url   = '${ApiConstants.baseUrl}/api/profile_picture.php?phone=$phone';
    final token = ApiService.token;
    return GestureDetector(
      onTap: () => _showModal(context),
      child: CachedNetworkImage(
        imageUrl: url,
        httpHeaders: token != null ? {'Authorization': 'Bearer $token'} : {},
        imageBuilder: (_, p) => CircleAvatar(radius: 18, backgroundImage: p),
        placeholder: (_, __) => CircleAvatar(
          radius: 18, backgroundColor: Colors.white24,
          child: Text(initials, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        ),
        errorWidget: (_, __, ___) => CircleAvatar(
          radius: 18, backgroundColor: Colors.white24,
          child: Text(initials, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        ),
      ),
    );
  }
}

class _MessageList extends StatelessWidget {
  final ScrollController scrollCtrl;
  final bool loaded;
  final void Function(Message)? onResend;
  const _MessageList({required this.scrollCtrl, required this.loaded, this.onResend});

  @override
  Widget build(BuildContext context) => Consumer<ChatProvider>(
    builder: (_, chat, __) {
      if (chat.loadingMessages) {
        return const Center(child: CircularProgressIndicator());
      }
      if (chat.messages.isEmpty && loaded) {
        return Center(
          child: Text('Sin mensajes aún',
              style: TextStyle(color: AppTheme.textMuted)),
        );
      }
      return ListView.builder(
        controller: scrollCtrl,
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
        itemCount: chat.messages.length,
        itemBuilder: (_, i) {
          final msg = chat.messages[i];
          return _Bubble(
            msg: msg,
            onResend: msg.isLocalFailed && onResend != null ? () => onResend!(msg) : null,
          );
        },
      );
    },
  );
}

class _InputBar extends StatelessWidget {
  final Conversation conv;
  final TextEditingController inputCtrl;
  final FocusNode focusNode;
  final bool showEmoji;
  final VoidCallback onSend;
  final VoidCallback onToggleEmoji;
  final VoidCallback onToggleQR;
  final VoidCallback onAttach;

  const _InputBar({
    required this.conv,
    required this.inputCtrl,
    required this.focusNode,
    required this.showEmoji,
    required this.onSend,
    required this.onToggleEmoji,
    required this.onToggleQR,
    required this.onAttach,
  });

  bool get _canSend => conv.status != 'resolved' && conv.status != 'bot';

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    if (!_canSend) {
      return Container(
        color: isDark ? const Color(0xFF1F2C34) : Colors.white,
        padding: const EdgeInsets.all(14),
        child: SafeArea(
          top: false,
          child: Text(
            conv.status == 'resolved'
                ? '🔒 Conversación resuelta. Usa el menú ⋮ para reabrirla.'
                : '🤖 En modo bot. Usa el menú ⋮ para asignarte.',
            textAlign: TextAlign.center,
            style: const TextStyle(color: AppTheme.textMuted, fontSize: 13),
          ),
        ),
      );
    }

    return Container(
      color: isDark ? const Color(0xFF1F2C34) : Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 6),
      child: SafeArea(
        top: false,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            // Adjuntos
            IconButton(
              icon: Icon(Icons.attach_file,
                  color: Theme.of(context).colorScheme.primary),
              onPressed: onAttach,
              tooltip: 'Adjuntar',
            ),
            // Respuestas rápidas
            IconButton(
              icon: Icon(
                Icons.bolt,
                color: Theme.of(context).colorScheme.primary,
              ),
              onPressed: onToggleQR,
              tooltip: 'Respuestas rápidas',
            ),
            // Campo de texto
            Expanded(
              child: TextField(
                controller: inputCtrl,
                focusNode: focusNode,
                minLines: 1,
                maxLines: 5,
                textCapitalization: TextCapitalization.sentences,
                decoration: InputDecoration(
                  hintText: 'Mensaje...',
                  hintStyle: const TextStyle(color: AppTheme.textMuted),
                  filled: true,
                  fillColor: isDark ? const Color(0xFF2A3942) : const Color(0xFFF0F2F5),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(24),
                    borderSide: BorderSide.none,
                  ),
                  contentPadding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  // Botón emoji dentro del campo
                  suffixIcon: IconButton(
                    icon: Icon(
                      showEmoji ? Icons.keyboard_alt_outlined : Icons.emoji_emotions_outlined,
                      color: AppTheme.textMuted,
                    ),
                    onPressed: onToggleEmoji,
                  ),
                ),
              ),
            ),
            const SizedBox(width: 4),
            // Botón enviar
            Consumer<ChatProvider>(
              builder: (_, chat, __) => GestureDetector(
                onTap: chat.sending ? null : onSend,
                child: Container(
                  width: 46, height: 46,
                  decoration: const BoxDecoration(
                    color: AppTheme.primaryLight,
                    shape: BoxShape.circle,
                  ),
                  child: chat.sending
                      ? const Padding(
                          padding: EdgeInsets.all(12),
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white),
                        )
                      : const Icon(Icons.send_rounded, color: Colors.white, size: 22),
                ),
              ),
            ),
            const SizedBox(width: 4),
          ],
        ),
      ),
    );
  }
}

class _QuickRepliesPanel extends StatelessWidget {
  final List<String> replies;
  final void Function(String) onSelected;

  const _QuickRepliesPanel({required this.replies, required this.onSelected});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      height: 120,
      color: isDark ? const Color(0xFF1F2C34) : Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.bolt, size: 14, color: Colors.amber),
              const SizedBox(width: 4),
              Text('Respuestas rápidas',
                  style: TextStyle(
                    fontSize: 11,
                    color: AppTheme.textMuted,
                    fontWeight: FontWeight.w600,
                  )),
            ],
          ),
          const SizedBox(height: 6),
          Expanded(
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: replies.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (_, i) => GestureDetector(
                onTap: () => onSelected(replies[i]),
                child: Container(
                  constraints: const BoxConstraints(maxWidth: 220),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.primary.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: Theme.of(context).colorScheme.primary.withOpacity(0.3),
                    ),
                  ),
                  child: Text(
                    replies[i],
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      fontSize: 12,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ── Burbujas de mensaje ──────────────────────────────────────────────────────

class _Bubble extends StatelessWidget {
  final Message msg;
  final VoidCallback? onResend;
  const _Bubble({required this.msg, this.onResend});

  @override
  Widget build(BuildContext context) {
    final isOut  = msg.isOutgoing;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final bgColor = isOut
        ? (msg.isLocalFailed
            ? (isDark ? const Color(0xFF4A1010) : Colors.red.shade50)
            : (isDark ? AppTheme.outBubbleDark : AppTheme.outBubble))
        : (isDark ? AppTheme.inBubbleDark  : AppTheme.inBubble);

    return Align(
      alignment: isOut ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.75,
        ),
        margin: EdgeInsets.only(
          top: 2, bottom: 2,
          left: isOut ? 52 : 0,
          right: isOut ? 0 : 52,
        ),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
        decoration: BoxDecoration(
          color: bgColor,
          borderRadius: BorderRadius.only(
            topLeft:     const Radius.circular(16),
            topRight:    const Radius.circular(16),
            bottomLeft:  Radius.circular(isOut ? 16 : 4),
            bottomRight: Radius.circular(isOut ? 4 : 16),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(isDark ? 0.3 : 0.06),
              blurRadius: 2,
              offset: const Offset(0, 1),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment:
              isOut ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            // Nombre del agente (solo mensajes salientes con nombre)
            if (isOut && msg.agentName != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 3),
                child: Text(
                  msg.agentName!,
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.primary,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            // Contenido
            if (msg.isImage && msg.fileUrl != null)
              _ImageContent(url: msg.fileUrl!)
            else if (msg.isAudio && msg.fileUrl != null)
              _AudioContent(url: msg.fileUrl!)
            else if (msg.isDocument && msg.fileUrl != null)
              _DocContent(name: msg.fileName ?? 'Documento', url: msg.fileUrl!)
            else
              _LinkText(
                text: msg.displayText,
                style: TextStyle(
                  fontSize: 15,
                  color: isDark ? Colors.white.withOpacity(0.87) : const Color(0xFF111B21),
                ),
              ),
            // Hora + check + reenviar
            const SizedBox(height: 2),
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _time(msg.createdAt),
                  style: const TextStyle(fontSize: 10, color: AppTheme.textMuted),
                ),
                if (isOut) ...[
                  const SizedBox(width: 3),
                  Icon(
                    msg.failed
                        ? Icons.error_outline_rounded
                        : Icons.done_all_rounded,
                    size: 13,
                    color: msg.failed ? Colors.red : AppTheme.textMuted,
                  ),
                ],
                if (msg.isLocalFailed && onResend != null) ...[
                  const SizedBox(width: 6),
                  GestureDetector(
                    onTap: onResend,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.refresh_rounded, size: 13, color: Colors.red.shade700),
                        const SizedBox(width: 2),
                        Text('Reintentar',
                            style: TextStyle(fontSize: 10, color: Colors.red.shade700,
                                fontWeight: FontWeight.w600)),
                      ],
                    ),
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _time(String dt) {
    try {
      final d = DateTime.parse(dt).toLocal();
      return '${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
    } catch (_) {
      return '';
    }
  }
}

// ── Texto con links/emails clickeables ──────────────────────────────────────

class _LinkText extends StatefulWidget {
  final String text;
  final TextStyle style;
  const _LinkText({required this.text, required this.style});

  @override
  State<_LinkText> createState() => _LinkTextState();
}

class _LinkTextState extends State<_LinkText> {
  final List<TapGestureRecognizer> _recognizers = [];
  late List<InlineSpan> _spans;

  static final _re = RegExp(
    r'(https?://[^\s]+|www\.[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}[^\s]*'
    r'|[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})',
    caseSensitive: false,
  );

  @override
  void initState() {
    super.initState();
    _spans = _buildSpans(widget.text);
  }

  @override
  void didUpdateWidget(_LinkText old) {
    super.didUpdateWidget(old);
    if (old.text != widget.text) {
      for (final r in _recognizers) r.dispose();
      _recognizers.clear();
      _spans = _buildSpans(widget.text);
    }
  }

  @override
  void dispose() {
    for (final r in _recognizers) r.dispose();
    super.dispose();
  }

  List<InlineSpan> _buildSpans(String text) {
    final spans = <InlineSpan>[];
    int last = 0;
    for (final m in _re.allMatches(text)) {
      if (m.start > last) {
        spans.add(TextSpan(text: text.substring(last, m.start)));
      }
      final raw = m.group(0)!;
      // Quitar puntuación final que no es parte del enlace
      final trail = RegExp(r'[.,;:!?)\]>]+$').firstMatch(raw);
      final clean = trail != null ? raw.substring(0, trail.start) : raw;
      final extra = trail != null ? raw.substring(trail.start) : '';

      final isEmail = clean.contains('@') && !clean.startsWith('http');
      final uri = Uri.tryParse(
        isEmail ? 'mailto:$clean' : (clean.startsWith('www.') ? 'https://$clean' : clean),
      );
      if (uri != null) {
        final rec = TapGestureRecognizer()
          ..onTap = () async {
            try {
              await launchUrl(uri, mode: LaunchMode.externalApplication);
            } catch (_) {}
          };
        _recognizers.add(rec);
        spans.add(TextSpan(
          text: clean,
          recognizer: rec,
          style: const TextStyle(
            color: Color(0xFF1A73E8),
            decoration: TextDecoration.underline,
            decorationColor: Color(0xFF1A73E8),
          ),
        ));
      } else {
        spans.add(TextSpan(text: clean));
      }
      if (extra.isNotEmpty) spans.add(TextSpan(text: extra));
      last = m.end;
    }
    if (last < text.length) spans.add(TextSpan(text: text.substring(last)));
    return spans;
  }

  @override
  Widget build(BuildContext context) => SelectableText.rich(
    TextSpan(children: _spans, style: widget.style),
  );
}

void _showImageModal(BuildContext context, String url) {
  final token = ApiService.token;
  showGeneralDialog(
    context: context,
    barrierDismissible: true,
    barrierLabel: 'Cerrar',
    barrierColor: Colors.black.withOpacity(0.92),
    transitionDuration: const Duration(milliseconds: 240),
    pageBuilder: (ctx, _, __) => SafeArea(
      child: Stack(
        alignment: Alignment.center,
        children: [
          InteractiveViewer(
            minScale: 0.5,
            maxScale: 5.0,
            child: CachedNetworkImage(
              imageUrl: url,
              httpHeaders: token != null ? {'Authorization': 'Bearer $token'} : {},
              fit: BoxFit.contain,
              placeholder: (_, __) => const Center(
                child: CircularProgressIndicator(color: Colors.white),
              ),
              errorWidget: (_, __, ___) => const Icon(
                Icons.broken_image_outlined,
                color: Colors.white54,
                size: 64,
              ),
            ),
          ),
          Positioned(
            top: 8, right: 8,
            child: GestureDetector(
              onTap: () => Navigator.pop(ctx),
              child: Container(
                padding: const EdgeInsets.all(6),
                decoration: const BoxDecoration(
                  color: Colors.black45,
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.close, color: Colors.white, size: 22),
              ),
            ),
          ),
        ],
      ),
    ),
    transitionBuilder: (_, anim, __, child) => FadeTransition(
      opacity: anim,
      child: ScaleTransition(
        scale: Tween<double>(begin: 0.85, end: 1.0).animate(
          CurvedAnimation(parent: anim, curve: Curves.easeOutCubic),
        ),
        child: child,
      ),
    ),
  );
}

class _ImageContent extends StatelessWidget {
  final String url;
  const _ImageContent({required this.url});

  @override
  Widget build(BuildContext context) {
    final token = ApiService.token;
    return GestureDetector(
      onTap: () => _showImageModal(context, url),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: CachedNetworkImage(
          imageUrl: url,
          httpHeaders: token != null ? {'Authorization': 'Bearer $token'} : {},
          width: 200, height: 200,
          fit: BoxFit.cover,
          placeholder: (_, __) => const SizedBox(
            width: 200, height: 200,
            child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
          ),
          errorWidget: (_, __, ___) => const SizedBox(
            width: 200, height: 120,
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.broken_image_outlined, size: 36, color: AppTheme.textMuted),
                Text('No disponible', style: TextStyle(color: AppTheme.textMuted, fontSize: 11)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _AudioContent extends StatefulWidget {
  final String url;
  const _AudioContent({required this.url});

  @override
  State<_AudioContent> createState() => _AudioContentState();
}

class _AudioContentState extends State<_AudioContent> {
  late final AudioPlayer _player;
  bool _playing  = false;
  bool _loading  = false;
  Duration _position = Duration.zero;
  Duration _duration = Duration.zero;

  @override
  void initState() {
    super.initState();
    _player = AudioPlayer();
    _player.onPlayerStateChanged.listen((s) {
      if (!mounted) return;
      setState(() {
        _playing = s == PlayerState.playing;
        if (s == PlayerState.completed) _position = Duration.zero;
      });
    });
    _player.onPositionChanged.listen((p) {
      if (mounted) setState(() => _position = p);
    });
    _player.onDurationChanged.listen((d) {
      if (mounted) setState(() => _duration = d);
    });
  }

  @override
  void dispose() {
    _player.dispose();
    super.dispose();
  }

  Future<void> _toggle() async {
    if (_loading) return;
    if (_playing) {
      await _player.pause();
    } else {
      setState(() => _loading = true);
      try {
        await _player.play(UrlSource(widget.url));
      } finally {
        if (mounted) setState(() => _loading = false);
      }
    }
  }

  String _fmt(Duration d) {
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    final color    = Theme.of(context).colorScheme.primary;
    final progress = _duration.inMilliseconds > 0
        ? (_position.inMilliseconds / _duration.inMilliseconds).clamp(0.0, 1.0)
        : 0.0;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        GestureDetector(
          onTap: _toggle,
          child: _loading
              ? SizedBox(
                  width: 36, height: 36,
                  child: CircularProgressIndicator(strokeWidth: 2, color: color),
                )
              : Icon(
                  _playing
                      ? Icons.pause_circle_filled_rounded
                      : Icons.play_circle_filled_rounded,
                  color: color,
                  size: 36,
                ),
        ),
        const SizedBox(width: 8),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: 150,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(2),
                child: LinearProgressIndicator(
                  value: progress,
                  minHeight: 3,
                  backgroundColor: color.withOpacity(0.2),
                  valueColor: AlwaysStoppedAnimation(color),
                ),
              ),
            ),
            const SizedBox(height: 3),
            Text(
              '${_fmt(_position)} / ${_fmt(_duration)}',
              style: const TextStyle(fontSize: 10, color: AppTheme.textMuted),
            ),
          ],
        ),
      ],
    );
  }
}

class _DocContent extends StatelessWidget {
  final String name;
  final String url;
  const _DocContent({required this.name, required this.url});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: () => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(Icons.insert_drive_file_outlined,
            color: Theme.of(context).colorScheme.primary, size: 24),
        const SizedBox(width: 8),
        Flexible(
          child: Text(
            name,
            style: TextStyle(
              color: Theme.of(context).colorScheme.primary,
              decoration: TextDecoration.underline,
            ),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    ),
  );
}

// ── Opción de adjunto ────────────────────────────────────────────────────────

class _AttachOption extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _AttachOption({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) => ListTile(
    leading: Container(
      width: 46, height: 46,
      decoration: BoxDecoration(
        color: color.withOpacity(0.12),
        shape: BoxShape.circle,
      ),
      child: Icon(icon, color: color),
    ),
    title: Text(label),
    onTap: onTap,
  );
}

// ── Acciones de conversación ─────────────────────────────────────────────────

enum _ConvAction { assign, release, resolve, transfer, reopen, rename }

class _ActionsSheet extends StatelessWidget {
  final Conversation conversation;
  final bool isAssigned;
  final bool isSupervisor;
  final void Function(_ConvAction) onAction;

  const _ActionsSheet({
    required this.conversation,
    required this.isAssigned,
    required this.isSupervisor,
    required this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    final status = conversation.status;

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 40, height: 4,
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
              child: Text(
                conversation.contactName,
                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
              ),
            ),
            const Divider(),

            if (status == 'pending')
              ListTile(
                leading: const Icon(Icons.person_add_outlined, color: Colors.green),
                title: const Text('Asignarme'),
                subtitle: const Text('Tomar esta conversación'),
                onTap: () => onAction(_ConvAction.assign),
              ),

            if (status == 'attending' && (isAssigned || isSupervisor)) ...[
              ListTile(
                leading: const Icon(Icons.swap_horiz_outlined, color: Colors.blue),
                title: const Text('Transferir'),
                subtitle: const Text('Pasar a otro agente en línea'),
                onTap: () => onAction(_ConvAction.transfer),
              ),
            ],

            if (status == 'attending' || status == 'pending' || status == 'resolved')
              ListTile(
                leading: const Icon(Icons.smart_toy_outlined, color: Colors.orange),
                title: const Text('Pasar al bot'),
                subtitle: const Text('El asistente virtual retoma el control'),
                onTap: () => onAction(_ConvAction.release),
              ),

            if (status == 'attending' && (isAssigned || isSupervisor))
              ListTile(
                leading: const Icon(Icons.check_circle_outline, color: Colors.teal),
                title: const Text('Resolver'),
                subtitle: const Text('Marcar conversación como resuelta'),
                onTap: () => onAction(_ConvAction.resolve),
              ),

            ListTile(
              leading: const Icon(Icons.edit_outlined, color: Colors.indigo),
              title: const Text('Editar nombre'),
              subtitle: const Text('Cambiar el nombre del contacto'),
              onTap: () => onAction(_ConvAction.rename),
            ),

            if (status == 'resolved')
              ListTile(
                leading: const Icon(Icons.lock_open_outlined, color: Colors.deepPurple),
                title: const Text('Reabrir conversación'),
                subtitle: const Text('Volver a atender este chat'),
                onTap: () => onAction(_ConvAction.reopen),
              ),

            if (status == 'bot')
              ListTile(
                leading: const Icon(Icons.person_add_outlined, color: Colors.green),
                title: const Text('Asignarme'),
                subtitle: const Text('Tomar el control del bot'),
                onTap: () => onAction(_ConvAction.reopen),
              ),
          ],
        ),
      ),
    );
  }
}

// ── Diálogo de transferencia ─────────────────────────────────────────────────

class _TransferSheet extends StatelessWidget {
  final int? excludeAgentId;
  final void Function(int id, String name) onSelect;

  const _TransferSheet({this.excludeAgentId, required this.onSelect});

  @override
  Widget build(BuildContext context) {
    return Consumer<ChatProvider>(
      builder: (_, chat, __) {
        final agents = chat.onlineAgents
            .where((a) => a['id'] != excludeAgentId)
            .toList();

        return DraggableScrollableSheet(
          initialChildSize: 0.5,
          minChildSize: 0.3,
          maxChildSize: 0.85,
          expand: false,
          builder: (_, scrollCtrl) => Column(
            children: [
              Container(
                width: 40, height: 4,
                margin: const EdgeInsets.symmetric(vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const Text(
                'Transferir a agente',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
              ),
              const Divider(),
              if (chat.loadingAgents)
                const Padding(
                  padding: EdgeInsets.all(32),
                  child: CircularProgressIndicator(),
                )
              else if (agents.isEmpty)
                const Padding(
                  padding: EdgeInsets.all(32),
                  child: Text(
                    'No hay agentes en línea disponibles.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppTheme.textMuted),
                  ),
                )
              else
                Expanded(
                  child: ListView.builder(
                    controller: scrollCtrl,
                    itemCount: agents.length,
                    itemBuilder: (_, i) {
                      final ag   = agents[i];
                      final name = ag['name'] as String? ?? 'Agente';
                      return ListTile(
                        leading: CircleAvatar(
                          backgroundColor: AppTheme.primary.withOpacity(0.15),
                          child: Text(
                            name.isNotEmpty ? name[0].toUpperCase() : '?',
                            style: const TextStyle(
                              color: AppTheme.primary,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                        title: Text(name),
                        subtitle: Text(ag['role'] as String? ?? ''),
                        trailing: const Icon(Icons.arrow_forward_ios,
                            size: 14, color: AppTheme.textMuted),
                        onTap: () => onSelect(ag['id'] as int, name),
                      );
                    },
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}
