<template>
    <AppLayout title="Extra Off & PH Report">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Extra Off & PH Report
            </h2>
        </template>

        <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
            <div class="h-full">
                <div class="bg-white dark:bg-gray-800 min-h-screen">
                    <div class="p-4 text-gray-900 dark:text-gray-100">
                        
                        <!-- Filters -->
                        <div class="mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                <!-- Start Date -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Tanggal Mulai
                                    </label>
                                    <input
                                        type="date"
                                        v-model="filters.start_date"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    >
                                </div>

                                <!-- End Date -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Tanggal Selesai
                                    </label>
                                    <input
                                        type="date"
                                        v-model="filters.end_date"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    >
                                </div>

                                <!-- Employee -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Karyawan
                                    </label>
                                    <select
                                        v-model="filters.user_id"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="">Semua Karyawan</option>
                                        <option v-for="user in users" :key="user.id" :value="user.id">
                                            {{ user.nama_lengkap }} ({{ user.nik }})
                                        </option>
                                    </select>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-col justify-end">
                                    <div class="flex gap-2">
                                        <button
                                            @click="loadReport"
                                            :disabled="loading"
                                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <i v-if="loading" class="fa-solid fa-spinner fa-spin mr-2"></i>
                                            {{ loading ? 'Loading...' : 'Filter' }}
                                        </button>
                                        <button
                                            @click="exportToExcel"
                                            :disabled="loading || !reportData"
                                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            <i class="fa-solid fa-file-excel mr-2"></i>
                                            Export Excel
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div v-if="reportData" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-calendar-plus text-2xl text-blue-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Extra Off Earned</p>
                                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                            {{ reportData.summary?.extra_off_earned || 0 }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-calendar-minus text-2xl text-red-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Extra Off Used</p>
                                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                            {{ reportData.summary?.extra_off_used || 0 }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-gift text-2xl text-green-600"></i>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Public Holiday</p>
                                        <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                            {{ reportData.summary?.public_holiday_count || 0 }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Report Data -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    Data Report Extra Off & PH
                                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                                        ({{ pagination?.total || 0 }} data)
                                    </span>
                                </h3>
                            </div>

                            <!-- Tabs -->
                            <div class="border-b border-gray-200 dark:border-gray-700">
                                <nav class="-mb-px flex space-x-8 px-6">
                                    <button 
                                        @click="activeTab = 'extra_off'"
                                        :class="[
                                            'py-4 px-1 border-b-2 font-medium text-sm',
                                            activeTab === 'extra_off' 
                                                ? 'border-blue-500 text-blue-600' 
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                        ]"
                                    >
                                        Extra Off Transactions
                                    </button>
                                    <button 
                                        @click="activeTab = 'public_holiday'"
                                        :class="[
                                            'py-4 px-1 border-b-2 font-medium text-sm',
                                            activeTab === 'public_holiday' 
                                                ? 'border-blue-500 text-blue-600' 
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                        ]"
                                    >
                                        Public Holiday Compensations
                                    </button>
                                </nav>
                            </div>

                            <!-- Extra Off Tab -->
                            <div v-if="activeTab === 'extra_off'">
                                <div v-if="reportData?.extra_off?.transactions?.length > 0">
                                    <!-- Action Buttons -->
                                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                        <div class="flex gap-2">
                                            <button @click="selectAllExtraOff" 
                                                    class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
                                                {{ selectedExtraOff.length === reportData.extra_off.transactions.length ? 'Deselect All' : 'Select All' }}
                                            </button>
                                            <button v-if="selectedExtraOff.length > 0" 
                                                    @click="deleteMultipleExtraOff"
                                                    class="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700">
                                                Delete Selected ({{ selectedExtraOff.length }})
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="overflow-x-auto max-h-[calc(100vh-300px)]">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-700">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        <input type="checkbox" 
                                                               :checked="selectedExtraOff.length === reportData.extra_off.transactions.length"
                                                               @change="selectAllExtraOff"
                                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Karyawan
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Outlet
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Jenis
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Jumlah
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Tanggal
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Deskripsi
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Aksi
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                <tr v-for="transaction in reportData.extra_off.transactions" :key="transaction.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <input type="checkbox" 
                                                               :checked="selectedExtraOff.includes(transaction.id)"
                                                               @change="toggleSelectExtraOff(transaction.id)"
                                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ transaction.employee_name }}
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ transaction.outlet_name || '-' }}
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                        <span :class="[
                                                            'px-2 py-1 text-xs font-semibold rounded-full',
                                                            transaction.transaction_type === 'earned' 
                                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' 
                                                                : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
                                                        ]">
                                                            {{ transaction.transaction_type === 'earned' ? 'Earned' : 'Used' }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ transaction.amount }}
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ formatDate(transaction.created_at) }}
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                        {{ transaction.description || '-' }}
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                        <button @click="deleteSingleExtraOff(transaction.id)"
                                                                class="text-red-600 hover:text-red-900 font-medium">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                
                                    <!-- Pagination -->
                                    <div v-if="pagination && pagination.total > 0" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-4">
                                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                                    Menampilkan {{ pagination.from }} sampai {{ pagination.to }} dari {{ pagination.total }} data
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <label class="text-sm text-gray-700 dark:text-gray-300">Per halaman:</label>
                                                    <select 
                                                        v-model="pagination.per_page" 
                                                        @change="changePerPage(pagination.per_page)"
                                                        class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                    >
                                                        <option value="10">10</option>
                                                        <option value="15">15</option>
                                                        <option value="25">25</option>
                                                        <option value="50">50</option>
                                                        <option value="100">100</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center gap-2">
                                                <!-- Previous Button -->
                                                <button
                                                    @click="changePage(pagination.current_page - 1)"
                                                    :disabled="pagination.current_page <= 1"
                                                    class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                                >
                                                    <i class="fa-solid fa-chevron-left"></i>
                                                </button>
                                                
                                                <!-- Page Numbers -->
                                                <div class="flex items-center gap-1">
                                                    <!-- First page -->
                                                    <button
                                                        v-if="pagination.current_page > 3"
                                                        @click="changePage(1)"
                                                        class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700"
                                                    >
                                                        1
                                                    </button>
                                                    
                                                    <!-- Ellipsis -->
                                                    <span v-if="pagination.current_page > 4" class="px-2 text-gray-500">...</span>
                                                    
                                                    <!-- Pages around current page -->
                                                    <button
                                                        v-for="page in getVisiblePages()"
                                                        :key="page"
                                                        @click="changePage(page)"
                                                        :class="[
                                                            'px-3 py-1 text-sm border rounded',
                                                            page === pagination.current_page
                                                                ? 'bg-blue-600 text-white border-blue-600'
                                                                : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                                                        ]"
                                                    >
                                                        {{ page }}
                                                    </button>
                                                    
                                                    <!-- Ellipsis -->
                                                    <span v-if="pagination.current_page < pagination.last_page - 3" class="px-2 text-gray-500">...</span>
                                                    
                                                    <!-- Last page -->
                                                    <button
                                                        v-if="pagination.current_page < pagination.last_page - 2"
                                                        @click="changePage(pagination.last_page)"
                                                        class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700"
                                                    >
                                                        {{ pagination.last_page }}
                                                    </button>
                                                </div>
                                                
                                                <!-- Next Button -->
                                                <button
                                                    @click="changePage(pagination.current_page + 1)"
                                                    :disabled="!pagination.has_more_pages"
                                                    class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                                >
                                                    <i class="fa-solid fa-chevron-right"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else-if="loading" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                                    <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                                    Loading...
                                </div>
                                <div v-else class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                                    Tidak ada data extra off transactions
                                </div>
                            </div>

                            <!-- Public Holiday Tab -->
                            <div v-if="activeTab === 'public_holiday'">
                                <div v-if="reportData?.public_holiday?.compensations?.length > 0">
                                    <!-- Action Buttons -->
                                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                        <div class="flex gap-2">
                                            <button @click="selectAllPublicHoliday" 
                                                    class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700">
                                                {{ selectedPublicHoliday.length === reportData.public_holiday.compensations.length ? 'Deselect All' : 'Select All' }}
                                            </button>
                                            <button v-if="selectedPublicHoliday.length > 0" 
                                                    @click="deleteMultiplePublicHoliday"
                                                    class="px-3 py-1 text-sm bg-red-600 text-white rounded hover:bg-red-700">
                                                Delete Selected ({{ selectedPublicHoliday.length }})
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="overflow-x-auto max-h-[calc(100vh-300px)]">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-700">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        <input type="checkbox" 
                                                               :checked="selectedPublicHoliday.length === reportData.public_holiday.compensations.length"
                                                               @change="selectAllPublicHoliday"
                                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Karyawan
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Outlet
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Tanggal Libur
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Jenis
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Jumlah
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Status
                                                    </th>
                                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                        Aksi
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                <tr v-for="compensation in reportData.public_holiday.compensations" :key="compensation.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                                    <td class="px-4 py-3 whitespace-nowrap">
                                                        <input type="checkbox" 
                                                               :checked="selectedPublicHoliday.includes(compensation.id)"
                                                               @change="toggleSelectPublicHoliday(compensation.id)"
                                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                        {{ compensation.employee_name }}
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ compensation.outlet_name || '-' }}
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ formatDate(compensation.holiday_date) }}
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ compensation.compensation_type }}
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ compensation.compensation_amount }}
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                        <span :class="[
                                                            'px-2 py-1 text-xs font-semibold rounded-full',
                                                            compensation.status === 'approved' 
                                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' 
                                                                : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
                                                        ]">
                                                            {{ compensation.status === 'approved' ? 'Approved' : 'Used' }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                        <button @click="deleteSinglePublicHoliday(compensation.id)"
                                                                class="text-red-600 hover:text-red-900 font-medium">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Pagination -->
                                    <div v-if="pagination && pagination.total > 0" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-4">
                                                <div class="text-sm text-gray-700 dark:text-gray-300">
                                                    Menampilkan {{ pagination.from }} sampai {{ pagination.to }} dari {{ pagination.total }} data
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <label class="text-sm text-gray-700 dark:text-gray-300">Per halaman:</label>
                                                    <select 
                                                        v-model="pagination.per_page" 
                                                        @change="changePerPage(pagination.per_page)"
                                                        class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                    >
                                                        <option value="10">10</option>
                                                        <option value="15">15</option>
                                                        <option value="25">25</option>
                                                        <option value="50">50</option>
                                                        <option value="100">100</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center gap-2">
                                                <!-- Previous Button -->
                                                <button
                                                    @click="changePage(pagination.current_page - 1)"
                                                    :disabled="pagination.current_page <= 1"
                                                    class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                                >
                                                    <i class="fa-solid fa-chevron-left"></i>
                                                </button>
                                                
                                                <!-- Page Numbers -->
                                                <div class="flex items-center gap-1">
                                                    <!-- First page -->
                                                    <button
                                                        v-if="pagination.current_page > 3"
                                                        @click="changePage(1)"
                                                        class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700"
                                                    >
                                                        1
                                                    </button>
                                                    
                                                    <!-- Ellipsis -->
                                                    <span v-if="pagination.current_page > 4" class="px-2 text-gray-500">...</span>
                                                    
                                                    <!-- Pages around current page -->
                                                    <button
                                                        v-for="page in getVisiblePages()"
                                                        :key="page"
                                                        @click="changePage(page)"
                                                        :class="[
                                                            'px-3 py-1 text-sm border rounded',
                                                            page === pagination.current_page
                                                                ? 'bg-blue-600 text-white border-blue-600'
                                                                : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'
                                                        ]"
                                                    >
                                                        {{ page }}
                                                    </button>
                                                    
                                                    <!-- Ellipsis -->
                                                    <span v-if="pagination.current_page < pagination.last_page - 3" class="px-2 text-gray-500">...</span>
                                                    
                                                    <!-- Last page -->
                                                    <button
                                                        v-if="pagination.current_page < pagination.last_page - 2"
                                                        @click="changePage(pagination.last_page)"
                                                        class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700"
                                                    >
                                                        {{ pagination.last_page }}
                                                    </button>
                                                </div>
                                                
                                                <!-- Next Button -->
                                                <button
                                                    @click="changePage(pagination.current_page + 1)"
                                                    :disabled="!pagination.has_more_pages"
                                                    class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                                >
                                                    <i class="fa-solid fa-chevron-right"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-else-if="loading" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                                    <i class="fa-solid fa-spinner fa-spin mr-2"></i>
                                    Loading...
                                </div>
                                <div v-else class="px-4 py-3 text-center text-gray-500 dark:text-gray-400">
                                    Tidak ada data public holiday compensations
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
import { ref, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'
import Swal from 'sweetalert2'

// Reactive data
const loading = ref(false)
const reportData = ref(null)
const activeTab = ref('extra_off')
const users = ref([])

// Pagination
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
    from: 0,
    to: 0,
    has_more_pages: false
})

