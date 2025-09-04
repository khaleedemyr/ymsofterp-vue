<template>
  <div class="flex flex-col md:flex-row gap-2 mb-4">
    <select v-model="localOutlet" @change="onOutletChange" class="border rounded p-2" :disabled="props.disableOutlet">
      <option value="" disabled>{{ $t('kanban.pilih_outlet') }}</option>
      <option v-for="o in outlets" :key="o.id_outlet" :value="String(o.id_outlet)">{{ o.nama_outlet }}</option>
    </select>
    <select v-if="showRuko && !props.hideRuko" v-model="localRuko" @change="onRukoChange" class="border rounded p-2">
      <option value="" disabled>{{ $t('kanban.pilih_ruko') }}</option>
      <option v-for="r in rukos" :key="r.id_ruko" :value="r.id_ruko">{{ r.nama_ruko }}</option>
    </select>
    <button 
      @click="loadTasks"
      class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors"
      :disabled="!canLoadTasks"
      :class="{'opacity-50 cursor-not-allowed': !canLoadTasks}"
    >
      <i class="fas fa-sync-alt mr-2"></i>
      Load Task
    </button>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  outlets: Array,
  rukos: Array,
  selectedOutlet: [String, Number],
  selectedRuko: [String, Number],
  disableOutlet: Boolean,
  hideRuko: Boolean,
});

const emit = defineEmits(['update:outlet', 'update:ruko']);
const localOutlet = ref(String(props.selectedOutlet));
const localRuko = ref(props.selectedRuko);
const showRuko = computed(() => localOutlet.value == "1");

// Debug logging
console.log('FilterBar props:', {
  outlets: props.outlets,
  rukos: props.rukos,
  selectedOutlet: props.selectedOutlet,
  selectedRuko: props.selectedRuko,
  showRuko: showRuko.value
});

const canLoadTasks = computed(() => {
  if (!localOutlet.value) return false;
  if (localOutlet.value == "1" && !localRuko.value) return false;
  return true;
});

watch(() => props.selectedOutlet, v => localOutlet.value = String(v));
watch(() => props.selectedRuko, v => localRuko.value = v);

function onOutletChange() {
  emit('update:outlet', localOutlet.value);
  if (localOutlet.value != "1") emit('update:ruko', null);
}

function onRukoChange() {
  emit('update:ruko', localRuko.value);
}

function loadTasks() {
  if (!canLoadTasks.value) {
    Swal.fire({
      title: 'Perhatian',
      text: localOutlet.value == "1" ? 'Silakan pilih outlet dan ruko terlebih dahulu!' : 'Silakan pilih outlet terlebih dahulu!',
      icon: 'warning'
    });
    return;
  }
  
  emit('update:outlet', localOutlet.value);
  if (localOutlet.value == "1") {
    emit('update:ruko', localRuko.value);
  }
}
</script> 