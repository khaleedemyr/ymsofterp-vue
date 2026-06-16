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
    echo "Untuk commit ke database, jalankan: php scripts/seed_main_store_audit.php --apply --yes" . PHP_EOL;
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
    'code' => 'MST',
    'name' => 'Main Store',
    'audit_type' => 'Store Evaluation',
    'department' => 'Main Store',
    'version' => 1,
    'status' => 'A',
    'notes' => 'Seed from Main Store audit screenshots',
];

$categories = [
    ['code' => 'MST-C1', 'name' => 'RECEIVING, STORAGE, PREPARATION, DISTRIBUTION', 'sort_order' => 10],
    ['code' => 'MST-C2', 'name' => 'CLEANING AND SANITATION', 'sort_order' => 20],
    ['code' => 'MST-C3', 'name' => 'CHEMICAL CONTROL', 'sort_order' => 30],
    ['code' => 'MST-C4', 'name' => 'PEST CONTROL', 'sort_order' => 40],
    ['code' => 'MST-C5', 'name' => 'PERSONAL HYGIENE', 'sort_order' => 50],
    ['code' => 'MST-C6', 'name' => 'EMERGENCY & SAFETY FACILITIES', 'sort_order' => 60],
];

$subcategories = [
    ['cat' => 'MST-C1', 'code' => 'MST-S1-1', 'name' => 'RECEIVING', 'sort_order' => 10],
    ['cat' => 'MST-C1', 'code' => 'MST-S1-2', 'name' => 'STORAGE', 'sort_order' => 20],
    ['cat' => 'MST-C1', 'code' => 'MST-S1-3', 'name' => 'PREPARATION', 'sort_order' => 30],
    ['cat' => 'MST-C1', 'code' => 'MST-S1-4', 'name' => 'DISTRIBUTION', 'sort_order' => 40],

    ['cat' => 'MST-C2', 'code' => 'MST-S2-1', 'name' => 'PENGELOLAAN SAMPAH DI PREPARATION AREA', 'sort_order' => 10],
    ['cat' => 'MST-C2', 'code' => 'MST-S2-2', 'name' => 'STORE EQUIPMENT', 'sort_order' => 20],
    ['cat' => 'MST-C2', 'code' => 'MST-S2-3', 'name' => 'UTENSIL', 'sort_order' => 30],
    ['cat' => 'MST-C2', 'code' => 'MST-S2-4', 'name' => 'KENDARAAN & PERALATAN DISTRIBUSI', 'sort_order' => 40],
    ['cat' => 'MST-C2', 'code' => 'MST-S2-5', 'name' => 'STORE AREA CLEANLINESS', 'sort_order' => 50],
    ['cat' => 'MST-C2', 'code' => 'MST-S2-6', 'name' => 'PROSEDUR CLEANING & SANITASI', 'sort_order' => 60],
    ['cat' => 'MST-C2', 'code' => 'MST-S2-7', 'name' => 'CUCI TANGAN', 'sort_order' => 70],

    ['cat' => 'MST-C3', 'code' => 'MST-S3-1', 'name' => 'CHEMICAL CONTROL', 'sort_order' => 10],
    ['cat' => 'MST-C4', 'code' => 'MST-S4-1', 'name' => 'PEST CONTROL', 'sort_order' => 10],
    ['cat' => 'MST-C5', 'code' => 'MST-S5-1', 'name' => 'PERSONAL HYGIENE', 'sort_order' => 10],
    ['cat' => 'MST-C6', 'code' => 'MST-S6-1', 'name' => 'EMERGENCY & SAFETY FACILITIES', 'sort_order' => 10],
];

