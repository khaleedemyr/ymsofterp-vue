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
    echo "Untuk commit ke database, jalankan: php scripts/seed_service_audit.php --apply --yes" . PHP_EOL;
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
    'code' => 'SVA',
    'name' => 'Service Audit',
    'audit_type' => 'Service Evaluation',
    'department' => 'Service',
    'version' => 1,
    'status' => 'A',
    'notes' => 'Seed from Service Audit screenshots with dedicated SVA master data and fixed ordering.',
];

$categories = [
    ['code' => 'SVA-C1', 'name' => 'SEQUENCE OF SERVICE', 'sort_order' => 10],
    ['code' => 'SVA-C2', 'name' => 'SERVICE & HOSPITALITY', 'sort_order' => 20],
    ['code' => 'SVA-C3', 'name' => 'RESTAURANT CLEANLINESS', 'sort_order' => 30],
    ['code' => 'SVA-C4', 'name' => 'SERVING EQUIPMENT PROPERNESS', 'sort_order' => 40],
    ['code' => 'SVA-C5', 'name' => 'CHEMICAL CONTROL', 'sort_order' => 50],
    ['code' => 'SVA-C6', 'name' => 'PERSONAL HYGIENE', 'sort_order' => 60],
    ['code' => 'SVA-C7', 'name' => 'EMERGENCY & SAFETY FACILITIES', 'sort_order' => 70],
];

$subcategories = [
    ['cat' => 'SVA-C1', 'code' => 'SVA-S1-1', 'name' => 'SEQUENCE OF SERVICE', 'sort_order' => 10],
    ['cat' => 'SVA-C2', 'code' => 'SVA-S2-1', 'name' => 'SERVICE & HOSPITALITY', 'sort_order' => 10],

    ['cat' => 'SVA-C3', 'code' => 'SVA-S3-1', 'name' => 'AREA TAMU (DINING AREA)', 'sort_order' => 10],
    ['cat' => 'SVA-C3', 'code' => 'SVA-S3-2', 'name' => 'CASHIER & WAITING AREA', 'sort_order' => 20],
    ['cat' => 'SVA-C3', 'code' => 'SVA-S3-3', 'name' => 'TOILET TAMU', 'sort_order' => 30],
    ['cat' => 'SVA-C3', 'code' => 'SVA-S3-4', 'name' => 'AREA SERVICE STATION', 'sort_order' => 40],
    ['cat' => 'SVA-C3', 'code' => 'SVA-S3-5', 'name' => 'AREA PENYIMPANAN PERLENGKAPAN SERVICE', 'sort_order' => 50],
    ['cat' => 'SVA-C3', 'code' => 'SVA-S3-6', 'name' => 'AREA UMUM RESTORAN', 'sort_order' => 60],
    ['cat' => 'SVA-C3', 'code' => 'SVA-S3-7', 'name' => 'PEST CONTROL', 'sort_order' => 70],

    ['cat' => 'SVA-C4', 'code' => 'SVA-S4-1', 'name' => 'SERVING EQUIPMENT PROPERNESS', 'sort_order' => 10],
    ['cat' => 'SVA-C5', 'code' => 'SVA-S5-1', 'name' => 'CHEMICAL CONTROL', 'sort_order' => 10],
    ['cat' => 'SVA-C6', 'code' => 'SVA-S6-1', 'name' => 'PERSONAL HYGIENE', 'sort_order' => 10],
    ['cat' => 'SVA-C7', 'code' => 'SVA-S7-1', 'name' => 'EMERGENCY & SAFETY FACILITIES', 'sort_order' => 10],
];

