<template>
  <AppLayout>
    <Head title="Opex Outlet Dashboard" />
    
    <div class="max-w-7xl mx-auto py-8 px-4">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
          <i class="fa-solid fa-chart-line text-blue-500"></i>
          Opex Outlet Dashboard
        </h1>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Dari</label>
            <input
              type="date"
              v-model="filters.date_from"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Sampai</label>
            <input
              type="date"
              v-model="filters.date_to"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div v-if="canSelectOutlet">
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
            <select
              v-model="filters.outlet_id"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option :value="null">Semua Outlet</option>
              <option v-for="outlet in props.outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                {{ outlet.nama_outlet }}
              </option>
            </select>
          </div>
          <div v-else>
            <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
            <input
              type="text"
              :value="currentOutletName"
              disabled
              class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed"
            />
          </div>
          <div class="flex items-end">
            <button
              @click="applyFilters"
              class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
            >
              <i class="fa-solid fa-filter mr-2"></i>
              Terapkan Filter
            </button>
          </div>
        </div>
      </div>

      <!-- Message jika outlet belum dipilih (untuk admin) -->
      <div v-if="canSelectOutlet && !filters.outlet_id" class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-lg">
        <div class="flex items-center">
          <i class="fa-solid fa-exclamation-triangle text-yellow-600 text-xl mr-3"></i>
          <div>
            <p class="text-yellow-800 font-semibold">Pilih Outlet Terlebih Dahulu</p>
            <p class="text-yellow-700 text-sm mt-1">Silakan pilih outlet dari filter di atas untuk menampilkan data dashboard.</p>
          </div>
        </div>
      </div>

      <!-- Overview Cards -->
      <div v-if="!canSelectOutlet || filters.outlet_id" class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
        <div 
          @click="openCardModal('total_paid')"
          class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500 cursor-pointer hover:shadow-xl transition-shadow"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Paid PR</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(dashboardData.overview?.total_paid || 0) }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ dashboardData.overview?.payment_count || 0 }} payments</p>
            </div>
            <i class="fa-solid fa-money-bill-wave text-4xl text-blue-300"></i>
          </div>
        </div>

        <div 
          @click="openCardModal('retail_non_food')"
          class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 cursor-pointer hover:shadow-xl transition-shadow"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Retail Non Food</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(dashboardData.overview?.total_retail_non_food || 0) }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ dashboardData.overview?.retail_non_food_count || 0 }} transactions</p>
            </div>
            <i class="fa-solid fa-shopping-bag text-4xl text-green-300"></i>
          </div>
        </div>

        <div 
          @click="openCardModal('food')"
          class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500 cursor-pointer hover:shadow-xl transition-shadow"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Food Expenses</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(dashboardData.overview?.total_food || 0) }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ dashboardData.overview?.food_count || 0 }} transactions</p>
            </div>
            <i class="fa-solid fa-utensils text-4xl text-yellow-300"></i>
          </div>
        </div>

        <div 
          @click="openCardModal('unpaid_pr')"
          class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500 cursor-pointer hover:shadow-xl transition-shadow"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Unpaid PR</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(dashboardData.overview?.total_unpaid || 0) }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ dashboardData.overview?.unpaid_pr_count || 0 }} PRs</p>
            </div>
            <i class="fa-solid fa-exclamation-triangle text-4xl text-orange-300"></i>
          </div>
        </div>

        <div 
          @click="openCardModal('total_opex')"
          class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500 cursor-pointer hover:shadow-xl transition-shadow"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Opex</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(dashboardData.overview?.total_opex || 0) }}</p>
              <p class="text-xs text-gray-500 mt-1">Paid + RNF + Food</p>
            </div>
            <i class="fa-solid fa-chart-pie text-4xl text-purple-300"></i>
          </div>
        </div>
      </div>

      <!-- Opex Trend Chart -->
      <div v-if="!canSelectOutlet || filters.outlet_id" class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Opex Trend</h2>
        <apexchart
          type="line"
          height="350"
          :options="opexTrendOptions"
          :series="opexTrendSeries"
        />
      </div>

      <!-- Opex by Category Chart -->
      <div v-if="!canSelectOutlet || filters.outlet_id" class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Opex by Category</h2>
        <apexchart
          type="bar"
          height="400"
          :options="opexByCategoryOptions"
          :series="opexByCategorySeries"
        />
      </div>

      <!-- Food by Category Chart -->
      <div v-if="!canSelectOutlet || filters.outlet_id" class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Food Expenses by Category Item</h2>
        <apexchart
          type="bar"
          height="400"
          :options="foodByCategoryOptions"
          :series="foodByCategorySeries"
        />
      </div>

      <!-- Food Category Items Modal -->
      <div v-if="showFoodCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" @click.self="closeFoodCategoryModal">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
          <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-800">
              Detail Items - {{ selectedFoodCategory?.category_name }}{{ selectedFoodCategory?.sub_category_name ? ' - ' + selectedFoodCategory.sub_category_name : '' }}
            </h3>
            <button @click="closeFoodCategoryModal" class="text-gray-400 hover:text-gray-600">
              <i class="fas fa-times text-xl"></i>
            </button>
          </div>
          
          <div class="flex-1 overflow-y-auto p-6">
            <div v-if="loadingFoodCategoryItems" class="text-center py-8">
              <i class="fas fa-spinner fa-spin text-3xl text-gray-400"></i>
              <p class="mt-2 text-gray-500">Memuat data...</p>
            </div>
            
            <div v-else-if="foodCategoryItems.length === 0" class="text-center py-8 text-gray-500">
              Tidak ada data
            </div>
            
            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider w-10"></th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Item Name</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider">Qty</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider">Unit</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700 uppercase tracking-wider">Total</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-gray-700 uppercase tracking-wider">Source</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <template v-for="(item, index) in foodCategoryItems" :key="index">
                    <tr 
                      v-if="item.transactions && item.transactions.length > 0"
                      @click="toggleFoodItem(index)"
                      class="hover:bg-gray-50 cursor-pointer"
                    >
                      <td class="px-4 py-3 text-sm">
                        <i :class="expandedFoodItems.includes(index) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
                      </td>
                      <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ item.item_name }}</td>
                      <td class="px-4 py-3 text-sm text-right text-gray-900">{{ item.total_qty.toLocaleString('id-ID') }}</td>
                      <td class="px-4 py-3 text-sm text-right text-gray-600">{{ item.unit || '-' }}</td>
                      <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">{{ formatCurrency(item.price * item.total_qty) }}</td>
                      <td class="px-4 py-3 text-sm text-center">
                        <span v-if="item.source_type === 'floor_order'" class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-semibold">Floor Order</span>
                        <span v-else-if="item.source_type === 'retail_food'" class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-semibold">Retail Food</span>
                        <span v-else-if="item.source_type === 'mixed'" class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs font-semibold">Mixed</span>
                      </td>
                    </tr>
                    <tr 
                      v-else
                      class="hover:bg-gray-50"
                    >
                      <td class="px-4 py-3 text-sm"></td>
                      <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ item.item_name }}</td>
                      <td class="px-4 py-3 text-sm text-right text-gray-900">{{ item.total_qty.toLocaleString('id-ID') }}</td>
                      <td class="px-4 py-3 text-sm text-right text-gray-600">{{ item.unit || '-' }}</td>
                      <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">{{ formatCurrency(item.price * item.total_qty) }}</td>
                      <td class="px-4 py-3 text-sm text-center">
                        <span v-if="item.source_type === 'floor_order'" class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-semibold">Floor Order</span>
                        <span v-else-if="item.source_type === 'retail_food'" class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-semibold">Retail Food</span>
                        <span v-else-if="item.source_type === 'mixed'" class="px-2 py-1 bg-purple-100 text-purple-800 rounded text-xs font-semibold">Mixed</span>
                      </td>
                    </tr>
                    <tr v-if="expandedFoodItems.includes(index) && item.transactions && item.transactions.length > 0">
                      <td colspan="6" class="px-4 py-3 bg-gray-50">
                        <div class="overflow-x-auto">
                          <table class="min-w-full text-xs">
                            <thead class="bg-gray-100">
                              <tr>
                                <th v-if="item.source_type === 'floor_order' || item.source_type === 'mixed'" class="px-2 py-1 text-left">Floor Order</th>
                                <th v-if="item.source_type === 'floor_order' || item.source_type === 'mixed'" class="px-2 py-1 text-left">DO</th>
                                <th v-if="item.source_type === 'floor_order' || item.source_type === 'mixed'" class="px-2 py-1 text-left">GR</th>
                                <th v-if="item.source_type === 'floor_order' || item.source_type === 'mixed'" class="px-2 py-1 text-left">Creator FO</th>
                                <th v-if="item.source_type === 'floor_order' || item.source_type === 'mixed'" class="px-2 py-1 text-left">Creator DO</th>
                                <th v-if="item.source_type === 'floor_order' || item.source_type === 'mixed'" class="px-2 py-1 text-left">Creator GR</th>
                                <th v-if="item.source_type === 'floor_order' || item.source_type === 'mixed'" class="px-2 py-1 text-right">Qty (Order)</th>
                                <th v-if="item.source_type === 'floor_order' || item.source_type === 'mixed'" class="px-2 py-1 text-right">Price (Order)</th>
                                <th v-if="item.source_type === 'floor_order' || item.source_type === 'mixed'" class="px-2 py-1 text-right">Qty (Received)</th>
                                <th v-if="item.source_type === 'floor_order' || item.source_type === 'mixed'" class="px-2 py-1 text-right">Subtotal</th>
                                <th v-if="item.source_type === 'retail_food' || item.source_type === 'mixed'" class="px-2 py-1 text-left">Retail Number</th>
                                <th v-if="item.source_type === 'retail_food' || item.source_type === 'mixed'" class="px-2 py-1 text-left">Creator</th>
                                <th v-if="item.source_type === 'retail_food' || item.source_type === 'mixed'" class="px-2 py-1 text-right">Qty</th>
                                <th v-if="item.source_type === 'retail_food' || item.source_type === 'mixed'" class="px-2 py-1 text-right">Price</th>
                                <th v-if="item.source_type === 'retail_food' || item.source_type === 'mixed'" class="px-2 py-1 text-right">Subtotal</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr v-for="(transaction, tIndex) in item.transactions" :key="tIndex" class="border-b border-gray-200">
                                <!-- Floor Order transaction -->
                                <template v-if="transaction.floor_order_number || transaction.gr_number">
                                  <td class="px-2 py-1">{{ transaction.floor_order_number || '-' }}</td>
                                  <td class="px-2 py-1">{{ transaction.do_number || '-' }}</td>
                                  <td class="px-2 py-1">{{ transaction.gr_number || '-' }}</td>
                                  <td class="px-2 py-1">
                                    <div>{{ transaction.fo_creator_name || '-' }}</div>
                                    <div v-if="transaction.fo_created_at" class="text-xs text-gray-500 mt-1">
                                      {{ formatDateTime(transaction.fo_created_at) }}
                                    </div>
                                  </td>
                                  <td class="px-2 py-1">
                                    <div>{{ transaction.do_creator_name || '-' }}</div>
                                    <div v-if="transaction.do_created_at" class="text-xs text-gray-500 mt-1">
                                      {{ formatDateTime(transaction.do_created_at) }}
                                    </div>
                                  </td>
                                  <td class="px-2 py-1">
                                    <div>{{ transaction.gr_creator_name || '-' }}</div>
                                    <div v-if="transaction.gr_created_at" class="text-xs text-gray-500 mt-1">
                                      {{ formatDateTime(transaction.gr_created_at) }}
                                    </div>
                                  </td>
                                  <td class="px-2 py-1 text-right">{{ transaction.fo_qty ? transaction.fo_qty.toLocaleString('id-ID') : '-' }}</td>
                                  <td class="px-2 py-1 text-right">{{ transaction.fo_price ? formatCurrency(transaction.fo_price) : '-' }}</td>
                                  <td class="px-2 py-1 text-right">{{ transaction.received_qty ? transaction.received_qty.toLocaleString('id-ID') : '-' }}</td>
                                  <td class="px-2 py-1 text-right font-semibold">{{ transaction.fo_price && transaction.fo_qty ? formatCurrency(transaction.fo_price * transaction.fo_qty) : '-' }}</td>
                                  <td v-if="item.source_type === 'mixed'" colspan="5" class="px-2 py-1">-</td>
                                </template>
                                <!-- Retail Food transaction -->
                                <template v-else-if="transaction.retail_number">
                                  <td v-if="item.source_type === 'mixed'" colspan="10" class="px-2 py-1">-</td>
                                  <td class="px-2 py-1">{{ transaction.retail_number || '-' }}</td>
                                  <td class="px-2 py-1">
                                    <div>{{ transaction.rf_creator_name || '-' }}</div>
                                    <div v-if="transaction.rf_created_at" class="text-xs text-gray-500 mt-1">
                                      {{ formatDateTime(transaction.rf_created_at) }}
                                    </div>
                                  </td>
                                  <td class="px-2 py-1 text-right">{{ transaction.qty ? transaction.qty.toLocaleString('id-ID') : '-' }}</td>
                                  <td class="px-2 py-1 text-right">{{ transaction.price ? formatCurrency(transaction.price) : '-' }}</td>
                                  <td class="px-2 py-1 text-right font-semibold">{{ transaction.price && transaction.qty ? formatCurrency(transaction.price * transaction.qty) : '-' }}</td>
                                </template>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
                <tfoot class="bg-gray-50">
                  <tr>
                    <td class="px-4 py-3 text-sm font-bold text-gray-900" colspan="5">Total</td>
                    <td class="px-4 py-3 text-sm text-right font-bold text-gray-900">
                      {{ formatCurrency(foodCategoryItems.reduce((sum, item) => sum + (item.price * item.total_qty), 0)) }}
                    </td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Category Detail Modal -->
      <div v-if="showCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" @click.self="closeCategoryModal">
        <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
            <h2 class="text-2xl font-bold text-gray-800">
              <span v-if="categoryDetail?.category">
                [{{ categoryDetail.category.division }}] {{ categoryDetail.category.name }}
              </span>
            </h2>
            <button @click="closeCategoryModal" class="text-gray-400 hover:text-gray-600">
              <i class="fas fa-times text-2xl"></i>
            </button>
          </div>
          
          <div class="p-6">
            <!-- Loading State -->
            <div v-if="loadingCategoryDetail" class="text-center py-12">
              <i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i>
              <p class="mt-4 text-gray-600">Memuat data...</p>
            </div>
            
            <!-- Content -->
            <div v-else>
              <!-- Trend Chart -->
              <div class="mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Trend</h3>
                <apexchart
                  type="line"
                  height="300"
                  :options="categoryTrendOptions"
                  :series="categoryTrendSeries"
                />
              </div>
              
              <!-- Transactions Table -->
              <div>
                <h3 class="text-lg font-bold text-gray-800 mb-4">Transaksi</h3>
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-12"></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Number</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Outlet</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <template v-for="transaction in categoryDetail?.transactions" :key="transaction.id">
                        <tr class="hover:bg-gray-50 cursor-pointer" @click="toggleCategoryTransaction(transaction.id)">
                          <td class="px-4 py-3 text-sm">
                            <i :class="expandedCategoryTransactions.includes(transaction.id) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
                          </td>
                          <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(transaction.payment_date) }}</td>
                          <td class="px-4 py-3 text-sm">
                            <div class="flex flex-col gap-1">
                              <span :class="transaction.type === 'payment' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'" class="px-2 py-1 rounded text-xs font-semibold">
                                {{ transaction.type === 'payment' ? 'Payment' : 'Retail Non Food' }}
                              </span>
                              <span v-if="transaction.type === 'floor_order_gr'" :class="transaction.has_payment ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'" class="px-2 py-1 rounded text-xs font-semibold">
                                {{ transaction.has_payment ? 'Paid' : 'Unpaid' }}
                              </span>
                            </div>
                          </td>
                          <td class="px-4 py-3 text-sm font-medium text-gray-900">
                            <div class="whitespace-normal break-words max-w-xs">
                              {{ transaction.payment_number || transaction.retail_number || transaction.number || '-' }}
                            </div>
                          </td>
                          <td class="px-4 py-3 text-sm text-gray-600">{{ transaction.outlet_name || '-' }}</td>
                          <td class="px-4 py-3 text-sm text-right font-semibold text-green-600">{{ formatCurrency(transaction.amount) }}</td>
                        </tr>
                        <tr v-if="expandedCategoryTransactions.includes(transaction.id) && transaction.items && transaction.items.length > 0">
                          <td colspan="6" class="px-4 py-3 bg-gray-50">
                            <div class="overflow-x-auto">
                              <table class="min-w-full text-xs">
                                <thead class="bg-gray-100">
                                  <tr>
                                    <th class="px-2 py-1 text-left">Item</th>
                                    <th class="px-2 py-1 text-right">Qty</th>
                                    <th class="px-2 py-1 text-right">Price</th>
                                    <th class="px-2 py-1 text-right">Total</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <tr v-for="item in transaction.items" :key="item.id" class="border-b border-gray-200">
                                    <td class="px-2 py-1">{{ item.item_name }}</td>
                                    <td class="px-2 py-1 text-right">{{ item.qty }} {{ item.unit }}</td>
                                    <td class="px-2 py-1 text-right">{{ formatCurrency(item.price) }}</td>
                                    <td class="px-2 py-1 text-right font-semibold">{{ formatCurrency(item.total || item.subtotal) }}</td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                          </td>
                        </tr>
                      </template>
                      <tr v-if="!categoryDetail?.transactions || categoryDetail.transactions.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Tidak ada data</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Card Detail Modal -->
      <div v-if="showCardModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4" @click.self="closeCardModal">
        <div class="bg-white rounded-xl shadow-2xl max-w-6xl w-full max-h-[90vh] overflow-y-auto">
          <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between z-10">
            <h2 class="text-2xl font-bold text-gray-800">
              {{ cardModalTitle }}
            </h2>
            <button @click="closeCardModal" class="text-gray-400 hover:text-gray-600">
              <i class="fas fa-times text-2xl"></i>
            </button>
          </div>
          
          <div class="p-6">
            <!-- Loading State -->
            <div v-if="loadingCardDetail" class="text-center py-12">
              <i class="fas fa-spinner fa-spin text-4xl text-blue-500"></i>
              <p class="mt-4 text-gray-600">Memuat data...</p>
            </div>
            
            <!-- Content -->
            <div v-else>
              <!-- Trend Chart -->
              <div class="mb-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Trend</h3>
                <apexchart
                  type="line"
                  height="300"
                  :options="cardTrendOptions"
                  :series="cardTrendSeries"
                />
              </div>
              
              <!-- Filters -->
              <div class="mb-6 bg-gray-50 rounded-lg p-4">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Dari</label>
                    <input
                      type="date"
                      v-model="cardModalFilters.date_from"
                      class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Tanggal Sampai</label>
                    <input
                      type="date"
                      v-model="cardModalFilters.date_to"
                      class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Search</label>
                    <input
                      type="text"
                      v-model="cardModalFilters.search"
                      @keyup.enter="applyCardModalFilters"
                      placeholder="Cari nomor, outlet, creator..."
                      class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Per Page</label>
                    <select
                      v-model="cardModalFilters.per_page"
                      class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                      <option :value="10">10</option>
                      <option :value="20">20</option>
                      <option :value="50">50</option>
                      <option :value="100">100</option>
                    </select>
                  </div>
                  <div class="flex items-end">
                    <button
                      @click="applyCardModalFilters"
                      class="w-full bg-blue-600 text-white px-3 py-1.5 text-sm rounded hover:bg-blue-700 transition-colors"
                    >
                      <i class="fa-solid fa-filter mr-1"></i>
                      Filter
                    </button>
                  </div>
                </div>
              </div>
              
              <!-- Transactions Table -->
              <div>
                <h3 class="text-lg font-bold text-gray-800 mb-4">Transaksi</h3>
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-12"></th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tanggal</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Number</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Outlet</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Creator</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <template v-for="transaction in cardDetail?.transactions" :key="transaction.id">
                        <tr class="hover:bg-gray-50 cursor-pointer" @click="toggleCardTransaction(transaction.id)">
                          <td class="px-4 py-3 text-sm">
                            <i :class="expandedCardTransactions.includes(transaction.id) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'"></i>
                          </td>
                          <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(transaction.payment_date || transaction.transaction_date || transaction.created_at) }}</td>
                          <td class="px-4 py-3 text-sm">
                            <div class="flex flex-col gap-1">
                              <span :class="getTransactionTypeClass(transaction.type)" class="px-2 py-1 rounded text-xs font-semibold">
                                {{ getTransactionTypeLabel(transaction.type) }}
                              </span>
                              <span v-if="transaction.type === 'floor_order_gr'" :class="transaction.has_payment ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'" class="px-2 py-1 rounded text-xs font-semibold">
                                {{ transaction.has_payment ? 'Paid' : 'Unpaid' }}
                              </span>
                            </div>
                          </td>
                          <td class="px-4 py-3 text-sm font-medium text-gray-900">
                            <div class="whitespace-normal break-words max-w-xs">
                              {{ transaction.payment_number || transaction.retail_number || transaction.pr_number || transaction.number || '-' }}
                            </div>
                          </td>
                          <td class="px-4 py-3 text-sm text-gray-600">{{ transaction.outlet_name || '-' }}</td>
                          <td class="px-4 py-3 text-sm text-gray-600" v-if="transaction.type !== 'floor_order_gr' && transaction.type !== 'retail_food'">
                            <span v-if="transaction.category_division && transaction.category_name">
                              [{{ transaction.category_division }}] {{ transaction.category_name }}
                            </span>
                            <span v-else>-</span>
                          </td>
                          <td class="px-4 py-3 text-sm text-gray-600" v-else>-</td>
                          <td class="px-4 py-3 text-sm text-gray-600">{{ transaction.creator_name || '-' }}</td>
                          <td class="px-4 py-3 text-sm text-right font-semibold" :class="getAmountClass(transaction.type)">
                            {{ formatCurrency(transaction.amount || transaction.unpaid_amount || transaction.total_amount) }}
                          </td>
                        </tr>
                        <tr v-if="expandedCardTransactions.includes(transaction.id) && transaction.items && transaction.items.length > 0">
                          <td colspan="8" class="px-4 py-3 bg-gray-50">
                            <div class="overflow-x-auto">
                              <table class="min-w-full text-xs">
                                <thead class="bg-gray-100">
                                  <tr>
                                    <th class="px-2 py-1 text-left">Item</th>
                                    <th class="px-2 py-1 text-right">Qty</th>
                                    <th class="px-2 py-1 text-right">Price</th>
                                    <th class="px-2 py-1 text-right">Total</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <tr v-for="item in transaction.items" :key="item.id" class="border-b border-gray-200">
                                    <td class="px-2 py-1">{{ item.item_name }}</td>
                                    <td class="px-2 py-1 text-right">{{ item.qty }} {{ item.unit }}</td>
                                    <td class="px-2 py-1 text-right">{{ formatCurrency(item.price) }}</td>
                                    <td class="px-2 py-1 text-right font-semibold">{{ formatCurrency(item.total || item.subtotal) }}</td>
                                  </tr>
                                </tbody>
                              </table>
                            </div>
                          </td>
                        </tr>
                      </template>
                      <tr v-if="!cardDetail?.transactions || cardDetail.transactions.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">Tidak ada data</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                
                <!-- Pagination -->
                <div v-if="cardDetail?.pagination && cardDetail.pagination.total_pages > 1" class="mt-4 flex items-center justify-between">
                  <div class="text-sm text-gray-700">
                    Menampilkan {{ cardDetail.pagination.from }} - {{ cardDetail.pagination.to }} dari {{ cardDetail.pagination.total }} transaksi
                  </div>
                  <div class="flex gap-2">
                    <button
                      @click="changeCardModalPage(cardDetail.pagination.current_page - 1)"
                      :disabled="cardDetail.pagination.current_page === 1"
                      :class="cardDetail.pagination.current_page === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100'"
                      class="px-3 py-1.5 text-sm border border-gray-300 rounded bg-white"
                    >
                      <i class="fas fa-chevron-left"></i>
                    </button>
                    <template v-for="page in Math.min(5, cardDetail.pagination.total_pages)" :key="page">
                      <button
                        v-if="page === 1 || page === cardDetail.pagination.total_pages || (page >= cardDetail.pagination.current_page - 1 && page <= cardDetail.pagination.current_page + 1)"
                        @click="changeCardModalPage(page)"
                        :class="page === cardDetail.pagination.current_page ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100'"
                        class="px-3 py-1.5 text-sm border border-gray-300 rounded"
                      >
                        {{ page }}
                      </button>
                      <span
                        v-else-if="page === cardDetail.pagination.current_page - 2 || page === cardDetail.pagination.current_page + 2"
                        class="px-2 py-1.5 text-sm text-gray-500"
                      >
                        ...
                      </span>
                    </template>
                    <button
                      @click="changeCardModalPage(cardDetail.pagination.current_page + 1)"
                      :disabled="cardDetail.pagination.current_page === cardDetail.pagination.total_pages"
                      :class="cardDetail.pagination.current_page === cardDetail.pagination.total_pages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-100'"
                      class="px-3 py-1.5 text-sm border border-gray-300 rounded bg-white"
                    >
                      <i class="fas fa-chevron-right"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';

