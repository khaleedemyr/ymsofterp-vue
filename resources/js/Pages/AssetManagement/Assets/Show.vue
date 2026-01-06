<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <!-- Header -->
      <div class="mb-6 flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-box text-blue-500"></i> Asset Detail
          </h1>
          <p class="text-gray-600 mt-1">{{ asset.asset_code }}</p>
        </div>
        <div class="flex gap-2">
          <Link
            :href="`/asset-management/assets/${asset.id}/edit`"
            class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg flex items-center gap-2"
          >
            <i class="fa-solid fa-edit"></i> Edit
          </Link>
          <Link
            href="/asset-management/assets"
            class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
          >
            <i class="fa-solid fa-arrow-left"></i> Kembali
          </Link>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Info -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Basic Information -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Basic Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-gray-500">Asset Code</label>
                <p class="text-sm font-semibold text-gray-900 mt-1">{{ asset.asset_code }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Nama</label>
                <p class="text-sm font-semibold text-gray-900 mt-1">{{ asset.name }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Kategori</label>
                <p class="text-sm font-semibold text-gray-900 mt-1">
                  <span v-if="asset.category" class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">
                    {{ asset.category.name }}
                  </span>
                  <span v-else class="text-gray-400">-</span>
                </p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Status</label>
                <p class="mt-1">
                  <span :class="getStatusBadgeClass(asset.status)" class="px-2 py-1 rounded-full text-xs font-medium">
                    {{ asset.status }}
                  </span>
                </p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Brand</label>
                <p class="text-sm text-gray-900 mt-1">{{ asset.brand || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Model</label>
                <p class="text-sm text-gray-900 mt-1">{{ asset.model || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Serial Number</label>
                <p class="text-sm text-gray-900 mt-1">{{ asset.serial_number || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Outlet</label>
                <p class="text-sm text-gray-900 mt-1">
                  <span v-if="asset.current_outlet" class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">
                    {{ asset.current_outlet.name }}
                  </span>
                  <span v-else class="text-gray-400">Tidak Terikat</span>
                </p>
              </div>
            </div>
          </div>

          <!-- Purchase Information -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Purchase Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-gray-500">Purchase Date</label>
                <p class="text-sm text-gray-900 mt-1">{{ formatDate(asset.purchase_date) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Purchase Price</label>
                <p class="text-sm font-semibold text-gray-900 mt-1">{{ formatCurrency(asset.purchase_price) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Supplier</label>
                <p class="text-sm text-gray-900 mt-1">{{ asset.supplier || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Useful Life</label>
                <p class="text-sm text-gray-900 mt-1">{{ asset.useful_life ? asset.useful_life + ' tahun' : '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Warranty Expiry Date</label>
                <p class="text-sm text-gray-900 mt-1">{{ formatDate(asset.warranty_expiry_date) }}</p>
              </div>
            </div>
          </div>

          <!-- Photos -->
          <div v-if="asset.photos && asset.photos.length > 0" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Photos</h2>
            <div class="grid grid-cols-4 gap-4">
              <div v-for="(photo, index) in asset.photos" :key="index" class="relative">
                <img :src="`/storage/${photo}`" alt="Photo" class="w-full h-32 object-cover rounded-lg cursor-pointer hover:opacity-80" @click="openImageModal(`/storage/${photo}`)" />
              </div>
            </div>
          </div>

          <!-- Description -->
          <div v-if="asset.description" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Description</h2>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ asset.description }}</p>
          </div>

          <!-- Transfers -->
          <div v-if="asset.transfers && asset.transfers.length > 0" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Recent Transfers</h2>
            <div class="space-y-3">
              <div v-for="transfer in asset.transfers" :key="transfer.id" class="border-b border-gray-200 pb-3 last:border-b-0">
                <div class="flex justify-between items-center">
                  <div>
                    <p class="text-sm font-medium text-gray-900">
                      {{ transfer.from_outlet?.name }} → {{ transfer.to_outlet?.name }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">{{ formatDate(transfer.transfer_date) }}</p>
                  </div>
                  <span :class="getStatusBadgeClass(transfer.status)" class="px-2 py-1 rounded-full text-xs font-medium">
                    {{ transfer.status }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Maintenances -->
          <div v-if="asset.maintenances && asset.maintenances.length > 0" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Recent Maintenances</h2>
            <div class="space-y-3">
              <div v-for="maintenance in asset.maintenances" :key="maintenance.id" class="border-b border-gray-200 pb-3 last:border-b-0">
                <div class="flex justify-between items-center">
                  <div>
                    <p class="text-sm font-medium text-gray-900">{{ maintenance.maintenance_type }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ formatDate(maintenance.maintenance_date) }} - {{ formatCurrency(maintenance.cost) }}</p>
                  </div>
                  <span :class="getStatusBadgeClass(maintenance.status)" class="px-2 py-1 rounded-full text-xs font-medium">
                    {{ maintenance.status }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- QR Code -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">QR Code</h2>
            <div v-if="asset.qr_code_image" class="flex justify-center">
              <img :src="`/storage/${asset.qr_code_image}`" alt="QR Code" class="w-48 h-48" />
            </div>
            <div v-else class="text-center text-gray-500 py-8">
              <i class="fa-solid fa-qrcode text-4xl mb-2"></i>
              <p class="text-sm">QR Code belum di-generate</p>
              <button
                @click="generateQrCode"
                class="mt-4 px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm"
              >
                Generate QR Code
              </button>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h2>
            <div class="space-y-2">
              <Link
                :href="`/asset-management/transfers/create?asset_id=${asset.id}`"
                class="block w-full px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-center text-sm"
              >
                <i class="fa-solid fa-exchange-alt"></i> Transfer Asset
              </Link>
              <Link
                :href="`/asset-management/maintenance-schedules/create?asset_id=${asset.id}`"
                class="block w-full px-4 py-2 bg-yellow-500 hover:bg-yellow-600 text-white rounded-lg text-center text-sm"
              >
                <i class="fa-solid fa-tools"></i> Schedule Maintenance
              </Link>
              <Link
                :href="`/asset-management/maintenances/create?asset_id=${asset.id}`"
                class="block w-full px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-lg text-center text-sm"
              >
                <i class="fa-solid fa-wrench"></i> Record Maintenance
              </Link>
              <Link
                :href="`/asset-management/disposals/create?asset_id=${asset.id}`"
                class="block w-full px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg text-center text-sm"
              >
                <i class="fa-solid fa-trash"></i> Request Disposal
              </Link>
              <Link
                :href="`/asset-management/documents/create?asset_id=${asset.id}`"
                class="block w-full px-4 py-2 bg-purple-500 hover:bg-purple-600 text-white rounded-lg text-center text-sm"
              >
                <i class="fa-solid fa-file-upload"></i> Upload Document
              </Link>
            </div>
          </div>

          <!-- Maintenance Schedules -->
          <div v-if="asset.maintenance_schedules && asset.maintenance_schedules.length > 0" class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-4">Maintenance Schedules</h2>
            <div class="space-y-3">
              <div v-for="schedule in asset.maintenance_schedules" :key="schedule.id" class="border-b border-gray-200 pb-3 last:border-b-0">
                <div class="flex justify-between items-center">
                  <div>
                    <p class="text-sm font-medium text-gray-900">{{ schedule.maintenance_type }}</p>
                    <p class="text-xs text-gray-500 mt-1">{{ schedule.frequency }} - Next: {{ formatDate(schedule.next_maintenance_date) }}</p>
                  </div>
                  <span :class="schedule.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" class="px-2 py-1 rounded-full text-xs font-medium">
                    {{ schedule.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Image Modal -->
      <div v-if="showImageModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4" @click="closeImageModal">
        <div class="relative max-w-4xl max-h-[90vh] w-full h-full flex items-center justify-center">
          <button @click="closeImageModal" class="absolute top-4 right-4 text-white hover:text-gray-300 text-3xl z-10">
            <i class="fas fa-times"></i>
          </button>
          <img :src="imageModalUrl" alt="Photo" class="max-w-full max-h-full object-contain rounded-lg shadow-2xl" @click.stop />
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  asset: Object,
});

const showImageModal = ref(false);
const imageModalUrl = ref('');

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
    'Active': 'bg-green-100 text-green-800',
    'Maintenance': 'bg-yellow-100 text-yellow-800',
    'Disposed': 'bg-red-100 text-red-800',
    'Lost': 'bg-gray-100 text-gray-800',
    'Transfer': 'bg-blue-100 text-blue-800',
    'Pending': 'bg-yellow-100 text-yellow-800',
    'Approved': 'bg-green-100 text-green-800',
    'Completed': 'bg-blue-100 text-blue-800',
    'Rejected': 'bg-red-100 text-red-800',
    'Scheduled': 'bg-gray-100 text-gray-800',
    'In Progress': 'bg-yellow-100 text-yellow-800',
    'Cancelled': 'bg-red-100 text-red-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

function openImageModal(url) {
  imageModalUrl.value = url;
  showImageModal.value = true;
}

function closeImageModal() {
  showImageModal.value = false;
  imageModalUrl.value = '';
}

async function generateQrCode() {
  try {
    const response = await axios.post(`/asset-management/assets/${props.asset.id}/generate-qr-code`, {}, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
    });
    
    if (response.data?.success) {
      Swal.fire('Berhasil', 'QR Code berhasil di-generate', 'success');
      router.reload();
    }
  } catch (error) {
    Swal.fire('Error', 'Gagal generate QR Code', 'error');
  }
}
</script>

