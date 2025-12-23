<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-gray-50 via-blue-50 to-indigo-50 p-4 md:p-8">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
          <div>
            <h1 class="text-4xl font-extrabold text-gray-900 mb-2 flex items-center gap-3">
              <div class="p-3 bg-gradient-to-br from-blue-600 to-indigo-600 rounded-xl shadow-lg">
                <i class="fa-solid fa-chart-line text-white text-2xl"></i>
              </div>
              <span class="bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                Purchase Order Ops Report
              </span>
            </h1>
            <p class="text-gray-600 ml-16">Comprehensive analytics and insights for Purchase Orders</p>
          </div>
          <div class="flex gap-3">
            <button 
              @click="exportReport" 
              class="px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 flex items-center gap-2 font-semibold"
            >
              <i class="fa-solid fa-file-excel"></i>
              Export Excel
            </button>
            <button 
              @click="refreshData" 
              class="px-6 py-3 bg-gradient-to-r from-blue-500 to-indigo-600 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 flex items-center gap-2 font-semibold"
              :disabled="loading"
            >
              <i class="fa-solid fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
              Refresh
            </button>
          </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-calendar-alt mr-1 text-blue-500"></i>
                Date From
              </label>
              <input 
                type="date" 
                v-model="filters.date_from" 
                class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
              />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-calendar-check mr-1 text-blue-500"></i>
                Date To
              </label>
              <input 
                type="date" 
                v-model="filters.date_to" 
                class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
              />
            </div>
          </div>
          <div class="mt-4 flex justify-end">
            <button 
              @click="applyFilters" 
              class="px-8 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 font-semibold flex items-center gap-2"
            >
              <i class="fa-solid fa-filter"></i>
              Apply Filters
            </button>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center py-20">
        <div class="text-center">
          <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-600 mb-4"></div>
          <p class="text-gray-600 text-lg font-semibold">Loading report data...</p>
        </div>
      </div>

      <!-- Report Content -->
      <div v-else>
        <!-- Summary KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-xl p-6 text-white transform hover:scale-105 transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 bg-white bg-opacity-20 rounded-xl">
                <i class="fa-solid fa-file-invoice text-2xl"></i>
              </div>
              <div class="text-right">
                <div class="text-3xl font-bold">{{ formatNumber(summary.total_po) }}</div>
                <div class="text-blue-100 text-sm">Total PO</div>
              </div>
            </div>
            <div class="text-sm text-blue-100 mt-2">
              <i class="fa-solid fa-arrow-trend-up mr-1"></i>
              {{ summary.approved_count }} Approved
            </div>
          </div>

          <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl shadow-xl p-6 text-white transform hover:scale-105 transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 bg-white bg-opacity-20 rounded-xl">
                <i class="fa-solid fa-money-bill-wave text-2xl"></i>
              </div>
              <div class="text-right">
                <div class="text-2xl font-bold">{{ formatCurrency(summary.total_value) }}</div>
                <div class="text-green-100 text-sm">Total Value</div>
              </div>
            </div>
            <div class="text-sm text-green-100 mt-2">
              <i class="fa-solid fa-chart-line mr-1"></i>
              Avg: {{ formatCurrency(summary.avg_po_value) }}
            </div>
          </div>

          <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl shadow-xl p-6 text-white transform hover:scale-105 transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 bg-white bg-opacity-20 rounded-xl">
                <i class="fa-solid fa-percent text-2xl"></i>
              </div>
              <div class="text-right">
                <div class="text-2xl font-bold">{{ formatCurrency(summary.total_discount) }}</div>
                <div class="text-purple-100 text-sm">Total Discount</div>
              </div>
            </div>
            <div class="text-sm text-purple-100 mt-2">
              <i class="fa-solid fa-tag mr-1"></i>
              {{ ((summary.total_discount / summary.total_subtotal) * 100).toFixed(2) }}% of Subtotal
            </div>
          </div>

          <div class="bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl shadow-xl p-6 text-white transform hover:scale-105 transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 bg-white bg-opacity-20 rounded-xl">
                <i class="fa-solid fa-receipt text-2xl"></i>
              </div>
              <div class="text-right">
                <div class="text-2xl font-bold">{{ formatCurrency(summary.total_ppn) }}</div>
                <div class="text-orange-100 text-sm">Total PPN</div>
              </div>
            </div>
            <div class="text-sm text-orange-100 mt-2">
              <i class="fa-solid fa-calculator mr-1"></i>
              11% Tax
            </div>
          </div>
        </div>

        <!-- Status Distribution Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
          <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-gray-400 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-gray-500 text-sm font-medium mb-1">Draft</div>
                <div class="text-3xl font-bold text-gray-700">{{ summary.draft_count }}</div>
              </div>
              <div class="p-3 bg-gray-100 rounded-lg">
                <i class="fa-solid fa-file text-2xl text-gray-600"></i>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-green-500 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-gray-500 text-sm font-medium mb-1">Approved</div>
                <div class="text-3xl font-bold text-green-600">{{ summary.approved_count }}</div>
              </div>
              <div class="p-3 bg-green-100 rounded-lg">
                <i class="fa-solid fa-check-circle text-2xl text-green-600"></i>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-blue-500 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-gray-500 text-sm font-medium mb-1">Received</div>
                <div class="text-3xl font-bold text-blue-600">{{ summary.received_count }}</div>
              </div>
              <div class="p-3 bg-blue-100 rounded-lg">
                <i class="fa-solid fa-box-check text-2xl text-blue-600"></i>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-lg p-5 border-l-4 border-red-500 hover:shadow-xl transition-all">
            <div class="flex items-center justify-between">
              <div>
                <div class="text-gray-500 text-sm font-medium mb-1">Rejected</div>
                <div class="text-3xl font-bold text-red-600">{{ summary.rejected_count }}</div>
              </div>
              <div class="p-3 bg-red-100 rounded-lg">
                <i class="fa-solid fa-times-circle text-2xl text-red-600"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <!-- Status Distribution Chart -->
          <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
              <i class="fa-solid fa-chart-pie text-blue-600"></i>
              Status Distribution
            </h3>
            <div class="h-64">
              <apexchart
                type="donut"
                height="100%"
                :options="statusChartOptions"
                :series="statusChartSeries"
              ></apexchart>
            </div>
          </div>

          <!-- Trend Chart -->
          <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
              <i class="fa-solid fa-chart-line text-indigo-600"></i>
              Daily Trend
            </h3>
            <div class="h-64">
              <apexchart
                type="line"
                height="100%"
                :options="trendChartOptions"
                :series="trendChartSeries"
              ></apexchart>
            </div>
          </div>
        </div>

        <!-- PO Per Outlet Chart -->
        <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100 mb-8">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-store text-purple-600"></i>
              Purchase Orders per Outlet
            </h3>
            <div class="text-sm text-gray-500">
              <i class="fa-solid fa-info-circle mr-1"></i>
              Menampilkan Top 20 Outlet
            </div>
          </div>
          <div class="overflow-x-auto">
            <div style="height: 600px; min-width: 800px;">
              <apexchart
                type="bar"
                height="600"
                :options="poPerOutletChartOptions"
                :series="poPerOutletChartSeries"
                @dataPointSelection="onOutletBarClick"
              ></apexchart>
            </div>
          </div>
          <div v-if="props.poPerOutlet && props.poPerOutlet.length > 20" class="mt-4 text-sm text-gray-600 text-center">
            <i class="fa-solid fa-info-circle mr-1"></i>
            Menampilkan {{ Math.min(20, props.poPerOutlet.length) }} dari {{ props.poPerOutlet.length }} outlet. 
            Klik bar untuk melihat detail outlet.
          </div>
        </div>

        <!-- PO Per Category Chart -->
        <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100 mb-8">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-tags text-indigo-600"></i>
              Purchase Orders per Category
            </h3>
            <div class="text-sm text-gray-500">
              <i class="fa-solid fa-info-circle mr-1"></i>
              Menampilkan Top 20 Category
            </div>
          </div>
          <div class="overflow-x-auto">
            <div style="height: 600px; min-width: 800px;">
              <apexchart
                type="bar"
                height="600"
                :options="poPerCategoryChartOptions"
                :series="poPerCategoryChartSeries"
                @dataPointSelection="onCategoryBarClick"
              ></apexchart>
            </div>
          </div>
          <div v-if="props.poPerCategory && props.poPerCategory.length > 20" class="mt-4 text-sm text-gray-600 text-center">
            <i class="fa-solid fa-info-circle mr-1"></i>
            Menampilkan {{ Math.min(20, props.poPerCategory.length) }} dari {{ props.poPerCategory.length }} category. 
            Klik bar untuk melihat detail category.
          </div>
        </div>

        <!-- Payment Analysis -->
        <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100 mb-8">
          <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
            <i class="fa-solid fa-credit-card text-green-600"></i>
            Payment Analysis
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-5 border border-green-200">
              <div class="text-green-600 text-sm font-semibold mb-2">Total Value (Approved PO)</div>
              <div class="text-2xl font-bold text-green-700">{{ formatCurrency(paymentAnalysis.total_value) }}</div>
              <div class="text-xs text-gray-500 mt-1">{{ paymentAnalysis.total_po }} PO</div>
            </div>
            <div class="bg-gradient-to-br from-blue-50 to-cyan-50 rounded-xl p-5 border border-blue-200">
              <div class="text-blue-600 text-sm font-semibold mb-2">Total Paid</div>
              <div class="text-2xl font-bold text-blue-700">{{ formatCurrency(paymentAnalysis.total_paid) }}</div>
              <div class="text-xs text-blue-500 mt-1">{{ paymentAnalysis.payment_rate.toFixed(1) }}% Payment Rate</div>
            </div>
            <div class="bg-gradient-to-br from-yellow-50 to-amber-50 rounded-xl p-5 border border-yellow-200">
              <div class="text-yellow-600 text-sm font-semibold mb-2">Total Pending</div>
              <div class="text-2xl font-bold text-yellow-700">{{ formatCurrency(paymentAnalysis.total_pending) }}</div>
              <div class="text-xs text-gray-500 mt-1">In process</div>
            </div>
            <div class="bg-gradient-to-br from-red-50 to-rose-50 rounded-xl p-5 border border-red-200">
              <div class="text-red-600 text-sm font-semibold mb-2">Total Unpaid</div>
              <div class="text-2xl font-bold text-red-700">{{ formatCurrency(paymentAnalysis.total_unpaid) }}</div>
              <div class="text-xs text-gray-500 mt-1">
                {{ paymentAnalysis.total_value > 0 ? ((paymentAnalysis.total_unpaid / paymentAnalysis.total_value) * 100).toFixed(1) : 0 }}% of Total
              </div>
            </div>
          </div>
          
          <!-- Verification Info -->
          <div class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex items-center gap-2 text-sm text-blue-700">
              <i class="fa-solid fa-info-circle"></i>
              <span>
                <strong>Verification:</strong> 
                Total Value ({{ formatCurrency(paymentAnalysis.total_value) }}) = 
                Paid ({{ formatCurrency(paymentAnalysis.total_paid) }}) + 
                Pending ({{ formatCurrency(paymentAnalysis.total_pending) }}) + 
                Unpaid ({{ formatCurrency(paymentAnalysis.total_unpaid) }})
                = {{ formatCurrency(paymentAnalysis.total_paid + paymentAnalysis.total_pending + paymentAnalysis.total_unpaid) }}
              </span>
            </div>
          </div>
          <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="text-center p-4 bg-green-50 rounded-lg">
              <div class="text-3xl font-bold text-green-600">{{ paymentAnalysis.fully_paid_count }}</div>
              <div class="text-sm text-gray-600 mt-1">Fully Paid PO</div>
            </div>
            <div class="text-center p-4 bg-yellow-50 rounded-lg">
              <div class="text-3xl font-bold text-yellow-600">{{ paymentAnalysis.partially_paid_count }}</div>
              <div class="text-sm text-gray-600 mt-1">Partially Paid PO</div>
            </div>
            <div class="text-center p-4 bg-red-50 rounded-lg">
              <div class="text-3xl font-bold text-red-600">{{ paymentAnalysis.unpaid_count }}</div>
              <div class="text-sm text-gray-600 mt-1">Unpaid PO</div>
            </div>
          </div>
        </div>

        <!-- Supplier Analysis -->
        <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100 mb-8">
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-truck text-purple-600"></i>
              Top Suppliers by Value
            </h3>
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
              <div class="relative flex-1 md:flex-initial">
                <input 
                  type="text" 
                  v-model="filters.supplier_search" 
                  placeholder="Cari supplier..."
                  @keyup.enter="applyFilters"
                  class="w-full md:w-64 px-4 py-2 pl-10 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                />
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              </div>
              <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600 whitespace-nowrap">Per Page:</label>
                <select 
                  v-model="filters.supplier_per_page" 
                  @change="applyFilters"
                  class="px-3 py-2 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                >
                  <option :value="5">5</option>
                  <option :value="10">10</option>
                  <option :value="25">25</option>
                  <option :value="50">50</option>
                  <option :value="100">100</option>
                </select>
              </div>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr class="bg-gray-50 border-b-2 border-gray-200">
                  <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Rank</th>
                  <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Supplier</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">PO Count</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Total Value</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Paid</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Pending</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Unpaid</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Avg Value</th>
                  <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-if="supplierAnalysis.data && supplierAnalysis.data.length === 0" class="hover:bg-gray-50">
                  <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                    <i class="fa-solid fa-search-minus text-2xl mb-2"></i>
                    <p>Tidak ada supplier yang ditemukan</p>
                  </td>
                </tr>
                <tr v-for="(supplier, index) in (supplierAnalysis.data || supplierAnalysis)" :key="supplier.supplier_id" 
                    class="hover:bg-blue-50 transition-colors">
                  <td class="px-4 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-8 w-8 flex items-center justify-center rounded-full"
                           :class="getSupplierRankClass(supplierAnalysis.current_page ? (supplierAnalysis.current_page - 1) * supplierAnalysis.per_page + index : index)">
                        <span class="font-bold">{{ supplierAnalysis.current_page ? (supplierAnalysis.current_page - 1) * supplierAnalysis.per_page + index + 1 : index + 1 }}</span>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap">
                    <div class="font-semibold text-gray-900">{{ supplier.supplier_name }}</div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="font-semibold text-gray-700">{{ supplier.po_count }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="font-bold text-green-600">{{ formatCurrency(supplier.total_value) }}</span>
                    <div class="text-xs text-gray-500">(Approved PO)</div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="font-semibold text-green-600">{{ formatCurrency(supplier.total_paid || 0) }}</span>
                    <div class="text-xs text-gray-500" v-if="supplier.total_value > 0">
                      {{ ((supplier.total_paid || 0) / supplier.total_value * 100).toFixed(1) }}%
                    </div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="font-semibold text-yellow-600">{{ formatCurrency(supplier.total_pending || 0) }}</span>
                    <div class="text-xs text-gray-500" v-if="supplier.total_value > 0">
                      {{ ((supplier.total_pending || 0) / supplier.total_value * 100).toFixed(1) }}%
                    </div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="font-semibold text-red-600">{{ formatCurrency(supplier.total_unpaid || 0) }}</span>
                    <div class="text-xs text-gray-500" v-if="supplier.total_value > 0">
                      {{ ((supplier.total_unpaid || 0) / supplier.total_value * 100).toFixed(1) }}%
                    </div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="text-gray-600">{{ formatCurrency(supplier.avg_value) }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-center">
                    <button 
                      @click="showSupplierDetail(supplier.supplier_id)"
                      class="px-3 py-1.5 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm font-semibold flex items-center gap-1"
                    >
                      <i class="fa-solid fa-eye"></i>
                      Detail
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- Supplier Pagination -->
          <div v-if="supplierAnalysis.current_page" class="mt-6 flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Showing {{ supplierAnalysis.from }} to {{ supplierAnalysis.to }} of {{ supplierAnalysis.total }} results
            </div>
            <div class="flex gap-2">
              <button 
                v-if="supplierAnalysis.prev_page_url"
                @click="loadSupplierPage(supplierAnalysis.current_page - 1)"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition"
              >
                <i class="fa-solid fa-chevron-left"></i> Previous
              </button>
              <button 
                v-if="supplierAnalysis.next_page_url"
                @click="loadSupplierPage(supplierAnalysis.current_page + 1)"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition"
              >
                Next <i class="fa-solid fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Item Analysis -->
        <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100 mb-8">
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-boxes-stacked text-orange-600"></i>
              Item Analysis
            </h3>
            <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
              <div class="relative flex-1 md:flex-initial">
                <input 
                  type="text" 
                  v-model="filters.item_search" 
                  placeholder="Cari item..."
                  @keyup.enter="applyFilters"
                  class="w-full md:w-64 px-4 py-2 pl-10 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all"
                />
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              </div>
              <div class="flex items-center gap-2">
                <label class="text-sm text-gray-600 whitespace-nowrap">Per Page:</label>
                <select 
                  v-model="filters.item_per_page" 
                  @change="applyFilters"
                  class="px-3 py-2 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-all"
                >
                  <option :value="10">10</option>
                  <option :value="15">15</option>
                  <option :value="25">25</option>
                  <option :value="50">50</option>
                  <option :value="100">100</option>
                </select>
              </div>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full">
              <thead>
                <tr class="bg-gray-50 border-b-2 border-gray-200">
                  <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Item Name</th>
                  <th class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider">Unit</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Total Quantity</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Avg Price</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Min Price</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Max Price</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Total Discount</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Total Value</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">PO Count</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-gray-700 uppercase tracking-wider">Item Count</th>
                  <th class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-if="itemAnalysisList.length === 0" class="hover:bg-gray-50">
                  <td colspan="11" class="px-4 py-8 text-center text-gray-500">
                    <i class="fa-solid fa-search-minus text-2xl mb-2"></i>
                    <p>Tidak ada item yang ditemukan</p>
                  </td>
                </tr>
                <tr v-for="item in itemAnalysisList" :key="item.item_name + item.unit" 
                    class="hover:bg-orange-50 transition-colors">
                  <td class="px-4 py-4 whitespace-nowrap">
                    <div class="font-semibold text-gray-900">{{ item.item_name }}</div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap">
                    <span class="text-gray-600">{{ item.unit }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="font-semibold text-gray-700">{{ formatNumber(item.total_quantity) }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="text-gray-700">{{ formatCurrency(item.avg_price) }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="text-blue-600">{{ formatCurrency(item.min_price) }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="text-purple-600">{{ formatCurrency(item.max_price) }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="text-red-600">{{ formatCurrency(item.total_discount) }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="font-bold text-green-600">{{ formatCurrency(item.total_value) }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="text-gray-600">{{ item.po_count }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <span class="text-gray-600">{{ item.item_count }}</span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-center">
                    <button 
                      @click="showItemDetail(item.item_name, item.unit)"
                      class="px-3 py-1.5 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition text-sm font-semibold flex items-center gap-1"
                    >
                      <i class="fa-solid fa-eye"></i>
                      Detail
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <!-- Item Pagination -->
          <div v-if="props.itemAnalysis && props.itemAnalysis.current_page" class="mt-6 flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Showing {{ props.itemAnalysis.from }} to {{ props.itemAnalysis.to }} of {{ props.itemAnalysis.total }} results
            </div>
            <div class="flex gap-2">
              <button 
                v-if="props.itemAnalysis.prev_page_url"
                @click="loadItemPage(props.itemAnalysis.current_page - 1)"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition"
              >
                <i class="fa-solid fa-chevron-left"></i> Previous
              </button>
              <button 
                v-if="props.itemAnalysis.next_page_url"
                @click="loadItemPage(props.itemAnalysis.current_page + 1)"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition"
              >
                Next <i class="fa-solid fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Detailed PO Table -->
        <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
            <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
              <i class="fa-solid fa-table text-indigo-600"></i>
              Purchase Orders Detail
            </h3>
            <div class="flex flex-col md:flex-row items-start md:items-center gap-3 w-full md:w-auto">
              <!-- Search Input -->
              <div class="w-full md:w-auto">
                <input 
                  type="text" 
                  v-model="filters.search" 
                  @keyup.enter="applyFilters"
                  placeholder="Search PO Number, Supplier, PR... (Press Enter)"
                  class="w-full md:w-64 px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                />
              </div>
              <div class="flex items-center gap-3">
                <div class="text-sm text-gray-500">
                  Showing {{ purchaseOrders.from }} - {{ purchaseOrders.to }} of {{ purchaseOrders.total }}
                </div>
                <div class="flex items-center gap-2">
                  <label class="text-sm text-gray-600 whitespace-nowrap">Per Page:</label>
                  <select 
                    v-model="filters.per_page" 
                    @change="applyFilters"
                    class="px-3 py-2 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                  >
                    <option :value="10">10</option>
                    <option :value="15">15</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
          
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gradient-to-r from-blue-600 to-indigo-600">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider cursor-pointer hover:bg-blue-700 transition-colors" @click="sortBy('number')">
                    PO Number
                    <i class="fa-solid fa-sort ml-1"></i>
                  </th>
                  <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Date</th>
                  <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Supplier</th>
                  <th class="px-4 py-3 text-left text-xs font-bold text-white uppercase tracking-wider">Status</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">Subtotal</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">Discount</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">PPN</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">Grand Total</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">Items</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">Paid</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">Pending</th>
                  <th class="px-4 py-3 text-right text-xs font-bold text-white uppercase tracking-wider">Remaining</th>
                  <th class="px-4 py-3 text-center text-xs font-bold text-white uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="po in purchaseOrders.data" :key="po.id" 
                    class="hover:bg-blue-50 transition-colors">
                  <td class="px-4 py-4 whitespace-nowrap">
                    <div class="font-semibold text-blue-600">{{ po.number }}</div>
                    <div class="text-xs text-gray-500" v-if="po.source_pr_number">
                      PR: {{ po.source_pr_number }}
                    </div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-700">
                    {{ formatDate(po.date) }}
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap">
                    <div class="font-medium text-gray-900">{{ po.supplier_name }}</div>
                    <div class="text-xs text-gray-500" v-if="po.outlet_name">
                      Outlet: {{ po.outlet_name }}
                    </div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap">
                    <span :class="getStatusClass(po.status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                      {{ po.status.toUpperCase() }}
                    </span>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-700">
                    {{ formatCurrency(po.subtotal) }}
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right text-sm text-red-600">
                    {{ formatCurrency(po.discount_total_amount) }}
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right text-sm text-blue-600">
                    {{ formatCurrency(po.ppn_amount) }}
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <div class="font-bold text-green-600">{{ formatCurrency(po.grand_total) }}</div>
                    <div class="text-xs text-gray-500" v-if="po.payment_type">
                      {{ po.payment_type === 'lunas' ? 'Lunas' : 'Termin' }}
                    </div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right text-sm text-gray-600">
                    {{ po.item_count || 0 }} items
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <div class="font-semibold text-green-600">{{ formatCurrency(po.total_paid || 0) }}</div>
                    <div class="text-xs text-gray-500" v-if="po.payment_count">
                      {{ po.payment_count }} payments
                    </div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <div class="font-semibold text-yellow-600">{{ formatCurrency(po.total_pending || 0) }}</div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-right">
                    <div class="font-bold" :class="(po.remaining_amount || 0) > 0 ? 'text-red-600' : 'text-green-600'">
                      {{ formatCurrency(po.remaining_amount || 0) }}
                    </div>
                  </td>
                  <td class="px-4 py-4 whitespace-nowrap text-center">
                    <a :href="route('po-ops.show', po.id)" 
                       class="text-blue-600 hover:text-blue-800 font-semibold text-sm">
                      <i class="fa-solid fa-eye"></i>
                    </a>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="mt-6 flex items-center justify-between">
            <div class="text-sm text-gray-700">
              Showing {{ purchaseOrders.from }} to {{ purchaseOrders.to }} of {{ purchaseOrders.total }} results
            </div>
            <div class="flex gap-2">
              <button 
                v-if="purchaseOrders.prev_page_url"
                @click="loadPage(purchaseOrders.current_page - 1)"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition"
              >
                <i class="fa-solid fa-chevron-left"></i> Previous
              </button>
              <button 
                v-if="purchaseOrders.next_page_url"
                @click="loadPage(purchaseOrders.current_page + 1)"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition"
              >
                Next <i class="fa-solid fa-chevron-right"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Supplier Detail Modal -->
    <div v-if="showSupplierDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showSupplierDetailModal = false">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white p-6 flex justify-between items-center">
          <div>
            <h3 class="text-2xl font-bold mb-1">Detail Supplier</h3>
            <p v-if="supplierDetail" class="text-purple-100">{{ supplierDetail.supplier?.name }}</p>
          </div>
          <button 
            @click="showSupplierDetailModal = false" 
            class="text-white hover:text-gray-200 transition text-2xl"
          >
            <i class="fa-solid fa-times"></i>
          </button>
        </div>

        <!-- Modal Content -->
        <div class="flex-1 overflow-y-auto p-6">
          <div v-if="loadingSupplierDetail" class="text-center py-20">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-purple-600 mb-4"></div>
            <p class="text-gray-600 text-lg font-semibold">Loading supplier detail...</p>
          </div>

          <div v-else-if="supplierDetail">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                <div class="text-blue-600 text-sm font-semibold mb-1">Total PO</div>
                <div class="text-2xl font-bold text-blue-700">{{ supplierDetail.summary.total_po }}</div>
              </div>
              <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                <div class="text-green-600 text-sm font-semibold mb-1">Total Value</div>
                <div class="text-2xl font-bold text-green-700">{{ formatCurrency(supplierDetail.summary.total_value) }}</div>
              </div>
              <div class="bg-emerald-50 rounded-xl p-4 border border-emerald-200">
                <div class="text-emerald-600 text-sm font-semibold mb-1">Total Paid</div>
                <div class="text-2xl font-bold text-emerald-700">{{ formatCurrency(supplierDetail.summary.total_paid) }}</div>
              </div>
              <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                <div class="text-red-600 text-sm font-semibold mb-1">Total Unpaid</div>
                <div class="text-2xl font-bold text-red-700">{{ formatCurrency(supplierDetail.summary.total_unpaid) }}</div>
              </div>
            </div>

            <!-- Purchase Orders List -->
            <div class="space-y-4">
              <h4 class="text-lg font-bold text-gray-800 mb-4">Purchase Orders</h4>
              
              <div v-for="po in supplierDetail.purchase_orders" :key="po.id" 
                   class="bg-white border-2 border-gray-200 rounded-xl p-5 hover:border-purple-300 transition-all">
                <!-- PO Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4 pb-4 border-b border-gray-200">
                  <div>
                    <div class="flex items-center gap-3 mb-2">
                      <span class="font-bold text-lg text-gray-900">{{ po.number }}</span>
                      <span :class="getStatusClass(po.status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                        {{ po.status.toUpperCase() }}
                      </span>
                    </div>
                    <div class="text-sm text-gray-600 space-y-1">
                      <div><i class="fa-solid fa-calendar mr-2"></i>{{ formatDate(po.date) }}</div>
                      <div v-if="po.source_pr_number">
                        <i class="fa-solid fa-file-invoice mr-2"></i>PR: {{ po.source_pr_number }}
                      </div>
                      <div v-if="po.outlet_name">
                        <i class="fa-solid fa-store mr-2"></i>{{ po.outlet_name }}
                      </div>
                      <div v-if="po.creator_name">
                        <i class="fa-solid fa-user mr-2"></i>{{ po.creator_name }}
                      </div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-2xl font-bold text-green-600 mb-1">{{ formatCurrency(po.grand_total) }}</div>
                    <div class="text-xs text-gray-500 space-y-1">
                      <div>Paid: <span class="text-green-600 font-semibold">{{ formatCurrency(po.total_paid) }}</span></div>
                      <div>Pending: <span class="text-yellow-600 font-semibold">{{ formatCurrency(po.total_pending) }}</span></div>
                      <div>Unpaid: <span class="text-red-600 font-semibold">{{ formatCurrency(po.total_unpaid) }}</span></div>
                    </div>
                  </div>
                </div>

                <!-- PO Items -->
                <div class="mt-4">
                  <h5 class="font-semibold text-gray-700 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-list text-purple-600"></i>
                    Items ({{ po.items.length }})
                  </h5>
                  <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                      <thead class="bg-gray-50">
                        <tr>
                          <th class="px-3 py-2 text-left text-xs font-bold text-gray-700">Item Name</th>
                          <th class="px-3 py-2 text-right text-xs font-bold text-gray-700">Qty</th>
                          <th class="px-3 py-2 text-right text-xs font-bold text-gray-700">Unit</th>
                          <th class="px-3 py-2 text-right text-xs font-bold text-gray-700">Price</th>
                          <th class="px-3 py-2 text-right text-xs font-bold text-gray-700">Discount</th>
                          <th class="px-3 py-2 text-right text-xs font-bold text-gray-700">Total</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-200">
                        <tr v-for="(item, idx) in po.items" :key="idx" class="hover:bg-gray-50">
                          <td class="px-3 py-2 font-medium text-gray-900">{{ item.item_name }}</td>
                          <td class="px-3 py-2 text-right text-gray-700">{{ formatNumber(item.quantity) }}</td>
                          <td class="px-3 py-2 text-right text-gray-600">{{ item.unit }}</td>
                          <td class="px-3 py-2 text-right text-gray-700">{{ formatCurrency(item.price) }}</td>
                          <td class="px-3 py-2 text-right text-red-600">{{ formatCurrency(item.discount_amount) }}</td>
                          <td class="px-3 py-2 text-right font-semibold text-green-600">{{ formatCurrency(item.total) }}</td>
                        </tr>
                      </tbody>
                      <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                          <td colspan="4" class="px-3 py-2 text-right">Subtotal:</td>
                          <td class="px-3 py-2 text-right text-red-600">-{{ formatCurrency(po.discount_total_amount) }}</td>
                          <td class="px-3 py-2 text-right">{{ formatCurrency(po.subtotal) }}</td>
                        </tr>
                        <tr>
                          <td colspan="5" class="px-3 py-2 text-right">PPN:</td>
                          <td class="px-3 py-2 text-right text-blue-600">{{ formatCurrency(po.ppn_amount) }}</td>
                        </tr>
                        <tr class="bg-green-50">
                          <td colspan="5" class="px-3 py-2 text-right font-bold">Grand Total:</td>
                          <td class="px-3 py-2 text-right font-bold text-green-700 text-lg">{{ formatCurrency(po.grand_total) }}</td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </div>
              </div>

              <div v-if="supplierDetail.purchase_orders.length === 0" class="text-center py-12 text-gray-500">
                <i class="fa-solid fa-inbox text-4xl mb-3"></i>
                <p>Tidak ada Purchase Order untuk supplier ini</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-gray-50 px-6 py-4 flex justify-end border-t border-gray-200">
          <button 
            @click="showSupplierDetailModal = false" 
            class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Item Detail Modal -->
    <div v-if="showItemDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showItemDetailModal = false">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-orange-600 to-red-600 text-white p-6 flex justify-between items-center">
          <div>
            <h3 class="text-2xl font-bold mb-1">Item Detail</h3>
            <p v-if="itemDetail" class="text-orange-100">
              {{ itemDetail.item_name }} <span v-if="itemDetail.unit">({{ itemDetail.unit }})</span>
            </p>
          </div>
          <button 
            @click="showItemDetailModal = false" 
            class="text-white hover:text-gray-200 transition text-2xl"
          >
            <i class="fa-solid fa-times"></i>
          </button>
        </div>

        <!-- Modal Content -->
        <div class="flex-1 overflow-y-auto p-6">
          <div v-if="loadingItemDetail" class="text-center py-20">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-orange-600 mb-4"></div>
            <p class="text-gray-600 text-lg font-semibold">Loading item detail...</p>
          </div>

          <div v-else-if="itemDetail">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                <div class="text-blue-600 text-sm font-semibold mb-1">Total Quantity</div>
                <div class="text-2xl font-bold text-blue-700">{{ formatNumber(itemDetail.summary.total_quantity) }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ itemDetail.unit }}</div>
              </div>
              <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                <div class="text-green-600 text-sm font-semibold mb-1">Total Value</div>
                <div class="text-2xl font-bold text-green-700">{{ formatCurrency(itemDetail.summary.total_value) }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ itemDetail.summary.item_count }} transactions</div>
              </div>
              <div class="bg-purple-50 rounded-xl p-4 border border-purple-200">
                <div class="text-purple-600 text-sm font-semibold mb-1">PO Count</div>
                <div class="text-2xl font-bold text-purple-700">{{ itemDetail.summary.po_count }}</div>
                <div class="text-xs text-gray-500 mt-1">{{ itemDetail.summary.supplier_count }} suppliers</div>
              </div>
              <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                <div class="text-red-600 text-sm font-semibold mb-1">Total Discount</div>
                <div class="text-2xl font-bold text-red-700">{{ formatCurrency(itemDetail.summary.total_discount) }}</div>
                <div class="text-xs text-gray-500 mt-1">Avg: {{ formatCurrency(itemDetail.summary.avg_price) }}</div>
              </div>
            </div>

            <!-- Price Range -->
            <div class="bg-gray-50 rounded-xl p-4 mb-6 border border-gray-200">
              <h4 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-chart-line text-orange-600"></i>
                Price Range
              </h4>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center">
                  <div class="text-sm text-gray-600 mb-1">Min Price</div>
                  <div class="text-xl font-bold text-blue-600">{{ formatCurrency(itemDetail.summary.min_price) }}</div>
                </div>
                <div class="text-center">
                  <div class="text-sm text-gray-600 mb-1">Average Price</div>
                  <div class="text-xl font-bold text-green-600">{{ formatCurrency(itemDetail.summary.avg_price) }}</div>
                </div>
                <div class="text-center">
                  <div class="text-sm text-gray-600 mb-1">Max Price</div>
                  <div class="text-xl font-bold text-purple-600">{{ formatCurrency(itemDetail.summary.max_price) }}</div>
                </div>
              </div>
            </div>

            <!-- Suppliers List -->
            <div v-if="itemDetail.suppliers && itemDetail.suppliers.length > 0" class="bg-blue-50 rounded-xl p-4 mb-6 border border-blue-200">
              <h4 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fa-solid fa-truck text-blue-600"></i>
                Suppliers ({{ itemDetail.suppliers.length }})
              </h4>
              <div class="flex flex-wrap gap-2">
                <span v-for="supplier in itemDetail.suppliers" :key="supplier" 
                      class="px-3 py-1 bg-white rounded-lg text-sm font-medium text-gray-700 border border-blue-200">
                  {{ supplier }}
                </span>
              </div>
            </div>

            <!-- Purchase Orders List -->
            <div class="space-y-4">
              <h4 class="text-lg font-bold text-gray-800 mb-4">Purchase Orders Detail</h4>
              
              <div v-for="detail in itemDetail.details" :key="detail.id" 
                   class="bg-white border-2 border-gray-200 rounded-xl p-5 hover:border-orange-300 transition-all">
                <!-- PO Header -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4 pb-4 border-b border-gray-200">
                  <div>
                    <div class="flex items-center gap-3 mb-2">
                      <span class="font-bold text-lg text-gray-900">{{ detail.po_number }}</span>
                      <span :class="getStatusClass(detail.po_status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                        {{ detail.po_status.toUpperCase() }}
                      </span>
                    </div>
                    <div class="text-sm text-gray-600 space-y-1">
                      <div><i class="fa-solid fa-calendar mr-2"></i>{{ formatDate(detail.po_date) }}</div>
                      <div v-if="detail.supplier_name">
                        <i class="fa-solid fa-truck mr-2"></i>{{ detail.supplier_name }}
                      </div>
                      <div v-if="detail.source_pr_number">
                        <i class="fa-solid fa-file-invoice mr-2"></i>PR: {{ detail.source_pr_number }}
                      </div>
                      <div v-if="detail.outlet_name">
                        <i class="fa-solid fa-store mr-2"></i>{{ detail.outlet_name }}
                      </div>
                      <div v-if="detail.creator_name">
                        <i class="fa-solid fa-user mr-2"></i>{{ detail.creator_name }}
                      </div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-sm text-gray-500 mb-1">PO Grand Total</div>
                    <div class="text-xl font-bold text-green-600">{{ formatCurrency(detail.po_grand_total) }}</div>
                  </div>
                </div>

                <!-- Item Detail -->
                <div class="bg-orange-50 rounded-lg p-4 border border-orange-200">
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                      <div class="text-gray-600 mb-1">Quantity</div>
                      <div class="font-bold text-gray-900">{{ formatNumber(detail.quantity) }} {{ detail.unit }}</div>
                    </div>
                    <div>
                      <div class="text-gray-600 mb-1">Price</div>
                      <div class="font-bold text-gray-900">{{ formatCurrency(detail.price) }}</div>
                    </div>
                    <div>
                      <div class="text-gray-600 mb-1">Discount</div>
                      <div class="font-bold text-red-600">
                        {{ detail.discount_percent > 0 ? detail.discount_percent + '%' : '-' }}
                        <span v-if="detail.discount_amount > 0"> ({{ formatCurrency(detail.discount_amount) }})</span>
                      </div>
                    </div>
                    <div>
                      <div class="text-gray-600 mb-1">Total</div>
                      <div class="font-bold text-green-600 text-lg">{{ formatCurrency(detail.total) }}</div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="itemDetail.details.length === 0" class="text-center py-12 text-gray-500">
                <i class="fa-solid fa-inbox text-4xl mb-3"></i>
                <p>Tidak ada data untuk item ini</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-gray-50 px-6 py-4 flex justify-end border-t border-gray-200">
          <button 
            @click="showItemDetailModal = false" 
            class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Outlet Detail Modal -->
    <div v-if="showOutletDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showOutletDetailModal = false">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white p-6 flex justify-between items-center">
          <div>
            <h3 class="text-2xl font-bold mb-1">Detail Outlet</h3>
            <p v-if="outletDetail" class="text-purple-100">
              <i class="fa-solid fa-store mr-2"></i>{{ outletDetail.outlet?.name }}
            </p>
          </div>
          <button 
            @click="showOutletDetailModal = false" 
            class="text-white hover:text-gray-200 transition text-2xl"
          >
            <i class="fa-solid fa-times"></i>
          </button>
        </div>

        <!-- Modal Content -->
        <div class="flex-1 overflow-y-auto p-6">
          <div v-if="loadingOutletDetail" class="text-center py-20">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-purple-600 mb-4"></div>
            <p class="text-gray-600 text-lg font-semibold">Loading outlet detail...</p>
          </div>

          <div v-else-if="outletDetail">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-purple-50 rounded-xl p-4 border border-purple-200">
                <div class="text-purple-600 text-sm font-semibold mb-1">Total PO</div>
                <div class="text-2xl font-bold text-purple-700">{{ outletDetail.summary.total_po }}</div>
              </div>
              <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                <div class="text-green-600 text-sm font-semibold mb-1">Total Value</div>
                <div class="text-2xl font-bold text-green-700">{{ formatCurrency(outletDetail.summary.total_value) }}</div>
              </div>
              <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                <div class="text-blue-600 text-sm font-semibold mb-1">Total Subtotal</div>
                <div class="text-2xl font-bold text-blue-700">{{ formatCurrency(outletDetail.summary.total_subtotal) }}</div>
              </div>
              <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                <div class="text-red-600 text-sm font-semibold mb-1">Total Discount</div>
                <div class="text-2xl font-bold text-red-700">{{ formatCurrency(outletDetail.summary.total_discount) }}</div>
              </div>
            </div>

            <!-- Payment Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                <div class="text-green-600 text-sm font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-check-circle"></i>
                  Paid
                </div>
                <div class="text-2xl font-bold text-green-700">{{ formatCurrency(outletDetail.summary.total_paid || 0) }}</div>
                <div class="text-xs text-gray-500 mt-1" v-if="outletDetail.summary.total_value > 0">
                  {{ ((outletDetail.summary.total_paid || 0) / outletDetail.summary.total_value * 100).toFixed(1) }}% dari Total Value
                </div>
              </div>
              <div class="bg-orange-50 rounded-xl p-4 border border-orange-200">
                <div class="text-orange-600 text-sm font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-clock"></i>
                  Pending
                </div>
                <div class="text-2xl font-bold text-orange-700">{{ formatCurrency(outletDetail.summary.total_pending || 0) }}</div>
                <div class="text-xs text-gray-500 mt-1" v-if="outletDetail.summary.total_value > 0">
                  {{ ((outletDetail.summary.total_pending || 0) / outletDetail.summary.total_value * 100).toFixed(1) }}% dari Total Value
                </div>
              </div>
              <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                <div class="text-red-600 text-sm font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-exclamation-circle"></i>
                  Unpaid
                </div>
                <div class="text-2xl font-bold text-red-700">{{ formatCurrency(outletDetail.summary.total_unpaid || 0) }}</div>
                <div class="text-xs text-gray-500 mt-1" v-if="outletDetail.summary.total_value > 0">
                  {{ ((outletDetail.summary.total_unpaid || 0) / outletDetail.summary.total_value * 100).toFixed(1) }}% dari Total Value
                </div>
              </div>
            </div>

            <!-- Purchase Orders List -->
            <div class="space-y-4">
              <h4 class="text-lg font-bold text-gray-800 mb-4">Purchase Orders ({{ outletDetail.purchase_orders?.length || 0 }})</h4>
              
              <div v-if="outletDetail.purchase_orders && outletDetail.purchase_orders.length === 0" class="text-center py-12 text-gray-500">
                <i class="fa-solid fa-inbox text-4xl mb-3"></i>
                <p>Tidak ada Purchase Order untuk outlet ini</p>
              </div>

              <div v-for="po in outletDetail.purchase_orders" :key="po.id" 
                   class="bg-white border-2 border-gray-200 rounded-xl overflow-hidden hover:border-purple-300 transition-all">
                <!-- PO Header (Always Visible) -->
                <div class="p-5 cursor-pointer" @click="togglePOExpand(po.id)">
                  <div class="flex items-center justify-between">
                    <div class="flex-1">
                      <div class="flex items-center gap-3 mb-2">
                        <span class="font-bold text-lg text-gray-900">{{ po.number }}</span>
                        <span :class="getStatusClass(po.status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                          {{ po.status.toUpperCase() }}
                        </span>
                        <i class="fa-solid fa-chevron-down transition-transform" 
                           :class="expandedPOs.has(po.id) ? 'rotate-180' : ''"></i>
                      </div>
                      <div class="text-sm text-gray-600 space-y-1">
                        <div><i class="fa-solid fa-calendar mr-2"></i>{{ formatDate(po.date) }}</div>
                        <div v-if="po.supplier_name">
                          <i class="fa-solid fa-truck mr-2"></i>{{ po.supplier_name }}
                        </div>
                        <div v-if="po.source_pr_number">
                          <i class="fa-solid fa-file-invoice mr-2"></i>PR: {{ po.source_pr_number }}
                        </div>
                        <div v-if="po.creator_name">
                          <i class="fa-solid fa-user mr-2"></i>{{ po.creator_name }}
                        </div>
                      </div>
                    </div>
                    <div class="text-right ml-4">
                      <div class="text-sm text-gray-500 mb-1">Grand Total</div>
                      <div class="text-xl font-bold text-green-600">{{ formatCurrency(po.grand_total) }}</div>
                      <div class="text-xs text-gray-500 mt-1">
                        Subtotal: {{ formatCurrency(po.subtotal) }}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- PO Items (Expandable) -->
                <div v-if="expandedPOs.has(po.id)" class="border-t border-gray-200 bg-gray-50 p-5">
                  <h5 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-boxes text-purple-600"></i>
                    Items ({{ po.items?.length || 0 }})
                  </h5>
                  
                  <div v-if="po.items && po.items.length > 0" class="space-y-3">
                    <div v-for="(item, idx) in po.items" :key="idx" 
                         class="bg-white rounded-lg p-4 border border-gray-200">
                      <div class="grid grid-cols-1 md:grid-cols-5 gap-4 text-sm">
                        <div class="md:col-span-2">
                          <div class="text-gray-600 mb-1">Item Name</div>
                          <div class="font-semibold text-gray-900">{{ item.item_name }}</div>
                        </div>
                        <div>
                          <div class="text-gray-600 mb-1">Quantity</div>
                          <div class="font-semibold text-gray-900">{{ formatNumber(item.quantity) }} {{ item.unit }}</div>
                        </div>
                        <div>
                          <div class="text-gray-600 mb-1">Price</div>
                          <div class="font-semibold text-gray-900">{{ formatCurrency(item.price) }}</div>
                        </div>
                        <div>
                          <div class="text-gray-600 mb-1">Total</div>
                          <div class="font-bold text-green-600">{{ formatCurrency(item.total) }}</div>
                          <div v-if="item.discount_percent > 0 || item.discount_amount > 0" class="text-xs text-red-600 mt-1">
                            Disc: {{ item.discount_percent > 0 ? item.discount_percent + '%' : '' }}
                            <span v-if="item.discount_amount > 0">({{ formatCurrency(item.discount_amount) }})</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div v-else class="text-center py-4 text-gray-500">
                    <i class="fa-solid fa-box-open text-2xl mb-2"></i>
                    <p>Tidak ada items</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-gray-50 px-6 py-4 flex justify-end border-t border-gray-200">
          <button 
            @click="showOutletDetailModal = false" 
            class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Category Detail Modal -->
    <div v-if="showCategoryDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showCategoryDetailModal = false">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-hidden flex flex-col">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-6 flex justify-between items-center">
          <div>
            <h3 class="text-2xl font-bold mb-1">Detail Category</h3>
            <p v-if="categoryDetail" class="text-indigo-100">
              <i class="fa-solid fa-tags mr-2"></i>{{ categoryDetail.category?.name }}
            </p>
          </div>
          <button 
            @click="showCategoryDetailModal = false" 
            class="text-white hover:text-gray-200 transition text-2xl"
          >
            <i class="fa-solid fa-times"></i>
          </button>
        </div>

        <!-- Modal Content -->
        <div class="flex-1 overflow-y-auto p-6">
          <div v-if="loadingCategoryDetail" class="text-center py-20">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-indigo-600 mb-4"></div>
            <p class="text-gray-600 text-lg font-semibold">Loading category detail...</p>
          </div>

          <div v-else-if="categoryDetail">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
              <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-200">
                <div class="text-indigo-600 text-sm font-semibold mb-1">Total PO</div>
                <div class="text-2xl font-bold text-indigo-700">{{ categoryDetail.summary.total_po }}</div>
              </div>
              <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                <div class="text-green-600 text-sm font-semibold mb-1">Total Value</div>
                <div class="text-2xl font-bold text-green-700">{{ formatCurrency(categoryDetail.summary.total_value) }}</div>
              </div>
              <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
                <div class="text-blue-600 text-sm font-semibold mb-1">Total Subtotal</div>
                <div class="text-2xl font-bold text-blue-700">{{ formatCurrency(categoryDetail.summary.total_subtotal) }}</div>
              </div>
              <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                <div class="text-red-600 text-sm font-semibold mb-1">Total Discount</div>
                <div class="text-2xl font-bold text-red-700">{{ formatCurrency(categoryDetail.summary.total_discount) }}</div>
              </div>
            </div>

            <!-- Payment Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div class="bg-green-50 rounded-xl p-4 border border-green-200">
                <div class="text-green-600 text-sm font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-check-circle"></i>
                  Paid
                </div>
                <div class="text-2xl font-bold text-green-700">{{ formatCurrency(categoryDetail.summary.total_paid || 0) }}</div>
                <div class="text-xs text-gray-500 mt-1" v-if="categoryDetail.summary.total_value > 0">
                  {{ ((categoryDetail.summary.total_paid || 0) / categoryDetail.summary.total_value * 100).toFixed(1) }}% dari Total Value
                </div>
              </div>
              <div class="bg-orange-50 rounded-xl p-4 border border-orange-200">
                <div class="text-orange-600 text-sm font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-clock"></i>
                  Pending
                </div>
                <div class="text-2xl font-bold text-orange-700">{{ formatCurrency(categoryDetail.summary.total_pending || 0) }}</div>
                <div class="text-xs text-gray-500 mt-1" v-if="categoryDetail.summary.total_value > 0">
                  {{ ((categoryDetail.summary.total_pending || 0) / categoryDetail.summary.total_value * 100).toFixed(1) }}% dari Total Value
                </div>
              </div>
              <div class="bg-red-50 rounded-xl p-4 border border-red-200">
                <div class="text-red-600 text-sm font-semibold mb-1 flex items-center gap-2">
                  <i class="fa-solid fa-exclamation-circle"></i>
                  Unpaid
                </div>
                <div class="text-2xl font-bold text-red-700">{{ formatCurrency(categoryDetail.summary.total_unpaid || 0) }}</div>
                <div class="text-xs text-gray-500 mt-1" v-if="categoryDetail.summary.total_value > 0">
                  {{ ((categoryDetail.summary.total_unpaid || 0) / categoryDetail.summary.total_value * 100).toFixed(1) }}% dari Total Value
                </div>
              </div>
            </div>

            <!-- Purchase Orders List -->
            <div class="space-y-4">
              <h4 class="text-lg font-bold text-gray-800 mb-4">Purchase Orders ({{ categoryDetail.purchase_orders?.length || 0 }})</h4>
              
              <div v-if="categoryDetail.purchase_orders && categoryDetail.purchase_orders.length === 0" class="text-center py-12 text-gray-500">
                <i class="fa-solid fa-inbox text-4xl mb-3"></i>
                <p>Tidak ada Purchase Order untuk category ini</p>
              </div>

              <div v-for="po in categoryDetail.purchase_orders" :key="po.id" 
                   class="bg-white border-2 border-gray-200 rounded-xl overflow-hidden hover:border-indigo-300 transition-all">
                <!-- PO Header (Always Visible) -->
                <div class="p-5 cursor-pointer" @click="toggleCategoryPOExpand(po.id)">
                  <div class="flex items-center justify-between">
                    <div class="flex-1">
                      <div class="flex items-center gap-3 mb-2">
                        <span class="font-bold text-lg text-gray-900">{{ po.number }}</span>
                        <span :class="getStatusClass(po.status)" class="px-3 py-1 rounded-full text-xs font-semibold">
                          {{ po.status.toUpperCase() }}
                        </span>
                        <i class="fa-solid fa-chevron-down transition-transform" 
                           :class="expandedCategoryPOs.has(po.id) ? 'rotate-180' : ''"></i>
                      </div>
                      <div class="text-sm text-gray-600 space-y-1">
                        <div><i class="fa-solid fa-calendar mr-2"></i>{{ formatDate(po.date) }}</div>
                        <div v-if="po.supplier_name">
                          <i class="fa-solid fa-truck mr-2"></i>{{ po.supplier_name }}
                        </div>
                        <div v-if="po.source_pr_number">
                          <i class="fa-solid fa-file-invoice mr-2"></i>PR: {{ po.source_pr_number }}
                        </div>
                        <div v-if="po.creator_name">
                          <i class="fa-solid fa-user mr-2"></i>{{ po.creator_name }}
                        </div>
                      </div>
                    </div>
                    <div class="text-right ml-4">
                      <div class="text-sm text-gray-500 mb-1">Grand Total</div>
                      <div class="text-xl font-bold text-green-600">{{ formatCurrency(po.grand_total) }}</div>
                      <div class="text-xs text-gray-500 mt-1">
                        Subtotal: {{ formatCurrency(po.subtotal) }}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- PO Items (Expandable) -->
                <div v-if="expandedCategoryPOs.has(po.id)" class="border-t border-gray-200 bg-gray-50 p-5">
                  <h5 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-boxes text-indigo-600"></i>
                    Items ({{ po.items?.length || 0 }})
                  </h5>
                  
                  <div v-if="po.items && po.items.length > 0" class="space-y-3">
                    <div v-for="(item, idx) in po.items" :key="idx" 
                         class="bg-white rounded-lg p-4 border border-gray-200">
                      <div class="grid grid-cols-1 md:grid-cols-5 gap-4 text-sm">
                        <div class="md:col-span-2">
                          <div class="text-gray-600 mb-1">Item Name</div>
                          <div class="font-semibold text-gray-900">{{ item.item_name }}</div>
                        </div>
                        <div>
                          <div class="text-gray-600 mb-1">Quantity</div>
                          <div class="font-semibold text-gray-900">{{ formatNumber(item.quantity) }} {{ item.unit }}</div>
                        </div>
                        <div>
                          <div class="text-gray-600 mb-1">Price</div>
                          <div class="font-semibold text-gray-900">{{ formatCurrency(item.price) }}</div>
                        </div>
                        <div>
                          <div class="text-gray-600 mb-1">Total</div>
                          <div class="font-bold text-green-600">{{ formatCurrency(item.total) }}</div>
                          <div v-if="item.discount_percent > 0 || item.discount_amount > 0" class="text-xs text-red-600 mt-1">
                            Disc: {{ item.discount_percent > 0 ? item.discount_percent + '%' : '' }}
                            <span v-if="item.discount_amount > 0">({{ formatCurrency(item.discount_amount) }})</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <div v-else class="text-center py-4 text-gray-500">
                    <i class="fa-solid fa-box-open text-2xl mb-2"></i>
                    <p>Tidak ada items</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-gray-50 px-6 py-4 flex justify-end border-t border-gray-200">
          <button 
            @click="showCategoryDetailModal = false" 
            class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition font-semibold"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, onMounted, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';

const props = defineProps({
  summary: Object,
  statusDistribution: Array,
  trendData: Array,
  supplierAnalysis: Array,
  paymentAnalysis: Object,
  itemAnalysis: Object,
  purchaseOrders: Object,
  poPerOutlet: Array,
  poPerCategory: Array,
  suppliers: Array,
  filters: Object,
});

const loading = ref(false);
const showSupplierDetailModal = ref(false);
const supplierDetail = ref(null);
const loadingSupplierDetail = ref(false);
const showItemDetailModal = ref(false);
const itemDetail = ref(null);
const loadingItemDetail = ref(false);
const showOutletDetailModal = ref(false);
const outletDetail = ref(null);
const loadingOutletDetail = ref(false);
const showCategoryDetailModal = ref(false);
const categoryDetail = ref(null);
const loadingCategoryDetail = ref(false);
const expandedPOs = ref(new Set());
const expandedCategoryPOs = ref(new Set());

const filters = reactive({
  date_from: props.filters.date_from || new Date().toISOString().split('T')[0],
  date_to: props.filters.date_to || new Date().toISOString().split('T')[0],
  status: props.filters.status || 'all',
  supplier_id: props.filters.supplier_id || '',
  search: props.filters.search || '',
  per_page: props.filters.per_page || 15,
  supplier_search: props.filters.supplier_search || '',
  supplier_per_page: props.filters.supplier_per_page || 10,
  item_search: props.filters.item_search || '',
  item_per_page: props.filters.item_per_page || 15,
});

function formatCurrency(value) {
  if (value == null || value === undefined) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', { 
    style: 'currency', 
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(value);
}

function formatNumber(value) {
  if (value == null || value === undefined) return '0';
  return new Intl.NumberFormat('id-ID').format(value);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

function getStatusClass(status) {
  const classes = {
    draft: 'bg-gray-100 text-gray-800',
    approved: 'bg-green-100 text-green-800',
    received: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

function getSupplierRankClass(rank) {
  if (rank === 0) return 'bg-yellow-100 text-yellow-800';
  if (rank === 1) return 'bg-gray-100 text-gray-800';
  if (rank === 2) return 'bg-orange-100 text-orange-800';
  return 'bg-blue-100 text-blue-800';
}

function applyFilters() {
  loading.value = true;
  // Reset pagination when filters change
  const filterParams = {
    ...filters,
    status: 'all', // Always use 'all' since filter is removed
    supplier_id: '', // Always use empty since filter is removed
    page: 1,
    supplier_page: 1,
    item_page: 1,
  };
  router.get(route('po-ops.report'), filterParams, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    }
  });
}

function loadPage(page) {
  loading.value = true;
  router.get(route('po-ops.report'), { ...filters, page }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    }
  });
}

function loadSupplierPage(page) {
  loading.value = true;
  router.get(route('po-ops.report'), { ...filters, supplier_page: page }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    }
  });
}

function loadItemPage(page) {
  loading.value = true;
  router.get(route('po-ops.report'), { ...filters, item_page: page }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    }
  });
}

function refreshData() {
  applyFilters();
}

const itemAnalysisList = computed(() => {
  // Debug: Log what we receive
  console.log('=== ITEM ANALYSIS DEBUG ===');
  console.log('props.itemAnalysis:', props.itemAnalysis);
  console.log('Type:', typeof props.itemAnalysis);
  console.log('Is Array:', Array.isArray(props.itemAnalysis));
  
  if (!props.itemAnalysis) {
    console.log('itemAnalysis is null/undefined');
    return [];
  }
  
  // Laravel paginator structure: { data: [...], current_page: ..., total: ..., ... }
  // Inertia might wrap it differently, so check both direct access and nested
  let dataArray = null;
  
  // Check if it's a direct array
  if (Array.isArray(props.itemAnalysis)) {
    console.log('Found direct array with', props.itemAnalysis.length, 'items');
    dataArray = props.itemAnalysis;
  }
  // Check for paginated data (Laravel paginator)
  else if (props.itemAnalysis.data !== undefined) {
    console.log('Found data property:', props.itemAnalysis.data);
    if (Array.isArray(props.itemAnalysis.data)) {
      console.log('data is array with', props.itemAnalysis.data.length, 'items');
      dataArray = props.itemAnalysis.data;
    } else {
      console.log('data exists but is not array, type:', typeof props.itemAnalysis.data);
    }
  }
  // Check for other possible structures
  else if (props.itemAnalysis.items && Array.isArray(props.itemAnalysis.items)) {
    console.log('Found items property with', props.itemAnalysis.items.length, 'items');
    dataArray = props.itemAnalysis.items;
  }
  // Try to access as object with numeric keys (sometimes Inertia does this)
  else if (typeof props.itemAnalysis === 'object' && props.itemAnalysis !== null) {
    console.log('Found object, keys:', Object.keys(props.itemAnalysis));
    // Check if it's an object that can be converted to array
    const keys = Object.keys(props.itemAnalysis);
    if (keys.length > 0 && keys.some(k => !isNaN(parseInt(k)))) {
      // It might be an object with numeric keys
      console.log('Object has numeric keys, converting to array');
      dataArray = Object.values(props.itemAnalysis);
    }
  }
  
  if (dataArray && Array.isArray(dataArray)) {
    console.log('Final dataArray length:', dataArray.length);
    // Filter out null/undefined items and ensure they have item_name
    const filtered = dataArray.filter(i => i && i.item_name);
    console.log('Filtered length:', filtered.length);
    console.log('First item:', filtered[0]);
    return filtered;
  }
  
  console.log('No valid data array found');
  return [];
});

function getItemAnalysisList() {
  return itemAnalysisList.value;
}

// Debug on mount
onMounted(() => {
  console.log('=== COMPONENT MOUNTED ===');
  console.log('props.itemAnalysis:', props.itemAnalysis);
  console.log('itemAnalysisList.value:', itemAnalysisList.value);
});

function exportReport() {
  // Build query string from current filters
  const params = new URLSearchParams({
    date_from: filters.date_from,
    date_to: filters.date_to,
    status: filters.status,
    supplier_id: filters.supplier_id || '',
    search: filters.search || '',
  });
  
  // Open export URL in new window to trigger download
  window.open(route('po-ops.report.export') + '?' + params.toString(), '_blank');
}

async function showSupplierDetail(supplierId) {
  loadingSupplierDetail.value = true;
  showSupplierDetailModal.value = true;
  supplierDetail.value = null;
  
  try {
    const response = await axios.get(route('po-ops.report.supplier-detail', supplierId), {
      params: {
        date_from: filters.date_from,
        date_to: filters.date_to,
        status: filters.status,
      }
    });
    
    if (response.data.success) {
      supplierDetail.value = response.data;
    } else {
      alert('Gagal mengambil data supplier detail');
    }
  } catch (error) {
    console.error('Error fetching supplier detail:', error);
    alert('Terjadi kesalahan saat mengambil data supplier detail');
  } finally {
    loadingSupplierDetail.value = false;
  }
}

async function showItemDetail(itemName, unit) {
  loadingItemDetail.value = true;
  showItemDetailModal.value = true;
  itemDetail.value = null;
  
  try {
    const response = await axios.get(route('po-ops.report.item-detail'), {
      params: {
        item_name: itemName,
        unit: unit,
        date_from: filters.date_from,
        date_to: filters.date_to,
        status: filters.status,
      }
    });
    
    if (response.data.success) {
      itemDetail.value = response.data;
    } else {
      alert('Gagal mengambil data item detail');
    }
  } catch (error) {
    console.error('Error fetching item detail:', error);
    alert('Terjadi kesalahan saat mengambil data item detail');
  } finally {
    loadingItemDetail.value = false;
  }
}

const statusChartSeries = computed(() => {
  return props.statusDistribution.map(s => s.count);
});

const statusChartOptions = computed(() => {
  const total = statusChartSeries.value.reduce((a, b) => a + b, 0);
  
  // Mapping warna berdasarkan status
  const statusColors = {
    'approved': '#84CC16', // Lime/Hijau untuk APPROVED
    'submitted': '#3B82F6', // Biru untuk SUBMITTED (tetap)
    'rejected': '#EF4444', // Merah untuk REJECTED
    'draft': '#9CA3AF', // Grey untuk status lain
    'received': '#8B5CF6', // Purple untuk status lain
  };
  
  // Generate colors array berdasarkan urutan status
  const colors = props.statusDistribution.map(s => {
    const statusKey = s.status.toLowerCase();
    return statusColors[statusKey] || '#9CA3AF'; // Default grey jika status tidak dikenal
  });
  
  return {
    chart: {
      type: 'donut',
      height: '100%',
    },
    labels: props.statusDistribution.map(s => s.status.toUpperCase()),
    colors: colors,
    legend: {
      position: 'bottom',
    },
    tooltip: {
      y: {
        formatter: function(value, { seriesIndex }) {
          const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
          return `${value} (${percentage}%)`;
        }
      }
    },
    dataLabels: {
      enabled: true,
      formatter: function(val) {
        return val.toFixed(1) + '%';
      }
    },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
        }
      }
    }
  };
});