const props = defineProps({
  dashboardData: Object,
  outlets: Array,
  filters: Object,
  userOutletId: Number,
  selectedOutletId: Number
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});

const canSelectOutlet = computed(() => props.userOutletId === 1);

const currentOutletName = computed(() => {
  if (props.selectedOutletId && props.outlets && props.outlets.length > 0) {
    const outlet = props.outlets.find(o => o.id_outlet === props.selectedOutletId);
    if (outlet) return outlet.nama_outlet;
  }
  return user.value?.outlet?.nama_outlet || 'Outlet User';
});

const dashboardData = computed(() => props.dashboardData || {});

const filters = ref({
  date_from: props.filters?.date_from || new Date().toISOString().split('T')[0],
  date_to: props.filters?.date_to || new Date().toISOString().split('T')[0],
  outlet_id: props.filters?.outlet_id || null
});

const expandedCategoryTransactions = ref([]);
const expandedCardTransactions = ref([]);

const showCategoryModal = ref(false);
const categoryDetail = ref(null);
const loadingCategoryDetail = ref(false);

const showCardModal = ref(false);
const cardDetail = ref(null);
const loadingCardDetail = ref(false);
const cardModalType = ref(null);
const cardModalFilters = ref({
  date_from: '',
  date_to: '',
  search: '',
  page: 1,
  per_page: 20
});