$groups = [
    ['source_prefix' => 'FCA-3.', 'target_prefix' => 'SVA-1.', 'target_sub' => 'SVA-S1-1'],
    ['source_prefix' => 'FCA-4.', 'target_prefix' => 'SVA-2.', 'target_sub' => 'SVA-S2-1'],

    ['source_prefix' => 'FCA-5.1.', 'target_prefix' => 'SVA-3.1.', 'target_sub' => 'SVA-S3-1'],
    ['source_prefix' => 'FCA-5.2.', 'target_prefix' => 'SVA-3.2.', 'target_sub' => 'SVA-S3-2'],
    ['source_prefix' => 'FCA-5.3.', 'target_prefix' => 'SVA-3.3.', 'target_sub' => 'SVA-S3-3'],
    ['source_prefix' => 'FCA-5.4.', 'target_prefix' => 'SVA-3.4.', 'target_sub' => 'SVA-S3-4'],
    ['source_prefix' => 'FCA-5.5.', 'target_prefix' => 'SVA-3.5.', 'target_sub' => 'SVA-S3-5'],
    ['source_prefix' => 'FCA-5.6.', 'target_prefix' => 'SVA-3.6.', 'target_sub' => 'SVA-S3-6'],
    ['source_prefix' => 'FCA-8.', 'target_prefix' => 'SVA-3.7.', 'target_sub' => 'SVA-S3-7'],

    ['source_prefix' => 'FCA-6.', 'target_prefix' => 'SVA-4.', 'target_sub' => 'SVA-S4-1'],
    ['source_prefix' => 'FCA-7.', 'target_prefix' => 'SVA-5.', 'target_sub' => 'SVA-S5-1'],
    ['source_prefix' => 'FCA-9.', 'target_prefix' => 'SVA-6.', 'target_sub' => 'SVA-S6-1'],
    ['source_prefix' => 'FCA-10.', 'target_prefix' => 'SVA-7.', 'target_sub' => 'SVA-S7-1'],
];

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

    $mappedRows = [];

    foreach ($groups as $group) {
        $sourceCodes = DB::table('qa2_parameters')
            ->where('status', 'A')
            ->where('code', 'like', $group['source_prefix'] . '%')
            ->pluck('code')
            ->map(fn ($x) => (string) $x)
            ->values()
            ->all();

        usort($sourceCodes, static fn (string $a, string $b) => strnatcasecmp($a, $b));

        $sourceParams = DB::table('qa2_parameters')
            ->whereIn('code', $sourceCodes)
            ->select('code', 'parameter_text', 'weight')
            ->get()
            ->keyBy('code');

        $sortInSub = 1;
        foreach ($sourceCodes as $sourceCode) {
            $sourceParam = $sourceParams->get($sourceCode);
            if (!$sourceParam) {
                continue;
            }

            $suffix = (string) substr($sourceCode, strlen($group['source_prefix']));
            $targetCode = $group['target_prefix'] . $suffix;

            $mappedRows[] = [
                'target_code' => $targetCode,
                'target_sub' => $group['target_sub'],
                'text' => (string) $sourceParam->parameter_text,
                'weight' => (float) $sourceParam->weight,
                'sort_order' => $sortInSub++,
            ];
        }
    }

    if (empty($mappedRows)) {
        throw new RuntimeException('Tidak ada source parameter service yang bisa dipetakan untuk SVA.');
    }

    foreach ($mappedRows as $row) {
        DB::table('qa2_parameters')->updateOrInsert(
            ['code' => $row['target_code']],
            [
                'subcategory_id' => $subcategoryIds[$row['target_sub']],
                'parameter_text' => $row['text'],
                'weight' => $row['weight'],
                'sort_order' => $row['sort_order'],
                'status' => 'A',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    $targetCodes = array_values(array_unique(array_map(static fn (array $r) => $r['target_code'], $mappedRows)));

    $targetParamIds = DB::table('qa2_parameters')
        ->whereIn('code', $targetCodes)
        ->pluck('id', 'code')
        ->all();

    $parameterIds = [];
    foreach ($mappedRows as $row) {
        $code = $row['target_code'];
        if (isset($targetParamIds[$code])) {
            $parameterIds[] = (int) $targetParamIds[$code];
        }
    }

    $parameterIds = array_values(array_unique($parameterIds));

    if (empty($parameterIds)) {
        throw new RuntimeException('Gagal membentuk parameter id untuk template SVA.');
    }

    DB::table('qa2_template_items')
        ->where('template_id', $templateId)
        ->whereNotIn('parameter_id', $parameterIds)
        ->delete();

    $sort = 1;
    foreach ($parameterIds as $parameterId) {
        DB::table('qa2_template_items')->updateOrInsert(
            ['template_id' => $templateId, 'parameter_id' => $parameterId],
            [
                'sort_order' => $sort++,
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

echo "Service Audit seed completed. Total template items: {$templateItemCount}" . PHP_EOL;
