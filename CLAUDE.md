# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WhatsApp CRM panel for managing agent-customer conversations. Consists of:
- **PHP/MySQL web panel** — server-rendered, deployed at `https://panelws.intermediahost.co`
- **Flutter Android app** — in `flutter_app/`, connects to the same PHP API

## Flutter App — Build Commands

```bash
cd flutter_app
flutter pub get          # Install dependencies
flutter run              # Run in debug on connected device
flutter build apk --release        # Build release APK
flutter build appbundle --release  # Build AAB for Play Store
flutter test                       # Run tests
```

Multi-tenant build via PowerShell:
```powershell
.\build.ps1 -empresa intermedia -apk
```

## Architecture

### PHP Backend
- **`config.php`** — DB credentials, WhatsApp API URL/key, panel areas (departments)
- **`config-general.php`** — Upload paths, session expiry (720h), rate limits, timezone (America/Bogota)
- **`db.php`** — PDO singleton (utf8mb4)
- **`auth.php`** — Session validation; populates `$currentAgent` global with role + departments
- **`incoming.php`** — Webhook entry point; authenticated by `X-Agent-Secret` header
- **`sse.php`** — Server-Sent Events stream for real-time web updates
- **`/api/`** — 23 REST endpoints returning `{"success": bool, "data": ...}` or `{"success": false, "error": "..."}`

### Conversation Status Flow
```
pending → attending → resolved
            ↓
           bot  (webhook retakes control)
```

### Flutter App Structure (`flutter_app/lib/`)
- **`main.dart`** — Firebase init, background service init, battery optimization request, Provider setup
- **`providers/chat_provider.dart`** — All conversation + message state; polls API every 5s (conversations) / 3s (messages)
- **`providers/auth_provider.dart`** — Login/logout, token persistence via SharedPreferences
- **`services/api_service.dart`** — HTTP client; token stored as `agent_token` in SharedPreferences
- **`services/background_service.dart`** — Foreground service (flutter_background_service); polls every 30s in background/killed state; shows local notifications for new messages
- **`services/notification_service.dart`** — Local notifications via flutter_local_notifications
- **`screens/conversations_screen.dart`** — Conversation list with tabs (all/pending/attending/resolved), search, department chip, profile photo modal
- **`screens/chat_screen.dart`** — Chat UI with message bubbles, image tap-to-expand modal, quick replies, file attachments

### Authentication
- **Web:** Session cookie `agent_token` (HttpOnly, Secure) + CSRF token; 30-day sliding expiry
- **Mobile:** Bearer token in `Authorization` header; stored in SharedPreferences as `agent_token`
- Roles: `supervisor` (sees all conversations) vs `agente` (sees own department + assigned)

### Real-Time (Web)
- Primary: SSE (`sse.php?token=...`) — long-lived HTTP connection
- Fallback: `api/poll.php` long-polling

### Real-Time (Mobile)
- Foreground: polling via `Timer.periodic` in screen widgets
- Background/killed: foreground Android service (flutter_background_service) polls every 30s
- Dead state: Firebase Cloud Messaging (requires `google-services.json` and server-side FCM push)

### Key API Endpoints
| Endpoint | Purpose |
|---|---|
| `POST api/login.php` | Returns Bearer token |
| `GET api/conversations.php?status=all&limit=100` | Conversation list |
| `GET api/conversation.php?id=X` | Messages for conversation |
| `POST api/send.php` | Send text or file (base64) |
| `POST api/assign.php` | Assign conversation to current agent |
| `POST api/resolve.php` | Mark resolved |
| `POST api/release.php` | Release to bot |
| `GET api/profile_picture.php?phone=X` | Contact photo (requires Bearer token) |

### Database Tables
`agents`, `departments`, `agent_departments`, `agent_sessions`, `bot_estados`, `conversations`, `messages`, `login_attempts`

### Android Manifest Notes
- `FOREGROUND_SERVICE_DATA_SYNC` required for Android 14+ (API 34) with dataSync foreground service type
- `stopWithTask="false"` on BackgroundService allows survival after user removes app from recents
- `REQUEST_IGNORE_BATTERY_OPTIMIZATIONS` requested at runtime on first launch