const foodByCategoryData = ref([]);
const loadingFoodByCategory = ref(false);

const showFoodCategoryModal = ref(false);
const foodCategoryItems = ref([]);
const loadingFoodCategoryItems = ref(false);
const selectedFoodCategory = ref(null);
const expandedFoodItems = ref([]);

// Store function reference for chart events
let openFoodCategoryModalFn = null;

function toggleCategoryTransaction(transactionId) {
  const index = expandedCategoryTransactions.value.indexOf(transactionId);
  if (index > -1) {
    expandedCategoryTransactions.value.splice(index, 1);
  } else {
    expandedCategoryTransactions.value.push(transactionId);
  }
}

function toggleCardTransaction(transactionId) {
  const index = expandedCardTransactions.value.indexOf(transactionId);
  if (index > -1) {
    expandedCardTransactions.value.splice(index, 1);
  } else {
    expandedCardTransactions.value.push(transactionId);
  }
}

function getTransactionTypeClass(type) {
  if (type === 'payment') return 'bg-blue-100 text-blue-800';
  if (type === 'retail_non_food') return 'bg-green-100 text-green-800';
  if (type === 'floor_order_gr' || type === 'retail_food') return 'bg-yellow-100 text-yellow-800';
  if (type === 'unpaid_pr') return 'bg-orange-100 text-orange-800';
  return 'bg-gray-100 text-gray-800';
}

