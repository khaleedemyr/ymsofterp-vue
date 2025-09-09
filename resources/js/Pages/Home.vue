<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AnalogClock from '@/Components/AnalogClock.vue';
import WeatherIcon from '@/Components/WeatherIcon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import AnnouncementList from '@/Components/AnnouncementList.vue';
import VueEasyLightbox from 'vue-easy-lightbox';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import Swal from 'sweetalert2';

const page = usePage();
const user = page.props.auth?.user || {};

const { t, locale } = useI18n();

const greeting = ref('');
const time = ref(new Date());
const weather = ref({ temp: '', desc: '', icon: '', city: '', code: '' });
const isNight = ref(false);
const quote = ref({ text: '', author: '' });
const loadingQuote = ref(true);

// Approval notifications
const pendingApprovals = ref([]);
const loadingApprovals = ref(false);
const showApprovalModal = ref(false);
const selectedApproval = ref(null);

// General notifications
const leaveNotifications = ref([]);
const loadingNotifications = ref(false);

// HRD approvals
const pendingHrdApprovals = ref([]);
const loadingHrdApprovals = ref(false);

// Correction approvals
const pendingCorrectionApprovals = ref([]);
const loadingCorrectionApprovals = ref(false);

// Announcements modal
const showAnnouncementsModal = ref(false);
const announcements = ref([]);
const loadingAnnouncements = ref(false);
const announcementsPagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 10,
    total: 0
});
const announcementsFilters = ref({
    search: '',
    target: '',
    date_from: '',
    date_to: ''
});

// Computed properties for user information
const userOutlet = computed(() => user.outlet?.nama_outlet || 'N/A');
const userDivisi = computed(() => user.divisi?.nama_divisi || 'N/A');
const userLevel = computed(() => user.jabatan?.level?.nama_level || 'N/A');
const userJabatan = computed(() => user.jabatan?.nama_jabatan || 'N/A');
const totalNotificationsCount = computed(() => {
    const approvalCount = pendingApprovals.value.length;
    const hrdApprovalCount = pendingHrdApprovals.value.length;
    const correctionApprovalCount = pendingCorrectionApprovals.value.length;
    const leaveNotificationCount = leaveNotifications.value.filter(n => !n.is_read && (n.type === 'leave_approved' || n.type === 'leave_rejected')).length;
    return approvalCount + hrdApprovalCount + correctionApprovalCount + leaveNotificationCount;
});

function updateGreeting() {
    const hour = time.value.getHours();
    if (hour >= 5 && hour < 12) greeting.value = t('greeting.pagi');
    else if (hour >= 12 && hour < 16) greeting.value = t('greeting.siang');
    else if (hour >= 16 && hour < 19) greeting.value = t('greeting.sore');
    else greeting.value = t('greeting.malam');
    isNight.value = (hour >= 19 || hour < 5);
}

function updateTime() {
    time.value = new Date();
    updateGreeting();
}

async function fetchQuote() {
    loadingQuote.value = true;
    try {
        const today = new Date();
        const startOfYear = new Date(today.getFullYear(), 0, 0);
        const diff = today - startOfYear;
        const dayOfYear = Math.floor(diff / (1000 * 60 * 60 * 24));
        
        const response = await fetch(`/api/quotes/${dayOfYear}`);
        const data = await response.json();
        
        if (data.quote && data.author) {
            quote.value = {
                text: data.quote,
                author: data.author
            };
        } else {
            quote.value = { text: t('home.default_quote'), author: t('home.default_author') };
        }
    } catch (e) {
        quote.value = { text: t('home.default_quote'), author: t('home.default_author') };
    }
    loadingQuote.value = false;
}

function getInitials(name) {
    if (!name) return '';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0,2);
}

async function fetchWeather() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(async (pos) => {
            const lat = pos.coords.latitude;
            const lon = pos.coords.longitude;
            const API_KEY = 'b1b15e88fa797225412429c1c50c122a1';
            const url = `https://api.openweathermap.org/data/2.5/weather?lat=${lat}&lon=${lon}&appid=${API_KEY}&units=metric&lang=${locale.value}`;
            const res = await fetch(url);
            const data = await res.json();
            weather.value = {
                temp: Math.round(data.main.temp) + '°C',
                desc: data.weather[0].description,
                icon: `https://openweathermap.org/img/wn/${data.weather[0].icon}@2x.png`,
                city: data.name,
                code: data.weather[0].id,
            };
        });
    }
}

// Approval functions
async function loadPendingApprovals() {
    loadingApprovals.value = true;
    try {
        const response = await axios.get('/api/approval/pending');
        if (response.data.success) {
            pendingApprovals.value = response.data.approvals;
        }
    } catch (error) {
        console.error('Error loading pending approvals:', error);
    } finally {
        loadingApprovals.value = false;
    }
}

// Notification functions
async function loadLeaveNotifications() {
    loadingNotifications.value = true;
    try {
        const response = await axios.get('/api/approval/notifications');
        if (response.data.success) {
            leaveNotifications.value = response.data.notifications;
        }
    } catch (error) {
        console.error('Error loading leave notifications:', error);
    } finally {
        loadingNotifications.value = false;
    }
}

// HRD approval functions
async function loadPendingHrdApprovals() {
    if (user.division_id !== 6) return; // Only for HRD users
    
    loadingHrdApprovals.value = true;
    try {
        const response = await axios.get('/api/approval/pending-hrd');
        if (response.data.success) {
            pendingHrdApprovals.value = response.data.approvals;
        }
    } catch (error) {
        console.error('Error loading pending HRD approvals:', error);
    } finally {
        loadingHrdApprovals.value = false;
    }
}

