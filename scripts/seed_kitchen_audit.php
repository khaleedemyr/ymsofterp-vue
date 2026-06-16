<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$args = $_SERVER['argv'] ?? [];
$isApply = in_array('--apply', $args, true);
$assumeYes = in_array('--yes', $args, true);

if (!$isApply) {
    echo "[SAFE MODE] Dry-run aktif. Semua perubahan akan di-ROLLBACK." . PHP_EOL;
    echo "Untuk commit ke database, jalankan: php scripts/seed_kitchen_audit.php --apply --yes" . PHP_EOL;
}

if ($isApply && !$assumeYes) {
    echo "Anda akan menulis data ke database production. Lanjut? ketik YES: ";
    $confirm = trim((string) fgets(STDIN));
    if ($confirm !== 'YES') {
        echo "Dibatalkan oleh user." . PHP_EOL;
        exit(1);
    }
}

$now = now();

$template = [
    'code' => 'KTA',
    'name' => 'Kitchen Audit',
    'audit_type' => 'Kitchen Evaluation',
    'department' => 'Kitchen',
    'version' => 1,
    'status' => 'A',
    'notes' => 'Seed from Kitchen Audit screenshots',
];

$categories = [
    ['code' => 'KTA-C1', 'name' => 'RECEIVING, STORAGE, PREPARATION, PROCESSING, SERVING', 'sort_order' => 10],
    ['code' => 'KTA-C2', 'name' => 'CLEANING AND SANITATION', 'sort_order' => 20],
    ['code' => 'KTA-C3', 'name' => 'CHEMICAL CONTROL', 'sort_order' => 30],
    ['code' => 'KTA-C4', 'name' => 'PEST CONTROL', 'sort_order' => 40],
    ['code' => 'KTA-C5', 'name' => 'PERSONAL HYGIENE', 'sort_order' => 50],
    ['code' => 'KTA-C6', 'name' => 'EMERGENCY & SAFETY FACILITIES', 'sort_order' => 60],
    ['code' => 'KTA-C7', 'name' => 'FOOD AND BEVERAGE QUALITY CHECK UNTUK INTERNAL QA', 'sort_order' => 70],
];

$subcategories = [
    ['cat' => 'KTA-C1', 'code' => 'KTA-S1-1', 'name' => 'RECEIVING', 'sort_order' => 10],
    ['cat' => 'KTA-C1', 'code' => 'KTA-S1-2', 'name' => 'STORAGE', 'sort_order' => 20],
    ['cat' => 'KTA-C1', 'code' => 'KTA-S1-3', 'name' => 'PREPARATION', 'sort_order' => 30],
    ['cat' => 'KTA-C1', 'code' => 'KTA-S1-4', 'name' => 'PROCESSING', 'sort_order' => 40],
    ['cat' => 'KTA-C1', 'code' => 'KTA-S1-5', 'name' => 'SERVING', 'sort_order' => 50],

    ['cat' => 'KTA-C2', 'code' => 'KTA-S2-1', 'name' => 'PENGELOLAAN SAMPAH DI PREPARATION AREA', 'sort_order' => 10],
    ['cat' => 'KTA-C2', 'code' => 'KTA-S2-2', 'name' => 'TEMPAT PEMBUANGAN SAMPAH UTAMA (GARBAGE ROOM)', 'sort_order' => 20],
    ['cat' => 'KTA-C2', 'code' => 'KTA-S2-3', 'name' => 'GREASE TRAP', 'sort_order' => 30],
    ['cat' => 'KTA-C2', 'code' => 'KTA-S2-4', 'name' => 'IPAL', 'sort_order' => 40],
    ['cat' => 'KTA-C2', 'code' => 'KTA-S2-5', 'name' => 'KITCHEN EQUIPMENT', 'sort_order' => 50],
    ['cat' => 'KTA-C2', 'code' => 'KTA-S2-6', 'name' => 'UTENSIL', 'sort_order' => 60],
    ['cat' => 'KTA-C2', 'code' => 'KTA-S2-7', 'name' => 'KITCHEN AREA CLEANLINESS', 'sort_order' => 70],
    ['cat' => 'KTA-C2', 'code' => 'KTA-S2-8', 'name' => 'PROSEDUR CLEANING & SANITASI', 'sort_order' => 80],
    ['cat' => 'KTA-C2', 'code' => 'KTA-S2-9', 'name' => 'CUCI TANGAN', 'sort_order' => 90],

    ['cat' => 'KTA-C3', 'code' => 'KTA-S3-1', 'name' => 'CHEMICAL CONTROL', 'sort_order' => 10],
    ['cat' => 'KTA-C4', 'code' => 'KTA-S4-1', 'name' => 'PEST CONTROL', 'sort_order' => 10],
    ['cat' => 'KTA-C5', 'code' => 'KTA-S5-1', 'name' => 'PERSONAL HYGIENE', 'sort_order' => 10],
    ['cat' => 'KTA-C6', 'code' => 'KTA-S6-1', 'name' => 'EMERGENCY & SAFETY FACILITIES', 'sort_order' => 10],
    ['cat' => 'KTA-C7', 'code' => 'KTA-S7-1', 'name' => 'FOOD AND BEVERAGE QUALITY CHECK UNTUK INTERNAL QA', 'sort_order' => 10],
];

