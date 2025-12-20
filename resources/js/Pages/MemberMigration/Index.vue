<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-database"></i> Migrasi Data Member
        </h1>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-blue-500">
          <div class="text-sm text-gray-600 mb-1">Total Customer</div>
          <div class="text-2xl font-bold text-gray-800">{{ stats.total }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-green-500">
          <div class="text-sm text-gray-600 mb-1">Siap Migrasi</div>
          <div class="text-2xl font-bold text-green-600">{{ stats.ready }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-purple-500">
          <div class="text-sm text-gray-600 mb-1">Sudah Migrasi</div>
          <div class="text-2xl font-bold text-purple-600">{{ stats.migrated }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-yellow-500">
          <div class="text-sm text-gray-600 mb-1">Tidak Ada Email</div>
          <div class="text-2xl font-bold text-yellow-600">{{ stats.no_email }}</div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="flex gap-4 items-end">
          <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700 mb-2">Cari</label>
            <input
              type="text"
              v-model="search"
              @input="onSearchInput"
              placeholder="Cari nama, email, ID, atau telepon..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div class="flex gap-2">
            <button
              v-if="selectedCustomers.length > 0"
              @click="migrateSelected"
              :disabled="loading"
              class="px-6 py-2 bg-gradient-to-r from-blue-500 to-blue-700 text-white rounded-lg font-semibold hover:from-blue-600 hover:to-blue-800 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="loading" class="fa fa-spinner fa-spin"></i>
              <i v-else class="fa fa-check-square"></i>
              Migrasi Terpilih ({{ selectedCustomers.length }})
            </button>
            <button
              @click="migrateAllReady"
              :disabled="loading || stats.ready === 0"
              class="px-6 py-2 bg-gradient-to-r from-green-500 to-green-700 text-white rounded-lg font-semibold hover:from-green-600 hover:to-green-800 transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <i v-if="loading" class="fa fa-spinner fa-spin"></i>
              <i v-else class="fa fa-database"></i>
              Migrasi Semua yang Siap
            </button>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gradient-to-r from-blue-500 to-blue-700 text-white">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">
                  <input
                    type="checkbox"
                    v-model="selectAll"
                    @change="toggleSelectAll"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                </th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">ID</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Nama</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Email</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Telepon</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Tanggal Lahir</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Jenis Kelamin</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Pekerjaan</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="customer in customers.data" :key="customer.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <input
                    type="checkbox"
                    v-model="selectedCustomers"
                    :value="customer.id"
                    :disabled="!customer.can_migrate"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ customer.costumers_id }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ customer.name }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ customer.email || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ customer.telepon || '-' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ customer.tanggal_lahir ? formatDate(customer.tanggal_lahir) : '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ customer.jenis_kelamin_text || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ customer.pekerjaan || '-' }}
                  <span v-if="customer.pekerjaan_id_mapped" class="text-green-600 text-xs ml-1">
                    <i class="fa fa-check"></i>
                  </span>
                  <span v-else-if="customer.pekerjaan" class="text-yellow-600 text-xs ml-1">
                    <i class="fa fa-exclamation-triangle"></i>
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span
                    :class="{
                      'bg-green-100 text-green-800': customer.migration_status === 'ready',
                      'bg-purple-100 text-purple-800': customer.migration_status === 'migrated',
                      'bg-yellow-100 text-yellow-800': customer.migration_status === 'no_email'
                    }"
                    class="px-2 py-1 rounded-full text-xs font-semibold"
                  >
                    {{ getStatusText(customer.migration_status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <button
                    v-if="customer.can_migrate"
                    @click="migrateSingle(customer.id)"
                    :disabled="loading"
                    class="text-blue-600 hover:text-blue-800 font-semibold disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <i class="fa fa-upload mr-1"></i> Migrasi
                  </button>
                  <span v-else-if="customer.is_migrated" class="text-green-600">
                    <i class="fa fa-check-circle"></i> Sudah Migrasi
                  </span>
                  <span v-else class="text-gray-400">
                    <i class="fa fa-ban"></i> Tidak Dapat Migrasi
                  </span>
                </td>
              </tr>
              <tr v-if="customers.data.length === 0">
                <td colspan="10" class="px-6 py-4 text-center text-gray-500">
                  Tidak ada data
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="customers.links && customers.links.length > 3" class="bg-gray-50 px-4 py-3 border-t border-gray-200">
          <div class="flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Menampilkan {{ customers.from }} sampai {{ customers.to }} dari {{ customers.total }} data
            </div>
            <div class="flex gap-2">
              <button
                v-for="link in customers.links"
                :key="link.label"
                @click="goToPage(link.url)"
                :disabled="!link.url"
                v-html="link.label"
                :class="{
                  'px-4 py-2 rounded-lg font-semibold': true,
                  'bg-blue-500 text-white': link.active,
                  'bg-white text-gray-700 hover:bg-gray-100': !link.active && link.url,
                  'bg-gray-100 text-gray-400 cursor-not-allowed': !link.url
                }"
              ></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  customers: Object,
  filters: Object,
  stats: Object
});

