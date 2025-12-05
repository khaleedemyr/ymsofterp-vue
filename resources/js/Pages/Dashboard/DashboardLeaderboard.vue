<template>
  <div class="bg-white rounded-xl p-4 shadow">
    <h2 class="text-lg font-semibold mb-2">Top 5 Member (Task Selesai)</h2>
    <ol class="space-y-2">
      <li v-for="(member, idx) in members" :key="member.id" class="flex items-center gap-3">
        <span class="font-bold text-lg w-6 text-right">{{ idx + 1 }}</span>
        <span class="flex-1">{{ member.name }}</span>
        <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-semibold">{{ member.done }} selesai</span>
      </li>
    </ol>
  </div>
</template>
<script setup>
const props = defineProps({ members: Array });
</script>
<style scoped>
.bg-white { background: #fff; }
</style> 