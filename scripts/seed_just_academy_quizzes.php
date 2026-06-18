<?php

declare(strict_types=1);

/**
 * Seed data awal Just Academy: kategori, materi, quiz, program + curriculum.
 *
 * Usage:
 *   php scripts/seed_just_academy_quizzes.php
 *   php scripts/seed_just_academy_quizzes.php --force   # hapus seed lama lalu insert ulang
 */

use App\Models\JustAcademy\JaCategory;
use App\Models\JustAcademy\JaMaterial;
use App\Models\JustAcademy\JaProgram;
use App\Models\JustAcademy\JaProgramItem;
use App\Models\JustAcademy\JaQuiz;
use App\Models\JustAcademy\JaQuizOption;
use App\Models\JustAcademy\JaQuizQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$force = in_array('--force', $_SERVER['argv'] ?? [], true);

if (!Schema::hasTable('ja_quizzes')) {
    echo "Tabel ja_quizzes belum ada. Jalankan create_just_academy_tables.sql dulu.\n";
    exit(1);
}

ensureJustAcademyQuizColumns();

$createdBy = (int) (User::query()->orderBy('id')->value('id') ?? 0);

$categories = [
    [
        'name' => '[SEED] Kebersihan & Food Safety',
        'description' => 'Training hygiene, HACCP, dan keamanan pangan untuk kitchen & outlet.',
        'sort_order' => 10,
    ],
    [
        'name' => '[SEED] Layanan & Operasional',
        'description' => 'Service excellence, POS, dan operasional lantai.',
        'sort_order' => 20,
    ],
    [
        'name' => '[SEED] K3 & Keselamatan',
        'description' => 'Keselamatan kerja, APAR, dan prosedur darurat outlet.',
        'sort_order' => 30,
    ],
];

$materials = [
    [
        'title' => '[SEED] Panduan Kebersihan F&B',
        'type' => 'link',
        'url' => 'https://www.who.int/teams/nutrition-and-food-safety/food-safety',
        'description' => 'Referensi standar kebersihan dan food safety (buka link materi).',
    ],
    [
        'title' => '[SEED] SOP Layanan Tamu & POS',
        'type' => 'link',
        'url' => 'https://example.com/sop-layanan-tamu',
        'description' => 'Ringkasan SOP sapa tamu, order, pembayaran, dan handling komplain.',
    ],
    [
        'title' => '[SEED] Checklist Safety Outlet',
        'type' => 'link',
        'url' => 'https://example.com/checklist-safety-outlet',
        'description' => 'Checklist harian APAR, jalur evakuasi, dan PPE staf.',
    ],
];

$programs = [
    [
        'code' => 'SEED-ONB-KITCHEN',
        'title' => '[SEED] Onboarding Staff Kitchen',
        'category' => '[SEED] Kebersihan & Food Safety',
        'description' => 'Program orientasi kitchen: pre-test, materi hygiene, evaluasi akhir.',
        'duration_hours' => 3,
        'status' => 'published',
        'curriculum' => [
            ['type' => 'quiz', 'ref' => '[SEED] Pre-Test Kebersihan F&B', 'required' => true],
            ['type' => 'material', 'ref' => '[SEED] Panduan Kebersihan F&B', 'required' => true],
            ['type' => 'quiz', 'ref' => '[SEED] Quiz Singkat — SOP Keamanan', 'required' => true],
        ],
    ],
    [
        'code' => 'SEED-SERVICE-FLOOR',
        'title' => '[SEED] Training Frontliner & Kasir',
        'category' => '[SEED] Layanan & Operasional',
        'description' => 'Materi SOP layanan lalu evaluasi pengetahuan POS & service.',
        'duration_hours' => 2.5,
        'status' => 'published',
        'curriculum' => [
            ['type' => 'material', 'ref' => '[SEED] SOP Layanan Tamu & POS', 'required' => true],
            ['type' => 'quiz', 'ref' => '[SEED] Post-Test Layanan & POS', 'required' => true],
            ['type' => 'quiz', 'ref' => '[SEED] Pre-Test Kebersihan F&B', 'required' => false],
        ],
    ],
    [
        'code' => 'SEED-MONTHLY-REFRESH',
        'title' => '[SEED] Refresher Bulanan Hygiene',
        'category' => '[SEED] Kebersihan & Food Safety',
        'description' => 'Refresh singkat hygiene untuk staf aktif — materi + quiz acak.',
        'duration_hours' => 1,
        'status' => 'published',
        'curriculum' => [
            ['type' => 'material', 'ref' => '[SEED] Panduan Kebersihan F&B', 'required' => true],
            ['type' => 'quiz', 'ref' => '[SEED] Pre-Test Kebersihan F&B', 'required' => true],
        ],
    ],
    [
        'code' => 'SEED-SAFETY-BRIEF',
        'title' => '[SEED] Safety Briefing Outlet',
        'category' => '[SEED] K3 & Keselamatan',
        'description' => 'Checklist safety + quiz singkat K3 untuk seluruh staf outlet.',
        'duration_hours' => 1.5,
        'status' => 'published',
        'curriculum' => [
            ['type' => 'material', 'ref' => '[SEED] Checklist Safety Outlet', 'required' => true],
            ['type' => 'quiz', 'ref' => '[SEED] Quiz Singkat — SOP Keamanan', 'required' => true],
        ],
    ],
];

