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
    echo "Untuk commit ke database, jalankan: php scripts/seed_building_safety_equipment_outlet.php --apply --yes" . PHP_EOL;
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
    'code' => 'BSEOUT',
    'name' => 'Building, Safety, Equipment Outlet',
    'audit_type' => 'Facility Evaluation',
    'department' => 'Engineering',
    'version' => 1,
    'status' => 'A',
    'notes' => 'Seed from Building, Safety, Equipment Outlet template screenshots (updated Jun 2026)',
];

$categories = [
    ['code' => 'BSEOUT-C1', 'name' => 'INFRASTRUCTURE', 'sort_order' => 10],
    ['code' => 'BSEOUT-C2', 'name' => 'KITCHEN & BAR EQUIPMENT', 'sort_order' => 20],
];

$subcategories = [
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-1', 'name' => 'FACADE', 'sort_order' => 10],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-2', 'name' => 'AREA KERJA (SERVICE, KITCHEN, BAR, STEWARD, STORE & AREA KERJA LAINNYA)', 'sort_order' => 20],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-3', 'name' => 'SUPPLY AIR & PLUMBING', 'sort_order' => 30],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-4', 'name' => 'SISTEM KELISTRIKAN', 'sort_order' => 40],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-5', 'name' => 'SIRKULASI UDARA', 'sort_order' => 50],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-6', 'name' => 'DRAINAGE SYSTEM', 'sort_order' => 60],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-7', 'name' => 'PROGRAM PREVENTIVE MAINTENANCE (PMT)', 'sort_order' => 70],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-8', 'name' => 'K3 & EMERGENCY SITUATION', 'sort_order' => 80],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-9', 'name' => 'STRUKTUR BANGUNAN & KELAYAKAN INFRASTRUKTUR', 'sort_order' => 90],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-10', 'name' => 'FIRE PROTECTION SYSTEM', 'sort_order' => 100],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-11', 'name' => 'GAS SAFETY SYSTEM', 'sort_order' => 110],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-12', 'name' => 'GREASE MANAGEMENT', 'sort_order' => 120],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-13', 'name' => 'POTABLE WATER SAFETY', 'sort_order' => 130],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-14', 'name' => 'ROOF & WATER LEAKAGE CONTROL', 'sort_order' => 140],
    ['cat' => 'BSEOUT-C1', 'code' => 'BSEOUT-S1-15', 'name' => 'FURNITURE & FIXTURE', 'sort_order' => 150],

    ['cat' => 'BSEOUT-C2', 'code' => 'BSEOUT-S2-1', 'name' => 'KELAYAKAN & FUNGSI EQUIPMENT PEMANAS', 'sort_order' => 10],
    ['cat' => 'BSEOUT-C2', 'code' => 'BSEOUT-S2-2', 'name' => 'KELAYAKAN & FUNGSI EQUIPMENT PENDINGIN', 'sort_order' => 20],
    ['cat' => 'BSEOUT-C2', 'code' => 'BSEOUT-S2-3', 'name' => 'TEMPERATURE & KINERJA', 'sort_order' => 30],
    ['cat' => 'BSEOUT-C2', 'code' => 'BSEOUT-S2-4', 'name' => 'PREVENTIVE MAINTENANCE PROGRAM', 'sort_order' => 40],
    ['cat' => 'BSEOUT-C2', 'code' => 'BSEOUT-S2-5', 'name' => 'INSPEKSI KELAYAKAN (FITNESS ASSESSMENT)', 'sort_order' => 50],
    ['cat' => 'BSEOUT-C2', 'code' => 'BSEOUT-S2-6', 'name' => 'MONITORING BREAKDOWN & CORRECTIVE MAINTENANCE', 'sort_order' => 60],
    ['cat' => 'BSEOUT-C2', 'code' => 'BSEOUT-S2-7', 'name' => 'EVALUASI UMUR PAKAI & PEREMAJAAN EQUIPMENT', 'sort_order' => 70],
    ['cat' => 'BSEOUT-C2', 'code' => 'BSEOUT-S2-8', 'name' => 'EVALUASI KEBUTUHAN PENAMBAHAN EQUIPMENT', 'sort_order' => 80],
    ['cat' => 'BSEOUT-C2', 'code' => 'BSEOUT-S2-9', 'name' => 'KALIBRASI EQUIPMENT', 'sort_order' => 90],
    ['cat' => 'BSEOUT-C2', 'code' => 'BSEOUT-S2-10', 'name' => 'DOKUMENTASI & ASSET MANAGEMENT', 'sort_order' => 100],
];

