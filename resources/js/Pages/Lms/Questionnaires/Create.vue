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
                  Buat Kuesioner Baru
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  Buat kuesioner untuk mengumpulkan feedback dari responden
                </p>
              </div>
              <Link
                :href="route('lms.questionnaires.index')"
                class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
              >
                <i class="fas fa-arrow-left mr-2"></i>
                Kembali
              </Link>
            </div>
          </div>
        </div>

        <!-- Form Section -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-8">
          <form @submit.prevent="submitForm" class="space-y-6">
            <!-- Title -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Judul Kuesioner <span class="text-red-400">*</span></label>
              <input
                v-model="form.title"
                type="text"
                required
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Masukkan judul kuesioner..."
              />
            </div>

            <!-- Description -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Deskripsi</label>
              <textarea
                v-model="form.description"
                rows="3"
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Masukkan deskripsi kuesioner (opsional)..."
              ></textarea>
            </div>

            <!-- Instructions -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Instruksi</label>
              <textarea
                v-model="form.instructions"
                rows="3"
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Masukkan instruksi untuk responden (opsional)..."
              ></textarea>
            </div>

            <!-- Settings -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Anonymous -->
              <div>
                <label class="flex items-center space-x-3">
                  <input
                    v-model="form.is_anonymous"
                    type="checkbox"
                    class="w-4 h-4 text-blue-600 bg-white/10 border-white/20 rounded focus:ring-blue-500 focus:ring-2"
                  />
                  <span class="text-sm font-medium text-gray-300">Kuesioner Anonymous</span>
                </label>
                <p class="text-xs text-white/60 mt-1">Responden tidak perlu login untuk mengisi kuesioner</p>
              </div>

              <!-- Multiple Responses -->
              <div>
                <label class="flex items-center space-x-3">
                  <input
                    v-model="form.allow_multiple_responses"
                    type="checkbox"
                    class="w-4 h-4 text-blue-600 bg-white/10 border-white/20 rounded focus:ring-blue-500 focus:ring-2"
                  />
                  <span class="text-sm font-medium text-gray-300">Izinkan Multiple Responses</span>
                </label>
                <p class="text-xs text-white/60 mt-1">Satu user dapat mengisi kuesioner berkali-kali</p>
              </div>
            </div>

            <!-- Date Range -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Start Date -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Tanggal Mulai</label>
                <input
                  v-model="form.start_date"
                  type="date"
                  class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
                <p class="text-xs text-white/60 mt-1">Kosongkan jika kuesioner langsung aktif</p>
              </div>

              <!-- End Date -->
              <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Tanggal Berakhir</label>
                <input
                  v-model="form.end_date"
                  type="date"
                  class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
                <p class="text-xs text-white/60 mt-1">Kosongkan jika kuesioner tidak ada batas waktu</p>
              </div>
            </div>

            <!-- Status -->
            <div>
              <label class="block text-sm font-medium text-gray-300 mb-2">Status <span class="text-red-400">*</span></label>
              <select
                v-model="form.status"
                required
                class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent [&>option]:bg-slate-800 [&>option]:text-white"
              >
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="archived">Archived</option>
              </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex space-x-4 pt-6">
              <Link
                :href="route('lms.questionnaires.index')"
                class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-lg transition-colors duration-200 text-center"
              >
                Batal
              </Link>
              <button
                type="submit"
                :disabled="loading"
                class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed relative overflow-hidden"
              >
                <span v-if="loading" class="flex items-center justify-center">
                  <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                  Menyimpan...
                </span>
                <span v-else class="flex items-center justify-center">
                  <i class="fas fa-save mr-2"></i>
                  Simpan Kuesioner
                </span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'

const loading = ref(false)

const form = ref({
  title: '',
  description: '',
  instructions: '',
  is_anonymous: false,
  allow_multiple_responses: false,
  start_date: '',
  end_date: '',
  status: 'draft'
})

const submitForm = async () => {
  if (!form.value.title.trim()) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Judul kuesioner harus diisi!'
    })
    return
  }

  loading.value = true

  try {
    await router.post(route('lms.questionnaires.store'), form.value, {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'Berhasil!',
          text: 'Kuesioner berhasil dibuat!'
        })
      },
      onError: (errors) => {
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: Object.values(errors)[0] || 'Terjadi kesalahan saat menyimpan kuesioner'
        })
      }
    })
  } catch (error) {
    console.error('Error submitting form:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat menyimpan kuesioner'
    })
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

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
</style>
