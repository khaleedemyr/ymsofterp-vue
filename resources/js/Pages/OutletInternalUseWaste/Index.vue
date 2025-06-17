<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-recycle text-green-500"></i> Internal Use & Waste Outlet
        </h1>
        <div class="flex gap-2">
          <button @click="goReportUniversal" class="bg-blue-500 text-white px-4 py-2 rounded-xl shadow-lg hover:bg-blue-600 transition-all font-semibold">
            <i class="fa fa-file-lines mr-1"></i> Laporan
          </button>
          <button @click="goCreate" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Tambah Baru
          </button>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tipe</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!props.data.length">
                <td colspan="8" class="text-center py-10 text-green-300">Tidak ada data.</td>
              </tr>
              <tr v-for="row in props.data" :key="row.id">
                <td class="px-6 py-3">{{ formatDate(row.date) }}</td>
                <td class="px-6 py-3">{{ typeLabel(row.type) }}</td>
                <td class="px-6 py-3">{{ row.outlet_name }}</td>
                <td class="px-6 py-3">{{ row.warehouse_outlet_name || '-' }}</td>
                <td class="px-6 py-3">
                  <button class="inline-flex items-center btn btn-xs bg-green-100 text-green-800 hover:bg-green-200 rounded px-2 py-1 font-semibold transition" @click="goDetail(row.id)">
                    <i class="fa fa-eye mr-1"></i> Detail
                  </button>
                  <button class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition ml-2" @click="onDelete(row.id)" :disabled="loadingId === row.id">
                    <span v-if="loadingId === row.id"><i class="fa fa-spinner fa-spin mr-1"></i> Menghapus...</span>
                    <span v-else><i class="fa fa-trash mr-1"></i> Hapus</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { ref } from 'vue'

const props = defineProps({
  data: Array
})

const loadingId = ref(null)

function goCreate() {
  router.visit(route('outlet-internal-use-waste.create'))
}

function goReportUniversal() {
  router.visit(route('outlet-internal-use-waste.report-universal'))
}

function goReportWasteSpoil() {
  router.visit(route('outlet-internal-use-waste.report-waste-spoil'))
}

function goDetail(id) {
  router.visit(route('outlet-internal-use-waste.show', id))
}

function onDelete(id) {
  Swal.fire({
    title: 'Yakin hapus data ini?',
    text: 'Stok akan di-rollback otomatis!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then(async (result) => {
    if (result.isConfirmed) {
      loadingId.value = id
      try {
        const res = await axios.delete(`/outlet-internal-use-waste/${id}`)
        if (res.data && res.data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: 'Data berhasil dihapus dan stok di-rollback!',
            timer: 1500,
            showConfirmButton: false
          })
          setTimeout(() => router.reload(), 1200)
        } else {
          throw new Error('Gagal menghapus data')
        }
      } catch (e) {
        Swal.fire({
          icon: 'error',
          title: 'Gagal',
          text: e.response?.data?.message || e.message || 'Gagal menghapus data',
        })
      } finally {
        loadingId.value = null
      }
    }
  })
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID')
}

function formatNumber(val) {
  if (val == null) return '-';
  if (Number(val) % 1 === 0) return Number(val);
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function typeLabel(type) {
  if (type === 'internal_use') return 'Internal Use';
  if (type === 'spoil') return 'Spoil';
  if (type === 'waste') return 'Waste';
  if (type === 'r_and_d') return 'R & D';
  if (type === 'marketing') return 'Marketing';
  return type;
}
</script> 