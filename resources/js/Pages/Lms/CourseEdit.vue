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
                  Edit Course
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Perbarui informasi program pelatihan
                </p>
              </div>
              <div class="flex items-center space-x-4">
                <Link 
                  :href="route('lms.courses.show', course.id)"
                  class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                >
                  <i class="fas fa-arrow-left mr-2"></i>
                  Kembali ke Course
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Edit Form -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
          <form @submit.prevent="updateCourse" class="space-y-8">
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
                  <label class="block text-sm font-medium text-gray-300 mb-2">Tipe Course <span class="text-red-400">*</span></label>
                  <select
                    v-model="form.course_type"
                    required
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                  >
                    <option value="">Pilih Tipe Course</option>
                    <option value="mandatory">Mandatory (Wajib)</option>
                    <option value="optional">Optional (Opsional)</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Target Divisi <span class="text-gray-400 text-xs">(Opsional)</span></label>
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
                    <option v-for="division in divisions" :key="division.id" :value="division.id">
                      {{ division.nama_divisi }}
                    </option>
                  </select>
                  
                  <!-- Multiple Divisions Selection -->
                  <div v-if="form.target_type === 'multiple'" class="space-y-2">
                    <div class="max-h-32 overflow-y-auto bg-white/5 rounded-lg p-3 border border-white/10">
                                             <div v-for="division in divisions" :key="division.id" class="flex items-center mb-2">
                         <input
                           type="checkbox"
                           :id="'division-' + division.id"
                           :value="parseInt(division.id)"
                           v-model="form.target_divisions"
                           class="w-4 h-4 text-blue-600 bg-white/10 border-white/20 rounded focus:ring-blue-500 focus:ring-2"
                         >
                        <label :for="'division-' + division.id" class="ml-2 text-sm text-white/80 cursor-pointer">
                          {{ division.nama_divisi }}
                        </label>
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
                  <label class="block text-sm font-medium text-gray-300 mb-2">Target Jabatan <span class="text-gray-400 text-xs">(Opsional)</span></label>
                  <div class="space-y-2">
                    <div class="max-h-32 overflow-y-auto bg-white/5 rounded-lg p-3 border border-white/10">
                                             <div v-for="jabatan in jabatans" :key="jabatan.id_jabatan" class="flex items-center mb-2">
                         <input
                           type="checkbox"
                           :id="'jabatan-' + jabatan.id_jabatan"
                           :value="parseInt(jabatan.id_jabatan)"
                           v-model="form.target_jabatan_ids"
                           class="w-4 h-4 text-green-600 bg-white/10 border-white/20 rounded focus:ring-green-500 focus:ring-2"
                         >
                        <label :for="'jabatan-' + jabatan.id_jabatan" class="ml-2 text-sm text-white/80 cursor-pointer">
                          {{ jabatan.nama_jabatan }}
                          <span v-if="jabatan.divisi" class="text-xs text-white/60">({{ jabatan.divisi.nama_divisi }})</span>
                        </label>
                      </div>
                    </div>
                    <p class="text-xs text-white/60">Pilih jabatan yang ditarget (opsional)</p>
                  </div>
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Target Outlet <span class="text-gray-400 text-xs">(Opsional)</span></label>
                  <div class="space-y-2">
                    <div class="max-h-32 overflow-y-auto bg-white/5 rounded-lg p-3 border border-white/10">
                      <div v-for="outlet in outlets" :key="outlet.id_outlet" class="flex items-center mb-2">
                        <input
                          type="checkbox"
                          :id="'outlet-' + outlet.id_outlet"
                          :value="parseInt(outlet.id_outlet)"
                          v-model="form.target_outlet_ids"
                          class="w-4 h-4 text-purple-600 bg-white/10 border-white/20 rounded focus:ring-purple-500 focus:ring-2"
                        >
                        <label :for="'outlet-' + outlet.id_outlet" class="ml-2 text-sm text-white/80 cursor-pointer">
                          {{ outlet.nama_outlet }}
                        </label>
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
                    <option value="generic">Generic</option>
                    <option value="departemental">Departemental</option>
                  </select>
                </div>
              </div>
            </div>



            <!-- Requirements Section -->
            <div class="mb-6">
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
                  class="w-full px-4 py-3 bg-green-500/20 border border-green-500/30 rounded-lg text-green-300 hover:bg-green-500/30 transition-colors"
                  :disabled="loading"
                >
                  <i class="fas fa-plus mr-2"></i>
                  Tambah Persyaratan
                </button>
              </div>
            </div>

            <!-- Curriculum Section -->
            <div class="mb-6">
              <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-list mr-2 text-blue-400"></i>
                Kurikulum Training
              </h4>
              <div class="space-y-4">
                <div v-for="(lesson, index) in form.curriculum" :key="index" class="border border-white/20 rounded-lg p-4">
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-3">
                    <div>
                      <label class="block text-sm font-medium text-gray-300 mb-2">Urutan <span class="text-red-400">*</span></label>
                      <input
                        v-model="lesson.order_number"
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
                        v-model="lesson.title"
                        type="text"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Judul sesi training"
                        :disabled="loading"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-300 mb-2">Durasi (menit) <span class="text-red-400">*</span></label>
                      <input
                        v-model="lesson.duration_minutes"
                        type="number"
                        min="1"
                        class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Durasi dalam menit"
                        :disabled="loading"
                      />
                    </div>
                  </div>
                  <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Deskripsi Sesi</label>
                    <textarea
                      v-model="lesson.description"
                      rows="2"
                      class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Deskripsi detail sesi training"
                      :disabled="loading"
                    ></textarea>
                  </div>
                  <div class="flex justify-end">
                    <button
                      v-if="form.curriculum.length > 1"
                      @click="removeCurriculum(index)"
                      type="button"
                      class="px-3 py-2 bg-red-500/20 border border-red-500/30 text-red-300 hover:bg-red-500/30 rounded-lg transition-colors text-sm"
                      :disabled="loading"
                    >
                      <i class="fas fa-trash mr-1"></i>
                      Hapus Sesi
                    </button>
                  </div>
                </div>
                <button
                  @click="addCurriculum"
                  type="button"
                  class="w-full py-3 border-2 border-dashed border-white/20 rounded-lg text-white hover:border-white/40 transition-colors"
                  :disabled="loading"
                >
                  <i class="fas fa-plus mr-2"></i>
                  Tambah Sesi Training
                </button>
              </div>
            </div>


            <!-- Certificate Template Section -->
            <div class="mb-6">
              <h4 class="text-lg font-semibold text-white mb-4 flex items-center">
                <i class="fas fa-certificate mr-2 text-yellow-400"></i>
                Template Sertifikat
              </h4>
              <div class="grid grid-cols-1 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">
                    Template Sertifikat Default
                    <span class="text-gray-400 text-xs ml-2">(Opsional)</span>
                  </label>
                  <select
                    v-model="form.certificate_template_id"
                    class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    :disabled="loading"
                  >
                    <option value="">Pilih template (akan diminta saat penerbitan sertifikat)</option>
                    <option 
                      v-for="template in certificateTemplates" 
                      :key="template.id" 
                      :value="template.id"
                    >
                      {{ template.name }}
                    </option>
                  </select>
                  <p class="text-xs text-gray-400 mt-1">
                    <i class="fas fa-info-circle mr-1"></i>
                    Jika dipilih, template ini akan otomatis digunakan saat menerbitkan sertifikat. 
                    Jika tidak dipilih, sistem akan meminta pilihan template saat penerbitan.
                  </p>
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
                <!-- Current Thumbnail -->
                <div v-if="course.thumbnail_url" class="flex items-center space-x-4">
                  <img :src="course.thumbnail_url" alt="Current thumbnail" class="w-32 h-24 object-cover rounded-lg border border-white/20" />
                  <div>
                    <p class="text-sm text-white/70">Thumbnail saat ini</p>
                    <p class="text-xs text-white/50">Upload file baru untuk mengganti</p>
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">Upload Thumbnail Baru</label>
                  <div class="border-2 border-dashed border-white/20 rounded-lg p-6 text-center hover:border-white/40 transition-colors">
                                         <input
                       ref="fileInput"
                       type="file"
                       accept="image/*"
                       @change="handleThumbnailChange"
                       class="hidden"
                       :disabled="loading"
                     />
                     <div v-if="!form.thumbnail" @click="() => { if (fileInput) fileInput.click() }" class="cursor-pointer">
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
                         @click="() => { if (fileInput) fileInput.click() }"
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
              <Link
                :href="route('lms.courses.show', course.id)"
                class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-lg transition-colors duration-200 text-center"
                :disabled="loading"
              >
                Batal
              </Link>
              <button
                type="submit"
                :disabled="loading || isSubmitting"
                class="flex-1 bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-4 py-3 rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed relative overflow-hidden"
              >
                <span v-if="loading" class="flex items-center justify-center">
                  <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                  Menyimpan...
                </span>
                <span v-else class="flex items-center justify-center">
                  <i class="fas fa-save mr-2"></i>
                  Update Course
                </span>
                <!-- Loading overlay -->
                <div v-if="loading" class="absolute inset-0 bg-blue-600/20 backdrop-blur-sm"></div>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
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
  course: Object,
  categories: Array,
  divisions: Array,
  jabatans: Array,
  outlets: Array,
  certificateTemplates: Array,
})

