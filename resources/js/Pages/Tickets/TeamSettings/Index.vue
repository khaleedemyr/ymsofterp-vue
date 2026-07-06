<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  settings: Object,
  categories: Array,
  filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'A');
const categoryId = ref(props.filters?.category_id || '');

const reload = debounce(() => {
  router.get(route('tickets.team-settings.index'), {
    search: search.value || undefined,
    status: status.value,
    category_id: categoryId.value || undefined,
  }, { preserveState: true, replace: true });
}, 350);

watch([status, categoryId], reload);

function openCreate() {
  router.visit(route('tickets.team-settings.create'));
}

function openEdit(row) {
  router.visit(route('tickets.team-settings.edit', row.id));
}

function backToTickets() {
  router.visit('/tickets');
}

async function deactivate(row) {
  const result = await Swal.fire({
    title: 'Nonaktifkan setting team?',
    text: row.name || row.category?.name || `Setting #${row.id}`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya',
  });
  if (!result.isConfirmed) return;
  router.delete(route('tickets.team-settings.destroy', row.id));
}

function scopeLabel(row) {
  const regions = (row.regions || []).map((r) => r.name).join(', ');
  const outlets = (row.outlets || []).map((o) => o.nama_outlet).join(', ');
  if (!regions && !outlets) return 'Semua outlet (category-wide)';
  const parts = [];
  if (regions) parts.push(`Region: ${regions}`);
  if (outlets) parts.push(`Outlet: ${outlets}`);
  return parts.join(' · ');
}

function usersLabel(row) {
  return (row.users || []).map((u) => u.nama_lengkap).join(', ') || '-';
}
</script>

<template>
  <AppLayout title="Ticketing Team Settings">
    <div class="w-full max-w-[90rem] mx-auto py-8 px-4 space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Setting Team Ticketing</h1>
          <p class="text-sm text-gray-500 mt-1">
            Atur tim penanganan per category + region/outlet. Ticket baru akan otomatis di-assign.
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button class="px-4 py-2 rounded-xl bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200" @click="backToTickets">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Tickets
          </button>
          <button class="px-4 py-2 rounded-xl bg-blue-600 text-white font-semibold hover:bg-blue-700" @click="openCreate">
            <i class="fa-solid fa-plus mr-1"></i> Tambah Setting
          </button>
        </div>
      </div>

      <div class="flex flex-wrap gap-3">
        <input
          v-model="search"
          type="text"
          placeholder="Cari nama setting / category / user..."
          class="min-w-[260px] flex-1 rounded-xl border px-4 py-2"
          @input="reload"
        />
        <select v-model="categoryId" class="rounded-xl border px-3 py-2">
          <option value="">Semua category</option>
          <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
        </select>
        <select v-model="status" class="rounded-xl border px-3 py-2">
          <option value="A">Aktif</option>
          <option value="N">Nonaktif</option>
          <option value="all">Semua</option>
        </select>
      </div>

      <div class="bg-white rounded-2xl shadow overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-indigo-700 text-white">
            <tr>
              <th class="px-4 py-3 text-left">Category</th>
              <th class="px-4 py-3 text-left">Nama / Scope</th>
              <th class="px-4 py-3 text-left">Team</th>
              <th class="px-4 py-3 text-center">Status</th>
              <th class="px-4 py-3 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in settings.data" :key="row.id" class="border-b hover:bg-indigo-50/40 align-top">
              <td class="px-4 py-3 font-semibold text-gray-800">{{ row.category?.name || '-' }}</td>
              <td class="px-4 py-3">
                <div v-if="row.name" class="font-medium text-gray-800">{{ row.name }}</div>
                <div class="text-xs text-gray-500 mt-0.5">{{ scopeLabel(row) }}</div>
              </td>
              <td class="px-4 py-3 text-gray-700">{{ usersLabel(row) }}</td>
              <td class="px-4 py-3 text-center">
                <span
                  :class="row.status === 'A' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                  class="px-2 py-1 rounded-full text-xs font-semibold"
                >
                  {{ row.status === 'A' ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="px-4 py-3 text-center space-x-2 whitespace-nowrap">
                <button class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded" @click="openEdit(row)">Edit</button>
                <button
                  v-if="row.status === 'A'"
                  class="px-2 py-1 bg-red-100 text-red-700 rounded"
                  @click="deactivate(row)"
                >
                  Nonaktifkan
                </button>
              </td>
            </tr>
            <tr v-if="!settings.data?.length">
              <td colspan="5" class="text-center py-10 text-gray-400">Belum ada setting team ticketing</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
