<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-bullhorn text-pink-600"></i>
            Marcomm Tracker
          </h1>
          <p class="text-sm text-gray-500 mt-1">Tracking job Marcomm — list & detail lengkap.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link
            :href="route('marcomm-trackers.dashboard')"
            class="inline-flex items-center gap-2 bg-white border border-rose-200 text-rose-700 px-4 py-2 rounded-lg shadow-sm hover:bg-rose-50 transition"
          >
            <i class="fa-solid fa-chart-pie"></i>
            Dashboard
          </Link>
          <Link
            :href="route('marcomm-trackers.create')"
            class="inline-flex items-center gap-2 bg-pink-600 text-white px-4 py-2 rounded-lg shadow hover:bg-pink-700 transition"
          >
            <i class="fa-solid fa-plus"></i>
            Buat Tracker
          </Link>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-5 gap-3">
          <input
            v-model="filterForm.search"
            type="text"
            placeholder="Cari nomor / PIC / task..."
            class="md:col-span-2 rounded-lg border-gray-300 focus:border-pink-500 focus:ring-pink-500"
          />
          <select v-model="filterForm.job_category" class="rounded-lg border-gray-300">
            <option value="">Semua Category</option>
            <option v-for="c in jobCategories" :key="c" :value="c">{{ c }}</option>
          </select>
          <select v-model="filterForm.job_status" class="rounded-lg border-gray-300">
            <option value="">Semua Job Status</option>
            <option v-for="(label, key) in jobStatuses" :key="key" :value="key">{{ label }}</option>
          </select>
          <div class="flex gap-2">
            <select v-model="filterForm.workflow_status" class="flex-1 rounded-lg border-gray-300">
              <option value="">Semua Workflow</option>
              <option v-for="(label, key) in workflowStatuses" :key="key" :value="key">{{ label }}</option>
            </select>
            <button type="submit" class="px-4 py-2 rounded-lg bg-pink-600 text-white hover:bg-pink-700">Cari</button>
          </div>
        </form>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Nomor</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Job Category</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">PIC</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Due Date</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Job Status</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Workflow</th>
              <th class="px-4 py-3 text-left font-semibold text-gray-700">Approver</th>
              <th class="px-4 py-3 text-right font-semibold text-gray-700">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!records.data?.length">
              <td colspan="8" class="px-4 py-8 text-center text-gray-500">Belum ada data Marcomm Tracker.</td>
            </tr>
            <tr v-for="row in records.data" :key="row.id" class="border-b hover:bg-pink-50/40 align-top">
              <td class="px-4 py-3 font-medium">{{ row.number }}</td>
              <td class="px-4 py-3">{{ row.job_category }}</td>
              <td class="px-4 py-3">{{ row.pic?.nama_lengkap || '-' }}</td>
              <td class="px-4 py-3">{{ formatDate(row.due_date) }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                  {{ jobStatuses[row.job_status] || row.job_status }}
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold" :class="workflowClass(row.workflow_status)">
                  {{ workflowStatuses[row.workflow_status] || row.workflow_status }}
                </span>
              </td>
              <td class="px-4 py-3">
                <ul v-if="sortedFlows(row).length" class="space-y-1 min-w-[180px]">
                  <li v-for="flow in sortedFlows(row)" :key="flow.id" class="text-xs">
                    <span class="font-medium">L{{ flow.approval_level }} · {{ flow.approver?.nama_lengkap || '-' }}</span>
                    <span :class="flowStatusClass(flow.status)"> · {{ flow.status }}</span>
                  </li>
                </ul>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <Link :href="route('marcomm-trackers.show', row.id)" class="text-pink-600 hover:text-pink-800 mr-3" title="Detail">
                  <i class="fa-solid fa-eye"></i>
                </Link>
                <Link
                  v-if="row.workflow_status !== 'approved'"
                  :href="route('marcomm-trackers.edit', row.id)"
                  class="text-indigo-600 hover:text-indigo-800"
                  title="Edit"
                >
                  <i class="fa-solid fa-pen"></i>
                </Link>
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
          :class="link.active ? 'bg-pink-600 text-white' : 'bg-white text-gray-700 border'"
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

const props = defineProps({
  records: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  jobCategories: { type: Array, default: () => [] },
  jobStatuses: { type: Object, default: () => ({}) },
  workflowStatuses: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
  search: props.filters.search || '',
  job_category: props.filters.job_category || '',
  job_status: props.filters.job_status || '',
  workflow_status: props.filters.workflow_status || '',
});

const applyFilters = () => {
  router.get(route('marcomm-trackers.index'), { ...filterForm }, { preserveState: true, replace: true });
};

const formatDate = (d) => {
  if (!d) return '-';
  return String(d).substring(0, 10);
};

const sortedFlows = (row) => {
  return [...(row.approval_flows || [])].sort((a, b) => a.approval_level - b.approval_level);
};

const workflowClass = (s) => {
  if (s === 'approved') return 'bg-green-100 text-green-800';
  if (s === 'submitted') return 'bg-amber-100 text-amber-800';
  if (s === 'rejected') return 'bg-red-100 text-red-800';
  return 'bg-gray-100 text-gray-700';
};

const flowStatusClass = (s) => {
  if (s === 'APPROVED') return 'text-green-600';
  if (s === 'REJECTED') return 'text-red-600';
  return 'text-amber-600';
};
</script>