// Delete functionality
const selectedExtraOff = ref([])
const selectedPublicHoliday = ref([])

const filters = ref({
    start_date: '',
    end_date: '',
    user_id: ''
})

// Methods
const loadReport = async (page = 1) => {
    if (!filters.value.start_date || !filters.value.end_date) {
        Swal.fire({
            icon: 'warning',
            title: 'Validation Error',
            text: 'Please select start and end dates',
            confirmButtonText: 'OK'
        })
        return
    }
    
    // Validate date range
    if (new Date(filters.value.start_date) > new Date(filters.value.end_date)) {
        Swal.fire({
            icon: 'warning',
            title: 'Validation Error',
            text: 'Start date cannot be greater than end date',
            confirmButtonText: 'OK'
        })
        return
    }

    loading.value = true
    pagination.value.current_page = page
    
    try {
        const params = new URLSearchParams()
        params.append('start_date', filters.value.start_date)
        params.append('end_date', filters.value.end_date)
        if (filters.value.user_id && filters.value.user_id !== '') {
            params.append('user_id', filters.value.user_id.toString())
        }
        params.append('page', String(page))
        params.append('per_page', String(pagination.value?.per_page || 15))
        
        console.log('Request parameters:', {
            page: String(page),
            per_page: String(pagination.value?.per_page || 15),
            page_type: typeof page,
            per_page_type: typeof (pagination.value?.per_page || 15),
            url: `/api/extra-off-report/data?${params.toString()}`
        })
        
        const response = await axios.get(`/api/extra-off-report/data?${params.toString()}`)
        
        if (response.data.success) {
            reportData.value = response.data.data
            pagination.value = response.data.pagination || {
                current_page: 1,
                last_page: 1,
                per_page: 15,
                total: 0,
                from: 0,
                to: 0,
                has_more_pages: false
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error loading report: ' + response.data.message,
                confirmButtonText: 'OK'
            })
        }
    } catch (error) {
        console.error('Error loading report:', error)
        
        let errorMessage = 'Error loading report data'
        if (error.response && error.response.data && error.response.data.message) {
            errorMessage = error.response.data.message
        } else if (error.response && error.response.status === 422) {
            if (error.response.data && error.response.data.errors) {
                const errors = Object.values(error.response.data.errors).flat()
                errorMessage = `Validation error: ${errors.join(', ')}`
            } else {
                errorMessage = 'Validation error: Please check your input parameters'
            }
        } else if (error.response && error.response.status === 404) {
            errorMessage = 'User not found'
        }
        
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorMessage,
            confirmButtonText: 'OK'
        })
        reportData.value = null
        pagination.value = {
            current_page: 1,
            last_page: 1,
            per_page: 15,
            total: 0,
            from: 0,
            to: 0,
            has_more_pages: false
        }
    } finally {
        loading.value = false
    }
}

