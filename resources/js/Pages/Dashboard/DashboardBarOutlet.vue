<template>
  <div class="bg-white rounded-xl p-4 shadow">
    <h2 class="text-base font-semibold mb-2">Task per Outlet</h2>
    <apexchart type="bar" :options="chartOptions" :series="series" height="260" />
  </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ data: Object });
const series = computed(() => [{ name: 'Task', data: Object.values(props.data) }]);
const chartOptions = computed(() => ({
  chart: {
    toolbar: { show: false },
    animations: { enabled: true, easing: 'easeinout', speed: 900 },
    dropShadow: { enabled: true, top: 4, left: 0, blur: 8, color: '#000', opacity: 0.10 },
  },
  xaxis: { categories: Object.keys(props.data), labels: { style: { fontWeight: 600 } } },
  colors: ['#6366f1', '#22c55e', '#f59e42', '#ef4444', '#2563eb', '#fbbf24'],
  plotOptions: { bar: { borderRadius: 8, columnWidth: '40%', distributed: true } },
  dataLabels: { enabled: false },
  legend: { show: false },
  grid: { borderColor: '#e5e7eb', strokeDashArray: 4 },
  theme: { mode: 'light' },
}));
</script>
<style scoped>
.bg-white { background: #fff; }
</style> 