const trendChartSeries = computed(() => {
  return [
    {
      name: 'PO Count',
      type: 'column',
      data: props.trendData.map(d => d.count),
    },
    {
      name: 'Total Value',
      type: 'line',
      data: props.trendData.map(d => d.total_value),
    }
  ];
});

// Store sorted outlets for click handling
const sortedOutletsForChart = ref([]);

const poPerOutletChartSeries = computed(() => {
  if (!props.poPerOutlet || props.poPerOutlet.length === 0) {
    sortedOutletsForChart.value = [];
    return [
      { name: 'Total Value', type: 'column', data: [] },
      { name: 'Paid', type: 'line', data: [] },
      { name: 'Pending', type: 'line', data: [] },
      { name: 'Unpaid', type: 'line', data: [] }
    ];
  }
  // Sort from largest to smallest (top outlets first)
  const sorted = [...props.poPerOutlet].sort((a, b) => b.total_value - a.total_value);
  sortedOutletsForChart.value = sorted;
  // Take top 20 only
  const topOutlets = sorted.slice(0, 20);
  return [
    {
      name: 'Total Value',
      type: 'column',
      data: topOutlets.map(o => o.total_value || 0),
    },
    {
      name: 'Paid',
      type: 'line',
      data: topOutlets.map(o => o.total_paid || 0),
    },
    {
      name: 'Pending',
      type: 'line',
      data: topOutlets.map(o => o.total_pending || 0),
    },
    {
      name: 'Unpaid',
      type: 'line',
      data: topOutlets.map(o => o.total_unpaid || 0),
    }
  ];
});