const loadUsers = async () => {
    try {
        const response = await axios.get('/api/users')
        if (response.data.success) {
            users.value = response.data.data
        }
    } catch (error) {
        console.error('Error loading users:', error)
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to load users list',
            confirmButtonText: 'OK'
        })
    }
}

const formatDate = (dateString) => {
    if (!dateString) return ''
    const date = new Date(dateString)
    return date.toLocaleDateString('id-ID')
}

const formatDateTime = (dateTime) => {
    if (!dateTime) return '-'
    return new Date(dateTime).toLocaleString('id-ID')
}

const exportToExcel = async () => {
    if (!filters.value.start_date || !filters.value.end_date) {
        Swal.fire({
            icon: 'warning',
            title: 'Validation Error',
            text: 'Please select start and end dates',
            confirmButtonText: 'OK'
        })
        return
    }
    
    // Validate date range
    if (new Date(filters.value.start_date) > new Date(filters.value.end_date)) {
        Swal.fire({
            icon: 'warning',
            title: 'Validation Error',
            text: 'Start date cannot be greater than end date',
            confirmButtonText: 'OK'
        })
        return
    }

    try {
        const response = await axios.get('/api/extra-off-report/export', {
            params: filters.value
        })
        
        if (response.data.success) {
            // Create CSV content from the data
            const csvContent = createCSVContent(response.data.data)
            
            // Create and download CSV file
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
            const link = document.createElement('a')
            const url = URL.createObjectURL(blob)
            link.setAttribute('href', url)
            link.setAttribute('download', response.data.filename || `extra_off_ph_report_${new Date().toISOString().split('T')[0]}.csv`)
            link.style.visibility = 'hidden'
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
            
            Swal.fire({
                icon: 'success',
                title: 'Export Successful',
                text: 'Report has been exported successfully',
                confirmButtonText: 'OK'
            })
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Export Failed',
                text: response.data.message || 'Failed to export report',
                confirmButtonText: 'OK'
            })
        }
    } catch (error) {
        console.error('Error exporting to Excel:', error)
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to export report to Excel',
            confirmButtonText: 'OK'
        })
    }
}

