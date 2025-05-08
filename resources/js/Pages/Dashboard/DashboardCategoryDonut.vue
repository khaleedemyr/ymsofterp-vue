<template>
  <div class="bg-white rounded-xl p-4 shadow">
  
    <apexchart type="donut" :options="chartOptions" :series="series" height="260" />
  </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ data: Object });
const series = computed(() => Object.values(props.data));
const chartOptions = computed(() => ({
  chart: {
    animations: { enabled: true, easing: 'easeinout', speed: 900 },
    dropShadow: { enabled: true, top: 4, left: 0, blur: 8, color: '#000', opacity: 0.12 },
  },
  labels: Object.keys(props.data),
  colors: ['#6366f1', '#22c55e', '#f59e42', '#ef4444', '#2563eb', '#fbbf24', '#a21caf', '#0ea5e9'],
  legend: {
    position: 'bottom',
    fontSize: '14px',
    fontWeight: 600,
    markers: { radius: 12 },
  },
  dataLabels: {
    enabled: true,
    style: { fontWeight: 700, fontSize: '16px' },
    formatter: (val) => val.toFixed(1) + '%',
    dropShadow: { enabled: true, top: 2, left: 0, blur: 4, color: '#000', opacity: 0.10 },
  },
  stroke: { show: true, width: 2, colors: ['#fff'] },
  fill: { type: 'gradient' },
  theme: { mode: 'light' },
  title: { text: undefined },
}));
</script>
<style scoped>
.bg-white { background: #fff; }
</style> 