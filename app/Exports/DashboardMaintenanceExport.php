<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\FromCollection;

class DashboardMaintenanceExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Tasks' => new class implements FromCollection, WithTitle {
                public function collection() {
                    return DB::table('maintenance_tasks')->get();
                }
                public function title(): string { return 'Tasks'; }
            },
            'Evidence' => new class implements FromCollection, WithTitle {
                public function collection() {
                    return DB::table('maintenance_evidence')->get();
                }
                public function title(): string { return 'Evidence'; }
            },
            'Activity' => new class implements FromCollection, WithTitle {
                public function collection() {
                    return DB::table('maintenance_activity_logs')->get();
                }
                public function title(): string { return 'Activity'; }
            },
            'Members' => new class implements FromCollection, WithTitle {
                public function collection() {
                    return DB::table('maintenance_members')->get();
                }
                public function title(): string { return 'Members'; }
            },
            'PO' => new class implements FromCollection, WithTitle {
                public function collection() {
                    return DB::table('maintenance_purchase_orders')->get();
                }
                public function title(): string { return 'PO'; }
            },
        ];
    }
} 