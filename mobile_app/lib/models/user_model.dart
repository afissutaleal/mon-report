// lib/models/user_model.dart

class UserModel {
  final int id;
  final String nip;
  final String name;
  final String email;
  final String role;
  final String jabatan;
  final String pangkatGol;
  final String timKerja;
  final String phone;
  final String avatar;
  final String themeMode;
  final bool isOnline;
  final String deviceType;
  final String lastActive;

  UserModel({
    required this.id,
    required this.nip,
    required this.name,
    required this.email,
    required this.role,
    required this.jabatan,
    this.pangkatGol = '',
    this.timKerja = '',
    this.phone = '',
    this.avatar = '',
    this.themeMode = 'light',
    this.isOnline = false,
    this.deviceType = 'web',
    this.lastActive = '',
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      nip: json['nip']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      role: json['role']?.toString() ?? 'petugas',
      jabatan: json['jabatan']?.toString() ?? '',
      pangkatGol: json['pangkat_gol']?.toString() ?? '',
      timKerja: json['tim_kerja']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      avatar: json['avatar']?.toString() ?? '',
      themeMode: json['theme_mode']?.toString() ?? 'light',
      isOnline: json['is_online'] == true || json['is_online'] == 1 || json['is_online'] == '1',
      deviceType: json['device_type']?.toString() ?? 'web',
      lastActive: json['last_active']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nip': nip,
      'name': name,
      'email': email,
      'role': role,
      'jabatan': jabatan,
      'pangkat_gol': pangkatGol,
      'tim_kerja': timKerja,
      'phone': phone,
      'avatar': avatar,
      'theme_mode': themeMode,
      'is_online': isOnline,
      'device_type': deviceType,
      'last_active': lastActive,
    };
  }
}
