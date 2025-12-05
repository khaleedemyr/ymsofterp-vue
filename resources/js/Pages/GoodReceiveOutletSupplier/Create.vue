<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-truck"></i> Buat Good Receive Outlet Supplier
        </h1>
        <button @click="router.visit('/good-receive-outlet-supplier')" class="bg-gray-500 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
          <i class="fa-solid fa-arrow-left mr-2"></i> Kembali
        </button>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6 max-w-2xl mx-auto">
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700 mb-2">Pilih RO Supplier atau Delivery Order</label>
          <select 
            v-model="selectedOption" 
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-400 focus:border-blue-400"
            @change="handleSelection"
          >
            <option value="">Pilih RO Supplier atau DO...</option>
            <optgroup label="RO Supplier">
              <option v-for="ro in roList" :key="ro.id" :value="'ro-' + ro.ro_number">
                {{ ro.ro_number }} - {{ ro.floor_order_number || '' }} - {{ ro.tanggal || '' }}
              </option>
            </optgroup>
            <optgroup label="Delivery Order (RO Supplier GR)">
              <option v-for="deliveryOrder in doList" :key="deliveryOrder.id" :value="'do-' + deliveryOrder.do_number">
                {{ deliveryOrder.do_number }} - {{ deliveryOrder.ro_gr_number || '' }} - {{ deliveryOrder.ro_floor_order_number || '' }} - {{ deliveryOrder.outlet_name || '' }}
              </option>
            </optgroup>
          </select>
          <div v-if="error" class="mt-2 text-sm text-red-600">{{ error }}</div>
        </div>

        <div v-if="loading" class="text-center py-8">
          <i class="fa fa-spinner fa-spin text-blue-400 text-2xl"></i>
          <p class="mt-2 text-gray-600">Memuat data...</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const selectedOption = ref('');
const roList = ref([]);
const doList = ref([]);
const error = ref('');
const loading = ref(false);

onMounted(async () => {
  loading.value = true;
  try {
    const [resRO, resDO] = await Promise.all([
      axios.get('/api/ro-suppliers'),
      axios.get('/api/delivery-orders-ro-supplier')
    ]);
    roList.value = resRO.data;
    doList.value = resDO.data;
  } catch (e) {
    console.error('Error fetching data:', e);
    error.value = 'Gagal memuat data';
  } finally {
    loading.value = false;
  }
});

const handleSelection = () => {
  if (!selectedOption.value) return;
  
  error.value = '';
  try {
    if (selectedOption.value.startsWith('ro-')) {
      const roNumber = selectedOption.value.replace('ro-', '');
      router.visit('/good-receive-outlet-supplier/create?ro_number=' + roNumber);
    } else if (selectedOption.value.startsWith('do-')) {
      const doNumber = selectedOption.value.replace('do-', '');
      const selectedDO = doList.value.find(deliveryOrder => deliveryOrder.do_number === doNumber);
      if (selectedDO) {
        router.visit('/good-receive-outlet-supplier/create-from-do/' + selectedDO.id);
      } else {
        error.value = 'Delivery Order tidak ditemukan';
      }
    }
  } catch (e) {
    error.value = 'Terjadi kesalahan';
  }
};
</script>