function getTransactionTypeLabel(type) {
  if (type === 'payment') return 'Payment';
  if (type === 'retail_non_food') return 'Retail Non Food';
  if (type === 'floor_order_gr') return 'Floor Order GR';
  if (type === 'retail_food') return 'Retail Food';
  if (type === 'unpaid_pr') return 'Unpaid PR';
  return type;
}

function getAmountClass(type) {
  if (type === 'unpaid_pr') return 'text-orange-600';
  return 'text-green-600';
}

const cardModalTitle = computed(() => {
  if (cardModalType.value === 'total_paid') return 'Total Paid';
  if (cardModalType.value === 'retail_non_food') return 'Retail Non Food';
  if (cardModalType.value === 'food') return 'Food Expenses';
  if (cardModalType.value === 'unpaid_pr') return 'Unpaid PR';
  if (cardModalType.value === 'total_opex') return 'Total Opex';
  return '';
});

async function openCardModal(type) {
  showCardModal.value = true;
  loadingCardDetail.value = true;
  cardModalType.value = type;
  cardDetail.value = null;
  expandedCardTransactions.value = [];
  
  // Initialize modal filters with main filters
  cardModalFilters.value = {
    date_from: filters.value.date_from,
    date_to: filters.value.date_to,
    search: '',
    page: 1,
    per_page: 20
  };
  
  await fetchCardDetail();
}

