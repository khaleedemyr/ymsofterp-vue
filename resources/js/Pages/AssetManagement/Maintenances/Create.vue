<template>
  <AppLayout>
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-wrench text-blue-500"></i> Record Maintenance
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
            @change="onAssetChange"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Pilih Asset</option>
            <option v-for="asset in assets" :key="asset.id" :value="asset.id">
              {{ asset.asset_code }} - {{ asset.name }}
            </option>
          </select>
        </div>

        <!-- Maintenance Schedule (Optional) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Maintenance Schedule (Optional)</label>
          <select
            v-model="form.maintenance_schedule_id"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="">Tidak dari Schedule</option>
            <option v-for="schedule in availableSchedules" :key="schedule.id" :value="schedule.id">
              {{ schedule.maintenance_type }} - {{ schedule.frequency }} (Next: {{ formatDate(schedule.next_maintenance_date) }})
            </option>
          </select>
        </div>

        <!-- Maintenance Date & Type -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Maintenance Date <span class="text-red-500">*</span>
            </label>
            <input
              type="date"
              v-model="form.maintenance_date"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
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
        </div>

        <!-- Cost & Vendor -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cost</label>
            <input
              type="number"
              v-model="form.cost"
              step="0.01"
              min="0"
              placeholder="0"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Vendor</label>
            <input
              type="text"
              v-model="form.vendor"
              placeholder="Masukkan vendor/service provider"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>

        <!-- Status & Performed By -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Status <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.status"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="Scheduled">Scheduled</option>
              <option value="In Progress">In Progress</option>
              <option value="Completed">Completed</option>
              <option value="Cancelled">Cancelled</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Performed By</label>
            <input
              type="text"
              v-model="form.performed_by"
              placeholder="Nama teknisi/performer"
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
        </div>

        <!-- Notes -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
          <textarea
            v-model="form.notes"
            rows="4"
            placeholder="Masukkan catatan maintenance..."
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
          ></textarea>
        </div>

        <!-- Actions -->
        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
          <Link
            href="/asset-management/maintenances"
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
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  assets: Array,
  schedules: Array,
});

const form = useForm({
  asset_id: '',
  maintenance_schedule_id: null,
  maintenance_date: new Date().toISOString().split('T')[0],
  maintenance_type: '',
  cost: null,
  vendor: '',
  notes: '',
  status: 'Scheduled',
  performed_by: null,
});

const availableSchedules = computed(() => {
  if (!form.asset_id) return [];
  return props.schedules.filter(s => s.asset_id == form.asset_id && s.is_active);
});

function onAssetChange() {
  // Auto-select maintenance type from schedule if available
  if (availableSchedules.value.length > 0) {
    form.maintenance_schedule_id = availableSchedules.value[0].id;
    form.maintenance_type = availableSchedules.value[0].maintenance_type;
  }
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

function submit() {
  form.post('/asset-management/maintenances', {
    preserveScroll: true,
  });
}
</script>

