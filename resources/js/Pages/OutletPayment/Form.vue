<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8">
      <h1 class="text-2xl font-bold text-blue-700 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-money-bill-wave"></i>
        {{ isEditing ? 'Edit Payment' : 'Buat Payment Baru' }}
      </h1>
      <form @submit.prevent="submitForm">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Outlet</label>
            <select v-model="form.outlet_id" class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 shadow-sm" required>
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Tanggal</label>
            <input type="date" v-model="form.date" class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 shadow-sm" required />
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">No. GR</label>
            <select v-model="form.gr_id" class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 shadow-sm" required>
              <option value="">Pilih GR</option>
              <option v-for="gr in grListFiltered" :key="gr.id" :value="gr.id">
                {{ gr.number || gr.gr_number || '-' }}<span v-if="gr.total_amount !== undefined && gr.total_amount !== null"> - {{ formatCurrency(gr.total_amount) }}</span>
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Total Amount</label>
            <div class="w-full border border-blue-200 rounded-lg px-4 py-3 bg-gray-50 font-bold text-blue-700 text-lg select-none">
              {{ formatCurrency(form.total_amount) }}
            </div>
          </div>
        </div>
        <div v-if="selectedGR" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 my-6">
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl shadow-xl border border-blue-200 p-5 transition-all duration-300 hover:scale-105">
            <h3 class="font-bold mb-2 text-blue-700 flex items-center gap-2"><i class="fa-solid fa-file-invoice"></i> Info GR</h3>
            <div class="text-sm">No: <span class="font-semibold">{{ selectedGR.number }}</span></div>
            <div class="text-sm">Tanggal: <span class="font-semibold">{{ formatDate(selectedGR.receive_date) }}</span></div>
            <div class="text-sm">Outlet: <span class="font-semibold">{{ selectedGR.outlet?.name }}</span></div>
            <div class="text-sm">Status: <span class="font-semibold">{{ selectedGR.status }}</span></div>
            <div class="text-sm">Amount: <span class="font-bold text-blue-700">{{ formatCurrency(selectedGR.total_amount) }}</span></div>
            <div class="text-sm">User Input: <span class="font-semibold">{{ selectedGR.creator?.nama_lengkap || '-' }}</span></div>
          </div>
          <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-2xl shadow-xl border border-green-200 p-5 transition-all duration-300 hover:scale-105" v-if="selectedGR.delivery_order">
            <h3 class="font-bold mb-2 text-green-700 flex items-center gap-2"><i class="fa-solid fa-truck"></i> Info DO</h3>
            <div class="text-sm">No: <span class="font-semibold">{{ selectedGR.delivery_order.number }}</span></div>
            <div class="text-sm">Tanggal: <span class="font-semibold">{{ formatDate(selectedGR.delivery_order.created_at) }}</span></div>
            <div class="text-sm">User Input: <span class="font-semibold">{{ selectedGR.delivery_order.creator?.nama_lengkap || '-' }}</span></div>
          </div>
          <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-2xl shadow-xl border border-yellow-200 p-5 transition-all duration-300 hover:scale-105" v-if="selectedGR.packing_list">
            <h3 class="font-bold mb-2 text-yellow-700 flex items-center gap-2"><i class="fa-solid fa-box"></i> Info Packing List</h3>
            <div class="text-sm">No: <span class="font-semibold">{{ selectedGR.packing_list.packing_number }}</span></div>
            <div class="text-sm">Tanggal: <span class="font-semibold">{{ formatDate(selectedGR.packing_list.created_at) }}</span></div>
            <div class="text-sm">User Input: <span class="font-semibold">{{ selectedGR.packing_list.creator?.nama_lengkap || '-' }}</span></div>
          </div>
          <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl shadow-xl border border-purple-200 p-5 transition-all duration-300 hover:scale-105" v-if="selectedGR.floor_order">
            <h3 class="font-bold mb-2 text-purple-700 flex items-center gap-2"><i class="fa-solid fa-clipboard-list"></i> Info RO</h3>
            <div class="text-sm">No: <span class="font-semibold">{{ selectedGR.floor_order.order_number }}</span></div>
            <div class="text-sm">Tanggal: <span class="font-semibold">{{ formatDate(selectedGR.floor_order.tanggal) }}</span></div>
            <div class="text-sm">User Input: <span class="font-semibold">{{ selectedGR.floor_order.user?.nama_lengkap || '-' }}</span></div>
          </div>
        </div>
        <div v-if="selectedGR && grItems.length" class="mt-6">
          <h3 class="font-bold mb-2 text-blue-700 flex items-center gap-2">
            <i class="fa-solid fa-list"></i> Item di GR
          </h3>
          <table class="w-full border border-gray-200 rounded-xl shadow">
            <thead>
              <tr class="bg-blue-50 text-blue-900">
                <th class="px-3 py-2 border">Item</th>
                <th class="px-3 py-2 border">Unit</th>
                <th class="px-3 py-2 border text-right">Qty</th>
                <th class="px-3 py-2 border text-right">Price</th>
                <th class="px-3 py-2 border text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedItems" :key="item.id">
                <td class="px-3 py-2 border">{{ item.item_name }}</td>
                <td class="px-3 py-2 border">{{ item.unit }}</td>
                <td class="px-3 py-2 border text-right">{{ formatQty(item.received_qty) }}</td>
                <td class="px-3 py-2 border text-right">{{ formatCurrency(item.price) }}</td>
                <td class="px-3 py-2 border text-right">{{ formatCurrency(item.received_qty * item.price) }}</td>
              </tr>
            </tbody>
          </table>
          <div v-if="totalItemsPages > 1" class="flex gap-2 justify-end mt-2">
            <button @click="itemsPage--" :disabled="itemsPage === 1" class="px-3 py-1 rounded border" :class="itemsPage === 1 ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&lt;</button>
            <span>Halaman {{ itemsPage }} / {{ totalItemsPages }}</span>
            <button @click="itemsPage++" :disabled="itemsPage === totalItemsPages" class="px-3 py-1 rounded border" :class="itemsPage === totalItemsPages ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&gt;</button>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-8">
          <button type="button" @click="goBack" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-semibold shadow-sm">Batal</button>
          <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-bold shadow-lg hover:bg-blue-700 transition-all">{{ isEditing ? 'Update' : 'Simpan' }}</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
