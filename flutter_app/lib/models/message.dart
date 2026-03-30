class Message {
  final int id;
  final int conversationId;
  final String direction;
  final String type;
  final String? content;
  final String? fileUrl;
  final String? fileName;
  final String? fileMime;
  final String? caption;
  final int? agentId;
  final String? agentName;
  final String? status;
  final String createdAt;

  const Message({
    required this.id,
    required this.conversationId,
    required this.direction,
    required this.type,
    this.content,
    this.fileUrl,
    this.fileName,
    this.fileMime,
    this.caption,
    this.agentId,
    this.agentName,
    this.status,
    required this.createdAt,
  });

  factory Message.fromJson(Map<String, dynamic> json) => Message(
    id:             json['id'] as int,
    conversationId: json['conversation_id'] as int,
    direction:      json['direction'] as String,
    type:           (json['type'] as String?) ?? 'text',
    content:        json['content'] as String?,
    fileUrl:        json['file_url'] as String?,
    fileName:       json['file_name'] as String?,
    fileMime:       json['file_mime'] as String?,
    caption:        json['caption'] as String?,
    agentId:        json['agent_id'] as int?,
    agentName:      json['agent_name'] as String?,
    status:         json['status'] as String?,
    createdAt:      json['created_at'] as String,
  );

  bool get isOutgoing  => direction == 'out';
  bool get isImage     => type == 'image';
  bool get isDocument  => type == 'document';
  bool get failed      => status == 'failed';

  String get displayText {
    if (type == 'text') return content ?? '';
    if (caption != null && caption!.isNotEmpty) return caption!;
    if (fileName != null && fileName!.isNotEmpty) return fileName!;
    return isImage ? '📷 Imagen' : '📄 Documento';
  }
}
