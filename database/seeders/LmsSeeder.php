<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LmsCategory;
use App\Models\LmsCourse;
use App\Models\LmsLesson;
use App\Models\User;

class LmsSeeder extends Seeder
{
    public function run()
    {
        // Create categories
        $categories = [
            [
                'name' => 'Teknologi Informasi',
                'description' => 'Kursus seputar teknologi informasi dan pemrograman',
                'color' => '#3B82F6',
                'icon' => 'fas fa-laptop-code',
                'status' => 'active'
            ],
            [
                'name' => 'Bisnis & Manajemen',
                'description' => 'Kursus seputar bisnis, manajemen, dan entrepreneurship',
                'color' => '#10B981',
                'icon' => 'fas fa-chart-line',
                'status' => 'active'
            ],
            [
                'name' => 'Desain & Kreativitas',
                'description' => 'Kursus seputar desain grafis, UI/UX, dan kreativitas',
                'color' => '#F59E0B',
                'icon' => 'fas fa-palette',
                'status' => 'active'
            ],
            [
                'name' => 'Marketing Digital',
                'description' => 'Kursus seputar digital marketing dan social media',
                'color' => '#EF4444',
                'icon' => 'fas fa-bullhorn',
                'status' => 'active'
            ],
            [
                'name' => 'Keuangan & Investasi',
                'description' => 'Kursus seputar keuangan pribadi dan investasi',
                'color' => '#8B5CF6',
                'icon' => 'fas fa-coins',
                'status' => 'active'
            ]
        ];

        foreach ($categories as $categoryData) {
            LmsCategory::create($categoryData);
        }

        // Get categories for course creation
        $techCategory = LmsCategory::where('name', 'Teknologi Informasi')->first();
        $businessCategory = LmsCategory::where('name', 'Bisnis & Manajemen')->first();
        $designCategory = LmsCategory::where('name', 'Desain & Kreativitas')->first();
        $marketingCategory = LmsCategory::where('name', 'Marketing Digital')->first();
        $financeCategory = LmsCategory::where('name', 'Keuangan & Investasi')->first();

        // Get instructor (use first user as instructor)
        $instructor = User::first();

        // Create courses
        $courses = [
            [
                'title' => 'Belajar Laravel dari Nol hingga Mahir',
                'description' => 'Kursus komprehensif untuk mempelajari framework Laravel dari dasar hingga tingkat lanjut. Cocok untuk pemula hingga developer berpengalaman.',
                'category_id' => $techCategory->id,
                'instructor_id' => $instructor->id,
                'difficulty_level' => 'beginner',
                'duration' => 120, // minutes
                'price' => 0,
                'status' => 'published',
                'thumbnail' => null,
                'video_intro' => null,
                'learning_objectives' => json_encode([
                    'Memahami konsep dasar Laravel dan MVC',
                    'Menguasai routing, controller, dan model',
                    'Membuat aplikasi web dinamis',
                    'Mengintegrasikan database dan authentication',
                    'Deploy aplikasi ke production'
                ]),
                'requirements' => json_encode([
                    'Pengetahuan dasar PHP',
                    'Komputer dengan koneksi internet',
                    'Text editor atau IDE',
                    'Kemauan untuk belajar'
                ])
            ],
            [
                'title' => 'Vue.js untuk Frontend Development',
                'description' => 'Pelajari Vue.js untuk membangun aplikasi frontend yang modern dan interaktif. Dari dasar hingga advanced concepts.',
                'category_id' => $techCategory->id,
                'instructor_id' => $instructor->id,
                'difficulty_level' => 'intermediate',
                'duration' => 180,
                'price' => 150000,
                'status' => 'published',
                'thumbnail' => null,
                'video_intro' => null,
                'learning_objectives' => json_encode([
                    'Memahami reactive data binding',
                    'Menguasai component-based architecture',
                    'Membuat single page applications',
                    'Integrasi dengan backend API',
                    'State management dengan Vuex'
                ]),
                'requirements' => json_encode([
                    'Pengetahuan dasar HTML, CSS, JavaScript',
                    'Pengalaman dengan framework frontend',
                    'Komputer dengan Node.js terinstall'
                ])
            ],
            [
                'title' => 'Digital Marketing Masterclass',
                'description' => 'Strategi lengkap digital marketing untuk bisnis modern. Dari social media marketing hingga SEO dan content marketing.',
                'category_id' => $marketingCategory->id,
                'instructor_id' => $instructor->id,
                'difficulty_level' => 'beginner',
                'duration' => 240,
                'price' => 200000,
                'status' => 'published',
                'thumbnail' => null,
                'video_intro' => null,
                'learning_objectives' => json_encode([
                    'Memahami landscape digital marketing',
                    'Menguasai social media marketing',
                    'SEO dan content marketing',
                    'Email marketing dan automation',
                    'Analytics dan performance tracking'
                ]),
                'requirements' => json_encode([
                    'Komputer dengan koneksi internet',
                    'Akun social media',
                    'Kemauan untuk belajar dan praktik'
                ])
            ],
            [
                'title' => 'UI/UX Design Fundamentals',
                'description' => 'Pelajari prinsip-prinsip desain UI/UX yang efektif. Dari research hingga prototyping dan testing.',
                'category_id' => $designCategory->id,
                'instructor_id' => $instructor->id,
                'difficulty_level' => 'beginner',
                'duration' => 300,
                'price' => 0,
                'status' => 'published',
                'thumbnail' => null,
                'video_intro' => null,
                'learning_objectives' => json_encode([
                    'Memahami prinsip desain UI/UX',
                    'User research dan persona development',
                    'Wireframing dan prototyping',
                    'Usability testing',
                    'Design system dan consistency'
                ]),
                'requirements' => json_encode([
                    'Komputer dengan design software',
                    'Kreativitas dan eye for design',
                    'Kemauan untuk belajar design thinking'
                ])
            ],
            [
                'title' => 'Investasi Saham untuk Pemula',
                'description' => 'Panduan lengkap investasi saham dari nol. Pelajari analisis fundamental dan teknikal.',
                'category_id' => $financeCategory->id,
                'instructor_id' => $instructor->id,
                'difficulty_level' => 'beginner',
                'duration' => 150,
                'price' => 100000,
                'status' => 'published',
                'thumbnail' => null,
                'video_intro' => null,
                'learning_objectives' => json_encode([
                    'Memahami dasar-dasar investasi saham',
                    'Analisis fundamental perusahaan',
                    'Analisis teknikal dan chart patterns',
                    'Risk management dan portfolio',
                    'Trading psychology'
                ]),
                'requirements' => json_encode([
                    'Komputer dengan koneksi internet',
                    'Modal awal untuk investasi',
                    'Kesabaran dan disiplin'
                ])
            ],
            [
                'title' => 'Project Management Professional',
                'description' => 'Kursus persiapan sertifikasi PMP. Pelajari metodologi project management yang efektif.',
                'category_id' => $businessCategory->id,
                'instructor_id' => $instructor->id,
                'difficulty_level' => 'advanced',
                'duration' => 400,
                'price' => 500000,
                'status' => 'published',
                'thumbnail' => null,
                'video_intro' => null,
                'learning_objectives' => json_encode([
                    'Memahami PMBOK Guide',
                    'Project initiation dan planning',
                    'Risk management dan quality control',
                    'Stakeholder management',
                    'Project monitoring dan controlling'
                ]),
                'requirements' => json_encode([
                    'Pengalaman project management minimal 3 tahun',
                    'Pendidikan minimal S1',
                    'Kemauan untuk belajar metodologi PMP'
                ])
            ]
        ];

        foreach ($courses as $courseData) {
            $course = LmsCourse::create($courseData);

            // Create lessons for each course
            $lessonCount = rand(5, 10);
            for ($i = 1; $i <= $lessonCount; $i++) {
                LmsLesson::create([
                    'course_id' => $course->id,
                    'title' => "Pelajaran {$i}: " . $this->getLessonTitle($course->title, $i),
                    'description' => "Deskripsi pelajaran {$i} untuk kursus {$course->title}",
                    'content' => "Konten pelajaran {$i}",
                    'type' => $this->getRandomLessonType(),
                    'duration' => rand(15, 60),
                    'order_number' => $i,
                    'is_preview' => $i <= 2, // First 2 lessons are preview
                    'status' => 'published'
                ]);
            }
        }
    }

    private function getLessonTitle($courseTitle, $lessonNumber)
    {
        $titles = [
            'Pengenalan dan Overview',
            'Konsep Dasar',
            'Praktik dan Implementasi',
            'Studi Kasus',
            'Tips dan Trik',
            'Troubleshooting',
            'Best Practices',
            'Optimasi dan Performance',
            'Deployment dan Maintenance',
            'Kesimpulan dan Next Steps'
        ];

        return $titles[($lessonNumber - 1) % count($titles)];
    }

    private function getRandomLessonType()
    {
        $types = ['video', 'document', 'quiz', 'assignment'];
        return $types[array_rand($types)];
    }
} 