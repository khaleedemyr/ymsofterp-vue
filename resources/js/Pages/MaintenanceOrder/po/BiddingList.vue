<template>
  <teleport to="body">
    <div class="fixed inset-0 z-[110000] flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-3xl p-6 relative">
        <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <div class="flex justify-between items-center mb-4">
          <h1 class="text-2xl font-bold">Daftar Bidding</h1>
          <button @click="$emit('input-bidding')" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
            Input Hasil Bidding
          </button>
        </div>
        <div v-if="loading" class="py-8 text-center text-gray-500">Loading...</div>
        <div v-else>
          <div v-if="Object.keys(groupedOffers).length" class="max-h-[60vh] overflow-y-auto">
            <div v-for="(items, supplierId) in groupedOffers" :key="supplierId" class="mb-4 border rounded-lg p-4">
              <div class="flex items-center justify-between mb-2">
                <div class="font-bold text-blue-700 text-lg flex items-center gap-2">
                  <i class="fas fa-user-tie"></i> {{ items[0].supplier_name }}
                </div>
                <div v-if="items[0].file_path">
                  <a :href="'/storage/' + items[0].file_path" target="_blank" class="text-blue-600 underline flex items-center gap-1">
                    <i class="fas fa-paperclip"></i> File Penawaran
                  </a>
                </div>
              </div>
              <table class="w-full text-sm">
                <thead>
                  <tr>
                    <th class="text-left">Item</th>
                    <th class="text-right">Qty</th>
                    <th class="text-left">Unit</th>
                    <th class="text-right">Harga Penawaran</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in items" :key="item.item_id">
                    <td>{{ item.item_name }}</td>
                    <td class="text-right">{{ item.quantity }}</td>
                    <td>{{ item.unit_name || '-' }}</td>
                    <td :class="isCheapest(item) ? 'text-green-600 font-bold' : ''" class="text-right">{{ formatCurrency(item.price) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <h2 class="font-bold mt-6 mb-2">Rekap Penawaran Termurah</h2>
            <table class="w-full text-sm mb-2">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Qty</th>
                  <th>Unit</th>
                  <th>Supplier</th>
                  <th>Harga Termurah</th>
                  <th>File</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="offer in cheapestOffers" :key="offer.item_id">
                  <td>{{ offer.item_name }}</td>
                  <td>{{ offer.quantity }}</td>
                  <td>{{ offer.unit_name }}</td>
                  <td>{{ offer.supplier_name }}</td>
                  <td class="text-green-600 font-bold">{{ formatCurrency(offer.price) }}</td>
                  <td>
                    <a v-if="offer.file_path" :href="'/storage/' + offer.file_path" target="_blank" class="text-blue-600 underline">File</a>
                  </td>
                </tr>
              </tbody>
            </table>
            <button @click="createPOFromBidding" class="mt-4 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
              Lanjutkan ke PO
            </button>
          </div>
          <div v-else class="text-center text-gray-500 py-8">Belum ada penawaran bidding.</div>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const props = defineProps({
  show: Boolean,
  taskId: [String, Number],
});

const offers = ref([]);
const loading = ref(false);

onMounted(fetchOffers);
watch(() => props.show, (val) => { if (val) fetchOffers(); });

async function fetchOffers() {
  if (!props.taskId) return;
  loading.value = true;
  try {
    const res = await axios.get('/api/maintenance-tasks/bidding-offers', { params: { task_id: props.taskId } });
    offers.value = res.data;
  } catch (e) {
    offers.value = [];
  } finally {
    loading.value = false;
  }
}

const groupedOffers = computed(() => {
  const groups = {};
  for (const offer of offers.value) {
    if (!groups[offer.supplier_id]) groups[offer.supplier_id] = [];
    groups[offer.supplier_id].push(offer);
  }
  return groups;
});

const cheapestOffers = computed(() => {
  const map = {};
  for (const offer of offers.value) {
    if (!map[offer.item_id] || offer.price < map[offer.item_id].price) {
      map[offer.item_id] = offer;
    }
  }
  return Object.values(map);
});

function isCheapest(item) {
  const cheapest = cheapestOffers.value.find(o => o.item_id === item.item_id);
  return cheapest && cheapest.supplier_id === item.supplier_id;
}

function formatCurrency(val) {
  if (!val) return '-';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}

async function createPOFromBidding() {
  try {
    await axios.post('/api/maintenance-tasks/create-po-from-bidding', {
      task_id: props.taskId,
      items: cheapestOffers.value.map(o => ({
        pr_item_id: o.item_id,
        supplier_id: o.supplier_id,
        price: o.price
      }))
    });
    Swal.fire('Sukses', 'Draft PO berhasil dibuat dari hasil bidding!', 'success');
    // TODO: redirect ke halaman PO atau tutup modal
  } catch (e) {
    Swal.fire('Gagal', 'Gagal membuat PO dari bidding!', 'error');
  }
}
</script> 