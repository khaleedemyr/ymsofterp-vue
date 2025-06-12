<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-2">
      <h1 class="text-3xl font-extrabold mb-6 flex items-center gap-2 text-blue-700 drop-shadow-sm">
        <i class="fa-solid fa-truck text-blue-500"></i> Detail Good Receive Outlet
      </h1>
      <div class="mb-4 bg-white rounded-xl shadow p-6 flex flex-col gap-2 border border-blue-100">
        <div class="font-bold text-lg">Status: <span :class="statusClass(goodReceive?.status)">{{ goodReceive?.status || '-' }}</span></div>
        <div class="text-sm text-gray-500">Tanggal: {{ formatDate(goodReceive?.receive_date) }}</div>
        <div class="text-sm text-gray-500">Nomor GR: {{ goodReceive?.number || '-' }}</div>
        <div class="text-sm text-gray-500">Outlet: {{ goodReceive?.outlet_name || '-' }}</div>
        <div class="text-sm text-gray-500">Nomor DO: {{ goodReceive?.delivery_order_number || '-' }}</div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <div class="bg-white rounded-xl shadow p-6 border border-blue-100 transition-all hover:shadow-lg">
          <div class="font-bold mb-2 text-blue-700 flex items-center gap-2"><i class="fa-solid fa-list"></i> Floor Order</div>
          <div v-if="props.deliveryOrder?.floor_order_number"><b>No:</b> {{ props.deliveryOrder.floor_order_number }}</div>
          <div v-if="props.deliveryOrder?.floor_order_date"><b>Tanggal:</b> {{ props.deliveryOrder.floor_order_date }}</div>
          <div v-if="props.deliveryOrder?.floor_order_desc"><b>Keterangan:</b> {{ props.deliveryOrder.floor_order_desc }}</div>
          <div v-if="!props.deliveryOrder?.floor_order_number && !props.deliveryOrder?.floor_order_date && !props.deliveryOrder?.floor_order_desc">—</div>
        </div>
        <div class="bg-white rounded-xl shadow p-6 border border-blue-100 transition-all hover:shadow-lg">
          <div class="font-bold mb-2 text-blue-700 flex items-center gap-2"><i class="fa-solid fa-box"></i> Packing List</div>
          <div v-if="props.deliveryOrder?.packing_number"><b>No:</b> {{ props.deliveryOrder.packing_number }}</div>
          <div v-if="props.deliveryOrder?.packing_reason"><b>Alasan:</b> {{ props.deliveryOrder.packing_reason }}</div>
          <div v-if="!props.deliveryOrder?.packing_number && !props.deliveryOrder?.packing_reason">—</div>
        </div>
      </div>
      <div class="bg-white rounded-xl shadow p-6 mb-4 border border-blue-100 transition-all hover:shadow-lg">
        <div class="font-bold mb-2 text-blue-700 flex items-center gap-2"><i class="fa-solid fa-list-check"></i> List Item DO</div>
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
            <tr v-for="item in (goodReceive?.items || details)" :key="item.id">
              <td>{{ item.item_name || item.item?.name || '-' }}</td>
              <td>{{ item.unit_name || item.unit?.name || '-' }}</td>
              <td class="text-right">{{ item.qty }}</td>
              <td class="text-right">{{ item.received_qty }}</td>
            </tr>
          </tbody>
        </table>
      </div>
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
  goodReceive: {
    type: Object,
    default: () => ({})
  },
  details: {
    type: Array,
    default: () => []
  },
  deliveryOrder: {
    type: Object,
    default: () => null
  }
})

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID')
}
function statusClass(status) {
  if (status === 'draft') return 'text-yellow-700 font-bold';
  if (status === 'completed') return 'text-blue-700 font-bold';
  if (status === 'stocked') return 'text-green-700 font-bold';
  return 'text-gray-700';
}
const allScanned = computed(() => {
  const items = props.goodReceive?.items || props.details || [];
  return items.length && items.every(i => Number(i.received_qty) >= Number(i.qty))
})
function goBack() {
  router.visit(route('outlet-food-good-receives.index'))
}
async function processStock() {
  try {
    const res = await axios.post(`/outlet-food-good-receives/${props.goodReceive?.id}` + '/process-stock')
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