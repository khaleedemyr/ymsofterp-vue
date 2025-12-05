<template>
  <AppLayout>
    <!-- Page Header -->
    <div class="bg-white shadow-sm border-b border-gray-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="py-6">
          <h1 class="text-2xl font-bold text-gray-900">Holiday Attendance Management</h1>
          <p class="mt-1 text-sm text-gray-600">Manage employee compensations for working on holidays</p>
        </div>
      </div>
    </div>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Tabs -->
        <div class="mb-6">
          <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
              <button
                @click="activeTab = 'holiday'"
                :class="[
                  activeTab === 'holiday' 
                    ? 'border-indigo-500 text-indigo-600' 
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                  'whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm'
                ]"
              >
                <i class="fas fa-calendar-day mr-2"></i>
                Holiday Attendance
              </button>
              <button
                @click="activeTab = 'extra-off'"
                :class="[
                  activeTab === 'extra-off' 
                    ? 'border-indigo-500 text-indigo-600' 
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300',
                  'whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm'
                ]"
              >
                <i class="fas fa-clock mr-2"></i>
                Extra Off Detection
              </button>
            </nav>
          </div>
        </div>

        <!-- Holiday Attendance Tab -->
        <div v-if="activeTab === 'holiday'">
          <!-- Statistics Cards -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                  </div>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-500">Total Compensations</p>
                  <p class="text-2xl font-semibold text-gray-900">
                    <i v-if="loadingStatistics" class="fas fa-spinner fa-spin text-blue-500"></i>
                    <span v-else>{{ statistics.total_compensations || 0 }}</span>
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                  </div>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-500">Extra Off Days</p>
                  <p class="text-2xl font-semibold text-gray-900">
                    <i v-if="loadingStatistics" class="fas fa-spinner fa-spin text-green-500"></i>
                    <span v-else>{{ statistics.extra_off_given || 0 }}</span>
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                  </div>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-500">Bonuses Paid</p>
                  <p class="text-2xl font-semibold text-gray-900">
                    <i v-if="loadingStatistics" class="fas fa-spinner fa-spin text-yellow-500"></i>
                    <span v-else>{{ statistics.bonus_paid || 0 }}</span>
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                  </div>
                </div>
                <div class="ml-4">
                  <p class="text-sm font-medium text-gray-500">Total Bonus Amount</p>
                  <p class="text-2xl font-semibold text-gray-900">
                    <i v-if="loadingStatistics" class="fas fa-spinner fa-spin text-purple-500"></i>
                    <span v-else>Rp {{ formatNumber(statistics.total_bonus_amount || 0) }}</span>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Process Holiday Section -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Process Holiday Attendance</h3>
            <div class="flex gap-4 items-end">
              <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Holiday Date</label>
                <input
                  v-model="processForm.date"
                  type="date"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
              </div>
              <button
                @click="processHoliday"
                :disabled="processing"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md disabled:opacity-50 flex items-center gap-2"
              >
                <i v-if="processing" class="fas fa-spinner fa-spin"></i>
                <i v-else class="fas fa-play"></i>
                {{ processing ? 'Memproses...' : 'Proses Holiday' }}
              </button>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Filters</h3>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                <input
                  v-model="filters.start_date"
                  type="date"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                <input
                  v-model="filters.end_date"
                  type="date"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Compensation Type</label>
                <select
                  v-model="filters.compensation_type"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                  <option value="">All Types</option>
                  <option value="extra_off">Extra Off Day</option>
                  <option value="bonus">Holiday Bonus</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select
                  v-model="filters.status"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                  <option value="">All Status</option>
                  <option value="pending">Pending</option>
                  <option value="approved">Approved</option>
                  <option value="used">Used</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
              <div class="flex items-end">
                <button
                  @click="applyFilters"
                  :disabled="loadingFilters"
                  class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md mr-2 disabled:opacity-50 flex items-center gap-2"
                >
                  <i v-if="loadingFilters" class="fas fa-spinner fa-spin"></i>
                  <i v-else class="fas fa-filter"></i>
                  {{ loadingFilters ? 'Memproses...' : 'Apply Filters' }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Compensations Table -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Holiday Attendance Compensations</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Used Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <!-- Grouped by Outlet and Division -->
                  <template v-for="outletGroup in groupedCompensations" :key="outletGroup.outlet">
                    <!-- Outlet Header Row -->
                    <tr class="bg-blue-50 hover:bg-blue-100 cursor-pointer" @click="toggleOutlet(outletGroup.outlet)">
                      <td colspan="8" class="px-6 py-4">
                        <div class="flex items-center justify-between">
                          <div class="flex items-center">
                            <i :class="expandedOutlets.includes(outletGroup.outlet) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="mr-3 text-blue-600"></i>
                            <div>
                              <h4 class="text-lg font-semibold text-blue-900">{{ outletGroup.outlet }}</h4>
                              <p class="text-sm text-blue-700">{{ outletGroup.totalEmployees }} employees • {{ formatCurrency(outletGroup.totalAmount) }} total compensation</p>
                            </div>
                          </div>
                          <div class="flex items-center space-x-4">
                            <span class="text-sm text-blue-600 font-medium">{{ outletGroup.divisions.length }} divisions</span>
                          </div>
                        </div>
                      </td>
                    </tr>
                    
                    <!-- Division Rows (only show if outlet is expanded) -->
                    <template v-if="expandedOutlets.includes(outletGroup.outlet)">
                      <template v-for="divisionGroup in outletGroup.divisions" :key="`${outletGroup.outlet}-${divisionGroup.division}`">
                        <!-- Division Header Row -->
                        <tr class="bg-green-50 hover:bg-green-100 cursor-pointer" @click="toggleDivision(outletGroup.outlet, divisionGroup.division)">
                          <td colspan="8" class="px-6 py-3 pl-12">
                            <div class="flex items-center justify-between">
                              <div class="flex items-center">
                                <i :class="expandedDivisions.includes(`${outletGroup.outlet}-${divisionGroup.division}`) ? 'fas fa-chevron-down' : 'fas fa-chevron-right'" class="mr-3 text-green-600"></i>
                                <div>
                                  <h5 class="text-md font-medium text-green-900">{{ divisionGroup.division }}</h5>
                                  <p class="text-sm text-green-700">{{ divisionGroup.totalEmployees }} employees • {{ formatCurrency(divisionGroup.totalAmount) }} total compensation</p>
                                </div>
                              </div>
                            </div>
                          </td>
                        </tr>
                        
                        <!-- Employee Rows (only show if division is expanded) -->
                        <template v-if="expandedDivisions.includes(`${outletGroup.outlet}-${divisionGroup.division}`)">
                          <tr v-for="compensation in divisionGroup.employees" :key="compensation.id" class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 pl-16">
                              {{ formatDate(compensation.holiday_date) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap pl-16">
                              <div class="text-sm font-medium text-gray-900">{{ compensation.nama_lengkap }}</div>
                              <div class="text-sm text-gray-500">{{ compensation.nik }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 pl-16">
                              {{ compensation.nama_jabatan }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap pl-16">
                              <span :class="compensation.compensation_type === 'extra_off' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                {{ compensation.compensation_type === 'extra_off' ? 'Extra Off Day' : 'Holiday Bonus' }}
                              </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 pl-16">
                              {{ compensation.compensation_type === 'extra_off' ? '1 day' : 'Rp ' + formatNumber(compensation.compensation_amount) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap pl-16">
                              <span :class="getStatusClass(compensation.status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                {{ getStatusText(compensation.status) }}
                              </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 pl-16">
                              {{ compensation.used_date ? formatDate(compensation.used_date) : '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium pl-16">
                              <button
                                v-if="compensation.compensation_type === 'extra_off' && compensation.status === 'pending'"
                                @click="showUseExtraOffModal(compensation)"
                                class="text-indigo-600 hover:text-indigo-900 mr-3 flex items-center gap-1"
                              >
                                <i class="fas fa-calendar-check"></i>
                                Use
                              </button>
                            </td>
                          </tr>
                        </template>
                      </template>
                    </template>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        </div>

        <!-- Extra Off Detection Tab -->
        <div v-if="activeTab === 'extra-off'">
          <!-- Extra Off Statistics Cards -->
          <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div class="p-6">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center">
                      <i class="fas fa-clock text-white"></i>
                    </div>
                  </div>
                  <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Extra Off</p>
                    <p class="text-2xl font-semibold text-gray-900">
                      <i v-if="loadingExtraOffStats" class="fas fa-spinner fa-spin text-purple-500"></i>
                      <span v-else>{{ extraOffStats.total_extra_off || 0 }}</span>
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div class="p-6">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-500 rounded-full flex items-center justify-center">
                      <i class="fas fa-hourglass-half text-white"></i>
                    </div>
                  </div>
                  <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Overtime</p>
                    <p class="text-2xl font-semibold text-gray-900">
                      <i v-if="loadingExtraOffStats" class="fas fa-spinner fa-spin text-orange-500"></i>
                      <span v-else>{{ extraOffStats.total_overtime || 0 }}</span>
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div class="p-6">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                      <i class="fas fa-users text-white"></i>
                    </div>
                  </div>
                  <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Users</p>
                    <p class="text-2xl font-semibold text-gray-900">
                      <i v-if="loadingExtraOffStats" class="fas fa-spinner fa-spin text-green-500"></i>
                      <span v-else>{{ extraOffStats.total_users || 0 }}</span>
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
              <div class="p-6">
                <div class="flex items-center">
                  <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                      <i class="fas fa-chart-line text-white"></i>
                    </div>
                  </div>
                  <div class="ml-4">
                    <p class="text-sm font-medium text-gray-500">Total Balance</p>
                    <p class="text-2xl font-semibold text-gray-900">
                      <i v-if="loadingExtraOffStats" class="fas fa-spinner fa-spin text-blue-500"></i>
                      <span v-else>{{ extraOffStats.total_balance || 0 }}</span>
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Process Extra Off Detection -->
          <div class="bg-white shadow-sm sm:rounded-lg mb-6">
            <div class="p-6">
              <h3 class="text-lg font-medium text-gray-900 mb-4">Process Extra Off Detection</h3>
              <div class="flex items-center space-x-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Select Date</label>
                  <input
                    v-model="extraOffForm.date"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  />
                </div>
                <div class="flex items-end">
                  <button
                    @click="processExtraOffDetection"
                    :disabled="processingExtraOff"
                    class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md disabled:opacity-50 flex items-center gap-2"
                  >
                    <i v-if="processingExtraOff" class="fas fa-spinner fa-spin"></i>
                    <i v-else class="fas fa-search"></i>
                    {{ processingExtraOff ? 'Processing...' : 'Detect Extra Off' }}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Extra Off Transactions Table -->
          <div class="bg-white shadow-sm sm:rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-medium text-gray-900">Extra Off Transactions</h3>
            </div>
            <div class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-if="loadingExtraOffTransactions">
                    <td colspan="6" class="px-6 py-4 text-center">
                      <i class="fas fa-spinner fa-spin text-gray-400"></i>
                      <span class="ml-2 text-gray-500">Loading...</span>
                    </td>
                  </tr>
                  <tr v-else-if="extraOffTransactions.length === 0">
                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                      No extra off transactions found
                    </td>
                  </tr>
                  <tr v-else v-for="transaction in extraOffTransactions" :key="transaction.id">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ formatDate(transaction.source_date) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ transaction.user ? transaction.user.nama_lengkap : 'Unknown User' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span :class="[
                        transaction.source_type === 'unscheduled_work' 
                          ? 'bg-green-100 text-green-800' 
                          : 'bg-orange-100 text-orange-800',
                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full'
                      ]">
                        {{ transaction.source_type === 'unscheduled_work' ? 'Extra Off' : 'Overtime' }}
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {{ transaction.amount > 0 ? '+' + transaction.amount : transaction.amount }} hari
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                      {{ transaction.description }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span :class="[
                        transaction.status === 'approved' 
                          ? 'bg-green-100 text-green-800' 
                          : 'bg-yellow-100 text-yellow-800',
                        'px-2 inline-flex text-xs leading-5 font-semibold rounded-full'
                      ]">
                        {{ transaction.status }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Use Extra Off Modal -->
    <Modal :show="showUseModal" @close="showUseModal = false">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 mb-4">Use Extra Off Day</h2>
        <div class="mb-4">
          <p class="text-sm text-gray-600">
            Employee: <strong>{{ selectedCompensation?.nama_lengkap }}</strong><br>
            Holiday Date: <strong>{{ selectedCompensation ? formatDate(selectedCompensation.holiday_date) : '' }}</strong>
          </p>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-2">Use Date</label>
          <input
            v-model="useForm.use_date"
            type="date"
            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
          />
        </div>
        <div class="flex justify-end space-x-3">
          <button
            @click="showUseModal = false"
            class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md"
          >
            Cancel
          </button>
          <button
            @click="useExtraOffDay"
            :disabled="using"
            class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md disabled:opacity-50 flex items-center gap-2"
          >
            <i v-if="using" class="fas fa-spinner fa-spin"></i>
            <i v-else class="fas fa-calendar-check"></i>
            {{ using ? 'Menggunakan...' : 'Gunakan Extra Off Day' }}
          </button>
        </div>
      </div>
    </Modal>
  </AppLayout>
</template>

<style>
/* SweetAlert2 Custom Styles */
.swal2-popup {
  font-family: 'Inter', sans-serif;
}

.swal2-title {
  font-size: 1.5rem;
  font-weight: 600;
}

.swal2-html-container {
  font-size: 1rem;
  line-height: 1.5;
}

.swal2-confirm {
  background-color: #3b82f6 !important;
  border: none !important;
  border-radius: 0.5rem !important;
  padding: 0.75rem 1.5rem !important;
  font-weight: 500 !important;
}

.swal2-cancel {
  background-color: #ef4444 !important;
  border: none !important;
  border-radius: 0.5rem !important;
  padding: 0.75rem 1.5rem !important;
  font-weight: 500 !important;
}

.swal2-loading {
  border-color: #3b82f6 transparent #3b82f6 transparent !important;
}

/* Loading spinner animation */
.fa-spinner {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  compensations: Array,
  users: Array,
  filters: Object
})

const statistics = ref({})
const processing = ref(false)
const using = ref(false)
const showUseModal = ref(false)
const selectedCompensation = ref(null)
const loadingStatistics = ref(false)
const loadingFilters = ref(false)

// Tab management
const activeTab = ref('holiday')

// Extra Off Detection variables
const extraOffStats = ref({})
const extraOffTransactions = ref([])
const loadingExtraOffStats = ref(false)
const loadingExtraOffTransactions = ref(false)
const processingExtraOff = ref(false)

const extraOffForm = ref({
  date: ''
})

// Expandable table state
const expandedOutlets = ref([])
const expandedDivisions = ref([])

const processForm = ref({
  date: ''
})

const useForm = ref({
  use_date: ''
})

const filters = ref({ ...props.filters })

// Computed property for grouped compensations
const groupedCompensations = computed(() => {
  console.log('props.compensations:', props.compensations)
  
  if (!props.compensations || props.compensations.length === 0) {
    console.log('No compensations data found')
    return []
  }

  // Group by outlet first
  const outletGroups = {}
  
  props.compensations.forEach(compensation => {
    console.log('Processing compensation:', compensation)
    const outlet = compensation.nama_outlet || 'Unknown Outlet'
    const division = compensation.nama_divisi || 'Unknown Division'
    const amount = parseFloat(compensation.compensation_amount) || 0
    console.log(`Amount parsed: ${amount} from "${compensation.compensation_amount}"`)
    
    console.log(`Outlet: ${outlet}, Division: ${division}, Amount: ${amount}`)
    
    if (!outletGroups[outlet]) {
      outletGroups[outlet] = {
        outlet: outlet,
        divisions: {},
        totalEmployees: 0,
        totalAmount: 0
      }
    }
    
    if (!outletGroups[outlet].divisions[division]) {
      outletGroups[outlet].divisions[division] = {
        division: division,
        employees: [],
        totalEmployees: 0,
        totalAmount: 0
      }
    }
    
    outletGroups[outlet].divisions[division].employees.push(compensation)
    outletGroups[outlet].divisions[division].totalEmployees++
    outletGroups[outlet].divisions[division].totalAmount += amount
    
    outletGroups[outlet].totalEmployees++
    outletGroups[outlet].totalAmount += amount
  })
  
  // Convert to array format
  const result = Object.values(outletGroups).map(outletGroup => ({
    ...outletGroup,
    divisions: Object.values(outletGroup.divisions)
  }))
  
  console.log('groupedCompensations result:', result)
  return result
})

// Toggle functions
const toggleOutlet = (outlet) => {
  const index = expandedOutlets.value.indexOf(outlet)
  if (index > -1) {
    expandedOutlets.value.splice(index, 1)
    // Also collapse all divisions in this outlet
    expandedDivisions.value = expandedDivisions.value.filter(key => !key.startsWith(`${outlet}-`))
  } else {
    expandedOutlets.value.push(outlet)
  }
}

const toggleDivision = (outlet, division) => {
  const key = `${outlet}-${division}`
  const index = expandedDivisions.value.indexOf(key)
  if (index > -1) {
    expandedDivisions.value.splice(index, 1)
  } else {
    expandedDivisions.value.push(key)
  }
}

// Helper function to format currency
const formatCurrency = (amount) => {
  console.log('formatCurrency input:', amount, 'type:', typeof amount)
  
  // Convert to number if it's a string
  let numAmount = amount
  if (typeof amount === 'string') {
    numAmount = parseFloat(amount)
  }
  
  if (amount === null || amount === undefined || isNaN(numAmount)) {
    return 'Rp 0'
  }
  return 'Rp ' + new Intl.NumberFormat('id-ID').format(numAmount)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID')
}

const formatNumber = (number) => {
  return new Intl.NumberFormat('id-ID').format(number)
}

const getStatusClass = (status) => {
  const classes = {
    'pending': 'bg-yellow-100 text-yellow-800',
    'approved': 'bg-green-100 text-green-800',
    'used': 'bg-blue-100 text-blue-800',
    'cancelled': 'bg-red-100 text-red-800'
  }
  return classes[status] || 'bg-gray-100 text-gray-800'
}

const getStatusText = (status) => {
  const texts = {
    'pending': 'Pending',
    'approved': 'Approved',
    'used': 'Used',
    'cancelled': 'Cancelled'
  }
  return texts[status] || 'Unknown'
}

const processHoliday = async () => {
  if (!processForm.value.date) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Silakan pilih tanggal terlebih dahulu!',
      confirmButtonText: 'OK'
    })
    return
  }

  // Show confirmation dialog
  const confirmResult = await Swal.fire({
    title: 'Proses Holiday Attendance?',
    text: `Apakah Anda yakin ingin memproses holiday attendance untuk tanggal ${formatDate(processForm.value.date)}?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Proses!',
    cancelButtonText: 'Batal'
  })

  if (!confirmResult.isConfirmed) {
    return
  }

  // Show loading
  Swal.fire({
    title: 'Memproses...',
    text: 'Sedang memproses holiday attendance, mohon tunggu...',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading()
    }
  })

  processing.value = true
  try {
    const response = await fetch('/api/holiday-attendance/process', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        date: processForm.value.date
      })
    })

    const result = await response.json()
    
    if (result.success) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        html: `
          <div class="text-left">
            <p><strong>Proses Holiday Attendance Selesai!</strong></p>
            <ul class="mt-3 space-y-1">
              <li>📊 Total diproses: <strong>${result.results.processed}</strong> karyawan</li>
              <li>🏖️ Extra off days: <strong>${result.results.extra_off_given}</strong></li>
              <li>💰 Bonuses: <strong>${result.results.bonus_paid}</strong></li>
            </ul>
          </div>
        `,
        confirmButtonText: 'OK'
      })
      
      // Refresh the page to show updated data
      router.reload()
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: result.message || 'Terjadi kesalahan saat memproses holiday attendance',
        confirmButtonText: 'OK'
      })
    }
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat memproses holiday attendance: ' + error.message,
      confirmButtonText: 'OK'
    })
  } finally {
    processing.value = false
  }
}

const applyFilters = async () => {
  loadingFilters.value = true
  
  try {
    await router.get('/holiday-attendance', filters.value, {
      preserveState: true,
      replace: true
    })
  } finally {
    loadingFilters.value = false
  }
}


const showUseExtraOffModal = (compensation) => {
  selectedCompensation.value = compensation
  useForm.value.use_date = ''
  showUseModal.value = true
}

const useExtraOffDay = async () => {
  if (!useForm.value.use_date) {
    Swal.fire({
      icon: 'warning',
      title: 'Peringatan',
      text: 'Silakan pilih tanggal penggunaan terlebih dahulu!',
      confirmButtonText: 'OK'
    })
    return
  }

  // Show confirmation dialog
  const confirmResult = await Swal.fire({
    title: 'Gunakan Extra Off Day?',
    html: `
      <div class="text-left">
        <p><strong>Konfirmasi Penggunaan Extra Off Day</strong></p>
        <ul class="mt-3 space-y-1">
          <li>👤 Karyawan: <strong>${selectedCompensation.value.nama_lengkap}</strong></li>
          <li>📅 Tanggal Libur: <strong>${formatDate(selectedCompensation.value.holiday_date)}</strong></li>
          <li>📅 Tanggal Penggunaan: <strong>${formatDate(useForm.value.use_date)}</strong></li>
        </ul>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Ya, Gunakan!',
    cancelButtonText: 'Batal'
  })

  if (!confirmResult.isConfirmed) {
    return
  }

  // Show loading
  Swal.fire({
    title: 'Memproses...',
    text: 'Sedang menggunakan extra off day, mohon tunggu...',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading()
    }
  })

  using.value = true
  try {
    const response = await fetch('/api/holiday-attendance/use-extra-off', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        compensation_id: selectedCompensation.value.id,
        use_date: useForm.value.use_date
      })
    })

    const result = await response.json()
    
    if (result.success) {
      Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Extra off day berhasil digunakan!',
        confirmButtonText: 'OK'
      })
      showUseModal.value = false
      router.reload()
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: result.message || 'Terjadi kesalahan saat menggunakan extra off day',
        confirmButtonText: 'OK'
      })
    }
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat menggunakan extra off day: ' + error.message,
      confirmButtonText: 'OK'
    })
  } finally {
    using.value = false
  }
}

