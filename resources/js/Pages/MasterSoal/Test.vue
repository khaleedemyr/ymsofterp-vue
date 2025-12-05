<template>
  <AppLayout>
    <div class="p-6">
      <h1 class="text-2xl font-bold mb-4">Test Master Soal</h1>
      
      <!-- Test Route Helper -->
      <div class="mb-4">
        <h2 class="text-lg font-semibold mb-2">Route Helper Test:</h2>
        <p>Index: {{ indexRoute }}</p>
        <p>Create: {{ createRoute }}</p>
      </div>
      
      <!-- Test Links -->
      <div class="space-y-2">
        <Link :href="indexRoute" class="bg-blue-500 text-white px-4 py-2 rounded">
          Go to Index
        </Link>
        <br>
        <Link :href="createRoute" class="bg-green-500 text-white px-4 py-2 rounded">
          Go to Create
        </Link>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

// Test route helper
const indexRoute = computed(() => {
  try {
    return route('master-soal.index');
  } catch (error) {
    console.error('Route helper error:', error);
    return '/master-soal';
  }
});

const createRoute = computed(() => {
  try {
    return route('master-soal.create');
  } catch (error) {
    console.error('Route helper error:', error);
    return '/master-soal/create';
  }
});
</script>
