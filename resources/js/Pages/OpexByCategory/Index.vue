<template>
  <AppLayout>
    <div class="w-full py-8 px-0">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-chart-pie"></i> OPEX By Category - Transaction Tracing
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
              <p class="text-sm font-medium text-gray-600">Total Categories</p>
              <p class="text-2xl font-bold text-gray-900">{{ summary.total_categories }}</p>
            </div>
            <i class="fa fa-tags text-blue-500 text-2xl"></i>
          </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-purple-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">RNF Total</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(summary.total_rnf) }}</p>
            </div>
            <i class="fa fa-shopping-cart text-purple-500 text-2xl"></i>
          </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-orange-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">PR Total</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(summary.total_pr) }}</p>
            </div>
            <i class="fa fa-file-invoice text-orange-500 text-2xl"></i>
          </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg p-4 border-l-4 border-green-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">NFP Total</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatCurrency(summary.total_nfp) }}</p>
            </div>
            <i class="fa fa-money-bill-wave text-green-500 text-2xl"></i>
          </div>
        </div>
      </div>

      <!-- Grand Total Card -->
      <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-xl shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm font-medium text-white opacity-90">Grand Total</p>
            <p class="text-3xl font-bold text-white">{{ formatCurrency(summary.grand_total) }}</p>
          </div>
          <i class="fa fa-chart-line text-white text-4xl opacity-80"></i>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
            <select 
              v-model="filters.category_id" 
              @change="onFilterChange"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">All Categories</option>
              <option v-for="category in categories" :key="category.id" :value="category.id">
                {{ category.name }} ({{ category.division }})
              </option>
            </select>
          </div>
          <div class="flex items-end">
            <button 
              @click="resetFilters" 
              class="w-full bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition"
            >
              <i class="fa fa-refresh mr-2"></i> Reset
            </button>
          </div>
        </div>
      </div>

      <!-- Category Data -->
      <div class="space-y-6">
        <div 
          v-for="category in categoryData" 
          :key="category.category_id"
          class="bg-white rounded-xl shadow-lg overflow-hidden"
        >
          <!-- Category Header -->
          <div class="bg-gradient-to-r from-blue-500 to-indigo-600 p-4 text-white">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-xl font-bold">{{ category.category_name }}</h2>
                <p class="text-sm opacity-90">
                  {{ category.category_division }} 
                  <span v-if="category.category_subcategory">- {{ category.category_subcategory }}</span>
                </p>
                <p class="text-xs opacity-75 mt-1">
                  Budget Type: {{ category.budget_type || 'N/A' }} | 
                  Budget Limit: {{ formatCurrency(category.budget_limit) }}
                </p>
              </div>
              <div class="text-right">
                <p class="text-sm opacity-90">Grand Total</p>
                <p class="text-2xl font-bold">{{ formatCurrency(category.totals.grand_total) }}</p>
              </div>
            </div>
          </div>

          <!-- Category Totals -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50">
            <div class="bg-purple-50 rounded-lg p-3 border-l-4 border-purple-500">
              <p class="text-sm font-medium text-gray-600">RNF Total</p>
              <p class="text-xl font-bold text-purple-700">{{ formatCurrency(category.totals.rnf_total) }}</p>
            </div>
            <div class="bg-orange-50 rounded-lg p-3 border-l-4 border-orange-500">
              <p class="text-sm font-medium text-gray-600">PR Total</p>
              <p class="text-xl font-bold text-orange-700">{{ formatCurrency(category.totals.pr_total) }}</p>
            </div>
            <div class="bg-green-50 rounded-lg p-3 border-l-4 border-green-500">
              <p class="text-sm font-medium text-gray-600">NFP Total</p>
              <p class="text-xl font-bold text-green-700">{{ formatCurrency(category.totals.nfp_total) }}</p>
            </div>
          </div>

          <!-- Transactions Tabs -->
          <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
              <button
                @click="activeTab[category.category_id] = 'rnf'"
                :class="[
                  'px-6 py-3 text-sm font-medium border-b-2 transition',
                  activeTab[category.category_id] === 'rnf'
                    ? 'border-purple-500 text-purple-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                ]"
              >
                <i class="fa fa-shopping-cart mr-2"></i>
                Retail Non Food ({{ category.transactions.rnf.length }})
              </button>
              <button
                @click="activeTab[category.category_id] = 'pr'"
                :class="[
                  'px-6 py-3 text-sm font-medium border-b-2 transition',
                  activeTab[category.category_id] === 'pr'
                    ? 'border-orange-500 text-orange-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                ]"
              >
                <i class="fa fa-file-invoice mr-2"></i>
                Purchase Requisition ({{ category.transactions.pr.length }})
              </button>
              <button
                @click="activeTab[category.category_id] = 'nfp'"
                :class="[
                  'px-6 py-3 text-sm font-medium border-b-2 transition',
                  activeTab[category.category_id] === 'nfp'
                    ? 'border-green-500 text-green-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                ]"
              >
                <i class="fa fa-money-bill-wave mr-2"></i>
                Non Food Payment ({{ category.transactions.nfp.length }})
              </button>
            </nav>
          </div>

          <!-- Transactions Content -->
          <div class="p-4">
            <!-- RNF Transactions -->
            <div v-if="activeTab[category.category_id] === 'rnf'">
              <div v-if="category.transactions.rnf.length === 0" class="text-center py-8 text-gray-500">
                <i class="fa fa-inbox text-4xl mb-2"></i>
                <p>No Retail Non Food transactions found</p>
              </div>
              <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-purple-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase w-12"></th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Transaction Number</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Date</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Outlet</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Amount</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <template v-for="txn in category.transactions.rnf" :key="txn.id">
                      <tr class="hover:bg-gray-50 cursor-pointer" @click="toggleTransaction(txn.id, 'rnf')">
                        <td class="px-4 py-3 text-sm">
                          <i :class="isExpanded(txn.id, 'rnf') ? 'fa fa-chevron-down' : 'fa fa-chevron-right'" class="text-gray-400"></i>
                        </td>
                        <td class="px-4 py-3 text-sm font-mono">{{ txn.transaction_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ formatDate(txn.date) }}</td>
                        <td class="px-4 py-3 text-sm">{{ txn.outlet_name || '-' }}</td>
                        <td class="px-4 py-3 text-sm font-semibold">{{ formatCurrency(txn.amount) }}</td>
                        <td class="px-4 py-3 text-sm">
                          <span :class="getStatusClass(txn.status)" class="px-2 py-1 rounded-full text-xs">
                            {{ txn.status }}
                          </span>
                        </td>
                      </tr>
                      <tr v-if="isExpanded(txn.id, 'rnf')" class="bg-gray-50">
                        <td colspan="6" class="px-4 py-4">
                          <div class="space-y-4">
                            <!-- Items -->
                            <div v-if="txn.items && txn.items.length > 0">
                              <h4 class="font-semibold text-gray-700 mb-2">
                                <i class="fa fa-list mr-2"></i>Items ({{ txn.items.length }})
                              </h4>
                              <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                  <thead class="bg-purple-100">
                                    <tr>
                                      <th class="px-3 py-2 text-left">Item Name</th>
                                      <th class="px-3 py-2 text-right">Quantity</th>
                                      <th class="px-3 py-2 text-left">Unit</th>
                                      <th class="px-3 py-2 text-right">Price</th>
                                      <th class="px-3 py-2 text-right">Subtotal</th>
                                    </tr>
                                  </thead>
                                  <tbody class="divide-y divide-gray-200">
                                    <tr v-for="item in txn.items" :key="item.id">
                                      <td class="px-3 py-2">{{ item.item_name }}</td>
                                      <td class="px-3 py-2 text-right">{{ item.quantity }}</td>
                                      <td class="px-3 py-2">{{ item.unit }}</td>
                                      <td class="px-3 py-2 text-right">{{ formatCurrency(item.price) }}</td>
                                      <td class="px-3 py-2 text-right font-semibold">{{ formatCurrency(item.subtotal) }}</td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                            <!-- Attachments -->
                            <div v-if="txn.attachments && txn.attachments.length > 0">
                              <h4 class="font-semibold text-gray-700 mb-2">
                                <i class="fa fa-paperclip mr-2"></i>Attachments ({{ txn.attachments.length }})
                              </h4>
                              <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div v-for="attachment in txn.attachments" :key="attachment.id" class="bg-white p-3 rounded border">
                                  <a :href="attachment.file_path" target="_blank" class="text-blue-600 hover:underline flex items-center gap-2">
                                    <i class="fa fa-file"></i>
                                    <span>{{ attachment.file_name }}</span>
                                  </a>
                                  <p v-if="attachment.description" class="text-xs text-gray-500 mt-1">{{ attachment.description }}</p>
                                </div>
                              </div>
                            </div>
                            <div v-if="(!txn.items || txn.items.length === 0) && (!txn.attachments || txn.attachments.length === 0)" class="text-gray-500 text-sm">
                              No items or attachments available
                            </div>
                          </div>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- PR Transactions -->
            <div v-if="activeTab[category.category_id] === 'pr'">
              <div v-if="category.transactions.pr.length === 0" class="text-center py-8 text-gray-500">
                <i class="fa fa-inbox text-4xl mb-2"></i>
                <p>No Purchase Requisition transactions found</p>
              </div>
              <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-orange-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase w-12"></th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">PR Number</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Date</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Outlet</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Amount</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <template v-for="txn in category.transactions.pr" :key="txn.id">
                      <tr class="hover:bg-gray-50 cursor-pointer" @click="toggleTransaction(txn.id, 'pr')">
                        <td class="px-4 py-3 text-sm">
                          <i :class="isExpanded(txn.id, 'pr') ? 'fa fa-chevron-down' : 'fa fa-chevron-right'" class="text-gray-400"></i>
                        </td>
                        <td class="px-4 py-3 text-sm font-mono">{{ txn.transaction_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ formatDate(txn.date) }}</td>
                        <td class="px-4 py-3 text-sm">{{ txn.outlet_name || '-' }}</td>
                        <td class="px-4 py-3 text-sm font-semibold">{{ formatCurrency(txn.amount) }}</td>
                        <td class="px-4 py-3 text-sm">
                          <span :class="getStatusClass(txn.status)" class="px-2 py-1 rounded-full text-xs">
                            {{ txn.status }}
                          </span>
                        </td>
                      </tr>
                      <tr v-if="isExpanded(txn.id, 'pr')" class="bg-gray-50">
                        <td colspan="6" class="px-4 py-4">
                          <div class="space-y-4">
                            <!-- Items -->
                            <div v-if="txn.items && txn.items.length > 0">
                              <h4 class="font-semibold text-gray-700 mb-2">
                                <i class="fa fa-list mr-2"></i>Items ({{ txn.items.length }})
                              </h4>
                              <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                  <thead class="bg-orange-100">
                                    <tr>
                                      <th class="px-3 py-2 text-left">Item Name</th>
                                      <th class="px-3 py-2 text-right">Quantity</th>
                                      <th class="px-3 py-2 text-left">Unit</th>
                                      <th class="px-3 py-2 text-right">Price</th>
                                      <th class="px-3 py-2 text-right">Subtotal</th>
                                    </tr>
                                  </thead>
                                  <tbody class="divide-y divide-gray-200">
                                    <tr v-for="item in txn.items" :key="item.id">
                                      <td class="px-3 py-2">{{ item.item_name }}</td>
                                      <td class="px-3 py-2 text-right">{{ item.quantity }}</td>
                                      <td class="px-3 py-2">{{ item.unit }}</td>
                                      <td class="px-3 py-2 text-right">{{ formatCurrency(item.price) }}</td>
                                      <td class="px-3 py-2 text-right font-semibold">{{ formatCurrency(item.subtotal) }}</td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                            <!-- Attachments -->
                            <div v-if="txn.attachments && txn.attachments.length > 0">
                              <h4 class="font-semibold text-gray-700 mb-2">
                                <i class="fa fa-paperclip mr-2"></i>Attachments ({{ txn.attachments.length }})
                              </h4>
                              <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div v-for="attachment in txn.attachments" :key="attachment.id" class="bg-white p-3 rounded border">
                                  <a :href="attachment.file_path" target="_blank" class="text-blue-600 hover:underline flex items-center gap-2">
                                    <i class="fa fa-file"></i>
                                    <span>{{ attachment.file_name }}</span>
                                  </a>
                                  <p v-if="attachment.description" class="text-xs text-gray-500 mt-1">{{ attachment.description }}</p>
                                </div>
                              </div>
                            </div>
                            <div v-if="(!txn.items || txn.items.length === 0) && (!txn.attachments || txn.attachments.length === 0)" class="text-gray-500 text-sm">
                              No items or attachments available
                            </div>
                          </div>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- NFP Transactions -->
            <div v-if="activeTab[category.category_id] === 'nfp'">
              <div v-if="category.transactions.nfp.length === 0" class="text-center py-8 text-gray-500">
                <i class="fa fa-inbox text-4xl mb-2"></i>
                <p>No Non Food Payment transactions found</p>
              </div>
              <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-green-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase w-12"></th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Payment Number</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Date</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Outlet</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Amount</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <template v-for="txn in category.transactions.nfp" :key="txn.id">
                      <tr class="hover:bg-gray-50 cursor-pointer" @click="toggleTransaction(txn.id, 'nfp')">
                        <td class="px-4 py-3 text-sm">
                          <i :class="isExpanded(txn.id, 'nfp') ? 'fa fa-chevron-down' : 'fa fa-chevron-right'" class="text-gray-400"></i>
                        </td>
                        <td class="px-4 py-3 text-sm font-mono">{{ txn.transaction_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ formatDate(txn.date) }}</td>
                        <td class="px-4 py-3 text-sm">{{ txn.outlet_name || '-' }}</td>
                        <td class="px-4 py-3 text-sm font-semibold">{{ formatCurrency(txn.amount) }}</td>
                        <td class="px-4 py-3 text-sm">
                          <span :class="getStatusClass(txn.status)" class="px-2 py-1 rounded-full text-xs">
                            {{ txn.status }}
                          </span>
                        </td>
                      </tr>
                      <tr v-if="isExpanded(txn.id, 'nfp')" class="bg-gray-50">
                        <td colspan="6" class="px-4 py-4">
                          <div class="space-y-4">
                            <!-- Items -->
                            <div v-if="txn.items && txn.items.length > 0">
                              <h4 class="font-semibold text-gray-700 mb-2">
                                <i class="fa fa-list mr-2"></i>Items ({{ txn.items.length }})
                              </h4>
                              <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                  <thead class="bg-green-100">
                                    <tr>
                                      <th class="px-3 py-2 text-left">Item Name</th>
                                      <th class="px-3 py-2 text-right">Quantity</th>
                                      <th class="px-3 py-2 text-left">Unit</th>
                                      <th class="px-3 py-2 text-right">Price</th>
                                      <th class="px-3 py-2 text-right">Subtotal</th>
                                    </tr>
                                  </thead>
                                  <tbody class="divide-y divide-gray-200">
                                    <tr v-for="item in txn.items" :key="item.id">
                                      <td class="px-3 py-2">{{ item.item_name }}</td>
                                      <td class="px-3 py-2 text-right">{{ item.quantity }}</td>
                                      <td class="px-3 py-2">{{ item.unit }}</td>
                                      <td class="px-3 py-2 text-right">{{ formatCurrency(item.price) }}</td>
                                      <td class="px-3 py-2 text-right font-semibold">{{ formatCurrency(item.subtotal) }}</td>
                                    </tr>
                                  </tbody>
                                </table>
                              </div>
                            </div>
                            <!-- Attachments -->
                            <div v-if="txn.attachments && txn.attachments.length > 0">
                              <h4 class="font-semibold text-gray-700 mb-2">
                                <i class="fa fa-paperclip mr-2"></i>Attachments ({{ txn.attachments.length }})
                              </h4>
                              <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div v-for="attachment in txn.attachments" :key="attachment.id" class="bg-white p-3 rounded border">
                                  <a :href="attachment.file_path" target="_blank" class="text-blue-600 hover:underline flex items-center gap-2">
                                    <i class="fa fa-file"></i>
                                    <span>{{ attachment.file_name }}</span>
                                  </a>
                                  <p v-if="attachment.description" class="text-xs text-gray-500 mt-1">{{ attachment.description }}</p>
                                </div>
                              </div>
                            </div>
                            <div v-if="(!txn.items || txn.items.length === 0) && (!txn.attachments || txn.attachments.length === 0)" class="text-gray-500 text-sm">
                              No items or attachments available
                            </div>
                          </div>
                        </td>
                      </tr>
                    </template>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="categoryData.length === 0" class="bg-white rounded-xl shadow-lg p-12 text-center">
          <i class="fa fa-inbox text-6xl text-gray-400 mb-4"></i>
          <p class="text-xl text-gray-600 mb-2">No transactions found</p>
          <p class="text-gray-500">Try adjusting your filters</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  categoryData: {
    type: Array,
    default: () => []
  },
  categories: {
    type: Array,
    default: () => []
  },
  filters: {
    type: Object,
    default: () => {
      const today = new Date();
      const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
      const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
      return {
        date_from: firstDay.toISOString().split('T')[0],
        date_to: lastDay.toISOString().split('T')[0],
        category_id: ''
      };
    }
  },
  summary: {
    type: Object,
    default: () => ({
      total_categories: 0,
      total_rnf: 0,
      total_pr: 0,
      total_nfp: 0,
      grand_total: 0
    })
  }
});

