// lib/services/auth_service.dart
import 'dart:async';
import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/user_model.dart';
import 'api_service.dart';

class AuthService {
  static UserModel? currentUser;
  static final ValueNotifier<ThemeMode> themeModeNotifier = ValueNotifier(ThemeMode.light);
  static Timer? _heartbeatTimer;

  static const String _userKey = 'monreport_user';
  static const String _themeKey = 'monreport_theme';

  static Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    final userJson = prefs.getString(_userKey);
    if (userJson != null) {
      try {
        currentUser = UserModel.fromJson(jsonDecode(userJson));
        startHeartbeat();
      } catch (_) {}
    }

    final savedTheme = prefs.getString(_themeKey) ?? currentUser?.themeMode ?? 'light';
    themeModeNotifier.value = savedTheme == 'dark' ? ThemeMode.dark : ThemeMode.light;
  }

  static Future<void> saveUser(UserModel user) async {
    currentUser = user;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_userKey, jsonEncode(user.toJson()));

    if (user.themeMode.isNotEmpty) {
      await setTheme(user.themeMode);
    }
    startHeartbeat();
  }

  static Future<void> logout() async {
    _heartbeatTimer?.cancel();
    currentUser = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_userKey);
  }

  static Future<void> setTheme(String mode) async {
    themeModeNotifier.value = mode == 'dark' ? ThemeMode.dark : ThemeMode.light;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_themeKey, mode);
  }

  static void toggleTheme() {
    final newMode = themeModeNotifier.value == ThemeMode.dark ? 'light' : 'dark';
    setTheme(newMode);
  }

  static void startHeartbeat() {
    _heartbeatTimer?.cancel();
    if (currentUser != null) {
      ApiService.sendHeartbeat(currentUser!.id);
      _heartbeatTimer = Timer.periodic(const Duration(seconds: 45), (timer) {
        if (currentUser != null) {
          ApiService.sendHeartbeat(currentUser!.id);
        } else {
          timer.cancel();
        }
      });
    }
  }
}
