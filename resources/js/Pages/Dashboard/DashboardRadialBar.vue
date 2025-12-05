<template>
  <div class="bg-white rounded-xl p-4 shadow">
    <apexchart type="polarArea" :options="polarOptions" :series="series" height="260" />
  </div>
</template>
<script setup>
import { computed } from 'vue';
const props = defineProps({ data: Object, colors: Array });
const series = computed(() => Object.values(props.data));
const polarOptions = computed(() => ({
  labels: Object.keys(props.data),
  colors: props.colors && props.colors.length ? props.colors : ['#2563eb', '#22c55e', '#f59e42', '#ef4444', '#6366f1', '#fbbf24'],
  legend: {
    show: true,
    position: 'bottom',
    fontSize: '14px',
    fontWeight: 600,
    markers: { radius: 12 },
    formatter: function(seriesName, opts) {
      // Tampilkan label + jumlah task
      return seriesName + ': ' + opts.w.globals.series[opts.seriesIndex];
    }
  },
  stroke: { show: true, width: 2, colors: ['#fff'] },
  fill: { opacity: 0.9 },
  theme: { mode: 'light' },
  tooltip: {
    y: {
      formatter: function(val) {
        return val;
      }
    }
  },
}));
</script>
<style scoped>
.bg-white { background: #fff; }
</style> 