<template>
  <AppLayout title="Attendance">
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Jadwal Kerja & Attendance
        </h2>
        <div class="flex items-center gap-4">
          <div class="text-sm text-gray-600 dark:text-gray-400">
            Selamat datang, <span class="font-medium">{{ user.nama_lengkap }}</span>
          </div>
        </div>
      </div>
    </template>

    <div class="py-4">
      <div class="w-full px-4 sm:px-6 lg:px-8">
        <!-- Filter Section -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4">
          <div class="p-4">
            <div class="flex flex-wrap items-center gap-4">
              <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Periode:</label>
                <select
                  v-model="filters.bulan"
                  class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  @change="fetchData"
                >
                  <option value="1">Januari</option>
                  <option value="2">Februari</option>
                  <option value="3">Maret</option>
                  <option value="4">April</option>
                  <option value="5">Mei</option>
                  <option value="6">Juni</option>
                  <option value="7">Juli</option>
                  <option value="8">Agustus</option>
                  <option value="9">September</option>
                  <option value="10">Oktober</option>
                  <option value="11">November</option>
                  <option value="12">Desember</option>
                </select>
                <select
                  v-model="filters.tahun"
                  class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  @change="fetchData"
                >
                  <option v-for="year in yearOptions" :key="year" :value="year">{{ year }}</option>
                </select>
              </div>
              <button
                @click="resetFilters"
                class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors"
              >
                Reset
              </button>
            </div>
          </div>
        </div>

        <!-- Attendance Summary -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-3 mb-4">
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-3 sm:p-4">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-6 h-6 sm:w-8 sm:h-8 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-check text-green-600 dark:text-green-400 text-xs sm:text-sm"></i>
                  </div>
                </div>
                <div class="ml-2 sm:ml-4">
                  <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Hadir</p>
                  <p class="text-lg sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ attendanceSummary.present_days || 0 }} Hari
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-3 sm:p-4">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-6 h-6 sm:w-8 sm:h-8 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 dark:text-yellow-400 text-xs sm:text-sm"></i>
                  </div>
                </div>
                <div class="ml-2 sm:ml-4">
                  <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Terlambat</p>
                  <p class="text-lg sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ attendanceSummary.total_late_minutes || 0 }} Menit
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-3 sm:p-4">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-6 h-6 sm:w-8 sm:h-8 bg-red-100 dark:bg-red-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-times text-red-600 dark:text-red-400 text-xs sm:text-sm"></i>
                  </div>
                </div>
                <div class="ml-2 sm:ml-4">
                  <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Tidak Hadir</p>
                  <p class="text-lg sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ attendanceSummary.absent_days || 0 }} Hari
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-3 sm:p-4">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-6 h-6 sm:w-8 sm:h-8 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-hourglass-half text-orange-600 dark:text-orange-400 text-xs sm:text-sm"></i>
                  </div>
                </div>
                <div class="ml-2 sm:ml-4">
                  <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Lembur</p>
                  <p class="text-lg sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ attendanceSummary.total_lembur_hours || 0 }} Jam
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-3 sm:p-4">
              <div class="flex items-center">
                <div class="flex-shrink-0">
                  <div class="w-6 h-6 sm:w-8 sm:h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                    <i class="fas fa-percentage text-blue-600 dark:text-blue-400 text-xs sm:text-sm"></i>
                  </div>
                </div>
                <div class="ml-2 sm:ml-4">
                  <p class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400">Persentase</p>
                  <p class="text-lg sm:text-2xl font-semibold text-gray-900 dark:text-gray-100">
                    {{ attendanceSummary.percentage || 0 }}%
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Calendar Section -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4">
          <div class="p-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">
              Kalender Jadwal Kerja
            </h3>
            <div class="calendar-container">
              <!-- Desktop Header -->
              <div class="hidden sm:grid grid-cols-7 gap-0.5 mb-1">
                <div class="text-center text-sm font-medium text-gray-500 dark:text-gray-400 py-1">Minggu</div>
                <div class="text-center text-sm font-medium text-gray-500 dark:text-gray-400 py-1">Senin</div>
                <div class="text-center text-sm font-medium text-gray-500 dark:text-gray-400 py-1">Selasa</div>
                <div class="text-center text-sm font-medium text-gray-500 dark:text-gray-400 py-1">Rabu</div>
                <div class="text-center text-sm font-medium text-gray-500 dark:text-gray-400 py-1">Kamis</div>
                <div class="text-center text-sm font-medium text-gray-500 dark:text-gray-400 py-1">Jumat</div>
                <div class="text-center text-sm font-medium text-gray-500 dark:text-gray-400 py-1">Sabtu</div>
              </div>
              
              <!-- Mobile Header -->
              <div class="sm:hidden grid grid-cols-7 gap-0.5 mb-1">
                <div class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 py-1">Min</div>
                <div class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 py-1">Sen</div>
                <div class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 py-1">Sel</div>
                <div class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 py-1">Rab</div>
                <div class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 py-1">Kam</div>
                <div class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 py-1">Jum</div>
                <div class="text-center text-xs font-medium text-gray-500 dark:text-gray-400 py-1">Sab</div>
              </div>
              
              <div class="grid grid-cols-7 gap-0.5">
                <div
                  v-for="day in calendarDays"
                  :key="day.date"
                  class="min-h-[60px] sm:min-h-[100px] border border-gray-200 dark:border-gray-600 p-0.5 sm:p-1 relative"
                  :class="{
                    'bg-gray-50 dark:bg-gray-700': !day.isInPayrollPeriod,
                    'bg-white dark:bg-gray-800': day.isInPayrollPeriod && !day.holiday,
                    'bg-blue-50 dark:bg-blue-900': day.isToday,
                    'bg-red-100 dark:bg-red-900': day.holiday
                  }"
                >
                  <div class="text-xs sm:text-sm font-medium mb-0.5" :class="{
                    'text-gray-400 dark:text-gray-500': !day.isInPayrollPeriod,
                    'text-gray-900 dark:text-gray-100': day.isInPayrollPeriod && !day.isToday && !day.holiday,
                    'text-blue-600 dark:text-blue-400': day.isToday,
                    'text-red-600 dark:text-red-400': day.holiday
                  }">
                    {{ day.day }}
                  </div>
                  <div v-if="day.holiday" class="text-xs sm:text-xs text-red-600 dark:text-red-400 font-bold mb-0.5 leading-tight">
                    {{ day.holiday.name }}
                  </div>
                  <div v-if="day.schedules && day.schedules.length > 0" class="space-y-0.5">
                    <div
                      v-for="schedule in day.schedules"
                      :key="schedule.shift_name"
                      class="text-xs sm:text-xs p-0.5 rounded font-semibold shadow-sm"
                      :class="getScheduleClass(schedule)"
                    >
                      <div class="font-medium">{{ schedule.shift_name || 'OFF' }}</div>
                      <div v-if="schedule.time_start && schedule.time_end" class="hidden sm:block text-xs opacity-75">{{ schedule.time_start }} - {{ schedule.time_end }}</div>
                      
                      <!-- Attendance info -->
                      <div v-if="schedule.has_attendance && schedule.first_in" class="mt-1 pt-1 border-t border-white/20">
                        <div class="text-xs opacity-90">
                          <!-- Mobile: Show only check-in time -->
                          <div class="sm:hidden flex items-center gap-1">
                            <i class="fa-solid fa-check text-green-400"></i>
                            <span class="text-green-400">{{ schedule.first_in }}</span>
                            <span v-if="schedule.has_no_checkout" class="ml-1 text-red-500 font-bold text-xs">⚠️</span>
                          </div>
                          
                          <!-- Desktop: Show all details -->
                          <div class="hidden sm:block">
                            <div class="flex items-center gap-1">
                              <i class="fa-solid fa-sign-in-alt text-green-400"></i>
                              <span class="text-green-400">{{ schedule.first_in }}</span>
                            </div>
                            <div v-if="schedule.last_out" class="flex items-center gap-1 mt-0.5">
                              <i class="fa-solid fa-sign-out-alt text-red-400"></i>
                              <span class="text-red-400">{{ schedule.last_out }}</span>
                            </div>
                            <div v-else-if="schedule.has_no_checkout" class="flex items-center gap-1 mt-0.5">
                              <i class="fa-solid fa-exclamation-triangle text-red-500"></i>
                              <span class="text-red-500 font-bold">TIDAK CHECKOUT</span>
                            </div>
                            
                            <!-- Telat and Lembur -->
                            <div v-if="schedule.telat > 0 || schedule.lembur > 0" class="mt-1 space-y-0.5">
                              <div v-if="schedule.telat > 0" class="flex items-center gap-1">
                                <i class="fa-solid fa-clock text-yellow-400"></i>
                                <span class="text-yellow-400">Telat {{ schedule.telat }} Menit</span>
                              </div>
                              <div v-if="schedule.lembur > 0" class="flex items-center gap-1">
                                <i class="fa-solid fa-hourglass-half text-orange-400"></i>
                                <span class="text-orange-400">Lembur {{ schedule.lembur }} Jam</span>
                              </div>
                            </div>
                          </div>
                          
                          <button 
                            @click="showAttendanceDetail(day.date)"
                            class="mt-1 text-xs bg-blue-500 hover:bg-blue-600 text-white px-1 py-0.5 rounded transition-colors"
                          >
                            <i class="fa-solid fa-info-circle sm:mr-1"></i>
                            <span class="hidden sm:inline">Detail</span>
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Approved Absent Display -->
                  <div v-if="getApprovedAbsentForDate(day.date)" class="mt-1">
                    <div class="w-full text-xs bg-green-500 text-white px-1 py-0.5 rounded">
                      <i class="fa-solid fa-check-circle sm:mr-1"></i>
                      <span class="hidden sm:inline">{{ getApprovedAbsentForDate(day.date).leave_type_name }}</span>
                      <span class="sm:hidden">✓</span>
                    </div>
                    <div class="text-xs text-green-600 dark:text-green-400 mt-0.5">
                      {{ getApprovedAbsentForDate(day.date).reason }}
                    </div>
                  </div>
                  
                  <!-- Absent Button - Show only if no attendance data, no approved absent, is in payroll period, not past date, and backdate is allowed -->
                  <div v-else-if="day.isInPayrollPeriod && !day.holiday && !hasAttendanceData(day) && !isPastDate(day.date) && isBackdateAllowed(day)" class="mt-1">
                    <button 
                      @click="showAbsentModal(day.date)"
                      class="w-full text-xs bg-red-500 hover:bg-red-600 text-white px-1 py-0.5 rounded transition-colors"
                    >
                      <i class="fa-solid fa-user-times sm:mr-1"></i>
                      <span class="hidden sm:inline">Absent</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Attendance Detail Modal -->
    <div v-if="showDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-hidden">
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Detail Absensi - {{ formatDate(selectedDate) }}
          </h3>
          <button 
            @click="closeDetailModal"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>
        
        <div class="p-4 overflow-y-auto max-h-[60vh]">
          <div v-if="loadingDetail" class="text-center py-8">
            <i class="fa-solid fa-spinner fa-spin text-2xl text-blue-500"></i>
            <p class="mt-2 text-gray-600 dark:text-gray-400">Memuat detail absensi...</p>
          </div>
          
          <div v-else-if="attendanceDetail.length === 0" class="text-center py-8">
            <i class="fa-solid fa-calendar-times text-4xl text-gray-400 mb-4"></i>
            <p class="text-gray-600 dark:text-gray-400">Tidak ada data absensi untuk tanggal ini</p>
          </div>
          
          <div v-else class="space-y-4">
            <div 
              v-for="(detail, index) in attendanceDetail" 
              :key="index"
              class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4"
            >
              <div class="flex items-center justify-between mb-3">
                <h4 class="font-semibold text-gray-900 dark:text-gray-100">
                  {{ detail.nama_outlet }}
                </h4>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                  Total: IN {{ detail.total_in }}, OUT {{ detail.total_out }}
                </span>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-sign-in-alt text-green-500"></i>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Jam Masuk:</span>
                    <span class="text-sm text-gray-900 dark:text-gray-100">
                      {{ detail.jam_in || '-' }}
                    </span>
                  </div>
                  
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-sign-out-alt text-red-500"></i>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Jam Keluar:</span>
                    <span v-if="detail.has_no_checkout" class="text-sm text-red-600 font-bold">
                      <i class="fa-solid fa-exclamation-triangle mr-1"></i>TIDAK CHECKOUT
                    </span>
                    <span v-else class="text-sm text-gray-900 dark:text-gray-100">
                      {{ detail.jam_out || '-' }}
                    </span>
                  </div>
                </div>
                
                <div class="space-y-2">
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-clock text-blue-500"></i>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Durasi Kerja:</span>
                    <span class="text-sm text-gray-900 dark:text-gray-100">
                      {{ calculateWorkDuration(detail.jam_in, detail.jam_out) }}
                    </span>
                  </div>
                  
                  <div class="flex items-center gap-2">
                    <i class="fa-solid fa-building text-purple-500"></i>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Outlet:</span>
                    <span class="text-sm text-gray-900 dark:text-gray-100">
                      {{ detail.nama_outlet }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="flex justify-end p-4 border-t border-gray-200 dark:border-gray-700">
          <button 
            @click="closeDetailModal"
            class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-lg transition-colors"
          >
            Tutup
          </button>
        </div>
      </div>
        </div>

        <!-- My Leave Requests Section -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4">
          <div class="p-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
              <i class="fa-solid fa-calendar-xmark mr-2 text-blue-500"></i>
              Permohonan Izin/Cuti
            </h3>
            
            <!-- Show message if no requests -->
            <div v-if="!userLeaveRequests || userLeaveRequests.length === 0" class="text-center py-8">
              <div class="text-gray-400 dark:text-gray-500 mb-4">
                <i class="fa-solid fa-calendar-check text-4xl"></i>
              </div>
              <p class="text-gray-600 dark:text-gray-400 text-sm">
                Tidak ada permohonan izin/cuti untuk bulan ini
              </p>
              <p class="text-gray-500 dark:text-gray-500 text-xs mt-1">
                Semua permohonan (disetujui, ditolak, atau dibatalkan) akan ditampilkan di sini
              </p>
            </div>
            
            <!-- Show active requests -->
            <div v-else class="space-y-3">
              <div
                v-for="request in userLeaveRequests"
                :key="request.id"
                class="border border-gray-200 dark:border-gray-600 rounded-lg p-4"
                :class="{
                  'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-700': request.status === 'pending',
                  'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700': request.status === 'supervisor_approved' || request.status === 'approved',
                  'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700': request.status === 'rejected',
                  'bg-gray-50 dark:bg-gray-900/20 border-gray-200 dark:border-gray-700': request.status === 'cancelled'
                }"
              >
                <div class="flex items-center justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="{
                              'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': request.status === 'pending',
                              'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': request.status === 'supervisor_approved' || request.status === 'approved',
                              'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': request.status === 'rejected',
                              'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200': request.status === 'cancelled'
                            }">
                        {{ getStatusText(request.status) }}
                      </span>
                      <span class="text-sm text-gray-600 dark:text-gray-400">
                        {{ request.leave_type_name }}
                      </span>
                    </div>
                    
                    <div class="text-sm text-gray-900 dark:text-gray-100 mb-1">
                      <strong>Periode:</strong> {{ formatDateRange(request.date_from, request.date_to) }}
                    </div>
                    
                    <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                      <strong>Alasan:</strong> {{ request.reason }}
                    </div>
                    
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                      <strong>Diajukan:</strong> {{ formatDateTime(request.created_at) }}
                    </div>
                    
                  </div>
                  
                  <!-- Cancel Button -->
                  <div v-if="canCancelRequest(request)" class="ml-4">
                    <button
                      @click="showCancelModal(request)"
                      class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                    >
                      <i class="fa-solid fa-times mr-1"></i>
                      Batalkan
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- PH and Extra Off Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
          <!-- PH (Public Holiday) Section -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                <i class="fa-solid fa-calendar-star mr-2 text-blue-500"></i>
                Public Holiday (PH)
              </h3>
              
              <!-- PH Summary -->
              <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3 border border-blue-200 dark:border-blue-700">
                  <div class="text-center">
                    <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">
                      {{ phData.total_days }}
                    </div>
                    <div class="text-sm text-blue-700 dark:text-blue-300">Total Hari PH</div>
                  </div>
                </div>
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-3 border border-green-200 dark:border-green-700">
                  <div class="text-center">
                    <div class="text-2xl font-bold text-green-900 dark:text-green-100">
                      {{ phData.total_bonus.toLocaleString('id-ID') }}
                    </div>
                    <div class="text-sm text-green-700 dark:text-green-300">Total Bonus (Rp)</div>
                  </div>
                </div>
              </div>

              <!-- PH Details -->
              <div v-if="phData.compensations && phData.compensations.length > 0" class="space-y-2">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Detail PH Bulan Ini:</div>
                <div class="space-y-2 max-h-40 overflow-y-auto">
                  <div 
                    v-for="compensation in phData.compensations" 
                    :key="compensation.id"
                    class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded border"
                  >
                    <div class="flex-1">
                      <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ compensation.holiday_name || 'Hari Libur' }}
                      </div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ formatDate(compensation.holiday_date) }}
                      </div>
                    </div>
                    <div class="text-right">
                      <span 
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                        :class="{
                          'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': compensation.compensation_type === 'extra_off',
                          'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': compensation.compensation_type === 'bonus'
                        }"
                      >
                        {{ compensation.compensation_type === 'extra_off' ? 'Extra Off' : 'Bonus' }}
                      </span>
                      <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ compensation.compensation_type === 'bonus' ? 'Rp ' + compensation.compensation_amount.toLocaleString('id-ID') : compensation.compensation_amount + ' hari' }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- No PH Data -->
              <div v-else class="text-center py-4">
                <div class="text-gray-400 dark:text-gray-500 mb-2">
                  <i class="fa-solid fa-calendar-xmark text-2xl"></i>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  Tidak ada PH untuk bulan ini
                </p>
              </div>
            </div>
          </div>

          <!-- Extra Off Section -->
          <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                <i class="fa-solid fa-calendar-plus mr-2 text-purple-500"></i>
                Extra Off
              </h3>
              
              <!-- Extra Off Summary -->
              <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-3 border border-purple-200 dark:border-purple-700">
                  <div class="text-center">
                    <div class="text-2xl font-bold text-purple-900 dark:text-purple-100">
                      {{ extraOffData.current_balance }}
                    </div>
                    <div class="text-sm text-purple-700 dark:text-purple-300">Saldo Saat Ini</div>
                  </div>
                </div>
                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-3 border border-orange-200 dark:border-orange-700">
                  <div class="text-center">
                    <div class="text-2xl font-bold text-orange-900 dark:text-orange-100">
                      {{ extraOffData.period_net }}
                    </div>
                    <div class="text-sm text-orange-700 dark:text-orange-300">Net Bulan Ini</div>
                  </div>
                </div>
              </div>

              <!-- Extra Off Details -->
              <div v-if="extraOffData.transactions && extraOffData.transactions.length > 0" class="space-y-2">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Transaksi Bulan Ini:</div>
                <div class="space-y-2 max-h-40 overflow-y-auto">
                  <div 
                    v-for="transaction in extraOffData.transactions" 
                    :key="transaction.id"
                    class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700 rounded border"
                  >
                    <div class="flex-1">
                      <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ getTransactionDescription(transaction) }}
                      </div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ formatDateTime(transaction.created_at) }}
                      </div>
                    </div>
                    <div class="text-right">
                      <span 
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                        :class="{
                          'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': transaction.transaction_type === 'earned',
                          'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': transaction.transaction_type === 'used'
                        }"
                      >
                        {{ transaction.transaction_type === 'earned' ? '+' : '-' }}{{ transaction.amount }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- No Extra Off Data -->
              <div v-else class="text-center py-4">
                <div class="text-gray-400 dark:text-gray-500 mb-2">
                  <i class="fa-solid fa-calendar-plus text-2xl"></i>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                  Tidak ada transaksi extra off untuk bulan ini
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Absent Modal -->
    <div v-if="showAbsentModalFlag" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Ajukan Izin/Cuti - {{ formatDate(selectedAbsentDate) }}
          </h3>
          <button 
            @click="closeAbsentModal"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>
        
        <div class="p-4 overflow-y-auto flex-1">
          <form @submit.prevent="submitAbsentRequest">
            <div class="space-y-4">
              <!-- Leave Type -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Jenis Izin/Cuti
                </label>
                <select 
                  v-model="absentForm.leave_type_id"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                  required
                >
                  <option value="">Pilih jenis izin/cuti</option>
                  <option 
                    v-for="leaveType in leaveTypes" 
                    :key="leaveType.id" 
                    :value="leaveType.id"
                  >
                    {{ leaveType.name }}
                    <span v-if="leaveType.max_days > 0">({{ leaveType.max_days }} hari)</span>
                  </option>
                </select>
                <p v-if="selectedLeaveType && selectedLeaveType.description" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  {{ selectedLeaveType.description }}
                </p>
              </div>
              
              <!-- Public Holiday Extra Off Days Balance - Show only if Public Holiday is selected -->
              <div v-if="isPublicHolidayType" class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-700">
                <div class="flex items-center justify-between">
                  <div>
                    <h4 class="text-sm font-medium text-blue-900 dark:text-blue-100">Saldo Extra Off dari Public Holiday</h4>
                    <p class="text-xs text-blue-700 dark:text-blue-300 mt-1">
                      Total hari extra off dari kerja di hari libur nasional
                    </p>
                  </div>
                  <div class="text-right">
                    <div v-if="loadingExtraOff" class="flex items-center">
                      <i class="fas fa-spinner fa-spin text-blue-600 mr-2"></i>
                      <span class="text-sm text-blue-600">Loading...</span>
                    </div>
                    <div v-else>
                      <span class="text-2xl font-bold text-blue-900 dark:text-blue-100">
                        {{ totalExtraOffDays }}
                      </span>
                      <span class="text-sm text-blue-700 dark:text-blue-300 ml-1">hari</span>
                    </div>
                  </div>
                </div>
                
                <!-- Show warning if no extra off days available -->
                <div v-if="!loadingExtraOff && totalExtraOffDays === 0" class="mt-3 p-2 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-200 dark:border-yellow-700">
                  <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                    <span class="text-sm text-yellow-800 dark:text-yellow-200">
                      Anda tidak memiliki saldo extra off dari kerja di hari libur nasional.
                    </span>
                  </div>
                </div>
                
                <!-- Show extra off days details -->
                <div v-if="!loadingExtraOff && totalExtraOffDays > 0" class="mt-3">
                  <details class="text-xs">
                    <summary class="cursor-pointer text-blue-700 dark:text-blue-300 hover:text-blue-900 dark:hover:text-blue-100">
                      Lihat detail extra off days
                    </summary>
                    <div class="mt-2 space-y-1">
                      <div v-for="extraOff in extraOffDays" :key="extraOff.id" class="flex justify-between items-center p-2 bg-white dark:bg-gray-800 rounded border">
                        <div>
                          <span class="font-medium">{{ extraOff.holiday_name }}</span>
                          <span class="text-gray-500 dark:text-gray-400 ml-2">({{ extraOff.holiday_date }})</span>
                        </div>
                        <span class="text-green-600 dark:text-green-400 font-medium">1 hari</span>
                      </div>
                    </div>
                  </details>
                </div>
              </div>
              
              <!-- Annual Leave Balance - Show only if Annual Leave is selected -->
              <div v-if="isAnnualLeaveType" class="mt-4 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-700">
                <div class="flex items-center justify-between">
                  <div>
                    <h4 class="text-sm font-medium text-green-900 dark:text-green-100">Saldo Cuti Tahunan</h4>
                    <p class="text-xs text-green-700 dark:text-green-300 mt-1">
                      Total hari cuti tahunan yang tersedia
                    </p>
                  </div>
                  <div class="text-right">
                    <span class="text-2xl font-bold text-green-900 dark:text-green-100">
                      {{ annualLeaveBalance }}
                    </span>
                    <span class="text-sm text-green-700 dark:text-green-300 ml-1">hari</span>
                  </div>
                </div>
                
                <!-- Show warning if no annual leave balance available -->
                <div v-if="annualLeaveBalance === 0" class="mt-3 p-2 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-200 dark:border-yellow-700">
                  <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                    <span class="text-sm text-yellow-800 dark:text-yellow-200">
                      Anda tidak memiliki saldo cuti tahunan yang tersedia.
                    </span>
                  </div>
                </div>
                
                <!-- Show info if annual leave balance is available -->
                <div v-if="annualLeaveBalance > 0" class="mt-3 p-2 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-700">
                  <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    <span class="text-sm text-blue-800 dark:text-blue-200">
                      Anda dapat menggunakan {{ annualLeaveBalance }} hari cuti tahunan.
                    </span>
                  </div>
                </div>
              </div>
              
              <!-- Regular Extra Off Balance - Show only if Extra Off is selected -->
              <div v-if="isExtraOffType" class="mt-4 p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg border border-purple-200 dark:border-purple-700">
                <div class="flex items-center justify-between">
                  <div>
                    <h4 class="text-sm font-medium text-purple-900 dark:text-purple-100">Saldo Extra Off</h4>
                    <p class="text-xs text-purple-700 dark:text-purple-300 mt-1">
                      Total hari extra off dari kerja tanpa shift
                    </p>
                  </div>
                  <div class="text-right">
                    <div v-if="loadingExtraOffBalance" class="flex items-center">
                      <i class="fas fa-spinner fa-spin text-purple-600 mr-2"></i>
                      <span class="text-sm text-purple-600">Loading...</span>
                    </div>
                    <div v-else>
                      <span class="text-2xl font-bold text-purple-900 dark:text-purple-100">
                        {{ regularExtraOffBalance }}
                      </span>
                      <span class="text-sm text-purple-700 dark:text-purple-300 ml-1">hari</span>
                    </div>
                  </div>
                </div>
                
                <!-- Show warning if no extra off balance available -->
                <div v-if="!loadingExtraOffBalance && regularExtraOffBalance === 0" class="mt-3 p-2 bg-yellow-50 dark:bg-yellow-900/20 rounded border border-yellow-200 dark:border-yellow-700">
                  <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                    <span class="text-sm text-yellow-800 dark:text-yellow-200">
                      Anda tidak memiliki saldo extra off yang tersedia.
                    </span>
                  </div>
                </div>
                
                <!-- Show info if extra off balance is available -->
                <div v-if="!loadingExtraOffBalance && regularExtraOffBalance > 0" class="mt-3 p-2 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-700">
                  <div class="flex items-center">
                    <i class="fas fa-info-circle text-blue-600 mr-2"></i>
                    <span class="text-sm text-blue-800 dark:text-blue-200">
                      Anda dapat menggunakan {{ regularExtraOffBalance }} hari extra off.
                    </span>
                  </div>
                </div>
              </div>
              
              <!-- Date Range -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Tanggal
                </label>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Dari</label>
                    <input 
                      type="date"
                      v-model="absentForm.date_from"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                      required
                    >
                  </div>
                  <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Sampai</label>
                    <input 
                      type="date"
                      v-model="absentForm.date_to"
                      :disabled="selectedLeaveType && selectedLeaveType.max_days > 0"
                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                      :class="{ 'bg-gray-100 dark:bg-gray-600 cursor-not-allowed': selectedLeaveType && selectedLeaveType.max_days > 0 }"
                      required
                    >
                    <p v-if="selectedLeaveType && selectedLeaveType.max_days > 0" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                      Otomatis berdasarkan durasi maksimal
                    </p>
                  </div>
                </div>
              </div>
              
              <!-- Days Count and Validation -->
              <div v-if="selectedLeaveType && (isPublicHolidayType || isAnnualLeaveType || isExtraOffType)">
                <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border">
                  <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Jumlah Hari yang Dipilih:</span>
                    <span class="text-lg font-bold" :class="isExceedingBalance ? 'text-red-600' : 'text-green-600'">
                      {{ selectedDaysCount }} hari
                    </span>
                  </div>
                  
                  <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Saldo Tersedia:</span>
                    <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                      {{ availableBalance }} hari
                    </span>
                  </div>
                  
                  <!-- Error message if exceeding balance -->
                  <div v-if="isExceedingBalance" class="mt-3 p-2 bg-red-50 dark:bg-red-900/20 rounded border border-red-200 dark:border-red-700">
                    <div class="flex items-center">
                      <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
                      <span class="text-sm text-red-800 dark:text-red-200">
                        {{ balanceErrorMessage }}
                      </span>
                    </div>
                  </div>
                  
                  <!-- Success message if within balance -->
                  <div v-else-if="selectedDaysCount > 0 && availableBalance > 0" class="mt-3 p-2 bg-green-50 dark:bg-green-900/20 rounded border border-green-200 dark:border-green-700">
                    <div class="flex items-center">
                      <i class="fas fa-check-circle text-green-600 mr-2"></i>
                      <span class="text-sm text-green-800 dark:text-green-200">
                        Jumlah hari yang dipilih masih dalam batas saldo yang tersedia.
                      </span>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Reason -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Alasan
                </label>
                <textarea 
                  v-model="absentForm.reason"
                  rows="3"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                  placeholder="Masukkan alasan izin/cuti..."
                  required
                ></textarea>
              </div>
              
              <!-- Approver Selection -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Pilih Atasan <span class="text-red-500">*</span>
                </label>
                
                <!-- Search Input -->
                <div class="relative">
                  <input
                    v-model="approverSearch"
                    type="text"
                    placeholder="Cari atasan berdasarkan nama, email, atau jabatan..."
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                    @focus="approverSearch.length >= 2 && loadApprovers(approverSearch)"
                    @input="onApproverSearchInput"
                  />
                  
                  <!-- Dropdown Results -->
                  <div v-if="showApproverDropdown && approverResults.length > 0" class="absolute z-10 w-full mt-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg max-h-60 overflow-y-auto">
                    <div
                      v-for="user in approverResults"
                      :key="user.id"
                      @click="addApprover(user)"
                      class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-600 cursor-pointer border-b border-gray-200 dark:border-gray-500 last:border-b-0"
                    >
                      <div class="font-medium text-gray-900 dark:text-white">{{ user.nama_lengkap }}</div>
                      <div class="text-sm text-gray-600 dark:text-gray-400">{{ user.email }}</div>
                      <div v-if="user.nama_jabatan" class="text-xs text-blue-600 dark:text-blue-400 font-medium">{{ user.nama_jabatan }}</div>
                      <div v-if="user.nama_divisi" class="text-xs text-gray-500 dark:text-gray-400">{{ user.nama_divisi }}</div>
                      <div v-if="user.nama_outlet" class="text-xs text-green-600 dark:text-green-400 font-medium">{{ user.nama_outlet }}</div>
                    </div>
                  </div>
                </div>
                
                <!-- Selected Approvers List -->
                <div v-if="selectedApprovers.length > 0" class="mt-3 space-y-2">
                  <div 
                    v-for="(approver, index) in selectedApprovers" 
                    :key="approver.id || index"
                    class="p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-md"
                  >
                    <div class="flex items-start justify-between">
                      <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                          <span class="text-xs font-semibold text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-800 px-2 py-0.5 rounded">
                            Level {{ index + 1 }}
                          </span>
                        </div>
                        <div class="font-medium text-blue-900 dark:text-blue-100">{{ approver.nama_lengkap }}</div>
                        <div class="text-sm text-blue-700 dark:text-blue-300">{{ approver.email }}</div>
                        <div v-if="approver.nama_jabatan" class="text-xs text-blue-600 dark:text-blue-400 font-medium">{{ approver.nama_jabatan }}</div>
                        <div v-if="approver.nama_divisi" class="text-xs text-blue-500 dark:text-blue-400">{{ approver.nama_divisi }}</div>
                        <div v-if="approver.nama_outlet" class="text-xs text-green-600 dark:text-green-400 font-medium">{{ approver.nama_outlet }}</div>
                      </div>
                      <button
                        @click="removeApprover(index)"
                        class="ml-2 p-1 text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 flex-shrink-0"
                        title="Hapus Atasan"
                      >
                        <i class="fa fa-times"></i>
                      </button>
                    </div>
                  </div>
                </div>
                
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  Ketik minimal 2 karakter untuk mencari atasan. Anda dapat menambahkan multiple approvers berjenjang (Level 1, 2, 3, dst). Setelah semua approver approve, baru akan muncul di approval HRD.
                </p>
              </div>
              
              <!-- Supporting Document - Camera Capture -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Dokumen Pendukung
                  <span v-if="selectedLeaveType && selectedLeaveType.requires_document" class="text-red-500">*</span>
                  <span v-else class="text-gray-500">(Opsional)</span>
                </label>
                
                <!-- Camera Capture Button -->
                <div v-if="capturedImages.length === 0" class="space-y-3">
                  <button 
                    type="button"
                    @click="openCameraModal"
                    class="w-full px-4 py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-blue-500 dark:hover:border-blue-400 transition-colors flex items-center justify-center space-x-2"
                  >
                    <i class="fa-solid fa-camera text-2xl text-gray-400"></i>
                    <span class="text-gray-600 dark:text-gray-300">Ambil Foto dengan Kamera</span>
                  </button>
                  <p class="text-xs text-gray-500 dark:text-gray-400 text-center">
                    Klik untuk membuka kamera dan ambil foto dokumen pendukung
                    <span v-if="selectedLeaveType && selectedLeaveType.requires_document" class="text-red-500 block">
                      - Dokumen wajib diambil
                    </span>
                  </p>
                </div>
                
                <!-- Captured Images Preview -->
                <div v-else class="space-y-3">
                  <!-- Photo Thumbnails Grid -->
                  <div class="grid grid-cols-2 gap-3">
                    <div 
                      v-for="image in capturedImages" 
                      :key="image.id"
                      class="relative group"
                    >
                      <img 
                        :src="image.preview" 
                        :alt="`Captured Document ${image.id}`"
                        class="w-full h-32 object-cover rounded-lg border border-gray-300 dark:border-gray-600"
                      >
                      <!-- Remove Button -->
                      <button 
                        type="button"
                        @click="removePhoto(image.id)"
                        class="absolute -top-2 -right-2 p-1 bg-red-600 text-white rounded-full hover:bg-red-700 transition-colors opacity-0 group-hover:opacity-100"
                        title="Hapus Foto"
                      >
                        <i class="fa-solid fa-times text-xs"></i>
                      </button>
                    </div>
                  </div>
                  
                  <!-- Add More Photos Button -->
                  <button 
                    type="button"
                    @click="openCameraModal"
                    class="w-full px-4 py-2 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg hover:border-blue-500 dark:hover:border-blue-400 transition-colors flex items-center justify-center space-x-2"
                  >
                    <i class="fa-solid fa-plus text-gray-400"></i>
                    <span class="text-gray-600 dark:text-gray-300">Tambah Foto Lainnya</span>
                  </button>
                  
                  <!-- Clear All Button -->
                  <button 
                    v-if="capturedImages.length > 0"
                    type="button"
                    @click="clearAllPhotos"
                    class="w-full px-4 py-2 text-red-600 border border-red-300 dark:border-red-600 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors flex items-center justify-center space-x-2"
                  >
                    <i class="fa-solid fa-trash text-sm"></i>
                    <span>Hapus Semua Foto</span>
                  </button>
                  
                  <!-- Status Message -->
                  <p class="text-xs text-green-600 dark:text-green-400 text-center">
                    <i class="fa-solid fa-check-circle mr-1"></i>
                    {{ capturedImages.length }} foto berhasil diambil dan siap digunakan
                  </p>
                </div>
              </div>
            </div>
          </form>
        </div>
        
        <div class="flex justify-end gap-3 p-4 border-t border-gray-200 dark:border-gray-700 flex-shrink-0">
          <button 
            type="button"
            @click="closeAbsentModal"
            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors"
          >
            Batal
          </button>
          <button 
            type="button"
            @click="submitAbsentRequest"
            :disabled="submittingAbsent || (isPublicHolidayType && totalExtraOffDays === 0) || (isAnnualLeaveType && annualLeaveBalance === 0) || (isExtraOffType && regularExtraOffBalance === 0) || isExceedingBalance || selectedApprovers.length === 0"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:bg-red-400 rounded-md transition-colors"
          >
            <i v-if="submittingAbsent" class="fa-solid fa-spinner fa-spin mr-2"></i>
            <i v-else class="fa-solid fa-paper-plane mr-2"></i>
            {{ submittingAbsent ? 'Mengirim...' : 'Kirim Permohonan' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Camera Modal -->
    <div v-if="showCameraModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full mx-4">
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Ambil Foto Dokumen
          </h3>
          <button 
            @click="closeCameraModal"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>
        
        <div class="p-4">
          <!-- Camera Error -->
          <div v-if="cameraError" class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-700">
            <div class="flex items-center">
              <i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>
              <span class="text-sm text-red-800 dark:text-red-200">{{ cameraError }}</span>
            </div>
          </div>
          
          <!-- Camera View -->
          <div v-else class="space-y-4">
            <div class="relative bg-black rounded-lg overflow-hidden">
              <video 
                ref="videoRef"
                autoplay
                playsinline
                muted
                class="w-full h-64 object-cover"
              ></video>
              
              <!-- Camera Controls Overlay -->
              <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex items-center space-x-4">
                <!-- Switch Camera Button -->
                <button 
                  v-if="availableCameras.length > 1"
                  @click="switchCamera"
                  class="p-3 bg-white bg-opacity-20 backdrop-blur-sm rounded-full hover:bg-opacity-30 transition-all"
                  title="Ganti Kamera"
                >
                  <i class="fa-solid fa-camera-rotate text-white text-xl"></i>
                </button>
                
                <!-- Capture Button -->
                <button 
                  @click="capturePhoto"
                  class="p-4 bg-white rounded-full hover:bg-gray-100 transition-all shadow-lg"
                  title="Ambil Foto"
                >
                  <i class="fa-solid fa-camera text-gray-800 text-2xl"></i>
                </button>
                
                <!-- Close Button -->
                <button 
                  @click="closeCameraModal"
                  class="p-3 bg-white bg-opacity-20 backdrop-blur-sm rounded-full hover:bg-opacity-30 transition-all"
                  title="Tutup"
                >
                  <i class="fa-solid fa-times text-white text-xl"></i>
                </button>
              </div>
              
              <!-- Camera Info -->
              <div class="absolute top-4 left-4 bg-black bg-opacity-50 text-white px-3 py-1 rounded-full text-sm">
                <i class="fa-solid fa-camera mr-1"></i>
                {{ currentCamera === 'user' ? 'Kamera Depan' : 'Kamera Belakang' }}
              </div>
            </div>
            
            <!-- Instructions -->
            <div class="text-center">
              <p class="text-sm text-gray-600 dark:text-gray-300">
                Posisikan dokumen dalam frame kamera, lalu klik tombol kamera untuk mengambil foto
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Cancel Leave Modal -->
    <div v-if="showCancelModalFlag" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Batalkan Permohonan Izin/Cuti
          </h3>
          <button
            @click="closeCancelModal"
            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <i class="fa-solid fa-times text-xl"></i>
          </button>
        </div>
        
        <div class="p-4">
          <div class="mb-4">
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-3 mb-4">
              <div class="flex items-center">
                <i class="fa-solid fa-exclamation-triangle text-yellow-600 dark:text-yellow-400 mr-2"></i>
                <span class="text-sm text-yellow-800 dark:text-yellow-200 font-medium">
                  Apakah Anda yakin ingin membatalkan permohonan ini?
                </span>
              </div>
            </div>
            
            <div v-if="selectedCancelRequest" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3 mb-4">
              <div class="text-sm text-gray-900 dark:text-gray-100">
                <strong>Jenis:</strong> {{ selectedCancelRequest.leave_type_name }}
              </div>
              <div class="text-sm text-gray-900 dark:text-gray-100">
                <strong>Periode:</strong> {{ formatDateRange(selectedCancelRequest.date_from, selectedCancelRequest.date_to) }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400">
                <strong>Alasan:</strong> {{ selectedCancelRequest.reason }}
              </div>
            </div>
          </div>
          
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Alasan Pembatalan (Opsional)
            </label>
            <textarea
              v-model="cancelReason"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
              placeholder="Jelaskan alasan pembatalan (opsional)..."
            ></textarea>
          </div>
          
          <div class="flex justify-end gap-3">
            <button
              @click="closeCancelModal"
              class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors"
            >
              Batal
            </button>
            <button
              @click="confirmCancelRequest"
              :disabled="cancellingRequest"
              class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:bg-red-400 rounded-md transition-colors"
            >
              <i v-if="cancellingRequest" class="fa-solid fa-spinner fa-spin mr-2"></i>
              <i v-else class="fa-solid fa-times mr-2"></i>
              {{ cancellingRequest ? 'Membatalkan...' : 'Ya, Batalkan' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Hidden Canvas for Photo Capture -->
    <canvas ref="canvasRef" style="display: none;"></canvas>

    <!-- Correction Requests Section - Moved to bottom -->
    <div v-if="correctionRequests && correctionRequests.length > 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4">
      <div class="p-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-3">
          <i class="fas fa-edit text-blue-600 mr-2"></i>
          Status Pengajuan Koreksi
        </h3>
        <div class="space-y-3">
          <div v-for="request in correctionRequests" :key="request.id" 
               class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-2">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="{
                          'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': request.status === 'pending',
                          'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': request.status === 'approved',
                          'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': request.status === 'rejected'
                        }">
                    <i class="fas fa-clock mr-1" v-if="request.status === 'pending'"></i>
                    <i class="fas fa-check mr-1" v-if="request.status === 'approved'"></i>
                    <i class="fas fa-times mr-1" v-if="request.status === 'rejected'"></i>
                    {{ getStatusText(request.status) }}
                  </span>
                  <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ formatDate(request.tanggal) }}
                  </span>
                </div>
                
                <div class="mb-2">
                  <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                    {{ getTypeText(request.type) }} - {{ request.nama_outlet }}
                  </p>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                    <i class="fas fa-user mr-1"></i>
                    Diajukan oleh: <span class="font-medium">{{ request.requested_by_name }}</span>
                  </p>
                      <div class="text-sm text-gray-600 dark:text-gray-400">
                        <div class="mb-2">
                          <span class="font-medium text-red-600 dark:text-red-400">Sebelum:</span>
                          <div class="ml-4 mt-1 p-2 bg-red-50 dark:bg-red-900/20 rounded border-l-2 border-red-300">
                            {{ formatCorrectionValue(request.old_value, request.type) }}
                          </div>
                        </div>
                        <div>
                          <span class="font-medium text-green-600 dark:text-green-400">Sesudah:</span>
                          <div class="ml-4 mt-1 p-2 bg-green-50 dark:bg-green-900/20 rounded border-l-2 border-green-300">
                            {{ formatCorrectionValue(request.new_value, request.type) }}
                          </div>
                        </div>
                      </div>
                </div>
                
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                  <strong>Alasan:</strong> {{ request.reason }}
                </p>
                
                <div v-if="request.status === 'approved' && request.approved_by_name" class="text-sm text-green-600 dark:text-green-400">
                  <i class="fas fa-user-check mr-1"></i>
                  Disetujui oleh: {{ request.approved_by_name }}
                  <span class="ml-2">{{ formatDateTime(request.approved_at) }}</span>
                </div>
                
                <div v-if="request.status === 'rejected' && request.rejection_reason" class="text-sm text-red-600 dark:text-red-400">
                  <i class="fas fa-exclamation-triangle mr-1"></i>
                  Alasan penolakan: {{ request.rejection_reason }}
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
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  workSchedules: Array,
  attendanceRecords: Array,
  attendanceSummary: Object,
  calendar: Object,
  holidays: Array,
  approvedAbsents: Array,
  userLeaveRequests: Array,
  leaveTypes: Array,
  availableApprovers: Array,
  phData: Object,
  extraOffData: Object,
  correctionRequests: Array,
  filters: Object,
  user: Object
})

const filters = ref({
  bulan: props.filters.bulan,
  tahun: props.filters.tahun
})

// Modal data
const showDetailModal = ref(false)
const selectedDate = ref('')
const attendanceDetail = ref([])
const loadingDetail = ref(false)

const calendarData = ref({})
const loading = ref(false)

// Reactive data for user leave requests
const userLeaveRequests = ref(props.userLeaveRequests || [])

// Extra off days data
const extraOffDays = ref([])
const loadingExtraOff = ref(false)

// Regular extra off balance data
const extraOffBalance = ref(0)
const loadingExtraOffBalance = ref(false)

// Year options for dropdown
const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear()
  const years = []
  for (let i = currentYear - 2; i <= currentYear + 1; i++) {
    years.push(i)
  }
  return years
})

