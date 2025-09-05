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
                  
                  <!-- Absent Button - Show only if no attendance data, is in payroll period, and not past date -->
                  <div v-if="day.isInPayrollPeriod && !day.holiday && !hasAttendanceData(day) && !isPastDate(day.date)" class="mt-1">
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
                    <span class="text-sm text-gray-900 dark:text-gray-100">
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

    <!-- Absent Modal -->
    <div v-if="showAbsentModalFlag" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
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
        
        <div class="p-4">
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
              
              
              <!-- Supporting Document -->
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Dokumen Pendukung
                  <span v-if="selectedLeaveType && selectedLeaveType.requires_document" class="text-red-500">*</span>
                  <span v-else class="text-gray-500">(Opsional)</span>
                </label>
                <input 
                  type="file"
                  @change="handleFileUpload"
                  accept=".pdf,.jpg,.jpeg,.png"
                  :required="selectedLeaveType && selectedLeaveType.requires_document"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-gray-100"
                >
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  Format yang didukung: PDF, JPG, JPEG, PNG (Max 5MB)
                  <span v-if="selectedLeaveType && selectedLeaveType.requires_document" class="text-red-500">
                    - Dokumen wajib diupload
                  </span>
                </p>
              </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
              <button 
                type="button"
                @click="closeAbsentModal"
                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors"
              >
                Batal
              </button>
              <button 
                type="submit"
                :disabled="submittingAbsent"
                class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:bg-red-400 rounded-md transition-colors"
              >
                <i v-if="submittingAbsent" class="fa-solid fa-spinner fa-spin mr-2"></i>
                <i v-else class="fa-solid fa-paper-plane mr-2"></i>
                {{ submittingAbsent ? 'Mengirim...' : 'Kirim Permohonan' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, onMounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps({
  workSchedules: Array,
  attendanceRecords: Array,
  attendanceSummary: Object,
  calendar: Object,
  holidays: Array,
  leaveTypes: Array,
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
  document: null,
  date_from: '',
  date_to: ''
})

// Computed property untuk mendapatkan leave type yang dipilih
const selectedLeaveType = computed(() => {
  if (!absentForm.value.leave_type_id || !props.leaveTypes) return null
  return props.leaveTypes.find(lt => lt.id == absentForm.value.leave_type_id)
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
    // Jika max_days > 0, hitung tanggal to = date_from + max_days
    const dateTo = new Date(dateFrom)
    dateTo.setDate(dateTo.getDate() + maxDays)
    return dateTo.toISOString().split('T')[0]
  }
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
    
    return date < today
  } catch (error) {
    console.error('Error checking past date:', error)
    return false
  }
}

const showAbsentModal = (date) => {
  selectedAbsentDate.value = date
  showAbsentModalFlag.value = true
  // Reset form
  absentForm.value = {
    leave_type_id: '',
    reason: '',
    document: null,
    date_from: date,
    date_to: date
  }
}

const closeAbsentModal = () => {
  showAbsentModalFlag.value = false
  selectedAbsentDate.value = ''
  submittingAbsent.value = false
}

const handleFileUpload = (event) => {
  const file = event.target.files[0]
  if (file) {
    // Validate file size (5MB max)
    if (file.size > 5 * 1024 * 1024) {
      alert('Ukuran file maksimal 5MB')
      event.target.value = ''
      return
    }
    absentForm.value.document = file
  }
}

const submitAbsentRequest = async () => {
  submittingAbsent.value = true
  
  try {
    const formData = new FormData()
    formData.append('leave_type_id', absentForm.value.leave_type_id)
    formData.append('date_from', absentForm.value.date_from)
    formData.append('date_to', absentForm.value.date_to)
    formData.append('reason', absentForm.value.reason)
    
    if (absentForm.value.document) {
      formData.append('document', absentForm.value.document)
    }
    
    const response = await axios.post('/api/attendance/absent-request', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    
    if (response.data.success) {
      alert('Permohonan izin/cuti berhasil dikirim!')
      closeAbsentModal()
      // Optionally refresh the calendar data
      // loadCalendarData()
    } else {
      alert('Gagal mengirim permohonan: ' + (response.data.message || 'Terjadi kesalahan'))
    }
  } catch (error) {
    console.error('Error submitting absent request:', error)
    if (error.response && error.response.data && error.response.data.errors) {
      // Handle validation errors
      const errors = error.response.data.errors
      const errorMessages = Object.values(errors).flat().join('\n')
      alert('Data tidak valid:\n' + errorMessages)
    } else {
      alert('Terjadi kesalahan saat mengirim permohonan')
    }
  } finally {
    submittingAbsent.value = false
  }
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

const getStatusText = (status) => {
  switch (status) {
    case 'present':
      return 'Hadir'
    case 'late':
      return 'Terlambat'
    case 'absent':
      return 'Tidak Hadir'
    case 'half_day':
      return 'Setengah Hari'
    default:
      return status
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
</script>

<style scoped>
.calendar-container {
  font-family: 'Inter', sans-serif;
}

.min-h-\[80px\] {
  min-height: 80px;
}
</style>
