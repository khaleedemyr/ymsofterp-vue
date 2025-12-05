<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
          <div class="flex items-center gap-3 mb-2">
            <Link
              :href="route('master-soal.index')"
              class="text-gray-600 hover:text-gray-800"
            >
              <i class="fa-solid fa-arrow-left"></i>
            </Link>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
              <i class="fa-solid fa-edit text-blue-600"></i>
              Edit Soal
            </h1>
          </div>
          <p class="text-gray-600">Edit soal: {{ soal.judul }}</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="submitForm" class="space-y-6">
          <div class="bg-white rounded-lg shadow-sm border p-6">
            <!-- Judul -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Judul Soal *
              </label>
              <input
                v-model="form.judul"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.judul }"
                placeholder="Masukkan judul soal"
                required
              />
              <p v-if="errors.judul" class="mt-1 text-sm text-red-600">{{ errors.judul }}</p>
            </div>

            <!-- Tipe Soal -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Tipe Soal *
                </label>
                <select
                  v-model="form.tipe_soal"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.tipe_soal }"
                  required
                  @change="onTipeSoalChange"
                >
                  <option value="">Pilih Tipe Soal</option>
                  <option
                    v-for="option in tipeSoalOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
                <p v-if="errors.tipe_soal" class="mt-1 text-sm text-red-600">{{ errors.tipe_soal }}</p>
            </div>

            <!-- Pertanyaan -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Pertanyaan *
              </label>
              <textarea
                v-model="form.pertanyaan"
                rows="4"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.pertanyaan }"
                placeholder="Masukkan pertanyaan soal"
                required
              ></textarea>
              <p v-if="errors.pertanyaan" class="mt-1 text-sm text-red-600">{{ errors.pertanyaan }}</p>
            </div>

            <!-- Waktu dan Skor -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Waktu Pengerjaan (detik) *
                </label>
                <input
                  v-model="form.waktu_detik"
                  type="number"
                  min="1"
                  max="3600"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.waktu_detik }"
                  placeholder="60"
                  required
                />
                <p class="mt-1 text-sm text-gray-500">
                  Minimal 1 detik, maksimal 3600 detik (1 jam)
                </p>
                <p v-if="errors.waktu_detik" class="mt-1 text-sm text-red-600">{{ errors.waktu_detik }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Skor *
                </label>
                <input
                  v-model="form.skor"
                  type="number"
                  step="0.01"
                  min="0.01"
                  max="100"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.skor }"
                  placeholder="1.00"
                  required
                />
                <p class="mt-1 text-sm text-gray-500">
                  Minimal 0.01, maksimal 100
                </p>
                <p v-if="errors.skor" class="mt-1 text-sm text-red-600">{{ errors.skor }}</p>
              </div>
            </div>

            <!-- Pilihan Ganda -->
            <div v-if="form.tipe_soal === 'pilihan_ganda'" class="mb-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Pilihan Jawaban</h3>
              <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Pilihan A *
                    </label>
                    <input
                      v-model="form.pilihan_a"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="{ 'border-red-500': errors.pilihan_a }"
                      placeholder="Masukkan pilihan A"
                      required
                    />
                    <p v-if="errors.pilihan_a" class="mt-1 text-sm text-red-600">{{ errors.pilihan_a }}</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Pilihan B *
                    </label>
                    <input
                      v-model="form.pilihan_b"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="{ 'border-red-500': errors.pilihan_b }"
                      placeholder="Masukkan pilihan B"
                      required
                    />
                    <p v-if="errors.pilihan_b" class="mt-1 text-sm text-red-600">{{ errors.pilihan_b }}</p>
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Pilihan C *
                    </label>
                    <input
                      v-model="form.pilihan_c"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="{ 'border-red-500': errors.pilihan_c }"
                      placeholder="Masukkan pilihan C"
                      required
                    />
                    <p v-if="errors.pilihan_c" class="mt-1 text-sm text-red-600">{{ errors.pilihan_c }}</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Pilihan D *
                    </label>
                    <input
                      v-model="form.pilihan_d"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="{ 'border-red-500': errors.pilihan_d }"
                      placeholder="Masukkan pilihan D"
                      required
                    />
                    <p v-if="errors.pilihan_d" class="mt-1 text-sm text-red-600">{{ errors.pilihan_d }}</p>
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Jawaban Benar *
                  </label>
                  <select
                    v-model="form.jawaban_benar"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :class="{ 'border-red-500': errors.jawaban_benar }"
                    required
                  >
                    <option value="">Pilih Jawaban Benar</option>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                  </select>
                  <p v-if="errors.jawaban_benar" class="mt-1 text-sm text-red-600">{{ errors.jawaban_benar }}</p>
                </div>
              </div>
            </div>

            <!-- Yes/No -->
            <div v-if="form.tipe_soal === 'yes_no'" class="mb-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Jawaban Benar</h3>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Jawaban Benar *
                </label>
                <select
                  v-model="form.jawaban_benar"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  :class="{ 'border-red-500': errors.jawaban_benar }"
                  required
                >
                  <option value="">Pilih Jawaban Benar</option>
                  <option value="yes">Ya</option>
                  <option value="no">Tidak</option>
                </select>
                <p v-if="errors.jawaban_benar" class="mt-1 text-sm text-red-600">{{ errors.jawaban_benar }}</p>
              </div>
            </div>

            <!-- Status -->
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Status *
              </label>
              <select
                v-model="form.status"
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                :class="{ 'border-red-500': errors.status }"
                required
              >
                <option value="">Pilih Status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Tidak Aktif</option>
              </select>
              <p v-if="errors.status" class="mt-1 text-sm text-red-600">{{ errors.status }}</p>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-end gap-4">
            <Link
              :href="route('master-soal.index')"
              class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              Batal
            </Link>
            <button
              type="submit"
              :disabled="isSubmitting"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin mr-2"></i>
              {{ isSubmitting ? 'Menyimpan...' : 'Update' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  soal: Object,
  tipeSoalOptions: Array,
  errors: Object
});

const isSubmitting = ref(false);

const form = reactive({
  judul: props.soal.judul,
  tipe_soal: props.soal.tipe_soal,
  pertanyaan: props.soal.pertanyaan,
  waktu_detik: props.soal.waktu_detik,
  jawaban_benar: props.soal.jawaban_benar || '',
  pilihan_a: props.soal.pilihan_a || '',
  pilihan_b: props.soal.pilihan_b || '',
  pilihan_c: props.soal.pilihan_c || '',
  pilihan_d: props.soal.pilihan_d || '',
  skor: props.soal.skor,
  status: props.soal.status
});

const errors = reactive(props.errors || {});

const onTipeSoalChange = () => {
  // Reset pilihan dan jawaban ketika tipe soal berubah
  if (form.tipe_soal !== 'pilihan_ganda') {
    form.pilihan_a = '';
    form.pilihan_b = '';
    form.pilihan_c = '';
    form.pilihan_d = '';
  }
  if (form.tipe_soal !== 'yes_no' && form.tipe_soal !== 'pilihan_ganda') {
    form.jawaban_benar = '';
  }
};

const submitForm = () => {
  isSubmitting.value = true;
  
  router.put(route('master-soal.update', props.soal.id), form, {
    onSuccess: () => {
      isSubmitting.value = false;
    },
    onError: (errors) => {
      isSubmitting.value = false;
      Object.assign(errors, errors);
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
};
</script>
