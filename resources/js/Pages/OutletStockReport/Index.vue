<template>
  <AppLayout>
    <div class="w-full py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-chart-line text-blue-500"></i> Outlet Stock Report
        </h1>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-xl shadow-xl p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
            <select 
              v-model="filters.outlet_id" 
              @change="loadWarehouseOutlets"
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                {{ outlet.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Warehouse Outlet</label>
            <select 
              v-model="filters.warehouse_outlet_id" 
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
              :disabled="!filters.outlet_id"
            >
              <option value="">Pilih Warehouse</option>
              <option v-for="warehouse in warehouseOutlets" :key="warehouse.id" :value="warehouse.id">
                {{ warehouse.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
            <input 
              type="month" 
              v-model="filters.bulan" 
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div class="flex items-end gap-2">
            <button 
              @click="exportToExcel" 
              :disabled="loading || exporting || !canExport"
              class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="exporting" class="fa-solid fa-spinner fa-spin mr-1"></i>
              <i v-else class="fa-solid fa-file-excel mr-1"></i> 
              {{ exporting ? 'Exporting...' : 'Export Excel' }}
            </button>
            <button 
              @click="loadReport" 
              :disabled="loading"
              class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="loading" class="fa-solid fa-spinner fa-spin mr-1"></i>
              <i v-else class="fa-solid fa-search mr-1"></i> 
              {{ loading ? 'Loading...' : 'Load Data' }}
            </button>
          </div>
        </div>
        
        <!-- Search and Per Page -->
        <div v-if="filters.outlet_id && filters.warehouse_outlet_id" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Cari Barang</label>
            <input 
              type="text" 
              v-model="filters.search" 
              @input="debouncedSearch"
              placeholder="Cari berdasarkan kode, nama, atau kategori..."
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Per Halaman</label>
            <select 
              v-model="filters.per_page" 
              @change="loadReport"
              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
              <option :value="200">200</option>
              <option :value="500">500</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Loading Spinner -->
      <div v-if="loading" class="bg-white rounded-xl shadow-xl p-12 text-center">
        <div class="text-blue-500">
          <i class="fa-solid fa-spinner fa-spin text-4xl mb-4"></i>
          <p class="text-lg font-medium">Memuat data...</p>
        </div>
      </div>

      <!-- Grand Total Summary -->
      <div v-if="!loading && filteredReportDataGrouped && Object.keys(filteredReportDataGrouped).length > 0" class="bg-white rounded-xl shadow-xl p-6 mb-6">
        <h2 class="text-lg font-bold mb-4">Grand Total MAC</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div class="bg-blue-50 p-4 rounded-lg">
            <div class="text-sm text-gray-600 mb-1">Last Stock</div>
            <div class="text-2xl font-bold text-blue-600">{{ formatNumber(grandTotalLastStockMac) }}</div>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <div class="text-sm text-gray-600 mb-1">Stock Opname Physical</div>
            <div class="text-2xl font-bold text-green-600">{{ formatNumber(grandTotalStockOpnameMac) }}</div>
          </div>
          <div class="bg-purple-50 p-4 rounded-lg">
            <div class="text-sm text-gray-600 mb-1">Selisih</div>
            <div class="text-2xl font-bold text-purple-600">{{ formatNumber(grandTotalDifferenceMac) }}</div>
          </div>
        </div>
      </div>

      <!-- Report Table -->
      <div v-if="!loading && filteredReportDataGrouped && Object.keys(filteredReportDataGrouped).length > 0" class="bg-white rounded-xl shadow-xl overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode Barang</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Barang</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UOM</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Begin Inventory</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Good Receive</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Good Sold</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wasted</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Spoil</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guest Supplies</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">R&D</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marketing</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wrong Maker</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Internal Used</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Non Commodity</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">WIP Production IN</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">WIP Production OUT</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Internal Transfer IN</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Internal Transfer OUT</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Stock</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock Opname Physical</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Selisih (Last Stock - Stock Opname)</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <template v-for="(categoryData, categoryName) in filteredReportDataGrouped" :key="categoryName">
              <!-- Category Header -->
              <tr class="bg-blue-50 cursor-pointer hover:bg-blue-100" @click="toggleCategory(categoryName)">
                <td colspan="22" class="px-4 py-3">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                      <i :class="expandedCategories.includes(categoryName) ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"></i>
                      <span class="font-semibold text-blue-900">{{ categoryName }}</span>
                      <span class="text-sm text-blue-700">({{ getCategoryItems(categoryData).length }} item)</span>
                    </div>
                    <div class="flex items-center gap-4 text-sm">
                      <div class="text-blue-700">
                        <span class="font-medium">Subtotal Last Stock MAC:</span>
                        <span class="font-bold ml-2">{{ formatNumber(getCategorySubtotalLastStockMac(categoryName)) }}</span>
                      </div>
                      <div class="text-green-700">
                        <span class="font-medium">Subtotal Stock Opname MAC:</span>
                        <span class="font-bold ml-2">{{ formatNumber(getCategorySubtotalStockOpnameMac(categoryName)) }}</span>
                      </div>
                      <div class="text-purple-700">
                        <span class="font-medium">Subtotal Selisih MAC:</span>
                        <span class="font-bold ml-2">{{ formatNumber(getCategorySubtotalDifferenceMac(categoryName)) }}</span>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
              <!-- Items in Category -->
              <template v-if="expandedCategories.includes(categoryName)">
                <tr v-for="(item, index) in getCategoryItems(categoryData)" :key="`${categoryName}-${index}`" class="hover:bg-gray-50">
                  <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">{{ getItemNumber(categoryName, index) }}</td>
                  <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ item.item_code }}</td>
                  <td class="px-4 py-4 text-sm text-gray-900">{{ item.item_name }}</td>
                  <td class="px-4 py-4 text-sm text-gray-900">
                    <div v-if="Array.isArray(item.uom)" class="flex flex-col">
                      <div v-for="(uom, idx) in item.uom" :key="idx">{{ uom }}</div>
                    </div>
                    <div v-else>{{ item.uom }}</div>
                  </td>
                  <td class="px-4 py-4 text-sm text-gray-900">
                    <div v-if="Array.isArray(item.begin_inventory)" class="flex flex-col">
                      <div v-for="(inv, idx) in item.begin_inventory" :key="idx">{{ inv }}</div>
                    </div>
                    <div v-else>{{ item.begin_inventory }}</div>
                  </td>
                  <!-- Good Receive - IN (lime) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.good_receive)" class="flex flex-col">
                      <div v-for="(gr, idx) in item.good_receive" :key="idx" class="text-lime-600 font-medium">{{ gr }}</div>
                    </div>
                    <div v-else class="text-lime-600 font-medium">{{ item.good_receive || '0' }}</div>
                  </td>
                  <!-- Good Sold - OUT (red) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.good_sold)" class="flex flex-col">
                      <div v-for="(gs, idx) in item.good_sold" :key="idx" class="text-red-600 font-medium">{{ gs }}</div>
                    </div>
                    <div v-else class="text-red-600 font-medium">{{ item.good_sold || '0' }}</div>
                  </td>
                  <!-- Wasted - OUT (red) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.wasted)" class="flex flex-col">
                      <div v-for="(w, idx) in item.wasted" :key="idx" class="text-red-600 font-medium">{{ w }}</div>
                    </div>
                    <div v-else class="text-red-600 font-medium">{{ item.wasted || '0' }}</div>
                  </td>
                  <!-- Spoil - OUT (red) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.spoil)" class="flex flex-col">
                      <div v-for="(s, idx) in item.spoil" :key="idx" class="text-red-600 font-medium">{{ s }}</div>
                    </div>
                    <div v-else class="text-red-600 font-medium">{{ item.spoil || '0' }}</div>
                  </td>
                  <!-- Guest Supplies - OUT (red) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.guest_supplies)" class="flex flex-col">
                      <div v-for="(gs, idx) in item.guest_supplies" :key="idx" class="text-red-600 font-medium">{{ gs }}</div>
                    </div>
                    <div v-else class="text-red-600 font-medium">{{ item.guest_supplies || '0' }}</div>
                  </td>
                  <!-- RnD - OUT (red) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.rnd)" class="flex flex-col">
                      <div v-for="(r, idx) in item.rnd" :key="idx" class="text-red-600 font-medium">{{ r }}</div>
                    </div>
                    <div v-else class="text-red-600 font-medium">{{ item.rnd || '0' }}</div>
                  </td>
                  <!-- Marketing - OUT (red) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.marketing)" class="flex flex-col">
                      <div v-for="(m, idx) in item.marketing" :key="idx" class="text-red-600 font-medium">{{ m }}</div>
                    </div>
                    <div v-else class="text-red-600 font-medium">{{ item.marketing || '0' }}</div>
                  </td>
                  <!-- Wrong Maker - OUT (red) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.wrong_maker)" class="flex flex-col">
                      <div v-for="(wm, idx) in item.wrong_maker" :key="idx" class="text-red-600 font-medium">{{ wm }}</div>
                    </div>
                    <div v-else class="text-red-600 font-medium">{{ item.wrong_maker || '0' }}</div>
                  </td>
                  <!-- Internal Used - OUT (red) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.internal_used)" class="flex flex-col">
                      <div v-for="(iu, idx) in item.internal_used" :key="idx" class="text-red-600 font-medium">{{ iu }}</div>
                    </div>
                    <div v-else class="text-red-600 font-medium">{{ item.internal_used || '0' }}</div>
                  </td>
                  <!-- Non Commodity - OUT (red) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.non_commodity)" class="flex flex-col">
                      <div v-for="(nc, idx) in item.non_commodity" :key="idx" class="text-red-600 font-medium">{{ nc }}</div>
                    </div>
                    <div v-else class="text-red-600 font-medium">{{ item.non_commodity || '0' }}</div>
                  </td>
                  <!-- WIP Production IN - IN (lime) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.wip_production_in)" class="flex flex-col">
                      <div v-for="(wipIn, idx) in item.wip_production_in" :key="idx" class="text-lime-600 font-medium">{{ wipIn }}</div>
                    </div>
                    <div v-else class="text-lime-600 font-medium">{{ item.wip_production_in || '0' }}</div>
                  </td>
                  <!-- WIP Production OUT - OUT (red) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.wip_production_out)" class="flex flex-col">
                      <div v-for="(wipOut, idx) in item.wip_production_out" :key="idx" class="text-red-600 font-medium">{{ wipOut }}</div>
                    </div>
                    <div v-else class="text-red-600 font-medium">{{ item.wip_production_out || '0' }}</div>
                  </td>
                  <!-- Internal Transfer IN - IN (lime) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.internal_transfer_in)" class="flex flex-col">
                      <div v-for="(transferIn, idx) in item.internal_transfer_in" :key="idx" class="text-lime-600 font-medium">{{ transferIn }}</div>
                    </div>
                    <div v-else class="text-lime-600 font-medium">{{ item.internal_transfer_in || '0' }}</div>
                  </td>
                  <!-- Internal Transfer OUT - OUT (red) -->
                  <td class="px-4 py-4 text-sm">
                    <div v-if="Array.isArray(item.internal_transfer_out)" class="flex flex-col">
                      <div v-for="(transferOut, idx) in item.internal_transfer_out" :key="idx" class="text-red-600 font-medium">{{ transferOut }}</div>
                    </div>
                    <div v-else class="text-red-600 font-medium">{{ item.internal_transfer_out || '0' }}</div>
                  </td>
                  <!-- Last Stock -->
                  <td class="px-4 py-4 text-sm text-gray-900">
                    <div v-if="Array.isArray(item.last_stock)" class="flex flex-col">
                      <div v-for="(ls, idx) in item.last_stock" :key="idx">
                        <template v-if="typeof ls === 'string'">
                          <span v-if="ls.includes('@')">
                            <span class="text-gray-900">{{ ls.split('@')[0].trim() }}</span>
                            <span class="text-blue-600 font-semibold">@ {{ ls.split('@')[1].trim() }}</span>
                          </span>
                          <span v-else-if="ls.includes('Subtotal:')">
                            <span class="text-gray-700 font-medium">{{ ls.split(':')[0] }}:</span>
                            <span class="text-blue-600 font-bold ml-1">{{ ls.split(':')[1].trim() }}</span>
                          </span>
                          <span v-else>{{ ls }}</span>
                        </template>
                        <span v-else>{{ ls }}</span>
                      </div>
                    </div>
                    <div v-else>{{ item.last_stock || '0' }}</div>
                  </td>
                  <!-- Stock Opname Physical -->
                  <td class="px-4 py-4 text-sm text-gray-900">
                    <div v-if="Array.isArray(item.stock_opname_physical)" class="flex flex-col">
                      <div v-for="(sop, idx) in item.stock_opname_physical" :key="idx">
                        <template v-if="typeof sop === 'string'">
                          <span v-if="sop.includes('@')">
                            <span class="text-gray-900">{{ sop.split('@')[0].trim() }}</span>
                            <span class="text-green-600 font-semibold">@ {{ sop.split('@')[1].trim() }}</span>
                          </span>
                          <span v-else-if="sop.includes('Subtotal:')">
                            <span class="text-gray-700 font-medium">{{ sop.split(':')[0] }}:</span>
                            <span class="text-green-600 font-bold ml-1">{{ sop.split(':')[1].trim() }}</span>
                          </span>
                          <span v-else>{{ sop }}</span>
                        </template>
                        <span v-else>{{ sop }}</span>
                      </div>
                    </div>
                    <div v-else>{{ item.stock_opname_physical || '0' }}</div>
                  </td>
                  <!-- Selisih (Last Stock - Stock Opname) -->
                  <td class="px-4 py-4 text-sm text-gray-900">
                    <div v-if="item.has_stock_opname_data && item.difference_stock_opname">
                      <div v-if="Array.isArray(item.difference_stock_opname)" class="flex flex-col">
                        <div 
                          v-for="(diff, idx) in item.difference_stock_opname" 
                          :key="idx"
                        >
                          <template v-if="typeof diff === 'string'">
                            <span v-if="diff.includes('@')">
                              <span :class="{ 'text-red-600 font-medium': diff.startsWith('-'), 'text-lime-600 font-medium': diff.startsWith('+'), 'text-gray-900': !diff.startsWith('-') && !diff.startsWith('+') }">
                                {{ diff.split('@')[0].trim() }}
                              </span>
                              <span class="text-purple-600 font-semibold">@ {{ diff.split('@')[1].trim() }}</span>
                            </span>
                            <span v-else-if="diff.includes('Subtotal:')">
                              <span class="text-gray-700 font-medium">{{ diff.split(':')[0] }}:</span>
                              <span :class="{ 'text-red-600 font-bold': diff.includes('-'), 'text-lime-600 font-bold': diff.includes('+'), 'text-purple-600 font-bold': !diff.includes('-') && !diff.includes('+') }" class="ml-1">
                                {{ diff.split(':')[1].trim() }}
                              </span>
                            </span>
                            <span v-else :class="{ 'text-red-600 font-medium': diff.startsWith('-'), 'text-lime-600 font-medium': diff.startsWith('+') }">
                              {{ diff }}
                            </span>
                          </template>
                          <span v-else>{{ diff }}</span>
                        </div>
                      </div>
                      <div v-else>{{ item.difference_stock_opname || '0' }}</div>
                    </div>
                    <div v-else class="text-gray-400">-</div>
                  </td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
        
        <!-- Pagination -->
        <div v-if="pagination && pagination.total_pages > 1" class="px-4 py-4 border-t border-gray-200 flex items-center justify-between">
          <div class="flex items-center gap-2 text-sm text-gray-700">
            <span>Menampilkan {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} - {{ Math.min(pagination.current_page * pagination.per_page, pagination.total_items) }} dari {{ pagination.total_items }} item</span>
          </div>
          <div class="flex items-center gap-2">
            <button 
              @click="goToPage(pagination.current_page - 1)"
              :disabled="pagination.current_page <= 1"
              class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            <span class="px-3 py-1 text-sm text-gray-700">
              Halaman {{ pagination.current_page }} dari {{ pagination.total_pages }}
            </span>
            <button 
              @click="goToPage(pagination.current_page + 1)"
              :disabled="pagination.current_page >= pagination.total_pages"
              class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i class="fa-solid fa-chevron-right"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="!loading && filters.outlet_id && filters.warehouse_outlet_id" class="bg-white rounded-xl shadow-xl p-12 text-center">
        <div class="text-gray-500">
          <i class="fa-solid fa-inbox text-4xl mb-4"></i>
          <p class="text-lg font-medium">Tidak ada data</p>
          <p class="text-sm">Tidak ada inventory items untuk outlet dan warehouse yang dipilih</p>
        </div>
      </div>

      <!-- Initial State -->
      <div v-else-if="!loading" class="bg-white rounded-xl shadow-xl p-12 text-center">
        <div class="text-gray-500">
          <i class="fa-solid fa-filter text-4xl mb-4"></i>
          <p class="text-lg font-medium">Pilih Filter</p>
          <p class="text-sm">Silakan pilih outlet, warehouse, dan bulan untuk melihat report</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  reportData: {
    type: Array,
    default: () => []
  },
  reportDataGrouped: {
    type: Object,
    default: () => ({})
  },
  outlets: {
    type: Array,
    default: () => []
  },
  warehouseOutlets: {
    type: Array,
    default: () => []
  },
  pagination: {
    type: Object,
    default: () => null
  },
  filters: {
    type: Object,
    default: () => ({
      outlet_id: '',
      warehouse_outlet_id: '',
      bulan: new Date().toISOString().slice(0, 7), // Format: YYYY-MM
      search: '',
      per_page: 50,
      page: 1
    })
  },
  grandTotalLastStockMac: {
    type: Number,
    default: 0
  },
  grandTotalStockOpnameMac: {
    type: Number,
    default: 0
  },
  grandTotalDifferenceMac: {
    type: Number,
    default: 0
  }
});

