<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-link text-blue-500"></i> Items Supplier
        </h1>
        <button @click="goCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Tambah Baru
        </button>
      </div>
      <div class="mb-4">
        <input v-model="search" type="text" placeholder="Cari supplier, item, atau outlet..." class="w-full px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" />
      </div>
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Supplier</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Item</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Unit</th>
                <th class="px-4 py-3 text-right text-xs font-bold text-blue-700 uppercase tracking-wider">Harga</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet</th>
                <th class="px-4 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="!props.data.data.length">
                <td colspan="6" class="text-center py-10 text-gray-400">Tidak ada data.</td>
              </tr>
              <tr v-for="row in props.data.data" :key="row.id" class="hover:bg-blue-50 transition shadow-sm">
                <td class="px-4 py-3 font-semibold">{{ row.supplier?.name || '-' }}</td>
                <td class="px-4 py-3">{{ row.item?.name || '-' }}</td>
                <td class="px-4 py-3">{{ row.unit?.name || '-' }}</td>
                <td class="px-4 py-3 text-right">{{ formatRupiah(row.price) }}</td>
                <td class="px-4 py-3">
                  <ul class="list-disc ml-4">
                    <li v-for="outlet in row.item_supplier_outlets" :key="outlet.id">
                      {{ outlet.outlet?.nama_outlet || '-' }}
                    </li>
                  </ul>
                </td>
                <td class="px-4 py-3">
                  <div class="flex gap-2">
                    <button @click="goEdit(row.id)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                      <i class="fa fa-pen mr-1"></i> Edit
                    </button>
                    <button @click="onDelete(row)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                      <i class="fa fa-trash mr-1"></i> Hapus
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in props.data.links"
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
import { ref, watch } from 'vue'
import { debounce } from 'lodash'

const props = defineProps({
  data: Object,
  filters: Object
})

const search = ref(props.filters?.search || '')
const debouncedSearch = debounce(() => {
  router.get('/item-supplier', { search: search.value }, { preserveState: true, replace: true })
}, 400)
watch(search, debouncedSearch)

function goCreate() {
  router.visit('/item-supplier/create')
}
function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true })
}
async function onDelete(row) {
  const result = await Swal.fire({
    title: 'Hapus Data?',
    text: `Yakin ingin menghapus data item supplier ini?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  })
  if (!result.isConfirmed) return
  try {
    const res = await axios.delete(`/item-supplier/${row.id}`)
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
      text: e.response?.data?.message || 'Gagal menghapus data'
    })
  }
}
function formatRupiah(val) {
  if (!val) return 'Rp 0'
  return 'Rp ' + Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function goEdit(id) {
  router.visit(`/item-supplier/${id}/edit`)
}
</script> 