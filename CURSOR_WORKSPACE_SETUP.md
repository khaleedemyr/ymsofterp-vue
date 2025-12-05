# 🎯 Setup Cursor Workspace untuk Laravel + Flutter

## ✅ **Rekomendasi: Multi-Root Workspace**

Agar bisa coding Laravel dan Flutter di Cursor yang sama, gunakan **Multi-Root Workspace**.

---

## 📁 **Struktur Folder**

```
D:\Gawean\YM\web\
├── ymsofterp/                    # Laravel Backend
│   ├── app/
│   ├── routes/
│   └── ...
│
└── frontend/                     # Flutter Mobile App
    ├── lib/
    ├── pubspec.yaml
    └── ...
```

---

## 🔧 **Setup Multi-Root Workspace di Cursor**

### **Opsi 1: Multi-Root Workspace (DISARANKAN)**

1. **Buka Cursor**
2. **File → Add Folder to Workspace...**
3. **Pilih folder `D:\Gawean\YM\web\ymsofterp`** (Laravel)
4. **File → Add Folder to Workspace...** (lagi)
5. **Pilih folder `D:\Gawean\YM\web\frontend`** (Flutter)
6. **File → Save Workspace As...**
7. **Simpan sebagai `ymsofterp-workspace.code-workspace`**

**Hasil:**
- ✅ Bisa coding Laravel dan Flutter di Cursor yang sama
- ✅ Sidebar menampilkan kedua project
- ✅ Search bisa di kedua project
- ✅ Git bisa di kedua project

**File workspace:**
```json
{
  "folders": [
    {
      "path": "D:\\Gawean\\YM\\web\\ymsofterp",
      "name": "Laravel Backend"
    },
    {
      "path": "D:\\Gawean\\YM\\web\\frontend",
      "name": "Flutter Mobile App"
    }
  ],
  "settings": {
    "files.exclude": {
      "**/node_modules": true,
      "**/vendor": true,
      "**/.dart_tool": true,
      "**/build": true
    }
  }
}
```

---

### **Opsi 2: Buka Folder Parent (Alternatif)**

1. **File → Open Folder...**
2. **Pilih folder `D:\Gawean\YM\web\`** (parent folder)
3. **Buka kedua subfolder (`ymsofterp` dan `frontend`) di sidebar**

**Hasil:**
- ✅ Bisa lihat kedua project
- ⚠️ Workspace jadi lebih besar
- ⚠️ Search akan scan semua file

---

### **Opsi 3: Buka Terpisah (Tidak Disarankan)**

1. **Buka Cursor instance 1** → `D:\Gawean\YM\web\ymsofterp`
2. **Buka Cursor instance 2** → `D:\Gawean\YM\web\frontend`

**Hasil:**
- ❌ Harus switch antar window
- ❌ Tidak bisa search cross-project
- ❌ Tidak efisien

---

## 📋 **Langkah-langkah Setup**

### **Step 1: Buat Folder Flutter**

```bash
# Di Windows Explorer atau Command Prompt
mkdir D:\Gawean\YM\web\frontend
```

### **Step 2: Copy Flutter Project**

Copy semua file Flutter project ke:
```
D:\Gawean\YM\web\frontend\
```

### **Step 3: Setup Multi-Root Workspace**

1. **Buka Cursor**
2. **File → Add Folder to Workspace...**
3. **Pilih `D:\Gawean\YM\web\ymsofterp`**
4. **File → Add Folder to Workspace...** (lagi)
5. **Pilih `D:\Gawean\YM\web\frontend`**
6. **File → Save Workspace As...**
7. **Simpan di `D:\Gawean\YM\web\ymsofterp-workspace.code-workspace`**

### **Step 4: Buka Workspace**

Setelah itu, buka workspace file:
```
File → Open Workspace from File...
Pilih: D:\Gawean\YM\web\ymsofterp-workspace.code-workspace
```

---

## 🎨 **Tampilan di Cursor**

Setelah setup, sidebar akan terlihat seperti ini:

```
📁 EXPLORER
├── 📁 Laravel Backend (ymsofterp)
│   ├── 📁 app
│   ├── 📁 routes
│   ├── 📁 database
│   └── ...
│
└── 📁 Flutter Mobile App (frontend)
    ├── 📁 lib
    ├── 📁 android
    ├── 📁 ios
    └── ...