const poPerOutletChartOptions = computed(() => {
  if (!props.poPerOutlet || props.poPerOutlet.length === 0) {
    return {
      chart: { type: 'bar', height: '100%' },
      xaxis: { categories: [] },
    };
  }
  
  // Limit to top 20 outlets untuk readability
  const sorted = sortedOutletsForChart.value.length > 0 
    ? sortedOutletsForChart.value 
    : [...props.poPerOutlet].sort((a, b) => b.total_value - a.total_value); // Sort descending untuk top outlets
  
  const topOutlets = sorted.slice(0, 20); // Ambil top 20 saja
  
  return {
    chart: {
      type: 'bar',
      height: 600,
      toolbar: { show: true },
    },
    stroke: {
      width: [0, 3, 3, 3], // No stroke for bar, 3px for lines
      curve: 'smooth'
    },
    plotOptions: {
      bar: {
        horizontal: false, // Vertical bar chart
        columnWidth: '60%',
        borderRadius: 4,
        dataLabels: {
          position: 'top' // Label di atas bar
        }
      }
    },
    dataLabels: {
      enabled: true,
      enabledOnSeries: [0], // Only show labels on bar chart (Total Value)
      formatter: function(val) {
        // Format lebih compact (Indonesian format)
        if (val >= 1000000000) {
          return 'Rp ' + (val / 1000000000).toFixed(1) + ' M';
        } else if (val >= 1000000) {
          return 'Rp ' + (val / 1000000).toFixed(0) + ' Jt';
        } else if (val >= 1000) {
          return 'Rp ' + (val / 1000).toFixed(0) + ' Rb';
        }
        return formatCurrency(val);
      },
      style: {
        fontSize: '10px',
        fontWeight: 600,
        colors: ['#1F2937']
      },
      offsetY: -5
    },
    xaxis: {
      categories: topOutlets.map(o => {
        // Truncate nama outlet jika terlalu panjang
        const name = o.outlet_name || '';
        return name.length > 20 ? name.substring(0, 20) + '...' : name;
      }),
      labels: {
        rotate: -45,
        rotateAlways: true,
        style: {
          fontSize: '11px',
          fontWeight: 500
        },
        maxHeight: 100
      },
      title: {
        text: 'Outlet',
        style: {
          fontSize: '12px',
          fontWeight: 600
        }
      }
    },
    yaxis: {
      title: {
        text: 'Total Value',
        style: {
          fontSize: '12px',
          fontWeight: 600
        }
      },
      labels: {
        formatter: function(val) {
          if (val >= 1000000000) {
            return 'Rp ' + (val / 1000000000).toFixed(1) + ' M';
          } else if (val >= 1000000) {
            return 'Rp ' + (val / 1000000).toFixed(0) + ' Jt';
          } else if (val >= 1000) {
            return 'Rp ' + (val / 1000).toFixed(0) + ' Rb';
          }
          return formatCurrency(val);
        },
        style: {
          fontSize: '11px'
        }
      }
    },
    tooltip: {
      shared: true,
      intersect: false,
      custom: function({series, seriesIndex, dataPointIndex, w}) {
        // Get outlet data from sortedOutletsForChart
        const topOutlets = sortedOutletsForChart.value.slice(0, 20);
        const outlet = topOutlets[dataPointIndex];
        if (!outlet) return '';
        
        const totalValue = series[0] ? (series[0][dataPointIndex] || 0) : 0;
        const paid = series[1] ? (series[1][dataPointIndex] || 0) : 0;
        const pending = series[2] ? (series[2][dataPointIndex] || 0) : 0;
        const unpaid = series[3] ? (series[3][dataPointIndex] || 0) : 0;
        
        // Format currency helper (Indonesian format)
        const formatCurr = (val) => {
          if (val >= 1000000000) {
            return 'Rp ' + (val / 1000000000).toFixed(1) + ' M';
          } else if (val >= 1000000) {
            return 'Rp ' + (val / 1000000).toFixed(0) + ' Jt';
          } else if (val >= 1000) {
            return 'Rp ' + (val / 1000).toFixed(0) + ' Rb';
          }
          return new Intl.NumberFormat('id-ID', { 
            style: 'currency', 
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
          }).format(val);
        };
        
        return `
          <div style="padding: 12px; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="font-weight: bold; color: #1f2937; margin-bottom: 8px; font-size: 14px;">${outlet.outlet_name || 'Outlet'}</div>
            <div style="display: flex; flex-direction: column; gap: 6px; font-size: 12px;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #6b7280;">Total Value:</span>
                <span style="font-weight: 600; color: #8b5cf6;">${formatCurr(totalValue)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #6b7280;">Paid:</span>
                <span style="font-weight: 600; color: #10b981;">${formatCurr(paid)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #6b7280;">Pending:</span>
                <span style="font-weight: 600; color: #f59e0b;">${formatCurr(pending)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #6b7280;">Unpaid:</span>
                <span style="font-weight: 600; color: #ef4444;">${formatCurr(unpaid)}</span>
              </div>
            </div>
          </div>
        `;
      }
    },
    colors: ['#8B5CF6', '#10B981', '#F59E0B', '#EF4444'], // Purple for bar, Green for Paid, Orange for Pending, Red for Unpaid
    legend: {
      show: true,
      position: 'top',
      horizontalAlign: 'right',
    },
    grid: {
      xaxis: {
        lines: {
          show: false
        }
      },
      yaxis: {
        lines: {
          show: true
        }
      }
    },
    markers: {
      size: [0, 5, 5, 5], // No markers for bar, size 5 for lines
      hover: {
        size: [0, 7, 7, 7]
      }
    }
  };
});