const activeTab = ref({});
const expandedTransactions = ref({});

onMounted(() => {
  // Initialize active tab for each category
  props.categoryData.forEach(category => {
    if (!activeTab.value[category.category_id]) {
      activeTab.value[category.category_id] = 'rnf';
    }
  });
});

const toggleTransaction = (txnId, sourceType) => {
  const key = `${sourceType}-${txnId}`;
  expandedTransactions.value[key] = !expandedTransactions.value[key];
};

const isExpanded = (txnId, sourceType) => {
  const key = `${sourceType}-${txnId}`;
  return expandedTransactions.value[key] || false;
};

const formatCurrency = (value) => {
  if (!value) return 'Rp 0';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0
  }).format(value);
};

const formatDate = (date) => {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
};

const getStatusClass = (status) => {
  const statusMap = {
    'approved': 'bg-green-100 text-green-800',
    'pending': 'bg-yellow-100 text-yellow-800',
    'paid': 'bg-blue-100 text-blue-800',
    'SUBMITTED': 'bg-blue-100 text-blue-800',
    'APPROVED': 'bg-green-100 text-green-800',
    'PROCESSED': 'bg-purple-100 text-purple-800',
    'COMPLETED': 'bg-indigo-100 text-indigo-800',
    'REJECTED': 'bg-red-100 text-red-800'
  };
  return statusMap[status] || 'bg-gray-100 text-gray-800';
};

const onFilterChange = () => {
  router.get('/opex-by-category', props.filters, {
    preserveState: true,
    preserveScroll: true
  });
};

const resetFilters = () => {
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
  const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
  
  router.get('/opex-by-category', {
    date_from: firstDay.toISOString().split('T')[0],
    date_to: lastDay.toISOString().split('T')[0],
    category_id: ''
  });
};

const exportReport = () => {
  const params = new URLSearchParams({
    date_from: filters.value.date_from || '',
    date_to: filters.value.date_to || '',
    category_id: filters.value.category_id || '',
    export: 'excel'
  });
  
  window.location.href = `/opex-by-category/export?${params.toString()}`;
};
</script>