// Computed properties
const calendarDays = computed(() => {
  const startDate = new Date(props.filters.start_date)
  const endDate = new Date(props.filters.end_date)
  const days = []
  
  // Start from Sunday of the week containing startDate
  const startCalendar = new Date(startDate)
  startCalendar.setDate(startDate.getDate() - startDate.getDay())
  
  // Calculate total days needed to cover the payroll period
  const totalDays = Math.ceil((endDate - startCalendar) / (1000 * 60 * 60 * 24)) + 1
  const weeksNeeded = Math.ceil(totalDays / 7)
  const totalCalendarDays = weeksNeeded * 7
  
  // Generate calendar days
  for (let i = 0; i < totalCalendarDays; i++) {
    const currentDate = new Date(startCalendar)
    currentDate.setDate(startCalendar.getDate() + i)
    
    const dateStr = currentDate.toISOString().split('T')[0]
    const isInPayrollPeriod = currentDate >= startDate && currentDate <= endDate
    const isToday = dateStr === new Date().toISOString().split('T')[0]
    
    // Get shifts for this date from calendar data
    const shifts = props.calendar[dateStr] || {}
    const schedules = Object.values(shifts)
    
    // Check if this date is a holiday
    const holiday = props.holidays.find(h => h.date === dateStr)
    
    days.push({
      date: dateStr,
      day: currentDate.getDate(),
      isInPayrollPeriod,
      isToday,
      schedules: schedules,
      holiday: holiday
    })
  }
  
  return days
})

