class ApiConstants {
  static const String baseUrl = 'https://panelws.intermediahost.co';

  static const String loginUrl         = '$baseUrl/api/login.php';
  static const String logoutUrl        = '$baseUrl/api/logout.php';
  static const String conversationsUrl = '$baseUrl/api/conversations.php';
  static const String conversationUrl  = '$baseUrl/api/conversation.php';
  static const String sendUrl          = '$baseUrl/api/send.php';
  static const String assignUrl        = '$baseUrl/api/assign.php';
  static const String releaseUrl       = '$baseUrl/api/release.php';
  static const String transferUrl      = '$baseUrl/api/transfer.php';
  static const String resolveUrl       = '$baseUrl/api/resolve.php';
  static const String onlineAgentsUrl  = '$baseUrl/api/online_agents.php';
  static const String profilePicUrl    = '$baseUrl/api/profile_picture.php';
  static const String reopenUrl        = '$baseUrl/api/reopen.php';
  static const String startConvUrl     = '$baseUrl/api/start_conversation.php';
  static const String fcmTokenUrl      = '$baseUrl/api/fcm_token.php';

  static const Duration pollConversations = Duration(seconds: 5);
  static const Duration pollMessages      = Duration(seconds: 3);
  static const Duration requestTimeout    = Duration(seconds: 15);
}
