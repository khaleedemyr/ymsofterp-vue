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
                Purchase Requisition Ops Report
              </span>
            </h1>
            <p class="text-gray-600 ml-16">Comprehensive analytics and insights for Purchase Requisitions</p>
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
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-filter mr-1 text-blue-500"></i>
                Status
              </label>
              <select 
                v-model="filters.status" 
                class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
              >
                <option value="all">All Status</option>
                <option value="DRAFT">Draft</option>
                <option value="SUBMITTED">Submitted</option>
                <option value="APPROVED">Approved</option>
                <option value="REJECTED">Rejected</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-building mr-1 text-blue-500"></i>
                Category
              </label>
              <select 
                v-model="filters.category_id" 
                class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
              >
                <option value="">All Categories</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-store mr-1 text-blue-500"></i>
                Outlet
              </label>
              <select 
                v-model="filters.outlet_id" 
                class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
              >
                <option value="">All Outlets</option>
                <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-sitemap mr-1 text-blue-500"></i>
                Division
              </label>
              <select 
                v-model="filters.division_id" 
                class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
              >
                <option value="">All Divisions</option>
                <option v-for="div in divisions" :key="div.id" :value="div.id">{{ div.name }}</option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-gray-700 mb-2">
                <i class="fa-solid fa-search mr-1 text-blue-500"></i>
                Search
              </label>
              <input 
                type="text" 
                v-model="filters.search" 
                placeholder="Search PR Number or Description..."
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
                <div class="text-3xl font-bold">{{ formatNumber(summary.total_pr) }}</div>
                <div class="text-blue-100 text-sm">Total PR</div>
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
              Avg: {{ formatCurrency(summary.avg_pr_value) }}
            </div>
          </div>

          <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl shadow-xl p-6 text-white transform hover:scale-105 transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 bg-white bg-opacity-20 rounded-xl">
                <i class="fa-solid fa-paper-plane text-2xl"></i>
              </div>
              <div class="text-right">
                <div class="text-3xl font-bold">{{ formatNumber(summary.submitted_count) }}</div>
                <div class="text-purple-100 text-sm">Submitted</div>
              </div>
            </div>
            <div class="text-sm text-purple-100 mt-2">
              <i class="fa-solid fa-clock mr-1"></i>
              Waiting for approval
            </div>
          </div>

          <div class="bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl shadow-xl p-6 text-white transform hover:scale-105 transition-all duration-300">
            <div class="flex items-center justify-between mb-4">
              <div class="p-3 bg-white bg-opacity-20 rounded-xl">
                <i class="fa-solid fa-times-circle text-2xl"></i>
              </div>
              <div class="text-right">
                <div class="text-3xl font-bold">{{ formatNumber(summary.rejected_count) }}</div>
                <div class="text-orange-100 text-sm">Rejected</div>
              </div>
            </div>
            <div class="text-sm text-orange-100 mt-2">
              <i class="fa-solid fa-exclamation-triangle mr-1"></i>
              {{ summary.draft_count }} Draft
            </div>
          </div>
        </div>

        <!-- Status Distribution Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
          <div v-for="status in statusDistribution" :key="status.status" 
            class="bg-white rounded-xl shadow-lg p-5 border-l-4 hover:shadow-xl transition-all"
            :class="{
              'border-gray-400': status.status === 'DRAFT',
              'border-yellow-400': status.status === 'SUBMITTED',
              'border-green-500': status.status === 'APPROVED',
              'border-red-500': status.status === 'REJECTED'
            }"
          >
            <div class="flex items-center justify-between">
              <div>
                <div class="text-gray-500 text-sm font-medium mb-1">{{ status.status }}</div>
                <div class="text-3xl font-bold" :class="{
                  'text-gray-700': status.status === 'DRAFT',
                  'text-yellow-600': status.status === 'SUBMITTED',
                  'text-green-600': status.status === 'APPROVED',
                  'text-red-600': status.status === 'REJECTED'
                }">{{ status.count }}</div>
                <div class="text-sm text-gray-500 mt-1">{{ formatCurrency(status.total_value) }}</div>
              </div>
              <div class="p-3 rounded-lg" :class="{
                'bg-gray-100': status.status === 'DRAFT',
                'bg-yellow-100': status.status === 'SUBMITTED',
                'bg-green-100': status.status === 'APPROVED',
                'bg-red-100': status.status === 'REJECTED'
              }">
                <i class="fa-solid text-2xl" :class="{
                  'fa-file text-gray-600': status.status === 'DRAFT',
                  'fa-paper-plane text-yellow-600': status.status === 'SUBMITTED',
                  'fa-check-circle text-green-600': status.status === 'APPROVED',
                  'fa-times-circle text-red-600': status.status === 'REJECTED'
                }"></i>
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

        <!-- Analysis Tables -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <!-- Category Analysis -->
          <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
              <i class="fa-solid fa-tags text-purple-600"></i>
              Category Analysis
            </h3>
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="bg-gray-50">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Category</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">PR Count</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">PR Value</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">RNF Count</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">RNF Value</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total Value</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Remaining</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="cat in categoryAnalysis.data" :key="cat.category_id" 
                    class="border-b hover:bg-blue-50 cursor-pointer transition-colors"
                    @click="showCategoryDetail(cat.category_id)"
                  >
                    <td class="px-4 py-3">
                      <div class="text-sm font-medium text-blue-600 hover:underline">{{ cat.display_name || cat.category_name }}</div>
                      <div v-if="cat.budget_limit" class="text-xs text-gray-500 mt-1">
                        Budget: {{ formatCurrency(cat.budget_limit) }}
                      </div>
                      <div v-else class="text-xs text-gray-400 mt-1">No Budget Limit</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ cat.pr_count || 0 }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700">{{ formatCurrency(cat.pr_value || 0) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ cat.rnf_count || 0 }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700">{{ formatCurrency(cat.rnf_value || 0) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">{{ formatCurrency(cat.total_value) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold" :class="cat.remaining_budget !== null && cat.remaining_budget < 0 ? 'text-red-600' : 'text-gray-700'">
                      <span v-if="cat.remaining_budget !== null">{{ formatCurrency(cat.remaining_budget) }}</span>
                      <span v-else class="text-gray-400">-</span>
                    </td>
                  </tr>
                  <tr v-if="categoryAnalysis.data.length === 0">
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">No data available</td>
                  </tr>
                </tbody>
              </table>
              <div v-if="categoryAnalysis.last_page > 1" class="mt-4 flex justify-center gap-2">
                <button 
                  v-for="page in categoryAnalysis.last_page" 
                  :key="page"
                  @click="loadCategoryPage(page)"
                  class="px-3 py-1 rounded"
                  :class="categoryAnalysis.current_page === page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                >
                  {{ page }}
                </button>
              </div>
            </div>
          </div>

          <!-- Outlet Analysis -->
          <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
              <i class="fa-solid fa-store text-indigo-600"></i>
              Outlet Analysis
            </h3>
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="bg-gray-50">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Outlet</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">PR Count</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">PR Value</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">RNF Count</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">RNF Value</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total Value</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="outlet in outletAnalysis.data" :key="outlet.outlet_id" 
                    class="border-b hover:bg-blue-50 cursor-pointer transition-colors"
                    @click="showOutletDetail(outlet.outlet_id)"
                  >
                    <td class="px-4 py-3 text-sm font-medium text-blue-600 hover:underline">{{ outlet.outlet_name }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ outlet.pr_count || 0 }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700">{{ formatCurrency(outlet.pr_value || 0) }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600">{{ outlet.rnf_count || 0 }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-700">{{ formatCurrency(outlet.rnf_value || 0) }}</td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">{{ formatCurrency(outlet.total_value) }}</td>
                  </tr>
                  <tr v-if="outletAnalysis.data.length === 0">
                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No data available</td>
                  </tr>
                </tbody>
              </table>
              <div v-if="outletAnalysis.last_page > 1" class="mt-4 flex justify-center gap-2">
                <button 
                  v-for="page in outletAnalysis.last_page" 
                  :key="page"
                  @click="loadOutletPage(page)"
                  class="px-3 py-1 rounded"
                  :class="outletAnalysis.current_page === page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
                >
                  {{ page }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Division Analysis -->
        <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100 mb-8">
          <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-sitemap text-green-600"></i>
            Division Analysis
          </h3>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Division</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">PR Count</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total Value</th>
                </tr>
              </thead>
                <tbody>
                <tr v-for="div in divisionAnalysis" :key="div.division_id" 
                  class="border-b hover:bg-blue-50 cursor-pointer transition-colors"
                  @click="showDivisionDetail(div.division_id)"
                >
                  <td class="px-4 py-3 text-sm font-medium text-blue-600 hover:underline">{{ div.division_name }}</td>
                  <td class="px-4 py-3 text-sm text-right text-gray-600">{{ div.pr_count }}</td>
                  <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">{{ formatCurrency(div.total_value) }}</td>
                </tr>
                <tr v-if="divisionAnalysis.length === 0">
                  <td colspan="3" class="px-4 py-8 text-center text-gray-500">No data available</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- PR List -->
        <div class="bg-white rounded-2xl shadow-xl p-6 border border-gray-100 mb-8">
          <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-list text-blue-600"></i>
            Purchase Requisitions
          </h3>
          <div class="overflow-x-auto">
            <table class="w-full">
              <thead>
                <tr class="bg-gray-50">
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">PR Number</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Division</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Items</th>
                  <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="pr in purchaseRequisitions.data" :key="pr.id" class="border-b hover:bg-gray-50">
                  <td class="px-4 py-3 text-sm font-medium text-blue-600">
                    <Link :href="route('purchase-requisitions.show', pr.id)" class="hover:underline">
                      {{ pr.pr_number }}
                    </Link>
                  </td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(pr.created_at) }}</td>
                  <td class="px-4 py-3 text-sm text-gray-600">{{ pr.division?.nama_divisi || '-' }}</td>
                  <td class="px-4 py-3">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold" :class="getStatusClass(pr.status)">
                      {{ pr.status }}
                    </span>
                  </td>
                  <td class="px-4 py-3 text-sm text-right text-gray-600">{{ pr.items?.length || 0 }}</td>
                  <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900">
                    {{ formatCurrency(pr.items?.reduce((sum, item) => sum + (item.subtotal || 0), 0) || 0) }}
                  </td>
                </tr>
                <tr v-if="purchaseRequisitions.data.length === 0">
                  <td colspan="6" class="px-4 py-8 text-center text-gray-500">No data available</td>
                </tr>
              </tbody>
            </table>
            <div v-if="purchaseRequisitions.last_page > 1" class="mt-4 flex justify-center gap-2">
              <button 
                v-for="page in purchaseRequisitions.last_page" 
                :key="page"
                @click="loadPage(page)"
                class="px-3 py-1 rounded"
                :class="purchaseRequisitions.current_page === page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'"
              >
                {{ page }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Category Detail Modal -->
    <div v-if="showCategoryDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showCategoryDetailModal = false">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-6xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
          <h3 class="text-2xl font-bold text-gray-800">
            <i class="fa-solid fa-tags text-purple-600 mr-2"></i>
            Category Detail: {{ categoryDetail?.category?.name || '' }}
          </h3>
          <button @click="showCategoryDetailModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div v-if="loadingCategoryDetail" class="flex items-center justify-center py-20">
          <div class="text-center">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-600 mb-4"></div>
            <p class="text-gray-600 text-lg font-semibold">Loading category detail...</p>
          </div>
        </div>

        <div v-else-if="categoryDetail" class="p-6">
          <!-- Summary -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
              <div class="text-sm text-blue-600 font-medium mb-1">PR Count</div>
              <div class="text-2xl font-bold text-blue-800">{{ categoryDetail.summary?.pr_count || 0 }}</div>
            </div>
            <div class="bg-green-50 rounded-xl p-4 border border-green-200">
              <div class="text-sm text-green-600 font-medium mb-1">PR Total</div>
              <div class="text-xl font-bold text-green-800">{{ formatCurrency(categoryDetail.summary?.pr_total || 0) }}</div>
            </div>
            <div class="bg-purple-50 rounded-xl p-4 border border-purple-200">
              <div class="text-sm text-purple-600 font-medium mb-1">RNF Count</div>
              <div class="text-2xl font-bold text-purple-800">{{ categoryDetail.summary?.rnf_count || 0 }}</div>
            </div>
            <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-200">
              <div class="text-sm text-indigo-600 font-medium mb-1">RNF Total</div>
              <div class="text-xl font-bold text-indigo-800">{{ formatCurrency(categoryDetail.summary?.rnf_total || 0) }}</div>
            </div>
          </div>

          <div class="bg-yellow-50 rounded-xl p-4 border border-yellow-200 mb-6">
            <div class="text-sm text-yellow-600 font-medium mb-1">Grand Total</div>
            <div class="text-3xl font-bold text-yellow-800">{{ formatCurrency(categoryDetail.summary?.grand_total || 0) }}</div>
          </div>

          <!-- Purchase Requisitions -->
          <div class="mb-6">
            <div class="flex justify-between items-center mb-4">
              <h4 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <i class="fa-solid fa-file-invoice text-blue-600"></i>
                Purchase Requisitions ({{ categoryDetail.purchase_requisitions?.total || 0 }})
              </h4>
              <div class="flex items-center gap-2">
                <input 
                  v-model="categoryPRSearch" 
                  @input="loadCategoryPRPage(1)"
                  type="text" 
                  placeholder="Search PR number..." 
                  class="px-3 py-1 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                <select 
                  v-model="categoryPRPerPage" 
                  @change="loadCategoryPRPage(1)"
                  class="px-3 py-1 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option :value="5">5 per page</option>
                  <option :value="10">10 per page</option>
                  <option :value="20">20 per page</option>
                  <option :value="50">50 per page</option>
                </select>
              </div>
            </div>
            <div class="space-y-3">
              <div v-for="pr in categoryDetail.purchase_requisitions?.data || []" :key="pr.id" 
                class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-2">
                  <div class="flex-1">
                    <Link :href="route('purchase-requisitions.show', pr.id)" class="text-blue-600 hover:underline font-semibold">
                      {{ pr.pr_number }}
                    </Link>
                    <div class="text-sm text-gray-600 mt-1">{{ formatDate(pr.date) }} | {{ pr.division }}</div>
                    <div v-if="pr.creator" class="flex items-center gap-2 mt-2">
                      <img v-if="pr.creator.avatar" :src="pr.creator.avatar" :alt="pr.creator.name" 
                        class="w-6 h-6 rounded-full object-cover">
                      <div v-else class="w-6 h-6 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600">
                        {{ pr.creator.name.charAt(0).toUpperCase() }}
                      </div>
                      <span class="text-xs text-gray-600">{{ pr.creator.name }}</span>
                    </div>
                    <div v-if="pr.approval && pr.approval.approver" class="flex items-center gap-2 mt-2">
                      <i class="fa-solid fa-check-circle text-green-600 text-xs"></i>
                      <img v-if="pr.approval.approver.avatar" :src="pr.approval.approver.avatar" :alt="pr.approval.approver.name" 
                        class="w-6 h-6 rounded-full object-cover">
                      <div v-else class="w-6 h-6 rounded-full bg-green-300 flex items-center justify-center text-xs text-green-700">
                        {{ pr.approval.approver.name.charAt(0).toUpperCase() }}
                      </div>
                      <div class="flex flex-col">
                        <span class="text-xs text-gray-600 font-medium">{{ pr.approval.approver.name }}</span>
                        <span v-if="pr.approval.approved_at" class="text-xs text-gray-500">{{ formatDateTime(pr.approval.approved_at) }}</span>
                      </div>
                    </div>
                  </div>
                  <div class="text-right">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold" :class="getStatusClass(pr.status)">
                      {{ pr.status }}
                    </span>
                    <div v-if="pr.approval" class="mt-1">
                      <span class="px-2 py-1 rounded-full text-xs font-semibold" 
                        :class="pr.approval.status === 'APPROVED' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                        {{ pr.approval.status }}
                      </span>
                    </div>
                    <div class="text-lg font-bold text-gray-900 mt-1">{{ formatCurrency(pr.total_amount) }}</div>
                  </div>
                </div>
                <div v-if="pr.items && pr.items.length > 0" class="mt-3 pt-3 border-t border-gray-200">
                  <div class="text-xs text-gray-500 mb-2">Items:</div>
                  <div class="space-y-1">
                    <div v-for="(item, idx) in pr.items" :key="idx" class="text-sm text-gray-700 flex justify-between">
                      <span>{{ item.item_name }} ({{ item.qty }}x)</span>
                      <span class="font-semibold">{{ formatCurrency(item.subtotal) }}</span>
                    </div>
                  </div>
                </div>
              </div>
              <div v-if="!categoryDetail.purchase_requisitions?.data || categoryDetail.purchase_requisitions.data.length === 0" 
                class="text-center py-8 text-gray-500">
                No Purchase Requisitions
              </div>
            </div>
            <!-- Pagination -->
            <div v-if="categoryDetail.purchase_requisitions && categoryDetail.purchase_requisitions.last_page > 1" 
              class="mt-4 flex justify-center items-center gap-2">
              <button 
                @click="loadCategoryPRPage(categoryDetail.purchase_requisitions.current_page - 1)"
                :disabled="categoryDetail.purchase_requisitions.current_page === 1"
                class="px-3 py-1 rounded border"
                :class="categoryDetail.purchase_requisitions.current_page === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'"
              >
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <span class="text-sm text-gray-600">
                Page {{ categoryDetail.purchase_requisitions.current_page }} of {{ categoryDetail.purchase_requisitions.last_page }}
              </span>
              <button 
                @click="loadCategoryPRPage(categoryDetail.purchase_requisitions.current_page + 1)"
                :disabled="categoryDetail.purchase_requisitions.current_page === categoryDetail.purchase_requisitions.last_page"
                class="px-3 py-1 rounded border"
                :class="categoryDetail.purchase_requisitions.current_page === categoryDetail.purchase_requisitions.last_page ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-50'"
              >
                <i class="fa-solid fa-chevron-right"></i>
              </button>
            </div>
          </div>

          <!-- Retail Non Food -->
          <div>
            <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
              <i class="fa-solid fa-shopping-bag text-purple-600"></i>
              Retail Non Food ({{ categoryDetail.retail_non_food?.length || 0 }})
            </h4>
            <div class="space-y-3">
              <div v-for="rnf in categoryDetail.retail_non_food" :key="rnf.id" 
                class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-2">
                  <div class="flex-1">
                    <div class="font-semibold text-gray-900">{{ rnf.retail_number }}</div>
                    <div class="text-sm text-gray-600 mt-1">{{ formatDate(rnf.date) }} | {{ rnf.outlet_name }} | {{ rnf.supplier_name }}</div>
                    <div v-if="rnf.notes" class="text-sm text-gray-500 mt-1 italic">{{ rnf.notes }}</div>
                    <div v-if="rnf.creator" class="flex items-center gap-2 mt-2">
                      <img v-if="rnf.creator.avatar" :src="rnf.creator.avatar" :alt="rnf.creator.name" 
                        class="w-6 h-6 rounded-full object-cover">
                      <div v-else class="w-6 h-6 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600">
                        {{ rnf.creator.name.charAt(0).toUpperCase() }}
                      </div>
                      <span class="text-xs text-gray-600">{{ rnf.creator.name }}</span>
                    </div>
                  </div>
                  <div class="text-lg font-bold text-gray-900">{{ formatCurrency(rnf.total_amount) }}</div>
                </div>
                <div v-if="rnf.items && rnf.items.length > 0" class="mt-3 pt-3 border-t border-gray-200">
                  <div class="text-xs text-gray-500 mb-2 font-semibold">Items:</div>
                  <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead>
                        <tr class="bg-gray-50">
                          <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Item</th>
                          <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Qty</th>
                          <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Unit</th>
                          <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Price</th>
                          <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Subtotal</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(item, idx) in rnf.items" :key="idx" class="border-b border-gray-100">
                          <td class="px-3 py-2 text-gray-700">{{ item.item_name }}</td>
                          <td class="px-3 py-2 text-right text-gray-600">{{ item.qty }}</td>
                          <td class="px-3 py-2 text-right text-gray-600">{{ item.unit || '-' }}</td>
                          <td class="px-3 py-2 text-right text-gray-700">{{ formatCurrency(item.price) }}</td>
                          <td class="px-3 py-2 text-right font-semibold text-gray-900">{{ formatCurrency(item.subtotal) }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              <div v-if="!categoryDetail.retail_non_food || categoryDetail.retail_non_food.length === 0" 
                class="text-center py-8 text-gray-500">
                No Retail Non Food
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Outlet Detail Modal -->
    <div v-if="showOutletDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showOutletDetailModal = false">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-6xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
          <h3 class="text-2xl font-bold text-gray-800">
            <i class="fa-solid fa-store text-indigo-600 mr-2"></i>
            Outlet Detail: {{ outletDetail?.outlet?.name || '' }}
          </h3>
          <button @click="showOutletDetailModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div v-if="loadingOutletDetail" class="flex items-center justify-center py-20">
          <div class="text-center">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-600 mb-4"></div>
            <p class="text-gray-600 text-lg font-semibold">Loading outlet detail...</p>
          </div>
        </div>

        <div v-else-if="outletDetail" class="p-6">
          <!-- Summary -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
              <div class="text-sm text-blue-600 font-medium mb-1">Total PR</div>
              <div class="text-2xl font-bold text-blue-800">{{ outletDetail.summary?.total_pr || 0 }}</div>
              <div class="text-sm text-blue-600 mt-1">{{ formatCurrency(outletDetail.summary?.total_pr_amount || 0) }}</div>
            </div>
            <div class="bg-purple-50 rounded-xl p-4 border border-purple-200">
              <div class="text-sm text-purple-600 font-medium mb-1">Total RNF</div>
              <div class="text-2xl font-bold text-purple-800">{{ outletDetail.summary?.total_rnf || 0 }}</div>
              <div class="text-sm text-purple-600 mt-1">{{ formatCurrency(outletDetail.summary?.total_rnf_amount || 0) }}</div>
            </div>
            <div class="bg-yellow-50 rounded-xl p-4 border border-yellow-200">
              <div class="text-sm text-yellow-600 font-medium mb-1">Grand Total</div>
              <div class="text-2xl font-bold text-yellow-800">{{ formatCurrency(outletDetail.summary?.grand_total || 0) }}</div>
            </div>
          </div>

          <!-- Categories -->
          <div class="space-y-4">
            <div v-for="category in outletDetail.categories" :key="category.category_id" 
              class="bg-white border border-gray-200 rounded-lg overflow-hidden">
              <!-- Category Header -->
              <div class="bg-gradient-to-r from-purple-50 to-indigo-50 px-4 py-3 cursor-pointer hover:from-purple-100 hover:to-indigo-100 transition-colors"
                @click="toggleCategoryExpand(category.category_id)">
                <div class="flex justify-between items-center">
                  <div class="flex items-center gap-3">
                    <i class="fa-solid" :class="expandedCategories.has(category.category_id) ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                    <div>
                      <div class="font-bold text-gray-900">{{ category.display_name }}</div>
                      <div class="text-sm text-gray-600">
                        PR: {{ category.pr_count }} | RNF: {{ category.rnf_count }}
                      </div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-lg font-bold text-gray-900">{{ formatCurrency(category.total) }}</div>
                    <div class="text-xs text-gray-500">
                      PR: {{ formatCurrency(category.pr_total) }} | RNF: {{ formatCurrency(category.rnf_total) }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Category Content (Expandable) -->
              <div v-if="expandedCategories.has(category.category_id)" class="p-4 space-y-4">
                <!-- Purchase Requisitions -->
                <div v-if="category.purchase_requisitions && category.purchase_requisitions.length > 0">
                  <h5 class="text-md font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice text-blue-600"></i>
                    Purchase Requisitions ({{ category.pr_count }})
                  </h5>
                  <div class="space-y-2 ml-4">
                    <div v-for="pr in category.purchase_requisitions" :key="pr.id" 
                      class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                      <div class="flex justify-between items-start mb-2">
                        <div class="flex-1">
                          <Link :href="route('purchase-requisitions.show', pr.id)" class="text-blue-600 hover:underline font-semibold">
                            {{ pr.pr_number }}
                          </Link>
                          <div class="text-xs text-gray-600 mt-1">{{ formatDate(pr.date) }}</div>
                          <div v-if="pr.creator" class="flex items-center gap-2 mt-2">
                            <img v-if="pr.creator.avatar" :src="pr.creator.avatar" :alt="pr.creator.name" 
                              class="w-5 h-5 rounded-full object-cover">
                            <div v-else class="w-5 h-5 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600">
                              {{ pr.creator.name.charAt(0).toUpperCase() }}
                            </div>
                            <span class="text-xs text-gray-600">{{ pr.creator.name }}</span>
                          </div>
                          <div v-if="pr.approval && pr.approval.approver" class="flex items-center gap-2 mt-2">
                            <i class="fa-solid fa-check-circle text-green-600 text-xs"></i>
                            <img v-if="pr.approval.approver.avatar" :src="pr.approval.approver.avatar" :alt="pr.approval.approver.name" 
                              class="w-5 h-5 rounded-full object-cover">
                            <div v-else class="w-5 h-5 rounded-full bg-green-300 flex items-center justify-center text-xs text-green-700">
                              {{ pr.approval.approver.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="flex flex-col">
                              <span class="text-xs text-gray-600 font-medium">{{ pr.approval.approver.name }}</span>
                              <span v-if="pr.approval.approved_at" class="text-xs text-gray-500">{{ formatDateTime(pr.approval.approved_at) }}</span>
                            </div>
                          </div>
                        </div>
                        <div class="text-right">
                          <span class="px-2 py-1 rounded-full text-xs font-semibold" :class="getStatusClass(pr.status)">
                            {{ pr.status }}
                          </span>
                          <div v-if="pr.approval" class="mt-1">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold" 
                              :class="pr.approval.status === 'APPROVED' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                              {{ pr.approval.status }}
                            </span>
                          </div>
                          <div class="text-sm font-bold text-gray-900 mt-1">{{ formatCurrency(pr.total_amount) }}</div>
                        </div>
                      </div>
                      <div v-if="pr.items && pr.items.length > 0" class="mt-2 pt-2 border-t border-gray-200">
                        <div class="text-xs text-gray-500 mb-1">Items:</div>
                        <div class="space-y-1">
                          <div v-for="(item, idx) in pr.items" :key="idx" class="text-xs text-gray-700 flex justify-between">
                            <span>{{ item.item_name }} ({{ item.qty }}x)</span>
                            <span class="font-semibold">{{ formatCurrency(item.subtotal) }}</span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Retail Non Food -->
                <div v-if="category.retail_non_food && category.retail_non_food.length > 0">
                  <h5 class="text-md font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-shopping-bag text-purple-600"></i>
                    Retail Non Food ({{ category.rnf_count }})
                  </h5>
                  <div class="space-y-2 ml-4">
                    <div v-for="rnf in category.retail_non_food" :key="rnf.id" 
                      class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                      <div class="flex justify-between items-start mb-2">
                        <div class="flex-1">
                          <div class="font-semibold text-gray-900">{{ rnf.retail_number }}</div>
                          <div class="text-xs text-gray-600 mt-1">{{ formatDate(rnf.date) }} | {{ rnf.supplier_name }}</div>
                          <div v-if="rnf.notes" class="text-xs text-gray-500 mt-1 italic">{{ rnf.notes }}</div>
                          <div v-if="rnf.creator" class="flex items-center gap-2 mt-2">
                            <img v-if="rnf.creator.avatar" :src="rnf.creator.avatar" :alt="rnf.creator.name" 
                              class="w-5 h-5 rounded-full object-cover">
                            <div v-else class="w-5 h-5 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600">
                              {{ rnf.creator.name.charAt(0).toUpperCase() }}
                            </div>
                            <span class="text-xs text-gray-600">{{ rnf.creator.name }}</span>
                          </div>
                        </div>
                        <div class="text-sm font-bold text-gray-900">{{ formatCurrency(rnf.total_amount) }}</div>
                      </div>
                      <div v-if="rnf.items && rnf.items.length > 0" class="mt-2 pt-2 border-t border-gray-200">
                        <div class="text-xs text-gray-500 mb-1 font-semibold">Items:</div>
                        <div class="overflow-x-auto">
                          <table class="w-full text-xs">
                            <thead>
                              <tr class="bg-gray-100">
                                <th class="px-2 py-1 text-left text-xs font-semibold text-gray-600">Item</th>
                                <th class="px-2 py-1 text-right text-xs font-semibold text-gray-600">Qty</th>
                                <th class="px-2 py-1 text-right text-xs font-semibold text-gray-600">Unit</th>
                                <th class="px-2 py-1 text-right text-xs font-semibold text-gray-600">Price</th>
                                <th class="px-2 py-1 text-right text-xs font-semibold text-gray-600">Subtotal</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr v-for="(item, idx) in rnf.items" :key="idx" class="border-b border-gray-100">
                                <td class="px-2 py-1 text-gray-700">{{ item.item_name }}</td>
                                <td class="px-2 py-1 text-right text-gray-600">{{ item.qty }}</td>
                                <td class="px-2 py-1 text-right text-gray-600">{{ item.unit || '-' }}</td>
                                <td class="px-2 py-1 text-right text-gray-700">{{ formatCurrency(item.price) }}</td>
                                <td class="px-2 py-1 text-right font-semibold text-gray-900">{{ formatCurrency(item.subtotal) }}</td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="(!category.purchase_requisitions || category.purchase_requisitions.length === 0) && 
                          (!category.retail_non_food || category.retail_non_food.length === 0)" 
                  class="text-center py-4 text-gray-500 text-sm">
                  No data for this category
                </div>
              </div>
            </div>
            <div v-if="!outletDetail.categories || outletDetail.categories.length === 0" 
              class="text-center py-8 text-gray-500">
              No categories found
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Division Detail Modal -->
    <div v-if="showDivisionDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4" @click.self="showDivisionDetailModal = false">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-6xl max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
          <h3 class="text-2xl font-bold text-gray-800">
            <i class="fa-solid fa-sitemap text-green-600 mr-2"></i>
            Division Detail: {{ divisionDetail?.division?.name || '' }}
          </h3>
          <button @click="showDivisionDetailModal = false" class="text-gray-400 hover:text-gray-600 text-2xl">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div v-if="loadingDivisionDetail" class="flex items-center justify-center py-20">
          <div class="text-center">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-600 mb-4"></div>
            <p class="text-gray-600 text-lg font-semibold">Loading division detail...</p>
          </div>
        </div>

        <div v-else-if="divisionDetail" class="p-6">
          <!-- Summary -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-blue-50 rounded-xl p-4 border border-blue-200">
              <div class="text-sm text-blue-600 font-medium mb-1">Total PR</div>
              <div class="text-2xl font-bold text-blue-800">{{ divisionDetail.summary?.pr_count || 0 }}</div>
            </div>
            <div class="bg-green-50 rounded-xl p-4 border border-green-200">
              <div class="text-sm text-green-600 font-medium mb-1">Total Value</div>
              <div class="text-xl font-bold text-green-800">{{ formatCurrency(divisionDetail.summary?.pr_total || 0) }}</div>
            </div>
          </div>

          <!-- Purchase Requisitions -->
          <div>
            <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
              <i class="fa-solid fa-file-invoice text-blue-600"></i>
              Purchase Requisitions ({{ divisionDetail.purchase_requisitions?.length || 0 }})
            </h4>
            <div class="space-y-3">
              <div v-for="pr in divisionDetail.purchase_requisitions" :key="pr.id" 
                class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-2">
                  <div class="flex-1">
                    <Link :href="route('purchase-requisitions.show', pr.id)" class="text-blue-600 hover:underline font-semibold">
                      {{ pr.pr_number }}
                    </Link>
                    <div class="text-sm text-gray-600 mt-1">{{ formatDate(pr.date) }}</div>
                    <div v-if="pr.description" class="text-sm text-gray-500 mt-1 italic">{{ pr.description }}</div>
                    <div v-if="pr.creator" class="flex items-center gap-2 mt-2">
                      <img v-if="pr.creator.avatar" :src="pr.creator.avatar" :alt="pr.creator.name" 
                        class="w-6 h-6 rounded-full object-cover">
                      <div v-else class="w-6 h-6 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600">
                        {{ pr.creator.name.charAt(0).toUpperCase() }}
                      </div>
                      <span class="text-xs text-gray-600">{{ pr.creator.name }}</span>
                    </div>
                    <div v-if="pr.approval && pr.approval.approver" class="flex items-center gap-2 mt-2">
                      <i class="fa-solid fa-check-circle text-green-600 text-xs"></i>
                      <img v-if="pr.approval.approver.avatar" :src="pr.approval.approver.avatar" :alt="pr.approval.approver.name" 
                        class="w-6 h-6 rounded-full object-cover">
                      <div v-else class="w-6 h-6 rounded-full bg-green-300 flex items-center justify-center text-xs text-green-700">
                        {{ pr.approval.approver.name.charAt(0).toUpperCase() }}
                      </div>
                      <div class="flex flex-col">
                        <span class="text-xs text-gray-600 font-medium">{{ pr.approval.approver.name }}</span>
                        <span v-if="pr.approval.approved_at" class="text-xs text-gray-500">{{ formatDateTime(pr.approval.approved_at) }}</span>
                      </div>
                    </div>
                  </div>
                  <div class="text-right">
                    <span class="px-2 py-1 rounded-full text-xs font-semibold" :class="getStatusClass(pr.status)">
                      {{ pr.status }}
                    </span>
                    <div v-if="pr.approval" class="mt-1">
                      <span class="px-2 py-1 rounded-full text-xs font-semibold" 
                        :class="pr.approval.status === 'APPROVED' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                        {{ pr.approval.status }}
                      </span>
                    </div>
                    <div class="text-lg font-bold text-gray-900 mt-1">{{ formatCurrency(pr.total_amount) }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ pr.items_count }} items</div>
                  </div>
                </div>
                <div v-if="pr.items && pr.items.length > 0" class="mt-3 pt-3 border-t border-gray-200">
                  <div class="flex justify-between items-center mb-2 cursor-pointer" @click="toggleDivisionPRExpand(pr.id)">
                    <div class="text-xs text-gray-500 font-semibold">Items ({{ pr.items.length }})</div>
                    <i class="fa-solid text-xs text-gray-500" :class="expandedDivisionPRs.has(pr.id) ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                  </div>
                  <div v-if="expandedDivisionPRs.has(pr.id)" class="mt-2">
                    <div class="overflow-x-auto">
                      <table class="w-full text-sm">
                        <thead>
                          <tr class="bg-gray-50">
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">Item</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Qty</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Unit Price</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">Subtotal</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="(item, idx) in pr.items" :key="idx" class="border-b border-gray-100">
                            <td class="px-3 py-2 text-gray-700">{{ item.item_name }}</td>
                            <td class="px-3 py-2 text-right text-gray-600">{{ item.qty }}</td>
                            <td class="px-3 py-2 text-right text-gray-700">{{ formatCurrency(item.unit_price) }}</td>
                            <td class="px-3 py-2 text-right font-semibold text-gray-900">{{ formatCurrency(item.subtotal) }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <div v-if="!divisionDetail.purchase_requisitions || divisionDetail.purchase_requisitions.length === 0" 
                class="text-center py-8 text-gray-500">
                No Purchase Requisitions
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import axios from 'axios';

const props = defineProps({
  summary: Object,
  statusDistribution: Array,
  trendData: Array,
  categoryAnalysis: Object,
  outletAnalysis: Object,
  divisionAnalysis: Array,
  itemAnalysis: Object,
  purchaseRequisitions: Object,
  prPerOutlet: Array,
  prPerCategory: Array,
  categories: Array,
  outlets: Array,
  divisions: Array,
  filters: Object,
});

const loading = ref(false);
const showCategoryDetailModal = ref(false);
const categoryDetail = ref(null);
const loadingCategoryDetail = ref(false);
const categoryPRSearch = ref('');
const categoryPRPerPage = ref(10);
const currentCategoryId = ref(null);
const showOutletDetailModal = ref(false);
const outletDetail = ref(null);
const loadingOutletDetail = ref(false);
const showDivisionDetailModal = ref(false);
const divisionDetail = ref(null);
const loadingDivisionDetail = ref(false);
const expandedCategories = ref(new Set());
const expandedPRs = ref(new Set());
const expandedDivisionPRs = ref(new Set());

const filters = reactive({
  date_from: props.filters.date_from || new Date().toISOString().split('T')[0],
  date_to: props.filters.date_to || new Date().toISOString().split('T')[0],
  status: props.filters.status || 'all',
  category_id: props.filters.category_id || '',
  outlet_id: props.filters.outlet_id || '',
  division_id: props.filters.division_id || '',
  search: props.filters.search || '',
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

function formatDateTime(dateTime) {
  if (!dateTime) return '-';
  return new Date(dateTime).toLocaleString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function getStatusClass(status) {
  const classes = {
    'DRAFT': 'bg-gray-100 text-gray-800',
    'SUBMITTED': 'bg-yellow-100 text-yellow-800',
    'APPROVED': 'bg-green-100 text-green-800',
    'REJECTED': 'bg-red-100 text-red-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

function applyFilters() {
  loading.value = true;
  router.get(route('pr-ops.report'), filters, {
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

function exportReport() {
  const params = new URLSearchParams(filters);
  window.location.href = route('pr-ops.report.export') + '?' + params.toString();
}

function loadPage(page) {
  loading.value = true;
  router.get(route('pr-ops.report'), { ...filters, page }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    }
  });
}

function loadCategoryPage(page) {
  loading.value = true;
  router.get(route('pr-ops.report'), { ...filters, category_page: page }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    }
  });
}

function loadOutletPage(page) {
  loading.value = true;
  router.get(route('pr-ops.report'), { ...filters, outlet_page: page }, {
    preserveState: true,
    preserveScroll: true,
    onFinish: () => {
      loading.value = false;
    }
  });
}

async function showCategoryDetail(categoryId) {
  loadingCategoryDetail.value = true;
  showCategoryDetailModal.value = true;
  categoryDetail.value = null;
  categoryPRSearch.value = '';
  categoryPRPerPage.value = 10;
  currentCategoryId.value = categoryId;
  
  try {
    const response = await axios.get(route('pr-ops.report.category-detail', categoryId), {
      params: {
        date_from: filters.date_from,
        date_to: filters.date_to,
        status: filters.status,
        search: categoryPRSearch.value,
        per_page: categoryPRPerPage.value,
        page: 1,
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

async function loadCategoryPRPage(page) {
  if (!currentCategoryId.value) return;
  
  loadingCategoryDetail.value = true;
  
  try {
    const response = await axios.get(route('pr-ops.report.category-detail', currentCategoryId.value), {
      params: {
        date_from: filters.date_from,
        date_to: filters.date_to,
        status: filters.status,
        search: categoryPRSearch.value,
        per_page: categoryPRPerPage.value,
        page: page,
      }
    });
    
    if (response.data.success) {
      categoryDetail.value = response.data;
    }
  } catch (error) {
    console.error('Error fetching category detail page:', error);
    alert('Terjadi kesalahan saat mengambil data');
  } finally {
    loadingCategoryDetail.value = false;
  }
}

async function showOutletDetail(outletId) {
  loadingOutletDetail.value = true;
  showOutletDetailModal.value = true;
  outletDetail.value = null;
  expandedCategories.value.clear();
  expandedPRs.value.clear();
  
  try {
    const response = await axios.get(route('pr-ops.report.outlet-detail', outletId), {
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

function toggleCategoryExpand(categoryId) {
  if (expandedCategories.value.has(categoryId)) {
    expandedCategories.value.delete(categoryId);
  } else {
    expandedCategories.value.add(categoryId);
  }
}

function togglePRExpand(prId) {
  if (expandedPRs.value.has(prId)) {
    expandedPRs.value.delete(prId);
  } else {
    expandedPRs.value.add(prId);
  }
}

async function showDivisionDetail(divisionId) {
  loadingDivisionDetail.value = true;
  showDivisionDetailModal.value = true;
  divisionDetail.value = null;
  expandedDivisionPRs.value.clear();
  
  try {
    const response = await axios.get(route('pr-ops.report.division-detail', divisionId), {
      params: {
        date_from: filters.date_from,
        date_to: filters.date_to,
        status: filters.status,
      }
    });
    
    if (response.data.success) {
      divisionDetail.value = response.data;
    } else {
      alert('Gagal mengambil data division detail');
    }
  } catch (error) {
    console.error('Error fetching division detail:', error);
    alert('Terjadi kesalahan saat mengambil data division detail');
  } finally {
    loadingDivisionDetail.value = false;
  }
}

function toggleDivisionPRExpand(prId) {
  if (expandedDivisionPRs.value.has(prId)) {
    expandedDivisionPRs.value.delete(prId);
  } else {
    expandedDivisionPRs.value.add(prId);
  }
}

const statusChartSeries = computed(() => {
  return props.statusDistribution.map(s => s.count);
});

const statusChartOptions = computed(() => {
  return {
    chart: {
      type: 'donut',
    },
    labels: props.statusDistribution.map(s => s.status),
    legend: {
      position: 'bottom',
    },
    colors: ['#9CA3AF', '#FBBF24', '#10B981', '#EF4444'],
  };
});

const trendChartSeries = computed(() => {
  return [
    {
      name: 'PR Value',
      type: 'line',
      data: props.trendData.map(d => d.pr_value || 0),
    },
    {
      name: 'RNF Value',
      type: 'line',
      data: props.trendData.map(d => d.rnf_value || 0),
    }
  ];
});

const trendChartOptions = computed(() => {
  return {
    chart: {
      type: 'line',
      toolbar: {
        show: true,
      },
    },
    stroke: {
      width: [3, 3],
      curve: 'smooth',
    },
    labels: props.trendData.map(d => formatDate(d.date)),
    xaxis: {
      type: 'category',
    },
    yaxis: {
      title: {
        text: 'Value (Rp)',
      },
      labels: {
        formatter: function(val) {
          return formatCurrency(val);
        }
      }
    },
    tooltip: {
      shared: true,
      intersect: false,
      y: {
        formatter: function(val) {
          return formatCurrency(val);
        }
      }
    },
    legend: {
      position: 'top',
    },
    fill: {
      opacity: [1, 1],
    },
    colors: ['#3B82F6', '#8B5CF6'], // Blue for PR Value, Purple for RNF Value
    markers: {
      size: [5, 5],
      hover: {
        size: [7, 7]
      }
    }
  };
});

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

