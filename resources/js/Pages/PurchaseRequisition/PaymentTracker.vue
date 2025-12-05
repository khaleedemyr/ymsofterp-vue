<template>
  <AppLayout title="Payment Tracker">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white flex items-center gap-2">
          <i class="fa-solid fa-chart-line text-blue-500"></i> Payment Tracker
        </h1>
      </div>

      <!-- Filters -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- From Date -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              From Date
            </label>
            <input
              v-model="filters.fromDate"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            />
          </div>

          <!-- To Date -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              To Date
            </label>
            <input
              v-model="filters.toDate"
              type="date"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            />
          </div>

          <!-- General Search -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Search (All Columns)
            </label>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Search PR Number, Title, Amount, Division, Outlet, Category, Creator, Approver..."
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
            />
          </div>
        </div>
        
        <!-- Per Page Selector -->
        <div class="mt-4 flex items-center gap-2">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Per Page:</label>
          <select
            v-model="filters.perPage"
            @change="loadData"
            class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
          >
            <option value="15">15</option>
            <option value="30">30</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>

        <div class="flex justify-end gap-2 mt-4">
          <button
            @click="resetFilters"
            class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors"
          >
            Reset
          </button>
          <button
            @click="loadData"
            class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition-colors"
          >
            <i class="fa fa-search mr-2"></i>Search
          </button>
        </div>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="text-center py-8">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
        <p class="mt-2 text-gray-600 dark:text-gray-400">Loading data...</p>
      </div>

      <!-- Data Table -->
      <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
          <div class="flex justify-between items-center">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-white">
              Approved Payments
            </h2>
            <span class="text-sm text-gray-600 dark:text-gray-400">
              <span v-if="pagination">Menampilkan {{ pagination.from }} sampai {{ pagination.to }} dari {{ pagination.total }} records</span>
              <span v-else>Total: {{ payments.length }} records</span>
            </span>
          </div>
        </div>

        <div v-if="payments.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
          <i class="fa fa-inbox text-4xl mb-2"></i>
          <p>No approved payments found</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider w-12">
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  PR Number
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Mode
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Title
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Amount
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Division
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Outlet
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Category
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Creator
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Approver
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Approval Level
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Approved At
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <template v-for="payment in payments" :key="payment.id">
                <tr 
                  class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                  @click="toggleExpand(payment.id)"
                >
                  <td class="px-4 py-3 whitespace-nowrap" @click.stop>
                    <button
                      @click="toggleExpand(payment.id)"
                      class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                      <i :class="expandedRows.has(payment.id) ? 'fa fa-chevron-down' : 'fa fa-chevron-right'"></i>
                    </button>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ payment.pr_number }}
                    </span>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span v-if="payment.mode" 
                          :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getModeBadgeClass(payment.mode)]">
                      {{ getModeLabel(payment.mode) }}
                    </span>
                    <span v-else class="text-xs text-gray-500 dark:text-gray-400">-</span>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getStatusBadgeClass(payment.status)]">
                      {{ payment.status || '-' }}
                    </span>
                  </td>
                  <td class="px-4 py-3">
                    <div class="text-sm text-gray-900 dark:text-white max-w-xs truncate" :title="payment.title">
                      {{ payment.title }}
                    </div>
                    <div v-if="payment.description" class="text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate" :title="payment.description">
                      {{ payment.description }}
                    </div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">
                      Rp {{ formatNumber(payment.amount) }}
                    </span>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span class="text-sm text-gray-900 dark:text-white">
                      {{ payment.division || '-' }}
                    </span>
                  </td>
                <td class="px-4 py-3">
                  <div class="flex flex-col gap-1 max-w-xs">
                    <template v-if="paymentDetails[payment.id]">
                      <template v-if="getOutletList(payment).length > 0">
                        <div v-for="(outlet, idx) in getOutletList(payment).slice(0, 2)" :key="idx" 
                             class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-800 dark:bg-blue-900 dark:text-blue-200 border border-blue-200 dark:border-blue-700">
                          {{ outlet }}
                        </div>
                        <button 
                          v-if="getOutletList(payment).length > 2" 
                          @click.stop="showOutletModal(payment)"
                          class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 italic underline cursor-pointer"
                        >
                          +{{ getOutletList(payment).length - 2 }} outlet lainnya
                        </button>
                      </template>
                      <span v-else class="text-sm text-gray-400">-</span>
                    </template>
                    <span v-else class="text-sm text-gray-400">-</span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <div class="flex flex-col gap-1 max-w-xs">
                    <template v-if="paymentDetails[payment.id]">
                      <template v-if="getCategoriesList(payment).length > 0">
                        <div v-for="(category, idx) in getCategoriesList(payment).slice(0, 2)" :key="idx" 
                             class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-50 text-green-800 dark:bg-green-900 dark:text-green-200 border border-green-200 dark:border-green-700">
                          {{ category }}
                        </div>
                        <button 
                          v-if="getCategoriesList(payment).length > 2" 
                          @click.stop="showCategoryModal(payment)"
                          class="text-xs text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 italic underline cursor-pointer"
                        >
                          +{{ getCategoriesList(payment).length - 2 }} category lainnya
                        </button>
                      </template>
                      <span v-else class="text-sm text-gray-400">-</span>
                    </template>
                    <span v-else class="text-sm text-gray-400">-</span>
                  </div>
                </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-8 w-8">
                        <!-- Avatar Creator -->
                        <div v-if="payment.creator?.avatar" 
                             class="w-8 h-8 rounded-full overflow-hidden cursor-pointer hover:scale-110 transition-transform border-2 border-gray-200 dark:border-gray-600"
                             @click="openImageModal(`/storage/${payment.creator.avatar}`)">
                          <img 
                            :src="`/storage/${payment.creator.avatar}`" 
                            :alt="payment.creator?.name || 'User'"
                            class="w-full h-full object-cover"
                          />
                        </div>
                        <div 
                          v-else 
                          class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold border-2 border-gray-200 dark:border-gray-600"
                        >
                          {{ getInitials(payment.creator?.name || 'U') }}
                        </div>
                      </div>
                      <div class="ml-3">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ payment.creator?.name || '-' }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ payment.creator?.email || '' }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-8 w-8">
                        <!-- Avatar Approver -->
                        <div v-if="payment.approver?.avatar" 
                             class="w-8 h-8 rounded-full overflow-hidden cursor-pointer hover:scale-110 transition-transform border-2 border-gray-200 dark:border-gray-600"
                             @click="openImageModal(`/storage/${payment.approver.avatar}`)">
                          <img 
                            :src="`/storage/${payment.approver.avatar}`" 
                            :alt="payment.approver?.name || 'User'"
                            class="w-full h-full object-cover"
                          />
                        </div>
                        <div 
                          v-else 
                          class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center text-white text-xs font-bold border-2 border-gray-200 dark:border-gray-600"
                        >
                          {{ getInitials(payment.approver?.name || 'U') }}
                        </div>
                      </div>
                      <div class="ml-3">
                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ payment.approver?.name || '-' }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ payment.approver?.email || '' }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                      Level {{ payment.approval_level }}
                    </span>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="text-sm text-gray-900 dark:text-white">
                      {{ formatDate(payment.approved_at) }}
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                      {{ formatTime(payment.approved_at) }}
                    </div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap text-sm font-medium" @click.stop>
                    <button
                      @click="viewDetails(payment.id)"
                      class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                      <i class="fa fa-eye mr-1"></i>View
                    </button>
                  </td>
                </tr>
                <!-- Expanded Row with Details -->
                <tr v-if="expandedRows.has(payment.id)" class="bg-gray-50 dark:bg-gray-900">
                  <td colspan="13" class="px-4 py-4">
                    <div v-if="loadingDetails[payment.id]" class="text-center py-4">
                      <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                      <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Loading details...</p>
                    </div>
                    <div v-else-if="paymentDetails[payment.id]" class="space-y-4">
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Basic Info -->
                        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                          <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Basic Information</h4>
                          <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                              <span class="text-gray-600 dark:text-gray-400">PR Number:</span>
                              <span class="font-medium text-gray-900 dark:text-white">{{ paymentDetails[payment.id].pr_number }}</span>
                            </div>
                            <div class="flex justify-between">
                              <span class="text-gray-600 dark:text-gray-400">Status:</span>
                              <span class="font-medium text-gray-900 dark:text-white">{{ paymentDetails[payment.id].status }}</span>
                            </div>
                            <div class="flex justify-between">
                              <span class="text-gray-600 dark:text-gray-400">Date:</span>
                              <span class="font-medium text-gray-900 dark:text-white">{{ formatDate(paymentDetails[payment.id].date) }}</span>
                            </div>
                            <div class="flex justify-between">
                              <span class="text-gray-600 dark:text-gray-400">Created At:</span>
                              <span class="font-medium text-gray-900 dark:text-white">{{ formatDate(paymentDetails[payment.id].created_at) }}</span>
                            </div>
                          </div>
                        </div>

                        <!-- Approval Info -->
                        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                          <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Approval Information</h4>
                          <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                              <span class="text-gray-600 dark:text-gray-400">Approval Level:</span>
                              <span class="font-medium text-gray-900 dark:text-white">Level {{ payment.approval_level }}</span>
                            </div>
                            <div class="flex justify-between">
                              <span class="text-gray-600 dark:text-gray-400">Approved At:</span>
                              <span class="font-medium text-gray-900 dark:text-white">{{ formatDate(payment.approved_at) }} {{ formatTime(payment.approved_at) }}</span>
                            </div>
                            <div v-if="payment.approval_comments" class="mt-2">
                              <span class="text-gray-600 dark:text-gray-400 block mb-1">Comments:</span>
                              <span class="text-gray-900 dark:text-white">{{ payment.approval_comments }}</span>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Items -->
                      <div v-if="paymentDetails[payment.id].items && paymentDetails[payment.id].items.length > 0" 
                           class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Items ({{ paymentDetails[payment.id].items.length }})</h4>
                        <div class="overflow-x-auto">
                          <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                              <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300">Item</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300">Quantity</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300">Unit</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300">Unit Price</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300">Total</th>
                              </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                              <tr v-for="(item, idx) in paymentDetails[payment.id].items" :key="idx">
                                <td class="px-3 py-2 text-gray-900 dark:text-white">{{ item.description || item.item_name || '-' }}</td>
                                <td class="px-3 py-2 text-gray-900 dark:text-white">{{ item.qty || item.quantity || '-' }}</td>
                                <td class="px-3 py-2 text-gray-900 dark:text-white">{{ item.unit || '-' }}</td>
                                <td class="px-3 py-2 text-gray-900 dark:text-white">{{ item.unit_price ? 'Rp ' + formatNumber(item.unit_price) : '-' }}</td>
                                <td class="px-3 py-2 text-gray-900 dark:text-white">{{ item.subtotal || item.total ? 'Rp ' + formatNumber(item.subtotal || item.total) : '-' }}</td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>

                      <!-- Attachments -->
                      <div v-if="paymentDetails[payment.id].attachments && paymentDetails[payment.id].attachments.length > 0" 
                           class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Attachments ({{ paymentDetails[payment.id].attachments.length }})</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                          <div v-for="(attachment, idx) in paymentDetails[payment.id].attachments" :key="idx"
                               class="border border-gray-200 dark:border-gray-700 rounded p-2">
                            <!-- Image Thumbnail with Lightbox -->
                            <div v-if="isImage(attachment)" class="cursor-pointer" @click="openLightbox(attachment)">
                              <img :src="`/purchase-requisitions/attachments/${attachment.id}/download`" 
                                   :alt="attachment.file_name"
                                   class="w-full h-32 object-cover rounded mb-2 hover:opacity-80 transition-opacity" />
                              <p class="text-xs text-gray-600 dark:text-gray-400 truncate" :title="attachment.file_name">
                                {{ attachment.file_name }}
                              </p>
                            </div>
                            <!-- Non-image file download -->
                            <div v-else>
                              <a :href="`/purchase-requisitions/attachments/${attachment.id}/download`" 
                                 target="_blank"
                                 class="flex flex-col items-center text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                <i class="fa fa-file text-4xl mb-2"></i>
                                <p class="text-xs text-center truncate w-full" :title="attachment.file_name">
                                  {{ attachment.file_name }}
                                </p>
                              </a>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="pagination && pagination.last_page > 1" class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-4 bg-white dark:bg-gray-800 p-4 rounded-lg shadow-md">
        <!-- Pagination Info -->
        <div class="text-sm text-gray-600 dark:text-gray-400">
          Menampilkan {{ pagination.from }} sampai {{ pagination.to }} dari {{ pagination.total }} records
        </div>
        
        <!-- Pagination Navigation -->
        <nav class="flex rounded-lg shadow-sm -space-x-px" aria-label="Pagination">
          <button 
            @click="goToPage(1)" 
            :disabled="pagination.current_page === 1"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-l-lg transition-colors',
              pagination.current_page === 1 
                ? 'bg-gray-100 dark:bg-gray-700 text-gray-400 cursor-not-allowed' 
                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
            ]"
          >
            First
          </button>
          <button 
            @click="goToPage(pagination.current_page - 1)" 
            :disabled="pagination.current_page === 1"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 transition-colors',
              pagination.current_page === 1 
                ? 'bg-gray-100 dark:bg-gray-700 text-gray-400 cursor-not-allowed' 
                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
            ]"
          >
            Previous
          </button>
          <template v-for="pageNum in getPageNumbers()" :key="pageNum">
            <button 
              v-if="pageNum !== '...'"
              @click="goToPage(pageNum)" 
              :class="[
                'px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 transition-colors',
                pageNum === pagination.current_page
                  ? 'bg-blue-600 text-white border-blue-600' 
                  : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
              ]"
            >
              {{ pageNum }}
            </button>
            <span 
              v-else
              class="px-3 py-2 text-sm border border-gray-200 dark:border-gray-600 text-gray-400 dark:text-gray-500 bg-gray-50 dark:bg-gray-700"
            >
              ...
            </span>
          </template>
          <button 
            @click="goToPage(pagination.current_page + 1)" 
            :disabled="pagination.current_page === pagination.last_page"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 transition-colors',
              pagination.current_page === pagination.last_page 
                ? 'bg-gray-100 dark:bg-gray-700 text-gray-400 cursor-not-allowed' 
                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
            ]"
          >
            Next
          </button>
          <button 
            @click="goToPage(pagination.last_page)" 
            :disabled="pagination.current_page === pagination.last_page"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-r-lg transition-colors',
              pagination.current_page === pagination.last_page 
                ? 'bg-gray-100 dark:bg-gray-700 text-gray-400 cursor-not-allowed' 
                : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'
            ]"
          >
            Last
          </button>
        </nav>
      </div>
    </div>

    <!-- Lightbox for Images -->
    <vue-easy-lightbox
      :visible="showLightbox"
      :imgs="lightboxImages"
      :index="lightboxIndex"
      @hide="showLightbox = false"
    />

    <!-- Outlet Modal -->
    <div v-if="showOutletModalFlag" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showOutletModalFlag = false">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md mx-4" @click.stop>
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            <i class="fa fa-store mr-2 text-blue-500"></i>
            All Outlets - {{ selectedPaymentForModal?.pr_number }}
          </h3>
          <button @click="showOutletModalFlag = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>
        <div class="max-h-96 overflow-y-auto">
          <div class="space-y-2">
            <div 
              v-for="(outlet, idx) in getOutletList(selectedPaymentForModal)" 
              :key="idx"
              class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg"
            >
              <div class="flex items-center">
                <i class="fa fa-store mr-2 text-blue-500"></i>
                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ outlet }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Category Modal -->
    <div v-if="showCategoryModalFlag" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showCategoryModalFlag = false">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md mx-4" @click.stop>
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            <i class="fa fa-tags mr-2 text-green-500"></i>
            All Categories - {{ selectedPaymentForModal?.pr_number }}
          </h3>
          <button @click="showCategoryModalFlag = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>
        <div class="max-h-96 overflow-y-auto">
          <div class="space-y-2">
            <div 
              v-for="(category, idx) in getCategoriesList(selectedPaymentForModal)" 
              :key="idx"
              class="p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 rounded-lg"
            >
              <div class="flex items-center">
                <i class="fa fa-tag mr-2 text-green-500"></i>
                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ category }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import axios from 'axios';