// Computed property untuk mendapatkan approved absent berdasarkan tanggal
const getApprovedAbsentForDate = computed(() => {
  return (dateString) => {
    if (!props.approvedAbsents) return null
    
    const date = new Date(dateString).toISOString().split('T')[0]
    
    return props.approvedAbsents.find(absent => {
      const fromDate = new Date(absent.date_from).toISOString().split('T')[0]
      const toDate = new Date(absent.date_to).toISOString().split('T')[0]
      
      return date >= fromDate && date <= toDate
    })
  }
})

// Methods
const fetchData = () => {
  loading.value = true
  router.get(route('attendance.index'), filters.value, {
    preserveState: true,
    replace: true,
    onFinish: () => {
      loading.value = false
    }
  })
}

// Calendar data is now provided via props, no need for separate API call

const resetFilters = () => {
  const now = new Date()
  filters.value.bulan = now.getMonth() + 1
  filters.value.tahun = now.getFullYear()
  fetchData()
}

// Modal methods
const showAttendanceDetail = async (date) => {
  selectedDate.value = date
  showDetailModal.value = true
  loadingDetail.value = true
  attendanceDetail.value = []
  
  try {
    const response = await axios.get('/attendance-report/detail', {
      params: {
        user_id: props.user.id,
        tanggal: date
      }
    })
    
    attendanceDetail.value = response.data
  } catch (error) {
    console.error('Error fetching attendance detail:', error)
    attendanceDetail.value = []
  } finally {
    loadingDetail.value = false
  }
}

