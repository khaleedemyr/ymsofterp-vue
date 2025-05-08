<template>
  <div class="bg-white rounded-xl p-4 shadow">
   
    <apexchart type="line" :options="chartOptions" :series="series" height="260" />
  </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ data: Object });
const series = computed(() => [
  { name: 'Task Masuk', data: props.data.created || [] },
  { name: 'Task Selesai', data: props.data.done || [] },
]);
const chartOptions = computed(() => ({
  chart: {
    id: 'trend-line',
    toolbar: { show: false },
    animations: { enabled: true, easing: 'easeinout', speed: 900 },
    dropShadow: { enabled: true, top: 4, left: 0, blur: 8, color: '#000', opacity: 0.10 },
  },
  xaxis: { categories: props.data.labels || [], labels: { style: { fontWeight: 600 } } },
  colors: ['#2563eb', '#22c55e'],
  stroke: { curve: 'smooth', width: 4 },
  markers: { size: 7, strokeWidth: 3, strokeColors: '#fff', hover: { size: 10 } },
  legend: { position: 'top', fontWeight: 700 },
  grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
  dataLabels: { enabled: false },
  theme: { mode: 'light' },
}));
</script>
<style scoped>
.bg-white { background: #fff; }
</style> 