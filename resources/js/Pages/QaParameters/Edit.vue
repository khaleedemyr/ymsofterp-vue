<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  parameter: Object
});

const form = ref({
  kode_parameter: props.parameter.kode_parameter,
  parameter: props.parameter.parameter,
  status: props.parameter.status
});

const errors = ref({});
const isSubmitting = ref(false);

function submit() {
  isSubmitting.value = true;
  errors.value = {};
  
  router.put(`/qa-parameters/${props.parameter.id}`, form.value, {
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
  router.visit(`/qa-parameters/${props.parameter.id}`);
}
</script>

<template>
  <AppLayout title="Edit QA Parameter">
    <div class="w-full py-8 px-4">
      <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
          <button @click="cancel" class="text-gray-500 hover:text-gray-700 transition">
            <i class="fa-solid fa-arrow-left text-xl"></i>
          </button>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-edit text-blue-500"></i> Edit QA Parameter
          </h1>
        </div>

        <!-- Form -->
        <div class="bg-white rounded-2xl shadow-lg p-8">
          <form @submit.prevent="submit" class="space-y-6">
            <!-- Kode Parameter -->
            <div>
              <label for="kode_parameter" class="block text-sm font-medium text-gray-700 mb-2">
                Kode Parameter <span class="text-red-500">*</span>
              </label>
              <input
                id="kode_parameter"
                v-model="form.kode_parameter"
                type="text"
                class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                placeholder="Masukkan kode parameter"
                required
              />
              <div class="mt-1 text-sm text-blue-600">
                <i class="fa-solid fa-info-circle mr-1"></i>
                Kode parameter dapat diedit sesuai kebutuhan
              </div>
              <div v-if="errors.kode_parameter" class="mt-1 text-sm text-red-600">
                {{ errors.kode_parameter }}
              </div>
            </div>

            <!-- Parameter -->
            <div>
              <label for="parameter" class="block text-sm font-medium text-gray-700 mb-2">
                Parameter <span class="text-red-500">*</span>
              </label>
              <input
                id="parameter"
                v-model="form.parameter"
                type="text"
                class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition"
                placeholder="Masukkan nama parameter"
                required
              />
              <div v-if="errors.parameter" class="mt-1 text-sm text-red-600">
                {{ errors.parameter }}
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
                {{ isSubmitting ? 'Menyimpan...' : 'Update' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
