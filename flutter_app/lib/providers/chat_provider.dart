import 'package:flutter/foundation.dart';
import '../models/conversation.dart';
import '../models/message.dart';
import '../services/api_service.dart';
import '../services/notification_service.dart';
import '../core/constants.dart';

class ChatProvider extends ChangeNotifier {
  // ── Conversaciones ───────────────────────────────────────────
  List<Conversation> _conversations = [];
  bool _loadingConvs = false;
  String? _convsError;

  List<Conversation> get conversations => _conversations;
  bool    get loadingConversations => _loadingConvs;
  String? get conversationsError   => _convsError;

  // ── Chat activo ──────────────────────────────────────────────
  Conversation? _activeConv;
  List<Message> _messages = [];
  bool _loadingMsgs = false;
  bool _sending     = false;
  String? _sendError;

  Conversation? get activeConversation => _activeConv;
  List<Message> get messages           => _messages;
  bool    get loadingMessages          => _loadingMsgs;
  bool    get sending                  => _sending;
  String? get sendError                => _sendError;

  // ── Seguimiento para notificaciones ─────────────────────────
  int? _activeChatId;
  final Map<int, int> _lastUnreadCounts = {};

  void setActiveChatId(int? id) => _activeChatId = id;

  // ── Mensajes locales fallidos ────────────────────────────────
  int _localIdCounter = -1;

  // ── Agentes en línea (para transferir) ──────────────────────
  List<Map<String, dynamic>> _onlineAgents = [];
  bool _loadingAgents = false;

  List<Map<String, dynamic>> get onlineAgents   => _onlineAgents;
  bool                       get loadingAgents  => _loadingAgents;

  // ── Carga de conversaciones (con indicador) ──────────────────
  Future<void> fetchConversations({String status = 'all'}) async {
    if (_loadingConvs) return;
    _loadingConvs = true;
    _convsError   = null;
    notifyListeners();

    final res = await ApiService.get(
      ApiConstants.conversationsUrl,
      params: {'status': status, 'limit': '100'},
    );

    _loadingConvs = false;
    if (res['success'] == true) {
      _conversations = _parseConvs(res);
    } else {
      _convsError = res['error'] as String?;
    }
    notifyListeners();
  }

  /// Actualización silenciosa para polling — dispara notificaciones si hay nuevos mensajes.
  Future<void> refreshConversations({String status = 'all'}) async {
    final res = await ApiService.get(
      ApiConstants.conversationsUrl,
      params: {'status': status, 'limit': '100'},
    );
    if (res['success'] != true) return;

    final updated = _parseConvs(res);

    for (final conv in updated) {
      final prev     = _lastUnreadCounts[conv.id] ?? 0;
      final current  = conv.unreadCount;
      final isActive = conv.id == _activeChatId;

      if (current > prev && !isActive && conv.lastMessage != null) {
        NotificationService.showNewMessage(
          convId:  conv.id,
          contact: conv.contactName,
          message: conv.lastMessage!,
          msgKey:  '${conv.id}_${conv.lastMessage}',
        );
      }
      _lastUnreadCounts[conv.id] = current;
    }

    _conversations = updated;
    notifyListeners();
  }

  List<Conversation> _parseConvs(Map<String, dynamic> res) =>
      (res['conversations'] as List<dynamic>? ?? [])
          .map((e) => Conversation.fromJson(e as Map<String, dynamic>))
          .toList();

  // ── Abrir conversación ───────────────────────────────────────
  Future<void> openConversation(int convId) async {
    _activeConv  = null;
    _messages    = [];
    _sendError   = null;
    _loadingMsgs = true;
    setActiveChatId(convId);
    notifyListeners();

    final res = await ApiService.get(
      ApiConstants.conversationUrl,
      params: {'id': convId.toString()},
    );

    _loadingMsgs = false;
    if (res['success'] == true) {
      _activeConv = Conversation.fromJson(
          res['conversation'] as Map<String, dynamic>);
      _messages = _parseMsgs(res);
      _lastUnreadCounts[convId] = 0;
    }
    notifyListeners();
  }

  /// Polling silencioso de mensajes.
  Future<void> refreshMessages(int convId) async {
    final res = await ApiService.get(
      ApiConstants.conversationUrl,
      params: {'id': convId.toString()},
    );
    if (res['success'] != true) return;

    final serverMsgs  = _parseMsgs(res);
    final localFailed = _messages.where((m) => m.isLocalFailed).toList();
    final merged      = [...serverMsgs, ...localFailed];

    if (merged.length != _messages.length) {
      _messages   = merged;
      _activeConv = Conversation.fromJson(
          res['conversation'] as Map<String, dynamic>);
      notifyListeners();
    }
  }