const createCSVContent = (data) => {
    let csvContent = '\uFEFF' // BOM for UTF-8
    
    // Summary section
    csvContent += 'SUMMARY REPORT\n'
    csvContent += 'Extra Off Earned,' + (data.summary?.extra_off_earned || 0) + '\n'
    csvContent += 'Extra Off Used,' + (data.summary?.extra_off_used || 0) + '\n'
    csvContent += 'Public Holiday Count,' + (data.summary?.public_holiday_count || 0) + '\n\n'
    
    // Extra Off Transactions
    csvContent += 'EXTRA OFF TRANSACTIONS\n'
    csvContent += 'Employee Name,Outlet,Type,Amount,Date,Description\n'
    
    if (data.extra_off?.transactions) {
        data.extra_off.transactions.forEach(transaction => {
            csvContent += `"${transaction.employee_name || ''}","${transaction.outlet_name || '-'}","${transaction.transaction_type || ''}","${transaction.amount || ''}","${formatDate(transaction.created_at)}","${transaction.description || ''}"\n`
        })
    }
    
    csvContent += '\n'
    
    // Public Holiday Compensations
    csvContent += 'PUBLIC HOLIDAY COMPENSATIONS\n'
    csvContent += 'Employee Name,Outlet,Holiday Date,Type,Amount,Status\n'
    
    if (data.public_holiday?.compensations) {
        data.public_holiday.compensations.forEach(compensation => {
            csvContent += `"${compensation.employee_name || ''}","${compensation.outlet_name || '-'}","${formatDate(compensation.holiday_date)}","${compensation.compensation_type || ''}","${compensation.compensation_amount || ''}","${compensation.status || ''}"\n`
        })
    }
    
    return csvContent
}