const search = ref(props.filters?.search || '');
const loading = ref(false);
const selectedCustomers = ref([]);

const selectAll = computed({
  get: () => {
    const readyCustomers = props.customers.data.filter(c => c.can_migrate);
    return readyCustomers.length > 0 && readyCustomers.every(c => selectedCustomers.value.includes(c.id));
  },
  set: (value) => {
    if (value) {
      const readyCustomerIds = props.customers.data.filter(c => c.can_migrate).map(c => c.id);
      selectedCustomers.value = [...new Set([...selectedCustomers.value, ...readyCustomerIds])];
    } else {
      const currentPageIds = props.customers.data.map(c => c.id);
      selectedCustomers.value = selectedCustomers.value.filter(id => !currentPageIds.includes(id));
    }
  }
});

function toggleSelectAll() {
  selectAll.value = !selectAll.value;
}

const debouncedSearch = debounce(() => {
  router.get('/member-migration', {
    search: search.value
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) router.visit(url, { preserveState: true, replace: true });
}

function formatDate(date) {
  if (!date) return '-';
  const d = new Date(date);
  return d.toLocaleDateString('id-ID');
}

function getStatusText(status) {
  const statusMap = {
    'ready': 'Siap Migrasi',
    'migrated': 'Sudah Migrasi',
    'no_email': 'Tidak Ada Email'
  };
  return statusMap[status] || status;
}

async function migrateSingle(customerId) {
  const result = await Swal.fire({
    title: 'Migrasi Member?',
    text: 'Yakin ingin migrasi member ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Migrasi',
    cancelButtonText: 'Batal'
  });

  if (!result.isConfirmed) return;

  loading.value = true;
  try {
    const response = await axios.post(`/member-migration/${customerId}/migrate`);
    
    if (response.data.success) {
      await Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: response.data.message
      });
      router.reload();
    } else {
      await Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: response.data.message
      });
    }
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.response?.data?.message || 'Gagal migrasi member'
    });
  } finally {
    loading.value = false;
  }
}

