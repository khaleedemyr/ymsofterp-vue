<template>
  <div class="bg-white rounded-xl p-4 shadow">
    <apexchart v-if="type === 'radial'" type="radialBar" :options="radialOptions" :series="series" height="260" />
    <apexchart v-else type="pie" :options="chartOptions" :series="series" height="260" />
  </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ data: Object, type: String, colors: Array });
const series = computed(() => Object.values(props.data));
const chartOptions = computed(() => ({
  chart: {
    animations: { enabled: true, easing: 'easeinout', speed: 900 },
    dropShadow: { enabled: true, top: 4, left: 0, blur: 8, color: '#000', opacity: 0.12 },
  },
  labels: Object.keys(props.data),
  colors: props.colors && props.colors.length ? props.colors : ['#2563eb', '#22c55e', '#f59e42', '#ef4444', '#6366f1', '#fbbf24'],
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
}));
const radialOptions = computed(() => ({
  chart: {
    animations: { enabled: true, easing: 'easeinout', speed: 900 },
    dropShadow: { enabled: true, top: 4, left: 0, blur: 8, color: '#000', opacity: 0.12 },
  },
  labels: Object.keys(props.data),
  colors: props.colors && props.colors.length ? props.colors : ['#2563eb', '#22c55e', '#f59e42', '#ef4444', '#6366f1', '#fbbf24'],
  legend: {
    show: true,
    position: 'bottom',
    fontSize: '14px',
    fontWeight: 600,
    markers: { radius: 12 },
  },
  plotOptions: {
    radialBar: {
      dataLabels: {
        name: { fontSize: '16px', fontWeight: 700 },
        value: {
          fontSize: '18px', fontWeight: 700,
          formatter: function(val, opts) {
            // Tampilkan value asli (jumlah task)
            return opts.w.config.series[opts.seriesIndex];
          }
        },
        total: {
          show: true,
          label: 'Total',
          formatter: function () {
            return series.value.reduce((a, b) => a + b, 0);
          }
        }
      }
    }
  },
  fill: { type: 'gradient' },
  theme: { mode: 'light' },
}));

function downloadReport() {
  const url = '/api/dashboard/maintenance/report';
  fetch(url, { headers: { 'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' } })
    .then(response => response.blob())
    .then(blob => {
      const link = document.createElement('a');
      link.href = window.URL.createObjectURL(blob);
      link.download = 'maintenance_report.xlsx';
      link.click();
      window.URL.revokeObjectURL(link.href);
    });
}
</script>
<style scoped>
.bg-white { background: #fff; }
</style> 