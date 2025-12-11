<template>
  <AppLayout>
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-clipboard-check text-blue-500"></i> Stock Opname
        </h1>
        <Link
          :href="route('stock-opnames.create')"
          class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
        >
          <i class="fa-solid fa-plus mr-2"></i> Buat Stock Opname Baru
        </Link>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <input
            type="text"
            v-model="filters.search"
            @input="applyFilters"
            placeholder="Cari opname number, notes..."
            class="px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <select
            v-model="filters.status"
            @change="applyFilters"
            class="px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option value="all">Semua Status</option>
            <option value="DRAFT">Draft</option>
            <option value="SUBMITTED">Submitted</option>
            <option value="APPROVED">Approved</option>
            <option value="REJECTED">Rejected</option>
            <option value="COMPLETED">Completed</option>
          </select>
          <select
            v-model="filters.outlet_id"
            @change="applyFilters"
            class="px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            :disabled="!outletSelectable"
          >
            <option value="">Semua Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">
              {{ outlet.name }}
            </option>
          </select>
          <input
            type="date"
            v-model="filters.date_from"
            @change="applyFilters"
            class="px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <input
            type="date"
            v-model="filters.date_to"
            @change="applyFilters"
            class="px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Opname Number</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Warehouse Outlet</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Tanggal</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Status</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Created By</th>
              <th class="px-6 py-3 text-left text-xs font-bold text-gray-700 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="stockOpnames.data.length === 0">
              <td colspan="7" class="text-center py-10 text-gray-400">Tidak ada data stock opname.</td>
            </tr>
            <tr
              v-for="opname in stockOpnames.data"
              :key="opname.id"
              class="hover:bg-gray-50 transition"
            >
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                {{ opname.opname_number }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                {{ opname.outlet?.nama_outlet || '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                {{ opname.warehouse_outlet?.name || '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                {{ formatDate(opname.opname_date) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getStatusClass(opname.status)" class="px-2 py-1 rounded-full text-xs font-semibold">
                  {{ opname.status }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                {{ opname.creator?.nama_lengkap || '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm">
                <div class="flex gap-2">
                  <Link
                    :href="route('stock-opnames.show', opname.id)"
                    class="text-blue-600 hover:text-blue-800 font-semibold"
                  >
                    View
                  </Link>
                  <Link
                    v-if="opname.status === 'DRAFT'"
                    :href="route('stock-opnames.edit', opname.id)"
                    class="text-green-600 hover:text-green-800 font-semibold"
                  >
                    Edit
                  </Link>
                  <button
                    v-if="opname.status === 'DRAFT'"
                    @click="deleteStockOpname(opname.id)"
                    class="text-red-600 hover:text-red-800 font-semibold"
                  >
                    Delete
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div class="mt-4 flex justify-between items-center" v-if="stockOpnames.data.length > 0">
        <div class="text-sm text-gray-600">
          Menampilkan {{ stockOpnames.from }} - {{ stockOpnames.to }} dari {{ stockOpnames.total }} data
        </div>
        <div class="flex gap-2">
          <Link
            v-if="stockOpnames.prev_page_url"
            :href="stockOpnames.prev_page_url"
            class="px-3 py-1 rounded border text-sm bg-white text-blue-700 hover:bg-blue-50"
          >
            &lt; Prev
          </Link>
          <Link
            v-if="stockOpnames.next_page_url"
            :href="stockOpnames.next_page_url"
            class="px-3 py-1 rounded border text-sm bg-white text-blue-700 hover:bg-blue-50"
          >
            Next &gt;
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  stockOpnames: Object,
  outlets: Array,
  filters: Object,
  user_outlet_id: [String, Number],
});

const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || 'all',
  outlet_id: props.filters?.outlet_id || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
});

const outletSelectable = computed(() => String(props.user_outlet_id) === '1');

function applyFilters() {
  router.get(route('stock-opnames.index'), filters.value, {
    preserveState: true,
    preserveScroll: true,
  });
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function getStatusClass(status) {
  const classes = {
    DRAFT: 'bg-gray-200 text-gray-800',
    SUBMITTED: 'bg-yellow-200 text-yellow-800',
    APPROVED: 'bg-green-200 text-green-800',
    REJECTED: 'bg-red-200 text-red-800',
    COMPLETED: 'bg-blue-200 text-blue-800',
  };
  return classes[status] || 'bg-gray-200 text-gray-800';
}

async function deleteStockOpname(id) {
  if (!confirm('Yakin ingin menghapus stock opname ini?')) {
    return;
  }

  try {
    await axios.delete(route('stock-opnames.destroy', id));
    router.reload();
  } catch (error) {
    console.error('Error deleting:', error);
    alert('Gagal menghapus stock opname. Silakan coba lagi.');
  }
}
</script>

