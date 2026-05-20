/**
 * Generates sidebar i18n files and patches AppLayout.vue menu strings.
 * Run: node scripts/generate-sidebar-i18n.mjs
 */
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const appLayoutPath = path.join(root, 'resources/js/Layouts/AppLayout.vue');

const EN_TO_ID = {
  'Sales Outlet Dashboard': 'Dashboard Penjualan Outlet',
  'Marketing Dashboard': 'Dashboard Marketing',
  'Dashboard CRM': 'Dashboard CRM',
  'Cashflow Outlet Dashboard': 'Dashboard Arus Kas Outlet',
  'My Attendance': 'Absensi Saya',
  'Dokumen Bersama': 'Dokumen Bersama',
  Payment: 'Pembayaran',
  'PR Assets': 'PR Aset',
  'Payment Report': 'Laporan Pembayaran',
  'Payment Approval Tracker': 'Pelacak Persetujuan Pembayaran',
  'Video Tutorial Gallery': 'Galeri Video Tutorial',
  'Asset Management': 'Manajemen Aset',
  Dashboard: 'Dashboard',
  'Asset Categories': 'Kategori Aset',
  Assets: 'Aset',
  Transfers: 'Transfer',
  'Maintenance Schedules': 'Jadwal Perawatan',
  Maintenances: 'Perawatan',
  Disposals: 'Pembuangan',
  Documents: 'Dokumen',
  Depreciations: 'Depresiasi',
  Reports: 'Laporan',
  'Lost & Breakage': 'Hilang & Rusak',
  'Asset Good Receive': 'Penerimaan Barang Aset',
  'Asset Inventory Transfer': 'Transfer Inventori Aset',
  'Asset Stock Adjustment': 'Penyesuaian Stok Aset',
  'Asset Service': 'Servis Aset',
  'Asset Disposal': 'Pembuangan Aset',
  'Asset Inventory Report': 'Laporan Inventori Aset',
  'Saldo Awal Stock Asset': 'Saldo Awal Stok Aset',
  'Master Data': 'Data Master',
  Categories: 'Kategori',
  'Sub Category': 'Sub Kategori',
  Units: 'Satuan',
  Items: 'Barang',
  Repack: 'Repack',
  'Menu Type': 'Tipe Menu',
  Modifiers: 'Modifier',
  'Modifier Options': 'Opsi Modifier',
  Warehouses: 'Gudang',
  'Warehouse Outlet': 'Gudang Outlet',
  'Warehouse Division': 'Divisi Gudang',
  Outlets: 'Outlet',
  Customers: 'Pelanggan',
  Suppliers: 'Supplier',
  Regions: 'Wilayah',
  'Item Schedule': 'Jadwal Barang',
  'RO Schedule': 'Jadwal RO',
  'Items Supplier': 'Supplier Barang',
  'Master MoQ': 'Master MoQ',
  'Data Investor Outlet': 'Data Investor Outlet',
  'Officer Check': 'Cek Petugas',
  'Jenis Pembayaran': 'Jenis Pembayaran',
  'Video Tutorial': 'Video Tutorial',
  'Group Video Tutorial': 'Grup Video Tutorial',
  'Locked Budget Food Categories': 'Kategori Budget Food Terkunci',
  'Budget Management': 'Manajemen Budget',
  'Chart of Account': 'Bagan Akun',
  'Master Data Bank': 'Data Master Bank',
  'Quality Assurance': 'Jaminan Kualitas',
  'QA Categories': 'Kategori QA',
  'QA Parameters': 'Parameter QA',
  'QA Guidance': 'Panduan QA',
  Inspections: 'Inspeksi',
  'Ops Management': 'Manajemen Operasional',
  'Master Daily Report': 'Master Laporan Harian',
  'Daily Report': 'Laporan Harian',
  'Ticketing System': 'Sistem Tiket',
  'PR Tracking Report': 'Laporan Pelacakan PR',
  'RO vs Forecast Harian': 'RO vs Forecast Harian',
  'Human Resource': 'Sumber Daya Manusia',
  'Data Level': 'Data Level',
  'Kategori BPJS': 'Kategori BPJS',
  'Data Jabatan': 'Data Jabatan',
  'Data Divisi': 'Data Divisi',
  'Data Karyawan': 'Data Karyawan',
  'Saldo Cuti & Extra Off': 'Saldo Cuti & Extra Off',
  'Report Transaksi Cuti': 'Laporan Transaksi Cuti',
  'Regional Management': 'Manajemen Regional',
  'Report Man Power Outlet': 'Laporan Man Power Outlet',
  'Job Vacancy': 'Lowongan Kerja',
  'Master Data Outlet': 'Data Master Outlet',
  'Master Jam Kerja': 'Master Jam Kerja',
  'Input Shift Mingguan': 'Input Shift Mingguan',
  'Kalender Jadwal Shift': 'Kalender Jadwal Shift',
  'Schedule/Attendance Correction': 'Koreksi Jadwal/Absensi',
  'Report Schedule/Attendance Correction': 'Laporan Koreksi Jadwal/Absensi',
  'Report Absent': 'Laporan Absen',
  'Libur Nasional': 'Libur Nasional',
  'Report Attendance': 'Laporan Absensi',
  'Attendance per Outlet': 'Absensi per Outlet',
  'Holiday Attendance': 'Absensi Hari Libur',
  'Extra Off & PH Report': 'Laporan Extra Off & PH',
  'Master Payroll': 'Master Payroll',
  Payroll: 'Payroll',
  'Employee Movement': 'Mutasi Karyawan',
  'Employee Resignation': 'Resign Karyawan',
  'Outlet/HO Inspection': 'Inspeksi Outlet/HO',
  Coaching: 'Coaching',
  'Employee Survey': 'Survei Karyawan',
  'Employee Survey Report': 'Laporan Survei Karyawan',
  'Master Soal': 'Master Soal',
  'Enroll Test': 'Daftar Tes',
  'My Tests': 'Tes Saya',
  'Report Hasil Test': 'Laporan Hasil Tes',
  'Manajemen Cuti': 'Manajemen Cuti',
  'Report Travel & Kasbon': 'Laporan Travel & Kasbon',
  'Report Kasbon': 'Laporan Kasbon',
  'Outlet Management': 'Manajemen Outlet',
  'Dashboard Sales Outlet': 'Dashboard Penjualan Outlet',
  'POS Design Sync Monitor': 'Monitor Sinkronisasi Desain POS',
  'Laporan Void Bill POS': 'Laporan Void Bill POS',
  'Request Order (RO)': 'Request Order (RO)',
  'Outlet Good Receive': 'Penerimaan Barang Outlet',
  'GR Nomor Seri': 'GR Nomor Seri',
  'Good Receive Outlet Supplier': 'Penerimaan Barang Outlet Supplier',
  'Outlet Stock Adjustment': 'Penyesuaian Stok Outlet',
  'Laporan Stok Akhir Outlet': 'Laporan Stok Akhir Outlet',
  'Saldo Awal Stok Outlet': 'Saldo Awal Stok Outlet',
  'Kartu Stok Outlet': 'Kartu Stok Outlet',
  'Laporan Nilai Persediaan Outlet': 'Laporan Nilai Persediaan Outlet',
  'Laporan Rekap Persediaan per Kategori Outlet': 'Laporan Rekap Persediaan per Kategori Outlet',
  'Category Cost Outlet': 'Biaya Kategori Outlet',
  'Outlet Transfer': 'Transfer Outlet',
  'Internal Warehouse Transfer': 'Transfer Gudang Internal',
  'Retail Food': 'Retail Food',
  'Retail Non Food': 'Retail Non Food',
  'Outlet Food Return': 'Retur Makanan Outlet',
  'Stock Opname': 'Stock Opname',
  'Report Invoice Outlet': 'Laporan Invoice Outlet',
  'Stock Cut': 'Stock Cut',
  'Outlet WIP Production': 'Produksi WIP Outlet',
  'Laporan Outlet WIP': 'Laporan WIP Outlet',
  'Outlet Report': 'Laporan Outlet',
  'Sales Report': 'Laporan Penjualan',
  'Opex Outlet Dashboard': 'Dashboard Opex Outlet',
  'Daily Outlet Revenue': 'Pendapatan Harian Outlet',
  'Weekly Outlet FB Revenue': 'Pendapatan FB Mingguan Outlet',
  'Daily Revenue Forecast': 'Forecast Pendapatan Harian',
  'Monthly FB Revenue Performance': 'Kinerja Pendapatan FB Bulanan',
  'Receiving Sheet': 'Lembar Penerimaan',
  'Item Engineering': 'Item Engineering',
  'HO Finance': 'Keuangan HO',
  Jurnal: 'Jurnal',
  'Buku Besar': 'Buku Besar',
  'Neraca Saldo': 'Neraca Saldo',
  'Laporan Arus Kas': 'Laporan Arus Kas',
  'Contra Bon': 'Contra Bon',
  'Food Payment': 'Pembayaran Food',
  'Non Food Payment': 'Pembayaran Non Food',
  'Retail Non Food Payment': 'Pembayaran Retail Non Food',
  'OPEX Report': 'Laporan OPEX',
  'OPEX By Category': 'OPEX per Kategori',
  'Outlet Payments': 'Pembayaran Outlet',
  'Buku Bank': 'Buku Bank',
  'Report Penjualan Pivot per Outlet per Sub Kategori': 'Laporan Penjualan Pivot per Outlet per Sub Kategori',
  'Report Rekap FJ': 'Laporan Rekap FJ',
  'Rekap PB1 Outlet': 'Rekap PB1 Outlet',
  'Report Hutang': 'Laporan Hutang',
  Purchasing: 'Pembelian',
  'Purchase Order Foods': 'Purchase Order Foods',
  'Purchase Order Ops': 'Purchase Order Ops',
  'Report PO GR': 'Laporan PO GR',
  'Report Purchase Order Ops': 'Laporan Purchase Order Ops',
  'Warehouse Management': 'Manajemen Gudang',
  'Purchase Requisition Foods': 'Purchase Requisition Foods',
  'Good Receive': 'Penerimaan Barang',
  'Food Good Receive Report': 'Laporan Penerimaan Barang Food',
  'Pindah Gudang': 'Pindah Gudang',
  'Stock Adjustment': 'Penyesuaian Stok',
  'Packing List': 'Packing List',
  'Delivery Order': 'Delivery Order',
  'Penjualan Warehouse Retail': 'Penjualan Warehouse Retail',
  'Warehouse Retail Food': 'Warehouse Retail Food',
  'Saldo Awal Stok': 'Saldo Awal Stok',
  'Laporan Stok Akhir': 'Laporan Stok Akhir',
  'Laporan Kartu Stok': 'Laporan Kartu Stok',
  'Laporan Penerimaan Barang': 'Laporan Penerimaan Barang',
  'Laporan Nilai Persediaan': 'Laporan Nilai Persediaan',
  'Laporan Riwayat Perubahan Harga Pokok': 'Laporan Riwayat Perubahan Harga Pokok',
  'Laporan Stok Minimum': 'Laporan Stok Minimum',
  'Laporan Rekap Persediaan per Kategori': 'Laporan Rekap Persediaan per Kategori',
  'Laporan Aging Persediaan': 'Laporan Aging Persediaan',
  'Internal Use & Waste': 'Pemakaian Internal & Sampah',
  'Penjualan Antar Gudang': 'Penjualan Antar Gudang',
  'Outlet Rejection': 'Penolakan Outlet',
  'Kelola Return Outlet': 'Kelola Retur Outlet',
  'Cost Control': 'Kontrol Biaya',
  'Laporan Perubahan Harga PO': 'Laporan Perubahan Harga PO',
  'MAC Report': 'Laporan MAC',
  'Outlet MAC Tracking': 'Pelacakan MAC Outlet',
  'Warehouse MAC Tracking': 'Pelacakan MAC Gudang',
  'Tracking Nomor Seri': 'Pelacakan Nomor Seri',
  'Outlet Stock Report': 'Laporan Stok Outlet',
  'Cost Report': 'Laporan Biaya',
  'Cost Report HO': 'Laporan Biaya HO',
  'Report Pembelanjaan Supplier (Warehouse GR)': 'Laporan Pembelanjaan Supplier (GR Gudang)',
  'Report RnD, BM, WM': 'Laporan RnD, BM, WM',
  'Report Penjualan per Category': 'Laporan Penjualan per Kategori',
  'Report Penjualan per Tanggal': 'Laporan Penjualan per Tanggal',
  'Report Penjualan All Item ke All Outlet': 'Laporan Penjualan Semua Item ke Semua Outlet',
  'Report Good Receive Outlet': 'Laporan Penerimaan Barang Outlet',
  'Report Retail Food per Supplier': 'Laporan Retail Food per Supplier',
  'Stock Opname Adjustment Report': 'Laporan Penyesuaian Stock Opname',
  'Cek Resep BOM': 'Cek Resep BOM',
  'Report Rekap Diskon': 'Laporan Rekap Diskon',
  Production: 'Produksi',
  Butcher: 'Butcher',
  'Butcher Report': 'Laporan Butcher',
  'Laporan Stok & Cost Butcher': 'Laporan Stok & Biaya Butcher',
  'Laporan Analisis Butcher': 'Laporan Analisis Butcher',
  'Summary Hasil Butcher': 'Ringkasan Hasil Butcher',
  'MK Production': 'Produksi MK',
  'Laporan MK Production': 'Laporan Produksi MK',
  'OPS-Kitchen': 'OPS-Kitchen',
  'Action Plan Guest Review': 'Action Plan Ulasan Tamu',
  'Sales & Marketing': 'Penjualan & Marketing',
  'Scrapper Google Review': 'Scraper Google Review',
  Promo: 'Promo',
  'Marketing Visit Checklist': 'Checklist Kunjungan Marketing',
  Reservasi: 'Reservasi',
  'Data Roulette': 'Data Roulette',
  'Menu Book': 'Buku Menu',
  'Web Profile': 'Profil Web',
  'Rekap Transaksi Bank': 'Rekap Transaksi Bank',
  'Revenue Targets': 'Target Pendapatan',
  'User Management': 'Manajemen Pengguna',
  'Role Management': 'Manajemen Role',
  'User Role Setting': 'Pengaturan Role Pengguna',
  'Menu Management': 'Manajemen Menu',
  Support: 'Dukungan',
  'Support Admin Panel': 'Panel Admin Dukungan',
  'Monitoring User Aktif': 'Monitoring Pengguna Aktif',
  'Server Performance Monitoring': 'Monitoring Performa Server',
  'Activity Log Report': 'Laporan Log Aktivitas',
  'CCTV Access Request': 'Permintaan Akses CCTV',
  Announcement: 'Pengumuman',
  CRM: 'CRM',
  'Data Member': 'Data Member',
  'Guest Comment (OCR)': 'Komentar Tamu (OCR)',
  'Customer Voice Command Center': 'Pusat Komando Suara Pelanggan',
  'Kirim Notifikasi Member': 'Kirim Notifikasi Member',
  'Inject Point Manual': 'Injeksi Poin Manual',
  'Member Apps Settings': 'Pengaturan Aplikasi Member',
  LMS: 'LMS',
  'Kategori Training': 'Kategori Pelatihan',
  Training: 'Pelatihan',
  Quiz: 'Kuis',
  Kuesioner: 'Kuesioner',
  'Template Sertifikat': 'Template Sertifikat',
  'Jadwal Training': 'Jadwal Pelatihan',
  'Trainer Report': 'Laporan Trainer',
  'Laporan Training Karyawan': 'Laporan Pelatihan Karyawan',
  'Training Report': 'Laporan Pelatihan',
  'Quiz Report': 'Laporan Kuis',
};

