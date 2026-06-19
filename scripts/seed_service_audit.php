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
    'notes' => 'Seed from Service Audit template screenshots (updated Jun 2026)',
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

$parameters = [
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.1', 'text' => 'MENYAMBUT TAMU : Tamu disambut maksimal ≤5 detik pada saat memasuki area greeter dengan senyum, kontak mata, salam sesuai standar, dan bahasa tubuh positif. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.2', 'text' => 'MENANYAKAN RESERVASI TAMU : Staff menanyakan status reservasi secara sopan. Guide Line: 1. Penanganan tamu yang telah melakukan reservasi: Verifikasi reservasi tamu: Staff menanyakan nama pemesan, nomor telepon, waktu reservasi, dan jumlah tamu sesuai data reservasi yang tercatat. Konfirmasi detail reservasi: Staff memastikan kembali jumlah tamu, kebutuhan khusus, preferensi area duduk, serta permintaan khusus yang telah dicatat sebelumnya. Penanganan tamu datang lebih awal: Apabila tamu datang sebelum waktu reservasi dan meja belum siap, staff menyampaikan estimasi waktu tunggu sopan serta menawarkan area tunggu yang nyaman. 2. Penanganan tamu yang belum melakukan reservasi (walk-in): Penanganan kebutuhan tamu: Staff menanyakan jumlah tamu, preferensi area duduk (smoking/non-smoking apabila tersedia), serta melakukan pengecekan ketersediaan meja sesuai kapasitas. Waiting list: Apabila seluruh meja penuh, staff mencatat nama tamu, nomor kontak, jumlah tamu, dan estimasi waktu tunggu pada daftar tunggu (waiting list). Menawarkan area tunggu: Staff mempersilahkan tamu menunggu di area yang telah ditentukan dengan tetap memperhatikan kenyamanan tamu. Follow up waiting list: Staff memanggil tamu sesuai urutan waiting list dan memastikan meja telah siap sebelum mengantar tamu. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.3', 'text' => 'MENGANTARKAN TAMU KE MEJA : Tamu diantar dan disambut sesuai standar, maksimal ≤5 detik menuju meja yang sesuai kapasitas dan preferensi dengan posisi staff memimpin tamu. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.4', 'text' => 'MEMPERSILAHKAN TAMU DUDUK : Staff membantu menarik kursi apabila diperlukan dan mempersilahkan tamu duduk dengan sopan. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.5', 'text' => 'MEMBERIKAN BUKU MENU : Buku menu diberikan kepada tamu dalam kondisi bersih dan lengkap maksimal ≤1 menit setelah tamu duduk. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 50],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.6', 'text' => 'MENANYAKAN MEMBER JUSTUS : Staff menanyakan apakah tamu telah terdaftar sebagai Member Justus. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.7', 'text' => 'MENANYAKAN POIN MEMBER JUSTUS : Staff menanyakan penggunaan poin Member Justus yang dimiliki tamu. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 70],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.8', 'text' => 'MENANYAKAN JOIN MEMBER JUSTUS : Staff menawarkan program pendaftaran Member Justus kepada tamu yang belum bergabung serta menjelaskan manfaat utamanya. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 80],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.9', 'text' => 'MENGINFORMASIKAN PROMO BANK : Staff menjelaskan promo pembayaran bank yang sedang berlaku beserta syarat dan ketentuannya. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 90],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.10', 'text' => 'MENGINFORMASIKAN MENU PROMO : Staff menyampaikan menu promo yang tersedia secara jelas dan lengkap. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 100],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.11', 'text' => 'TAKING ORDER : Staff mencatat pesanan secara lengkap, fokus, menggunakan standar komunikasi yang baik, dan tidak memotong pembicaraan tamu. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 110],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.12', 'text' => 'SUGGESTIVE SELLING & UPSELLING : Staff menawarkan produk tambahan atau upgrade yang relevan minimal 1 kali kepada tamu. (SOP/2025/Justus SH/01)', 'weight' => 30, 'sort_order' => 120],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.13', 'text' => 'REPEAT ORDER : Staff mengulangi seluruh pesanan tamu untuk memastikan akurasi. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 130],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.14', 'text' => 'MENANYAKAN ID MEMBER JUSTUS : Staff meminta nomor ID Member Justus untuk keperluan input transaksi. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 140],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.15', 'text' => 'MENGINFORMASIKAN WAKTU PENYAJIAN : Staff menyampaikan estimasi waktu penyajian sesuai standar produk yang dipesan. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 150],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.16', 'text' => 'INPUT PESANAN KE POS : Pesanan diinput ke POS maksimal ≤2 menit setelah taking order selesai tanpa kesalahan input. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 160],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.17', 'text' => 'ADJUSTMENT (SET UP ALAT SAJI) : Alat saji disiapkan sesuai jenis menu yang dipesan sebelum makanan atau minuman tersaji. (SOP/2025/Justus SH/01)', 'weight' => 30, 'sort_order' => 170],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.18', 'text' => 'MELETAKAN CHECKMARK : Check mark di print dan diletakan di meja tamu sesuai standar. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 180],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.19', 'text' => 'MENGINFORMASIKAN PESANAN MELALUI CHECK MARK : Staff meminta tamu untuk melakukan pengecekan ulang kesesuaian pesanan menggunakan check mark sebelum penyajian. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 190],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.20', 'text' => 'MENYAJIKAN MINUMAN : Minuman disajikan sesuai urutan pelayanan, kondisi produk baik, dan identitas pesanan tepat. (SOP/2025/Justus SH/01). Guide Line: Karyawan tidak menyentuh bagian atas/bibir gelas. Jika ada ketidaksesuaian dengan minuman yang akan disajikan ke customer, karyawan checker service tidak memperbaiki/menangani minuman langsung, tetapi langsung dikembalikan ke bar/pantry.', 'weight' => 50, 'sort_order' => 200],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.21', 'text' => 'MEMPERSILAHKAN MENIKMATI MINUMAN : Staff mempersilahkan tamu menikmati minuman dengan sopan. (SOP/2025/Justus SH/01)', 'weight' => 30, 'sort_order' => 210],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.22', 'text' => 'MENCORET PRODUK PADA CHECK MARK : Produk yang telah tersaji dicoret/ditandai pada check mark secara konsisten. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 220],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.23', 'text' => 'MENYAJIKAN MENU PEMBUKA : Menu pembuka; Salad/Soup/Light Meals disajikan sesuai standar waktu dan kepada tamu yang tepat. (SOP/2025/Justus SH/01). Guide Line: Karyawan tidak menyentuh bagian dalam plate ketika membawa makanan. Ketika membawa lebih dari satu plate dalam satu tangan, bagian bawah plate yang satu tidak menyentuh bagian atas plate lainnya. Jika ada ketidaksesuaian dengan makanan yang akan disajikan ke customer, karyawan checker service tidak memperbaiki/menangani makanan langsung tetapi langsung dikembalikan ke kitchen.', 'weight' => 50, 'sort_order' => 230],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.24', 'text' => 'MEMPERSILAHKAN TAMU MENIKMATI HIDANGAN : Staff menyampaikan ucapan yang mempersilahkan tamu menikmati hidangan. (SOP/2025/Justus SH/01)', 'weight' => 30, 'sort_order' => 240],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.25', 'text' => 'MENANYAKAN PENYAJIAN MENU UTAMA : Staff meminta konfirmasi apakah menu utama dapat disajikan setelah appetizer selesai atau sesuai keinginan tamu. (SOP/2025/Justus SH/01)', 'weight' => 30, 'sort_order' => 250],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.26', 'text' => 'CLEAR UP MENU PEMBUKA : Peralatan appetizer dibersihkan maksimal ≤3 menit setelah selesai digunakan atau setelah mendapat izin tamu. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 260],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.27', 'text' => 'MENYAJIKAN MENU UTAMA : Menu utama disajikan sesuai urutan, kondisi produk baik, dan kepada tamu yang tepat. (SOP/2025/Justus SH/01). Guide Line: Karyawan tidak menyentuh bagian dalam plate ketika membawa makanan. Ketika membawa lebih dari satu plate dalam satu tangan, bagian bawah plate yang satu tidak menyentuh bagian atas plate lainnya. Jika ada ketidaksesuaian dengan makanan yang akan disajikan ke customer, karyawan checker service tidak memperbaiki/menangani makanan langsung tetapi langsung dikembalikan ke kitchen.', 'weight' => 50, 'sort_order' => 270],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.28', 'text' => 'MENGINFORMASIKAN TINGKAT KEMATANGAN : Staff menjelaskan apabila tingkat kematangan steak tidak sesuai, untuk dapat menginformasikan kepada staff restoran. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 280],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.29', 'text' => 'MEMPERSILAHKAN TAMU MENIKMATI HIDANGAN : Staff menyampaikan ucapan yang mempersilahkan tamu menikmati hidangan. (SOP/2025/Justus SH/01)', 'weight' => 30, 'sort_order' => 290],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.30', 'text' => 'MEMASTIKAN SELURUH PESANAN TERSAJI : Staff memastikan seluruh item pesanan telah diterima tamu tanpa ada yang tertinggal. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 300],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.31', 'text' => 'MENAWARKAN BANTUAN LAIN : Staff menawarkan bantuan tambahan atau kebutuhan lain kepada tamu setelah penyajian selesai. (SOP/2025/Justus SH/01)', 'weight' => 30, 'sort_order' => 310],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.32', 'text' => 'MENCORET PRODUK PADA CHECK MARK : Seluruh item yang telah tersaji ditandai pada check mark hingga status pesanan lengkap. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 320],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.33', 'text' => 'VISIT TABLE & MARKETING PROMO : Staff melakukan table visit dalam 3–5 menit setelah menu utama tersaji untuk memastikan kepuasan tamu serta menyampaikan program Discount terkait IG, photo/selfie/ post & Testimonial (apabila tersedia). (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 330],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.34', 'text' => 'CLEAR UP MENU UTAMA : Peralatan makan dibersihkan setelah selesai digunakan dengan meminta izin kepada tamu terlebih dahulu. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 340],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.35', 'text' => 'MENAWARKAN DESSERT/HOT COFFEE/TEA : Staff menawarkan dessert, kopi, atau teh sebagai closing upselling. (SOP/2025/Justus SH/01)', 'weight' => 30, 'sort_order' => 350],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.36', 'text' => 'MEMBERIKAN GUEST COMMENT/GOOGLE REVIEW : Staff mengajak tamu memberikan guest comment atau Google Review secara sopan tanpa memaksa. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 360],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.37', 'text' => 'MEMBERIKAN BILL : Bill disajikan dengan akurat, bersih, dan dalam holder sesuai permintaan tamu maksimal ≤5 menit setelah diminta. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 370],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.38', 'text' => 'MENGUCAPKAN TERIMA KASIH : Staff mengucapkan terima kasih, menyebut nama tamu (jika diketahui), dan mengundang tamu untuk berkunjung kembali. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 380],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.39', 'text' => 'MENGINGATKAN BARANG BAWAAN : Staff mengingatkan tamu untuk memeriksa kembali barang bawaan sebelum meninggalkan meja/restoran. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 390],
    ['sc' => 'SVA-S1-1', 'code' => 'SVA-1.40', 'text' => 'MEMBERSIHKAN & SET UP MEJA : Meja dibersihkan, disanitasi, dan dilakukan reset sesuai standar maksimal ≤5 menit setelah tamu meninggalkan meja dan area bawah meja bersih bebas sampah, debu, dan sisa makanan. (SOP/2025/Justus SH/01)', 'weight' => 50, 'sort_order' => 400],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.1', 'text' => 'PENAMPILAN & GROOMING : Uniform lengkap, bersih, rapi, name tag digunakan, rambut tertata, kebersihan diri terjaga sesuai standar perusahaan.', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.2', 'text' => 'KERAMAHAN SAAT MENYAMBUT : Staff menyapa tamu dengan senyum tulus, salam, kontak mata, dan nada bicara yang ramah.', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.3', 'text' => 'ANTUSIASME PELAYANAN : Staff menunjukkan sikap positif, energik, dan antusias selama melayani tamu.', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.4', 'text' => 'BAHASA TUBUH : Berdiri tegap, tidak melipat tangan, tidak memasukkan tangan ke saku, dan tidak menunjukkan ekspresi tidak menyenangkan.', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.5', 'text' => 'PENGGUNAAN BAHASA YANG SOPAN : Menggunakan kata-kata yang santun, profesional, dan mudah dipahami tamu.', 'weight' => 50, 'sort_order' => 50],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.6', 'text' => 'KEMAMPUAN MENDENGARKAN : Staff mendengarkan kebutuhan tamu tanpa memotong pembicaraan dan memberikan perhatian penuh.', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.7', 'text' => 'KONTAK MATA : Melakukan kontak mata yang wajar saat berinteraksi dengan tamu.', 'weight' => 50, 'sort_order' => 70],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.8', 'text' => 'PENGGUNAAN NAMA TAMU : Menyebut nama tamu apabila diketahui untuk menciptakan pengalaman yang lebih personal.', 'weight' => 50, 'sort_order' => 80],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.9', 'text' => 'RESPONSIVITAS : Tanggap terhadap panggilan atau permintaan tamu maksimal ≤1 menit.', 'weight' => 50, 'sort_order' => 90],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.10', 'text' => 'INISIATIF MEMBANTU : Secara proaktif menawarkan bantuan tanpa harus diminta oleh tamu.', 'weight' => 30, 'sort_order' => 100],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.11', 'text' => 'PRODUCT KNOWLEDGE : Memahami menu, promo, dan program member dengan benar.', 'weight' => 50, 'sort_order' => 110],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.12', 'text' => 'KEMAMPUAN MENJAWAB PERTANYAAN : Memberikan informasi yang akurat dan tidak memberikan jawaban yang bersifat asumsi.', 'weight' => 50, 'sort_order' => 120],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.13', 'text' => 'MEMAHAMI KEBUTUHAN TAMU : Mampu mengidentifikasi preferensi atau kebutuhan khusus tamu.', 'weight' => 30, 'sort_order' => 130],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.14', 'text' => 'PENANGANAN TAMU ANAK-ANAK : Menunjukkan perhatian dan keramahan terhadap tamu anak-anak serta keluarga.', 'weight' => 50, 'sort_order' => 140],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.15', 'text' => 'PENANGANAN TAMU LANSIA/DISABILITAS : Memberikan bantuan tambahan sesuai kebutuhan tamu secara hormat dan sigap.', 'weight' => 50, 'sort_order' => 150],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.16', 'text' => 'EMPATI TERHADAP TAMU : Menunjukkan kepedulian terhadap kondisi atau situasi yang dialami tamu.', 'weight' => 50, 'sort_order' => 160],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.17', 'text' => 'KECEPATAN PELAYANAN : Pelayanan diberikan sesuai standar waktu yang telah ditetapkan.', 'weight' => 50, 'sort_order' => 170],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.18', 'text' => 'AKURASI PELAYANAN : Tidak terjadi kesalahan informasi, pesanan, maupun transaksi.', 'weight' => 50, 'sort_order' => 180],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.19', 'text' => 'PENANGANAN KELUHAN : Keluhan ditangani dengan tenang, empati, meminta maaf, dan mencari solusi.', 'weight' => 50, 'sort_order' => 190],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.20', 'text' => 'KONSISTENSI PELAYANAN : Standar keramahan dan kualitas pelayanan diberikan kepada seluruh tamu tanpa diskriminasi.', 'weight' => 50, 'sort_order' => 200],
    ['sc' => 'SVA-S2-1', 'code' => 'SVA-2.21', 'text' => 'PERHATIAN TERHADAP DETAIL : Memperhatikan kebersihan meja, kelengkapan alat saji, dan kenyamanan tamu.', 'weight' => 50, 'sort_order' => 210],
    ['sc' => 'SVA-S3-1', 'code' => 'SVA-3.1.1', 'text' => 'Kebersihan lantai area tamu : Lantai bersih, tidak lengket, tidak berdebu, tidak terdapat sisa makanan atau minuman. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'SVA-S3-1', 'code' => 'SVA-3.1.2', 'text' => 'Kondisi meja makan : Meja bersih, kering, bebas noda, tidak lengket, tidak rusak dan tidak goyang. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'SVA-S3-1', 'code' => 'SVA-3.1.3', 'text' => 'Kondisi kursi tamu : Kursi bersih, stabil, tidak rusak, bebas debu dan noda. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'SVA-S3-1', 'code' => 'SVA-3.1.4', 'text' => 'Kebersihan sofa : Bebas debu, noda, remah makanan, dan tidak berbau. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'SVA-S3-1', 'code' => 'SVA-3.1.5', 'text' => 'Kebersihan condiment set & tent card : Bersih, terisi sesuai standar, tidak lengket, dan tertata rapi. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 50],
    ['sc' => 'SVA-S3-1', 'code' => 'SVA-3.1.6', 'text' => 'Kebersihan buku menu : Buku menu bersih, tidak sobek, tidak berminyak, dan mudah dibaca. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'SVA-S3-1', 'code' => 'SVA-3.1.7', 'text' => 'Kebersihan dekorasi : Bebas debu, sarang laba-laba, dan noda. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 70],
    ['sc' => 'SVA-S3-1', 'code' => 'SVA-3.1.8', 'text' => 'Kebersihan kaca/jendela : Bersih, tidak buram, bebas sidik jari dan noda. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 80],
    ['sc' => 'SVA-S3-1', 'code' => 'SVA-3.1.9', 'text' => 'Kebersihan pintu masuk : Bersih, tidak terdapat bekas tangan berlebihan atau kotoran. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 90],
    ['sc' => 'SVA-S3-2', 'code' => 'SVA-3.2.1', 'text' => 'Kebersihan counter cashier : Bersih, tertata rapi, bebas barang pribadi. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'SVA-S3-2', 'code' => 'SVA-3.2.2', 'text' => 'Kebersihan mesin EDC/POS : Bersih, berfungsi baik, bebas debu. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'SVA-S3-2', 'code' => 'SVA-3.2.3', 'text' => 'Kebersihan area tunggu : Kursi, lantai, balon area tunggu bersih dan tertata. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'SVA-S3-2', 'code' => 'SVA-3.2.4', 'text' => 'Tempat promosi/display : Bersih, tertata, dan tidak rusak. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'SVA-S3-3', 'code' => 'SVA-3.3.1', 'text' => 'Kebersihan lantai toilet : Kering, bersih, tidak licin, dan tidak berbau. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'SVA-S3-3', 'code' => 'SVA-3.3.2', 'text' => 'Kebersihan closet/urinoir : Bersih, bebas kerak, noda, dan berfungsi baik. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'SVA-S3-3', 'code' => 'SVA-3.3.3', 'text' => 'Ketersediaan tissue toilet : Tissue tersedia dan mencukupi. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'SVA-S3-3', 'code' => 'SVA-3.3.4', 'text' => 'Ketersediaan sabun cuci tangan : Sabun tersedia dan dapat digunakan. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'SVA-S3-3', 'code' => 'SVA-3.3.5', 'text' => 'Ketersediaan alat hygiene & sanitasi : Digital sanitizer urinoir/closer, sanitary bin, tersedia dan berfungsi. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 50],
    ['sc' => 'SVA-S3-3', 'code' => 'SVA-3.3.6', 'text' => 'Kebersihan wastafel : Bersih, tidak tersumbat, bebas noda air. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'SVA-S3-3', 'code' => 'SVA-3.3.7', 'text' => 'Kebersihan cermin : Bersih, bebas bercak dan sidik jari. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 70],
    ['sc' => 'SVA-S3-3', 'code' => 'SVA-3.3.8', 'text' => 'Tempat sampah toilet : Bersih, tidak penuh (maksimal ¾ kapasitas tempat sampah). menggunakan liner. (SOP-QA-009)', 'weight' => 50, 'sort_order' => 80],
    ['sc' => 'SVA-S3-3', 'code' => 'SVA-3.3.9', 'text' => 'Aroma toilet : Tidak terdapat bau tidak sedap, digital sprayer (pengharum ruangan) tersedia dan berfungsi. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 90],
    ['sc' => 'SVA-S3-3', 'code' => 'SVA-3.3.10', 'text' => 'Checklist toilet : Checklist monitoring tersedia dan terisi sesuai jadwal. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 100],
    ['sc' => 'SVA-S3-4', 'code' => 'SVA-3.4.1', 'text' => 'Kebersihan service station : Permukaan bersih, kering, bebas debu, noda, dan sisa makanan/minuman dan tidak terdapat barang pribadi atau barang lain yang tidak diperlukan. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'SVA-S3-4', 'code' => 'SVA-3.4.2', 'text' => 'Kerapihan & penyimpan alat saji : Seluruh perlengkapan disimpan rapi sesuai lokasi yang telah ditentukan dan disimpan terlindung dari debu dan potensi kontaminasi. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'SVA-S3-4', 'code' => 'SVA-3.4.3', 'text' => 'Kebersihan alat saji : Terlindung dari kontaminasi, bersih, bebas noda, debu, sidik jari, karat, tidak berbau amis, apek, atau bau chemical. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'SVA-S3-4', 'code' => 'SVA-3.4.4', 'text' => 'Kebersihan tray dan alat penunjang lainnya : Bersih, bebas noda & debu dan dalam kondisi baik. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'SVA-S3-4', 'code' => 'SVA-3.4.5', 'text' => 'Ketersediaan alat penunjang : Jumlah mencukupi sesuai kebutuhan operasional. (SOP-QA-005)', 'weight' => 30, 'sort_order' => 50],
    ['sc' => 'SVA-S3-4', 'code' => 'SVA-3.4.6', 'text' => 'Sampah dan sisa makanan/minuman pada serving equipment dipisahkan dan dibuang sesuai kategori sampah. (SOP-QA-023)', 'weight' => 30, 'sort_order' => 60],
    ['sc' => 'SVA-S3-4', 'code' => 'SVA-3.4.7', 'text' => 'Serving equipment dipisahkan dengan menggunakan sistem decoy guna mendukung efektifitas proses pencucian. (SOP-QA-023)', 'weight' => 30, 'sort_order' => 70],
    ['sc' => 'SVA-S3-5', 'code' => 'SVA-3.5.1', 'text' => 'Penyimpanan packaging : Packaging tersimpan bersih, kering, dan terhindar dari kontaminasi. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'SVA-S3-5', 'code' => 'SVA-3.5.2', 'text' => 'Kebersihan rak penyimpanan : Rak bersih, tidak berdebu, tidak berkarat, dan tertata. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'SVA-S3-5', 'code' => 'SVA-3.5.3', 'text' => 'Kondisi stok : Tidak terdapat stok rusak, kadaluarsa, atau tidak layak pakai. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'SVA-S3-6', 'code' => 'SVA-3.6.1', 'text' => 'Kebersihan dinding dan partisi : Bersih, bebas noda dan debu. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'SVA-S3-6', 'code' => 'SVA-3.6.2', 'text' => 'Kebersihan tangga : Bersih, bebas noda dan debu. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'SVA-S3-6', 'code' => 'SVA-3.6.3', 'text' => 'Kebersihan plafon : Tidak terdapat debu, noda bocor, atau sarang laba-laba. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'SVA-S3-6', 'code' => 'SVA-3.6.4', 'text' => 'Kebersihan lampu : Bersih, berfungsi, dan tidak terdapat serangga mati. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'SVA-S3-6', 'code' => 'SVA-3.6.5', 'text' => 'Kebersihan AC, kipas dan diffuser : Bersih, tidak berdebu, tidak menetes. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 50],
    ['sc' => 'SVA-S3-6', 'code' => 'SVA-3.6.6', 'text' => 'Kebersihan taman dan tanaman : Bersih dan terawat. (SOP-QA-006, FRM-QA-003)', 'weight' => 30, 'sort_order' => 60],
    ['sc' => 'SVA-S3-6', 'code' => 'SVA-3.6.7', 'text' => 'Kebersihan tempat sampah : Bersih, tertutup, tidak penuh, menggunakan liner. (SOP-QA-006, FRM-QA-003)', 'weight' => 50, 'sort_order' => 70],
    ['sc' => 'SVA-S3-7', 'code' => 'SVA-3.7.1', 'text' => 'Indikasi hama : Tidak ditemukan lalat, kecoa, tikus, semut berlebihan, atau tanda keberadaan hama. (SOP-QA-018)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'SVA-S3-7', 'code' => 'SVA-3.7.2', 'text' => 'Perangkat pest control : Perangkat pest control tersedia, bersih, dan dalam kondisi baik. (SOP-QA-018)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'SVA-S3-7', 'code' => 'SVA-3.7.3', 'text' => 'Tindak lanjut temuan hama : Terdapat tindakan korektif dan pencatatan apabila ditemukan indikasi hama. (SOP-QA-018)', 'weight' => 10, 'sort_order' => 30],
    ['sc' => 'SVA-S4-1', 'code' => 'SVA-4.1', 'text' => 'Kelayakan penggunaan : Peralatan masih layak digunakan sesuai fungsi operasional, Tidak retak, pecah, penyok, patah, rusak, bebas karat, korosi, serpihan, dan bagian tajam yang berbahaya. (SOP-QA-005)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'SVA-S4-1', 'code' => 'SVA-4.2', 'text' => 'Kelengkapan peralatan : Peralatan lengkap dan tersimpan dalam cover terpasang dengan baik, rapi dan tersusun sesuai standar (Cuttleries set : pisau/sendok disebelah kanan garpu. Sendok diatas/menutupi garpu). (SOP-QA-005)', 'weight' => 30, 'sort_order' => 20],
    ['sc' => 'SVA-S4-1', 'code' => 'SVA-4.3', 'text' => 'Ketersediaan peralatan : Jumlah peralatan mencukupi sesuai kebutuhan operasional. (SOP-QA-005)', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'SVA-S5-1', 'code' => 'SVA-5.1', 'text' => 'Bahan kimia disimpan pada area khusus yang bersih, rapi, memiliki ventilasi yang memadai, terpisah dari bahan pangan, kemasan, dan alat makan. Chemical disusun sesuai kategori penggunaannya, dalam wadah yang sesuai, dengan kondisi tertutup, serta tidak disimpan langsung di lantai. (SOP-QA-028)', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'SVA-S5-1', 'code' => 'SVA-5.2', 'text' => 'Material Safety Data Sheet (MSDS) atau Safety Data Sheet (SDS) tersedia untuk seluruh bahan kimia yang digunakan, mudah diakses oleh karyawan, dalam kondisi terbaru, dan digunakan sebagai acuan dalam penanganan, penyimpanan, penggunaan, serta tindakan darurat apabila terjadi paparan atau tumpahan bahan kimia. (SOP-QA-028)', 'weight' => 10, 'sort_order' => 20],
    ['sc' => 'SVA-S5-1', 'code' => 'SVA-5.3', 'text' => 'Seluruh wadah bahan kimia, termasuk wadah transfer atau hasil pengenceran, memiliki identitas yang jelas dan mudah dibaca, minimal mencantumkan nama bahan kimia. (SOP-QA-029)', 'weight' => 30, 'sort_order' => 30],
    ['sc' => 'SVA-S5-1', 'code' => 'SVA-5.4', 'text' => 'Penggunaan dan pengenceran bahan kimia dilakukan sesuai instruksi produsen atau SOP yang berlaku, menggunakan alat ukur yang sesuai untuk memastikan konsentrasi yang tepat dan aman digunakan. (STD-QA-003)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'SVA-S5-1', 'code' => 'SVA-5.5', 'text' => 'Tersedia peralatan penanganan tumpahan (spill kit) atau prosedur penanganan tumpahan bahan kimia, dan karyawan memahami tindakan yang harus dilakukan apabila terjadi insiden paparan atau tumpahan chemical. (SOP-QA-030)', 'weight' => 10, 'sort_order' => 50],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.1', 'text' => 'Karyawan menggunakan perlengkapan kerja sesuai ketentuan, seperti masker, serta hand glove sekali pakai apabila dipersyaratkan sesuai jenis pekerjaan. PPE (Personal Protective Equipment) diganti secara berkala atau apabila kotor, rusak, maupun terkontaminasi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 10],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.2', 'text' => 'Karyawan menjaga kebersihan tubuh, tidak berbau badan, memakai wangi-wangian sewajarnya (aroma soft) (P/W). (STD-QA-013)', 'weight' => 50, 'sort_order' => 20],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.3', 'text' => 'Karyawan pria tidak diperkenankan berkumis atau berjenggot. Rambut harus bersih, rapi (untuk pria menggunakan gel/pomade) tidak diwarnai dengan warna yang mencolok/disengaja, tidak melebihi alis mata maupun telinga, untuk wanita rambut tidak terurai kedepan (jika berponi harus menggunakan jepit) dan yang berambut panjang di ikat dan menggunakan hairnet berwarna hitam. (STD-QA-013)', 'weight' => 50, 'sort_order' => 30],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.4', 'text' => 'Karyawan tidak menggunakan softlens (cosmetic contact lens), bulu mata palsu/eyelash extension, acne patch yang terlihat atau berpotensi terlepas, kutek, kuku palsu, maupun kosmetik berlebihan yang dapat menjadi sumber kontaminasi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 40],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.5', 'text' => 'Karyawan tidak diperkenankan menggunakan perhiasan atau aksesori seperti gelang, cincin (kecuali cincin nikah), kalung. (STD-QA-013)', 'weight' => 50, 'sort_order' => 50],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.6', 'text' => 'Kuku tangan dipotong pendek, bersih, terawat, serta tidak terdapat kotoran pada sela-sela kuku. (STD-QA-013)', 'weight' => 50, 'sort_order' => 60],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.7', 'text' => 'Karyawan menggunakan seragam kerja lengkap sesuai standar perusahaan, meliputi pakaian, apron, celana hitam, kaos kaki, ikat pinggang, dan sepatu. Seluruh seragam dalam kondisi bersih, rapi, tidak rusak, dan terawat dengan baik. (STD-QA-013)', 'weight' => 50, 'sort_order' => 70],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.8', 'text' => 'Seluruh karyawan outlet wajib mengenakan jaket saat keluar dari outlet. (STD-QA-013)', 'weight' => 30, 'sort_order' => 80],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.9', 'text' => 'Karyawan incharge di area checker tidak melakukan tindakan yang dapat menyebabkan kontaminasi silang pada saat handling produk, seperti batuk, bersin, membuang ingus, meludah, menggaruk anggota tubuh, menyentuh wajah atau rambut, serta mencicipi produk menggunakan jari atau utensil yang digunakan berulang tanpa proses pembersihan. (STD-QA-013)', 'weight' => 50, 'sort_order' => 90],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.10', 'text' => 'Karyawan yang memiliki luka terbuka wajib menutup luka menggunakan perban tahan air (waterproof dressing). Luka tidak boleh dibiarkan terbuka selama bekerja di area produksi. (STD-QA-013)', 'weight' => 50, 'sort_order' => 100],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.11', 'text' => 'Karyawan tidak diperkenankan makan, mengunyah produk/permen karet, merokok, atau menggunakan rokok elektronik di area kerja selama jam operasional. (STD-QA-013)', 'weight' => 50, 'sort_order' => 110],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.12', 'text' => 'Karyawan tidak diperbolehkan mengonsumsi produk atau minuman sisa tamu untuk menjaga standar higiene, kesehatan, dan profesionalisme kerja. (STD-QA-013)', 'weight' => 50, 'sort_order' => 120],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.13', 'text' => 'Barang pribadi karyawan disimpan pada loker yang telah disediakan. Karyawan tidak diperkenankan meninggalkan barang pribadi seperti tas, pakaian, sepatu, celana, maupun perlengkapan lainnya di area operasional. Pada akhir jam kerja, loker dikosongkan dari barang yang tidak diperbolehkan sesuai ketentuan perusahaan. (STD-QA-013)', 'weight' => 30, 'sort_order' => 130],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.14', 'text' => 'Persediaan hand glove sekali pakai, dan masker tersedia dalam jumlah yang memadai untuk mendukung operasional serta mudah diakses oleh karyawan pada saat dibutuhkan. (STD-QA-013)', 'weight' => 30, 'sort_order' => 140],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.15', 'text' => 'Fasilitas karyawan seperti loker, rak sepatu, toilet karyawan, mushola, area istirahat, dan fasilitas pendukung lainnya berada dalam kondisi bersih, rapi, terawat, tidak berbau, serta dilakukan pembersihan secara rutin sesuai jadwal yang telah ditetapkan. (SOP-QA-006, FRM-QA-003). Guide Line: Lemari loker difungsikan sebagai tempat menyimpan ponsel, tas dan barang pribadi karyawan sebelum bekerja. Didalam loker tidak terdapat : sepatu, rokok, masker, hand gloves, senjata jenis apapun, obat-obatan terlarang. Tidak terdapat barang tidak terpakai, sepatu dalam kondisi tertutup (terbungkus), uniform kerja dan sepatu kerja harus dibawa pulang.', 'weight' => 30, 'sort_order' => 150],
    ['sc' => 'SVA-S6-1', 'code' => 'SVA-6.16', 'text' => 'Karyawan wajib melakukan cuci tangan sesuai SOP menggunakan air mengalir dan sabun pada waktu-waktu kritis, seperti sebelum memulai pekerjaan, setelah menggunakan toilet, setelah membuang sampah, setelah batuk atau bersin, setelah menyentuh bagian tubuh, dan setiap kali terdapat potensi kontaminasi silang. (SOP-QA-026). Guide Line: Standar 6 langkah cuci tangan : 1. Telapak tangan dengan telapak tangan : Gosok kedua telapak tangan secara bergantian. 2. Punggung tangan : Gosok punggung tangan kiri dengan telapak tangan kanan dan sebaliknya, jari saling terkait. 3. Sela-sela jari : Gosok kedua telapak tangan dengan jari saling terkait. 4. Punggung jari : Gosok punggung jari ke telapak tangan dengan posisi jari saling mengunci. 5. Ibu jari : Gosok ibu jari kiri dengan gerakan memutar menggunakan tangan kanan, lalu sebaliknya. 6. Ujung jari dan kuku : Gosok ujung jari pada telapak tangan dengan gerakan memutar untuk membersihkan area kuku.', 'weight' => 30, 'sort_order' => 160],
    ['sc' => 'SVA-S7-1', 'code' => 'SVA-7.1', 'text' => 'Kotak P3K tersedia pada lokasi yang telah ditentukan, mudah dijangkau, diberi identifikasi yang jelas, dalam kondisi bersih dan lengkap sesuai daftar isi standar perusahaan. Seluruh isi P3K berada dalam masa berlaku (tidak kedaluwarsa), dan dilakukan pemeriksaan serta pengisian ulang secara berkala. (SOP-QA-031). Guide Line: Isi kotak P3K tipe B (jumlah pekerja 26-50 orang) : Kasa steril terbungkus, Perban 5 cm dan perban 10 cm, Plester gulung, Plester cepat, Kapas, Kain segitiga (mitella), Gunting, Peniti, Sarung tangan sekali pakai, Masker, Pinset, Lampu senter/penlight, Gelas pencuci mata, Larutan saline/NaCl atau pencuci mata, Povidone iodine 60 ml, Alkohol 70%, Kantong plastik bersih/biohazard, Kantong es (ICE pack), Buku panduan P3K, Buku catatan/laporan P3K, Daftar isi kotak P3K', 'weight' => 30, 'sort_order' => 10],
    ['sc' => 'SVA-S7-1', 'code' => 'SVA-7.2', 'text' => 'Lampu emergency tersedia pada area yang dipersyaratkan, dalam kondisi bersih, berfungsi dengan baik, serta mampu menyala secara otomatis saat terjadi pemadaman listrik. Pemeriksaan dan uji fungsi dilakukan secara berkala serta terdokumentasi. (SOP-QA-006)', 'weight' => 10, 'sort_order' => 20],
];

