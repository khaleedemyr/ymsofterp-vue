<?php

namespace App\Services;

/**
 * Perhitungan potongan BPJS karyawan (JKN + TK) dan rincian iuran perusahaan (informasi saja, tidak mengurangi THP).
 */
class PayrollBpjsCalculator
{
    /**
     * @param  object|null  $levelRow  Baris tbl_data_level (opsional, untuk fallback kolom lama)
     * @return array{kesehatan: float, ketenagakerjaan: float}
     */
    public static function resolveDasarFromLevel(?object $levelRow): array
    {
        if (! $levelRow) {
            return ['kesehatan' => 0.0, 'ketenagakerjaan' => 0.0];
        }

        $legacy = (float) ($levelRow->nilai_dasar_potongan_bpjs ?? 0);
        $kesehatan = (float) ($levelRow->nilai_dasar_potongan_bpjs_kesehatan ?? 0);
        $ketenagakerjaan = (float) ($levelRow->nilai_dasar_potongan_bpjs_ketenagakerjaan ?? 0);

        if ($kesehatan <= 0 && $legacy > 0) {
            $kesehatan = $legacy;
        }
        if ($ketenagakerjaan <= 0 && $legacy > 0) {
            $ketenagakerjaan = $legacy;
        }

        return [
            'kesehatan' => $kesehatan,
            'ketenagakerjaan' => $ketenagakerjaan,
        ];
    }

    /**
     * @param  object  $masterData  Objek dengan bpjs_jkn, bpjs_tk (0|1)
     * @param  object|null  $kategori  Baris tbl_bpjs_kategori (aktif) atau null
     * @return array{bpjs_jkn: float, bpjs_tk: float, perusahaan_detail: array|null}
     */
    public static function calculate(
        object $masterData,
        float $nilaiDasarBPJSKesehatan,
        float $nilaiDasarBPJSKetenagakerjaan,
        ?object $kategori,
        int $idOutlet
    ): array {
        $jknOn = isset($masterData->bpjs_jkn) && (int) $masterData->bpjs_jkn === 1;
        $tkOn = isset($masterData->bpjs_tk) && (int) $masterData->bpjs_tk === 1;

        $bpjsJkn = 0.0;
        $bpjsTk = 0.0;
        $perusahaanDetail = null;

        $canJkn = $jknOn && $nilaiDasarBPJSKesehatan > 0;
        $canTk = $tkOn && $nilaiDasarBPJSKetenagakerjaan > 0;

        if ((! $jknOn && ! $tkOn) || (! $canJkn && ! $canTk)) {
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

            if ($canJkn) {
                $bpjsJkn = round($nilaiDasarBPJSKesehatan * $p('pct_kes_karyawan') / 100.0, 2);
            }
            if ($canTk) {
                $pctTkKar = $p('pct_jht_karyawan') + $p('pct_jp_karyawan');
                $bpjsTk = round($nilaiDasarBPJSKetenagakerjaan * $pctTkKar / 100.0, 2);
            }

            $lines = [];
            $defs = [
                ['label' => 'BPJS Kesehatan (perusahaan)', 'pct' => $p('pct_kes_perusahaan'), 'key' => 'kes_perusahaan', 'base' => 'kesehatan'],
                ['label' => 'JHT (perusahaan)', 'pct' => $p('pct_jht_perusahaan'), 'key' => 'jht_perusahaan', 'base' => 'ketenagakerjaan'],
                ['label' => 'JP (perusahaan)', 'pct' => $p('pct_jp_perusahaan'), 'key' => 'jp_perusahaan', 'base' => 'ketenagakerjaan'],
                ['label' => 'JKK (perusahaan)', 'pct' => $p('pct_jkk_perusahaan'), 'key' => 'jkk_perusahaan', 'base' => 'ketenagakerjaan'],
                ['label' => 'JKM (perusahaan)', 'pct' => $p('pct_jkm_perusahaan'), 'key' => 'jkm_perusahaan', 'base' => 'ketenagakerjaan'],
            ];
            $sumPerusahaan = 0.0;
            foreach ($defs as $row) {
                if ($row['pct'] <= 0) {
                    continue;
                }
                $dasar = $row['base'] === 'kesehatan' ? $nilaiDasarBPJSKesehatan : $nilaiDasarBPJSKetenagakerjaan;
                if ($dasar <= 0) {
                    continue;
                }
                $amt = round($dasar * $row['pct'] / 100.0, 2);
                $sumPerusahaan += $amt;
                $lines[] = [
                    'key' => $row['key'],
                    'label' => $row['label'],
                    'pct' => round($row['pct'], 4),
                    'amount' => $amt,
                    'dasar' => round($dasar, 2),
                ];
            }

            $perusahaanDetail = [
                'source' => 'kategori',
                'kategori_id' => (int) ($kategori->id ?? 0),
                'kategori_nama' => (string) ($kategori->nama_kategori ?? ''),
                'dasar_potongan_kesehatan' => round($nilaiDasarBPJSKesehatan, 2),
                'dasar_potongan_ketenagakerjaan' => round($nilaiDasarBPJSKetenagakerjaan, 2),
                'dasar_potongan' => round($nilaiDasarBPJSKesehatan, 2),
                'lines' => $lines,
                'total_perusahaan' => round($sumPerusahaan, 2),
                'pct_kes_karyawan' => round($p('pct_kes_karyawan'), 4),
                'pct_jht_karyawan' => round($p('pct_jht_karyawan'), 4),
                'pct_jp_karyawan' => round($p('pct_jp_karyawan'), 4),
            ];
        } else {
            if ($canJkn) {
                $bpjsJkn = round($nilaiDasarBPJSKesehatan * 0.01, 2);
            }
            if ($canTk) {
                $rateTk = $idOutlet === 1 ? 0.03 : 0.02;
                $bpjsTk = round($nilaiDasarBPJSKetenagakerjaan * $rateTk, 2);
            }
            $perusahaanDetail = [
                'source' => 'legacy',
                'kategori_id' => null,
                'kategori_nama' => '',
                'dasar_potongan_kesehatan' => round($nilaiDasarBPJSKesehatan, 2),
                'dasar_potongan_ketenagakerjaan' => round($nilaiDasarBPJSKetenagakerjaan, 2),
                'dasar_potongan' => round($nilaiDasarBPJSKesehatan, 2),
                'message' => 'Belum ada Kategori BPJS pada Data Level untuk level jabatan ini. Potongan karyawan memakai aturan lama (JKN 1% dari dasar kesehatan; TK 2% atau 3% dari dasar ketenagakerjaan).',
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