const closeDetailModal = () => {
  showDetailModal.value = false
  selectedDate.value = ''
  attendanceDetail.value = []
}

// Absent modal functions
const showAbsentModalFlag = ref(false)
const selectedAbsentDate = ref('')
const submittingAbsent = ref(false)
const absentForm = ref({
  leave_type_id: '',
  reason: '',
  approver_id: '', // Keep for backward compatibility
  document: null,
  date_from: '',
  date_to: ''
})

// Approver search functionality
const approverSearch = ref('')
const approverResults = ref([])
const showApproverDropdown = ref(false)
const selectedApprovers = ref([]) // Changed to array for multiple approvers

// Cancel leave functionality
const showCancelModalFlag = ref(false)
const selectedCancelRequest = ref(null)
const cancelReason = ref('')
const cancellingRequest = ref(false)

// Camera capture states
const showCameraModal = ref(false)
const cameraStream = ref(null)
const videoRef = ref(null)
const canvasRef = ref(null)
const capturedImages = ref([]) // Array untuk multiple foto
const currentCamera = ref('user') // 'user' for front camera, 'environment' for back camera
const availableCameras = ref([])
const cameraError = ref('')

// Computed property untuk mendapatkan leave type yang dipilih
const selectedLeaveType = computed(() => {
  if (!absentForm.value.leave_type_id || !props.leaveTypes) return null
  return props.leaveTypes.find(lt => lt.id == absentForm.value.leave_type_id)
})

