<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import JaLayout from '@/Components/JustAcademy/JaLayout.vue';
import { jaUi } from '@/composables/useJustAcademyUi';

const props = defineProps({ schedules: Array, attendance: Object, completions: Object, filters: Object });

const scheduleId = ref(props.filters?.schedule_id || '');
const from = ref(props.filters?.from || '');
const to = ref(props.filters?.to || '');

function applyFilters() {
  router.get(route('just-academy.reports.index'), {
    schedule_id: scheduleId.value || undefined,
    from: from.value || undefined,
    to: to.value || undefined,
  }, { preserveState: true });
}
</script>

<template>
  <JaLayout title="Laporan" subtitle="Kehadiran dan hasil quiz training" icon="fa-solid fa-chart-column">
    <div :class="[jaUi.card, 'mb-6 flex flex-wrap gap-3 p-4']">
      <select v-model="scheduleId" :class="jaUi.select">
        <option value="">Semua jadwal</option>
        <option v-for="s in schedules" :key="s.id" :value="s.id">{{ s.title }} ({{ s.start_at }})</option>
      </select>
      <input v-model="from" type="date" :class="jaUi.input" class="w-auto" />
      <input v-model="to" type="date" :class="jaUi.input" class="w-auto" />
      <button type="button" :class="jaUi.btnPrimary" @click="applyFilters">Filter</button>
    </div>

    <div :class="[jaUi.tableWrap, 'mb-8']">
      <h2 class="border-b border-slate-100 px-5 py-3 font-semibold text-slate-800">Kehadiran</h2>
      <table :class="jaUi.table">
        <thead :class="jaUi.thead">
          <tr>
            <th :class="jaUi.th">Karyawan</th>
            <th :class="jaUi.th">Jadwal</th>
            <th :class="jaUi.th">Check-in</th>
            <th :class="jaUi.th">Metode</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in attendance.data" :key="a.id" :class="jaUi.tr">
            <td :class="jaUi.td">{{ a.user?.name }}</td>
            <td :class="jaUi.td">{{ a.schedule?.title }}</td>
            <td :class="jaUi.td">{{ a.check_in_at }}</td>
            <td :class="jaUi.td">{{ a.method }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div :class="jaUi.tableWrap">
      <h2 class="border-b border-slate-100 px-5 py-3 font-semibold text-slate-800">Quiz / Completion</h2>
      <table :class="jaUi.table">
        <thead :class="jaUi.thead">
          <tr>
            <th :class="jaUi.th">Karyawan</th>
            <th :class="jaUi.th">Quiz</th>
            <th :class="jaUi.th">Nilai</th>
            <th :class="jaUi.th">Lulus</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in completions.data" :key="c.id" :class="jaUi.tr">
            <td :class="jaUi.td">{{ c.user?.name }}</td>
            <td :class="jaUi.td">{{ c.quiz?.title }}</td>
            <td :class="jaUi.td">{{ c.score }}</td>
            <td :class="jaUi.td">{{ c.passed ? 'Ya' : 'Tidak' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </JaLayout>
</template>
