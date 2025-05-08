<template>
  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold text-blue-900">Maintenance Order</h1>
    <a href="/maintenance-order/schedule-calendar" class="btn-primary">
      <i class="fas fa-calendar-alt mr-2"></i> Lihat Kalender Jadwal
    </a>
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
import { ref, watch, onMounted, computed } from 'vue';
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
  const { data } = await axios.get('/api/outlet');
  outlets.value = data;
  console.log('Outlets fetched:', data);
}

async function fetchRukos(outletId) {
  console.log('Fetching rukos for outlet:', outletId);
  if (outletId == 1) {
    const { data } = await axios.get('/api/ruko', { params: { id_outlet: 1 } });
    rukos.value = data;
    console.log('Rukos fetched:', data);
  } else {
    rukos.value = [];
    console.log('Rukos cleared for non-outlet-1');
  }
}

async function onOutletChange(val) {
  console.log('Outlet changed to:', val);
  selectedOutlet.value = String(val);
  if (val != 1) selectedRuko.value = '';
  await fetchRukos(val);
  if (kanbanBoard.value?.refreshTasks) {
    console.log('Refreshing tasks after outlet change');
    await kanbanBoard.value.refreshTasks();
  }
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
  console.log('Component mounted, userOutlet:', userOutlet.value);
  await fetchOutlets();
  if (isOutletFixed.value) {
    selectedOutlet.value = String(userOutlet.value);
    await fetchRukos(userOutlet.value);
  } else if (userOutlet.value == 1) {
    selectedOutlet.value = '1';
    await fetchRukos(1);
  }
  document.title = 'Maintenance Order - YMSoft';
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