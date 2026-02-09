# Trace Notifikasi – ymsofterp

Dokumen ini memetakan alur notifikasi di aplikasi: route, controller, model, pembuatan notifikasi, dan frontend.

---

## 1. API Routes (Web / ERP – user auth)

**File:** `routes/api.php` (dalam `Route::middleware('auth')->group`, ~baris 129–149)

| Method | Path | Controller | Method |
|--------|------|------------|--------|
| GET | `/api/notifications` | `App\Http\Controllers\NotificationController` | `index` |
| GET | `/api/notifications/unread-count` | `NotificationController` | `unreadCount` |
| POST | `/api/notifications/{id}/read` | `NotificationController` | `markAsRead` |
| POST | `/api/notifications/read-all` | `NotificationController` | `markAllAsRead` |

Prefix `/api` biasanya dari `RouteServiceProvider` (prefix `api`).

---

## 2. Controller – NotificationController (ERP)

**File:** `app/Http/Controllers/NotificationController.php`

| Method | Fungsi | Cache |
|--------|--------|--------|
| `index()` | List notifikasi user (50 terakhir), di-map ke array | `Cache::remember("notifications_user_{id}", 30, ...)` |
| `unreadCount()` | Hitung notifikasi belum dibaca | `Cache::remember("notifications_unread_count_{id}", 60, ...)` |
| `markAsRead($id)` | Set satu notifikasi jadi read, lalu invalidate cache user | Cache di-forget setelah update |
| `markAllAsRead()` | Set semua notifikasi user jadi read, lalu invalidate cache | Cache di-forget setelah update |

- Semua method pakai `Auth::id()` (user login web).
- Model: `App\Models\Notification` (tabel `notifications`).

---

## 3. Model

**File:** `app/Models/Notification.php`

- Tabel: `notifications`
- Fillable: `user_id`, `task_id`, `approval_id`, `type`, `title`, `message`, `url`, `is_read`
- Relasi: `user()`, `task()` (MaintenanceOrder)

---

## 4. Pembuatan notifikasi (siapa yang insert)

Notifikasi **dibuat** lewat dua cara:

### A. Langsung `Notification::create()`

| File | Keterangan |
|------|------------|
| `FoodPaymentController.php` | Setelah approve/reject Food Payment (notif ke `created_by`) |
| `NonFoodPaymentController.php` | Setelah approve/reject Non Food Payment |
| `ContraBonController.php` | Setelah aksi terkait Contra Bon |

### B. Via `NotificationService::insert()` / `create()`

**File:** `app/Services/NotificationService.php`

- `NotificationService::insert($data)` → single/batch, memanggil `create()`.
- `NotificationService::create($data)` → validasi `user_id` & `message`, generate `title` dari `type`, lalu `Notification::create()`.
- Banyak controller approval memakai service ini agar konsisten + bisa dipakai observer (mis. push FCM).

**Controller yang memanggil NotificationService (contoh):**

- `StockOpnameController` – `sendNotificationToNextApprover()` → notif ke next approver
- `OutletTransferController` – notif transfer/approval
- `OutletInternalUseWasteController`
- `OutletFoodInventoryAdjustmentController`
- `FoodFloorOrderController`
- `PurchaseRequisitionController`
- `OutletRejectionController`
- `WarehouseStockOpnameController`
- `FoodInventoryAdjustmentController`
- `ContraBonController`
- `PurchaseOrderOpsController`
- `ScheduleAttendanceCorrectionController`
- `AttendanceController`
- `CctvAccessRequestController`
- `PurchaseOrderFoodsController`
- `PrFoodController`
- `EmployeeResignationController`
- `EmployeeMovementController`
- `ApprovalController`
- `MaintenanceOrderController`, `MaintenanceTaskController`, `MaintenancePurchaseRequisitionController`, dll.
- `ActionPlanController`, `CalendarController`, `DailyReportController`, `TrainingScheduleController`, `TicketController`, `CoachingController`, `AnnouncementController`, `EnrollTestController`, `RetailController`, `LiveSupportController`, dll.

---

## 5. Route lain (approval-app & mobile)

- **Approval App** (`api.php` ~738–741): prefix approval-app, controller yang sama `NotificationController` (ERP), route name `api.approval-app.notifications.*`.
- **Mobile Member** (`api.php` ~1042–1045): controller `App\Http\Controllers\Mobile\Member\NotificationController`, model **`MemberAppsNotification`** (bukan `Notification`), route name `api.mobile.member.notifications.*`.

Jadi ada dua sistem notifikasi:
- **ERP (web):** tabel `notifications`, model `Notification`, `NotificationController`.
- **Mobile member:** tabel untuk member apps, model `MemberAppsNotification`, `Mobile\Member\NotificationController`.

---

## 6. Frontend (Vue) – yang pakai API notifikasi ERP

**File:** `resources/js/Layouts/AppLayout.vue`

| Aksi | API yang dipanggil |
|------|---------------------|
| Load list + badge | `GET /api/notifications` dan `GET /api/notifications/unread-count` |
| Polling | `setInterval` 60 detik: `fetchNotifications()` + `fetchUnreadCount()` |
| Mark satu read | `POST /api/notifications/{id}/read` |
| Mark all read | `POST /api/notifications/read-all` |

- State: `notifications`, `unreadCount`, `showNotifDropdown`, `loading`, `lastNotificationIds`.
- Toast + suara untuk notifikasi baru (unread yang belum ada di `lastNotificationIds`).

**File lain:** `resources/js/Pages/Home.vue` memakai `POST /api/approval/notifications/{id}/mark-read` (route approval-app, beda dari read-all ERP di atas).

---

## 7. Ringkasan alur

```
[User buka ERP]
  → AppLayout.vue onMounted
  → GET /api/notifications + GET /api/notifications/unread-count
  → setiap 60s: GET /api/notifications + GET /api/notifications/unread-count

[Backend]
  → routes/api.php (auth) → NotificationController
  → index: Cache 30s "notifications_user_{id}" → Notification::where('user_id')->limit(50)
  → unreadCount: Cache 60s "notifications_unread_count_{id}" → Notification::where('user_id')->where('is_read', false)->count()
  → markAsRead / markAllAsRead: update DB + Cache::forget(...)

[Pembuatan notifikasi]
  → Berbagai controller (approval, maintenance, dll.)
  → NotificationService::insert() / create() atau Notification::create()
  → NotificationService → Notification::create() → tabel notifications
```

---

## 8. File penting (quick reference)

| Peran | File |
|-------|------|
| Route API (ERP) | `routes/api.php` (~140–145) |
| Controller baca/update | `app/Http/Controllers/NotificationController.php` |
| Service buat notifikasi | `app/Services/NotificationService.php` |
| Model (ERP) | `app/Models/Notification.php` |
| Frontend polling & UI | `resources/js/Layouts/AppLayout.vue` |
| Mobile member (API + model terpisah) | `app/Http/Controllers/Mobile/Member/NotificationController.php`, model `MemberAppsNotification` |
