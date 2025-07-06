<template>
  <div class="min-h-screen w-full bg-gray-50 p-0">
    <div class="w-full bg-white shadow-2xl rounded-2xl p-8">
      <h1 class="text-2xl font-bold mb-6 text-blue-800 flex items-center gap-2">
        <i class="fa-solid fa-cogs"></i> Item Engineering
      </h1>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8 items-end">
        <div>
          <label class="block text-sm font-medium mb-1">Outlet</label>
          <select v-model="filters.outlet" :disabled="!outletDropdownEnabled" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2">
            <option value="">Pilih Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.qr_code">{{ outlet.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tanggal From</label>
          <input type="date" v-model="filters.date_from" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Tanggal To</label>
          <input type="date" v-model="filters.date_to" class="block w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2" />
        </div>
        <div class="flex items-end h-full gap-2">
          <button @click="fetchReport" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg shadow hover:bg-blue-700 transition">Tampilkan</button>
          <button @click="exportExcel" class="w-full bg-green-600 text-white px-4 py-2 rounded-lg shadow hover:bg-green-700 transition">Export to Excel</button>
        </div>
      </div>
      <div v-if="loading" class="text-center py-10">
        <span class="text-gray-500">Loading...</span>
      </div>
      <div v-else>
        <div class="overflow-x-auto mb-8">
          <table class="min-w-full rounded-2xl overflow-hidden shadow-lg">
            <thead>
              <tr class="bg-[#2563eb] text-white font-bold text-base">
                <th class="px-6 py-3 text-left">No</th>
                <th class="px-6 py-3 text-left">Nama Item</th>
                <th class="px-6 py-3 text-right">Qty Terjual</th>
                <th class="px-6 py-3 text-right">Harga Jual</th>
                <th class="px-6 py-3 text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in items" :key="item.item_name" class="bg-white border-b last:border-b-0 hover:bg-blue-50 transition">
                <td class="px-6 py-3">{{ idx + 1 }}</td>
                <td class="px-6 py-3 font-semibold text-gray-800">{{ item.item_name }}</td>
                <td class="px-6 py-3 text-right font-semibold">{{ item.qty_terjual }}</td>
                <td class="px-6 py-3 text-right font-semibold">{{ formatCurrency(item.harga_jual) }}</td>
                <td class="px-6 py-3 text-right font-semibold">{{ formatCurrency(item.subtotal) }}</td>
              </tr>
              <tr v-if="items.length">
                <td colspan="4" class="px-6 py-3 text-right font-bold bg-blue-100">Grand Total</td>
                <td class="px-6 py-3 text-right font-bold bg-blue-100">{{ formatCurrency(grandTotal) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <!-- MODIFIER ENGINEERING TABLE -->
        <div class="overflow-x-auto mb-8">
          <h2 class="text-lg font-bold text-blue-800 mb-2 flex items-center gap-2"><i class="fa-solid fa-gears"></i> Modifier Engineering</h2>
          <table class="min-w-full rounded-2xl overflow-hidden shadow-lg">
            <thead>
              <tr class="bg-[#2563eb] text-white font-bold text-base">
                <th class="px-6 py-3 text-left">No</th>
                <th class="px-6 py-3 text-left">Nama Modifier</th>
                <th class="px-6 py-3 text-right">Qty Terjual</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(mod, idx) in modifiers" :key="mod.name" class="bg-white border-b last:border-b-0 hover:bg-blue-50 transition">
                <td class="px-6 py-3">{{ idx + 1 }}</td>
                <td class="px-6 py-3 font-semibold text-gray-800">{{ mod.name }}</td>
                <td class="px-6 py-3 text-right font-semibold">{{ mod.qty }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
defineOptions({ layout: AppLayout });
import { ref, reactive, onMounted, computed } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import * as XLSX from 'xlsx';

const filters = reactive({
  outlet: '',
  date_from: '',
  date_to: '',
});
const outlets = ref([]);
const items = ref([]);
const modifiers = ref([]);
const loading = ref(false);
const outletDropdownEnabled = ref(false);
const user = usePage().props.auth?.user || {};
const grand_total = ref(0);
const grandTotal = computed(() => grand_total.value);
function formatCurrency(val) {
  if (typeof val === 'number') return val.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  if (!val) return '-';
  const num = Number(val);
  if (!isNaN(num)) return num.toLocaleString('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
  return val;
}

const fetchOutlets = async () => {
  const res = await axios.get('/api/outlets/report');
  outlets.value = res.data.outlets || [];
};

const fetchMyOutletQr = async () => {
  const res = await axios.get('/api/my-outlet-qr');
  if (res.data.qr_code) {
    filters.outlet = res.data.qr_code;
  }
};

const fetchReport = async () => {
  loading.value = true;
  try {
    const res = await axios.get('/api/report/item-engineering', { params: filters });
    items.value = res.data.items || [];
    modifiers.value = res.data.modifiers || [];
    grand_total.value = res.data.grand_total || 0;
  } finally {
    loading.value = false;
  }
};

const exportExcel = () => {
  const params = {
    outlet: filters.outlet,
    date_from: filters.date_from,
    date_to: filters.date_to,
  };
  const query = Object.entries(params).map(([k, v]) => `${k}=${encodeURIComponent(v||'')}`).join('&');
  window.open(`/report/item-engineering/export?${query}`, '_blank');
};

onMounted(async () => {
  if (user.id_outlet == 1) {
    outletDropdownEnabled.value = true;
    await fetchOutlets();
  } else {
    outletDropdownEnabled.value = false;
    await fetchMyOutletQr();
  }
});
</script> 