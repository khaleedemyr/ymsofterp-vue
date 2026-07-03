<?php

namespace App\Services;

use App\Models\FbProductCalibration;
use App\Models\FbProductCalibrationParticipant;
use App\Models\FbProductCalibrationProduct;
use App\Models\FbProductCalibrationResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FbProductCalibrationService
{
    public const PARAMETER_CODES = [
        'presentation',
        'taste_profile',
        'portion_size',
        'recipe_compliance',
        'cooking_method',
        'texture',
        'temperature',
    ];

    /**
     * @return list<array{code: string, label: string}>
     */
    public function parameterOptions(): array
    {
        return [
            ['code' => 'presentation', 'label' => 'Presentation'],
            ['code' => 'taste_profile', 'label' => 'Taste Profile'],
            ['code' => 'portion_size', 'label' => 'Portion Size'],
            ['code' => 'recipe_compliance', 'label' => 'Recipe Compliance'],
            ['code' => 'cooking_method', 'label' => 'Cooking Method'],
            ['code' => 'texture', 'label' => 'Texture'],
            ['code' => 'temperature', 'label' => 'Temperature'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function calendarEvents(int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        $records = FbProductCalibration::query()
            ->with(['products'])
            ->whereBetween('scheduled_date', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->orderBy('scheduled_date')
            ->get();

        return $records->map(function (FbProductCalibration $record) {
            $productCount = $record->products->count();
            $color = match ($record->status) {
                'completed' => '#16a34a',
                'in_progress' => '#2563eb',
                default => '#7c3aed',
            };

            return [
                'id' => 'fbc-'.$record->id,
                'title' => $record->outlet_name.' · '.$productCount.' product',
                'start' => $record->scheduled_date->format('Y-m-d'),
                'allDay' => true,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'calibration_id' => $record->id,
                    'outlet_name' => $record->outlet_name,
                    'conductor_name' => $record->conductor_name,
                    'status' => $record->status,
                    'created_by' => $record->created_by,
                    'product_count' => $productCount,
                    'products' => $record->products->map(fn ($p) => $p->item_name)->values()->all(),
                ],
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchConductors(string $query, int $limit = 15): array
    {
        $search = trim($query);
        if ($search === '') {
            return [];
        }

        return User::query()
            ->where('users.status', 'A')
            ->where(function ($q) use ($search) {
                $q->where('users.nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('users.nik', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            })
            ->leftJoin('tbl_data_jabatan as j', 'users.id_jabatan', '=', 'j.id_jabatan')
            ->orderBy('users.nama_lengkap')
            ->limit($limit)
            ->get([
                'users.id',
                'users.nama_lengkap',
                DB::raw('j.nama_jabatan as jabatan_name'),
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'nama_lengkap' => (string) $row->nama_lengkap,
                'jabatan_name' => (string) ($row->jabatan_name ?? '-'),
                'display_label' => trim($row->nama_lengkap.' — '.($row->jabatan_name ?? '-')),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchParticipants(string $query, int $limit = 15): array
    {
        return $this->searchConductors($query, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchProducts(int $outletId, string $term = '', array $excludeIds = []): array
    {
        $outlet = DB::table('tbl_data_outlet')->where('id_outlet', $outletId)->first();
        if (! $outlet) {
            return [];
        }

        $regionId = $outlet->region_id ? (int) $outlet->region_id : null;
        $term = trim($term);
        $limit = $term === '' ? 50 : 30;

        $query = DB::table('items as i')
            ->join('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('sub_categories as sc', 'sc.id', '=', 'i.sub_category_id')
            ->where('c.show_pos', '1')
            ->where('i.status', 'active')
            ->where(function ($q) {
                $q->where('c.status', 'active')->orWhereNull('c.status');
            })
            ->whereExists(function ($sub) use ($outletId, $regionId) {
                $sub->select(DB::raw(1))
                    ->from('item_availabilities as ia')
                    ->whereColumn('ia.item_id', 'i.id')
                    ->where(function ($w) use ($outletId, $regionId) {
                        $w->whereIn('ia.availability_type', ['all'])
                            ->orWhere(function ($w2) use ($outletId) {
                                $w2->whereIn('ia.availability_type', ['outlet', 'byOutlet'])
                                    ->where('ia.outlet_id', $outletId);
                            });

                        if ($regionId) {
                            $w->orWhere(function ($w2) use ($regionId) {
                                $w2->whereIn('ia.availability_type', ['region', 'byRegion'])
                                    ->where('ia.region_id', $regionId);
                            });
                        }
                    });
            })
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('i.name', 'like', "%{$term}%")
                        ->orWhere('i.sku', 'like', "%{$term}%")
                        ->orWhere('c.name', 'like', "%{$term}%")
                        ->orWhere('sc.name', 'like', "%{$term}%");
                });
            })
            ->when(! empty($excludeIds), function ($q) use ($excludeIds) {
                $q->whereNotIn('i.id', array_map('intval', $excludeIds));
            })
            ->select(
                'i.id',
                'i.name',
                'i.sku',
                'c.name as category_name',
                'sc.name as sub_category_name'
            )
            ->distinct()
            ->orderBy('i.name')
            ->limit($limit);

        return $query->get()->map(function ($row) {
            $categoryName = (string) ($row->category_name ?? '');
            $subCategoryName = $row->sub_category_name ? (string) $row->sub_category_name : null;
            $categoryLabel = $subCategoryName
                ? "{$categoryName} · {$subCategoryName}"
                : $categoryName;

            return [
                'id' => (int) $row->id,
                'item_id' => (int) $row->id,
                'item_name' => (string) $row->name,
                'category_name' => $categoryName,
                'sub_category_name' => $subCategoryName,
                'display_label' => (string) $row->name.' ('.$categoryLabel.')',
            ];
        })->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    public function syncProducts(FbProductCalibration $calibration, array $products): void
    {
        FbProductCalibrationProduct::where('calibration_id', $calibration->id)->delete();

        foreach ($products as $index => $product) {
            FbProductCalibrationProduct::create([
                'calibration_id' => $calibration->id,
                'item_id' => (int) $product['item_id'],
                'item_name' => (string) $product['item_name'],
                'category_name' => $product['category_name'] ?? null,
                'sub_category_name' => $product['sub_category_name'] ?? null,
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $participants
     * @param  array<int, array<string, mixed>>  $results
     */
    public function saveConduct(
        FbProductCalibration $calibration,
        array $participants,
        array $results
    ): void {
        FbProductCalibrationParticipant::where('calibration_id', $calibration->id)->delete();
        FbProductCalibrationResult::where('calibration_id', $calibration->id)->delete();

        $calibration->load('products');
        $productMap = $calibration->products->keyBy('id');

        $participantModels = [];
        foreach ($participants as $index => $participant) {
            $user = User::query()
                ->where('id', (int) $participant['user_id'])
                ->where('status', 'A')
                ->with('jabatan')
                ->firstOrFail();

            $participantModels[(int) $user->id] = FbProductCalibrationParticipant::create([
                'calibration_id' => $calibration->id,
                'user_id' => $user->id,
                'user_name' => (string) $user->nama_lengkap,
                'jabatan_name' => (string) ($user->jabatan?->nama_jabatan ?? '-'),
                'sort_order' => $index,
            ]);
        }

        foreach ($results as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $productId = (int) ($row['calibration_product_id'] ?? 0);
            if (! isset($participantModels[$userId]) || ! $productMap->has($productId)) {
                continue;
            }

            $payload = [
                'calibration_id' => $calibration->id,
                'participant_id' => $participantModels[$userId]->id,
                'calibration_product_id' => $productId,
            ];

            $rowComplete = true;
            foreach (self::PARAMETER_CODES as $code) {
                $value = $row[$code] ?? null;
                if (! in_array($value, ['C', 'NC'], true)) {
                    $rowComplete = false;
                    break;
                }
                $payload[$code] = $value;
            }

            if (! $rowComplete) {
                continue;
            }

            FbProductCalibrationResult::create($payload);
        }

        $calibration->update([
            'status' => 'completed',
            'updated_by' => auth()->id(),
        ]);
    }

    public function notifyConductor(FbProductCalibration $calibration): void
    {
        $calibration->load('products');
        $productList = $calibration->products->pluck('item_name')->implode(', ');
        $dateLabel = $calibration->scheduled_date->format('d M Y');

        NotificationService::create([
            'user_id' => $calibration->conductor_id,
            'type' => 'fb_product_calibration_assigned',
            'title' => 'F&B Product Calibration',
            'message' => sprintf(
                'Anda ditugaskan conduct calibration di %s pada %s. Produk: %s',
                $calibration->outlet_name,
                $dateLabel,
                $productList ?: '-'
            ),
            'url' => config('app.url').'/fb-product-calibration/'.$calibration->id,
            'is_read' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildConductPayload(FbProductCalibration $calibration): array
    {
        $calibration->load(['products', 'participants', 'results']);

        $resultMap = [];
        foreach ($calibration->results as $result) {
            $participant = $calibration->participants->firstWhere('id', $result->participant_id);
            if (! $participant) {
                continue;
            }
            $key = $participant->user_id.'_'.$result->calibration_product_id;
            $resultMap[$key] = [
                'user_id' => (int) $participant->user_id,
                'calibration_product_id' => (int) $result->calibration_product_id,
            ];
            foreach (self::PARAMETER_CODES as $code) {
                $resultMap[$key][$code] = $result->{$code};
            }
        }

        return [
            'participants' => $calibration->participants->map(fn ($p) => [
                'user_id' => (int) $p->user_id,
                'user_name' => $p->user_name,
                'jabatan_name' => $p->jabatan_name,
                'display_label' => $p->user_name.' — '.($p->jabatan_name ?? '-'),
            ])->values()->all(),
            'results' => array_values($resultMap),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildReport(
        string $dateFrom,
        string $dateTo,
        ?int $outletId = null,
        ?string $employeeSearch = null
    ): array {
        $employeeSearch = trim((string) $employeeSearch);

        $calibrations = FbProductCalibration::query()
            ->where('status', 'completed')
            ->whereBetween('scheduled_date', [$dateFrom, $dateTo])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->orderBy('scheduled_date')
            ->orderBy('outlet_name')
            ->with(['products', 'participants', 'results'])
            ->get();

        $rows = [];

        foreach ($calibrations as $calibration) {
            $productMap = $calibration->products->keyBy('id');
            $participantMap = $calibration->participants->keyBy('id');

            foreach ($calibration->results as $result) {
                $participant = $participantMap->get($result->participant_id);
                $product = $productMap->get($result->calibration_product_id);
                if (! $participant || ! $product) {
                    continue;
                }

                if ($employeeSearch !== '' && ! str_contains(
                    mb_strtolower($participant->user_name),
                    mb_strtolower($employeeSearch)
                )) {
                    continue;
                }

                $category = trim((string) ($product->category_name ?? ''));
                if ($product->sub_category_name) {
                    $category = $category !== ''
                        ? $category.' · '.$product->sub_category_name
                        : (string) $product->sub_category_name;
                }

                $parameters = [];
                foreach (self::PARAMETER_CODES as $code) {
                    $parameters[$code] = $result->{$code};
                }

                $rows[] = [
                    'product_name' => $product->item_name,
                    'category' => $category !== '' ? $category : '-',
                    'calibration_date' => $calibration->scheduled_date?->format('Y-m-d'),
                    'employee_name' => $participant->user_name,
                    'outlet' => $calibration->outlet_name,
                    'conducted_by' => $calibration->conductor_name,
                    'parameters' => $parameters,
                ];
            }
        }

        usort($rows, function (array $a, array $b): int {
            return [$a['calibration_date'], $a['outlet'], $a['employee_name'], $a['product_name']]
                <=> [$b['calibration_date'], $b['outlet'], $b['employee_name'], $b['product_name']];
        });

        foreach ($rows as $index => &$row) {
            $row['no'] = $index + 1;
        }
        unset($row);

        return [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'outlet_id' => $outletId,
                'employee_search' => $employeeSearch !== '' ? $employeeSearch : null,
            ],
            'parameter_options' => $this->parameterOptions(),
            'rows' => $rows,
            'total' => count($rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(FbProductCalibration $calibration): array
    {
        $calibration->load(['products', 'participants']);

        return [
            'id' => $calibration->id,
            'outlet_id' => $calibration->outlet_id,
            'outlet_name' => $calibration->outlet_name,
            'scheduled_date' => $calibration->scheduled_date?->format('Y-m-d'),
            'conductor_id' => $calibration->conductor_id,
            'conductor_name' => $calibration->conductor_name,
            'status' => $calibration->status,
            'products' => $calibration->products->map(fn ($p) => [
                'item_id' => $p->item_id,
                'item_name' => $p->item_name,
            ])->values()->all(),
            'participants' => $calibration->participants->map(fn ($p) => [
                'user_id' => $p->user_id,
                'user_name' => $p->user_name,
            ])->values()->all(),
        ];
    }
}
