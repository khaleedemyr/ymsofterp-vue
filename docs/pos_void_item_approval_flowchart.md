# Flowchart — Approval Void Item (POS → ymsofterp)

Dokumen ini menjelaskan alur **void item** di Order Screen ketika baris sudah tersimpan di database: multi-approver (OR), polling, dan opsi ganti approver.

## Gambar ringkas (PNG)

Banyak preview Markdown **tidak merender** diagram Mermaid di bawah; kalau yang tampil hanya blok kode, buka file PNG ini (bisa disisipkan di presentasi / WA / Word):

![Alur approval void item](pos_void_item_approval_flowchart.png)

*File: `docs/pos_void_item_approval_flowchart.png` (satu folder dengan file ini).*

---

## 1. Alur utama (kasir — Order Screen)

```mermaid
flowchart TD
    A[Kasir klik hapus item] --> B{Order aktif dan item<br/>sudah tersimpan DB?<br/>existingOrder + bukan isNew}
    B -->|Tidak| C[Pilih alasan saja]
    C --> D[Hapus dari layar + DB lokal<br/>tanpa approval pusat]
    B -->|Ya| E[Pilih alasan]
    E --> F[Pilih 1 atau lebih<br/>Approver HO]
    F --> G{Minimal satu<br/>approver terisi?}
    G -->|Tidak| H[Tampilkan peringatan]
    H --> F
    G -->|Ya| I[POST /api/pos/void/item-request<br/>approver_user_ids + snapshot]
    I --> J{Server OK?}
    J -->|Error / belum sync| K[Tampilkan error]
    J -->|OK / duplicate pending| L[Dapat public_token]
    L --> M[Polling GET .../item-request/status]
    M --> N{Status?}
    N -->|pending| M
    N -->|rejected| O[Tampilkan ditolak + catatan]
    N -->|approved| P[Hapus baris di POS:<br/>DELETE order_items + log void]
    N -->|timeout| Q[Beritahu kasir: belum ada keputusan]
```

---

## 2. Siapa yang boleh approve / tolak (ERP — Home / session)

```mermaid
flowchart LR
    subgraph Pusat["Database ymsofterp"]
        R[pos_void_item_requests]
        J[pos_void_item_request_approvers]
        R --- J
    end

    subgraph HO["User HO id_outlet = 1, status = A"]
        U1[Approver A]
        U2[Approver B]
        U3[Approver C]
    end

    J --> U1
    J --> U2
    J --> U3

    U1 --> X["Salah satu boleh<br/>Approve ATAU Tolak<br/>bukan urutan level"]
    U2 --> X
    U3 --> X
```

---

## 3. Alur approver di Home (setelah login)

```mermaid
flowchart TD
    A[User login ymsofterp] --> B[GET /api/pending-approvals/all<br/>atau inbox POS Void Item]
    B --> C{User.id ada di<br/>junction approver<br/>untuk request pending?}
    C -->|Tidak| D[Tidak tampil di kartu ini]
    C -->|Ya| E[Tampil kartu permintaan]
    E --> F{Aksi}
    F -->|Setujui| G[POST /pos-void-item-requests/id/approve]
    F -->|Tolak| H[POST /pos-void-item-requests/id/reject<br/>+ alasan opsional]
    G --> I[status = approved<br/>approved_by_user_id = user login]
    H --> J[status = rejected]
    I --> K[Kasir polling di POS mendeteksi approved]
    J --> L[Kasir polling mendeteksi rejected]
```

---

## 4. Ganti daftar approver (masih pending)

Hanya untuk permintaan **pending**. Kasir yang sama (`requester_user_id` cocok dengan yang tercatat, jika ada).

```mermaid
flowchart TD
    A[Permintaan masih pending] --> B[Kasir perlu ganti approver<br/>salah pilih / tidak ada yang bisa approve]
    B --> C[POST /api/pos/void/item-request/reassign-approvers<br/>public_token, kode_outlet,<br/>approver_user_ids baru,<br/>requester_user_id]
    C --> D{Valid?}
    D -->|Bukan pemohon yang sama| E[403 ditolak]
    D -->|OK| F[Hapus baris junction lama,<br/>isi approver baru]
    F --> G[Cache inbox di-refresh untuk user lama dan user baru]
```

---

## 5. Ringkasan aktor

| Aktor | Peran |
|--------|--------|
| Kasir POS | Hapus item, kirim request, polling, eksekusi hapus DB lokal setelah **approved** |
| Approver HO | Satu dari banyak yang dipilih kasir; siapa saja di daftar boleh approve/tolak |
| Database pusat | Menyimpan request + junction approver; sumber kebenaran status |

---

*File terkait SQL: `database/sql/pos_void_item_requests.sql`*  
*Controller: `App\Http\Controllers\PosVoidItemRequestController`*