// Computed property untuk mengecek apakah leave type adalah public holiday
const isPublicHolidayType = computed(() => {
  return selectedLeaveType.value && selectedLeaveType.value.name && 
         (selectedLeaveType.value.name.toLowerCase().includes('public holiday') ||
          selectedLeaveType.value.name.toLowerCase().includes('libur nasional') ||
          selectedLeaveType.value.name.toLowerCase().includes('hari libur'))
})

// Computed property untuk mengecek apakah leave type adalah annual leave
const isAnnualLeaveType = computed(() => {
  return selectedLeaveType.value && selectedLeaveType.value.name && 
         (selectedLeaveType.value.name.toLowerCase().includes('annual leave') ||
          selectedLeaveType.value.name.toLowerCase().includes('cuti tahunan') ||
          selectedLeaveType.value.name.toLowerCase().includes('cuti'))
})

// Computed property untuk mengecek apakah leave type adalah extra off
const isExtraOffType = computed(() => {
  return selectedLeaveType.value && selectedLeaveType.value.name && 
         selectedLeaveType.value.name.toLowerCase().includes('extra off')
})

// Computed property untuk menghitung total saldo extra off
const totalExtraOffDays = computed(() => {
  if (!extraOffDays.value || extraOffDays.value.length === 0) return 0
  
  // Calculate total days based on available_amount
  return extraOffDays.value.reduce((total, day) => {
    // Use available_amount if available, otherwise fallback to compensation_amount
    const availableAmount = day.available_amount !== undefined ? day.available_amount : day.compensation_amount
    return total + (parseFloat(availableAmount) || 0)
  }, 0)
})