// Correction approval functions
async function loadPendingCorrectionApprovals() {
    if (user.division_id !== 6) return; // Only for HRD users
    
    loadingCorrectionApprovals.value = true;
    try {
        const response = await axios.get('/api/schedule-attendance-correction/pending-approvals');
        if (response.data.success) {
            pendingCorrectionApprovals.value = response.data.approvals;
        }
    } catch (error) {
        console.error('Error loading pending correction approvals:', error);
    } finally {
        loadingCorrectionApprovals.value = false;
    }
}

async function showApprovalDetails(approvalId) {
    try {
        const response = await axios.get(`/api/approval/${approvalId}`);
        if (response.data.success) {
            selectedApproval.value = response.data.approval;
            showApprovalModal.value = true;
        }
    } catch (error) {
        console.error('Error loading approval details:', error);
        await Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Gagal memuat detail approval',
            confirmButtonText: 'OK',
            confirmButtonColor: '#EF4444'
        });
    }
}

async function handleNotificationClick(notification) {
    // Jika notifikasi adalah permohonan izin yang perlu approval
    if (notification.type === 'leave_approval_request' || notification.type === 'leave_hrd_approval_request') {
        // Cari approval request yang sesuai
        const approvalId = await findApprovalIdFromNotification(notification);
        if (approvalId) {
            await showApprovalDetails(approvalId);
        }
    }
}

async function findApprovalIdFromNotification(notification) {
    try {
        // Jika notifikasi memiliki approval_id, gunakan itu
        if (notification.approval_id) {
            return notification.approval_id;
        }
        
        // Fallback: cari dari pending approvals
        if (user.division_id === 6) {
            // Jika user adalah HRD, cari di pending HRD approvals
            const response = await axios.get('/api/approval/pending-hrd');
            if (response.data.success && response.data.approvals.length > 0) {
                // Ambil approval terbaru yang sesuai
                return response.data.approvals[0].id;
            }
        } else {
            // Jika user adalah atasan, cari di pending approvals
            const response = await axios.get('/api/approval/pending');
            if (response.data.success && response.data.approvals.length > 0) {
                // Ambil approval terbaru yang sesuai
                return response.data.approvals[0].id;
            }
        }
    } catch (error) {
        console.error('Error finding approval ID:', error);
    }
    return null;
}

async function showAllPendingApprovals() {
    try {
        // Ambil semua pending approvals
        let allApprovals = [];
        
        if (user.division_id === 6) {
            // Jika user adalah HRD, ambil pending HRD approvals
            const response = await axios.get('/api/approval/pending-hrd?limit=50');
            if (response.data.success) {
                allApprovals = response.data.approvals;
            }
        } else {
            // Jika user adalah atasan, ambil pending approvals
            const response = await axios.get('/api/approval/pending?limit=50');
            if (response.data.success) {
                allApprovals = response.data.approvals;
            }
        }
        
        if (allApprovals.length > 0) {
            // Tampilkan approval pertama
            await showApprovalDetails(allApprovals[0].id);
        } else {
            await Swal.fire({
                icon: 'info',
                title: 'Tidak Ada Approval',
                text: 'Tidak ada permohonan yang perlu disetujui saat ini.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#3B82F6'
            });
        }
    } catch (error) {
        console.error('Error loading pending approvals:', error);
        await Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Gagal memuat data approval',
            confirmButtonText: 'OK',
            confirmButtonColor: '#EF4444'
        });
    }
}

async function approveRequest(approvalId) {
    const result = await Swal.fire({
        title: 'Setujui Permohonan?',
        text: 'Apakah Anda yakin ingin menyetujui permohonan izin/cuti ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Setujui',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280'
    });

    if (result.isConfirmed) {
        try {
            const response = await axios.post(`/api/approval/${approvalId}/approve`);
            if (response.data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: response.data.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#10B981'
                });
                showApprovalModal.value = false;
                await loadLeaveNotifications();
            }
        } catch (error) {
            console.error('Error approving request:', error);
            await Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Gagal menyetujui permohonan',
                confirmButtonText: 'OK',
                confirmButtonColor: '#EF4444'
            });
        }
    }
}

async function rejectRequest(approvalId) {
    const { value: notes } = await Swal.fire({
        title: 'Tolak Permohonan',
        text: 'Berikan alasan penolakan:',
        input: 'textarea',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        inputValidator: (value) => {
            if (!value) {
                return 'Alasan penolakan harus diisi!';
            }
        },
        showCancelButton: true,
        confirmButtonText: 'Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280'
    });

    if (notes) {
        try {
            const response = await axios.post(`/api/approval/${approvalId}/reject`, {
                notes: notes
            });
            if (response.data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: response.data.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#10B981'
                });
                showApprovalModal.value = false;
                await loadLeaveNotifications();
            }
        } catch (error) {
            console.error('Error rejecting request:', error);
            await Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Gagal menolak permohonan',
                confirmButtonText: 'OK',
                confirmButtonColor: '#EF4444'
            });
        }
    }
}

async function hrdApproveRequest(approvalId) {
    const result = await Swal.fire({
        title: 'Setujui Permohonan HRD?',
        text: 'Apakah Anda yakin ingin menyetujui permohonan izin/cuti ini sebagai HRD?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Setujui',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280'
    });

    if (result.isConfirmed) {
        try {
            const response = await axios.post(`/api/approval/${approvalId}/hrd-approve`);
            if (response.data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: response.data.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#10B981'
                });
                showApprovalModal.value = false;
                await loadPendingHrdApprovals();
                await loadLeaveNotifications();
            }
        } catch (error) {
            console.error('Error HRD approving request:', error);
            await Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Gagal menyetujui permohonan',
                confirmButtonText: 'OK',
                confirmButtonColor: '#EF4444'
            });
        }
    }
}

