<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi } from '@/composables/useJustAcademyUi';

const props = defineProps({
  rows: { type: Array, default: () => [] },
  divisions: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  reportMeta: { type: Object, default: () => ({}) },
});

const year = ref(props.filters?.year || new Date().getFullYear());
const month = ref(props.filters?.month || new Date().getMonth() + 1);
const divisionId = ref(props.filters?.division_id || '');

function applyFilters() {
  router.get(route('just-academy.reports.index'), {
    year: year.value,
    month: month.value,
    division_id: divisionId.value || undefined,
  }, { preserveState: true });
}

function printReport() {
  window.print();
}

function formatScore(value) {
  if (value === null || value === undefined || value === '') return '—';
  return `${value}%`;
}

function formatRate(value) {
  if (value === null || value === undefined || value === '') return '—';
  return `${value}%`;
}
</script>

<template>
  <JaLayout title="Laporan Training" subtitle="Departmental Training Report" icon="fa-solid fa-chart-column">
    <div class="mb-4 flex flex-wrap items-end gap-3 print:hidden">
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Bulan</label>
        <input v-model.number="month" type="number" min="1" max="12" :class="[jaUi.input, 'w-20']" />
      </div>
      <div>
        <label class="mb-1 block text-xs font-medium text-slate-500">Tahun</label>
        <input v-model.number="year" type="number" min="2020" max="2100" :class="[jaUi.input, 'w-28']" />
      </div>
      <div class="min-w-[14rem]">
        <label class="mb-1 block text-xs font-medium text-slate-500">Departemen</label>
        <select v-model="divisionId" :class="jaUi.select">
          <option value="">Semua departemen</option>
          <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
        </select>
      </div>
      <button type="button" :class="jaUi.btnPrimary" @click="applyFilters">Tampilkan</button>
      <button type="button" :class="jaUi.btnSecondary" @click="printReport">
        <i class="fa-solid fa-print mr-1" /> Cetak
      </button>
    </div>

    <div id="ja-dept-training-report" class="overflow-x-auto rounded-xl border border-slate-300 bg-white p-4 shadow-sm print:border-0 print:p-0 print:shadow-none">
      <div class="mb-4 text-center">
        <h2 class="text-lg font-bold uppercase tracking-wide text-slate-900 print:text-xl">
          Departmental Training Report
        </h2>
        <div class="mt-3 flex flex-wrap justify-center gap-8 text-sm text-slate-700">
          <p><span class="font-semibold">Month :</span> {{ reportMeta.month_label }}</p>
          <p><span class="font-semibold">Department :</span> {{ reportMeta.department_label }}</p>
        </div>
      </div>

      <table class="min-w-full border-collapse text-xs text-slate-800">
        <thead>
          <tr class="bg-slate-800 text-[10px] uppercase text-white">
            <th rowspan="2" class="border border-slate-700 px-2 py-2 text-center">No</th>
            <th rowspan="2" class="border border-slate-700 px-2 py-2 text-left">Training Subject</th>
            <th rowspan="2" class="border border-slate-700 px-2 py-2 text-left">Objective</th>
            <th rowspan="2" class="border border-slate-700 px-2 py-2 text-left">Method</th>
            <th rowspan="2" class="border border-slate-700 px-2 py-2 text-left">Duration</th>
            <th colspan="3" class="border border-slate-700 px-2 py-2 text-center">Participant</th>
            <th rowspan="2" class="border border-slate-700 px-2 py-2 text-left">Date of Training</th>
            <th rowspan="2" class="border border-slate-700 px-2 py-2 text-left">Venue</th>
            <th colspan="3" class="border border-slate-700 px-2 py-2 text-center">Training Evaluation</th>
            <th rowspan="2" class="border border-slate-700 px-2 py-2 text-left">Trainer</th>
          </tr>
          <tr class="bg-slate-800 text-[10px] uppercase text-white">
            <th class="border border-slate-700 px-2 py-2 text-center">Total Registered</th>
            <th class="border border-slate-700 px-2 py-2 text-center">Total Attendees</th>
            <th class="border border-slate-700 px-2 py-2 text-center">Attendance Rate %</th>
            <th class="border border-slate-700 px-2 py-2 text-center">Pre-Test</th>
            <th class="border border-slate-700 px-2 py-2 text-center">Post Test</th>
            <th class="border border-slate-700 px-2 py-2 text-center">Improvement %</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.no" class="align-top">
            <td class="border border-slate-300 px-2 py-2 text-center">{{ row.no }}</td>
            <td class="border border-slate-300 px-2 py-2 font-medium">{{ row.training_subject }}</td>
            <td class="border border-slate-300 px-2 py-2 max-w-[12rem] whitespace-pre-wrap">{{ row.objective }}</td>
            <td class="border border-slate-300 px-2 py-2">{{ row.method }}</td>
            <td class="border border-slate-300 px-2 py-2 whitespace-nowrap">{{ row.duration }}</td>
            <td class="border border-slate-300 px-2 py-2 text-center">{{ row.total_registered }}</td>
            <td class="border border-slate-300 px-2 py-2 text-center">{{ row.total_attendees }}</td>
            <td class="border border-slate-300 px-2 py-2 text-center">{{ formatRate(row.attendance_rate) }}</td>
            <td class="border border-slate-300 px-2 py-2 whitespace-nowrap">{{ row.training_date }}</td>
            <td class="border border-slate-300 px-2 py-2">{{ row.venue }}</td>
            <td class="border border-slate-300 px-2 py-2 text-center">{{ formatScore(row.pre_test) }}</td>
            <td class="border border-slate-300 px-2 py-2 text-center">{{ formatScore(row.post_test) }}</td>
            <td class="border border-slate-300 px-2 py-2 text-center">{{ formatRate(row.improvement_pct) }}</td>
            <td class="border border-slate-300 px-2 py-2">{{ row.trainer }}</td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="14" class="border border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
              Tidak ada data training untuk filter ini.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </JaLayout>
</template>

<style>
@media print {
  @page {
    size: landscape;
    margin: 10mm;
  }
}
</style>
