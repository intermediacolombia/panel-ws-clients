import 'package:flutter/material.dart';

class Conversation {
  final int id;
  final String phone;
  final String contactName;
  final String status;
  final int? agentId;
  final String? agentName;
  final int? departmentId;
  final String? deptName;
  final String? deptColor;
  final int unreadCount;
  final String? lastMessage;
  final String? lastDirection;
  final String timeFormatted;

  const Conversation({
    required this.id,
    required this.phone,
    required this.contactName,
    required this.status,
    this.agentId,
    this.agentName,
    this.departmentId,
    this.deptName,
    this.deptColor,
    required this.unreadCount,
    this.lastMessage,
    this.lastDirection,
    required this.timeFormatted,
  });

  Conversation copyWith({String? contactName}) => Conversation(
    id:            id,
    phone:         phone,
    contactName:   contactName ?? this.contactName,
    status:        status,
    agentId:       agentId,
    agentName:     agentName,
    departmentId:  departmentId,
    deptName:      deptName,
    deptColor:     deptColor,
    unreadCount:   unreadCount,
    lastMessage:   lastMessage,
    lastDirection: lastDirection,
    timeFormatted: timeFormatted,
  );

  factory Conversation.fromJson(Map<String, dynamic> json) {
    final phone = json['phone'] as String;
    final name  = json['contact_name'] as String?;
    return Conversation(
      id:            json['id'] as int,
      phone:         phone,
      contactName:   (name != null && name.isNotEmpty) ? name : phone,
      status:        json['status'] as String,
      agentId:       json['agent_id'] as int?,
      agentName:     json['agent_name'] as String?,
      departmentId:  json['department_id'] as int?,
      deptName:      json['dept_name'] as String?,
      deptColor:     json['dept_color'] as String?,
      unreadCount:   json['unread_count'] as int? ?? 0,
      lastMessage:   json['last_message'] as String?,
      lastDirection: json['last_direction'] as String?,
      timeFormatted: json['time_formatted'] as String? ?? '',
    );
  }

  Color get statusColor {
    switch (status) {
      case 'pending':   return const Color(0xFFE67E22);
      case 'attending': return const Color(0xFF27AE60);
      case 'resolved':  return const Color(0xFF95A5A6);
      default:          return const Color(0xFF3498DB);
    }
  }

  String get statusLabel {
    switch (status) {
      case 'pending':   return 'Pendiente';
      case 'attending': return 'Atendiendo';
      case 'resolved':  return 'Resuelto';
      default:          return 'Bot';
    }
  }

  String get initials {
    final parts = contactName.trim().split(' ');
    if (parts.length >= 2) {
      return '${parts[0][0]}${parts[1][0]}'.toUpperCase();
    }
    return contactName.isNotEmpty ? contactName[0].toUpperCase() : '?';
  }

  Color get deptColorValue {
    if (deptColor == null || deptColor!.isEmpty) return const Color(0xFF95A5A6);
    try {
      return Color(int.parse(deptColor!.replaceFirst('#', '0xFF')));
    } catch (_) {
      return const Color(0xFF95A5A6);
    }
  }
}
