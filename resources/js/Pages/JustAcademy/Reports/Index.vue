<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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
  <AppLayout title="Reports — Just Academy">
    <div class="max-w-[100rem] w-full mx-auto py-8 px-2 space-y-8">
      <h1 class="text-2xl font-bold">Laporan</h1>

      <div class="flex flex-wrap gap-3 bg-white rounded-2xl shadow p-4">
        <select v-model="scheduleId" class="border rounded-xl px-3 py-2">
          <option value="">Semua jadwal</option>
          <option v-for="s in schedules" :key="s.id" :value="s.id">{{ s.title }} ({{ s.start_at }})</option>
        </select>
        <input v-model="from" type="date" class="border rounded-xl px-3 py-2" />
        <input v-model="to" type="date" class="border rounded-xl px-3 py-2" />
        <button type="button" class="bg-indigo-600 text-white px-4 py-2 rounded-xl" @click="applyFilters">Filter</button>
      </div>

      <div class="bg-white rounded-2xl shadow overflow-hidden">
        <h2 class="font-semibold px-4 py-3 border-b">Kehadiran</h2>
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left">Karyawan</th>
              <th class="px-4 py-2 text-left">Jadwal</th>
              <th class="px-4 py-2 text-left">Check-in</th>
              <th class="px-4 py-2 text-left">Metode</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in attendance.data" :key="a.id" class="border-t">
              <td class="px-4 py-2">{{ a.user?.name }}</td>
              <td class="px-4 py-2">{{ a.schedule?.title }}</td>
              <td class="px-4 py-2">{{ a.check_in_at }}</td>
              <td class="px-4 py-2">{{ a.method }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="bg-white rounded-2xl shadow overflow-hidden">
        <h2 class="font-semibold px-4 py-3 border-b">Quiz / Completion</h2>
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-2 text-left">Karyawan</th>
              <th class="px-4 py-2 text-left">Quiz</th>
              <th class="px-4 py-2 text-left">Nilai</th>
              <th class="px-4 py-2 text-left">Lulus</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in completions.data" :key="c.id" class="border-t">
              <td class="px-4 py-2">{{ c.user?.name }}</td>
              <td class="px-4 py-2">{{ c.quiz?.title }}</td>
              <td class="px-4 py-2">{{ c.score }}</td>
              <td class="px-4 py-2">{{ c.passed ? 'Ya' : 'Tidak' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AppLayout>
</template>