function toKey(str) {
  const base = str
    .replace(/route\([^)]*\)/g, '')
    .replace(/[^a-zA-Z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .toLowerCase();
  return base || 'item';
}

function usedKeys() {
  return new Set();
}

const keyRegistry = new Map();

function uniqueKey(str, prefix) {
  let key = toKey(str);
  let full = `${prefix}.${key}`;
  let i = 2;
  while (keyRegistry.has(full)) {
    key = `${toKey(str)}_${i}`;
    full = `${prefix}.${key}`;
    i++;
  }
  keyRegistry.set(full, str);
  return key;
}

let content = fs.readFileSync(appLayoutPath, 'utf8');
const start = content.indexOf('const menuGroups = [');
const end = content.indexOf('\n];', start) + 3;
const block = content.slice(start, end);

const groups = {};
const menus = {};

// group titles (non-t)
for (const m of block.matchAll(/title: \(\) => '([^']+)'/g)) {
  const text = m[1];
  const key = uniqueKey(text, 'groups');
  groups[key.split('.').pop()] = { en: text, id: EN_TO_ID[text] ?? text };
}

// menu names (non-t)
for (const m of block.matchAll(/name: \(\) => '([^']+)'/g)) {
  const text = m[1];
  const key = uniqueKey(text, 'menus');
  menus[key.split('.').pop()] = { en: text, id: EN_TO_ID[text] ?? text };
}

