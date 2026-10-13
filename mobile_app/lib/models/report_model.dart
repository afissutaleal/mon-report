// lib/models/report_model.dart

class ReportModel {
  final int id;
  final String reportCode;
  final int categoryId;
  final String categoryName;
  final String categoryShort;
  final String badgeColor;
  final String title;
  final String targetName;
  final String frequency;
  final String location;
  final double latitude;
  final double longitude;
  final String dateMonitored;
  final String status;
  final String officerName;
  final String officerNip;
  final String officerAvatar;
  final String? verifierName;
  final String verificationNotes;
  final Map<String, dynamic> technicalData;

  ReportModel({
    required this.id,
    required this.reportCode,
    required this.categoryId,
    this.categoryName = '',
    this.categoryShort = '',
    this.badgeColor = 'primary',
    required this.title,
    required this.targetName,
    this.frequency = '',
    required this.location,
    this.latitude = -0.502106,
    this.longitude = 117.153709,
    required this.dateMonitored,
    this.status = 'submitted',
    this.officerName = '',
    this.officerNip = '',
    this.officerAvatar = '',
    this.verifierName,
    this.verificationNotes = '',
    this.technicalData = const {},
  });

  factory ReportModel.fromJson(Map<String, dynamic> json) {
    Map<String, dynamic> tech = {};
    if (json['technical_data_parsed'] is Map<String, dynamic>) {
      tech = json['technical_data_parsed'];
    }

    return ReportModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      reportCode: json['report_code']?.toString() ?? '',
      categoryId: int.tryParse(json['category_id']?.toString() ?? '1') ?? 1,
      categoryName: json['category_name']?.toString() ?? '',
      categoryShort: json['short_title']?.toString() ?? '',
      badgeColor: json['badge_color']?.toString() ?? 'primary',
      title: json['title']?.toString() ?? '',
      targetName: json['target_name']?.toString() ?? '',
      frequency: json['frequency']?.toString() ?? '',
      location: json['location']?.toString() ?? '',
      latitude: double.tryParse(json['latitude']?.toString() ?? '-0.502106') ?? -0.502106,
      longitude: double.tryParse(json['longitude']?.toString() ?? '117.153709') ?? 117.153709,
      dateMonitored: json['date_monitored']?.toString() ?? '',
      status: json['status']?.toString() ?? 'submitted',
      officerName: json['officer_name']?.toString() ?? '',
      officerNip: json['officer_nip']?.toString() ?? '',
      officerAvatar: json['officer_avatar']?.toString() ?? '',
      verifierName: json['verifier_name']?.toString(),
      verificationNotes: json['verification_notes']?.toString() ?? '',
      technicalData: tech,
    );
  }
}
