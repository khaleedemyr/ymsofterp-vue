<template>
  <div class="bg-white rounded-xl p-4 shadow">
    <h2 class="text-base font-semibold mb-2">Tasks per Member</h2>
    <apexchart type="bar" :options="chartOptions" :series="series" height="260" />
  </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ data: Array });
const series = computed(() => [
  { name: 'Total Tasks', data: props.data.map(m => m.total) },
  { name: 'Completed Tasks', data: props.data.map(m => m.done) },
]);
const chartOptions = computed(() => ({
  chart: {
    type: 'bar',
    toolbar: { show: false },
    animations: { enabled: true, easing: 'easeinout', speed: 900 },
  },
  plotOptions: {
    bar: {
      horizontal: true,
      borderRadius: 8,
      barHeight: '60%',
      dataLabels: { position: 'top' },
    },
  },
  colors: ['#6366f1', '#22c55e'],
  xaxis: {
    categories: props.data.map(m => m.name),
    labels: { style: { fontWeight: 600 } },
  },
  legend: { position: 'top', fontWeight: 700 },
  grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
  dataLabels: { enabled: false },
  theme: { mode: 'light' },
}));
</script>
<style scoped>
.bg-white { background: #fff; }
</style> 