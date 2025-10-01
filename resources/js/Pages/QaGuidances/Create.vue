<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  guidance: Object,
  categories: Array,
  parameters: Array,
  departemenOptions: Array,
});

const form = ref({
  title: '',
  departemen: '',
  status: 'A',
  categories: [
    {
      category_id: '',
      parameters: [
        {
          parameter_pemeriksaan: '',
          details: [
            {
              parameter_id: '',
              point: 0
            }
          ]
        }
      ]
    }
  ]
});

const errors = ref({});
const isSubmitting = ref(false);

function addCategory() {
  form.value.categories.push({
    category_id: '',
    parameters: [
      {
        parameter_pemeriksaan: '',
        details: [
          {
            parameter_id: '',
            point: 0
          }
        ]
      }
    ]
  });
}

function removeCategory(index) {
  if (form.value.categories.length > 1) {
    form.value.categories.splice(index, 1);
  }
}

function addParameterPemeriksaan(categoryIndex) {
  form.value.categories[categoryIndex].parameters.push({
    parameter_pemeriksaan: '',
    details: [
      {
        parameter_id: '',
        point: 0
      }
    ]
  });
}

function removeParameterPemeriksaan(categoryIndex, parameterIndex) {
  if (form.value.categories[categoryIndex].parameters.length > 1) {
    form.value.categories[categoryIndex].parameters.splice(parameterIndex, 1);
  }
}

function addParameterDetail(categoryIndex, parameterIndex) {
  form.value.categories[categoryIndex].parameters[parameterIndex].details.push({
    parameter_id: '',
    point: 0
  });
}

function removeParameterDetail(categoryIndex, parameterIndex, detailIndex) {
  if (form.value.categories[categoryIndex].parameters[parameterIndex].details.length > 1) {
    form.value.categories[categoryIndex].parameters[parameterIndex].details.splice(detailIndex, 1);
  }
}

