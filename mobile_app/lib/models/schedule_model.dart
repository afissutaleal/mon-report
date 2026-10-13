// lib/models/schedule_model.dart

class ScheduleModel {
  final int id;
  final String sptNumber;
  final String title;
  final int categoryId;
  final String categoryTitle;
  final String badgeColor;
  final String leaderName;
  final String leaderAvatar;
  final String memberNames;
  final String location;
  final String targetObject;
  final String startDate;
  final String endDate;
  final String status;
  final String notes;

  ScheduleModel({
    required this.id,
    required this.sptNumber,
    required this.title,
    required this.categoryId,
    this.categoryTitle = '',
    this.badgeColor = 'primary',
    required this.leaderName,
    this.leaderAvatar = '',
    this.memberNames = '',
    required this.location,
    required this.targetObject,
    required this.startDate,
    required this.endDate,
    this.status = 'scheduled',
    this.notes = '',
  });

  factory ScheduleModel.fromJson(Map<String, dynamic> json) {
    return ScheduleModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      sptNumber: json['spt_number']?.toString() ?? '',
      title: json['title']?.toString() ?? '',
      categoryId: int.tryParse(json['category_id']?.toString() ?? '1') ?? 1,
      categoryTitle: json['short_title']?.toString() ?? json['category_title']?.toString() ?? '',
      badgeColor: json['badge_color']?.toString() ?? 'primary',
      leaderName: json['leader_name']?.toString() ?? '',
      leaderAvatar: json['leader_avatar']?.toString() ?? '',
      memberNames: json['member_names']?.toString() ?? '',
      location: json['location']?.toString() ?? '',
      targetObject: json['target_object']?.toString() ?? '',
      startDate: json['start_date']?.toString() ?? '',
      endDate: json['end_date']?.toString() ?? '',
      status: json['status']?.toString() ?? 'scheduled',
      notes: json['notes']?.toString() ?? '',
    );
  }
}
