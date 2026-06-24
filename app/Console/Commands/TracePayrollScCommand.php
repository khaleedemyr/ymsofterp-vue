<?php

namespace App\Console\Commands;

use App\Services\PayrollScTraceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TracePayrollScCommand extends Command
{
    protected $signature = 'payroll:trace-sc
        {outlet_id? : ID outlet}
        {--year= : Tahun gajian (default: tahun ini)}
        {--month= : Bulan gajian (default: bulan ini)}
        {--pool= : Override pool SC manual (mis. 22750551)}
        {--find-outlet= : Cari outlet dari nama karyawan (mis. "Iqbal Hamdani")}
        {--json : Output JSON}';

    protected $description = 'Trace perhitungan SC payroll ERP vs formula Excel manual';

    public function handle(PayrollScTraceService $tracer): int
    {
        $outletId = $this->argument('outlet_id');
        $findName = $this->option('find-outlet');

        if ($findName && ! $outletId) {
            $matches = DB::table('users')
                ->where('nama_lengkap', 'like', '%'.$findName.'%')
                ->where('status', 'A')
                ->get(['id', 'nama_lengkap', 'id_outlet', 'nik']);

            if ($matches->isEmpty()) {
                $this->error('Karyawan tidak ditemukan: '.$findName);

                return self::FAILURE;
            }

            $this->info('Karyawan ditemukan:');
            foreach ($matches as $m) {
                $outletName = DB::table('tbl_data_outlet')->where('id_outlet', $m->id_outlet)->value('nama_outlet');
                $this->line("  [{$m->id_outlet}] {$outletName} — {$m->nama_lengkap} (NIK {$m->nik})");
            }

            if ($matches->count() === 1) {
                $outletId = $matches->first()->id_outlet;
                $this->info("Pakai outlet_id: {$outletId}");
            } else {
                $this->warn('Jalankan ulang dengan outlet_id eksplisit.');

                return self::FAILURE;
            }
        }

        if (! $outletId) {
            $this->error('Wajib outlet_id atau --find-outlet="Nama"');

            return self::FAILURE;
        }

        $year = (int) ($this->option('year') ?: date('Y'));
        $month = (int) ($this->option('month') ?: date('m'));
        $poolOverride = $this->option('pool') !== null ? (float) $this->option('pool') : null;

        $this->info("Tracing outlet {$outletId}, periode {$month}/{$year}...");

        try {
            $result = $tracer->run((int) $outletId, $year, $month, $poolOverride);
        } catch (\Throwable $e) {
            $this->error('Error: '.$e->getMessage());
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line($tracer->formatReport($result));
        }

        return self::SUCCESS;
    }
}
