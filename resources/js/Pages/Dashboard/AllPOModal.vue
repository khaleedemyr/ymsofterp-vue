<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-y-auto relative animate-fadeIn">
      <button @click="$emit('close')" class="absolute top-3 right-3 text-gray-400 hover:text-red-500 text-xl z-10">
        <i class="fas fa-times"></i>
      </button>
      <div class="p-6">
        <h2 class="text-xl font-bold mb-4">Semua Purchase Order</h2>
        
        <!-- Search -->
        <div class="mb-4">
          <input type="text" v-model="search" placeholder="Cari PO..." 
            class="w-full md:w-64 px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm rounded-xl overflow-hidden">
            <thead class="bg-gray-100">
              <tr>
                <th class="py-2 px-3 text-left font-bold">PO Number</th>
                <th class="py-2 px-3 text-left font-bold">Task Number</th>
                <th class="py-2 px-3 text-left font-bold">Title</th>
                <th class="py-2 px-3 text-left font-bold">Supplier</th>
                <th class="py-2 px-3 text-left font-bold">Outlet</th>
                <th class="py-2 px-3 text-left font-bold">Status</th>
                <th class="py-2 px-3 text-right font-bold">Total</th>
                <th class="py-2 px-3 text-left font-bold">Tanggal</th>
                <th class="py-2 px-3 text-center font-bold">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="po in poList" :key="po.id" class="hover:bg-blue-50 transition">
                <td class="py-2 px-3 font-semibold text-blue-700">{{ po.po_number }}</td>
                <td class="py-2 px-3">{{ po.task_number || '-' }}</td>
                <td class="py-2 px-3">{{ po.title || '-' }}</td>
                <td class="py-2 px-3">{{ po.supplier_name || '-' }}</td>
                <td class="py-2 px-3">{{ po.nama_outlet || '-' }}</td>
                <td class="py-2 px-3">{{ po.status }}</td>
                <td class="py-2 px-3 text-right">{{ formatRupiah(po.total_amount) }}</td>
                <td class="py-2 px-3">{{ po.created_at ? po.created_at.substring(0,10) : '-' }}</td>
                <td class="py-2 px-3 text-center">
                  <button class="p-2 rounded hover:bg-blue-100 transition" @click="openPODetail(po)">
                    <i class="fas fa-eye text-blue-600"></i>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-between items-center mt-4">
          <div class="text-sm text-gray-500">
            Menampilkan {{ (currentPage - 1) * perPage + 1 }} - {{ Math.min(currentPage * perPage, total) }} dari {{ total }} data
          </div>
          <div class="flex gap-2">
            <button @click="prevPage" :disabled="currentPage === 1" 
              class="px-3 py-1 rounded border disabled:opacity-50 disabled:cursor-not-allowed">
              <i class="fas fa-chevron-left"></i>
            </button>
            <button @click="nextPage" :disabled="currentPage === lastPage" 
              class="px-3 py-1 rounded border disabled:opacity-50 disabled:cursor-not-allowed">
              <i class="fas fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({ show: Boolean });
const emit = defineEmits(['close']);

const poList = ref([]);
const currentPage = ref(1);
const perPage = ref(10);
const total = ref(0);
const lastPage = ref(1);
const search = ref('');
const loading = ref(false);
let searchTimeout = null;

async function fetchPOList() {
  loading.value = true;
  try {
    const res = await axios.get('/api/maintenance-po-latest', {
      params: {
        page: currentPage.value,
        perPage: perPage.value,
        search: search.value
      }
    });
    poList.value = res.data.data;
    total.value = res.data.total;
    lastPage.value = res.data.lastPage;
  } finally {
    loading.value = false;
  }
}

function formatRupiah(val) {
  if (!val) return 'Rp 0';
  return 'Rp ' + Number(val).toLocaleString('id-ID');
}

function prevPage() {
  if (currentPage.value > 1) {
    currentPage.value--;
    fetchPOList();
  }
}

function nextPage() {
  if (currentPage.value < lastPage.value) {
    currentPage.value++;
    fetchPOList();
  }
}

function openPODetail(po) {
  emit('open-detail', po);
}

watch(search, () => {
  currentPage.value = 1;
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchPOList();
  }, 300);
});

onMounted(() => {
  fetchPOList();
});
</script> 