const loading = ref(false)
const fileInput = ref(null)
const isSubmitting = ref(false)

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
  status: 'draft',

  requirements: ['', ''], // Default with 2 empty fields
  curriculum: [
    {
      order_number: 1,
      title: '',
      description: '',
      duration_minutes: ''
    }
  ], // Default with 1 empty lesson
  certificate_template_id: '', // Default certificate template
  thumbnail: null // For thumbnail upload
})

// Initialize form with course data
onMounted(() => {
  // Convert target_divisions to array of numbers if it's a string or object
  let targetDivisions = []
  if (props.course.target_divisions) {
    console.log('Processing target_divisions:', props.course.target_divisions, typeof props.course.target_divisions)
    
    if (Array.isArray(props.course.target_divisions)) {
      targetDivisions = props.course.target_divisions.map(id => parseInt(id))
      console.log('Array processing result:', targetDivisions)
    } else if (typeof props.course.target_divisions === 'string') {
      // If it's a JSON string, parse it
      try {
        const parsed = JSON.parse(props.course.target_divisions)
        console.log('JSON parsed result:', parsed)
        targetDivisions = Array.isArray(parsed) ? parsed.map(id => parseInt(id)) : []
      } catch (e) {
        console.log('JSON parsing failed, treating as single value')
        // If parsing fails, treat as single value
        targetDivisions = [parseInt(props.course.target_divisions)]
      }
    } else if (typeof props.course.target_divisions === 'object') {
      // If it's an object (like from relationship), extract IDs
      console.log('Object processing:', props.course.target_divisions)
      if (Array.isArray(props.course.target_divisions)) {
        targetDivisions = props.course.target_divisions.map(item => 
          typeof item === 'object' ? parseInt(item.id) : parseInt(item)
        )
      }
    }
  }

  // Convert target_jabatan_ids to array of numbers if it's a string or object
  let targetJabatanIds = []
  if (props.course.target_jabatan_ids) {
    console.log('Processing target_jabatan_ids:', props.course.target_jabatan_ids, typeof props.course.target_jabatan_ids)
    
    if (Array.isArray(props.course.target_jabatan_ids)) {
      targetJabatanIds = props.course.target_jabatan_ids.map(id => parseInt(id))
    } else if (typeof props.course.target_jabatan_ids === 'string') {
      try {
        const parsed = JSON.parse(props.course.target_jabatan_ids)
        targetJabatanIds = Array.isArray(parsed) ? parsed.map(id => parseInt(id)) : []
      } catch (e) {
        targetJabatanIds = [parseInt(props.course.target_jabatan_ids)]
      }
    } else if (typeof props.course.target_jabatan_ids === 'object') {
      if (Array.isArray(props.course.target_jabatan_ids)) {
        targetJabatanIds = props.course.target_jabatan_ids.map(item => 
          typeof item === 'object' ? parseInt(item.id_jabatan) : parseInt(item)
        )
      }
    }
  }

  // Convert target_outlet_ids to array of numbers if it's a string or object
  let targetOutletIds = []
  if (props.course.target_outlet_ids) {
    console.log('Processing target_outlet_ids:', props.course.target_outlet_ids, typeof props.course.target_outlet_ids)
    
    if (Array.isArray(props.course.target_outlet_ids)) {
      targetOutletIds = props.course.target_outlet_ids.map(id => parseInt(id))
    } else if (typeof props.course.target_outlet_ids === 'string') {
      try {
        const parsed = JSON.parse(props.course.target_outlet_ids)
        targetOutletIds = Array.isArray(parsed) ? parsed.map(id => parseInt(id)) : []
      } catch (e) {
        targetOutletIds = [parseInt(props.course.target_outlet_ids)]
      }
    } else if (typeof props.course.target_outlet_ids === 'object') {
      if (Array.isArray(props.course.target_outlet_ids)) {
        targetOutletIds = props.course.target_outlet_ids.map(item => 
          typeof item === 'object' ? parseInt(item.id_outlet) : parseInt(item)
        )
      }
    }
  }

  form.value = {
    title: props.course.title || '',
    short_description: props.course.short_description || '',
    description: props.course.description || '',
    category_id: props.course.category_id || '',
    target_type: props.course.target_type || '',
    target_division_id: props.course.target_division_id ? parseInt(props.course.target_division_id) : '',
    target_divisions: targetDivisions,
    target_jabatan_ids: targetJabatanIds,
    target_outlet_ids: targetOutletIds,
    difficulty_level: props.course.difficulty_level || '',
    duration_minutes: props.course.duration_minutes || '',
    type: props.course.type || 'offline',
    status: props.course.status || 'draft',

    requirements: props.course.requirements && props.course.requirements.length > 0 
      ? [...props.course.requirements] 
      : ['', ''], // Default with 2 empty fields
    curriculum: props.course.lessons && props.course.lessons.length > 0 
      ? props.course.lessons.map(lesson => ({
          order_number: lesson.order_number,
          title: lesson.title,
          description: lesson.description || '',
          duration_minutes: lesson.duration_minutes
        }))
      : [{
          order_number: 1,
          title: '',
          description: '',
          duration_minutes: ''
        }], // Default with 1 empty lesson
    certificate_template_id: props.course.certificate_template_id ? parseInt(props.course.certificate_template_id) : '',
    course_type: props.course.course_type || '',
    thumbnail: null
  }

  // Debug log to check the data
  console.log('Course data:', props.course)
  console.log('Original target_divisions:', props.course.target_divisions)
  console.log('Processed target_divisions:', targetDivisions)
  console.log('Form target_divisions:', form.value.target_divisions)
  console.log('Form target_jabatan_ids:', form.value.target_jabatan_ids)
  console.log('Form target_outlet_ids:', form.value.target_outlet_ids)
  console.log('Divisions data:', props.divisions)
  console.log('Jabatans data:', props.jabatans)
  console.log('Outlets data:', props.outlets)
})


