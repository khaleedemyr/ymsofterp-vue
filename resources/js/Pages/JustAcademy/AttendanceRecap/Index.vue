<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi } from '@/composables/useJustAcademyUi';

const props = defineProps({
  sections: { type: Array, default: () => [] },
  scheduleOptions: { type: Array, default: () => [] },
  divisions: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  reportMeta: { type: Object, default: () => ({}) },
});

const year = ref(props.filters?.year || new Date().getFullYear());
const month = ref(props.filters?.month || new Date().getMonth() + 1);
const divisionId = ref(props.filters?.division_id || '');
const scheduleId = ref(props.filters?.schedule_id || '');

const totalRegistered = computed(() => props.sections.reduce((sum, s) => sum + (s.summary?.registered || 0), 0));
const totalAttendees = computed(() => props.sections.reduce((sum, s) => sum + (s.summary?.attendees || 0), 0));

function applyFilters() {
  router.get(route('just-academy.attendance-recap.index'), {
    year: year.value,
    month: month.value,
    division_id: divisionId.value || undefined,
    schedule_id: scheduleId.value || undefined,
  }, { preserveState: true });
}

function printReport() {
  window.print();
}

function formatRate(value) {
  if (value === null || value === undefined || value === '') return '—';
  return `${value}%`;
}

function attendanceLabel(row) {
  return row.attended ? 'Hadir' : 'Tidak hadir';
}

function methodLabel(method) {
  if (!method) return '—';
  if (method === 'qr') return 'QR';
  if (method === 'manual') return 'Manual';
  return method;
}

function quizResultLabel(result) {
  if (!result || result.status === 'not_started') return 'Belum mengerjakan';
  if (result.status === 'in_progress') return 'Sedang mengerjakan';
  const score = result.score === null || result.score === undefined ? '—' : `${result.score}%`;
  if (result.passed === true) return `${score} (Lulus)`;
  if (result.passed === false) return `${score} (Tidak lulus)`;
  return score;
}

function quizResultClass(result) {
  if (!result || result.status !== 'submitted') return 'text-slate-500';
  if (result.passed === true) return 'text-emerald-700 font-semibold';
  if (result.passed === false) return 'text-rose-700 font-semibold';
  return 'text-slate-800';
}
</script>

<template>
  <JaLayout
    title="Rekap Kehadiran Training"
    subtitle="Roster peserta, status kehadiran, dan hasil test per training plan"
    icon="fa-solid fa-clipboard-user"
  >
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
      <div class="min-w-[18rem] flex-1">
        <label class="mb-1 block text-xs font-medium text-slate-500">Training Plan</label>
        <select v-model="scheduleId" :class="jaUi.select">
          <option value="">Semua training plan</option>
          <option v-for="s in scheduleOptions" :key="s.id" :value="s.id">
            {{ s.start_at ? `${s.start_at} — ` : '' }}{{ s.title }}
          </option>
        </select>
      </div>
      <button type="button" :class="jaUi.btnPrimary" @click="applyFilters">Tampilkan</button>
      <button type="button" :class="jaUi.btnSecondary" @click="printReport">
        <i class="fa-solid fa-print mr-1" /> Cetak
      </button>
    </div>

    <div
      id="ja-attendance-recap"
      class="space-y-6 rounded-xl border border-slate-300 bg-white p-4 shadow-sm print:border-0 print:p-0 print:shadow-none"
    >
      <div class="mb-2 text-center">
        <h2 class="text-lg font-bold uppercase tracking-wide text-slate-900 print:text-xl">
          Rekap Kehadiran Training
        </h2>
        <div class="mt-3 flex flex-wrap justify-center gap-6 text-sm text-slate-700">
          <p><span class="font-semibold">Month :</span> {{ reportMeta.month_label }}</p>
          <p><span class="font-semibold">Department :</span> {{ reportMeta.department_label }}</p>
          <p><span class="font-semibold">Training Plan :</span> {{ reportMeta.schedule_label }}</p>
        </div>
        <p v-if="sections.length" class="mt-2 text-xs text-slate-500">
          Total: {{ totalAttendees }}/{{ totalRegistered }} hadir · {{ sections.length }} training plan
        </p>
      </div>

      <div v-if="!sections.length" class="rounded-lg border border-dashed border-slate-300 px-4 py-12 text-center text-sm text-slate-500">
        Tidak ada data training untuk filter ini.
      </div>

      <section
        v-for="(section, sectionIdx) in sections"
        :key="section.schedule_id"
        class="overflow-hidden rounded-lg border border-slate-200 print:break-inside-avoid"
      >
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-3">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h3 class="text-sm font-bold text-slate-900">
                {{ sectionIdx + 1 }}. {{ section.title }}
              </h3>
              <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600">
                <span><span class="font-semibold">Tanggal:</span> {{ section.training_date || '—' }}</span>
                <span><span class="font-semibold">Venue:</span> {{ section.venue }}</span>
                <span><span class="font-semibold">Trainer:</span> {{ section.trainer }}</span>
                <span><span class="font-semibold">Method:</span> {{ section.method }}</span>
              </div>
            </div>
            <div class="text-right text-xs text-slate-700">
              <p class="font-semibold">
                Hadir {{ section.summary.attendees }}/{{ section.summary.registered }}
                <span class="text-slate-500">({{ formatRate(section.summary.attendance_rate) }})</span>
              </p>
            </div>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full border-collapse text-xs text-slate-800">
            <thead>
              <tr class="bg-slate-800 text-[10px] uppercase text-white">
                <th class="border border-slate-700 px-2 py-2 text-center w-10">No</th>
                <th class="border border-slate-700 px-2 py-2 text-left">Peserta</th>
                <th class="border border-slate-700 px-2 py-2 text-center">Kehadiran</th>
                <th class="border border-slate-700 px-2 py-2 text-center">Check-in</th>
                <th class="border border-slate-700 px-2 py-2 text-center">Method</th>
                <th
                  v-for="quiz in section.quizzes"
                  :key="quiz.id"
                  class="border border-slate-700 px-2 py-2 text-center min-w-[8rem]"
                >
                  {{ quiz.title }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, idx) in section.participants" :key="row.user_id" class="align-top">
                <td class="border border-slate-300 px-2 py-2 text-center">{{ idx + 1 }}</td>
                <td class="border border-slate-300 px-2 py-2 font-medium">{{ row.user_name }}</td>
                <td class="border border-slate-300 px-2 py-2 text-center">
                  <span
                    class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                    :class="row.attended ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                  >
                    {{ attendanceLabel(row) }}
                  </span>
                </td>
                <td class="border border-slate-300 px-2 py-2 text-center whitespace-nowrap">
                  {{ row.check_in_at || '—' }}
                </td>
                <td class="border border-slate-300 px-2 py-2 text-center">
                  {{ methodLabel(row.method) }}
                </td>
                <td
                  v-for="quiz in section.quizzes"
                  :key="`${row.user_id}-${quiz.id}`"
                  class="border border-slate-300 px-2 py-2 text-center"
                  :class="quizResultClass(row.quiz_results.find((r) => r.quiz_id === quiz.id))"
                >
                  {{ quizResultLabel(row.quiz_results.find((r) => r.quiz_id === quiz.id)) }}
                </td>
              </tr>
              <tr v-if="!section.participants.length">
                <td
                  :colspan="5 + section.quizzes.length"
                  class="border border-slate-300 px-4 py-6 text-center text-slate-500"
                >
                  Belum ada peserta pada training plan ini.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
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
