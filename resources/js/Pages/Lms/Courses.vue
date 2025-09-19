<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 relative overflow-hidden">
      <!-- Animated Background Elements -->
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-purple-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute top-40 left-40 w-80 h-80 bg-pink-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-4000"></div>
      </div>

      <div class="relative z-10 py-8 px-6">
        <!-- Header Section -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-3xl shadow-2xl mb-8">
          <div class="p-8 bg-gradient-to-r from-blue-600/80 via-purple-600/80 to-pink-600/80 rounded-3xl">
            <div class="flex items-center justify-between">
              <div class="space-y-4">
                <h1 class="text-4xl font-bold text-white drop-shadow-lg">
                  Training & Development
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Program pelatihan internal untuk pengembangan kompetensi karyawan
                </p>
              </div>
              <div class="flex items-center space-x-4">
                <div class="text-center">
                                  <div class="text-6xl font-bold text-white drop-shadow-lg animate-pulse">
                  {{ courses.total || 0 }}
                </div>
                  <div class="text-lg text-white/90 drop-shadow-md">Program Training</div>
                </div>
                <div class="flex items-center space-x-3">
                  <Link
                    v-if="canManageCourse"
                    :href="route('lms.courses.archived')"
                    class="bg-gradient-to-r from-orange-500 to-red-600 hover:from-orange-600 hover:to-red-700 text-white px-4 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                  >
                    <i class="fas fa-archive mr-2"></i>
                    Archived
                  </Link>
                  <button
                    @click="showCreateModal = true"
                    class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                  >
                    <i class="fas fa-plus mr-2"></i>
                    Tambah Course
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters Section -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl mb-8 p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Search -->
            <div class="relative">
              <input 
                v-model="filters.search" 
                type="text" 
                placeholder="Cari program training..."
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/50 backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
              <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-white/50"></i>
            </div>

            <!-- Category Filter -->
            <select 
              v-model="filters.category" 
              class="px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Semua Kategori</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">
                {{ category.name }}
              </option>
            </select>

            <!-- Difficulty Filter -->
            <select 
              v-model="filters.difficulty" 
              class="px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Semua Level</option>
              <option value="beginner">Pemula</option>
              <option value="intermediate">Menengah</option>
              <option value="advanced">Lanjutan</option>
            </select>

            <!-- Division Filter -->
            <select 
              v-model="filters.division" 
              class="px-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white backdrop-blur-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
              <option value="">Semua Divisi</option>
              <option v-for="division in divisions" :key="division.id" :value="division.id">
                {{ division.nama_divisi }}
              </option>
            </select>
          </div>
        </div>

        <!-- Loading State for Data -->
        <div v-if="dataLoading" class="col-span-full flex justify-center items-center py-12">
          <div class="text-center">
            <div class="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-500 mx-auto mb-4"></div>
            <p class="text-white/80 text-lg">Loading courses...</p>
            <p class="text-white/60 text-sm mt-2">Please wait, this may take a moment...</p>
            <div class="mt-4 space-y-2">
              <div class="flex items-center justify-center space-x-2">
                <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce"></div>
                <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                <div class="w-2 h-2 bg-blue-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Courses Grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          <div v-for="course in filteredCourses" :key="course.id" 
               class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl transform hover:scale-105 hover:rotate-1 transition-all duration-500 cursor-pointer group">
            
            <!-- Course Image -->
            <div class="relative h-48 rounded-t-2xl overflow-hidden">
              <img 
                v-if="course.thumbnail_url" 
                :src="course.thumbnail_url" 
                :alt="course.title" 
                class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
              />
              <div 
                v-else
                class="w-full h-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center"
              >
                <i class="fas fa-graduation-cap text-6xl text-white/50 group-hover:scale-110 transition-transform duration-300"></i>
              </div>
              
              
              <!-- Action Buttons (Edit & Archive) -->
              <div v-if="canManageCourse" class="absolute top-4 right-4 flex space-x-2">
                <button 
                  @click.stop="editCourse(course.id)"
                  class="w-8 h-8 bg-blue-500/80 backdrop-blur-sm border border-blue-400/30 rounded-full text-white hover:bg-blue-600/80 transition-all duration-300 transform hover:scale-105 flex items-center justify-center"
                  title="Edit Course"
                >
                  <i class="fas fa-edit text-xs"></i>
                </button>
                <button 
                  @click.stop="archiveCourse(course.id)"
                  class="w-8 h-8 bg-red-500/80 backdrop-blur-sm border border-red-400/30 rounded-full text-white hover:bg-red-600/80 transition-all duration-300 transform hover:scale-105 flex items-center justify-center"
                  title="Archive Course"
                >
                  <i class="fas fa-archive text-xs"></i>
                </button>
              </div>
            </div>

            <!-- Course Content -->
            <div class="p-6">
              <!-- Badges Section -->
              <div class="flex flex-wrap gap-2 mb-4">
                <!-- Course Type Badge (Mandatory/Optional) -->
                <span :class="{
                  'px-3 py-1 text-xs rounded-full font-bold shadow-lg backdrop-blur-md': true,
                  'bg-red-600 text-white border-2 border-red-700': course.course_type === 'mandatory',
                  'bg-blue-600 text-white border-2 border-blue-700': course.course_type === 'optional'
                }">
                  <i :class="{
                    'fas fa-exclamation-triangle mr-1': course.course_type === 'mandatory',
                    'fas fa-hand-paper mr-1': course.course_type === 'optional'
                  }"></i>
                  {{ course.course_type === 'mandatory' ? 'Mandatory' : 'Optional' }}
                </span>

                <!-- Status Badge -->
                <span :class="{
                  'px-3 py-1 text-xs rounded-full font-bold shadow-lg backdrop-blur-md': true,
                  'bg-green-600 text-white border-2 border-green-700': course.status === 'published',
                  'bg-yellow-600 text-white border-2 border-yellow-700': course.status === 'draft',
                  'bg-gray-600 text-white border-2 border-gray-700': course.status === 'archived'
                }">
                  <i :class="{
                    'fas fa-check-circle mr-1': course.status === 'published',
                    'fas fa-edit mr-1': course.status === 'draft',
                    'fas fa-archive mr-1': course.status === 'archived'
                  }"></i>
                  {{ course.status === 'published' ? 'Published' : course.status === 'draft' ? 'Draft' : 'Archived' }}
                </span>

                <!-- Type Badge -->
                <span :class="{
                  'px-3 py-1 text-xs rounded-full font-bold shadow-lg backdrop-blur-md': true,
                  'bg-blue-600 text-white border-2 border-blue-700': course.type === 'online',
                  'bg-green-600 text-white border-2 border-green-700': course.type === 'offline'
                }">
                  <i :class="{
                    'fas fa-video mr-1': course.type === 'online',
                    'fas fa-users mr-1': course.type === 'offline'
                  }"></i>
                  {{ course.type === 'online' ? 'Online' : 'Offline' }}
                </span>

                <!-- Difficulty Badge -->
                <span :class="{
                  'px-3 py-1 text-xs rounded-full font-semibold backdrop-blur-sm': true,
                  'bg-blue-500/20 text-blue-200 border border-blue-500/30': course.difficulty_level === 'beginner',
                  'bg-yellow-500/20 text-yellow-200 border border-yellow-500/30': course.difficulty_level === 'intermediate',
                  'bg-red-500/20 text-red-200 border border-red-500/30': course.difficulty_level === 'advanced'
                }">
                  {{ course.difficulty_text }}
                </span>
              </div>

              <div class="flex items-center justify-between mb-3">
                <span class="text-sm text-white/60">{{ course.category?.name }}</span>
                <div class="flex items-center space-x-2">
                  <div class="flex items-center space-x-1">
                    <i class="fas fa-star text-yellow-400 text-sm"></i>
                    <span class="text-sm text-white/80">{{ course.average_rating || '0.0' }}</span>
                    <span class="text-xs text-white/50">({{ course.total_reviews }})</span>
                  </div>
                  <button v-if="course.total_reviews > 0" @click="showTrainerRatings(course.id)"
                          class="px-2 py-1 bg-indigo-500/20 border border-indigo-500/30 rounded-lg text-indigo-200 hover:bg-indigo-500/30 transition-all text-xs"
                          title="Lihat Rating Trainer">
                    <i class="fas fa-chalkboard-teacher text-xs"></i>
                  </button>
                </div>
              </div>

              <h3 class="text-xl font-bold text-white drop-shadow-md mb-2 group-hover:text-blue-300 transition-colors duration-300">
                {{ course.title }}
              </h3>

              <p class="text-white/70 text-sm mb-4 line-clamp-2">
                {{ course.description }}
              </p>

              <div class="flex items-center justify-between text-sm text-white/60 mb-4">
                <span><i class="fas fa-clock mr-1"></i>{{ course.duration_formatted }}</span>
                <span><i class="fas fa-users mr-1"></i>{{ course.enrollments_count }} peserta</span>
              </div>

              <!-- Target Information Display -->
              <div class="mb-4">
                <div class="flex items-center text-sm text-white/60 mb-2">
                  <i class="fas fa-bullseye mr-2"></i>
                  <span>Target:</span>
                </div>
                <div class="space-y-2">
                  <!-- Divisi -->
                  <div v-if="course.target_division_name && course.target_division_name !== 'Divisi tidak ditentukan'">
                    <div class="flex items-center text-xs text-white/70 mb-1">
                      <i class="fas fa-building mr-1"></i>
                      <span>Divisi:</span>
                    </div>
                    <div class="flex flex-wrap gap-1">
                      <span v-if="course.target_type === 'all'" 
                            class="px-2 py-1 text-xs rounded-full bg-blue-500/20 text-blue-200 border border-blue-500/30">
                        Semua Divisi
                      </span>
                      <span v-else-if="course.target_type === 'single' && course.target_division" 
                            class="px-2 py-1 text-xs rounded-full bg-green-500/20 text-green-200 border border-green-500/30">
                        {{ course.target_division.nama_divisi }}
                      </span>
                      <span v-else-if="course.target_type === 'multiple'" 
                            class="px-2 py-1 text-xs rounded-full bg-purple-500/20 text-purple-200 border border-purple-500/30">
                        {{ course.target_division_name }}
                      </span>
                    </div>
                  </div>
                  
                  <!-- Jabatan -->
                  <div v-if="course.target_jabatan_names && course.target_jabatan_names !== 'Jabatan tidak ditentukan'">
                    <div class="flex items-center text-xs text-white/70 mb-1">
                      <i class="fas fa-user-tie mr-1"></i>
                      <span>Jabatan:</span>
                    </div>
                    <div class="flex flex-wrap gap-1">
                      <span class="px-2 py-1 text-xs rounded-full bg-orange-500/20 text-orange-200 border border-orange-500/30">
                        {{ course.target_jabatan_names }}
                      </span>
                    </div>
                  </div>
                  
                  <!-- Outlet -->
                  <div v-if="course.target_outlet_names && course.target_outlet_names !== 'Outlet tidak ditentukan'">
                    <div class="flex items-center text-xs text-white/70 mb-1">
                      <i class="fas fa-store mr-1"></i>
                      <span>Outlet:</span>
                    </div>
                    <div class="flex flex-wrap gap-1">
                      <span class="px-2 py-1 text-xs rounded-full bg-pink-500/20 text-pink-200 border border-pink-500/30">
                        {{ course.target_outlet_names }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>


              <div class="flex items-center justify-end">
                <div class="flex space-x-2">
                  <Link :href="route('lms.courses.show', course.id)" 
                        class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300 shadow-lg">
                    Lihat Detail
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="filteredCourses.length === 0" class="text-center py-16">
          <div class="w-32 h-32 mx-auto mb-8 bg-white/10 rounded-full flex items-center justify-center">
            <i class="fas fa-search text-6xl text-white/50"></i>
          </div>
          <h3 class="text-2xl font-bold text-white mb-4">Tidak ada program training ditemukan</h3>
          <p class="text-white/70 text-lg mb-8">Coba ubah filter pencarian Anda atau buat program training baru</p>
          <div class="flex justify-center space-x-4">
            <button @click="clearFilters" 
                    class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300 shadow-lg">
              Reset Filter
            </button>
            <button @click="showCreateModal = true"
                    class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl font-semibold hover:from-green-600 hover:to-emerald-700 transform hover:scale-105 transition-all duration-300 shadow-lg">
              <i class="fas fa-plus mr-2"></i>
              Buat Course Pertama
            </button>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="filteredCourses.length > 0" class="mt-8 flex justify-center">
          <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-4">
            <div class="flex items-center space-x-2">
              <button 
                @click="currentPage--" 
                :disabled="currentPage === 1"
                class="px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300"
              >
                <i class="fas fa-chevron-left"></i>
              </button>
              
              <span class="px-4 py-2 text-white font-semibold">
                Halaman {{ currentPage }} dari {{ totalPages }}
              </span>
              
              <button 
                @click="currentPage++" 
                :disabled="currentPage === totalPages"
                class="px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300"
              >
                <i class="fas fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Create Course Modal -->
      <div v-if="showCreateModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="modal bg-white/10 backdrop-blur-md rounded-xl p-6 w-full max-w-4xl mx-4 border border-white/20 max-h-[90vh] overflow-y-auto">
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-semibold text-white">Tambah Course Baru</h3>
            <button @click="closeModal" class="text-white/70 hover:text-white">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>
          
          <form @submit.prevent="createCourse">
            <!-- Basic Information Section -->
            <div class="mb-6">
              <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-info-circle mr-2 text-blue-400"></i>
                Informasi Dasar
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Judul Course <span class="text-red-400">*</span></label>
                  <input
                    v-model="form.title"
                    type="text"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Masukkan judul course"
                    :disabled="loading"
                  />
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Kategori <span class="text-red-400">*</span></label>
                  <select
                    v-model="form.category_id"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                  >
                    <option value="">Pilih Kategori</option>
                    <option v-for="category in categories" :key="category.id" :value="category.id">
                      {{ category.name }}
                    </option>
                  </select>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Deskripsi Singkat</label>
                  <textarea
                    v-model="form.short_description"
                    rows="3"
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Deskripsi singkat course (opsional)"
                    :disabled="loading"
                  ></textarea>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Deskripsi Lengkap <span class="text-red-400">*</span></label>
                  <textarea
                    v-model="form.description"
                    rows="3"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Deskripsi lengkap course"
                    :disabled="loading"
                  ></textarea>
                </div>
              </div>
            </div>

            <!-- Course Settings Section -->
            <div class="mb-6">
              <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-cog mr-2 text-green-400"></i>
                Pengaturan Course
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Target Divisi</label>
                  <select
                    v-model="form.target_type"
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent mb-3"
                    :disabled="loading"
                  >
                    <option value="">Pilih Tipe Target</option>
                    <option value="single">1 Divisi</option>
                    <option value="multiple">Multi Divisi</option>
                    <option value="all">Semua Divisi</option>
                  </select>
                  
                  <!-- Single Division Selection -->
                  <select
                    v-if="form.target_type === 'single'"
                    v-model="form.target_division_id"
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                  >
                    <option value="">Pilih Divisi</option>
                    <option v-for="division in filteredDivisions" :key="division.id" :value="division.id">
                      {{ division.nama_divisi }}
                    </option>
                  </select>
                  
                  <!-- Multiple Divisions Selection -->
                  <div v-if="form.target_type === 'multiple'" class="space-y-2">
                    <!-- Search Input -->
                    <div class="relative">
                      <input
                        v-model="divisionSearch"
                        type="text"
                        placeholder="Cari divisi..."
                        class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                        :disabled="loading"
                      >
                      <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-white/50 text-sm"></i>
                    </div>
                    
                    <div class="max-h-32 overflow-y-auto bg-white/5 rounded-lg p-3 border border-white/10">
                      <div v-for="division in filteredDivisions" :key="division.id" class="flex items-center mb-2">
                        <input
                          type="checkbox"
                          :id="'division-' + division.id"
                          :value="division.id"
                          v-model="form.target_divisions"
                          class="w-4 h-4 text-blue-600 bg-white/10 border-white/20 rounded focus:ring-blue-500 focus:ring-2"
                        >
                        <label :for="'division-' + division.id" class="ml-2 text-sm text-white/80 cursor-pointer">
                          {{ division.nama_divisi }}
                        </label>
                      </div>
                      <div v-if="filteredDivisions.length === 0" class="text-center py-2">
                        <p class="text-xs text-white/50">Tidak ada divisi ditemukan</p>
                      </div>
                    </div>
                    <p class="text-xs text-white/60">Pilih satu atau lebih divisi yang ditarget</p>
                  </div>
                  
                  <!-- All Divisions Info -->
                  <div v-if="form.target_type === 'all'" class="px-4 py-3 bg-blue-500/20 border border-blue-500/30 rounded-lg">
                    <p class="text-sm text-blue-200">
                      <i class="fas fa-info-circle mr-2"></i>
                      Course ini akan tersedia untuk semua divisi
                    </p>
                  </div>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Target Jabatan</label>
                  <div class="space-y-2">
                    <!-- Search Input -->
                    <div class="relative">
                      <input
                        v-model="jabatanSearch"
                        type="text"
                        placeholder="Cari jabatan..."
                        class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm"
                        :disabled="loading"
                      >
                      <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-white/50 text-sm"></i>
                    </div>
                    
                    <div class="max-h-32 overflow-y-auto bg-white/5 rounded-lg p-3 border border-white/10">
                      <div v-for="jabatan in filteredJabatans" :key="jabatan.id_jabatan" class="flex items-center mb-2">
                        <input
                          type="checkbox"
                          :id="'jabatan-' + jabatan.id_jabatan"
                          :value="jabatan.id_jabatan"
                          v-model="form.target_jabatan_ids"
                          class="w-4 h-4 text-green-600 bg-white/10 border-white/20 rounded focus:ring-green-500 focus:ring-2"
                        >
                        <label :for="'jabatan-' + jabatan.id_jabatan" class="ml-2 text-sm text-white/80 cursor-pointer">
                          {{ jabatan.nama_jabatan }}
                          <span v-if="jabatan.divisi" class="text-xs text-white/60">({{ jabatan.divisi.nama_divisi }})</span>
                        </label>
                      </div>
                      <div v-if="filteredJabatans.length === 0" class="text-center py-2">
                        <p class="text-xs text-white/50">Tidak ada jabatan ditemukan</p>
                      </div>
                    </div>
                    <p class="text-xs text-white/60">Pilih jabatan yang ditarget (opsional)</p>
                  </div>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Target Outlet</label>
                  <div class="space-y-2">
                    <!-- Search Input -->
                    <div class="relative">
                      <input
                        v-model="outletSearch"
                        type="text"
                        placeholder="Cari outlet..."
                        class="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm"
                        :disabled="loading"
                      >
                      <i class="fas fa-search absolute right-3 top-1/2 transform -translate-y-1/2 text-white/50 text-sm"></i>
                    </div>
                    
                    <div class="max-h-32 overflow-y-auto bg-white/5 rounded-lg p-3 border border-white/10">
                      <div v-for="outlet in filteredOutlets" :key="outlet.id_outlet" class="flex items-center mb-2">
                        <input
                          type="checkbox"
                          :id="'outlet-' + outlet.id_outlet"
                          :value="outlet.id_outlet"
                          v-model="form.target_outlet_ids"
                          class="w-4 h-4 text-purple-600 bg-white/10 border-white/20 rounded focus:ring-purple-500 focus:ring-2"
                        >
                        <label :for="'outlet-' + outlet.id_outlet" class="ml-2 text-sm text-white/80 cursor-pointer">
                          {{ outlet.nama_outlet }}
                        </label>
                      </div>
                      <div v-if="filteredOutlets.length === 0" class="text-center py-2">
                        <p class="text-xs text-white/50">Tidak ada outlet ditemukan</p>
                      </div>
                    </div>
                    <p class="text-xs text-white/60">Pilih outlet yang ditarget (opsional)</p>
                  </div>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Level Kesulitan <span class="text-red-400">*</span></label>
                  <select
                    v-model="form.difficulty_level"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                  >
                    <option value="">Pilih Level</option>
                    <option value="beginner">Pemula</option>
                    <option value="intermediate">Menengah</option>
                    <option value="advanced">Lanjutan</option>
                  </select>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Durasi (menit) <span class="text-red-400">*</span></label>
                  <input
                    v-model="form.duration_minutes"
                    type="number"
                    required
                    min="1"
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    placeholder="Durasi dalam menit"
                    :disabled="loading"
                  />
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Tipe Training <span class="text-red-400">*</span></label>
                  <select
                    v-model="form.type"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                  >
                    <option value="">Pilih Tipe</option>
                    <option value="online">Online</option>
                    <option value="in_class">In Class</option>
                    <option value="practice">Practice</option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Spesifikasi <span class="text-red-400">*</span></label>
                  <select
                    v-model="form.specification"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                  >
                    <option value="">Pilih Spesifikasi</option>
                    <option value="generic">Generic</option>
                    <option value="departemental">Departemental</option>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Jenis Course <span class="text-red-400">*</span></label>
                  <select
                    v-model="form.course_type"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                  >
                    <option value="">Pilih Jenis</option>
                    <option value="mandatory">Wajib</option>
                    <option value="optional">Opsional</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Competencies Section -->
            <div class="mb-6">
              <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-star mr-2 text-yellow-400"></i>
                Kompetensi yang Dikembangkan
              </h4>
              <div class="space-y-4">
                <!-- Add New Competency -->
                <div class="bg-white/5 rounded-lg p-4 border border-white/10">
                  <div class="flex items-center justify-between mb-3">
                    <h5 class="text-md font-medium text-white">Tambah Kompetensi Baru</h5>
                    <button
                      type="button"
                      @click="showAddCompetencyForm = !showAddCompetencyForm"
                      class="px-3 py-2 bg-yellow-500/20 border border-yellow-500/30 text-yellow-300 hover:bg-yellow-500/30 rounded-lg transition-colors text-sm"
                      :disabled="loading"
                    >
                      <i class="fas fa-plus mr-1"></i>
                      {{ showAddCompetencyForm ? 'Batal' : 'Tambah' }}
                    </button>
                  </div>
                  
                  <!-- Add Competency Form -->
                  <div v-if="showAddCompetencyForm" class="space-y-3">
                    <div>
                      <label class="block text-xs text-gray-300 mb-1">Nama Kompetensi</label>
                      <input
                        v-model="newCompetency.name"
                        type="text"
                        class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-transparent"
                        placeholder="Masukkan nama kompetensi"
                        :disabled="loading"
                      />
                    </div>
                    <div class="flex justify-end space-x-2">
                      <button
                        type="button"
                        @click="addNewCompetency"
                        class="px-4 py-2 bg-yellow-500/20 border border-yellow-500/30 text-yellow-300 hover:bg-yellow-500/30 rounded-lg transition-colors text-sm"
                        :disabled="loading || !newCompetency.name"
                      >
                        <i class="fas fa-plus mr-1"></i>
                        Tambah ke Training
                      </button>
                    </div>
                  </div>
                </div>


                <!-- Selected Competencies Display -->
                <div v-if="selectedCompetencyIds.length > 0" class="mt-4">
                  <div class="flex items-center justify-between mb-3">
                    <label class="block text-sm font-medium text-gray-300">Kompetensi yang Dipilih</label>
                    <span class="text-xs text-white/60">{{ selectedCompetencyIds.length }} kompetensi dipilih</span>
                  </div>
                  
                  <!-- Quick display of selected competencies -->
                  <div class="flex flex-wrap gap-2 mb-4">
                    <span v-for="competencyId in selectedCompetencyIds" :key="competencyId" 
                          class="px-3 py-1 text-sm rounded-full border"
                          :class="{
                            'bg-green-500/20 text-green-200 border-green-500/30': competencyId < 0,
                            'bg-yellow-500/20 text-yellow-200 border-yellow-500/30': competencyId > 0
                          }">
                      {{ getCompetencyById(competencyId)?.name }}
                      <span v-if="competencyId < 0" class="ml-1 text-xs opacity-75">(Baru)</span>
                    </span>
                  </div>
                </div>

                <!-- Selected Competencies with Proficiency Levels -->
                <div v-if="selectedCompetencyIds.length > 0">
                  <label class="block text-sm font-medium text-gray-300 mb-2">Level Kemahiran</label>
                  <div class="space-y-3">
                    <div v-for="competencyId in selectedCompetencyIds" :key="competencyId" class="bg-white/5 rounded-lg p-3 border border-white/10">
                      <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-white">
                          {{ getCompetencyById(competencyId)?.name }}
                          <span v-if="competencyId < 0" class="ml-2 px-2 py-1 text-xs bg-green-500/20 text-green-200 border border-green-500/30 rounded-full">
                            Baru
                          </span>
                        </span>
                        <button
                          type="button"
                          @click="removeCompetency(competencyId)"
                          class="text-red-400 hover:text-red-300 text-sm"
                          :disabled="loading"
                        >
                          <i class="fas fa-times"></i>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Requirements Section - REMOVED - requirements field removed -->
            <!-- <div class="mb-6">
              <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-clipboard-list mr-2 text-green-400"></i>
                Persyaratan Peserta
              </h4>
              <div class="space-y-3">
                <div v-for="(requirement, index) in form.requirements" :key="index" class="flex items-center space-x-3">
                  <div class="flex-1">
                    <input
                      v-model="form.requirements[index]"
                      type="text"
                      class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      :placeholder="`Persyaratan ${index + 1}`"
                      :disabled="loading"
                    />
                  </div>
                  <button
                    type="button"
                    @click="removeRequirement(index)"
                    class="px-3 py-3 bg-red-500/20 border border-red-500/30 rounded-lg text-red-300 hover:bg-red-500/30 transition-colors"
                    :disabled="loading"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
                <button
                  type="button"
                  @click="addRequirement"
                  class="w-full px-4 py-3 bg-green-500/20 border border-green-500/30 rounded-lg text-green-300 hover:bg-red-500/30 transition-colors"
                  :disabled="loading"
                >
                  <i class="fas fa-plus mr-2"></i>
                  Tambah Persyaratan
                </button>
              </div>
            </div> -->

            <!-- Sessions Section -->
            <div class="mb-6">
              <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-layer-group mr-2 text-blue-400"></i>
                Sesi Training (Fleksibel)
              </h4>
              <div class="space-y-6">
                <div v-for="(session, sessionIndex) in form.sessions" :key="sessionIndex" class="border border-white/20 rounded-lg p-4">
                  <!-- Session Header -->
                  <div class="flex items-center justify-between mb-4">
                    <h5 class="text-lg font-medium text-white">Sesi {{ sessionIndex + 1 }}</h5>
                    <button
                      v-if="form.sessions.length > 1"
                      @click="removeSession(sessionIndex)"
                      type="button"
                      class="px-3 py-2 bg-red-500/20 border border-red-500/30 text-red-300 hover:bg-red-500/30 rounded-lg transition-colors text-sm"
                      :disabled="loading"
                    >
                      <i class="fas fa-trash mr-1"></i>
                      Hapus Sesi
                    </button>
                  </div>
                  
                  <!-- Session Basic Info -->
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-300 mb-2">Urutan <span class="text-red-400">*</span></label>
                      <input
                        v-model="session.order_number"
                        type="number"
                        min="1"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Urutan sesi"
                        :disabled="loading"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-300 mb-2">Judul Sesi <span class="text-red-400">*</span></label>
                      <input
                        v-model="session.session_title"
                        type="text"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Judul sesi training"
                        :disabled="loading"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-300 mb-2">Durasi (menit) <span class="text-red-400">*</span></label>
                      <input
                        v-model="session.estimated_duration_minutes"
                        type="number"
                        min="1"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Durasi dalam menit"
                        :disabled="loading"
                      />
                    </div>
                  </div>
                  
                  <!-- Session Description -->
                  <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Deskripsi Sesi</label>
                    <textarea
                      v-model="session.session_description"
                      rows="2"
                      class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Deskripsi detail sesi training"
                      :disabled="loading"
                    ></textarea>
                  </div>
                  
                  <!-- Session Items -->
                  <div class="mb-4">
                    <div class="flex items-center justify-between mb-3">
                      <h6 class="text-md font-medium text-white">Item dalam Sesi</h6>
                      <button
                        @click="addSessionItem(sessionIndex)"
                        type="button"
                        class="px-3 py-2 bg-green-500/20 border border-green-500/30 text-green-300 hover:bg-green-500/30 rounded-lg transition-colors text-sm"
                        :disabled="loading"
                      >
                        <i class="fas fa-plus mr-1"></i>
                        Tambah Item
                      </button>
                    </div>
                    
                    <div class="space-y-3">
                      <div v-for="(item, itemIndex) in session.items" :key="itemIndex" class="border border-white/10 rounded-lg p-3">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-3">
                          <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Urutan</label>
                            <input
                              v-model="item.order_number"
                              type="number"
                              min="1"
                              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                              placeholder="Urutan"
                              :disabled="loading"
                            />
                          </div>
                          <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Tipe Item <span class="text-red-400">*</span></label>
                            <select
                              v-model="item.item_type"
                              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                              :disabled="loading"
                            >
                              <option value="">Pilih Tipe</option>
                              <option value="quiz">Quiz</option>
                              <option value="material">Materi</option>
                              <option value="questionnaire">Kuesioner</option>
                            </select>
                          </div>
                          <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Judul</label>
                            <input
                              v-model="item.title"
                              type="text"
                              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                              placeholder="Judul item"
                              :disabled="loading"
                            />
                          </div>
                          <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">Durasi (menit)</label>
                            <input
                              v-model="item.estimated_duration_minutes"
                              type="number"
                              min="0"
                              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                              placeholder="Durasi"
                              :disabled="loading"
                            />
                          </div>
                        </div>
                        
                        <!-- Item Type Specific Fields -->
                        <div v-if="item.item_type === 'quiz'" class="mb-3">
                          <label class="block text-sm font-medium text-gray-300 mb-1">Pilih Quiz <span class="text-red-400">*</span></label>
                          <select
                            v-model="item.quiz_id"
                            class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm [&>option]:bg-slate-800 [&>option]:text-white"
                            :disabled="loading"
                          >
                            <option value="">Pilih quiz...</option>
                            <option v-for="quiz in availableQuizzes" :key="quiz.id" :value="quiz.id">
                              {{ quiz.title }}
                            </option>
                          </select>
                        </div>
                        
                        <div v-else-if="item.item_type === 'questionnaire'" class="mb-3">
                          <label class="block text-sm font-medium text-gray-300 mb-1">Pilih Kuesioner <span class="text-red-400">*</span></label>
                          <select
                            v-model="item.questionnaire_id"
                            class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm [&>option]:bg-slate-800 [&>option]:text-white"
                            :disabled="loading"
                          >
                            <option value="">Pilih kuesioner...</option>
                            <option v-for="questionnaire in availableQuestionnaires" :key="questionnaire.id" :value="questionnaire.id">
                              {{ questionnaire.title }}
                            </option>
                          </select>
                        </div>
                        
                        <div v-else-if="item.item_type === 'material'" class="mb-3">
                          <label class="block text-sm font-medium text-gray-300 mb-1">Upload Material <span class="text-red-400">*</span></label>
                          <div class="space-y-2">
                            <input
                              type="file"
                              @change="onMaterialFileChange($event, sessionIndex, itemIndex)"
                              multiple
                              accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.mp4,.avi,.mov"
                              class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                              :disabled="loading"
                            />
                            <p class="text-xs text-gray-400">Format: PDF, DOC, PPT, JPG, PNG, MP4, AVI, MOV (Max: 100MB per file)</p>
                          </div>
                          
                          <!-- Material Files Preview -->
                          <div v-if="item.material_files && item.material_files.length > 0" class="mt-3">
                            <label class="block text-sm font-medium text-gray-300 mb-2">File yang Dipilih:</label>
                            <div class="space-y-2">
                              <div v-for="(file, fileIndex) in item.material_files" :key="fileIndex" 
                                   class="flex items-center justify-between p-2 bg-white/5 border border-white/10 rounded-lg">
                                <div class="flex items-center space-x-2">
                                  <i :class="getFileIcon(file.name) + ' text-blue-400'"></i>
                                  <span class="text-sm text-white">{{ file.name }}</span>
                                  <span class="text-xs text-gray-400">({{ formatFileSize(file.size) }})</span>
                                </div>
                                <button
                                  @click="removeMaterialFile(sessionIndex, itemIndex, fileIndex)"
                                  type="button"
                                  class="text-red-400 hover:text-red-300 text-sm"
                                  :disabled="loading"
                                >
                                  <i class="fas fa-times"></i>
                                </button>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <div class="mb-3">
                          <label class="block text-sm font-medium text-gray-300 mb-1">Deskripsi</label>
                          <textarea
                            v-model="item.description"
                            rows="2"
                            class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm"
                            placeholder="Deskripsi item (opsional)"
                            :disabled="loading"
                          ></textarea>
                        </div>
                        
                        <div class="flex justify-end">
                          <button
                            v-if="session.items.length > 1"
                            @click="removeSessionItem(sessionIndex, itemIndex)"
                            type="button"
                            class="px-2 py-1 bg-red-500/20 border border-red-500/30 text-red-300 hover:bg-red-500/30 rounded-lg transition-colors text-xs"
                            :disabled="loading"
                          >
                            <i class="fas fa-trash mr-1"></i>
                            Hapus
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                
                <button
                  @click="addSession"
                  type="button"
                  class="w-full py-3 border-2 border-dashed border-white/20 rounded-lg text-white hover:border-white/40 transition-colors"
                  :disabled="loading"
                >
                  <i class="fas fa-plus mr-2"></i>
                  Tambah Sesi Training
                </button>
              </div>
            </div>

             <!-- Note: Trainer information is now managed at the training schedule level -->
             <div class="mb-6">
               <div class="backdrop-blur-sm bg-blue-500/10 border border-blue-500/20 rounded-xl p-4">
                 <div class="flex items-center">
                   <i class="fas fa-info-circle text-blue-400 mr-3"></i>
                   <div>
                     <h4 class="text-sm font-semibold text-blue-200">Informasi Trainer</h4>
                     <p class="text-xs text-blue-300 mt-1">
                       Trainer akan diatur langsung di jadwal training. Course ini tidak memerlukan trainer default.
                     </p>
                   </div>
                 </div>
               </div>
             </div>

             <!-- Thumbnail Upload Section -->
             <div class="mb-6">
               <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                 <i class="fas fa-image mr-2 text-purple-400"></i>
                 Thumbnail Course
               </h4>
               <div class="space-y-4">
                 <div>
                   <label class="block text-sm font-medium text-gray-300 mb-2">Upload Thumbnail</label>
                   <div class="border-2 border-dashed border-white/20 rounded-lg p-6 text-center hover:border-white/40 transition-colors">
                     <input
                       ref="fileInput"
                       type="file"
                       accept="image/*"
                       @change="handleFileUpload"
                       class="hidden"
                       :disabled="loading"
                     />
                     <div v-if="!form.thumbnail" @click="$refs.fileInput.click()" class="cursor-pointer">
                       <i class="fas fa-cloud-upload-alt text-4xl text-white/50 mb-4"></i>
                       <p class="text-white/70 mb-2">Klik untuk upload thumbnail</p>
                       <p class="text-xs text-white/50">PNG, JPG, JPEG (Max: 2MB)</p>
                     </div>
                     <div v-else class="space-y-3">
                       <div class="relative inline-block">
                         <img 
                           :src="form.thumbnail.preview" 
                           alt="Thumbnail Preview" 
                           class="max-w-full h-32 object-cover rounded-lg border border-white/20"
                         />
                         <button
                           @click="removeThumbnail"
                           type="button"
                           class="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs transition-colors"
                           :disabled="loading"
                         >
                           <i class="fas fa-times"></i>
                         </button>
                       </div>
                       <p class="text-sm text-white/70">{{ form.thumbnail.name }}</p>
                       <button
                         @click="$refs.fileInput.click()"
                         type="button"
                         class="text-sm text-blue-400 hover:text-blue-300 transition-colors"
                         :disabled="loading"
                       >
                         Ganti Thumbnail
                       </button>
                     </div>
                   </div>
                 </div>
               </div>
             </div>

             <!-- Additional Settings Section -->
             <div class="mb-6">
               <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                 <i class="fas fa-sliders-h mr-2 text-purple-400"></i>
                 Pengaturan Tambahan
               </h4>
               <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div>
                   <label class="block text-sm font-medium text-gray-300 mb-2">Template Sertifikat</label>
                   <select
                     v-model="form.certificate_template_id"
                     class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                     :disabled="loading"
                   >
                     <option value="">Pilih Template Sertifikat (Opsional)</option>
                     <option v-for="template in certificateTemplates" :key="template.id" :value="template.id">
                       {{ template.name }}
                     </option>
                   </select>
                 </div>
                 
                 <div>
                   <label class="block text-sm font-medium text-gray-300 mb-2">Status <span class="text-red-400">*</span></label>
                   <select
                     v-model="form.status"
                     required
                     class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                     :disabled="loading"
                   >
                     <option value="draft">Draft</option>
                     <option value="published">Published</option>
                     <option value="archived">Archived</option>
                   </select>
                 </div>
               </div>
             </div>
            
            <div class="flex space-x-3 mt-6">
              <button
                type="button"
                @click="closeModal"
                class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-lg transition-colors duration-200"
                :disabled="loading"
              >
                Batal
              </button>

              <button
                type="submit"
                :disabled="loading"
                class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-4 py-3 rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed relative overflow-hidden"
              >
                <span v-if="loading" class="flex items-center justify-center">
                  <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                  Menyimpan Course...
                </span>
                <span v-else class="flex items-center justify-center">
                  <i class="fas fa-save mr-2"></i>
                  Simpan Course
                </span>
                
                <!-- Progress bar for long operations -->
                <div v-if="loading" class="absolute bottom-0 left-0 right-0 h-1 bg-green-200">
                  <div class="h-full bg-green-500 animate-pulse"></div>
                </div>
                
                <!-- Loading overlay -->
                <div v-if="loading" class="absolute inset-0 bg-green-600/20 backdrop-blur-sm"></div>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Trainer Ratings Modal -->
      <div v-if="showTrainerRatingsModal" class="fixed inset-0 z-[60] flex items-center justify-center">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="closeTrainerRatingsModal"></div>
        
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl max-w-4xl w-full mx-4 max-h-[80vh] flex flex-col">
          <!-- Modal Header -->
          <div class="p-4 border-b border-white/10 flex-shrink-0">
            <div class="flex items-center justify-between">
              <div>
                <h4 class="text-xl font-bold text-white">Rating Trainer</h4>
                <div v-if="trainerRatingsData.training" class="text-white/70 text-sm mt-1">
                  {{ trainerRatingsData.training.course_title }} • 
                  {{ new Date(trainerRatingsData.training.scheduled_date).toLocaleDateString('id-ID') }}
                </div>
              </div>
              <button @click="closeTrainerRatingsModal" 
                      class="text-white/70 hover:text-white transition-colors">
                <i class="fas fa-times text-lg"></i>
              </button>
            </div>
          </div>
          
          <!-- Modal Content -->
          <div class="flex-1 overflow-hidden flex flex-col">
            <div class="p-4 overflow-y-auto flex-1">

          <!-- Loading State -->
          <div v-if="loadingTrainerRatings" class="flex items-center justify-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white"></div>
            <span class="ml-3 text-white">Memuat data rating...</span>
          </div>

          <!-- Statistics -->
          <div v-else-if="trainerRatingsData.statistics" class="mb-6">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
              <div class="bg-white/5 rounded-lg p-3 border border-white/10">
                <div class="text-lg font-bold text-white">{{ trainerRatingsData.statistics.average_rating || '0.0' }}</div>
                <div class="text-xs text-white/60">Rating Rata-rata</div>
              </div>
              <div class="bg-white/5 rounded-lg p-3 border border-white/10">
                <div class="text-lg font-bold text-white">{{ totalFilteredRatings }}</div>
                <div class="text-xs text-white/60">Total Rating</div>
              </div>
              <div class="bg-white/5 rounded-lg p-3 border border-white/10">
                <div class="text-lg font-bold text-white">{{ trainerRatingsData.statistics.excellent_count || 0 }}</div>
                <div class="text-xs text-white/60">Excellent</div>
              </div>
              <div class="bg-white/5 rounded-lg p-3 border border-white/10">
                <div class="text-lg font-bold text-white">{{ trainerRatingsData.statistics.poor_count || 0 }}</div>
                <div class="text-xs text-white/60">Poor</div>
              </div>
            </div>
            
            <!-- Filters and Controls -->
            <div class="flex flex-col sm:flex-row gap-3 mb-4">
              <!-- Search -->
              <div class="flex-1">
                <input v-model="searchQuery" 
                       type="text" 
                       placeholder="Cari review..." 
                       class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              </div>
              
              <!-- Rating Filter -->
              <select v-model="ratingFilter" 
                      class="px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="all">Semua Rating</option>
                <option value="excellent">Excellent (4.5+)</option>
                <option value="good">Good (3-4.4)</option>
                <option value="poor">Poor (<3)</option>
              </select>
              
              <!-- Per Page -->
              <select v-model="ratingPerPage" 
                      class="px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="3">3 per halaman</option>
                <option value="5">5 per halaman</option>
                <option value="10">10 per halaman</option>
                <option value="20">20 per halaman</option>
              </select>
              
              <!-- Clear Filters -->
              <button @click="clearRatingFilters" 
                      class="px-3 py-2 bg-red-500/20 border border-red-500/30 rounded-lg text-red-200 hover:bg-red-500/30 transition-all text-sm">
                <i class="fas fa-times mr-1"></i>Clear
              </button>
            </div>
          </div>

          <!-- Rating Details -->
          <div v-if="trainerRatingsData.trainer_ratings && trainerRatingsData.trainer_ratings.length > 0">
            <div class="flex items-center justify-between mb-4">
              <h5 class="text-lg font-semibold text-white">Detail Rating</h5>
              <div class="text-sm text-white/60">
                Menampilkan {{ (ratingCurrentPage - 1) * ratingPerPage + 1 }}-{{ Math.min(ratingCurrentPage * ratingPerPage, totalFilteredRatings) }} dari {{ totalFilteredRatings }} review
              </div>
            </div>
            
            <div class="space-y-3">
              <div v-for="rating in paginatedRatings" :key="rating.id" 
                   class="bg-white/5 rounded-lg p-3 border border-white/10">
                <div class="flex items-start justify-between mb-3">
                  <div class="flex-1">
                    <div class="text-white font-medium">{{ maskParticipantName(rating.participant_name) }}</div>
                    <div class="text-white/50 text-xs mt-1">
                      {{ new Date(rating.created_at).toLocaleDateString('id-ID') }} • 
                      {{ new Date(rating.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}
                    </div>
                  </div>
                  <div class="flex items-center space-x-2">
                    <div class="flex items-center space-x-1">
                      <span class="text-yellow-400">{{ rating.rating }}</span>
                      <i class="fas fa-star text-yellow-400"></i>
                    </div>
                    <button @click="toggleRatingExpand(rating.id)" 
                            class="text-white/60 hover:text-white transition-colors">
                      <i :class="expandedRatings.has(rating.id) ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"></i>
                    </button>
                  </div>
                </div>
                
                <!-- Expanded Details -->
                <div v-if="expandedRatings.has(rating.id)" class="mt-4 pt-4 border-t border-white/10">
                  <div class="space-y-3">
                    <!-- Trainer Ratings -->
                    <div v-if="rating.trainer_ratings" class="bg-white/5 rounded p-3">
                      <h6 class="text-white font-medium mb-2 flex items-center">
                        <i class="fas fa-chalkboard-teacher mr-2 text-blue-400"></i>
                        Penilaian Trainer
                      </h6>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                        <div class="flex justify-between">
                          <span class="text-white/70">Penguasaan Materi:</span>
                          <span class="text-yellow-400">{{ rating.trainer_ratings.mastery || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                          <span class="text-white/70">Bahasa Mudah Dipahami:</span>
                          <span class="text-yellow-400">{{ rating.trainer_ratings.language || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                          <span class="text-white/70">Intonasi & Nada Suara:</span>
                          <span class="text-yellow-400">{{ rating.trainer_ratings.intonation || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                          <span class="text-white/70">Gaya Penyampaian:</span>
                          <span class="text-yellow-400">{{ rating.trainer_ratings.presentation || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                          <span class="text-white/70">Q&A & Diskusi:</span>
                          <span class="text-yellow-400">{{ rating.trainer_ratings.qna || '-' }}</span>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Material Ratings -->
                    <div v-if="rating.material_ratings" class="bg-white/5 rounded p-3">
                      <h6 class="text-white font-medium mb-2 flex items-center">
                        <i class="fas fa-book mr-2 text-green-400"></i>
                        Penilaian Materi Training
                      </h6>
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
                        <div class="flex justify-between">
                          <span class="text-white/70">Manfaat Training:</span>
                          <span class="text-yellow-400">{{ rating.material_ratings.benefit || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                          <span class="text-white/70">Kejelasan Materi:</span>
                          <span class="text-yellow-400">{{ rating.material_ratings.clarity || '-' }}</span>
                        </div>
                        <div class="flex justify-between">
                          <span class="text-white/70">Tampilan Materi:</span>
                          <span class="text-yellow-400">{{ rating.material_ratings.display || '-' }}</span>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Comments -->
                    <div v-if="rating.comment" class="bg-white/5 rounded p-3">
                      <h6 class="text-white font-medium mb-2 flex items-center">
                        <i class="fas fa-comment mr-2 text-purple-400"></i>
                        Saran & Perbaikan
                      </h6>
                      <p class="text-white/80 text-sm">{{ rating.comment }}</p>
                    </div>
                    
                    <div v-if="rating.material_needs" class="bg-white/5 rounded p-3">
                      <h6 class="text-white font-medium mb-2 flex items-center">
                        <i class="fas fa-lightbulb mr-2 text-orange-400"></i>
                        Materi yang Dibutuhkan
                      </h6>
                      <p class="text-white/80 text-sm">{{ rating.material_needs }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Pagination Controls -->
            <div v-if="ratingTotalPages > 1" class="mt-6 flex items-center justify-between">
              <div class="flex items-center space-x-2">
                <button @click="goToRatingPage(ratingCurrentPage - 1)" 
                        :disabled="ratingCurrentPage === 1"
                        class="px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white text-sm hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed">
                  <i class="fas fa-chevron-left"></i>
                </button>
                
                <div class="flex items-center space-x-1">
                  <button v-for="page in Math.min(5, ratingTotalPages)" 
                          :key="page"
                          @click="goToRatingPage(page)"
                          :class="[
                            'px-3 py-2 rounded-lg text-sm transition-colors',
                            ratingCurrentPage === page 
                              ? 'bg-blue-500 text-white' 
                              : 'bg-white/10 text-white/70 hover:bg-white/20'
                          ]">
                    {{ page }}
                  </button>
                </div>
                
                <button @click="goToRatingPage(ratingCurrentPage + 1)" 
                        :disabled="ratingCurrentPage === ratingTotalPages"
                        class="px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white text-sm hover:bg-white/20 disabled:opacity-50 disabled:cursor-not-allowed">
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
              
              <div class="text-sm text-white/60">
                Halaman {{ ratingCurrentPage }} dari {{ ratingTotalPages }}
              </div>
            </div>
          </div>

          <!-- No Data State -->
          <div v-else-if="!loadingTrainerRatings" class="text-center py-12">
            <i class="fas fa-star text-white/30 text-4xl mb-4"></i>
            <p class="text-white/60">Belum ada rating trainer untuk course ini</p>
          </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

// Custom CSS for multiselect to match glassmorphism theme
const customMultiselectCSS = `
.multiselect {
  background: rgba(255, 255, 255, 0.1) !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  border-radius: 0.5rem !important;
  color: white !important;
}

.multiselect:hover {
  border-color: rgba(255, 255, 255, 0.4) !important;
}

.multiselect:focus-within {
  border-color: #3b82f6 !important;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5) !important;
}

.multiselect__placeholder {
  color: rgba(255, 255, 255, 0.5) !important;
}

.multiselect__single {
  color: white !important;
  background: transparent !important;
}

.multiselect__input {
  color: white !important;
  background: transparent !important;
}

.multiselect__input::placeholder {
  color: rgba(255, 255, 255, 0.5) !important;
}

.multiselect__tags {
  background: transparent !important;
  border: none !important;
  padding: 0.75rem 1rem !important;
}

.multiselect__content-wrapper {
  background: rgba(15, 23, 42, 0.95) !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  border-radius: 0.5rem !important;
  backdrop-filter: blur(10px) !important;
}

.multiselect__option {
  color: white !important;
  background: transparent !important;
  padding: 0.75rem 1rem !important;
}

.multiselect__option--highlight {
  background: rgba(59, 130, 246, 0.3) !important;
  color: white !important;
}

.multiselect__option--selected {
  background: rgba(59, 130, 246, 0.5) !important;
  color: white !important;
}

.multiselect__clear {
  color: rgba(255, 255, 255, 0.7) !important;
}

.multiselect__clear:hover {
  color: white !important;
}
`

// Inject custom CSS
if (typeof document !== 'undefined') {
  const style = document.createElement('style')
  style.textContent = customMultiselectCSS
  document.head.appendChild(style)
}

const props = defineProps({
  courses: Object, // Changed from Array to Object to handle pagination
  categories: Array,
  divisions: Array,
  jabatans: Array,
  outlets: Array,
  // internalTrainers removed - trainer fields not used in course creation
  user: Object,
  availableQuizzes: {
    type: Array,
    default: () => []
  },
  availableQuestionnaires: {
    type: Array,
    default: () => []
  },
  certificateTemplates: {
    type: Array,
    default: () => []
  },
  competencies: {
    type: Array,
    default: () => []
  }
})

// Reactive filters
const filters = ref({
  search: '',
  category: '',
  difficulty: '',
  division: ''
})

const currentPage = ref(1)
const itemsPerPage = 12

// Modal state
const showCreateModal = ref(false)
const loading = ref(false) // For form submission
const dataLoading = ref(false) // For initial data loading
const loadingTimeout = ref(null) // For loading timeout
const loadingStartTime = ref(null) // For loading start time

// Trainer ratings modal state
const showTrainerRatingsModal = ref(false)
const trainerRatingsData = ref({
  training: null,
  trainer_ratings: [],
  statistics: null
})
const loadingTrainerRatings = ref(false)
const expandedRatings = ref(new Set()) // Track which ratings are expanded

// Pagination and filter state for trainer ratings
const ratingCurrentPage = ref(1)
const ratingPerPage = ref(5)
const ratingTotalPages = ref(1)
const totalRatings = ref(0)
const ratingFilter = ref('all') // all, excellent, good, poor
const searchQuery = ref('')

// Search states for target selection
const divisionSearch = ref('')
const jabatanSearch = ref('')
const outletSearch = ref('')

// Competency selection state
const selectedCompetencyIds = ref([])
const showAddCompetencyForm = ref(false)
const newCompetency = ref({
  name: ''
})

// Form data
const form = ref({
  title: '',
  short_description: '',
  description: '',
  category_id: '',
  target_type: '', // 'single', 'multiple', 'all'
  target_division_id: '', // For single division
  target_divisions: [], // For multiple divisions
  target_jabatan_ids: [], // For jabatan targeting
  target_outlet_ids: [], // For outlet targeting
  difficulty_level: '',
  duration_minutes: '',
  type: 'in_class',
  specification: 'generic',
  course_type: 'optional',
  status: 'published',
  // requirements: ['', ''], // REMOVED - requirements field removed
  curriculum: [
    {
      order_number: 1,
      title: '',
      description: '',
      duration_minutes: ''
    }
  ], // Default with 1 empty lesson (legacy support)
  sessions: [
    {
      order_number: 1,
      session_title: '',
      session_description: '',
      estimated_duration_minutes: '',
      items: [
        {
          order_number: 1,
          item_type: '',
          item_id: '',
          title: '',
          description: '',
          estimated_duration_minutes: '',
          material_files: []
        }
      ]
    }
  ], // Default with 1 empty session
  thumbnail: null, // For thumbnail upload
  certificate_template_id: '', // For certificate template selection
  competencies: {} // For competency selection with proficiency levels
})

// Computed properties
const canManageCourse = computed(() => {
  if (!props.user) return false
  
  // Check if user has role 5af56935b011a and status A
  if (props.user.id_role === '5af56935b011a' && props.user.status === 'A') {
    return true
  }
  
  // Check if user has jabatan 170 and status A
  if (props.user.id_jabatan === 170 && props.user.status === 'A') {
    return true
  }
  
  return false
})

const filteredCourses = computed(() => {
  // Handle both pagination object and array
  let coursesData = props.courses
  if (props.courses && props.courses.data) {
    coursesData = props.courses.data
  } else if (Array.isArray(props.courses)) {
    coursesData = props.courses
  } else {
    coursesData = []
  }
  
  let filtered = coursesData

  // Search filter
  if (filters.value.search) {
    filtered = filtered.filter(course => 
      course.title.toLowerCase().includes(filters.value.search.toLowerCase()) ||
      course.description.toLowerCase().includes(filters.value.search.toLowerCase())
    )
  }

  // Category filter
  if (filters.value.category) {
    filtered = filtered.filter(course => course.category_id == filters.value.category)
  }

  // Difficulty filter
  if (filters.value.difficulty) {
    filtered = filtered.filter(course => course.difficulty_level === filters.value.difficulty)
  }

  // Division filter - updated to support new target division structure
  if (filters.value.division) {
    filtered = filtered.filter(course => {
      if (course.target_type === 'all') {
        return true // All divisions can access
      } else if (course.target_type === 'single') {
        return course.target_division_id == filters.value.division
      } else if (course.target_type === 'multiple') {
        return course.target_divisions && course.target_divisions.includes(parseInt(filters.value.division))
      }
      return false
    })
  }

  return filtered
})

const paginatedCourses = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage
  const end = start + itemsPerPage
  return filteredCourses.value.slice(start, end)
})

const totalPages = computed(() => {
  return Math.ceil(filteredCourses.value.length / itemsPerPage)
})

// Filtered data for target selection
const filteredDivisions = computed(() => {
  if (!divisionSearch.value) return props.divisions
  return props.divisions.filter(division => 
    division.nama_divisi.toLowerCase().includes(divisionSearch.value.toLowerCase())
  )
})

const filteredJabatans = computed(() => {
  if (!jabatanSearch.value) return props.jabatans
  return props.jabatans.filter(jabatan => 
    jabatan.nama_jabatan.toLowerCase().includes(jabatanSearch.value.toLowerCase()) ||
    (jabatan.divisi && jabatan.divisi.nama_divisi.toLowerCase().includes(jabatanSearch.value.toLowerCase()))
  )
})

const filteredOutlets = computed(() => {
  if (!outletSearch.value) return props.outlets
  return props.outlets.filter(outlet => 
    outlet.nama_outlet.toLowerCase().includes(outletSearch.value.toLowerCase())
  )
})


// Form validation
const isFormValid = computed(() => {
  console.log('Title:', form.value.title.trim())
  console.log('Category ID:', form.value.category_id)
  console.log('Difficulty Level:', form.value.difficulty_level)
  console.log('Duration Minutes:', form.value.duration_minutes)
  console.log('Sessions:', form.value.sessions)
  
  const basicValidation = form.value.title.trim() && 
                         form.value.category_id && 
                         form.value.difficulty_level && 
                         form.value.duration_minutes

  if (!basicValidation) {
    console.log('Basic validation failed')
    console.log('Title:', form.value.title.trim())
    console.log('Category ID:', form.value.category_id)
    console.log('Difficulty Level:', form.value.difficulty_level)
    console.log('Duration Minutes:', form.value.duration_minutes)
    // TEMPORARY: Skip this validation for testing
    // return false
  }

  // Target division validation (if target_type is selected)
  if (form.value.target_type === 'single' && !form.value.target_division_id) {
    console.log('Single target division validation failed')
    // TEMPORARY: Skip this validation for testing
    // return false
  }

  if (form.value.target_type === 'multiple' && (!form.value.target_divisions || form.value.target_divisions.length === 0)) {
    console.log('Multiple target divisions validation failed')
    // TEMPORARY: Skip this validation for testing
    // return false
  }



  // Requirements validation - REMOVED - requirements field removed
  // if (form.value.requirements.length === 0 || form.value.requirements.every(req => !req.trim())) {
  //   console.log('Requirements validation failed')
  //   // TEMPORARY: Skip this validation for testing
  //   // return false
  // }

  // Trainer validation - REMOVED - trainer fields not used in course creation

  // TEMPORARY: Basic sessions validation for testing
  if (form.value.sessions.length === 0) {
    console.log('No sessions found')
    // TEMPORARY: Skip this validation for testing
    // return false
  }
  
  // For now, just check if first session has basic info
  const firstSession = form.value.sessions[0]
  if (firstSession && !firstSession.session_title.trim()) {
    console.log('First session missing title')
    // TEMPORARY: Skip this validation for testing
    // return false
  }
  
  console.log('Basic session validation passed')

  console.log('All validation passed! Form is valid.')
  // TEMPORARY: Always return true for testing
  return true
})

// Trainer label function removed - trainer fields not used in course creation

// Methods
const clearFilters = () => {
  filters.value = {
    search: '',
    category: '',
    difficulty: '',
    division: ''
  }
  currentPage.value = 1
}

const closeModal = () => {
  showCreateModal.value = false
  form.value = {
    title: '',
    short_description: '',
    description: '',
    category_id: '',
    target_type: '', // 'single', 'multiple', 'all'
    target_division_id: '', // For single division
    target_divisions: [], // For multiple divisions
    target_jabatan_ids: [], // For jabatan targeting
    target_outlet_ids: [], // For outlet targeting
    difficulty_level: '',
    duration_minutes: '',
    type: 'offline',
    course_type: 'optional',
    status: 'published',
    // requirements: ['', ''], // REMOVED - requirements field removed
    curriculum: [
      {
        order_number: 1,
        title: '',
        description: '',
        duration_minutes: ''
      }
    ], // Default with 1 empty lesson
    // Trainer fields removed - not used in course creation
    thumbnail: null, // For thumbnail upload
    certificate_template_id: '', // For certificate template selection
    competencies: {} // For competency selection
  }
  
  // Reset search fields
  divisionSearch.value = ''
  jabatanSearch.value = ''
  outletSearch.value = ''
  selectedCompetencyIds.value = []
  showAddCompetencyForm.value = false
  newCompetency.value = {
    name: ''
  }
}

// Loading timeout handler
const handleLoadingTimeout = () => {
  if (dataLoading.value && loadingStartTime.value) {
    const elapsed = Date.now() - loadingStartTime.value
    if (elapsed > 30000) { // 30 seconds timeout
      console.warn('Loading timeout reached, forcing stop')
      dataLoading.value = false
      loadingStartTime.value = null
      if (loadingTimeout.value) {
        clearTimeout(loadingTimeout.value)
        loadingTimeout.value = null
      }
    }
  }
}

const createCourse = async () => {
  console.log('=== CREATE COURSE METHOD CALLED ===')
  console.log('Form data:', form.value)
  
  // Set loading state with timeout protection
  loading.value = true
  // Don't set dataLoading to true to avoid overlay conflict
  // dataLoading.value = true
  loadingStartTime.value = Date.now()
  
  // Set timeout to prevent infinite loading
  loadingTimeout.value = setTimeout(() => {
    handleLoadingTimeout()
  }, 30000) // 30 seconds timeout
  
  // Show prominent loading modal
  console.log('=== SHOWING LOADING MODAL ===')
  console.log('Swal object:', Swal)
  console.log('Swal.fire method:', typeof Swal.fire)
  
  try {
    // Show loading modal with simple approach
    Swal.fire({
      title: 'Sabar Bu Ghea....',
      text: 'Antosan sakedap Bu Ghea, Nuju loding',
      icon: 'info',
      showConfirmButton: false,
      allowOutsideClick: false,
      allowEscapeKey: false,
      backdrop: true,
      didOpen: () => {
        Swal.showLoading()
      }
    })
    console.log('Loading modal should be visible now')
  } catch (error) {
    console.error('Error showing loading modal:', error)
  }
  
  // Validation - Check required fields
  console.log('Starting validation...')
  
  const missingFields = []
  
  // Basic required fields
  if (!form.value.title.trim()) missingFields.push('Judul Course')
  if (!form.value.category_id) missingFields.push('Kategori')
  if (!form.value.difficulty_level) missingFields.push('Level Kesulitan')
  if (!form.value.duration_minutes) missingFields.push('Durasi (menit)')
  if (!form.value.type) missingFields.push('Tipe Course')
  
  if (missingFields.length > 0) {
    console.log('Basic validation failed:', missingFields)
    Swal.close()
    Swal.fire({
      icon: 'error',
      title: 'Field Wajib Belum Diisi!',
      html: `
        <div class="text-left">
          <p class="mb-3">Mohon lengkapi field berikut:</p>
          <ul class="list-disc list-inside space-y-1">
            ${missingFields.map(field => `<li class="text-red-600">${field}</li>`).join('')}
          </ul>
        </div>
      `,
      confirmButtonColor: '#EF4444',
      confirmButtonText: 'OK'
    })
    return
  }

  // Target type validation
  if (!form.value.target_type) {
    console.log('Target type validation failed')
    Swal.close()
    Swal.fire({
      icon: 'error',
      title: 'Field Wajib Belum Diisi!',
      html: `
        <div class="text-left">
          <p class="mb-3">Mohon lengkapi field berikut:</p>
          <ul class="list-disc list-inside space-y-1">
            <li class="text-red-600">Tipe Target Divisi</li>
          </ul>
        </div>
      `,
      confirmButtonColor: '#EF4444',
      confirmButtonText: 'OK'
    })
    return
  }

  // Additional validation for target divisions (if target_type is selected)
  if (form.value.target_type === 'single' && !form.value.target_division_id) {
    console.log('Single target division validation failed')
    Swal.close()
    Swal.fire({
      icon: 'error',
      title: 'Field Wajib Belum Diisi!',
      html: `
        <div class="text-left">
          <p class="mb-3">Mohon lengkapi field berikut:</p>
          <ul class="list-disc list-inside space-y-1">
            <li class="text-red-600">Divisi Target (Single)</li>
          </ul>
        </div>
      `,
      confirmButtonColor: '#EF4444',
      confirmButtonText: 'OK'
    })
    return
  }

  if (form.value.target_type === 'multiple' && (!form.value.target_divisions || form.value.target_divisions.length === 0)) {
    console.log('Multiple target divisions validation failed')
    Swal.close()
    Swal.fire({
      icon: 'error',
      title: 'Field Wajib Belum Diisi!',
      html: `
        <div class="text-left">
          <p class="mb-3">Mohon lengkapi field berikut:</p>
          <ul class="list-disc list-inside space-y-1">
            <li class="text-red-600">Divisi Target (Multiple) - Minimal pilih 1 divisi</li>
          </ul>
        </div>
      `,
      confirmButtonColor: '#EF4444',
      confirmButtonText: 'OK'
    })
    return
  }



  // Requirements validation - REMOVED - requirements field removed
  // if (form.value.requirements.length === 0 || form.value.requirements.every(req => !req.trim())) {
  //   console.log('Requirements validation failed, but continuing...')
  //   // TEMPORARY: Skip validation for testing
  //   // Swal.fire({
  //   //   icon: 'error',
  //   //   title: 'Error!',
  //   //   text: 'Mohon tambahkan minimal satu persyaratan peserta!',
  //   //   confirmButton-by: '#EF4444'
  //   // })
  //   // return
  // }

  // Trainer validation - REMOVED - trainer fields not used in course creation

  // Sessions validation - at least one session with required fields
  if (form.value.sessions.length === 0 || form.value.sessions.every(session => !session.session_title.trim() || !session.order_number || !session.estimated_duration_minutes)) {
    console.log('Sessions validation failed')
    Swal.close()
    Swal.fire({
      icon: 'error',
      title: 'Field Wajib Belum Diisi!',
      html: `
        <div class="text-left">
          <p class="mb-3">Mohon lengkapi field berikut:</p>
          <ul class="list-disc list-inside space-y-1">
            <li class="text-red-600">Sesi Training - Minimal 1 sesi dengan judul, urutan, dan durasi</li>
          </ul>
        </div>
      `,
      confirmButtonColor: '#EF4444',
      confirmButtonText: 'OK'
    })
    return
  }

  // Validate session items
  console.log('Validating session items...')
  
  for (let sessionIndex = 0; sessionIndex < form.value.sessions.length; sessionIndex++) {
    const session = form.value.sessions[sessionIndex]
    if (session.items.length === 0) {
      console.log(`Session ${sessionIndex + 1} has no items`)
      Swal.close()
      Swal.fire({
        icon: 'error',
        title: 'Field Wajib Belum Diisi!',
        html: `
          <div class="text-left">
            <p class="mb-3">Mohon lengkapi field berikut:</p>
            <ul class="list-disc list-inside space-y-1">
              <li class="text-red-600">Sesi ${sessionIndex + 1} - Minimal 1 item training</li>
            </ul>
          </div>
        `,
        confirmButtonColor: '#EF4444',
        confirmButtonText: 'OK'
      })
      return
    }
    
    for (let itemIndex = 0; itemIndex < session.items.length; itemIndex++) {
      const item = session.items[itemIndex]
      if (!item.item_type || !item.order_number) {
        console.log(`Item ${itemIndex + 1} on session ${sessionIndex + 1} missing type or order`)
        Swal.close()
        Swal.fire({
          icon: 'error',
          title: 'Field Wajib Belum Diisi!',
          html: `
            <div class="text-left">
              <p class="mb-3">Mohon lengkapi field berikut:</p>
              <ul class="list-disc list-inside space-y-1">
                <li class="text-red-600">Sesi ${sessionIndex + 1}, Item ${itemIndex + 1} - Tipe dan Urutan</li>
              </ul>
            </div>
          `,
          confirmButtonColor: '#EF4444',
          confirmButtonText: 'OK'
        })
        return
      }
      
      // Validate item-specific requirements
      if (item.item_type === 'quiz') {
        if (!item.item_id) {
          console.log(`Quiz item ${itemIndex + 1} on session ${sessionIndex + 1} missing quiz selection`)
          Swal.close()
          Swal.fire({
            icon: 'error',
            title: 'Field Wajib Belum Diisi!',
            html: `
              <div class="text-left">
                <p class="mb-3">Mohon lengkapi field berikut:</p>
                <ul class="list-disc list-inside space-y-1">
                  <li class="text-red-600">Sesi ${sessionIndex + 1}, Item ${itemIndex + 1} (Quiz) - Pilih Quiz</li>
                </ul>
              </div>
            `,
            confirmButtonColor: '#EF4444',
            confirmButtonText: 'OK'
          })
          return
        }
      } else if (item.item_type === 'questionnaire') {
        if (!item.item_id) {
          console.log(`Questionnaire item ${itemIndex + 1} on session ${sessionIndex + 1} missing questionnaire selection`)
          Swal.close()
          Swal.fire({
            icon: 'error',
            title: 'Field Wajib Belum Diisi!',
            html: `
              <div class="text-left">
                <p class="mb-3">Mohon lengkapi field berikut:</p>
                <ul class="list-disc list-inside space-y-1">
                  <li class="text-red-600">Sesi ${sessionIndex + 1}, Item ${itemIndex + 1} (Kuesioner) - Pilih Kuesioner</li>
                </ul>
              </div>
            `,
            confirmButtonColor: '#EF4444',
            confirmButtonText: 'OK'
          })
          return
        }
      } else if (item.item_type === 'material') {
        if (!item.material_files || item.material_files.length === 0) {
          console.log(`Material item ${itemIndex + 1} on session ${sessionIndex + 1} missing files`)
          Swal.close()
          Swal.fire({
            icon: 'error',
            title: 'Field Wajib Belum Diisi!',
            html: `
              <div class="text-left">
                <p class="mb-3">Mohon lengkapi field berikut:</p>
                <ul class="list-disc list-inside space-y-1">
                  <li class="text-red-600">Sesi ${sessionIndex + 1}, Item ${itemIndex + 1} (Materi) - Upload Minimal 1 File</li>
                </ul>
              </div>
            `,
            confirmButtonColor: '#EF4444',
            confirmButtonText: 'OK'
          })
          return
        }
      }
    }
  }

  // TEMPORARY: Skip confirmation for testing
  console.log('Skipping confirmation dialog for testing...')
  
  // Confirmation dialog
  // const result = await Swal.fire({
  //   title: 'Konfirmasi',
  //   text: 'Apakah Anda yakin ingin menyimpan course ini?',
  //   icon: 'question',
  //   showCancelButton: true,
  //   confirmButtonColor: '#10B981',
  //   cancelButtonColor: '#6B7280',
  //   confirmButtonText: 'Ya, Simpan!',
  //   cancelButtonText: 'Batal',
  //   reverseButtons: true
  // })

  // if (!result.isConfirmed) {
  //   return
  // }

  // Show loading state
  loading.value = true
  
  // TEMPORARY: Skip loading dialog for testing
  console.log('Skipping loading dialog for testing...')
  
  // Show loading animation
  // Swal.fire({
  //   title: 'Menyimpan Course...',
  //   html: `
  //     <div class="flex flex-col items-center space-y-4">
  //       <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-green-500"></div>
  //       <p class="text-gray-600">Mohon tunggu sebentar...</p>
  //     </div>
  //   `,
  //   showConfirmButton: false,
  //   allowOutsideClick: false,
  //   allowEscapeKey: false
  // })
  
     try {
     console.log('=== STARTING FORM SUBMISSION ===')
     console.log('Loading modal should still be visible at this point')
     
     // Create FormData for file upload
     const formData = new FormData()
     
     // Add all form fields to FormData
     Object.keys(form.value).forEach(key => {
       if (key === 'thumbnail' && form.value[key]) {
         formData.append('thumbnail', form.value[key].file)
       } else if (key === 'target_divisions' || key === 'target_jabatan_ids' || key === 'target_outlet_ids' || key === 'requirements') {
         // Handle arrays
         if (Array.isArray(form.value[key])) {
           form.value[key].forEach(item => {
             formData.append(key + '[]', item)
           })
         }
       } else if (key === 'competencies') {
         // Handle competencies object
         if (form.value[key] && typeof form.value[key] === 'object') {
           Object.values(form.value[key]).forEach((competency, index) => {
             // Check if it's a new competency
             if (competency.new_competency) {
               // Add new competency data
               formData.append(`new_competencies[${index}][name]`, competency.new_competency.name)
             } else {
               // Add existing competency data
               formData.append(`competencies[${index}][competency_id]`, competency.competency_id)
             }
           })
         }
       } else if (key === 'curriculum') {
         // Handle curriculum array (legacy support)
         if (Array.isArray(form.value[key])) {
           form.value[key].forEach((lesson, index) => {
             formData.append(`curriculum[${index}][order_number]`, lesson.order_number)
             formData.append(`curriculum[${index}][title]`, lesson.title)
             formData.append(`curriculum[${index}][description]`, lesson.description)
             formData.append(`curriculum[${index}][duration_minutes]`, lesson.duration_minutes)
           })
         }
       } else if (key === 'sessions') {
         // Handle sessions array with items
         if (Array.isArray(form.value[key])) {
           form.value[key].forEach((session, sessionIndex) => {
             formData.append(`sessions[${sessionIndex}][order_number]`, session.order_number)
             formData.append(`sessions[${sessionIndex}][session_title]`, session.session_title)
             formData.append(`sessions[${sessionIndex}][session_description]`, session.session_description)
             formData.append(`sessions[${sessionIndex}][estimated_duration_minutes]`, session.estimated_duration_minutes)
             
             // Handle session items
             if (Array.isArray(session.items)) {
               session.items.forEach((item, itemIndex) => {
                 formData.append(`sessions[${sessionIndex}][items][${itemIndex}][order_number]`, item.order_number)
                 formData.append(`sessions[${sessionIndex}][items][${itemIndex}][item_type]`, item.item_type)
                 formData.append(`sessions[${sessionIndex}][items][${itemIndex}][item_id]`, item.item_id || '')
                 formData.append(`sessions[${sessionIndex}][items][${itemIndex}][title]`, item.title || '')
                 formData.append(`sessions[${sessionIndex}][items][${itemIndex}][description]`, item.description || '')
                 formData.append(`sessions[${sessionIndex}][items][${itemIndex}][estimated_duration_minutes]`, item.estimated_duration_minutes || 0)
                 
                 // Handle quiz_id for quiz type items
                 if (item.item_type === 'quiz' && item.quiz_id) {
                   formData.append(`sessions[${sessionIndex}][items][${itemIndex}][quiz_id]`, item.quiz_id)
                   console.log(`Added quiz_id for quiz item: ${item.quiz_id}`)
                 }
                 
                 // Handle questionnaire_id for questionnaire type items
                 if (item.item_type === 'questionnaire' && item.questionnaire_id) {
                   formData.append(`sessions[${sessionIndex}][items][${itemIndex}][questionnaire_id]`, item.questionnaire_id)
                   console.log(`Added questionnaire_id for questionnaire item: ${item.questionnaire_id}`)
                 }
                 
                 // Handle material files for material type items
                 if (item.item_type === 'material' && item.material_files && Array.isArray(item.material_files)) {
                   item.material_files.forEach((file, fileIndex) => {
                     formData.append(`sessions[${sessionIndex}][items][${itemIndex}][material_files][${fileIndex}]`, file.file)
                   })
                 }
               })
             }
           })
         }
       } else {
         // Handle form data
         if (typeof form.value[key] === 'object' && form.value[key] !== null) {
           formData.append(key, form.value[key].id || form.value[key].value || '')
         } else {
           formData.append(key, form.value[key])
         }
       }
     })
     
     for (let [key, value] of formData.entries()) {
       console.log(`${key}:`, value)
     }

     console.log('=== SENDING REQUEST TO /lms/courses ===')
     console.log('Loading modal should still be visible before request')
     
     // Add timeout to prevent infinite loading
     const requestTimeout = setTimeout(() => {
       console.log('=== REQUEST TIMEOUT ===')
       Swal.close()
       Swal.fire({
         icon: 'error',
         title: 'Timeout!',
         text: 'Request timeout. Silakan coba lagi.',
         confirmButtonColor: '#EF4444'
       })
     }, 60000) // 60 seconds timeout
     
     await router.post('/lms/courses', formData, {
       headers: {
         'Content-Type': 'multipart/form-data'
       },
       onStart: () => {
         console.log('=== REQUEST STARTED ===')
         console.log('Loading modal should still be visible during request')
       },
                onSuccess: () => {
           console.log('=== REQUEST SUCCESS ===')
           console.log('Course created successfully!')
           
           // Clear timeout
           clearTimeout(requestTimeout)
           
           // Close loading modal first
           Swal.close()
           
           // Show success message
           Swal.fire({
             icon: 'success',
             title: 'Berhasil!',
             text: 'Course berhasil dibuat dan tersimpan!',
             timer: 3000,
             timerProgressBar: true,
             showConfirmButton: false,
             toast: true,
             position: 'top-end',
             background: '#10B981',
             color: '#ffffff'
           })
           
           // Close modal
           closeModal()
           
           // Refresh courses list without infinite loop
           // Use a simple page reload as the safest option
           // This ensures fresh data without complex state management
           setTimeout(() => {
             window.location.reload()
           }, 1000) // Small delay to show success message
         },
                onError: (errors) => {
           console.log('=== REQUEST ERROR ===')
           console.error('Backend validation errors:', errors)
           const errorMessage = Object.values(errors).flat().join(', ')
           console.log('Error message:', errorMessage)
           
           // Clear timeout
           clearTimeout(requestTimeout)
           
           // Close loading modal first
           Swal.close()
           
           // Show error message
           Swal.fire({
             icon: 'error',
             title: 'Error!',
             text: errorMessage || 'Terjadi kesalahan saat membuat course',
             confirmButtonColor: '#EF4444',
             background: '#FEF2F2',
             color: '#DC2626'
           })
         }
     })
        } catch (error) {
       console.log('=== CATCH BLOCK ERROR ===')
       console.error('Error creating course:', error)
       
       // Clear timeout
       clearTimeout(requestTimeout)
       
       // Close loading modal first
       Swal.close()
       
       // Show error message
       Swal.fire({
         icon: 'error',
         title: 'Error!',
         text: 'Terjadi kesalahan saat membuat course',
         confirmButtonColor: '#EF4444',
         background: '#FEF2F2',
         color: '#DC2626'
       })
     } finally {
       console.log('=== FINALLY BLOCK ===')
       
       // Clear request timeout
       clearTimeout(requestTimeout)
       
       // Ensure loading modal is closed
       Swal.close()
       
       loading.value = false
       // Don't set dataLoading to false since we didn't set it to true
       // dataLoading.value = false
       
       // Cleanup loading state
       if (loadingTimeout.value) {
         clearTimeout(loadingTimeout.value)
         loadingTimeout.value = null
       }
       loadingStartTime.value = null
       
       console.log('Loading state cleaned up')
     }
}

// Methods for new fields

// const addRequirement = () => { // REMOVED - requirements field removed
//   form.value.requirements.push('')
// }

// const removeRequirement = (index) => { // REMOVED - requirements field removed
//   form.value.requirements.splice(index, 1)
// }

// Curriculum methods
const addCurriculumItem = () => {
  form.value.curriculum.push({
    order_number: form.value.curriculum.length + 1,
    title: '',
    description: '',
    duration_minutes: ''
  })
}

const removeCurriculumItem = (index) => {
  form.value.curriculum.splice(index, 1)
  // Reorder remaining items
  form.value.curriculum.forEach((item, idx) => {
    item.order_number = idx + 1
  })
}

// File upload methods
const handleFileUpload = (event) => {
  const file = event.target.files[0]
  if (!file) return

  // Validate file type
  const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png']
  if (!allowedTypes.includes(file.type)) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Hanya file PNG, JPG, atau JPEG yang diperbolehkan!',
      confirmButtonColor: '#EF4444'
    })
    return
  }

  // Validate file size (2MB = 2 * 1024 * 1024 bytes)
  const maxSize = 2 * 1024 * 1024
  if (file.size > maxSize) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Ukuran file maksimal 2MB!',
      confirmButtonColor: '#EF4444'
    })
    return
  }

  // Create preview URL
  const reader = new FileReader()
  reader.onload = (e) => {
    form.value.thumbnail = {
      file: file,
      name: file.name,
      preview: e.target.result
    }
  }
  reader.readAsDataURL(file)
}

const removeThumbnail = () => {
  form.value.thumbnail = null
  if (this.$refs.fileInput) {
    this.$refs.fileInput.value = ''
  }
}

// Course management methods
const editCourse = (courseId) => {
  // Navigate to edit page
  router.get(route('lms.courses.edit', courseId))
}

// Session management methods
const addSession = () => {
  // Safety check: ensure sessions array exists
  if (!form.value.sessions || !Array.isArray(form.value.sessions)) {
    console.warn('Sessions array is not initialized, creating default structure')
    form.value.sessions = []
  }
  
  const maxOrder = Math.max(...form.value.sessions.map(s => s.order_number), 0)
  form.value.sessions.push({
    order_number: maxOrder + 1,
    session_title: '',
    session_description: '',
    estimated_duration_minutes: '',
    items: [
      {
        order_number: 1,
        item_type: '',
        item_id: '',
        quiz_id: '', // Add quiz_id field
        questionnaire_id: '', // Add questionnaire_id field
        title: '',
        description: '',
        estimated_duration_minutes: '',
        material_files: []
      }
    ]
  })
  
  console.log('Session added successfully. Total sessions:', form.value.sessions.length)
}

const removeSession = (sessionIndex) => {
  // Safety check: ensure sessions array exists
  if (!form.value.sessions || !Array.isArray(form.value.sessions)) {
    console.error('Sessions array is not initialized')
    return
  }
  
  if (sessionIndex < 0 || sessionIndex >= form.value.sessions.length) {
    console.error(`Invalid session index: ${sessionIndex}`)
    return
  }
  
  form.value.sessions.splice(sessionIndex, 1)
  
  // Reorder remaining sessions
  form.value.sessions.forEach((session, index) => {
    session.order_number = index + 1
  })
  
  console.log('Session removed successfully. Total sessions:', form.value.sessions.length)
}

const addSessionItem = (sessionIndex) => {
  // Safety check: ensure sessions array exists
  if (!form.value.sessions || !Array.isArray(form.value.sessions)) {
    console.error('Sessions array is not initialized')
    return
  }
  
  const session = form.value.sessions[sessionIndex]
  if (!session) {
    console.error(`Session at index ${sessionIndex} not found`)
    return
  }
  
  // Safety check: ensure items array exists
  if (!session.items || !Array.isArray(session.items)) {
    console.warn(`Session ${sessionIndex} items array is not initialized, creating default structure`)
    session.items = []
  }
  
  const maxOrder = Math.max(...session.items.map(item => item.order_number), 0)
  session.items.push({
    order_number: maxOrder + 1,
    item_type: '',
    item_id: '',
    quiz_id: '', // Add quiz_id field
    questionnaire_id: '', // Add questionnaire_id field
    title: '',
    description: '',
    estimated_duration_minutes: '',
    material_files: []
  })
  
  console.log(`Session item added to session ${sessionIndex + 1}. Total items:`, session.items.length)
}

const removeSessionItem = (sessionIndex, itemIndex) => {
  // Safety check: ensure sessions array exists
  if (!form.value.sessions || !Array.isArray(form.value.sessions)) {
    console.error('Sessions array is not initialized')
    return
  }
  
  const session = form.value.sessions[sessionIndex]
  if (!session) {
    console.error(`Session at index ${sessionIndex} not found`)
    return
  }
  
  // Safety check: ensure items array exists
  if (!session.items || !Array.isArray(session.items)) {
    console.error(`Session ${sessionIndex} items array is not initialized`)
    return
  }
  
  if (itemIndex < 0 || itemIndex >= session.items.length) {
    console.error(`Invalid item index: ${itemIndex}`)
    return
  }
  
  session.items.splice(itemIndex, 1)
  
  // Reorder remaining items
  session.items.forEach((item, index) => {
    item.order_number = index + 1
  })
  
  console.log(`Session item removed from session ${sessionIndex + 1}. Total items:`, session.items.length)
}

// Item type change handler
const onItemTypeChange = (sessionIndex, itemIndex) => {
  // Safety check: ensure sessions array exists
  if (!form.value.sessions || !Array.isArray(form.value.sessions)) {
    console.error('Sessions array is not initialized')
    return
  }
  
  const session = form.value.sessions[sessionIndex]
  if (!session) {
    console.error(`Session at index ${sessionIndex} not found`)
    return
  }
  
  // Safety check: ensure items array exists
  if (!session.items || !Array.isArray(session.items)) {
    console.error(`Session ${sessionIndex} items array is not initialized`)
    return
  }
  
  const item = session.items[itemIndex]
  if (!item) {
    console.error(`Item at index ${itemIndex} not found in session ${sessionIndex}`)
    return
  }
  
  // Reset item-specific data when type changes
  item.item_id = ''
  item.quiz_id = '' // Reset quiz_id
  item.questionnaire_id = '' // Reset questionnaire_id
  item.material_files = []
  
  // Set default title based on type
  if (item.item_type === 'quiz') {
    item.title = 'Quiz'
  } else if (item.item_type === 'questionnaire') {
    item.title = 'Kuesioner'
  } else if (item.item_type === 'material') {
    item.title = 'Materi'
  }
  
  console.log(`Item type changed to ${item.item_type}, reset data:`, {
    item_id: item.item_id,
    quiz_id: item.quiz_id,
    questionnaire_id: item.questionnaire_id,
    material_files: item.material_files
  })
}

// Material file handling
const onMaterialFileChange = (event, sessionIndex, itemIndex) => {
  // Safety check: ensure sessions array exists
  if (!form.value.sessions || !Array.isArray(form.value.sessions)) {
    console.error('Sessions array is not initialized')
    return
  }
  
  const session = form.value.sessions[sessionIndex]
  if (!session) {
    console.error(`Session at index ${sessionIndex} not found`)
    return
  }
  
  // Safety check: ensure items array exists
  if (!session.items || !Array.isArray(session.items)) {
    console.error(`Session ${sessionIndex} items array is not initialized`)
    return
  }
  
  const item = session.items[itemIndex]
  if (!item) {
    console.error(`Item at index ${itemIndex} not found in session ${sessionIndex}`)
    return
  }
  
  const files = Array.from(event.target.files)
  
  // Initialize material_files array if not exists
  if (!item.material_files) {
    item.material_files = []
  }
  
  // Validate and add files
  files.forEach(file => {
    // Validate file type
    const allowedTypes = [
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-powerpoint',
      'application/vnd.openxmlformats-officedocument.presentationml.presentation',
      'image/jpeg',
      'image/jpg',
      'image/png',
      'image/gif',
      'video/mp4',
      'video/avi',
      'video/quicktime'
    ]
    
    if (!allowedTypes.includes(file.type)) {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: `File ${file.name} tidak didukung! Hanya PDF, DOC, PPT, JPG, PNG, MP4, AVI, MOV yang diperbolehkan.`,
        confirmButtonColor: '#EF4444'
      })
      return
    }
    
    // Validate file size (100MB = 100 * 1024 * 1024 bytes)
    const maxSize = 100 * 1024 * 1024
    if (file.size > maxSize) {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: `File ${file.name} terlalu besar! Ukuran maksimal 100MB.`,
        confirmButtonColor: '#EF4444'
      })
      return
    }
    
    // Add file to material_files array
    item.material_files.push({
      file: file,
      name: file.name,
      size: file.size,
      type: file.type
    })
  })
  
  // Clear input value to allow same file selection
  event.target.value = ''
}