const page = usePage();
const user = page.props.auth?.user || {};

const isSuperadmin = computed(() => {
  return user.id_role === '5af56935b011a' && user.status === 'A';
});

const loading = ref(false);
const payments = ref([]);
const expandedRows = ref(new Set());
const paymentDetails = ref({});
const loadingDetails = ref({});
const showLightbox = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);
const showOutletModalFlag = ref(false);
const showCategoryModalFlag = ref(false);
const selectedPaymentForModal = ref(null);

const filters = ref({
  fromDate: '',
  toDate: '',
  search: '',
  perPage: 15,
  page: 1,
});

const pagination = ref(null);

// Set default date range (first day of current month to last day of current month)
const setDefaultDateRange = () => {
  const now = new Date();
  const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
  const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  
  filters.value.fromDate = firstDay.toISOString().split('T')[0];
  filters.value.toDate = lastDay.toISOString().split('T')[0];
};

const loadData = async () => {
  loading.value = true;
  try {
    const params = {
      from_date: filters.value.fromDate || null,
      to_date: filters.value.toDate || null,
      search: filters.value.search || null,
      page: filters.value.page || 1,
      per_page: filters.value.perPage || 15,
    };

    // Remove null values
    Object.keys(params).forEach(key => {
      if (params[key] === null || params[key] === '') {
        delete params[key];
      }
    });

    const response = await axios.get('/api/purchase-requisitions/payment-tracker', { params });
    
    if (response.data.success) {
      payments.value = response.data.data || [];
      pagination.value = response.data.pagination || null;
      // Reset expanded rows when data changes
      expandedRows.value.clear();
      paymentDetails.value = {};
      // Pre-load details to show outlet and category in main table
      preloadAllDetails();
    } else {
      console.error('Error loading payment tracker:', response.data.error);
      payments.value = [];
      pagination.value = null;
    }
  } catch (error) {
    console.error('Error loading payment tracker:', error);
    payments.value = [];
    pagination.value = null;
  } finally {
    loading.value = false;
  }
};

