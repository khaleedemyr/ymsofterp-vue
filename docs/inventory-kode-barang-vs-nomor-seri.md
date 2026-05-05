# Perbandingan: Kode Barang (Barcode) vs Nomor Seri (1 Qty = 1 Seri)

Dokumen ini merangkum **kekuatan (strengths)** dan **kelemahan (weaknesses)** dibandingkan dengan pendekatan inventory yang memakai **kode barang satu untuk banyak unit** versus **satu nomor seri unik per satu fisik barang** (track per piece).

---

## Konteks singkat

| Aspek | Kode barang / SKU per produk | Nomor seri per unit |
|--------|------------------------------|---------------------|
| Identitas | Satu kode = banyak qty dalam stok | Satu nomor = satu fisik barang |
| Scan saat transaksi | Menambah/mengurangi qty agregat | Identifikasi unit spesifik |
| Umum dipakai untuk | Retail cepat, konsinyasi sederhana | Elektronik, garansi, recall, asset |

---

## Kode barang (barcoding per SKU) — Strengths

1. **Operasional cepat** — Scan sekali menambah/mengurangi qty; cocok untuk POS dan gudang throughput tinggi.
2. **Biaya & kompleksitas lebih rendah** — Tidak perlu generate, cetak, atau simpan jutaan nomor unik per unit.
3. **Label lebih sederhana** — Satu desain barcode untuk seluruh batch/varian yang sama.
4. **Integrasi mudah** — Hampir semua sistem ERP/POS mendukung SKU + qty tanpa layer serial.
5. **Pelatihan staf lebih ringan** — Alur “scan = tambah/kurang qty” mudah dipahami.

## Kode barang — Weaknesses

1. **Tidak tahu *unit* mana** — Jika ada retur, garansi, atau kasus rusak, sulit membuktikan barang A vs B sejenis.
2. **Celah keamanan & kecurangan** — Barcode sama bisa dipindai berkali-kali; risiko salin label, double entry, atau substitusi barang.
3. **Recall & kualitas lemah** — Hanya bisa “recall by batch/tanggal”, bukan by unit.
4. **Serial/garansi resmi** — Banyak brand elektronik mensyaratkan nomor seri per device; kode barang saja sering tidak cukup.
5. **Audit trail per fisik** — Setelah keluar gudang, jejak “unit persis” hilang jika hanya di level SKU.

---

## Nomor seri (1 qty = 1 nomor seri) — Strengths

1. **Jejak unik per fisik** — Setiap barang punya identitas; cocok garansi, klaim, dan sengketa.
2. **Keamanan operasional lebih baik** — Kombinasi serial + status (sold/return) mengurangi duplikasi dan substitusi terencana.
3. **Recall tajam** — Bisa target unit level (misalnya hanya seri X–Y).
4. **Kepatuhan & aset** — IT asset, alat medis, alat berat: sering wajib serial.
5. **Retur & RMA jelas** — Sistem tahu barang retur apakah memang unit yang pernah dijual ke customer itu.

## Nomor seri — Weaknesses

1. **Beban proses** — Setiap barang masuk harus “diaktivasi” seri; receiving lebih lama, risiko typo/scan miss.
2. **Data & performa** — Banyak tabel + index; laporan agregat harus hati-hati (bukan sekadar `SUM(qty)` di satu baris).
3. **Label & logistik** — Cetak unik per unit, dudukan label, damage barcode = butuh alur ganti/override.
4. **Salah scan / human error** — Tukar seri saat picking bisa menyulitkan rekonsiliasi.
5. **Biaya development** — Modul penerimaan, transfer, sales return, stock opname perlu desain khusus serial.
6. **Produk non-serial** — Makanan, material curah, fast-moving consumer: seri per unit sering *overkill*.

---

## Kapan memakai apa (ringkas)

- **Tetap kode barang (SKU) saja** jika: barang homogen, kecepatan kasir utama, margin rendah, tidak perlu bukti per unit.
- **Tambah nomor seri** jika: garansi, elektronik, harga tinggi, regulasi, anti-fraud, atau butuh bukti *unit* spesifik.
- **Hibrida** (sering praktis): **SKU + batch/lot** untuk makanan/obat; **SKU + serial** hanya untuk kategori tertentu di master item.

---

## Catatan implementasi (jika nanti dibangun)

- Definisikan: seri **wajib** vs **opsional** per jenis item.
- Status seri: available, reserved, sold, in repair, scrapped.
- Alur ganti seri (label rusak) harus ter-audit.
- Stock opname: match daftar seri di sistem vs fisik, bukan hanya hitung qty.

---

*Dokumen referensi keputusan desain; bukan spesifikasi teknis database.*
