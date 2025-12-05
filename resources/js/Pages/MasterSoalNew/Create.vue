<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
          <div class="flex items-center gap-3 mb-2">
            <Link
              href="/master-soal-new"
              class="text-gray-600 hover:text-gray-800"
            >
              <i class="fa-solid fa-arrow-left"></i>
            </Link>
            <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
              <i class="fa-solid fa-plus-circle text-blue-600"></i>
              Tambah Soal Baru
            </h1>
          </div>
          <p class="text-gray-600">Buat judul soal dan tambahkan pertanyaan</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="submitForm" class="space-y-6">
          <!-- Master Soal Info -->
          <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Informasi Soal</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
              <!-- Judul -->
              <div class="md:col-span-2">
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

              <!-- Deskripsi -->
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Deskripsi
                </label>
                <textarea
                  v-model="form.deskripsi"
                  rows="3"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Deskripsi soal (opsional)"
                ></textarea>
              </div>



              <!-- Status -->
              <div>
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
          </div>

          <!-- Pertanyaan Section -->
          <div class="bg-white rounded-lg shadow-sm border p-6">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-xl font-semibold text-gray-900">Pertanyaan</h2>
            </div>

            <!-- Pertanyaan Cards -->
            <div v-if="form.pertanyaans.length === 0" class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-question-circle text-4xl mb-4 block"></i>
              <p>Belum ada pertanyaan. Klik tombol "Tambah Pertanyaan" di bawah untuk menambah.</p>
            </div>

            <div v-else class="space-y-4">
              <div
                v-for="(pertanyaan, index) in form.pertanyaans"
                :key="index"
                class="border border-gray-200 rounded-lg p-4 bg-gray-50"
              >
                <!-- Pertanyaan Header -->
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-lg font-medium text-gray-900">
                    Pertanyaan {{ index + 1 }}
                  </h3>
                  <button
                    type="button"
                    @click="removePertanyaan(index)"
                    class="text-red-600 hover:text-red-800"
                    title="Hapus Pertanyaan"
                  >
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>

                <!-- Form Pertanyaan -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <!-- Tipe Soal -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Tipe Soal *
                    </label>
                    <select
                      v-model="pertanyaan.tipe_soal"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="{ 'border-red-500': errors[`pertanyaans.${index}.tipe_soal`] }"
                      required
                      @change="onTipeSoalChange(index)"
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
                    <p v-if="errors[`pertanyaans.${index}.tipe_soal`]" class="mt-1 text-sm text-red-600">
                      {{ errors[`pertanyaans.${index}.tipe_soal`] }}
                    </p>
                  </div>

                  <!-- Waktu -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Waktu (detik) *
                    </label>
                    <input
                      v-model="pertanyaan.waktu_detik"
                      type="number"
                      min="1"
                      max="1800"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="{ 'border-red-500': errors[`pertanyaans.${index}.waktu_detik`] }"
                      required
                    />
                    <p v-if="errors[`pertanyaans.${index}.waktu_detik`]" class="mt-1 text-sm text-red-600">
                      {{ errors[`pertanyaans.${index}.waktu_detik`] }}
                    </p>
                  </div>

                  <!-- Skor (hanya untuk pilihan ganda dan yes/no) -->
                  <div v-if="pertanyaan.tipe_soal !== 'essay'">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Skor *
                    </label>
                    <input
                      v-model="pertanyaan.skor"
                      type="number"
                      step="0.01"
                      min="0.01"
                      max="100"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="{ 'border-red-500': errors[`pertanyaans.${index}.skor`] }"
                      required
                    />
                    <p v-if="errors[`pertanyaans.${index}.skor`]" class="mt-1 text-sm text-red-600">
                      {{ errors[`pertanyaans.${index}.skor`] }}
                    </p>
                  </div>
                  
                  <!-- Info untuk Essay -->
                  <div v-if="pertanyaan.tipe_soal === 'essay'" class="bg-blue-50 border border-blue-200 rounded-md p-3">
                    <p class="text-sm text-blue-800">
                      <i class="fa-solid fa-info-circle mr-2"></i>
                      Untuk soal essay, skor akan diinput manual oleh penilai.
                    </p>
                  </div>
                </div>

                <!-- Pertanyaan Text -->
                <div class="mt-4">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Pertanyaan *
                  </label>
                  <textarea
                    v-model="pertanyaan.pertanyaan"
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :class="{ 'border-red-500': errors[`pertanyaans.${index}.pertanyaan`] }"
                    placeholder="Masukkan pertanyaan"
                    required
                  ></textarea>
                  <p v-if="errors[`pertanyaans.${index}.pertanyaan`]" class="mt-1 text-sm text-red-600">
                    {{ errors[`pertanyaans.${index}.pertanyaan`] }}
                  </p>
                </div>

                <!-- Upload Gambar Pertanyaan (Optional) -->
                <div class="mt-4">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fa-solid fa-image mr-2"></i>
                    Gambar Pertanyaan (Optional - Multiple Images)
                  </label>
                  <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 bg-gray-50">
                    <input
                      type="file"
                      :ref="`pertanyaan_images_${index}`"
                      @change="handlePertanyaanImages($event, index)"
                      multiple
                      accept="image/*"
                      class="hidden"
                    />
                    <div class="text-center">
                      <button
                        type="button"
                        @click="() => $refs[`pertanyaan_images_${index}`][0].click()"
                        class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600 flex items-center gap-2 mx-auto"
                      >
                        <i class="fa-solid fa-upload"></i>
                        Upload Gambar Pertanyaan
                      </button>
                      <p class="text-sm text-gray-500 mt-2">
                        <i class="fa-solid fa-info-circle mr-1"></i>
                        Optional - Bisa upload multiple images untuk pertanyaan
                      </p>
                    </div>
                    
                    <!-- Preview Images -->
                    <div v-if="pertanyaan.pertanyaan_gambar && pertanyaan.pertanyaan_gambar.length > 0" class="mt-4">
                      <h5 class="text-sm font-medium text-gray-700 mb-2">Gambar yang diupload:</h5>
                      <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <div
                          v-for="(image, imgIndex) in pertanyaan.pertanyaan_gambar"
                          :key="imgIndex"
                          class="relative bg-white rounded border p-2"
                        >
                          <img
                            :src="image.preview"
                            :alt="`Pertanyaan ${index + 1} - Image ${imgIndex + 1}`"
                            class="w-full h-20 object-cover rounded"
                          />
                          <button
                            type="button"
                            @click="removePertanyaanImage(index, imgIndex)"
                            class="absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-red-600"
                            title="Hapus gambar"
                          >
                            ×
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Pilihan Ganda -->
                <div v-if="pertanyaan.tipe_soal === 'pilihan_ganda'" class="mt-4">
                  <h4 class="text-sm font-medium text-gray-700 mb-3">Pilihan Jawaban</h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Pilihan A *</label>
                      <input
                        v-model="pertanyaan.pilihan_a"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :class="{ 'border-red-500': errors[`pertanyaans.${index}.pilihan_a`] }"
                        required
                      />
                      <p v-if="errors[`pertanyaans.${index}.pilihan_a`]" class="mt-1 text-sm text-red-600">
                        {{ errors[`pertanyaans.${index}.pilihan_a`] }}
                      </p>
                      
                      <!-- Upload Gambar Pilihan A (Optional) -->
                      <div class="mt-2">
                        <input
                          type="file"
                          :ref="`pilihan_a_image_${index}`"
                          @change="handlePilihanImage($event, index, 'a')"
                          accept="image/*"
                          class="hidden"
                        />
                        <button
                          type="button"
                          @click="() => $refs[`pilihan_a_image_${index}`][0].click()"
                          class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200 flex items-center gap-1"
                        >
                          <i class="fa-solid fa-image"></i>
                          Upload Gambar (Optional)
                        </button>
                        <div v-if="pertanyaan.pilihan_a_gambar" class="mt-2 flex items-center gap-2">
                          <img
                            :src="pertanyaan.pilihan_a_gambar.preview"
                            alt="Pilihan A"
                            class="w-16 h-16 object-cover rounded border"
                          />
                          <button
                            type="button"
                            @click="removePilihanImage(index, 'a')"
                            class="text-red-500 hover:text-red-700 text-sm"
                            title="Hapus gambar"
                          >
                            <i class="fa-solid fa-trash"></i> Hapus
                          </button>
                        </div>
                      </div>
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Pilihan B *</label>
                      <input
                        v-model="pertanyaan.pilihan_b"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :class="{ 'border-red-500': errors[`pertanyaans.${index}.pilihan_b`] }"
                        required
                      />
                      <p v-if="errors[`pertanyaans.${index}.pilihan_b`]" class="mt-1 text-sm text-red-600">
                        {{ errors[`pertanyaans.${index}.pilihan_b`] }}
                      </p>
                      
                      <!-- Upload Gambar Pilihan B (Optional) -->
                      <div class="mt-2">
                        <input
                          type="file"
                          :ref="`pilihan_b_image_${index}`"
                          @change="handlePilihanImage($event, index, 'b')"
                          accept="image/*"
                          class="hidden"
                        />
                        <button
                          type="button"
                          @click="() => $refs[`pilihan_b_image_${index}`][0].click()"
                          class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200 flex items-center gap-1"
                        >
                          <i class="fa-solid fa-image"></i>
                          Upload Gambar (Optional)
                        </button>
                        <div v-if="pertanyaan.pilihan_b_gambar" class="mt-2 flex items-center gap-2">
                          <img
                            :src="pertanyaan.pilihan_b_gambar.preview"
                            alt="Pilihan B"
                            class="w-16 h-16 object-cover rounded border"
                          />
                          <button
                            type="button"
                            @click="removePilihanImage(index, 'b')"
                            class="text-red-500 hover:text-red-700 text-sm"
                            title="Hapus gambar"
                          >
                            <i class="fa-solid fa-trash"></i> Hapus
                          </button>
                        </div>
                      </div>
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Pilihan C *</label>
                      <input
                        v-model="pertanyaan.pilihan_c"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :class="{ 'border-red-500': errors[`pertanyaans.${index}.pilihan_c`] }"
                        required
                      />
                      <p v-if="errors[`pertanyaans.${index}.pilihan_c`]" class="mt-1 text-sm text-red-600">
                        {{ errors[`pertanyaans.${index}.pilihan_c`] }}
                      </p>
                      
                      <!-- Upload Gambar Pilihan C (Optional) -->
                      <div class="mt-2">
                        <input
                          type="file"
                          :ref="`pilihan_c_image_${index}`"
                          @change="handlePilihanImage($event, index, 'c')"
                          accept="image/*"
                          class="hidden"
                        />
                        <button
                          type="button"
                          @click="() => $refs[`pilihan_c_image_${index}`][0].click()"
                          class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200 flex items-center gap-1"
                        >
                          <i class="fa-solid fa-image"></i>
                          Upload Gambar (Optional)
                        </button>
                        <div v-if="pertanyaan.pilihan_c_gambar" class="mt-2 flex items-center gap-2">
                          <img
                            :src="pertanyaan.pilihan_c_gambar.preview"
                            alt="Pilihan C"
                            class="w-16 h-16 object-cover rounded border"
                          />
                          <button
                            type="button"
                            @click="removePilihanImage(index, 'c')"
                            class="text-red-500 hover:text-red-700 text-sm"
                            title="Hapus gambar"
                          >
                            <i class="fa-solid fa-trash"></i> Hapus
                          </button>
                        </div>
                      </div>
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Pilihan D *</label>
                      <input
                        v-model="pertanyaan.pilihan_d"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :class="{ 'border-red-500': errors[`pertanyaans.${index}.pilihan_d`] }"
                        required
                      />
                      <p v-if="errors[`pertanyaans.${index}.pilihan_d`]" class="mt-1 text-sm text-red-600">
                        {{ errors[`pertanyaans.${index}.pilihan_d`] }}
                      </p>
                      
                      <!-- Upload Gambar Pilihan D (Optional) -->
                      <div class="mt-2">
                        <input
                          type="file"
                          :ref="`pilihan_d_image_${index}`"
                          @change="handlePilihanImage($event, index, 'd')"
                          accept="image/*"
                          class="hidden"
                        />
                        <button
                          type="button"
                          @click="() => $refs[`pilihan_d_image_${index}`][0].click()"
                          class="text-sm bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200 flex items-center gap-1"
                        >
                          <i class="fa-solid fa-image"></i>
                          Upload Gambar (Optional)
                        </button>
                        <div v-if="pertanyaan.pilihan_d_gambar" class="mt-2 flex items-center gap-2">
                          <img
                            :src="pertanyaan.pilihan_d_gambar.preview"
                            alt="Pilihan D"
                            class="w-16 h-16 object-cover rounded border"
                          />
                          <button
                            type="button"
                            @click="removePilihanImage(index, 'd')"
                            class="text-red-500 hover:text-red-700 text-sm"
                            title="Hapus gambar"
                          >
                            <i class="fa-solid fa-trash"></i> Hapus
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Jawaban Benar -->
                  <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Jawaban Benar *
                    </label>
                    <select
                      v-model="pertanyaan.jawaban_benar"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="{ 'border-red-500': errors[`pertanyaans.${index}.jawaban_benar`] }"
                      required
                    >
                      <option value="">Pilih Jawaban Benar</option>
                      <option value="A">A</option>
                      <option value="B">B</option>
                      <option value="C">C</option>
                      <option value="D">D</option>
                    </select>
                    <p v-if="errors[`pertanyaans.${index}.jawaban_benar`]" class="mt-1 text-sm text-red-600">
                      {{ errors[`pertanyaans.${index}.jawaban_benar`] }}
                    </p>
                  </div>
                </div>

                <!-- Yes/No -->
                <div v-if="pertanyaan.tipe_soal === 'yes_no'" class="mt-4">
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Jawaban Benar *
                  </label>
                  <select
                    v-model="pertanyaan.jawaban_benar"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    :class="{ 'border-red-500': errors[`pertanyaans.${index}.jawaban_benar`] }"
                    required
                  >
                    <option value="">Pilih Jawaban Benar</option>
                    <option value="yes">Ya</option>
                    <option value="no">Tidak</option>
                  </select>
                  <p v-if="errors[`pertanyaans.${index}.jawaban_benar`]" class="mt-1 text-sm text-red-600">
                    {{ errors[`pertanyaans.${index}.jawaban_benar`] }}
                  </p>
                </div>
              </div>
            </div>
            
            <!-- Tambah Pertanyaan Button (di bawah card) -->
            <div class="mt-6 pt-4 border-t border-gray-200">
              <button
                type="button"
                @click="addPertanyaan"
                class="w-full bg-green-600 hover:bg-green-700 text-white px-4 py-3 rounded-lg flex items-center justify-center gap-2 font-medium"
              >
                <i class="fa-solid fa-plus"></i>
                Tambah Pertanyaan
              </button>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex justify-end gap-4">
            <Link
              href="/master-soal-new"
              class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              Batal
            </Link>
            <button
              type="submit"
              :disabled="isSubmitting || form.pertanyaans.length === 0"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin mr-2"></i>
              {{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  tipeSoalOptions: Array,
  errors: Object
});