const filters = ref({
  outlet_id: props.filters.outlet_id || '',
  warehouse_outlet_id: props.filters.warehouse_outlet_id || '',
  bulan: props.filters.bulan || new Date().toISOString().slice(0, 7),
  search: props.filters.search || '',
  per_page: props.filters.per_page || 50,
  page: props.filters.page || 1
});

const outlets = ref(props.outlets || []);
const warehouseOutlets = ref(props.warehouseOutlets || []);
const reportData = ref(props.reportData || []);
const reportDataGrouped = ref(props.reportDataGrouped || {});
const pagination = ref(props.pagination);
const loading = ref(false);
const exporting = ref(false);
const expandedCategories = ref([]);
const searchTimeout = ref(null);
const grandTotalLastStockMac = ref(props.grandTotalLastStockMac || 0);
const grandTotalStockOpnameMac = ref(props.grandTotalStockOpnameMac || 0);
const grandTotalDifferenceMac = ref(props.grandTotalDifferenceMac || 0);

const canExport = computed(() => {
  return filters.value.outlet_id && filters.value.warehouse_outlet_id && filters.value.bulan;
});

// Watch for changes in props
watch(() => props.warehouseOutlets, (newVal) => {
  warehouseOutlets.value = newVal || [];
}, { immediate: true });

