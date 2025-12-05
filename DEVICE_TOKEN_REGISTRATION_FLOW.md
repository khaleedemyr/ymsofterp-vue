# 📱 Device Token Registration Flow

## ✅ **Status: Device Token Register Setelah Login/Register**

Device token **TIDAK** di-register saat register, tapi **SETELAH** member login atau app dibuka.

---

## 🔄 **Flow Device Token Registration**

### **Skenario 1: Register → Auto Login → Register Device Token**

```
1. User Register
   ↓
2. Backend create member + return token
   ↓
3. Flutter save token (auto-login)
   ↓
4. Flutter navigate ke Home Screen
   ↓
5. Home Screen detect user sudah login
   ↓
6. Flutter get FCM token dari Firebase
   ↓
7. Flutter call: POST /api/mobile/member/device-token/register
   ↓
8. Backend insert ke member_apps_device_tokens ✅
```

### **Skenario 2: Login → Register Device Token**

```
1. User Login
   ↓
2. Backend return token
   ↓
3. Flutter save token
   ↓
4. Flutter navigate ke Home Screen
   ↓
5. Home Screen detect user sudah login
   ↓
6. Flutter get FCM token dari Firebase
   ↓
7. Flutter call: POST /api/mobile/member/device-token/register
   ↓
8. Backend insert ke member_apps_device_tokens ✅
```

### **Skenario 3: App Buka (Auto-Login) → Register Device Token**

```
1. App buka, cek login status
   ↓
2. User sudah login (token tersimpan)
   ↓
3. Flutter get FCM token dari Firebase
   ↓
4. Flutter call: POST /api/mobile/member/device-token/register
   ↓
5. Backend insert/update ke member_apps_device_tokens ✅
```

---

## 📋 **Kapan Device Token Di-Register?**

### ✅ **Di-Register:**
1. **Setelah login sukses** (di Home Screen atau setelah login)
2. **Saat app pertama kali dibuka** (jika user sudah login)
3. **Saat FCM token refresh** (Firebase bisa refresh token)

### ❌ **TIDAK Di-Register:**
1. **Saat register** (karena belum ada FCM token, app baru buka)
2. **Sebelum login** (karena perlu auth token)

---

## 🔧 **Implementasi di Flutter**

### **Option 1: Register di Home Screen (Recommended)**

```dart
// lib/screens/home_screen.dart
@override
void initState() {
  super.initState();
  _checkAuthAndLoadData();
  _registerDeviceToken(); // Register setelah login
}

Future<void> _registerDeviceToken() async {
  try {
    final isLoggedIn = await AuthService.isLoggedIn();
    if (!isLoggedIn) return;

    final token = await AuthService.getUserToken();
    if (token == null) return;

    // Get FCM token (contoh, sesuaikan dengan implementasi FCM Anda)
    String? fcmToken = await FirebaseMessaging.instance.getToken();
    
    if (fcmToken != null) {
      await ApiService.registerDeviceToken(
        deviceToken: fcmToken,
        deviceType: Platform.isAndroid ? 'android' : 'ios',
        authToken: token,
      );
      print('Device token registered successfully');
    }
  } catch (e) {
    print('Error registering device token: $e');
  }
}
```

### **Option 2: Register di Login/Register Screen (Setelah Success)**

```dart
// lib/screens/login_screen.dart atau register_screen.dart
// Setelah login/register success
if (response['success'] == true) {
  // Save token
  await AuthService.login(member['id'].toString(), token);
  
  // Register device token
  try {
    String? fcmToken = await FirebaseMessaging.instance.getToken();
    if (fcmToken != null) {
      await ApiService.registerDeviceToken(
        deviceToken: fcmToken,
        deviceType: Platform.isAndroid ? 'android' : 'ios',
        authToken: token,
      );
    }
  } catch (e) {
    print('Error registering device token: $e');
  }
  
  // Navigate to home
  Navigator.pushReplacement(...);
}
```

---

## 📊 **Table: member_apps_device_tokens**

Device token akan ter-insert ke table ini **SETELAH**:
- ✅ Member login (dan FCM token tersedia)
- ✅ App dibuka (jika user sudah login)
- ✅ FCM token refresh

**TIDAK** ter-insert saat:
- ❌ Register (karena belum ada FCM token)
- ❌ Sebelum login (karena perlu auth token)

---

## ✅ **Kesimpulan**

**Device token TIDAK di-register saat register**, tapi:
1. ✅ **Setelah login sukses** → Register device token
2. ✅ **Saat app dibuka** (jika sudah login) → Register device token
3. ✅ **Saat FCM token refresh** → Update device token

Ini adalah **flow yang benar** karena:
- Device token perlu FCM token dari Firebase
- FCM token biasanya baru tersedia setelah app fully loaded
- Device token perlu auth token untuk register (protected endpoint)

---

## 🚀 **Next Steps**

1. **Implementasi di Flutter**:
   - Tambahkan register device token di Home Screen setelah login
   - Atau di Login/Register screen setelah success

2. **Test**:
   - Register member baru
   - Login
   - Cek table `member_apps_device_tokens` → harus ada data

---

**Status: Flow sudah benar, tinggal implementasi di Flutter untuk register device token setelah login!** ✅