const isSubmitting = ref(false);

const form = reactive({
  judul: '',
  deskripsi: '',
  status: 'active',
  pertanyaans: []
});

const errors = reactive(props.errors || {});

const addPertanyaan = () => {
  form.pertanyaans.push({
    tipe_soal: '',
    pertanyaan: '',
    pertanyaan_gambar: [],
    waktu_detik: 60,
    jawaban_benar: '',
    pilihan_a: '',
    pilihan_a_gambar: '',
    pilihan_b: '',
    pilihan_b_gambar: '',
    pilihan_c: '',
    pilihan_c_gambar: '',
    pilihan_d: '',
    pilihan_d_gambar: '',
    skor: 1.00
  });
};

const removePertanyaan = (index) => {
  form.pertanyaans.splice(index, 1);
};

const onTipeSoalChange = (index) => {
  // Reset pilihan dan jawaban ketika tipe soal berubah
  const pertanyaan = form.pertanyaans[index];
  pertanyaan.jawaban_benar = '';
  pertanyaan.pilihan_a = '';
  pertanyaan.pilihan_a_gambar = '';
  pertanyaan.pilihan_b = '';
  pertanyaan.pilihan_b_gambar = '';
  pertanyaan.pilihan_c = '';
  pertanyaan.pilihan_c_gambar = '';
  pertanyaan.pilihan_d = '';
  pertanyaan.pilihan_d_gambar = '';
};