watch(() => props.reportDataGrouped, (newVal) => {
  reportDataGrouped.value = newVal || {};
  // Auto expand all categories when data loads
  if (newVal && Object.keys(newVal).length > 0) {
    expandedCategories.value = Object.keys(newVal);
  }
}, { immediate: true });

watch(() => props.grandTotalLastStockMac, (newVal) => {
  grandTotalLastStockMac.value = newVal || 0;
}, { immediate: true });

watch(() => props.grandTotalStockOpnameMac, (newVal) => {
  grandTotalStockOpnameMac.value = newVal || 0;
}, { immediate: true });

watch(() => props.grandTotalDifferenceMac, (newVal) => {
  grandTotalDifferenceMac.value = newVal || 0;
}, { immediate: true });

watch(() => props.pagination, (newVal) => {
  pagination.value = newVal;
}, { immediate: true });

const loadWarehouseOutlets = () => {
  if (!filters.value.outlet_id) {
    warehouseOutlets.value = [];
    filters.value.warehouse_outlet_id = '';
    return;
  }

  router.get(route('outlet-stock-report.index'), {
    outlet_id: filters.value.outlet_id,
    warehouse_outlet_id: '',
    bulan: filters.value.bulan
  }, {
    preserveState: false,
    preserveScroll: true,
    only: ['warehouseOutlets', 'filters']
  });
};

