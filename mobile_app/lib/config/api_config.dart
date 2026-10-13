import 'package:shared_preferences/shared_preferences.dart';

class ApiConfig {
  // Default to 192.168.1.204 for real device over Wi-Fi, or 10.0.2.2 for Android Emulator
  static String baseUrl = 'http://192.168.1.204/monreport';

  static const String _keyBaseUrl = 'api_base_url';

  static Future<void> init() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final savedUrl = prefs.getString(_keyBaseUrl);
      if (savedUrl != null && savedUrl.isNotEmpty) {
        baseUrl = savedUrl;
      }
    } catch (_) {}
  }

  // Helper method to set custom server address if testing on real device over WiFi
  static Future<void> setBaseUrl(String url) async {
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      url = 'http://$url';
    }
    if (url.endsWith('/')) {
      url = url.substring(0, url.length - 1);
    }
    baseUrl = url;
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_keyBaseUrl, baseUrl);
    } catch (_) {}
  }

  // Endpoints
  static String get login => '$baseUrl/api/login.php';
  static String get dashboard => '$baseUrl/api/dashboard.php';
  static String get reports => '$baseUrl/api/reports.php';
  static String get verification => '$baseUrl/api/verification.php';
  static String get schedules => '$baseUrl/api/schedules.php';
  static String get chat => '$baseUrl/api/chat.php';
  static String get activeUsers => '$baseUrl/api/active_users.php';
  static String get heartbeat => '$baseUrl/api/heartbeat.php';
  static String get toggleTheme => '$baseUrl/api/toggle_theme.php';
}