async function fetchCardDetail() {
  if (!cardModalType.value) return;
  
  loadingCardDetail.value = true;
  try {
    const params = new URLSearchParams({
      type: cardModalType.value,
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
      outlet_id: filters.value.outlet_id || '',
      modal_date_from: cardModalFilters.value.date_from,
      modal_date_to: cardModalFilters.value.date_to,
      search: cardModalFilters.value.search,
      page: cardModalFilters.value.page,
      per_page: cardModalFilters.value.per_page
    });
    
    const response = await fetch(`/opex-outlet-dashboard/card-detail?${params.toString()}`);
    const data = await response.json();
    cardDetail.value = data;
  } catch (error) {
    console.error('Error fetching card detail:', error);
    alert('Terjadi kesalahan saat memuat data');
  } finally {
    loadingCardDetail.value = false;
  }
}

function applyCardModalFilters() {
  cardModalFilters.value.page = 1; // Reset to first page
  fetchCardDetail();
}

function changeCardModalPage(page) {
  cardModalFilters.value.page = page;
  fetchCardDetail();
}

function changeCardModalPerPage() {
  cardModalFilters.value.page = 1; // Reset to first page
  fetchCardDetail();
}

function closeCardModal() {
  showCardModal.value = false;
  cardDetail.value = null;
  cardModalType.value = null;
  expandedCardTransactions.value = [];
}

