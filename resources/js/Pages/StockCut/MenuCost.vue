<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-2">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-calculator text-green-500"></i> Report Cost Per Menu
        </h1>
        <div class="flex gap-2">
          <Link :href="route('stock-cut.form')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
            <i class="fa-solid fa-plus mr-1"></i> Stock Cut
          </Link>
          <Link :href="route('stock-cut.index')" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700 transition">
            <i class="fa-solid fa-list mr-1"></i> Log Stock Cut
          </Link>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="bg-white rounded-xl shadow-xl p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
            <select v-model="filters.outlet_id" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Pilih Outlet</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal</label>
            <input type="date" v-model="filters.tanggal" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
            <select v-model="filters.type" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Semua</option>
              <option value="food">Food</option>
              <option value="beverages">Beverages</option>
            </select>
          </div>
          <div class="flex items-end">
            <button @click="loadMenuCosts" class="w-full bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
              <i class="fa-solid fa-search mr-1"></i> Cari
            </button>
          </div>
        </div>
      </div>

      <!-- Summary Cards -->
      <div v-if="summary" class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
        <div class="bg-blue-50 rounded-xl p-6 border border-blue-200">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
              <i class="fa-solid fa-utensils text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-blue-600">Total Menu</p>
              <p class="text-2xl font-bold text-blue-900">{{ summary.total_menu }}</p>
            </div>
          </div>
        </div>
        <div class="bg-purple-50 rounded-xl p-6 border border-purple-200">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
              <i class="fa-solid fa-sliders text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-purple-600">Total Modifier</p>
              <p class="text-2xl font-bold text-purple-900">{{ summary.total_modifier || 0 }}</p>
            </div>
          </div>
        </div>
        <div class="bg-green-50 rounded-xl p-6 border border-green-200">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
              <i class="fa-solid fa-coins text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-green-600">Total Cost</p>
              <p class="text-2xl font-bold text-green-900">Rp {{ formatNumber(summary.total_cost) }}</p>
            </div>
          </div>
        </div>
        <div class="bg-orange-50 rounded-xl p-6 border border-orange-200">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 text-orange-600">
              <i class="fa-solid fa-money-bill-wave text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-orange-600">Total Revenue</p>
              <p class="text-2xl font-bold text-orange-900">Rp {{ formatNumber(summary.total_revenue) }}</p>
            </div>
          </div>
        </div>
        <div class="bg-emerald-50 rounded-xl p-6 border border-emerald-200">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-emerald-100 text-emerald-600">
              <i class="fa-solid fa-chart-line text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-emerald-600">Total Profit</p>
              <p class="text-2xl font-bold text-emerald-900">Rp {{ formatNumber(summary.total_profit) }}</p>
            </div>
          </div>
        </div>
        <div class="bg-indigo-50 rounded-xl p-6 border border-indigo-200">
          <div class="flex items-center">
            <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
              <i class="fa-solid fa-percentage text-xl"></i>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-indigo-600">Profit Margin</p>
              <p class="text-2xl font-bold text-indigo-900">{{ summary.total_profit_margin }}%</p>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Cost Breakdown -->
      <div v-if="summary" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-green-50 rounded-xl p-4 border border-green-200">
          <p class="text-sm font-medium text-green-700 mb-1">Menu Cost</p>
          <p class="text-xl font-bold text-green-900">Rp {{ formatNumber(summary.total_menu_cost || 0) }}</p>
        </div>
        <div class="bg-purple-50 rounded-xl p-4 border border-purple-200">
          <p class="text-sm font-medium text-purple-700 mb-1">Modifier Cost</p>
          <p class="text-xl font-bold text-purple-900">Rp {{ formatNumber(summary.total_modifier_cost || 0) }}</p>
        </div>
      </div>

      <!-- Periode Info -->
      <div v-if="summary" class="bg-white rounded-xl shadow-xl p-4 mb-6">
        <div class="flex items-center justify-center">
          <div class="flex items-center gap-2 text-gray-600">
            <i class="fa-solid fa-calendar text-purple-500"></i>
            <span class="font-medium">Periode:</span>
            <span class="text-purple-600 font-semibold">{{ formatDate(summary.periode) }}</span>
          </div>
        </div>
      </div>

      <!-- Menu Cost Table -->
      <div v-if="filteredMenuCostsGrouped && Object.keys(filteredMenuCostsGrouped).length > 0" class="bg-white rounded-xl shadow-xl p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-lg font-semibold text-gray-800">Detail Cost Per Menu</h2>
          <div class="flex gap-2">
            <input
              v-model="searchMenu"
              type="text"
              placeholder="Cari menu..."
              class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <button @click="exportToExcel" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
              <i class="fa-solid fa-file-excel mr-1"></i> Export Excel
            </button>
          </div>
        </div>
        
        <div class="overflow-x-auto">
          <template v-for="(menus, categoryName) in filteredMenuCostsGrouped" :key="categoryName">
            <!-- Category Header -->
            <div class="mb-4">
              <div class="bg-blue-100 px-4 py-2 rounded-t-lg border-b-2 border-blue-300">
                <h3 class="text-lg font-semibold text-blue-900 flex items-center gap-2">
                  <i class="fa-solid fa-folder text-blue-600"></i>
                  {{ categoryName }}
                  <span class="text-sm font-normal text-blue-700">({{ menus.length }} menu)</span>
                </h3>
              </div>
            </div>
            
            <table class="min-w-full divide-y divide-gray-200 mb-6">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Menu</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sub Kategori</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost/Unit</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Harga Menu</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profit</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Margin</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detail</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <template v-for="menu in menus" :key="menu.item_id">
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">{{ menu.item_name }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm text-gray-900">{{ menu.sub_category_name }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ menu.qty_ordered }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                      Rp {{ formatNumber(menu.cost_per_unit) }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      Rp {{ formatNumber(menu.total_cost) }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                      Rp {{ formatNumber(menu.menu_price) }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                      Rp {{ formatNumber(menu.total_revenue) }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium" :class="menu.profit >= 0 ? 'text-green-600' : 'text-red-600'">
                      Rp {{ formatNumber(menu.profit) }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium" :class="menu.profit_margin >= 0 ? 'text-green-600' : 'text-red-600'">
                      {{ menu.profit_margin }}%
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <button @click="toggleBomDetails(menu.item_id)" class="text-blue-600 hover:text-blue-800">
                        <i :class="expandedMenus.includes(menu.item_id) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'"></i>
                        {{ expandedMenus.includes(menu.item_id) ? 'Sembunyikan' : 'Tampilkan' }}
                      </button>
                    </td>
                  </tr>
                  <!-- BOM Details Row -->
                  <tr v-if="expandedMenus.includes(menu.item_id)" :key="`bom-${menu.item_id}`" class="bg-gray-50">
                    <td colspan="10" class="px-4 py-4">
                      <div class="bg-white rounded-lg p-4 border">
                        <h4 class="font-medium text-gray-900 mb-3">Detail Bahan Baku:</h4>
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 text-sm">
                          <div class="font-medium text-gray-700">Bahan Baku</div>
                          <div class="font-medium text-gray-700">Qty</div>
                          <div class="font-medium text-gray-700">Unit</div>
                          <div class="font-medium text-gray-700">Cost/Unit</div>
                          <div class="font-medium text-gray-700">Total</div>
                        </div>
                        <div v-for="(bom, bomIndex) in (menu.bom_details || [])" :key="`${menu.item_id}-bom-${bomIndex}`" class="grid grid-cols-1 md:grid-cols-5 gap-4 py-2 border-b border-gray-100">
                          <div class="text-gray-900">{{ bom.material_name }}</div>
                          <div class="text-gray-900">{{ bom.qty_needed }}</div>
                          <div class="text-gray-900">{{ bom.unit_name }}</div>
                          <div class="text-gray-900">Rp {{ formatNumber(bom.cost_per_unit) }}</div>
                          <div class="text-gray-900 font-medium">Rp {{ formatNumber(bom.total_cost) }}</div>
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </template>
        </div>
      </div>

      <!-- Modifier Cost Table -->
      <div v-if="filteredModifierCostsGrouped && Object.keys(filteredModifierCostsGrouped).length > 0" class="bg-white rounded-xl shadow-xl p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-lg font-semibold text-gray-800">Detail Cost Per Modifier</h2>
          <input
            v-model="searchModifier"
            type="text"
            placeholder="Cari modifier..."
            class="px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
          />
        </div>
        
        <div class="overflow-x-auto">
          <template v-for="(modifiers, categoryName) in filteredModifierCostsGrouped" :key="categoryName">
            <!-- Category Header -->
            <div class="mb-4">
              <div class="bg-purple-100 px-4 py-2 rounded-t-lg border-b-2 border-purple-300">
                <h3 class="text-lg font-semibold text-purple-900 flex items-center gap-2">
                  <i class="fa-solid fa-folder text-purple-600"></i>
                  {{ categoryName }}
                  <span class="text-sm font-normal text-purple-700">({{ modifiers.length }} modifier)</span>
                </h3>
              </div>
            </div>
            
            <table class="min-w-full divide-y divide-gray-200 mb-6">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modifier</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cost/Unit</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detail</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <template v-for="modifier in modifiers" :key="modifier.modifier_name">
                  <tr class="hover:bg-gray-50">
                    <td class="px-4 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">{{ modifier.modifier_name }}</div>
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ modifier.total_qty }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900">
                      Rp {{ formatNumber(modifier.cost_per_unit) }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                      Rp {{ formatNumber(modifier.total_cost) }}
                    </td>
                    <td class="px-4 py-4 whitespace-nowrap">
                      <button @click="toggleModifierBomDetails(modifier.modifier_name)" class="text-blue-600 hover:text-blue-800">
                        <i :class="expandedModifiers.includes(modifier.modifier_name) ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'"></i>
                        {{ expandedModifiers.includes(modifier.modifier_name) ? 'Sembunyikan' : 'Tampilkan' }}
                      </button>
                    </td>
                  </tr>
                  <!-- Modifier BOM Details Row -->
                  <tr v-if="expandedModifiers.includes(modifier.modifier_name)" :key="`modifier-bom-${modifier.modifier_name}`" class="bg-gray-50">
                    <td colspan="5" class="px-4 py-4">
                      <div class="bg-white rounded-lg p-4 border">
                        <h4 class="font-medium text-gray-900 mb-3">Detail Bahan Baku Modifier:</h4>
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 text-sm">
                          <div class="font-medium text-gray-700">Bahan Baku</div>
                          <div class="font-medium text-gray-700">Qty</div>
                          <div class="font-medium text-gray-700">Unit</div>
                          <div class="font-medium text-gray-700">Cost/Unit</div>
                          <div class="font-medium text-gray-700">Total</div>
                        </div>
                        <div v-for="(bom, bomIndex) in (modifier.bom_details || [])" :key="`${modifier.modifier_name}-bom-${bomIndex}`" class="grid grid-cols-1 md:grid-cols-5 gap-4 py-2 border-b border-gray-100">
                          <div class="text-gray-900">{{ bom.material_name }}</div>
                          <div class="text-gray-900">{{ bom.qty_needed }}</div>
                          <div class="text-gray-900">{{ bom.unit_name }}</div>
                          <div class="text-gray-900">Rp {{ formatNumber(bom.cost_per_unit) }}</div>
                          <div class="text-gray-900 font-medium">Rp {{ formatNumber(bom.total_cost) }}</div>
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </template>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!loading && !hasSearched && (!filteredMenuCostsGrouped || Object.keys(filteredMenuCostsGrouped).length === 0) && (!filteredModifierCostsGrouped || Object.keys(filteredModifierCostsGrouped).length === 0)" class="bg-white rounded-xl shadow-xl p-6 text-center">
        <div class="text-gray-500">
          <i class="fa-solid fa-calculator text-4xl mb-4"></i>
          <p class="text-lg font-medium">Pilih filter dan klik tombol "Cari" untuk melihat report cost per menu</p>
          <p class="text-sm mt-2">Pastikan outlet dan tanggal sudah dipilih sebelum mencari</p>
        </div>
      </div>

      <!-- No Data State -->
      <div v-if="!loading && hasSearched && (!filteredMenuCostsGrouped || Object.keys(filteredMenuCostsGrouped).length === 0) && (!filteredModifierCostsGrouped || Object.keys(filteredModifierCostsGrouped).length === 0)" class="bg-white rounded-xl shadow-xl p-6 text-center">
        <div class="text-gray-500">
          <i class="fa-solid fa-calculator text-4xl mb-4"></i>
          <p class="text-lg font-medium">Tidak ada data cost menu</p>
          <p class="text-sm">Tidak ada order items yang sudah dipotong stock untuk periode yang dipilih</p>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="bg-white rounded-xl shadow-xl p-6 text-center">
        <div class="text-gray-500">
          <i class="fa-solid fa-spinner fa-spin text-4xl mb-4"></i>
          <p class="text-lg font-medium">Memuat data...</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'

const filters = ref({
  outlet_id: '',
  tanggal: new Date().toISOString().split('T')[0],
  type: ''
})

const outlets = ref([])
const menuCosts = ref([])
const menuCostsGrouped = ref({})
const modifierCosts = ref([])
const modifierCostsGrouped = ref({})
const summary = ref(null)
const loading = ref(false)
const expandedMenus = ref([])
const expandedModifiers = ref([])
const hasSearched = ref(false)
const searchMenu = ref('')
const searchModifier = ref('')

onMounted(async () => {
  await loadOutlets()
})

async function loadOutlets() {
  try {
    console.log('Loading outlets from /api/outlets/report...')
    const response = await axios.get('/api/outlets/report')
    console.log('Response from /api/outlets/report:', response.data)
    outlets.value = response.data.outlets || []
    console.log('Outlets loaded:', outlets.value)
  } catch (error) {
    console.error('Error loading outlets:', error)
    console.error('Error response:', error.response?.data)
    outlets.value = []
  }
}

async function loadMenuCosts() {
  if (!filters.value.outlet_id || !filters.value.tanggal) {
    alert('Silakan pilih outlet dan tanggal terlebih dahulu')
    return
  }

  loading.value = true
  hasSearched.value = true
  try {
    const params = {
      id_outlet: filters.value.outlet_id,
      tanggal: filters.value.tanggal,
      type: filters.value.type
    }
    
    console.log('Loading menu costs with params:', params)
    const response = await axios.get('/api/stock-cut/menu-cost', { params })
    console.log('Menu costs response:', response.data)
    
    if (response.data.status === 'success') {
      menuCosts.value = response.data.menu_costs || []
      menuCostsGrouped.value = response.data.menu_costs_grouped || {}
      modifierCosts.value = response.data.modifier_costs || []
      modifierCostsGrouped.value = response.data.modifier_costs_grouped || {}
      summary.value = {
        total_menu: response.data.total_menu || 0,
        total_modifier: response.data.total_modifier || 0,
        total_menu_cost: response.data.total_menu_cost || 0,
        total_modifier_cost: response.data.total_modifier_cost || 0,
        total_cost: response.data.total_cost || 0,
        total_revenue: response.data.total_revenue || 0,
        total_profit: response.data.total_profit || 0,
        total_profit_margin: response.data.total_profit_margin || 0,
        periode: response.data.periode
      }
    } else {
      menuCosts.value = []
      menuCostsGrouped.value = {}
      modifierCosts.value = []
      modifierCostsGrouped.value = {}
      summary.value = null
      console.warn('API returned non-success status:', response.data)
    }
  } catch (error) {
    console.error('Error loading menu costs:', error)
    console.error('Error response:', error.response?.data)
    menuCosts.value = []
    menuCostsGrouped.value = {}
    modifierCosts.value = []
    modifierCostsGrouped.value = {}
    summary.value = null
  } finally {
    loading.value = false
  }
}

// Filtered menu costs grouped by category with search
const filteredMenuCostsGrouped = computed(() => {
  if (!menuCostsGrouped.value || Object.keys(menuCostsGrouped.value).length === 0) {
    return {}
  }
  
  const search = searchMenu.value.toLowerCase().trim()
  if (!search) {
    return menuCostsGrouped.value
  }
  
  const filtered = {}
  for (const [categoryName, menus] of Object.entries(menuCostsGrouped.value)) {
    const filteredMenus = menus.filter(menu => 
      menu.item_name.toLowerCase().includes(search) ||
      menu.category_name.toLowerCase().includes(search) ||
      (menu.sub_category_name && menu.sub_category_name.toLowerCase().includes(search))
    )
    if (filteredMenus.length > 0) {
      filtered[categoryName] = filteredMenus
    }
  }
  return filtered
})

// Filtered modifier costs grouped by category with search
const filteredModifierCostsGrouped = computed(() => {
  if (!modifierCostsGrouped.value || Object.keys(modifierCostsGrouped.value).length === 0) {
    return {}
  }
  
  const search = searchModifier.value.toLowerCase().trim()
  if (!search) {
    return modifierCostsGrouped.value
  }
  
  const filtered = {}
  for (const [categoryName, modifiers] of Object.entries(modifierCostsGrouped.value)) {
    const filteredModifiers = modifiers.filter(modifier => 
      modifier.modifier_name.toLowerCase().includes(search) ||
      modifier.category_name.toLowerCase().includes(search)
    )
    if (filteredModifiers.length > 0) {
      filtered[categoryName] = filteredModifiers
    }
  }
  return filtered
})

function toggleBomDetails(itemId) {
  const index = expandedMenus.value.indexOf(itemId)
  if (index > -1) {
    expandedMenus.value.splice(index, 1)
  } else {
    expandedMenus.value.push(itemId)
  }
}

function toggleModifierBomDetails(modifierName) {
  const index = expandedModifiers.value.indexOf(modifierName)
  if (index > -1) {
    expandedModifiers.value.splice(index, 1)
  } else {
    expandedModifiers.value.push(modifierName)
  }
}

function formatNumber(number) {
  if (number === null || number === undefined || isNaN(number)) {
    return '0,00'
  }
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(number)
}

function formatDate(dateString) {
  if (!dateString) return 'Tidak ada tanggal'
  
  const date = new Date(dateString)
  if (isNaN(date.getTime())) {
    return 'Tanggal tidak valid'
  }
  
  return date.toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

function exportToExcel() {
  // Implementasi export Excel bisa ditambahkan di sini
  alert('Fitur export Excel akan segera tersedia')
}
</script>

