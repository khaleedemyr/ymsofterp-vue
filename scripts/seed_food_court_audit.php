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
    echo "Untuk commit ke database, jalankan: php scripts/seed_food_court_audit.php --apply --yes" . PHP_EOL;
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
    'code' => 'FCA',
    'name' => 'Food Court Audit',
    'audit_type' => 'Restaurant Evaluation',
    'department' => 'Food Court',
    'version' => 1,
    'status' => 'A',
    'notes' => 'Seed from latest audit form',
];

$categories = [
    ['code' => 'FC-C1', 'name' => 'RECEIVING, STORAGE, PREPARATION, PROCESSING, SERVING', 'sort_order' => 10],
    ['code' => 'FC-C2', 'name' => 'KITCHEN CLEANLINESS', 'sort_order' => 20],
    ['code' => 'FC-C3', 'name' => 'SEQUENCE OF SERVICE', 'sort_order' => 30],
    ['code' => 'FC-C4', 'name' => 'SERVICE & HOSPITALITY', 'sort_order' => 40],
    ['code' => 'FC-C5', 'name' => 'RESTAURANT CLEANLINESS', 'sort_order' => 50],
    ['code' => 'FC-C6', 'name' => 'SERVICE EQUIPMENT', 'sort_order' => 60],
    ['code' => 'FC-C7', 'name' => 'CHEMICAL CONTROL', 'sort_order' => 70],
    ['code' => 'FC-C8', 'name' => 'PEST CONTROL', 'sort_order' => 80],
    ['code' => 'FC-C9', 'name' => 'PERSONAL HYGIENE', 'sort_order' => 90],
    ['code' => 'FC-C10', 'name' => 'EMERGENCY & SAFETY FACILITIES', 'sort_order' => 100],
    ['code' => 'FC-C11', 'name' => 'FOOD AND BEVERAGE QUALITY CHECK', 'sort_order' => 110],
];

$subcategories = [
    ['cat' => 'FC-C1', 'code' => 'FC-S1-1', 'name' => 'RECEIVING', 'sort_order' => 10],
    ['cat' => 'FC-C1', 'code' => 'FC-S1-2', 'name' => 'STORAGE', 'sort_order' => 20],
    ['cat' => 'FC-C1', 'code' => 'FC-S1-3', 'name' => 'PREPARATION', 'sort_order' => 30],
    ['cat' => 'FC-C1', 'code' => 'FC-S1-4', 'name' => 'COOKING', 'sort_order' => 40],
    ['cat' => 'FC-C1', 'code' => 'FC-S1-5', 'name' => 'HOLDING / WARMING', 'sort_order' => 50],

    ['cat' => 'FC-C2', 'code' => 'FC-S2-1', 'name' => 'FOOD PRODUCTION/PREPARATION AREA', 'sort_order' => 10],
    ['cat' => 'FC-C2', 'code' => 'FC-S2-2', 'name' => 'KITCHEN STORAGE AREA', 'sort_order' => 20],
    ['cat' => 'FC-C2', 'code' => 'FC-S2-3', 'name' => 'KITCHEN UTENSILS & EQUIPMENT AREA', 'sort_order' => 30],
    ['cat' => 'FC-C2', 'code' => 'FC-S2-4', 'name' => 'UTILITY', 'sort_order' => 40],
    ['cat' => 'FC-C2', 'code' => 'FC-S2-5', 'name' => 'FOOD HANDLING EQUIPMENT', 'sort_order' => 50],
    ['cat' => 'FC-C2', 'code' => 'FC-S2-6', 'name' => 'COOKING EQUIPMENT', 'sort_order' => 60],
    ['cat' => 'FC-C2', 'code' => 'FC-S2-7', 'name' => 'FLOOR DRAIN / WASTE CONTROL', 'sort_order' => 70],
    ['cat' => 'FC-C2', 'code' => 'FC-S2-8', 'name' => 'CEILING', 'sort_order' => 80],

    ['cat' => 'FC-C3', 'code' => 'FC-S3-1', 'name' => 'SEQUENCE OF SERVICE', 'sort_order' => 10],
    ['cat' => 'FC-C4', 'code' => 'FC-S4-1', 'name' => 'SERVICE & HOSPITALITY', 'sort_order' => 10],

    ['cat' => 'FC-C5', 'code' => 'FC-S5-1', 'name' => 'AREA TAMU (DINING AREA)', 'sort_order' => 10],
    ['cat' => 'FC-C5', 'code' => 'FC-S5-2', 'name' => 'CASHIER AREA', 'sort_order' => 20],
    ['cat' => 'FC-C5', 'code' => 'FC-S5-3', 'name' => 'AREA SERVICE STATION', 'sort_order' => 30],
    ['cat' => 'FC-C5', 'code' => 'FC-S5-4', 'name' => 'AREA PENYIMPANAN PERLENGKAPAN SERVICE', 'sort_order' => 40],
    ['cat' => 'FC-C5', 'code' => 'FC-S5-5', 'name' => 'AREA UMUM RESTORAN', 'sort_order' => 50],

    ['cat' => 'FC-C6', 'code' => 'FC-S6-1', 'name' => 'SERVICE EQUIPMENT', 'sort_order' => 10],
    ['cat' => 'FC-C7', 'code' => 'FC-S7-1', 'name' => 'CHEMICAL CONTROL', 'sort_order' => 10],
    ['cat' => 'FC-C8', 'code' => 'FC-S8-1', 'name' => 'PEST CONTROL', 'sort_order' => 10],
    ['cat' => 'FC-C9', 'code' => 'FC-S9-1', 'name' => 'PERSONAL HYGIENE', 'sort_order' => 10],
    ['cat' => 'FC-C10', 'code' => 'FC-S10-1', 'name' => 'EMERGENCY & SAFETY FACILITIES', 'sort_order' => 10],
    ['cat' => 'FC-C11', 'code' => 'FC-S11-1', 'name' => 'FOOD AND BEVERAGE QUALITY CHECK', 'sort_order' => 10],
];