const toggleExpand = async (paymentId) => {
  if (expandedRows.value.has(paymentId)) {
    expandedRows.value.delete(paymentId);
  } else {
    expandedRows.value.add(paymentId);
    // Load details if not already loaded
    if (!paymentDetails.value[paymentId]) {
      await loadPaymentDetails(paymentId);
    }
  }
};

// Pre-load details for all payments to show outlet and category in main table (async, non-blocking)
const preloadAllDetails = async () => {
  const paymentIds = payments.value.map(p => p.id);
  // Load in parallel but limit concurrency to avoid overwhelming the server
  const batchSize = 5;
  for (let i = 0; i < paymentIds.length; i += batchSize) {
    const batch = paymentIds.slice(i, i + batchSize);
    await Promise.all(batch.map(paymentId => {
      if (!paymentDetails.value[paymentId]) {
        return loadPaymentDetails(paymentId);
      }
    }));
  }
};

const loadPaymentDetails = async (paymentId) => {
  loadingDetails.value[paymentId] = true;
  try {
    const response = await axios.get(`/api/purchase-requisitions/${paymentId}/approval-details`);
    if (response.data.success) {
      paymentDetails.value[paymentId] = response.data.purchase_requisition;
    }
  } catch (error) {
    console.error('Error loading payment details:', error);
  } finally {
    loadingDetails.value[paymentId] = false;
  }
};