```

---

## ⚙️ **Settings untuk Multi-Root Workspace**

**File: `ymsofterp-workspace.code-workspace`**

```json
{
  "folders": [
    {
      "path": "D:\\Gawean\\YM\\web\\ymsofterp",
      "name": "Laravel Backend"
    },
    {
      "path": "D:\\Gawean\\YM\\web\\frontend",
      "name": "Flutter Mobile App"
    }
  ],
  "settings": {
    // Exclude folders yang tidak perlu
    "files.exclude": {
      "**/node_modules": true,
      "**/vendor": true,
      "**/.dart_tool": true,
      "**/build": true,
      "**/.flutter-plugins": true,
      "**/.flutter-plugins-dependencies": true,
      "**/.packages": true,
      "**/.pub-cache": true,
      "**/.pub": true
    },
    // Search exclude
    "search.exclude": {
      "**/node_modules": true,
      "**/vendor": true,
      "**/build": true,
      "**/.dart_tool": true
    },
    // File watcher exclude
    "files.watcherExclude": {
      "**/node_modules/**": true,
      "**/vendor/**": true,
      "**/build/**": true,
      "**/.dart_tool/**": true
    }
  },
  "extensions": {
    "recommendations": [
      "bmewburn.vscode-intelephense-client",  // PHP
      "dart-code.dart-code",                 // Dart/Flutter
      "dart-code.flutter",                   // Flutter
      "ms-vscode.vscode-json"                // JSON
    ]
  }
}
```

---

## 🔍 **Tips Development**

### **1. Search di Kedua Project**

- **Ctrl+Shift+F** → Search di semua workspace
- **Ctrl+P** → Quick open file dari kedua project

### **2. Terminal**

- Bisa buka multiple terminal
- Terminal 1: Laravel (`cd ymsofterp`)
- Terminal 2: Flutter (`cd ymsofterp-mobile`)

### **3. Git**

- Git status per folder
- Commit terpisah untuk Laravel dan Flutter

### **4. Extensions**

Install extensions untuk:
- **PHP/Laravel**: Intelephense, Laravel Extension Pack
- **Dart/Flutter**: Dart, Flutter

---

## 📝 **Workflow Development**

### **Skenario 1: Update API di Laravel**

1. Edit controller di `ymsofterp/app/Http/Controllers/Mobile/Member/`
2. Test dengan Postman atau Flutter
3. Flutter langsung bisa test karena di workspace yang sama

### **Skenario 2: Update Flutter**

1. Edit code di `frontend/lib/`
2. Hot reload di Flutter
3. Test API call ke Laravel

### **Skenario 3: Debug Bersama**

1. Set breakpoint di Laravel (Xdebug)
2. Set breakpoint di Flutter (Dart DevTools)
3. Debug kedua sisi secara bersamaan

---

## ✅ **Checklist Setup**

- [ ] Folder `frontend` sudah dibuat
- [ ] Flutter project sudah di-copy
- [ ] Multi-root workspace sudah dibuat
- [ ] Workspace file sudah disimpan
- [ ] Extensions sudah di-install (PHP + Flutter)
- [ ] Base URL di Flutter sudah di-update
- [ ] Test koneksi API dari Flutter ke Laravel

---

## 🚀 **Quick Start**

1. **Buat folder:**
   ```bash
   mkdir D:\Gawean\YM\web\frontend
   ```

2. **Copy Flutter project** ke folder tersebut

3. **Buka Cursor → File → Add Folder to Workspace**
   - Tambahkan `ymsofterp`
   - Tambahkan `frontend`

4. **Save workspace** sebagai `ymsofterp-workspace.code-workspace`

5. **Buka workspace** setiap kali coding

---

## 💡 **Kesimpulan**

✅ **Gunakan Multi-Root Workspace** agar bisa coding Laravel dan Flutter di Cursor yang sama!

**Struktur:**
```
D:\Gawean\YM\web\
├── ymsofterp/              # Laravel
└── frontend/               # Flutter
```

**Workspace File:**
```
D:\Gawean\YM\web\ymsofterp-workspace.code-workspace
```

Dengan setup ini, Anda bisa:
- ✅ Coding Laravel dan Flutter di Cursor yang sama
- ✅ Search di kedua project
- ✅ Git terpisah tapi di workspace yang sama
- ✅ Terminal untuk kedua project
- ✅ Debug kedua sisi

---

**Selamat coding!** 🎉


