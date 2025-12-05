<template>
  <AppLayout title="Payment">
    <div class="w-full py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
          <i class="fa-solid fa-shopping-cart text-blue-500"></i> Payment
        </h1>
        <div class="flex gap-3">
          <button @click="openCreate" class="bg-gradient-to-r from-blue-500 to-blue-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold">
            + Buat Payment Baru
          </button>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-blue-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total PR</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
            </div>
            <i class="fa-solid fa-shopping-cart text-4xl text-blue-300"></i>
          </div>
        </div>
        <!-- Draft -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-gray-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Draft</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.draft }}</p>
            </div>
            <i class="fa-solid fa-edit text-4xl text-gray-300"></i>
          </div>
        </div>
        <!-- Submitted -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-yellow-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Submitted</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.submitted }}</p>
            </div>
            <i class="fa-solid fa-paper-plane text-4xl text-yellow-300"></i>
          </div>
        </div>
        <!-- Approved -->
        <div class="rounded-xl shadow-lg p-6 border-l-4 border-green-500 bg-white hover:shadow-xl transition-all">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Approved</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.approved }}</p>
            </div>
            <i class="fa-solid fa-check-circle text-4xl text-green-300"></i>
          </div>
        </div>
      </div>

      <!-- Filter and Search -->
      <div class="flex flex-col gap-4 mb-6">
        <!-- Search and Basic Filters -->
        <div class="flex flex-col md:flex-row gap-4">
          <input
            type="text"
            v-model="search"
            @input="onSearchInput"
            placeholder="Cari semua kolom (PR number, title, creator, division, outlet, dll)..."
            class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          />
          <select
            v-model="status"
            @change="debouncedSearch"
            class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          >
            <option value="all">Semua Status</option>
            <option value="DRAFT">Draft</option>
            <option value="SUBMITTED">Submitted</option>
            <option value="APPROVED">Approved</option>
            <option value="REJECTED">Rejected</option>
            <option value="PROCESSED">Processed</option>
            <option value="COMPLETED">Completed</option>
            <option value="PAID">Paid</option>
          </select>
          <select
            v-model="division"
            @change="debouncedSearch"
            class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          >
            <option value="all">Semua Divisi</option>
            <option v-for="d in filterOptions.divisions" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
          </select>
          <select
            v-model="category"
            @change="debouncedSearch"
            class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          >
            <option value="all">Semua Category</option>
            <option v-for="c in filterOptions.categories" :key="c.id" :value="c.id">
              [{{ c.division }}] {{ c.name }}
            </option>
          </select>
          <select
            v-model="isHeld"
            @change="debouncedSearch"
            class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          >
            <option value="all">Semua Status Hold</option>
            <option value="held">Hold</option>
            <option value="not_held">Tidak Hold</option>
          </select>
          <select
            v-model="perPage"
            @change="debouncedSearch"
            class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
          >
            <option value="15">15 Per Halaman</option>
            <option value="30">30 Per Halaman</option>
            <option value="50">50 Per Halaman</option>
          </select>
        </div>
        <!-- Date Range Filter -->
        <div class="flex flex-col md:flex-row gap-4 items-end">
          <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700 whitespace-nowrap">Dari Tanggal:</label>
            <input
              type="date"
              v-model="dateFrom"
              class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
            />
          </div>
          <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700 whitespace-nowrap">Sampai Tanggal:</label>
            <input
              type="date"
              v-model="dateTo"
              class="px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
            />
          </div>
          <button
            @click="loadData"
            class="px-6 py-2 rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition text-sm font-medium shadow-md hover:shadow-lg flex items-center gap-2"
          >
            <i class="fas fa-search mr-1"></i>
            Load Data
          </button>
          <button
            v-if="dateFrom || dateTo"
            @click="clearDateFilter"
            class="px-4 py-2 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition text-sm font-medium"
          >
            <i class="fas fa-times mr-1"></i>
            Hapus Filter Tanggal
          </button>
        </div>
      </div>

      <!-- Table -->
      <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PR Number</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Division</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mode</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO/Payment</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hold/Release</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Creator</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="pr in data.data" :key="pr.id" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                  {{ pr.pr_number }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <div class="max-w-xs truncate" :title="pr.title">
                    {{ pr.title }}
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <span v-if="pr.division" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="getDivisionBadgeClass(pr.division.nama_divisi)">
                    {{ pr.division.nama_divisi }}
                  </span>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                  <div class="flex flex-col gap-1">
                    <span v-for="(outlet, idx) in getOutletList(pr)" :key="idx" class="inline-block">
                      {{ outlet }}
                    </span>
                    <span v-if="getOutletList(pr).length === 0" class="text-gray-400">-</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <span class="font-semibold text-green-600">
                    {{ formatCurrency(pr.amount) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                  <div class="flex flex-col gap-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="getModeBadgeClass(pr.mode)">
                      {{ getModeLabel(pr.mode) }}
                    </span>
                    <!-- Category Information -->
                    <div v-if="getCategoriesList(pr).length > 0" class="flex flex-col gap-1 mt-1 max-h-32 overflow-y-auto">
                      <div v-for="(category, idx) in getCategoriesList(pr).slice(0, 3)" :key="idx" 
                           class="flex flex-col gap-0.5 p-1.5 bg-blue-50 rounded border border-blue-100">
                        <span class="text-xs font-medium text-gray-800">{{ category.name }}</span>
                      <div class="flex items-center gap-1 flex-wrap">
                          <span v-if="category.division" class="text-xs text-gray-600 bg-white px-1.5 py-0.5 rounded border border-gray-200">
                            {{ category.division }}
                        </span>
                          <span v-if="category.subcategory" class="text-xs text-gray-600 bg-white px-1.5 py-0.5 rounded border border-gray-200">
                            {{ category.subcategory }}
                        </span>
                        </div>
                      </div>
                      <div v-if="getCategoriesList(pr).length > 3" class="text-xs text-gray-500 italic mt-1 px-1.5">
                        +{{ getCategoriesList(pr).length - 3 }} category lainnya
                      </div>
                    </div>
                    <span v-else class="text-xs text-gray-400 italic">No category</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex flex-col gap-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                          :class="getStatusColor(pr.status)">
                      {{ pr.status }}
                    </span>
                    <!-- Hold Indicator -->
                    <span v-if="pr.is_held" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-gradient-to-r from-red-500 to-red-600 text-white shadow-md">
                      <i class="fas fa-lock mr-1"></i>
                      ON HOLD
                    </span>
                    <div v-if="pr.is_held && pr.hold_reason" class="text-xs text-red-600 mt-1 italic" :title="pr.hold_reason">
                      {{ pr.hold_reason.length > 30 ? pr.hold_reason.substring(0, 30) + '...' : pr.hold_reason }}
                    </div>
                    <!-- Pending Approval Info -->
                    <div v-if="getPendingApprover(pr)" class="mt-1">
                      <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-1.5">
                        <div class="flex items-center gap-1.5">
                          <i class="fas fa-clock text-yellow-600 text-xs"></i>
                          <div class="text-xs">
                            <p class="font-medium text-yellow-800 leading-tight">Menunggu Approval</p>
                            <p class="text-yellow-700 leading-tight">{{ getPendingApprover(pr) }}</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex flex-col gap-2">
                    <!-- PO Status Indicator -->
                    <button
                      v-if="pr.has_po"
                      @click="openPODetailModal(pr)"
                      class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-bold bg-gradient-to-r from-blue-500 to-blue-600 text-white shadow-md hover:shadow-lg hover:from-blue-600 hover:to-blue-700 transition-all cursor-pointer"
                      title="Klik untuk melihat detail PO"
                    >
                      <i class="fas fa-file-invoice mr-2 text-lg"></i>
                      <span class="font-semibold">PO: {{ pr.po_count }} {{ pr.po_count > 1 ? 'POs' : 'PO' }}</span>
                    </button>
                    <div v-else class="text-xs text-gray-400 italic">Belum ada PO</div>
                    
                    <!-- Payment Status Indicator -->
                    <button
                      v-if="pr.has_payment"
                      @click="openPaymentDetailModal(pr)"
                      class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-bold bg-gradient-to-r from-green-500 to-green-600 text-white shadow-md hover:shadow-lg hover:from-green-600 hover:to-green-700 transition-all cursor-pointer"
                      title="Klik untuk melihat detail Payment"
                    >
                      <i class="fas fa-check-circle mr-2 text-lg"></i>
                      <span class="font-semibold">Paid: {{ pr.payment_count }} {{ pr.payment_count > 1 ? 'payments' : 'payment' }}</span>
                    </button>
                    <div v-else-if="pr.has_po" class="text-xs text-orange-500 italic">Belum dibayar</div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex justify-center">
                    <!-- Hold Button -->
                    <button
                      v-if="!pr.is_held && pr.status !== 'PAID' && !pr.has_payment && (pr.status === 'APPROVED' || pr.status === 'PROCESSED' || pr.status === 'SUBMITTED')"
                      @click="holdPR(pr)"
                      class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold bg-gradient-to-r from-orange-500 to-orange-600 text-white shadow-md hover:shadow-lg hover:from-orange-600 hover:to-orange-700 transition-all"
                      title="Hold PR"
                    >
                      <i class="fas fa-lock text-base"></i>
                      <span>Hold</span>
                    </button>
                    <!-- Release Button -->
                    <button
                      v-if="pr.is_held && pr.status !== 'PAID' && !pr.has_payment"
                      @click="releasePR(pr)"
                      class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold bg-gradient-to-r from-green-500 to-green-600 text-white shadow-md hover:shadow-lg hover:from-green-600 hover:to-green-700 transition-all"
                      title="Release PR"
                    >
                      <i class="fas fa-unlock text-base"></i>
                      <span>Release</span>
                    </button>
                    <!-- No Action Available -->
                    <span v-if="pr.status === 'PAID' || pr.has_payment || (pr.is_held === false && !(pr.status === 'APPROVED' || pr.status === 'PROCESSED' || pr.status === 'SUBMITTED'))" class="text-xs text-gray-400 italic">
                      -
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-8 w-8">
                      <!-- Avatar User Creator -->
                      <div v-if="pr.creator?.avatar" class="w-8 h-8 rounded-full overflow-hidden cursor-pointer hover:scale-110 transition-transform border-2 border-gray-200" @click="openImageModal(`/storage/${pr.creator.avatar}`)">
                        <img 
                          :src="`/storage/${pr.creator.avatar}`" 
                          :alt="pr.creator?.nama_lengkap || 'User'"
                          class="w-full h-full object-cover"
                        />
                      </div>
                      <div 
                        v-else 
                        class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold border-2 border-gray-200"
                      >
                        {{ getInitials(pr.creator?.nama_lengkap || 'U') }}
                      </div>
                    </div>
                    <div class="ml-3">
                      <div class="text-sm font-medium text-gray-900">{{ pr.creator?.nama_lengkap || 'Unknown' }}</div>
                      <div class="text-xs text-gray-500">{{ pr.creator?.email || '' }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                  <div>{{ formatDate(pr.created_at) }}</div>
                  <div class="text-xs text-gray-400 mt-0.5">{{ formatTime(pr.created_at) }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex space-x-2">
                    <button
                      @click="viewPR(pr)"
                      class="text-blue-600 hover:text-blue-900"
                      title="View Details"
                    >
                      <i class="fas fa-eye"></i>
                    </button>
                    <button
                      @click="openCommentModal(pr)"
                      class="relative text-indigo-600 hover:text-indigo-900"
                      title="Comments"
                    >
                      <i class="fas fa-comment"></i>
                      <span 
                        v-if="pr.unread_comments_count > 0"
                        class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center"
                      >
                        {{ pr.unread_comments_count > 99 ? '99+' : pr.unread_comments_count }}
                      </span>
                    </button>
                    <button
                      v-if="pr.status === 'DRAFT' || pr.status === 'SUBMITTED'"
                      @click="editPR(pr)"
                      class="text-green-600 hover:text-green-900"
                      title="Edit"
                    >
                      <i class="fas fa-edit"></i>
                    </button>
                    <button
                      @click="printSinglePR(pr)"
                      class="text-purple-600 hover:text-purple-900"
                      title="Print PDF"
                    >
                      <i class="fas fa-print"></i>
                    </button>
                    <button
                      v-if="pr.status === 'DRAFT' || pr.status === 'SUBMITTED' || (pr.status === 'APPROVED' && props.auth?.user?.id_role === '5af56935b011a')"
                      @click="deletePR(pr)"
                      :disabled="!canDelete(pr)"
                      :class="[
                        'text-red-600 hover:text-red-900',
                        !canDelete(pr) ? 'opacity-50 cursor-not-allowed' : ''
                      ]"
                      :title="canDelete(pr) ? 'Delete' : getDeleteTooltip(pr)"
                    >
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="data.data.length === 0" class="text-center py-12">
          <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-shopping-cart text-3xl text-gray-400"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-600 mb-2">No Payments Found</h3>
          <p class="text-gray-500 mb-6">Start by creating your first payment</p>
          <button @click="openCreate" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors">
            <i class="fa-solid fa-plus mr-2"></i>
            Create New Payment
          </button>
        </div>
      </div>

      <!-- Pagination -->
      <div class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-4">
        <!-- Pagination Info -->
        <div class="text-sm text-gray-600">
          Menampilkan {{ data.from || 0 }} sampai {{ data.to || 0 }} dari {{ data.total || 0 }} payments
        </div>
        
        <!-- Pagination Navigation -->
        <nav class="flex rounded-lg shadow-sm -space-x-px" aria-label="Pagination">
          <button 
            @click="goToPage(data.first_page_url)" 
            :disabled="!data.first_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 rounded-l-lg transition-colors',
              !data.first_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50'
            ]"
          >
            First
          </button>
          <button 
            @click="goToPage(data.prev_page_url)" 
            :disabled="!data.prev_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 transition-colors',
              !data.prev_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50'
            ]"
          >
            Previous
          </button>
          <template v-for="(link, i) in data.links" :key="i">
            <button 
              v-if="link.url" 
              @click="goToPage(link.url)" 
              :class="[
                'px-3 py-2 text-sm border border-gray-300 transition-colors',
                link.active 
                  ? 'bg-blue-600 text-white border-blue-600' 
                  : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'
              ]" 
              v-html="link.label"
            ></button>
            <span 
              v-else 
              class="px-3 py-2 text-sm border border-gray-200 text-gray-400 bg-gray-50" 
              v-html="link.label"
            ></span>
          </template>
          <button 
            @click="goToPage(data.next_page_url)" 
            :disabled="!data.next_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 transition-colors',
              !data.next_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50'
            ]"
          >
            Next
          </button>
          <button 
            @click="goToPage(data.last_page_url)" 
            :disabled="!data.last_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 rounded-r-lg transition-colors',
              !data.last_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50'
            ]"
          >
            Last
          </button>
        </nav>
      </div>
    </div>

    <!-- Print Preview Modal -->
    <div v-if="showPrintModal" class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-6xl p-6 relative">
        <button @click="closePrintModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium">Preview Payment</h3>
          <div class="flex gap-2">
            <button 
              @click="printPreview"
              class="px-3 py-1 text-sm bg-blue-100 text-blue-600 rounded hover:bg-blue-200 flex items-center gap-1"
            >
              <i class="fas fa-print"></i>
              Print
            </button>
          </div>
        </div>
        <div class="p-4" style="height: 80vh;">
          <iframe 
            :src="previewUrl" 
            class="w-full h-full border-0" 
            ref="previewFrame"
          ></iframe>
        </div>
      </div>
    </div>

    <!-- Image Modal -->
    <div v-if="showImageModal" class="fixed inset-0 z-[100001] flex items-center justify-center bg-black/80" @click="closeImageModal">
      <div class="relative max-w-4xl max-h-[90vh] p-4">
        <button @click="closeImageModal" class="absolute top-2 right-2 text-white hover:text-gray-300 z-10 bg-black/50 rounded-full p-2">
          <i class="fas fa-times text-xl"></i>
        </button>
        <img 
          :src="imageModalUrl" 
          alt="Avatar" 
          class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl"
          @click.stop
        />
      </div>
    </div>

    <!-- PO Detail Modal -->
    <div v-if="showPODetailModal" class="fixed inset-0 z-[100002] flex items-center justify-center bg-black/40" @click="closePODetailModal">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative max-h-[90vh] overflow-y-auto" @click.stop>
        <button @click="closePODetailModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <div class="mb-4">
          <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-file-invoice text-blue-500"></i>
            Detail Purchase Order
          </h3>
          <p class="text-sm text-gray-500 mt-1">PR Number: {{ selectedPR?.pr_number }}</p>
        </div>
        <div v-if="selectedPR && selectedPR.po_details && selectedPR.po_details.length > 0" class="space-y-3">
          <div v-for="(po, index) in selectedPR.po_details" :key="index" 
               class="bg-blue-50 border border-blue-200 rounded-lg p-4 hover:bg-blue-100 transition-colors">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold flex-shrink-0">
                {{ index + 1 }}
              </div>
              <div class="flex-1">
                <div class="font-semibold text-blue-900 mb-1">{{ po.number }}</div>
                <div class="text-xs text-blue-600 mb-2">Purchase Order</div>
                <div class="space-y-1 text-xs text-gray-600">
                  <div class="flex items-center gap-2">
                    <i class="fas fa-calendar text-gray-400"></i>
                    <span>{{ formatDate(po.created_at) }} {{ formatTime(po.created_at) }}</span>
                  </div>
                  <div v-if="po.creator_name" class="flex items-center gap-2">
                    <i class="fas fa-user text-gray-400"></i>
                    <span>{{ po.creator_name }}</span>
                    <span v-if="po.creator_email" class="text-gray-400">({{ po.creator_email }})</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          <i class="fas fa-file-invoice text-4xl mb-4 text-gray-300"></i>
          <p>Tidak ada Purchase Order</p>
        </div>
      </div>
    </div>

    <!-- Payment Detail Modal -->
    <div v-if="showPaymentDetailModal" class="fixed inset-0 z-[100002] flex items-center justify-center bg-black/40" @click="closePaymentDetailModal">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative max-h-[90vh] overflow-y-auto" @click.stop>
        <button @click="closePaymentDetailModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <div class="mb-4">
          <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-check-circle text-green-500"></i>
            Detail Payment
          </h3>
          <p class="text-sm text-gray-500 mt-1">PR Number: {{ selectedPR?.pr_number }}</p>
        </div>
        <div v-if="selectedPR && selectedPR.payment_details && selectedPR.payment_details.length > 0" class="space-y-3">
          <div v-for="(payment, index) in selectedPR.payment_details" :key="index" 
               class="bg-green-50 border border-green-200 rounded-lg p-4 hover:bg-green-100 transition-colors">
            <div class="flex items-start gap-3">
              <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-bold flex-shrink-0">
                {{ index + 1 }}
              </div>
              <div class="flex-1">
                <div class="font-semibold text-green-900 mb-1">{{ payment.payment_number }}</div>
                <div class="text-xs text-green-600 mb-2">Non-Food Payment</div>
                <div class="space-y-1 text-xs text-gray-600">
                  <div class="flex items-center gap-2">
                    <i class="fas fa-calendar text-gray-400"></i>
                    <span>{{ formatDate(payment.created_at) }} {{ formatTime(payment.created_at) }}</span>
                  </div>
                  <div v-if="payment.creator_name" class="flex items-center gap-2">
                    <i class="fas fa-user text-gray-400"></i>
                    <span>{{ payment.creator_name }}</span>
                    <span v-if="payment.creator_email" class="text-gray-400">({{ payment.creator_email }})</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          <i class="fas fa-check-circle text-4xl mb-4 text-gray-300"></i>
          <p>Tidak ada Payment</p>
        </div>
      </div>
    </div>

    <!-- Edit Comment Modal -->
    <div v-if="showEditCommentModal" class="fixed inset-0 z-[100004] flex items-center justify-center bg-black/40" @click="closeEditCommentModal">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 relative" @click.stop>
        <button @click="closeEditCommentModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <div class="mb-4">
          <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-edit text-blue-500"></i>
            Edit Comment
          </h3>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Comment</label>
            <textarea
              v-model="editingComment.comment"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              placeholder="Enter your comment..."
            ></textarea>
          </div>

          <div>
            <label class="flex items-center">
              <input
                v-model="editingComment.is_internal"
                type="checkbox"
                class="mr-2"
              />
              <span class="text-sm text-gray-600">Internal comment</span>
            </label>
          </div>

          <div class="flex justify-end gap-2">
            <button
              @click="closeEditCommentModal"
              class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300"
            >
              Cancel
            </button>
            <button
              @click="updateComment"
              :disabled="!editingComment.comment.trim() || updatingComment"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
            >
              <i v-if="updatingComment" class="fas fa-spinner fa-spin mr-2"></i>
              {{ updatingComment ? 'Updating...' : 'Update Comment' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Image Lightbox Modal -->
    <div v-if="showImageLightbox" class="fixed inset-0 z-[100005] flex items-center justify-center bg-black/80" @click="closeImageLightbox">
      <div class="relative max-w-4xl max-h-[90vh] p-4" @click.stop>
        <button @click="closeImageLightbox" class="absolute top-2 right-2 text-white hover:text-gray-300 z-10 bg-black/50 rounded-full p-2">
          <i class="fas fa-times text-xl"></i>
        </button>
        <img
          v-if="lightboxImage"
          :src="lightboxImageUrl"
          :alt="lightboxImage.attachment_name"
          class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl"
        />
        <div class="absolute bottom-4 left-4 right-4 text-center">
          <p class="text-white bg-black/50 px-3 py-1 rounded-lg text-sm">
            {{ lightboxImage?.attachment_name }}
          </p>
        </div>
      </div>
    </div>

    <!-- Comment Modal -->
    <div v-if="showCommentModal" class="fixed inset-0 z-[100003] flex items-center justify-center bg-black/40" @click="closeCommentModal" style="z-index: 100003;">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-3xl p-6 relative max-h-[90vh] overflow-y-auto" @click.stop>
        <button @click="closeCommentModal" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
          <i class="fas fa-times text-lg"></i>
        </button>
        <div class="mb-4">
          <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            <i class="fas fa-comment text-indigo-500"></i>
            Comments
          </h3>
          <p class="text-sm text-gray-500 mt-1">PR Number: {{ selectedPRForComment?.pr_number }}</p>
        </div>

        <!-- Add Comment Form -->
        <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
          <textarea
            v-model="newComment"
            rows="3"
            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
            placeholder="Add a comment..."
          ></textarea>
          
          <!-- Attachment Upload -->
          <div class="mt-3">
            <label class="block text-sm font-medium text-gray-700 mb-2">
              Attachment (Optional)
            </label>
            <div class="flex items-center gap-2">
              <input
                type="file"
                ref="attachmentInput"
                @change="handleAttachmentChange"
                accept="image/*,.pdf,.doc,.docx,.xls,.xlsx"
                class="hidden"
              />
              <button
                @click="$refs.attachmentInput.click()"
                type="button"
                class="px-3 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 text-sm"
              >
                <i class="fas fa-paperclip mr-2"></i>
                Choose File
              </button>
              <span v-if="selectedAttachment" class="text-sm text-gray-600 flex items-center gap-2">
                <i class="fas fa-file"></i>
                {{ selectedAttachment.name }}
                <button
                  @click="removeAttachment"
                  class="text-red-500 hover:text-red-700"
                >
                  <i class="fas fa-times"></i>
                </button>
              </span>
              <span v-else class="text-sm text-gray-400">No file selected</span>
            </div>
            <p class="text-xs text-gray-500 mt-1">Max size: 10MB (Images, PDF, Word, Excel)</p>
          </div>

          <div class="mt-3 flex items-center justify-between">
            <label class="flex items-center">
              <input
                v-model="isInternalComment"
                type="checkbox"
                class="mr-2"
              />
              <span class="text-sm text-gray-600">Internal comment</span>
            </label>
            <button
              @click="addCommentToPR"
              :disabled="!newComment.trim() || uploadingComment"
              class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i v-if="uploadingComment" class="fas fa-spinner fa-spin mr-2"></i>
              <i v-else class="fas fa-paper-plane mr-2"></i>
              {{ uploadingComment ? 'Uploading...' : 'Add Comment' }}
            </button>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="loadingComments" class="text-center py-8">
          <i class="fas fa-spinner fa-spin text-4xl text-indigo-500 mb-4"></i>
          <p class="text-gray-500">Loading comments...</p>
        </div>

        <!-- Comments List -->
        <div v-else-if="comments.length > 0" class="space-y-4">
          <div
            v-for="comment in comments"
            :key="comment.id"
            class="p-4 border border-gray-200 rounded-lg bg-white hover:bg-gray-50 transition-colors"
          >
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center space-x-2">
                <span class="font-medium text-gray-900">{{ comment.user?.nama_lengkap || 'Unknown User' }}</span>
                <span v-if="comment.is_internal" class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">
                  Internal
                </span>
              </div>
              <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">{{ formatDate(comment.created_at) }} {{ formatTime(comment.created_at) }}</span>
                <!-- Edit/Delete buttons (only for own comments) -->
                <div v-if="comment.user_id === props.auth?.user?.id" class="flex items-center gap-1">
                  <button
                    @click="editComment(comment)"
                    class="text-blue-600 hover:text-blue-800 text-sm"
                    title="Edit comment"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button
                    @click="deleteCommentConfirm(comment)"
                    class="text-red-600 hover:text-red-800 text-sm"
                    title="Delete comment"
                  >
                    <i class="fas fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
            <p class="text-gray-700 whitespace-pre-wrap">{{ comment.comment }}</p>
            
            <!-- Attachment Display -->
            <div v-if="comment.attachment_path" class="mt-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
              <div class="flex items-center gap-3">
                <!-- Image Preview -->
                <div v-if="isImageFile(comment.attachment_mime_type)" class="flex-shrink-0">
                  <img
                    :src="getAttachmentUrl(comment)"
                    :alt="comment.attachment_name"
                    class="w-24 h-24 object-cover rounded-lg border border-gray-300 cursor-pointer hover:opacity-80 transition-opacity"
                    @click="openImageLightbox(comment)"
                  />
                </div>
                <!-- File Icon for non-images -->
                <div v-else class="flex-shrink-0">
                  <i :class="getFileIcon(comment.attachment_name)" class="text-3xl"></i>
                </div>
                
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-gray-900 truncate">{{ comment.attachment_name }}</p>
                  <p class="text-xs text-gray-500">{{ formatFileSize(comment.attachment_size) }}</p>
                </div>
                
                <div class="flex items-center gap-2">
                  <a
                    :href="getAttachmentUrl(comment)"
                    :download="comment.attachment_name"
                    class="px-3 py-1.5 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm"
                  >
                    <i class="fas fa-download mr-1"></i>
                    Download
                  </a>
                  <button
                    v-if="isImageFile(comment.attachment_mime_type)"
                    @click="openImageLightbox(comment)"
                    class="px-3 py-1.5 bg-green-600 text-white rounded-md hover:bg-green-700 text-sm"
                  >
                    <i class="fas fa-eye mr-1"></i>
                    View
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          <i class="fas fa-comment text-4xl mb-4 text-gray-300"></i>
          <p>No comments yet</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  data: Object,
  filters: Object,
  filterOptions: Object,
  statistics: {
    type: Object,
    default: () => ({
      total: 0,
      draft: 0,
      submitted: 0,
      approved: 0
    })
  },
  auth: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const division = ref(props.filters?.division || 'all');
const category = ref(props.filters?.category || 'all');
const isHeld = ref(props.filters?.is_held || 'all');
// Set default date range to current month if not provided
const getDefaultDateFrom = () => {
  if (props.filters?.date_from) return props.filters.date_from;
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
};
const getDefaultDateTo = () => {
  if (props.filters?.date_to) return props.filters.date_to;
  const now = new Date();
  const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  return `${lastDay.getFullYear()}-${String(lastDay.getMonth() + 1).padStart(2, '0')}-${String(lastDay.getDate()).padStart(2, '0')}`;
};
const dateFrom = ref(getDefaultDateFrom());
const dateTo = ref(getDefaultDateTo());
const perPage = ref(props.filters?.per_page || 15);

// Print functionality
const showPrintModal = ref(false);
const printData = ref([]);
const previewUrl = ref('');
const previewFrame = ref(null);

// Image modal functionality
const showImageModal = ref(false);
const imageModalUrl = ref('');

// PO and Payment Detail Modals
const showPODetailModal = ref(false);
const showPaymentDetailModal = ref(false);
const selectedPR = ref(null);

// Comment Modal
const showCommentModal = ref(false);
const selectedPRForComment = ref(null);
const comments = ref([]);
const newComment = ref('');
const isInternalComment = ref(false);
const loadingComments = ref(false);
const uploadingComment = ref(false);
const selectedAttachment = ref(null);
const attachmentInput = ref(null);

// Edit Comment Modal
const showEditCommentModal = ref(false);
const editingComment = ref({});
const updatingComment = ref(false);

// Image Lightbox
const showImageLightbox = ref(false);
const lightboxImage = ref(null);

function getInitials(name) {
  if (!name) return 'U';
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

function openImageModal(imageUrl) {
  imageModalUrl.value = imageUrl;
  showImageModal.value = true;
}

function closeImageModal() {
  showImageModal.value = false;
  imageModalUrl.value = '';
}

// PO and Payment Detail Modal Functions
function openPODetailModal(pr) {
  selectedPR.value = pr;
  showPODetailModal.value = true;
}

function closePODetailModal() {
  showPODetailModal.value = false;
  selectedPR.value = null;
}

function openPaymentDetailModal(pr) {
  selectedPR.value = pr;
  showPaymentDetailModal.value = true;
}

function closePaymentDetailModal() {
  showPaymentDetailModal.value = false;
  selectedPR.value = null;
}

// Comment Modal Functions
async function openCommentModal(pr) {
  selectedPRForComment.value = pr;
  showCommentModal.value = true;
  newComment.value = '';
  isInternalComment.value = false;
  loadingComments.value = true;
  comments.value = [];
  
  try {
    const response = await axios.get(`/purchase-requisitions/${pr.id}/comments`);
    if (response.data.success) {
      comments.value = response.data.data;
      // Update unread count to 0 after loading comments
      if (pr.unread_comments_count > 0) {
        pr.unread_comments_count = 0;
      }
    }
  } catch (error) {
    console.error('Error loading comments:', error);
    Swal.fire({
      title: 'Error',
      text: 'Failed to load comments',
      icon: 'error',
      didOpen: () => {
        const setZIndex = () => {
          const swalContainer = document.querySelector('.swal2-container');
          if (swalContainer) {
            swalContainer.style.setProperty('z-index', '999999', 'important');
          }
        };
        setZIndex();
        setTimeout(setZIndex, 10);
      }
    });
  } finally {
    loadingComments.value = false;
  }
}

function closeCommentModal() {
  showCommentModal.value = false;
  selectedPRForComment.value = null;
  comments.value = [];
  newComment.value = '';
  isInternalComment.value = false;
  removeAttachment();
}

function handleAttachmentChange(event) {
  const file = event.target.files[0];
  if (file) {
    // Check file size (10MB max)
    if (file.size > 10 * 1024 * 1024) {
      Swal.fire({
        title: 'Error',
        text: 'File size must be less than 10MB',
        icon: 'error',
        didOpen: () => {
          const setZIndex = () => {
            const swalContainer = document.querySelector('.swal2-container');
            if (swalContainer) {
              swalContainer.style.setProperty('z-index', '999999', 'important');
            }
          };
          setZIndex();
          setTimeout(setZIndex, 10);
        }
      });
      event.target.value = '';
      return;
    }
    selectedAttachment.value = file;
  }
}

function removeAttachment() {
  selectedAttachment.value = null;
  if (attachmentInput.value) {
    attachmentInput.value.value = '';
  }
}

async function addCommentToPR() {
  if (!newComment.value.trim() || !selectedPRForComment.value) return;
  
  uploadingComment.value = true;
  
  try {
    const formData = new FormData();
    formData.append('comment', newComment.value);
    formData.append('is_internal', isInternalComment.value ? '1' : '0');
    
    if (selectedAttachment.value) {
      formData.append('attachment', selectedAttachment.value);
    }
    
    const response = await axios.post(`/purchase-requisitions/${selectedPRForComment.value.id}/comments`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    
    if (response.status === 200 || response.status === 201) {
      // Reload comments
      const commentsResponse = await axios.get(`/purchase-requisitions/${selectedPRForComment.value.id}/comments`);
      if (commentsResponse.data.success) {
        comments.value = commentsResponse.data.data;
      }
      
      newComment.value = '';
      isInternalComment.value = false;
      removeAttachment();
      
      // Show success message
      Swal.fire({
        title: 'Success!',
        text: 'Comment added successfully',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        didOpen: () => {
          const setZIndex = () => {
            const swalContainer = document.querySelector('.swal2-container');
            if (swalContainer) {
              swalContainer.style.setProperty('z-index', '999999', 'important');
            }
          };
          setZIndex();
          setTimeout(setZIndex, 10);
        }
      });
    }
  } catch (error) {
    console.error('Error adding comment:', error);
    Swal.fire({
      title: 'Error',
      text: error.response?.data?.message || 'Failed to add comment',
      icon: 'error',
      didOpen: () => {
        const setZIndex = () => {
          const swalContainer = document.querySelector('.swal2-container');
          if (swalContainer) {
            swalContainer.style.setProperty('z-index', '999999', 'important');
          }
        };
        setZIndex();
        setTimeout(setZIndex, 10);
      }
    });
  } finally {
    uploadingComment.value = false;
  }
}

function editComment(comment) {
  editingComment.value = {
    id: comment.id,
    comment: comment.comment,
    is_internal: comment.is_internal || false,
  };
  showEditCommentModal.value = true;
}

function closeEditCommentModal() {
  showEditCommentModal.value = false;
  editingComment.value = {};
}

async function updateComment() {
  if (!editingComment.value.comment?.trim() || !selectedPRForComment.value) return;
  
  updatingComment.value = true;
  
  try {
    const response = await axios.put(
      `/purchase-requisitions/${selectedPRForComment.value.id}/comments/${editingComment.value.id}`,
      {
        comment: editingComment.value.comment,
        is_internal: editingComment.value.is_internal,
      }
    );
    
    if (response.data.success) {
      // Update comment in list
      const index = comments.value.findIndex(c => c.id === editingComment.value.id);
      if (index !== -1) {
        comments.value[index] = response.data.data;
      }
      
      closeEditCommentModal();
      
      Swal.fire({
        title: 'Success!',
        text: 'Comment updated successfully',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        didOpen: () => {
          const setZIndex = () => {
            const swalContainer = document.querySelector('.swal2-container');
            if (swalContainer) {
              swalContainer.style.setProperty('z-index', '999999', 'important');
            }
          };
          setZIndex();
          setTimeout(setZIndex, 10);
        }
      });
    }
  } catch (error) {
    console.error('Error updating comment:', error);
    Swal.fire({
      title: 'Error',
      text: error.response?.data?.message || 'Failed to update comment',
      icon: 'error',
      didOpen: () => {
        const setZIndex = () => {
          const swalContainer = document.querySelector('.swal2-container');
          if (swalContainer) {
            swalContainer.style.setProperty('z-index', '999999', 'important');
          }
        };
        setZIndex();
        setTimeout(setZIndex, 10);
      }
    });
  } finally {
    updatingComment.value = false;
  }
}

function deleteCommentConfirm(comment) {
  Swal.fire({
    title: 'Delete Comment?',
    text: 'Are you sure you want to delete this comment? This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#EF4444',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Yes, Delete!',
    cancelButtonText: 'Cancel',
    reverseButtons: true,
    allowOutsideClick: false,
    didOpen: () => {
      // Force z-index to be highest - use multiple attempts to ensure it works
      const setZIndex = () => {
        const swalContainer = document.querySelector('.swal2-container');
        if (swalContainer) {
          swalContainer.style.setProperty('z-index', '999999', 'important');
          swalContainer.style.setProperty('position', 'fixed', 'important');
        }
        const swalPopup = document.querySelector('.swal2-popup');
        if (swalPopup) {
          swalPopup.style.setProperty('z-index', '999999', 'important');
        }
        const swalBackdrop = document.querySelector('.swal2-backdrop-show');
        if (swalBackdrop) {
          swalBackdrop.style.setProperty('z-index', '999998', 'important');
        }
      };
      
      // Try multiple times to ensure it works
      setZIndex();
      setTimeout(setZIndex, 10);
      setTimeout(setZIndex, 50);
      setTimeout(setZIndex, 100);
    }
  }).then((result) => {
    if (result.isConfirmed) {
      deleteComment(comment);
    }
  });
}

async function deleteComment(comment) {
  try {
    const response = await axios.delete(
      `/purchase-requisitions/${selectedPRForComment.value.id}/comments/${comment.id}`
    );
    
    if (response.data.success) {
      // Remove comment from list
      comments.value = comments.value.filter(c => c.id !== comment.id);
      
      Swal.fire({
        title: 'Deleted!',
        text: 'Comment has been deleted',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false,
        didOpen: () => {
          // Force z-index to be highest
          const setZIndex = () => {
            const swalContainer = document.querySelector('.swal2-container');
            if (swalContainer) {
              swalContainer.style.setProperty('z-index', '999999', 'important');
              swalContainer.style.setProperty('position', 'fixed', 'important');
            }
            const swalPopup = document.querySelector('.swal2-popup');
            if (swalPopup) {
              swalPopup.style.setProperty('z-index', '999999', 'important');
            }
            const swalBackdrop = document.querySelector('.swal2-backdrop-show');
            if (swalBackdrop) {
              swalBackdrop.style.setProperty('z-index', '999998', 'important');
            }
          };
          setZIndex();
          setTimeout(setZIndex, 10);
          setTimeout(setZIndex, 50);
        }
      });
    }
  } catch (error) {
    console.error('Error deleting comment:', error);
    Swal.fire({
      title: 'Error',
      text: error.response?.data?.message || 'Failed to delete comment',
      icon: 'error',
      didOpen: () => {
        // Force z-index to be highest
        const setZIndex = () => {
          const swalContainer = document.querySelector('.swal2-container');
          if (swalContainer) {
            swalContainer.style.setProperty('z-index', '999999', 'important');
            swalContainer.style.setProperty('position', 'fixed', 'important');
          }
          const swalPopup = document.querySelector('.swal2-popup');
          if (swalPopup) {
            swalPopup.style.setProperty('z-index', '999999', 'important');
          }
          const swalBackdrop = document.querySelector('.swal2-backdrop-show');
          if (swalBackdrop) {
            swalBackdrop.style.setProperty('z-index', '999998', 'important');
          }
        };
        setZIndex();
        setTimeout(setZIndex, 10);
        setTimeout(setZIndex, 50);
      }
    });
  }
}

function getAttachmentUrl(comment) {
  if (!comment.attachment_path) return '';
  return `/storage/${comment.attachment_path}`;
}

function isImageFile(mimeType) {
  if (!mimeType) return false;
  return mimeType.startsWith('image/');
}

function getFileIcon(fileName) {
  if (!fileName) return 'fa-file text-gray-500';
  
  const extension = fileName.split('.').pop().toLowerCase();
  
  const iconMap = {
    'pdf': 'fa-file-pdf text-red-500',
    'doc': 'fa-file-word text-blue-500',
    'docx': 'fa-file-word text-blue-500',
    'xls': 'fa-file-excel text-green-500',
    'xlsx': 'fa-file-excel text-green-500',
    'ppt': 'fa-file-powerpoint text-orange-500',
    'pptx': 'fa-file-powerpoint text-orange-500',
    'jpg': 'fa-file-image text-purple-500',
    'jpeg': 'fa-file-image text-purple-500',
    'png': 'fa-file-image text-purple-500',
    'gif': 'fa-file-image text-purple-500',
    'txt': 'fa-file-alt text-gray-500',
    'zip': 'fa-file-archive text-yellow-500',
    'rar': 'fa-file-archive text-yellow-500',
  };
  
  return iconMap[extension] || 'fa-file text-gray-500';
}

function formatFileSize(bytes) {
  if (!bytes) return '0 Bytes';
  
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function openImageLightbox(comment) {
  lightboxImage.value = comment;
  showImageLightbox.value = true;
}

function closeImageLightbox() {
  showImageLightbox.value = false;
  lightboxImage.value = null;
}

const lightboxImageUrl = computed(() => {
  if (!lightboxImage.value) return '';
  return getAttachmentUrl(lightboxImage.value);
});

// Get pending approver info (similar to PR Tracking Report)
function getPendingApprover(pr) {
  // Check PR approval flows first
  if (pr.approval_flows && pr.approval_flows.length > 0) {
    const pendingFlow = pr.approval_flows.find(flow => flow.status === 'PENDING');
    if (pendingFlow) {
      return pendingFlow.approver?.nama_lengkap || 'Unknown';
    }
  }
  
  // Check PO approval flows if PR has PO
  if (pr.purchase_orders && pr.purchase_orders.length > 0) {
    for (const po of pr.purchase_orders) {
      if (po.approval_flows && po.approval_flows.length > 0) {
        const pendingPOFlow = po.approval_flows.find(flow => flow.status === 'PENDING');
        if (pendingPOFlow) {
          return `PO: ${pendingPOFlow.approver?.nama_lengkap || 'Unknown'}`;
        }
      }
    }
  }
  
  return null;
}

const debouncedSearch = debounce(() => {
  router.get('/purchase-requisitions', {
    search: search.value,
    status: status.value,
    division: division.value,
    category: category.value,
    is_held: isHeld.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

function loadData() {
  router.get('/purchase-requisitions', {
    search: search.value,
    status: status.value,
    division: division.value,
    category: category.value,
    is_held: isHeld.value,
    date_from: dateFrom.value,
    date_to: dateTo.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}

function clearDateFilter() {
  dateFrom.value = '';
  dateTo.value = '';
  loadData();
}

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (url) {
    const urlObj = new URL(url);
    urlObj.searchParams.set('search', search.value);
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('division', division.value);
    urlObj.searchParams.set('category', category.value);
    urlObj.searchParams.set('is_held', isHeld.value);
    urlObj.searchParams.set('date_from', dateFrom.value);
    urlObj.searchParams.set('date_to', dateTo.value);
    urlObj.searchParams.set('per_page', perPage.value);
    
    router.visit(urlObj.toString(), { preserveState: true, replace: true });
  }
}

function openCreate() {
  router.visit('/purchase-requisitions/create');
}

function viewPR(pr) {
  router.visit(`/purchase-requisitions/${pr.id}`);
}

function editPR(pr) {
  router.visit(`/purchase-requisitions/${pr.id}/edit`);
}

function canDelete(pr) {
  // Allow delete for DRAFT status and if user is the creator
  // Also allow delete for SUBMITTED status (not yet approved) if user is the creator
  // Allow delete for APPROVED status if user has id_role = '5af56935b011a'
  // If user has special role (id_role='5af56935b011a'), allow delete all data without checking creator
  const deletableStatuses = ['DRAFT', 'SUBMITTED'];
  const isDeletableStatus = deletableStatuses.includes(pr.status);
  const isApprovedStatus = pr.status === 'APPROVED';
  const hasSpecialRole = props.auth?.user?.id_role === '5af56935b011a';
  
  // Convert to string for comparison to avoid type mismatch
  const createdBy = String(pr.created_by);
  const currentUserId = String(props.auth?.user?.id);
  const isCreator = createdBy === currentUserId;
  
  // For APPROVED status, only allow if user has special role
  if (isApprovedStatus) {
    return hasSpecialRole;
  }
  
  // For DRAFT and SUBMITTED, allow if user is the creator
  // If user has special role, allow delete all data without checking creator
  if (hasSpecialRole) {
    return isDeletableStatus;
  }
  
  return isDeletableStatus && isCreator;
}

function getDeleteTooltip(pr) {
  const hasSpecialRole = props.auth?.user?.id_role === '5af56935b011a';
  
  if (pr.status === 'APPROVED') {
    return hasSpecialRole ? 'Hapus PR yang sudah di-approve' : 'Hanya bisa dihapus oleh user dengan role khusus';
  }
  
  if (hasSpecialRole) {
    return 'Hapus PR (Anda memiliki akses untuk menghapus semua PR)';
  }
  
  return 'Hanya bisa dihapus jika status DRAFT atau SUBMITTED dan Anda adalah pembuat PR';
}

function deletePR(pr) {
  let statusText = pr.status === 'DRAFT' ? 'Draft' : pr.status === 'SUBMITTED' ? 'Submitted (belum di-approve)' : 'Approved';
  if (pr.status === 'APPROVED') {
    statusText += ' (Hanya bisa dihapus oleh user dengan role khusus)';
  }
  
  Swal.fire({
    title: 'Hapus Payment?',
    html: `
      <div class="text-left">
        <p class="mb-2"><strong>PR Number:</strong> ${pr.pr_number}</p>
        <p class="mb-2"><strong>Title:</strong> ${pr.title}</p>
        <p class="mb-2"><strong>Amount:</strong> ${formatCurrency(pr.amount)}</p>
        <p class="mb-2"><strong>Status:</strong> ${statusText}</p>
        <p class="text-red-600 font-semibold">Tindakan ini tidak dapat dibatalkan!</p>
      </div>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#EF4444',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then((result) => {
    if (result.isConfirmed) {
      // Make delete request using Inertia router
      router.delete(route('purchase-requisitions.destroy', pr.id), {
        onStart: () => {
          Swal.fire({
            title: 'Menghapus...',
            text: 'Sedang menghapus Payment',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });
        },
        onSuccess: () => {
          Swal.fire({
            title: 'Berhasil!',
            text: 'Payment berhasil dihapus',
            icon: 'success',
            confirmButtonColor: '#10B981'
          });
        },
        onError: (errors) => {
          Swal.close();
          console.error('Error deleting PR:', errors);
          let errorMessage = 'Gagal menghapus Payment';
          
          if (errors.message) {
            errorMessage = errors.message;
          } else if (errors.error) {
            errorMessage = errors.error;
          } else if (typeof errors === 'string') {
            errorMessage = errors;
          }
          
          Swal.fire({
            title: 'Error!',
            text: errorMessage,
            icon: 'error',
            confirmButtonColor: '#EF4444'
          });
        },
        onFinish: () => {
          // Swal will be closed in onSuccess or onError
        }
      });
    }
  });
}

function getStatusColor(status) {
  return {
    'DRAFT': 'bg-gray-100 text-gray-800',
    'SUBMITTED': 'bg-yellow-100 text-yellow-800',
    'APPROVED': 'bg-green-100 text-green-800',
    'REJECTED': 'bg-red-100 text-red-800',
    'PROCESSED': 'bg-blue-100 text-blue-800',
    'COMPLETED': 'bg-purple-100 text-purple-800',
    'PAID': 'bg-emerald-100 text-emerald-800',
  }[status] || 'bg-gray-100 text-gray-800';
}

function getDivisionBadgeClass(division) {
  const classes = {
    'MARKETING': 'bg-pink-100 text-pink-800',
    'MAINTENANCE': 'bg-orange-100 text-orange-800',
    'ASSET': 'bg-blue-100 text-blue-800',
    'PROJECT_ENHANCEMENT': 'bg-purple-100 text-purple-800',
  };
  return classes[division] || 'bg-gray-100 text-gray-800';
}

function getModeLabel(mode) {
  if (!mode) return '-';
  const labels = {
    'pr_ops': 'Purchase Requisition',
    'purchase_payment': 'Payment Application',
    'travel_application': 'Travel Application',
    'kasbon': 'Kasbon',
  };
  return labels[mode] || mode;
}

function getModeBadgeClass(mode) {
  if (!mode) return 'bg-gray-100 text-gray-800';
  const classes = {
    'pr_ops': 'bg-blue-100 text-blue-800',
    'purchase_payment': 'bg-green-100 text-green-800',
    'travel_application': 'bg-purple-100 text-purple-800',
    'kasbon': 'bg-orange-100 text-orange-800',
  };
  return classes[mode] || 'bg-gray-100 text-gray-800';
}

function formatCurrency(amount) {
  if (!amount) return '-';
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
  }).format(amount);
}

function getCategoriesList(pr) {
  if (!pr) return [];
  
  const categories = [];
  const categoryMap = new Map(); // Untuk menghindari duplikasi
  
  // Untuk mode pr_ops dan purchase_payment: ambil semua unique categories dari items
  if ((pr.mode === 'pr_ops' || pr.mode === 'purchase_payment') && pr.items && pr.items.length > 0) {
    pr.items.forEach(item => {
      if (item.category && item.category.id) {
        // Gunakan ID sebagai key untuk menghindari duplikasi
        if (!categoryMap.has(item.category.id)) {
          categoryMap.set(item.category.id, item.category);
          categories.push(item.category);
        }
      }
    });
  }
  
  // Fallback ke PR level category jika tidak ada category dari items
  if (categories.length === 0 && pr.category && pr.category.id) {
    if (!categoryMap.has(pr.category.id)) {
      categories.push(pr.category);
    }
  }
  
  return categories;
}


function getOutletList(pr) {
  if (!pr) return [];
  
  // Untuk mode pr_ops dan purchase_payment: ambil outlet dari items
  if (pr.mode === 'pr_ops' || pr.mode === 'purchase_payment') {
    if (pr.items && pr.items.length > 0) {
      // Ambil unique outlets dari items
      const outlets = new Set();
      pr.items.forEach(item => {
        if (item.outlet && item.outlet.nama_outlet) {
          outlets.add(item.outlet.nama_outlet);
        }
      });
      
      if (outlets.size > 0) {
        return Array.from(outlets).sort(); // Sort untuk konsistensi
      }
    }
    // Fallback ke main PR outlet untuk data lama
    if (pr.outlet?.nama_outlet) {
      return [pr.outlet.nama_outlet];
    }
    return [];
  }
  
  // Untuk mode travel_application: ambil dari travel_outlets (dari backend) atau parse dari notes
  if (pr.mode === 'travel_application') {
    // Cek apakah backend sudah menyediakan travel_outlets
    if (pr.travel_outlets && Array.isArray(pr.travel_outlets) && pr.travel_outlets.length > 0) {
      return pr.travel_outlets.sort(); // Sort untuk konsistensi
    }
    
    // Fallback: coba dari items
    if (pr.items && pr.items.length > 0) {
      const outlets = new Set();
      pr.items.forEach(item => {
        if (item.outlet && item.outlet.nama_outlet) {
          outlets.add(item.outlet.nama_outlet);
        }
      });
      if (outlets.size > 0) {
        return Array.from(outlets).sort(); // Sort untuk konsistensi
      }
    }
    
    // Fallback: parse dari notes JSON (tapi tidak bisa dapat nama, jadi return empty)
    // Atau fallback ke main PR outlet untuk data lama
    if (pr.outlet?.nama_outlet) {
      return [pr.outlet.nama_outlet];
    }
    return [];
  }
  
  // Untuk mode kasbon dan data lama: ambil dari main PR outlet
  if (pr.outlet?.nama_outlet) {
    return [pr.outlet.nama_outlet];
  }
  return [];
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function formatTime(date) {
  if (!date) return '-';
  return new Date(date).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  });
}

function holdPR(pr) {
  Swal.fire({
    title: 'Hold Purchase Requisition?',
    html: `
      <div class="text-left">
        <p class="mb-2"><strong>PR Number:</strong> ${pr.pr_number}</p>
        <p class="mb-2"><strong>Title:</strong> ${pr.title}</p>
        <p class="mb-4 text-gray-600">PR yang di-hold tidak dapat dibuat PO atau Payment sampai di-release.</p>
        <div class="mb-2">
          <label class="block text-sm font-medium text-gray-700 mb-1">Alasan Hold <span class="text-red-500">*</span>:</label>
          <textarea id="hold_reason" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" rows="3" placeholder="Masukkan alasan hold..." required></textarea>
        </div>
      </div>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#F97316',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Ya, Hold!',
    cancelButtonText: 'Batal',
    reverseButtons: true,
    preConfirm: () => {
      const reason = document.getElementById('hold_reason').value.trim();
      
      if (!reason || reason === '') {
        Swal.showValidationMessage('Alasan hold harus diisi');
        return false;
      }
      
      return { hold_reason: reason };
    }
  }).then((result) => {
    if (result.isConfirmed) {
      const holdReason = result.value?.hold_reason || '';
      
      router.post(route('purchase-requisitions.hold', pr.id), {
        hold_reason: holdReason
      }, {
        onStart: () => {
          Swal.fire({
            title: 'Memproses...',
            text: 'Sedang meng-hold PR',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });
        },
        onSuccess: () => {
          Swal.fire({
            title: 'Berhasil!',
            text: 'PR berhasil di-hold',
            icon: 'success',
            confirmButtonColor: '#10B981'
          });
        },
        onError: (errors) => {
          Swal.close();
          console.error('Error holding PR:', errors);
          let errorMessage = 'Gagal meng-hold PR';
          
          if (errors.message) {
            errorMessage = errors.message;
          } else if (errors.error) {
            errorMessage = errors.error;
          } else if (typeof errors === 'string') {
            errorMessage = errors;
          }
          
          Swal.fire({
            title: 'Error!',
            text: errorMessage,
            icon: 'error',
            confirmButtonColor: '#EF4444'
          });
        }
      });
    }
  });
}

function releasePR(pr) {
  Swal.fire({
    title: 'Release Purchase Requisition?',
    html: `
      <div class="text-left">
        <p class="mb-2"><strong>PR Number:</strong> ${pr.pr_number}</p>
        <p class="mb-2"><strong>Title:</strong> ${pr.title}</p>
        <p class="mb-2 text-gray-600">PR akan di-release dan dapat dibuat PO atau Payment kembali.</p>
        ${pr.hold_reason ? `<p class="mt-2 text-sm text-gray-500"><strong>Alasan Hold:</strong> ${pr.hold_reason}</p>` : ''}
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#10B981',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Ya, Release!',
    cancelButtonText: 'Batal',
    reverseButtons: true
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route('purchase-requisitions.release', pr.id), {}, {
        onStart: () => {
          Swal.fire({
            title: 'Memproses...',
            text: 'Sedang me-release PR',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });
        },
        onSuccess: () => {
          Swal.fire({
            title: 'Berhasil!',
            text: 'PR berhasil di-release',
            icon: 'success',
            confirmButtonColor: '#10B981'
          });
        },
        onError: (errors) => {
          Swal.close();
          console.error('Error releasing PR:', errors);
          let errorMessage = 'Gagal me-release PR';
          
          if (errors.message) {
            errorMessage = errors.message;
          } else if (errors.error) {
            errorMessage = errors.error;
          } else if (typeof errors === 'string') {
            errorMessage = errors;
          }
          
          Swal.fire({
            title: 'Error!',
            text: errorMessage,
            icon: 'error',
            confirmButtonColor: '#EF4444'
          });
        }
      });
    }
  });
}

// Print functionality
async function printSinglePR(pr) {
  try {
    printData.value = [pr];
    
    // Generate preview URL
    const prIds = pr.id.toString();
    previewUrl.value = `/purchase-requisitions/print-preview?ids=${encodeURIComponent(prIds)}`;
    showPrintModal.value = true;
  } catch (error) {
    console.error('Error preparing print:', error);
    Swal.fire('Error', 'Gagal mempersiapkan print', 'error');
  }
}

function closePrintModal() {
  showPrintModal.value = false;
  previewUrl.value = '';
  printData.value = [];
}

function printPreview() {
  if (previewFrame.value) {
    previewFrame.value.contentWindow.print();
  }
}

// Watch for changes
watch([search, status, division, category, isHeld, perPage], () => {
  debouncedSearch();
});

// Auto-load data with default date filter on first access
onMounted(() => {
  // Only auto-load if no date filter is provided in URL (first access)
  if (!props.filters?.date_from && !props.filters?.date_to) {
    loadData();
  }
  
  // Inject global style for SweetAlert to appear above modals
  const style = document.createElement('style');
  style.textContent = `
    /* SweetAlert harus selalu di atas semua modal */
    .swal2-container {
      z-index: 999999 !important;
      position: fixed !important;
    }
    .swal2-popup {
      z-index: 999999 !important;
      position: relative !important;
    }
    .swal2-backdrop-show {
      z-index: 999998 !important;
      position: fixed !important;
    }
  `;
  document.head.appendChild(style);
});
</script>

<style scoped>
/* Component styles here if needed */
</style>