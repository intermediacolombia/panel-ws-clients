import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../providers/chat_provider.dart';
import '../models/conversation.dart';
import '../services/api_service.dart';
import '../core/constants.dart';
import '../core/theme.dart';
import 'chat_screen.dart';
import 'new_conversation_screen.dart';
import 'settings_screen.dart';

class ConversationsScreen extends StatefulWidget {
  const ConversationsScreen({super.key});

  @override
  State<ConversationsScreen> createState() => _ConversationsScreenState();
}

class _ConversationsScreenState extends State<ConversationsScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  Timer? _pollTimer;

  static const _filters = ['all', 'pending', 'attending', 'resolved'];
  int _tabIndex = 0;

  final _searchCtrl = TextEditingController();
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: _filters.length, vsync: this);
    _tabs.addListener(_onTabChange);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _load();
      _startPolling();
    });
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _tabs.removeListener(_onTabChange);
    _tabs.dispose();
    _searchCtrl.dispose();
    super.dispose();
  }

  void _onTabChange() {
    if (!_tabs.indexIsChanging) {
      _tabIndex = _tabs.index;
      _load();
    }
  }

  String get _currentFilter => _filters[_tabIndex];

  Future<void> _load() =>
      context.read<ChatProvider>().fetchConversations(status: _currentFilter);

  void _startPolling() {
    _pollTimer = Timer.periodic(ApiConstants.pollConversations, (_) {
      context.read<ChatProvider>().refreshConversations(status: _currentFilter);
    });
  }

  void _openChat(Conversation conv) {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => ChatScreen(conversation: conv)),
    ).then((_) => _load());
  }

  void _showLongPressMenu(Conversation conv) {
    final chat         = context.read<ChatProvider>();
    final canTransfer  = conv.status == 'attending';
    final canReleaseBot = conv.status == 'attending' || conv.status == 'pending';

    if (!canTransfer && !canReleaseBot) return;

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
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
                child: Text(conv.contactName,
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
              ),
              const Divider(),
              if (canTransfer)
                ListTile(
                  leading: const Icon(Icons.swap_horiz_outlined, color: Colors.blue),
                  title: const Text('Transferir'),
                  subtitle: const Text('Pasar a otro agente en línea'),
                  onTap: () {
                    Navigator.pop(ctx);
                    _showTransferFromList(conv);
                  },
                ),
              if (canReleaseBot)
                ListTile(
                  leading: const Icon(Icons.smart_toy_outlined, color: Colors.orange),
                  title: const Text('Pasar al bot'),
                  subtitle: const Text('El asistente virtual retoma el control'),
                  onTap: () async {
                    Navigator.pop(ctx);
                    final error = await chat.releaseToBot(conv.id);
                    if (mounted) {
                      if (error != null) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(error), backgroundColor: Colors.red.shade700),
                        );
                      } else {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Conversación pasada al bot')),
                        );
                      }
                    }
                    _load();
                  },
                ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showTransferFromList(Conversation conv) async {
    await context.read<ChatProvider>().loadOnlineAgents();
    if (!mounted) return;
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => _AgentsSheet(
        excludeAgentId: context.read<AuthProvider>().agent?.id,
        onSelect: (agentId, agentName) async {
          Navigator.pop(ctx);
          final error = await context.read<ChatProvider>().transferTo(conv.id, agentId);
          if (mounted) {
            if (error != null) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text(error), backgroundColor: Colors.red.shade700),
              );
            } else {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('Transferido a $agentName')),
              );
            }
          }
          _load();
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final agent = context.read<AuthProvider>().agent;
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Conversaciones'),
            if (agent != null)
              Text(
                agent.name,
                style: const TextStyle(fontSize: 11, color: Colors.white70),
              ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            tooltip: 'Configuración',
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => const SettingsScreen()),
            ),
          ),
          IconButton(
            icon: const Icon(Icons.logout_outlined),
            tooltip: 'Cerrar sesión',
            onPressed: () async {
              final confirm = await showDialog<bool>(
                context: context,
                builder: (ctx) => AlertDialog(
                  title: const Text('Cerrar sesión'),
                  content: const Text('¿Seguro que quieres salir?'),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.pop(ctx, false),
                      child: const Text('Cancelar'),
                    ),
                    FilledButton(
                      onPressed: () => Navigator.pop(ctx, true),
                      style: FilledButton.styleFrom(backgroundColor: Colors.red),
                      child: const Text('Salir'),
                    ),
                  ],
                ),
              );
              if (confirm == true) {
                _pollTimer?.cancel();
                if (mounted) await context.read<AuthProvider>().logout();
              }
            },
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(96),
          child: Column(
            children: [
              // Barra de búsqueda
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                child: TextField(
                  controller: _searchCtrl,
                  onChanged: (v) => setState(() => _searchQuery = v.toLowerCase()),
                  style: const TextStyle(color: Colors.white),
                  decoration: InputDecoration(
                    hintText: 'Buscar por nombre o número...',
                    hintStyle: const TextStyle(color: Colors.white60),
                    prefixIcon: const Icon(Icons.search, color: Colors.white60),
                    suffixIcon: _searchQuery.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.clear, color: Colors.white60),
                            onPressed: () {
                              _searchCtrl.clear();
                              setState(() => _searchQuery = '');
                            },
                          )
                        : null,
                    filled: true,
                    fillColor: Colors.white12,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(24),
                      borderSide: BorderSide.none,
                    ),
                    contentPadding: const EdgeInsets.symmetric(vertical: 8),
                    isDense: true,
                  ),
                ),
              ),
              // Tabs
              TabBar(
                controller: _tabs,
                labelColor: Colors.white,
                unselectedLabelColor: Colors.white54,
                indicatorColor: AppTheme.primaryLight,
                indicatorWeight: 3,
                tabs: const [
                  Tab(text: 'Todas'),
                  Tab(text: 'Pendientes'),
                  Tab(text: 'Atendiendo'),
                  Tab(text: 'Resueltas'),
                ],
              ),
            ],
          ),
        ),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () => Navigator.push(
          context,
          MaterialPageRoute(builder: (_) => const NewConversationScreen()),
        ).then((_) => _load()),
        tooltip: 'Nueva conversación',
        child: const Icon(Icons.chat_outlined),
      ),
      body: Consumer<ChatProvider>(
        builder: (context, chat, _) {
          if (chat.loadingConversations && chat.conversations.isEmpty) {
            return const Center(child: CircularProgressIndicator());
          }
          if (chat.conversationsError != null && chat.conversations.isEmpty) {
            return _ErrorView(message: chat.conversationsError!, onRetry: _load);
          }

          // Filtro local por búsqueda
          final convs = _searchQuery.isEmpty
              ? chat.conversations
              : chat.conversations.where((c) =>
                  c.contactName.toLowerCase().contains(_searchQuery) ||
                  c.phone.contains(_searchQuery)).toList();

          if (convs.isEmpty) return const _EmptyView();

          return RefreshIndicator(
            onRefresh: _load,
            child: ListView.separated(
              itemCount: convs.length,
              separatorBuilder: (_, __) =>
                  const Divider(height: 1, indent: 72, endIndent: 16),
              itemBuilder: (_, i) => _ConvTile(
                conv: convs[i],
                onTap: () => _openChat(convs[i]),
                onLongPress: () => _showLongPressMenu(convs[i]),
              ),
            ),
          );
        },
      ),
    );
  }
}