$parameters = [
    ['sc' => 'BSEOUT-S1-1', 'code' => 'BSEOUT-1.1.1', 'text' => 'Fasad bangunan dalam kondisi bersih, terawat, aman, dan representatif. Tidak terdapat kerusakan seperti retak, cat mengelupas, kebocoran, korosi, kaca pecah, huruf signage rusak / Mati maupun kondisi lain yang dapat mempengaruhi citra perusahaan dan keselamatan.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-2', 'code' => 'BSEOUT-1.2.1', 'text' => 'Area kerja dalam kondisi layak fungsi, aman, bersih, dan mendukung kelancaran operasional. Seluruh elemen bangunan seperti lantai, dinding, plafon, pintu, meja kerja, dan fasilitas pendukung tidak mengalami kerusakan yang dapat mengganggu produktivitas maupun keamanan pangan.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-3', 'code' => 'BSEOUT-1.3.1', 'text' => 'Sistem penyediaan air dan plumbing berfungsi dengan baik untuk mendukung operasional. Ketersediaan air bersih mencukupi, tekanan air memadai, serta tidak terdapat kebocoran maupun gangguan pada sistem distribusi.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-4', 'code' => 'BSEOUT-1.4.1', 'text' => 'Instalasi listrik berada dalam kondisi aman, layak fungsi, dan mampu mendukung seluruh kebutuhan operasional tanpa menimbulkan risiko keselamatan. Panel listrik bersih dan tertutup, MCB berfungsi baik, Tidak terdapat kabel terbuka, Stop kontak dalam kondisi aman, Beban listrik sesuai kapasitas, Genset berfungsi (jika tersedia).', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-5', 'code' => 'BSEOUT-1.5.1', 'text' => 'Sistem sirkulasi udara berfungsi dengan baik untuk menjaga kenyamanan kerja, mengendalikan suhu ruangan, mengurangi panas, asap, kelembaban, dan menjaga kualitas udara di area operasional. Exhaust Hood berfungsi, Fresh air system berfungsi, AC beroperasi optimal, Tidak terdapat bau menyengat, Filter bersih, Suhu ruangan sesuai standar.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-6', 'code' => 'BSEOUT-1.6.1', 'text' => 'Sistem drainase berfungsi dengan baik, tidak tersumbat, tidak menyebabkan genangan, serta mampu mengalirkan limbah sesuai kapasitas yang dibutuhkan.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-7', 'code' => 'BSEOUT-1.7.1', 'text' => 'PMT tersedia, dilaksanakan sesuai jadwal, dan terdokumentasi untuk memastikan seluruh fasilitas dan utilitas bangunan berfungsi optimal. Guide Line : 1. Kitchen Hood dan filter. 2. Air Conditioner (AC). 3. Main Grease Trap. 4. Toren/Tangki Air. 5. Exhaust Fan. 6. Fresh Air Unit. 7. Pompa air. 8. Panel listrik. 9. Lampu emergency. 10. Genset (jika tersedia).', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-8', 'code' => 'BSEOUT-1.8.1', 'text' => 'Fasilitas K3 dan tanggap darurat tersedia, berfungsi dengan baik, mudah diakses, serta seluruh karyawan memahami prosedur keadaan darurat. Guide Line : 1. APAR tersedia dan tidak kedaluwarsa. 2. Emergency exit tersedia. 3. Lampu emergency berfungsi. 4. Kotak P3K lengkap. 5. Jalur evakuasi bebas hambatan. 6. Assembly point tersedia. 7. Karyawan memahami tindakan saat kebakaran dan gempa bumi. 8. Simulasi keadaan darurat dilakukan secara berkala.', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-9', 'code' => 'BSEOUT-1.9.1', 'text' => 'Struktur bangunan dalam kondisi aman, stabil, dan layak digunakan. Tidak ditemukan indikasi kerusakan yang dapat mempengaruhi keselamatan penghuni maupun kelangsungan operasional. Guide Line : 1. Tidak terdapat retak struktural. 2. Tidak terdapat penurunan lantai. 3. Tidak terdapat kebocoran atap. 4. Tangga dan handrail aman digunakan. 5. Area parkir dan akses keluar masuk layak. 6. Tidak terdapat potensi bahaya akibat kerusakan bangunan.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-10', 'code' => 'BSEOUT-1.10.1', 'text' => 'Seluruh sistem proteksi kebakaran tersedia, berfungsi dengan baik, mudah diakses, dan dilakukan pemeriksaan secara berkala sesuai ketentuan yang berlaku. Guide Line : 1. APAR tersedia sesuai jenis risiko dan kapasitas. 2. APAR tidak kedaluwarsa dan tekanan normal. 3. Fire blanket tersedia di area kitchen. 4. Tersedia checklist inspeksi rutin.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-11', 'code' => 'BSEOUT-1.11.1', 'text' => 'Sistem instalasi gas aman, bebas kebocoran, dan dilengkapi dengan fasilitas pengamanan untuk mencegah terjadinya kebakaran atau ledakan. Guide Line : 1. Tidak terdapat kebocoran gas. 2. Regulator dan selang dalam kondisi baik. 3. Tanggal kedaluwarsa selang masih berlaku. 4. Tersedia detektor kebocoran gas. 5. Katup shut-off mudah dijangkau. 6. Ruang gas memiliki ventilasi memadai. 7. Tidak digunakan sebagai area penyimpanan barang lain.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-12', 'code' => 'BSEOUT-1.12.1', 'text' => 'Sistem pengelolaan lemak berfungsi optimal untuk mencegah penyumbatan, bau, dan risiko pencemaran lingkungan. Guide Line : 1. Main grease trap dibersihkan sesuai jadwal. 2. Tidak terjadi overflow. 3. Tidak menimbulkan bau menyengat. 4. Terdapat bukti cleaning dan disposal. 5. Penutup grease trap terpasang baik.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-13', 'code' => 'BSEOUT-1.13.1', 'text' => 'Kualitas dan ketersediaan air yang digunakan untuk operasional memenuhi persyaratan keamanan pangan. Guide Line : 1. Toren dibersihkan secara berkala. 2. Tersedia bukti cleaning toren. 3. Air jernih, tidak berbau, tidak berwarna. 4. Hasil uji kualitas air tersedia (sesuai ketentuan perusahaan/regulasi). 5. Distribusi air tidak terkontaminasi.', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-14', 'code' => 'BSEOUT-1.14.1', 'text' => 'Tidak terdapat kebocoran atap, rembesan air, atau kerusakan bangunan yang dapat menyebabkan kontaminasi pangan maupun gangguan operasional. Guide Line : 1. Tidak terdapat titik bocor. 2. Tidak ada noda rembesan pada plafon. 3. Talang air berfungsi baik. 4. Area produksi terlindungi dari tetesan air.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S1-15', 'code' => 'BSEOUT-1.15.1', 'text' => 'Seluruh furniture dan fixture dalam kondisi layak fungsi, aman digunakan, bersih, terawat, serta tidak menimbulkan risiko terhadap keselamatan tamu maupun karyawan. Program inspeksi dan perawatan dilakukan secara berkala untuk menjaga kualitas dan estetika area operasional. Guide Line : 1. Seluruh furniture seperti meja, kursi, sofa, rak, cabinet, counter, shelving, dan fixture lainnya dalam kondisi kokoh, stabil, berfungsi dengan baik, serta tidak mengalami kerusakan yang dapat mengganggu operasional. 2. Material furniture seperti kayu, stainless steel, besi, kaca, kain, maupun upholstery berada dalam kondisi baik, tidak sobek, tidak lapuk, tidak berkarat, tidak terkelupas, dan tidak mengalami perubahan bentuk. 3. Meja, kursi, rak, dan fixture tidak goyah, miring, atau berpotensi roboh pada saat digunakan. 4. Tersedia program inspeksi dan perawatan berkala terhadap furniture dan fixture, termasuk perbaikan atau penggantian apabila ditemukan kerusakan. 5. Furniture dan fixture memiliki tampilan yang terawat, seragam, tidak kusam, tidak pudar, serta mendukung standar visual dan citra perusahaan.', 'weight' => 50, 'sort_order' => 10],

    ['sc' => 'BSEOUT-S2-1', 'code' => 'BSEOUT-2.1.1', 'text' => 'Seluruh equipment pemanas berfungsi sesuai peruntukannya, aman digunakan, tidak mengalami kerusakan, serta mampu mendukung kelancaran operasional tanpa mengganggu kualitas produk maupun pelayanan. Guide Line: 1. Equipment dapat dioperasikan dengan normal. 2. Tombol, thermostat, timer, display, alarm, dan indikator berfungsi. 3. Tidak terdapat kerusakan fisik. 4. Tidak terdapat kebocoran gas. 5. Tidak terdapat bunyi/getaran abnormal. 6. Equipment dalam kondisi siap pakai.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S2-2', 'code' => 'BSEOUT-2.2.1', 'text' => 'Seluruh equipment pendingin berfungsi sesuai peruntukannya, aman digunakan, tidak mengalami kerusakan, serta mampu mendukung kelancaran operasional tanpa mengganggu kualitas produk maupun pelayanan. Guide Line: 1. Equipment dapat dioperasikan dengan normal. 2. Tombol, thermostat, timer, display, dan indikator berfungsi. 3. Tidak terdapat kerusakan fisik. 4. Tidak terdapat kebocoran air, atau refrigeran. 5. Tidak terdapat bunyi/getaran abnormal. 6. Equipment dalam kondisi siap pakai.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S2-3', 'code' => 'BSEOUT-2.3.1', 'text' => 'Equipment mampu mencapai dan mempertahankan suhu operasional sesuai standar perusahaan. Equipment Pemanas: Oven, Stove, Grill, Kwali Range, Fryer, Bain marie, Salamander, Microwave, Hot holding cabinet, Coffee Machine. Equipment Pendingin: Walkin Chiller, Walkin Freezer, Undercounter chiller, Up-Right chiller/freezer, Chest Freezer, Display / Show Case chiller. Guide Line : 1. Temperatur aktual sesuai standar. 2. Recovery time normal. 3. Tidak terjadi fluktuasi suhu berlebihan. 4. Tersedia pencatatan suhu.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S2-4', 'code' => 'BSEOUT-2.4.1', 'text' => 'Program Preventive Maintenance tersedia dan dilaksanakan sesuai jadwal untuk menjaga keandalan dan umur pakai equipment. Guide Line : 1. Jadwal PMT tersedia. 2. PMT dilaksanakan sesuai frekuensi. 3. Tersedia checklist PMT. 4. Bukti pekerjaan terdokumentasi.', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S2-5', 'code' => 'BSEOUT-2.5.1', 'text' => 'Dilakukan inspeksi berkala untuk menilai tingkat kelayakan operasional setiap equipment berdasarkan kondisi fisik, fungsi, keamanan, dan performanya. Guide Line : 1. Kondisi fisik. 2. Fungsi operasional. 3. Tingkat keandalan. 4. Risiko kerusakan. 5. Efisiensi penggunaan energi. 6. Dampak terhadap kualitas produk.', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S2-6', 'code' => 'BSEOUT-2.6.1', 'text' => 'Seluruh kejadian kerusakan equipment dicatat, dianalisis, dan ditindaklanjuti untuk mencegah terjadinya gangguan berulang. Guide Line : 1. Logbook kerusakan tersedia. 2. Terdapat work order perbaikan. 3. Analisa akar masalah dilakukan. 4. Waktu penyelesaian perbaikan dimonitor.', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S2-7', 'code' => 'BSEOUT-2.7.1', 'text' => 'Dilakukan evaluasi berkala terhadap umur ekonomis dan tingkat keandalan equipment sebagai dasar pengambilan keputusan peremajaan (replacement). Guide Line : 1. Data usia equipment tersedia. 2. Riwayat kerusakan terdokumentasi. 3. Biaya perbaikan dibandingkan dengan nilai investasi baru. 4. Equipment dengan breakdown berulang diusulkan untuk penggantian. 5. Tersedia daftar prioritas replacement.', 'weight' => 10, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S2-8', 'code' => 'BSEOUT-2.8.1', 'text' => 'Dilakukan kajian kapasitas operasional secara berkala untuk memastikan jumlah dan jenis equipment sesuai dengan kebutuhan bisnis. Guide Line : 1. Kecukupan kapasitas produksi. 2. Volume transaksi dibanding kapasitas equipment. 3. Bottleneck operasional. 4. Penambahan menu atau konsep baru. 5. Pertumbuhan penjualan. 6. Utilisasi equipment saat peak hours. 7. Tersedia usulan CAPEX penambahan equipment.', 'weight' => 10, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S2-9', 'code' => 'BSEOUT-2.9.1', 'text' => 'Equipment yang memerlukan kalibrasi dilakukan verifikasi atau kalibrasi secara berkala untuk memastikan akurasi hasil pengukuran. Guide Line : 1. Thermometer terkalibrasi. 2. Temperature probe diverifikasi. 3. Bukti kalibrasi tersedia. 4. Label kalibrasi terpasang.', 'weight' => 10, 'sort_order' => 10],
    ['sc' => 'BSEOUT-S2-10', 'code' => 'BSEOUT-2.10.1', 'text' => 'Setiap equipment memiliki identitas aset dan dokumentasi yang lengkap sebagai dasar pengendalian dan pengambilan keputusan. Guide Line : 1. Kode aset tersedia. 2. Data spesifikasi equipment tersedia. 3. Tanggal pembelian tercatat. 4. Warranty terdokumentasi. 5. Manual book tersedia. 6. Riwayat PMT dan perbaikan terdokumentasi.', 'weight' => 10, 'sort_order' => 10],
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
        ->select('id', 'parameter_text', 'code')
        ->get();
    $wanted = $normalize($row['text']);

    foreach ($all as $param) {
        if ($normalize($param->parameter_text) === $wanted && (string) $param->code === $row['code']) {
            DB::table('qa2_parameters')->where('id', $param->id)->update([
                'subcategory_id' => $subcategoryId,
                'parameter_text' => $row['text'],
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
    $orderedResolvedParamIds = [];
    foreach ($parameters as $parameter) {
        $subcategoryId = $subcategoryIdByCode[$parameter['sc']];
        $resolvedId = $getParameterId($parameter, $subcategoryId, $normalize);
        $paramIds[] = $resolvedId;
        $orderedResolvedParamIds[] = $resolvedId;
    }

    $paramIds = array_values(array_unique($paramIds));

    DB::table('qa2_template_items')
        ->where('template_id', $templateId)
        ->whereNotIn('parameter_id', $paramIds)
        ->delete();

    $sortOrder = 1;
    foreach ($orderedResolvedParamIds as $pid) {
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

echo "Building, Safety, Equipment Outlet seed completed. Total template items: {$templateItemCount}" . PHP_EOL;
