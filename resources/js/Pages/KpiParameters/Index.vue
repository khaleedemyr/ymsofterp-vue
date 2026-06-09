<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import KpiParameterFormModal from './KpiParameterFormModal.vue';

const props = defineProps({
  parameters: Object,
  filters: Object,
  options: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'A');
const sourceType = ref(props.filters?.source_type || '');
const showModal = ref(false);
const modalMode = ref('create');
const selectedRow = ref(null);

const debouncedSearch = debounce(() => {
  router.get(route('kpi-parameters.index'), {
    search: search.value,
    status: status.value,
    source_type: sourceType.value || undefined,
  }, { preserveState: true, replace: true });
}, 400);

watch([status, sourceType], () => debouncedSearch());

function sourceLabel(v) {
  return props.options?.source_types?.find((o) => o.value === v)?.label || v;
}

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
  const result = await Swal.fire({
    title: 'Nonaktifkan parameter?',
    text: `"${row.name}" akan dinonaktifkan.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  router.delete(route('kpi-parameters.destroy', row.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Parameter dinonaktifkan.', 'success'),
  });
}

async function toggleStatus(row) {
  try {
    const response = await axios.patch(route('kpi-parameters.toggle-status', row.id));
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      router.reload({ preserveState: true });
    }
  } catch {
    Swal.fire('Error', 'Gagal mengubah status.', 'error');
  }
}

function reload() {
  router.reload({ preserveState: true });
}
</script>

<template>
  <AppLayout title="KPI Parameter">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-sliders text-indigo-600"></i>
            KPI Parameter Catalog
          </h1>
          <p class="text-sm text-gray-600 mt-1">Master parameter reusable — ERP, manual, atau hybrid.</p>
        </div>
        <button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded-xl font-semibold" @click="openCreate">
          + Tambah Parameter
        </button>
      </div>

      <div class="flex flex-wrap gap-3 mb-4">
        <input
          v-model="search"
          type="text"
          placeholder="Cari kode / nama..."
          class="px-4 py-2 rounded-xl border max-w-md"
          @input="debouncedSearch"
        />
        <select v-model="status" class="px-3 py-2 rounded-xl border">
          <option value="A">Aktif</option>
          <option value="N">Nonaktif</option>
          <option value="all">Semua</option>
        </select>
        <select v-model="sourceType" class="px-3 py-2 rounded-xl border">
          <option value="">Semua source</option>
          <option v-for="opt in options.source_types" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>

      <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-indigo-700 text-white">
            <tr>
              <th class="px-4 py-3 text-left">Kode</th>
              <th class="px-4 py-3 text-left">Nama</th>
              <th class="px-4 py-3 text-left">Source</th>
              <th class="px-4 py-3 text-left">Scope</th>
              <th class="px-4 py-3 text-center">Shared</th>
              <th class="px-4 py-3 text-left">ERP Resolver</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-left">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="row in parameters.data" :key="row.id" class="hover:bg-indigo-50/40">
              <td class="px-4 py-3 font-mono text-indigo-700">{{ row.code }}</td>
              <td class="px-4 py-3">{{ row.name }}</td>
              <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-gray-100">{{ sourceLabel(row.source_type) }}</span></td>
              <td class="px-4 py-3 capitalize">{{ row.scope_type }}</td>
              <td class="px-4 py-3 text-center">{{ row.is_shared ? 'Ya' : 'Tidak' }}</td>
              <td class="px-4 py-3 text-xs text-gray-600">{{ row.erp_mapping?.resolver_key || '—' }}</td>
              <td class="px-4 py-3 text-center">
                <button class="text-xs px-2 py-1 rounded-full" :class="row.status === 'A' ? 'bg-green-100 text-green-700' : 'bg-gray-100'" @click="toggleStatus(row)">
                  {{ row.status === 'A' ? 'Aktif' : 'Nonaktif' }}
                </button>
              </td>
              <td class="px-4 py-3">
                <button class="text-indigo-600 mr-2" @click="openEdit(row)"><i class="fa-solid fa-pen"></i></button>
                <button class="text-red-600" @click="hapus(row)"><i class="fa-solid fa-trash"></i></button>
              </td>
            </tr>
            <tr v-if="!parameters.data?.length">
              <td colspan="8" class="px-4 py-8 text-center text-gray-500">Belum ada parameter.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <KpiParameterFormModal
      :show="showModal"
      :mode="modalMode"
      :row="selectedRow"
      :options="options"
      @close="showModal = false"
      @success="reload"
    />
  </AppLayout>
</template>