const getVisiblePages = () => {
    const current = pagination.value.current_page
    const last = pagination.value.last_page
    const pages = []
    
    // Show 2 pages before and after current page
    const start = Math.max(1, current - 2)
    const end = Math.min(last, current + 2)
    
    for (let i = start; i <= end; i++) {
        pages.push(i)
    }
    
    return pages
}

// Pagination functions
const changePage = (page) => {
    const pageNum = parseInt(page)
    if (pageNum >= 1 && pageNum <= (pagination.value?.last_page || 1)) {
        loadReport(pageNum)
    } else {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Page',
            text: 'Page number is out of range',
            confirmButtonText: 'OK'
        })
    }
}

const changePerPage = (perPage) => {
    const newPerPage = parseInt(perPage)
    if (newPerPage >= 1 && newPerPage <= 100) {
        pagination.value.per_page = newPerPage
        loadReport(1)
    } else {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Value',
            text: 'Items per page must be between 1 and 100',
            confirmButtonText: 'OK'
        })
    }
}

// Delete functions
const toggleSelectExtraOff = (id) => {
    const index = selectedExtraOff.value.indexOf(id)
    if (index > -1) {
        selectedExtraOff.value.splice(index, 1)
    } else {
        selectedExtraOff.value.push(id)
    }
}

