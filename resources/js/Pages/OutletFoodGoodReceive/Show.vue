<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-2">
      <h1 class="text-2xl font-bold mb-6 flex items-center gap-2 text-blue-700">
        <i class="fa-solid fa-truck text-blue-500"></i> Detail Good Receive Outlet
      </h1>
      <div class="mb-4">
        <div class="font-bold">Status: <span :class="statusClass(goodReceive.status)">{{ goodReceive.status }}</span></div>
        <div class="text-sm text-gray-500">Tanggal: {{ formatDate(goodReceive.receive_date) }}</div>
        <div class="text-sm text-gray-500">Nomor GR: {{ goodReceive.number }}</div>
        <div class="text-sm text-gray-500">Outlet: {{ goodReceive.outlet?.name || '-' }}</div>
        <div class="text-sm text-gray-500">Nomor DO: {{ goodReceive.deliveryOrder?.number || '-' }}</div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div class="bg-white rounded shadow p-4">
          <div class="font-bold mb-2">Floor Order</div>
          <div v-if="goodReceive.deliveryOrder?.floor_order?.order_number">No: {{ goodReceive.deliveryOrder.floor_order.order_number }}</div>
          <div v-if="goodReceive.deliveryOrder?.floor_order?.tanggal">Tanggal: {{ goodReceive.deliveryOrder.floor_order.tanggal }}</div>
          <div v-if="goodReceive.deliveryOrder?.floor_order?.description">Keterangan: {{ goodReceive.deliveryOrder.floor_order.description }}</div>
        </div>
        <div class="bg-white rounded shadow p-4">
          <div class="font-bold mb-2">Packing List</div>
          <div v-if="goodReceive.deliveryOrder?.packing_list?.packing_number">No: {{ goodReceive.deliveryOrder.packing_list.packing_number }}</div>
          <div v-if="goodReceive.deliveryOrder?.packing_list?.reason">Alasan: {{ goodReceive.deliveryOrder.packing_list.reason }}</div>
        </div>
      </div>
      <div class="bg-white rounded shadow p-4 mb-4">
        <div class="font-bold mb-2">List Item DO</div>
        <table class="min-w-full text-sm">
          <thead>
            <tr>
              <th class="text-left">Item</th>
              <th class="text-left">Satuan</th>
              <th class="text-right">Qty DO</th>
              <th class="text-right">Qty Scan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in goodReceive.items" :key="item.id">
              <td>{{ item.item?.name || '-' }}</td>
              <td>{{ item.unit?.name || '-' }}</td>
              <td class="text-right">{{ item.qty }}</td>
              <td class="text-right">{{ item.received_qty }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <button v-if="goodReceive.status === 'done' && allScanned" @click="processStock" class="btn bg-gradient-to-r from-green-500 to-green-700 text-white px-8 py-2 rounded-lg font-bold shadow hover:shadow-xl transition-all mb-4">
        Proses ke Stok
      </button>
      <button @click="goBack" class="btn btn-ghost px-6 py-2 rounded-lg">Kembali</button>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { computed } from 'vue'
import Swal from 'sweetalert2'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  goodReceive: Object
})

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID')
}
function statusClass(status) {
  if (status === 'draft') return 'text-yellow-700 font-bold';
  if (status === 'done') return 'text-blue-700 font-bold';
  if (status === 'stocked') return 'text-green-700 font-bold';
  return 'text-gray-700';
}
const allScanned = computed(() => {
  return props.goodReceive.items && props.goodReceive.items.length && props.goodReceive.items.every(i => Number(i.received_qty) >= Number(i.qty))
})
function goBack() {
  router.visit(route('outlet-food-good-receives.index'))
}
async function processStock() {
  try {
    const res = await axios.post(`/outlet-food-good-receives/${props.goodReceive.id}/process-stock`)
    if (res.data && res.data.success) {
      Swal.fire('Berhasil', 'Stok outlet sudah diupdate!', 'success')
      setTimeout(() => window.location.reload(), 1200)
    } else {
      throw new Error(res.data.message || 'Gagal proses stok')
    }
  } catch (e) {
    Swal.fire('Gagal', e.response?.data?.message || e.message || 'Gagal proses stok', 'error')
  }
}
</script> 