async function migrateSelected() {
  if (selectedCustomers.value.length === 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Tidak Ada Pilihan',
      text: 'Silakan pilih customer yang akan di-migrasi'
    });
    return;
  }

  // Filter only ready customers
  const readySelected = selectedCustomers.value.filter(id => {
    const customer = props.customers.data.find(c => c.id === id);
    return customer && customer.can_migrate;
  });

  if (readySelected.length === 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Tidak Ada yang Siap',
      text: 'Customer yang dipilih tidak dapat di-migrasi'
    });
    return;
  }

  const result = await Swal.fire({
    title: 'Migrasi Terpilih?',
    text: `Yakin ingin migrasi ${readySelected.length} member yang dipilih?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Ya, Migrasi',
    cancelButtonText: 'Batal'
  });

  if (!result.isConfirmed) return;

  loading.value = true;
  try {
    const response = await axios.post('/member-migration/migrate-multiple', {
      customer_ids: readySelected
    });
    
    if (response.data.success) {
      await Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        html: `
          <p>${response.data.message}</p>
          ${response.data.errors && response.data.errors.length > 0 ? `
            <div class="mt-4 text-left">
              <p class="font-semibold">Error Details:</p>
              <ul class="list-disc list-inside text-sm mt-2">
                ${response.data.errors.slice(0, 10).map(e => `<li>${e}</li>`).join('')}
                ${response.data.errors.length > 10 ? `<li>... dan ${response.data.errors.length - 10} error lainnya</li>` : ''}
              </ul>
            </div>
          ` : ''}
        `,
        width: '600px'
      });
      selectedCustomers.value = [];
      router.reload();
    } else {
      await Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: response.data.message
      });
    }
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Gagal',
      text: error.response?.data?.message || 'Gagal migrasi member'
    });
  } finally {
    loading.value = false;
  }
}

async function migrateAllReady() {
  // Get all ready customers from all pages, not just current page
  // We need to fetch all ready customers from the backend
  loading.value = true;
  try {
    // Fetch all ready customer IDs from backend
    const response = await axios.get('/member-migration/get-ready-customers');
    const readyCustomerIds = response.data.customer_ids || [];
    
    if (readyCustomerIds.length === 0) {
      await Swal.fire({
        icon: 'warning',
        title: 'Tidak Ada Data',
        text: 'Tidak ada customer yang siap untuk di-migrasi'
      });
      loading.value = false;
      return;
    }

    // Warn if too many customers
    if (readyCustomerIds.length > 500) {
      const result = await Swal.fire({
        title: 'Migrasi Besar Terdeteksi',
        html: `
          <p>Ditemukan <strong>${readyCustomerIds.length} member</strong> yang siap untuk di-migrasi.</p>
          <p class="text-sm text-gray-600 mt-2">Untuk migrasi besar, disarankan menggunakan command line untuk menghindari timeout:</p>
          <div class="bg-gray-100 p-3 rounded mt-2 text-left">
            <code class="text-xs">php artisan members:migrate --all --chunk=50</code>
          </div>
          <p class="text-sm text-gray-600 mt-2">Atau lanjutkan dengan migrasi batch (maksimal 500 per batch)?</p>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Lanjutkan Batch',
        cancelButtonText: 'Batal'
      });

      if (!result.isConfirmed) {
        loading.value = false;
        return;
      }
      
      // Process in batches of 500
      let processed = 0;
      let totalSuccess = 0;
      let totalFailed = 0;
      const allErrors = [];
      
      while (processed < readyCustomerIds.length) {
        const batch = readyCustomerIds.slice(processed, processed + 500);
        
        try {
          const migrateResponse = await axios.post('/member-migration/migrate-multiple', {
            customer_ids: batch
          });
          
          if (migrateResponse.data.success) {
            totalSuccess += migrateResponse.data.success_count || 0;
            totalFailed += migrateResponse.data.failed_count || 0;
            if (migrateResponse.data.errors) {
              allErrors.push(...migrateResponse.data.errors);
            }
          }
        } catch (error) {
          totalFailed += batch.length;
          allErrors.push(`Batch ${Math.floor(processed / 500) + 1}: ${error.response?.data?.message || 'Gagal migrasi batch'}`);
        }
        
        processed += batch.length;
        
        // Show progress
        await Swal.fire({
          title: 'Sedang Memproses...',
          html: `Memproses ${processed} dari ${readyCustomerIds.length} member...`,
          icon: 'info',
          allowOutsideClick: false,
          showConfirmButton: false,
          timer: 2000
        });
      }
      
      await Swal.fire({
        icon: 'success',
        title: 'Migrasi Selesai',
        html: `
          <p>Berhasil: ${totalSuccess}</p>
          <p>Gagal: ${totalFailed}</p>
          ${allErrors.length > 0 ? `
            <div class="mt-4 text-left">
              <p class="font-semibold">Error Details (${Math.min(allErrors.length, 10)} dari ${allErrors.length}):</p>
              <ul class="list-disc list-inside text-sm mt-2 max-h-60 overflow-y-auto">
                ${allErrors.slice(0, 10).map(e => `<li>${e}</li>`).join('')}
                ${allErrors.length > 10 ? `<li>... dan ${allErrors.length - 10} error lainnya</li>` : ''}
              </ul>
            </div>
          ` : ''}
        `,
        width: '600px'
      });
      
      router.reload();
      loading.value = false;
      return;
    }

    const result = await Swal.fire({
      title: 'Migrasi Semua?',
      html: `
        <p>Yakin ingin migrasi <strong>${readyCustomerIds.length} member</strong> yang siap?</p>
        <p class="text-sm text-gray-600 mt-2">Ini akan memigrasi semua customer yang status aktif dan email belum terdaftar di member_apps_members.</p>
      `,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#6c757d',
      confirmButtonText: 'Ya, Migrasi Semua',
      cancelButtonText: 'Batal'
    });

    if (!result.isConfirmed) {
      loading.value = false;
      return;
    }

    const migrateResponse = await axios.post('/member-migration/migrate-multiple', {
      customer_ids: readyCustomerIds
    });
    
    if (migrateResponse.data.success) {
      await Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        html: `
          <p>${migrateResponse.data.message}</p>
          ${migrateResponse.data.errors && migrateResponse.data.errors.length > 0 ? `
            <div class="mt-4 text-left">
              <p class="font-semibold">Error Details:</p>
              <ul class="list-disc list-inside text-sm mt-2 max-h-60 overflow-y-auto">
                ${migrateResponse.data.errors.slice(0, 10).map(e => `<li>${e}</li>`).join('')}
                ${migrateResponse.data.errors.length > 10 ? `<li>... dan ${migrateResponse.data.errors.length - 10} error lainnya</li>` : ''}
              </ul>
            </div>
          ` : ''}
        `,
        width: '600px'
      });
      router.reload();
    } else {
      await Swal.fire({
        icon: 'error',
        title: 'Gagal',
        text: migrateResponse.data.message
      });
    }
  } catch (error) {
    await Swal.fire({
      icon: 'error',
      title: 'Gagal',
      html: `
        <p>${error.response?.data?.message || 'Gagal migrasi member'}</p>
        ${error.response?.data?.suggestion ? `
          <div class="mt-4 bg-blue-50 p-3 rounded text-left">
            <p class="font-semibold text-sm">Saran:</p>
            <p class="text-sm">${error.response.data.suggestion}</p>
          </div>
        ` : ''}
      `
    });
  } finally {
    loading.value = false;
  }
}
</script>

