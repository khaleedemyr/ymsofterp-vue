# App Version Update Guide

## Setup

### 1. Jalankan Query SQL

Jalankan query SQL di file `database/migrations/create_app_versions_table.sql` untuk membuat tabel `app_versions`.

### 2. Endpoint API

Endpoint sudah tersedia di:
```
POST /api/mobile/member/app/check-version
```

**Request Body:**
```json
{
  "current_version": "1.0.0",
  "platform": "android" // atau "ios"
}
```

**Response:**
```json
{
  "success": true,
  "needs_update": true,
  "current_version": "1.0.0",
  "latest_version": "1.1.0",
  "platform": "android",
  "store_url": "https://play.google.com/store/apps/details?id=com.justusgroup.memberapp",
  "force_update": false,
  "update_message": "A new version of the app is available. Please update to continue.",
  "whats_new": "Bug fixes and performance improvements"
}
```

## Cara Update Versi di Database

### Update Versi Android
```sql
UPDATE app_versions 
SET version = '1.1.0',
    play_store_url = 'https://play.google.com/store/apps/details?id=com.justusgroup.memberapp',
    force_update = 0,
    update_message = 'A new version is available with bug fixes and new features.',
    whats_new = 'Bug fixes and performance improvements',
    updated_at = NOW()
WHERE platform = 'android' AND is_active = 1;
```

### Update Versi iOS
```sql
UPDATE app_versions 
SET version = '1.1.0',
    app_store_url = 'https://apps.apple.com/app/id123456789',
    force_update = 0,
    update_message = 'A new version is available with bug fixes and new features.',
    whats_new = 'Bug fixes and performance improvements',
    updated_at = NOW()
WHERE platform = 'ios' AND is_active = 1;
```

### Force Update (Wajib Update)
```sql
UPDATE app_versions 
SET force_update = 1,
    update_message = 'This update is required. Please update now to continue using the app.',
    updated_at = NOW()
WHERE platform = 'android' AND is_active = 1;
```

## Implementasi di Mobile App (Contoh)

### React Native / Flutter

**1. Cek versi saat app dibuka (di Home Screen):**

```javascript
// Contoh untuk React Native
import { useEffect, useState } from 'react';
import { Platform, Linking, Alert } from 'react-native';
import axios from 'axios';

const API_BASE_URL = 'https://ymsofterp.com/api';

const useAppVersionCheck = () => {
  const [showUpdateModal, setShowUpdateModal] = useState(false);
  const [updateInfo, setUpdateInfo] = useState(null);

  useEffect(() => {
    checkAppVersion();
  }, []);

  const checkAppVersion = async () => {
    try {
      const currentVersion = '1.0.0'; // Ambil dari package.json atau app config
      const platform = Platform.OS === 'android' ? 'android' : 'ios';

      const response = await axios.post(
        `${API_BASE_URL}/mobile/member/app/check-version`,
        {
          current_version: currentVersion,
          platform: platform
        }
      );

      if (response.data.success && response.data.needs_update) {
        setUpdateInfo(response.data);
        setShowUpdateModal(true);
      }
    } catch (error) {
      console.error('Error checking app version:', error);
    }
  };

  const handleUpdate = () => {
    if (updateInfo?.store_url) {
      Linking.openURL(updateInfo.store_url);
    }
  };

  const handleLater = () => {
    if (updateInfo?.force_update) {
      // Jika force update, tetap tampilkan modal
      Alert.alert(
        'Update Required',
        'This update is required. Please update now to continue using the app.',
        [
          {
            text: 'Update Now',
            onPress: handleUpdate
          }
        ],
        { cancelable: false }
      );
    } else {
      // Jika optional, tutup modal
      setShowUpdateModal(false);
    }
  };

  return {
    showUpdateModal,
    updateInfo,
    handleUpdate,
    handleLater
  };
};

export default useAppVersionCheck;
```

**2. Modal Component:**

```javascript
import React from 'react';
import { Modal, View, Text, Button, StyleSheet } from 'react-native';

const UpdateModal = ({ visible, updateInfo, onUpdate, onLater }) => {
  if (!updateInfo) return null;

  return (
    <Modal
      visible={visible}
      transparent={true}
      animationType="fade"
      onRequestClose={updateInfo.force_update ? undefined : onLater}
    >
      <View style={styles.overlay}>
        <View style={styles.modal}>
          <Text style={styles.title}>Update Available</Text>
          <Text style={styles.message}>
            {updateInfo.update_message}
          </Text>
          {updateInfo.whats_new && (
            <View style={styles.whatsNew}>
              <Text style={styles.whatsNewTitle}>What's New:</Text>
              <Text style={styles.whatsNewText}>{updateInfo.whats_new}</Text>
            </View>
          )}
          <View style={styles.buttons}>
            {!updateInfo.force_update && (
              <Button title="Later" onPress={onLater} />
            )}
            <Button title="Update Now" onPress={onUpdate} />
          </View>
        </View>
      </View>
    </Modal>
  );
};

const styles = StyleSheet.create({
  overlay: {
    flex: 1,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  modal: {
    backgroundColor: 'white',
    borderRadius: 10,
    padding: 20,
    width: '80%',
    maxWidth: 400,
  },
  title: {
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 10,
  },
  message: {
    fontSize: 16,
    marginBottom: 15,
  },
  whatsNew: {
    marginBottom: 15,
  },
  whatsNewTitle: {
    fontSize: 14,
    fontWeight: 'bold',
    marginBottom: 5,
  },
  whatsNewText: {
    fontSize: 14,
  },
  buttons: {
    flexDirection: 'row',
    justifyContent: 'space-around',
    marginTop: 10,
  },
});

export default UpdateModal;
```

**3. Gunakan di Home Screen:**

```javascript
import React from 'react';
import { View } from 'react-native';
import useAppVersionCheck from './hooks/useAppVersionCheck';
import UpdateModal from './components/UpdateModal';

const HomeScreen = () => {
  const { showUpdateModal, updateInfo, handleUpdate, handleLater } = useAppVersionCheck();

  return (
    <View>
      {/* Your home screen content */}
      
      <UpdateModal
        visible={showUpdateModal}
        updateInfo={updateInfo}
        onUpdate={handleUpdate}
        onLater={handleLater}
      />
    </View>
  );
};

export default HomeScreen;
```

## Field Tabel app_versions

- `platform`: 'android' atau 'ios'
- `version`: Versi terbaru (contoh: '1.0.0', '2.1.3')
- `play_store_url`: URL Google Play Store (untuk Android)
- `app_store_url`: URL Apple App Store (untuk iOS)
- `force_update`: 1 = wajib update, 0 = optional
- `update_message`: Pesan yang ditampilkan ke user
- `whats_new`: Fitur baru atau perubahan (optional)
- `is_active`: 1 = aktif, 0 = tidak aktif

## Catatan

1. **Force Update**: Jika `force_update = 1`, user tidak bisa menutup modal dan harus update
2. **Version Comparison**: Menggunakan `version_compare()` PHP untuk membandingkan versi
3. **Platform**: Pastikan kirim platform yang benar ('android' atau 'ios')
4. **Store URLs**: Update URL Play Store/App Store sesuai dengan app ID yang sebenarnya