// Store sorted categories for click handling
const sortedCategoriesForChart = ref([]);

const poPerCategoryChartSeries = computed(() => {
  if (!props.poPerCategory || props.poPerCategory.length === 0) {
    sortedCategoriesForChart.value = [];
    return [
      { name: 'Total Value', type: 'column', data: [] },
      { name: 'Paid', type: 'line', data: [] },
      { name: 'Pending', type: 'line', data: [] },
      { name: 'Unpaid', type: 'line', data: [] }
    ];
  }
  // Sort from largest to smallest (top categories first)
  const sorted = [...props.poPerCategory].sort((a, b) => b.total_value - a.total_value);
  sortedCategoriesForChart.value = sorted;
  // Take top 20 only
  const topCategories = sorted.slice(0, 20);
  return [
    {
      name: 'Total Value',
      type: 'column',
      data: topCategories.map(c => c.total_value || 0),
    },
    {
      name: 'Paid',
      type: 'line',
      data: topCategories.map(c => c.total_paid || 0),
    },
    {
      name: 'Pending',
      type: 'line',
      data: topCategories.map(c => c.total_pending || 0),
    },
    {
      name: 'Unpaid',
      type: 'line',
      data: topCategories.map(c => c.total_unpaid || 0),
    }
  ];
});

const poPerCategoryChartOptions = computed(() => {
  if (!props.poPerCategory || props.poPerCategory.length === 0) {
    return {
      chart: { type: 'bar', height: '100%' },
      xaxis: { categories: [] },
    };
  }
  
  // Limit to top 20 categories untuk readability
  const sorted = sortedCategoriesForChart.value.length > 0 
    ? sortedCategoriesForChart.value 
    : [...props.poPerCategory].sort((a, b) => b.total_value - a.total_value);
  
  const topCategories = sorted.slice(0, 20);
  
  return {
    chart: {
      type: 'bar',
      height: 600,
      toolbar: { show: true },
    },
    stroke: {
      width: [0, 3, 3, 3],
      curve: 'smooth'
    },
    plotOptions: {
      bar: {
        horizontal: false,
        columnWidth: '60%',
        borderRadius: 4,
        dataLabels: {
          position: 'top'
        }
      }
    },
    dataLabels: {
      enabled: true,
      enabledOnSeries: [0],
      formatter: function(val) {
        if (val >= 1000000000) {
          return 'Rp ' + (val / 1000000000).toFixed(1) + ' M';
        } else if (val >= 1000000) {
          return 'Rp ' + (val / 1000000).toFixed(0) + ' Jt';
        } else if (val >= 1000) {
          return 'Rp ' + (val / 1000).toFixed(0) + ' Rb';
        }
        return formatCurrency(val);
      },
      style: {
        fontSize: '10px',
        fontWeight: 600,
        colors: ['#1F2937']
      },
      offsetY: -5
    },
    xaxis: {
      categories: topCategories.map(c => {
        const name = c.category_name || '';
        return name.length > 20 ? name.substring(0, 20) + '...' : name;
      }),
      labels: {
        rotate: -45,
        rotateAlways: true,
        style: {
          fontSize: '11px',
          fontWeight: 500
        },
        maxHeight: 100
      },
      title: {
        text: 'Category',
        style: {
          fontSize: '12px',
          fontWeight: 600
        }
      }
    },
    yaxis: {
      title: {
        text: 'Total Value',
        style: {
          fontSize: '12px',
          fontWeight: 600
        }
      },
      labels: {
        formatter: function(val) {
          if (val >= 1000000000) {
            return 'Rp ' + (val / 1000000000).toFixed(1) + ' M';
          } else if (val >= 1000000) {
            return 'Rp ' + (val / 1000000).toFixed(0) + ' Jt';
          } else if (val >= 1000) {
            return 'Rp ' + (val / 1000).toFixed(0) + ' Rb';
          }
          return formatCurrency(val);
        },
        style: {
          fontSize: '11px'
        }
      }
    },
    tooltip: {
      shared: true,
      intersect: false,
      custom: function({series, seriesIndex, dataPointIndex, w}) {
        const topCategories = sortedCategoriesForChart.value.slice(0, 20);
        const category = topCategories[dataPointIndex];
        if (!category) return '';
        
        const totalValue = series[0] ? (series[0][dataPointIndex] || 0) : 0;
        const paid = series[1] ? (series[1][dataPointIndex] || 0) : 0;
        const pending = series[2] ? (series[2][dataPointIndex] || 0) : 0;
        const unpaid = series[3] ? (series[3][dataPointIndex] || 0) : 0;
        
        const formatCurr = (val) => {
          if (val >= 1000000000) {
            return 'Rp ' + (val / 1000000000).toFixed(1) + ' M';
          } else if (val >= 1000000) {
            return 'Rp ' + (val / 1000000).toFixed(0) + ' Jt';
          } else if (val >= 1000) {
            return 'Rp ' + (val / 1000).toFixed(0) + ' Rb';
          }
          return new Intl.NumberFormat('id-ID', { 
            style: 'currency', 
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
          }).format(val);
        };
        
        return `
          <div style="padding: 12px; background: white; border: 1px solid #e5e7eb; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <div style="font-weight: bold; color: #1f2937; margin-bottom: 8px; font-size: 14px;">${category.category_name || 'Category'}</div>
            <div style="display: flex; flex-direction: column; gap: 6px; font-size: 12px;">
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #6b7280;">Total Value:</span>
                <span style="font-weight: 600; color: #8b5cf6;">${formatCurr(totalValue)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #6b7280;">Paid:</span>
                <span style="font-weight: 600; color: #10b981;">${formatCurr(paid)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #6b7280;">Pending:</span>
                <span style="font-weight: 600; color: #f59e0b;">${formatCurr(pending)}</span>
              </div>
              <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="color: #6b7280;">Unpaid:</span>
                <span style="font-weight: 600; color: #ef4444;">${formatCurr(unpaid)}</span>
              </div>
            </div>
          </div>
        `;
      }
    },
    colors: ['#6366F1', '#10B981', '#F59E0B', '#EF4444'], // Indigo for bar, Green for Paid, Orange for Pending, Red for Unpaid
    legend: {
      show: true,
      position: 'top',
      horizontalAlign: 'right',
    },
    grid: {
      xaxis: {
        lines: {
          show: false
        }
      },
      yaxis: {
        lines: {
          show: true
        }
      }
    },
    markers: {
      size: [0, 5, 5, 5],
      hover: {
        size: [0, 7, 7, 7]
      }
    }
  };
});