async function openCategoryModal(categoryId) {
  showCategoryModal.value = true;
  loadingCategoryDetail.value = true;
  categoryDetail.value = null;
  
  try {
    const response = await fetch(`/opex-outlet-dashboard/category-detail?category_id=${categoryId}&date_from=${filters.value.date_from}&date_to=${filters.value.date_to}&outlet_id=${filters.value.outlet_id || ''}`);
    const data = await response.json();
    categoryDetail.value = data;
  } catch (error) {
    console.error('Error fetching category detail:', error);
    alert('Terjadi kesalahan saat memuat data kategori');
  } finally {
    loadingCategoryDetail.value = false;
  }
}

function closeCategoryModal() {
  showCategoryModal.value = false;
  categoryDetail.value = null;
  expandedCategoryTransactions.value = [];
}

async function openFoodCategoryModal(category) {
  showFoodCategoryModal.value = true;
  loadingFoodCategoryItems.value = true;
  foodCategoryItems.value = [];
  selectedFoodCategory.value = category;
  
  try {
    const params = new URLSearchParams({
      category_name: category.category_name,
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
      outlet_id: filters.value.outlet_id || ''
    });
    
    if (category.sub_category_name) {
      params.append('sub_category_name', category.sub_category_name);
    }
    
    const response = await fetch(`/opex-outlet-dashboard/food-category-items?${params.toString()}`);
    const data = await response.json();
    foodCategoryItems.value = data;
  } catch (error) {
    console.error('Error fetching food category items:', error);
    alert('Terjadi kesalahan saat memuat data items');
  } finally {
    loadingFoodCategoryItems.value = false;
  }
}

function closeFoodCategoryModal() {
  showFoodCategoryModal.value = false;
  foodCategoryItems.value = [];
  selectedFoodCategory.value = null;
  expandedFoodItems.value = [];
}

function toggleFoodItem(index) {
  const itemIndex = expandedFoodItems.value.indexOf(index);
  if (itemIndex > -1) {
    expandedFoodItems.value.splice(itemIndex, 1);
  } else {
    expandedFoodItems.value.push(index);
  }
}

// Assign function reference after definition
openFoodCategoryModalFn = openFoodCategoryModal;


function applyFilters() {
  router.get('/opex-outlet-dashboard', filters.value, {
    preserveState: true,
    replace: true
  });
  // Hanya fetch jika outlet sudah dipilih (untuk admin) atau sudah ada outlet (untuk non-admin)
  if (!canSelectOutlet.value || filters.value.outlet_id) {
    fetchFoodByCategory();
  }
}

async function fetchFoodByCategory() {
  loadingFoodByCategory.value = true;
  try {
    const params = new URLSearchParams({
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
      outlet_id: filters.value.outlet_id || ''
    });
    
    const response = await fetch(`/opex-outlet-dashboard/food-by-category?${params.toString()}`);
    const data = await response.json();
    foodByCategoryData.value = data;
  } catch (error) {
    console.error('Error fetching food by category:', error);
  } finally {
    loadingFoodByCategory.value = false;
  }
}

function formatCurrency(value) {
  if (!value) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

function formatDateTime(date) {
  if (!date) return '-';
  return new Date(date).toLocaleString('id-ID', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  });
}

// Opex Trend Chart
const opexTrendSeries = computed(() => {
  if (!dashboardData.value?.opexTrend) return [];
  
  return [
    {
      name: 'Paid Amount',
      type: 'line',
      data: dashboardData.value.opexTrend.map(item => parseFloat(item.paid_amount || 0))
    },
    {
      name: 'Retail Non Food',
      type: 'line',
      data: dashboardData.value.opexTrend.map(item => parseFloat(item.retail_non_food_amount || 0))
    },
    {
      name: 'Food Expenses',
      type: 'line',
      data: dashboardData.value.opexTrend.map(item => parseFloat(item.food_amount || 0))
    }
  ];
});

