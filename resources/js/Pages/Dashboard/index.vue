<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Dashboard from './Dashboard.vue';
import { onMounted } from 'vue';

onMounted(() => {
  document.title = 'MT Dashboard - YMSoft';
});
</script>

<template>
  <AppLayout>
    <Dashboard />
  </AppLayout>
</template> 