// Computed property untuk mendapatkan saldo cuti tahunan dari user
const annualLeaveBalance = computed(() => {
  return props.user?.cuti || 0
})

// Computed property untuk mendapatkan saldo extra off biasa
const regularExtraOffBalance = computed(() => {
  return extraOffBalance.value
})

// Computed property untuk menghitung tanggal to berdasarkan max_days
const calculatedDateTo = computed(() => {
  if (!selectedLeaveType.value || !absentForm.value.date_from) return absentForm.value.date_from
  
  const dateFrom = new Date(absentForm.value.date_from)
  const maxDays = selectedLeaveType.value.max_days
  
  if (maxDays === 0) {
    // Jika max_days = 0, return date_from (user bisa pilih manual)
    return absentForm.value.date_from
  } else {
    // Jika max_days > 0, hitung tanggal to = date_from + (max_days - 1)
    // karena tanggal yang dipilih sudah dihitung sebagai hari pertama
    const dateTo = new Date(dateFrom)
    dateTo.setDate(dateTo.getDate() + (maxDays - 1))
    return dateTo.toISOString().split('T')[0]
  }
})

// Computed property untuk menghitung jumlah hari yang dipilih
const selectedDaysCount = computed(() => {
  if (!absentForm.value.date_from || !absentForm.value.date_to) return 0
  
  const dateFrom = new Date(absentForm.value.date_from)
  const dateTo = new Date(absentForm.value.date_to)
  
  if (dateTo < dateFrom) return 0
  
  const timeDiff = dateTo.getTime() - dateFrom.getTime()
  const daysDiff = Math.ceil(timeDiff / (1000 * 3600 * 24)) + 1
  
  return daysDiff
})

// Computed property untuk mendapatkan saldo yang tersedia berdasarkan jenis cuti
const availableBalance = computed(() => {
  if (isPublicHolidayType.value) {
    return totalExtraOffDays.value
  } else if (isAnnualLeaveType.value) {
    return annualLeaveBalance.value
  } else if (isExtraOffType.value) {
    return regularExtraOffBalance.value
  }
  return 0
})

// Computed property untuk mengecek apakah jumlah hari melebihi saldo
const isExceedingBalance = computed(() => {
  if (!selectedLeaveType.value) return false
  
  // Hanya validasi untuk jenis cuti yang memiliki saldo
  if (isPublicHolidayType.value || isAnnualLeaveType.value || isExtraOffType.value) {
    return selectedDaysCount.value > availableBalance.value
  }
  
  return false
})

// Computed property untuk mendapatkan pesan error
const balanceErrorMessage = computed(() => {
  if (!isExceedingBalance.value) return ''
  
  const leaveTypeName = selectedLeaveType.value?.name || 'jenis cuti ini'
  const available = availableBalance.value
  const selected = selectedDaysCount.value
  
  return `Jumlah hari yang dipilih (${selected} hari) melebihi saldo yang tersedia (${available} hari) untuk ${leaveTypeName}.`
})

// Watch untuk update date_to ketika leave_type_id atau date_from berubah
watch([() => absentForm.value.leave_type_id, () => absentForm.value.date_from], () => {
  if (selectedLeaveType.value && selectedLeaveType.value.max_days > 0) {
    absentForm.value.date_to = calculatedDateTo.value
  }
})

const hasAttendanceData = (day) => {
  if (!day.schedules || day.schedules.length === 0) return false
  
  return day.schedules.some(schedule => 
    schedule.has_attendance && schedule.first_in
  )
}

// Function to check if a date is yesterday (backdate)
const isYesterday = (dateString) => {
  if (!dateString) return false
  
  try {
    const today = new Date()
    const date = new Date(dateString)
    
    // Check if date is valid
    if (isNaN(date.getTime())) return false
    
    // Set time to start of day for accurate comparison
    today.setHours(0, 0, 0, 0)
    date.setHours(0, 0, 0, 0)
    
    // Check if date is yesterday
    const yesterday = new Date(today)
    yesterday.setDate(yesterday.getDate() - 1)
    
    return date.getTime() === yesterday.getTime()
  } catch (error) {
    console.error('Error checking yesterday:', error)
    return false
  }
}

// Function to check if backdate is allowed (no attendance in the previous day)
const isBackdateAllowed = (day) => {
  if (!isYesterday(day.date)) return true // Not yesterday, so backdate rules don't apply
  
  // If it's yesterday, check if there's no attendance data
  return !hasAttendanceData(day)
}

const isPastDate = (dateString) => {
  if (!dateString) return false
  
  try {
    const today = new Date()
    const date = new Date(dateString)
    
    // Check if date is valid
    if (isNaN(date.getTime())) return false
    
    // Set time to start of day for accurate comparison
    today.setHours(0, 0, 0, 0)
    date.setHours(0, 0, 0, 0)
    
    // Allow backdate 1 day (yesterday)
    const yesterday = new Date(today)
    yesterday.setDate(yesterday.getDate() - 1)
    
    // Return true if date is before yesterday (more than 1 day ago)
    return date < yesterday
  } catch (error) {
    console.error('Error checking past date:', error)
    return false
  }
}

const showAbsentModal = (date) => {
  selectedAbsentDate.value = date
  showAbsentModalFlag.value = true
  // Reset form and captured images
  absentForm.value = {
    leave_type_id: '',
    reason: '',
    approver_id: '',
    document: null,
    date_from: date,
    date_to: date
  }
  capturedImages.value = []
  selectedApprovers.value = [] // Reset approvers list
  approverSearch.value = '' // Reset search
  showApproverDropdown.value = false // Close dropdown
  // Load extra off days and balance
  loadExtraOffDays()
  loadExtraOffBalance()
}

// Function to load extra off days
const loadExtraOffDays = async () => {
  loadingExtraOff.value = true
  try {
    const response = await axios.get('/api/holiday-attendance/my-extra-off-days')
    if (response.data.success) {
      extraOffDays.value = response.data.extra_off_days
    }
  } catch (error) {
    console.error('Error loading extra off days:', error)
  } finally {
    loadingExtraOff.value = false
  }
}

// Function to load regular extra off balance
const loadExtraOffBalance = async () => {
  loadingExtraOffBalance.value = true
  try {
    const response = await axios.get('/api/extra-off/balance')
    if (response.data.success) {
      extraOffBalance.value = response.data.balance
    }
  } catch (error) {
    console.error('Error loading extra off balance:', error)
  } finally {
    loadingExtraOffBalance.value = false
  }
}

const closeAbsentModal = () => {
  showAbsentModalFlag.value = false
  selectedAbsentDate.value = ''
  submittingAbsent.value = false
  // Reset camera states
  showCameraModal.value = false
  capturedImages.value = []
  absentForm.value.document = null
  stopCamera()
  // Reset approver search and approvers list
  selectedApprovers.value = []
  approverSearch.value = ''
  approverResults.value = []
  showApproverDropdown.value = false
  absentForm.value.approver_id = ''
}

// Camera functions
const openCameraModal = async () => {
  showCameraModal.value = true
  cameraError.value = ''
  await nextTick()
  await initializeCamera()
}

const closeCameraModal = () => {
  showCameraModal.value = false
  stopCamera()
}

const initializeCamera = async () => {
  try {
    // Get available cameras
    const devices = await navigator.mediaDevices.enumerateDevices()
    availableCameras.value = devices.filter(device => device.kind === 'videoinput')
    
    if (availableCameras.value.length === 0) {
      cameraError.value = 'Tidak ada kamera yang tersedia'
      return
    }
    
    // Start camera stream
    const constraints = {
      video: {
        facingMode: currentCamera.value,
        width: { ideal: 1280 },
        height: { ideal: 720 }
      }
    }
    
    cameraStream.value = await navigator.mediaDevices.getUserMedia(constraints)
    
    if (videoRef.value) {
      videoRef.value.srcObject = cameraStream.value
    }
  } catch (error) {
    console.error('Camera error:', error)
    if (error.name === 'NotAllowedError') {
      cameraError.value = 'Akses kamera ditolak. Silakan berikan izin kamera di browser.'
    } else if (error.name === 'NotFoundError') {
      cameraError.value = 'Tidak ada kamera yang ditemukan di perangkat ini.'
    } else if (error.name === 'NotSupportedError') {
      cameraError.value = 'Browser tidak mendukung akses kamera.'
    } else {
      cameraError.value = 'Gagal mengakses kamera. Pastikan izin kamera sudah diberikan.'
    }
  }
}

const stopCamera = () => {
  if (cameraStream.value) {
    cameraStream.value.getTracks().forEach(track => track.stop())
    cameraStream.value = null
  }
}

const switchCamera = async () => {
  if (availableCameras.value.length <= 1) {
    return // Don't switch if only one camera available
  }
  
  currentCamera.value = currentCamera.value === 'user' ? 'environment' : 'user'
  stopCamera()
  await nextTick()
  await initializeCamera()
}