const resetFilters = () => {
  filters.value = {
    fromDate: '',
    toDate: '',
    search: '',
    perPage: 15,
    page: 1,
  };
  setDefaultDateRange();
  loadData();
};

const goToPage = (pageNum) => {
  if (pageNum >= 1 && pageNum <= pagination.value.last_page) {
    filters.value.page = pageNum;
    loadData();
  }
};

const getPageNumbers = () => {
  if (!pagination.value) return [];
  const current = pagination.value.current_page;
  const last = pagination.value.last_page;
  const pages = [];
  
  if (last <= 7) {
    // Show all pages if 7 or less
    for (let i = 1; i <= last; i++) {
      pages.push(i);
    }
  } else {
    // Show first page, current page, and last page with ellipsis
    if (current <= 3) {
      for (let i = 1; i <= 4; i++) pages.push(i);
      pages.push('...');
      pages.push(last);
    } else if (current >= last - 2) {
      pages.push(1);
      pages.push('...');
      for (let i = last - 3; i <= last; i++) pages.push(i);
    } else {
      pages.push(1);
      pages.push('...');
      for (let i = current - 1; i <= current + 1; i++) pages.push(i);
      pages.push('...');
      pages.push(last);
    }
  }
  
  return pages;
};

const getInitials = (name) => {
  if (!name) return 'U';
  const parts = name.trim().split(' ');
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  }
  return name.substring(0, 2).toUpperCase();
};

