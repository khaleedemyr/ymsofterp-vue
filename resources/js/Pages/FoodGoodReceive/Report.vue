<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-chart-bar text-blue-500"></i> Food Good Receive Report
        </h1>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden mb-6">
        <div class="p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Filter Report</h3>
          <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Date Range -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
              <input
                type="date"
                v-model="filters.from_date"
                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
              <input
                type="date"
                v-model="filters.to_date"
                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>

            <!-- Supplier Filter -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
              <Multiselect
                v-model="filters.supplier_id"
                :options="suppliers"
                :searchable="true"
                :create-option="false"
                placeholder="Select supplier..."
                track-by="id"
                label="name"
                class="w-full"
              />
            </div>

            <!-- Item Filter -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Item</label>
              <Multiselect
                v-model="filters.item_id"
                :options="items"
                :searchable="true"
                :create-option="false"
                placeholder="Select item..."
                track-by="id"
                label="name"
                class="w-full"
              />
            </div>

            <!-- Search -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
              <input
                type="text"
                v-model="filters.search"
                placeholder="Search GR Number, PO Number, Supplier, Item..."
                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              />
            </div>

            <!-- Per Page -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Items per Page</label>
              <select
                v-model="filters.per_page"
                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
              >
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </div>

            <!-- Action Buttons -->
            <div class="md:col-span-2 flex gap-2">
              <button
                type="submit"
                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <i class="fa fa-search mr-2"></i> Apply Filters
              </button>
              <button
                type="button"
                @click="clearFilters"
                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500"
              >
                <i class="fa fa-times mr-2"></i> Clear
              </button>
              <button
                type="button"
                @click="exportReport"
                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
              >
                <i class="fa fa-download mr-2"></i> Export Excel
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
          <div class="p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                  <i class="fa-solid fa-file-lines text-white"></i>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total GR</p>
                <p class="text-2xl font-semibold text-gray-900">{{ summary.total_gr || 0 }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
          <div class="p-6">
            <div class="flex items-center">
              <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                  <i class="fa-solid fa-boxes-stacked text-white"></i>
                </div>
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Qty Received</p>
                <p class="text-2xl font-semibold text-gray-900">{{ formatNumber(summary.total_qty_received) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Results Table -->
      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium text-gray-900">Good Receive Data</h3>
            <p class="text-sm text-gray-500">
              Showing {{ results.from }} to {{ results.to }} of {{ results.total }} results
            </p>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12"></th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GR Number</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receive Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items Count</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Received By</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <template v-for="gr in results.data" :key="gr.id">
                  <!-- Main GR Row -->
                  <tr class="hover:bg-gray-50 cursor-pointer" @click="toggleExpand(gr.id)">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <i 
                        :class="[
                          'fas transition-transform duration-200',
                          expandedRows.includes(gr.id) ? 'fa-chevron-down text-blue-500' : 'fa-chevron-right text-gray-400'
                        ]"
                      ></i>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      {{ gr.gr_number }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ formatDate(gr.receive_date) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ gr.po_number }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ formatDate(gr.po_date) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div>
                        <div class="font-medium text-gray-900">{{ gr.supplier_name }}</div>
                        <div class="text-gray-500">{{ gr.supplier_code }}</div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        {{ gr.total_items }} items
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ gr.received_by_name }}
                    </td>
                  </tr>
                  
                  <!-- Expanded Items Row -->
                  <tr v-if="expandedRows.includes(gr.id)" class="bg-gray-50">
                    <td colspan="8" class="px-6 py-4">
                      <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                          <h4 class="text-sm font-medium text-gray-900">Items in {{ gr.gr_number }}</h4>
                        </div>
                        <div class="overflow-x-auto">
                          <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                              <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">SKU</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Ordered</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty Received</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining Qty</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                              </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                              <tr v-for="item in getItemsForGR(gr.id)" :key="item.item_id" class="hover:bg-gray-50">
                                <td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">
                                  {{ item.item_name }}
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                  {{ item.item_sku }}
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                  {{ formatNumber(item.qty_ordered) }}
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                  {{ formatNumber(item.qty_received) }}
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                  {{ formatNumber(item.remaining_qty) }}
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-500">
                                  {{ item.unit_name }}
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="mt-6">
            <div class="flex justify-between items-center">
              <div class="text-sm text-gray-700">
                Showing {{ results.from }} to {{ results.to }} of {{ results.total }} results
              </div>
              <div class="flex gap-2">
                <button
                  v-for="link in results.links"
                  :key="link.label"
                  :disabled="!link.url"
                  @click="goToPage(link.url)"
                  v-html="link.label"
                  class="px-3 py-1 rounded-lg border text-sm font-semibold"
                  :class="[
                    link.active ? 'bg-blue-600 text-white shadow-lg' : 'bg-white text-blue-700 hover:bg-blue-50',
                    !link.url ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'
                  ]"
                />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  results: Object,
  itemsData: Object,
  summary: Object,
  suppliers: Array,
  items: Array,
  filters: Object
})

