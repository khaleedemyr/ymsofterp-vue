<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8">
      <h1 class="text-2xl font-bold text-blue-700 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-money-bill-wave"></i>
        {{ isEditing ? 'Edit Payment Supplier' : 'Buat Payment Supplier Baru' }}
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
            <label class="block text-sm font-semibold text-gray-700 mb-2">No. GR Supplier</label>
            <select v-model="form.gr_id" class="w-full border border-blue-200 rounded-lg px-4 py-3 focus:ring-blue-500 focus:border-blue-500 shadow-sm" required>
              <option value="">Pilih GR Supplier</option>
              <option v-for="gr in grListFiltered" :key="gr.id" :value="gr.id">
                {{ gr.gr_number || '-' }}<span v-if="gr.total_amount !== undefined && gr.total_amount !== null"> - {{ formatCurrency(gr.total_amount) }}</span>
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
        <div v-if="selectedGR" class="grid grid-cols-1 md:grid-cols-2 gap-6 my-6">
          <!-- Card GR Supplier -->
          <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-2xl shadow-xl border border-blue-200 p-5 transition-all duration-300 hover:scale-105">
            <h3 class="font-bold mb-2 text-blue-700 flex items-center gap-2"><i class="fa-solid fa-file-invoice"></i> Info GR Supplier</h3>
            <div class="text-sm">No: <span class="font-semibold">{{ selectedGR.gr_number }}</span></div>
            <div class="text-sm">Tanggal: <span class="font-semibold">{{ formatDate(selectedGR.receive_date) }}</span></div>
            <div class="text-sm">Outlet: <span class="font-semibold">{{ selectedGR.outlet_name }}</span></div>
            <div class="text-sm">Supplier: <span class="font-semibold text-purple-700">{{ selectedGR.supplier_name || '-' }}</span></div>
            <div class="text-sm">Status: <span class="font-semibold">{{ selectedGR.status }}</span></div>
            <div class="text-sm">Amount: <span class="font-bold text-blue-700">{{ formatCurrency(selectedGR.total_amount) }}</span></div>
          </div>
          <!-- Card RO Supplier -->
          <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-2xl shadow-xl border border-purple-200 p-5 transition-all duration-300 hover:scale-105">
            <h3 class="font-bold mb-2 text-purple-700 flex items-center gap-2"><i class="fa-solid fa-clipboard-list"></i> Info RO Supplier</h3>
            <div class="text-sm">No: <span class="font-semibold">{{ selectedGR.ro_number }}</span></div>
            <div class="text-sm">Tanggal: <span class="font-semibold">{{ formatDate(selectedGR.ro_created_at) }}</span></div>
          </div>
        </div>
        <div v-if="selectedGR && grItems.length" class="mt-6">
          <h3 class="font-bold mb-2 text-blue-700 flex items-center gap-2">
            <i class="fa-solid fa-list"></i> Item di GR
          </h3>
          <table class="w-full border border-gray-200 rounded-xl shadow">
            <thead>
              <tr class="bg-blue-50 text-blue-900">
                <th class="px-3 py-2 border">Nama Item</th>
                <th class="px-3 py-2 border text-right">Qty</th>
                <th class="px-3 py-2 border">Unit</th>
                <th class="px-3 py-2 border text-right">Harga</th>
                <th class="px-3 py-2 border text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in paginatedItems" :key="item.id">
                <td class="px-3 py-2 border">{{ item.item_name }}</td>
                <td class="px-3 py-2 border text-right">{{ formatQty(item.qty_received) }}</td>
                <td class="px-3 py-2 border">{{ item.unit_name }}</td>
                <td class="px-3 py-2 border text-right">{{ formatCurrency(item.price) }}</td>
                <td class="px-3 py-2 border text-right">{{ formatCurrency(item.qty_received * item.price) }}</td>
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
watch(() => form.value.gr_id, (newVal) => {
  const gr = grListFiltered.value.find(g => g.id == newVal);
  form.value.total_amount = gr && gr.total_amount ? gr.total_amount : 0;
});
const grItems = computed(() => selectedGR.value?.items || []);
const itemsPage = ref(1);
const itemsPerPage = 5;
const paginatedItems = computed(() => {
  const start = (itemsPage.value - 1) * itemsPerPage;
  return grItems.value.slice(start, start + itemsPerPage);
});
const totalItemsPages = computed(() => Math.ceil(grItems.value.length / itemsPerPage));
function goBack() {
  router.get('/outlet-payment-suppliers');
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
  if (!form.value.outlet_id || !form.value.gr_id || !form.value.date || !form.value.total_amount) {
    alert('Lengkapi semua data!');
    return;
  }
  const Swal = (await import('sweetalert2')).default;
  const confirm = await Swal.fire({
    title: 'Simpan Payment Supplier?',
    text: 'Yakin ingin menyimpan data payment supplier ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Simpan',
    cancelButtonText: 'Batal',
  });
  if (!confirm.isConfirmed) return;
  Swal.fire({
    title: 'Menyimpan...',
    allowOutsideClick: false,
    didOpen: () => { Swal.showLoading(); }
  });
  router.post('/outlet-payment-suppliers', form.value, {
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Payment supplier berhasil disimpan', timer: 1500, showConfirmButton: false });
      goBack();
    },
    onError: (err) => {
      let msg = 'Gagal menyimpan data';
      if (err && err.response && err.response.data && err.response.data.message) {
        msg = err.response.data.message;
      } else if (err && err.message) {
        msg = err.message;
      }
      Swal.fire({ icon: 'error', title: 'Gagal', text: msg });
    }
  });
}
</script> 