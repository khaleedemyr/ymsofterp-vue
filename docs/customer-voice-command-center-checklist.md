# Customer Voice Command Center - Checklist Implementasi

Dokumen ini jadi referensi utama untuk memantau progress fitur **Customer Voice Command Center** (gabungan Google Review, Instagram Comment, dan Guest Comment OCR).

## Cara Pakai

- Ubah status item dengan format: `[ ]` belum, `[-]` sedang dikerjakan, `[x]` selesai.
- Isi kolom `Tanggal Update` setiap kali ada perubahan status.
- Tambahkan catatan singkat kalau ada blocker atau keputusan teknis baru.
- Jangan hapus item lama; pakai bagian riwayat di bawah untuk audit progres.

## Legend Status

- `[ ]` Belum dikerjakan
- `[-]` Sedang dikerjakan
- `[x]` Selesai

## Baseline Audit (2026-04-22)

- Modul `google-review` sudah punya dashboard insight, klasifikasi AI, dan penyimpanan item hasil AI.
- Modul `guest_comment_forms` sudah punya OCR + klasifikasi `issue_severity`, `issue_topics`, `issue_summary_id`.
- Belum ada data model khusus command center (`feedback_cases` dkk), belum ada action board berbasis case, dan belum ada SLA/eskalasi formal.

---

## A. Fondasi Data

- [x] Buat tabel `feedback_cases` (source_type, source_id, outlet, severity, topic, status, sla, assigned_to, dll)
- [x] Buat tabel `feedback_case_activities` (timeline aksi per case)
- [x] Buat tabel `feedback_alert_rules`
- [x] Buat tabel `feedback_alert_logs`
- [x] Tambah index penting (outlet, status, severity, due_at, event_at)
- [ ] Buat script backfill data awal dari sumber existing

## B. Ingestion & Normalisasi

- [x] Buat proses ingest dari `google_review_ai_items`
- [x] Buat proses ingest dari `instagram_comments_db` (via report AI)
- [x] Buat proses ingest dari `guest_comment_forms` (`issue_*`)
- [x] Buat normalisasi field ke skema unified
- [x] Buat dedupe untuk mencegah duplikasi case
- [-] Buat risk scoring awal berbasis severity + topic + recency

## C. Rule Engine & SLA

- [-] Implement rule: `severe` auto-case + alert manager
- [-] Implement rule: `negative` auto-case + assign supervisor
- [-] Implement rule: `mild_negative` masuk queue normal
- [x] Set SLA default per severity
- [-] Buat escalation otomatis saat SLA terlewat
- [-] Buat anti-spam rule untuk alert berulang

## D. UI Operasional

- [x] Buat menu `Customer Voice Command Center`
- [-] Buat panel `Overview` (volume, negative rate, top issue, top outlet risk)
- [-] Buat panel `Action Board` (queue case operasional)
- [x] Buat panel `Case Detail` (timeline, assign, resolve, escalate)
- [-] Tambah filter cepat (severe, overdue, outlet, channel, unassigned)
- [-] Tambah pencarian teks komentar/ringkasan

## E. Workflow Follow-Up

- [x] Tombol assign PIC
- [x] Tombol ubah status (`new`, `in_progress`, `resolved`, `ignored`)
- [x] Catatan internal per case
- [ ] Template response draft (human approval)
- [ ] Tugas follow-up customer (khusus data dengan kontak)
- [x] Audit log perubahan status dan assignment

## F. Notifikasi

- [-] Notifikasi in-app untuk case severe
- [ ] Integrasi kanal eksternal (WA/Telegram/Slack/Email)
- [ ] Ringkasan harian otomatis (daily digest)
- [ ] Ringkasan mingguan otomatis (weekly digest)
- [ ] Retry mekanisme jika pengiriman gagal

## G. KPI & Monitoring

- [-] First response time (median)
- [-] SLA compliance rate
- [-] Resolution time
- [-] Negative rate trend per outlet
- [-] Repeat issue rate
- [-] Dashboard performa PIC/outlet

## H. Quality, Security, dan Testing

- [ ] Unit test untuk normalisasi dan rule engine
- [ ] Integration test untuk ingestion pipeline
- [ ] Uji performa query Action Board
- [ ] Validasi akses berbasis outlet/role
- [ ] Validasi data sensitif dan masking bila perlu
- [ ] SOP rollback jika ada issue produksi

---

## Milestone Implementasi

### M1 - MVP Operasional
- [ ] Data unified + Action Board dasar + alert severe

### M2 - SLA & Eskalasi
- [ ] SLA timer + escalation + activity timeline lengkap

### M3 - Smart Follow-Up
- [ ] Draft response AI + approval + integrasi notifikasi eksternal

---

## Catatan Keputusan Teknis

- Tanggal: 2026-04-22
  - Keputusan: Mulai dari baseline existing (Google Review AI + Guest Comment OCR), lalu bangun layer baru `feedback_cases` untuk operasional.
  - Alasan: Data sumber sudah tersedia, tapi belum ada alur case management dan SLA follow-up.
  - Dampak: Implementasi difokuskan ke ingestion unified + action board + alert severe sebagai prioritas pertama.
- Tanggal: 2026-04-22
  - Keputusan: Pembuatan tabel dilakukan via SQL manual (tanpa migration Laravel), dan menu command center dibuat terpisah dari menu lain.
  - Alasan: Mengikuti arahan implementasi saat ini dan menjaga boundary fitur tetap jelas.
  - Dampak: Disiapkan file SQL khusus tabel command center + file SQL insert menu/permission tersendiri.

---

## Riwayat Progress

- 2026-04-22 - Inisialisasi checklist dokumen.
- 2026-04-22 - Baseline audit diisi; status parsial (`[-]`) ditandai pada area yang sudah punya fondasi di sistem existing.
- 2026-04-22 - Ditambahkan query SQL tabel command center (`database/sql/customer_voice_command_center_tables.sql`).
- 2026-04-22 - Ditambahkan query SQL menu+permission terpisah (`database/sql/insert_customer_voice_command_center_erp_menu.sql`).
- 2026-04-22 - Ditambahkan route + halaman terpisah `customer-voice-command-center` (tidak menyatu ke modul lain).
- 2026-04-22 - Ditambahkan ingestion service + tombol sinkronisasi untuk menarik data Google/Instagram AI dan Guest Comment ke `feedback_cases`.
- 2026-04-22 - Ditambahkan aksi operasional case: assign PIC, update status, tambah catatan, dan timeline aktivitas.
- 2026-04-22 - Ditambahkan indikator SLA/overdue di Action Board + command scheduler `feedback:escalate-overdue` untuk membuat escalation alert log internal.
- 2026-04-22 - UI dipoles lebih modern/professional + ditambahkan Case Detail drawer dan modal catatan aktivitas.
- 2026-04-22 - Ditambahkan KPI section (avg first response, avg resolution time, SLA compliance, repeat issue rate, top negative outlet 30 hari).
- 2026-04-22 - KPI first response di-upgrade ke median + ditambahkan tabel trend harian 14 hari (total vs negatif).
- 2026-04-22 - Ditambahkan leaderboard performa PIC dan performa outlet (window 30 hari) untuk monitoring eksekusi tim.

