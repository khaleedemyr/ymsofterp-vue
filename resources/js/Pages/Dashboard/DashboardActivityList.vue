<template>
  <div class="flex flex-col gap-3">
    <div v-for="activity in activities" :key="activity.id" class="flex items-start gap-3 p-3 rounded-xl hover:bg-blue-50 transition">
      <div class="flex-shrink-0">
        <span class="user-badge">{{ activity.user_initials }}</span>
      </div>
      <div class="flex-1">
        <div class="font-semibold text-sm">{{ activity.user_name }} <span class="text-xs font-normal text-gray-400">{{ activity.type }}</span></div>
        <div class="text-xs text-gray-500">{{ activity.description }}</div>
        <div class="text-xs text-blue-400 mt-1">{{ activity.time_ago }}</div>
      </div>
    </div>
  </div>
</template>
<script setup>
const props = defineProps({ activities: Array });
</script>
<style scoped>
.user-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.2rem;
  height: 2.2rem;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1 60%, #a5b4fc 100%);
  color: #fff;
  font-weight: 700;
  font-size: 1.1rem;
  box-shadow: 0 2px 8px #e0e7ef44;
}
</style> 