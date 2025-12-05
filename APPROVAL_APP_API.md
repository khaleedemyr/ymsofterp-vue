# Approval App API Documentation

API endpoints khusus untuk Approval Mobile App (terpisah dari web dan member app).

## Base URL
```
https://ymsofterp.com/api/approval-app
```

## Authentication

Approval App menggunakan `remember_token` untuk authentication, berbeda dengan:
- **Web App**: Menggunakan session-based authentication
- **Member App**: Menggunakan token khusus untuk member apps

## Endpoints

### 1. Login
**POST** `/api/approval-app/auth/login`

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password",
  "device_id": "optional-device-id",
  "device_info": {
    "platform": "android",
    "model": "device-model",
    "manufacturer": "manufacturer-name",
    "version": "android-version"
  }
}
```

**Response (Success):**
```json
{
  "success": true,
  "access_token": "generated-token",
  "token_type": "bearer",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "nama_lengkap": "User Name",
    "division_name": "Division Name",
    "jabatan_name": "Jabatan Name",
    "outlet_name": "Outlet Name",
    ...
  }
}
```

**Response (Error):**
```json
{
  "success": false,
  "message": "Email atau password salah"
}
```

### 2. Get Current User
**GET** `/api/approval-app/auth/me`

**Headers:**
```
Authorization: Bearer {access_token}
Accept: application/json
```

**Response:**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "email": "user@example.com",
    "nama_lengkap": "User Name",
    ...
  }
}
```

### 3. Logout
**POST** `/api/approval-app/auth/logout`

**Headers:**
```
Authorization: Bearer {access_token}
Accept: application/json
```

**Response:**
```json
{
  "success": true,
  "message": "Berhasil logout"
}
```

## Middleware

Approval App menggunakan custom middleware `ApprovalAppAuth` yang:
- Memvalidasi Bearer token dari header
- Mencari user berdasarkan `remember_token`
- Menambahkan user ke request untuk akses mudah

## Controller Location

- **Auth Controller:** `app/Http/Controllers/Mobile/ApprovalApp/AuthController.php`
- **Middleware:** `app/Http/Middleware/ApprovalAppAuth.php`

## Differences from Other Apps

| Feature | Web App | Member App | Approval App |
|---------|---------|------------|--------------|
| Auth Method | Session | Custom Token | remember_token |
| Route Prefix | `/` | `/api/mobile/member` | `/api/approval-app` |
| Controller Path | Various | `Mobile/Member/` | `Mobile/ApprovalApp/` |
| User Model | `User` | `MemberAppsMember` | `User` |

## Security Notes

- Token disimpan di `users.remember_token`
- Token di-generate menggunakan `bin2hex(random_bytes(32))`
- Device info optional, digunakan untuk tracking
- User harus memiliki status `'A'` (Active)

