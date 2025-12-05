<template>
  <AppLayout title="Customer Analytics">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <!-- Header -->
      <div class="mb-8">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Customer Analytics</h1>
            <p class="mt-2 text-gray-600">Analisis transaksi mencurigakan member</p>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
          <!-- Year Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tahun</label>
            <select
              v-model="selectedYear"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
            </select>
          </div>

          <!-- Period Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Periode</label>
            <select
              v-model="selectedPeriod"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="1">Januari - Juni</option>
              <option value="2">Juli - Desember</option>
            </select>
          </div>

          <!-- Suspicion Level Filter -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Level Kecurigaan</label>
            <select
              v-model="mainFilters.suspicion_level"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="">Semua Level</option>
              <option value="Tinggi">Tinggi</option>
              <option value="Sedang">Sedang</option>
              <option value="Rendah">Rendah</option>
            </select>
          </div>

          <!-- Sort By -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Urutkan</label>
            <select
              v-model="mainFilters.sort_by"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="transaction_count">Jumlah Transaksi</option>
              <option value="total_value">Total Nilai</option>
              <option value="customer_name">Nama Customer</option>
              <option value="transaction_date">Tanggal Transaksi</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <!-- Search -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
            <input
              v-model="mainFilters.search"
              @input="debounceMainSearch"
              type="text"
              placeholder="Nama, ID, telepon, email, cabang..."
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Sort Order -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Urutan</label>
            <select
              v-model="mainFilters.sort_order"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="desc">Tertinggi</option>
              <option value="asc">Terendah</option>
            </select>
          </div>

          <!-- Per Page -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tampilkan</label>
            <select
              v-model="mainFilters.per_page"
              @change="applyFilters"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="10">10 data</option>
              <option value="25">25 data</option>
              <option value="50">50 data</option>
              <option value="100">100 data</option>
            </select>
          </div>


        </div>
      </div>

      <!-- Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Total Suspicious Customers -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Member Mencurigakan</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatNumber(suspiciousData.summary.total_suspicious_customers) }}</p>
            </div>
            <div class="bg-red-100 p-3 rounded-lg">
              <i class="fa-solid fa-exclamation-triangle text-red-600 text-xl"></i>
            </div>
          </div>
        </div>

        <!-- Total Suspicious Days -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Hari Mencurigakan</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatNumber(suspiciousData.summary.total_suspicious_days) }}</p>
            </div>
            <div class="bg-orange-100 p-3 rounded-lg">
              <i class="fa-solid fa-calendar-exclamation text-orange-600 text-xl"></i>
            </div>
          </div>
        </div>

        <!-- Total Transactions -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Transaksi</p>
              <p class="text-2xl font-bold text-gray-900">{{ formatNumber(suspiciousData.summary.total_transactions) }}</p>
            </div>
            <div class="bg-yellow-100 p-3 rounded-lg">
              <i class="fa-solid fa-receipt text-yellow-600 text-xl"></i>
            </div>
          </div>
        </div>

        <!-- Total Value -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Nilai Transaksi</p>
              <p class="text-lg font-bold text-gray-900">{{ suspiciousData.summary.total_value_formatted }}</p>
            </div>
            <div class="bg-purple-100 p-3 rounded-lg">
              <i class="fa-solid fa-money-bill-wave text-purple-600 text-xl"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Suspicion Level Distribution -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-pie text-red-500"></i>
            Distribusi Level Kecurigaan
          </h3>
          <div class="space-y-4">
            <div v-for="(level, key) in suspiciousData.suspicionLevels" :key="key" class="flex items-center justify-between p-3 rounded-lg" :class="getLevelColor(key)">
              <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full" :class="getLevelDotColor(key)"></div>
                <span class="font-medium">{{ key }}</span>
              </div>
              <div class="text-right">
                <p class="font-bold">{{ level.count }} hari</p>
                <p class="text-sm text-gray-600">{{ level.total_value_formatted }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Period Info -->
        <div class="bg-white rounded-xl shadow-lg p-6 lg:col-span-2">
          <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-info-circle text-blue-500"></i>
            Informasi Periode
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <p class="text-sm font-medium text-gray-600">Periode Aktif</p>
              <p class="text-lg font-bold text-gray-900">{{ suspiciousData.summary.period_name }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-600">Rata-rata Transaksi/Hari</p>
              <p class="text-lg font-bold text-gray-900">{{ formatNumber(suspiciousData.summary.average_transactions_per_day, 1) }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-600">Total Point</p>
              <p class="text-lg font-bold text-gray-900">{{ formatNumber(suspiciousData.summary.total_points) }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-600">Nilai Rata-rata/Transaksi</p>
              <p class="text-lg font-bold text-gray-900">{{ formatRupiah(suspiciousData.summary.total_value / suspiciousData.summary.total_transactions) }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Suspicious Transactions Table -->
      <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-table text-gray-500"></i>
          Daftar Transaksi Mencurigakan
        </h3>
        
        <!-- Pagination Info -->
        <div v-if="mainPagination.total > 0" class="mb-4 flex flex-col sm:flex-row justify-between items-center gap-4">
          <div class="text-sm text-gray-600">
            Menampilkan {{ mainPagination.from || 0 }} - {{ mainPagination.to || 0 }} dari {{ mainPagination.total || 0 }} data
          </div>
          
          <div v-if="mainPagination.last_page > 1" class="flex gap-1">
            <!-- First Page -->
            <button
              @click="changeMainPage(1)"
              :disabled="mainPagination.current_page === 1"
              class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
              title="Halaman Pertama"
            >
              <i class="fa-solid fa-angles-left"></i>
            </button>
            
            <!-- Previous Page -->
            <button
              @click="changeMainPage(mainPagination.current_page - 1)"
              :disabled="mainPagination.current_page === 1"
              class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
              title="Halaman Sebelumnya"
            >
              <i class="fa-solid fa-chevron-left"></i>
            </button>
            
            <!-- Page Numbers -->
            <button
              v-for="page in getMainPageNumbers()"
              :key="page"
              @click="page !== '...' ? changeMainPage(page) : null"
              :class="[
                'px-3 py-2 border rounded-md text-sm min-w-[40px]',
                page === mainPagination.current_page
                  ? 'bg-blue-600 text-white border-blue-600'
                  : page === '...'
                    ? 'border-gray-300 text-gray-500 cursor-default'
                    : 'border-gray-300 text-gray-700 hover:bg-gray-50'
              ]"
            >
              {{ page }}
            </button>
            
            <!-- Next Page -->
            <button
              @click="changeMainPage(mainPagination.current_page + 1)"
              :disabled="mainPagination.current_page === mainPagination.last_page"
              class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
              title="Halaman Selanjutnya"
            >
              <i class="fa-solid fa-chevron-right"></i>
            </button>
            
            <!-- Last Page -->
            <button
              @click="changeMainPage(mainPagination.last_page)"
              :disabled="mainPagination.current_page === mainPagination.last_page"
              class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
              title="Halaman Terakhir"
            >
              <i class="fa-solid fa-angles-right"></i>
            </button>
          </div>
        </div>

        <div v-if="suspiciousData.transactions.length === 0" class="text-center py-8 text-gray-500">
          <i class="fa-solid fa-shield-check text-4xl mb-4"></i>
          <p>Tidak ada transaksi mencurigakan untuk periode ini</p>
        </div>
        
        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jumlah Transaksi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Point</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Nilai</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="transaction in suspiciousData.transactions" :key="`${transaction.customer_id}-${transaction.transaction_date}`" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div class="text-sm font-medium text-gray-900">{{ transaction.customer_name }}</div>
                    <div class="text-sm text-gray-500">{{ transaction.costumers_id }}</div>
                    <div class="text-xs text-gray-400">{{ transaction.telepon }}</div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ transaction.transaction_date }}</div>
                  <div class="text-xs text-gray-500">{{ transaction.first_transaction }} - {{ transaction.last_transaction }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" :class="getTransactionCountColor(transaction.transaction_count)">
                    {{ transaction.transaction_count }}x
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ transaction.total_points_formatted }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ transaction.total_value_formatted }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" :class="getLevelBadgeColor(transaction.suspicion_level)">
                    {{ transaction.suspicion_level }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <button
                    @click="showTransactionDetails(transaction)"
                    class="text-blue-600 hover:text-blue-900 flex items-center gap-1"
                  >
                    <i class="fa-solid fa-eye"></i>
                    Detail
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Transaction Details Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-4/5 lg:w-3/4 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
        <div class="mt-3">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Detail Transaksi</h3>
            <button @click="closeModal" class="text-gray-400 hover:text-gray-600">
              <i class="fa-solid fa-times text-xl"></i>
            </button>
          </div>
          
          <div v-if="loading" class="text-center py-8">
            <i class="fa-solid fa-spinner fa-spin text-2xl text-blue-600"></i>
            <p class="mt-2 text-gray-600">Memuat detail transaksi...</p>
          </div>
          
          <div v-else-if="transactionDetails.length > 0">
            <!-- Customer Info -->
            <div class="mb-4 p-3 bg-gray-50 rounded-lg">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <div>
                  <p class="text-sm text-gray-600">Member: <span class="font-medium">{{ selectedTransaction?.customer_name }}</span></p>
                  <p class="text-sm text-gray-600">ID: <span class="font-medium">{{ selectedTransaction?.costumers_id }}</span></p>
                </div>
                <div>
                  <p class="text-sm text-gray-600">Telepon: <span class="font-medium">{{ selectedTransaction?.telepon || '-' }}</span></p>
                  <p class="text-sm text-gray-600">Email: <span class="font-medium">{{ selectedTransaction?.email || '-' }}</span></p>
                </div>
              </div>
              <p class="text-sm text-gray-600 mt-2">Tanggal: <span class="font-medium">{{ selectedTransaction?.transaction_date }}</span></p>
            </div>

            <!-- Filters -->
            <div class="mb-4 p-4 bg-blue-50 rounded-lg">
              <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Search -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Cari</label>
                  <input
                    v-model="modalFilters.search"
                    @input="debounceSearch"
                    type="text"
                    placeholder="Bill number, cabang, nama, email, telepon..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  />
                </div>

                <!-- Type Filter -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Tipe Transaksi</label>
                  <select
                    v-model="modalFilters.type"
                    @change="() => { modalFilters.page = 1; loadTransactionDetails(); }"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="">Semua</option>
                    <option value="1">Top Up</option>
                    <option value="2">Redeem</option>
                  </select>
                </div>

                <!-- Sort By -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Urutkan</label>
                  <select
                    v-model="modalFilters.sort_by"
                    @change="() => { modalFilters.page = 1; loadTransactionDetails(); }"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="created_at">Waktu</option>
                    <option value="point">Point</option>
                    <option value="jml_trans">Nilai</option>
                    <option value="no_bill">Bill Number</option>
                  </select>
                </div>

                <!-- Sort Order -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Urutan</label>
                  <select
                    v-model="modalFilters.sort_order"
                    @change="() => { modalFilters.page = 1; loadTransactionDetails(); }"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  >
                    <option value="asc">A-Z</option>
                    <option value="desc">Z-A</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Per Page Selector and Pagination Info -->
            <div class="mb-4 flex flex-col sm:flex-row justify-between items-center gap-4">
              <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700">Tampilkan:</label>
                <select
                  v-model="modalFilters.per_page"
                  @change="() => { modalFilters.page = 1; loadTransactionDetails(); }"
                  class="px-3 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="5">5 data</option>
                  <option value="10">10 data</option>
                  <option value="25">25 data</option>
                  <option value="50">50 data</option>
                  <option value="100">100 data</option>
                </select>
                <span class="text-sm text-gray-600">per halaman</span>
              </div>
              
              <div class="text-sm text-gray-600" v-if="pagination.total > 0">
                Menampilkan {{ pagination.from || 0 }} - {{ pagination.to || 0 }} dari {{ pagination.total }} data
              </div>
            </div>
            
            <!-- Table -->
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0">
                  <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer" @click="changeSort('created_at')">
                      Waktu
                      <i v-if="modalFilters.sort_by === 'created_at'" :class="modalFilters.sort_order === 'asc' ? 'fa-solid fa-sort-up' : 'fa-solid fa-sort-down'" class="ml-1"></i>
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer" @click="changeSort('no_bill')">
                      Bill Number
                      <i v-if="modalFilters.sort_by === 'no_bill'" :class="modalFilters.sort_order === 'asc' ? 'fa-solid fa-sort-up' : 'fa-solid fa-sort-down'" class="ml-1"></i>
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipe</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer" @click="changeSort('point')">
                      Point
                      <i v-if="modalFilters.sort_by === 'point'" :class="modalFilters.sort_order === 'asc' ? 'fa-solid fa-sort-up' : 'fa-solid fa-sort-down'" class="ml-1"></i>
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer" @click="changeSort('jml_trans')">
                      Nilai
                      <i v-if="modalFilters.sort_by === 'jml_trans'" :class="modalFilters.sort_order === 'asc' ? 'fa-solid fa-sort-up' : 'fa-solid fa-sort-down'" class="ml-1"></i>
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cabang</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="detail in transactionDetails" :key="detail.id" class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-sm text-gray-900">{{ detail.created_at }}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 font-mono">{{ detail.bill_number }}</td>
                    <td class="px-4 py-2 text-sm">
                      <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full" :class="detail.type === '1' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'">
                        {{ detail.type_text }}
                      </span>
                    </td>
                    <td class="px-4 py-2 text-sm text-gray-900 font-mono">{{ detail.point_formatted }}</td>
                    <td class="px-4 py-2 text-sm text-gray-900 font-mono">{{ detail.jml_trans_formatted }}</td>
                    <td class="px-4 py-2 text-sm text-gray-900">{{ detail.cabang_name }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Pagination -->
            <div class="mt-6">

              
              <div v-if="pagination.total > 0" class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <div class="text-sm text-gray-600">
                  Halaman {{ pagination.current_page }} dari {{ pagination.last_page }}
                </div>
                
                <div v-if="pagination.last_page > 1" class="flex gap-1">
                  <!-- First Page -->
                  <button
                    @click="changePage(1)"
                    :disabled="pagination.current_page === 1"
                    class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                    title="Halaman Pertama"
                  >
                    <i class="fa-solid fa-angles-left"></i>
                  </button>
                  
                  <!-- Previous Page -->
                  <button
                    @click="changePage(pagination.current_page - 1)"
                    :disabled="pagination.current_page === 1"
                    class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                    title="Halaman Sebelumnya"
                  >
                    <i class="fa-solid fa-chevron-left"></i>
                  </button>
                  
                  <!-- Page Numbers -->
                  <button
                    v-for="page in getPageNumbers()"
                    :key="page"
                    @click="page !== '...' ? changePage(page) : null"
                    :class="[
                      'px-3 py-2 border rounded-md text-sm min-w-[40px]',
                      page === pagination.current_page
                        ? 'bg-blue-600 text-white border-blue-600'
                        : page === '...'
                          ? 'border-gray-300 text-gray-500 cursor-default'
                          : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                    ]"
                  >
                    {{ page }}
                  </button>
                  
                  <!-- Next Page -->
                  <button
                    @click="changePage(pagination.current_page + 1)"
                    :disabled="pagination.current_page === pagination.last_page"
                    class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                    title="Halaman Selanjutnya"
                  >
                    <i class="fa-solid fa-chevron-right"></i>
                  </button>
                  
                  <!-- Last Page -->
                  <button
                    @click="changePage(pagination.last_page)"
                    :disabled="pagination.current_page === pagination.last_page"
                    class="px-3 py-2 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                    title="Halaman Terakhir"
                  >
                    <i class="fa-solid fa-angles-right"></i>
                  </button>
                </div>
                
                <!-- No Pagination Message -->
                <div v-else class="text-sm text-gray-500">
                  Semua data ditampilkan ({{ pagination.total }} data)
                </div>
              </div>
              
              <!-- No Data Message -->
              <div v-else class="w-full text-center text-sm text-gray-500">
                Tidak ada data untuk ditampilkan
              </div>
            </div>
          </div>
          
          <div v-else class="text-center py-8 text-gray-500">
            <i class="fa-solid fa-exclamation-triangle text-2xl mb-2"></i>
            <p>Tidak ada detail transaksi</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  suspiciousData: Object,
  yearOptions: Array,
  currentYear: Number,
  currentPeriod: String,
  filters: Object,
});