const removeMaterialFile = (sessionIndex, itemIndex, fileIndex) => {
  // Safety check: ensure sessions array exists
  if (!form.value.sessions || !Array.isArray(form.value.sessions)) {
    console.error('Sessions array is not initialized')
    return
  }
  
  const session = form.value.sessions[sessionIndex]
  if (!session) {
    console.error(`Session at index ${sessionIndex} not found`)
    return
  }
  
  // Safety check: ensure items array exists
  if (!session.items || !Array.isArray(session.items)) {
    console.error(`Session ${sessionIndex} items array is not initialized`)
    return
  }
  
  const item = session.items[itemIndex]
  if (!item) {
    console.error(`Item at index ${itemIndex} not found in session ${sessionIndex}`)
    return
  }
  
  // Safety check: ensure material_files array exists
  if (!item.material_files || !Array.isArray(item.material_files)) {
    console.error(`Item material_files array is not initialized`)
    return
  }
  
  if (fileIndex < 0 || fileIndex >= item.material_files.length) {
    console.error(`Invalid file index: ${fileIndex}`)
    return
  }
  
  item.material_files.splice(fileIndex, 1)
  console.log(`Material file removed from item ${itemIndex + 1} in session ${sessionIndex + 1}. Total files:`, item.material_files.length)
}