const props = defineProps({
  mode: { type: String, default: 'create' },
  payment: Object,
  outlets: Array,
  grList: Array,
});
const isEditing = computed(() => props.mode === 'edit');
const form = ref({
  outlet_id: '',
  date: new Date().toISOString().split('T')[0],
  gr_id: '',
  total_amount: 0
});
const grListFiltered = computed(() => {
  if (!form.value.outlet_id) return [];
  return props.grList.filter(gr => gr.outlet_id == form.value.outlet_id);
});
const selectedGR = computed(() => grListFiltered.value.find(gr => gr.id == form.value.gr_id));
const grItems = computed(() => selectedGR.value?.items || []);
const itemsPage = ref(1);
const itemsPerPage = 5;
const paginatedItems = computed(() => {
  const start = (itemsPage.value - 1) * itemsPerPage;
  return grItems.value.slice(start, start + itemsPerPage);
});
const totalItemsPages = computed(() => Math.ceil(grItems.value.length / itemsPerPage));
watch(() => form.value.gr_id, (newVal) => {
  const gr = grListFiltered.value.find(g => g.id == newVal);
  form.value.total_amount = gr && gr.total_amount ? gr.total_amount : 0;
});
function goBack() {
  router.get('/outlet-payments');
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
function formatQty(val) {
  if (val == null) return '';
  return Number(val).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
async function submitForm() {
  console.log('submitForm: dipanggil');
  const Swal = (await import('sweetalert2')).default;
  const confirm = await Swal.fire({
    title: isEditing.value ? 'Update Payment?' : 'Simpan Payment?',
    text: isEditing.value ? 'Yakin ingin update data payment ini?' : 'Yakin ingin menyimpan data payment ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: isEditing.value ? 'Update' : 'Simpan',
    cancelButtonText: 'Batal',
  });
  console.log('submitForm: hasil konfirmasi', confirm);
  if (!confirm.isConfirmed) {
    console.log('submitForm: dibatalkan oleh user');
    return;
  }
  Swal.fire({
    title: isEditing.value ? 'Mengupdate...' : 'Menyimpan...',
    allowOutsideClick: false,
    didOpen: () => { Swal.showLoading(); }
  });
  const onSuccess = () => {
    console.log('submitForm: sukses simpan');
    Swal.fire({ icon: 'success', title: 'Berhasil', text: isEditing.value ? 'Payment berhasil diupdate' : 'Payment berhasil disimpan', timer: 1500, showConfirmButton: false });
    goBack();
  };
  const onError = (err) => {
    let msg = 'Gagal menyimpan data';
    if (err && err.response && err.response.data && err.response.data.message) {
      msg = err.response.data.message;
    } else if (err && err.message) {
      msg = err.message;
    }
    console.log('submitForm: error simpan', err);
    Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
  };
  try {
    if (isEditing.value) {
      console.log('submitForm: mode edit, PUT ke backend', form.value);
      await router.put(`/outlet-payments/${form.value.id}`, form.value, { onSuccess, onError });
    } else {
      console.log('submitForm: mode create, POST ke backend', form.value);
      await router.post('/outlet-payments', form.value, { onSuccess, onError });
    }
  } catch (e) {
    onError(e);
  }
}
</script> 