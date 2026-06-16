<?php
/**
 * @deprecated Gunakan scripts/compare_rekap_fj_vs_outlet_payment.php
 *
 * Wrapper untuk kasus Dago 11-Jun-2026:
 *   php scripts/compare_dago_fj_vs_payment.php
 */
passthru('php ' . escapeshellarg(__DIR__ . '/compare_rekap_fj_vs_outlet_payment.php')
    . ' --outlet-name=Dago --date=2026-06-11 --verbose', $exitCode);
exit($exitCode);
