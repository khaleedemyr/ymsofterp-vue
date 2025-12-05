<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Competency;

class CompetencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $competencies = [
            // Technical Skills
            [
                'name' => 'Programming',
                'description' => 'Kemampuan dalam menulis dan mengembangkan kode program',
                'category' => 'Technical',
                'level' => 'intermediate',
            ],
            [
                'name' => 'Database Management',
                'description' => 'Kemampuan dalam mengelola dan mengoptimalkan database',
                'category' => 'Technical',
                'level' => 'intermediate',
            ],
            [
                'name' => 'System Administration',
                'description' => 'Kemampuan dalam mengelola dan memelihara sistem komputer',
                'category' => 'Technical',
                'level' => 'advanced',
            ],
            [
                'name' => 'Network Security',
                'description' => 'Kemampuan dalam mengamankan jaringan komputer',
                'category' => 'Technical',
                'level' => 'advanced',
            ],
            [
                'name' => 'Cloud Computing',
                'description' => 'Kemampuan dalam menggunakan layanan cloud computing',
                'category' => 'Technical',
                'level' => 'intermediate',
            ],

            // Soft Skills
            [
                'name' => 'Communication',
                'description' => 'Kemampuan berkomunikasi secara efektif',
                'category' => 'Soft Skills',
                'level' => 'beginner',
            ],
            [
                'name' => 'Teamwork',
                'description' => 'Kemampuan bekerja dalam tim',
                'category' => 'Soft Skills',
                'level' => 'beginner',
            ],
            [
                'name' => 'Time Management',
                'description' => 'Kemampuan mengelola waktu dengan efektif',
                'category' => 'Soft Skills',
                'level' => 'beginner',
            ],
            [
                'name' => 'Problem Solving',
                'description' => 'Kemampuan memecahkan masalah secara sistematis',
                'category' => 'Soft Skills',
                'level' => 'intermediate',
            ],
            [
                'name' => 'Adaptability',
                'description' => 'Kemampuan beradaptasi dengan perubahan',
                'category' => 'Soft Skills',
                'level' => 'intermediate',
            ],

            // Leadership
            [
                'name' => 'Strategic Thinking',
                'description' => 'Kemampuan berpikir strategis dan visioner',
                'category' => 'Leadership',
                'level' => 'advanced',
            ],
            [
                'name' => 'Decision Making',
                'description' => 'Kemampuan mengambil keputusan yang tepat',
                'category' => 'Leadership',
                'level' => 'intermediate',
            ],
            [
                'name' => 'Team Leadership',
                'description' => 'Kemampuan memimpin dan mengarahkan tim',
                'category' => 'Leadership',
                'level' => 'advanced',
            ],
            [
                'name' => 'Conflict Resolution',
                'description' => 'Kemampuan menyelesaikan konflik',
                'category' => 'Leadership',
                'level' => 'intermediate',
            ],
            [
                'name' => 'Mentoring',
                'description' => 'Kemampuan membimbing dan mengembangkan orang lain',
                'category' => 'Leadership',
                'level' => 'advanced',
            ],

            // Communication
            [
                'name' => 'Public Speaking',
                'description' => 'Kemampuan berbicara di depan umum',
                'category' => 'Communication',
                'level' => 'intermediate',
            ],
            [
                'name' => 'Written Communication',
                'description' => 'Kemampuan menulis dengan jelas dan efektif',
                'category' => 'Communication',
                'level' => 'beginner',
            ],
            [
                'name' => 'Presentation Skills',
                'description' => 'Kemampuan membuat dan menyampaikan presentasi',
                'category' => 'Communication',
                'level' => 'intermediate',
            ],
            [
                'name' => 'Active Listening',
                'description' => 'Kemampuan mendengarkan secara aktif',
                'category' => 'Communication',
                'level' => 'beginner',
            ],

            // Management
            [
                'name' => 'Project Management',
                'description' => 'Kemampuan mengelola proyek dari awal hingga selesai',
                'category' => 'Management',
                'level' => 'advanced',
            ],
            [
                'name' => 'Budget Management',
                'description' => 'Kemampuan mengelola anggaran dan keuangan',
                'category' => 'Management',
                'level' => 'intermediate',
            ],
            [
                'name' => 'Resource Planning',
                'description' => 'Kemampuan merencanakan dan mengalokasikan sumber daya',
                'category' => 'Management',
                'level' => 'advanced',
            ],
            [
                'name' => 'Quality Management',
                'description' => 'Kemampuan mengelola kualitas produk atau layanan',
                'category' => 'Management',
                'level' => 'intermediate',
            ],

            // Analytical
            [
                'name' => 'Data Analysis',
                'description' => 'Kemampuan menganalisis data untuk mendapatkan insight',
                'category' => 'Analytical',
                'level' => 'intermediate',
            ],
            [
                'name' => 'Critical Thinking',
                'description' => 'Kemampuan berpikir kritis dan analitis',
                'category' => 'Analytical',
                'level' => 'intermediate',
            ],
            [
                'name' => 'Research Skills',
                'description' => 'Kemampuan melakukan penelitian dan investigasi',
                'category' => 'Analytical',
                'level' => 'intermediate',
            ],
            [
                'name' => 'Statistical Analysis',
                'description' => 'Kemampuan melakukan analisis statistik',
                'category' => 'Analytical',
                'level' => 'advanced',
            ],

            // Creative
            [
                'name' => 'Creative Thinking',
                'description' => 'Kemampuan berpikir kreatif dan inovatif',
                'category' => 'Creative',
                'level' => 'intermediate',
            ],
            [
                'name' => 'Design Thinking',
                'description' => 'Kemampuan menggunakan design thinking dalam pemecahan masalah',
                'category' => 'Creative',
                'level' => 'intermediate',
            ],
            [
                'name' => 'Innovation',
                'description' => 'Kemampuan menciptakan inovasi dan ide baru',
                'category' => 'Creative',
                'level' => 'advanced',
            ],
        ];

        foreach ($competencies as $competency) {
            Competency::create($competency);
        }
    }
}
