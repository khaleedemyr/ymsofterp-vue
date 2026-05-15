<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">{{ props.return.return_number }}</h1>
        <button @click="goBack" class="bg-gray-500 text-white px-4 py-2 rounded-xl">Kembali</button>
      </div>

      <div class="bg-white rounded-xl shadow p-6 mb-6 grid md:grid-cols-2 gap-4 text-sm">
        <div><span class="text-gray-500">Tanggal</span><p class="font-medium">{{ formatDate(props.return.return_date) }}</p></div>
        <div><span class="text-gray-500">Status</span><p class="font-medium capitalize">{{ props.return.status }}</p></div>
        <div><span class="text-gray-500">Outlet</span><p class="font-medium">{{ props.return.nama_outlet }}</p></div>
        <div><span class="text-gray-500">Warehouse Outlet</span><p class="font-medium">{{ props.return.warehouse_outlet_name }}</p></div>
        <div><span class="text-gray-500">Good Receive</span><p class="font-medium">{{ props.return.gr_number }}</p></div>
        <div v-if="props.return.return_mode && props.return.return_mode !== 'normal'">
          <span class="text-gray-500">Mode</span>
          <p class="font-medium">{{ props.return.return_mode === 'serial' ? 'Serial' : 'Campuran' }}</p>
        </div>
        <div v-if="props.return.notes" class="md:col-span-2"><span class="text-gray-500">Catatan</span><p>{{ props.return.notes }}</p></div>
      </div>

      <div v-if="serialItems.length" class="bg-orange-50 rounded-xl shadow p-6 mb-6">
        <h3 class="font-semibold mb-3"><i class="fa fa-qrcode mr-1"></i> Nomor Seri</h3>
        <table class="min-w-full text-sm">
          <thead><tr class="text-left text-gray-600"><th class="py-2">Serial</th><th>Item</th><th>Qty</th></tr></thead>
          <tbody>
            <tr v-for="s in serialItems" :key="s.id" class="border-t border-orange-100">
              <td class="py-2 font-mono">{{ s.serial_number }}</td>
              <td>{{ s.item_name }}</td>
              <td>{{ s.return_qty }} {{ s.unit_name }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="qtyItems.length" class="bg-white rounded-xl shadow p-6">
        <h3 class="font-semibold mb-3">Item (Qty)</h3>
        <table class="min-w-full text-sm">
          <thead><tr class="text-left text-gray-600"><th class="py-2">Item</th><th>Qty Return</th><th>Unit</th></tr></thead>
          <tbody>
            <tr v-for="it in qtyItems" :key="it.id" class="border-t">
              <td class="py-2">{{ it.item_name }}</td>
              <td>{{ it.return_qty }}</td>
              <td>{{ it.unit_name }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="props.return.status === 'pending'" class="mt-6">
        <button @click="approve" :disabled="approving" class="bg-green-600 text-white px-6 py-2 rounded-lg">
          {{ approving ? 'Memproses...' : 'Approve Return' }}
        </button>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({ return: Object })
const approving = ref(false)

const serialItems = computed(() => props.return?.serialItems || [])
const qtyItems = computed(() => props.return?.items || [])

function goBack() { router.visit('/outlet-food-return') }

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleDateString('id-ID')
}

async function approve() {
  const r = await Swal.fire({ title: 'Approve return?', icon: 'question', showCancelButton: true })
  if (!r.isConfirmed) return
  approving.value = true
  try {
    const { data } = await axios.post(`/outlet-food-return/${props.return.id}/approve`)
    if (data.success) {
      Swal.fire('Berhasil', data.message, 'success').then(() => router.reload())
    } else {
      Swal.fire('Error', data.message, 'error')
    }
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message || 'Gagal approve', 'error')
  } finally {
    approving.value = false
  }
}
</script>