$parameters = [
    // 1.1 RECEIVING
    ['sc' => 'KTA-S1-1', 'code' => 'KTA-1.1.1', 'text' => 'Seluruh proses penerimaan dan penyimpanan barang harus menggunakan alas atau pallet yang sesuai. Produk tidak diperbolehkan diletakan atau disimpan langsung dilantai. Seluruh produk food disimpan pada area terpisah dari bahan kimia (chemical) untuk mencegah resiko kontaminasi silang. (SOP-QA-019)', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'KTA-S1-1', 'code' => 'KTA-1.1.2', 'text' => 'Proses penerimaan produk dilakukan diarea penerimaan yang bersih, tertata dan berada pada lokasi yang telah ditentukan. (SOP-QA-019)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'KTA-S1-1', 'code' => 'KTA-1.1.3', 'text' => 'Produk yang diterima atau dibeli secara ritel sesuai dengan SPS produk yang telah ditetapkan oleh perusahaan. (SOP-OPS-005)', 'weight' => 10, 'sort_order' => 30],
    ['sc' => 'KTA-S1-1', 'code' => 'KTA-1.1.4', 'text' => 'Seluruh produk yang diterima harus melalui proses pemeriksaan terhadap kualitas, kuantitas, identitas/label, kesesuaian SPS, dan kondisi kemasan. Produk dengan kemasan rusak, penyok, kadaluarsa, atau tidak sesuai SPS wajib dipisahkan, didokumentasikan, dan ditolak atau ditindaklanjuti sesuai prosedur yang berlaku. (SOP-QA-017, SOP-QA-019, SOP-QA-020, SOP-OPS-005)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'KTA-S1-1', 'code' => 'KTA-1.1.5', 'text' => 'Seluruh produk harus diterima dan digunakan sesuai dengan ketentuan FIFO dan FEFO. (SOP-QA-001)', 'weight' => 30, 'sort_order' => 50],
    ['sc' => 'KTA-S1-1', 'code' => 'KTA-1.1.6', 'text' => 'Frozen dan chilled produk harus didistribusikan dengan menjaga integritas rantai dingin (cold chain) agar suhu produk tetap sesuai standar, termasuk memastikan produk frozen tetap dalam kondisi beku selama proses distribusi. (SOP-QA-004, SOP-QA-019, FRM-QA-001). Pada saat proses receiving wajib dilakukan pengecekan dan dokumentasi meliputi: 1. Suhu produk pada saat diterima. 2. Waktu produk dimasukan kedalam freezer/chiller. Guide Line: 1. Suhu produk pada saat diterima harus dicek sesuai dengan standar masing-masing kategori (freezer suhu <-12 C, chiller 1-8 C). 2. Seluruh hasil pemeriksaan dan pencatatan harus didokumentasikan dengan menggunakan form receiving.', 'weight' => 10, 'sort_order' => 60],
    ['sc' => 'KTA-S1-1', 'code' => 'KTA-1.1.7', 'text' => 'Seluruh pencatatan administrasi harus terdokumentasi dengan lengkap, benar, dan diperbaharui setiap hari, meliputi proses Purchase Request (PR) (erp), Dokumen surat jalan, PO dan atau invoice dari Supplier guna memastikan ketertelusuran, keakuratan stock, dan pengendalian operasional. (SOP-OPS-001, SOP-OPS-002, SOP-OPS-003)', 'weight' => 10, 'sort_order' => 70],

    // 1.2 STORAGE
    ['sc' => 'KTA-S1-2', 'code' => 'KTA-1.2.1', 'text' => 'Produk disimpan sesuai standar suhu berdasarkan jenis produknya. Sebelum disimpan, seluruh produk harus dipastikan memiliki kondisi kemasan yang bersih, utuh, tidak rusak, tidak bocor, dan dalam keadaan baik. (SOP-QA-002, SOP-QA-004)', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'KTA-S1-2', 'code' => 'KTA-1.2.2', 'text' => 'Penyimpanan produk harus dipisahkan berdasarkan kategori jenis produk, seperti seafood, beef, poultry, ikan, sauce, dairy product, roti, bahan setengah jadi, buah dan sayuran untuk mencegah terjadinya kontaminasi silang. Apabila terdapat keterbatasan ruang/tempat penyimpanan, produk dapat disimpan dalam area yang sama dengan menggunakan box container tertutup. (SOP-QA-014)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'KTA-S1-2', 'code' => 'KTA-1.2.3', 'text' => 'Seluruh produk yang disimpan tidak boleh bersentuhan langsung dengan lantai. Produk harus ditempatkan menggunakan alas (pallet) atau rak penyimpanan dengan ketinggian/jarak rak terbawah kurang minimal 15 cm dari lantai. (SOP-QA-002)', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'KTA-S1-2', 'code' => 'KTA-1.2.4', 'text' => 'Tidak terdapat potensi kontaminasi silang yang dapat mempengaruhi keamanan pangan, termasuk bahaya kimia, bahaya fisik, maupun kondisi kebersihan produk. Produk harus disimpan dan ditangani dalam kondisi bersih, bebas dari benda asing. (SOP-QA-015)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'KTA-S1-2', 'code' => 'KTA-1.2.5', 'text' => 'Produk yang disimpan didalam freezer dan chiller harus disusun secara rapi, teratur dan tidak melebihi kapasitas penyimpanan. Susunan produk tidak boleh menutupi blower, jalur sirkulasi udara maupun ventilasi pendingin agar distribusi suhu tetap optimal. Rak penyimpanan harus dalam kondisi baik, bersih, dan digunakan sesuai fungsinya untuk menjaga kestabilan produk serta efektivitas sistem pendingin. (SOP-QA-002)', 'weight' => 10, 'sort_order' => 50],
    ['sc' => 'KTA-S1-2', 'code' => 'KTA-1.2.6', 'text' => 'Seluruh produk harus disimpan sesuai dengan ketentuan FIFO dan FEFO. (SOP-QA-001)', 'weight' => 30, 'sort_order' => 60],
    ['sc' => 'KTA-S1-2', 'code' => 'KTA-1.2.7', 'text' => 'Setiap bahan (raw material) seperti sayuran, buah-buahan, telur, maupun bahan baku/produk lain tanpa label identifikasi dari HO atau suplier wajib diberikan label identifikasi pada saat penerimaan. Label mencantumkan nama produk dan tanggal kedatangan/penerimaan untuk memastikan ketertelusuran serta mendukung sistem penerapan FIFO/FEFO. (SOP-QA-017). Guide Line: 1. Sayuran: Onion, Garlic', 'weight' => 30, 'sort_order' => 70],
    ['sc' => 'KTA-S1-2', 'code' => 'KTA-1.2.8', 'text' => 'Tidak ditemukan product yang telah melewati tanggal kadaluwarsa (expired). Bahan baku, bahan proses, atau produk yang telah expired, rusak, mengalami penurunan mutu atau tidak sesuai dengan SPS harus segera dipisahkan dari produk yang masih layak digunakan serta diberi label identifikasi yang jelas. (SOP-QA-017, SOP-QA-019, SOP-QA-020, SOP-OPS-005)', 'weight' => 50, 'sort_order' => 80],

    // 1.3 PREPARATION
    ['sc' => 'KTA-S1-3', 'code' => 'KTA-1.3.1', 'text' => 'Tidak terdapat penyimpangan terhadap standar kualitas produk yang telah ditetapkan. Produk harus berada dalam kondisi layak produksi, tidak mengalami kerusakan, pembusukan (spoiled), perubahan warna, perubahan aroma, perubahan tekstur atau indikasi penurunan mutu lainnya. (SOP-QA-008, SOP-QA-010, SOP-QA-020)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'KTA-S1-3', 'code' => 'KTA-1.3.2', 'text' => 'Tidak ditemukan product yang telah melewati tanggal kadaluwarsa (expired). Bahan baku, bahan proses, atau produk yang telah expired, rusak, mengalami penurunan mutu atau tidak sesuai dengan SPS harus segera dipisahkan dari produk yang masih layak digunakan serta diberi label identifikasi yang jelas. (SOP-QA-017, SOP-QA-020, SOP-OPS-005)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'KTA-S1-3', 'code' => 'KTA-1.3.3', 'text' => 'Seluruh produk yang digunakan dalam proses preparation harus mengikuti ketentuan FIFO dan FEFO. (SOP-QA-001)', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'KTA-S1-3', 'code' => 'KTA-1.3.4', 'text' => 'Tidak terdapat potensi kontaminasi silang yang dapat mempengaruhi keamanan pangan, termasuk bahaya kimia, bahaya fisik, maupun kondisi kebersihan produk. Produk harus disimpan dan ditangani dalam kondisi bersih, bebas dari benda asing. (SOP-QA-015)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'KTA-S1-3', 'code' => 'KTA-1.3.5', 'text' => 'Penyimpanan produk harus dipisahkan berdasarkan kategori jenis produk, seperti beef, poultry, sauce, dairy product, bahan setengah jadi, buah dan sayuran untuk mencegah terjadinya kontaminasi silang. Apabila terdapat keterbatasan ruang/tempat penyimpanan, produk dapat disimpan dalam area yang sama dengan menggunakan box container tertutup. (SOP-QA-014)', 'weight' => 30, 'sort_order' => 50],
    ['sc' => 'KTA-S1-3', 'code' => 'KTA-1.3.6', 'text' => 'Seluruh produk yang telah disiapkan (prepared produk) serta item dalam kemasan yang telah dibuka wajib memiliki label identifikasi yang lengkap dan jelas. Mencantumkan nama item/produk, tanggal preparation, jam preparation serta tanggal kadaluwarsa atau batas penggunaan (shelf life) sesuai ketentuan perusahaan. (SOP-QA-017, SL-01/(K/QA)/X/25)', 'weight' => 30, 'sort_order' => 60],
    ['sc' => 'KTA-S1-3', 'code' => 'KTA-1.3.7', 'text' => 'Produk di kemas per bungkus/pack sesuai dengan takaran yang telah di tentukan wajib memiliki label identifikasi yang lengkap dan jelas. Tercantum nama item/produk, production date, batch number, expired date, gramasi produk, nomor seri, barcode sesuai ketentuan perusahaan dan disimpan di dalam suhu sesuai standar penyimpanan produk (SOP-QA-017, SL-01/(K/QA)/X/25). Guide Line: Barcode yang tertera dikemasan: tidak boleh menutupi tanggal produksi atau tanggal kadaluarsa dari produsen, terdapat nama produk/tanggal produksi/tanggal kadaluarsa/nomor barcode dengan lengkap dan jelas, tidak sobek, dapat terbaca dengan jelas, kode produksi up date, warna tinta tidak luntur, satu barcode untuk setiap satu kemasan, nama item produk pada barcode sesuai dengan produk dalam kemasan.', 'weight' => 30, 'sort_order' => 70],
    ['sc' => 'KTA-S1-3', 'code' => 'KTA-1.3.8', 'text' => 'Seluruh produk di persiapkan sesuai dengan SOP, instruksi kerja (IK), ketentuan shelf life, dan spesifikasi produk yang telah ditetapkan, serta menerapkan prinsip keamanan pangan (food safety selama proses preparation). (SOP-OPS-004, SOP-QA-003, SOP-QA-016)', 'weight' => 50, 'sort_order' => 80],
    ['sc' => 'KTA-S1-3', 'code' => 'KTA-1.3.9', 'text' => 'Tidak menggunakan peralatan yang sama untuk menangani produk mentah dan produk matang/siap saji guna mencegah terjadinya kontaminasi silang. Peralatan seperti pisau, chopping board dan peralatan pendukung lainnya harus digunakan sesuai dengan peruntukannya serta dibedakan berdasarkan kode warna yang telah ditetapkan oleh perusahaan. (SOP-QA-015, STD-QA-008, STD-QA-010). Guide Line: Standar penggunaan cutting board dan pisau: 1. Merah: Daging merah (beef, lamb dan sejenisnya). 2. Kuning: Unggas (poultry). 3. Biru: Seafood dan ikan. 4. Hijau: Sayuran dan buah buahan. 5. Putih: Makanan siap saji. 6. Coklat: Makanan matang. Standar kode warna penggunaan lap: 1. Biru: Digunakan untuk mengelap dan mengeringkan peralatan alat saji. 2. Hijau: Digunakan untuk mengelap atau membersihkan peralatan makanan siap saji. 3. Merah: Digunakan untuk membersihkan peralatan/equipment (bagian luar). 4. Kuning: Digunakan untuk membersihkan peralatan/equipment (bagian dalam). 5. Coklat: Digunakan khusus untuk area makanan matang.', 'weight' => 30, 'sort_order' => 90],
    ['sc' => 'KTA-S1-3', 'code' => 'KTA-1.3.10', 'text' => 'Proses thawing produk beku harus dilakukan sesuai dengan metode yang telah ditetapkan untuk menjamin keamanan pangan dan untuk menjaga kualitas produk. (SOP-QA-021). Guide Line: Metode thawing: 1. Thawing chiller pada suhu 1-4 C. 2. Thawing defrost menggunakan microwave. 3. Menggunakan air bersih dan mengalir (ayam dan seafood).', 'weight' => 30, 'sort_order' => 100],
    ['sc' => 'KTA-S1-3', 'code' => 'KTA-1.3.11', 'text' => 'Utensil yang digunakan untuk proses persiapan produk (preparation) dalam kondisi bersih, terawat dan layak pakai, serta bebas dari kotoran, karat, retak, pecah, atau kerusakan lainnya yang dapat mencemari produk pangan. (SOP-QA-007)', 'weight' => 30, 'sort_order' => 110],

    // 1.4 PROCESSING
    ['sc' => 'KTA-S1-4', 'code' => 'KTA-1.4.1', 'text' => 'Tidak terdapat penyimpangan terhadap standar kualitas produk yang telah ditetapkan. Produk harus berada dalam kondisi layak produksi, tidak mengalami kerusakan, pembusukan (spoiled), perubahan warna, perubahan aroma, perubahan tekstur atau indikasi penurunan mutu lainnya. (SOP-QA-008, SOP-QA-010, SOP-QA-020)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'KTA-S1-4', 'code' => 'KTA-1.4.2', 'text' => 'Tidak ditemukan product yang telah melewati tanggal kadaluwarsa (expired). Bahan baku, bahan proses, atau produk yang telah expired, rusak, mengalami penurunan mutu atau tidak sesuai dengan SPS harus segera dipisahkan dari produk yang masih layak digunakan serta diberi label identifikasi yang jelas. (SOP-QA-017, SOP-QA-020, SOP-OPS-005)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'KTA-S1-4', 'code' => 'KTA-1.4.3', 'text' => 'Seluruh produk yang digunakan dalam proses processing harus mengikuti ketentuan FIFO dan FEFO. (SOP-QA-001)', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'KTA-S1-4', 'code' => 'KTA-1.4.4', 'text' => 'Tidak terdapat potensi kontaminasi silang yang dapat mempengaruhi keamanan pangan, termasuk bahaya kimia, bahaya fisik, maupun kondisi kebersihan produk. Produk harus disimpan dan ditangani dalam kondisi bersih, bebas dari benda asing. (SOP-QA-015)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'KTA-S1-4', 'code' => 'KTA-1.4.5', 'text' => 'Penyimpanan produk harus dipisahkan berdasarkan kategori jenis produk, seperti beef, poultry, sauce, dairy product, bahan setengah jadi, buah dan sayuran untuk mencegah terjadinya kontaminasi silang. Apabila terdapat keterbatasan ruang/tempat penyimpanan, produk dapat disimpan dalam area yang sama dengan menggunakan box container tertutup. (SOP-QA-014)', 'weight' => 30, 'sort_order' => 50],
    ['sc' => 'KTA-S1-4', 'code' => 'KTA-1.4.6', 'text' => 'Seluruh produk food di persiapkan dan di proses sesuai dengan SOP (standar recipe), instruksi kerja (IK), ketentuan shelf life, dan spesifikasi produk yang telah ditetapkan, serta menerapkan prinsip keamanan pangan (food safety selama proses preparation). (SOP-OPS-004, SOP-QA-003, SOP-QA-016)', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'KTA-S1-4', 'code' => 'KTA-1.4.7', 'text' => 'Tidak menggunakan peralatan yang sama untuk menangani produk mentah dan produk matang/siap saji guna mencegah terjadinya kontaminasi silang. Peralatan seperti pisau, chopping board dan peralatan pendukung lainnya harus digunakan sesuai dengan peruntukannya serta dibedakan berdasarkan kode warna yang telah ditetapkan oleh perusahaan. (SOP-QA-015, STD-QA-008, STD-QA-010). Guide Line: Standar penggunaan cutting board dan pisau: 1. Merah: Daging merah (beef, lamb dan sejenisnya). 2. Kuning: Unggas (poultry). 3. Biru: Seafood dan ikan. 4. Hijau: Sayuran dan buah buahan. 5. Putih: Makanan siap saji. 6. Coklat: Makanan matang. Standar kode warna penggunaan lap: 1. Biru: Digunakan untuk mengelap dan mengeringkan peralatan alat saji. 2. Hijau: Digunakan untuk mengelap atau membersihkan peralatan makanan siap saji. 3. Merah: Digunakan untuk membersihkan peralatan/equipment (bagian luar). 4. Kuning: Digunakan untuk membersihkan peralatan/equipment (bagian dalam). 5. Coklat: Digunakan khusus untuk area makanan matang.', 'weight' => 30, 'sort_order' => 70],
    ['sc' => 'KTA-S1-4', 'code' => 'KTA-1.4.8', 'text' => 'Proses thawing produk beku harus dilakukan sesuai dengan metode yang telah ditetapkan untuk menjamin keamanan pangan dan untuk menjaga kualitas produk. (SOP-QA-021). Guide Line: Metode thawing: 1. Thawing chiller pada suhu 1-4 C. 2. Thawing defrost menggunakan microwave. 3. Menggunakan air bersih dan mengalir (ayam dan seafood).', 'weight' => 30, 'sort_order' => 80],
    ['sc' => 'KTA-S1-4', 'code' => 'KTA-1.4.9', 'text' => 'Utensil yang digunakan untuk proses persiapan produk (processing) dalam kondisi bersih, terawat dan layak pakai, serta bebas dari kotoran, karat, retak, pecah, atau kerusakan lainnya yang dapat mencemari produk pangan. (SOP-QA-007)', 'weight' => 30, 'sort_order' => 90],

    // 1.5 SERVING
    ['sc' => 'KTA-S1-5', 'code' => 'KTA-1.5.1', 'text' => 'Tidak terdapat penyimpangan terhadap standar kualitas produk yang telah ditetapkan. Produk harus berada dalam kondisi layak konsumsi, tidak mengalami kerusakan, pembusukan (spoiled), perubahan warna, perubahan aroma, perubahan tekstur atau indikasi penurunan mutu lainnya. (SOP-QA-008, SOP-QA-010, SOP-QA-020)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'KTA-S1-5', 'code' => 'KTA-1.5.2', 'text' => 'Tidak ditemukan product yang telah melewati tanggal kadaluwarsa (expired). Bahan baku, bahan proses, atau produk yang telah expired, rusak, mengalami penurunan mutu atau tidak sesuai dengan SPS harus segera dipisahkan dari produk yang masih layak digunakan serta diberi label identifikasi yang jelas. (SOP-QA-017, SOP-QA-020, SOP-OPS-005)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'KTA-S1-5', 'code' => 'KTA-1.5.3', 'text' => 'Kesesuaian produk sesuai dengan standar recipe (tidak ada pengurangan atau penambahan bahan baku, ukuran porsi sesuai standar atau gramasi). (SOP-OPS-004)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'KTA-S1-5', 'code' => 'KTA-1.5.4', 'text' => 'Produk yang disajikan sesuai dengan standar temperatur penyajian. (SOP-QA-011). Guide Line: Produk panas disajikan >= 60 C. Produk dingin disajikan <= 5 C. Pengukuran suhu dilakukan menggunakan thermometer yang terkalibrasi.', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'KTA-S1-5', 'code' => 'KTA-1.5.5', 'text' => 'Produk yang disajikan sesuai dengan standar penampilan (appearance). Warna produk sesuai standar, garnish sesuai standar menu, bentuk dan tampilan sesuai standar, tidak gosong, pucat, pecah atau rusak, tidak terdapat benda asing. (SOP-OPS-004, SOP-QA-008)', 'weight' => 50, 'sort_order' => 50],
    ['sc' => 'KTA-S1-5', 'code' => 'KTA-1.5.6', 'text' => 'Produk terlindung dari potensi kontaminasi silang, tidak ada kontak langsung dengan tangan terhadap makanan siap saji, penggunaan handglove atau utensil sesuai ketentuan. (SOP-QA-003, SOP-QA-015)', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'KTA-S1-5', 'code' => 'KTA-1.5.7', 'text' => 'Penyajian produk sesuai dengan pesanan dan permintaan tamu. (SOP-OPS-004)', 'weight' => 50, 'sort_order' => 70],
    ['sc' => 'KTA-S1-5', 'code' => 'KTA-1.5.8', 'text' => 'Peralatan saji yang digunakan kondisi bersih dan layak pakai. (SOP-QA-005)', 'weight' => 50, 'sort_order' => 80],

    // 2 CLEANING AND SANITATION
    ['sc' => 'KTA-S2-1', 'code' => 'KTA-2.2.1', 'text' => 'Tempat sampah dilengkapi tutup dan pedal yang berfungsi dengan baik, menggunakan plastik sampah dalam kondisi bersih dan terawat untuk mencegah potensi kontaminasi. (SOP-QA-009)', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'KTA-S2-1', 'code' => 'KTA-2.2.2', 'text' => 'Sampah tidak melebihi kapasitas tempat sampah atau keluar dari atas penutup. Apabila tempat sampah telah mencapai batas yang ditentukan, sampah harus segera dibuang sesuai prosedur yang berlaku. Sampah dipilah dan dipisahkan antara sampah organik dan non organik. (SOP-QA-009)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'KTA-S2-1', 'code' => 'KTA-2.2.3', 'text' => 'Sampah organik dan non organik dipisahkan pada tempat sampah yang berbeda, diberi identifikasi yang jelas serta dikelola sesuai prosedur pengelolaan sampah yang berlaku. (SOP-QA-009)', 'weight' => 30, 'sort_order' => 30],

    ['sc' => 'KTA-S2-2', 'code' => 'KTA-2.2.1-GR', 'text' => 'Area garbage room dalam kondisi bersih dan terawat; lantai, dinding, pintu, plafon bebas dari penumpukan kotoran. Tidak terdapat genangan air, rembesan atau kebocoran. (SOP-QA-009)', 'weight' => 10, 'sort_order' => 10],
    ['sc' => 'KTA-S2-2', 'code' => 'KTA-2.2.2-GR', 'text' => 'Sampah dipisahkan sesuai kategorinya (organik, non organik dan B3 (Bahan Berbahaya dan Beracun) apabila ada). Tidak ditemukan pencampuran jenis sampah. (SOP-QA-009)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'KTA-S2-2', 'code' => 'KTA-2.2.3-GR', 'text' => 'Sampah tidak melebihi kapasitas tempat sampah atau kontainer. Pembuangan dilakukan secara rutin sesuai jadwal yang ditetapkan. (SOP-QA-009)', 'weight' => 10, 'sort_order' => 30],
    ['sc' => 'KTA-S2-2', 'code' => 'KTA-2.2.4-GR', 'text' => 'Garbage room terpasang insect killer dan adanya sistem pengelolaan pengendalian hama. (SOP-QA-009)', 'weight' => 10, 'sort_order' => 40],
    ['sc' => 'KTA-S2-2', 'code' => 'KTA-2.2.5-GR', 'text' => 'Garbage room dibersihkan dan disanitasi sesuai jadwal. Tersedia jadwal dan ceklis pembersihan garbage room. (SOP-QA-009)', 'weight' => 30, 'sort_order' => 50],

    ['sc' => 'KTA-S2-3', 'code' => 'KTA-2.3.1', 'text' => 'Main greasetrap dalam kondisi baik, berfungsi, bersih, terawat, tertutup, tidak bocor, saluran pembuangan lancar. Tersedia jadwal dan ceklis pembersihan main gease trap. (SOP-QA-006)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'KTA-S2-3', 'code' => 'KTA-2.3.2', 'text' => 'Greastap sink dalam kondisi baik, berfungsi, bersih, terawat, tertutup, tidak bocor, saluran pembuangan lancar. Tersedia jadwal dan ceklis pembersihan grease trap. (SOP-QA-006)', 'weight' => 50, 'sort_order' => 20],

    ['sc' => 'KTA-S2-4', 'code' => 'KTA-2.4.1', 'text' => 'IPAL dalam kondisi baik, berfungsi, bersih, terawat, tertutup, tidak bocor, saluran pembuangan lancar. Tersedia jadwal dan ceklis pembersihan dan perawatan IPAL. (SOP-QA-022, FRM-QA-004). Guide Line: Aplikasi penggunaan bakteri pengurai IPAL terjadwal dan sesuai dosis yang telah ditentukan. Air buangan IPAL sebelum dibuang ke lingkungan memenuhi baku mutu air. Pencatatan kebersihan dan penggunaan bakteri pengurai di area IPAL tersedia.', 'weight' => 30, 'sort_order' => 10],

    ['sc' => 'KTA-S2-5', 'code' => 'KTA-2.5.1', 'text' => 'Kitchen operating equipment berada dalam kondisi siap pakai, seluruh fungsi operasional seperti pengaturan suhu, timer, indikator, tombol control dan sistem pengaman bekerja dengan baik tanpa menghambat kelancaran proses produksi. (SOP-QA-004, SOP-QA-013)', 'weight' => 10, 'sort_order' => 10],
    ['sc' => 'KTA-S2-5', 'code' => 'KTA-2.5.2', 'text' => 'Kitchen operating equipment dalam kondisi bersih, terawat, bebas dari kerak, minyak, debu, sisa produk, dan kerusakan. Pembersihan equipment dilakukan secara rutin menggunakan metode cleaning dan sanitasi sesuai prosedur yang ditetapkan untuk memastikan kebersihan dan keamanan pangan. (SOP-QA-006, FRM-QA-002)', 'weight' => 30, 'sort_order' => 20],

    ['sc' => 'KTA-S2-6', 'code' => 'KTA-2.6.1', 'text' => 'Seluruh utensil tersedia sesuai kebutuhan operasional, dalam kondisi bersih, terawat, aman digunakan, serta tidak ditemukan kerusakan seperti retak, pecah, penyok, patah atau berkarat. (SOP-QA-005, SOP-QA-007)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'KTA-S2-6', 'code' => 'KTA-2.6.2', 'text' => 'Utensil dibersihkan dengan metode cleaning dan sanitasi sesuai dengan SOP yang berlaku, bebas dari sisa makanan, noda minyak, kerak, bau tidak sedap, serta dikeringkan sebelum digunakan atau disimpan. (SOP-QA-006)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'KTA-S2-6', 'code' => 'KTA-2.6.3', 'text' => 'Utensil disimpan dalam kondisi bersih, kering, tertata rapi pada area yang telah ditentukan, terlindung dari debu, percikan, hama dan potensi kontaminasi silang. (SOP-QA-006)', 'weight' => 10, 'sort_order' => 30],

    ['sc' => 'KTA-S2-7', 'code' => 'KTA-2.7.1', 'text' => 'Lantai area kerja dalam kondisi bersih, kering, tidak licin, bebas dari sisa makanan, tumpahan cairan, minyak, debu, genangan air, dan tidak terdapat kerusakan yang dapat mengganggu keselamatan kerja. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'KTA-S2-7', 'code' => 'KTA-2.7.2', 'text' => 'Dinding area kerja bersih, bebas dari noda, cipratan produk, minyak, debu, jamur, sarang hama, serta dalam kondisi terawat tanpa kerusakan yang berpotensi menjadi sumber kontaminasi. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'KTA-S2-7', 'code' => 'KTA-2.7.3', 'text' => 'Plafon dalam kondisi bersih bebas dari debu, sarang laba-laba, jamur, kebocoran, cat mengelupas atau kerusakan lainnya yang dapat mencemari area produksi. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'KTA-S2-7', 'code' => 'KTA-2.7.4', 'text' => 'Pintu area kerja bersih berfungsi dengan baik bebas dari kotoran dan kerusakan, serta dapat menutup dengan sempurna untuk mencegah masuknya hama. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 40],
    ['sc' => 'KTA-S2-7', 'code' => 'KTA-2.7.5', 'text' => 'Exhaust hood, filter, dan sistem fresh air dalam kondisi bersih, bebas dari penumpukan minyak, debu, dan kotoran. Berfungsi dengan baik untuk menjaga sirkulasi udara, serta mengurangi panas, asap, dan uap diarea kerja. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 50],
    ['sc' => 'KTA-S2-7', 'code' => 'KTA-2.7.6', 'text' => 'Saluran pembuangan (drainase) dalam kondisi bersih tidak tersumbat, tidak menimbulkan genangan, bebas dari sisa makanan dan bau tidak sedap serta dilengkapi penutup sesuai standar yang berlaku. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'KTA-S2-7', 'code' => 'KTA-2.7.7', 'text' => 'Lampu diarea produksi memiliki pelindung (cover lamp) dalam kondisi bersih berfungsi dengan baik, memberikan pencahayaan, serta tidak terdapat lampu mati atau pecah diarea kerja. (SOP-QA-006, FRM-QA-003). Guide Line: Standar lux di area produksi (kitchen, bar, steward, store).', 'weight' => 30, 'sort_order' => 70],
    ['sc' => 'KTA-S2-7', 'code' => 'KTA-2.7.8', 'text' => 'Ruangan gas dalam kondisi bersih, rapi, memiliki ventilasi yang memadai, bebas dari barang yang tidak berkaitan dengan operasional gas, tidak terdapat indikasi kebocoran, serta dilengkapi dengan rambu keselamatan dan APAR sesuai ketentuan yang berlaku. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 80],

    ['sc' => 'KTA-S2-8', 'code' => 'KTA-2.8.1', 'text' => 'Tersedia jadwal kegiatan cleaning dan sanitasi yang mencakup frekuensi, area/peralatan yang dibersihkan, PIC, serta dilengkapi dengan checklist atau laporan pelaksanaan yang terdokumentasi dan terverifikasi. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'KTA-S2-8', 'code' => 'KTA-2.8.2', 'text' => 'Tersedia prosedur sanitasi yang terdokumentasi dan mudah diakses oleh karyawan. Implementasi sanitasi dilakukan sesuai SOP, termasuk penggunaan bahan kimia dengan konsentrasi, metode, waktu kontak (contact time), dan frekuensi yang telah ditetapkan. (SOP-QA-006)', 'weight' => 10, 'sort_order' => 20],
    ['sc' => 'KTA-S2-8', 'code' => 'KTA-2.8.3', 'text' => 'Seluruh alat kebersihan dalam kondisi bersih, terawat, berfungsi dengan baik, diberi identifikasi sesuai peruntukannya, serta disimpan dengan rapi pada area yang telah ditentukan untuk mencegah kontaminasi silang. (STD-QA-001)', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'KTA-S2-8', 'code' => 'KTA-2.8.4', 'text' => 'Cleaning cloth dalam kondisi bersih dan dilakukan pembersihan atau penggantian secara berkala selama operasional. Lap dicuci menggunakan deterjen dan direndam dalam larutan sanitasi sesuai konsentrasi standar pada saat closing untuk mencegah pertumbuhan mikroorganisme serta kontaminasi silang. (SOP-QA-006)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'KTA-S2-8', 'code' => 'KTA-2.8.5', 'text' => 'Buah, sayuran, dan telur dicuci serta disanitasi sebelum digunakan sesuai SOP yang berlaku, menggunakan air mengalir dan/atau larutan sanitasi food grade dengan konsentrasi dan waktu kontak yang ditetapkan untuk mengurangi resiko kontaminasi mikrobiologis. (SOP-QA-025, SOP-QA-024). Guide Line: Chemical, Dilusi, Waktu.', 'weight' => 30, 'sort_order' => 50],

    ['sc' => 'KTA-S2-9', 'code' => 'KTA-2.9.1', 'text' => 'Fasilitas cuci tangan (handwash station) tersedia, mudah diakses, bersih, dan berfungsi dengan baik, dilengkapi dengan air mengalir, sabun cuci tangan, tissue sekali pakai, tempat sampah tertutup, serta poster tata cara cuci tangan. Karyawan wajib melakukan handwashing sesuai SOP, antara lain sebelum memulai pekerjaan, setelah menangani bahan mentah, setelah menggunakan toilet, setelah membuang sampah, setelah batuk/bersin, setelah menyentuh bagian tubuh, dan setiap kali berpotensi menyebabkan kontaminasi silang. (SOP-QA-026)', 'weight' => 30, 'sort_order' => 10],

    // 3 CHEMICAL CONTROL
    ['sc' => 'KTA-S3-1', 'code' => 'KTA-3.1', 'text' => 'Bahan kimia disimpan pada area khusus yang bersih, rapi, memiliki ventilasi yang memadai, terpisah dari bahan pangan, kemasan, dan alat makan. Chemical disusun sesuai kategori penggunaannya, dalam wadah yang sesuai, dengan kondisi tertutup, serta tidak disimpan langsung di lantai. (SOP-QA-028)', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'KTA-S3-1', 'code' => 'KTA-3.2', 'text' => 'Material Safety Data Sheet (MSDS) atau Safety Data Sheet (SDS) tersedia untuk seluruh bahan kimia yang digunakan, mudah diakses oleh karyawan, dalam kondisi terbaru, dan digunakan sebagai acuan dalam penanganan, penyimpanan, penggunaan, serta tindakan darurat apabila terjadi paparan atau tumpahan bahan kimia. (SOP-QA-028)', 'weight' => 10, 'sort_order' => 20],
    ['sc' => 'KTA-S3-1', 'code' => 'KTA-3.3', 'text' => 'Seluruh wadah bahan kimia, termasuk wadah transfer atau hasil pengenceran, memiliki identitas yang jelas dan mudah dibaca, minimal mencantumkan nama bahan kimia, konsentrasi (jika dilakukan pengenceran), tanggal pembuatan/pengenceran, masa berlaku (expired date), serta nama atau paraf petugas yang menyiapkan. (SOP-QA-029)', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'KTA-S3-1', 'code' => 'KTA-3.4', 'text' => 'Penggunaan dan pengenceran bahan kimia dilakukan sesuai instruksi produsen atau SOP yang berlaku, menggunakan alat ukur yang sesuai untuk memastikan konsentrasi yang tepat dan aman digunakan. (STD-QA-003)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'KTA-S3-1', 'code' => 'KTA-3.5', 'text' => 'Tersedia peralatan penanganan tumpahan (spill kit) atau prosedur penanganan tumpahan bahan kimia, dan karyawan memahami tindakan yang harus dilakukan apabila terjadi insiden paparan atau tumpahan chemical. (SOP-QA-030)', 'weight' => 10, 'sort_order' => 50],

    // 4 PEST CONTROL
    ['sc' => 'KTA-S4-1', 'code' => 'KTA-4.1', 'text' => 'Tersedia program pest control yang dilaksanakan secara terjadwal sesuai ketentuan perusahaan atau vendor yang ditunjuk, dilengkapi dengan jadwal kunjungan, laporan hasil inspeksi, denah titik monitoring, serta tindak lanjut atas setiap temuan. (SOP-QA-018)', 'weight' => 10, 'sort_order' => 10],
    ['sc' => 'KTA-S4-1', 'code' => 'KTA-4.2', 'text' => 'Tidak ditemukan aktivitas maupun tanda-tanda infestasi hama seperti tikus, kecoa, lalat, semut, burung, cicak, atau serangga lainnya, termasuk kotoran hama, bangkai, telur, sarang, bekas gigitan, maupun jejak lintasan pada area operasional dan penyimpanan. (SOP-QA-018)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'KTA-S4-1', 'code' => 'KTA-4.3', 'text' => 'Tidak terdapat celah, lubang, retakan, pintu yang tidak rapat, saluran terbuka, atau kondisi lain yang berpotensi menjadi jalur akses (entry point), tempat persembunyian (harborage), maupun area istirahat (resting area) bagi hama. (SOP-QA-018)', 'weight' => 10, 'sort_order' => 30],
    ['sc' => 'KTA-S4-1', 'code' => 'KTA-4.4', 'text' => 'Peralatan pengendalian hama seperti perangkap tikus, insect light trap/fly catcher, glue trap, bait station, dan perangkat monitoring lainnya tersedia sesuai kebutuhan, ditempatkan pada titik yang telah ditentukan, dalam kondisi bersih, berfungsi dengan baik, diberi identifikasi, serta dilakukan pemeriksaan secara berkala dan terdokumentasi. (SOP-QA-018)', 'weight' => 30, 'sort_order' => 40],
    ['sc' => 'KTA-S4-1', 'code' => 'KTA-4.5', 'text' => 'Area operasional dan area sekitar dijaga dalam kondisi bersih, rapi, bebas dari penumpukan barang, sampah, genangan air, sisa makanan, dan sumber daya tarik lainnya yang dapat memicu keberadaan hama. (SOP-QA-006)', 'weight' => 30, 'sort_order' => 50],

    // 5 PERSONAL HYGIENE
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.1', 'text' => 'Karyawan menggunakan perlengkapan kerja sesuai ketentuan, seperti hair net yang menutupi seluruh rambut, masker, serta hand glove sekali pakai apabila dipersyaratkan sesuai jenis pekerjaan. PPE (Personal Protective Equipment) diganti secara berkala atau apabila kotor, rusak, maupun terkontaminasi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.2', 'text' => 'Karyawan menjaga kebersihan tubuh, tidak berbau badan, penggunaan deodorant diperbolehkan untuk menjaga kebersihan dan kenyamanan diri. Tidak diperkenankan menggunakan parfum. (STD-QA-013)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.3', 'text' => 'Karyawan pria tidak diperkenankan berkumis atau berjenggot. Rambut harus bersih, rapi, tidak diwarnai dengan warna yang mencolok/disengaja, tidak melebihi alis mata maupun telinga, serta seluruh rambut harus tertutup sempurna oleh hair net atau penutup kepala selama berada di area produksi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.4', 'text' => 'Karyawan tidak menggunakan softlens berwarna (cosmetic contact lens), bulu mata palsu/eyelash extension, acne patch yang terlihat atau berpotensi terlepas, ketek, kuku palsu, maupun kosmetik berlebihan yang dapat menjadi sumber kontaminasi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.5', 'text' => 'Karyawan tidak diperkenankan menggunakan perhiasan atau aksesoris seperti anting, gelang, cincin, kalung, jam tangan, maupun aksesoris lainnya selama berada di area produksi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 50],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.6', 'text' => 'Kuku tangan dipotong pendek, bersih, terawat serta tidak terdapat kotoran pada sela-sela kuku. (STD-QA-013)', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.7', 'text' => 'Karyawan menggunakan seragam kerja lengkap sesuai standar perusahaan, meliputi chef jacket, apron, topi atau hair net, celana hitam, kaos kaki, ikat pinggang, dan safety shoes. Seluruh seragam dalam kondisi bersih, rapi, tidak rusak, dan terawat dengan baik. (STD-QA-013)', 'weight' => 50, 'sort_order' => 70],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.8', 'text' => 'Karyawan wajib melepas atau tidak menggunakan chef jacket, apron, dan penutup kepala ketika keluar dari area produksi untuk menghindari kontaminasi silang dari lingkungan luar ke area pengolahan makanan. (STD-QA-013)', 'weight' => 30, 'sort_order' => 80],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.9', 'text' => 'Karyawan tidak melakukan tindakan yang dapat menyebabkan kontaminasi silang pada saat handling produk, seperti batuk, bersin, membuang ingus, meludah, menggaruk anggota tubuh, menyentuh wajah atau rambut, serta mencicipi makanan menggunakan jari atau utensil yang digunakan berulang tanpa proses pembersihan. (STD-QA-013)', 'weight' => 50, 'sort_order' => 90],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.10', 'text' => 'Karyawan yang memiliki luka terbuka wajib menutup luka menggunakan perban tahan air (waterproof dressing). Luka tidak boleh dibiarkan terbuka selama bekerja di area produksi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 100],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.11', 'text' => 'Karyawan tidak diperkenankan makan, mengunyah makanan/permen karet, merokok, atau menggunakan rokok elektronik di area kerja selama jam operasional. (STD-QA-013)', 'weight' => 50, 'sort_order' => 110],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.12', 'text' => 'Barang pribadi karyawan disimpan pada loker yang telah disediakan. Karyawan tidak diperkenankan meninggalkan barang pribadi seperti tas, pakaian, sepatu, celana, maupun perlengkapan lainnya di area operasional. Pada akhir jam kerja, loker dikosongkan dari barang yang tidak diperbolehkan sesuai ketentuan perusahaan. (STD-QA-013)', 'weight' => 30, 'sort_order' => 120],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.13', 'text' => 'Persediaan hand glove sekali pakai, hair net, dan masker tersedia dalam jumlah yang memadai untuk mendukung operasional serta mudah diakses oleh karyawan pada saat dibutuhkan. (STD-QA-013)', 'weight' => 30, 'sort_order' => 130],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.14', 'text' => 'Fasilitas karyawan seperti loker, rak sepatu, toilet karyawan, mushola, area istirahat, dan fasilitas pendukung lainnya berada dalam kondisi bersih, rapi, terawat, tidak berbau, serta dilakukan pembersihan secara rutin sesuai jadwal yang telah ditetapkan. (STD-QA-006). Guide Line: Lemari loker difungsikan sebagai tempat menyimpan ponsel, tas dan barang pribadi karyawan sebelum bekerja. Didalam loker tidak terdapat: sepatu, rokok, masker, hand gloves, senjata jenis apapun, obat-obatan terlarang. Tidak terdapat barang tidak terpakai, sepatu dalam kondisi tertutup (terbungkus), uniform kerja dan sepatu kerja harus dibawa pulang.', 'weight' => 30, 'sort_order' => 140],
    ['sc' => 'KTA-S5-1', 'code' => 'KTA-5.15', 'text' => 'Karyawan wajib melakukan cuci tangan sesuai SOP menggunakan air mengalir dan sabun pada waktu-waktu kritis, seperti sebelum memulai pekerjaan, setelah menggunakan toilet, setelah menangani bahan mentah, setelah membuang sampah, setelah batuk atau bersin, setelah menyentuh bagian tubuh, dan setiap kali terdapat potensi kontaminasi silang. (STD-QA-026). Guide Line: Standar 6 langkah cuci tangan: 1. Telapak tangan dengan telapak tangan. 2. Punggung tangan. 3. Sela-sela jari. 4. Punggung jari. 5. Ibu jari. 6. Ujung jari dan kuku.', 'weight' => 30, 'sort_order' => 150],

    // 6 EMERGENCY & SAFETY FACILITIES
    ['sc' => 'KTA-S6-1', 'code' => 'KTA-6.1', 'text' => 'Kotak P3K tersedia pada lokasi yang telah ditentukan, mudah dijangkau, diberi identifikasi yang jelas, dalam kondisi bersih dan lengkap sesuai daftar isi standar perusahaan. Seluruh isi P3K berada dalam masa berlaku (tidak kedaluwarsa), dan dilakukan pemeriksaan serta pengisian ulang secara berkala. (SOP-QA-031). Guide Line: Isi kotak P3K tipe B (jumlah pekerja 26-50 orang): Kasa steril terbungkus, Perban 5 cm dan perban 10 cm, Plester gulung, Plester cepat, Kapas, Kain segitiga (mitella), Gunting, Peniti, Sarung tangan sekali pakai, Masker, Pinset, Lampu senter/penlight, Gelas pencuci mata, Larutan saline/NaCl atau pencuci mata, Povidone iodine 60 ml, Alkohol 70%, Kantong plastik bersih/biohazard, Kantong es (ICE pack), Buku panduan P3K, Buku catatan/laporan P3K, Daftar isi kotak P3K.', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'KTA-S6-1', 'code' => 'KTA-6.2', 'text' => 'Lampu emergency tersedia pada area yang dipersyaratkan, dalam kondisi bersih, berfungsi dengan baik, serta mampu menyala secara otomatis saat terjadi pemadaman listrik. Pemeriksaan dan uji fungsi dilakukan secara berkala serta terdokumentasi. (SOP-QA-006)', 'weight' => 10, 'sort_order' => 20],

    // 7 FOOD AND BEVERAGE QUALITY CHECK UNTUK INTERNAL QA
    ['sc' => 'KTA-S7-1', 'code' => 'KTA-7.1', 'text' => 'Produk makanan dibuat sesuai recipe standard, standard portion, metode pengolahan, dan spesifikasi produk yang telah ditetapkan oleh perusahaan. (SOP-OPS-004)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'KTA-S7-1', 'code' => 'KTA-7.2', 'text' => 'Penampilan produk sesuai standar, meliputi bentuk, warna, tekstur, garnish, kebersihan alat saji, serta presentasi produk yang konsisten. (SOP-OPS-004)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'KTA-S7-1', 'code' => 'KTA-7.3', 'text' => 'Produk disajikan pada suhu yang sesuai dengan standar, sehingga kualitas, keamanan, dan pengalaman tamu tetap terjaga. (SOP-QA-011)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'KTA-S7-1', 'code' => 'KTA-7.4', 'text' => 'Produk yang telah diproduksi dan disimpan mengikuti ketentuan holding time dan masa simpan yang berlaku. Produk yang telah melewati batas waktu penyajian tidak diperbolehkan untuk disajikan kepada tamu. (SL-01/(K/QA)/IX/25)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'KTA-S7-1', 'code' => 'KTA-7.5', 'text' => 'Produk bebas dari kontaminasi fisik, kimia, dan biologis, seperti rambut, serpihan benda asing, bau tidak normal, lendir, atau indikasi kerusakan lainnya. (SOP-QA-015)', 'weight' => 50, 'sort_order' => 50],
    ['sc' => 'KTA-S7-1', 'code' => 'KTA-7.6', 'text' => 'Produk disajikan menggunakan serving equipment yang sesuai standar, bersih, tidak rusak, dan mendukung kualitas presentasi produk. (SOP-OPS-004, SOP-QA-005)', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'KTA-S7-1', 'code' => 'KTA-7.7', 'text' => 'Produk disajikan kepada tamu sesuai standar waktu penyajian (service time) yang ditetapkan tanpa mengurangi kualitas produk. (STD-QA-004)', 'weight' => 30, 'sort_order' => 70],
];

$normalize = static function (string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return $value;
};

$getCategoryId = static function (array $row, callable $normalize) use ($now): int {
    $existingByCode = DB::table('qa2_categories')->where('code', $row['code'])->value('id');
    if ($existingByCode) {
        DB::table('qa2_categories')->where('id', $existingByCode)->update([
            'name' => $row['name'],
            'status' => 'A',
            'updated_at' => $now,
        ]);
        return (int) $existingByCode;
    }

    $all = DB::table('qa2_categories')->select('id', 'name')->get();
    $wanted = $normalize($row['name']);
    foreach ($all as $cat) {
        if ($normalize($cat->name) === $wanted) {
            DB::table('qa2_categories')->where('id', $cat->id)->update([
                'status' => 'A',
                'updated_at' => $now,
            ]);
            return (int) $cat->id;
        }
    }

    return (int) DB::table('qa2_categories')->insertGetId([
        'code' => $row['code'],
        'name' => $row['name'],
        'status' => 'A',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
};

$getSubcategoryId = static function (array $row, int $categoryId, callable $normalize) use ($now): int {
    $existingByCode = DB::table('qa2_subcategories')->where('code', $row['code'])->value('id');
    if ($existingByCode) {
        DB::table('qa2_subcategories')->where('id', $existingByCode)->update([
            'category_id' => $categoryId,
            'name' => $row['name'],
            'sort_order' => $row['sort_order'],
            'status' => 'A',
            'updated_at' => $now,
        ]);
        return (int) $existingByCode;
    }

    $all = DB::table('qa2_subcategories')->where('category_id', $categoryId)->select('id', 'name')->get();
    $wanted = $normalize($row['name']);
    foreach ($all as $sub) {
        if ($normalize($sub->name) === $wanted) {
            DB::table('qa2_subcategories')->where('id', $sub->id)->update([
                'sort_order' => $row['sort_order'],
                'status' => 'A',
                'updated_at' => $now,
            ]);
            return (int) $sub->id;
        }
    }

    return (int) DB::table('qa2_subcategories')->insertGetId([
        'category_id' => $categoryId,
        'code' => $row['code'],
        'name' => $row['name'],
        'sort_order' => $row['sort_order'],
        'status' => 'A',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
};

$getParameterId = static function (array $row, int $subcategoryId, callable $normalize) use ($now): int {
    $existingByCode = DB::table('qa2_parameters')->where('code', $row['code'])->value('id');
    if ($existingByCode) {
        DB::table('qa2_parameters')->where('id', $existingByCode)->update([
            'subcategory_id' => $subcategoryId,
            'parameter_text' => $row['text'],
            'weight' => $row['weight'],
            'sort_order' => $row['sort_order'],
            'status' => 'A',
            'updated_at' => $now,
        ]);
        return (int) $existingByCode;
    }

    $all = DB::table('qa2_parameters')
        ->where('subcategory_id', $subcategoryId)
        ->select('id', 'parameter_text')
        ->get();
    $wanted = $normalize($row['text']);

    foreach ($all as $param) {
        if ($normalize($param->parameter_text) === $wanted) {
            DB::table('qa2_parameters')->where('id', $param->id)->update([
                'weight' => $row['weight'],
                'sort_order' => $row['sort_order'],
                'status' => 'A',
                'updated_at' => $now,
            ]);
            return (int) $param->id;
        }
    }

    return (int) DB::table('qa2_parameters')->insertGetId([
        'subcategory_id' => $subcategoryId,
        'code' => $row['code'],
        'parameter_text' => $row['text'],
        'weight' => $row['weight'],
        'sort_order' => $row['sort_order'],
        'status' => 'A',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
};

DB::beginTransaction();
$templateItemCount = 0;

try {
    DB::table('qa2_templates')->updateOrInsert(
        ['code' => $template['code'], 'version' => $template['version']],
        [
            'name' => $template['name'],
            'audit_type' => $template['audit_type'],
            'department' => $template['department'],
            'status' => $template['status'],
            'notes' => $template['notes'],
            'updated_at' => $now,
            'created_at' => $now,
        ]
    );

    $templateId = (int) DB::table('qa2_templates')
        ->where('code', $template['code'])
        ->where('version', $template['version'])
        ->value('id');

    $categoryIdByCode = [];
    foreach ($categories as $category) {
        $categoryIdByCode[$category['code']] = $getCategoryId($category, $normalize);
    }

    $subcategoryIdByCode = [];
    foreach ($subcategories as $subcategory) {
        $categoryId = $categoryIdByCode[$subcategory['cat']];
        $subcategoryIdByCode[$subcategory['code']] = $getSubcategoryId($subcategory, $categoryId, $normalize);
    }

    $paramIds = [];
    foreach ($parameters as $parameter) {
        $subcategoryId = $subcategoryIdByCode[$parameter['sc']];
        $paramIds[] = $getParameterId($parameter, $subcategoryId, $normalize);
    }

    $paramIds = array_values(array_unique($paramIds));

    DB::table('qa2_template_items')
        ->where('template_id', $templateId)
        ->whereNotIn('parameter_id', $paramIds)
        ->delete();

    $sortOrder = 1;
    foreach ($parameters as $parameter) {
        $pid = DB::table('qa2_parameters')->where('code', $parameter['code'])->value('id');
        if (!$pid) {
            continue;
        }

        DB::table('qa2_template_items')->updateOrInsert(
            ['template_id' => $templateId, 'parameter_id' => (int) $pid],
            [
                'sort_order' => $sortOrder++,
                'is_required' => 1,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    $templateItemCount = (int) DB::table('qa2_template_items')->where('template_id', $templateId)->count();

    if ($isApply) {
        DB::commit();
        echo "[APPLY MODE] Commit berhasil." . PHP_EOL;
    } else {
        DB::rollBack();
        echo "[SAFE MODE] Dry-run selesai. Perubahan di-rollback." . PHP_EOL;
    }
} catch (Throwable $e) {
    DB::rollBack();
    throw $e;
}

echo "Kitchen Audit seed completed. Total template items: {$templateItemCount}" . PHP_EOL;
