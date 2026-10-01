<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$args = $_SERVER['argv'] ?? [];
$isApply = in_array('--apply', $args, true);
$assumeYes = in_array('--yes', $args, true);

if (!$isApply) {
    echo "[SAFE MODE] Dry-run aktif. Semua perubahan akan di-ROLLBACK." . PHP_EOL;
    echo "Untuk commit, jalankan: php scripts/seed_facility_safety_qa2.php --apply --yes" . PHP_EOL;
}

if ($isApply && !$assumeYes) {
    echo "Anda akan menulis data ke database. Lanjut? ketik YES: ";
    if (trim((string) fgets(STDIN)) !== 'YES') {
        echo "Dibatalkan oleh user." . PHP_EOL;
        exit(1);
    }
}

if (!Schema::hasColumn('qa2_templates', 'scoring_mode')) {
    throw new RuntimeException('Kolom scoring_mode belum tersedia. Jalankan php artisan migrate terlebih dahulu.');
}

$now = now();
$template = [
    'code' => 'FSA-BKP',
    'name' => 'FORM AUDIT KELAYAKAN BANGUNAN, KESELAMATAN KERJA & PERALATAN',
    'audit_type' => 'Facility Evaluation',
    'department' => 'Engineering',
    'version' => 1,
    'scoring_mode' => 'c_mn_my',
    'status' => 'A',
    'notes' => 'Seed checklist dari form audit kelayakan bangunan, keselamatan kerja, dan peralatan.',
];

$categories = [
    ['code' => 'FSA-C1', 'name' => 'CIVIL', 'sort_order' => 10],
    ['code' => 'FSA-C2', 'name' => 'MECHANICAL', 'sort_order' => 20],
    ['code' => 'FSA-C3', 'name' => 'ELECTRICAL', 'sort_order' => 30],
    ['code' => 'FSA-C4', 'name' => 'PLUMBING', 'sort_order' => 40],
    ['code' => 'FSA-C5', 'name' => 'PREVENTIVE MAINTENANCE', 'sort_order' => 50],
];

$subcategories = [
    ['code' => 'FSA-S1-1', 'category' => 'FSA-C1', 'name' => 'Building & Structure', 'sort_order' => 10],
    ['code' => 'FSA-S1-2', 'category' => 'FSA-C1', 'name' => 'Furniture', 'sort_order' => 20],
    ['code' => 'FSA-S1-3', 'category' => 'FSA-C1', 'name' => 'Area & Guest Facility', 'sort_order' => 30],
    ['code' => 'FSA-S2-1', 'category' => 'FSA-C2', 'name' => 'Mechanical Checklist', 'sort_order' => 10],
    ['code' => 'FSA-S3-1', 'category' => 'FSA-C3', 'name' => 'Electrical Checklist', 'sort_order' => 10],
    ['code' => 'FSA-S4-1', 'category' => 'FSA-C4', 'name' => 'Plumbing Checklist', 'sort_order' => 10],
    ['code' => 'FSA-S5-1', 'category' => 'FSA-C5', 'name' => 'Planning', 'sort_order' => 10],
    ['code' => 'FSA-S5-2', 'category' => 'FSA-C5', 'name' => 'Execution', 'sort_order' => 20],
    ['code' => 'FSA-S5-3', 'category' => 'FSA-C5', 'name' => 'Corrective Action', 'sort_order' => 30],
    ['code' => 'FSA-S5-4', 'category' => 'FSA-C5', 'name' => 'Documentation & Monitoring', 'sort_order' => 40],
];