const openImageModal = (imageUrl) => {
  lightboxImages.value = [imageUrl];
  lightboxIndex.value = 0;
  showLightbox.value = true;
};

const showOutletModal = (payment) => {
  selectedPaymentForModal.value = payment;
  showOutletModalFlag.value = true;
};

const showCategoryModal = (payment) => {
  selectedPaymentForModal.value = payment;
  showCategoryModalFlag.value = true;
};

const viewDetails = (prId) => {
  router.visit(`/purchase-requisitions/${prId}`);
};

const formatNumber = (number) => {
  return new Intl.NumberFormat('id-ID').format(number || 0);
};

const formatDate = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const formatTime = (dateString) => {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
  });
};

const getModeLabel = (mode) => {
  const labels = {
    'pr_ops': 'PR Ops',
    'purchase_payment': 'Purchase Payment',
    'travel_application': 'Travel Application',
    'kasbon': 'Kasbon',
  };
  return labels[mode] || mode;
};

const getModeBadgeClass = (mode) => {
  const classes = {
    'pr_ops': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    'purchase_payment': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'travel_application': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    'kasbon': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
  };
  return classes[mode] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
};

const getStatusBadgeClass = (status) => {
  const classes = {
    'DRAFT': 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    'SUBMITTED': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    'APPROVED': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    'REJECTED': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    'PROCESSED': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    'COMPLETED': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
    'PAID': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
  };
  return classes[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
};

const isImage = (attachment) => {
  if (!attachment.file_name) return false;
  const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.svg'];
  const fileName = attachment.file_name.toLowerCase();
  return imageExtensions.some(ext => fileName.endsWith(ext));
};

const openLightbox = (attachment) => {
  const paymentId = Object.keys(paymentDetails.value).find(id => 
    paymentDetails.value[id].attachments?.some(att => att.id === attachment.id)
  );
  
  if (paymentId) {
    const images = paymentDetails.value[paymentId].attachments
      .filter(att => isImage(att))
      .map(att => `/purchase-requisitions/attachments/${att.id}/download`);
    
    const index = paymentDetails.value[paymentId].attachments
      .findIndex(att => att.id === attachment.id);
    
    lightboxImages.value = images;
    lightboxIndex.value = index;
    showLightbox.value = true;
  }
};

const getOutletList = (payment) => {
  if (!payment) return [];
  
  const detail = paymentDetails.value[payment.id];
  if (!detail || !detail.items || detail.items.length === 0) {
    // Fallback ke PR level outlet
    if (detail?.outlet?.nama_outlet) {
      return [detail.outlet.nama_outlet];
    }
    return [];
  }
  
  // Untuk mode pr_ops dan purchase_payment: ambil outlet dari items
  if (payment.mode === 'pr_ops' || payment.mode === 'purchase_payment') {
    const outlets = new Set();
    detail.items.forEach(item => {
      if (item.outlet && item.outlet.nama_outlet) {
        outlets.add(item.outlet.nama_outlet);
      }
    });
    
    if (outlets.size > 0) {
      return Array.from(outlets).sort();
    }
  }
  
  // Fallback ke main PR outlet untuk data lama
  if (detail?.outlet?.nama_outlet) {
    return [detail.outlet.nama_outlet];
  }
  
  return [];
};

const getCategoriesList = (payment) => {
  if (!payment) return [];
  
  const detail = paymentDetails.value[payment.id];
  if (!detail || !detail.items || detail.items.length === 0) {
    // Fallback ke PR level category
    if (detail?.category?.name) {
      return [detail.category.name];
    }
    return [];
  }
  
  const categories = [];
  const categoryMap = new Map();
  
  // Untuk mode pr_ops dan purchase_payment: ambil semua unique categories dari items
  if ((payment.mode === 'pr_ops' || payment.mode === 'purchase_payment') && detail.items) {
    detail.items.forEach(item => {
      if (item.category && item.category.id) {
        if (!categoryMap.has(item.category.id)) {
          categoryMap.set(item.category.id, item.category);
          // Push category name as string
          const categoryName = item.category.name || (typeof item.category === 'string' ? item.category : '-');
          categories.push(categoryName);
        }
      }
    });
  }
  
  // Fallback ke PR level category jika tidak ada category dari items
  if (categories.length === 0 && detail?.category?.name) {
    categories.push(detail.category.name);
  }
  
  return categories;
};

onMounted(() => {
  setDefaultDateRange();
  loadData();
});
</script>
