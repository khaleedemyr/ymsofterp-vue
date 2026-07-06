<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  templates: Object,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'A');
const templateStatus = ref(props.filters?.template_status || '');

const debouncedSearch = debounce(() => {
  router.get(route('kpi-templates.index'), {
    search: search.value,
    status: status.value,
    template_status: templateStatus.value || undefined,
  }, { preserveState: true, replace: true });
}, 400);

watch([status, templateStatus], () => debouncedSearch());

function statusBadge(st) {
  const map = { draft: 'bg-yellow-100 text-yellow-800', active: 'bg-green-100 text-green-800', archived: 'bg-gray-100 text-gray-600' };
  return map[st] || 'bg-gray-100';
}

function openCreate() {
  router.visit(route('kpi-templates.create'));
}

function openEdit(row) {
  router.visit(route('kpi-templates.edit', row.id));
}

function openShow(row) {
  router.visit(route('kpi-templates.show', row.id));
}

function formatPublishError(errors) {
  if (!errors) return 'Gagal publish template.';
  const first = errors.strategies || errors.jabatan_ids || Object.values(errors).flat()[0];
  return Array.isArray(first) ? first[0] : (first || 'Gagal publish template.');
}

async function publish(row) {
  const result = await Swal.fire({
    title: 'Publish template?',
    text: 'Template akan aktif dan siap dipakai evaluasi KPI.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Publish',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  router.post(route('kpi-templates.publish', row.id), {}, {
    onSuccess: () => Swal.fire('Berhasil', 'Template KPI berhasil dipublish.', 'success'),
    onError: (errors) => Swal.fire('Gagal publish', formatPublishError(errors), 'error'),
  });
}

async function hapus(row) {
  const result = await Swal.fire({ title: 'Nonaktifkan template?', icon: 'warning', showCancelButton: true });
  if (!result.isConfirmed) return;
  router.delete(route('kpi-templates.destroy', row.id));
}
</script>

<template>
  <AppLayout title="KPI Template">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold flex items-center gap-2">
            <i class="fa-solid fa-bullseye text-rose-600"></i>
            KPI Template
          </h1>
          <p class="text-sm text-gray-600 mt-1">Master template KPI per jabatan — Key Strategy, KPI items, bobot & formula.</p>
        </div>
        <button type="button" class="bg-rose-600 text-white px-4 py-2 rounded-xl font-semibold" @click="openCreate">+ Buat Template</button>
      </div>

      <div class="flex flex-wrap gap-3 mb-4">
        <input v-model="search" type="text" placeholder="Cari kode / nama..." class="px-4 py-2 rounded-xl border max-w-md" @input="debouncedSearch" />
        <select v-model="templateStatus" class="px-3 py-2 rounded-xl border">
          <option value="">Semua status template</option>
          <option value="draft">Draft</option>
          <option value="active">Active</option>
          <option value="archived">Archived</option>
        </select>
      </div>

      <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-rose-700 text-white">
            <tr>
              <th class="px-4 py-3 text-left">Kode</th>
              <th class="px-4 py-3 text-left">Nama</th>
              <th class="px-4 py-3 text-left">Jabatan</th>
              <th class="px-4 py-3 text-center">Strategies</th>
              <th class="px-4 py-3 text-center">Status Template</th>
              <th class="px-4 py-3 text-left">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="row in templates.data" :key="row.id" class="hover:bg-rose-50/40">
              <td class="px-4 py-3 font-mono text-rose-700">{{ row.code }}</td>
              <td class="px-4 py-3 font-medium">{{ row.name }}</td>
              <td class="px-4 py-3">
                <span v-for="p in row.positions" :key="p.id" class="inline-block mr-1 mb-1 px-2 py-0.5 bg-gray-100 rounded text-xs">
                  {{ p.jabatan?.nama_jabatan || p.id_jabatan }}
                </span>
                <span v-if="!row.positions?.length" class="text-gray-400">—</span>
              </td>
              <td class="px-4 py-3 text-center">{{ row.strategies_count }}</td>
              <td class="px-4 py-3 text-center">
                <span class="px-2 py-1 rounded-full text-xs capitalize" :class="statusBadge(row.template_status)">{{ row.template_status }}</span>
              </td>
              <td class="px-4 py-3 whitespace-nowrap">
                <button class="text-gray-600 mr-2" title="Preview" @click="openShow(row)"><i class="fa-solid fa-eye"></i></button>
                <button class="text-rose-600 mr-2" title="Edit" @click="openEdit(row)"><i class="fa-solid fa-pen"></i></button>
                <button v-if="row.template_status === 'draft'" class="text-green-600 mr-2" title="Publish" @click="publish(row)"><i class="fa-solid fa-check"></i></button>
                <button class="text-red-600" title="Nonaktifkan" @click="hapus(row)"><i class="fa-solid fa-trash"></i></button>
              </td>
            </tr>
            <tr v-if="!templates.data?.length">
              <td colspan="6" class="px-4 py-8 text-center text-gray-500">Belum ada template.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