$quizzes = [
    [
        'title' => '[SEED] Pre-Test Kebersihan F&B',
        'pass_score' => 70,
        'time_limit_mode' => 'quiz',
        'time_limit_min' => 25,
        'time_limit_question_sec' => null,
        'questions_per_attempt' => 10,
        'randomize_questions' => true,
        'randomize_options' => true,
        'questions' => [
            [
                'question' => 'Suhu penyimpanan makanan dingin (chiller) yang aman adalah…',
                'options' => [
                    ['text' => '0°C sampai 5°C', 'correct' => true],
                    ['text' => '6°C sampai 10°C', 'correct' => false],
                    ['text' => '10°C sampai 15°C', 'correct' => false],
                    ['text' => 'Di bawah -18°C', 'correct' => false],
                ],
            ],
            [
                'question' => 'Sebelum menangani makanan, langkah pertama personal hygiene yang wajib adalah…',
                'options' => [
                    ['text' => 'Memakai sarung tangan tanpa cuci tangan', 'correct' => false],
                    ['text' => 'Cuci tangan dengan sabun minimal 20 detik', 'correct' => true],
                    ['text' => 'Menyemprot handsanitizer saja', 'correct' => false],
                    ['text' => 'Mengeringkan tangan di apron', 'correct' => false],
                ],
            ],
            [
                'question' => 'Produk mentah dan matang di kulkas harus…',
                'options' => [
                    ['text' => 'Disimpan dalam satu wadah agar hemat tempat', 'correct' => false],
                    ['text' => 'Dipisahkan untuk mencegah kontaminasi silang', 'correct' => true],
                    ['text' => 'Diletakkan di rak paling atas', 'correct' => false],
                    ['text' => 'Dibungkus plastik hitam saja', 'correct' => false],
                ],
            ],
            [
                'question' => 'Masa simpan makanan siap saji di suhu ruang sebaiknya tidak lebih dari…',
                'options' => [
                    ['text' => '30 menit', 'correct' => false],
                    ['text' => '1 jam', 'correct' => false],
                    ['text' => '2 jam', 'correct' => true],
                    ['text' => '4 jam', 'correct' => false],
                ],
            ],
            [
                'question' => 'Labeling FIFO pada bahan baku berarti…',
                'options' => [
                    ['text' => 'First In First Out — yang masuk dulu dipakai dulu', 'correct' => true],
                    ['text' => 'Fast In Fast Out — cepat masuk cepat keluar', 'correct' => false],
                    ['text' => 'Final Inspection For Outlet', 'correct' => false],
                    ['text' => 'Fresh Item For Operation', 'correct' => false],
                ],
            ],
            [
                'question' => 'Luka terbuka kecil saat bekerja di kitchen, tindakan yang benar adalah…',
                'options' => [
                    ['text' => 'Tetap bekerja asal pakai sarung tangan', 'correct' => false],
                    ['text' => 'Tutup luka waterproof lalu sarung tangan, atau pindah tugas non-food', 'correct' => true],
                    ['text' => 'Dibiarkan kering tanpa penutup', 'correct' => false],
                    ['text' => 'Diolesi bumbu dapur agar cepat sembuh', 'correct' => false],
                ],
            ],
            [
                'question' => 'Cairan pembersih kimia di area kitchen harus…',
                'options' => [
                    ['text' => 'Disimpan terpisah dari bahan makanan dan diberi label', 'correct' => true],
                    ['text' => 'Dicampur dengan deterjen makanan', 'correct' => false],
                    ['text' => 'Disimpan di rak atas kompor', 'correct' => false],
                    ['text' => 'Ditaruh di lantai dekat wastafel', 'correct' => false],
                ],
            ],
            [
                'question' => 'Suhu internal daging ayam matang minimal yang aman dikonsumsi adalah…',
                'options' => [
                    ['text' => '60°C', 'correct' => false],
                    ['text' => '65°C', 'correct' => false],
                    ['text' => '75°C', 'correct' => true],
                    ['text' => '50°C', 'correct' => false],
                ],
            ],
            [
                'question' => 'Alat potong yang digunakan untuk daging mentah dan sayur matang harus…',
                'options' => [
                    ['text' => 'Sama asal dicuci sekali sehari', 'correct' => false],
                    ['text' => 'Dipisahkan warna/penggunaan untuk cegah kontaminasi', 'correct' => true],
                    ['text' => 'Digunakan bergantian tanpa sanitasi', 'correct' => false],
                    ['text' => 'Hanya dibilas air', 'correct' => false],
                ],
            ],
            [
                'question' => 'Tanda bahan makanan harus dibuang meski belum habis masa simpannya adalah…',
                'options' => [
                    ['text' => 'Bau tidak sedap atau tampilan tidak normal', 'correct' => true],
                    ['text' => 'Kemasan masih utuh', 'correct' => false],
                    ['text' => 'Harga bahan mahal', 'correct' => false],
                    ['text' => 'Stok masih banyak', 'correct' => false],
                ],
            ],
            [
                'question' => 'Hand washing sink di kitchen tidak boleh digunakan untuk…',
                'options' => [
                    ['text' => 'Mencuci tangan staf', 'correct' => false],
                    ['text' => 'Mencuci sayur atau peralatan makan', 'correct' => true],
                    ['text' => 'Bilas setelah sabun', 'correct' => false],
                    ['text' => 'Cuci tangan sebelum handling food', 'correct' => false],
                ],
            ],
            [
                'question' => 'Defrosting bahan beku yang paling aman adalah…',
                'options' => [
                    ['text' => 'Di suhu ruang terbuka berjam-jam', 'correct' => false],
                    ['text' => 'Di chiller terkontrol suhu', 'correct' => true],
                    ['text' => 'Di atas meja stainless seharian', 'correct' => false],
                    ['text' => 'Di dekat kompor menyala', 'correct' => false],
                ],
            ],
            [
                'question' => 'Alergen umum yang wajib diinformasikan ke tamu termasuk…',
                'options' => [
                    ['text' => 'Kacang, susu, telur, gluten', 'correct' => true],
                    ['text' => 'Garam dan merica saja', 'correct' => false],
                    ['text' => 'Air mineral', 'correct' => false],
                    ['text' => 'Gula pasir semua merek', 'correct' => false],
                ],
            ],
            [
                'question' => 'Sampah basah di area prep kitchen harus…',
                'options' => [
                    ['text' => 'Dibiarkan sampai shift berikutnya', 'correct' => false],
                    ['text' => 'Dikeluarkan rutin dan tong ditutup rapat', 'correct' => true],
                    ['text' => 'Dibakar di area dapur', 'correct' => false],
                    ['text' => 'Dicampur sampah kering', 'correct' => false],
                ],
            ],
            [
                'question' => 'Pest control di restoran dilakukan dengan…',
                'options' => [
                    ['text' => 'Racun sembarangan oleh staf', 'correct' => false],
                    ['text' => 'Vendor berlisensi + inspeksi rutin', 'correct' => true],
                    ['text' => 'Menutup semua ventilasi', 'correct' => false],
                    ['text' => 'Menyemprot saat tamu sedang makan', 'correct' => false],
                ],
            ],
            [
                'question' => 'Makanan jatuh ke lantai (5 detik rule) sebaiknya…',
                'options' => [
                    ['text' => 'Dicuci air lalu dipakai', 'correct' => false],
                    ['text' => 'Dibuang dan area disanitasi', 'correct' => true],
                    ['text' => 'Dipanaskan ulang lalu disajikan', 'correct' => false],
                    ['text' => 'Digoreng agar steril', 'correct' => false],
                ],
            ],
            [
                'question' => 'Suhu freezer ideal untuk penyimpanan beku jangka pendek adalah…',
                'options' => [
                    ['text' => '-5°C', 'correct' => false],
                    ['text' => '-10°C', 'correct' => false],
                    ['text' => '-18°C atau lebih dingin', 'correct' => true],
                    ['text' => '0°C', 'correct' => false],
                ],
            ],
            [
                'question' => 'Sanitasi permukaan kerja dilakukan…',
                'options' => [
                    ['text' => 'Sebelum dan sesudah handling food', 'correct' => true],
                    ['text' => 'Sekali seminggu saja', 'correct' => false],
                    ['text' => 'Hanya saat audit', 'correct' => false],
                    ['text' => 'Setelah tutup outlet saja', 'correct' => false],
                ],
            ],
            [
                'question' => 'Staf yang mengalami diare atau muntah sebaiknya…',
                'options' => [
                    ['text' => 'Tetap handling food dengan masker', 'correct' => false],
                    ['text' => 'Tidak menangani makanan sampai sembuh 48 jam', 'correct' => true],
                    ['text' => 'Hanya memegang uang kasir', 'correct' => false],
                    ['text' => 'Minum obat lalu langsung masak', 'correct' => false],
                ],
            ],
            [
                'question' => 'Dokumen HACCP/CCP digunakan untuk…',
                'options' => [
                    ['text' => 'Mengontrol titik kritis keamanan pangan', 'correct' => true],
                    ['text' => 'Menghitung pajak outlet', 'correct' => false],
                    ['text' => 'Desain interior restoran', 'correct' => false],
                    ['text' => 'Promosi media sosial', 'correct' => false],
                ],
            ],
        ],
    ],
    [
        'title' => '[SEED] Post-Test Layanan & POS',
        'pass_score' => 75,
        'time_limit_mode' => 'question',
        'time_limit_min' => null,
        'time_limit_question_sec' => 60,
        'questions_per_attempt' => null,
        'randomize_questions' => true,
        'randomize_options' => true,
        'questions' => [
            [
                'question' => 'Saat tamu datang, sapaan pertama yang tepat adalah…',
                'options' => [
                    ['text' => 'Diam saja sampai tamu memanggil', 'correct' => false],
                    ['text' => 'Sapa ramah, senyum, dan tawarkan bantuan', 'correct' => true],
                    ['text' => 'Langsung minta pesanan tanpa sapa', 'correct' => false],
                    ['text' => 'Melihat HP sambil menunggu', 'correct' => false],
                ],
            ],
            [
                'question' => 'Upselling yang etis dilakukan dengan…',
                'options' => [
                    ['text' => 'Memaksa tamu memesan menu termahal', 'correct' => false],
                    ['text' => 'Menawarkan rekomendasi sesuai preferensi tamu', 'correct' => true],
                    ['text' => 'Menyembunyikan harga', 'correct' => false],
                    ['text' => 'Menambah item tanpa konfirmasi', 'correct' => false],
                ],
            ],
            [
                'question' => 'Jika tamu komplain makanan terlambat, langkah pertama adalah…',
                'options' => [
                    ['text' => 'Menyalahkan kitchen', 'correct' => false],
                    ['text' => 'Mendengarkan, minta maaf, cek status pesanan', 'correct' => true],
                    ['text' => 'Mengabaikan komplain', 'correct' => false],
                    ['text' => 'Meminta tamu pindah meja', 'correct' => false],
                ],
            ],
            [
                'question' => 'Void transaksi di POS seharusnya dilakukan…',
                'options' => [
                    ['text' => 'Sembarang tanpa alasan', 'correct' => false],
                    ['text' => 'Sesuai SOP dengan otorisasi supervisor', 'correct' => true],
                    ['text' => 'Setelah shift selesai saja', 'correct' => false],
                    ['text' => 'Hanya oleh tamu', 'correct' => false],
                ],
            ],
            [
                'question' => 'Split bill di POS digunakan ketika…',
                'options' => [
                    ['text' => 'Tamu ingin membayar terpisah', 'correct' => true],
                    ['text' => 'Stok habis', 'correct' => false],
                    ['text' => 'Kitchen tutup', 'correct' => false],
                    ['text' => 'Hanya untuk tamu member', 'correct' => false],
                ],
            ],
            [
                'question' => 'Handling uang kembalian yang benar adalah…',
                'options' => [
                    ['text' => 'Hitung di depan tamu dan serahkan dengan sopan', 'correct' => true],
                    ['text' => 'Taruh di meja tanpa dihitung', 'correct' => false],
                    ['text' => 'Masukkan ke kantong sendiri dulu', 'correct' => false],
                    ['text' => 'Bulatkan tanpa konfirmasi', 'correct' => false],
                ],
            ],
            [
                'question' => 'Jika mesin EDC gagal, alternatif yang tepat adalah…',
                'options' => [
                    ['text' => 'Biarkan tamu pergi tanpa bayar', 'correct' => false],
                    ['text' => 'Tawarkan metode bayar lain sesuai kebijakan outlet', 'correct' => true],
                    ['text' => 'Marah ke tamu', 'correct' => false],
                    ['text' => 'Matikan POS', 'correct' => false],
                ],
            ],
            [
                'question' => 'Closing shift kasir mencakup…',
                'options' => [
                    ['text' => 'Rekonsiliasi uang, laporan penjualan, serah terima', 'correct' => true],
                    ['text' => 'Hanya matikan lampu', 'correct' => false],
                    ['text' => 'Tidak perlu hitung uang', 'correct' => false],
                    ['text' => 'Langsung pulang', 'correct' => false],
                ],
            ],
            [
                'question' => 'Member / loyalty program saat checkout sebaiknya…',
                'options' => [
                    ['text' => 'Ditanyakan sebelum transaksi selesai', 'correct' => true],
                    ['text' => 'Tidak perlu ditanyakan', 'correct' => false],
                    ['text' => 'Hanya untuk tamu asing', 'correct' => false],
                    ['text' => 'Ditambahkan otomatis tanpa izin', 'correct' => false],
                ],
            ],
            [
                'question' => 'Komunikasi ke kitchen untuk alergi tamu harus…',
                'options' => [
                    ['text' => 'Jelas, spesifik, dan dikonfirmasi ulang', 'correct' => true],
                    ['text' => 'Cukup bilang "hati-hati"', 'correct' => false],
                    ['text' => 'Tidak perlu disampaikan', 'correct' => false],
                    ['text' => 'Hanya lewat chat pribadi', 'correct' => false],
                ],
            ],
            [
                'question' => 'Table turn time yang sehat membantu…',
                'options' => [
                    ['text' => 'Kapasitas tamu dan revenue outlet', 'correct' => true],
                    ['text' => 'Menurunkan kualitas layanan saja', 'correct' => false],
                    ['text' => 'Menghilangkan standar service', 'correct' => false],
                    ['text' => 'Mengurangi kebersihan', 'correct' => false],
                ],
            ],
            [
                'question' => 'Setelah tamu selesai makan, meja harus…',
                'options' => [
                    ['text' => 'Dibersihkan dan diset ulang sesuai standar', 'correct' => true],
                    ['text' => 'Dibiarkan sampai besok', 'correct' => false],
                    ['text' => 'Hanya dilap sebagian', 'correct' => false],
                    ['text' => 'Digunakan staf untuk makan', 'correct' => false],
                ],
            ],
        ],
    ],
    [
        'title' => '[SEED] Quiz Singkat — SOP Keamanan',
        'pass_score' => 80,
        'time_limit_mode' => 'none',
        'time_limit_min' => null,
        'time_limit_question_sec' => null,
        'questions_per_attempt' => 5,
        'randomize_questions' => true,
        'randomize_options' => false,
        'questions' => [
            [
                'question' => 'APAR (alat pemadam api ringan) dicek secara rutin untuk memastikan…',
                'options' => [
                    ['text' => 'Masih berfungsi dan tidak kadaluarsa', 'correct' => true],
                    ['text' => 'Warnanya cocok dengan interior', 'correct' => false],
                    ['text' => 'Ditaruh di gudang tertutup', 'correct' => false],
                    ['text' => 'Tidak perlu dicek', 'correct' => false],
                ],
            ],
            [
                'question' => 'Jalur evakuasi saat kebakaran harus…',
                'options' => [
                    ['text' => 'Bebas halangan dan terang', 'correct' => true],
                    ['text' => 'Disimpan barang inventori', 'correct' => false],
                    ['text' => 'Dikunci agar aman', 'correct' => false],
                    ['text' => 'Ditutup tirai', 'correct' => false],
                ],
            ],
            [
                'question' => 'Lantai licin di area staf, tindakan cepat yang benar…',
                'options' => [
                    ['text' => 'Pasang tanda peringatan dan segera keringkan', 'correct' => true],
                    ['text' => 'Biarkan sampai shift malam', 'correct' => false],
                    ['text' => 'Siram sabun lagi', 'correct' => false],
                    ['text' => 'Abaikan', 'correct' => false],
                ],
            ],
            [
                'question' => 'Kontak list darurat outlet minimal memuat…',
                'options' => [
                    ['text' => 'Manager, security, rumah sakit/polisi terdekat', 'correct' => true],
                    ['text' => 'Hanya nomor teman pribadi', 'correct' => false],
                    ['text' => 'Tidak perlu ada', 'correct' => false],
                    ['text' => 'Hanya nomor supplier', 'correct' => false],
                ],
            ],
            [
                'question' => 'Saat gempa saat outlet buka, prioritas pertama adalah…',
                'options' => [
                    ['text' => 'Keselamatan tamu dan staf, lalu evakuasi teratur', 'correct' => true],
                    ['text' => 'Simpan uang kas dulu', 'correct' => false],
                    ['text' => 'Lanjut masak pesanan', 'correct' => false],
                    ['text' => 'Rekam video untuk sosmed', 'correct' => false],
                ],
            ],
            [
                'question' => 'Pakaian kerja longgar dekat kompor berisiko…',
                'options' => [
                    ['text' => 'Tersangkut api / kecelakaan kerja', 'correct' => true],
                    ['text' => 'Membuat masakan lebih cepat matang', 'correct' => false],
                    ['text' => 'Tidak berpengaruh', 'correct' => false],
                    ['text' => 'Meningkatkan hygiene', 'correct' => false],
                ],
            ],
            [
                'question' => 'Stop kontak listrik basah sebaiknya…',
                'options' => [
                    ['text' => 'Jangan disentuh; matikan dari panel / panggil teknisi', 'correct' => true],
                    ['text' => 'Dikeringkan dengan lap basah', 'correct' => false],
                    ['text' => 'Dicolokkan lagi', 'correct' => false],
                    ['text' => 'Ditutup lakban saja', 'correct' => false],
                ],
            ],
            [
                'question' => 'Seragam dan sepatu kerja anti slip digunakan untuk…',
                'options' => [
                    ['text' => 'Mengurangi risiko terpeleset', 'correct' => true],
                    ['text' => 'Gaya saja', 'correct' => false],
                    ['text' => 'Mempercepat lari tamu', 'correct' => false],
                    ['text' => 'Mengganti APAR', 'correct' => false],
                ],
            ],
        ],
    ],
];

