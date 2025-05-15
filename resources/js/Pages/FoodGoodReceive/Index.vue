<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-truck"></i> Good Receive
        </h1>
        <button @click="showForm = true" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          + Buat Good Receive
        </button>
      </div>
      <div class="flex flex-wrap gap-3 mb-4 items-center">
        <input
          v-model="search"
          @input="onSearchInput"
          type="text"
          placeholder="Cari PO, Supplier, atau Petugas..."
          class="w-64 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <input type="date" v-model="from" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Dari tanggal" />
        <span>-</span>
        <input type="date" v-model="to" @change="onDateChange" class="px-2 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition" placeholder="Sampai tanggal" />
      </div>
      <div class="bg-white rounded-2xl shadow-2xl overflow-x-auto transition-all">
        <table class="w-full min-w-full divide-y divide-gray-200">
          <thead class="bg-gradient-to-r from-blue-50 to-blue-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tl-2xl">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">No. PO</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Supplier</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider">Petugas</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-blue-700 uppercase tracking-wider rounded-tr-2xl">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!goodReceives.data || !goodReceives.data.length">
              <td colspan="5" class="text-center py-10 text-gray-400">Belum ada data Good Receive.</td>
            </tr>
            <tr v-for="gr in goodReceives.data" :key="gr.id" class="hover:bg-blue-50 transition shadow-sm">
              <td class="px-6 py-3">{{ gr.receive_date }}</td>
              <td class="px-6 py-3">{{ gr.po_number }}</td>
              <td class="px-6 py-3">{{ gr.supplier_name }}</td>
              <td class="px-6 py-3">{{ gr.received_by_name }}</td>
              <td class="px-6 py-3">
                <div class="flex gap-2">
                  <button @click="openDetail(gr.id)" class="inline-flex items-center btn btn-xs bg-blue-100 text-blue-800 hover:bg-blue-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa-solid fa-eye mr-1"></i> Detail
                  </button>
                  <button @click="openEdit(gr.id)" class="inline-flex items-center btn btn-xs bg-yellow-100 text-yellow-800 hover:bg-yellow-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                  </button>
                  <button @click="hapus(gr.id)" class="inline-flex items-center btn btn-xs bg-red-100 text-red-700 hover:bg-red-200 rounded px-2 py-1 font-semibold transition">
                    <i class="fa-solid fa-trash mr-1"></i> Hapus
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <div class="flex justify-end mt-4 gap-2">
        <button
          v-for="link in goodReceives.links"
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
      <FormGoodReceive v-if="showForm" @close="showForm = false" />
      <ModalDetailGoodReceive :show="showDetailModal" :gr="detailGR" @close="closeDetailModal" />
      <ModalEditGoodReceive
        :show="showEditModal"
        :gr="editGR"
        @close="closeEditModal"
        @success="handleEditSuccess"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import AppLayout from '@/Layouts/AppLayout.vue';
import FormGoodReceive from './Form.vue';
import ModalDetailGoodReceive from './ModalDetailGoodReceive.vue';
import ModalEditGoodReceive from './ModalEditGoodReceive.vue';

const props = defineProps({
  goodReceives: Object,
  filters: Object
});

const showForm = ref(false);
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');
const search = ref(props.filters?.search || '');
const showDetailModal = ref(false);
const showEditModal = ref(false);
const detailGR = ref(null);
const editGR = ref(null);

const debouncedSearch = debounce(() => {
  router.get('/food-good-receive', { search: search.value, from: from.value, to: to.value }, { preserveState: true, replace: true });
}, 400);

function onDateChange() {
  debouncedSearch();
}
function onSearchInput() {
  debouncedSearch();
}
async function openDetail(id) {
  try {
    const res = await fetch(`/food-good-receive/${id}`);
    if (!res.ok) throw new Error('Gagal fetch detail');
    detailGR.value = await res.json();
    showDetailModal.value = true;
  } catch (e) {
    alert('Gagal mengambil detail Good Receive');
  }
}
async function openEdit(id) {
  try {
    const res = await fetch(`/food-good-receive/${id}`);
    if (!res.ok) throw new Error('Gagal fetch detail');
    editGR.value = await res.json();
    showEditModal.value = true;
  } catch (e) {
    alert('Gagal mengambil detail Good Receive');
  }
}
function hapus(id) {
  // Implement hapus logic here
}
function closeDetailModal() {
  showDetailModal.value = false;
  detailGR.value = null;
}
function closeEditModal() {
  showEditModal.value = false;
  editGR.value = null;
}
function handleEditSuccess() {
  showEditModal.value = false;
  editGR.value = null;
  // Reload data dengan filter yang sama
  router.get('/food-good-receive', { search: search.value, from: from.value, to: to.value }, { preserveState: true, replace: true });
}
function goToPage(url) {
  if (url) {
    router.get(url, { search: search.value, from: from.value, to: to.value }, { preserveState: true, replace: true });
  }
}
</script> 