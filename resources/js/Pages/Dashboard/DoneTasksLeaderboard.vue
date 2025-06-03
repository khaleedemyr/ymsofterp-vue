<template>
  <div>
    <div v-if="loading" class="flex justify-center py-8"><i class="fas fa-spinner fa-spin text-2xl text-blue-400"></i></div>
    <div v-else>
      <!-- Top 3 Cards -->
      <div class="flex flex-wrap gap-4 mb-6">
        <div v-for="(member, idx) in top3" :key="member.id" class="flex-1 min-w-[180px] bg-gradient-to-br from-blue-50 to-indigo-100 rounded-xl shadow p-4 flex flex-col items-center relative">
          <div class="absolute -top-4 left-1/2 -translate-x-1/2 text-3xl">
            <span v-if="idx === 0">🥇</span>
            <span v-else-if="idx === 1">🥈</span>
            <span v-else-if="idx === 2">🥉</span>
          </div>
          <div class="text-xl font-bold mt-4">{{ member.name }}</div>
          <div class="text-2xl font-extrabold text-green-600 mt-2">{{ member.done }}</div>
          <div class="text-xs text-gray-500">Tasks Selesai</div>
          <div class="text-xs text-gray-400 mt-1">Total: {{ member.total }}</div>
          <div class="text-xs text-blue-600 mt-1 font-semibold">Produktivitas: {{ member.produktivitas }}%</div>
        </div>
      </div>
      <!-- List Ranking -->
      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <div class="font-semibold text-base mb-2">Ranking Semua Member</div>
        <table class="min-w-full text-sm">
          <thead>
            <tr>
              <th class="py-1 px-2 text-left">#</th>
              <th class="py-1 px-2 text-left">Nama</th>
              <th class="py-1 px-2 text-center">Tasks Selesai</th>
              <th class="py-1 px-2 text-center">Total Task</th>
              <th class="py-1 px-2 text-center">Produktivitas</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(member, idx) in rest" :key="member.id">
              <td class="py-1 px-2">{{ idx + 4 }}</td>
              <td class="py-1 px-2">{{ member.name }}</td>
              <td class="py-1 px-2 text-center font-bold text-green-700">{{ member.done }}</td>
              <td class="py-1 px-2 text-center text-gray-500">{{ member.total }}</td>
              <td class="py-1 px-2 text-center text-blue-600 font-semibold">{{ member.produktivitas }}%</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
const members = ref([]);
const loading = ref(true);
onMounted(async () => {
  loading.value = true;
  const res = await axios.get('/api/maintenance-tasks/leaderboard-done');
  members.value = res.data;
  loading.value = false;
});
const top3 = computed(() => members.value.slice(0, 3));
const rest = computed(() => members.value.slice(3));
</script>
<style scoped>
</style> 