// ── Tile de conversación ─────────────────────────────────────────────────────

void _showProfileModal(BuildContext context, String phone, String initials, Color color) {
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
              color: color.withOpacity(0.2),
              alignment: Alignment.center,
              child: Text(initials,
                  style: TextStyle(color: color, fontSize: 72, fontWeight: FontWeight.bold)),
            ),
            errorWidget: (_, __, ___) => Container(
              width: 280, height: 280,
              color: color.withOpacity(0.2),
              alignment: Alignment.center,
              child: Text(initials,
                  style: TextStyle(color: color, fontSize: 72, fontWeight: FontWeight.bold)),
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

class _ConvTile extends StatelessWidget {
  final Conversation conv;
  final VoidCallback onTap;
  final VoidCallback? onLongPress;

  const _ConvTile({required this.conv, required this.onTap, this.onLongPress});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      onLongPress: onLongPress,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      leading: GestureDetector(
        onTap: () => _showProfileModal(context, conv.phone, conv.initials, conv.deptColorValue),
        child: _ProfileAvatar(phone: conv.phone, initials: conv.initials, color: conv.deptColorValue),
      ),
      title: Row(
        children: [
          Expanded(
            child: Text(
              conv.contactName,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontWeight: conv.unreadCount > 0
                    ? FontWeight.bold
                    : FontWeight.w500,
              ),
            ),
          ),
          Text(
            conv.timeFormatted,
            style: TextStyle(
              fontSize: 11,
              color: conv.unreadCount > 0
                  ? Theme.of(context).colorScheme.primary
                  : AppTheme.textMuted,
              fontWeight: conv.unreadCount > 0 ? FontWeight.w600 : FontWeight.normal,
            ),
          ),
        ],
      ),
      subtitle: Padding(
        padding: const EdgeInsets.only(top: 2),
        child: Row(
          children: [
            if (conv.lastDirection == 'out')
              const Padding(
                padding: EdgeInsets.only(right: 3),
                child: Icon(Icons.done_all, size: 13, color: AppTheme.textMuted),
              ),
            Expanded(
              child: Text(
                conv.lastMessage ?? '',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 13,
                  color: conv.unreadCount > 0
                      ? Theme.of(context).textTheme.bodyMedium?.color
                      : AppTheme.textMuted,
                  fontWeight: conv.unreadCount > 0
                      ? FontWeight.w500
                      : FontWeight.normal,
                ),
              ),
            ),
            const SizedBox(width: 6),
            if (conv.deptName != null) ...[
              _DeptChip(conv: conv),
              const SizedBox(width: 4),
            ],
            _StatusBadge(conv: conv),
          ],
        ),
      ),
    );
  }
}

