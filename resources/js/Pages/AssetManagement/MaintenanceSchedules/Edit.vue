<template>
  <AppLayout>
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-edit text-blue-500"></i> Edit Maintenance Schedule
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

        <!-- Maintenance Type & Frequency -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Maintenance Type <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.maintenance_type"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Pilih Tipe</option>
              <option value="Cleaning">Cleaning</option>
              <option value="Service">Service</option>
              <option value="Repair">Repair</option>
              <option value="Inspection">Inspection</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Frequency <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.frequency"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Pilih Frequency</option>
              <option value="Daily">Daily</option>
              <option value="Weekly">Weekly</option>
              <option value="Monthly">Monthly</option>
              <option value="Quarterly">Quarterly</option>
              <option value="Yearly">Yearly</option>
            </select>
          </div>
        </div>

        <!-- Next Maintenance Date & Last Maintenance Date -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Next Maintenance Date <span class="text-red-500">*</span>
            </label>
            <input
              type="date"
              v-model="form.next_maintenance_date"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Last Maintenance Date</label>
            <input
              type="date"
              v-model="form.last_maintenance_date"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
          <textarea
            v-model="form.notes"
            rows="3"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          ></textarea>
        </div>

        <!-- Is Active -->
        <div>
          <label class="flex items-center gap-2">
            <input
              type="checkbox"
              v-model="form.is_active"
              class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
            />
            <span class="text-sm font-medium text-gray-700">Active</span>
          </label>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
          <Link
            :href="`/asset-management/maintenance-schedules/${schedule.id}`"
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
            <span>{{ form.processing ? 'Menyimpan...' : 'Simpan' }}</span>
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  schedule: Object,
  assets: Array,
});

const form = useForm({
  asset_id: props.schedule.asset_id || '',
  maintenance_type: props.schedule.maintenance_type || '',
  frequency: props.schedule.frequency || '',
  next_maintenance_date: props.schedule.next_maintenance_date || '',
  last_maintenance_date: props.schedule.last_maintenance_date || '',
  notes: props.schedule.notes || '',
  is_active: props.schedule.is_active || true,
});

function submit() {
  form.put(`/asset-management/maintenance-schedules/${props.schedule.id}`, {
    preserveScroll: true,
  });
}
</script>

