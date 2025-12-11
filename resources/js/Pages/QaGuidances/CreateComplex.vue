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
      <div class="max-w-7xl mx-auto">
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
            </div>

            <!-- 3-Level Dynamic Form -->
            <div class="space-y-8">
              <div class="flex items-center justify-between">
                <h3 class="text-xl font-semibold text-gray-800">Categories & Parameters</h3>
                <button
                  type="button"
                  @click="addCategory"
                  class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-xl font-medium transition flex items-center"
                >
                  <i class="fa-solid fa-plus mr-2"></i>Tambah Category
                </button>
              </div>

              <!-- Categories Level -->
              <div v-for="(category, categoryIndex) in form.categories" :key="categoryIndex" class="border border-gray-200 rounded-xl p-6 bg-gray-50">
                <div class="flex items-center justify-between mb-6">
                  <h4 class="text-lg font-semibold text-gray-800">Category {{ categoryIndex + 1 }}</h4>
                  <button
                    v-if="form.categories.length > 1"
                    type="button"
                    @click="removeCategory(categoryIndex)"
                    class="px-3 py-1 bg-red-500 hover:bg-red-600 text-white rounded-lg text-sm transition"
                  >
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>

                <!-- Category Selection -->
                <div class="mb-6">
                  <label :for="`category_${categoryIndex}`" class="block text-sm font-medium text-gray-700 mb-2">
                    Pilih Category <span class="text-red-500">*</span>
                  </label>
                  <select
                    :id="`category_${categoryIndex}`"
                    v-model="category.category_id"
                    class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                    required
                  >
                    <option value="">Pilih Category</option>
                    <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.categories }}</option>
                  </select>
                  <div v-if="errors[`categories.${categoryIndex}.category_id`]" class="mt-1 text-sm text-red-600">
                    {{ errors[`categories.${categoryIndex}.category_id`] }}
                  </div>
                </div>

                <!-- Parameters Level -->
                <div class="space-y-4">
                  <div class="flex items-center justify-between">
                    <h5 class="text-md font-semibold text-gray-700">Parameter Pemeriksaan</h5>
                    <button
                      type="button"
                      @click="addParameterPemeriksaan(categoryIndex)"
                      class="px-3 py-1 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm transition flex items-center"
                    >
                      <i class="fa-solid fa-plus mr-1"></i>Tambah Parameter Pemeriksaan
                    </button>
                  </div>

                  <div v-for="(parameter, parameterIndex) in category.parameters" :key="parameterIndex" class="border border-gray-300 rounded-lg p-4 bg-white">
                    <div class="flex items-center justify-between mb-4">
                      <h6 class="text-sm font-semibold text-gray-700">Parameter Pemeriksaan {{ parameterIndex + 1 }}</h6>
                      <button
                        v-if="category.parameters.length > 1"
                        type="button"
                        @click="removeParameterPemeriksaan(categoryIndex, parameterIndex)"
                        class="px-2 py-1 bg-red-500 hover:bg-red-600 text-white rounded-lg text-xs transition"
                      >
                        <i class="fa-solid fa-trash"></i>
                      </button>
                    </div>

                    <!-- Parameter Pemeriksaan Name -->
                    <div class="mb-4">
                      <label :for="`parameter_pemeriksaan_${categoryIndex}_${parameterIndex}`" class="block text-sm font-medium text-gray-700 mb-2">
                        Nama Parameter Pemeriksaan <span class="text-red-500">*</span>
                      </label>
                      <textarea
                        :id="`parameter_pemeriksaan_${categoryIndex}_${parameterIndex}`"
                        v-model="parameter.parameter_pemeriksaan"
                        rows="3"
                        class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition resize-y"
                        placeholder="Masukkan nama parameter pemeriksaan"
                        required
                      ></textarea>
                      <div v-if="errors[`categories.${categoryIndex}.parameters.${parameterIndex}.parameter_pemeriksaan`]" class="mt-1 text-sm text-red-600">
                        {{ errors[`categories.${categoryIndex}.parameters.${parameterIndex}.parameter_pemeriksaan`] }}
                      </div>
                    </div>

                    <!-- Parameter Details Level -->
                    <div class="space-y-3">
                      <div class="flex items-center justify-between">
                        <h6 class="text-sm font-medium text-gray-600">Parameter & Point</h6>
                        <button
                          type="button"
                          @click="addParameterDetail(categoryIndex, parameterIndex)"
                          class="px-2 py-1 bg-green-500 hover:bg-green-600 text-white rounded-lg text-xs transition flex items-center"
                        >
                          <i class="fa-solid fa-plus mr-1"></i>Tambah Parameter
                        </button>
                      </div>

                      <div v-for="(detail, detailIndex) in parameter.details" :key="detailIndex" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <label :for="`parameter_${categoryIndex}_${parameterIndex}_${detailIndex}`" class="block text-sm font-medium text-gray-700 mb-2">
                            Parameter <span class="text-red-500">*</span>
                          </label>
                          <select
                            :id="`parameter_${categoryIndex}_${parameterIndex}_${detailIndex}`"
                            v-model="detail.parameter_id"
                            class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                            required
                          >
                            <option value="">Pilih Parameter</option>
                            <option v-for="param in parameters" :key="param.id" :value="param.id">{{ param.parameter }}</option>
                          </select>
                          <div v-if="errors[`categories.${categoryIndex}.parameters.${parameterIndex}.details.${detailIndex}.parameter_id`]" class="mt-1 text-sm text-red-600">
                            {{ errors[`categories.${categoryIndex}.parameters.${parameterIndex}.details.${detailIndex}.parameter_id`] }}
                          </div>
                        </div>

                        <div>
                          <label :for="`point_${categoryIndex}_${parameterIndex}_${detailIndex}`" class="block text-sm font-medium text-gray-700 mb-2">
                            Point <span class="text-red-500">*</span>
                          </label>
                          <div class="flex gap-2">
                            <input
                              :id="`point_${categoryIndex}_${parameterIndex}_${detailIndex}`"
                              v-model.number="detail.point"
                              type="number"
                              min="0"
                              class="flex-1 px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                              placeholder="Masukkan point"
                              required
                            />
                            <button
                              v-if="parameter.details.length > 1"
                              type="button"
                              @click="removeParameterDetail(categoryIndex, parameterIndex, detailIndex)"
                              class="px-3 py-3 bg-red-500 hover:bg-red-600 text-white rounded-xl transition"
                            >
                              <i class="fa-solid fa-trash"></i>
                            </button>
                          </div>
                          <div v-if="errors[`categories.${categoryIndex}.parameters.${parameterIndex}.details.${detailIndex}.point`]" class="mt-1 text-sm text-red-600">
                            {{ errors[`categories.${categoryIndex}.parameters.${parameterIndex}.details.${detailIndex}.point`] }}
                          </div>
                        </div>
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
