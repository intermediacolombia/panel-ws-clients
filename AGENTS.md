# AGENTS.md — Panel de Agentes WhatsApp

WhatsApp Agent Panel CRM: **PHP 8.1+** backend (MySQL/PDO, SSE) + **Flutter** mobile app (Android)

---

## Build Commands

### PHP Backend
```bash
# No build — PHP is interpreted. Configure via config.php/config-general.php
# Database: import schema.sql
```

### Flutter App
```bash
flutter pub get              # Install dependencies
flutter run                  # Debug mode
flutter build apk --release  # Build APK
flutter build appbundle --release  # Build AAB

# Tests
flutter test                          # Run all tests
flutter test test/widget_test.dart    # Run single test
flutter test --coverage               # With coverage

# Utils
dart run flutter_launcher_icons
flutter pub run change_app_package_name:main co.example.package
```

### Multi-tenant Build
```powershell
powershell -ExecutionPolicy Bypass -File flutter_app/build.ps1 -empresa intermedia -run
powershell -ExecutionPolicy Bypass -File flutter_app/build.ps1 -empresa intermedia -apk
powershell -ExecutionPolicy Bypass -File flutter_app/build.ps1 -empresa intermedia -release
```

---

## PHP Code Style

```php
<?php
/**
 * FileName.php — Short description.
 */

require_once __DIR__ . '/helpers.php';  // Use __DIR__, not dirname(__FILE__)
define('UPLOAD_DIR', __DIR__ . '/uploads/');
```

**Naming:** Classes=`PascalCase`, Functions=`snake_case`, Constants=`UPPER_SNAKE_CASE`, Variables=`camelCase`

**Type Declarations & Error Handling:**
```php
function jsonResponse(array $data, int $code = 200): void
function getOrCreateDepartment(string $areaLabel): ?int

try {
    $pdo = DB::get();
} catch (PDOException $e) {
    error_log('[Context] ' . $e->getMessage());
}
```

**Database:** Prepared statements only, `PDO::FETCH_ASSOC`, singleton via `DB::get()`

**Security:** `sanitize()` for output, whitelist MIME types, verify with `finfo`, never log passwords/tokens

---

## Flutter/Dart Code Style

**Imports:** package imports first, then relative imports

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/constants.dart';
import '../services/api_service.dart';
```

**Naming:** Classes/Models=`PascalCase`, Variables/Functions=`camelCase`, Private=`_underscorePrefix`, Constants=`kCamelCase`

**State Management (Provider):**
```dart
class ChatProvider extends ChangeNotifier {
  List<Conversation> _conversations = [];
  List<Conversation> get conversations => _conversations;
  
  Future<void> fetchConversations() async {
    notifyListeners();
  }
}
```

**API Calls — return `Map<String, dynamic>` with `success` key:**
```dart
try {
  final res = await ApiService.post(url, body);
  if (res['success'] == true) { /* handle success */ }
} catch (e, st) {
  debugPrint('[Context] Error: $e\n$st');
}
```

---

## Architecture

### PHP Backend
```
├── config.php, config-general.php, db.php, auth.php, helpers.php
├── api/   # REST endpoints (include auth.php)
├── sections/  # Web UI
├── incoming.php, webhook.php, sse.php
└── uploads/
```

### Flutter App
```
lib/
├── main.dart, core/constants.dart, core/theme.dart
├── models/, providers/, services/, screens/
```

---

## Key Patterns

**API Response:** `{ "success": true, "data": {...} }` or `{ "success": false, "error": "..." }`

**Conversation Status:** `pending → attending → resolved` or `(bot)`

**Authentication:** Web=session cookie, Mobile=Bearer token. Sessions in `agent_sessions` table

---

## Database
- MySQL 5.7+ / MariaDB 10.4+ with `utf8mb4_unicode_ci`
- Tables: `agents`, `agent_sessions`, `conversations`, `messages`, `departments`

---

## Important Notes
1. Firebase is **optional** — app works without `google-services.json`
2. SSE requires Nginx: disable buffering for `/sse.php`
3. Multi-tenant: Flutter builds via `build.ps1`
4. **No PHP testing framework** — manual verification required
5. Flutter SDK: `>=3.3.0 <4.0.0`

*Last updated: 2026-04-01*
