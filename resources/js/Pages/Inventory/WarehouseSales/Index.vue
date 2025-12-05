<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fas fa-exchange-alt"></i> Penjualan Antar Gudang
        </h1>
        <Link
          :href="route('warehouse-sales.create')"
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          + Buat Penjualan Antar Gudang
        </Link>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Nomor</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Gudang Asal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Gudang Tujuan</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Total Item</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Dibuat Oleh</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!sales.data || !sales.data.length">
              <td colspan="8" class="text-center py-10 text-gray-400">Belum ada data Penjualan Antar Gudang.</td>
            </tr>
            <tr v-for="sale in sales.data" :key="sale.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3 font-mono font-semibold text-blue-700">{{ sale.number }}</td>
              <td class="px-6 py-3">{{ formatDate(sale.date) }}</td>
              <td class="px-6 py-3">{{ sale.source_warehouse?.name }}</td>
              <td class="px-6 py-3">{{ sale.target_warehouse?.name }}</td>
              <td class="px-6 py-3">{{ sale.total_items }}</td>
              <td class="px-6 py-3">
                <span :class="{
                  'bg-gray-100 text-gray-700': sale.status === 'draft',
                  'bg-green-100 text-green-700': sale.status === 'confirmed',
                  'bg-red-100 text-red-700': sale.status === 'rejected',
                }" class="px-2 py-1 rounded-full text-xs font-semibold shadow">
                  {{ sale.status }}
                </span>
              </td>
              <td class="px-6 py-3">{{ sale.creator?.nama_lengkap }}</td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="viewDetail(sale)" class="text-blue-600 hover:text-blue-900 mr-2">
                    <i class="fas fa-eye"></i>
                  </button>
                  <button @click="deleteSale(sale)" class="text-red-600 hover:text-red-900">
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in sales.links"
          :key="link.label"
          :disabled="!link.url"
          @click="goToPage(link.url)"
          v-html="link.label"
          class="px-3 py-1 rounded-lg border text-sm font-semibold"
          :class="[
            link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
            !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
          ]"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';

const props = defineProps({
  sales: Object
});

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function viewDetail(sale) {
  router.visit(`/warehouse-sales/${sale.id}`);
}

async function deleteSale(sale) {
  const result = await Swal.fire({
    title: 'Hapus Data?',
    text: 'Apakah Anda yakin ingin menghapus Penjualan Antar Gudang ini? Data yang dihapus tidak dapat dikembalikan.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
    allowOutsideClick: false
  });

  if (result.isConfirmed) {
    Swal.fire({
      title: 'Menghapus...',
      text: 'Mohon tunggu sebentar',
      allowOutsideClick: false,
      allowEscapeKey: false,
      showConfirmButton: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    try {
      await router.delete(route('warehouse-sales.destroy', sale.id), {
        onSuccess: () => {
          Swal.fire({
            title: 'Berhasil!',
            text: 'Data berhasil dihapus',
            icon: 'success',
            timer: 1500,
            showConfirmButton: false
          });
        },
        onError: (errors) => {
          Swal.fire({
            title: 'Error!',
            text: errors.message || 'Terjadi kesalahan saat menghapus data',
            icon: 'error'
          });
        }
      });
    } catch (error) {
      Swal.fire({
        title: 'Error!',
        text: 'Terjadi kesalahan saat menghapus data',
        icon: 'error'
      });
    }
  }
}
</script> 