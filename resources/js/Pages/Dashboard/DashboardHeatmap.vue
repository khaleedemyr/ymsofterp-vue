<template>
  <div class="bg-white rounded-xl p-4 shadow">
    <h2 class="text-lg font-semibold mb-2">Heatmap Aktivitas Task</h2>
    <apexchart type="heatmap" :options="chartOptions" :series="series" height="300" />
  </div>
</template>
<script setup>
import { computed } from 'vue';
import VueApexCharts from 'vue3-apexcharts';
// Dummy: 7 hari x 4 minggu
const days = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const series = computed(() => Array.from({ length: 4 }, (_, week) => ({
  name: `Minggu ${week + 1}`,
  data: days.map(() => Math.floor(Math.random() * 10)),
})));
const chartOptions = computed(() => ({
  chart: { toolbar: { show: false } },
  xaxis: { categories: days },
  colors: ['#e0f2fe', '#38bdf8', '#0ea5e9'],
  dataLabels: { enabled: false },
  legend: { show: false },
}));
</script>
<style scoped>
.bg-white { background: #fff; }
</style> 