// Handle upload gambar pertanyaan (multiple images)
const handlePertanyaanImages = (event, index) => {
  const files = Array.from(event.target.files);
  const pertanyaan = form.pertanyaans[index];
  
  if (!pertanyaan.pertanyaan_gambar) {
    pertanyaan.pertanyaan_gambar = [];
  }
  
  files.forEach(file => {
    if (file.type.startsWith('image/')) {
      // Store both File object and preview URL
      const reader = new FileReader();
      reader.onload = (e) => {
        pertanyaan.pertanyaan_gambar.push({
          file: file,
          preview: e.target.result
        });
      };
      reader.readAsDataURL(file);
    }
  });
  
  // Reset input
  event.target.value = '';
};

// Handle upload gambar pilihan
const handlePilihanImage = (event, index, pilihan) => {
  const file = event.target.files[0];
  const pertanyaan = form.pertanyaans[index];
  
  if (file && file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = (e) => {
      pertanyaan[`pilihan_${pilihan}_gambar`] = {
        file: file,
        preview: e.target.result
      };
    };
    reader.readAsDataURL(file);
  }
  
  // Reset input
  event.target.value = '';
};

// Remove gambar pertanyaan
const removePertanyaanImage = (index, imgIndex) => {
  form.pertanyaans[index].pertanyaan_gambar.splice(imgIndex, 1);
};

