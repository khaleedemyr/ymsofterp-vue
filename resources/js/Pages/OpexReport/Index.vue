<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-chart-line"></i> OPEX Report per Outlet
        </h1>
        <div class="flex gap-2 items-center">
          <button @click="exportReport" class="bg-gradient-to-r from-green-500 to-green-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            <i class="fa fa-download mr-2"></i> Export
          </button>
        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-blue-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Outlets</p>
              <p class="text-2xl font-bold text-gray-900">{{ summary.total_outlets }}</p>
            </div>
            <i class="fa fa-building text-blue-500 text-2xl"></i>
          </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-green-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Amount</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(summary.total_amount) }}</p>
            </div>
            <i class="fa fa-money-bill-wave text-green-500 text-2xl"></i>
          </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-yellow-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Paid Amount</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(summary.paid_amount) }}</p>
            </div>
            <i class="fa fa-check-circle text-yellow-500 text-2xl"></i>
          </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-red-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Unpaid Amount</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(summary.unpaid_amount) }}</p>
            </div>
            <i class="fa fa-exclamation-circle text-red-500 text-2xl"></i>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
            <input 
              type="date" 
              v-model="filters.date_from" 
              @change="onFilterChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
            <input 
              type="date" 
              v-model="filters.date_to" 
              @change="onFilterChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
            <select 
              v-model="filters.outlet_id" 
              @change="onFilterChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Outlets</option>
              <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select 
              v-model="filters.category_id" 
              @change="onFilterChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Categories</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select 
              v-model="filters.status" 
              @change="onFilterChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Status</option>
              <option value="paid">Paid</option>
              <option value="unpaid">Unpaid</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Hierarchical Report -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="p-4 border-b border-gray-200">
          <h2 class="text-lg font-semibold text-gray-800">OPEX Report by Outlet</h2>
        </div>
        
        <div class="divide-y divide-gray-200">
          <div v-if="!outletData || !outletData.length" class="p-8 text-center text-gray-500">
            <i class="fa fa-inbox text-4xl mb-4"></i>
            <p>No data available for the selected filters.</p>
          </div>
          
          <div v-for="outlet in outletData" :key="outlet.outlet_id" class="p-4">
            <!-- Outlet Level -->
            <div class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-3 rounded-lg" @click="toggleOutlet(outlet.outlet_id)">
              <div class="flex items-center gap-3">
                <i :class="expandedOutlets.includes(outlet.outlet_id) ? 'fa fa-chevron-down' : 'fa fa-chevron-right'" class="text-gray-400"></i>
                <div class="flex items-center gap-2">
                  <i class="fa fa-building text-blue-500"></i>
                  <span class="font-semibold text-gray-900">{{ outlet.outlet_name }}</span>
                </div>
              </div>
              <div class="flex items-center gap-4">
                <div class="text-right">
                  <div class="text-sm font-medium text-gray-900">{{ formatCurrency(outlet.total_amount) }}</div>
                  <div class="text-xs text-gray-500">
                    <span class="text-green-600">{{ formatCurrency(outlet.paid_amount) }} paid</span> • 
                    <span class="text-red-600">{{ formatCurrency(outlet.unpaid_amount) }} unpaid</span>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium">
                    {{ outlet.categories.length }} categories
                  </span>
                </div>
              </div>
            </div>

            <!-- Categories Level -->
            <div v-if="expandedOutlets.includes(outlet.outlet_id)" class="ml-8 mt-2 space-y-2">
              <div v-for="category in outlet.categories" :key="category.category_id" class="border-l-2 border-gray-200 pl-4">
                <div class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-2 rounded-lg" @click="toggleCategory(outlet.outlet_id, category.category_id)">
                  <div class="flex items-center gap-3">
                    <i :class="expandedCategories.includes(`${outlet.outlet_id}-${category.category_id}`) ? 'fa fa-chevron-down' : 'fa fa-chevron-right'" class="text-gray-400"></i>
                    <div class="flex items-center gap-2">
                      <i class="fa fa-tags text-green-500"></i>
                      <span class="font-medium text-gray-800">{{ category.category_name }}</span>
                      <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">{{ category.category_division }}</span>
                    </div>
                  </div>
                  <div class="flex items-center gap-4">
                    <div class="text-right">
                      <div class="text-sm font-medium text-gray-900">{{ formatCurrency(category.total_amount) }}</div>
                      <div class="text-xs text-gray-500">
                        <span class="text-green-600">{{ formatCurrency(category.paid_amount) }} paid</span> • 
                        <span class="text-red-600">{{ formatCurrency(category.unpaid_amount) }} unpaid</span>
                      </div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs font-medium">
                        {{ category.purchase_orders.length }} POs
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Purchase Orders Level -->
                <div v-if="expandedCategories.includes(`${outlet.outlet_id}-${category.category_id}`)" class="ml-8 mt-2 space-y-2">
                  <div v-for="po in category.purchase_orders" :key="po.po_id" class="border-l-2 border-gray-200 pl-4">
                    <div class="flex items-center justify-between cursor-pointer hover:bg-gray-50 p-2 rounded-lg" @click="togglePO(outlet.outlet_id, category.category_id, po.po_id)">
                      <div class="flex items-center gap-3">
                        <i :class="expandedPOs.includes(`${outlet.outlet_id}-${category.category_id}-${po.po_id}`) ? 'fa fa-chevron-down' : 'fa fa-chevron-right'" class="text-gray-400"></i>
                        <div class="flex items-center gap-2">
                          <i class="fa fa-file-invoice text-purple-500"></i>
                          <span class="font-medium text-gray-800">{{ po.po_number }}</span>
                          <span class="text-sm text-gray-500">{{ formatDate(po.po_date) }}</span>
                        </div>
                        <div class="flex items-center gap-1">
                          <span :class="po.is_paid ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="px-2 py-1 rounded-full text-xs font-medium">
                            {{ po.is_paid ? 'Paid' : 'Unpaid' }}
                          </span>
                          <span v-if="po.payment_number" class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                            {{ po.payment_number }}
                          </span>
                        </div>
                      </div>
                      <div class="flex items-center gap-4">
                        <div class="text-right">
                          <div class="text-sm font-medium text-gray-900">{{ formatCurrency(po.total_amount) }}</div>
                          <div class="text-xs text-gray-500">
                            <span class="text-green-600">{{ formatCurrency(po.paid_amount || 0) }} paid</span>
                            <span v-if="(po.paid_amount || 0) > 0 && (po.unpaid_amount || 0) > 0"> • </span>
                            <span v-if="(po.unpaid_amount || 0) > 0" class="text-red-600">{{ formatCurrency(po.unpaid_amount) }} unpaid</span>
                          </div>
                          <div class="text-xs text-gray-500 mt-1">{{ po.supplier_name }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                          <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded-full text-xs font-medium">
                            {{ po.items.length }} items
                          </span>
                        </div>
                      </div>
                    </div>

                    <!-- Items Level -->
                    <div v-if="expandedPOs.includes(`${outlet.outlet_id}-${category.category_id}-${po.po_id}`)" class="ml-8 mt-2">
                      <div class="bg-gray-50 rounded-lg p-3">
                        <div class="overflow-x-auto">
                          <table class="w-full text-sm">
                            <thead class="bg-gray-100">
                              <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Item</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Qty</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Unit</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-700">Price</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-700">Total</th>
                              </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                              <tr v-for="item in po.items" :key="item.po_item_id">
                                <td class="px-3 py-2 font-medium text-gray-900">{{ item.item_name }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ item.quantity }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ item.unit }}</td>
                                <td class="px-3 py-2 text-right text-gray-700">{{ formatCurrency(item.price) }}</td>
                                <td class="px-3 py-2 text-right font-medium text-gray-900">{{ formatCurrency(item.total) }}</td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>
            </div>

          </div>
        </div>

        <!-- Budget Information Section (Non-expandable) -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden mt-6">
          <div class="p-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-800">Budget Information by Category</h2>
          </div>
          
          <div class="p-4">
            <div v-if="!outletData || !outletData.length" class="text-center py-8 text-gray-500">
              <i class="fa fa-inbox text-4xl mb-4"></i>
              <p>No data available for the selected filters.</p>
            </div>
            
            <!-- Categories with budget information -->
            <div v-else class="space-y-4">
              <div v-for="categoryGroup in allCategories" :key="categoryGroup.category_id" class="border border-gray-200 rounded-lg">
                <!-- Category Header -->
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-t-lg">
                  <div class="flex items-center gap-3">
                    <div class="flex items-center gap-2">
                      <i class="fa fa-tags text-green-500"></i>
                      <span class="font-semibold text-gray-800">{{ categoryGroup.category_name }}</span>
                      <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">{{ categoryGroup.category_division }}</span>
                      <span v-if="categoryGroup.budget_type" :class="categoryGroup.budget_type === 'GLOBAL' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'" class="px-2 py-1 rounded-full text-xs font-medium">
                        {{ categoryGroup.budget_type === 'GLOBAL' ? 'Global Budget' : 'Per Outlet Budget' }}
                      </span>
                    </div>
                  </div>
                  <div class="flex items-center gap-4">
                    <div class="text-right">
                      <div class="text-sm font-medium text-gray-900">{{ formatCurrency(categoryGroup.total_budget_limit) }}</div>
                      <div class="text-xs text-gray-500">Total Budget Limit</div>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs font-medium">
                        {{ categoryGroup.outlets.length }} outlets
                      </span>
                      <!-- Expand button only for per outlet budget type -->
                      <button v-if="categoryGroup.budget_type === 'PER_OUTLET'" @click="togglePerOutletCategory(categoryGroup.category_id)" class="p-1 hover:bg-gray-200 rounded">
                        <i :class="expandedPerOutletCategories.includes(categoryGroup.category_id) ? 'fa fa-chevron-down' : 'fa fa-chevron-right'" class="text-gray-400"></i>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Budget Information (always visible) -->
                <div class="p-4">
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Budget Limit -->
                    <div class="bg-blue-50 rounded-lg p-3">
                      <div class="text-xs text-gray-500 mb-1">Budget Limit</div>
                      <div class="text-lg font-bold text-blue-600">{{ formatCurrency(categoryGroup.total_budget_limit) }}</div>
                    </div>
                    
                    <!-- Paid Amount -->
                    <div class="bg-green-50 rounded-lg p-3 relative">
                      <div class="flex items-center justify-between mb-1">
                        <div class="text-xs text-gray-500">Paid Amount</div>
                        <button 
                          v-if="categoryGroup.transactions && categoryGroup.transactions.paid && categoryGroup.transactions.paid.length > 0"
                          @click="toggleBudgetCard(categoryGroup.category_id, 'paid')"
                          class="text-gray-400 hover:text-gray-600 transition-colors"
                        >
                          <i :class="isBudgetCardExpanded(categoryGroup.category_id, 'paid') ? 'fa fa-chevron-up' : 'fa fa-chevron-down'" class="text-xs"></i>
                        </button>
                      </div>
                      <div class="text-lg font-bold text-green-600">{{ formatCurrency(categoryGroup.total_paid_amount) }}</div>
                      <div class="text-xs text-gray-500">{{ getPercentage(categoryGroup.total_paid_amount, categoryGroup.budget_limit) }}% of budget</div>
                      
                      <!-- Transaction Details -->
                      <div v-if="isBudgetCardExpanded(categoryGroup.category_id, 'paid') && categoryGroup.transactions && categoryGroup.transactions.paid" class="mt-3 pt-3 border-t border-green-200">
                        <div class="text-xs font-medium text-gray-700 mb-2">Transaction Details:</div>
                        <div class="max-h-60 overflow-y-auto space-y-2">
                          <div v-for="(transaction, idx) in categoryGroup.transactions.paid" :key="idx" class="bg-white rounded p-2 text-xs">
                            <div class="flex items-center justify-between">
                              <div class="flex items-center gap-2 flex-wrap">
                                <span :class="transaction.type === 'retail_non_food' ? 'bg-teal-100 text-teal-800' : 'bg-blue-100 text-blue-800'" class="px-2 py-0.5 rounded text-xs font-medium">
                                  {{ transaction.type === 'retail_non_food' ? 'RNF' : 'PR' }}
                                </span>
                                <span class="font-medium">{{ transaction.number }}</span>
                                <span v-if="transaction.payment_number" class="text-gray-500 text-xs">
                                  (Payment: {{ transaction.payment_number }})
                                </span>
                              </div>
                              <span class="font-bold text-green-600">{{ formatCurrency(transaction.amount) }}</span>
                            </div>
                            <div class="text-gray-500 mt-1">
                              {{ transaction.outlet }} • {{ formatDate(transaction.date) }}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Unpaid Amount -->
                    <div class="bg-red-50 rounded-lg p-3 relative">
                      <div class="flex items-center justify-between mb-1">
                        <div class="text-xs text-gray-500">Unpaid Amount</div>
                        <button 
                          v-if="categoryGroup.transactions && categoryGroup.transactions.unpaid && categoryGroup.transactions.unpaid.length > 0"
                          @click="toggleBudgetCard(categoryGroup.category_id, 'unpaid')"
                          class="text-gray-400 hover:text-gray-600 transition-colors"
                        >
                          <i :class="isBudgetCardExpanded(categoryGroup.category_id, 'unpaid') ? 'fa fa-chevron-up' : 'fa fa-chevron-down'" class="text-xs"></i>
                        </button>
                      </div>
                      <div class="text-lg font-bold text-red-600">{{ formatCurrency(categoryGroup.total_unpaid_amount) }}</div>
                      <div class="text-xs text-gray-500">{{ getPercentage(categoryGroup.total_unpaid_amount, categoryGroup.budget_limit) }}% of budget</div>
                      
                      <!-- Transaction Details -->
                      <div v-if="isBudgetCardExpanded(categoryGroup.category_id, 'unpaid') && categoryGroup.transactions && categoryGroup.transactions.unpaid" class="mt-3 pt-3 border-t border-red-200">
                        <div class="text-xs font-medium text-gray-700 mb-2">Transaction Details:</div>
                        <div class="max-h-60 overflow-y-auto space-y-2">
                          <div v-for="(transaction, idx) in categoryGroup.transactions.unpaid" :key="idx" class="bg-white rounded p-2 text-xs">
                            <div class="flex items-center justify-between">
                              <div class="flex items-center gap-2">
                                <span class="bg-blue-100 text-blue-800 px-2 py-0.5 rounded text-xs font-medium">PR</span>
                                <span class="font-medium">{{ transaction.number }}</span>
                                <span v-if="transaction.po_numbers" class="text-gray-400">•</span>
                                <span v-if="transaction.po_numbers" class="bg-green-100 text-green-800 px-2 py-0.5 rounded text-xs font-medium">PO: {{ transaction.po_numbers }}</span>
                              </div>
                              <span class="font-bold text-red-600">{{ formatCurrency(transaction.amount) }}</span>
                            </div>
                            <div class="text-gray-500 mt-1">
                              {{ transaction.outlet }} • {{ formatDate(transaction.date) }}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                    
                    <!-- Remaining Budget -->
                    <div class="bg-gray-50 rounded-lg p-3">
                      <div class="text-xs text-gray-500 mb-1">Remaining Budget</div>
                      <div class="text-lg font-bold" :class="categoryGroup.total_remaining_budget > 0 ? 'text-green-600' : 'text-red-600'">
                        {{ formatCurrency(categoryGroup.total_remaining_budget) }}
                      </div>
                      <div class="text-xs" :class="categoryGroup.total_remaining_budget > 0 ? 'text-green-600' : 'text-red-600'">
                        {{ categoryGroup.total_remaining_budget > 0 ? 'Available' : 'Exceeded' }}
                      </div>
                    </div>
                  </div>
                  
                  <!-- Budget Breakdown Detail -->
                  <div v-if="categoryGroup.budget_breakdown" class="mt-4 pt-4 border-t border-gray-200">
                    <h5 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                      <i class="fa fa-list-ul text-blue-500"></i>Budget Breakdown Detail
                    </h5>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                      <div class="p-3 bg-white rounded-lg border border-blue-100 shadow-sm">
                        <p class="text-blue-600 font-medium text-xs mb-1">PR Unpaid</p>
                        <p class="text-base font-bold text-blue-800">{{ formatCurrency(categoryGroup.budget_breakdown.pr_unpaid || 0) }}</p>
                        <p class="text-xs text-gray-500 mt-1">PR Submitted & Approved<br>yang belum dibuat PO</p>
                      </div>
                      <div class="p-3 bg-white rounded-lg border border-blue-100 shadow-sm">
                        <p class="text-blue-600 font-medium text-xs mb-1">PO Unpaid</p>
                        <p class="text-base font-bold text-blue-800">{{ formatCurrency(categoryGroup.budget_breakdown.po_unpaid || 0) }}</p>
                        <p class="text-xs text-gray-500 mt-1">PO Submitted & Approved<br>yang belum dibuat NFP</p>
                      </div>
                      <div class="p-3 bg-white rounded-lg border border-orange-100 shadow-sm">
                        <p class="text-orange-600 font-medium text-xs mb-1">NFP Submitted</p>
                        <p class="text-base font-bold text-orange-600">{{ formatCurrency(categoryGroup.budget_breakdown.nfp_submitted || 0) }}</p>
                      </div>
                      <div class="p-3 bg-white rounded-lg border border-yellow-100 shadow-sm">
                        <p class="text-yellow-600 font-medium text-xs mb-1">NFP Approved</p>
                        <p class="text-base font-bold text-yellow-600">{{ formatCurrency(categoryGroup.budget_breakdown.nfp_approved || 0) }}</p>
                      </div>
                      <div class="p-3 bg-white rounded-lg border border-green-100 shadow-sm">
                        <p class="text-green-600 font-medium text-xs mb-1">NFP Paid</p>
                        <p class="text-base font-bold text-green-600">{{ formatCurrency(categoryGroup.budget_breakdown.nfp_paid || 0) }}</p>
                      </div>
                      <div class="p-3 bg-white rounded-lg border border-purple-100 shadow-sm">
                        <p class="text-purple-600 font-medium text-xs mb-1">Retail Non Food</p>
                        <p class="text-base font-bold text-purple-600">{{ formatCurrency(categoryGroup.budget_breakdown.retail_non_food || 0) }}</p>
                        <p class="text-xs text-gray-500 mt-1">Status: Approved</p>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Budget Breakdown by Source (Old) -->
                  <div v-if="categoryGroup.budget_breakdown && (categoryGroup.budget_breakdown.nfp_amount || categoryGroup.budget_breakdown.pr_unpaid_amount || categoryGroup.budget_breakdown.rnf_amount)" class="mt-4 pt-4 border-t border-gray-200">
                    <div class="text-xs font-medium text-gray-700 mb-3">Total Penggunaan Budget:</div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                      <!-- NFP Amount -->
                      <div class="bg-blue-50 rounded-lg p-3">
                        <div class="text-xs text-gray-500 mb-1">NFP (Non-Food Payment)</div>
                        <div class="text-base font-bold text-blue-600">{{ formatCurrency(categoryGroup.budget_breakdown.nfp_amount || 0) }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                          {{ getPercentage(categoryGroup.budget_breakdown.nfp_amount || 0, categoryGroup.total_budget_limit) }}% of budget
                        </div>
                      </div>
                      
                      <!-- PR Unpaid Amount -->
                      <div class="bg-orange-50 rounded-lg p-3">
                        <div class="text-xs text-gray-500 mb-1">PR Unpaid</div>
                        <div class="text-base font-bold text-orange-600">{{ formatCurrency(categoryGroup.budget_breakdown.pr_unpaid_amount || 0) }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                          {{ getPercentage(categoryGroup.budget_breakdown.pr_unpaid_amount || 0, categoryGroup.total_budget_limit) }}% of budget
                        </div>
                      </div>
                      
                      <!-- RNF Amount -->
                      <div class="bg-teal-50 rounded-lg p-3">
                        <div class="text-xs text-gray-500 mb-1">RNF (Retail Non-Food)</div>
                        <div class="text-base font-bold text-teal-600">{{ formatCurrency(categoryGroup.budget_breakdown.rnf_amount || 0) }}</div>
                        <div class="text-xs text-gray-500 mt-1">
                          {{ getPercentage(categoryGroup.budget_breakdown.rnf_amount || 0, categoryGroup.total_budget_limit) }}% of budget
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Progress Bar -->
                  <div class="mt-4">
                    <div class="flex justify-between text-xs text-gray-600 mb-1">
                      <span>Budget Usage</span>
                      <span>{{ getPercentage((categoryGroup.total_paid_amount || 0) + (categoryGroup.total_unpaid_amount || 0), categoryGroup.budget_limit) }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                      <div 
                        class="h-2 rounded-full transition-all duration-300"
                        :class="getProgressBarColor((categoryGroup.total_paid_amount || 0) + (categoryGroup.total_unpaid_amount || 0), categoryGroup.budget_limit)"
                        :style="{ width: `${Math.min(getPercentage((categoryGroup.total_paid_amount || 0) + (categoryGroup.total_unpaid_amount || 0), categoryGroup.budget_limit), 100)}%` }"
                      ></div>
                    </div>
                  </div>
                </div>

                <!-- Per Outlet Breakdown (expandable only for per_outlet type) -->
                <div v-if="categoryGroup.budget_type === 'PER_OUTLET' && expandedPerOutletCategories.includes(categoryGroup.category_id)" class="p-4 bg-gray-50 border-t border-gray-200">
                  <h4 class="font-medium text-gray-700 mb-3">Per Outlet Budget Breakdown:</h4>
                  <div class="space-y-3">
                    <div v-for="outlet in categoryGroup.outlets" :key="`${categoryGroup.category_id}-${outlet.outlet_id}`" class="bg-white border border-gray-200 rounded-lg p-4">
                      <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                          <i class="fa fa-building text-blue-500"></i>
                          <span class="font-semibold text-gray-800">{{ outlet.outlet_name }}</span>
                        </div>
                        <div class="text-right">
                          <div class="text-sm font-medium" :class="outlet.remaining_budget >= 0 ? 'text-green-600' : 'text-red-600'">
                            {{ formatCurrency(outlet.remaining_budget) }}
                            <span v-if="outlet.remaining_budget >= 0">remaining</span>
                            <span v-else>exceeded</span>
                          </div>
                          <div class="text-xs text-gray-500">
                            {{ getPercentage((outlet.paid_amount || 0) + (outlet.unpaid_amount || 0), outlet.budget_limit) }}% used
                          </div>
                        </div>
                      </div>
                      
                      <!-- Budget Details Grid -->
                      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Allocated Budget -->
                        <div class="bg-blue-50 rounded-lg p-3">
                          <div class="text-xs text-gray-500 mb-1">Allocated Budget</div>
                          <div class="text-lg font-bold text-blue-600">{{ formatCurrency(outlet.budget_limit) }}</div>
                        </div>
                        
                        <!-- Used Budget -->
                        <div class="bg-green-50 rounded-lg p-3">
                          <div class="text-xs text-gray-500 mb-1">Used Budget</div>
                          <div class="text-lg font-bold text-green-600">{{ formatCurrency((outlet.paid_amount || 0) + (outlet.unpaid_amount || 0)) }}</div>
                          <div class="text-xs text-gray-500">{{ getPercentage((outlet.paid_amount || 0) + (outlet.unpaid_amount || 0), outlet.budget_limit) }}% of allocated</div>
                        </div>
                        
                        <!-- Remaining Budget -->
                        <div class="bg-gray-50 rounded-lg p-3">
                          <div class="text-xs text-gray-500 mb-1">Remaining Budget</div>
                          <div class="text-lg font-bold" :class="outlet.remaining_budget > 0 ? 'text-green-600' : 'text-red-600'">
                            {{ formatCurrency(outlet.remaining_budget) }}
                          </div>
                          <div class="text-xs" :class="outlet.remaining_budget > 0 ? 'text-green-600' : 'text-red-600'">
                            {{ outlet.remaining_budget > 0 ? 'Available' : 'Exceeded' }}
                          </div>
                        </div>
                      </div>
                      
                      <!-- Budget Breakdown Detail per Outlet -->
                      <div v-if="outlet.breakdown" class="mt-4 pt-4 border-t border-gray-200">
                        <h5 class="text-xs font-semibold text-gray-700 mb-3 flex items-center gap-2">
                          <i class="fa fa-list-ul text-blue-500"></i>Budget Breakdown Detail
                        </h5>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                          <div class="p-2 bg-white rounded border border-blue-100">
                            <p class="text-blue-600 font-medium">PR Unpaid</p>
                            <p class="text-sm font-bold text-blue-800">{{ formatCurrency(outlet.breakdown.pr_unpaid || 0) }}</p>
                            <p class="text-xs text-gray-500 mt-1">PR Submitted & Approved<br>yang belum dibuat PO</p>
                          </div>
                          <div class="p-2 bg-white rounded border border-blue-100">
                            <p class="text-blue-600 font-medium">PO Unpaid</p>
                            <p class="text-sm font-bold text-blue-800">{{ formatCurrency(outlet.breakdown.po_unpaid || 0) }}</p>
                            <p class="text-xs text-gray-500 mt-1">PO Submitted & Approved<br>yang belum dibuat NFP</p>
                          </div>
                          <div class="p-2 bg-white rounded border border-orange-100">
                            <p class="text-orange-600 font-medium">NFP Submitted</p>
                            <p class="text-sm font-bold text-orange-600">{{ formatCurrency(outlet.breakdown.nfp_submitted || 0) }}</p>
                          </div>
                          <div class="p-2 bg-white rounded border border-yellow-100">
                            <p class="text-yellow-600 font-medium">NFP Approved</p>
                            <p class="text-sm font-bold text-yellow-600">{{ formatCurrency(outlet.breakdown.nfp_approved || 0) }}</p>
                          </div>
                          <div class="p-2 bg-white rounded border border-green-100">
                            <p class="text-green-600 font-medium">NFP Paid</p>
                            <p class="text-sm font-bold text-green-600">{{ formatCurrency(outlet.breakdown.nfp_paid || 0) }}</p>
                          </div>
                          <div class="p-2 bg-white rounded border border-purple-100">
                            <p class="text-purple-600 font-medium">Retail Non Food</p>
                            <p class="text-sm font-bold text-purple-600">{{ formatCurrency(outlet.breakdown.retail_non_food || 0) }}</p>
                            <p class="text-xs text-gray-500 mt-1">Status: Approved</p>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Progress Bar -->
                      <div class="mt-3">
                        <div class="flex justify-between text-xs text-gray-600 mb-1">
                          <span>Budget Usage</span>
                          <span>{{ getPercentage((outlet.paid_amount || 0) + (outlet.unpaid_amount || 0), outlet.budget_limit) }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                          <div 
                            class="h-2 rounded-full transition-all duration-300"
                            :class="getProgressBarColor((outlet.paid_amount || 0) + (outlet.unpaid_amount || 0), outlet.budget_limit)"
                            :style="{ width: `${Math.min(getPercentage((outlet.paid_amount || 0) + (outlet.unpaid_amount || 0), outlet.budget_limit), 100)}%` }"
                          ></div>
                        </div>
                      </div>
                    </div>
                    
                    <!-- No Outlet Budget Message -->
                    <div v-if="categoryGroup.outlets.length === 0" class="text-center py-8 text-gray-500">
                      <i class="fa fa-building text-4xl mb-4"></i>
                      <p>No outlet budget data available for this category.</p>
                    </div>
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
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  outletData: Array,
  outlets: Array,
  categories: Array,
  allCategories: Array,
  filters: Object,
  summary: Object
});

const expandedOutlets = ref([]);
const expandedCategories = ref([]);
const expandedPOs = ref([]);
const expandedPerOutletCategories = ref([]);
const expandedBudgetCards = ref({});

const filters = reactive({
  date_from: props.filters.date_from || new Date().toISOString().slice(0, 7) + '-01',
  date_to: props.filters.date_to || new Date().toISOString().slice(0, 7) + '-' + new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).getDate(),
  outlet_id: props.filters.outlet_id || '',
  category_id: props.filters.category_id || '',
  status: props.filters.status || ''
});

function toggleOutlet(outletId) {
  const index = expandedOutlets.value.indexOf(outletId);
  if (index > -1) {
    expandedOutlets.value.splice(index, 1);
  } else {
    expandedOutlets.value.push(outletId);
  }
}

function toggleCategory(outletId, categoryId) {
  const key = `${outletId}-${categoryId}`;
  const index = expandedCategories.value.indexOf(key);
  if (index > -1) {
    expandedCategories.value.splice(index, 1);
  } else {
    expandedCategories.value.push(key);
  }
}

function togglePO(outletId, categoryId, poId) {
  const key = `${outletId}-${categoryId}-${poId}`;
  const index = expandedPOs.value.indexOf(key);
  if (index > -1) {
    expandedPOs.value.splice(index, 1);
  } else {
    expandedPOs.value.push(key);
  }
}

function togglePerOutletCategory(categoryId) {
  const index = expandedPerOutletCategories.value.indexOf(categoryId);
  if (index > -1) {
    expandedPerOutletCategories.value.splice(index, 1);
  } else {
    expandedPerOutletCategories.value.push(categoryId);
  }
}

function toggleBudgetCard(categoryId, cardType) {
  const key = `${categoryId}-${cardType}`;
  if (expandedBudgetCards.value[key]) {
    delete expandedBudgetCards.value[key];
  } else {
    expandedBudgetCards.value[key] = true;
  }
}

function isBudgetCardExpanded(categoryId, cardType) {
  const key = `${categoryId}-${cardType}`;
  return !!expandedBudgetCards.value[key];
}

function getPercentage(amount, total) {
  if (!total || total === 0) return '0.0';
  return ((amount / total) * 100).toFixed(1);
}

function getProgressBarColor(amount, total) {
  if (!total || total === 0) return 'bg-gray-500';
  const percentage = (amount / total) * 100;
  if (percentage > 80) return 'bg-red-500';
  if (percentage > 60) return 'bg-yellow-500';
  return 'bg-green-500';
}

function onFilterChange() {
  router.get('/opex-report', filters, {
    preserveState: true,
    replace: true
  });
}

function exportReport() {
  // TODO: Implement export functionality
  console.log('Export report functionality to be implemented');
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}
</script>
