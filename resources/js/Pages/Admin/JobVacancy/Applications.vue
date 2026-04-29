<template>
  <AppLayout title="Data Pelamar Job Vacancy">
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-file-circle-check"></i> Data Pelamar Job Vacancy
        </h2>
        <a
          href="/admin/job-vacancy"
          class="bg-gray-600 text-white px-4 py-2 rounded shadow hover:bg-gray-700 transition"
        >
          Kembali ke Lowongan
        </a>
      </div>

      <div class="flex flex-wrap gap-2 mb-4">
        <select v-model="filterScope" class="rounded border px-2 py-1">
          <option value="">Semua Kategori</option>
          <option value="outlet">Outlet</option>
          <option value="head_office">Head Office</option>
        </select>
        <select v-model="filterStatus" class="rounded border px-2 py-1">
          <option value="">Semua Status</option>
          <option v-for="status in statusOptions" :key="status" :value="status">
            {{ formatStatus(status) }}
          </option>
        </select>
        <input
          v-model="search"
          placeholder="Cari nama/email/posisi..."
          class="rounded border px-2 py-1 w-64"
        />
        <button @click="fetchApplications(1)" class="bg-blue-500 text-white px-3 py-1 rounded">
          Cari
        </button>
      </div>

      <div class="overflow-x-auto rounded shadow">
        <table class="min-w-full">
          <thead>
            <tr class="bg-indigo-700 text-white">
              <th class="px-3 py-2">#</th>
              <th class="px-3 py-2">Pelamar</th>
              <th class="px-3 py-2">Posisi</th>
              <th class="px-3 py-2">Kategori</th>
              <th class="px-3 py-2">Kontak</th>
              <th class="px-3 py-2">CV</th>
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2">Dilamar</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(row, idx) in rows.data"
              :key="row.id"
              class="bg-white border-b last:border-b-0"
            >
              <td class="px-3 py-2">{{ idx + 1 + (rows.current_page - 1) * rows.per_page }}</td>
              <td class="px-3 py-2">
                <div class="font-semibold">{{ row.full_name }}</div>
                <div class="text-xs text-gray-500 line-clamp-2">{{ row.cover_letter || "-" }}</div>
              </td>
              <td class="px-3 py-2">
                <div class="font-medium">{{ row.job_vacancy?.position || "-" }}</div>
                <div class="text-xs text-gray-500">{{ row.job_vacancy?.location || "-" }}</div>
              </td>
              <td class="px-3 py-2">
                <span
                  class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                  :class="
                    row.job_vacancy?.job_scope === 'head_office'
                      ? 'bg-indigo-100 text-indigo-700'
                      : 'bg-emerald-100 text-emerald-700'
                  "
                >
                  {{ row.job_vacancy?.job_scope === "head_office" ? "Head Office" : "Outlet" }}
                </span>
              </td>
              <td class="px-3 py-2">
                <div class="text-sm">{{ row.email }}</div>
                <div class="text-sm text-gray-600">{{ row.phone }}</div>
              </td>
              <td class="px-3 py-2">
                <a
                  v-if="row.cv_file"
                  :href="`/storage/${row.cv_file}`"
                  target="_blank"
                  class="text-blue-600 hover:underline"
                >
                  Download CV
                </a>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-3 py-2">
                <select
                  :value="row.status"
                  class="rounded border px-2 py-1 text-sm"
                  @change="(e) => updateStatus(row.id, e.target.value)"
                >
                  <option v-for="status in statusOptions" :key="status" :value="status">
                    {{ formatStatus(status) }}
                  </option>
                </select>
              </td>
              <td class="px-3 py-2 text-sm text-gray-600">
                {{ formatDate(row.created_at) }}
              </td>
            </tr>
            <tr v-if="rows.data.length === 0">
              <td colspan="8" class="text-center py-8 text-gray-400">Belum ada data pelamar</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex justify-end">
        <nav v-if="rows.last_page > 1" class="inline-flex -space-x-px">
          <button
            v-for="page in rows.last_page"
            :key="page"
            @click="fetchApplications(page)"
            :class="[
              'px-3 py-1 border border-gray-300',
              page === rows.current_page
                ? 'bg-indigo-600 text-white'
                : 'bg-white text-indigo-700 hover:bg-indigo-50',
            ]"
          >
            {{ page }}
          </button>
        </nav>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  applications: Object,
  filters: Object,
  statusOptions: Array,
});

const rows = ref(props.applications);
const search = ref(props.filters?.search || '');
const filterScope = ref(props.filters?.scope || '');
const filterStatus = ref(props.filters?.status || '');
const statusOptions = ref(props.statusOptions || []);

function fetchApplications(page = 1) {
  router.get('/admin/job-vacancy/applications', {
    search: search.value,
    scope: filterScope.value,
    status: filterStatus.value,
    page,
  }, { preserveState: true, replace: true });
}

watch([search, filterScope, filterStatus], () => {
  fetchApplications(1);
});

function formatStatus(status) {
  if (!status) return '-';
  return status.replace('_', ' ').replace(/\b\w/g, (s) => s.toUpperCase());
}

function formatDate(value) {
  if (!value) return '-';
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return value;
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function updateStatus(id, status) {
  router.patch(`/admin/job-vacancy/applications/${id}/status`, { status }, {
    preserveScroll: true,
    onSuccess: () => fetchApplications(rows.value.current_page || 1),
  });
}
</script>

