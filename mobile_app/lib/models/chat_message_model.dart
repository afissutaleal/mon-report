// lib/models/chat_message_model.dart

class ChatMessageModel {
  final int id;
  final int senderId;
  final int? receiverId;
  final bool isTeamChat;
  final String message;
  final String createdAt;
  final String timeFormatted;
  final String senderName;
  final String senderJabatan;
  final String avatar;
  final bool isMe;

  ChatMessageModel({
    required this.id,
    required this.senderId,
    this.receiverId,
    this.isTeamChat = false,
    required this.message,
    required this.createdAt,
    this.timeFormatted = '',
    required this.senderName,
    this.senderJabatan = '',
    this.avatar = '',
    this.isMe = false,
  });

  factory ChatMessageModel.fromJson(Map<String, dynamic> json) {
    return ChatMessageModel(
      id: int.tryParse(json['id']?.toString() ?? '0') ?? 0,
      senderId: int.tryParse(json['sender_id']?.toString() ?? '0') ?? 0,
      receiverId: json['receiver_id'] != null ? int.tryParse(json['receiver_id'].toString()) : null,
      isTeamChat: json['is_team_chat'] == true || json['is_team_chat'] == 1 || json['is_team_chat'] == '1',
      message: json['message']?.toString() ?? '',
      createdAt: json['created_at']?.toString() ?? '',
      timeFormatted: json['time_formatted']?.toString() ?? '',
      senderName: json['sender_name']?.toString() ?? '',
      senderJabatan: json['sender_jabatan']?.toString() ?? '',
      avatar: json['avatar']?.toString() ?? '',
      isMe: json['is_me'] == true || json['is_me'] == 1,
    );
  }
}
