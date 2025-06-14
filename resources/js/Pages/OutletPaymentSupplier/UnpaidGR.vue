<template>
  <AppLayout>
    <div class="w-full py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa fa-clock text-red-500"></i> GR Supplier Belum Dibayar
        </h1>
      </div>
      <div class="flex flex-wrap gap-2 mb-4 items-center">
        <input v-model="grSearch" @input="onGrFilterChange" type="text" placeholder="Cari outlet atau nomor GR Supplier..." class="px-3 py-2 rounded border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition w-48" />
        <input v-model="grFrom" @change="onGrFilterChange" type="date" class="px-2 py-2 rounded border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Dari tanggal" />
        <span>-</span>
        <input v-model="grTo" @change="onGrFilterChange" type="date" class="px-2 py-2 rounded border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Sampai tanggal" />
      </div>
      <div v-if="grGroups && grGroups.data && grGroups.data.length">
        <div v-for="group in grGroups.data" :key="group.outlet_name + group.date + group.supplier_name" class="mb-6">
          <div class="font-semibold text-blue-700 mb-1">
            Outlet: {{ group.outlet_name }} | Supplier: <span class="text-purple-700">{{ group.supplier_name || '-' }}</span> | Tanggal: {{ formatDate(group.date) }}
          </div>
          <table class="w-full border border-gray-200 rounded-xl shadow mb-2">
            <thead>
              <tr class="bg-red-50 text-red-900">
                <th class="px-3 py-2 border">No. GR Supplier</th>
                <th class="px-3 py-2 border">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="gr in group.items" :key="gr.id">
                <td class="px-3 py-2 border font-mono">{{ gr.gr_number }}</td>
                <td class="px-3 py-2 border text-right">{{ formatCurrency(gr.total_amount) }}</td>
              </tr>
              <tr class="bg-yellow-50 font-bold">
                <td class="px-3 py-2 border text-right">Subtotal</td>
                <td class="px-3 py-2 border text-right">{{ formatCurrency(group.subtotal) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex justify-end gap-2 mt-2">
          <button @click="goToGrPage(grGroups.prev_page_url)" :disabled="!grGroups.prev_page_url" class="px-3 py-1 rounded border" :class="!grGroups.prev_page_url ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">&laquo; Previous</button>
          <span>Halaman {{ grGroups.current_page }} / {{ grGroups.last_page }}</span>
          <button @click="goToGrPage(grGroups.next_page_url)" :disabled="!grGroups.next_page_url" class="px-3 py-1 rounded border" :class="!grGroups.next_page_url ? 'bg-gray-200 text-gray-400' : 'bg-white text-blue-700 hover:bg-blue-50'">Next &raquo;</button>
        </div>
      </div>
      <div v-else>
        <div class="text-center text-gray-400 py-4">Tidak ada GR Supplier yang belum dibayar.</div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
const props = defineProps({
  grGroups: Object
});
const grSearch = ref('');
const grFrom = ref('');
const grTo = ref('');
if (typeof window !== 'undefined') {
  const url = new URL(window.location.href);
  grSearch.value = url.searchParams.get('gr_search') || '';
  grFrom.value = url.searchParams.get('gr_from') || '';
  grTo.value = url.searchParams.get('gr_to') || '';
}
function onGrFilterChange() {
  router.get(window.location.pathname, {
    gr_search: grSearch.value,
    gr_from: grFrom.value,
    gr_to: grTo.value,
    gr_page: 1
  }, { preserveState: true, replace: true });
}
function goToGrPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}
function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}
function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}
</script> 