const capturePhoto = () => {
  if (!videoRef.value || !canvasRef.value) return
  
  const video = videoRef.value
  const canvas = canvasRef.value
  const context = canvas.getContext('2d')
  
  // Set canvas size to match video
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  
  // Draw video frame to canvas
  context.drawImage(video, 0, 0, canvas.width, canvas.height)
  
  // Convert canvas to blob
  canvas.toBlob((blob) => {
    if (blob) {
      // Create file from blob with unique name
      const timestamp = new Date().getTime()
      const file = new File([blob], `camera-capture-${timestamp}.jpg`, { type: 'image/jpeg' })
      
      // Add to captured images array
      capturedImages.value.push({
        id: timestamp,
        file: file,
        preview: URL.createObjectURL(blob)
      })
      
      // Close camera modal immediately
      closeCameraModal()
      
      // Show success message
      Swal.fire({
        icon: 'success',
        title: 'Foto Berhasil Diambil',
        text: 'Foto telah berhasil diambil dan ditambahkan ke dokumen pendukung.',
        confirmButtonText: 'OK',
        confirmButtonColor: '#10B981',
        timer: 2000,
        showConfirmButton: false
      })
    }
  }, 'image/jpeg', 0.8)
}

const removePhoto = (photoId) => {
  const index = capturedImages.value.findIndex(img => img.id === photoId)
  if (index > -1) {
    // Revoke object URL to free memory
    URL.revokeObjectURL(capturedImages.value[index].preview)
    capturedImages.value.splice(index, 1)
  }
}

const clearAllPhotos = () => {
  // Revoke all object URLs to free memory
  capturedImages.value.forEach(img => {
    URL.revokeObjectURL(img.preview)
  })
  capturedImages.value = []
}

const submitAbsentRequest = async () => {
  // Validate public holiday extra off days availability
  if (isPublicHolidayType.value && totalExtraOffDays.value === 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Saldo Tidak Tersedia',
      text: 'Anda tidak memiliki saldo extra off dari kerja di hari libur nasional yang tersedia untuk mengajukan izin ini.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#3B82F6'
    })
    return
  }
  
  // Validate annual leave balance
  if (isAnnualLeaveType.value && annualLeaveBalance.value === 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Saldo Tidak Tersedia',
      text: 'Anda tidak memiliki saldo cuti tahunan yang tersedia untuk mengajukan izin ini.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#3B82F6'
    })
    return
  }
  
  // Validate regular extra off balance
  if (isExtraOffType.value && regularExtraOffBalance.value === 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Saldo Tidak Tersedia',
      text: 'Anda tidak memiliki saldo extra off yang tersedia untuk mengajukan izin ini.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#3B82F6'
    })
    return
  }
  
  // Validate if selected days exceed available balance
  if (isExceedingBalance.value) {
    await Swal.fire({
      icon: 'error',
      title: 'Jumlah Hari Melebihi Saldo',
      text: balanceErrorMessage.value,
      confirmButtonText: 'OK',
      confirmButtonColor: '#EF4444'
    })
    return
  }
  
  submittingAbsent.value = true
  
  // Validate approvers
  if (selectedApprovers.value.length === 0) {
    await Swal.fire({
      icon: 'warning',
      title: 'Atasan Diperlukan',
      text: 'Silakan pilih minimal satu atasan sebelum mengirim permohonan',
      confirmButtonText: 'OK',
      confirmButtonColor: '#3B82F6'
    })
    submittingAbsent.value = false
    return
  }
  
  try {
    const formData = new FormData()
    formData.append('leave_type_id', absentForm.value.leave_type_id)
    formData.append('date_from', absentForm.value.date_from)
    formData.append('date_to', absentForm.value.date_to)
    formData.append('reason', absentForm.value.reason)
    
    // Send approvers as array (for multi-level approval)
    if (selectedApprovers.value.length > 0) {
      selectedApprovers.value.forEach(approver => {
        formData.append('approvers[]', approver.id)
      })
    }
    
    // Backward compatibility: also send approver_id if only one approver
    if (selectedApprovers.value.length === 1) {
      formData.append('approver_id', selectedApprovers.value[0].id)
    }
    
    // Add captured images to FormData
    if (capturedImages.value.length > 0) {
      capturedImages.value.forEach((img, index) => {
        formData.append('documents[]', img.file)
      })
    }
    
    const response = await axios.post('/api/attendance/absent-request', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    if (response.data.success) {
      await Swal.fire({
        icon: 'success',
        title: 'Berhasil!',
        text: 'Permohonan izin/cuti berhasil dikirim!',
        confirmButtonText: 'OK',
        confirmButtonColor: '#10B981'
      })
      closeAbsentModal()
      // Optionally refresh the calendar data
      // loadCalendarData()
    } else {
      await Swal.fire({
        icon: 'error',
        title: 'Gagal Mengirim',
        text: 'Gagal mengirim permohonan: ' + (response.data.message || 'Terjadi kesalahan'),
        confirmButtonText: 'OK',
        confirmButtonColor: '#EF4444'
      })
    }
  } catch (error) {
    console.error('Error submitting absent request:', error)
    
    // Handle different types of errors with detailed information
    if (error.response && error.response.data) {
      const data = error.response.data
      const status = error.response.status
      
      // Handle validation errors (422)
      if (status === 422 && data.errors) {
        const errors = data.errors
        let errorHtml = '<div class="text-left">'
        errorHtml += '<p class="font-semibold text-red-600 mb-3">Data tidak valid. Periksa field berikut:</p>'
        errorHtml += '<ul class="list-disc list-inside text-sm space-y-1">'
        
        Object.keys(errors).forEach(field => {
          const fieldName = getFieldDisplayName(field)
          if (Array.isArray(errors[field])) {
            errors[field].forEach(error => {
              errorHtml += `<li class="text-red-600"><strong>${fieldName}:</strong> ${error}</li>`
            })
          } else {
            errorHtml += `<li class="text-red-600"><strong>${fieldName}:</strong> ${errors[field]}</li>`
          }
        })
        
        errorHtml += '</ul>'
        errorHtml += '<p class="text-xs text-gray-500 mt-3">Silakan perbaiki field yang bermasalah dan coba lagi.</p>'
        errorHtml += '</div>'
        
        await Swal.fire({
          icon: 'error',
          title: 'Data Tidak Valid',
          html: errorHtml,
          confirmButtonText: 'OK',
          confirmButtonColor: '#EF4444',
          width: '500px'
        })
      }
      // Handle specific error messages (400, 500, etc.)
      else if (data.message) {
        let errorHtml = '<div class="text-left">'
        errorHtml += '<p class="font-semibold text-red-600 mb-3">Gagal mengirim permohonan izin/cuti:</p>'
        errorHtml += '<div class="bg-red-50 border border-red-200 rounded-md p-3 mb-3">'
        errorHtml += `<p class="text-red-800">${data.message}</p>`
        errorHtml += '</div>'
        
        // Add additional context for common errors
        if (data.message.includes('sudah mengajukan izin/cuti')) {
          errorHtml += '<div class="bg-yellow-50 border border-yellow-200 rounded-md p-3 mb-3">'
          errorHtml += '<p class="text-yellow-800 text-sm"><strong>Solusi:</strong></p>'
          errorHtml += '<ul class="list-disc list-inside text-yellow-700 text-sm mt-2">'
          errorHtml += '<li>Periksa apakah Anda sudah mengajukan izin/cuti untuk tanggal yang sama</li>'
          errorHtml += '<li>Pilih rentang tanggal yang berbeda</li>'
          errorHtml += '<li>Hubungi HR jika Anda yakin belum pernah mengajukan izin/cuti</li>'
          errorHtml += '</ul>'
          errorHtml += '</div>'
        } else if (data.message.includes('data kehadiran')) {
          errorHtml += '<div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-3">'
          errorHtml += '<p class="text-blue-800 text-sm"><strong>Informasi:</strong></p>'
          errorHtml += '<p class="text-blue-700 text-sm mt-2">Anda sudah memiliki data kehadiran untuk salah satu tanggal dalam rentang ini. Tidak dapat mengajukan izin/cuti untuk tanggal yang sudah ada kehadiran.</p>'
          errorHtml += '</div>'
        }
        
        errorHtml += '<p class="text-xs text-gray-500 mt-3">Jika masalah berlanjut, silakan hubungi administrator.</p>'
        errorHtml += '</div>'
        
        await Swal.fire({
          icon: 'error',
          title: 'Gagal Mengirim Permohonan',
          html: errorHtml,
          confirmButtonText: 'OK',
          confirmButtonColor: '#EF4444',
          width: '600px'
        })
      }
      // Handle other error responses
      else {
        await Swal.fire({
          icon: 'error',
          title: 'Terjadi Kesalahan',
          html: `
            <div class="text-left">
              <p class="font-semibold text-red-600 mb-3">Terjadi kesalahan saat mengirim permohonan:</p>
              <div class="bg-red-50 border border-red-200 rounded-md p-3 mb-3">
                <p class="text-red-800">Status: ${status}</p>
                <p class="text-red-800">Response: ${JSON.stringify(data, null, 2)}</p>
              </div>
              <p class="text-xs text-gray-500 mt-3">Silakan coba lagi atau hubungi administrator jika masalah berlanjut.</p>
            </div>
          `,
          confirmButtonText: 'OK',
          confirmButtonColor: '#EF4444',
          width: '600px'
        })
      }
    }
    // Handle network errors
    else if (error.request) {
      await Swal.fire({
        icon: 'error',
        title: 'Koneksi Bermasalah',
        html: `
          <div class="text-left">
            <p class="font-semibold text-red-600 mb-3">Tidak dapat terhubung ke server:</p>
            <div class="bg-red-50 border border-red-200 rounded-md p-3 mb-3">
              <p class="text-red-800">Periksa koneksi internet Anda dan coba lagi.</p>
            </div>
            <p class="text-xs text-gray-500 mt-3">Jika masalah berlanjut, silakan hubungi administrator.</p>
          </div>
        `,
        confirmButtonText: 'OK',
        confirmButtonColor: '#EF4444',
        width: '500px'
      })
    }
    // Handle other errors
    else {
      await Swal.fire({
        icon: 'error',
        title: 'Terjadi Kesalahan',
        html: `
          <div class="text-left">
            <p class="font-semibold text-red-600 mb-3">Terjadi kesalahan tidak terduga:</p>
            <div class="bg-red-50 border border-red-200 rounded-md p-3 mb-3">
              <p class="text-red-800">${error.message || 'Unknown error'}</p>
            </div>
            <p class="text-xs text-gray-500 mt-3">Silakan coba lagi atau hubungi administrator jika masalah berlanjut.</p>
          </div>
        `,
        confirmButtonText: 'OK',
        confirmButtonColor: '#EF4444',
        width: '500px'
      })
    }
  } finally {
    submittingAbsent.value = false
  }
}