async function hrdRejectRequest(approvalId) {
    const { value: notes } = await Swal.fire({
        title: 'Tolak Permohonan HRD',
        text: 'Berikan alasan penolakan:',
        input: 'textarea',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        inputValidator: (value) => {
            if (!value) {
                return 'Alasan penolakan harus diisi!';
            }
        },
        showCancelButton: true,
        confirmButtonText: 'Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280'
    });

    if (notes) {
        try {
            const response = await axios.post(`/api/approval/${approvalId}/hrd-reject`, {
                notes: notes
            });
            if (response.data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: response.data.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#10B981'
                });
                showApprovalModal.value = false;
                await loadPendingHrdApprovals();
                await loadLeaveNotifications();
            }
        } catch (error) {
            console.error('Error HRD rejecting request:', error);
            await Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Gagal menolak permohonan',
                confirmButtonText: 'OK',
                confirmButtonColor: '#EF4444'
            });
        }
    }
}

// Correction approval functions
async function approveCorrection(approvalId) {
    const result = await Swal.fire({
        title: 'Setujui Koreksi?',
        text: 'Apakah Anda yakin ingin menyetujui koreksi ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Setujui',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280'
    });

    if (result.isConfirmed) {
        try {
            const response = await axios.post(`/api/schedule-attendance-correction/approve/${approvalId}`);
            if (response.data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: response.data.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#10B981'
                });
                await loadPendingCorrectionApprovals();
            }
        } catch (error) {
            console.error('Error approving correction:', error);
            await Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Gagal menyetujui koreksi',
                confirmButtonText: 'OK',
                confirmButtonColor: '#EF4444'
            });
        }
    }
}

async function rejectCorrection(approvalId) {
    const { value: rejectionReason } = await Swal.fire({
        title: 'Tolak Koreksi',
        text: 'Berikan alasan penolakan:',
        input: 'textarea',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        inputValidator: (value) => {
            if (!value) {
                return 'Alasan penolakan harus diisi!';
            }
        },
        showCancelButton: true,
        confirmButtonText: 'Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280'
    });

    if (rejectionReason) {
        try {
            const response = await axios.post(`/api/schedule-attendance-correction/reject/${approvalId}`, {
                rejection_reason: rejectionReason
            });
            if (response.data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: response.data.message,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#10B981'
                });
                await loadPendingCorrectionApprovals();
            }
        } catch (error) {
            console.error('Error rejecting correction:', error);
            await Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Gagal menolak koreksi',
                confirmButtonText: 'OK',
                confirmButtonColor: '#EF4444'
            });
        }
    }
}

// Lightbox state
const lightboxVisible = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

function isImageFile(filePath) {
    if (!filePath) return false;
    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp'];
    const extension = filePath.toLowerCase().substring(filePath.lastIndexOf('.'));
    return imageExtensions.includes(extension);
}

// Add image handling function like in RevenueReportModal.vue
const getImageUrl = (imagePath) => {
    if (!imagePath) return null;
    try {
        return `/storage/${imagePath}`;
    } catch (error) {
        console.error('Error processing image:', error);
        return null;
    }
}

function openImageModal(imageUrl, allImagePaths = []) {
    console.log('=== OPENING LIGHTBOX ===');
    console.log('imageUrl:', imageUrl);
    console.log('allImagePaths:', allImagePaths);
    console.log('allImagePaths type:', typeof allImagePaths);
    console.log('allImagePaths length:', allImagePaths?.length);
    
    if (!allImagePaths || allImagePaths.length === 0) {
        console.log('No image paths provided, using single image');
        lightboxImages.value = [imageUrl];
        lightboxIndex.value = 0;
    } else {
        // Convert all image paths to full URLs
        lightboxImages.value = allImagePaths.map(path => getImageUrl(path)).filter(url => url);
        console.log('Converted lightboxImages:', lightboxImages.value);
        
        // Find current image index
        lightboxIndex.value = allImagePaths.findIndex(path => 
            imageUrl.includes(path.split('/').pop())
        );
        
        if (lightboxIndex.value === -1) {
            lightboxIndex.value = 0;
        }
    }
    
    lightboxVisible.value = true;
    
    console.log('Final state:', { 
        lightboxVisible: lightboxVisible.value,
        lightboxImages: lightboxImages.value, 
        lightboxIndex: lightboxIndex.value
    });
    console.log('=== LIGHTBOX OPENED ===');
}


// Announcement functions
async function loadAnnouncements(page = 1) {
    loadingAnnouncements.value = true;
    try {
        const params = new URLSearchParams({
            page: page,
            per_page: announcementsPagination.value.per_page,
            search: announcementsFilters.value.search,
            target: announcementsFilters.value.target,
            date_from: announcementsFilters.value.date_from,
            date_to: announcementsFilters.value.date_to
        });
        
        const response = await axios.get(`/api/user-announcements?${params}`);
        if (response.data.success) {
            announcements.value = response.data.announcements.data || [];
            announcementsPagination.value = {
                current_page: response.data.announcements.current_page,
                last_page: response.data.announcements.last_page,
                per_page: response.data.announcements.per_page,
                total: response.data.announcements.total
            };
        }
    } catch (error) {
        console.error('Error loading announcements:', error);
        await Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Gagal memuat pengumuman',
            confirmButtonText: 'OK',
            confirmButtonColor: '#EF4444'
        });
    } finally {
        loadingAnnouncements.value = false;
    }
}

