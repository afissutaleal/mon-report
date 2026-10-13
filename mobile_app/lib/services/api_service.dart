// lib/services/api_service.dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';
import '../models/user_model.dart';
import '../models/category_model.dart';
import '../models/report_model.dart';
import '../models/schedule_model.dart';
import '../models/chat_message_model.dart';

class ApiService {
  // 1. Login
  static Future<Map<String, dynamic>> login(String identifier, String password) async {
    try {
      final res = await http.post(
        Uri.parse(ApiConfig.login),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'identifier': identifier,
          'password': password,
          'device_type': 'mobile',
        }),
      );

      final data = jsonDecode(res.body);
      if (res.statusCode == 200 && data['success'] == true) {
        return {
          'success': true,
          'user': UserModel.fromJson(data['user']),
          'message': data['message'] ?? 'Login berhasil',
        };
      } else {
        return {
          'success': false,
          'message': data['message'] ?? 'Login gagal. Periksa kembali NIP dan password.',
        };
      }
    } catch (e) {
      return {'success': false, 'message': 'Gagal terhubung ke server: $e'};
    }
  }

  // 2. Dashboard KPI
  static Future<Map<String, dynamic>> fetchDashboard() async {
    try {
      final res = await http.get(Uri.parse(ApiConfig.dashboard));
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['success'] == true) {
          final List<CategoryModel> categories = (data['categories'] as List? ?? [])
              .map((c) => CategoryModel.fromJson(c))
              .toList();

          final List<ReportModel> recentReports = (data['recent_reports'] as List? ?? [])
              .map((r) => ReportModel.fromJson(r))
              .toList();

          final List<ScheduleModel> upcomingSchedules = (data['upcoming_schedules'] as List? ?? [])
              .map((s) => ScheduleModel.fromJson(s))
              .toList();

          return {
            'success': true,
            'kpi_summary': data['kpi_summary'] ?? {},
            'categories': categories,
            'recent_reports': recentReports,
            'upcoming_schedules': upcomingSchedules,
            'monthly_trend': data['monthly_trend'] ?? [],
          };
        }
      }
      return {'success': false, 'message': 'Gagal mengambil data dashboard.'};
    } catch (e) {
      return {'success': false, 'message': 'Error: $e'};
    }
  }

  // 3. Reports List
  static Future<List<ReportModel>> fetchReports({int? categoryId, String? status}) async {
    try {
      String url = ApiConfig.reports;
      List<String> query = [];
      if (categoryId != null && categoryId > 0) query.add('category_id=$categoryId');
      if (status != null && status.isNotEmpty) query.add('status=$status');
      if (query.isNotEmpty) url += '?${query.join('&')}';

      final res = await http.get(Uri.parse(url));
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['success'] == true) {
          return (data['reports'] as List? ?? [])
              .map((r) => ReportModel.fromJson(r))
              .toList();
        }
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  // 4. Report Detail
  static Future<ReportModel?> fetchReportDetail(int id) async {
    try {
      final res = await http.get(Uri.parse('${ApiConfig.reports}?id=$id'));
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['success'] == true && data['report'] != null) {
          return ReportModel.fromJson(data['report']);
        }
      }
      return null;
    } catch (e) {
      return null;
    }
  }

  // 5. Submit Report
  static Future<Map<String, dynamic>> submitReport(Map<String, dynamic> reportData) async {
    try {
      final res = await http.post(
        Uri.parse(ApiConfig.reports),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode(reportData),
      );
      final data = jsonDecode(res.body);
      return data;
    } catch (e) {
      return {'success': false, 'message': 'Gagal menyimpan laporan: $e'};
    }
  }

  // 6. Verification
  static Future<bool> submitVerification({
    required int reportId,
    required String status,
    required String notes,
    required int verifierId,
  }) async {
    try {
      final res = await http.post(
        Uri.parse(ApiConfig.verification),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'report_id': reportId,
          'status': status,
          'notes': notes,
          'verifier_id': verifierId,
        }),
      );
      final data = jsonDecode(res.body);
      return data['success'] == true;
    } catch (e) {
      return false;
    }
  }

  // 7. Schedules
  static Future<List<ScheduleModel>> fetchSchedules() async {
    try {
      final res = await http.get(Uri.parse(ApiConfig.schedules));
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['success'] == true) {
          return (data['schedules'] as List? ?? [])
              .map((s) => ScheduleModel.fromJson(s))
              .toList();
        }
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  // 8. Active Users
  static Future<List<UserModel>> fetchActiveUsers() async {
    try {
      final res = await http.get(Uri.parse(ApiConfig.activeUsers));
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['success'] == true) {
          return (data['officers'] as List? ?? [])
              .map((u) => UserModel.fromJson(u))
              .toList();
        }
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  // 9. Chat Messages
  static Future<List<ChatMessageModel>> fetchChatMessages({
    bool isTeam = true,
    int? partnerId,
    int? currentUserId,
  }) async {
    try {
      String url = ApiConfig.chat;
      if (isTeam) {
        url += '?room=team';
      } else if (partnerId != null && currentUserId != null) {
        url += '?user_id=$partnerId&current_user_id=$currentUserId';
      }

      final res = await http.get(Uri.parse(url));
      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        if (data['success'] == true) {
          return (data['messages'] as List? ?? [])
              .map((m) => ChatMessageModel.fromJson(m))
              .toList();
        }
      }
      return [];
    } catch (e) {
      return [];
    }
  }

  // 10. Send Chat Message
  static Future<bool> sendMessage({
    bool isTeam = true,
    int? receiverId,
    required String message,
    required int senderId,
  }) async {
    try {
      final res = await http.post(
        Uri.parse(ApiConfig.chat),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'is_team': isTeam ? 1 : 0,
          'receiver_id': receiverId,
          'message': message,
          'sender_id': senderId,
        }),
      );
      final data = jsonDecode(res.body);
      return data['success'] == true;
    } catch (e) {
      return false;
    }
  }

  // 11. Heartbeat
  static Future<void> sendHeartbeat(int userId) async {
    try {
      await http.post(
        Uri.parse(ApiConfig.heartbeat),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({'user_id': userId, 'device_type': 'mobile'}),
      );
    } catch (_) {}
  }
}