// Remove gambar pilihan
const removePilihanImage = (index, pilihan) => {
  form.pertanyaans[index][`pilihan_${pilihan}_gambar`] = '';
};

const submitForm = () => {
  if (form.pertanyaans.length === 0) {
    alert('Minimal harus ada 1 pertanyaan');
    return;
  }

  isSubmitting.value = true;
  
  // Create FormData for file uploads
  const formData = new FormData();
  
  // Add basic form data
  formData.append('judul', form.judul);
  formData.append('deskripsi', form.deskripsi || '');
  formData.append('status', form.status);
  
  // Add pertanyaans data
  form.pertanyaans.forEach((pertanyaan, index) => {
    formData.append(`pertanyaans[${index}][tipe_soal]`, pertanyaan.tipe_soal);
    formData.append(`pertanyaans[${index}][pertanyaan]`, pertanyaan.pertanyaan);
    formData.append(`pertanyaans[${index}][waktu_detik]`, pertanyaan.waktu_detik);
    formData.append(`pertanyaans[${index}][skor]`, pertanyaan.skor || '');
    formData.append(`pertanyaans[${index}][jawaban_benar]`, pertanyaan.jawaban_benar || '');
    formData.append(`pertanyaans[${index}][pilihan_a]`, pertanyaan.pilihan_a || '');
    formData.append(`pertanyaans[${index}][pilihan_b]`, pertanyaan.pilihan_b || '');
    formData.append(`pertanyaans[${index}][pilihan_c]`, pertanyaan.pilihan_c || '');
    formData.append(`pertanyaans[${index}][pilihan_d]`, pertanyaan.pilihan_d || '');
    
    // Add pertanyaan images
    if (pertanyaan.pertanyaan_gambar && pertanyaan.pertanyaan_gambar.length > 0) {
      pertanyaan.pertanyaan_gambar.forEach((image, imgIndex) => {
        if (image.file instanceof File) {
          formData.append(`pertanyaans[${index}][pertanyaan_images][]`, image.file);
        }
      });
    }
    
    // Add pilihan images
    if (pertanyaan.pilihan_a_gambar && pertanyaan.pilihan_a_gambar.file instanceof File) {
      formData.append(`pertanyaans[${index}][pilihan_a_image]`, pertanyaan.pilihan_a_gambar.file);
    }
    if (pertanyaan.pilihan_b_gambar && pertanyaan.pilihan_b_gambar.file instanceof File) {
      formData.append(`pertanyaans[${index}][pilihan_b_image]`, pertanyaan.pilihan_b_gambar.file);
    }
    if (pertanyaan.pilihan_c_gambar && pertanyaan.pilihan_c_gambar.file instanceof File) {
      formData.append(`pertanyaans[${index}][pilihan_c_image]`, pertanyaan.pilihan_c_gambar.file);
    }
    if (pertanyaan.pilihan_d_gambar && pertanyaan.pilihan_d_gambar.file instanceof File) {
      formData.append(`pertanyaans[${index}][pilihan_d_image]`, pertanyaan.pilihan_d_gambar.file);
    }
  });
  
  router.post('/master-soal-new', formData, {
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