const loadStatistics = async () => {
  loadingStatistics.value = true
  
  try {
    const params = new URLSearchParams({
      start_date: filters.value.start_date,
      end_date: filters.value.end_date
    })
    
    const response = await fetch(`/api/holiday-attendance/statistics?${params.toString()}`)
    const result = await response.json()
    
    if (result.success) {
      statistics.value = result.statistics
    } else {
      console.error('Error loading statistics:', result.message)
    }
  } catch (error) {
    console.error('Error loading statistics:', error)
  } finally {
    loadingStatistics.value = false
  }
}

// Extra Off Detection functions
const processExtraOffDetection = async () => {
  if (!extraOffForm.value.date) {
    Swal.fire({
      icon: 'warning',
      title: 'Warning!',
      text: 'Please select a date',
      confirmButtonText: 'OK'
    })
    return
  }

  processingExtraOff.value = true
  
  try {
    const response = await fetch('/api/extra-off/detect', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        date: extraOffForm.value.date
      })
    })
    
    // Check if response is ok
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    
    // Check if response is JSON
    const contentType = response.headers.get('content-type')
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text()
      throw new Error(`Expected JSON response but got: ${text.substring(0, 200)}...`)
    }
    
    const result = await response.json()
    
    if (result.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        html: `
          <div class="text-left">
            <p><strong>Extra Off Detection Results:</strong></p>
            <ul class="mt-2 space-y-1">
              <li>• Detected: ${result.results.detected} employees</li>
              <li>• Extra Off Processed: ${result.results.processed} employees (>8 hours)</li>
              <li>• Overtime Processed: ${result.results.overtime_processed} employees (≤8 hours)</li>
              <li>• Errors: ${result.results.errors.length}</li>
            </ul>
          </div>
        `,
        confirmButtonText: 'OK'
      })
      
      // Reload extra off data
      loadExtraOffStats()
      loadExtraOffTransactions()
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: result.message || 'Terjadi kesalahan saat memproses extra off detection',
        confirmButtonText: 'OK'
      })
    }
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat memproses extra off detection: ' + error.message,
      confirmButtonText: 'OK'
    })
  } finally {
    processingExtraOff.value = false
  }
}