function onCategoryBarClick(event, chartContext, config) {
  if (config.dataPointIndex !== undefined && sortedCategoriesForChart.value && sortedCategoriesForChart.value[config.dataPointIndex]) {
    const topCategories = sortedCategoriesForChart.value.slice(0, 20);
    const category = topCategories[config.dataPointIndex];
    if (category) {
      showCategoryDetail(category.category_id);
    }
  }
}

async function showCategoryDetail(categoryId) {
  loadingCategoryDetail.value = true;
  showCategoryDetailModal.value = true;
  categoryDetail.value = null;
  expandedCategoryPOs.value.clear();
  
  try {
    const response = await axios.get(route('po-ops.report.category-detail', categoryId), {
      params: {
        date_from: filters.date_from,
        date_to: filters.date_to,
        status: filters.status,
      }
    });
    
    if (response.data.success) {
      categoryDetail.value = response.data;
    } else {
      alert('Gagal mengambil data category detail');
    }
  } catch (error) {
    console.error('Error fetching category detail:', error);
    alert('Terjadi kesalahan saat mengambil data category detail');
  } finally {
    loadingCategoryDetail.value = false;
  }
}

function toggleCategoryPOExpand(poId) {
  if (expandedCategoryPOs.value.has(poId)) {
    expandedCategoryPOs.value.delete(poId);
  } else {
    expandedCategoryPOs.value.add(poId);
  }
}