// Reactive data
const selectedYear = ref(props.currentYear);
const selectedPeriod = ref(props.currentPeriod);
const showModal = ref(false);
const loading = ref(false);
const selectedTransaction = ref(null);
const transactionDetails = ref([]);

// Main page filters
const mainFilters = ref({
  search: props.filters?.search || '',
  suspicion_level: props.filters?.suspicion_level || '',
  sort_by: props.filters?.sort_by || 'transaction_count',
  sort_order: props.filters?.sort_order || 'desc',
  per_page: props.filters?.per_page || 10,
  page: props.filters?.page || 1,
});

// Modal pagination
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0,
});

// Computed properties
const mainPagination = computed(() => props.suspiciousData?.pagination || {
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0,
  from: 0,
  to: 0,
});

// Modal filters
const modalFilters = ref({
  search: '',
  type: '',
  sort_by: 'created_at',
  sort_order: 'asc',
  per_page: 10,
  page: 1,
});

// Debounce search
let searchTimeout = null;
let mainSearchTimeout = null;

// Methods
function formatNumber(number, decimals = 0) {
  return number.toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

function formatRupiah(amount) {
  return 'Rp ' + amount.toLocaleString('id-ID');
}

function applyFilters() {
  router.visit('/crm/customer-analytics', {
    data: {
      year: selectedYear.value,
      period: selectedPeriod.value,
      ...mainFilters.value,
    },
    preserveState: true,
  });
}



function getLevelColor(level) {
  switch (level) {
    case 'Tinggi': return 'bg-red-50 border-l-4 border-red-500';
    case 'Sedang': return 'bg-orange-50 border-l-4 border-orange-500';
    case 'Rendah': return 'bg-yellow-50 border-l-4 border-yellow-500';
    default: return 'bg-gray-50 border-l-4 border-gray-500';
  }
}

function getLevelDotColor(level) {
  switch (level) {
    case 'Tinggi': return 'bg-red-500';
    case 'Sedang': return 'bg-orange-500';
    case 'Rendah': return 'bg-yellow-500';
    default: return 'bg-gray-500';
  }
}

function getTransactionCountColor(count) {
  if (count >= 5) return 'bg-red-100 text-red-800';
  if (count >= 3) return 'bg-orange-100 text-orange-800';
  return 'bg-yellow-100 text-yellow-800';
}

function getLevelBadgeColor(level) {
  switch (level) {
    case 'Tinggi': return 'bg-red-100 text-red-800';
    case 'Sedang': return 'bg-orange-100 text-orange-800';
    case 'Rendah': return 'bg-yellow-100 text-yellow-800';
    default: return 'bg-gray-100 text-gray-800';
  }
}

async function showTransactionDetails(transaction) {
  selectedTransaction.value = transaction;
  showModal.value = true;
  
  // Reset filters
  modalFilters.value = {
    search: '',
    type: '',
    sort_by: 'created_at',
    sort_order: 'asc',
    per_page: 10,
    page: 1,
  };
  
  await loadTransactionDetails();
}

async function loadTransactionDetails() {
  if (!selectedTransaction.value) return;
  
  loading.value = true;
  
  try {
    // Convert date format from dd/mm/yyyy to yyyy-mm-dd for backend
    const dateParts = selectedTransaction.value.transaction_date.split('/');
    const formattedDate = dateParts.length === 3 
      ? `${dateParts[2]}-${dateParts[1].padStart(2, '0')}-${dateParts[0].padStart(2, '0')}`
      : selectedTransaction.value.transaction_date;

    const params = new URLSearchParams({
      customer_id: selectedTransaction.value.customer_id,
      date: formattedDate,
      page: modalFilters.value.page,
      per_page: modalFilters.value.per_page,
      search: modalFilters.value.search,
      type: modalFilters.value.type,
      sort_by: modalFilters.value.sort_by,
      sort_order: modalFilters.value.sort_order,
    });

    const response = await fetch(`/crm/customer-analytics/transactions?${params}`, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    
    if (response.ok) {
      const data = await response.json();
      transactionDetails.value = data.transactions;
      pagination.value = data.pagination;
    } else {
      transactionDetails.value = [];
      pagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0,
        from: 0,
        to: 0,
      };
    }
  } catch (error) {
    console.error('Error fetching transaction details:', error);
    transactionDetails.value = [];
  } finally {
    loading.value = false;
  }
}

function closeModal() {
  showModal.value = false;
  selectedTransaction.value = null;
  transactionDetails.value = [];
  pagination.value = {
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0,
    from: 0,
    to: 0,
  };
}

function debounceSearch() {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    modalFilters.value.page = 1; // Reset to first page when searching
    loadTransactionDetails();
  }, 500);
}

