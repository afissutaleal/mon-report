// lib/models/category_model.dart

class CategoryModel {
  final int id;
  final String code;
  final String name;
  final String shortTitle;
  final String description;
  final String icon;
  final String badgeColor;
  final int targetAnnual;
  final int realized;
  final int verified;
  final int pending;
  final int revision;
  final double percentage;
  final String unit;

  CategoryModel({
    required this.id,
    required this.code,
    required this.name,
    required this.shortTitle,
    required this.description,
    this.icon = 'fa-broadcast-tower',
    this.badgeColor = 'primary',
    this.targetAnnual = 100,
    this.realized = 0,
    this.verified = 0,
    this.pending = 0,
    this.revision = 0,
    this.percentage = 0.0,
    this.unit = 'Kegiatan',
  });

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    return CategoryModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      code: json['code']?.toString() ?? '',
      name: json['name']?.toString() ?? '',
      shortTitle: json['short_title']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      icon: json['icon']?.toString() ?? 'fa-broadcast-tower',
      badgeColor: json['badge_color']?.toString() ?? 'primary',
      targetAnnual: int.tryParse(json['target_annual']?.toString() ?? '100') ?? 100,
      realized: int.tryParse(json['realized']?.toString() ?? '0') ?? 0,
      verified: int.tryParse(json['verified']?.toString() ?? '0') ?? 0,
      pending: int.tryParse(json['pending']?.toString() ?? '0') ?? 0,
      revision: int.tryParse(json['revision']?.toString() ?? '0') ?? 0,
      percentage: double.tryParse(json['percentage']?.toString() ?? '0.0') ?? 0.0,
      unit: json['unit']?.toString() ?? 'Kegiatan',
    );
  }
}
