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
              ),
            ),
          );
        },
      ),
    );
  }
}

// ── Tile de conversación ─────────────────────────────────────────────────────

class _ConvTile extends StatelessWidget {
  final Conversation conv;
  final VoidCallback onTap;

  const _ConvTile({required this.conv, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      leading: _ProfileAvatar(phone: conv.phone, initials: conv.initials, color: conv.deptColorValue),
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
