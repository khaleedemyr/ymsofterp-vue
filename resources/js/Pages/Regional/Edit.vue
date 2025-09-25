<template>
  <AppLayout>
    <div class="w-full px-4 py-8">
      <h1 class="text-2xl font-bold text-blue-700 mb-6 flex items-center gap-2">
        <i class="fa-solid fa-globe"></i>
        Edit Regional - {{ user.name }}
      </h1>
      
      <form @submit.prevent="submitForm">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">User</label>
            <div class="w-full border border-gray-200 rounded-lg px-4 py-3 bg-gray-50 text-gray-600">
              {{ user.name }} ({{ user.email }})
            </div>
          </div>
          
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Pilih Outlets</label>
            <div class="border border-blue-200 rounded-lg max-h-64 overflow-y-auto bg-white">
              <div v-if="outlets.length > 0" class="p-3 border-b border-gray-200 bg-gray-50">
                <label class="flex items-center cursor-pointer">
                  <input 
                    type="checkbox" 
                    v-model="selectAllOutlets"
                    @change="toggleSelectAll"
                    class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                  />
                  <span class="ml-2 text-sm font-medium text-gray-700">Pilih Semua Outlet</span>
                  <span class="ml-auto text-xs text-gray-500">{{ selectedOutlets.length }} dari {{ outlets.length }} dipilih</span>
                </label>
              </div>
              
              <div v-if="outlets.length > 0" class="divide-y divide-gray-200">
                <div v-for="outlet in outlets" :key="outlet.id_outlet" class="p-3 hover:bg-gray-50 transition-colors">
                  <label class="flex items-center cursor-pointer">
                    <input 
                      type="checkbox" 
                      :value="outlet.id_outlet"
                      v-model="selectedOutlets"
                      class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                    />
                    <span class="ml-3 text-sm font-medium text-gray-700">{{ outlet.nama_outlet }}</span>
                  </label>
                </div>
              </div>
              
              <div v-else class="p-6 text-center text-gray-500">
                <i class="fas fa-store text-2xl mb-2"></i>
                <p class="text-sm">Tidak ada outlet yang tersedia</p>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2 mt-8">
          <button type="button" @click="goBack" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-semibold shadow-sm">Batal</button>
          <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg font-bold shadow-lg hover:bg-blue-700 transition-all">Update</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  user: Object,
  currentOutlets: Array,
  outlets: Array
});

const form = ref({
  outlet_ids: []
});

const selectedOutlets = ref([]);
const selectAllOutlets = ref(false);

// Initialize selected outlets from current outlets
onMounted(() => {
  selectedOutlets.value = [...props.currentOutlets];
  form.value.outlet_ids = [...props.currentOutlets];
});

// Computed property untuk outlet yang dipilih
const selectedOutletIds = computed(() => {
  return selectedOutlets.value;
});

// Watch untuk update form.outlet_ids
watch(selectedOutlets, (newVal) => {
  form.value.outlet_ids = newVal;
}, { deep: true });

// Watch untuk select all
watch(selectAllOutlets, (newVal) => {
  if (newVal) {
    selectedOutlets.value = props.outlets.map(outlet => outlet.id_outlet);
  } else {
    selectedOutlets.value = [];
  }
});

// Watch untuk update select all berdasarkan selected outlets
watch(selectedOutlets, (newVal) => {
  selectAllOutlets.value = newVal.length > 0 && newVal.length === props.outlets.length;
}, { deep: true });

function toggleSelectAll() {
  if (selectAllOutlets.value) {
    selectedOutlets.value = props.outlets.map(outlet => outlet.id_outlet);
  } else {
    selectedOutlets.value = [];
  }
}

function submitForm() {
  if (form.value.outlet_ids.length === 0) {
    alert('Pilih minimal satu outlet');
    return;
  }

  router.put(`/regional/${props.user.id}`, form.value, {
    onSuccess: () => {
      // Success handled by controller redirect
    },
    onError: (errors) => {
      console.error('Validation errors:', errors);
    }
  });
}

function goBack() {
  router.get('/regional');
}
</script>