const loadReport = () => {
  if (!filters.value.outlet_id || !filters.value.warehouse_outlet_id) {
    alert('Silakan pilih outlet dan warehouse terlebih dahulu');
    return;
  }

  loading.value = true;
  filters.value.page = 1; // Reset to first page when loading new data
  
  router.get(route('outlet-stock-report.index'), {
    outlet_id: filters.value.outlet_id,
    warehouse_outlet_id: filters.value.warehouse_outlet_id,
    bulan: filters.value.bulan,
    search: filters.value.search,
    per_page: filters.value.per_page,
    page: filters.value.page
  }, {
    preserveState: false,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    }
  });
};

const debouncedSearch = () => {
  if (searchTimeout.value) {
    clearTimeout(searchTimeout.value);
  }
  
  searchTimeout.value = setTimeout(() => {
    filters.value.page = 1; // Reset to first page when searching
    loadReport();
  }, 500);
};

const goToPage = (page) => {
  if (page < 1 || page > pagination.value.total_pages) return;
  
  filters.value.page = page;
  loading.value = true;
  
  router.get(route('outlet-stock-report.index'), {
    outlet_id: filters.value.outlet_id,
    warehouse_outlet_id: filters.value.warehouse_outlet_id,
    bulan: filters.value.bulan,
    search: filters.value.search,
    per_page: filters.value.per_page,
    page: filters.value.page
  }, {
    preserveState: false,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
      // Scroll to top of table
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  });
};