$parameters = [
    ['sc' => 'MST-S1-1', 'code' => 'MST-1.1.1', 'text' => 'Seluruh proses penerimaan dan penyimpanan barang harus menggunakan alas atau pallet yang sesuai. Produk tidak diperbolehkan diletakan atau disimpan langsung dilantai. Seluruh produk food disimpan pada area terpisah dari bahan kimia (chemical) untuk mencegah resiko kontaminasi silang. (SOP-QA-019)', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'MST-S1-1', 'code' => 'MST-1.1.2', 'text' => 'Proses penerimaan produk dilakukan diarea penerimaan yang bersih, tertata dan berada pada lokasi yang telah ditentukan. (SOP-QA-019)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'MST-S1-1', 'code' => 'MST-1.1.3', 'text' => 'Produk yang diterima atau dibeli secara ritel sesuai dengan SPS produk yang telah ditetapkan oleh perusahaan. (SOP-OPS-005)', 'weight' => 10, 'sort_order' => 30],
    ['sc' => 'MST-S1-1', 'code' => 'MST-1.1.4', 'text' => 'Seluruh produk yang diterima harus melalui proses pemeriksaan terhadap kualitas, kuantitas, identitas/label, kesesuaian SPS, dan kondisi kemasan. Produk dengan kemasan rusak, penyok, kadaluarsa, atau tidak sesuai SPS wajib dipisahkan, didokumentasikan, dan ditolak atau ditindaklanjuti sesuai prosedur yang berlaku. (SOP-QA-017, SOP-QA-019, SOP-QA-020, SOP-OPS-005)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'MST-S1-1', 'code' => 'MST-1.1.5', 'text' => 'Seluruh produk harus diterima dan digunakan sesuai dengan ketentuan FIFO dan FEFO. (SOP-QA-001)', 'weight' => 30, 'sort_order' => 50],
    ['sc' => 'MST-S1-1', 'code' => 'MST-1.1.6', 'text' => 'Frozen dan chilled produk harus didistribusikan dengan menjaga integritas rantai dingin (cold chain) agar suhu produk tetap sesuai standar, termasuk memastikan produk frozen tetap dalam kondisi beku selama proses distribusi. (SOP-QA-004, SOP-QA-019, FRM-QA-001). Pada saat proses receiving wajib dilakukan pengecekan dan dokumentasi meliputi: 1. Kondisi kebersihan kendaraan pengiriman. 2. Suhu kendaraan. 3. Suhu produk pada saat diterima. 4. Waktu kedatangan driver. 5. Waktu driver meninggalkan area loading. 6. Waktu produk dimasukan kedalam freezer/chiller. Guide Line: 1. Suhu kendaraan pada saat pengiriman sesuai standar masing-masing kategori produk ( freezer suhu <-12 C, chiller 1-8 C ). 2. Suhu produk pada saat di terima harus dicek sesuai dengan standar masing-masing kategori (freezer suhu <-12 C, chiller 1-8 C). 3. Seluruh hasil pemeriksaan dan pencatatan harus didokumentasikan dengan menggunakan form receiving.', 'weight' => 10, 'sort_order' => 60],
    ['sc' => 'MST-S1-1', 'code' => 'MST-1.1.7', 'text' => 'Seluruh pencatatan administrasi harus terdokumentasi dengan lengkap, benar, dan diperbaharui setiap hari, meliputi proses Purchase Request (PR) (erp), Dokumen surat jalan, PO dan atau invoice dari Supplier guna memastikan ketertelusuran, keakuratan stock, dan pengendalian operasional. (SOP-OPS-001, SOP-OPS-002, SOP-OPS-003)', 'weight' => 10, 'sort_order' => 70],

    ['sc' => 'MST-S1-2', 'code' => 'MST-1.2.1', 'text' => 'Produk disimpan sesuai standar suhu berdasarkan jenis produknya. Sebelum disimpan, seluruh produk harus dipastikan memiliki kondisi kemasan yang bersih, utuh, tidak rusak, tidak bocor, dan dalam keadaan baik. (SOP-QA-002, SOP-QA-004)', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'MST-S1-2', 'code' => 'MST-1.2.2', 'text' => 'Penyimpanan produk harus dipisahkan berdasarkan kategori jenis produk, seperti seafood, beef, poultry, ikan, sauce, dairy product, roti, bahan setengah jadi, buah dan sayuran untuk mencegah terjadinya kontaminasi silang. Apabila terdapat keterbatasan ruang/tempat penyimpanan, produk dapat disimpan dalam area yang sama dengan menggunakan box container tertutup. (SOP-QA-014)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'MST-S1-2', 'code' => 'MST-1.2.3', 'text' => 'Seluruh produk yang disimpan tidak boleh bersentuhan langsung dengan lantai. Produk harus ditempatkan menggunakan alas (pallet) atau rak penyimpanan dengan ketinggian/jarak rak terbawah kurang minimal 15 cm dari lantai. (SOP-QA-002)', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'MST-S1-2', 'code' => 'MST-1.2.4', 'text' => 'Tidak terdapat potensi kontaminasi silang yang dapat mempengaruhi keamanan pangan, termasuk bahaya kimia, bahaya fisik, maupun kondisi kebersihan produk. Produk harus disimpan dan ditangani dalam kondisi bersih, bebas dari benda asing. (SOP-QA-015)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'MST-S1-2', 'code' => 'MST-1.2.5', 'text' => 'Produk yang disimpan didalam freezer dan chiller harus disusun secara rapi, teratur dan tidak melebihi kapasitas penyimpanan. Susunan produk tidak boleh menutupi blower, jalur sirkulasi udara maupun ventilasi pendingin agar distribusi suhu tetap optimal. Rak penyimpanan harus dalam kondisi baik, bersih, dan digunakan sesuai fungsinya untuk menjaga kestabilan produk serta efektivitas sistem pendingin. (SOP-QA-002)', 'weight' => 10, 'sort_order' => 50],
    ['sc' => 'MST-S1-2', 'code' => 'MST-1.2.6', 'text' => 'Seluruh produk harus disimpan sesuai dengan ketentuan FIFO dan FEFO. (SOP-QA-001)', 'weight' => 30, 'sort_order' => 60],
    ['sc' => 'MST-S1-2', 'code' => 'MST-1.2.7', 'text' => 'Setiap bahan (raw material) seperti sayuran, buah-buahan, telur, maupun bahan baku/produk lain tanpa label identifikasi dari supplier wajib diberikan label identifikasi pada saat penerimaan. Label mencantumkan nama produk dan tanggal kedatangan/penerimaan untuk memastikan ketertelusuran serta mendukung sistem penerapan FIFO/FEFO. (SOP-QA-017). Guide Line: 1. Sayuran : String bean, Onion, Garlic, Tomat, Jamur champignon. 2. Buah-buahan : Lemon, Sunkist, Apel.', 'weight' => 30, 'sort_order' => 70],
    ['sc' => 'MST-S1-2', 'code' => 'MST-1.2.8', 'text' => 'Tidak ditemukan product yang telah melewati tanggal kadaluwarsa (expired). Bahan baku, bahan proses, atau produk yang telah expired, rusak, mengalami penurunan mutu atau tidak sesuai dengan SPS harus segera dipisahkan dari produk yang masih layak digunakan serta diberi label identifikasi yang jelas. (SOP-QA-017, SOP-QA-019, SOP-QA-020, SOP-OPS-005)', 'weight' => 50, 'sort_order' => 80],

    ['sc' => 'MST-S1-3', 'code' => 'MST-1.3.1', 'text' => 'Tidak terdapat penyimpangan terhadap standar kualitas produk yang telah ditetapkan. Produk harus berada dalam kondisi layak distribusi ke outlet, tidak mengalami kerusakan, pembusukan (spoiled), perubahan warna, perubahan aroma, perubahan tekstur atau indikasi penurunan mutu lainnya. (SOP-QA-008, SOP-QA-010, SOP-QA-020)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'MST-S1-3', 'code' => 'MST-1.3.2', 'text' => 'Tidak ditemukan product yang telah melewati tanggal kadaluwarsa (expired). Bahan baku, bahan proses, atau produk yang telah expired, rusak, mengalami penurunan mutu atau tidak sesuai dengan SPS harus segera dipisahkan dari produk yang masih layak digunakan serta diberi label identifikasi yang jelas. (SOP-QA-017, SOP-QA-020, SOP-OPS-005)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'MST-S1-3', 'code' => 'MST-1.3.3', 'text' => 'Seluruh produk yang digunakan dalam proses preparation harus mengikuti ketentuan FIFO dan FEFO. (SOP-QA-001)', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'MST-S1-3', 'code' => 'MST-1.3.4', 'text' => 'Tidak terdapat potensi kontaminasi silang yang dapat mempengaruhi keamanan pangan, termasuk bahaya kimia, bahaya fisik, maupun kondisi kebersihan produk. Produk harus disimpan dan ditangani dalam kondisi bersih, bebas dari benda asing. (SOP-QA-015)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'MST-S1-3', 'code' => 'MST-1.3.5', 'text' => 'Produk di kemas per bungkus/pack sesuai dengan takaran yang telah di tentukan wajib memiliki label identifikasi yang lengkap dan jelas. Tercantum nama item/produk, production date, batch number, expired date, gramasi produk, nomor seri barcode. Khusus produk repacking dari supplier, menggunakan labeling sesuai dengan ketentuan perusahaan, dan disimpan di dalam suhu sesuai standar penyimpanan produk (SOP-QA-017, SL-01/(K/QA)/I/X/25). Guide Line: Barcode yang tertera dikemasan : tidak boleh menutupi tanggal produksi atau tanggal kadaluarsa dari produsen, terdapat nama produk, tanggal produksi, tanggal kadaluarsa, nomor barcode dengan lengkap dan jelas, tidak sobek, dapat terbaca dengan jelas, kode produksi up date, warna tinta tidak luntur, satu barcode untuk setiap satu kemasan, nama item produk pada barcode sesuai dengan produk dalam kemasan.', 'weight' => 30, 'sort_order' => 50],
    ['sc' => 'MST-S1-3', 'code' => 'MST-1.3.6', 'text' => 'Bahan pengemas primer dan sekunder yang digunakan sesuai dengan standar (SOP-QA-033). Guide Line: Kemasan tertutup rapat dan tidak rusak : simpul plastik terikat dengan kuat/seal menempel kuat, tutup jerigen tertutup dengan rapat, bahan plastik : tidak regas/tidak sobek, tidak berlubang, tidak kotor, bahan kaleng : tidak penyok, tidak berkarat, tidak mengembung, tidak kotor, jerigen : tidak ada bagian yang bocor, tidak kotor, wadah box : tidak ada bagian yang bocor, bagian dinding tidak berlubang/retak, tidak kotor, bahan kaca : tidak ada bagian yang retak/tidak pecah, tidak kotor.', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'MST-S1-3', 'code' => 'MST-1.3.7', 'text' => 'Seluruh produk di persiapkan sesuai dengan SOP, instruksi kerja (IK) dan spesifikasi produk yang telah ditetapkan, serta menerapkan prinsip prinsip keamanan pangan (food safety selama proses preparation). (SOP-OPS-004, SOP-QA-003, SOP-QA-016)', 'weight' => 50, 'sort_order' => 70],
    ['sc' => 'MST-S1-3', 'code' => 'MST-1.3.8', 'text' => 'Utensil yang digunakan untuk proses persiapan produk (preparation) dalam kondisi bersih, terawat dan layak pakai, serta bebas dari kotoran, karat, retak, pecah, atau kerusakan lainnya yang dapat mencemari produk pangan. (SOP-QA-007)', 'weight' => 30, 'sort_order' => 80],

    ['sc' => 'MST-S1-4', 'code' => 'MST-1.4.1', 'text' => 'Tidak terdapat penyimpangan terhadap standar kualitas produk yang telah ditetapkan. Produk harus berada dalam kondisi layak distribusi ke outlet, tidak mengalami kerusakan, pembusukan (spoiled), perubahan warna, perubahan aroma, perubahan tekstur atau indikasi penurunan mutu lainnya. (SOP-QA-008, SOP-QA-010, SOP-QA-020)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'MST-S1-4', 'code' => 'MST-1.4.2', 'text' => 'Seluruh produk yang didistribusikan harus melalui proses pemeriksaan terhadap kualitas, kuantitas, identitas atau label, kesesuaian SPS, dan kondisi kemasan. Produk dengan kemasan rusak, penyok, kadaluarsa, atau tidak sesuai SPS wajib dipisahkan, didokumentasikan, dan ditindaklanjuti sesuai prosedur yang berlaku. (SOP-QA-008, SOP-QA-032, SOP-QA-020, SOP-OPS-005)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'MST-S1-4', 'code' => 'MST-1.4.3', 'text' => 'Seluruh produk yang disimpan tidak boleh bersentuhan langsung dengan lantai mobil. Produk harus ditempatkan menggunakan container. (SOP-QA-002)', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'MST-S1-4', 'code' => 'MST-1.4.4', 'text' => 'Kendaraan, peralatan distribusi mampu melindungi produk dari hujan, panas, debu, dan bebas dari kerusakan yang dapat menyebabkan kontaminasi produk selama pengiriman. (SOP-QA-006)', 'weight' => 30, 'sort_order' => 40],
    ['sc' => 'MST-S1-4', 'code' => 'MST-1.4.5', 'text' => 'Kendaraan berpendingin (jika diperlukan) berfungsi dengan baik serta mampu menjaga suhu sesuai spesifikasi produk dengan menjaga integritas rantai dingin (cold chain) agar suhu produk tetap sesuai standar, termasuk memastikan produk frozen tetap dalam kondisi beku selama proses distribusi dan memiliki kondisi fisik yang baik dan layak operasional. (SOP-QA-004, SOP-QA-019, FRM-QA-001). Pada saat proses distribusi wajib dilakukan pengecekan dan dokumentasi meliputi : 1. Kondisi kebersihan kendaraan pengiriman. 2. Suhu kendaraan. 3. Suhu produk pada saat pengiriman. 4. Waktu keberangkatan driver. Guide Line: 1. Suhu kendaraan pada saat pengiriman sesuai standar masing-masing kategori produk ( freezer suhu <-12 C chiller 1-8 C ). 2. Suhu produk pada saat di terima harus dicek sesuai dengan standar masing-masing kategori (freezer suhu <-12 C, chiller 1-8 C). 3. Seluruh hasil pemeriksaan dan pencatatan harus didokumentasikan dengan menggunakan form receiving.', 'weight' => 50, 'sort_order' => 50],
    ['sc' => 'MST-S1-4', 'code' => 'MST-1.4.6', 'text' => 'Seluruh produk yang digunakan dalam proses distribusi harus mengikuti ketentuan FIFO dan FEFO. (SOP-QA-001)', 'weight' => 30, 'sort_order' => 60],
    ['sc' => 'MST-S1-4', 'code' => 'MST-1.4.7', 'text' => 'Kendaraan tidak digunakan untuk membawa bahan berbahaya yang dapat mencemari produk pangan, tidak terdapat potensi kontaminasi silang yang dapat mempengaruhi keamanan pangan, termasuk bahaya kimia, bahaya fisik, maupun kondisi kebersihan produk (khusus chemical, menggunakan box container yang tertutup). Produk harus disimpan dan ditangani dalam kondisi bersih, bebas dari benda asing. (SOP-QA-015)', 'weight' => 50, 'sort_order' => 70],

    ['sc' => 'MST-S2-1', 'code' => 'MST-2.1.1', 'text' => 'Tempat sampah dilengkapi tutup dan pedal yang berfungsi dengan baik, menggunakan plastik sampah dalam kondisi bersih dan terawat untuk mencegah potensi kontaminasi. (SOP-QA-009)', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'MST-S2-1', 'code' => 'MST-2.1.2', 'text' => 'Sampah tidak melebihi kapasitas tempat sampah atau keluar dari batas penutup. Apabila tempat sampah telah mencapai batas yang ditentukan, sampah harus segera dibuang sesuai prosedur yang berlaku Sampah dipilah dan dipisahkan antara sampah organik dan non organik. (SOP-QA-009)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'MST-S2-1', 'code' => 'MST-2.1.3', 'text' => 'Sampah organik dan non organik dipisahkan pada tempat sampah yang berbeda, diberi identifikasi yang jelas serta dikelola sesuai prosedur pengelolaan sampah yang berlaku. (SOP-QA-009)', 'weight' => 30, 'sort_order' => 30],

    ['sc' => 'MST-S2-2', 'code' => 'MST-2.2.1', 'text' => 'Store operating equipment berada dalam kondisi siap pakai, seluruh fungsi operasional seperti pengaturan suhu, indikator, tombol control dan sistem pengaman bekerja dengan baik tanpa menghambat kelancaran proses produksi. (SOP-QA-004, SOP-QA-013)', 'weight' => 10, 'sort_order' => 10],
    ['sc' => 'MST-S2-2', 'code' => 'MST-2.2.2', 'text' => 'Store operating equipment dalam kondisi bersih, terawat, bebas dari kerak, minyak, debu, sisa produk, dan kerusakan. Pembersihan equipment dilakukan secara rutin menggunakan metode cleaning dan sanitasi sesuai prosedur yang ditetapkan untuk memastikan kebersihan dan keamanan pangan. (SOP-QA-006, FRM-QA-002)', 'weight' => 30, 'sort_order' => 20],

    ['sc' => 'MST-S2-3', 'code' => 'MST-2.3.1', 'text' => 'Seluruh utensil tersedia sesuai kebutuhan operasional, dalam kondisi bersih, terawat, aman digunakan, serta tidak ditemukan kerusakan seperti retak, pecah, penyok, patah atau berkarat. (SOP-QA-005, SOP-QA-007)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'MST-S2-3', 'code' => 'MST-2.3.2', 'text' => 'Utensil dibersihkan dengan metode cleaning dan sanitasi sesuai dengan SOP yang berlaku, bebas dari sisa makanan, noda minyak, kerak, bau tidak sedap, serta dikeringkan sebelum digunakan atau disimpan. (SOP-QA-006)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'MST-S2-3', 'code' => 'MST-2.3.3', 'text' => 'Utensil disimpan dalam kondisi bersih, kering, tertata rapi pada area yang telah ditentukan, terlindung dari debu, percikan, hama dan potensi kontaminasi silang. (SOP-QA-006)', 'weight' => 10, 'sort_order' => 30],

    ['sc' => 'MST-S2-4', 'code' => 'MST-2.4.1', 'text' => 'Kendaraan distribusi, troli, keranjang, container, alat bantu distribusi dalam kondisi bersih, bebas debu, kotoran, minyak, bau asing dan higienis. (SOP-QA-006)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'MST-S2-4', 'code' => 'MST-2.4.2', 'text' => 'Tidak terdapat hama, jejak hama, atau kontaminan pada kendaraan. (SOP-QA-006)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'MST-S2-4', 'code' => 'MST-2.4.3', 'text' => 'Area muat produk, peralatan dibersihkan dan disanitasi secara rutin sesuai jadwal pembersihan. (SOP-QA-006)', 'weight' => 30, 'sort_order' => 30],

    ['sc' => 'MST-S2-5', 'code' => 'MST-2.5.1', 'text' => 'Lantai area kerja dalam kondisi bersih, kering, tidak licin, bebas dari sisa makanan, tumpahan cairan, minyak, debu, genangan air, dan tidak terdapat kerusakan yang dapat mengganggu keselamatan kerja. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'MST-S2-5', 'code' => 'MST-2.5.2', 'text' => 'Dinding area kerja bersih, bebas dari noda, cipratan produk, minyak, debu, jamur, sarang hama, serta dalam kondisi terawat tanpa kerusakan yang berpotensi menjadi sumber kontaminasi. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'MST-S2-5', 'code' => 'MST-2.5.3', 'text' => 'Plafon dalam kondisi bersih bebas dari debu, sarang laba-laba, jamur, kebocoran, cat mengelupas atau kerusakan lainnya yang dapat mencemari area produksi. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'MST-S2-5', 'code' => 'MST-2.5.4', 'text' => 'Pintu area kerja bersih berfungsi dengan baik bebas dari kotoran dan kerusakan, serta dapat menutup dengan sempurna untuk mencegah masuknya hama. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 40],
    ['sc' => 'MST-S2-5', 'code' => 'MST-2.5.5', 'text' => 'Exhaust fan dinding dalam kondisi bersih, bebas dari penumpukan minyak, debu, dan kotoran. Berfungsi dengan baik untuk menjaga sirkulasi udara, serta mengurangi panas, asap, dan uap diarea kerja. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 50],
    ['sc' => 'MST-S2-5', 'code' => 'MST-2.5.6', 'text' => 'Saluran pembuangan (drainase) dalam kondisi bersih tidak tersumbat, tidak menimbulkan genangan, bebas dari sisa makanan dan bau tidak sedap serta dilengkapi penutup sesuai standar yang berlaku. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'MST-S2-5', 'code' => 'MST-2.5.7', 'text' => 'Lampu diarea store memiliki pelindung (cover lamp) dalam kondisi bersih berfungsi dengan baik, memberikan pencahayaan, serta tidak terdapat lampu mati atau pecah diarea kerja. (SOP-QA-006, FRM-QA-003). Guide Line: Standar lux di area produksi (store, bar, steward, store).', 'weight' => 30, 'sort_order' => 70],

    ['sc' => 'MST-S2-6', 'code' => 'MST-2.6.1', 'text' => 'Tersedia jadwal kegiatan cleaning dan sanitasi yang mencakup frekuensi, area/peralatan yang dibersihkan, PIC, serta dilengkapi dengan checklist atau laporan pelaksanaan yang terdokumentasi dan terverifikasi. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'MST-S2-6', 'code' => 'MST-2.6.2', 'text' => 'Tersedia prosedur sanitasi yang terdokumentasi dan mudah diakses oleh karyawan. Implementasi sanitasi dilakukan sesuai SOP, termasuk penggunaan bahan kimia dengan konsentrasi, metode, waktu kontak (contact time), dan frekuensi yang telah ditetapkan. (SOP-QA-006)', 'weight' => 10, 'sort_order' => 20],
    ['sc' => 'MST-S2-6', 'code' => 'MST-2.6.3', 'text' => 'Seluruh alat kebersihan dalam kondisi bersih, terawat, berfungsi dengan baik, diberi identifikasi sesuai peruntukannya, serta disimpan dengan rapi pada area yang telah ditentukan untuk mencegah kontaminasi silang. (STD-QA-001)', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'MST-S2-6', 'code' => 'MST-2.6.4', 'text' => 'Cleaning cloth dalam kondisi bersih dan dilakukan pembersihan atau penggantian secara berkala selama operasional. (SOP-QA-006)', 'weight' => 50, 'sort_order' => 40],

    ['sc' => 'MST-S2-7', 'code' => 'MST-2.7.1', 'text' => 'Fasilitas cuci tangan (handwash station) tersedia, mudah diakses, bersih, dan berfungsi dengan baik, dilengkapi dengan air mengalir, sabun cuci tangan, tissue sekali pakai, tempat sampah tertutup, serta poster tata cara cuci tangan. Karyawan wajib melakukan handwashing sesuai SOP, antara lain sebelum memulai pekerjaan, setelah menangani bahan mentah, setelah menggunakan toilet, setelah membuang sampah, setelah batuk/bersin, setelah menyentuh bagian tubuh, dan setiap kali berpotensi menyebabkan kontaminasi silang. (SOP-QA-026)', 'weight' => 30, 'sort_order' => 10],

    ['sc' => 'MST-S3-1', 'code' => 'MST-3.1', 'text' => 'Bahan kimia disimpan pada area khusus yang bersih, rapi, memiliki ventilasi yang memadai, terpisah dari bahan pangan, kemasan, dan alat makan. Chemical disusun sesuai kategori penggunaannya, dalam wadah yang sesuai, dengan kondisi tertutup, serta tidak disimpan langsung di lantai. (SOP-QA-028)', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'MST-S3-1', 'code' => 'MST-3.2', 'text' => 'Material Safety Data Sheet (MSDS) atau Safety Data Sheet (SDS) tersedia untuk seluruh bahan kimia yang digunakan, mudah diakses oleh karyawan, dalam kondisi terbaru, dan digunakan sebagai acuan dalam penanganan, penyimpanan, penggunaan, serta tindakan darurat apabila terjadi paparan atau tumpahan bahan kimia. (SOP-QA-028)', 'weight' => 10, 'sort_order' => 20],

    ['sc' => 'MST-S4-1', 'code' => 'MST-4.1', 'text' => 'Tersedia program pest control yang dilaksanakan secara terjadwal sesuai ketentuan perusahaan atau vendor yang ditunjuk, dilengkapi dengan jadwal kunjungan, laporan hasil inspeksi, denah titik monitoring, serta tindak lanjut atas setiap temuan. (SOP-QA-018)', 'weight' => 10, 'sort_order' => 10],
    ['sc' => 'MST-S4-1', 'code' => 'MST-4.2', 'text' => 'Tidak ditemukan aktivitas maupun tanda-tanda infestasi hama seperti tikus, kecoa, lalat, semut, burung, cicak, atau serangga lainnya, termasuk kotoran hama, bangkai, telur, sarang, bekas gigitan, maupun jejak lintasan pada area operasional dan penyimpanan. (SOP-QA-018)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'MST-S4-1', 'code' => 'MST-4.3', 'text' => 'Tidak terdapat celah, lubang, retakan, pintu yang tidak rapat, saluran terbuka, atau kondisi lain yang berpotensi menjadi jalur akses (entry point), tempat persembunyian (harborage), maupun area istirahat (resting area) bagi hama. (SOP-QA-018)', 'weight' => 10, 'sort_order' => 30],
    ['sc' => 'MST-S4-1', 'code' => 'MST-4.4', 'text' => 'Peralatan pengendalian hama seperti perangkap tikus, insect light trap/fly catcher, glue trap, bait station, dan perangkat monitoring lainnya tersedia sesuai kebutuhan, ditempatkan pada titik yang telah ditentukan, dalam kondisi bersih, berfungsi dengan baik, diberi identifikasi, serta dilakukan pemeriksaan secara berkala dan terdokumentasi. (SOP-QA-018)', 'weight' => 30, 'sort_order' => 40],
    ['sc' => 'MST-S4-1', 'code' => 'MST-4.5', 'text' => 'Area operasional dan area sekitar dijaga dalam kondisi bersih, rapi, bebas dari penumpukan barang, sampah, genangan air, sisa makanan, dan sumber daya tarik lainnya yang dapat memicu keberadaan hama. (SOP-QA-006)', 'weight' => 30, 'sort_order' => 50],

    ['sc' => 'MST-S5-1', 'code' => 'MST-5.1', 'text' => 'Karyawan menggunakan perlengkapan kerja sesuai ketentuan, seperti hair net yang menutupi seluruh rambut, masker, serta hand glove sekali pakai apabila dipersyaratkan sesuai jenis pekerjaan. PPE (Personal Protective Equipment) diganti secara berkala atau apabila kotor, rusak, maupun terkontaminasi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.2', 'text' => 'Karyawan menjaga kebersihan tubuh, tidak berbau badan, penggunaan deodorant diperbolehkan untuk menjaga kebersihan dan kenyamanan diri. Tidak diperkenankan menggunakan parfum. (STD-QA-013)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.3', 'text' => 'Karyawan pria tidak diperkenankan berkumis atau berjenggot. Rambut harus bersih, rapi, tidak diwarnai dengan warna yang mencolok/disengaja, tidak melebihi alis mata maupun telinga, serta seluruh rambut harus tertutup sempurna oleh hair net atau penutup kepala selama berada di area produksi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.4', 'text' => 'Karyawan tidak menggunakan bulu mata palsu/eyelash extension, kuku palsu, maupun kosmetik berlebihan yang dapat menjadi sumber kontaminasi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.5', 'text' => 'Karyawan tidak diperkenankan menggunakan perhiasan atau aksesori seperti gelang, kalung, maupun aksesori lainnya selama berada di area produksi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 50],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.6', 'text' => 'Kuku tangan bersih, terawat, tidak menggunakan kuku palsu, serta tidak terdapat kotoran pada sela-sela kuku. (STD-QA-013)', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.7', 'text' => 'Karyawan menggunakan seragam kerja lengkap sesuai standar perusahaan. Seluruh seragam dalam kondisi bersih, rapi, tidak rusak, dan terawat dengan baik. (STD-QA-013)', 'weight' => 50, 'sort_order' => 70],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.8', 'text' => 'Karyawan tidak melakukan tindakan yang dapat menyebabkan kontaminasi silang pada saat handling produk, seperti batuk, bersin, membuang ingus, meludah, menggaruk anggota tubuh, menyentuh wajah atau rambut, serta mencicipi makanan menggunakan jari atau utensil yang digunakan berulang tanpa proses pembersihan. (STD-QA-013)', 'weight' => 50, 'sort_order' => 80],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.9', 'text' => 'Karyawan yang memiliki luka terbuka wajib menutup luka menggunakan perban tahan air (waterproof dressing). Luka tidak boleh dibiarkan terbuka selama bekerja di area produksi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 90],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.10', 'text' => 'Karyawan tidak diperkenankan makan, mengunyah makanan/permen karet, merokok, atau menggunakan rokok elektronik di area kerja selama jam operasional. (STD-QA-013)', 'weight' => 50, 'sort_order' => 100],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.11', 'text' => 'Barang pribadi karyawan disimpan pada loker yang telah disediakan. Karyawan tidak diperkenankan meninggalkan barang pribadi seperti tas, pakaian, sepatu, celana, maupun perlengkapan lainnya di area operasional. Pada akhir jam kerja, loker dikosongkan dari barang yang tidak diperbolehkan sesuai ketentuan perusahaan. (STD-QA-013)', 'weight' => 30, 'sort_order' => 110],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.12', 'text' => 'Persediaan hand glove sekali pakai, hair net, dan masker tersedia dalam jumlah yang memadai untuk mendukung operasional serta mudah diakses oleh karyawan pada saat dibutuhkan. (STD-QA-013)', 'weight' => 30, 'sort_order' => 120],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.13', 'text' => 'Fasilitas karyawan seperti loker, rak sepatu, toilet karyawan, mushola, area istirahat, dan fasilitas pendukung lainnya berada dalam kondisi bersih, rapi, terawat, tidak berbau, serta dilakukan pembersihan secara rutin sesuai jadwal yang telah ditetapkan. (STD-QA-006). Guide Line: Lemari loker difungsikan sebagai tempat menyimpan ponsel, tas dan barang pribadi karyawan sebelum bekerja. Didalam loker tidak terdapat: sepatu, rokok, masker, hand gloves, senjata jenis apapun, obat-obatan terlarang. Tidak terdapat barang tidak terpakai, sepatu dalam kondisi tertutup (terbungkus), uniform kerja dan sepatu kerja harus dibawa pulang.', 'weight' => 30, 'sort_order' => 130],
    ['sc' => 'MST-S5-1', 'code' => 'MST-5.14', 'text' => 'Karyawan wajib melakukan cuci tangan sesuai SOP menggunakan air mengalir dan sabun pada waktu-waktu kritis, seperti sebelum memulai pekerjaan, setelah menggunakan toilet, setelah menangani bahan mentah, setelah membuang sampah, setelah batuk atau bersin, setelah menyentuh bagian tubuh, dan setiap kali terdapat potensi kontaminasi silang. (STD-QA-026). Guide Line: Standar 6 langkah cuci tangan: 1. Telapak tangan dengan telapak tangan. 2. Punggung tangan. 3. Sela-sela jari. 4. Punggung jari. 5. Ibu jari. 6. Ujung jari dan kuku.', 'weight' => 30, 'sort_order' => 140],

    ['sc' => 'MST-S6-1', 'code' => 'MST-6.1', 'text' => 'Kotak P3K tersedia pada lokasi yang telah ditentukan, mudah dijangkau, diberi identifikasi yang jelas, dalam kondisi bersih dan lengkap sesuai daftar isi standar perusahaan. Seluruh isi P3K berada dalam masa berlaku (tidak kedaluwarsa), dan dilakukan pemeriksaan serta pengisian ulang secara berkala. (SOP-QA-031). Guide Line: Isi kotak P3K tipe B (jumlah pekerja 26-50 orang): Kasa steril terbungkus, Perban 5 cm dan perban 10 cm, Plester gulung, Plester cepat, Kapas, Kain segitiga (mitella), Gunting, Peniti, Sarung tangan sekali pakai, Masker, Pinset, Lampu senter/penlight, Gelas pencuci mata, Larutan saline/NaCl atau pencuci mata, Povidone iodine 60 ml, Alkohol 70%, Kantong plastik bersih/biohazard, Kantong es (ICE pack), Buku panduan P3K, Buku catatan/laporan P3K, Daftar isi kotak P3K.', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'MST-S6-1', 'code' => 'MST-6.2', 'text' => 'Lampu emergency tersedia pada area yang dipersyaratkan, dalam kondisi bersih, berfungsi dengan baik, serta mampu menyala secara otomatis saat terjadi pemadaman listrik. Pemeriksaan dan uji fungsi dilakukan secara berkala serta terdokumentasi. (SOP-QA-006)', 'weight' => 10, 'sort_order' => 20],
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

echo "Main Store seed completed. Total template items: {$templateItemCount}" . PHP_EOL;