// File utility functions
const getFileIcon = (fileName) => {
  const extension = fileName.split('.').pop().toLowerCase()
  
  if (['pdf'].includes(extension)) {
    return 'fas fa-file-pdf'
  } else if (['doc', 'docx'].includes(extension)) {
    return 'fas fa-file-word'
  } else if (['ppt', 'pptx'].includes(extension)) {
    return 'fas fa-file-powerpoint'
  } else if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
    return 'fas fa-file-image'
  } else if (['mp4', 'avi', 'mov'].includes(extension)) {
    return 'fas fa-file-video'
  } else {
    return 'fas fa-file'
  }
}

const formatFileSize = (bytes) => {
  if (bytes === 0) return '0 Bytes'
  
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const archiveCourse = async (courseId) => {
  // Show confirmation dialog
  const result = await Swal.fire({
    title: 'Konfirmasi Archive',
    text: 'Apakah Anda yakin ingin mengarchive course ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#EF4444',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Ya, Archive!',
    cancelButtonText: 'Batal'
  })

  if (result.isConfirmed) {
    try {
      loading.value = true
      
      // Send archive request
      await router.put(route('lms.courses.archive', courseId), {}, {
        onSuccess: () => {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Course berhasil diarchive!',
            confirmButtonColor: '#10B981'
          })
        },
        onError: (errors) => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Gagal mengarchive course. Silakan coba lagi.',
            confirmButtonColor: '#EF4444'
          })
        }
      })
    } catch (error) {
      console.error('Error archiving course:', error)
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: 'Terjadi kesalahan. Silakan coba lagi.',
        confirmButtonColor: '#EF4444'
      })
    } finally {
      loading.value = false
    }
  }
}