  List<Message> _parseMsgs(Map<String, dynamic> res) =>
      (res['messages'] as List<dynamic>? ?? [])
          .map((e) => Message.fromJson(e as Map<String, dynamic>))
          .toList();

  // ── Enviar mensaje ───────────────────────────────────────────
  Future<bool> sendMessage(int convId, String text) async {
    if (text.trim().isEmpty) return false;
    _sending   = true;
    _sendError = null;
    notifyListeners();

    final res = await ApiService.post(ApiConstants.sendUrl, {
      'conversationId': convId,
      'type':           'text',
      'message':        text.trim(),
    });

    _sending = false;
    if (res['success'] == true) {
      final msgJson = res['message'] as Map<String, dynamic>?;
      if (msgJson != null) _messages.add(Message.fromJson(msgJson));
      notifyListeners();
      return true;
    }

    // Sin conexión u otro error: agregar mensaje local fallido para poder reenviar
    _messages.add(Message.localFailed(
      localId:        _localIdCounter--,
      conversationId: convId,
      text:           text.trim(),
    ));
    _sendError = res['error'] as String? ?? 'Error al enviar.';
    notifyListeners();
    return false;
  }

  /// Reenviar un mensaje local fallido.
  Future<bool> resendMessage(Message failedMsg) async {
    if (!failedMsg.isLocalFailed) return false;
    _messages.removeWhere((m) => m.id == failedMsg.id);
    notifyListeners();
    return sendMessage(failedMsg.conversationId, failedMsg.content ?? '');
  }

  /// Enviar archivo (imagen o documento) ya leído como bytes.
  Future<bool> sendFile({
    required int convId,
    required String fileBase64,
    required String fileName,
    required String mimeType,
    String caption = '',
  }) async {
    _sending   = true;
    _sendError = null;
    notifyListeners();

    final type = mimeType.startsWith('image/') ? 'image' : 'document';

    final res = await ApiService.post(ApiConstants.sendUrl, {
      'conversationId': convId,
      'type':           type,
      'fileBase64':     fileBase64,
      'fileName':       fileName,
      'mimeType':       mimeType,
      'caption':        caption,
    });

    _sending = false;
    if (res['success'] == true) {
      final msgJson = res['message'] as Map<String, dynamic>?;
      if (msgJson != null) _messages.add(Message.fromJson(msgJson));
      notifyListeners();
      return true;
    }

    _sendError = res['error'] as String? ?? 'Error al enviar archivo.';
    notifyListeners();
    return false;
  }

  // ── Acciones de conversación ─────────────────────────────────

  Future<String?> assignToMe(int convId) async {
    final res = await ApiService.post(ApiConstants.assignUrl,
        {'conversationId': convId});
    if (res['success'] == true) {
      await refreshMessages(convId);
      return null;
    }
    return res['error'] as String? ?? 'Error al asignar.';
  }

  Future<String?> releaseToBot(int convId) async {
    final res = await ApiService.post(ApiConstants.releaseUrl,
        {'conversationId': convId});
    if (res['success'] == true) {
      await refreshMessages(convId);
      return null;
    }
    return res['error'] as String? ?? 'Error al pasar al bot.';
  }

  Future<String?> resolve(int convId) async {
    final res = await ApiService.post(ApiConstants.resolveUrl,
        {'conversationId': convId});
    if (res['success'] == true) {
      await refreshMessages(convId);
      return null;
    }
    return res['error'] as String? ?? 'Error al resolver.';
  }

  Future<String?> reopen(int convId) async {
    final res = await ApiService.post(ApiConstants.reopenUrl,
        {'conversationId': convId});
    if (res['success'] == true) {
      await openConversation(convId);
      return null;
    }
    return res['error'] as String? ?? 'Error al reabrir.';
  }

  Future<String?> transferTo(int convId, int targetAgentId) async {
    final res = await ApiService.post(ApiConstants.transferUrl, {
      'conversationId': convId,
      'targetAgentId':  targetAgentId,
    });
    if (res['success'] == true) {
      await refreshMessages(convId);
      return null;
    }
    return res['error'] as String? ?? 'Error al transferir.';
  }

  /// Carga agentes en línea para el diálogo de transferencia.
  Future<void> loadOnlineAgents() async {
    _loadingAgents = true;
    notifyListeners();

    final res = await ApiService.get(ApiConstants.onlineAgentsUrl);
    _loadingAgents = false;

    if (res['success'] == true) {
      _onlineAgents = (res['agents'] as List<dynamic>? ?? [])
          .cast<Map<String, dynamic>>();
    } else {
      _onlineAgents = [];
    }
    notifyListeners();
  }

  void clearActive() {
    setActiveChatId(null);
    _activeConv = null;
    _messages   = [];
    _sendError  = null;
  }
}
