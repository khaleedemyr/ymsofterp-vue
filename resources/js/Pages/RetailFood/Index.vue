<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-store text-blue-500"></i> Outlet Retail Food
        </h1>
        <button @click="goCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Baru
        </button>
      </div>

      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">No. Transaksi</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Warehouse Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Supplier</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Metode Pembayaran</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Total</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!props.retailFoods.data.length">
                <td colspan="8" class="text-center py-10 text-gray-400">Tidak ada data.</td>
              </tr>
              <tr v-for="row in props.retailFoods.data" :key="row.id">
                <td class="px-6 py-3">{{ formatDate(row.transaction_date) }}</td>
                <td class="px-6 py-3">{{ row.retail_number }}</td>
                <td class="px-6 py-3">{{ row.outlet?.nama_outlet || '-' }}</td>
                <td class="px-6 py-3">{{ row.warehouse_outlet_name || '-' }}</td>
                <td class="px-6 py-3">{{ row.supplier_name || '-' }}</td>
                <td class="px-6 py-3">
                  <span :class="{
                    'px-2 py-1 text-xs font-semibold rounded-full': true,
                    'bg-green-100 text-green-800': row.payment_method === 'cash',
                    'bg-blue-100 text-blue-800': row.payment_method === 'contra_bon'
                  }">
                    {{ row.payment_method === 'cash' ? 'Cash' : 'Contra Bon' }}
                  </span>
                </td>
                <td class="px-6 py-3 text-right">{{ formatRupiah(row.total_amount) }}</td>
                <td class="px-6 py-3">
                  <span :class="{
                    'px-2 py-1 text-xs font-semibold rounded-full': true,
                    'bg-yellow-100 text-yellow-800': row.status === 'draft',
                    'bg-green-100 text-green-800': row.status === 'approved'
                  }">
                    {{ row.status === 'draft' ? 'Draft' : 'Approved' }}
                  </span>
                </td>
                <td class="px-6 py-3">
                  <button class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition" @click="goDetail(row.id)">
                    <i class="fa fa-eye mr-1"></i> Detail
                  </button>
                  <button v-if="row.status === 'draft'" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition ml-2" @click="onDelete(row)" :disabled="loadingId === row.id">
                    <span v-if="loadingId === row.id"><i class="fa fa-spinner fa-spin mr-1"></i> Menghapus...</span>
                    <span v-else><i class="fa fa-trash mr-1"></i> Hapus</span>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in props.retailFoods.links"
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
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'
import { ref } from 'vue'

const props = defineProps({
  user: Object,
  retailFoods: Object
})

const loadingId = ref(null)

function goCreate() {
  router.visit('/retail-food/create')
}

function goDetail(id) {
  router.visit(`/retail-food/${id}`)
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true })
}

async function onDelete(row) {
  const result = await Swal.fire({
    title: 'Hapus Transaksi?',
    text: `Yakin ingin menghapus transaksi ${row.retail_number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  })

  if (!result.isConfirmed) return

  loadingId.value = row.id
  try {
    const res = await axios.delete(`/retail-food/${row.id}`)
    if (res.data && res.data.message) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: res.data.message,
        timer: 1500,
        showConfirmButton: false
      })
      setTimeout(() => router.reload(), 1200)
    }
  } catch (e) {
    Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: e.response?.data?.message || 'Gagal menghapus transaksi'
    })
  } finally {
    loadingId.value = null
  }
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

function formatRupiah(val) {
  if (!val) return 'Rp 0'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script> 