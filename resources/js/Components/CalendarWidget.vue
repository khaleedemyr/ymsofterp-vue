<template>
    <div class="backdrop-blur-sm rounded-lg shadow-lg border p-4" 
         :class="isNight ? 'bg-slate-800/95 border-slate-600/50' : 'bg-white/95 border-slate-200'">
        <!-- Calendar Header -->
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold" :class="isNight ? 'text-white' : 'text-slate-700'">Kalender</h3>
            <div class="flex items-center gap-2">
                <button @click="previousMonth" class="p-1 rounded" :class="isNight ? 'hover:bg-slate-700' : 'hover:bg-slate-100'">
                    <svg class="w-4 h-4" :class="isNight ? 'text-slate-300' : 'text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>
                <span class="text-sm font-medium min-w-[120px] text-center" :class="isNight ? 'text-slate-200' : 'text-slate-700'">
                    {{ currentMonthName }} {{ currentYear }}
                </span>
                <button @click="nextMonth" class="p-1 rounded" :class="isNight ? 'hover:bg-slate-700' : 'hover:bg-slate-100'">
                    <svg class="w-4 h-4" :class="isNight ? 'text-slate-300' : 'text-slate-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="grid grid-cols-7 gap-1 mb-2">
            <div v-for="day in weekDays" :key="day" class="text-center text-xs font-medium py-1" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                {{ day }}
            </div>
        </div>

        <div class="grid grid-cols-7 gap-1">
            <div v-for="day in calendarDays" :key="day.key" 
                 @click="selectDate(day)"
                 :class="[
                     'relative p-2 text-center text-sm cursor-pointer rounded transition-colors',
                     day.isCurrentMonth ? (isNight ? 'text-slate-200' : 'text-slate-700') : (isNight ? 'text-slate-500' : 'text-slate-400'),
                     day.isToday ? (isNight ? 'bg-blue-600 text-white font-semibold' : 'bg-blue-100 text-blue-700 font-semibold') : '',
                     day.isSelected ? 'bg-blue-500 text-white' : '',
                     day.isHoliday ? (isNight ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700') : '',
                     day.hasReminder && !day.isToday && !day.isSelected && !day.isHoliday ? (isNight ? 'bg-green-600 border-2 border-green-400 text-white font-medium' : 'bg-green-50 border-2 border-green-300 text-green-700 font-medium') : '',
                     day.isCurrentMonth && !day.isToday && !day.isSelected && !day.isHoliday && !day.hasReminder ? (isNight ? 'hover:bg-slate-700' : 'hover:bg-slate-100') : ''
                 ]">
                {{ day.date }}
                
                <!-- Holiday indicator -->
                <div v-if="day.isHoliday" class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></div>
                
                <!-- Reminder indicator - more prominent -->
                <div v-if="day.hasReminder" class="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-white shadow-sm"></div>
            </div>
        </div>

        <!-- Selected Date Info -->
        <div v-if="selectedDate" class="mt-4 p-3 rounded-lg" :class="isNight ? 'bg-slate-700/50' : 'bg-slate-50'">
            <div class="flex items-center justify-between mb-2">
                <h4 class="font-medium" :class="isNight ? 'text-slate-200' : 'text-slate-700'">
                    {{ formatSelectedDate(selectedDate) }}
                </h4>
                <button @click="showReminderModal = true" class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
                    + Reminder
                </button>
            </div>
            
            <!-- Holiday Info -->
            <div v-if="selectedDateHoliday" class="mb-2 p-2 rounded text-sm" :class="isNight ? 'bg-red-600/20 text-red-300' : 'bg-red-100 text-red-700'">
                <strong>Hari Libur:</strong> {{ selectedDateHoliday.keterangan }}
            </div>
            
            <!-- Reminders -->
            <div v-if="selectedDateReminders.length > 0" class="space-y-1">
                <div v-for="reminder in selectedDateReminders" :key="reminder.id" 
                     @click="openReminderDetail(reminder)"
                     class="flex items-center justify-between p-2 rounded text-sm cursor-pointer transition-colors"
                     :class="isNight ? 'bg-blue-600/20 hover:bg-blue-600/30' : 'bg-blue-50 hover:bg-blue-100'">
                    <div class="flex flex-col">
                        <span class="font-medium" :class="isNight ? 'text-blue-300' : 'text-blue-700'">{{ reminder.title }}</span>
                        <span v-if="reminder.time" class="text-xs" :class="isNight ? 'text-blue-400' : 'text-blue-600'">{{ formatDisplayTime(reminder.time) }}</span>
                        <span v-if="reminder.created_by_name" class="text-xs" :class="isNight ? 'text-blue-500' : 'text-blue-500'">
                            Oleh: {{ reminder.created_by_name }}
                        </span>
                    </div>
                    <button @click.stop="deleteReminder(reminder.id)" class="text-red-500 hover:text-red-700">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reminder Modal -->
    <div v-if="showReminderModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-visible relative" style="z-index: 100;">
            <h3 class="text-lg font-semibold mb-4">Tambah Reminder</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal</label>
                    <input v-model="newReminder.date" type="date" 
                           class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Waktu (24 Jam)</label>
                    <div class="flex gap-2">
                        <select v-model="newReminder.hour" 
                                class="flex-1 px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">--</option>
                            <option v-for="h in 24" :key="h" :value="h-1">{{ String(h-1).padStart(2, '0') }}</option>
                        </select>
                        <span class="flex items-center text-slate-500">:</span>
                        <select v-model="newReminder.minute" 
                                class="flex-1 px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">--</option>
                            <option v-for="m in 60" :key="m" :value="m-1">{{ String(m-1).padStart(2, '0') }}</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Judul</label>
                    <input v-model="newReminder.title" type="text" placeholder="Masukkan judul reminder"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
                    <textarea v-model="newReminder.description" rows="3" placeholder="Masukkan deskripsi (opsional)"
                              class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <!-- User Selection -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Untuk Siapa?</label>
                    
                    <!-- Search Type -->
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Cari berdasarkan:</label>
                        <select v-model="searchType" @change="onSearchTypeChange"
                                class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            <option value="all">Semua User</option>
                            <option value="divisi">Divisi</option>
                            <option value="jabatan">Jabatan</option>
                            <option value="outlet">Outlet</option>
                        </select>
                    </div>
                    
                    <!-- Filter Selection (Divisi/Jabatan/Outlet) -->
                    <div v-if="searchType !== 'all'" class="mb-3">
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Pilih {{ searchType.charAt(0).toUpperCase() + searchType.slice(1) }}:
                        </label>
                        <select v-model="selectedFilter" 
                                class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            <option value="">-- Pilih {{ searchType.charAt(0).toUpperCase() + searchType.slice(1) }} --</option>
                            <option v-for="option in filterOptions" :key="option.id" :value="option.id">
                                {{ option.name }}
                            </option>
                        </select>
                    </div>
                    
                    <!-- Debug Info -->
                    <div class="mb-2 text-xs text-gray-500">
                        Debug: {{ filteredUsers.length }} users available
                    </div>
                    
                    <!-- Custom User Selector -->
                    <div class="relative" ref="userSelectorRef">
                        <!-- Search Input -->
                        <input 
                            v-model="userSearchQuery"
                            type="text"
                            placeholder="Cari user..."
                            class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                            @focus="showUserDropdown = true"
                            @blur="closeDropdown"
                            @keydown.escape="showUserDropdown = false"
                        >
                        
                        <!-- Dropdown -->
                        <div v-if="showUserDropdown" class="absolute top-full left-0 right-0 bg-white border border-slate-300 rounded-md shadow-lg z-50 max-h-60 overflow-y-auto">
                            <div v-if="filteredUsers.length === 0" class="px-3 py-2 text-sm text-slate-500">
                                Tidak ada user ditemukan
                            </div>
                            <div v-else>
                                <div 
                                    v-for="user in filteredUsers" 
                                    :key="user.id"
                                    @mousedown.prevent="toggleUserSelection(user)"
                                    class="px-3 py-2 hover:bg-slate-50 cursor-pointer border-b border-slate-100 last:border-b-0"
                                    :class="{ 'bg-blue-50': isUserSelected(user) }"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex-1">
                                            <div class="font-medium text-sm">{{ user.nama_lengkap }}</div>
                                            <div class="text-xs text-slate-500">
                                                {{ user.divisi }} - {{ user.jabatan }}
                                            </div>
                                            <div class="text-xs text-slate-400">{{ user.outlet }}</div>
                                        </div>
                                        <div v-if="isUserSelected(user)" class="text-blue-500 text-sm">
                                            ✓
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Selected Users Tags -->
                        <div v-if="selectedUsers.length > 0" class="mt-2 flex flex-wrap gap-1">
                            <div 
                                v-for="user in selectedUsers" 
                                :key="user.id"
                                class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs flex items-center gap-1"
                            >
                                <span>{{ user.nama_lengkap }}</span>
                                <button 
                                    @click="removeUser(user)"
                                    class="text-blue-600 hover:text-blue-800 font-bold"
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Selected Users Count -->
                    <div v-if="selectedUsers.length > 0" class="mt-2 text-xs text-slate-600">
                        {{ selectedUsers.length }} user dipilih
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-2 mt-6">
                <button @click="showReminderModal = false" 
                        class="px-4 py-2 text-slate-600 border border-slate-300 rounded-md hover:bg-slate-50">
                    Batal
                </button>
                <button @click="saveReminder" 
                        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                    Simpan
                </button>
            </div>
        </div>
    </div>

    <!-- Reminder Detail Modal -->
    <div v-if="showReminderDetailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto relative" style="z-index: 100;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Detail Reminder</h3>
                <button @click="closeReminderDetail" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div v-if="selectedReminderDetail" class="space-y-4">
                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Judul</label>
                    <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-md text-slate-900">
                        {{ selectedReminderDetail.title }}
                    </div>
                </div>
                
                <!-- Date -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal</label>
                    <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-md text-slate-900">
                        {{ formatDateDisplay(selectedReminderDetail.date) }}
                    </div>
                </div>
                
                <!-- Time -->
                <div v-if="selectedReminderDetail.time">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Waktu</label>
                    <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-md text-slate-900">
                        {{ formatDisplayTime(selectedReminderDetail.time) }}
                    </div>
                </div>
                
                <!-- Description -->
                <div v-if="selectedReminderDetail.description">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi</label>
                    <div class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-md text-slate-900 min-h-[60px]">
                        {{ selectedReminderDetail.description }}
                    </div>
                </div>
                
                <!-- Created Info -->
                <div class="pt-4 border-t border-slate-200">
                    <div class="text-sm text-slate-600 space-y-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            <span class="font-medium">Dibuat oleh:</span>
                            <span class="text-slate-900 font-semibold">{{ selectedReminderDetail.created_by_name || 'Unknown' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="font-medium">Tanggal dibuat:</span>
                            <span class="text-slate-900">{{ formatDateTime(selectedReminderDetail.created_at) }}</span>
                        </div>
                        <div v-if="selectedReminderDetail.created_by_email" class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span class="font-medium">Email:</span>
                            <span class="text-slate-900">{{ selectedReminderDetail.created_by_email }}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex justify-end gap-2 mt-6">
                <button @click="closeReminderDetail" 
                        class="px-4 py-2 text-slate-600 border border-slate-300 rounded-md hover:bg-slate-50 transition-colors">
                    Tutup
                </button>
                <button v-if="selectedReminderDetail" @click="deleteReminder(selectedReminderDetail.id); closeReminderDetail()" 
                        class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                    Hapus
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import axios from 'axios';
import Multiselect from '@vueform/multiselect';

// Props
const props = defineProps({
    isNight: {
        type: Boolean,
        default: false
    }
});

const currentDate = ref(new Date());
const selectedDate = ref(null);
const holidays = ref([]);
const reminders = ref([]);
const showReminderModal = ref(false);
const showReminderDetailModal = ref(false);
const selectedReminderDetail = ref(null);
const newReminder = ref({
    date: '',
    hour: '',
    minute: '',
    title: '',
    description: '',
    target_users: []
});

// User search and selection
const users = ref([]);
const jabatans = ref([]);
const divisis = ref([]);
const outlets = ref([]);
const searchQuery = ref('');
const searchType = ref('all'); // all, divisi, jabatan, outlet
const selectedFilter = ref(null); // Selected divisi/jabatan/outlet ID
const selectedUsers = ref([]);
const loadingUsers = ref(false);
const userSearchQuery = ref('');
const showUserDropdown = ref(false);
const userSelectorRef = ref(null);

const weekDays = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

const currentYear = computed(() => currentDate.value.getFullYear());
const currentMonth = computed(() => currentDate.value.getMonth());

// Filtered data based on search type and selected filter
const filteredUsers = computed(() => {
    let filtered = users.value;
    
    // Filter by selected divisi/jabatan/outlet
    if (searchType.value !== 'all' && selectedFilter.value) {
        switch (searchType.value) {
            case 'divisi':
                filtered = users.value.filter(u => u.division_id == selectedFilter.value);
                break;
            case 'jabatan':
                filtered = users.value.filter(u => u.id_jabatan == selectedFilter.value);
                break;
            case 'outlet':
                filtered = users.value.filter(u => u.id_outlet == selectedFilter.value);
                break;
        }
    }
    
    // Apply user search query filter
    if (userSearchQuery.value.trim()) {
        const query = userSearchQuery.value.toLowerCase();
        filtered = filtered.filter(user => 
            user.nama_lengkap.toLowerCase().includes(query) ||
            user.divisi.toLowerCase().includes(query) ||
            user.jabatan.toLowerCase().includes(query) ||
            user.outlet.toLowerCase().includes(query)
        );
    }
    
    console.log('Filtered users:', {
        searchType: searchType.value,
        selectedFilter: selectedFilter.value,
        userSearchQuery: userSearchQuery.value,
        totalUsers: users.value.length,
        filteredCount: filtered.length,
        sampleFiltered: filtered[0],
        allFilteredUsers: filtered
    });
    
    return filtered;
});

// Get filter options based on search type
const filterOptions = computed(() => {
    switch (searchType.value) {
        case 'divisi':
            return divisis.value;
        case 'jabatan':
            return jabatans.value;
        case 'outlet':
            return outlets.value;
        default:
            return [];
    }
});

const currentMonthName = computed(() => {
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return months[currentMonth.value];
});

const calendarDays = computed(() => {
    const year = currentYear.value;
    const month = currentMonth.value;
    
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDate = new Date(firstDay);
    startDate.setDate(startDate.getDate() - firstDay.getDay());
    
    const days = [];
    const today = new Date();
    
    for (let i = 0; i < 42; i++) {
        const date = new Date(startDate);
        date.setDate(startDate.getDate() + i);
        
        const dateString = formatDateString(date);
        const isCurrentMonth = date.getMonth() === month;
        const isToday = date.toDateString() === today.toDateString();
        const isHoliday = holidays.value.some(holiday => holiday.tgl_libur === dateString);
        const hasReminder = reminders.value.some(reminder => reminder.date === dateString);
        
        days.push({
            key: `${year}-${month}-${i}`,
            date: date.getDate(),
            fullDate: date,
            isCurrentMonth,
            isToday,
            isHoliday,
            hasReminder,
            isSelected: selectedDate.value && date.toDateString() === selectedDate.value.toDateString()
        });
    }
    
    return days;
});

const selectedDateHoliday = computed(() => {
    if (!selectedDate.value) return null;
    const dateString = formatDateString(selectedDate.value);
    return holidays.value.find(holiday => holiday.tgl_libur === dateString);
});

const selectedDateReminders = computed(() => {
    if (!selectedDate.value) return [];
    const dateString = formatDateString(selectedDate.value);
    return reminders.value.filter(reminder => reminder.date === dateString);
});

const formatDateString = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const formatTimeString = (date) => {
    const hours = date.getHours();
    const minutes = date.getMinutes();
    
    return {
        hour: hours,
        minute: minutes
    };
};

const formatDisplayTime = (time24) => {
    if (!time24) return '';
    
    const [hours, minutes] = time24.split(':');
    return `${hours}:${minutes}`;
};

const formatSelectedDate = (date) => {
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                   'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    const dayName = days[date.getDay()];
    const day = date.getDate();
    const monthName = months[date.getMonth()];
    const year = date.getFullYear();
    
    return `${dayName}, ${day} ${monthName} ${year}`;
};

const selectDate = (day) => {
    selectedDate.value = day.fullDate;
};

const previousMonth = () => {
    currentDate.value = new Date(currentYear.value, currentMonth.value - 1, 1);
};

const nextMonth = () => {
    currentDate.value = new Date(currentYear.value, currentMonth.value + 1, 1);
};

const fetchHolidays = async () => {
    try {
        const response = await axios.get('/api/holidays');
        holidays.value = response.data;
    } catch (error) {
        console.error('Error fetching holidays:', error);
    }
};

const fetchReminders = async () => {
    try {
        const response = await axios.get('/api/reminders');
        reminders.value = response.data;
    } catch (error) {
        console.error('Error fetching reminders:', error);
    }
};

const saveReminder = async () => {
    if (!newReminder.value.title.trim()) {
        alert('Judul reminder harus diisi');
        return;
    }
    
    if (selectedUsers.value.length === 0) {
        alert('Pilih minimal satu user untuk reminder');
        return;
    }
    
    try {
        // Convert hour and minute to time format
        const timeString = newReminder.value.hour !== '' && newReminder.value.minute !== '' 
            ? `${newReminder.value.hour.toString().padStart(2, '0')}:${newReminder.value.minute.toString().padStart(2, '0')}`
            : null;
        
        const reminderData = {
            date: newReminder.value.date,
            time: timeString,
            title: newReminder.value.title,
            description: newReminder.value.description,
            target_users: selectedUsers.value.map(user => user.id)
        };
        
        const response = await axios.post('/api/reminders', reminderData);
        
        // Add reminder to current user's list if they're in selected users
        const currentUserId = window.Laravel?.user?.id;
        if (selectedUsers.value.some(user => user.id === currentUserId)) {
            reminders.value.push(response.data);
        }
        
        // Reset form
        const currentTime = formatTimeString(new Date());
        newReminder.value = {
            date: formatDateString(selectedDate.value),
            hour: currentTime.hour,
            minute: currentTime.minute,
            title: '',
            description: '',
            target_users: []
        };
        selectedUsers.value = [];
        showReminderModal.value = false;
    } catch (error) {
        console.error('Error saving reminder:', error);
        alert('Gagal menyimpan reminder');
    }
};

// Custom user selector methods
const toggleUserSelection = (user) => {
    const index = selectedUsers.value.findIndex(u => u.id === user.id);
    if (index > -1) {
        selectedUsers.value.splice(index, 1);
    } else {
        selectedUsers.value.push(user);
    }
};

const isUserSelected = (user) => {
    return selectedUsers.value.some(u => u.id === user.id);
};

const removeUser = (user) => {
    const index = selectedUsers.value.findIndex(u => u.id === user.id);
    if (index > -1) {
        selectedUsers.value.splice(index, 1);
    }
};

const closeDropdown = () => {
    // Delay closing to allow click events to fire first
    setTimeout(() => {
        showUserDropdown.value = false;
    }, 150);
};

const handleClickOutside = (event) => {
    if (userSelectorRef.value && !userSelectorRef.value.contains(event.target)) {
        showUserDropdown.value = false;
    }
};

const openReminderDetail = (reminder) => {
    selectedReminderDetail.value = reminder;
    showReminderDetailModal.value = true;
};

const closeReminderDetail = () => {
    showReminderDetailModal.value = false;
    selectedReminderDetail.value = null;
};

const formatDateDisplay = (dateString) => {
    if (!dateString) return '';
    // Parse date string as local date to avoid timezone issues
    const [year, month, day] = dateString.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    return date.toLocaleDateString('id-ID', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const formatDateTime = (dateTimeString) => {
    if (!dateTimeString) return '';
    const date = new Date(dateTimeString);
    return date.toLocaleString('id-ID', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const deleteReminder = async (id) => {
    if (!confirm('Yakin ingin menghapus reminder ini?')) return;
    
    try {
        await axios.delete(`/api/reminders/${id}`);
        reminders.value = reminders.value.filter(r => r.id !== id);
    } catch (error) {
        console.error('Error deleting reminder:', error);
        alert('Gagal menghapus reminder');
    }
};

const fetchUsersData = async () => {
    loadingUsers.value = true;
    
    try {
        const response = await axios.get('/api/users/data');
        users.value = response.data.users;
        jabatans.value = response.data.jabatans;
        divisis.value = response.data.divisis;
        outlets.value = response.data.outlets;
        
        console.log('Users data loaded:', {
            users: users.value.length,
            jabatans: jabatans.value.length,
            divisis: divisis.value.length,
            outlets: outlets.value.length,
            sampleUser: users.value[0]
        });
    } catch (error) {
        console.error('Error fetching users data:', error);
        users.value = [];
        jabatans.value = [];
        divisis.value = [];
        outlets.value = [];
    } finally {
        loadingUsers.value = false;
    }
};

const onSearchTypeChange = () => {
    // Reset selected filter when search type changes
    selectedFilter.value = null;
};

// Debug selected users
watch(selectedUsers, (newUsers) => {
    console.log('Selected users changed:', newUsers);
}, { deep: true });

// Initialize
onMounted(() => {
    fetchHolidays();
    fetchReminders();
    fetchUsersData();
    selectedDate.value = new Date();
    newReminder.value.date = formatDateString(new Date());
    const currentTime = formatTimeString(new Date());
    newReminder.value.hour = currentTime.hour;
    newReminder.value.minute = currentTime.minute;
    
    // Add click outside listener
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    // Remove click outside listener
    document.removeEventListener('click', handleClickOutside);
});

// Watch for date changes to update reminder form
watch(selectedDate, (newDate) => {
    if (newDate) {
        newReminder.value.date = formatDateString(newDate);
    }
});
</script>

<style>
@import '@vueform/multiselect/themes/default.css';

.multiselect-tag {
    background: #3b82f6;
    color: white;
    border-radius: 4px;
    padding: 2px 6px;
    margin: 2px;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.multiselect-tag-icon {
    cursor: pointer;
    font-style: normal;
    font-weight: bold;
}

.multiselect-tag-icon:before {
    content: '×';
}

/* Ensure modal has proper z-index */
.fixed.inset-0 {
    z-index: 50;
}

/* Fix multiselect positioning in modal */
.multiselect {
    position: relative;
}

/* Custom multiselect styling */
.multiselect-custom {
    position: relative;
}

/* Force multiselect dropdown to be visible when open */
.multiselect.is-open .multiselect-dropdown {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    z-index: 99999 !important;
}

/* Ensure multiselect options are visible */
.multiselect-options {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    z-index: 99999 !important;
}

/* Fix multiselect dropdown when using append-to-body */
.multiselect-dropdown {
    z-index: 99999 !important;
    background: white !important;
    border: 1px solid #d1d5db !important;
    border-radius: 0.375rem !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
}

/* Global multiselect dropdown fix */
body .multiselect-dropdown {
    z-index: 99999 !important;
    position: absolute !important;
    background: white !important;
    border: 1px solid #d1d5db !important;
    border-radius: 0.375rem !important;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
}

/* Ensure multiselect is clickable */
.multiselect-custom .multiselect {
    pointer-events: auto !important;
}

.multiselect-custom .multiselect.is-open {
    pointer-events: auto !important;
}
</style>
