// lib/screens/chat_list_screen.dart
import 'package:flutter/material.dart';
import '../config/app_theme.dart';
import '../models/user_model.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';
import 'chat_room_screen.dart';

class ChatListScreen extends StatefulWidget {
  const ChatListScreen({super.key});

  @override
  State<ChatListScreen> createState() => _ChatListScreenState();
}

class _ChatListScreenState extends State<ChatListScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _isLoading = true;
  List<UserModel> _officers = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadOfficers();
  }

  Future<void> _loadOfficers() async {
    setState(() => _isLoading = true);
    final list = await ApiService.fetchActiveUsers();
    setState(() {
      _officers = list;
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    final currentUser = AuthService.currentUser;
    final onlineCount = _officers.where((o) => o.isOnline).length;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Interaksi & Chat Petugas'),
        bottom: TabBar(
          controller: _tabController,
          labelColor: AppTheme.primary,
          unselectedLabelColor: Colors.grey,
          indicatorColor: AppTheme.primary,
          tabs: [
            const Tab(
              icon: Icon(Icons.groups, size: 20),
              text: 'Diskusi Tim Balmon',
            ),
            Tab(
              icon: const Icon(Icons.people_outline, size: 20),
              text: 'Petugas Aktif ($onlineCount)',
            ),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          // Tab 1: Team Chat Room Banner
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      children: [
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: AppTheme.primary.withOpacity(0.12),
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.groups, size: 40, color: AppTheme.primary),
                        ),
                        const SizedBox(height: 14),
                        const Text(
                          'Forum Koordinasi Tim Balmon Samarinda',
                          textAlign: TextAlign.center,
                          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Ruang percakapan bersama untuk semua petugas monitoring, koordinator, dan kepala balai. Digunakan untuk instruksi lapangan cepat dan info pengawasan spektrum frekuensi.',
                          textAlign: TextAlign.center,
                          style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                        ),
                        const SizedBox(height: 20),
                        ElevatedButton.icon(
                          onPressed: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const ChatRoomScreen(
                                  isTeamChat: true,
                                  title: 'Diskusi Tim Balmon Samarinda',
                                ),
                              ),
                            );
                          },
                          icon: const Icon(Icons.forum),
                          label: const Text('Buka Ruang Diskusi Tim'),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Petugas Yang Sedang Online:',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                ),
                const SizedBox(height: 8),
                Expanded(
                  child: _isLoading
                      ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
                      : ListView.separated(
                          itemCount: _officers.length,
                          separatorBuilder: (_, __) => const SizedBox(height: 8),
                          itemBuilder: (ctx, idx) {
                            final off = _officers[idx];
                            return Card(
                              child: ListTile(
                                leading: Stack(
                                  children: [
                                    CircleAvatar(
                                      backgroundColor: Colors.grey.shade200,
                                      child: Text(
                                        off.name.isNotEmpty ? off.name[0] : 'P',
                                        style: const TextStyle(fontWeight: FontWeight.w700),
                                      ),
                                    ),
                                    Positioned(
                                      bottom: 0,
                                      right: 0,
                                      child: Container(
                                        width: 12,
                                        height: 12,
                                        decoration: BoxDecoration(
                                          color: off.isOnline ? AppTheme.success : Colors.grey,
                                          shape: BoxShape.circle,
                                          border: Border.all(color: Colors.white, width: 2),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                title: Text(off.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                                subtitle: Text(off.jabatan, style: const TextStyle(fontSize: 11)),
                                trailing: off.isOnline
                                    ? Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                        decoration: BoxDecoration(
                                          color: Colors.green.shade50,
                                          borderRadius: BorderRadius.circular(6),
                                        ),
                                        child: Text(
                                          'Online (${off.deviceType.toUpperCase()})',
                                          style: TextStyle(color: Colors.green.shade800, fontSize: 10, fontWeight: FontWeight.w700),
                                        ),
                                      )
                                    : null,
                              ),
                            );
                          },
                        ),
                ),
              ],
            ),
          ),

          // Tab 2: Direct Chat with Specific Officers
          _isLoading
              ? const Center(child: CircularProgressIndicator(color: AppTheme.primary))
              : RefreshIndicator(
                  onRefresh: _loadOfficers,
                  color: AppTheme.primary,
                  child: ListView.separated(
                    padding: const EdgeInsets.all(16),
                    itemCount: _officers.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 8),
                    itemBuilder: (ctx, idx) {
                      final off = _officers[idx];
                      if (currentUser != null && off.id == currentUser.id) {
                        return const SizedBox.shrink();
                      }

                      return Card(
                        child: ListTile(
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => ChatRoomScreen(
                                  isTeamChat: false,
                                  partnerId: off.id,
                                  title: off.name,
                                ),
                              ),
                            );
                          },
                          leading: Stack(
                            children: [
                              CircleAvatar(
                                backgroundColor: AppTheme.primary.withOpacity(0.12),
                                child: Text(
                                  off.name.isNotEmpty ? off.name[0] : 'P',
                                  style: const TextStyle(color: AppTheme.primary, fontWeight: FontWeight.w800),
                                ),
                              ),
                              Positioned(
                                bottom: 0,
                                right: 0,
                                child: Container(
                                  width: 12,
                                  height: 12,
                                  decoration: BoxDecoration(
                                    color: off.isOnline ? AppTheme.success : Colors.grey,
                                    shape: BoxShape.circle,
                                    border: Border.all(color: Colors.white, width: 2),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          title: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  off.name,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                                ),
                              ),
                              if (off.isOnline)
                                Text(
                                  'Aktif (${off.deviceType.toUpperCase()})',
                                  style: const TextStyle(fontSize: 10, color: AppTheme.success, fontWeight: FontWeight.w700),
                                ),
                            ],
                          ),
                          subtitle: Text(
                            off.jabatan,
                            style: TextStyle(fontSize: 11, color: Colors.grey.shade600),
                          ),
                          trailing: const Icon(Icons.chat_bubble_outline, size: 18, color: AppTheme.primary),
                        ),
                      );
                    },
                  ),
                ),
        ],
      ),
    );
  }
}
