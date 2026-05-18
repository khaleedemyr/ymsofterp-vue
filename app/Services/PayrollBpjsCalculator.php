<?php

namespace App\Services;

/**
 * Perhitungan potongan BPJS karyawan (JKN + TK) dan rincian iuran perusahaan (informasi saja, tidak mengurangi THP).
 */
class PayrollBpjsCalculator
{
    /**
     * @param  object  $masterData  Objek dengan bpjs_jkn, bpjs_tk (0|1)
     * @param  object|null  $kategori  Baris tbl_bpjs_kategori (aktif) atau null
     * @return array{bpjs_jkn: float, bpjs_tk: float, perusahaan_detail: array|null}
     */
    public static function calculate(object $masterData, float $nilaiDasarBPJS, ?object $kategori, int $idOutlet): array
    {
        $jknOn = isset($masterData->bpjs_jkn) && (int) $masterData->bpjs_jkn === 1;
        $tkOn = isset($masterData->bpjs_tk) && (int) $masterData->bpjs_tk === 1;

        $bpjsJkn = 0.0;
        $bpjsTk = 0.0;
        $perusahaanDetail = null;

        if ((! $jknOn && ! $tkOn) || $nilaiDasarBPJS <= 0) {
            return [
                'bpjs_jkn' => 0.0,
                'bpjs_tk' => 0.0,
                'perusahaan_detail' => null,
            ];
        }

        if ($kategori) {
            $p = function (string $col) use ($kategori): float {
                return (float) ($kategori->{$col} ?? 0);
            };

            if ($jknOn) {
                $bpjsJkn = round($nilaiDasarBPJS * $p('pct_kes_karyawan') / 100.0, 2);
            }
            if ($tkOn) {
                $pctTkKar = $p('pct_jht_karyawan') + $p('pct_jp_karyawan');
                $bpjsTk = round($nilaiDasarBPJS * $pctTkKar / 100.0, 2);
            }

            $lines = [];
            $defs = [
                ['label' => 'BPJS Kesehatan (perusahaan)', 'pct' => $p('pct_kes_perusahaan'), 'key' => 'kes_perusahaan'],
                ['label' => 'JHT (perusahaan)', 'pct' => $p('pct_jht_perusahaan'), 'key' => 'jht_perusahaan'],
                ['label' => 'JP (perusahaan)', 'pct' => $p('pct_jp_perusahaan'), 'key' => 'jp_perusahaan'],
                ['label' => 'JKK (perusahaan)', 'pct' => $p('pct_jkk_perusahaan'), 'key' => 'jkk_perusahaan'],
                ['label' => 'JKM (perusahaan)', 'pct' => $p('pct_jkm_perusahaan'), 'key' => 'jkm_perusahaan'],
            ];
            $sumPerusahaan = 0.0;
            foreach ($defs as $row) {
                if ($row['pct'] <= 0) {
                    continue;
                }
                $amt = round($nilaiDasarBPJS * $row['pct'] / 100.0, 2);
                $sumPerusahaan += $amt;
                $lines[] = [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'pct' => round($row['pct'], 4),
                    'amount' => $amt,
                ];
            }

            $perusahaanDetail = [
                'source' => 'kategori',
                'kategori_id' => (int) ($kategori->id ?? 0),
                'kategori_nama' => (string) ($kategori->nama_kategori ?? ''),
                'dasar_potongan' => round($nilaiDasarBPJS, 2),
                'lines' => $lines,
                'total_perusahaan' => round($sumPerusahaan, 2),
                'pct_kes_karyawan' => round($p('pct_kes_karyawan'), 4),
                'pct_jht_karyawan' => round($p('pct_jht_karyawan'), 4),
                'pct_jp_karyawan' => round($p('pct_jp_karyawan'), 4),
            ];
        } else {
            if ($jknOn) {
                $bpjsJkn = round($nilaiDasarBPJS * 0.01, 2);
            }
            if ($tkOn) {
                $rateTk = $idOutlet === 1 ? 0.03 : 0.02;
                $bpjsTk = round($nilaiDasarBPJS * $rateTk, 2);
            }
            $perusahaanDetail = [
                'source' => 'legacy',
                'kategori_id' => null,
                'kategori_nama' => '',
                'dasar_potongan' => round($nilaiDasarBPJS, 2),
                'message' => 'Belum ada Kategori BPJS pada Data Level untuk level jabatan ini. Potongan karyawan memakai aturan lama (JKN 1%; TK 2% atau 3% untuk outlet tertentu).',
                'lines' => [],
                'total_perusahaan' => 0.0,
            ];
        }

        return [
            'bpjs_jkn' => $bpjsJkn,
            'bpjs_tk' => $bpjsTk,
            'perusahaan_detail' => $perusahaanDetail,
        ];
    }
}
