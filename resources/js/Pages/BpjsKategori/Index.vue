<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Switch } from '@headlessui/vue';
import BpjsKategoriFormModal from './BpjsKategoriFormModal.vue';

const props = defineProps({
  bpjsKategoris: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const showInactive = ref(false);
const showModal = ref(false);
const modalMode = ref('create');
const selectedRow = ref(null);

function fmtPct(v) {
  if (v === null || v === undefined || v === '') return '—';
  const n = Number(v);
  if (Number.isNaN(n)) return '—';
  return `${n}%`;
}

const debouncedSearch = debounce(() => {
  router.get('/bpjs-kategori', { search: search.value, status: showInactive.value ? 'inactive' : 'active' }, { preserveState: true, replace: true });
}, 400);

watch(showInactive, (val) => {
  router.get('/bpjs-kategori', { search: search.value, status: val ? 'inactive' : 'active' }, { preserveState: true, replace: true });
});

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
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
    title: 'Nonaktifkan kategori?',
    text: `Kategori "${row.nama_kategori}" akan dinonaktifkan.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  router.delete(route('bpjs-kategori.destroy', row.id), {
    onSuccess: () => Swal.fire('Berhasil', 'Kategori BPJS dinonaktifkan.', 'success'),
  });
}

function reload() {
  router.reload({ preserveState: true, replace: true });
}

function closeModal() {
  showModal.value = false;
}

function toggleStatus(row) {
  router.patch(route('bpjs-kategori.toggle-status', row.id), {}, {
    preserveState: true,
    onSuccess: reload,
  });
}
</script>

<template>
  <AppLayout>
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-percent text-emerald-600"></i>
          Kategori BPJS
        </h1>
        <button
          type="button"
          @click="openCreate"
          class="bg-gradient-to-r from-emerald-500 to-teal-600 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          + Tambah kategori
        </button>
      </div>
      <p class="text-sm text-gray-600 mb-4 max-w-3xl">
        Master persentase iuran (perusahaan & karyawan) per kategori. Nilai dalam persen (%), dipakai sebagai referensi;
        integrasi ke perhitungan payroll dapat ditambahkan terpisah.
      </p>
      <div class="flex items-center gap-3 mb-4">
        <Switch
          v-model="showInactive"
          :class="showInactive ? 'bg-emerald-600' : 'bg-gray-200'"
          class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none"
        >
          <span
            :class="showInactive ? 'translate-x-6' : 'translate-x-1'"
            class="inline-block h-4 w-4 transform rounded-full bg-white transition"
          />
        </Switch>
        <span class="text-sm text-gray-700">Tampilkan nonaktif</span>
      </div>
      <div class="mb-4">
        <input
          v-model="search"
          type="text"
          placeholder="Cari nama kategori..."
          class="w-full max-w-md px-4 py-2 rounded-xl border border-emerald-200 shadow focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 transition"
          @input="onSearchInput"
        />
      </div>
      <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gradient-to-r from-emerald-600 to-teal-700 text-white">
              <tr>
                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Nama</th>
                <th class="px-2 py-3 text-center font-semibold whitespace-nowrap" title="Kesehatan perusahaan">Kes. Perus.</th>
                <th class="px-2 py-3 text-center font-semibold whitespace-nowrap" title="Kesehatan karyawan">Kes. Kar.</th>
                <th class="px-2 py-3 text-center font-semibold whitespace-nowrap">JHT Perus.</th>
                <th class="px-2 py-3 text-center font-semibold whitespace-nowrap">JP Perus.</th>
                <th class="px-2 py-3 text-center font-semibold whitespace-nowrap">JKK Perus.</th>
                <th class="px-2 py-3 text-center font-semibold whitespace-nowrap">JKM Perus.</th>
                <th class="px-2 py-3 text-center font-semibold whitespace-nowrap">JHT Kar.</th>
                <th class="px-2 py-3 text-center font-semibold whitespace-nowrap">JP Kar.</th>
                <th class="px-2 py-3 text-center font-semibold whitespace-nowrap">Status</th>
                <th class="px-3 py-3 text-left font-semibold whitespace-nowrap">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="row in bpjsKategoris.data" :key="row.id" class="hover:bg-emerald-50/50">
                <td class="px-3 py-2 font-medium text-gray-900 whitespace-nowrap">{{ row.nama_kategori }}</td>
                <td class="px-2 py-2 text-center">{{ fmtPct(row.pct_kes_perusahaan) }}</td>
                <td class="px-2 py-2 text-center">{{ fmtPct(row.pct_kes_karyawan) }}</td>
                <td class="px-2 py-2 text-center">{{ fmtPct(row.pct_jht_perusahaan) }}</td>
                <td class="px-2 py-2 text-center">{{ fmtPct(row.pct_jp_perusahaan) }}</td>
                <td class="px-2 py-2 text-center">{{ fmtPct(row.pct_jkk_perusahaan) }}</td>
                <td class="px-2 py-2 text-center">{{ fmtPct(row.pct_jkm_perusahaan) }}</td>
                <td class="px-2 py-2 text-center">{{ fmtPct(row.pct_jht_karyawan) }}</td>
                <td class="px-2 py-2 text-center">{{ fmtPct(row.pct_jp_karyawan) }}</td>
                <td class="px-2 py-2 text-center">
                  <button
                    type="button"
                    :class="row.status === 'A' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
                    class="px-2 py-1 rounded-full text-xs font-semibold"
                    @click="toggleStatus(row)"
                  >
                    {{ row.status === 'A' ? 'Aktif' : 'Nonaktif' }}
                  </button>
                </td>
                <td class="px-3 py-2 whitespace-nowrap">
                  <div class="flex gap-2">
                    <button
                      type="button"
                      class="inline-flex items-center rounded px-2 py-1 text-xs font-semibold bg-amber-100 text-amber-900 hover:bg-amber-200"
                      @click="openEdit(row)"
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      class="inline-flex items-center rounded px-2 py-1 text-xs font-semibold bg-red-100 text-red-800 hover:bg-red-200"
                      @click="hapus(row)"
                    >
                      Nonaktifkan
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="flex justify-end mt-4 gap-2 flex-wrap">
        <button
          v-for="link in bpjsKategoris.links"
          :key="link.label"
          type="button"
          :disabled="!link.url"
          class="px-3 py-1 rounded-lg border text-sm font-semibold"
          :class="[
            link.active ? 'bg-emerald-600 text-white shadow' : 'bg-white text-emerald-800 hover:bg-emerald-50',
            !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
          ]"
          @click="goToPage(link.url)"
          v-html="link.label"
        />
      </div>
      <BpjsKategoriFormModal
        :show="showModal"
        :mode="modalMode"
        :row="selectedRow"
        @close="closeModal"
        @success="reload"
      />
    </div>
  </AppLayout>
</template>
