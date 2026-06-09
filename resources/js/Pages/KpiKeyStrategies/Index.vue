<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import KpiKeyStrategyFormModal from './KpiKeyStrategyFormModal.vue';

const props = defineProps({
  strategies: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'A');
const showModal = ref(false);
const modalMode = ref('create');
const selectedRow = ref(null);

const debouncedSearch = debounce(() => {
  router.get(route('kpi-key-strategies.index'), { search: search.value, status: status.value }, { preserveState: true, replace: true });
}, 400);

watch(status, () => debouncedSearch());

function openCreate() {
  modalMode.value = 'create';
  selectedRow.value = null;
  showModal.value = true;
}

function openEdit(row) {
  modalMode.value = 'edit';
  selectedRow.value = row;
  showModal.value = true;
}

async function hapus(row) {
  const result = await Swal.fire({ title: 'Nonaktifkan?', text: row.name, icon: 'warning', showCancelButton: true });
  if (!result.isConfirmed) return;
  router.delete(route('kpi-key-strategies.destroy', row.id));
}

async function toggleStatus(row) {
  const response = await axios.patch(route('kpi-key-strategies.toggle-status', row.id));
  if (response.data.success) router.reload({ preserveState: true });
}
</script>

<template>
  <AppLayout title="KPI Key Strategy">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold flex items-center gap-2">
            <i class="fa-solid fa-layer-group text-violet-600"></i>
            KPI Key Strategy
          </h1>
          <p class="text-sm text-gray-600 mt-1">Master kategori Key Strategy — reusable di template KPI.</p>
        </div>
        <button type="button" class="bg-violet-600 text-white px-4 py-2 rounded-xl font-semibold" @click="openCreate">+ Tambah</button>
      </div>

      <div class="flex gap-3 mb-4">
        <input v-model="search" type="text" placeholder="Cari..." class="px-4 py-2 rounded-xl border max-w-md" @input="debouncedSearch" />
        <select v-model="status" class="px-3 py-2 rounded-xl border">
          <option value="A">Aktif</option>
          <option value="N">Nonaktif</option>
          <option value="all">Semua</option>
        </select>
      </div>

      <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-violet-700 text-white">
            <tr>
              <th class="px-4 py-3 text-left">Kode</th>
              <th class="px-4 py-3 text-left">Nama</th>
              <th class="px-4 py-3 text-left">Deskripsi</th>
              <th class="px-4 py-3 text-center">Order</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-left">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="row in strategies.data" :key="row.id" class="hover:bg-violet-50/40">
              <td class="px-4 py-3 font-mono">{{ row.code }}</td>
              <td class="px-4 py-3 font-medium">{{ row.name }}</td>
              <td class="px-4 py-3 text-gray-600">{{ row.description || '—' }}</td>
              <td class="px-4 py-3 text-center">{{ row.sort_order }}</td>
              <td class="px-4 py-3 text-center">
                <button class="text-xs px-2 py-1 rounded-full" :class="row.status === 'A' ? 'bg-green-100' : 'bg-gray-100'" @click="toggleStatus(row)">
                  {{ row.status === 'A' ? 'Aktif' : 'Nonaktif' }}
                </button>
              </td>
              <td class="px-4 py-3">
                <button class="text-violet-600 mr-2" @click="openEdit(row)"><i class="fa-solid fa-pen"></i></button>
                <button class="text-red-600" @click="hapus(row)"><i class="fa-solid fa-trash"></i></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <KpiKeyStrategyFormModal :show="showModal" :mode="modalMode" :row="selectedRow" @close="showModal = false" @success="() => router.reload()" />
  </AppLayout>
</template>