// Approver search methods
const loadApprovers = async (search = '') => {
  try {
    const response = await axios.get('/api/attendance/approvers', {
      params: { search },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    })
    
    if (response.data.success) {
      approverResults.value = response.data.users
      showApproverDropdown.value = true
    }
  } catch (error) {
    console.error('Failed to load approvers:', error)
    approverResults.value = []
  }
}

const addApprover = (user) => {
  // Check if approver already exists
  if (!selectedApprovers.value.find(approver => approver.id === user.id)) {
    selectedApprovers.value.push(user)
  }
  approverSearch.value = ''
  showApproverDropdown.value = false
}

const removeApprover = (index) => {
  selectedApprovers.value.splice(index, 1)
}

const onApproverSearchInput = () => {
  if (approverSearch.value.length >= 2) {
    loadApprovers(approverSearch.value)
  } else {
    showApproverDropdown.value = false
    approverResults.value = []
  }
}

// Cancel leave methods
const canCancelRequest = (request) => {
  // Can cancel if status is pending, supervisor_approved, or approved (HRD approved)
  if (!['pending', 'supervisor_approved', 'approved'].includes(request.status)) {
    return false
  }
  
  // Check if date_from has not passed (can cancel until the day of the leave)
  const startDate = new Date(request.date_from)
  startDate.setHours(0, 0, 0, 0) // Set to start of day
  
  const today = new Date()
  today.setHours(0, 0, 0, 0) // Set to start of day
  
  // Can cancel if start date is today or in the future
  if (startDate < today) {
    return false
  }
  
  return true
}

const showCancelModal = (request) => {
  selectedCancelRequest.value = request
  cancelReason.value = ''
  showCancelModalFlag.value = true
}

const closeCancelModal = () => {
  showCancelModalFlag.value = false
  selectedCancelRequest.value = null
  cancelReason.value = ''
  cancellingRequest.value = false
}

const confirmCancelRequest = async () => {
  if (!selectedCancelRequest.value) return
  
  cancellingRequest.value = true
  
  try {
    const response = await axios.post(`/api/attendance/cancel-leave/${selectedCancelRequest.value.id}`, {
      reason: cancelReason.value || 'Dibatalkan oleh user'
    })
    
    if (response.data.success) {
      try {
        // Remove the cancelled request from the list first
        const index = userLeaveRequests.value.findIndex(req => req.id === selectedCancelRequest.value.id)
        if (index > -1) {
          userLeaveRequests.value.splice(index, 1)
        }
        
        closeCancelModal()
        
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil!',
          text: 'Permohonan izin/cuti berhasil dibatalkan',
          confirmButtonText: 'OK',
          confirmButtonColor: '#10B981'
        })
      } catch (successError) {
        console.error('Error in success handling:', successError)
        // Still show success message even if there's a minor error
        await Swal.fire({
          icon: 'success',
          title: 'Berhasil!',
          text: 'Permohonan izin/cuti berhasil dibatalkan',
          confirmButtonText: 'OK',
          confirmButtonColor: '#10B981'
        })
      }
    } else {
      await Swal.fire({
        icon: 'error',
        title: 'Gagal Membatalkan',
        text: response.data.message || 'Terjadi kesalahan saat membatalkan permohonan',
        confirmButtonText: 'OK',
        confirmButtonColor: '#EF4444'
      })
    }
  } catch (error) {
    console.error('Error cancelling leave request:', error)
    
    let errorMessage = 'Terjadi kesalahan saat membatalkan permohonan'
    if (error.response && error.response.data && error.response.data.message) {
      errorMessage = error.response.data.message
    }
    
    await Swal.fire({
      icon: 'error',
      title: 'Gagal Membatalkan',
      text: errorMessage,
      confirmButtonText: 'OK',
      confirmButtonColor: '#EF4444'
    })
  } finally {
    cancellingRequest.value = false
  }
}

// Helper function to get display name for form fields
const getFieldDisplayName = (field) => {
  const fieldNames = {
    'leave_type_id': 'Jenis Izin/Cuti',
    'date_from': 'Tanggal Mulai',
    'date_to': 'Tanggal Selesai',
    'reason': 'Alasan',
    'approver_id': 'Atasan',
    'document': 'Dokumen',
    'documents': 'Dokumen'
  }
  return fieldNames[field] || field
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatDateRange = (dateFrom, dateTo) => {
  if (!dateFrom || !dateTo) return ''
  
  const fromDate = new Date(dateFrom)
  const toDate = new Date(dateTo)
  
  if (dateFrom === dateTo) {
    return fromDate.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    })
  }
  
  return `${fromDate.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })} - ${toDate.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric'
  })}`
}

const formatDateTime = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getStatusText = (status) => {
  // Leave request status
  const leaveStatusMap = {
    'pending': 'Menunggu Persetujuan',
    'supervisor_approved': 'Disetujui Atasan',
    'approved': 'Disetujui',
    'rejected': 'Ditolak',
    'cancelled': 'Dibatalkan'
  }
  
  // Attendance status
  const attendanceStatusMap = {
    'present': 'Hadir',
    'late': 'Terlambat',
    'absent': 'Tidak Hadir',
    'half_day': 'Setengah Hari'
  }
  
  // Correction request status
  const correctionStatusMap = {
    'pending': 'Menunggu Persetujuan',
    'approved': 'Disetujui',
    'rejected': 'Ditolak'
  }
  
  // Check leave status first
  if (leaveStatusMap[status]) {
    return leaveStatusMap[status]
  }
  
  // Check attendance status
  if (attendanceStatusMap[status]) {
    return attendanceStatusMap[status]
  }
  
  // Check correction request status
  if (correctionStatusMap[status]) {
    return correctionStatusMap[status]
  }
  
  return status
}

const getTransactionDescription = (transaction) => {
  if (transaction.description) {
    return transaction.description
  }
  
  // Generate description based on source_type
  const sourceTypeMap = {
    'unscheduled_work': 'Kerja tanpa shift',
    'manual_adjustment': 'Penyesuaian manual',
    'holiday_work': 'Kerja di hari libur',
    'overtime_work': 'Lembur'
  }
  
  return sourceTypeMap[transaction.source_type] || transaction.source_type || 'Extra Off'
}

const calculateWorkDuration = (jamIn, jamOut) => {
  if (!jamIn || !jamOut) return '-'
  
  try {
    const inTime = new Date(`2000-01-01 ${jamIn}`)
    const outTime = new Date(`2000-01-01 ${jamOut}`)
    
    // Handle cross-day (if out time is earlier than in time, add 24 hours)
    if (outTime < inTime) {
      outTime.setHours(outTime.getHours() + 24)
    }
    
    const diffMs = outTime - inTime
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
    const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60))
    
    return `${diffHours}j ${diffMinutes}m`
  } catch (error) {
    return '-'
  }
}

const getScheduleClass = (schedule) => {
  // Use similar color scheme as UserShift/Calendar.vue
  const shiftName = schedule.shift_name?.toLowerCase() || ''
  
  if (!shiftName || shiftName === '-' || shiftName === 'off') {
    return 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
  } else if (shiftName.includes('m95') || shiftName.includes('morning')) {
    return 'bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200'
  } else if (shiftName.includes('s95') || shiftName.includes('siang')) {
    return 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200'
  } else if (shiftName.includes('malam') || shiftName.includes('night')) {
    return 'bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200'
  } else {
    return 'bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200'
  }
}

const getStatusClass = (status) => {
  switch (status) {
    case 'present':
      return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    case 'late':
      return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
    case 'absent':
      return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    case 'half_day':
      return 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
  }
}


const formatTime = (time) => {
  return new Date(time).toLocaleTimeString('id-ID', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

// Lifecycle - no need for onMounted since data comes from props

// Watch for filter changes
watch(filters, () => {
  fetchData()
}, { deep: true })

// Helper methods for correction requests

const getTypeText = (type) => {
  const typeMap = {
    'schedule': 'Koreksi Jadwal',
    'attendance': 'Koreksi Kehadiran',
    'manual_attendance': 'Tambah Kehadiran Manual'
  }
  return typeMap[type] || type
}

const formatCorrectionValue = (value, type) => {
  if (!value) return 'Tidak ada data'
  
  try {
    // Parse JSON if it's a string
    const data = typeof value === 'string' ? JSON.parse(value) : value
    
    if (type === 'schedule' || type === 'attendance') {
      // For schedule corrections, show shift name
      if (type === 'schedule') {
        return data === 'OFF' ? 'Libur' : `Shift: ${data}`
      }
      
      // For attendance corrections, show formatted attendance data
      if (type === 'attendance' && data.scan_date) {
        const scanDate = new Date(data.scan_date)
        const formattedDate = scanDate.toLocaleDateString('id-ID', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric'
        })
        const formattedTime = scanDate.toLocaleTimeString('id-ID', {
          hour: '2-digit',
          minute: '2-digit'
        })
        
        const inoutText = data.inoutmode === 1 ? 'Masuk' : data.inoutmode === 2 ? 'Keluar' : `Mode ${data.inoutmode}`
        
        return `${inoutText} - ${formattedDate} ${formattedTime}`
      }
    }
    
    // For manual attendance
    if (type === 'manual_attendance' && data.scan_date) {
      const scanDate = new Date(data.scan_date)
      const formattedDate = scanDate.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      })
      const formattedTime = scanDate.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit'
      })
      
      const inoutText = data.inoutmode === 1 ? 'Masuk' : data.inoutmode === 2 ? 'Keluar' : `Mode ${data.inoutmode}`
      
      return `Tambah ${inoutText} - ${formattedDate} ${formattedTime}`
    }
    
    // Fallback: return original value if can't parse
    return value
  } catch (error) {
    // If JSON parsing fails, return original value
    return value
  }
}

</script>

<style scoped>
.calendar-container {
  font-family: 'Inter', sans-serif;
}

.min-h-\[80px\] {
  min-height: 80px;
}
</style>