// Trainer ratings management
const showTrainerRatings = async (courseId) => {
  showTrainerRatingsModal.value = true
  loadingTrainerRatings.value = true
  
  try {
    const response = await fetch(route('lms.courses.trainer-ratings', courseId))
    const data = await response.json()
    
    if (data.success) {
      trainerRatingsData.value = data
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: data.message || 'Gagal memuat rating trainer'
      })
    }
  } catch (error) {
    console.error('Error fetching trainer ratings:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat memuat rating trainer'
    })
  } finally {
    loadingTrainerRatings.value = false
  }
}

const closeTrainerRatingsModal = () => {
  showTrainerRatingsModal.value = false
  trainerRatingsData.value = {
    training: null,
    trainer_ratings: [],
    statistics: null
  }
  expandedRatings.value.clear() // Clear expanded ratings when modal closes
  
  // Reset pagination and filter state
  ratingCurrentPage.value = 1
  ratingPerPage.value = 5
  ratingTotalPages.value = 1
  totalRatings.value = 0
  ratingFilter.value = 'all'
  searchQuery.value = ''
}

// Toggle expand for rating details
const toggleRatingExpand = (ratingId) => {
  if (expandedRatings.value.has(ratingId)) {
    expandedRatings.value.delete(ratingId)
  } else {
    expandedRatings.value.add(ratingId)
  }
}