const toggleCategory = (categoryName) => {
  const index = expandedCategories.value.indexOf(categoryName);
  if (index > -1) {
    expandedCategories.value.splice(index, 1);
  } else {
    expandedCategories.value.push(categoryName);
  }
};

const getItemNumber = (categoryName, index) => {
  // Calculate item number based on category position and item index
  try {
    let itemNumber = index + 1;
    const categories = Object.keys(reportDataGrouped.value || {});
    const categoryIndex = categories.indexOf(categoryName);
    
    if (categoryIndex === -1) {
      return index + 1;
    }
    
    for (let i = 0; i < categoryIndex; i++) {
      const prevCategory = categories[i];
      const prevCategoryData = reportDataGrouped.value[prevCategory];
      
      if (prevCategoryData) {
        // Check if it's the new structure with 'items' key
        if (prevCategoryData.items && Array.isArray(prevCategoryData.items)) {
          itemNumber += prevCategoryData.items.length;
        } 
        // Check if it's the old structure (direct array)
        else if (Array.isArray(prevCategoryData)) {
          itemNumber += prevCategoryData.length;
        }
      }
    }
    
    return itemNumber;
  } catch (error) {
    return index + 1;
  }
};

const formatNumber = (value) => {
  if (!value && value !== 0) return '0';
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value);
};

