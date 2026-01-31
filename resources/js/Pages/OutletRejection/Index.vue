<template>
  <AppLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Outlet Rejection
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900">
            
            <!-- Header with Create Button -->
            <div class="flex justify-between items-center mb-6">
              <h3 class="text-lg font-semibold">Daftar Outlet Rejection</h3>
              <Link
                :href="route('outlet-rejections.create')"
                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
              >
                <i class="fas fa-plus mr-2"></i>
                Create Rejection
              </Link>
            </div>

            <!-- Filters -->
            <div class="bg-gray-50 p-4 rounded-lg mb-6">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                  <input
                    v-model="filters.search"
                    type="text"
                    placeholder="Search number, outlet, warehouse..."
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    @input="debounceSearch"
                  />
                </div>

                <!-- Status Filter -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                  <select
                    v-model="filters.status"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    @change="applyFilters"
                  >
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="submitted">Submitted</option>
                    <option value="approved">Approved</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                </div>

                <!-- Outlet Filter -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
                  <select
                    v-model="filters.outlet_id"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    @change="applyFilters"
                  >
                    <option value="">All Outlets</option>
                    <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                      {{ outlet.nama_outlet }}
                    </option>
                  </select>
                </div>

                <!-- Warehouse Filter -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse</label>
                  <select
                    v-model="filters.warehouse_id"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    @change="applyFilters"
                  >
                    <option value="">All Warehouses</option>
                    <option v-for="warehouse in warehouses" :key="warehouse.id" :value="warehouse.id">
                      {{ warehouse.name }}
                    </option>
                  </select>
                </div>
              </div>

              <!-- Date Range Filters -->
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                  <input
                    v-model="filters.date_from"
                    type="date"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    @change="applyFilters"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                  <input
                    v-model="filters.date_to"
                    type="date"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    @change="applyFilters"
                  />
                </div>
              </div>

              <!-- Clear Filters -->
              <div class="mt-4">
                <button
                  @click="clearFilters"
                  class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                >
                  Clear Filters
                </button>
              </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Number
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Date
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Outlet
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Warehouse
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Created By
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Approved By
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Completed By
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="rejection in rejections.data" :key="rejection.id">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">
                        {{ rejection.number }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">
                        {{ formatDate(rejection.rejection_date) }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">
                        {{ rejection.outlet?.nama_outlet }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">
                        {{ rejection.warehouse?.name }}
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span :class="getStatusBadgeClass(rejection.status)">
                        {{ getStatusLabel(rejection.status) }}
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">
                        <div>{{ rejection.approval_info?.created_by || rejection.createdBy?.nama_lengkap || '-' }}</div>
                        <div class="text-xs text-gray-500">{{ rejection.approval_info?.created_at || formatDateTime(rejection.created_at) }}</div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">
                        <div v-if="rejection.approval_info?.ssd_manager || rejection.ssdManager?.nama_lengkap">
                          <div>{{ rejection.approval_info?.ssd_manager || rejection.ssdManager?.nama_lengkap }}</div>
                          <div class="text-xs text-gray-500">{{ rejection.approval_info?.ssd_manager_at || formatDateTime(rejection.ssd_manager_approved_at) }}</div>
                        </div>
                        <div v-else class="text-gray-400">-</div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">
                        <div v-if="rejection.approval_info?.completed_by || rejection.completedBy?.nama_lengkap">
                          <div>{{ rejection.approval_info?.completed_by || rejection.completedBy?.nama_lengkap }}</div>
                          <div class="text-xs text-gray-500">{{ rejection.approval_info?.completed_at || formatDateTime(rejection.completed_at) }}</div>
                        </div>
                        <div v-else class="text-gray-400">-</div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                      <div class="flex space-x-2">
                        <!-- View -->
                        <Link
                          :href="route('outlet-rejections.show', rejection.id)"
                          class="text-blue-600 hover:text-blue-900"
                        >
                          <i class="fas fa-eye"></i>
                        </Link>

                        <!-- Edit (only for draft) -->
                        <Link
                          v-if="rejection.status === 'draft'"
                          :href="route('outlet-rejections.edit', rejection.id)"
                          class="text-green-600 hover:text-green-900"
                        >
                          <i class="fas fa-edit"></i>
                        </Link>

                        <!-- Delete (only for draft) -->
                        <button
                          v-if="rejection.status === 'draft' && props.canDelete"
                          @click="deleteRejection(rejection.id)"
                          class="text-red-600 hover:text-red-900"
                        >
                          <i class="fas fa-trash"></i>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">
              <Pagination :links="rejections.links" />
            </div>

            <!-- Empty State -->
            <div v-if="rejections.data.length === 0" class="text-center py-12">
              <i class="fas fa-inbox text-4xl text-gray-400 mb-4"></i>
              <h3 class="text-lg font-medium text-gray-900 mb-2">No rejections found</h3>
              <p class="text-gray-500">Get started by creating a new outlet rejection.</p>
            </div>

          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  rejections: Object,
  outlets: Array,
  warehouses: Array,
  filters: Object,
  canDelete: Boolean
})

const filters = ref({
  search: props.filters?.search || '',
  status: props.filters?.status || '',
  outlet_id: props.filters?.outlet_id || '',
  warehouse_id: props.filters?.warehouse_id || '',
  date_from: props.filters?.date_from || '',
  date_to: props.filters?.date_to || '',
  per_page: props.filters?.per_page || 15
})

let searchTimeout = null

const debounceSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    applyFilters()
  }, 500)
}

const applyFilters = () => {
  router.get(route('outlet-rejections.index'), filters.value, {
    preserveState: true,
    preserveScroll: true,
    replace: true
  })
}

const clearFilters = () => {
  filters.value = {
    search: '',
    status: '',
    outlet_id: '',
    warehouse_id: '',
    date_from: '',
    date_to: '',
    per_page: 15
  }
  applyFilters()
}

const deleteRejection = (id) => {
  if (confirm('Are you sure you want to delete this rejection?')) {
    router.delete(route('outlet-rejections.destroy', id), {
      onSuccess: () => {
        // Success message will be handled by the controller
      }
    })
  }
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const formatDateTime = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: 'numeric'
  })
}

const getStatusLabel = (status) => {
  const labels = {
    draft: 'Draft',
    submitted: 'Submitted',
    approved: 'Approved',
    completed: 'Completed',
    cancelled: 'Cancelled'
  }
  return labels[status] || status
}

const getStatusBadgeClass = (status) => {
  const classes = {
    draft: 'bg-gray-100 text-gray-800',
    submitted: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800'
  }
  return `inline-flex px-2 py-1 text-xs font-semibold rounded-full ${classes[status] || 'bg-gray-100 text-gray-800'}`
}
</script>