$parameters = [
    ['sc' => 'FC-S1-1', 'code' => 'FCA-1.1.1', 'text' => 'Area receiving bersih dan produk diterima sesuai standar', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'FC-S1-1', 'code' => 'FCA-1.1.2', 'text' => 'Produk diterima sesuai FIFO/FEFO dan dokumen penerimaan lengkap', 'weight' => 10, 'sort_order' => 20],
    ['sc' => 'FC-S1-1', 'code' => 'FCA-1.1.3', 'text' => 'Pemeriksaan kualitas, kuantitas, label, suhu dan kemasan saat receiving', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'FC-S1-1', 'code' => 'FCA-1.1.4', 'text' => 'Penerapan FIFO/FEFO untuk bahan masuk', 'weight' => 30, 'sort_order' => 40],
    ['sc' => 'FC-S1-1', 'code' => 'FCA-1.1.5', 'text' => 'Cold chain terjaga saat receiving frozen/chilled product', 'weight' => 10, 'sort_order' => 50],
    ['sc' => 'FC-S1-1', 'code' => 'FCA-1.1.6', 'text' => 'Dokumen purchase/DO terdokumentasi benar', 'weight' => 10, 'sort_order' => 60],

    ['sc' => 'FC-S1-2', 'code' => 'FCA-1.2.1', 'text' => 'Suhu simpan sesuai standar jenis produk', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'FC-S1-2', 'code' => 'FCA-1.2.2', 'text' => 'Pemisahan penyimpanan antar jenis produk', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'FC-S1-2', 'code' => 'FCA-1.2.3', 'text' => 'Produk tidak langsung kontak lantai', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'FC-S1-2', 'code' => 'FCA-1.2.4', 'text' => 'Tidak ada kontaminasi silang di area storage', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'FC-S1-2', 'code' => 'FCA-1.2.5', 'text' => 'Freezer/chiller tertata dan tidak over-capacity', 'weight' => 10, 'sort_order' => 50],
    ['sc' => 'FC-S1-2', 'code' => 'FCA-1.2.6', 'text' => 'Penerapan FIFO/FEFO konsisten di storage', 'weight' => 30, 'sort_order' => 60],
    ['sc' => 'FC-S1-2', 'code' => 'FCA-1.2.7', 'text' => 'Label identifikasi bahan jelas dan lengkap', 'weight' => 30, 'sort_order' => 70],
    ['sc' => 'FC-S1-2', 'code' => 'FCA-1.2.8', 'text' => 'Tidak ada bahan rusak/expired/abnormal', 'weight' => 50, 'sort_order' => 80],
    ['sc' => 'FC-S1-2', 'code' => 'FCA-1.2.9', 'text' => 'Administrasi stock dan inventory terdokumentasi', 'weight' => 10, 'sort_order' => 90],

    ['sc' => 'FC-S1-3', 'code' => 'FCA-1.3.1', 'text' => 'Produk sesuai kualitas standar saat preparation', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S1-3', 'code' => 'FCA-1.3.2', 'text' => 'Tidak ada bahan expired/rusak dipakai', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'FC-S1-3', 'code' => 'FCA-1.3.3', 'text' => 'FIFO/FEFO diterapkan saat preparation', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'FC-S1-3', 'code' => 'FCA-1.3.4', 'text' => 'No cross contamination di prep area', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'FC-S1-3', 'code' => 'FCA-1.3.5', 'text' => 'Pemisahan bahan mentah/setengah jadi/matang benar', 'weight' => 30, 'sort_order' => 50],
    ['sc' => 'FC-S1-3', 'code' => 'FCA-1.3.6', 'text' => 'Produk diberi label dan tanggal prep/open', 'weight' => 30, 'sort_order' => 60],
    ['sc' => 'FC-S1-3', 'code' => 'FCA-1.3.7', 'text' => 'Proses prep sesuai SOP dan spesifikasi', 'weight' => 50, 'sort_order' => 70],

    ['sc' => 'FC-S1-4', 'code' => 'FCA-1.4.1', 'text' => 'Proses cooking sesuai SOP dan parameter waktu-suhu', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S1-4', 'code' => 'FCA-1.4.2', 'text' => 'Tidak ada bahan expired/rusak pada proses cooking', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'FC-S1-4', 'code' => 'FCA-1.4.3', 'text' => 'FIFO/FEFO diterapkan selama cooking', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'FC-S1-4', 'code' => 'FCA-1.4.4', 'text' => 'Tidak terjadi kontaminasi silang selama cooking', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'FC-S1-4', 'code' => 'FCA-1.4.5', 'text' => 'Pemisahan bahan mentah dan matang benar', 'weight' => 30, 'sort_order' => 50],

    ['sc' => 'FC-S1-5', 'code' => 'FCA-1.5.1', 'text' => 'Produk di holding area dijaga pada suhu aman', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S1-5', 'code' => 'FCA-1.5.2', 'text' => 'Tidak ada produk expired di holding area', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'FC-S1-5', 'code' => 'FCA-1.5.3', 'text' => 'FIFO/FEFO tetap berlaku di holding area', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'FC-S1-5', 'code' => 'FCA-1.5.4', 'text' => 'Tidak ada kontaminasi silang di holding area', 'weight' => 50, 'sort_order' => 40],

    ['sc' => 'FC-S2-1', 'code' => 'FCA-2.1.1', 'text' => 'Area produksi/preparation bersih, kering, bebas noda', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S2-1', 'code' => 'FCA-2.1.2', 'text' => 'Food contact surface disanitasi sesuai jadwal', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'FC-S2-1', 'code' => 'FCA-2.1.3', 'text' => 'Tidak ada penumpukan sampah/residu di area kerja', 'weight' => 50, 'sort_order' => 30],

    ['sc' => 'FC-S2-2', 'code' => 'FCA-2.2.1', 'text' => 'Area storage dapur tertata dan bersih', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S2-2', 'code' => 'FCA-2.2.2', 'text' => 'Chiller/freezer berfungsi baik dan termonitor', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'FC-S2-2', 'code' => 'FCA-2.2.3', 'text' => 'Produk disimpan terklasifikasi', 'weight' => 50, 'sort_order' => 30],

    ['sc' => 'FC-S2-3', 'code' => 'FCA-2.3.1', 'text' => 'Peralatan dapur dibersihkan setelah penggunaan', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S2-3', 'code' => 'FCA-2.3.2', 'text' => 'Penyimpanan alat sesuai area/label', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'FC-S2-3', 'code' => 'FCA-2.3.3', 'text' => 'Tidak ada alat rusak/retak/karat', 'weight' => 50, 'sort_order' => 30],

    ['sc' => 'FC-S2-4', 'code' => 'FCA-2.4.1', 'text' => 'Area utility bersih dan aman', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'FC-S2-4', 'code' => 'FCA-2.4.2', 'text' => 'Sabun cuci tangan/tissue/sanitizer tersedia', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'FC-S2-4', 'code' => 'FCA-2.4.3', 'text' => 'Drainase lancar dan tidak berbau', 'weight' => 30, 'sort_order' => 30],

    ['sc' => 'FC-S2-5', 'code' => 'FCA-2.5.1', 'text' => 'Food handling equipment bersih dan layak pakai', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S2-6', 'code' => 'FCA-2.6.1', 'text' => 'Cooking equipment bersih dan siap operasi', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S2-7', 'code' => 'FCA-2.7.1', 'text' => 'Lantai kitchen bersih, tidak licin, tidak ada genangan', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S2-8', 'code' => 'FCA-2.8.1', 'text' => 'Ceiling kitchen bersih, tidak berjamur, tidak bocor', 'weight' => 30, 'sort_order' => 10],

    ['sc' => 'FC-S3-1', 'code' => 'FCA-3.1', 'text' => 'Menyambut tamu sesuai standar greeting', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S3-1', 'code' => 'FCA-3.2', 'text' => 'Mengarahkan dan informasikan pilihan menu', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'FC-S3-1', 'code' => 'FCA-3.3', 'text' => 'Menanyakan member Justus', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'FC-S3-1', 'code' => 'FCA-3.4', 'text' => 'Menawarkan penggunaan poin member', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'FC-S3-1', 'code' => 'FCA-3.5', 'text' => 'Menawarkan join member Justus', 'weight' => 50, 'sort_order' => 50],
    ['sc' => 'FC-S3-1', 'code' => 'FCA-3.6', 'text' => 'Informasi promo dan menu signature', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'FC-S3-1', 'code' => 'FCA-3.7', 'text' => 'Taking order ramah dan lengkap', 'weight' => 50, 'sort_order' => 70],
    ['sc' => 'FC-S3-1', 'code' => 'FCA-3.8', 'text' => 'Input pesanan ke POS akurat', 'weight' => 50, 'sort_order' => 80],
    ['sc' => 'FC-S3-1', 'code' => 'FCA-3.9', 'text' => 'Suggestive selling / upselling', 'weight' => 30, 'sort_order' => 90],
    ['sc' => 'FC-S3-1', 'code' => 'FCA-3.10', 'text' => 'Repeat order untuk konfirmasi', 'weight' => 50, 'sort_order' => 100],

    ['sc' => 'FC-S4-1', 'code' => 'FCA-4.1', 'text' => 'Penampilan dan grooming sesuai standar', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S4-1', 'code' => 'FCA-4.2', 'text' => 'Keramahan saat menyambut', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'FC-S4-1', 'code' => 'FCA-4.3', 'text' => 'Antusiasme pelayanan', 'weight' => 50, 'sort_order' => 30],

    ['sc' => 'FC-S5-1', 'code' => 'FCA-5.1.1', 'text' => 'Lantai area tamu bersih', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S5-2', 'code' => 'FCA-5.2.1', 'text' => 'Area cashier bersih dan tertata', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S5-3', 'code' => 'FCA-5.3.1', 'text' => 'Area service station bersih', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S5-4', 'code' => 'FCA-5.4.1', 'text' => 'Penyimpanan perlengkapan service bersih', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S5-5', 'code' => 'FCA-5.5.1', 'text' => 'Area umum restoran bersih', 'weight' => 50, 'sort_order' => 10],

    ['sc' => 'FC-S6-1', 'code' => 'FCA-6.1', 'text' => 'Serving equipment tersedia dan siap pakai', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'FC-S6-1', 'code' => 'FCA-6.2', 'text' => 'Serving equipment bersih dan disanitasi', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'FC-S6-1', 'code' => 'FCA-6.3', 'text' => 'Tidak ada serving equipment rusak', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'FC-S6-1', 'code' => 'FCA-6.4', 'text' => 'Penyimpanan serving equipment benar', 'weight' => 10, 'sort_order' => 40],

    ['sc' => 'FC-S7-1', 'code' => 'FCA-7.1', 'text' => 'Bahan kimia disimpan aman dan terpisah', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'FC-S7-1', 'code' => 'FCA-7.2', 'text' => 'MSDS/SDS tersedia dan up to date', 'weight' => 10, 'sort_order' => 20],
    ['sc' => 'FC-S7-1', 'code' => 'FCA-7.3', 'text' => 'Label bahan kimia lengkap', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'FC-S7-1', 'code' => 'FCA-7.4', 'text' => 'Pengenceran sesuai instruksi', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'FC-S7-1', 'code' => 'FCA-7.5', 'text' => 'Prosedur spill kit tersedia', 'weight' => 10, 'sort_order' => 50],

    ['sc' => 'FC-S8-1', 'code' => 'FCA-8.1', 'text' => 'Program pest control sesuai jadwal', 'weight' => 10, 'sort_order' => 10],
    ['sc' => 'FC-S8-1', 'code' => 'FCA-8.2', 'text' => 'Tidak ada indikasi aktivitas hama', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'FC-S8-1', 'code' => 'FCA-8.3', 'text' => 'Tidak ada celah akses hama', 'weight' => 10, 'sort_order' => 30],
    ['sc' => 'FC-S8-1', 'code' => 'FCA-8.4', 'text' => 'Peralatan pest control terpasang dan tercatat', 'weight' => 30, 'sort_order' => 40],
    ['sc' => 'FC-S8-1', 'code' => 'FCA-8.5', 'text' => 'Perimeter luar bersih dari sumber hama', 'weight' => 30, 'sort_order' => 50],

    ['sc' => 'FC-S9-1', 'code' => 'FCA-9.1', 'text' => 'Seragam dan APD sesuai standar', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S9-1', 'code' => 'FCA-9.2', 'text' => 'Kebersihan badan sesuai aturan', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'FC-S9-1', 'code' => 'FCA-9.3', 'text' => 'Rambut sesuai ketentuan grooming', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'FC-S9-1', 'code' => 'FCA-9.4', 'text' => 'Tidak ada aksesoris/kosmetik terlarang', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'FC-S9-1', 'code' => 'FCA-9.5', 'text' => 'Kuku pendek bersih tanpa kuteks', 'weight' => 50, 'sort_order' => 50],

    ['sc' => 'FC-S10-1', 'code' => 'FCA-10.1', 'text' => 'Kotak P3K lengkap dan valid', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'FC-S10-1', 'code' => 'FCA-10.2', 'text' => 'Lampu emergency berfungsi', 'weight' => 10, 'sort_order' => 20],

    ['sc' => 'FC-S11-1', 'code' => 'FCA-11.1', 'text' => 'Produk sesuai recipe, porsi, dan spesifikasi', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'FC-S11-1', 'code' => 'FCA-11.2', 'text' => 'Presentasi produk konsisten', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'FC-S11-1', 'code' => 'FCA-11.3', 'text' => 'Suhu produk saat disajikan sesuai standar', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'FC-S11-1', 'code' => 'FCA-11.4', 'text' => 'Holding time dan masa simpan dipatuhi', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'FC-S11-1', 'code' => 'FCA-11.5', 'text' => 'Produk bebas kontaminasi', 'weight' => 50, 'sort_order' => 50],
    ['sc' => 'FC-S11-1', 'code' => 'FCA-11.6', 'text' => 'Serving equipment mendukung kualitas sajian', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'FC-S11-1', 'code' => 'FCA-11.7', 'text' => 'Service time sesuai standar', 'weight' => 30, 'sort_order' => 70],
];

$appendParam = static function (array &$list, string $sc, string $code, string $text, int $weight, int $sortOrder): void {
    $list[] = [
        'sc' => $sc,
        'code' => $code,
        'text' => $text,
        'weight' => $weight,
        'sort_order' => $sortOrder,
    ];
};

// Additional items from latest audit form (real wording, no placeholder text)
$appendParam($parameters, 'FC-S1-3', 'FCA-1.3.8', 'Tidak menggunakan peralatan yang sama untuk produk mentah dan matang, wajib pemisahan kode warna cutting board/lap.', 30, 80);
$appendParam($parameters, 'FC-S1-3', 'FCA-1.3.9', 'Proses thawing produk beku dilakukan sesuai metode aman untuk menjaga keamanan pangan.', 30, 90);
$appendParam($parameters, 'FC-S1-3', 'FCA-1.3.10', 'Minyak goreng dipantau kualitasnya dan diganti sebelum menurun kualitas.', 30, 100);
$appendParam($parameters, 'FC-S1-3', 'FCA-1.3.11', 'Peralatan pengendali suhu panas berfungsi baik dan setting temperatur/waktu sesuai standar.', 30, 110);
$appendParam($parameters, 'FC-S1-3', 'FCA-1.3.12', 'Utensil preparation bersih, layak pakai, bebas karat/retak/pecah.', 30, 120);

$appendParam($parameters, 'FC-S1-4', 'FCA-1.4.6', 'Prepared product dan item open wajib memiliki label identifikasi lengkap (nama item, tanggal, jam, shelf life).', 30, 60);
$appendParam($parameters, 'FC-S1-4', 'FCA-1.4.7', 'Seluruh produk dipersiapkan sesuai SOP, IK, shelf life, dan spesifikasi produk.', 50, 70);
$appendParam($parameters, 'FC-S1-4', 'FCA-1.4.8', 'Pemisahan peralatan mentah dan matang, termasuk kode warna cutting board dan lap, diterapkan konsisten.', 30, 80);
$appendParam($parameters, 'FC-S1-4', 'FCA-1.4.9', 'Proses thawing dijalankan sesuai metode yang ditetapkan.', 30, 90);
$appendParam($parameters, 'FC-S1-4', 'FCA-1.4.10', 'Kualitas minyak goreng dipantau dan diganti sebelum menghitam/menurun.', 30, 100);
$appendParam($parameters, 'FC-S1-4', 'FCA-1.4.11', 'Peralatan pengendali suhu panas (bain marie/fryer/oven/microwave) berfungsi baik.', 30, 110);
$appendParam($parameters, 'FC-S1-4', 'FCA-1.4.12', 'Utensil preparation dalam kondisi bersih, terawat, dan layak pakai.', 30, 120);
$appendParam($parameters, 'FC-S1-4', 'FCA-1.4.13', 'Seluruh proses cooking dan quality control didokumentasikan sesuai SOP yang berlaku.', 30, 130);

$appendParam($parameters, 'FC-S1-5', 'FCA-1.5.5', 'Produk disajikan sesuai standar penampilan (appearance), warna, garnish, dan tanpa benda asing.', 50, 50);
$appendParam($parameters, 'FC-S1-5', 'FCA-1.5.6', 'Produk terlindung dari kontaminasi silang, tidak kontak langsung tangan pada makanan siap saji.', 50, 60);
$appendParam($parameters, 'FC-S1-5', 'FCA-1.5.7', 'Penyajian produk sesuai pesanan dan permintaan tamu.', 50, 70);
$appendParam($parameters, 'FC-S1-5', 'FCA-1.5.8', 'Peralatan saji dalam kondisi bersih dan layak pakai.', 50, 80);

$appendParam($parameters, 'FC-S2-3', 'FCA-2.3.4', 'Pencucian alat saji manual dilakukan sesuai standar: pre-rinse, wash, rinse, sanitize, dan treatment alat tertentu.', 50, 40);
$appendParam($parameters, 'FC-S2-3', 'FCA-2.3.5', 'Tidak ada sisa makanan/minuman masuk ke sink sebelum proses pencucian.', 30, 50);
$appendParam($parameters, 'FC-S2-3', 'FCA-2.3.6', 'Volume air sink tidak melebihi batas yang ditetapkan untuk efektivitas pencucian.', 30, 60);
$appendParam($parameters, 'FC-S2-3', 'FCA-2.3.7', 'Sponge pencucian dipisah sesuai peruntukan dan dijaga kebersihannya.', 50, 70);
$appendParam($parameters, 'FC-S2-3', 'FCA-2.3.8', 'Hasil pencucian alat saji bersih, tidak berkerak, bebas noda/baunya normal.', 50, 80);

$appendParam($parameters, 'FC-S2-5', 'FCA-2.5.2', 'Utensil dibersihkan dengan metode cleaning/sanitasi SOP, bebas sisa makanan/noda/kerak.', 30, 20);
$appendParam($parameters, 'FC-S2-5', 'FCA-2.5.3', 'Utensil disimpan bersih, kering, tertata, dan terlindung dari debu/percikan/hama.', 10, 30);

$appendParam($parameters, 'FC-S2-6', 'FCA-2.6.2', 'Dinding area kerja bersih, bebas noda/cipratan minyak/debu/jamur/hama.', 30, 20);
$appendParam($parameters, 'FC-S2-6', 'FCA-2.6.3', 'Plafon dalam kondisi bersih, tidak ada kebocoran atau kerusakan pencemar area produksi.', 50, 30);
$appendParam($parameters, 'FC-S2-6', 'FCA-2.6.4', 'Pintu area kerja bersih, berfungsi baik, dan dapat menutup sempurna.', 30, 40);
$appendParam($parameters, 'FC-S2-6', 'FCA-2.6.5', 'Exhaust hood/filter/fresh air bersih dan berfungsi untuk sirkulasi udara.', 30, 50);
$appendParam($parameters, 'FC-S2-6', 'FCA-2.6.6', 'Saluran pembuangan (drainase) bersih, tidak tersumbat, tidak menimbulkan genangan.', 50, 60);
$appendParam($parameters, 'FC-S2-6', 'FCA-2.6.7', 'Lampu area kitchen/steward memiliki cover lamp dan berfungsi baik.', 30, 70);

$appendParam($parameters, 'FC-S2-7', 'FCA-2.7.2', 'Prosedur sanitasi terdokumentasi dan dilaksanakan sesuai konsentrasi/metode/contact time/frekuensi.', 10, 20);
$appendParam($parameters, 'FC-S2-7', 'FCA-2.7.3', 'Seluruh alat kebersihan bersih, terawat, teridentifikasi, dan disimpan rapi.', 30, 30);
$appendParam($parameters, 'FC-S2-7', 'FCA-2.7.4', 'Cleaning cloth bersih dan diganti berkala (opening/during/closing) untuk cegah kontaminasi silang.', 50, 40);
$appendParam($parameters, 'FC-S2-7', 'FCA-2.7.5', 'Buah/sayur/telur dicuci-disanitasi sesuai SOP dan konsentrasi food grade yang ditetapkan.', 30, 50);

$appendParam($parameters, 'FC-S3-1', 'FCA-3.11', 'MENANYAKAN ID MEMBER JUSTUS: Staff meminta nomor ID member untuk proses transaksi.', 50, 110);
$appendParam($parameters, 'FC-S3-1', 'FCA-3.12', 'MENGINFORMASIKAN TOTAL TAGIHAN: Staff menyampaikan nominal pembayaran dengan jelas.', 50, 120);
$appendParam($parameters, 'FC-S3-1', 'FCA-3.13', 'MENERIMA PEMBAYARAN: Pembayaran diproses sesuai metode yang dipilih tamu.', 50, 130);
$appendParam($parameters, 'FC-S3-1', 'FCA-3.14', 'MEMBERIKAN BILL SETTLEMENT: Struk pembayaran diserahkan lengkap dan benar.', 30, 140);
$appendParam($parameters, 'FC-S3-1', 'FCA-3.15', 'MENGINFORMASIKAN WAKTU PENYAJIAN: Estimasi waktu penyajian disampaikan sesuai standar.', 50, 150);
$appendParam($parameters, 'FC-S3-1', 'FCA-3.16', 'MEMBERIKAN WIRELESS PAGING: perangkat aktif, bersih, disertai penjelasan penggunaan.', 50, 160);
$appendParam($parameters, 'FC-S3-1', 'FCA-3.17', 'MEMPERSIAPKAN & SET UP PESANAN: pesanan disiapkan sesuai standar dan kelengkapan alat saji.', 50, 170);
$appendParam($parameters, 'FC-S3-1', 'FCA-3.18', 'MENYAJIKAN PESANAN TAMU: pesanan disajikan sopan, ramah, dan tepat.', 50, 180);
$appendParam($parameters, 'FC-S3-1', 'FCA-3.19', 'MENGINFORMASIKAN PENANGANAN TINGKAT KEMATANGAN STEAK jika tidak sesuai.', 50, 190);
$appendParam($parameters, 'FC-S3-1', 'FCA-3.20', 'MEMPERSILAHKAN TAMU MENIKMATI HIDANGAN dengan kalimat standar.', 50, 200);
$appendParam($parameters, 'FC-S3-1', 'FCA-3.21', 'MENGUCAPKAN TERIMA KASIH & MENGUNDANG TAMU KEMBALI saat tamu meninggalkan outlet.', 50, 210);
$appendParam($parameters, 'FC-S3-1', 'FCA-3.22', 'MEMBERSIHKAN & SET UP MEJA KEMBALI maksimal 3 menit setelah tamu meninggalkan meja.', 50, 220);

$appendParam($parameters, 'FC-S4-1', 'FCA-4.4', 'BAHASA TUBUH: Berdiri tegap, tidak melipat tangan, tidak menunjukkan ekspresi menyimpang.', 50, 40);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.5', 'PENGGUNAAN BAHASA SOPAN: Menggunakan kata santun, profesional, mudah dipahami.', 50, 50);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.6', 'KEMAMPUAN MENDENGARKAN: mendengar kebutuhan tamu tanpa memotong pembicaraan.', 50, 60);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.7', 'KONTAK MATA: Melakukan kontak mata yang wajar saat berinteraksi dengan tamu.', 50, 70);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.8', 'PENGGUNAAN NAMA TAMU bila diketahui untuk personalisasi layanan.', 50, 80);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.9', 'RESPONSIVITAS: Tanggap panggilan/permintaan tamu maksimal 1 menit.', 50, 90);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.10', 'INISIATIF MEMBANTU: Proaktif menawarkan bantuan tanpa diminta.', 30, 100);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.11', 'PRODUCT KNOWLEDGE: Memahami menu, promo, dan program member dengan benar.', 50, 110);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.12', 'KEMAMPUAN MENJAWAB PERTANYAAN: Informasi akurat dan tidak asumsi.', 50, 120);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.13', 'MEMAHAMI KEBUTUHAN TAMU: mampu identifikasi preferensi/kebutuhan khusus.', 30, 130);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.14', 'PENANGANAN TAMU ANAK-ANAK: menunjukkan perhatian dan keramahan.', 50, 140);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.15', 'PENANGANAN TAMU LANSIA/DISABILITAS: bantuan tambahan sesuai kebutuhan secara hormat.', 50, 150);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.16', 'EMPATI TERHADAP TAMU: menunjukkan kepedulian atas kondisi/situasi tamu.', 50, 160);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.17', 'KECEPATAN PELAYANAN: layanan diberikan sesuai standar waktu.', 50, 170);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.18', 'AKURASI PELAYANAN: tidak terjadi kesalahan informasi/pesanan/transaksi.', 50, 180);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.19', 'PENANGANAN KELUHAN: ditangani tenang, empati, minta maaf, dan cari solusi.', 50, 190);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.20', 'KONSISTENSI PELAYANAN: standar layanan diberikan ke seluruh tamu tanpa diskriminasi.', 50, 200);
$appendParam($parameters, 'FC-S4-1', 'FCA-4.21', 'PERHATIAN TERHADAP DETAIL: kebersihan meja, kelengkapan alat saji, kenyamanan tamu.', 50, 210);

$appendParam($parameters, 'FC-S5-1', 'FCA-5.1.2', 'Kondisi meja makan bersih, kering, bebas noda, tidak lengket, tidak rusak, tidak goyang.', 50, 20);
$appendParam($parameters, 'FC-S5-1', 'FCA-5.1.3', 'Kondisi kursi tamu bersih, stabil, tidak rusak, bebas debu dan noda.', 50, 30);
$appendParam($parameters, 'FC-S5-1', 'FCA-5.1.4', 'Kebersihan sofa: bebas debu, noda, remah makanan, tidak berbau.', 50, 40);
$appendParam($parameters, 'FC-S5-1', 'FCA-5.1.5', 'Kebersihan condiment set & tent card: bersih, terisi sesuai standar, tertata rapi.', 50, 50);
$appendParam($parameters, 'FC-S5-1', 'FCA-5.1.6', 'Kebersihan buku menu: bersih, tidak sobek/berminyak, mudah dibaca.', 50, 60);
$appendParam($parameters, 'FC-S5-1', 'FCA-5.1.7', 'Kebersihan area bawah meja: bebas sampah, debu, sisa makanan.', 50, 70);
$appendParam($parameters, 'FC-S5-1', 'FCA-5.1.8', 'Kebersihan dekorasi: bebas debu, sarang laba-laba, noda.', 30, 80);
$appendParam($parameters, 'FC-S5-1', 'FCA-5.1.9', 'Kebersihan kaca/jendela: bersih, tidak buram, bebas sidik jari dan noda.', 50, 90);
$appendParam($parameters, 'FC-S5-1', 'FCA-5.1.10', 'Kebersihan pintu masuk: bersih, tidak ada bekas tangan berlebihan/kotoran.', 30, 100);

$appendParam($parameters, 'FC-S5-2', 'FCA-5.2.2', 'Kebersihan mesin EDC/POS: bersih, berfungsi baik, bebas debu.', 50, 20);
$appendParam($parameters, 'FC-S5-2', 'FCA-5.2.3', 'Tempat promosi/display bersih, tertata, dan tidak rusak.', 50, 30);

$appendParam($parameters, 'FC-S5-3', 'FCA-5.3.2', 'Kerapihan penyimpanan alat saji: tersimpan rapi sesuai lokasi dan terlindung debu.', 30, 20);
$appendParam($parameters, 'FC-S5-3', 'FCA-5.3.3', 'Kebersihan alat saji: bebas noda/debu/sidik jari/karat/bau.', 50, 30);
$appendParam($parameters, 'FC-S5-3', 'FCA-5.3.4', 'Kebersihan tray dan alat penunjang lainnya: bersih, bebas noda/debu, kondisi baik.', 50, 40);

$appendParam($parameters, 'FC-S5-4', 'FCA-5.4.2', 'Kebersihan rak penyimpanan: rak bersih, tidak berdebu, tidak berkarat, tertata.', 50, 20);
$appendParam($parameters, 'FC-S5-4', 'FCA-5.4.3', 'Kondisi stok: tidak ada stok rusak, kadaluarsa, atau tidak layak pakai.', 50, 30);

$appendParam($parameters, 'FC-S5-5', 'FCA-5.5.2', 'Kebersihan plafon: tidak terdapat debu, noda bocor, atau sarang laba-laba.', 50, 20);
$appendParam($parameters, 'FC-S5-5', 'FCA-5.5.3', 'Kebersihan lampu: bersih, berfungsi, tidak terdapat serangga mati.', 50, 30);
$appendParam($parameters, 'FC-S5-5', 'FCA-5.5.4', 'Kebersihan AC dan diffuser: bersih, tidak berdebu, tidak menetes.', 30, 40);
$appendParam($parameters, 'FC-S5-5', 'FCA-5.5.5', 'Kebersihan tanaman: bersih dan terawat.', 30, 50);
$appendParam($parameters, 'FC-S5-5', 'FCA-5.5.6', 'Kebersihan tempat sampah: bersih, tertutup, tidak penuh, menggunakan liner.', 50, 60);

$appendParam($parameters, 'FC-S9-1', 'FCA-9.6', 'Kuku tangan dipotong pendek, bersih, terawat, tidak memakai kutek/kuku palsu.', 50, 60);
$appendParam($parameters, 'FC-S9-1', 'FCA-9.7', 'Karyawan menggunakan seragam kerja lengkap sesuai standar perusahaan.', 50, 70);
$appendParam($parameters, 'FC-S9-1', 'FCA-9.8', 'Karyawan wajib melepas/tidak menggunakan APD tertentu saat keluar area produksi.', 30, 80);
$appendParam($parameters, 'FC-S9-1', 'FCA-9.9', 'Tidak melakukan tindakan yang memicu kontaminasi silang saat handling produk.', 50, 90);
$appendParam($parameters, 'FC-S9-1', 'FCA-9.10', 'Luka terbuka wajib ditutup waterproof dressing dan tidak dibiarkan terbuka.', 50, 100);
$appendParam($parameters, 'FC-S9-1', 'FCA-9.11', 'Dilarang makan/minum/permen karet/merokok di area kerja operasional.', 50, 110);
$appendParam($parameters, 'FC-S9-1', 'FCA-9.12', 'Karyawan tidak diperbolehkan konsumsi makanan/minuman sisa tamu.', 50, 120);
$appendParam($parameters, 'FC-S9-1', 'FCA-9.13', 'Barang pribadi disimpan pada area/loker yang disediakan, tidak ditinggal di area operasional.', 30, 130);
$appendParam($parameters, 'FC-S9-1', 'FCA-9.14', 'Ketersediaan hand glove sekali pakai, hair net, dan masker memadai.', 30, 140);
$appendParam($parameters, 'FC-S9-1', 'FCA-9.15', 'Fasilitas karyawan (loker, toilet, mushola, area istirahat) bersih, rapi, terawat.', 30, 150);
$appendParam($parameters, 'FC-S9-1', 'FCA-9.16', 'Karyawan melakukan cuci tangan sesuai SOP pada waktu-waktu kritis.', 30, 160);

// Guarantee unique codes to keep script idempotent.
$parameterMap = [];
foreach ($parameters as $row) {
    $parameterMap[$row['code']] = $row;
}
$parameters = array_values($parameterMap);

DB::beginTransaction();
$seededCount = 0;

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

    foreach ($categories as $row) {
        DB::table('qa2_categories')->updateOrInsert(
            ['code' => $row['code']],
            [
                'name' => $row['name'],
                'status' => 'A',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    $categoryIds = DB::table('qa2_categories')->pluck('id', 'code')->all();

    foreach ($subcategories as $row) {
        DB::table('qa2_subcategories')->updateOrInsert(
            ['code' => $row['code']],
            [
                'category_id' => $categoryIds[$row['cat']],
                'name' => $row['name'],
                'sort_order' => $row['sort_order'],
                'status' => 'A',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    $subcategoryIds = DB::table('qa2_subcategories')->pluck('id', 'code')->all();

    $templateId = DB::table('qa2_templates')
        ->where('code', 'FCA')
        ->where('version', 1)
        ->value('id');

    $parameterCodes = array_column($parameters, 'code');
    DB::table('qa2_template_items')
        ->where('template_id', $templateId)
        ->whereIn('parameter_id', function ($query) use ($parameterCodes) {
            $query->select('id')->from('qa2_parameters')->whereIn('code', $parameterCodes);
        })
        ->delete();

    foreach ($parameters as $row) {
        DB::table('qa2_parameters')->updateOrInsert(
            ['code' => $row['code']],
            [
                'subcategory_id' => $subcategoryIds[$row['sc']],
                'parameter_text' => $row['text'],
                'weight' => $row['weight'],
                'sort_order' => $row['sort_order'],
                'status' => 'A',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    $orderedParams = DB::table('qa2_parameters as p')
        ->join('qa2_subcategories as sc', 'sc.id', '=', 'p.subcategory_id')
        ->join('qa2_categories as c', 'c.id', '=', 'sc.category_id')
        ->whereIn('p.code', $parameterCodes)
        ->orderBy('c.id')
        ->orderBy('sc.sort_order')
        ->orderBy('p.sort_order')
        ->select('p.id')
        ->get();

    $sort = 1;
    foreach ($orderedParams as $param) {
        DB::table('qa2_template_items')->updateOrInsert(
            ['template_id' => $templateId, 'parameter_id' => $param->id],
            [
                'sort_order' => $sort++,
                'is_required' => 1,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    $seededCount = DB::table('qa2_template_items')
        ->where('template_id', $templateId)
        ->count();

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

echo "Food Court Audit seed completed. Total template items: {$seededCount}" . PHP_EOL;