function submit() {
  isSubmitting.value = true;
  errors.value = {};
  
  router.post('/qa-guidances', form.value, {
    onSuccess: () => {
      // Success handled by redirect
    },
    onError: (errs) => {
      errors.value = errs;
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}

function cancel() {
  router.visit('/qa-guidances');
}
</script>

<template>
  <AppLayout title="Tambah QA Guidance">
    <div class="w-full py-8 px-4">
      <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
          <button @click="cancel" class="text-gray-500 hover:text-gray-700 transition">
            <i class="fa-solid fa-arrow-left text-xl"></i>
          </button>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-plus text-blue-500"></i> Tambah QA Guidance Baru
          </h1>
        </div>

        <!-- Form -->
        <div class="bg-white rounded-2xl shadow-lg p-8">
          <form @submit.prevent="submit" class="space-y-8">
            <!-- Header Information -->
            <div class="space-y-6">
              <!-- Title -->
              <div>
                <label for="title" class="block text-sm font-medium text-gray-700 mb-2">
                  Title <span class="text-red-500">*</span>
                </label>
                <input
                  id="title"
                  v-model="form.title"
                  type="text"
                  class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                  placeholder="Masukkan title QA Guidance"
                  required
                />
                <div v-if="errors.title" class="mt-1 text-sm text-red-600">
                  {{ errors.title }}
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Departemen -->
                <div>
                  <label for="departemen" class="block text-sm font-medium text-gray-700 mb-2">
                    Departemen <span class="text-red-500">*</span>
                  </label>
                  <select
                    id="departemen"
                    v-model="form.departemen"
                    class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                    required
                  >
                    <option value="">Pilih Departemen</option>
                    <option v-for="dept in departemenOptions" :key="dept" :value="dept">{{ dept }}</option>
                  </select>
                  <div v-if="errors.departemen" class="mt-1 text-sm text-red-600">
                    {{ errors.departemen }}
                  </div>
                </div>

                <!-- Status -->
                <div>
                  <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                    Status <span class="text-red-500">*</span>
                  </label>
                  <select
                    id="status"
                    v-model="form.status"
                    class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                    required
                  >
                    <option value="A">Aktif</option>
                    <option value="N">Non-Aktif</option>
                  </select>
                  <div v-if="errors.status" class="mt-1 text-sm text-red-600">
                    {{ errors.status }}
                  </div>
                </div>
              </div>

              <!-- Categories (Multiple Selection) -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Categories <span class="text-red-500">*</span>
                </label>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                  <label v-for="category in categories" :key="category.id" class="flex items-center p-3 border border-gray-300 rounded-xl hover:bg-blue-50 cursor-pointer transition">
                    <input
                      type="checkbox"
                      :value="category.id"
                      v-model="form.category_ids"
                      class="mr-3 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    />
                    <span class="text-sm font-medium text-gray-700">{{ category.categories }}</span>
                  </label>
                </div>
                <div v-if="errors.category_ids" class="mt-1 text-sm text-red-600">
                  {{ errors.category_ids }}
                </div>
                <div v-if="form.category_ids.length === 0" class="mt-1 text-sm text-gray-500">
                  Pilih minimal 1 category
                </div>
                <div v-else class="mt-1 text-sm text-green-600">
                  <i class="fa-solid fa-check-circle mr-1"></i>
                  {{ form.category_ids.length }} category dipilih
                </div>
              </div>
            </div>

            <!-- Dynamic Parameters -->
            <div class="space-y-6">
              <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Parameter Pemeriksaan</h3>
                <button
                  type="button"
                  @click="addParameterPemeriksaan"
                  class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-xl font-medium transition flex items-center"
                >
                  <i class="fa-solid fa-plus mr-2"></i>Tambah Parameter Pemeriksaan
                </button>
              </div>

              <div v-for="(param, index) in form.parameters" :key="index" class="border border-gray-200 rounded-xl p-6 bg-gray-50">
                <div class="flex items-center justify-between mb-4">
                  <h4 class="text-md font-semibold text-gray-700">Parameter Pemeriksaan {{ index + 1 }}</h4>
                  <button
                    v-if="form.parameters.length > 1"
                    type="button"
                    @click="removeParameterPemeriksaan(index)"
                    class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm transition"
                  >
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>

                <!-- Parameter Pemeriksaan Name -->
                <div class="mb-4">
                  <label :for="`parameter_pemeriksaan_${index}`" class="block text-sm font-medium text-gray-700 mb-2">
                    Nama Parameter Pemeriksaan <span class="text-red-500">*</span>
                  </label>
                  <input
                    :id="`parameter_pemeriksaan_${index}`"
                    v-model="param.parameter_pemeriksaan"
                    type="text"
                    class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                    placeholder="Masukkan nama parameter pemeriksaan"
                    required
                  />
                  <div v-if="errors[`parameters.${index}.parameter_pemeriksaan`]" class="mt-1 text-sm text-red-600">
                    {{ errors[`parameters.${index}.parameter_pemeriksaan`] }}
                  </div>
                </div>

                <!-- Parameters and Points -->
                <div class="space-y-3">
                  <div class="flex items-center justify-between">
                    <h5 class="text-sm font-medium text-gray-600">Parameter & Point</h5>
                    <button
                      type="button"
                      @click="addParameterToPemeriksaan(index)"
                      class="px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm transition flex items-center"
                    >
                      <i class="fa-solid fa-plus mr-1"></i>Tambah Parameter
                    </button>
                  </div>

                  <!-- Dynamic Parameters for this pemeriksaan -->
                  <div v-for="(subParam, subIndex) in [param]" :key="subIndex" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label :for="`parameter_${index}_${subIndex}`" class="block text-sm font-medium text-gray-700 mb-2">
                        Parameter <span class="text-red-500">*</span>
                      </label>
                      <select
                        :id="`parameter_${index}_${subIndex}`"
                        v-model="subParam.parameter_id"
                        class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                        required
                      >
                        <option value="">Pilih Parameter</option>
                        <option v-for="parameter in parameters" :key="parameter.id" :value="parameter.id">{{ parameter.parameter }}</option>
                      </select>
                      <div v-if="errors[`parameters.${index}.parameter_id`]" class="mt-1 text-sm text-red-600">
                        {{ errors[`parameters.${index}.parameter_id`] }}
                      </div>
                    </div>

                    <div>
                      <label :for="`point_${index}_${subIndex}`" class="block text-sm font-medium text-gray-700 mb-2">
                        Point <span class="text-red-500">*</span>
                      </label>
                      <input
                        :id="`point_${index}_${subIndex}`"
                        v-model.number="subParam.point"
                        type="number"
                        min="0"
                        class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                        placeholder="Masukkan point"
                        required
                      />
                      <div v-if="errors[`parameters.${index}.point`]" class="mt-1 text-sm text-red-600">
                        {{ errors[`parameters.${index}.point`] }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-4 pt-6">
              <button
                type="button"
                @click="cancel"
                class="flex-1 px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-medium transition"
              >
                Batal
              </button>
              <button
                type="submit"
                :disabled="isSubmitting"
                class="flex-1 px-6 py-3 bg-blue-500 hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white rounded-xl font-medium transition flex items-center justify-center"
              >
                <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin mr-2"></i>
                <i v-else class="fa-solid fa-save mr-2"></i>
                {{ isSubmitting ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
