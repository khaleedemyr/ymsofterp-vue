<template>
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-blue-900">Maintenance Order</h1>
    <div class="flex gap-3">
      <a href="/maintenance-order/list" class="btn-secondary">
        <i class="fas fa-list mr-2"></i> List View
      </a>
      <a href="/maintenance-order/schedule-calendar" class="btn-primary">
        <i class="fas fa-calendar-alt mr-2"></i> Lihat Kalender Jadwal
      </a>
    </div>
  </div>
  <div class="mb-2 text-sm text-gray-500">ID Outlet User Login: {{ userOutlet }}</div>
  <FilterBar
    :outlets="outlets"
    :rukos="rukos"
    :selectedOutlet="selectedOutlet"
    :selectedRuko="selectedRuko"
    :disableOutlet="isOutletFixed"
    :hideRuko="isOutletFixed"
    @update:outlet="onOutletChange"
    @update:ruko="onRukoChange"
  />
  <KanbanBoard 
    ref="kanbanBoard"
    :outlet="selectedOutlet" 
    :ruko="selectedRuko" 
  />
</template>

<script setup>
import { ref, watch, onMounted, computed, nextTick } from 'vue';
import axios from 'axios';
import FilterBar from './FilterBar.vue';
import KanbanBoard from './KanbanBoard.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { usePage } from '@inertiajs/vue3';

axios.defaults.baseURL = window.location.origin;

const page = usePage();
const userOutlet = computed(() => page.props.auth?.user?.id_outlet || '');
const kanbanBoard = ref(null);

const outlets = ref([]);
const rukos = ref([]);
const selectedOutlet = ref('');
const selectedRuko = ref('');

const isOutletFixed = computed(() => userOutlet.value && userOutlet.value != 1);

async function fetchOutlets() {
  console.log('Fetching outlets...');
  const { data } = await axios.get('/api/outlet/active');
  outlets.value = data;
  console.log('Outlets fetched:', data);
}

async function fetchRukos(outletId) {
  console.log('Fetching rukos for outlet:', outletId);
  try {
    if (outletId == 1) {
      // Test endpoint first
      console.log('Testing ruko endpoint...');
      const testResponse = await axios.get('/api/ruko/test');
      console.log('Ruko test response:', testResponse.data);
      
      const response = await axios.get('/api/ruko', { params: { id_outlet: 1 } });
      console.log('Rukos API response:', response);
      rukos.value = response.data;
      console.log('Rukos fetched:', response.data);
      console.log('Rukos count:', rukos.value.length);
    } else {
      rukos.value = [];
      console.log('Rukos cleared for non-outlet-1');
    }
  } catch (error) {
    console.error('Error fetching rukos:', error);
    console.error('Error response:', error.response);
    rukos.value = [];
  }
}

async function onOutletChange(val) {
  console.log('=== OUTLET CHANGE START ===');
  console.log('Outlet changed to:', val);
  selectedOutlet.value = String(val);
  
  // Reset ruko selection when outlet changes
  selectedRuko.value = '';
  console.log('Ruko selection reset');
  
  // Fetch rukos for the new outlet
  await fetchRukos(val);
  
  // If outlet is 1 and we have rukos, select the first one automatically
  if (val == 1 && rukos.value.length > 0) {
    selectedRuko.value = rukos.value[0].id_ruko;
    console.log('Auto-selected first ruko:', selectedRuko.value);
    
    // Wait for next tick to ensure state is updated
    await nextTick();
    console.log('After nextTick - selectedRuko:', selectedRuko.value);
  }
  
  console.log('Final state - selectedOutlet:', selectedOutlet.value, 'selectedRuko:', selectedRuko.value);
  console.log('Rukos array state:', rukos.value);
  console.log('Rukos length:', rukos.value.length);
  if (rukos.value.length > 0) {
    console.log('First ruko data:', rukos.value[0]);
  }
  
  if (kanbanBoard.value?.refreshTasks) {
    console.log('Refreshing tasks after outlet change');
    await kanbanBoard.value.refreshTasks();
  }
  
  console.log('=== OUTLET CHANGE END ===');
}

async function onRukoChange(val) {
  console.log('Ruko changed to:', val);
  selectedRuko.value = val;
  if (kanbanBoard.value?.refreshTasks) {
    console.log('Refreshing tasks after ruko change');
    await kanbanBoard.value.refreshTasks();
  }
}

onMounted(async () => {
  console.log('=== COMPONENT MOUNTED START ===');
  console.log('Component mounted, userOutlet:', userOutlet.value);
  await fetchOutlets();
  
  if (isOutletFixed.value) {
    selectedOutlet.value = String(userOutlet.value);
    await fetchRukos(userOutlet.value);
  } else if (userOutlet.value == 1) {
    selectedOutlet.value = '1';
    await fetchRukos(1);
    
    // Auto-select first ruko if available
    if (rukos.value.length > 0) {
      selectedRuko.value = rukos.value[0].id_ruko;
      console.log('Auto-selected first ruko on mount:', selectedRuko.value);
      
      // Wait for next tick to ensure state is updated
      await nextTick();
      console.log('After nextTick - selectedRuko:', selectedRuko.value);
    }
  }
  
  console.log('Final mounted state - selectedOutlet:', selectedOutlet.value, 'selectedRuko:', selectedRuko.value);
  console.log('Rukos array state:', rukos.value);
  console.log('Rukos length:', rukos.value.length);
  if (rukos.value.length > 0) {
    console.log('First ruko data:', rukos.value[0]);
  }
  document.title = 'Maintenance Order - YMSoft';
  console.log('=== COMPONENT MOUNTED END ===');
});

watch(userOutlet, (val) => {
  if (isOutletFixed.value) {
    selectedOutlet.value = String(val);
    fetchRukos(val);
  } else if (val == 1) {
    selectedOutlet.value = '1';
    fetchRukos(1);
  }
});
</script>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default {
  layout: AppLayout
}
</script>

<style scoped>
.btn-primary {
  @apply px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors;
}

.btn-secondary {
  @apply px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors;
}
</style> 