const getCategoryItems = (categoryData) => {
  // Jika categoryData adalah array, return langsung
  if (Array.isArray(categoryData)) {
    return categoryData;
  }
  // Jika categoryData adalah object dengan items, return items
  if (categoryData && categoryData.items) {
    return categoryData.items;
  }
  return [];
};

const getCategorySubtotalLastStockMac = (categoryName) => {
  const category = reportDataGrouped.value[categoryName];
  if (!category) return 0;
  
  // Jika category adalah array, hitung dari items
  if (Array.isArray(category)) {
    return category.reduce((sum, item) => {
      return sum + (item.last_stock_subtotal_mac || 0);
    }, 0);
  }
  
  // Jika category adalah object dengan _subtotal, gunakan itu
  if (category._subtotal_last_stock_mac !== undefined) {
    return category._subtotal_last_stock_mac;
  }
  
  return 0;
};

const getCategorySubtotalStockOpnameMac = (categoryName) => {
  const category = reportDataGrouped.value[categoryName];
  if (!category) return 0;
  
  // Jika category adalah array, hitung dari items
  if (Array.isArray(category)) {
    return category.reduce((sum, item) => {
      return sum + (item.stock_opname_subtotal_mac || 0);
    }, 0);
  }
  
  // Jika category adalah object dengan _subtotal, gunakan itu
  if (category._subtotal_stock_opname_mac !== undefined) {
    return category._subtotal_stock_opname_mac;
  }
  
  return 0;
};

const getCategorySubtotalDifferenceMac = (categoryName) => {
  const category = reportDataGrouped.value[categoryName];
  if (!category) return 0;
  
  // Jika category adalah array, hitung dari items
  if (Array.isArray(category)) {
    return category.reduce((sum, item) => {
      return sum + (item.difference_subtotal_mac || 0);
    }, 0);
  }
  
  // Jika category adalah object dengan _subtotal, gunakan itu
  if (category._subtotal_difference_mac !== undefined) {
    return category._subtotal_difference_mac;
  }
  
  return 0;
};

// Filtered report data grouped (for client-side search if needed, but we're using server-side)
const filteredReportDataGrouped = computed(() => {
  return reportDataGrouped.value;
});

const exportToExcel = () => {
  if (!canExport.value) {
    return;
  }
  
  exporting.value = true;
  const params = new URLSearchParams({
    outlet_id: filters.value.outlet_id,
    warehouse_outlet_id: filters.value.warehouse_outlet_id,
    bulan: filters.value.bulan,
    search: filters.value.search || '',
  });
  
  window.location.href = '/outlet-stock-report/export?' + params.toString();
  
  // Reset exporting after a delay (in case download doesn't trigger)
  setTimeout(() => {
    exporting.value = false;
  }, 2000);
};

onMounted(() => {
  // Initialize filters from props
  if (props.filters) {
    filters.value = { ...props.filters };
  }
  
  // Auto expand all categories when data loads
  if (reportDataGrouped.value && Object.keys(reportDataGrouped.value).length > 0) {
    expandedCategories.value = Object.keys(reportDataGrouped.value);
  }
});
</script>

