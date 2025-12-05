<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  sale: Object,
  items: Array,
});

function goBack() {
  router.visit(route('retail-warehouse-sale.index'));
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('id-ID');
}

function formatCurrency(amount) {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR'
  }).format(amount);
}

async function deleteSale() {
  const result = await Swal.fire({
    title: 'Hapus Penjualan?',
    text: `Yakin ingin menghapus penjualan "${props.sale.number}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  });

  if (result.isConfirmed) {
    router.delete(route('retail-warehouse-sale.destroy', props.sale.id), {
      onSuccess: () => {
        Swal.fire('Berhasil', 'Penjualan berhasil dihapus!', 'success');
      },
    });
  }
}

function printReceipt() {
  // Open print page in new window
  window.open(route('retail-warehouse-sale.print', props.sale.id), '_blank');
}
</script>

<template>
  <AppLayout>
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-store text-blue-500"></i>
          Detail Penjualan Warehouse Retail
        </h1>
        <div class="flex gap-2">
          <button @click="printReceipt" class="bg-green-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold">
            <i class="fa-solid fa-print mr-2"></i>
            Cetak
          </button>
          <button @click="deleteSale" class="bg-red-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold">
            <i class="fa-solid fa-trash mr-2"></i>
            Hapus
          </button>
          <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-xl transition-all font-semibold">
            Kembali
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Sale Information -->
        <div class="lg:col-span-1">
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Penjualan</h2>
            
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-500">No. Penjualan</label>
                <p class="text-lg font-bold text-blue-600">{{ sale.number }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-500">Tanggal Penjualan</label>
                <p class="text-gray-800">{{ formatDate(sale.sale_date || sale.created_at) }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-500">Status</label>
                <span :class="{
                  'bg-green-100 text-green-700': sale.status === 'completed',
                  'bg-yellow-100 text-yellow-700': sale.status === 'draft',
                  'bg-red-100 text-red-700': sale.status === 'cancelled'
                }" class="px-2 py-1 rounded-full text-xs font-semibold">
                  {{ sale.status === 'completed' ? 'Selesai' : sale.status === 'draft' ? 'Draft' : 'Dibatalkan' }}
                </span>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-500">Total Amount</label>
                <p class="text-2xl font-bold text-green-600">{{ formatCurrency(sale.total_amount) }}</p>
              </div>

              <div v-if="sale.notes">
                <label class="block text-sm font-medium text-gray-500">Catatan</label>
                <p class="text-gray-800">{{ sale.notes }}</p>
              </div>
            </div>
          </div>

          <!-- Customer Information -->
          <div class="bg-white rounded-2xl shadow-2xl p-6 mt-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Customer</h2>
            
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-500">Nama Customer</label>
                <p class="text-lg font-semibold text-gray-800">{{ sale.customer_name }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-500">Kode Customer</label>
                <p class="text-gray-800">{{ sale.customer_code }}</p>
              </div>

              <div v-if="sale.customer_phone">
                <label class="block text-sm font-medium text-gray-500">Nomor Telepon</label>
                <p class="text-gray-800">{{ sale.customer_phone }}</p>
              </div>

              <div v-if="sale.customer_address">
                <label class="block text-sm font-medium text-gray-500">Alamat</label>
                <p class="text-gray-800">{{ sale.customer_address }}</p>
              </div>
            </div>
          </div>

          <!-- Warehouse Information -->
          <div class="bg-white rounded-2xl shadow-2xl p-6 mt-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Informasi Warehouse</h2>
            
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-500">Warehouse</label>
                <p class="text-lg font-semibold text-gray-800">{{ sale.warehouse_name }}</p>
              </div>

              <div v-if="sale.division_name">
                <label class="block text-sm font-medium text-gray-500">Division</label>
                <p class="text-gray-800">{{ sale.division_name }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-500">Dibuat Oleh</label>
                <p class="text-gray-800">{{ sale.created_by_name }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Items List -->
        <div class="lg:col-span-2">
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Item Penjualan</h2>
            
            <div v-if="items.length === 0" class="text-center py-10 text-gray-400">
              Tidak ada item dalam penjualan ini
            </div>

            <div v-else class="space-y-4">
              <div v-for="(item, index) in items" :key="index" class="border border-gray-200 rounded-lg p-4">
                <div class="flex justify-between items-start mb-3">
                  <div class="flex-1">
                    <h3 class="font-semibold text-gray-800">{{ item.item_name }}</h3>
                    <p v-if="item.barcode" class="text-sm text-gray-500">Barcode: {{ item.barcode }}</p>
                  </div>
                  <div class="text-right">
                    <p class="font-semibold text-blue-600">{{ formatCurrency(item.subtotal) }}</p>
                  </div>
                </div>

                <div class="grid grid-cols-3 gap-4 text-sm">
                  <div>
                    <label class="block text-gray-500">Qty</label>
                    <p class="font-semibold">{{ item.qty }}</p>
                  </div>
                  <div>
                    <label class="block text-gray-500">Unit</label>
                    <p class="font-semibold">{{ item.unit }}</p>
                  </div>
                  <div>
                    <label class="block text-gray-500">Harga</label>
                    <p class="font-semibold">{{ formatCurrency(item.price) }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Summary -->
            <div class="mt-6 pt-6 border-t border-gray-200">
              <div class="flex justify-between items-center">
                <span class="text-lg font-semibold text-gray-800">Total</span>
                <span class="text-2xl font-bold text-green-600">{{ formatCurrency(sale.total_amount) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
@media print {
  .bg-white {
    background: white !important;
  }
  
  .shadow-2xl {
    box-shadow: none !important;
  }
  
  button {
    display: none !important;
  }
}
</style> 