const toggleSelectPublicHoliday = (id) => {
    const index = selectedPublicHoliday.value.indexOf(id)
    if (index > -1) {
        selectedPublicHoliday.value.splice(index, 1)
    } else {
        selectedPublicHoliday.value.push(id)
    }
}

const selectAllExtraOff = () => {
    if (selectedExtraOff.value.length === reportData.value?.extra_off?.transactions?.length) {
        selectedExtraOff.value = []
    } else {
        selectedExtraOff.value = reportData.value?.extra_off?.transactions?.map(t => t.id) || []
    }
}

const selectAllPublicHoliday = () => {
    if (selectedPublicHoliday.value.length === reportData.value?.public_holiday?.compensations?.length) {
        selectedPublicHoliday.value = []
    } else {
        selectedPublicHoliday.value = reportData.value?.public_holiday?.compensations?.map(c => c.id) || []
    }
}

const deleteSingleExtraOff = async (id) => {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: 'You won\'t be able to revert this!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    })

    if (result.isConfirmed) {
        try {
            const response = await axios.delete(`/api/extra-off-report/extra-off/${id}`)
            if (response.data.success) {
                Swal.fire('Deleted!', response.data.message, 'success')
                loadReport(pagination.value.current_page)
            } else {
                Swal.fire('Error!', response.data.message, 'error')
            }
        } catch (error) {
            Swal.fire('Error!', 'Failed to delete transaction', 'error')
        }
    }
}

const deleteSinglePublicHoliday = async (id) => {
    const result = await Swal.fire({
        title: 'Are you sure?',
        text: 'You won\'t be able to revert this!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    })

    if (result.isConfirmed) {
        try {
            const response = await axios.delete(`/api/extra-off-report/public-holiday/${id}`)
            if (response.data.success) {
                Swal.fire('Deleted!', response.data.message, 'success')
                loadReport(pagination.value.current_page)
            } else {
                Swal.fire('Error!', response.data.message, 'error')
            }
        } catch (error) {
            Swal.fire('Error!', 'Failed to delete compensation', 'error')
        }
    }
}

const deleteMultipleExtraOff = async () => {
    if (selectedExtraOff.value.length === 0) {
        Swal.fire('Warning!', 'Please select items to delete', 'warning')
        return
    }

    const result = await Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete ${selectedExtraOff.value.length} transactions!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete them!'
    })

    if (result.isConfirmed) {
        try {
            const response = await axios.delete('/api/extra-off-report/extra-off-multiple', {
                data: { ids: selectedExtraOff.value }
            })
            if (response.data.success) {
                Swal.fire('Deleted!', response.data.message, 'success')
                selectedExtraOff.value = []
                loadReport(pagination.value.current_page)
            } else {
                Swal.fire('Error!', response.data.message, 'error')
            }
        } catch (error) {
            Swal.fire('Error!', 'Failed to delete transactions', 'error')
        }
    }
}

const deleteMultiplePublicHoliday = async () => {
    if (selectedPublicHoliday.value.length === 0) {
        Swal.fire('Warning!', 'Please select items to delete', 'warning')
        return
    }

    const result = await Swal.fire({
        title: 'Are you sure?',
        text: `You are about to delete ${selectedPublicHoliday.value.length} compensations!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete them!'
    })

    if (result.isConfirmed) {
        try {
            const response = await axios.delete('/api/extra-off-report/public-holiday-multiple', {
                data: { ids: selectedPublicHoliday.value }
            })
            if (response.data.success) {
                Swal.fire('Deleted!', response.data.message, 'success')
                selectedPublicHoliday.value = []
                loadReport(pagination.value.current_page)
            } else {
                Swal.fire('Error!', response.data.message, 'error')
            }
        } catch (error) {
            Swal.fire('Error!', 'Failed to delete compensations', 'error')
        }
    }
}

// Initialize
onMounted(() => {
    loadUsers()
})
</script>