async function showAllAnnouncements() {
    showAnnouncementsModal.value = true;
    announcementsFilters.value = {
        search: '',
        target: '',
        date_from: '',
        date_to: ''
    };
    await loadAnnouncements(1);
}

function closeAnnouncementsModal() {
    showAnnouncementsModal.value = false;
    announcements.value = [];
    announcementsPagination.value = {
        current_page: 1,
        last_page: 1,
        per_page: 10,
        total: 0
    };
}

async function applyFilters() {
    await loadAnnouncements(1);
}

function clearFilters() {
    announcementsFilters.value = {
        search: '',
        target: '',
        date_from: '',
        date_to: ''
    };
    loadAnnouncements(1);
}

async function changePage(page) {
    await loadAnnouncements(page);
}

function getTargetNames(targets) {
    if (!targets || targets.length === 0) return 'Semua';
    
    const names = targets.map(target => target.target_name).filter(Boolean);
    if (names.length === 0) return 'Semua';
    
    if (names.length <= 2) {
        return names.join(', ');
    } else {
        return `${names.slice(0, 2).join(', ')} dan ${names.length - 2} lainnya`;
    }
}

function viewAnnouncement(id) {
    window.open(`/announcement/${id}`, '_blank');
}

onMounted(() => {
    updateGreeting();
    setInterval(updateTime, 1000);
    fetchQuote();
    fetchWeather();
    loadPendingApprovals();
    loadLeaveNotifications();
    loadPendingHrdApprovals();
    loadPendingCorrectionApprovals();
});

watch(locale, () => {
    updateGreeting();
    fetchWeather();
});
</script>