function onOutletBarClick(event, chartContext, config) {
  if (config.dataPointIndex !== undefined && sortedOutletsForChart.value && sortedOutletsForChart.value[config.dataPointIndex]) {
    // Use sorted outlets for chart to match the displayed order (top 20)
    const topOutlets = sortedOutletsForChart.value.slice(0, 20);
    const outlet = topOutlets[config.dataPointIndex];
    if (outlet) {
      showOutletDetail(outlet.outlet_id);
    }
  }
}

async function showOutletDetail(outletId) {
  loadingOutletDetail.value = true;
  showOutletDetailModal.value = true;
  outletDetail.value = null;
  expandedPOs.value.clear();
  
  try {
    const response = await axios.get(route('po-ops.report.outlet-detail', outletId), {
      params: {
        date_from: filters.date_from,
        date_to: filters.date_to,
        status: filters.status,
      }
    });
    
    if (response.data.success) {
      outletDetail.value = response.data;
    } else {
      alert('Gagal mengambil data outlet detail');
    }
  } catch (error) {
    console.error('Error fetching outlet detail:', error);
    alert('Terjadi kesalahan saat mengambil data outlet detail');
  } finally {
    loadingOutletDetail.value = false;
  }
}

function togglePOExpand(poId) {
  if (expandedPOs.value.has(poId)) {
    expandedPOs.value.delete(poId);
  } else {
    expandedPOs.value.add(poId);
  }
}

const trendChartOptions = computed(() => {
  return {
    chart: {
      type: 'line',
      height: '100%',
      toolbar: {
        show: true,
      },
    },
    stroke: {
      width: [0, 3],
      curve: 'smooth',
    },
    labels: props.trendData.map(d => formatDate(d.date)),
    xaxis: {
      type: 'category',
    },
    yaxis: [
      {
        title: {
          text: 'PO Count',
        },
      },
      {
        opposite: true,
        title: {
          text: 'Total Value (Rp)',
        },
        labels: {
          formatter: function(val) {
            return formatCurrency(val);
          }
        }
      }
    ],
    tooltip: {
      shared: true,
      intersect: false,
      y: {
        formatter: function(val, { seriesIndex }) {
          if (seriesIndex === 0) {
            return val;
          } else {
            return formatCurrency(val);
          }
        }
      }
    },
    legend: {
      position: 'top',
    },
    fill: {
      opacity: [0.85, 1],
    },
    colors: ['#3B82F6', '#10B981'],
  };
});

// Register ApexCharts component
defineOptions({
  components: {
    apexchart: VueApexCharts
  }
});
</script>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fadeIn {
  animation: fadeIn 0.5s ease-out;
}
</style>