const loadExtraOffStats = async () => {
  loadingExtraOffStats.value = true
  
  try {
    const response = await fetch('/api/extra-off/statistics', {
      headers: {
        'Accept': 'application/json'
      }
    })
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    
    const contentType = response.headers.get('content-type')
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text()
      throw new Error(`Expected JSON response but got: ${text.substring(0, 200)}...`)
    }
    
    const result = await response.json()
    
    if (result.success) {
      extraOffStats.value = result.statistics
    } else {
      console.error('Error loading extra off stats:', result.message)
    }
  } catch (error) {
    console.error('Error loading extra off stats:', error)
  } finally {
    loadingExtraOffStats.value = false
  }
}

const loadExtraOffTransactions = async () => {
  loadingExtraOffTransactions.value = true
  
  try {
    const response = await fetch('/api/extra-off/all-transactions', {
      headers: {
        'Accept': 'application/json'
      }
    })
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }
    
    const contentType = response.headers.get('content-type')
    if (!contentType || !contentType.includes('application/json')) {
      const text = await response.text()
      throw new Error(`Expected JSON response but got: ${text.substring(0, 200)}...`)
    }
    
    const result = await response.json()
    
    if (result.success) {
      extraOffTransactions.value = result.transactions
    } else {
      console.error('Error loading extra off transactions:', result.message)
    }
  } catch (error) {
    console.error('Error loading extra off transactions:', error)
  } finally {
    loadingExtraOffTransactions.value = false
  }
}

onMounted(() => {
  console.log('Component mounted, props:', props)
  console.log('Compensations data:', props.compensations)
  loadStatistics()
  loadExtraOffStats()
  loadExtraOffTransactions()
})
</script>