$parameterRows = [
    ['subcategory' => 'FSA-S1-1', 'number' => '1.1.1', 'text' => 'Kondisi lantai baik, tidak retak, pecah, berlubang, amblas, atau licin.'],
    ['subcategory' => 'FSA-S1-1', 'number' => '1.1.2', 'text' => 'Kondisi dinding baik, tidak retak, lembap, berjamur, mengelupas, atau rusak.'],
    ['subcategory' => 'FSA-S1-1', 'number' => '1.1.3', 'text' => 'Kondisi plafon baik, tidak bocor, bernoda, retak, atau berpotensi jatuh.'],
    ['subcategory' => 'FSA-S1-1', 'number' => '1.1.4', 'text' => 'Kondisi atap baik dan tidak terdapat kebocoran.'],
    ['subcategory' => 'FSA-S1-1', 'number' => '1.1.5', 'text' => 'Kondisi pintu baik; engsel, handle, lock, dan door closer berfungsi.'],
    ['subcategory' => 'FSA-S1-1', 'number' => '1.1.6', 'text' => 'Kondisi kaca dan jendela baik, tidak retak/pecah.'],
    ['subcategory' => 'FSA-S1-1', 'number' => '1.1.7', 'text' => 'Kondisi frame dan konstruksi bangunan kokoh dan aman.'],

    ['subcategory' => 'FSA-S1-2', 'number' => '1.2.1', 'text' => 'Meja dalam kondisi kokoh, stabil, tidak goyang, dan tidak memiliki bagian tajam.'],
    ['subcategory' => 'FSA-S1-2', 'number' => '1.2.2', 'text' => 'Permukaan meja tidak rusak, pecah, mengelupas, atau membahayakan tamu.'],
    ['subcategory' => 'FSA-S1-2', 'number' => '1.2.3', 'text' => 'Kursi dalam kondisi kokoh, stabil, tidak patah atau goyang.'],
    ['subcategory' => 'FSA-S1-2', 'number' => '1.2.4', 'text' => 'Sambungan dan kaki kursi dalam kondisi baik.'],
    ['subcategory' => 'FSA-S1-2', 'number' => '1.2.5', 'text' => 'Sofa dalam kondisi kokoh dan stabil.'],
    ['subcategory' => 'FSA-S1-2', 'number' => '1.2.6', 'text' => 'Cushion sofa dalam kondisi baik dan nyaman digunakan.'],
    ['subcategory' => 'FSA-S1-2', 'number' => '1.2.7', 'text' => 'Upholstery sofa tidak sobek, rusak, atau mengelupas.'],
    ['subcategory' => 'FSA-S1-2', 'number' => '1.2.8', 'text' => 'Furniture dalam kondisi bersih dan memiliki appearance yang baik.'],
    ['subcategory' => 'FSA-S1-2', 'number' => '1.2.9', 'text' => 'Built-in furniture seperti counter, bar, cashier, cabinet, dan shelving dalam kondisi baik.'],
    ['subcategory' => 'FSA-S1-2', 'number' => '1.2.10', 'text' => 'Penempatan furniture tidak mengganggu circulation dan jalur evakuasi.'],

    ['subcategory' => 'FSA-S1-3', 'number' => '1.3.1', 'text' => 'Dining area dalam kondisi rapi, aman, nyaman, dan terawat.'],
    ['subcategory' => 'FSA-S1-3', 'number' => '1.3.2', 'text' => 'Kitchen civil condition baik dan mudah dibersihkan.'],
    ['subcategory' => 'FSA-S1-3', 'number' => '1.3.3', 'text' => 'Toilet, cubicle, partition, ceiling, dan accessories dalam kondisi baik.'],
    ['subcategory' => 'FSA-S1-3', 'number' => '1.3.4', 'text' => 'Exterior dan facade dalam kondisi baik.'],
    ['subcategory' => 'FSA-S1-3', 'number' => '1.3.5', 'text' => 'Kanopi, railing, pedestrian, dan area luar aman.'],
    ['subcategory' => 'FSA-S1-3', 'number' => '1.3.6', 'text' => 'Signage terpasang kokoh dan memiliki appearance yang baik.'],
    ['subcategory' => 'FSA-S1-3', 'number' => '1.3.7', 'text' => 'Drainage area tidak tersumbat dan tidak menyebabkan genangan.'],
    ['subcategory' => 'FSA-S1-3', 'number' => '1.3.8', 'text' => 'Tidak terdapat kondisi civil/furniture yang berpotensi menyebabkan injury.'],

    ['subcategory' => 'FSA-S2-1', 'number' => '2.1', 'text' => 'AC/HVAC berfungsi normal.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.2', 'text' => 'Temperatur ruangan sesuai standar.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.3', 'text' => 'AC indoor unit bersih dan tidak bocor.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.4', 'text' => 'AC outdoor unit dalam kondisi baik.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.5', 'text' => 'Airflow AC optimal.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.6', 'text' => 'Exhaust kitchen berfungsi optimal.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.7', 'text' => 'Fresh air/ventilation berfungsi baik.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.8', 'text' => 'Exhaust toilet berfungsi baik.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.9', 'text' => 'Fan dan blower tidak menimbulkan abnormal noise atau vibration.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.10', 'text' => 'Water pump berfungsi normal.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.11', 'text' => 'Grease trap equipment berfungsi baik.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.12', 'text' => 'Mechanical kitchen equipment berfungsi sesuai standar.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.13', 'text' => 'Motor, belt, bearing, dan moving parts dalam kondisi baik.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.14', 'text' => 'Tidak terdapat abnormal vibration atau overheating.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.15', 'text' => 'Safety guard equipment tersedia dan terpasang dengan baik.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.16', 'text' => 'Equipment memiliki identification/tagging.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.17', 'text' => 'Critical mechanical equipment memiliki spare part yang diperlukan.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.18', 'text' => 'Tidak terdapat equipment yang berpotensi mengganggu operasional.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.19', 'text' => 'Kondisi mechanical equipment mendukung produktivitas operasional.'],
    ['subcategory' => 'FSA-S2-1', 'number' => '2.20', 'text' => 'Seluruh mechanical equipment dalam kondisi aman digunakan.'],

    ['subcategory' => 'FSA-S3-1', 'number' => '3.1', 'text' => 'Main electrical panel dalam kondisi baik dan aman.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.2', 'text' => 'Sub-panel dalam kondisi baik.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.3', 'text' => 'MCB/MCCB sesuai kapasitas dan berfungsi normal.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.4', 'text' => 'Kabel listrik tidak terbuka atau terkelupas.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.5', 'text' => 'Tidak terdapat kabel terbakar atau rusak.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.6', 'text' => 'Tidak terjadi electrical overload.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.7', 'text' => 'Socket dalam kondisi baik dan berfungsi.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.8', 'text' => 'Switch berfungsi normal.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.9', 'text' => 'Lighting dining area, neon sign berfungsi dan sesuai standar.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.10', 'text' => 'Lighting kitchen berfungsi dan sesuai standar.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.11', 'text' => 'Emergency lighting berfungsi.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.12', 'text' => 'Exit sign menyala dan terlihat jelas.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.13', 'text' => 'Grounding tersedia dan berfungsi.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.14', 'text' => 'Panel memiliki label circuit yang jelas.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.15', 'text' => 'Panel bersih dan bebas dari barang/material.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.16', 'text' => 'Tidak terdapat indikasi overheating pada panel.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.17', 'text' => 'Genset dalam kondisi siap digunakan.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.18', 'text' => 'UPS/battery dalam kondisi baik bila tersedia.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.19', 'text' => 'Electrical equipment memiliki preventive maintenance.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.20', 'text' => 'Electrical testing dilakukan sesuai jadwal/requirement.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.21', 'text' => 'Tidak terdapat sambungan kabel yang tidak standar.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.22', 'text' => 'Tidak terdapat penggunaan extension/terminal secara berlebihan.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.23', 'text' => 'Electrical installation dalam kondisi aman.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.24', 'text' => 'Tidak terdapat potensi short circuit.'],
    ['subcategory' => 'FSA-S3-1', 'number' => '3.25', 'text' => 'Tidak terdapat potensi electrical fire atau electric shock.'],

    ['subcategory' => 'FSA-S4-1', 'number' => '4.1', 'text' => 'Water supply tersedia dan stabil.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.2', 'text' => 'Water pressure sesuai kebutuhan operasional.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.3', 'text' => 'Pipa air tidak mengalami kebocoran.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.4', 'text' => 'Faucet/kran berfungsi dan tidak bocor.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.5', 'text' => 'Sink berfungsi dan tidak bocor.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.6', 'text' => 'Floor drain tidak tersumbat.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.7', 'text' => 'Drain cover tersedia dan dalam kondisi baik.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.8', 'text' => 'Saluran pembuangan mengalir lancar.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.9', 'text' => 'Tidak terdapat bau dari saluran drain.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.10', 'text' => 'Grease trap berfungsi dan dibersihkan sesuai jadwal.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.11', 'text' => 'Water tank bersih, tertutup, dan dalam kondisi baik.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.12', 'text' => 'Water pump berfungsi normal.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.13', 'text' => 'Water heater berfungsi dan aman.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.14', 'text' => 'Toilet flush berfungsi normal.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.15', 'text' => 'Bidet/spray berfungsi normal.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.16', 'text' => 'Tidak terdapat kebocoran air.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.17', 'text' => 'Tidak terdapat genangan air.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.18', 'text' => 'Tidak terjadi backflow.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.19', 'text' => 'Plumbing tidak menjadi sumber kontaminasi.'],
    ['subcategory' => 'FSA-S4-1', 'number' => '4.20', 'text' => 'Tidak terdapat kondisi yang berpotensi menyebabkan water damage.'],

    ['subcategory' => 'FSA-S5-1', 'number' => '5.1.1', 'text' => 'Seluruh equipment memiliki preventive maintenance schedule.'],
    ['subcategory' => 'FSA-S5-1', 'number' => '5.1.2', 'text' => 'Frequency preventive maintenance ditentukan dengan jelas.'],
    ['subcategory' => 'FSA-S5-1', 'number' => '5.1.3', 'text' => 'Critical equipment memiliki prioritas preventive maintenance.'],
    ['subcategory' => 'FSA-S5-1', 'number' => '5.1.4', 'text' => 'Preventive maintenance schedule dikomunikasikan kepada PIC terkait.'],
    ['subcategory' => 'FSA-S5-1', 'number' => '5.1.5', 'text' => 'Tidak terdapat preventive maintenance yang overdue.'],

    ['subcategory' => 'FSA-S5-2', 'number' => '5.2.1', 'text' => 'Preventive maintenance dilakukan sesuai jadwal.'],
    ['subcategory' => 'FSA-S5-2', 'number' => '5.2.2', 'text' => 'PM checklist tersedia untuk setiap equipment.'],
    ['subcategory' => 'FSA-S5-2', 'number' => '5.2.3', 'text' => 'Checklist diisi lengkap dan benar.'],
    ['subcategory' => 'FSA-S5-2', 'number' => '5.2.4', 'text' => 'Maintenance dilakukan oleh personel/vendor yang kompeten.'],
    ['subcategory' => 'FSA-S5-2', 'number' => '5.2.5', 'text' => 'Hasil inspection dicatat.'],
    ['subcategory' => 'FSA-S5-2', 'number' => '5.2.6', 'text' => 'Spare part yang diganti dicatat.'],
    ['subcategory' => 'FSA-S5-2', 'number' => '5.2.7', 'text' => 'Equipment diverifikasi setelah maintenance.'],

    ['subcategory' => 'FSA-S5-3', 'number' => '5.3.1', 'text' => 'Setiap kerusakan memiliki Work Order.'],
    ['subcategory' => 'FSA-S5-3', 'number' => '5.3.2', 'text' => 'Setiap finding memiliki PIC.'],
    ['subcategory' => 'FSA-S5-3', 'number' => '5.3.3', 'text' => 'Setiap repair memiliki target completion.'],
    ['subcategory' => 'FSA-S5-3', 'number' => '5.3.4', 'text' => 'Outstanding repair dimonitor sampai selesai.'],
    ['subcategory' => 'FSA-S5-3', 'number' => '5.3.5', 'text' => 'Critical breakdown mendapatkan immediate action.'],
    ['subcategory' => 'FSA-S5-3', 'number' => '5.3.6', 'text' => 'Repeat breakdown dilakukan analisis.'],
    ['subcategory' => 'FSA-S5-3', 'number' => '5.3.7', 'text' => 'Corrective action dilakukan untuk mencegah kerusakan berulang.'],

    ['subcategory' => 'FSA-S5-4', 'number' => '5.4.1', 'text' => 'Maintenance history tersedia.'],
    ['subcategory' => 'FSA-S5-4', 'number' => '5.4.2', 'text' => 'Vendor/service report terdokumentasi.'],
    ['subcategory' => 'FSA-S5-4', 'number' => '5.4.3', 'text' => 'Warranty information terdokumentasi.'],
    ['subcategory' => 'FSA-S5-4', 'number' => '5.4.4', 'text' => 'Equipment manual tersedia.'],
    ['subcategory' => 'FSA-S5-4', 'number' => '5.4.5', 'text' => 'Calibration record tersedia untuk equipment yang membutuhkan.'],
    ['subcategory' => 'FSA-S5-4', 'number' => '5.4.6', 'text' => 'Breakdown frequency dimonitor.'],
    ['subcategory' => 'FSA-S5-4', 'number' => '5.4.7', 'text' => 'Equipment downtime dimonitor.'],
    ['subcategory' => 'FSA-S5-4', 'number' => '5.4.8', 'text' => 'Maintenance cost dimonitor.'],
    ['subcategory' => 'FSA-S5-4', 'number' => '5.4.9', 'text' => 'PM effectiveness dievaluasi secara berkala.'],
];