function debounceMainSearch() {
  clearTimeout(mainSearchTimeout);
  mainSearchTimeout = setTimeout(() => {
    mainFilters.value.page = 1; // Reset to first page when searching
    applyFilters();
  }, 500);
}

function changePage(page) {
  if (page >= 1 && page <= pagination.value.last_page && page !== '...') {
    modalFilters.value.page = page;
    loadTransactionDetails();
  }
}

function changeMainPage(page) {
  if (page >= 1 && page !== '...') {
    mainFilters.value.page = page;
    applyFilters();
  }
}

function changeSort(field) {
  if (modalFilters.value.sort_by === field) {
    modalFilters.value.sort_order = modalFilters.value.sort_order === 'asc' ? 'desc' : 'asc';
  } else {
    modalFilters.value.sort_by = field;
    modalFilters.value.sort_order = 'asc';
  }
  modalFilters.value.page = 1; // Reset to first page when sorting
  loadTransactionDetails();
}

function getPageNumbers() {
  const current = pagination.value.current_page;
  const last = pagination.value.last_page;
  const delta = 2;
  
  if (last <= 7) {
    // If total pages <= 7, show all pages
    return Array.from({ length: last }, (_, i) => i + 1);
  }
  
  const range = [];
  const rangeWithDots = [];
  
  // Calculate range around current page
  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    range.push(i);
  }
  
  // Add first page and dots if needed
  if (current - delta > 2) {
    rangeWithDots.push(1, '...');
  } else {
    rangeWithDots.push(1);
  }
  
  // Add range around current page
  rangeWithDots.push(...range);
  
  // Add last page and dots if needed
  if (current + delta < last - 1) {
    rangeWithDots.push('...', last);
  } else if (last > 1) {
    rangeWithDots.push(last);
  }
  
  // Remove duplicates and filter
  return rangeWithDots.filter((item, index, array) => {
    if (item === '...') return true;
    return array.indexOf(item) === index;
  });
}

function getMainPageNumbers() {
  if (!mainPagination.value) return [];
  
  const current = mainPagination.value.current_page;
  const last = mainPagination.value.last_page;
  const delta = 2;
  
  if (last <= 7) {
    // If total pages <= 7, show all pages
    return Array.from({ length: last }, (_, i) => i + 1);
  }
  
  const range = [];
  const rangeWithDots = [];
  
  // Calculate range around current page
  for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
    range.push(i);
  }
  
  // Add first page and dots if needed
  if (current - delta > 2) {
    rangeWithDots.push(1, '...');
  } else {
    rangeWithDots.push(1);
  }
  
  // Add range around current page
  rangeWithDots.push(...range);
  
  // Add last page and dots if needed
  if (current + delta < last - 1) {
    rangeWithDots.push('...', last);
  } else if (last > 1) {
    rangeWithDots.push(last);
  }
  
  // Remove duplicates and filter
  return rangeWithDots.filter((item, index, array) => {
    if (item === '...') return true;
    return array.indexOf(item) === index;
  });
}
</script> 