// Patch AppLayout: replace title/name literals with t()
let newBlock = block;
for (const [full, text] of keyRegistry) {
  const [prefix, key] = full.split('.');
  const tKey = `sidebar.${prefix}.${key}`;
  if (prefix === 'groups') {
    newBlock = newBlock.replace(`title: () => '${text.replace(/'/g, "\\'")}'`, `title: () => t('${tKey}')`);
  } else {
    newBlock = newBlock.replace(`name: () => '${text.replace(/'/g, "\\'")}'`, `name: () => t('${tKey}')`);
  }
}

content = content.slice(0, start) + newBlock + content.slice(end);
fs.writeFileSync(appLayoutPath, content);

function sortObj(obj) {
  return Object.fromEntries(Object.keys(obj).sort().map((k) => [k, obj[k]]));
}

const idSidebar = {
  groups: Object.fromEntries(
    Object.entries(groups).map(([k, v]) => [k, v.id]),
  ),
  menus: Object.fromEntries(
    Object.entries(menus).map(([k, v]) => [k, v.id]),
  ),
};

const enSidebar = {
  groups: Object.fromEntries(
    Object.entries(groups).map(([k, v]) => [k, v.en]),
  ),
  menus: Object.fromEntries(
    Object.entries(menus).map(([k, v]) => [k, v.en]),
  ),
};

const idPath = path.join(root, 'resources/js/lang/sidebar-id.js');
const enPath = path.join(root, 'resources/js/lang/sidebar-en.js');

fs.writeFileSync(
  idPath,
  `// Auto-generated sidebar translations (ID)\nexport default ${JSON.stringify(sortObj(idSidebar), null, 2)};\n`,
);
fs.writeFileSync(
  enPath,
  `// Auto-generated sidebar translations (EN)\nexport default ${JSON.stringify(sortObj(enSidebar), null, 2)};\n`,
);

console.log(`Generated ${Object.keys(groups).length} groups, ${Object.keys(menus).length} menus`);
console.log('Updated AppLayout.vue');
console.log('Wrote', idPath, enPath);