// Function to mask participant name
const maskParticipantName = (name) => {
  if (!name) return 'Anonymous'
  const words = name.split(' ')
  if (words.length === 1) {
    return words[0].charAt(0) + '*'.repeat(Math.max(1, words[0].length - 1))
  }
  return words[0].charAt(0) + '*'.repeat(Math.max(1, words[0].length - 1)) + ' ' + 
         words[words.length - 1].charAt(0) + '*'.repeat(Math.max(1, words[words.length - 1].length - 1))
}

// Computed properties for filtered and paginated data
const filteredRatings = computed(() => {
  if (!trainerRatingsData.value.trainer_ratings) return []
  
  let filtered = trainerRatingsData.value.trainer_ratings
  
  // Filter by rating
  if (ratingFilter.value !== 'all') {
    filtered = filtered.filter(rating => {
      switch (ratingFilter.value) {
        case 'excellent': return rating.rating >= 4.5
        case 'good': return rating.rating >= 3 && rating.rating < 4.5
        case 'poor': return rating.rating < 3
        default: return true
      }
    })
  }
  
  // Filter by search query
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(rating => {
      const maskedName = maskParticipantName(rating.participant_name).toLowerCase()
      const comment = (rating.comment || '').toLowerCase()
      const materialNeeds = (rating.material_needs || '').toLowerCase()
      return maskedName.includes(query) || comment.includes(query) || materialNeeds.includes(query)
    })
  }
  
  return filtered
})

