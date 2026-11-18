<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-calendar-days text-violet-600"></i>
            Tasking Plan
          </h1>
          <p class="text-sm text-gray-500 mt-1">Penugasan konten — Flow 1 pemberi tugas, Flow 2 PIC menyusul.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            :href="route('content-plans.dashboard')"
            class="inline-flex items-center gap-2 bg-white border border-violet-200 text-violet-700 px-4 py-2 rounded-lg shadow-sm hover:bg-violet-50 transition"
          >
            <i class="fa-solid fa-chart-pie"></i>
            Dashboard
          </Link>
          <Link
            :href="route('content-plans.create')"
            class="inline-flex items-center gap-2 bg-violet-600 text-white px-4 py-2 rounded-lg shadow hover:bg-violet-700 transition"
          >
            <i class="fa-solid fa-plus"></i>
            Buat Tasking Plan
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-6 gap-3">
          <input
            v-model="filterForm.search"
            type="text"
            placeholder="Cari nomor / pillar / caption / brand..."
            class="md:col-span-2 rounded-lg border-gray-300 focus:border-violet-500 focus:ring-violet-500"
          />
          <select v-model="filterForm.brand_id" class="rounded-lg border-gray-300">
            <option value="">Semua Brand</option>
            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.brand }}</option>
          </select>
          <select v-model="filterForm.platform" class="rounded-lg border-gray-300">
            <option value="">Semua Platform</option>
            <option v-for="p in platforms" :key="p" :value="p">{{ p }}</option>
          </select>
          <select v-model="filterForm.priority" class="rounded-lg border-gray-300">
            <option value="">Semua Prioritas</option>
            <option v-for="(label, key) in priorities" :key="key" :value="key">{{ label }}</option>
          </select>
          <div class="flex gap-2">
            <select v-model="filterForm.workflow_status" class="flex-1 rounded-lg border-gray-300">
              <option value="">Semua Approval</option>
              <option v-for="(label, key) in workflowStatuses" :key="key" :value="key">{{ label }}</option>
            </select>
            <button type="submit" class="px-4 py-2 rounded-lg bg-violet-600 text-white hover:bg-violet-700">Cari</button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">Nomor TP</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">Created Date</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">Created By</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">PIC</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">Brand</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">Platform</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">Deadline Content</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">Date Post</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">Pillar</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">Prioritas</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700 whitespace-nowrap">Status Approval</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700 whitespace-nowrap">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!records.data?.length">
              <td colspan="12" class="px-4 py-8 text-center text-gray-500">Belum ada Tasking Plan.</td>
            </tr>
            <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-violet-50/40 align-top">
              <td class="px-4 py-3 font-medium whitespace-nowrap">{{ row.number }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ formatDateTime(row.created_at) }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ row.creator?.nama_lengkap || '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ row.pic?.nama_lengkap || '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ row.brand?.brand || '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ row.platform || '-' }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(row.deadline_content) }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ formatDate(row.date_post) }}</td>
              <td class="px-4 py-3 whitespace-nowrap">{{ row.pillar || '-' }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex px-2.5 py-1 rounded-md text-xs font-semibold border" :class="priorityClass(row.priority)">
                  {{ priorities[row.priority] || row.priority || '-' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex px-2.5 py-1 rounded-md text-xs font-semibold" :class="workflowClass(row.workflow_status)">
                  {{ workflowStatuses[row.workflow_status] || row.workflow_status }}
                </span>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <Link :href="route('content-plans.show', row.id)" class="text-violet-600 hover:text-violet-800 mr-3" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </Link>
                <Link
                  v-if="row.can_edit"
                  :href="route('content-plans.edit', row.id)"
                  class="text-indigo-600 hover:text-indigo-800 mr-3"
                  title="Edit Flow 1"
                >
                  <i class="fa-solid fa-pen"></i>
                </Link>
                <Link
                  v-if="row.can_edit_pic"
                  :href="route('content-plans.show', row.id)"
                  class="text-emerald-600 hover:text-emerald-800 mr-3"
                  title="Isi Flow 2 (PIC)"
                >
                  <i class="fa-solid fa-user-pen"></i>
                </Link>
                <button
                  v-if="row.can_delete"
                  type="button"
                  class="text-red-600 hover:text-red-800"
                  title="Hapus"
                  @click="confirmDelete(row)"
                >
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="records.links?.length > 3" class="mt-4 flex justify-center gap-1 flex-wrap">
        <Link
          v-for="(link, i) in records.links"
          :key="i"
          :href="link.url || '#'"
          class="px-3 py-1 rounded text-sm"
          :class="link.active ? 'bg-violet-600 text-white' : 'bg-white text-gray-700 border'"
          v-html="link.label"
        />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  brands: { type: Array, default: () => [] },
  platforms: { type: Array, default: () => [] },
  priorities: { type: Object, default: () => ({}) },
  jobStatuses: { type: Object, default: () => ({}) },
  workflowStatuses: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
  search: props.filters.search || '',
  brand_id: props.filters.brand_id || '',
  platform: props.filters.platform || '',
  priority: props.filters.priority || '',
  workflow_status: props.filters.workflow_status || '',
});

const applyFilters = () => {
  router.get(route('content-plans.index'), { ...filterForm }, { preserveState: true, replace: true });
};

const formatDate = (d) => (d ? String(d).substring(0, 10) : '-');

const formatDateTime = (d) => {
  if (!d) return '-';
  return String(d).replace('T', ' ').substring(0, 19);
};

const workflowClass = (s) => {
  if (s === 'approved') return 'bg-green-100 text-green-800';
  if (s === 'submitted') return 'bg-amber-100 text-amber-800';
  if (s === 'rejected') return 'bg-red-100 text-red-800';
  return 'bg-gray-100 text-gray-700';
};

const priorityClass = (s) => {
  if (s === 'urgent') return 'bg-red-50 text-red-700 border-red-200';
  if (s === 'high') return 'bg-amber-50 text-amber-700 border-amber-200';
  if (s === 'medium') return 'bg-blue-50 text-blue-700 border-blue-200';
  return 'bg-slate-50 text-slate-700 border-slate-200';
};

const confirmDelete = async (row) => {
  const result = await Swal.fire({
    title: 'Hapus Tasking Plan?',
    text: row.number,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
  });
  if (!result.isConfirmed) return;
  router.delete(route('content-plans.destroy', row.id));
};
</script>
