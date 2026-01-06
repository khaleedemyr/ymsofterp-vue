<template>
  <AppLayout>
    <div class="max-w-4xl w-full mx-auto py-8 px-2">
      <!-- Header -->
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-wrench text-blue-500"></i> Maintenance Detail
          </h1>
          <p class="text-gray-600 mt-1">Maintenance #{{ maintenance.id }}</p>
        </div>
        <div class="flex gap-2">
          <Link
            :href="`/asset-management/maintenances/${maintenance.id}/edit`"
            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg flex items-center gap-2"
          >
            <i class="fa-solid fa-edit"></i> Edit
          </Link>
          <Link
            href="/asset-management/maintenances"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6 space-y-6">
        <!-- Asset Information -->
        <div>
          <h2 class="text-lg font-bold text-gray-900 mb-4">Asset Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-500">Asset Code</label>
              <p class="text-sm font-semibold text-gray-900 mt-1">{{ maintenance.asset?.asset_code }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Asset Name</label>
              <p class="text-sm font-semibold text-gray-900 mt-1">{{ maintenance.asset?.name }}</p>
            </div>
          </div>
        </div>

        <!-- Maintenance Information -->
        <div>
          <h2 class="text-lg font-bold text-gray-900 mb-4">Maintenance Information</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-500">Maintenance Date</label>
              <p class="text-sm text-gray-900 mt-1">{{ formatDate(maintenance.maintenance_date) }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Maintenance Type</label>
              <p class="text-sm text-gray-900 mt-1">{{ maintenance.maintenance_type }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Status</label>
              <p class="mt-1">
                <span :class="getStatusBadgeClass(maintenance.status)" class="px-2 py-1 rounded-full text-xs font-medium">
                  {{ maintenance.status }}
                </span>
              </p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Cost</label>
              <p class="text-sm font-semibold text-gray-900 mt-1">{{ formatCurrency(maintenance.cost) }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Vendor</label>
              <p class="text-sm text-gray-900 mt-1">{{ maintenance.vendor || '-' }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-500">Performed By</label>
              <p class="text-sm text-gray-900 mt-1">{{ maintenance.performed_by || '-' }}</p>
            </div>
            <div v-if="maintenance.schedule">
              <label class="text-sm font-medium text-gray-500">From Schedule</label>
              <p class="text-sm text-gray-900 mt-1">
                {{ maintenance.schedule.maintenance_type }} - {{ maintenance.schedule.frequency }}
              </p>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="maintenance.notes">
          <h2 class="text-lg font-bold text-gray-900 mb-4">Notes</h2>
          <p class="text-sm text-gray-700 whitespace-pre-line">{{ maintenance.notes }}</p>
        </div>

        <!-- Actions -->
        <div v-if="maintenance.status !== 'Completed'" class="flex gap-3 pt-4 border-t border-gray-200">
          <button
            @click="completeMaintenance"
            class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg flex items-center gap-2"
          >
            <i class="fa-solid fa-check-circle"></i> Complete Maintenance
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  maintenance: Object,
});

function formatCurrency(value) {
  if (value == null || value === undefined) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', { 
    style: 'currency', 
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

function getStatusBadgeClass(status) {
  const classes = {
    'Scheduled': 'bg-gray-100 text-gray-800',
    'In Progress': 'bg-yellow-100 text-yellow-800',
    'Completed': 'bg-green-100 text-green-800',
    'Cancelled': 'bg-red-100 text-red-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

async function completeMaintenance() {
  const result = await Swal.fire({
    title: 'Complete Maintenance?',
    text: `Konfirmasi bahwa maintenance untuk "${props.maintenance.asset?.name}" sudah selesai?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Complete',
    cancelButtonText: 'Batal',
  });

  if (result.isConfirmed) {
    try {
      const response = await axios.post(`/asset-management/maintenances/${props.maintenance.id}/complete`, {}, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });
      
      if (response.data?.success) {
        Swal.fire('Berhasil', 'Maintenance berhasil di-complete', 'success');
        router.reload();
      }
    } catch (error) {
      Swal.fire('Error', error.response?.data?.message || 'Gagal complete maintenance', 'error');
    }
  }
}
</script>