// ── Foto de perfil con cache ─────────────────────────────────────────────────

class _ProfileAvatar extends StatelessWidget {
  final String phone;
  final String initials;
  final Color color;

  const _ProfileAvatar({
    required this.phone,
    required this.initials,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    final url =
        '${ApiConstants.baseUrl}/api/profile_picture.php?phone=$phone';
    final token = ApiService.token;

    return CachedNetworkImage(
      imageUrl: url,
      httpHeaders: token != null ? {'Authorization': 'Bearer $token'} : {},
      imageBuilder: (_, provider) => CircleAvatar(
        radius: 24,
        backgroundImage: provider,
      ),
      placeholder: (_, __) => CircleAvatar(
        radius: 24,
        backgroundColor: color.withOpacity(0.15),
        child: Text(
          initials,
          style: TextStyle(color: color, fontWeight: FontWeight.bold),
        ),
      ),
      errorWidget: (_, __, ___) => CircleAvatar(
        radius: 24,
        backgroundColor: color.withOpacity(0.15),
        child: Text(
          initials,
          style: TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 16),
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final Conversation conv;
  const _StatusBadge({required this.conv});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
    decoration: BoxDecoration(
      color: conv.statusColor.withOpacity(0.12),
      borderRadius: BorderRadius.circular(8),
    ),
    child: Text(
      conv.statusLabel,
      style: TextStyle(
        color: conv.statusColor,
        fontSize: 10,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

class _DeptChip extends StatelessWidget {
  final Conversation conv;
  const _DeptChip({required this.conv});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
    decoration: BoxDecoration(
      color: conv.deptColorValue.withOpacity(0.15),
      borderRadius: BorderRadius.circular(6),
    ),
    child: Text(
      conv.deptName!,
      style: TextStyle(
        fontSize: 9,
        color: conv.deptColorValue,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

class _EmptyView extends StatelessWidget {
  const _EmptyView();

  @override
  Widget build(BuildContext context) => Center(
    child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.chat_bubble_outline,
            size: 60,
            color: Theme.of(context).colorScheme.primary.withOpacity(0.3)),
        const SizedBox(height: 12),
        Text(
          'Sin conversaciones',
          style: TextStyle(color: AppTheme.textMuted, fontSize: 16),
        ),
      ],
    ),
  );
}

class _ErrorView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  const _ErrorView({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.wifi_off_outlined, size: 48, color: Colors.red),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.red)),
          const SizedBox(height: 20),
          ElevatedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh),
            label: const Text('Reintentar'),
          ),
        ],
      ),
    ),
  );
}

// ── Sheet de agentes para transferir desde la lista ──────────────────────────

class _AgentsSheet extends StatelessWidget {
  final int? excludeAgentId;
  final void Function(int id, String name) onSelect;

  const _AgentsSheet({this.excludeAgentId, required this.onSelect});

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
              const Text('Transferir a agente',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
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
                              color: AppTheme.primary, fontWeight: FontWeight.bold),
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
