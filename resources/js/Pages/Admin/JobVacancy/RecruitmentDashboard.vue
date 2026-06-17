<template>
  <AppLayout title="Dashboard Rekrutmen">
    <div class="max-w-[1600px] w-full mx-auto py-8 px-2">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h2 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-chart-line text-orange-600"></i> Dashboard Progress Rekrutmen
        </h2>
        <div class="flex flex-wrap gap-2">
          <a href="/admin/job-vacancy/applications" class="bg-indigo-600 text-white px-4 py-2 rounded shadow hover:bg-indigo-700 text-sm">
            Input Progress Pelamar
          </a>
          <a href="/admin/job-vacancy" class="bg-gray-600 text-white px-4 py-2 rounded shadow hover:bg-gray-700 text-sm">
            Kembali ke Lowongan
          </a>
        </div>
      </div>

      <p class="mb-4 text-sm text-gray-600">
        <strong>Sourcing</strong> = jumlah pelamar per posisi (otomatis).
        Screening / HR / User / LOI diinput terpisah per pelamar di menu Data Pelamar.
      </p>

      <div class="flex flex-wrap gap-2 mb-4">
        <select v-model="filterScope" class="rounded border px-2 py-1.5 text-sm">
          <option value="">Semua Kategori</option>
          <option value="head_office">Head Office</option>
          <option value="outlet">Outlet</option>
        </select>
        <input v-model="dateFrom" type="date" class="rounded border px-2 py-1.5 text-sm" />
        <span class="self-center text-gray-500 text-sm">s/d</span>
        <input v-model="dateTo" type="date" class="rounded border px-2 py-1.5 text-sm" />
        <input v-model="search" placeholder="Cari posisi, lokasi, PIC..." class="rounded border px-2 py-1.5 text-sm w-64" />
        <button class="bg-blue-500 text-white px-3 py-1.5 rounded text-sm" @click="fetchDashboard">Cari</button>
      </div>

      <RecruitmentSection
        v-if="!filterScope || filterScope === 'head_office'"
        title="HEAD OFFICE & MANAGERIAL"
        :rows="grouped.head_office || []"
        @edit-config="openConfig"
        @view-applicants="goApplicants"
      />

      <RecruitmentSection
        v-if="!filterScope || filterScope === 'outlet'"
        class="mt-8"
        title="OUTLET"
        :rows="grouped.outlet || []"
        @edit-config="openConfig"
        @view-applicants="goApplicants"
      />

      <RecruitmentConfigForm
        v-if="configVacancy"
        :vacancy="configVacancy"
        @close="configVacancy = null"
        @saved="fetchDashboard"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RecruitmentConfigForm from './RecruitmentConfigForm.vue';
import RecruitmentSection from './RecruitmentSection.vue';

const props = defineProps({
  grouped: { type: Object, default: () => ({ head_office: [], outlet: [] }) },
  filters: { type: Object, default: () => ({}) },
});

const grouped = ref(props.grouped);
const search = ref(props.filters?.search || '');
const filterScope = ref(props.filters?.scope || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');
const configVacancy = ref(null);

watch(() => props.grouped, (v) => { grouped.value = v; });

function fetchDashboard() {
  router.get('/admin/job-vacancy/recruitment-dashboard', {
    search: search.value,
    scope: filterScope.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
  }, { preserveState: true, replace: true });
}

watch([filterScope], () => fetchDashboard());

function openConfig(vacancy) {
  configVacancy.value = vacancy;
}

function goApplicants(vacancy) {
  router.get('/admin/job-vacancy/applications', {
    job_vacancy_id: vacancy.id,
  });
}
</script>
