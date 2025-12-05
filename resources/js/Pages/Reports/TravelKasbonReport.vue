<template>
  <AppLayout title="Report Travel Application & Kasbon">
    <div class="py-8 px-4">
      <div class="w-full mx-auto">
        <!-- Header -->
        <div class="mb-6">
          <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa fa-plane text-blue-500"></i>
            Report Travel Application & Kasbon
          </h1>
          <p class="text-gray-600 mt-2">Laporan lengkap untuk Purchase Requisition dengan mode Travel Application dan Kasbon</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <form @submit.prevent="applyFilters" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Mode</label>
              <select v-model="filters.mode" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="all">Semua Mode</option>
                <option value="travel_application">Travel Application</option>
                <option value="kasbon">Kasbon</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
              <select v-model="filters.status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="all">Semua Status</option>
                <option value="DRAFT">Draft</option>
                <option value="SUBMITTED">Submitted</option>
                <option value="APPROVED">Approved</option>
                <option value="REJECTED">Rejected</option>
                <option value="PROCESSED">Processed</option>
                <option value="COMPLETED">Completed</option>
                <option value="PAID">Paid</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Divisi</label>
              <select v-model="filters.division_id" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Semua Divisi</option>
                <option v-for="division in divisions" :key="division.id" :value="division.id">{{ division.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
              <select v-model="filters.outlet_id" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Semua Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.id">{{ outlet.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
              <input type="date" v-model="filters.date_from" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
              <input type="date" v-model="filters.date_to" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-1">Pencarian</label>
              <input type="text" v-model="filters.search" placeholder="Cari PR Number, Title, atau Nama..." class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div class="md:col-span-4 flex gap-2">
              <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition">
                <i class="fa fa-filter mr-2"></i>Filter
              </button>
              <button type="button" @click="resetFilters" class="bg-gray-500 text-white px-6 py-2 rounded-md hover:bg-gray-600 transition">
                <i class="fa fa-redo mr-2"></i>Reset
              </button>
            </div>
          </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
          <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-3">
              <div>
                <p class="text-sm text-gray-600">Total PR</p>
                <p class="text-2xl font-bold text-gray-800">{{ summary.total_count }}</p>
              </div>
              <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fa fa-file-invoice text-blue-600 text-xl"></i>
              </div>
            </div>
            <!-- Breakdown by Status -->
            <div v-if="summary.by_status && Object.keys(summary.by_status).length > 0" class="mt-3 pt-3 border-t border-gray-200 space-y-1">
              <div v-for="(statusData, status) in summary.by_status" :key="status" class="flex justify-between text-xs">
                <span class="text-gray-600">{{ status }}:</span>
                <span class="font-medium text-gray-800">{{ statusData.count }}</span>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-3">
              <div>
                <p class="text-sm text-gray-600">Total Amount</p>
                <p class="text-2xl font-bold text-green-600">{{ formatCurrency(summary.total_amount) }}</p>
              </div>
              <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fa fa-money-bill-wave text-green-600 text-xl"></i>
              </div>
            </div>
            <!-- Breakdown by Status -->
            <div v-if="summary.by_status && Object.keys(summary.by_status).length > 0" class="mt-3 pt-3 border-t border-gray-200 space-y-1">
              <div v-for="(statusData, status) in summary.by_status" :key="status" class="flex justify-between text-xs">
                <span class="text-gray-600">{{ status }}:</span>
                <span class="font-medium text-green-700">{{ formatCurrency(statusData.amount) }}</span>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-3">
              <div>
                <p class="text-sm text-gray-600">Travel Application</p>
                <p class="text-2xl font-bold text-purple-600">{{ summary.by_mode.travel_application?.count || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ formatCurrency(summary.by_mode.travel_application?.amount || 0) }}</p>
              </div>
              <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                <i class="fa fa-plane text-purple-600 text-xl"></i>
              </div>
            </div>
            <!-- Breakdown by Status for Travel Application -->
            <div v-if="summary.by_mode.travel_application?.by_status && Object.keys(summary.by_mode.travel_application.by_status).length > 0" class="mt-3 pt-3 border-t border-gray-200 space-y-1">
              <div v-for="(statusData, status) in summary.by_mode.travel_application.by_status" :key="status" class="flex justify-between text-xs">
                <span class="text-gray-600">{{ status }}:</span>
                <span class="font-medium text-purple-700">{{ statusData.count }} ({{ formatCurrency(statusData.amount) }})</span>
              </div>
            </div>
          </div>
          <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-3">
              <div>
                <p class="text-sm text-gray-600">Kasbon</p>
                <p class="text-2xl font-bold text-orange-600">{{ summary.by_mode.kasbon?.count || 0 }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ formatCurrency(summary.by_mode.kasbon?.amount || 0) }}</p>
              </div>
              <div class="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center">
                <i class="fa fa-wallet text-orange-600 text-xl"></i>
              </div>
            </div>
            <!-- Breakdown by Status for Kasbon -->
            <div v-if="summary.by_mode.kasbon?.by_status && Object.keys(summary.by_mode.kasbon.by_status).length > 0" class="mt-3 pt-3 border-t border-gray-200 space-y-1">
              <div v-for="(statusData, status) in summary.by_mode.kasbon.by_status" :key="status" class="flex justify-between text-xs">
                <span class="text-gray-600">{{ status }}:</span>
                <span class="font-medium text-orange-700">{{ statusData.count }} ({{ formatCurrency(statusData.amount) }})</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Per Page & Pagination Info -->
        <div class="bg-white rounded-xl shadow-lg p-4 mb-4 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <label class="text-sm text-gray-700 flex items-center gap-2">
              <span>Per Page:</span>
              <select v-model="filters.per_page" @change="applyFilters" class="border border-gray-300 rounded-md px-3 py-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
              </select>
            </label>
            <div class="text-sm text-gray-600">
              Menampilkan {{ pagination.from || 0 }} - {{ pagination.to || 0 }} dari {{ pagination.total || 0 }} data
            </div>
          </div>
        </div>

        <!-- Report Table -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden w-full">
          <div class="overflow-x-auto w-full">
            <table class="w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12"></th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PR Number</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Divisi/Outlet</th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requester</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <template v-for="(pr, index) in reportData" :key="pr.id">
                  <tr class="hover:bg-gray-50 cursor-pointer" @click="toggleExpand(pr.id)">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <button class="text-gray-600 hover:text-gray-900">
                        <i :class="expandedRows.includes(pr.id) ? 'fa fa-chevron-down' : 'fa fa-chevron-right'"></i>
                      </button>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="text-sm font-medium text-gray-900">{{ pr.pr_number }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatDate(pr.date) }}</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="pr.mode === 'travel_application' ? 'bg-purple-100 text-purple-800' : 'bg-orange-100 text-orange-800'">
                        <i :class="pr.mode === 'travel_application' ? 'fa fa-plane mr-1' : 'fa fa-wallet mr-1'"></i>
                        {{ pr.mode_label }}
                      </span>
                    </td>
                    <td class="px-6 py-4">
                      <div class="text-sm text-gray-900">{{ pr.title || '-' }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      <div v-if="pr.division_name">{{ pr.division_name }}</div>
                      <div v-if="pr.outlet_name" class="text-xs">{{ pr.outlet_name }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-right text-gray-900">
                      {{ formatCurrency(pr.amount) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="getStatusClass(pr.status)">
                        {{ pr.status }}
                      </span>
                      <span v-if="pr.is_held" class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        <i class="fa fa-lock mr-1"></i>HOLD
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                      {{ pr.requester_name || pr.creator_name || '-' }}
                    </td>
                  </tr>
                  <!-- Expanded Details Row -->
                  <tr v-if="expandedRows.includes(pr.id)" class="bg-gray-50">
                    <td colspan="9" class="px-6 py-4">
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Information -->
                        <div>
                          <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fa fa-info-circle mr-2 text-blue-500"></i>
                            Informasi Dasar
                          </h4>
                          <div class="space-y-2 text-sm">
                            <div><strong>Description:</strong> <span class="text-gray-700">{{ pr.description || '-' }}</span></div>
                            <div><strong>Category:</strong> <span class="text-gray-700">{{ pr.category_name || '-' }}</span></div>
                            <div><strong>Priority:</strong> 
                              <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                    :class="getPriorityClass(pr.priority)">
                                {{ pr.priority || '-' }}
                              </span>
                            </div>
                            <div><strong>Created By:</strong> <span class="text-gray-700">{{ pr.creator_name || '-' }}</span></div>
                            <div><strong>Created At:</strong> <span class="text-gray-700">{{ formatDateTime(pr.created_at) }}</span></div>
                            <!-- Notes Display -->
                            <div v-if="pr.mode === 'travel_application' && pr.parsed_notes">
                              <strong>Notes:</strong>
                              <div class="mt-1 space-y-1 text-gray-700">
                                <div v-if="pr.parsed_notes.agenda">
                                  <strong class="text-gray-600">Agenda:</strong> {{ pr.parsed_notes.agenda }}
                                </div>
                                <div v-if="pr.parsed_notes.notes">
                                  <strong class="text-gray-600">Catatan:</strong> {{ pr.parsed_notes.notes }}
                                </div>
                                <div v-if="pr.parsed_notes.outlets && pr.parsed_notes.outlets.length > 0">
                                  <strong class="text-gray-600">Outlet:</strong> {{ pr.parsed_notes.outlets.join(', ') }}
                                </div>
                              </div>
                            </div>
                            <div v-else-if="pr.notes && pr.mode !== 'travel_application'">
                              <strong>Notes:</strong> <span class="text-gray-700">{{ pr.notes }}</span>
                            </div>
                            <div v-if="pr.is_held">
                              <strong>Hold Reason:</strong> 
                              <span class="text-red-700">{{ pr.hold_reason }}</span>
                              <span v-if="pr.held_by_name" class="text-gray-500 text-xs ml-2">(by {{ pr.held_by_name }})</span>
                            </div>
                          </div>
                        </div>

                        <!-- Approval & Payment Info -->
                        <div>
                          <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fa fa-check-circle mr-2 text-green-500"></i>
                            Approval & Payment
                          </h4>
                          <div class="space-y-2 text-sm">
                            <div v-if="pr.approved_ssd_name">
                              <strong>SSD Approved:</strong> 
                              <span class="text-gray-700">{{ pr.approved_ssd_name }}</span>
                              <span v-if="pr.approved_ssd_at" class="text-gray-500 text-xs ml-2">({{ formatDateTime(pr.approved_ssd_at) }})</span>
                            </div>
                            <div v-if="pr.approved_cc_name">
                              <strong>CC Approved:</strong> 
                              <span class="text-gray-700">{{ pr.approved_cc_name }}</span>
                              <span v-if="pr.approved_cc_at" class="text-gray-500 text-xs ml-2">({{ formatDateTime(pr.approved_cc_at) }})</span>
                            </div>
                            <div v-if="pr.payment">
                              <strong>Payment:</strong> 
                              <span class="text-gray-700">{{ pr.payment.payment_number }}</span>
                              <span class="text-gray-500 text-xs ml-2">
                                ({{ formatCurrency(pr.payment.amount) }} - {{ pr.payment.status }})
                              </span>
                            </div>
                            <div v-if="pr.approval_history && pr.approval_history.length > 0">
                              <strong>Approval History:</strong>
                              <ul class="mt-1 space-y-1">
                                <li v-for="(approval, idx) in pr.approval_history" :key="idx" class="text-xs text-gray-600">
                                  Level {{ approval.approval_level }}: {{ approval.approver_name || 'Pending' }} 
                                  <span v-if="approval.approved_at">({{ formatDateTime(approval.approved_at) }})</span>
                                  <span v-else class="text-yellow-600">(Pending)</span>
                                </li>
                              </ul>
                            </div>
                          </div>
                        </div>

                        <!-- Items -->
                        <div v-if="pr.items && pr.items.length > 0" class="md:col-span-2">
                          <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fa fa-list mr-2 text-purple-500"></i>
                            Items ({{ pr.items.length }})
                          </h4>
                          <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                              <thead class="bg-gray-100">
                                <tr>
                                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-700">Item Name</th>
                                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-700">Qty</th>
                                  <th class="px-3 py-2 text-left text-xs font-medium text-gray-700">Unit</th>
                                  <th class="px-3 py-2 text-right text-xs font-medium text-gray-700">Unit Price</th>
                                  <th class="px-3 py-2 text-right text-xs font-medium text-gray-700">Subtotal</th>
                                </tr>
                              </thead>
                              <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(item, idx) in pr.items" :key="idx">
                                  <td class="px-3 py-2 text-gray-900">{{ item.item_name || '-' }}</td>
                                  <td class="px-3 py-2 text-gray-700">{{ item.quantity || '-' }}</td>
                                  <td class="px-3 py-2 text-gray-700">{{ item.unit || '-' }}</td>
                                  <td class="px-3 py-2 text-right text-gray-700">{{ formatCurrency(item.unit_price) }}</td>
                                  <td class="px-3 py-2 text-right font-medium text-gray-900">{{ formatCurrency(item.subtotal) }}</td>
                                </tr>
                              </tbody>
                            </table>
                          </div>
                        </div>

                        <!-- Attachments -->
                        <div v-if="pr.attachments && pr.attachments.length > 0" class="md:col-span-2">
                          <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fa fa-paperclip mr-2 text-indigo-500"></i>
                            Attachments ({{ pr.attachments.length }})
                          </h4>
                          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <!-- Image attachments with thumbnail -->
                            <div v-for="(attachment, idx) in pr.attachments" :key="idx" class="relative">
                              <div v-if="isImageFile(attachment.file_name)" 
                                   @click="openLightbox(getAttachmentUrl(attachment), attachment.file_name)"
                                   class="cursor-pointer group">
                                <img :src="getAttachmentUrl(attachment)" 
                                     :alt="attachment.file_name"
                                     class="w-full h-24 object-cover rounded border-2 border-indigo-200 hover:border-indigo-400 transition-all hover:scale-105 shadow-md"
                                     @error="handleImageError($event)" />
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 rounded transition-all flex items-center justify-center">
                                  <i class="fa fa-search-plus text-white opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                </div>
                                <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 truncate" :title="attachment.file_name">
                                  {{ attachment.file_name }}
                                </div>
                              </div>
                              <!-- PDF/DOC attachments - direct download -->
                              <a v-else
                                 :href="getAttachmentUrl(attachment)" 
                                 @click="handleFileDownload($event, attachment.file_name)"
                                 class="inline-flex flex-col items-center justify-center p-3 bg-indigo-50 text-indigo-700 rounded-lg text-sm hover:bg-indigo-100 transition border-2 border-indigo-200 hover:border-indigo-400 min-h-[100px]">
                                <i :class="getFileIcon(attachment.file_name) + ' text-2xl mb-2'"></i>
                                <span class="text-xs text-center truncate w-full" :title="attachment.file_name">
                                  {{ attachment.file_name }}
                                </span>
                                <span class="text-xs text-indigo-500 mt-1">{{ formatFileSize(attachment.file_size) }}</span>
                              </a>
                            </div>
                          </div>
                        </div>

                        <!-- Comments -->
                        <div v-if="pr.comments && pr.comments.length > 0" class="md:col-span-2">
                          <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="fa fa-comments mr-2 text-yellow-500"></i>
                            Comments ({{ pr.comments.length }})
                          </h4>
                          <div class="space-y-2 max-h-40 overflow-y-auto">
                            <div v-for="(comment, idx) in pr.comments" :key="idx" class="bg-white border rounded p-2 text-sm">
                              <div class="font-medium text-gray-800">{{ comment.commenter_name || 'Unknown' }}</div>
                              <div class="text-gray-600">{{ comment.comment }}</div>
                              <div class="text-xs text-gray-400 mt-1">{{ formatDateTime(comment.created_at) }}</div>
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

          <!-- Pagination -->
          <div v-if="pagination.last_page > 1" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
              <div class="flex-1 flex justify-between sm:hidden">
                <button
                  @click="goToPage(pagination.current_page - 1)"
                  :disabled="pagination.current_page === 1"
                  class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Previous
                </button>
                <button
                  @click="goToPage(pagination.current_page + 1)"
                  :disabled="pagination.current_page === pagination.last_page"
                  class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                </button>
              </div>
              <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                <div>
                  <p class="text-sm text-gray-700">
                    Menampilkan
                    <span class="font-medium">{{ pagination.from || 0 }}</span>
                    sampai
                    <span class="font-medium">{{ pagination.to || 0 }}</span>
                    dari
                    <span class="font-medium">{{ pagination.total || 0 }}</span>
                    hasil
                  </p>
                </div>
                <div>
                  <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <button
                      @click="goToPage(pagination.current_page - 1)"
                      :disabled="pagination.current_page === 1"
                      class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <i class="fa fa-chevron-left"></i>
                    </button>
                    <template v-for="page in getPageNumbers()" :key="page">
                      <button
                        v-if="page !== '...'"
                        @click="goToPage(page)"
                        :class="page === pagination.current_page
                          ? 'z-10 bg-blue-50 border-blue-500 text-blue-600 relative inline-flex items-center px-4 py-2 border text-sm font-medium'
                          : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50 relative inline-flex items-center px-4 py-2 border text-sm font-medium'"
                      >
                        {{ page }}
                      </button>
                      <span v-else class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">
                        ...
                      </span>
                    </template>
                    <button
                      @click="goToPage(pagination.current_page + 1)"
                      :disabled="pagination.current_page === pagination.last_page"
                      class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <i class="fa fa-chevron-right"></i>
                    </button>
                  </nav>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lightbox Modal for Images -->
      <div v-if="lightboxImage" 
           class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-[10000]" 
           @click="closeLightbox">
        <div class="relative max-w-7xl max-h-[95vh] p-4" @click.stop>
          <button @click="closeLightbox" 
                  class="absolute top-2 right-2 text-white hover:text-gray-300 text-3xl font-bold z-10 bg-black bg-opacity-50 rounded-full w-10 h-10 flex items-center justify-center">
            <i class="fa fa-times"></i>
          </button>
          <img :src="lightboxImage" 
               :alt="lightboxImageName"
               class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" />
          <div class="text-center mt-4 text-white">
            <p class="text-sm">{{ lightboxImageName }}</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  reportData: Array,
  summary: Object,
  divisions: Array,
  outlets: Array,
  pagination: Object,
  filters: Object,
});

const expandedRows = ref([]);

// Lightbox state
const lightboxImage = ref(null);
const lightboxImageName = ref('');

// Auto-load data on mount if no data is loaded yet
onMounted(() => {
  // If we have date_from and date_to but no reportData, auto-load data
  // This ensures data is loaded on first page visit
  if (filters.value.date_from && filters.value.date_to && (!props.reportData || props.reportData.length === 0)) {
    applyFilters();
  }
});

// Helper function to get first and last day of current month
function getFirstDayOfMonth() {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  return `${year}-${month}-01`;
}

function getLastDayOfMonth() {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth() + 1;
  const lastDay = new Date(year, month, 0).getDate();
  return `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
}

const filters = ref({
  mode: props.filters?.mode || 'all',
  status: props.filters?.status || 'all',
  division_id: props.filters?.division_id || '',
  outlet_id: props.filters?.outlet_id || '',
  date_from: props.filters?.date_from || getFirstDayOfMonth(),
  date_to: props.filters?.date_to || getLastDayOfMonth(),
  search: props.filters?.search || '',
  per_page: props.filters?.per_page || 10,
});

// Watch for pagination updates from props
const pagination = computed(() => props.pagination || {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0,
});

function toggleExpand(prId) {
  const index = expandedRows.value.indexOf(prId);
  if (index > -1) {
    expandedRows.value.splice(index, 1);
  } else {
    expandedRows.value.push(prId);
  }
}

function applyFilters() {
  const filterData = {
    ...filters.value,
    has_filter: true, // Flag untuk menandai bahwa filter sudah diklik
  };
  router.get('/travel-kasbon-report', filterData, {
    preserveState: true,
    preserveScroll: true,
  });
}

function resetFilters() {
  filters.value = {
    mode: 'all',
    status: 'all',
    division_id: '',
    outlet_id: '',
    date_from: getFirstDayOfMonth(),
    date_to: getLastDayOfMonth(),
    search: '',
    per_page: 10,
  };
  // Reset tidak perlu load data, hanya reset form
  // applyFilters(); // Comment out agar tidak auto-load
}

function goToPage(page) {
  const paginationData = pagination.value;
  if (page < 1 || page > paginationData.last_page) return;
  const newFilters = { 
    ...filters.value, 
    page: page,
    has_filter: true, // Keep filter flag when paginating
  };
  router.get('/travel-kasbon-report', newFilters, {
    preserveState: true,
    preserveScroll: false,
  });
}

function getPageNumbers() {
  const paginationData = pagination.value;
  const current = paginationData.current_page;
  const last = paginationData.last_page;
  const pages = [];
  
  if (last <= 7) {
    for (let i = 1; i <= last; i++) {
      pages.push(i);
    }
  } else {
    if (current <= 3) {
      for (let i = 1; i <= 5; i++) {
        pages.push(i);
      }
      pages.push('...');
      pages.push(last);
    } else if (current >= last - 2) {
      pages.push(1);
      pages.push('...');
      for (let i = last - 4; i <= last; i++) {
        pages.push(i);
      }
    } else {
      pages.push(1);
      pages.push('...');
      for (let i = current - 1; i <= current + 1; i++) {
        pages.push(i);
      }
      pages.push('...');
      pages.push(last);
    }
  }
  
  return pages;
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function formatDateTime(dateTime) {
  if (!dateTime) return '-';
  return new Date(dateTime).toLocaleString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function formatCurrency(value) {
  if (value == null) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
  }).format(value);
}

function formatFileSize(bytes) {
  if (!bytes) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getStatusClass(status) {
  const classes = {
    'DRAFT': 'bg-gray-100 text-gray-800',
    'SUBMITTED': 'bg-blue-100 text-blue-800',
    'APPROVED': 'bg-green-100 text-green-800',
    'REJECTED': 'bg-red-100 text-red-800',
    'PROCESSED': 'bg-yellow-100 text-yellow-800',
    'COMPLETED': 'bg-purple-100 text-purple-800',
    'PAID': 'bg-emerald-100 text-emerald-800',
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

function getPriorityClass(priority) {
  const classes = {
    'LOW': 'bg-green-100 text-green-800',
    'MEDIUM': 'bg-yellow-100 text-yellow-800',
    'HIGH': 'bg-orange-100 text-orange-800',
    'URGENT': 'bg-red-100 text-red-800',
  };
  return classes[priority] || 'bg-gray-100 text-gray-800';
}

// Helper functions for file handling
function isImageFile(fileName) {
  if (!fileName) return false;
  const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp', '.svg'];
  const lowerFileName = fileName.toLowerCase();
  return imageExtensions.some(ext => lowerFileName.endsWith(ext));
}

function getFileIcon(fileName) {
  if (!fileName) return 'fa fa-file';
  const lowerFileName = fileName.toLowerCase();
  if (lowerFileName.endsWith('.pdf')) return 'fa fa-file-pdf';
  if (lowerFileName.endsWith('.doc') || lowerFileName.endsWith('.docx')) return 'fa fa-file-word';
  if (lowerFileName.endsWith('.xls') || lowerFileName.endsWith('.xlsx')) return 'fa fa-file-excel';
  return 'fa fa-file';
}

function getAttachmentUrl(attachment) {
  if (attachment.id) {
    // Use download route if available
    return `/purchase-requisitions/attachments/${attachment.id}/download`;
  }
  // Fallback to direct storage path
  return attachment.file_path ? `/storage/${attachment.file_path}` : '#';
}

function openLightbox(imageUrl, fileName) {
  lightboxImage.value = imageUrl;
  lightboxImageName.value = fileName;
}

function closeLightbox() {
  lightboxImage.value = null;
  lightboxImageName.value = '';
}

function handleFileDownload(event, fileName) {
  // Let the browser handle the download naturally
  // The href will trigger the download
}

function handleImageError(event) {
  // If image fails to load, show a placeholder
  event.target.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100"%3E%3Crect fill="%23ddd" width="100" height="100"/%3E%3Ctext fill="%23999" font-family="sans-serif" font-size="12" x="50%25" y="50%25" text-anchor="middle" dy=".3em"%3EImage%3C/text%3E%3C/svg%3E';
}
</script>

