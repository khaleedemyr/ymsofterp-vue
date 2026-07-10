-- Contoh petunjuk input manual per parameter (opsional, bisa diedit di master KPI Parameters)
-- Jalankan setelah alter_kpi_parameters_manual_input_hint.sql

UPDATE `kpi_parameters` SET `manual_input_hint` = 'Isi total pendapatan F&B aktual (MTD) dalam rupiah — angka penuh tanpa titik/koma pemisah ribuan. Contoh: 19384769236.' WHERE `code` = 'D001';
UPDATE `kpi_parameters` SET `manual_input_hint` = 'Isi target/budget pendapatan F&B bulanan dalam rupiah. Contoh: 23750000000.' WHERE `code` = 'D002';
UPDATE `kpi_parameters` SET `manual_input_hint` = 'Isi persentase rasio COGS (%) dari menu Manual COGS, Deviation & Catcost untuk outlet & bulan data. Contoh: 42,5 — tanpa simbol %.' WHERE `code` = 'D048';
UPDATE `kpi_parameters` SET `manual_input_hint` = 'Isi persentase deviation (%) dari menu Manual COGS, Deviation & Catcost. Contoh: 1,5 — tanpa simbol %.' WHERE `code` = 'D049';
UPDATE `kpi_parameters` SET `manual_input_hint` = 'Isi persentase category cost (%) dari menu Manual COGS, Deviation & Catcost. Contoh: 3,2 — tanpa simbol %.' WHERE `code` = 'D050';
UPDATE `kpi_parameters` SET `manual_input_hint` = 'Isi persentase Loss & Breakage (%) dari Asset Manual Monthly L&B. Contoh: 0,5 — tanpa simbol %.' WHERE `code` = 'D051';
UPDATE `kpi_parameters` SET `manual_input_hint` = 'Isi persentase labor cost (%) dari Manual Monthly Labor Cost. Contoh: 28 — tanpa simbol %.' WHERE `code` = 'D052';
UPDATE `kpi_parameters` SET `manual_input_hint` = 'Isi rata-rata jam resolusi komplain CVCC. Contoh: 18,5.' WHERE `code` = 'D053';
UPDATE `kpi_parameters` SET `manual_input_hint` = 'Isi skor kepatuhan QA audit (%). Contoh: 92 — tanpa simbol %.' WHERE `code` = 'D016';
UPDATE `kpi_parameters` SET `manual_input_hint` = 'Isi % penyelesaian modul wajib Just Academy (0–100). Contoh: 85 — tanpa simbol %.' WHERE `code` = 'D018';
UPDATE `kpi_parameters` SET `manual_input_hint` = 'Isi rating Google Review rata-rata (skala 1–5) dari menu Manual Monthly Google Review untuk outlet & bulan data. Contoh: 4,75.' WHERE `code` = 'D026';
