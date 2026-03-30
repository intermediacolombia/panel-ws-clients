class Agent {
  final int id;
  final String username;
  final String name;
  final String email;
  final String role;
  final List<AgentDept> departments;

  const Agent({
    required this.id,
    required this.username,
    required this.name,
    required this.email,
    required this.role,
    required this.departments,
  });

  factory Agent.fromJson(Map<String, dynamic> json) => Agent(
    id:          json['id'] as int,
    username:    json['username'] as String,
    name:        json['name'] as String,
    email:       (json['email'] as String?) ?? '',
    role:        json['role'] as String,
    departments: (json['departments'] as List<dynamic>? ?? [])
        .map((d) => AgentDept.fromJson(d as Map<String, dynamic>))
        .toList(),
  );

  Map<String, dynamic> toJson() => {
    'id': id, 'username': username, 'name': name,
    'email': email, 'role': role,
    'departments': departments.map((d) => d.toJson()).toList(),
  };

  bool get isSupervisor => role == 'supervisor';
}

class AgentDept {
  final int id;
  final String slug;
  final String name;

  const AgentDept({required this.id, required this.slug, required this.name});

  factory AgentDept.fromJson(Map<String, dynamic> json) => AgentDept(
    id:   json['id'] as int,
    slug: json['slug'] as String,
    name: json['name'] as String,
  );

  Map<String, dynamic> toJson() => {'id': id, 'slug': slug, 'name': name};
}