const opexTrendOptions = computed(() => ({
  chart: {
    type: 'line',
    height: 350,
    toolbar: { show: true },
    animations: { enabled: true, easing: 'easeinout', speed: 800 }
  },
  xaxis: {
    categories: dashboardData.value?.opexTrend?.map(item => {
      return new Date(item.date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
    }) || [],
    labels: { rotate: -45, style: { fontSize: '12px' } }
  },
  yaxis: {
    title: { text: 'Amount (Rp)' },
    labels: {
      formatter: (value) => formatCurrency(value)
    }
  },
  colors: ['#3B82F6', '#10B981', '#EAB308'],
  stroke: { width: 3, curve: 'smooth' },
  legend: { position: 'top' },
  grid: { borderColor: '#e5e7eb' },
  tooltip: {
    shared: true,
    intersect: false,
    y: {
      formatter: (value) => formatCurrency(value)
    }
  }
}));

// Opex by Category Chart
const opexByCategorySeries = computed(() => {
  if (!dashboardData.value?.opexByCategory || !Array.isArray(dashboardData.value.opexByCategory)) return [];
  
  const seriesData = dashboardData.value.opexByCategory.map(cat => {
    const paid = parseFloat(cat.paid_amount || 0);
    const retail = parseFloat(cat.retail_non_food_amount || 0);
    return paid + retail;
  });
  
  return [{
    name: 'Opex Amount',
    data: seriesData
  }];
});

const opexByCategoryOptions = computed(() => {
  const categories = dashboardData.value?.opexByCategory || [];
  const labels = categories.map(cat => {
    const division = cat.division ? `[${cat.division}] ` : '';
    const name = cat.category_name || 'Unknown';
    // Truncate long names for better display
    const displayName = name.length > 20 ? name.substring(0, 17) + '...' : name;
    return `${division}${displayName}`;
  });
  
  return {
    chart: {
      type: 'bar',
      height: 400,
      toolbar: { show: true },
      events: {
        dataPointSelection: function(event, chartContext, config) {
          if (config.dataPointIndex !== undefined && categories.length > config.dataPointIndex) {
            const category = categories[config.dataPointIndex];
            // Allow clicking on uncategorized too, but skip modal for now
            if (category && category.category_id && category.category_id !== 'uncategorized') {
              openCategoryModal(category.category_id);
            }
          }
        },
        click: function(event, chartContext, config) {
          if (config.dataPointIndex !== undefined && categories.length > config.dataPointIndex) {
            const category = categories[config.dataPointIndex];
            // Allow clicking on uncategorized too, but skip modal for now
            if (category && category.category_id && category.category_id !== 'uncategorized') {
              openCategoryModal(category.category_id);
            }
          }
        }
      }
    },
    plotOptions: {
      bar: {
        horizontal: false,
        columnWidth: '60%',
        borderRadius: 8,
        borderRadiusApplication: 'end',
        dataLabels: {
          position: 'top'
        }
      }
    },
    dataLabels: {
      enabled: true,
      offsetY: -20,
      style: {
        fontSize: '12px',
        colors: ['#304758'],
        fontWeight: 600
      },
      formatter: function(val) {
        return formatCurrency(val);
      }
    },
    xaxis: {
      categories: labels.length > 0 ? labels : [],
      labels: {
        rotate: -45,
        rotateAlways: true,
        style: {
          fontSize: '11px'
        }
      }
    },
    yaxis: {
      title: {
        text: 'Amount (Rp)'
      },
      labels: {
        formatter: (value) => formatCurrency(value)
      }
    },
    colors: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#14B8A6'],
    tooltip: {
      y: {
        formatter: (value) => formatCurrency(value)
      }
    },
    grid: {
      borderColor: '#e5e7eb',
      strokeDashArray: 4
    },
    fill: {
      type: 'solid',
      opacity: 1
    },
    noData: {
      text: 'Tidak ada data',
      align: 'center',
      verticalAlign: 'middle'
    }
  };
});

// Category Trend Chart (for modal)
const categoryTrendSeries = computed(() => {
  if (!categoryDetail.value?.trend) return [];
  
  return [
    {
      name: 'Paid Amount',
      type: 'line',
      data: categoryDetail.value.trend.map(item => parseFloat(item.paid_amount || 0))
    },
    {
      name: 'Retail Non Food',
      type: 'line',
      data: categoryDetail.value.trend.map(item => parseFloat(item.retail_non_food_amount || 0))
    }
  ];
});

const categoryTrendOptions = computed(() => ({
  chart: {
    type: 'line',
    height: 300,
    toolbar: { show: true },
    animations: { enabled: true, easing: 'easeinout', speed: 800 }
  },
  xaxis: {
    categories: categoryDetail.value?.trend?.map(item => {
      return new Date(item.date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
    }) || [],
    labels: { rotate: -45, style: { fontSize: '12px' } }
  },
  yaxis: {
    title: { text: 'Amount (Rp)' },
    labels: {
      formatter: (value) => formatCurrency(value)
    }
  },
  colors: ['#3B82F6', '#10B981'],
  stroke: { width: 3, curve: 'smooth' },
  legend: { position: 'top' },
  grid: { borderColor: '#e5e7eb' },
  tooltip: {
    shared: true,
    intersect: false,
    y: {
      formatter: (value) => formatCurrency(value)
    }
  }
}));

// Card Trend Chart (for card modal)
const cardTrendSeries = computed(() => {
  if (!cardDetail.value?.trend) return [];
  
  return [
    {
      name: cardModalType.value === 'unpaid_pr' ? 'Unpaid Amount' : 'Amount',
      type: 'line',
      data: cardDetail.value.trend.map(item => parseFloat(item.amount || item.paid_amount || item.retail_non_food_amount || item.unpaid_amount || 0))
    }
  ];
});

const cardTrendOptions = computed(() => ({
  chart: {
    type: 'line',
    height: 300,
    toolbar: { show: true },
    animations: { enabled: true, easing: 'easeinout', speed: 800 }
  },
  xaxis: {
    categories: cardDetail.value?.trend?.map(item => {
      return new Date(item.date).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
    }) || [],
    labels: { rotate: -45, style: { fontSize: '12px' } }
  },
  yaxis: {
    title: { text: 'Amount (Rp)' },
    labels: {
      formatter: (value) => formatCurrency(value)
    }
  },
  colors: cardModalType.value === 'unpaid_pr' ? ['#F59E0B'] : cardModalType.value === 'retail_non_food' ? ['#10B981'] : ['#3B82F6'],
  stroke: { width: 3, curve: 'smooth' },
  legend: { position: 'top' },
  grid: { borderColor: '#e5e7eb' },
  tooltip: {
    shared: true,
    intersect: false,
    y: {
      formatter: (value) => formatCurrency(value)
    }
  }
}));

// Food by Category Chart
const foodByCategorySeries = computed(() => {
  if (!foodByCategoryData.value || foodByCategoryData.value.length === 0) return [{ name: 'Amount', data: [] }];
  
  const categories = foodByCategoryData.value || [];
  
  // Main series for actual amount
  const series = [{
    name: 'Food Amount',
    data: categories.map(cat => parseFloat(cat.total_amount || 0))
  }];
  
  // Add budget series if there are any locked budgets
  const hasBudget = categories.some(cat => cat.locked_budget !== null && cat.locked_budget > 0);
  if (hasBudget) {
    series.push({
      name: 'Budget',
      data: categories.map(cat => {
        if (cat.locked_budget !== null && cat.locked_budget > 0) {
          return parseFloat(cat.locked_budget);
        }
        return null; // null will not show a bar
      })
    });
  }
  
  return series;
});

const foodByCategoryOptions = computed(() => {
  const categories = foodByCategoryData.value || [];
  const labels = categories.map(cat => {
    if (cat.is_sub_category && cat.sub_category_name) {
      return `${cat.category_name} - ${cat.sub_category_name}`;
    }
    return cat.category_name || 'Uncategorized';
  });
  
  // Calculate dynamic height based on number of categories
  const baseHeight = 400;
  const itemHeight = 40;
  const calculatedHeight = Math.max(baseHeight, categories.length * itemHeight);
  
  return {
    chart: {
      type: 'bar',
      height: calculatedHeight,
      toolbar: { show: true },
      animations: { enabled: true, easing: 'easeinout', speed: 800 },
      events: {
        dataPointSelection: (event, chartContext, config) => {
          const currentCategories = foodByCategoryData.value || [];
          // Only handle clicks on the first series (actual amount), not budget series
          if (config.seriesIndex === 0 && config.dataPointIndex !== undefined && currentCategories.length > config.dataPointIndex) {
            const category = currentCategories[config.dataPointIndex];
            if (openFoodCategoryModalFn) {
              openFoodCategoryModalFn(category);
            }
          }
        },
        click: (event, chartContext, config) => {
          const currentCategories = foodByCategoryData.value || [];
          // Only handle clicks on the first series (actual amount), not budget series
          if (config.seriesIndex === 0 && config.dataPointIndex !== undefined && currentCategories.length > config.dataPointIndex) {
            const category = currentCategories[config.dataPointIndex];
            if (openFoodCategoryModalFn) {
              openFoodCategoryModalFn(category);
            }
          }
        }
      },
      stacked: false
    },
    dataLabels: {
      enabled: true,
      formatter: (val, opts) => {
        // Only show labels for the first series (actual amount)
        if (opts.seriesIndex !== 0) return '';
        
        const category = categories[opts.dataPointIndex];
        if (!category) return '';
        
        let label = formatCurrency(val);
        
        // Add budget indicator if over budget
        if (category.locked_budget !== null && category.is_over_budget) {
          label += ' ⚠️';
        }
        
        return label;
      },
      offsetX: 10,
      style: {
        fontSize: '11px',
        colors: [
          // Colors for actual amount series
          ...categories.map(cat => {
            // If over budget, use red color for label
            if (cat.locked_budget !== null && cat.is_over_budget) {
              return '#EF4444';
            }
            return '#304758';
          }),
          // Colors for budget series (hidden)
          ...categories.map(() => 'transparent')
        ],
        fontWeight: 600
      }
    },
    xaxis: {
      categories: labels.length > 0 ? labels : [],
      labels: {
        show: true,
        style: {
          fontSize: '11px'
        },
        maxWidth: 300
      }
    },
    yaxis: {
      title: {
        text: 'Amount (Rp)'
      },
      labels: {
        formatter: (value) => {
          if (typeof value === 'number' && !isNaN(value)) {
            return formatCurrency(value);
          }
          return value;
        },
        style: {
          fontSize: '11px'
        }
      }
    },
    colors: [
      // Colors array: [series0_bar0, series1_bar0, series0_bar1, series1_bar1, ...]
      // For each category, we have 2 bars: Food Amount (series 0) and Budget (series 1)
      ...categories.flatMap(cat => [
        // Food Amount color (series 0)
        cat.locked_budget !== null && cat.is_over_budget ? '#EF4444' : '#10B981',
        // Budget color (series 1) - always blue
        '#3B82F6'
      ])
    ],
    fill: {
      type: 'solid',
      opacity: [1, 0.6] // Full opacity for actual amount, 60% for budget marker (more visible)
    },
    plotOptions: {
      bar: {
        horizontal: true,
        barHeight: ['70%', '8%'], // 70% for actual amount, 8% for budget marker (thicker for visibility)
        borderRadius: 4,
        dataLabels: {
          position: 'right'
        }
      }
    },
    tooltip: {
      y: {
        formatter: (value, { seriesIndex, dataPointIndex }) => {
          const category = categories[dataPointIndex];
          let tooltip = formatCurrency(value);
          
          // Add budget information if available
          if (category && category.locked_budget !== null) {
            const budget = formatCurrency(category.locked_budget);
            const percentage = ((value / category.locked_budget) * 100).toFixed(1);
            const status = category.is_over_budget ? '⚠️ OVER BUDGET' : '✓ OK';
            tooltip += `<br/><br/><strong>Budget:</strong> ${budget}`;
            tooltip += `<br/><strong>Usage:</strong> ${percentage}%`;
            tooltip += `<br/><strong>Status:</strong> ${status}`;
          }
          
          return tooltip;
        }
      }
    },
    grid: {
      borderColor: '#e5e7eb',
      strokeDashArray: 4,
      xaxis: {
        lines: {
          show: true
        }
      },
      yaxis: {
        lines: {
          show: false
        }
      }
    },
    legend: {
      show: true,
      position: 'top',
      markers: {
        width: 12,
        height: 12,
        radius: 2
      }
    },
    noData: {
      text: 'Tidak ada data',
      align: 'center',
      verticalAlign: 'middle'
    }
  };
});

// Fetch food by category on mount and when filters change - hanya jika outlet sudah dipilih
onMounted(() => {
  // Hanya fetch jika outlet sudah dipilih (untuk admin) atau sudah ada outlet (untuk non-admin)
  if (!canSelectOutlet.value || filters.value.outlet_id) {
    fetchFoodByCategory();
  }
});

watch([() => filters.value.date_from, () => filters.value.date_to, () => filters.value.outlet_id], () => {
  // Hanya fetch jika outlet sudah dipilih (untuk admin) atau sudah ada outlet (untuk non-admin)
  if (!canSelectOutlet.value || filters.value.outlet_id) {
    fetchFoodByCategory();
  }
});

</script>

<style scoped>
/* Custom styles */
</style>