if (count($parameterRows) !== 118) {
    throw new RuntimeException('Jumlah checklist tidak sesuai: diharapkan 118 parameter.');
}

$parameters = [];
$subcategorySort = [];
foreach ($parameterRows as $row) {
    $subcategorySort[$row['subcategory']] = ($subcategorySort[$row['subcategory']] ?? 0) + 10;
    $parameters[] = [
        'subcategory' => $row['subcategory'],
        'code' => 'FSA-' . $row['number'],
        'parameter_text' => $row['text'],
        'weight' => 0,
        'sort_order' => $subcategorySort[$row['subcategory']],
    ];
}

DB::beginTransaction();

try {
    $existingTemplate = DB::table('qa2_templates')
        ->where('code', $template['code'])
        ->where('version', $template['version'])
        ->first(['id']);

    $templateValues = [
        'name' => $template['name'],
        'audit_type' => $template['audit_type'],
        'department' => $template['department'],
        'scoring_mode' => $template['scoring_mode'],
        'status' => $template['status'],
        'notes' => $template['notes'],
        'updated_at' => $now,
    ];

    if ($existingTemplate) {
        $templateId = (int) $existingTemplate->id;
        DB::table('qa2_templates')->where('id', $templateId)->update($templateValues);
    } else {
        $templateId = (int) DB::table('qa2_templates')->insertGetId($templateValues + [
            'code' => $template['code'],
            'version' => $template['version'],
            'created_at' => $now,
        ]);
    }

    $categoryIds = [];
    foreach ($categories as $category) {
        $existingId = DB::table('qa2_categories')->where('code', $category['code'])->value('id');
        $values = [
            'name' => $category['name'],
            'status' => 'A',
            'updated_at' => $now,
        ];
        if ($existingId) {
            DB::table('qa2_categories')->where('id', $existingId)->update($values);
            $categoryIds[$category['code']] = (int) $existingId;
        } else {
            $categoryIds[$category['code']] = (int) DB::table('qa2_categories')->insertGetId($values + [
                'code' => $category['code'],
                'created_at' => $now,
            ]);
        }
    }

    $subcategoryIds = [];
    foreach ($subcategories as $subcategory) {
        $existingId = DB::table('qa2_subcategories')->where('code', $subcategory['code'])->value('id');
        $values = [
            'category_id' => $categoryIds[$subcategory['category']],
            'name' => $subcategory['name'],
            'sort_order' => $subcategory['sort_order'],
            'status' => 'A',
            'updated_at' => $now,
        ];
        if ($existingId) {
            DB::table('qa2_subcategories')->where('id', $existingId)->update($values);
            $subcategoryIds[$subcategory['code']] = (int) $existingId;
        } else {
            $subcategoryIds[$subcategory['code']] = (int) DB::table('qa2_subcategories')->insertGetId($values + [
                'code' => $subcategory['code'],
                'created_at' => $now,
            ]);
        }
    }

    $parameterIds = [];
    foreach ($parameters as $parameter) {
        $existingId = DB::table('qa2_parameters')->where('code', $parameter['code'])->value('id');
        $values = [
            'subcategory_id' => $subcategoryIds[$parameter['subcategory']],
            'parameter_text' => $parameter['parameter_text'],
            'weight' => $parameter['weight'],
            'sort_order' => $parameter['sort_order'],
            'status' => 'A',
            'updated_at' => $now,
        ];
        if ($existingId) {
            DB::table('qa2_parameters')->where('id', $existingId)->update($values);
            $parameterIds[] = (int) $existingId;
        } else {
            $parameterIds[] = (int) DB::table('qa2_parameters')->insertGetId($values + [
                'code' => $parameter['code'],
                'created_at' => $now,
            ]);
        }
    }

    DB::table('qa2_template_items')
        ->where('template_id', $templateId)
        ->whereIn('parameter_id', function ($query) {
            $query->select('id')->from('qa2_parameters')->where('code', 'like', 'FSA-%');
        })
        ->delete();

    foreach ($parameterIds as $index => $parameterId) {
        DB::table('qa2_template_items')->insert([
            'template_id' => $templateId,
            'parameter_id' => $parameterId,
            'sort_order' => $index + 1,
            'is_required' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    if ($isApply) {
        DB::commit();
        echo '[APPLY MODE] Commit berhasil.' . PHP_EOL;
    } else {
        DB::rollBack();
        echo '[SAFE MODE] Dry-run selesai. Perubahan di-rollback.' . PHP_EOL;
    }
} catch (Throwable $e) {
    DB::rollBack();
    throw $e;
}

echo sprintf(
    "Seed selesai: %d kategori, %d subkategori, %d parameter, template %s v%d (%s).%s",
    count($categories),
    count($subcategories),
    count($parameters),
    $template['code'],
    $template['version'],
    $template['scoring_mode'],
    PHP_EOL
);