<script setup>
import { ref, watch } from 'vue';
import { debounce } from 'lodash';

const props = defineProps({
  show: Boolean,
});

const emit = defineEmits(['close', 'customer-selected', 'customer-created']);

const searchQuery = ref('');
const customers = ref([]);
const showCreateForm = ref(false);
const isSearching = ref(false);
const isCreating = ref(false);

const newCustomer = ref({
  code: '',
  name: '',
  phone: '',
  email: '',
  address: '',
  type: 'customer',
  region: ''
});

const debouncedSearch = debounce(async () => {
  if (!searchQuery.value.trim()) {
    customers.value = [];
    return;
  }

  isSearching.value = true;
  try {
    const response = await fetch('/retail-warehouse-sale/search-customers', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({ search: searchQuery.value })
    });

    const result = await response.json();
    customers.value = result.customers || [];
  } catch (error) {
    console.error('Error searching customers:', error);
  } finally {
    isSearching.value = false;
  }
}, 300);

watch(searchQuery, () => {
  debouncedSearch();
});

function selectCustomer(customer) {
  emit('customer-selected', customer);
}

function toggleCreateForm() {
  showCreateForm.value = !showCreateForm.value;
  if (showCreateForm.value) {
    searchQuery.value = '';
    customers.value = [];
  }
}

async function createCustomer() {
  if (!newCustomer.value.code || !newCustomer.value.name) {
    alert('Kode dan nama customer harus diisi!');
    return;
  }

  isCreating.value = true;
  try {
    const response = await fetch('/retail-warehouse-sale/store-customer', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify(newCustomer.value)
    });

    const result = await response.json();
    
    if (result.success) {
      emit('customer-created', result.customer);
      resetForm();
    } else {
      alert('Gagal membuat customer: ' + result.message);
    }
  } catch (error) {
    console.error('Error creating customer:', error);
    alert('Terjadi kesalahan saat membuat customer');
  } finally {
    isCreating.value = false;
  }
}

function resetForm() {
  newCustomer.value = {
    code: '',
    name: '',
    phone: '',
    email: '',
    address: '',
    type: 'customer',
    region: ''
  };
  showCreateForm.value = false;
}

function closeModal() {
  emit('close');
  searchQuery.value = '';
  customers.value = [];
  showCreateForm.value = false;
  resetForm();
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
      <div class="p-6">
        <div class="flex justify-between items-center mb-6">
          <h3 class="text-2xl font-bold text-gray-900">Pilih Customer</h3>
          <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>

        <!-- Search Section -->
        <div v-if="!showCreateForm" class="mb-6">
          <div class="flex gap-2 mb-4">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Cari customer berdasarkan nama, kode, atau nomor telepon..."
              class="flex-1 rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
            />
            <button @click="toggleCreateForm" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
              <i class="fa-solid fa-plus mr-2"></i>
              Baru
            </button>
          </div>

          <!-- Search Results -->
          <div v-if="isSearching" class="text-center py-4">
            <i class="fa-solid fa-spinner fa-spin text-blue-500"></i>
            <p class="mt-2 text-gray-500">Mencari customer...</p>
          </div>

          <div v-else-if="customers.length > 0" class="space-y-2">
            <div
              v-for="customer in customers"
              :key="customer.id"
              @click="selectCustomer(customer)"
              class="p-3 border border-gray-200 rounded-lg hover:bg-blue-50 cursor-pointer transition"
            >
              <div class="font-semibold text-gray-800">{{ customer.name }}</div>
              <div class="text-sm text-gray-500">
                Kode: {{ customer.code }}
                <span v-if="customer.phone"> | Telp: {{ customer.phone }}</span>
              </div>
              <div v-if="customer.address" class="text-sm text-gray-400 mt-1">{{ customer.address }}</div>
            </div>
          </div>

          <div v-else-if="searchQuery" class="text-center py-4 text-gray-500">
            Tidak ada customer ditemukan
          </div>

          <div v-else class="text-center py-4 text-gray-500">
            Mulai mengetik untuk mencari customer
          </div>
        </div>

        <!-- Create Form -->
        <div v-else>
          <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-gray-800">Buat Customer Baru</h4>
            <button @click="toggleCreateForm" class="text-blue-500 hover:text-blue-700">
              <i class="fa-solid fa-arrow-left mr-2"></i>
              Kembali
            </button>
          </div>

          <form @submit.prevent="createCustomer" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kode Customer *</label>
                <input
                  v-model="newCustomer.code"
                  type="text"
                  required
                  maxlength="20"
                  class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Customer *</label>
                <input
                  v-model="newCustomer.name"
                  type="text"
                  required
                  maxlength="100"
                  class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon</label>
                <input
                  v-model="newCustomer.phone"
                  type="text"
                  maxlength="20"
                  class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input
                  v-model="newCustomer.email"
                  type="email"
                  maxlength="100"
                  class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipe</label>
                <select
                  v-model="newCustomer.type"
                  class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="customer">Customer</option>
                  <option value="branch">Branch</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Region</label>
                <input
                  v-model="newCustomer.region"
                  type="text"
                  maxlength="20"
                  class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
              <textarea
                v-model="newCustomer.address"
                rows="3"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500"
              ></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-4">
              <button
                type="button"
                @click="toggleCreateForm"
                class="px-4 py-2 rounded-lg bg-gray-100 text-gray-700 font-semibold hover:bg-gray-200"
              >
                Batal
              </button>
              <button
                type="submit"
                :disabled="isCreating"
                class="px-4 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 disabled:opacity-60"
              >
                <span v-if="isCreating">Menyimpan...</span>
                <span v-else>Simpan Customer</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template> 