// Methods for dynamic fields

const addRequirement = () => {
  form.value.requirements.push('')
}

const removeRequirement = (index) => {
  form.value.requirements.splice(index, 1)
}

const addCurriculum = () => {
  form.value.curriculum.push({
    order_number: form.value.curriculum.length + 1,
    title: '',
    description: '',
    duration_minutes: ''
  })
}

const removeCurriculum = (index) => {
  form.value.curriculum.splice(index, 1)
  // Reorder remaining items
  form.value.curriculum.forEach((item, idx) => {
    item.order_number = idx + 1
  })
}

const handleThumbnailChange = (event) => {
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
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const updateCourse = async () => {
  // Prevent double click
  if (isSubmitting.value || loading.value) {
    return
  }

  // Validation
  if (!form.value.title.trim() || !form.value.category_id || !form.value.difficulty_level || !form.value.duration_minutes) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Mohon lengkapi semua field yang wajib diisi!',
      confirmButtonColor: '#EF4444'
    })
    return
  }

  // Custom validation: At least one target must be selected
  const hasDivision = form.value.target_division_id || 
                     (form.value.target_divisions && form.value.target_divisions.length > 0) || 
                     form.value.target_type === 'all';
  const hasJabatan = form.value.target_jabatan_ids && form.value.target_jabatan_ids.length > 0;
  const hasOutlet = form.value.target_outlet_ids && form.value.target_outlet_ids.length > 0;
  
  if (!hasDivision && !hasJabatan && !hasOutlet) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Minimal harus memilih satu target: divisi, jabatan, atau outlet.',
      confirmButtonColor: '#EF4444'
    })
    return
  }

  // Additional validation for target divisions (if target_type is selected)
  if (form.value.target_type === 'single' && !form.value.target_division_id) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Mohon pilih divisi target!',
      confirmButtonColor: '#EF4444'
    })
    return
  }

  if (form.value.target_type === 'multiple' && (!form.value.target_divisions || form.value.target_divisions.length === 0)) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Mohon pilih minimal satu divisi target!',
      confirmButtonColor: '#EF4444'
    })
    return
  }



  // Requirements validation - at least one non-empty requirement
  if (form.value.requirements.length === 0 || form.value.requirements.every(req => !req.trim())) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Mohon tambahkan minimal satu persyaratan peserta!',
      confirmButtonColor: '#EF4444'
    })
    return
  }


  // Curriculum validation - at least one lesson with required fields
  if (form.value.curriculum.length === 0 || form.value.curriculum.every(lesson => !lesson.title.trim() || !lesson.order_number || !lesson.duration_minutes)) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Mohon tambahkan minimal satu sesi training dengan judul, urutan, dan durasi!',
      confirmButtonColor: '#EF4444'
    })
    return
  }

  // Course type validation
  if (!form.value.course_type) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Mohon pilih tipe course (mandatory/optional)!',
      confirmButtonColor: '#EF4444'
    })
    return
  }

  // Confirmation dialog
  const result = await Swal.fire({
    title: 'Konfirmasi',
    text: 'Apakah Anda yakin ingin memperbarui course ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3B82F6',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Ya, Update!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  })

  if (!result.isConfirmed) {
    return
  }

  // Show loading state
  console.log('Setting loading state to true')
  loading.value = true
  isSubmitting.value = true
  console.log('Loading state:', loading.value, 'isSubmitting:', isSubmitting.value)
  
  // Show simple loading notification
  Swal.fire({
    title: 'Memperbarui Course...',
    text: 'Mohon tunggu sebentar...',
    icon: 'info',
    showConfirmButton: false,
    allowOutsideClick: false,
    allowEscapeKey: false,
    didOpen: () => {
      Swal.showLoading()
    }
  })
  
  try {

    // Prepare form data
    const formData = new FormData()
    
    // Basic fields
    formData.append('title', form.value.title)
    formData.append('short_description', form.value.short_description || '')
    formData.append('description', form.value.description)
    formData.append('category_id', form.value.category_id)
    formData.append('difficulty_level', form.value.difficulty_level)
    formData.append('duration_minutes', form.value.duration_minutes)
    formData.append('status', form.value.status)
    formData.append('course_type', form.value.course_type)

    // Target fields
    formData.append('target_type', form.value.target_type)
    if (form.value.target_type === 'single') {
      formData.append('target_division_id', form.value.target_division_id.toString())
    } else if (form.value.target_type === 'multiple') {
      form.value.target_divisions.forEach(divisionId => {
        formData.append('target_divisions[]', divisionId.toString())
      })
    }

    // Target jabatans and outlets
    form.value.target_jabatan_ids.forEach(jabatanId => {
      formData.append('target_jabatan_ids[]', jabatanId.toString())
    })
    form.value.target_outlet_ids.forEach(outletId => {
      formData.append('target_outlet_ids[]', outletId.toString())
    })

    // Requirements
    form.value.requirements.forEach(requirement => {
      formData.append('requirements[]', requirement)
    })

    // Curriculum
    form.value.curriculum.forEach((lesson, index) => {
      formData.append(`curriculum[${index}][order_number]`, lesson.order_number)
      formData.append(`curriculum[${index}][title]`, lesson.title)
      formData.append(`curriculum[${index}][description]`, lesson.description)
      formData.append(`curriculum[${index}][duration_minutes]`, lesson.duration_minutes)
    })


    // Thumbnail
    if (form.value.thumbnail && form.value.thumbnail.file) {
      formData.append('thumbnail', form.value.thumbnail.file)
    }

    // Submit form
    router.post(route('lms.courses.update', props.course.id), formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      },
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Berhasil!',
          text: 'Course berhasil diperbarui!',
          timer: 3000,
          timerProgressBar: true,
          showConfirmButton: false,
          toast: true,
          position: 'top-end',
          background: '#10B981',
          color: '#ffffff'
        })
        // Reload the page to show updated course
        router.reload()
      },
      onError: (errors) => {
        const errorMessage = Object.values(errors).flat().join(', ')
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: errorMessage || 'Gagal memperbarui course. Silakan periksa kembali data yang diinput.',
          confirmButtonColor: '#EF4444',
          background: '#FEF2F2',
          color: '#DC2626'
        })
      }
    })

  } catch (error) {
    console.error('Error updating course:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat memperbarui course',
      confirmButtonColor: '#EF4444',
      background: '#FEF2F2',
      color: '#DC2626'
    })
  } finally {
    console.log('Resetting loading state to false')
    loading.value = false
    isSubmitting.value = false
    console.log('Loading state:', loading.value, 'isSubmitting:', isSubmitting.value)
  }
}
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

/* Form specific styling */
select {
  background-color: rgba(255, 255, 255, 0.1) !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  color: white !important;
}

select:focus {
  outline: none !important;
  box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5) !important;
  border-color: transparent !important;
}

input,
textarea {
  background-color: rgba(255, 255, 255, 0.1) !important;
  border: 1px solid rgba(255, 255, 255, 0.2) !important;
  color: white !important;
}

input::placeholder,
textarea::placeholder {
  color: rgba(156, 163, 175, 0.8) !important;
}

input:focus,
textarea:focus {
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
</style>