$normalize = static function (string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return $value;
};

$getCategoryId = static function (array $row, callable $normalize) use ($now): int {
    $existingByCode = DB::table('qa2_categories')->where('code', $row['code'])->value('id');
    if ($existingByCode) {
        DB::table('qa2_categories')->where('id', $existingByCode)->update([
            'name' => $row['name'],
            'status' => 'A',
            'updated_at' => $now,
        ]);
        return (int) $existingByCode;
    }

    $all = DB::table('qa2_categories')->select('id', 'name')->get();
    $wanted = $normalize($row['name']);
    foreach ($all as $cat) {
        if ($normalize($cat->name) === $wanted) {
            DB::table('qa2_categories')->where('id', $cat->id)->update([
                'status' => 'A',
                'updated_at' => $now,
            ]);
            return (int) $cat->id;
        }
    }

    return (int) DB::table('qa2_categories')->insertGetId([
        'code' => $row['code'],
        'name' => $row['name'],
        'status' => 'A',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
};

$getSubcategoryId = static function (array $row, int $categoryId, callable $normalize) use ($now): int {
    $existingByCode = DB::table('qa2_subcategories')->where('code', $row['code'])->value('id');
    if ($existingByCode) {
        DB::table('qa2_subcategories')->where('id', $existingByCode)->update([
            'category_id' => $categoryId,
            'name' => $row['name'],
            'sort_order' => $row['sort_order'],
            'status' => 'A',
            'updated_at' => $now,
        ]);
        return (int) $existingByCode;
    }

    $all = DB::table('qa2_subcategories')->where('category_id', $categoryId)->select('id', 'name')->get();
    $wanted = $normalize($row['name']);
    foreach ($all as $sub) {
        if ($normalize($sub->name) === $wanted) {
            DB::table('qa2_subcategories')->where('id', $sub->id)->update([
                'sort_order' => $row['sort_order'],
                'status' => 'A',
                'updated_at' => $now,
            ]);
            return (int) $sub->id;
        }
    }

    return (int) DB::table('qa2_subcategories')->insertGetId([
        'category_id' => $categoryId,
        'code' => $row['code'],
        'name' => $row['name'],
        'sort_order' => $row['sort_order'],
        'status' => 'A',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
};

$getParameterId = static function (array $row, int $subcategoryId, callable $normalize) use ($now): int {
    $existingByCode = DB::table('qa2_parameters')->where('code', $row['code'])->value('id');
    if ($existingByCode) {
        DB::table('qa2_parameters')->where('id', $existingByCode)->update([
            'subcategory_id' => $subcategoryId,
            'parameter_text' => $row['text'],
            'weight' => $row['weight'],
            'sort_order' => $row['sort_order'],
            'status' => 'A',
            'updated_at' => $now,
        ]);
        return (int) $existingByCode;
    }

    $all = DB::table('qa2_parameters')
        ->where('subcategory_id', $subcategoryId)
        ->select('id', 'parameter_text', 'code')
        ->get();
    $wanted = $normalize($row['text']);

    foreach ($all as $param) {
        if ($normalize($param->parameter_text) === $wanted && (string) $param->code === $row['code']) {
            DB::table('qa2_parameters')->where('id', $param->id)->update([
                'subcategory_id' => $subcategoryId,
                'parameter_text' => $row['text'],
                'weight' => $row['weight'],
                'sort_order' => $row['sort_order'],
                'status' => 'A',
                'updated_at' => $now,
            ]);
            return (int) $param->id;
        }
    }

    return (int) DB::table('qa2_parameters')->insertGetId([
        'subcategory_id' => $subcategoryId,
        'code' => $row['code'],
        'parameter_text' => $row['text'],
        'weight' => $row['weight'],
        'sort_order' => $row['sort_order'],
        'status' => 'A',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
};

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

    $categoryIdByCode = [];
    foreach ($categories as $category) {
        $categoryIdByCode[$category['code']] = $getCategoryId($category, $normalize);
    }

    $subcategoryIdByCode = [];
    foreach ($subcategories as $subcategory) {
        $categoryId = $categoryIdByCode[$subcategory['cat']];
        $subcategoryIdByCode[$subcategory['code']] = $getSubcategoryId($subcategory, $categoryId, $normalize);
    }

    $paramIds = [];
    foreach ($parameters as $parameter) {
        $subcategoryId = $subcategoryIdByCode[$parameter['sc']];
        $paramIds[] = $getParameterId($parameter, $subcategoryId, $normalize);
    }

    $paramIds = array_values(array_unique($paramIds));

    DB::table('qa2_template_items')
        ->where('template_id', $templateId)
        ->whereNotIn('parameter_id', $paramIds)
        ->delete();

    $sortOrder = 1;
    foreach ($parameters as $parameter) {
        $pid = DB::table('qa2_parameters')->where('code', $parameter['code'])->value('id');
        if (!$pid) {
            continue;
        }

        DB::table('qa2_template_items')->updateOrInsert(
            ['template_id' => $templateId, 'parameter_id' => (int) $pid],
            [
                'sort_order' => $sortOrder++,
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
