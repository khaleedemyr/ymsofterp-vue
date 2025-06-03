<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-truck text-blue-500"></i> Outlet Good Receive
        </h1>
        <button @click="showModal = true" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat GR Baru
        </button>
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">No</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No DO</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(gr, idx) in goodReceives" :key="gr.id" class="hover:bg-blue-50">
              <td class="px-6 py-3">{{ idx+1 }}</td>
              <td class="px-6 py-3">{{ formatDate(gr.receive_date) }}</td>
              <td class="px-6 py-3">{{ gr.outlet_name }}</td>
              <td class="px-6 py-3">{{ gr.delivery_order_number }}</td>
              <td class="px-6 py-3">
                <span :class="statusClass(gr.status)">{{ gr.status }}</span>
              </td>
              <td class="px-6 py-3">
                <Link :href="route('outlet-food-good-receives.show', gr.id)" class="text-blue-600 hover:underline">Detail</Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Modal Scan/Input DO -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-8 relative animate-fade-in">
          <div class="font-bold text-xl mb-4 text-blue-700 flex items-center gap-2"><i class="fa-solid fa-qrcode"></i> Scan/Input Delivery Order</div>
          <button @click="showModal = false" class="absolute top-4 right-4 text-gray-400 hover:text-red-500">
            <i class="fa-solid fa-xmark text-2xl"></i>
          </button>
          <div class="mb-4">
            <label class="block text-sm font-semibold mb-1">Nomor DO</label>
            <input v-model="doNumber" type="text" class="w-full border rounded-lg px-3 py-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Scan atau input nomor DO..." />
          </div>
          <div v-if="modalError" class="text-red-600 font-semibold mb-2">{{ modalError }}</div>
          <div class="flex justify-end gap-2">
            <button @click="showModal = false" class="px-4 py-2 rounded-md bg-gray-200 text-gray-700 font-semibold hover:bg-gray-300">Batal</button>
            <button @click="submitDO" :disabled="loadingCreate" class="px-4 py-2 rounded-md bg-blue-600 text-white font-semibold hover:bg-blue-700 flex items-center">
              <span v-if="loadingCreate" class="animate-spin mr-2"><i class="fas fa-spinner"></i></span>
              Lanjutkan
            </button>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';

const props = defineProps({
  goodReceives: Array
});

const showModal = ref(false);
const doNumber = ref('');
const modalError = ref('');
const loadingCreate = ref(false);

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function statusClass(status) {
  if (status === 'draft') return 'text-yellow-700 font-bold';
  if (status === 'done') return 'text-green-700 font-bold';
  return 'text-gray-700';
}

async function submitDO() {
  modalError.value = '';
  loadingCreate.value = true;
  try {
    const res = await fetch(`/api/delivery-orders/validate?number=${encodeURIComponent(doNumber.value)}`);
    if (!res.ok) {
      modalError.value = 'Terjadi kesalahan server.';
      loadingCreate.value = false;
      return;
    }
    const data = await res.json();
    if (data.success && data.delivery_order_id) {
      showModal.value = false;
      router.visit(`/outlet-food-good-receives/create-from-do/${data.delivery_order_id}`);
    } else {
      modalError.value = data.message || 'Delivery Order tidak ditemukan.';
    }
  } catch (e) {
    modalError.value = 'Terjadi kesalahan server.';
  } finally {
    loadingCreate.value = false;
  }
}
</script>

<style scoped>
@keyframes fade-in {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 0.5s;
}
</style> 