# Setup del proyecto Flutter

## 1. Crear proyecto base

```bash
flutter create flutter_app --org co.intermedia
cd flutter_app
```

Reemplaza `lib/` y `pubspec.yaml` con los archivos de esta carpeta, luego:

```bash
flutter pub get
```

---

## 2. Permisos Android

En `android/app/src/main/AndroidManifest.xml`, agrega dentro de `<manifest>`:

```xml
<uses-permission android:name="android.permission.INTERNET"/>
<uses-permission android:name="android.permission.POST_NOTIFICATIONS"/>
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" android:maxSdkVersion="32"/>
<uses-permission android:name="android.permission.READ_MEDIA_IMAGES"/>
<uses-permission android:name="android.permission.READ_MEDIA_VIDEO"/>
<uses-permission android:name="android.permission.CAMERA"/>
```

---

## 3. Permisos iOS

En `ios/Runner/Info.plist`, agrega:

```xml
<key>NSCameraUsageDescription</key>
<string>Necesitamos acceso a la cámara para enviar fotos.</string>
<key>NSPhotoLibraryUsageDescription</key>
<string>Necesitamos acceso a la galería para enviar imágenes.</string>
<key>NSMicrophoneUsageDescription</key>
<string>Se requiere para el acceso a la cámara.</string>
```

---

## 4. Notificaciones push en background (opcional)

Para recibir notificaciones cuando la app está cerrada, se requiere **Firebase Cloud Messaging**:

1. Crea un proyecto en [Firebase Console](https://console.firebase.google.com)
2. Agrega app Android/iOS al proyecto
3. Descarga `google-services.json` → colócalo en `android/app/`
4. Descarga `GoogleService-Info.plist` → colócalo en `ios/Runner/`
5. Agrega a `pubspec.yaml`:
   ```yaml
   firebase_core: ^3.0.0
   firebase_messaging: ^15.0.0
   ```
6. Sigue la guía oficial de FlutterFire: https://firebase.flutter.dev/docs/messaging/overview

---

## 5. Correr la app

```bash
flutter run
```

Para generar APK de producción:

```bash
flutter build apk --release
```
