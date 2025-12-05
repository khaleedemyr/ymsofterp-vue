<template>
  <AppLayout title="Data Lowongan Pekerjaan">
    <div class="max-w-7xl w-full mx-auto py-8 px-2">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-briefcase"></i> Data Lowongan Pekerjaan
        </h2>
        <div class="flex gap-2">
          <a href="/job-vacancies" target="_blank" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded shadow hover:from-green-600 hover:to-green-800 transition flex items-center gap-2" title="Lihat Halaman Landing">
            <i class="fa-solid fa-globe"></i> Lihat Landing Page
          </a>
          <button @click="openForm()" class="bg-blue-600 text-white px-4 py-2 rounded shadow hover:bg-blue-700 transition">+ Tambah Lowongan Baru</button>
        </div>
      </div>
      <div class="flex flex-wrap gap-2 mb-4">
        <select v-model="filterActive" class="rounded border px-2 py-1">
          <option value="">Semua Status</option>
          <option value="1">Aktif</option>
          <option value="0">Nonaktif</option>
        </select>
        <input v-model="search" placeholder="Cari Posisi, Lokasi, Deskripsi..." class="rounded border px-2 py-1 w-64" />
        <button @click="fetchJobs(1)" class="bg-blue-500 text-white px-3 py-1 rounded">Cari</button>
      </div>
      <div class="overflow-x-auto rounded shadow">
        <table class="min-w-full">
          <thead>
            <tr class="bg-blue-700 text-white">
              <th class="px-3 py-2">#</th>
              <th class="px-3 py-2">Banner</th>
              <th class="px-3 py-2">Posisi</th>
              <th class="px-3 py-2">Lokasi</th>
              <th class="px-3 py-2">Tgl Tutup</th>
              <th class="px-3 py-2">Status</th>
              <th class="px-3 py-2">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(job, idx) in jobs.data" :key="job.id" class="bg-white border-b last:border-b-0">
              <td class="px-3 py-2">{{ idx + 1 + ((jobs.current_page-1)*jobs.per_page) }}</td>
              <td class="px-3 py-2">
                <img v-if="job.banner" :src="bannerUrl(job.banner)" class="w-16 h-10 object-cover rounded shadow" />
              </td>
              <td class="px-3 py-2 font-bold">{{ job.position }}</td>
              <td class="px-3 py-2">{{ job.location }}</td>
              <td class="px-3 py-2">{{ job.closing_date }}</td>
              <td class="px-3 py-2">
                <span :class="job.is_active ? 'text-green-700 font-bold' : 'text-gray-400'">
                  {{ job.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="px-3 py-2 flex gap-2">
                <button @click="openForm(job)" class="bg-yellow-400 text-white px-2 py-1 rounded" title="Edit"><i class="fa fa-pen"></i></button>
                <button @click="deleteJob(job.id)" class="bg-red-500 text-white px-2 py-1 rounded" title="Hapus"><i class="fa fa-trash"></i></button>
                <button @click="toggleActive(job)" :class="job.is_active ? 'bg-gray-400' : 'bg-green-600'" class="text-white px-2 py-1 rounded" :title="job.is_active ? 'Nonaktifkan' : 'Aktifkan'">
                  <i :class="job.is_active ? 'fa fa-eye-slash' : 'fa fa-eye'" />
                </button>
              </td>
            </tr>
            <tr v-if="jobs.data.length === 0">
              <td colspan="7" class="text-center py-8 text-gray-400">Tidak ada data lowongan</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="mt-4 flex justify-end">
        <nav v-if="jobs.last_page > 1" class="inline-flex -space-x-px">
          <button v-for="page in jobs.last_page" :key="page" @click="fetchJobs(page)" :class="['px-3 py-1 border border-gray-300', page === jobs.current_page ? 'bg-blue-600 text-white' : 'bg-white text-blue-700 hover:bg-blue-50']">{{ page }}</button>
        </nav>
      </div>
      <JobVacancyForm v-if="showForm" :job="selectedJob" @close="closeForm" @saved="fetchJobs(jobs.current_page)" />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import JobVacancyForm from './Form.vue';

const props = defineProps({
  vacancies: Object, // { data, current_page, last_page, per_page, total, from, to }
  filters: Object,
});

const jobs = ref(props.vacancies);
const search = ref(props.filters?.search || '');
const filterActive = ref(props.filters?.is_active ?? '');
const showForm = ref(false);
const selectedJob = ref(null);

function fetchJobs(page = 1) {
  router.get('/admin/job-vacancy', {
    search: search.value,
    is_active: filterActive.value,
    page,
  }, { preserveState: true, replace: true });
}

watch([search, filterActive], () => {
  fetchJobs(1);
});

function openForm(job = null) {
  selectedJob.value = job;
  showForm.value = true;
}
function closeForm() {
  showForm.value = false;
  selectedJob.value = null;
}
function deleteJob(id) {
  if (confirm('Yakin hapus lowongan ini?')) {
    router.delete(`/admin/job-vacancy/${id}`, {
      onSuccess: () => fetchJobs(jobs.value.current_page),
    });
  }
}
function toggleActive(job) {
  router.patch(`/admin/job-vacancy/${job.id}/set-active`, { is_active: job.is_active ? 0 : 1 }, {
    onSuccess: () => fetchJobs(jobs.value.current_page),
  });
}
function bannerUrl(path) {
  return path ? `/storage/${path}` : '';
}
</script> 