<template>
    <AppLayout>
        <Head title="Home" />
        <div :class="[
            'min-h-screen w-full transition-all duration-700 relative',
            isNight ? 'bg-gradient-to-br from-slate-900 via-blue-900 to-indigo-900' : 'bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50'
        ]">
            <!-- Animasi bintang jika malam -->
            <div v-if="isNight" class="absolute inset-0 z-0 overflow-hidden pointer-events-none">
                <div v-for="n in 50" :key="n" :style="{
                    left: Math.random()*100+'vw',
                    top: Math.random()*100+'vh',
                    width: '2px',
                    height: '2px',
                    background: 'white',
                    position: 'absolute',
                    borderRadius: '50%',
                    opacity: Math.random()
                }"></div>
            </div>
            
            <!-- Main Content Grid -->
            <div class="relative z-10 w-full max-w-7xl mx-auto p-4 md:p-6 h-screen flex flex-col">
                <!-- Top Section: Welcome Card -->
                <div class="flex-shrink-0 mb-4">
                    <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 md:p-6 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                        :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                        <!-- Avatar user -->
                        <div class="flex items-center gap-4 mb-4">
                            <div v-if="user.avatar" class="w-16 h-16 rounded-full overflow-hidden border-3 border-white shadow-lg">
                                <img :src="user.avatar ? `/storage/${user.avatar}` : '/images/avatar-default.png'" alt="Avatar" class="w-full h-full object-cover" />
                            </div>
                            <div v-else class="w-16 h-16 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-2xl font-bold border-3 border-white shadow-lg">
                                {{ getInitials(user.nama_lengkap) }}
                            </div>
                            <div class="flex-1">
                                <div class="text-2xl md:text-3xl font-extrabold" :class="isNight ? 'text-white' : 'text-slate-800'">{{ greeting }},</div>
                                <div class="text-lg md:text-xl font-bold" :class="isNight ? 'text-indigo-200' : 'text-indigo-700'">{{ user.nama_lengkap }}</div>
                                
                                <!-- User Information Cards -->
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <!-- Outlet -->
                                    <div class="flex items-center gap-2 p-2 rounded-lg" :class="isNight ? 'bg-slate-700/50' : 'bg-blue-50'">
                                        <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                        <div class="text-xs font-medium" :class="isNight ? 'text-blue-300' : 'text-blue-700'">Outlet:</div>
                                        <div class="text-xs font-semibold" :class="isNight ? 'text-white' : 'text-slate-800'">{{ userOutlet }}</div>
                                    </div>
                                    
                                    <!-- Divisi -->
                                    <div class="flex items-center gap-2 p-2 rounded-lg" :class="isNight ? 'bg-slate-700/50' : 'bg-green-50'">
                                        <div class="w-2 h-2 rounded-full bg-green-500"></div>
                                        <div class="text-xs font-medium" :class="isNight ? 'text-green-300' : 'text-green-700'">Divisi:</div>
                                        <div class="text-xs font-semibold" :class="isNight ? 'text-white' : 'text-slate-800'">{{ userDivisi }}</div>
                                    </div>
                                    
                                    <!-- Level -->
                                    <div class="flex items-center gap-2 p-2 rounded-lg" :class="isNight ? 'bg-slate-700/50' : 'bg-purple-50'">
                                        <div class="w-2 h-2 rounded-full bg-purple-500"></div>
                                        <div class="text-xs font-medium" :class="isNight ? 'text-purple-300' : 'text-purple-700'">Level:</div>
                                        <div class="text-xs font-semibold" :class="isNight ? 'text-white' : 'text-slate-800'">{{ userLevel }}</div>
                                    </div>
                                    
                                    <!-- Jabatan -->
                                    <div class="flex items-center gap-2 p-2 rounded-lg" :class="isNight ? 'bg-slate-700/50' : 'bg-orange-50'">
                                        <div class="w-2 h-2 rounded-full bg-orange-500"></div>
                                        <div class="text-xs font-medium" :class="isNight ? 'text-orange-300' : 'text-orange-700'">Jabatan:</div>
                                        <div class="text-xs font-semibold" :class="isNight ? 'text-white' : 'text-slate-800'">{{ userJabatan }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quote Section -->
                        <div class="p-4 rounded-xl border shadow-lg animate-fade-in"
                            :class="[
                                isNight ? 'bg-slate-700/80 text-white border-slate-600' : 'bg-gradient-to-r from-indigo-50 to-purple-50 text-slate-800 border-indigo-200'
                            ]">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-semibold text-sm" :class="isNight ? 'text-indigo-300' : 'text-indigo-600'">{{ t('home.quote_of_the_day') }}</span>
                            </div>
                            <div v-if="loadingQuote" class="italic text-sm" :class="isNight ? 'text-slate-300' : 'text-slate-500'">{{ t('home.loading_quote') }}</div>
                            <template v-else>
                                <div class="italic text-sm md:text-base" :class="isNight ? 'text-slate-200' : 'text-slate-700'">"{{ quote.text }}"</div>
                                <div class="mt-1 text-right font-semibold text-xs" :class="isNight ? 'text-indigo-300' : 'text-indigo-600'">- {{ quote.author }}</div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Notifications Section -->
                <div v-if="totalNotificationsCount > 0" class="flex-shrink-0 mb-4">
                    <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                        :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-red-500 animate-pulse"></div>
                                <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    Notifikasi Izin/Cuti
                                </h3>
                            </div>
                            <div class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                                {{ totalNotificationsCount }}
                            </div>
                        </div>
                        
                        <div v-if="loadingApprovals || loadingNotifications || loadingHrdApprovals || loadingCorrectionApprovals" class="text-center py-4">
                            <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                            <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
                        </div>
                        
                        <div v-else class="space-y-2">
                            <!-- Approval Requests (Supervisor) -->
                            <div v-for="approval in pendingApprovals.slice(0, 2)" :key="'approval-' + approval.id"
                                @click="showApprovalDetails(approval.id)"
                                class="p-3 rounded-lg cursor-pointer transition-all duration-200 hover:scale-105"
                                :class="isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-blue-50 hover:bg-blue-100'">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                            {{ approval.user.nama_lengkap }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                            {{ approval.leave_type.name }} • {{ approval.duration_text }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                            {{ new Date(approval.date_from).toLocaleDateString('id-ID') }} - {{ new Date(approval.date_to).toLocaleDateString('id-ID') }}
                                        </div>
                                    </div>
                                    <div class="text-xs text-blue-500 font-medium">
                                        Klik untuk detail
                                    </div>
                                </div>
                            </div>

                            <!-- HRD Approval Requests -->
                            <div v-for="approval in pendingHrdApprovals.slice(0, 2)" :key="'hrd-approval-' + approval.id"
                                @click="showApprovalDetails(approval.id)"
                                class="p-3 rounded-lg cursor-pointer transition-all duration-200 hover:scale-105"
                                :class="isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-purple-50 hover:bg-purple-100'">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                            {{ approval.user.nama_lengkap }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                            {{ approval.leave_type.name }} • {{ approval.duration_text }} • HRD Approval
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                            {{ new Date(approval.date_from).toLocaleDateString('id-ID') }} - {{ new Date(approval.date_to).toLocaleDateString('id-ID') }}
                                        </div>
                                    </div>
                                    <div class="text-xs text-purple-500 font-medium">
                                        Klik untuk detail
                                    </div>
                                </div>
                            </div>

                            <!-- Correction Approval Requests -->
                            <div v-for="approval in pendingCorrectionApprovals.slice(0, 2)" :key="'correction-approval-' + approval.id"
                                class="p-3 rounded-lg transition-all duration-200"
                                :class="isNight ? 'bg-slate-700/50' : 'bg-orange-50'">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex-1">
                                        <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                            {{ approval.employee_name }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                            {{ approval.type === 'schedule' ? 'Koreksi Schedule' : 'Koreksi Attendance' }} • {{ approval.nama_outlet }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                            {{ new Date(approval.tanggal).toLocaleDateString('id-ID') }} • Dari: {{ approval.old_value }} → Ke: {{ approval.new_value }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="approveCorrection(approval.id)" 
                                            class="flex-1 text-xs bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600 transition-colors">
                                        <i class="fa-solid fa-check mr-1"></i>Setujui
                                    </button>
                                    <button @click="rejectCorrection(approval.id)" 
                                            class="flex-1 text-xs bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600 transition-colors">
                                        <i class="fa-solid fa-times mr-1"></i>Tolak
                                    </button>
                                </div>
                            </div>

                            <!-- Leave Notifications -->
                            <div v-for="notification in leaveNotifications.filter(n => n.type === 'leave_approved' || n.type === 'leave_rejected').slice(0, 2)" :key="'notification-' + notification.id"
                                @click="handleNotificationClick(notification)"
                                class="p-3 rounded-lg transition-all duration-200"
                                :class="[
                                    isNight ? 'bg-slate-700/50' : 'bg-green-50',
                                    notification.type === 'leave_approved' ? (isNight ? 'border-l-4 border-green-500' : 'border-l-4 border-green-500') :
                                    notification.type === 'leave_rejected' ? (isNight ? 'border-l-4 border-red-500' : 'border-l-4 border-red-500') :
                                    (isNight ? 'border-l-4 border-yellow-500' : 'border-l-4 border-yellow-500')
                                ]">
                                <div class="flex items-start gap-2">
                                    <div class="flex-shrink-0 mt-1">
                                        <div v-if="notification.type === 'leave_approved'" class="w-2 h-2 rounded-full bg-green-500"></div>
                                        <div v-else-if="notification.type === 'leave_rejected'" class="w-2 h-2 rounded-full bg-red-500"></div>
                                        <div v-else class="w-2 h-2 rounded-full bg-yellow-500"></div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="text-xs font-medium" :class="isNight ? 'text-white' : 'text-slate-800'">
                                            {{ notification.type === 'leave_approved' ? 'Izin Disetujui' : 'Izin Ditolak' }}
                                        </div>
                                        <div class="text-xs mt-1" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                            {{ notification.message }}
                                        </div>
                                        <div class="text-xs mt-1" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                            {{ new Date(notification.created_at).toLocaleString('id-ID') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="(pendingApprovals.length + pendingHrdApprovals.length + leaveNotifications.filter(n => n.type === 'leave_approved' || n.type === 'leave_rejected').length) > 6" class="text-center pt-2">
                                <button class="text-sm text-blue-500 hover:text-blue-700 font-medium">
                                    Lihat {{ (pendingApprovals.length + pendingHrdApprovals.length + leaveNotifications.filter(n => n.type === 'leave_approved' || n.type === 'leave_rejected').length) - 6 }} notifikasi lainnya...
                                </button>
                            </div>
                            
                            <!-- Tombol untuk melihat semua approval yang pending -->
                            <div v-if="(pendingApprovals.length > 0 || pendingHrdApprovals.length > 0)" class="text-center pt-2">
                                <button @click="showAllPendingApprovals" class="text-sm bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 transition-colors">
                                    <i class="fa-solid fa-list-check mr-1"></i>
                                    Lihat Semua Approval ({{ pendingApprovals.length + pendingHrdApprovals.length }})
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section: Clock, Weather, and Announcements -->
                <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-4 min-h-0">
                    <!-- Left: Clock and Weather -->
                    <div class="lg:col-span-1 flex flex-col gap-4">
                        <!-- Clock Card -->
                        <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 hover:shadow-3xl"
                            :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                            <div class="flex justify-center">
                                <AnalogClock :date="time" class="scale-100 md:scale-110 animate-slide-in" />
                            </div>
                        </div>
                        
                        <!-- Weather Card -->
                        <div v-if="weather.city && weather.code" class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                            :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                            <div class="flex flex-col items-center gap-2">
                                <div class="flex items-center gap-2">
                                    <WeatherIcon :code="weather.code" />
                                </div>
                                <div class="text-lg font-semibold" :class="isNight ? 'text-white' : 'text-slate-800'">{{ weather.city }}</div>
                                <div class="text-sm" :class="isNight ? 'text-slate-300' : 'text-slate-600'">{{ weather.temp }} - {{ weather.desc }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Announcements -->
                    <div class="lg:col-span-2">
                        <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 h-full transition-all duration-500 hover:shadow-3xl"
                            :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                            <AnnouncementList :is-night="isNight" @show-all="showAllAnnouncements" />
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex-shrink-0 mt-4 text-center text-xs opacity-90" :class="isNight ? 'text-indigo-300' : 'text-indigo-500'">
                    {{ t('home.powered') }} &copy; {{ new Date().getFullYear() }}
                </div>
            </div>
        </div>

        <!-- Approval Detail Modal -->
        <div v-if="showApprovalModal && selectedApproval" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showApprovalModal = false"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                Detail Permohonan Izin/Cuti
                            </h3>
                            <button @click="showApprovalModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <!-- Employee Info -->
                            <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded-lg">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Karyawan</div>
                                <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedApproval.user.nama_lengkap }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ selectedApproval.user.jabatan?.nama_jabatan || 'N/A' }}</div>
                            </div>

                            <!-- Leave Details -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Jenis Izin/Cuti</div>
                                    <div class="text-sm text-gray-900 dark:text-white">{{ selectedApproval.leave_type.name }}</div>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Durasi</div>
                                    <div class="text-sm text-gray-900 dark:text-white">{{ selectedApproval.duration_text }}</div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Mulai</div>
                                    <div class="text-sm text-gray-900 dark:text-white">{{ new Date(selectedApproval.date_from).toLocaleDateString('id-ID') }}</div>
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Tanggal Selesai</div>
                                    <div class="text-sm text-gray-900 dark:text-white">{{ new Date(selectedApproval.date_to).toLocaleDateString('id-ID') }}</div>
                                </div>
                            </div>

                            <!-- Reason -->
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Alasan</div>
                                <div class="text-sm text-gray-900 dark:text-white bg-gray-50 dark:bg-gray-700 p-2 rounded">{{ selectedApproval.reason }}</div>
                            </div>

                            <!-- Documents -->
                            <div v-if="(selectedApproval.document_paths && selectedApproval.document_paths.length > 0) || selectedApproval.document_path">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Dokumen Pendukung</div>
                                
                                <!-- Multiple Images Grid -->
                                <div v-if="selectedApproval.document_paths && selectedApproval.document_paths.length > 0" class="grid grid-cols-2 gap-3">
                                    <div 
                                        v-for="(docPath, index) in selectedApproval.document_paths" 
                                        :key="index"
                                        class="relative group"
                                    >
                                        <!-- Check if it's an image -->
                                        <div v-if="isImageFile(docPath)" class="relative cursor-pointer" @click="openImageModal(`/storage/${docPath}`, selectedApproval.document_paths)">
                                            <img 
                                                :src="`/storage/${docPath}`" 
                                                :alt="`Document ${index + 1}`"
                                                class="w-full h-32 object-cover rounded-lg border border-gray-300 dark:border-gray-600 hover:opacity-90 transition-opacity"
                                            >
                                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all rounded-lg flex items-center justify-center pointer-events-none">
                                                <i class="fa-solid fa-search-plus text-white opacity-0 group-hover:opacity-100 transition-opacity"></i>
                                            </div>
                                        </div>
                                        
                                        <!-- Non-image files -->
                                        <div v-else class="w-full h-32 bg-gray-100 dark:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600 flex items-center justify-center">
                                            <a :href="`/storage/${docPath}`" target="_blank" 
                                               class="text-center text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                <i class="fa-solid fa-file-pdf text-2xl mb-2"></i>
                                                <div class="text-xs">Lihat Dokumen</div>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Legacy single document support -->
                                <div v-else-if="selectedApproval.document_path" class="mt-2">
                                    <a :href="`/storage/${selectedApproval.document_path}`" target="_blank" 
                                       class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                        <i class="fa-solid fa-file-pdf mr-1"></i> Lihat Dokumen
                                    </a>
                                </div>
                            </div>

                            <!-- Approval Information -->
                            <div v-if="selectedApproval.status === 'approved' || selectedApproval.hrd_status === 'approved'">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Informasi Approval</div>
                                <div class="bg-green-50 dark:bg-green-900/20 p-3 rounded-lg space-y-2">
                                    <!-- Supervisor Approval -->
                                    <div v-if="selectedApproval.status === 'approved'">
                                        <div class="text-xs text-green-700 dark:text-green-300">
                                            <i class="fa-solid fa-check-circle mr-1"></i>
                                            Disetujui oleh Atasan: <strong>{{ selectedApproval.approver?.nama_lengkap }}</strong>
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ selectedApproval.approved_at ? new Date(selectedApproval.approved_at).toLocaleString('id-ID') : '' }}
                                        </div>
                                        <div v-if="selectedApproval.approval_notes" class="text-xs text-gray-600 dark:text-gray-400">
                                            Catatan: {{ selectedApproval.approval_notes }}
                                        </div>
                                    </div>
                                    
                                    <!-- HRD Approval -->
                                    <div v-if="selectedApproval.hrd_status === 'approved'">
                                        <div class="text-xs text-green-700 dark:text-green-300">
                                            <i class="fa-solid fa-check-circle mr-1"></i>
                                            Disetujui oleh HRD: <strong>{{ selectedApproval.hrd_approver?.nama_lengkap }}</strong>
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ selectedApproval.hrd_approved_at ? new Date(selectedApproval.hrd_approved_at).toLocaleString('id-ID') : '' }}
                                        </div>
                                        <div v-if="selectedApproval.hrd_approval_notes" class="text-xs text-gray-600 dark:text-gray-400">
                                            Catatan: {{ selectedApproval.hrd_approval_notes }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Rejection Information -->
                            <div v-if="selectedApproval.status === 'rejected' || selectedApproval.hrd_status === 'rejected'">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Informasi Penolakan</div>
                                <div class="bg-red-50 dark:bg-red-900/20 p-3 rounded-lg space-y-2">
                                    <!-- Supervisor Rejection -->
                                    <div v-if="selectedApproval.status === 'rejected'">
                                        <div class="text-xs text-red-700 dark:text-red-300">
                                            <i class="fa-solid fa-times-circle mr-1"></i>
                                            Ditolak oleh Atasan: <strong>{{ selectedApproval.approver?.nama_lengkap }}</strong>
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ selectedApproval.rejected_at ? new Date(selectedApproval.rejected_at).toLocaleString('id-ID') : '' }}
                                        </div>
                                        <div v-if="selectedApproval.approval_notes" class="text-xs text-gray-600 dark:text-gray-400">
                                            Alasan: {{ selectedApproval.approval_notes }}
                                        </div>
                                    </div>
                                    
                                    <!-- HRD Rejection -->
                                    <div v-if="selectedApproval.hrd_status === 'rejected'">
                                        <div class="text-xs text-red-700 dark:text-red-300">
                                            <i class="fa-solid fa-times-circle mr-1"></i>
                                            Ditolak oleh HRD: <strong>{{ selectedApproval.hrd_approver?.nama_lengkap }}</strong>
                                        </div>
                                        <div class="text-xs text-gray-600 dark:text-gray-400">
                                            {{ selectedApproval.hrd_rejected_at ? new Date(selectedApproval.hrd_rejected_at).toLocaleString('id-ID') : '' }}
                                        </div>
                                        <div v-if="selectedApproval.hrd_approval_notes" class="text-xs text-gray-600 dark:text-gray-400">
                                            Alasan: {{ selectedApproval.hrd_approval_notes }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Actions -->
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <!-- HRD Actions -->
                        <template v-if="user.division_id === 6 && selectedApproval.status === 'supervisor_approved'">
                            <button @click="hrdRejectRequest(selectedApproval.id)" 
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Tolak HRD
                            </button>
                            <button @click="hrdApproveRequest(selectedApproval.id)" 
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Setujui HRD
                            </button>
                        </template>
                        <!-- Supervisor Actions -->
                        <template v-else-if="selectedApproval.status === 'pending'">
                            <button @click="rejectRequest(selectedApproval.id)" 
                                    class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Tolak
                            </button>
                            <button @click="approveRequest(selectedApproval.id)" 
                                    class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Setujui
                            </button>
                        </template>
                        <button @click="showApprovalModal = false" 
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcements Modal -->
        <div v-if="showAnnouncementsModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeAnnouncementsModal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                Semua Pengumuman
                            </h3>
                            <button @click="closeAnnouncementsModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="fa-solid fa-times text-xl"></i>
                            </button>
                        </div>

                        <!-- Filters -->
                        <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <!-- Search -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cari</label>
                                    <input 
                                        v-model="announcementsFilters.search"
                                        type="text" 
                                        placeholder="Cari pengumuman..."
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-600 dark:text-gray-100"
                                    />
                                </div>

                                <!-- Target -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Target</label>
                                    <select 
                                        v-model="announcementsFilters.target"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-600 dark:text-gray-100"
                                    >
                                        <option value="">Semua Target</option>
                                        <option value="all">Semua Karyawan</option>
                                        <option value="outlet">Outlet Tertentu</option>
                                        <option value="division">Divisi Tertentu</option>
                                        <option value="level">Level Tertentu</option>
                                    </select>
                                </div>

                                <!-- Date From -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dari Tanggal</label>
                                    <input 
                                        v-model="announcementsFilters.date_from"
                                        type="date" 
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-600 dark:text-gray-100"
                                    />
                                </div>

                                <!-- Date To -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sampai Tanggal</label>
                                    <input 
                                        v-model="announcementsFilters.date_to"
                                        type="date" 
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-600 dark:text-gray-100"
                                    />
                                </div>
                            </div>

                            <!-- Filter Actions -->
                            <div class="flex justify-end gap-2 mt-4">
                                <button 
                                    @click="clearFilters"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 rounded-md transition-colors"
                                >
                                    Reset Filter
                                </button>
                                <button 
                                    @click="applyFilters"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors"
                                >
                                    Terapkan Filter
                                </button>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-if="loadingAnnouncements" class="text-center py-8">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                            <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat pengumuman...</p>
                        </div>

                        <!-- Empty State -->
                        <div v-else-if="announcements.length === 0" class="text-center py-8">
                            <div class="mb-4 text-gray-400 dark:text-gray-500">
                                <i class="fas fa-bullhorn text-4xl"></i>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">Tidak ada pengumuman yang ditemukan</p>
                        </div>

                        <!-- Announcements List -->
                        <div v-else class="space-y-4 max-h-96 overflow-y-auto">
                            <div 
                                v-for="announcement in announcements" 
                                :key="announcement.id"
                                class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer"
                                @click="viewAnnouncement(announcement.id)"
                            >
                                <!-- Header -->
                                <div class="flex items-start justify-between mb-2">
                                    <h4 class="font-semibold text-gray-900 dark:text-white flex-1 mr-4">
                                        {{ announcement.title }}
                                    </h4>
                                    <span class="text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                        {{ announcement.created_at_formatted }}
                                    </span>
                                </div>

                                <!-- Content -->
                                <p v-if="announcement.content" class="text-sm text-gray-600 dark:text-gray-300 mb-3 line-clamp-3">
                                    {{ announcement.content }}
                                </p>

                                <!-- Image -->
                                <div v-if="announcement.image_path" class="mb-3">
                                    <img 
                                        :src="`/storage/${announcement.image_path}`" 
                                        :alt="announcement.title"
                                        class="w-full h-32 object-cover rounded-lg"
                                    />
                                </div>

                                <!-- Files -->
                                <div v-if="announcement.files && announcement.files.length > 0" class="flex items-center gap-2 mb-3">
                                    <i class="fas fa-paperclip text-gray-400"></i>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ announcement.files.length }} file terlampir
                                    </span>
                                </div>

                                <!-- Target Info -->
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-users text-gray-400"></i>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ getTargetNames(announcement.targets) }}
                                        </span>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-400"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div v-if="announcementsPagination.last_page > 1" class="mt-6 flex items-center justify-between">
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                Menampilkan {{ (announcementsPagination.current_page - 1) * announcementsPagination.per_page + 1 }} 
                                sampai {{ Math.min(announcementsPagination.current_page * announcementsPagination.per_page, announcementsPagination.total) }} 
                                dari {{ announcementsPagination.total }} pengumuman
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <!-- Previous Button -->
                                <button 
                                    @click="changePage(announcementsPagination.current_page - 1)"
                                    :disabled="announcementsPagination.current_page <= 1"
                                    class="px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Sebelumnya
                                </button>

                                <!-- Page Numbers -->
                                <div class="flex items-center gap-1">
                                    <button 
                                        v-for="page in Math.min(5, announcementsPagination.last_page)" 
                                        :key="page"
                                        @click="changePage(page)"
                                        :class="[
                                            'px-3 py-2 text-sm font-medium rounded-md',
                                            page === announcementsPagination.current_page 
                                                ? 'bg-blue-600 text-white' 
                                                : 'text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600'
                                        ]"
                                    >
                                        {{ page }}
                                    </button>
                                </div>

                                <!-- Next Button -->
                                <button 
                                    @click="changePage(announcementsPagination.current_page + 1)"
                                    :disabled="announcementsPagination.current_page >= announcementsPagination.last_page"
                                    class="px-3 py-2 text-sm font-medium text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Selanjutnya
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Lightbox for Document Images -->
    <VueEasyLightbox
        :visible="lightboxVisible"
        :imgs="lightboxImages"
        :index="lightboxIndex"
        @hide="lightboxVisible = false"
    />
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 1s ease;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-slide-in {
    animation: slideIn 1s cubic-bezier(.4,2,.6,1) 0.2s both;
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(-40px) scale(0.8); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* Enhanced shadow effects */
.hover\:shadow-3xl:hover {
    box-shadow: 0 35px 60px -12px rgba(0, 0, 0, 0.25);
}

/* Glass morphism effect */
.backdrop-blur-md {
    backdrop-filter: blur(12px);
}

/* Gradient text effect */
.gradient-text {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Subtle glow effect */
.glow-effect {
    box-shadow: 0 0 20px rgba(99, 102, 241, 0.1);
}

/* Smooth transitions */
* {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Line clamp utilities */
.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

</style> 