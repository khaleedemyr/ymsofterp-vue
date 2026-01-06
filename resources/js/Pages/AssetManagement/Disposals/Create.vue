<template>
  <AppLayout>
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-trash text-blue-500"></i> Request Asset Disposal
        </h1>
      </div>

      <form @submit.prevent="submit" class="bg-white rounded-xl shadow-lg p-6 space-y-6">
        <!-- Asset -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Asset <span class="text-red-500">*</span>
          </label>
          <select
            v-model="form.asset_id"
            required
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Pilih Asset</option>
            <option v-for="asset in assets" :key="asset.id" :value="asset.id">
              {{ asset.asset_code }} - {{ asset.name }}
            </option>
          </select>
        </div>

        <!-- Disposal Date & Method -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Disposal Date <span class="text-red-500">*</span>
            </label>
            <input
              type="date"
              v-model="form.disposal_date"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Disposal Method <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.disposal_method"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Pilih Method</option>
              <option value="Sold">Sold</option>
              <option value="Broken">Broken</option>
              <option value="Donated">Donated</option>
              <option value="Scrapped">Scrapped</option>
            </select>
          </div>
        </div>

        <!-- Disposal Value -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Disposal Value</label>
          <input
            type="number"
            v-model="form.disposal_value"
            step="0.01"
            min="0"
            placeholder="0"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          />
          <p class="text-xs text-gray-500 mt-1">Nilai asset saat disposal (jika ada)</p>
        </div>

        <!-- Reason -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Reason <span class="text-red-500">*</span>
          </label>
          <textarea
            v-model="form.reason"
            required
            rows="4"
            placeholder="Masukkan alasan disposal..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          ></textarea>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
          <textarea
            v-model="form.notes"
            rows="3"
            placeholder="Masukkan catatan tambahan..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
          <Link
            href="/asset-management/disposals"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
          >
            Batal
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg flex items-center gap-2 disabled:opacity-50"
          >
            <i v-if="form.processing" class="fa-solid fa-spinner fa-spin"></i>
            <span>{{ form.processing ? 'Mengirim...' : 'Request Disposal' }}</span>
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { useForm, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  assets: Array,
});

const form = useForm({
  asset_id: '',
  disposal_date: new Date().toISOString().split('T')[0],
  disposal_method: '',
  disposal_value: null,
  reason: '',
  notes: '',
});

function submit() {
  form.post('/asset-management/disposals', {
    preserveScroll: true,
    onSuccess: () => {
      router.visit('/asset-management/disposals');
    },
  });
}
</script>