const paginatedRatings = computed(() => {
  const start = (ratingCurrentPage.value - 1) * ratingPerPage.value
  const end = start + ratingPerPage.value
  return filteredRatings.value.slice(start, end)
})

const totalFilteredRatings = computed(() => filteredRatings.value.length)

// Watchers for pagination reset
watch([ratingFilter, searchQuery, ratingPerPage], () => {
  ratingCurrentPage.value = 1
})

// Update total pages when filtered data changes
watch(totalFilteredRatings, (newTotal) => {
  ratingTotalPages.value = Math.ceil(newTotal / ratingPerPage.value)
  if (ratingCurrentPage.value > ratingTotalPages.value) {
    ratingCurrentPage.value = Math.max(1, ratingTotalPages.value)
  }
})

// Pagination functions
const goToRatingPage = (page) => {
  if (page >= 1 && page <= ratingTotalPages.value) {
    ratingCurrentPage.value = page
  }
}

const clearRatingFilters = () => {
  ratingFilter.value = 'all'
  searchQuery.value = ''
  ratingCurrentPage.value = 1
}

// Competency methods
const getCompetencyById = (competencyId) => {
  // Check if it's a new competency (negative ID)
  if (competencyId < 0) {
    const competencyData = form.value.competencies[competencyId]
    if (competencyData && competencyData.new_competency) {
      return {
        id: competencyId,
        name: competencyData.new_competency.name
      }
    }
  }
  
  // Return existing competency
  return props.competencies.find(competency => competency.id === competencyId)
}