function ensureJustAcademyQuizColumns(): void
{
    if (!Schema::hasColumn('ja_quizzes', 'questions_per_attempt')) {
        DB::statement("ALTER TABLE `ja_quizzes`
            ADD COLUMN `questions_per_attempt` INT UNSIGNED NULL
                COMMENT 'Jumlah soal per tes. NULL = semua soal di bank'
                AFTER `time_limit_min`");
        echo "  + kolom ja_quizzes.questions_per_attempt\n";
    }

    if (!Schema::hasColumn('ja_quizzes', 'randomize_questions')) {
        DB::statement("ALTER TABLE `ja_quizzes`
            ADD COLUMN `randomize_questions` TINYINT(1) NOT NULL DEFAULT 0
                COMMENT '1 = pilih & urutkan soal secara acak setiap attempt'
                AFTER `questions_per_attempt`");
        echo "  + kolom ja_quizzes.randomize_questions\n";
    }

    if (!Schema::hasColumn('ja_quizzes', 'randomize_options')) {
        DB::statement("ALTER TABLE `ja_quizzes`
            ADD COLUMN `randomize_options` TINYINT(1) NOT NULL DEFAULT 0
                COMMENT '1 = acak urutan opsi jawaban setiap attempt'
                AFTER `randomize_questions`");
        echo "  + kolom ja_quizzes.randomize_options\n";
    }

    if (!Schema::hasColumn('ja_quizzes', 'time_limit_mode')) {
        DB::statement("ALTER TABLE `ja_quizzes`
            ADD COLUMN `time_limit_mode` ENUM('none', 'quiz', 'question') NOT NULL DEFAULT 'none'
                COMMENT 'none=tanpa batas, quiz=total menit, question=detik per soal'
                AFTER `time_limit_min`");
        echo "  + kolom ja_quizzes.time_limit_mode\n";
    }

    if (!Schema::hasColumn('ja_quizzes', 'time_limit_question_sec')) {
        DB::statement("ALTER TABLE `ja_quizzes`
            ADD COLUMN `time_limit_question_sec` INT UNSIGNED NULL
                COMMENT 'Detik per soal jika time_limit_mode=question'
                AFTER `time_limit_mode`");
        echo "  + kolom ja_quizzes.time_limit_question_sec\n";
    }

    if (Schema::hasColumn('ja_quizzes', 'time_limit_min') && Schema::hasColumn('ja_quizzes', 'time_limit_mode')) {
        DB::statement("UPDATE `ja_quizzes`
            SET `time_limit_mode` = 'quiz'
            WHERE `time_limit_min` IS NOT NULL AND `time_limit_min` > 0
              AND (`time_limit_mode` IS NULL OR `time_limit_mode` = 'none')");
    }

    if (!Schema::hasColumn('ja_quiz_attempts', 'question_ids')) {
        DB::statement("ALTER TABLE `ja_quiz_attempts`
            ADD COLUMN `question_ids` JSON NULL
                COMMENT 'Urutan ID soal yang ditampilkan pada attempt ini'
                AFTER `user_id`");
        echo "  + kolom ja_quiz_attempts.question_ids\n";
    }

    if (!Schema::hasColumn('ja_quiz_attempts', 'option_orders')) {
        DB::statement("ALTER TABLE `ja_quiz_attempts`
            ADD COLUMN `option_orders` JSON NULL
                COMMENT 'Map question_id => urutan option_id untuk attempt ini'
                AFTER `question_ids`");
        echo "  + kolom ja_quiz_attempts.option_orders\n";
    }

    if (!Schema::hasColumn('ja_quiz_attempts', 'quiz_progress')) {
        DB::statement("ALTER TABLE `ja_quiz_attempts`
            ADD COLUMN `quiz_progress` JSON NULL
                COMMENT 'Progress mode per-soal: current_index, question_started_at'
                AFTER `option_orders`");
        echo "  + kolom ja_quiz_attempts.quiz_progress\n";
    }
}

function purgeSeedData(): void
{
    if (!Schema::hasTable('ja_programs')) {
        return;
    }

    $programs = JaProgram::where('code', 'like', 'SEED-%')->get();
    foreach ($programs as $program) {
        $program->items()->delete();
        $program->delete();
    }

    JaQuiz::where('title', 'like', '[SEED]%')->delete();
    JaMaterial::where('title', 'like', '[SEED]%')->delete();
    JaCategory::where('name', 'like', '[SEED]%')->delete();
}

function seedCategory(array $def): ?int
{
    $existing = JaCategory::where('name', $def['name'])->first();
    if ($existing) {
        echo "  SKIP kategori: {$def['name']}\n";

        return (int) $existing->id;
    }

    $category = JaCategory::create([
        'name' => $def['name'],
        'description' => $def['description'] ?? null,
        'is_active' => true,
        'sort_order' => $def['sort_order'] ?? 0,
    ]);

    echo "  OK kategori: {$def['name']} (id={$category->id})\n";

    return (int) $category->id;
}

function seedMaterial(array $def, int $createdBy): ?int
{
    $existing = JaMaterial::where('title', $def['title'])->first();
    if ($existing) {
        echo "  SKIP materi: {$def['title']}\n";

        return (int) $existing->id;
    }

    $payload = [
        'title' => $def['title'],
        'type' => $def['type'],
        'url' => $def['url'] ?? null,
        'file_path' => $def['file_path'] ?? null,
        'description' => $def['description'] ?? null,
        'is_active' => true,
    ];

    if (Schema::hasColumn('ja_materials', 'created_by')) {
        $payload['created_by'] = $createdBy ?: null;
    }

    $material = JaMaterial::create($payload);
    echo "  OK materi: {$def['title']} (id={$material->id})\n";

    return (int) $material->id;
}

function seedProgram(
    array $def,
    int $categoryId,
    array $quizIds,
    array $materialIds,
    int $createdBy,
): ?int {
    $existing = JaProgram::where('code', $def['code'])->first();
    if ($existing) {
        echo "  SKIP program: {$def['title']}\n";

        return (int) $existing->id;
    }

    return (int) DB::transaction(function () use ($def, $categoryId, $quizIds, $materialIds, $createdBy) {
        $payload = [
            'category_id' => $categoryId,
            'code' => $def['code'],
            'title' => $def['title'],
            'description' => $def['description'] ?? null,
            'duration_hours' => $def['duration_hours'] ?? null,
            'status' => $def['status'] ?? 'published',
        ];

        if (Schema::hasColumn('ja_programs', 'created_by')) {
            $payload['created_by'] = $createdBy ?: null;
        }

        $program = JaProgram::create($payload);

        foreach ($def['curriculum'] as $i => $item) {
            $ref = $item['ref'];
            $materialId = null;
            $quizId = null;

            if ($item['type'] === 'material') {
                $materialId = $materialIds[$ref] ?? null;
                if (!$materialId) {
                    throw new RuntimeException("Materi tidak ditemukan untuk program {$def['code']}: {$ref}");
                }
            } else {
                $quizId = $quizIds[$ref] ?? null;
                if (!$quizId) {
                    throw new RuntimeException("Quiz tidak ditemukan untuk program {$def['code']}: {$ref}");
                }
            }

            JaProgramItem::create([
                'program_id' => $program->id,
                'item_type' => $item['type'],
                'material_id' => $materialId,
                'quiz_id' => $quizId,
                'sort_order' => $i,
                'is_required' => $item['required'] ?? true,
            ]);
        }

        $steps = count($def['curriculum']);
        echo "  OK program: {$def['title']} — {$steps} langkah (id={$program->id})\n";

        return $program->id;
    });
}

function seedQuiz(array $def, int $createdBy, bool $force): ?int
{
    $title = $def['title'];
    $existing = JaQuiz::where('title', $title)->first();

    if ($existing && !$force) {
        echo "  SKIP (sudah ada): {$title}\n";

        return (int) $existing->id;
    }

    if ($existing && $force) {
        echo "  REPLACE: {$title}\n";
        $existing->delete();
    }

    return (int) DB::transaction(function () use ($def, $createdBy): int {
        $payload = [
            'title' => $def['title'],
            'pass_score' => $def['pass_score'],
            'time_limit_mode' => $def['time_limit_mode'],
            'time_limit_min' => $def['time_limit_min'],
            'time_limit_question_sec' => $def['time_limit_question_sec'],
            'questions_per_attempt' => $def['questions_per_attempt'],
            'randomize_questions' => $def['randomize_questions'],
            'randomize_options' => $def['randomize_options'],
            'is_active' => true,
        ];

        if (Schema::hasColumn('ja_quizzes', 'created_by')) {
            $payload['created_by'] = $createdBy ?: null;
        }

        $quiz = JaQuiz::create($payload);

        foreach ($def['questions'] as $i => $q) {
            $question = JaQuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question' => $q['question'],
                'type' => 'mcq',
                'sort_order' => $i,
                'points' => 1,
            ]);

            foreach ($q['options'] as $j => $opt) {
                JaQuizOption::create([
                    'question_id' => $question->id,
                    'option_text' => $opt['text'],
                    'is_correct' => $opt['correct'],
                    'sort_order' => $j,
                ]);
            }
        }

        $count = count($def['questions']);
        echo "  OK quiz: {$def['title']} — {$count} soal (quiz_id={$quiz->id})\n";

        return (int) $quiz->id;
    });
}

echo "=== Seed Just Academy ===\n";
echo 'Created by user_id: ' . ($createdBy ?: 'NULL') . "\n";
if ($force) {
    echo "Mode: --force (hapus data [SEED] / SEED-* lalu insert ulang)\n";
    purgeSeedData();
}

$categoryIds = [];
if (Schema::hasTable('ja_categories')) {
    echo "\n-- Kategori --\n";
    foreach ($categories as $catDef) {
        $id = seedCategory($catDef);
        if ($id) {
            $categoryIds[$catDef['name']] = $id;
        }
    }
} else {
    echo "Lewati kategori: tabel ja_categories belum ada.\n";
}

$materialIds = [];
if (Schema::hasTable('ja_materials')) {
    echo "\n-- Materi --\n";
    foreach ($materials as $matDef) {
        $id = seedMaterial($matDef, $createdBy);
        if ($id) {
            $materialIds[$matDef['title']] = $id;
        }
    }
} else {
    echo "Lewati materi: tabel ja_materials belum ada.\n";
}

echo "\n-- Quiz --\n";
$quizIds = [];
foreach ($quizzes as $quizDef) {
    $id = seedQuiz($quizDef, $createdBy, $force);
    if ($id) {
        $quizIds[$quizDef['title']] = $id;
    }
}

if (Schema::hasTable('ja_programs') && Schema::hasTable('ja_program_items')) {
    echo "\n-- Program --\n";
    foreach ($programs as $progDef) {
        $categoryId = $categoryIds[$progDef['category']] ?? null;
        if (!$categoryId) {
            echo "  SKIP program {$progDef['title']}: kategori tidak ditemukan ({$progDef['category']})\n";
            continue;
        }
        seedProgram($progDef, $categoryId, $quizIds, $materialIds, $createdBy);
    }
} else {
    echo "\nLewati program: tabel ja_programs belum ada.\n";
}

echo "\nSelesai.\n";
