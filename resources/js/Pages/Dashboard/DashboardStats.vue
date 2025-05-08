<template>
  <div class="flex flex-row gap-3 w-full">
    <div v-for="(stat, idx) in stats" :key="stat.label" :class="['stat-card', cardColors[idx % cardColors.length], 'flex-1', 'min-w-[120px]', 'px-4']">
      <div class="stat-icon">
        <component :is="icons[idx % icons.length]" class="w-5 h-5" />
      </div>
      <div class="stat-value">{{ stat.value }}</div>
      <div class="stat-label whitespace-normal break-words text-base">{{ stat.label }}</div>
    </div>
  </div>
</template>
<script setup>
import { h } from 'vue';
const props = defineProps({ stats: Array });
const icons = [
  {
    render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [h('path', { d: 'M12 8v4l3 3', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' })]); }
  },
  {
    render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [h('circle', { cx: 12, cy: 12, r: 10, stroke: 'currentColor', 'stroke-width': 2 }), h('path', { d: 'M12 6v6l4 2', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' })]); }
  },
  {
    render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [h('rect', { x: 3, y: 3, width: 18, height: 18, rx: 2, stroke: 'currentColor', 'stroke-width': 2 }), h('path', { d: 'M16 8v8M8 8v8', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' })]); }
  },
  {
    render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [h('path', { d: 'M3 12h18', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' })]); }
  },
  {
    render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [h('circle', { cx: 12, cy: 12, r: 10, stroke: 'currentColor', 'stroke-width': 2 }), h('path', { d: 'M8 12l2 2 4-4', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' })]); }
  },
  {
    render() { return h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [h('path', { d: 'M12 20l9-5-9-5-9 5 9 5z', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' })]); }
  },
];
const cardColors = [
  'bg-gradient-to-br from-blue-100 to-blue-300',
  'bg-gradient-to-br from-green-100 to-green-300',
  'bg-gradient-to-br from-yellow-100 to-yellow-300',
  'bg-gradient-to-br from-purple-100 to-purple-300',
  'bg-gradient-to-br from-pink-100 to-pink-300',
  'bg-gradient-to-br from-indigo-100 to-indigo-300',
];
</script>
<style scoped>
.stat-card {
  border-radius: 1.5rem;
  box-shadow: 0 4px 16px #e0e7ef44;
  padding: 1.5rem 1rem 1rem 1rem;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  min-height: 120px;
  position: relative;
  overflow: hidden;
}
.stat-icon {
  position: absolute;
  top: 1rem;
  right: 1rem;
  opacity: 0.10;
}
.stat-value {
  font-size: 2.2rem;
  font-weight: 800;
  color: #22223b;
  z-index: 1;
}
.stat-label {
  color: #4b5563;
  margin-top: 0.5rem;
  font-size: 1.05rem;
  font-weight: 500;
  z-index: 1;
}
</style>