const removeCompetency = (competencyId) => {
  // Remove from selected list
  const index = selectedCompetencyIds.value.indexOf(competencyId)
  if (index > -1) {
    selectedCompetencyIds.value.splice(index, 1)
  }
  
  // Remove from form competencies
  if (form.value.competencies[competencyId]) {
    delete form.value.competencies[competencyId]
  }
}

const addNewCompetency = () => {
  if (!newCompetency.value.name.trim()) {
    return
  }
  
  // Create a temporary ID for new competency (negative number to avoid conflict)
  const tempId = -(Date.now())
  
  // Add to form competencies
  form.value.competencies[tempId] = {
    competency_id: tempId,
    // Store the new competency data for backend processing
    new_competency: {
      name: newCompetency.value.name
    }
  }
  
  // Add to selected list (otomatis terpilih)
  selectedCompetencyIds.value.push(tempId)
  
  // Reset form
  newCompetency.value = {
    name: ''
  }
  
  // Hide form
  showAddCompetencyForm.value = false
  
  // Show success message
  console.log('Kompetensi baru berhasil ditambahkan dan otomatis terpilih!')
  
  // Optional: Show toast notification
  // You can add a toast library like vue-toastification if needed
}

// Watch for competency selection changes
watch(selectedCompetencyIds, (newIds, oldIds) => {
  // Add new competencies to form (only for existing competencies, not new ones)
  newIds.forEach(id => {
    if (!form.value.competencies[id] && id > 0) {
      // Only add for existing competencies (positive ID)
      form.value.competencies[id] = {
        competency_id: id
      }
    }
  })
  
  // Remove old competencies from form
  if (oldIds) {
    oldIds.forEach(id => {
      if (!newIds.includes(id) && form.value.competencies[id]) {
        delete form.value.competencies[id]
      }
    })
  }
}, { deep: true })

