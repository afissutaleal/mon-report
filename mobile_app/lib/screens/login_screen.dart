// lib/screens/login_screen.dart
import 'package:flutter/material.dart';
import '../config/api_config.dart';
import '../config/app_theme.dart';
import '../services/api_service.dart';
import '../services/auth_service.dart';
import 'main_navigation.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _identifierCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  bool _isLoading = false;
  bool _obscurePassword = true;

  Future<void> _handleLogin() async {
    final identifier = _identifierCtrl.text.trim();
    final password = _passwordCtrl.text.trim();

    if (identifier.isEmpty || password.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('NIP / Email dan kata sandi wajib diisi.')),
      );
      return;
    }

    setState(() => _isLoading = true);

    final res = await ApiService.login(identifier, password);

    setState(() => _isLoading = false);

    if (res['success'] == true && res['user'] != null) {
      await AuthService.saveUser(res['user']);
      if (!mounted) return;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(builder: (_) => const MainNavigation()),
      );
    } else {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res['message'] ?? 'Login gagal.'),
          backgroundColor: AppTheme.danger,
        ),
      );
    }
  }

  void _fillCredentials(String nip, String pass) {
    _identifierCtrl.text = nip;
    _passwordCtrl.text = pass;
  }

  void _showServerSettings() {
    final controller = TextEditingController(text: ApiConfig.baseUrl);
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Pengaturan Alamat Server'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Gunakan http://192.168.1.204/monreport untuk HP fisik di Wi-Fi yang sama, atau 10.0.2.2 untuk Android Emulator.',
              style: TextStyle(fontSize: 12),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              decoration: const InputDecoration(
                labelText: 'Server Base URL',
                hintText: 'http://192.168.1.204/monreport',
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            onPressed: () {
              ApiConfig.setBaseUrl(controller.text.trim());
              Navigator.pop(ctx);
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('Alamat server diubah: ${ApiConfig.baseUrl}')),
              );
            },
            child: const Text('Simpan'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Align(
                alignment: Alignment.topRight,
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    IconButton(
                      icon: const Icon(Icons.settings, size: 20),
                      tooltip: 'Atur Server API',
                      onPressed: _showServerSettings,
                    ),
                    IconButton(
                      icon: Icon(isDark ? Icons.light_mode : Icons.dark_mode, size: 20),
                      tooltip: 'Ganti Mode Tema',
                      onPressed: AuthService.toggleTheme,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 10),
              Center(
                child: Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: AppTheme.primary.withOpacity(0.12),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.cell_tower,
                    size: 54,
                    color: AppTheme.primary,
                  ),
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'MONREPORT MOBILE',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppTheme.primary,
                  letterSpacing: 0.5,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Balai Monitor Spektrum Frekuensi Radio Kelas I Samarinda',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                  color: Colors.grey.shade600,
                ),
              ),
              const SizedBox(height: 32),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Text(
                        'Masuk ke Akun Petugas',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 16),
                      TextField(
                        controller: _identifierCtrl,
                        decoration: const InputDecoration(
                          labelText: 'NIP atau Email Dinas',
                          prefixIcon: Icon(Icons.badge_outlined, size: 20),
                        ),
                      ),
                      const SizedBox(height: 14),
                      TextField(
                        controller: _passwordCtrl,
                        obscureText: _obscurePassword,
                        decoration: InputDecoration(
                          labelText: 'Kata Sandi',
                          prefixIcon: const Icon(Icons.lock_outline, size: 20),
                          suffixIcon: IconButton(
                            icon: Icon(
                              _obscurePassword ? Icons.visibility_off : Icons.visibility,
                              size: 20,
                            ),
                            onPressed: () {
                              setState(() => _obscurePassword = !_obscurePassword);
                            },
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),
                      ElevatedButton(
                        onPressed: _isLoading ? null : _handleLogin,
                        child: _isLoading
                            ? const SizedBox(
                                width: 22,
                                height: 22,
                                child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                              )
                            : const Text('Masuk Aplikasi', style: TextStyle(fontSize: 15)),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),
              const Text(
                'Akses Cepat Demo Akun Petugas:',
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  ActionChip(
                    avatar: const CircleAvatar(child: Text('KB', style: TextStyle(fontSize: 10))),
                    label: const Text('Kepala Balai', style: TextStyle(fontSize: 11)),
                    onPressed: () => _fillCredentials('19711129 199103 1 000', 'password123'),
                  ),
                  ActionChip(
                    avatar: const CircleAvatar(child: Text('MT', style: TextStyle(fontSize: 10))),
                    label: const Text('Ketua Montib', style: TextStyle(fontSize: 11)),
                    onPressed: () => _fillCredentials('19770506 200803 1 001', 'password123'),
                  ),
                  ActionChip(
                    avatar: const CircleAvatar(child: Text('AT', style: TextStyle(fontSize: 10))),
                    label: const Text('Admin Tim', style: TextStyle(fontSize: 11)),
                    onPressed: () => _fillCredentials('19960310 201902 2 006', 'password123'),
                  ),
                  ActionChip(
                    avatar: const CircleAvatar(child: Text('SP', style: TextStyle(fontSize: 10))),
                    label: const Text('Ketua Sarpras', style: TextStyle(fontSize: 11)),
                    onPressed: () => _fillCredentials('19880924 200803 1 001', 'password123'),
                  ),
                  ActionChip(
                    avatar: const CircleAvatar(child: Text('KL', style: TextStyle(fontSize: 10))),
                    label: const Text('Ketua Layanan', style: TextStyle(fontSize: 11)),
                    onPressed: () => _fillCredentials('19950215 201902 1 002', 'password123'),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