const filters = reactive({
  from_date: props.filters?.from_date || '',
  to_date: props.filters?.to_date || '',
  supplier_id: props.filters?.supplier_id || null,
  item_id: props.filters?.item_id || null,
  status: props.filters?.status || '',
  search: props.filters?.search || '',
  per_page: props.filters?.per_page || '15'
})

const expandedRows = ref([])

const toggleExpand = (grId) => {
  if (expandedRows.value.includes(grId)) {
    expandedRows.value = expandedRows.value.filter(id => id !== grId)
  } else {
    expandedRows.value.push(grId)
  }
}

const getItemsForGR = (grId) => {
  return props.itemsData[grId] || []
}

const applyFilters = () => {
  const filterData = {
    from_date: filters.from_date,
    to_date: filters.to_date,
    supplier_id: filters.supplier_id?.id || filters.supplier_id,
    item_id: filters.item_id?.id || filters.item_id,
    status: filters.status,
    search: filters.search,
    per_page: filters.per_page
  }
  
  router.get(route('food-good-receive.report'), filterData, {
    preserveState: true,
    preserveScroll: true
  })
}

const clearFilters = () => {
  Object.keys(filters).forEach(key => {
    if (key === 'per_page') {
      filters[key] = '15'
    } else if (key === 'supplier_id' || key === 'item_id') {
      filters[key] = null
    } else {
      filters[key] = ''
    }
  })
  applyFilters()
}

const exportReport = () => {
  const params = new URLSearchParams()
  
  if (filters.from_date) params.append('from_date', filters.from_date)
  if (filters.to_date) params.append('to_date', filters.to_date)
  if (filters.supplier_id?.id) params.append('supplier_id', filters.supplier_id.id)
  if (filters.supplier_id && !filters.supplier_id.id) params.append('supplier_id', filters.supplier_id)
  if (filters.item_id?.id) params.append('item_id', filters.item_id.id)
  if (filters.item_id && !filters.item_id.id) params.append('item_id', filters.item_id)
  if (filters.status) params.append('status', filters.status)
  if (filters.search) params.append('search', filters.search)
  
  const url = route('food-good-receive.report.export') + '?' + params.toString()
  window.open(url, '_blank')
}

const goToPage = (url) => {
  if (url) {
    router.visit(url, { preserveState: true })
  }
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('id-ID')
}

const formatNumber = (number) => {
  if (!number) return '0'
  return new Intl.NumberFormat('id-ID').format(number)
}
</script>

<style scoped>
:deep(.multiselect) {
  min-height: 38px;
}

:deep(.multiselect-dropdown) {
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
}

:deep(.multiselect-option) {
  padding: 8px 12px;
}

:deep(.multiselect-option.is-selected) {
  background-color: #3b82f6;
  color: white;
}

:deep(.multiselect-option.is-pointed) {
  background-color: #f3f4f6;
}
</style>