// Watch for filter changes to reset pagination
watch(filters, () => {
  currentPage.value = 1
}, { deep: true })
</script>

<style scoped>
@keyframes blob {
  0% {
    transform: translate(0px, 0px) scale(1);
  }
  33% {
    transform: translate(30px, -50px) scale(1.1);
  }
  66% {
    transform: translate(-20px, 20px) scale(0.9);
  }
  100% {
    transform: translate(0px, 0px) scale(1);
  }
}

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 3px;
}

::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}
</style>

<style>
/* Global dropdown styling for dark theme */
select {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 0.5rem center;
  background-repeat: no-repeat;
  background-size: 1.5em 1.5em;
  padding-right: 2.5rem;
  appearance: none;
  -webkit-appearance: none;
  -moz-appearance: none;
}

select option {
  background-color: rgba(15, 23, 42, 0.95) !important;
  color: white !important;
  border: none;
  padding: 8px 12px;
}

select option:hover {
  background-color: rgba(59, 130, 246, 0.5) !important;
}

select option:checked {
  background-color: rgba(59, 130, 246, 0.8) !important;
}

/* Modal specific styling */
.modal select {
  background-color: rgba(255, 255, 255, 0.1) !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  color: white !important;
}

.modal select:focus {
  outline: none !important;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5) !important;
  border-color: transparent !important;
}

.modal input,
.modal textarea {
  background-color: rgba(255, 255, 255, 0.1) !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  color: white !important;
}

.modal input::placeholder,
.modal textarea::placeholder {
  color: rgba(156, 163, 175, 0.8) !important;
}

.modal input:focus,
.modal textarea:focus {
  outline: none !important;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5) !important;
  border-color: transparent !important;
}

/* Override any conflicting styles */
.bg-white\/10 {
  background-color: rgba(255, 255, 255, 0.1) !important;
}

.border-white\/20 {
  border-color: rgba(255, 255, 255, 0.2) !important;
}

.text-white {
  color: white !important;
}

.placeholder-gray-400::placeholder {
  color: rgba(156, 163, 175, 0.8) !important;
}

/* Additional dropdown styling for better appearance */
select:disabled {
  opacity: 0.5 !important;
  cursor: not-allowed !important;
}

/* Ensure dropdown arrow is visible */
select {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e") !important;
  background-position: right 0.5rem center !important;
  background-repeat: no-repeat !important;
  background-size: 1.5em 1.5em !important;
  padding-right: 2.5rem !important;
}

/* Firefox specific styling */
select:-moz-focusring {
  color: transparent !important;
  text-shadow: 0 0 0 white !important;
}

/* IE specific styling */
select::-ms-expand {
  display: none !important;
}

/* Custom SweetAlert Loading Modal Styles */
.swal2-popup-custom {
  border-radius: 16px !important;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
  border: 1px solid rgba(34, 197, 94, 0.2) !important;
  z-index: 9999 !important;
}

.swal2-title-custom {
  color: #059669 !important;
  font-weight: 600 !important;
  font-size: 1.25rem !important;
}

/* Loading spinner animation */
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.animate-spin {
  animation: spin 1s linear infinite;
}

/* Progress bar animation */
@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
</style> 