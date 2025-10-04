<script setup>
import { ref, computed, onMounted, watch, nextTick, onBeforeUnmount } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AnalogClock from '@/Components/AnalogClock.vue';
import CalendarWidget from '@/Components/CalendarWidget.vue';
import NotesWidget from '@/Components/NotesWidget.vue';
import BirthdayWidget from '@/Components/BirthdayWidget.vue';
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

// Purchase Requisition approvals
const pendingPrApprovals = ref([]);
const loadingPrApprovals = ref(false);
const showPrApprovalModal = ref(false);
const selectedPrApproval = ref(null);
const prApprovalBudgetInfo = ref(null);

// Purchase Order Ops approvals
const pendingPoOpsApprovals = ref([]);
const loadingPoOpsApprovals = ref(false);
const showPoOpsApprovalModal = ref(false);
const selectedPoOpsApproval = ref(null);
const poOpsApprovalBudgetInfo = ref(null);

// General notifications
const leaveNotifications = ref([]);
const loadingNotifications = ref(false);

// HRD approvals
const pendingHrdApprovals = ref([]);
const loadingHrdApprovals = ref(false);

// Correction approvals
const pendingCorrectionApprovals = ref([]);
const loadingCorrectionApprovals = ref(false);

// All approvals modal
const showAllApprovalsModal = ref(false);
const allApprovals = ref([]);
const loadingAllApprovals = ref(false);

// Training invitations
const trainingInvitations = ref([]);
const loadingTrainingInvitations = ref(false);

// Available trainings
const availableTrainings = ref([]);
const loadingAvailableTrainings = ref(false);
const showAvailableTrainingsModal = ref(false);

// Active sanctions
const activeSanctions = ref([]);
const loadingSanctions = ref(false);

// Coaching approvals
const pendingCoachingApprovals = ref([]);
const loadingCoachingApprovals = ref(false);

// Training detail modal
const showTrainingDetailModal = ref(false);
const selectedTrainingDetail = ref(null);
const refreshingTrainingDetail = ref(false);

// Training check-in QR scanner
const showTrainingCheckInModal = ref(false);
const showCamera = ref(false);
const qrCodeInput = ref('');
const isProcessingCheckIn = ref(false);
const checkInStatusMessage = ref('');
let html5QrCode = null;
const cameras = ref([]);
const selectedCameraId = ref('');

// Training check-out QR scanner
const showTrainingCheckOutModal = ref(false);
const showCheckOutCamera = ref(false);
const qrCodeCheckOutInput = ref('');
const isProcessingCheckOut = ref(false);
const checkOutStatusMessage = ref('');
let html5QrCodeCheckOut = null;
const checkOutCameras = ref([]);
const selectedCheckOutCameraId = ref('');

// Training review modal
const showTrainingReviewModal = ref(false);
const isSubmittingReview = ref(false);
const reviewForm = ref({
    trainer_id: null,
    // Trainer ratings
    trainer_mastery: 5,
    trainer_language: 5,
    trainer_intonation: 5,
    trainer_presentation: 5,
    trainer_qna: 5,
    // Training material ratings
    material_benefit: 5,
    material_clarity: 5,
    material_display: 5,
    material_suggestions: '',
    material_needs: ''
});

// Training history modal
const showTrainingHistoryModal = ref(false);
const trainingHistory = ref([]);
const loadingTrainingHistory = ref(false);

// Computed property for development mode
const isDevelopment = computed(() => {
    return import.meta.env.DEV;
});

// Computed property to check if all session items are completed
const allSessionItemsCompleted = computed(() => {
    if (!selectedTrainingDetail.value || !selectedTrainingDetail.value.sessions) {
        return false;
    }
    
    // Check if all sessions have all their items completed
    return selectedTrainingDetail.value.sessions.every(session => {
        if (!session.items || session.items.length === 0) {
            return true; // No items means completed
        }
        
        // Check if all items in this session are completed
        return session.items.every(item => {
            return item.is_completed && item.completion_status;
        });
    });
});


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

// Check if user is a trainer
const isTrainer = computed(() => {
    const jabatan = userJabatan.value.toLowerCase();
    return jabatan.includes('trainer') || jabatan.includes('instruktur') || jabatan.includes('pengajar');
});
const totalNotificationsCount = computed(() => {
    const approvalCount = pendingApprovals.value.length;
    const hrdApprovalCount = pendingHrdApprovals.value.length;
    const correctionApprovalCount = pendingCorrectionApprovals.value.length;
    const leaveNotificationCount = leaveNotifications.value.filter(n => !n.is_read && (n.type === 'leave_approved' || n.type === 'leave_rejected')).length;
    return approvalCount + hrdApprovalCount + correctionApprovalCount + leaveNotificationCount;
});

const prApprovalCount = computed(() => {
    return pendingPrApprovals.value.length;
});

const poOpsApprovalCount = computed(() => {
    return pendingPoOpsApprovals.value.length;
});

const availableTrainingsStats = computed(() => {
    const total = availableTrainings.value.length;
    const completed = availableTrainings.value.filter(t => t.is_completed).length;
    const invited = availableTrainings.value.filter(t => t.participation_status === 'invited').length;
    const available = availableTrainings.value.filter(t => t.participation_status === 'available').length;
    
    return {
        total,
        completed,
        invited,
        available,
        completionRate: total > 0 ? Math.round((completed / total) * 100) : 0
    };
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

// Load Purchase Requisition approvals
async function loadPendingPrApprovals() {
    loadingPrApprovals.value = true;
    try {
        const response = await axios.get('/api/purchase-requisitions/pending-approvals');
        if (response.data.success) {
            pendingPrApprovals.value = response.data.purchase_requisitions;
        }
    } catch (error) {
        console.error('Error loading pending PR approvals:', error);
    } finally {
        loadingPrApprovals.value = false;
    }
}

// Load Purchase Order Ops approvals
async function loadPendingPoOpsApprovals() {
    loadingPoOpsApprovals.value = true;
    try {
        const response = await axios.get('/po-ops/pending-approvals');
        if (response.data.success) {
            pendingPoOpsApprovals.value = response.data.data;
        }
    } catch (error) {
        console.error('Error loading pending PO Ops approvals:', error);
    } finally {
        loadingPoOpsApprovals.value = false;
    }
}

// Load active sanctions
async function loadActiveSanctions() {
    loadingSanctions.value = true;
    try {
        const response = await axios.get('/api/coaching/user-sanctions');
        if (response.data.success) {
            activeSanctions.value = response.data.active_sanctions;
        }
    } catch (error) {
        console.error('Error loading active sanctions:', error);
    } finally {
        loadingSanctions.value = false;
    }
}

// Load coaching approvals
async function loadCoachingApprovals() {
    loadingCoachingApprovals.value = true;
    try {
        const response = await axios.get('/api/coaching/pending-approvals');
        console.log('Coaching approvals response:', response.data);
        if (response.data.success) {
            pendingCoachingApprovals.value = response.data.pending_approvals;
            console.log('Pending coaching approvals:', pendingCoachingApprovals.value);
        }
    } catch (error) {
        console.error('Error loading coaching approvals:', error);
    } finally {
        loadingCoachingApprovals.value = false;
    }
}

// Approve coaching
async function approveCoaching(coachingId, approverId) {
    try {
        const response = await axios.post(`/coaching/${coachingId}/approve`, {
            approver_id: approverId,
            comments: ''
        });
        
        if (response.data.message) {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: response.data.message,
                timer: 2000,
                showConfirmButton: false
            });
            
            // Reload approvals
            loadCoachingApprovals();
        }
    } catch (error) {
        console.error('Error approving coaching:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Gagal menyetujui coaching'
        });
    }
}

// Reject coaching
async function rejectCoaching(coachingId, approverId) {
    const { value: comments } = await Swal.fire({
        title: 'Tolak Coaching',
        input: 'textarea',
        inputLabel: 'Alasan Penolakan',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        inputAttributes: {
            'aria-label': 'Masukkan alasan penolakan'
        },
        showCancelButton: true,
        confirmButtonText: 'Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc3545',
        inputValidator: (value) => {
            if (!value) {
                return 'Alasan penolakan harus diisi!'
            }
        }
    });

    if (comments) {
        try {
            const response = await axios.post(`/coaching/${coachingId}/reject`, {
                approver_id: approverId,
                comments: comments
            });
            
            if (response.data.message) {
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil',
                    text: response.data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
                
                // Reload approvals
                loadCoachingApprovals();
            }
        } catch (error) {
            console.error('Error rejecting coaching:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Gagal menolak coaching'
            });
        }
    }
}

// Show PR approval details
async function showPrApprovalDetails(prId) {
    try {
        const response = await axios.get(`/api/purchase-requisitions/${prId}/approval-details`);
        if (response.data.success) {
            selectedPrApproval.value = response.data.purchase_requisition;
            prApprovalBudgetInfo.value = response.data.budget_info;
            
            // Debug logging
            console.log('PR Approval Details:', response.data.purchase_requisition);
            console.log('Outlet data:', response.data.purchase_requisition.outlet);
            console.log('Outlet name:', response.data.purchase_requisition.outlet?.nama_outlet);
            
            showPrApprovalModal.value = true;
        }
    } catch (error) {
        console.error('Error loading PR approval details:', error);
        Swal.fire('Error', 'Gagal memuat detail Purchase Requisition', 'error');
    }
}

// Approve PR
async function approvePr(prId) {
    try {
        const response = await axios.post(`/purchase-requisitions/${prId}/approve`);
        if (response.status === 200) {
            Swal.fire('Success', 'Purchase Requisition berhasil disetujui', 'success');
            showPrApprovalModal.value = false;
            loadPendingPrApprovals(); // Reload the list
        }
    } catch (error) {
        console.error('Error approving PR:', error);
        Swal.fire('Error', 'Gagal menyetujui Purchase Requisition', 'error');
    }
}

// Reject PR
async function rejectPr(prId, reason) {
    try {
        const response = await axios.post(`/purchase-requisitions/${prId}/reject`, {
            rejection_reason: reason
        });
        if (response.status === 200) {
            Swal.fire('Success', 'Purchase Requisition berhasil ditolak', 'success');
            showPrApprovalModal.value = false;
            loadPendingPrApprovals(); // Reload the list
        }
    } catch (error) {
        console.error('Error rejecting PR:', error);
        Swal.fire('Error', 'Gagal menolak Purchase Requisition', 'error');
    }
}

// Show reject PR modal
function showRejectPrModal(prId) {
    Swal.fire({
        title: 'Tolak Purchase Requisition',
        input: 'textarea',
        inputLabel: 'Alasan Penolakan',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        inputAttributes: {
            'aria-label': 'Masukkan alasan penolakan'
        },
        showCancelButton: true,
        confirmButtonText: 'Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        inputValidator: (value) => {
            if (!value) {
                return 'Alasan penolakan harus diisi!'
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            rejectPr(prId, result.value);
        }
    });
}

// Get status color for PO Ops
function getStatusColor(status) {
    const colors = {
        'draft': 'bg-gray-100 text-gray-800',
        'submitted': 'bg-yellow-100 text-yellow-800',
        'approved': 'bg-green-100 text-green-800',
        'rejected': 'bg-red-100 text-red-800',
        'received': 'bg-blue-100 text-blue-800',
        'cancelled': 'bg-gray-100 text-gray-800',
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}

// Show PO Ops approval details
async function showPoOpsApprovalDetails(poId) {
    console.log('showPoOpsApprovalDetails called with poId:', poId);
    try {
        const response = await axios.get(`/po-ops/${poId}`);
        console.log('PO Ops response:', response.data);
        if (response.data) {
            selectedPoOpsApproval.value = response.data.po;
            
            // Load budget info if PO has source PR
            if (response.data.po.source_type === 'purchase_requisition_ops' && response.data.po.source_id) {
                try {
                    const budgetResponse = await axios.get(`/api/purchase-requisitions/${response.data.po.source_id}/approval-details`);
                    if (budgetResponse.data.success && budgetResponse.data.budget_info) {
                        poOpsApprovalBudgetInfo.value = budgetResponse.data.budget_info;
                    }
                } catch (budgetError) {
                    console.log('No budget info available:', budgetError);
                }
            }
            
            showPoOpsApprovalModal.value = true;
            console.log('Modal should be visible now');
        }
    } catch (error) {
        console.error('Error loading PO Ops approval details:', error);
        Swal.fire('Error', 'Gagal memuat detail Purchase Order Ops', 'error');
    }
}

// Approve PO Ops
async function approvePoOps(poId) {
    try {
        const response = await axios.post(`/po-ops/${poId}/approve`, {
            approved: true,
            comments: ''
        });
        if (response.data.success) {
            Swal.fire('Success', 'Purchase Order Ops berhasil disetujui', 'success');
            showPoOpsApprovalModal.value = false;
            loadPendingPoOpsApprovals(); // Reload the list
        }
    } catch (error) {
        console.error('Error approving PO Ops:', error);
        Swal.fire('Error', 'Gagal menyetujui Purchase Order Ops', 'error');
    }
}

// Reject PO Ops
async function rejectPoOps(poId, reason) {
    try {
        const response = await axios.post(`/po-ops/${poId}/approve`, {
            approved: false,
            comments: reason
        });
        if (response.data.success) {
            Swal.fire('Success', 'Purchase Order Ops berhasil ditolak', 'success');
            showPoOpsApprovalModal.value = false;
            loadPendingPoOpsApprovals(); // Reload the list
        }
    } catch (error) {
        console.error('Error rejecting PO Ops:', error);
        Swal.fire('Error', 'Gagal menolak Purchase Order Ops', 'error');
    }
}

// Show reject PO Ops modal
function showRejectPoOpsModal(poId) {
    Swal.fire({
        title: 'Tolak Purchase Order Ops',
        input: 'textarea',
        inputLabel: 'Alasan Penolakan',
        inputPlaceholder: 'Masukkan alasan penolakan...',
        inputAttributes: {
            'aria-label': 'Masukkan alasan penolakan'
        },
        showCancelButton: true,
        confirmButtonText: 'Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        inputValidator: (value) => {
            if (!value) {
                return 'Alasan penolakan harus diisi!'
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            rejectPoOps(poId, result.value);
        }
    });
}

// Helper functions for modal
function getMonthName(monthNumber) {
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ]
    return months[monthNumber - 1] || 'Unknown'
}

// Helper functions for sanctions
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function isSanctionActive(sanction) {
    if (!sanction || !sanction.effective_date || !sanction.end_date) {
        console.log('Sanction not active - missing data:', sanction);
        return false;
    }
    
    const currentDate = new Date().toISOString().split('T')[0];
    const isActive = sanction.effective_date <= currentDate && sanction.end_date >= currentDate;
    console.log('Checking sanction:', sanction.name, 'from', sanction.effective_date, 'to', sanction.end_date, 'current:', currentDate, 'active:', isActive);
    return isActive;
}

function getBudgetProgressColor(usedAmount, totalBudget) {
    const percentage = (usedAmount / totalBudget) * 100
    if (percentage >= 100) return 'bg-red-500'
    if (percentage >= 80) return 'bg-yellow-500'
    if (percentage >= 60) return 'bg-orange-500'
    return 'bg-green-500'
}

function getApprovalFlowClass(status) {
    switch (status) {
        case 'APPROVED':
            return 'bg-green-50 border-green-200 dark:bg-green-900/20 dark:border-green-700'
        case 'REJECTED':
            return 'bg-red-50 border-red-200 dark:bg-red-900/20 dark:border-red-700'
        case 'PENDING':
            return 'bg-yellow-50 border-yellow-200 dark:bg-yellow-900/20 dark:border-yellow-700'
        default:
            return 'bg-gray-50 border-gray-200 dark:bg-gray-700 dark:border-gray-600'
    }
}

function getApprovalStatusTextClass(status) {
    switch (status) {
        case 'APPROVED':
            return 'text-green-600 dark:text-green-400'
        case 'REJECTED':
            return 'text-red-600 dark:text-red-400'
        case 'PENDING':
            return 'text-yellow-600 dark:text-yellow-400'
        default:
            return 'text-gray-600 dark:text-gray-400'
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

// All approvals modal functions
async function loadAllApprovals() {
    loadingAllApprovals.value = true;
    try {
        // Load all types of approvals
        const [approvalsResponse, hrdResponse, correctionResponse] = await Promise.all([
            axios.get('/api/approval/pending'),
            user.division_id === 6 ? axios.get('/api/approval/pending-hrd') : Promise.resolve({ data: { success: true, approvals: [] } }),
            user.division_id === 6 ? axios.get('/api/schedule-attendance-correction/pending-approvals') : Promise.resolve({ data: { success: true, approvals: [] } })
        ]);

        const allApprovalsData = [];
        
        // Add leave approvals
        if (approvalsResponse.data.success) {
            approvalsResponse.data.approvals.forEach(approval => {
                allApprovalsData.push({
                    ...approval,
                    type: 'leave',
                    typeLabel: 'Izin/Cuti'
                });
            });
        }

        // Add HRD approvals
        if (hrdResponse.data.success) {
            hrdResponse.data.approvals.forEach(approval => {
                allApprovalsData.push({
                    ...approval,
                    type: 'hrd_leave',
                    typeLabel: 'Izin/Cuti HRD'
                });
            });
        }

        // Add correction approvals
        if (correctionResponse.data.success) {
            correctionResponse.data.approvals.forEach(approval => {
                allApprovalsData.push({
                    ...approval,
                    type: 'correction',
                    typeLabel: approval.type === 'schedule' ? 'Koreksi Schedule' : 'Koreksi Attendance'
                });
            });
        }

        // Sort by created date
        allApprovalsData.sort((a, b) => new Date(b.created_at || b.tanggal) - new Date(a.created_at || a.tanggal));
        
        allApprovals.value = allApprovalsData;
    } catch (error) {
        console.error('Error loading all approvals:', error);
    } finally {
        loadingAllApprovals.value = false;
    }
}

function showAllApprovals() {
    showAllApprovalsModal.value = true;
    loadAllApprovals();
}

// Handle approval actions from modal
async function handleApprovalAction(approval) {
    if (approval.type === 'leave') {
        await showApprovalDetails(approval.id);
    } else if (approval.type === 'hrd_leave') {
        await showApprovalDetails(approval.id);
    } else if (approval.type === 'correction') {
        // For correction approvals, directly approve
        await approveCorrection(approval.id);
        // Reload all approvals after action
        await loadAllApprovals();
    }
}

async function handleRejectionAction(approval) {
    if (approval.type === 'leave') {
        await rejectRequest(approval.id);
    } else if (approval.type === 'hrd_leave') {
        await hrdRejectRequest(approval.id);
    } else if (approval.type === 'correction') {
        await rejectCorrection(approval.id);
    }
    
    // Reload all approvals after action
    await loadAllApprovals();
}

// Training invitation functions
async function loadTrainingInvitations() {
    loadingTrainingInvitations.value = true;
    try {
        const response = await axios.get('/lms/training-notifications');
        if (response.data.success) {
            trainingInvitations.value = response.data.notifications;
        }
    } catch (error) {
        console.error('Error loading training invitations:', error);
    } finally {
        loadingTrainingInvitations.value = false;
    }
}

// Available trainings functions
async function loadAvailableTrainings() {
    loadingAvailableTrainings.value = true;
    try {
        console.log('Loading available trainings...');
        const response = await axios.get('/lms/available-trainings');
        console.log('Available trainings response:', response.data);
        if (response.data.success) {
            availableTrainings.value = response.data.courses;
        } else {
            console.error('API returned success: false', response.data);
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: response.data.message || 'Gagal memuat training yang tersedia'
            });
        }
    } catch (error) {
        console.error('Error loading available trainings:', error);
        console.error('Error response:', error.response?.data);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: error.response?.data?.message || 'Gagal memuat training yang tersedia'
        });
    } finally {
        loadingAvailableTrainings.value = false;
    }
}

function openAvailableTrainingsModal() {
    showAvailableTrainingsModal.value = true;
    if (availableTrainings.value.length === 0) {
        loadAvailableTrainings();
    }
}

function closeAvailableTrainingsModal() {
    showAvailableTrainingsModal.value = false;
}

function getTrainingStatusBadge(training) {
    if (training.is_completed) {
        return {
            text: 'Selesai',
            class: 'bg-green-100 text-green-800 border-green-200',
            icon: 'fa-check-circle'
        };
    } else if (training.participation_status === 'invited') {
        const hasCheckedIn = training.current_invitations.some(inv => inv.is_checked_in);
        const hasCheckedOut = training.current_invitations.some(inv => inv.is_checked_out);
        
        if (hasCheckedOut) {
            return {
                text: 'Selesai',
                class: 'bg-green-100 text-green-800 border-green-200',
                icon: 'fa-check-circle'
            };
        } else if (hasCheckedIn) {
            return {
                text: 'Sedang Berlangsung',
                class: 'bg-blue-100 text-blue-800 border-blue-200',
                icon: 'fa-play-circle'
            };
        } else {
            return {
                text: 'Diundang',
                class: 'bg-yellow-100 text-yellow-800 border-yellow-200',
                icon: 'fa-clock'
            };
        }
    } else {
        return {
            text: 'Tersedia',
            class: 'bg-gray-100 text-gray-800 border-gray-200',
            icon: 'fa-book'
        };
    }
}

function getTargetDisplayText(training) {
    const targets = [];
    
    if (training.target_info.type === 'all') {
        targets.push('Semua Karyawan');
    } else {
        if (training.target_info.divisions.length > 0) {
            targets.push(`Divisi: ${training.target_info.divisions.join(', ')}`);
        }
        if (training.target_info.jabatans.length > 0) {
            targets.push(`Jabatan: ${training.target_info.jabatans.join(', ')}`);
        }
        if (training.target_info.outlets.length > 0) {
            targets.push(`Outlet: ${training.target_info.outlets.join(', ')}`);
        }
    }
    
    return targets.length > 0 ? targets.join(' • ') : 'Tidak ada target spesifik';
}

// Training history functions
async function loadTrainingHistory() {
    loadingTrainingHistory.value = true;
    try {
        const response = await axios.get('/lms/training-history');
        if (response.data.success) {
            trainingHistory.value = response.data.completed_trainings;
        }
    } catch (error) {
        console.error('Error loading training history:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Gagal memuat riwayat training'
        });
    } finally {
        loadingTrainingHistory.value = false;
    }
}

function openTrainingHistoryModal() {
    showTrainingHistoryModal.value = true;
    loadTrainingHistory();
}

function closeTrainingHistoryModal() {
    showTrainingHistoryModal.value = false;
    trainingHistory.value = [];
}

// Certificate functions
const showCertificateModal = ref(false);
const selectedCertificate = ref(null);

function previewCertificate(certificate) {
    selectedCertificate.value = certificate;
    showCertificateModal.value = true;
}

function downloadCertificate(certificate) {
    // Download certificate PDF
    const downloadUrl = route('lms.certificates.download', certificate.id);
    window.open(downloadUrl, '_blank');
}

function closeCertificateModal() {
    showCertificateModal.value = false;
    selectedCertificate.value = null;
}

function handleTrainingInvitationClick(invitation) {
    // Set selected training detail and show modal
    selectedTrainingDetail.value = invitation;
    showTrainingDetailModal.value = true;
}


function closeTrainingDetailModal() {
    showTrainingDetailModal.value = false;
    selectedTrainingDetail.value = null;
}

// Function to refresh training detail data
async function refreshTrainingDetail() {
    if (!selectedTrainingDetail.value) return;
    
    refreshingTrainingDetail.value = true;
    
    try {
        // Reload training invitations to get fresh data
        await loadTrainingInvitations();
        
        // Find the updated invitation data
        const updatedInvitation = trainingInvitations.value.find(
            inv => inv.schedule_id === selectedTrainingDetail.value.schedule_id
        );
        
        if (updatedInvitation) {
            // Update selected training detail with fresh data
            selectedTrainingDetail.value = updatedInvitation;
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Data Diperbarui!',
                text: 'Data training berhasil diperbarui',
                timer: 2000,
                showConfirmButton: false
            });
        }
    } catch (error) {
        console.error('Error refreshing training detail:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Gagal memperbarui data training'
        });
    } finally {
        refreshingTrainingDetail.value = false;
    }
}

// Handle session item click
function handleSessionItemClick(item, session) {
    if (!item.can_access) {
        return; // Don't do anything if item is not accessible
    }

    console.log('Session item clicked:', item, session);

    // Handle different item types
    switch (item.item_type) {
        case 'quiz':
            handleQuizItemClick(item, session);
            break;
        case 'material':
            handleMaterialItemClick(item, session);
            break;
        case 'activity':
            handleActivityItemClick(item, session);
            break;
        case 'questionnaire':
            handleQuestionnaireItemClick(item, session);
            break;
        default:
            console.log('Unknown item type:', item.item_type);
    }
}

// Handle quiz item click
async function handleQuizItemClick(item, session) {
    try {
        console.log('Quiz item clicked:', item);
        
        // Check if quiz is already completed
        if (item.is_completed && item.completion_status) {
            Swal.fire({
                icon: 'info',
                title: 'Quiz Sudah Selesai',
                html: `
                    <div class="text-left">
                        <p><strong>Score:</strong> ${item.completion_status.score}%</p>
                        <p><strong>Status:</strong> ${item.completion_status.is_passed ? 'Lulus' : 'Tidak Lulus'}</p>
                        <p><strong>Selesai:</strong> ${new Date(item.completion_status.completed_at).toLocaleString('id-ID')}</p>
                        <p><strong>Attempt:</strong> ${item.completion_status.attempt_number}</p>
                    </div>
                `,
                confirmButtonText: 'OK'
            });
            return;
        }
        
        console.log('Starting quiz attempt for item:', item);
        
        // Check if quiz data is available
        if (!item.quiz) {
            console.error('Quiz data not available for item:', item);
            
            // Try to start quiz attempt anyway using item_id
            console.log('Attempting to start quiz with item_id:', item.item_id);
        }

        // Start quiz attempt - use correct quiz ID
        const quizId = item.quiz ? item.quiz.id : (item.quiz_id || item.item_id);
        console.log('Using quiz ID:', quizId, 'from item:', item);
        console.log('Item structure:', {
            id: item.id,
            item_type: item.item_type,
            item_id: item.item_id,
            quiz_id: item.quiz_id,
            quiz: item.quiz
        });
        
        const response = await axios.post('/api/quiz/start-attempt', {
            quiz_id: quizId,
            schedule_id: selectedTrainingDetail.value?.schedule_id
        });

        if (response.data.attempt) {
            console.log('Quiz attempt started:', response.data.attempt);
            
            // Open quiz in new tab or redirect
            const quizUrl = `/lms/quiz/${quizId}/attempt/${response.data.attempt.id}`;
            window.open(quizUrl, '_blank');
        }
    } catch (error) {
        console.error('Error starting quiz attempt:', error);
        
        if (error.response?.data?.error) {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: error.response.data.error
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: 'Terjadi kesalahan saat memulai quiz'
            });
        }
    }
}

// Handle material item click
async function handleMaterialItemClick(item, session) {
    console.log('Material item clicked:', item);
    
    if (!item.material || !item.material.files || item.material.files.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Material Tidak Tersedia',
            text: 'File material belum tersedia atau belum diupload'
        });
        return;
    }
    
    // Get primary file or first file
    const primaryFile = item.material.primary_file || item.material.files[0];
    
    if (!primaryFile) {
        Swal.fire({
            icon: 'warning',
            title: 'File Tidak Tersedia',
            text: 'File material tidak ditemukan'
        });
        return;
    }
    
    console.log('Primary file:', primaryFile);
    
    // Mark material as completed when user clicks on it
    await markMaterialAsCompleted(item, session);
    
    // Handle different file types
    switch (primaryFile.file_type) {
        case 'pdf':
            openPdfViewer(primaryFile, item.material);
            break;
        case 'video':
            openVideoPlayer(primaryFile, item.material);
            break;
        case 'image':
            openImageViewer(primaryFile, item.material);
            break;
        case 'document':
        case 'docx':
        case 'doc':
        case 'xlsx':
        case 'xls':
        case 'pptx':
        case 'ppt':
            openDocumentViewer(primaryFile, item.material);
            break;
        case 'link':
            openLink(primaryFile, item.material);
            break;
        default:
            // Fallback: open in new tab
            window.open(primaryFile.file_url, '_blank');
            break;
    }
}

// Mark material as completed
async function markMaterialAsCompleted(item, session) {
    try {
        console.log('Marking material as completed:', {
            item_id: item.id,
            material_id: item.material.id,
            schedule_id: selectedTrainingDetail.value?.schedule_id,
            session_id: session.id
        });

        const response = await axios.post('/api/training/material/complete', {
            material_id: item.material.id,
            schedule_id: selectedTrainingDetail.value?.schedule_id,
            session_id: session.id,
            session_item_id: item.id,
            completion_data: {
                file_type: item.material.primary_file?.file_type || 'unknown',
                file_name: item.material.primary_file?.file_name || 'unknown',
                accessed_at: new Date().toISOString()
            }
        });

        if (response.data.success) {
            console.log('Material marked as completed successfully:', response.data);
            
            // Update the item's completion status in the UI
            item.is_completed = true;
            item.completion_status = {
                completed_at: response.data.data.completed_at,
                time_spent_seconds: response.data.data.time_spent_seconds
            };
            
            // Force reactivity update using nextTick
            await nextTick();
            
            // Force reactivity update
            console.log('Updated item completion status:', {
                item_id: item.id,
                is_completed: item.is_completed,
                completion_status: item.completion_status
            });
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Material Selesai!',
                text: 'Material berhasil ditandai sebagai selesai',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            console.error('Failed to mark material as completed:', response.data);
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: response.data.message || 'Gagal menandai material sebagai selesai',
                timer: 3000,
                showConfirmButton: false
            });
        }
    } catch (error) {
        console.error('Error marking material as completed:', error);
        
        // Show error message to user
        if (error.response && error.response.data && error.response.data.message) {
            Swal.fire({
                icon: 'error',
                title: 'Gagal Menandai Material',
                text: error.response.data.message,
                timer: 3000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Terjadi kesalahan saat menandai material sebagai selesai',
                timer: 3000,
                showConfirmButton: false
            });
        }
    }
}

// PDF Viewer
function openPdfViewer(file, material) {
    console.log('Opening PDF viewer for:', file);
    
    // Show options for PDF viewing
    Swal.fire({
        title: material.title,
        html: `
            <div class="text-center">
                <div class="mb-6">
                    <i class="fas fa-file-pdf text-8xl text-red-500 mb-4"></i>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">${file.file_name}</h3>
                    <div class="text-sm text-gray-600 space-y-1">
                        <p><strong>Size:</strong> ${file.file_size_formatted}</p>
                        <p><strong>Type:</strong> ${file.file_mime_type}</p>
                    </div>
                </div>
                <div class="space-y-3">
                    <p class="text-gray-600">Pilih cara untuk membuka PDF:</p>
                    <div class="flex flex-col space-y-2">
                        <button id="view-pdf" class="w-full bg-blue-500 text-white px-4 py-3 rounded-lg hover:bg-blue-600 transition-colors">
                            <i class="fas fa-eye mr-2"></i>Lihat PDF di Browser
                        </button>
                        <button id="download-pdf" class="w-full bg-green-500 text-white px-4 py-3 rounded-lg hover:bg-green-600 transition-colors">
                            <i class="fas fa-download mr-2"></i>Download PDF
                        </button>
                    </div>
                </div>
            </div>
        `,
        width: '500px',
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Batal',
        cancelButtonColor: '#6B7280',
        didOpen: () => {
            // Add event listeners for buttons
            document.getElementById('view-pdf').addEventListener('click', () => {
                Swal.close();
                // Use viewer_url for proper PDF viewing with headers
                const pdfUrl = file.viewer_url || file.file_url;
                window.open(pdfUrl, '_blank');
            });
            
            document.getElementById('download-pdf').addEventListener('click', () => {
                Swal.close();
                // Create download link
                const link = document.createElement('a');
                link.href = file.file_url;
                link.download = file.file_name;
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }
    });
}

// Video Player
function openVideoPlayer(file, material) {
    console.log('Opening video player for:', file);
    
    // Create modal for video player
    Swal.fire({
        title: material.title,
        html: `
            <div class="w-full">
                <video 
                    controls 
                    class="w-full rounded-lg"
                    style="max-height: 500px;"
                >
                    <source src="${file.viewer_url || file.file_url}" type="${file.file_mime_type}">
                    Browser Anda tidak mendukung video player.
                </video>
            </div>
            <div class="mt-4 text-sm text-gray-600">
                <p><strong>File:</strong> ${file.file_name}</p>
                <p><strong>Size:</strong> ${file.file_size_formatted}</p>
                <p><strong>Type:</strong> ${file.file_mime_type}</p>
            </div>
        `,
        width: '90%',
        showConfirmButton: true,
        confirmButtonText: 'Tutup',
        showCancelButton: true,
        cancelButtonText: 'Buka di Tab Baru',
        cancelButtonColor: '#3B82F6',
        confirmButtonColor: '#6B7280'
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
            window.open(file.file_url, '_blank');
        }
    });
}

// Image Viewer
function openImageViewer(file, material) {
    console.log('Opening image viewer for:', file);
    
    // Use existing lightbox functionality with viewer_url
    const imageUrl = file.viewer_url || file.file_url;
    openImageModal(imageUrl, [imageUrl]);
}

// Document Viewer
function openDocumentViewer(file, material) {
    console.log('Opening document viewer for:', file);
    
    // For documents, show info and provide download option
    Swal.fire({
        title: material.title,
        html: `
            <div class="text-center">
                <div class="mb-4">
                    <i class="fas fa-file-alt text-6xl text-blue-500"></i>
                </div>
                <div class="text-sm text-gray-600 space-y-2">
                    <p><strong>File:</strong> ${file.file_name}</p>
                    <p><strong>Size:</strong> ${file.file_size_formatted}</p>
                    <p><strong>Type:</strong> ${file.file_mime_type}</p>
                </div>
                <div class="mt-4">
                    <p class="text-gray-500">File dokumen akan dibuka di tab baru</p>
                </div>
            </div>
        `,
        showConfirmButton: true,
        confirmButtonText: 'Buka File',
        showCancelButton: true,
        cancelButtonText: 'Batal',
        confirmButtonColor: '#3B82F6',
        cancelButtonColor: '#6B7280'
    }).then((result) => {
        if (result.isConfirmed) {
            // Use viewer_url for proper routing through controller
            const url = file.viewer_url || file.file_url;
            console.log('Opening document URL:', url);
            window.open(url, '_blank');
        }
    });
}

// Link Handler
function openLink(file, material) {
    console.log('Opening link for:', file);
    
    // For links, show confirmation before opening
    Swal.fire({
        title: 'Buka Link',
        html: `
            <div class="text-center">
                <div class="mb-4">
                    <i class="fas fa-external-link-alt text-6xl text-green-500"></i>
                </div>
                <div class="text-sm text-gray-600 space-y-2">
                    <p><strong>Material:</strong> ${material.title}</p>
                    <p><strong>Link:</strong> ${file.file_name}</p>
                </div>
                <div class="mt-4">
                    <p class="text-gray-500">Link akan dibuka di tab baru</p>
                </div>
            </div>
        `,
        showConfirmButton: true,
        confirmButtonText: 'Buka Link',
        showCancelButton: true,
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280'
    }).then((result) => {
        if (result.isConfirmed) {
            window.open(file.file_url, '_blank');
        }
    });
}

// Handle activity item click
function handleActivityItemClick(item, session) {
    console.log('Activity item clicked:', item);
    // TODO: Implement activity handling
    Swal.fire({
        icon: 'info',
        title: 'Activity',
        text: 'Fitur activity akan segera tersedia'
    });
}

// Handle questionnaire item click
function handleQuestionnaireItemClick(item, session) {
    console.log('Questionnaire item clicked:', item);
    // TODO: Implement questionnaire handling
    Swal.fire({
        icon: 'info',
        title: 'Questionnaire',
        text: 'Fitur questionnaire akan segera tersedia'
    });
}

// Training Check-in Functions
function openTrainingCheckInModal() {
    showTrainingCheckInModal.value = true;
    qrCodeInput.value = '';
    checkInStatusMessage.value = '';
}

function closeTrainingCheckInModal() {
    showTrainingCheckInModal.value = false;
    showCamera.value = false;
    qrCodeInput.value = '';
    checkInStatusMessage.value = '';
    isProcessingCheckIn.value = false; // Reset processing state
    if (html5QrCode) {
        html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
    }
}

// Function to open first session item after successful check-in
function openFirstSessionItem() {
    if (!selectedTrainingDetail.value || !selectedTrainingDetail.value.sessions) {
        console.log('No training detail or sessions available');
        return;
    }

    // Find the first session with items
    const firstSession = selectedTrainingDetail.value.sessions.find(session => 
        session.items && session.items.length > 0
    );

    if (!firstSession) {
        console.log('No sessions with items found');
        return;
    }

    // Find the first accessible item in the first session
    const firstItem = firstSession.items.find(item => item.can_access);

    if (!firstItem) {
        console.log('No accessible items found in first session');
        return;
    }

    console.log('Opening first session item:', firstItem, 'from session:', firstSession);

    // Make sure training detail modal is open
    if (!showTrainingDetailModal.value) {
        showTrainingDetailModal.value = true;
    }

    // Wait a bit for modal to be ready, then trigger the first item
    setTimeout(() => {
        handleSessionItemClick(firstItem, firstSession);
    }, 500);
}

// Training Check-out Functions
function openTrainingCheckOutModal() {
    showTrainingCheckOutModal.value = true;
    qrCodeCheckOutInput.value = '';
    checkOutStatusMessage.value = '';
}

function closeTrainingCheckOutModal() {
    showTrainingCheckOutModal.value = false;
    showCheckOutCamera.value = false;
    qrCodeCheckOutInput.value = '';
    checkOutStatusMessage.value = '';
    if (html5QrCodeCheckOut) {
        html5QrCodeCheckOut.stop().then(() => html5QrCodeCheckOut.clear()).catch(() => {});
    }
}

// Training Review Functions
function openTrainingReviewModal() {
    showTrainingReviewModal.value = true;
    
    // Get trainer ID from selected training detail
    let trainerId = null;
    if (selectedTrainingDetail.value && selectedTrainingDetail.value.trainers && selectedTrainingDetail.value.trainers.length > 0) {
        // Get the first internal trainer
        const internalTrainer = selectedTrainingDetail.value.trainers.find(trainer => trainer.trainer_type === 'internal');
        if (internalTrainer && internalTrainer.trainer_id) {
            trainerId = internalTrainer.trainer_id;
        }
    }
    
    // Reset form
    reviewForm.value = {
        trainer_id: trainerId,
        // Trainer ratings
        trainer_mastery: 5,
        trainer_language: 5,
        trainer_intonation: 5,
        trainer_presentation: 5,
        trainer_qna: 5,
        // Training material ratings
        material_benefit: 5,
        material_clarity: 5,
        material_display: 5,
        material_suggestions: '',
        material_needs: ''
    };
}

function closeTrainingReviewModal() {
    showTrainingReviewModal.value = false;
    isSubmittingReview.value = false;
}

async function submitTrainingReview() {
    if (!selectedTrainingDetail.value) {
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Training detail tidak ditemukan'
        });
        return;
    }

    isSubmittingReview.value = true;

    try {
        await router.post(route('lms.training.review'), {
            training_schedule_id: selectedTrainingDetail.value.schedule_id,
            trainer_id: reviewForm.value.trainer_id,
            // Trainer ratings
            trainer_mastery: reviewForm.value.trainer_mastery,
            trainer_language: reviewForm.value.trainer_language,
            trainer_intonation: reviewForm.value.trainer_intonation,
            trainer_presentation: reviewForm.value.trainer_presentation,
            trainer_qna: reviewForm.value.trainer_qna,
            // Training material ratings
            material_benefit: reviewForm.value.material_benefit,
            material_clarity: reviewForm.value.material_clarity,
            material_display: reviewForm.value.material_display,
            material_suggestions: reviewForm.value.material_suggestions,
            material_needs: reviewForm.value.material_needs,
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                if (page.props.flash?.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Terima Kasih!',
                        html: `
                            <div class="text-center">
                                <div class="mb-4">
                                    <i class="fa-solid fa-heart text-red-500 text-4xl mb-3"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Review Berhasil Disimpan!</h3>
                                <p class="text-gray-600 mb-3">Terima kasih telah mengikuti training dan memberikan review yang berharga.</p>
                                <p class="text-sm text-gray-500">Feedback Anda sangat membantu kami untuk meningkatkan kualitas training di masa depan.</p>
                            </div>
                        `,
                        showConfirmButton: true,
                        confirmButtonText: 'Sama-sama!',
                        confirmButtonColor: '#F59E0B',
                        timer: 5000,
                        timerProgressBar: true,
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        closeTrainingReviewModal();
                    });
                } else {
                    // Fallback jika tidak ada flash success
                    Swal.fire({
                        icon: 'success',
                        title: 'Terima Kasih!',
                        html: `
                            <div class="text-center">
                                <div class="mb-4">
                                    <i class="fa-solid fa-heart text-red-500 text-4xl mb-3"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-gray-800 mb-2">Review Berhasil Disimpan!</h3>
                                <p class="text-gray-600 mb-3">Terima kasih telah mengikuti training dan memberikan review yang berharga.</p>
                                <p class="text-sm text-gray-500">Feedback Anda sangat membantu kami untuk meningkatkan kualitas training di masa depan.</p>
                            </div>
                        `,
                        showConfirmButton: true,
                        confirmButtonText: 'Sama-sama!',
                        confirmButtonColor: '#F59E0B',
                        timer: 5000,
                        timerProgressBar: true,
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    }).then(() => {
                        closeTrainingReviewModal();
                    });
                }
            },
            onError: (errors) => {
                const errorMessage = Object.values(errors)[0] || 'Terjadi kesalahan saat menyimpan review';
                Swal.fire({
                    icon: 'error',
                    title: 'Review Gagal!',
                    text: errorMessage
                });
            },
            onFinish: () => {
                isSubmittingReview.value = false;
            }
        });
    } catch (error) {
        console.error('Review submission error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Terjadi kesalahan saat menyimpan review'
        });
        isSubmittingReview.value = false;
    }
}

// QR Scanner Functions
watch(showCamera, async (val) => {
    if (val) {
        await nextTick();
        if (!window.Html5Qrcode) {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
            script.onload = setupCameras;
            document.body.appendChild(script);
        } else {
            setupCameras();
        }
    }
});

// Check-out QR Scanner Functions
watch(showCheckOutCamera, async (val) => {
    if (val) {
        await nextTick();
        if (!window.Html5Qrcode) {
            const script = document.createElement('script');
            script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
            script.onload = setupCheckOutCameras;
            document.body.appendChild(script);
        } else {
            setupCheckOutCameras();
        }
    }
});

async function setupCameras() {
    if (!window.Html5Qrcode) return;
    try {
        const devices = await window.Html5Qrcode.getCameras();
        cameras.value = devices;
        // Default ke kamera belakang jika ada
        const backCam = devices.find(cam => cam.label.toLowerCase().includes('back') || cam.label.toLowerCase().includes('belakang'));
        selectedCameraId.value = backCam?.id || devices[0]?.id || '';
        startCamera();
    } catch (err) {
        checkInStatusMessage.value = 'Tidak dapat mengakses kamera';
    }
}

function startCamera() {
    if (!window.Html5Qrcode || !selectedCameraId.value) return;
    if (html5QrCode) {
        html5QrCode.stop().then(() => html5QrCode.clear());
    }
    html5QrCode = new window.Html5Qrcode('training-qr-reader');
    html5QrCode.start(
        selectedCameraId.value,
        { fps: 10, qrbox: 250 },
        (decodedText) => {
            qrCodeInput.value = decodedText;
            showCamera.value = false;
            html5QrCode.stop().then(() => html5QrCode.clear());
            processTrainingCheckIn();
        },
        (errorMessage) => {}
    );
}

function switchCamera() {
    startCamera();
}

// Check-out Camera Functions
async function setupCheckOutCameras() {
    if (!window.Html5Qrcode) return;
    try {
        const devices = await window.Html5Qrcode.getCameras();
        checkOutCameras.value = devices;
        // Default ke kamera belakang jika ada
        const backCam = devices.find(cam => cam.label.toLowerCase().includes('back') || cam.label.toLowerCase().includes('belakang'));
        selectedCheckOutCameraId.value = backCam?.id || devices[0]?.id || '';
        startCheckOutCamera();
    } catch (err) {
        checkOutStatusMessage.value = 'Tidak dapat mengakses kamera';
    }
}

function startCheckOutCamera() {
    if (!window.Html5Qrcode || !selectedCheckOutCameraId.value) return;
    if (html5QrCodeCheckOut) {
        html5QrCodeCheckOut.stop().then(() => html5QrCodeCheckOut.clear());
    }
    html5QrCodeCheckOut = new window.Html5Qrcode('training-checkout-qr-reader');
    html5QrCodeCheckOut.start(
        selectedCheckOutCameraId.value,
        { fps: 10, qrbox: 250 },
        (decodedText) => {
            qrCodeCheckOutInput.value = decodedText;
            showCheckOutCamera.value = false;
            html5QrCodeCheckOut.stop().then(() => html5QrCodeCheckOut.clear());
            processTrainingCheckOut();
        },
        (errorMessage) => {}
    );
}

function switchCheckOutCamera() {
    startCheckOutCamera();
}

function closeCamera() {
    showCamera.value = false;
    if (html5QrCode) {
        html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
    }
}

// Check-in Process
async function processTrainingCheckIn() {
    if (!qrCodeInput.value.trim()) {
        checkInStatusMessage.value = 'QR Code tidak boleh kosong';
        return;
    }

    isProcessingCheckIn.value = true;
    checkInStatusMessage.value = 'Memproses check-in...';

    try {
        console.log('Processing Training QR Code:', qrCodeInput.value);
        console.log('Current user:', user);
        console.log('Selected training detail:', selectedTrainingDetail.value);

        await router.post(route('lms.check-in'), {
            qr_code: qrCodeInput.value.trim()
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                console.log('Check-in success:', page);
                
                // Always close modal on success (regardless of data)
                closeTrainingCheckInModal();
                
                if (page.props.flash?.success) {
                    checkInStatusMessage.value = page.props.flash.success;
                    
                    // Show success SweetAlert
                    Swal.fire({
                        icon: 'success',
                        title: 'Check-in Berhasil!',
                        text: page.props.flash.success,
                        timer: 3000,
                        showConfirmButton: false
                    });

                    // Update selected training detail with check-in response data
                    if (selectedTrainingDetail.value && page.props.flash?.training_sessions) {
                        console.log('Updating training detail with check-in data:', page.props.flash.training_sessions);
                        selectedTrainingDetail.value.sessions = page.props.flash.training_sessions;
                    }

                    // Also refresh training invitations data to update session accessibility
                    loadTrainingInvitations().then(() => {
                        // Update selected training detail with fresh data
                        if (selectedTrainingDetail.value) {
                            const updatedInvitation = trainingInvitations.value.find(
                                inv => inv.schedule_id === selectedTrainingDetail.value.schedule_id
                            );
                            if (updatedInvitation) {
                                // Merge the fresh data with check-in response data
                                selectedTrainingDetail.value = {
                                    ...updatedInvitation,
                                    sessions: selectedTrainingDetail.value.sessions || updatedInvitation.sessions
                                };
                            }
                        }
                    });

                    // Open first session item after success
                    setTimeout(() => {
                        openFirstSessionItem();
                    }, 1000);
                } else {
                    // Even if no success message, show generic success
                    Swal.fire({
                        icon: 'success',
                        title: 'Check-in Berhasil!',
                        text: 'Anda berhasil check-in ke training',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    
                    // Still try to refresh data
                    loadTrainingInvitations().then(() => {
                        if (selectedTrainingDetail.value) {
                            const updatedInvitation = trainingInvitations.value.find(
                                inv => inv.schedule_id === selectedTrainingDetail.value.schedule_id
                            );
                            if (updatedInvitation) {
                                selectedTrainingDetail.value = updatedInvitation;
                            }
                        }
                    });
                }
            },
            onError: (errors) => {
                console.log('Check-in error:', errors);
                const errorMessage = Object.values(errors)[0] || 'Terjadi kesalahan saat check-in'
                checkInStatusMessage.value = errorMessage;
                
                Swal.fire({
                    icon: 'error',
                    title: 'Check-in Gagal!',
                    text: errorMessage
                });
            },
            onFinish: () => {
                // Don't reset processing state here as it's handled in closeTrainingCheckInModal
                // isProcessingCheckIn.value = false;
            }
        });

    } catch (error) {
        console.error('Check-in error:', error);
        checkInStatusMessage.value = 'Terjadi kesalahan saat memproses check-in';
        isProcessingCheckIn.value = false;
        
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Terjadi kesalahan saat memproses check-in'
        });
    }
}

// Check-out Process
async function processTrainingCheckOut() {
    if (!qrCodeCheckOutInput.value.trim()) {
        checkOutStatusMessage.value = 'QR Code tidak boleh kosong';
        return;
    }

    isProcessingCheckOut.value = true;
    checkOutStatusMessage.value = 'Memproses check-out...';

    try {
        console.log('Processing Training Check-out QR Code:', qrCodeCheckOutInput.value);
        console.log('Current user:', user);
        console.log('Selected training detail:', selectedTrainingDetail.value);

        await router.post(route('lms.check-out'), {
            qr_code: qrCodeCheckOutInput.value.trim()
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                if (page.props.flash?.success) {
                    checkOutStatusMessage.value = page.props.flash.success;
                    
                    // Check if user can give feedback after checkout
                    const canGiveFeedback = selectedTrainingDetail.value?.can_give_feedback;
                    
                    if (canGiveFeedback) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Check-out Berhasil!',
                            text: page.props.flash.success,
                            showConfirmButton: true,
                            confirmButtonText: 'Berikan Review',
                            showCancelButton: true,
                            cancelButtonText: 'Nanti Saja',
                            confirmButtonColor: '#F59E0B',
                            cancelButtonColor: '#6B7280'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Open review modal
                                openTrainingReviewModal();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'success',
                            title: 'Check-out Berhasil!',
                            text: page.props.flash.success,
                            showConfirmButton: true,
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#F59E0B'
                        });
                    }

                    // Refresh training invitations data to update status
                    loadTrainingInvitations().then(() => {
                        // Update selected training detail with fresh data
                        if (selectedTrainingDetail.value) {
                            const updatedInvitation = trainingInvitations.value.find(
                                inv => inv.schedule_id === selectedTrainingDetail.value.schedule_id
                            );
                            if (updatedInvitation) {
                                selectedTrainingDetail.value = {
                                    ...updatedInvitation,
                                    sessions: selectedTrainingDetail.value.sessions || updatedInvitation.sessions
                                };
                            }
                        }
                    });

                    // Close modal after success
                    setTimeout(() => {
                        closeTrainingCheckOutModal();
                    }, 2000);
                } else {
                    checkOutStatusMessage.value = 'Check-out berhasil tapi tidak ada data training';
                }
            },
            onError: (errors) => {
                console.log('Check-out error:', errors);
                const errorMessage = Object.values(errors)[0] || 'Terjadi kesalahan saat check-out'
                checkOutStatusMessage.value = errorMessage;
                
                Swal.fire({
                    icon: 'error',
                    title: 'Check-out Gagal!',
                    text: errorMessage
                });
            },
            onFinish: () => {
                isProcessingCheckOut.value = false;
            }
        });
    } catch (error) {
        console.error('Check-out error:', error);
        checkOutStatusMessage.value = 'Terjadi kesalahan saat check-out';
        isProcessingCheckOut.value = false;
        
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Terjadi kesalahan saat memproses check-out'
        });
    }
}

// Cleanup on unmount
onBeforeUnmount(() => {
    if (html5QrCode) {
        html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
    }
    if (html5QrCodeCheckOut) {
        html5QrCodeCheckOut.stop().then(() => html5QrCodeCheckOut.clear()).catch(() => {});
    }
});


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

// Format correction value for display
function formatCorrectionValue(approval) {
    return formatAnyCorrectionValue(approval.old_value, approval.new_value, approval.type);
}

// Format correction time for detailed display
function getFormattedCorrectionTime(jsonValue) {
    try {
        const data = JSON.parse(jsonValue);
        const dateTime = new Date(data.scan_date);
        const date = dateTime.toLocaleDateString('id-ID');
        const time = dateTime.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        const mode = data.inoutmode === 1 ? 'Masuk' : 'Keluar';
        
        return `${date} ${time} (${mode})`;
    } catch (error) {
        return jsonValue;
    }
}

// Enhanced function to format any correction value
function formatAnyCorrectionValue(oldValue, newValue, type = 'attendance') {
    if (type === 'schedule') {
        return `Dari: ${oldValue} → Ke: ${newValue}`;
    }
    
    if (type === 'manual_attendance') {
        // For manual attendance, only newValue exists
        try {
            const newData = JSON.parse(newValue);
            if (newData.scan_date) {
                const time = new Date(newData.scan_date).toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                const mode = newData.inoutmode === 1 ? 'Masuk' : 'Keluar';
                return `Input ${mode}: ${time}`;
            }
        } catch (error) {
            return 'Input Absen Manual';
        }
    }
    
    // Try to parse as JSON for attendance
    try {
        const oldData = JSON.parse(oldValue);
        const newData = JSON.parse(newValue);
        
        if (oldData.scan_date && newData.scan_date) {
            const oldTime = new Date(oldData.scan_date).toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const newTime = new Date(newData.scan_date).toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            const mode = oldData.inoutmode === 1 ? 'Masuk' : 'Keluar';
            return `Waktu ${mode}: ${oldTime} → ${newTime}`;
        }
    } catch (error) {
        // If JSON parsing fails, try to extract time from string
        const timeRegex = /(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/;
        const oldMatch = oldValue.match(timeRegex);
        const newMatch = newValue.match(timeRegex);
        
        if (oldMatch && newMatch) {
            const oldTime = new Date(oldMatch[1]).toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            const newTime = new Date(newMatch[1]).toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            return `Waktu: ${oldTime} → ${newTime}`;
        }
    }
    
    // Final fallback - show a clean message
    return `Koreksi Attendance`;
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
    // Reset filters
    announcementsFilters.value = {
        search: '',
        target: '',
        date_from: '',
        date_to: ''
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
    loadPendingPrApprovals();
    loadPendingPoOpsApprovals();
    loadLeaveNotifications();
    loadPendingHrdApprovals();
    loadPendingCorrectionApprovals();
    loadTrainingInvitations();
    loadAvailableTrainings();
    loadActiveSanctions();
    loadCoachingApprovals();
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
            <div class="relative z-10 w-full h-screen flex flex-col">
                <!-- Top Section: Welcome Card -->
                <div class="flex-shrink-0 mb-4 px-4 md:px-6">
                    <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 md:p-6 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                        :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                        <!-- Avatar user -->
                        <div class="flex items-center gap-4 mb-4">
                            <div v-if="user.avatar" class="w-24 h-24 rounded-full overflow-hidden border-4 border-white shadow-xl cursor-pointer hover:shadow-2xl transition-all" @click="openImageModal(`/storage/${user.avatar}`)">
                                <img :src="user.avatar ? `/storage/${user.avatar}` : '/images/avatar-default.png'" alt="Avatar" class="w-full h-full object-cover hover:scale-105 transition-transform" />
                            </div>
                            <div v-else class="w-24 h-24 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-3xl font-bold border-4 border-white shadow-xl">
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
                        
                        <!-- Active Sanctions Badge -->
                        <div v-if="activeSanctions.length > 0" class="mt-4 p-4 rounded-xl border shadow-lg animate-fade-in"
                            :class="[
                                isNight ? 'bg-red-900/80 text-white border-red-600' : 'bg-gradient-to-r from-red-50 to-orange-50 text-red-800 border-red-200'
                            ]">
                            <div class="flex items-center gap-2 mb-3">
                                <i class="fa-solid fa-gavel text-lg" :class="isNight ? 'text-red-300' : 'text-red-600'"></i>
                                <span class="font-semibold text-sm" :class="isNight ? 'text-red-300' : 'text-red-600'">Sanksi Aktif</span>
                            </div>
                            <!-- Debug info -->
                            <div class="text-xs mb-2" :class="isNight ? 'text-red-300' : 'text-red-600'">
                                Debug: {{ activeSanctions.length }} sanctions found
                            </div>
                            <div v-for="sanction in activeSanctions" :key="sanction.id" class="mb-3 last:mb-0">
                                <div v-for="(action, index) in sanction.sanctions" :key="index" 
                                     class="p-3 rounded-lg border mb-2" 
                                     :class="isNight ? 'bg-red-800/50 border-red-700' : 'bg-white/80 border-red-200'"
                                     v-show="action && isSanctionActive(action)">
                                    <!-- Sanction Details -->
                                    <div class="space-y-2">
                                        <!-- Sanction Name -->
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-red-800'">
                                                {{ action.name }}
                                            </div>
                                            <div class="text-xs px-2 py-1 rounded-full" 
                                                 :class="isNight ? 'bg-red-700 text-red-200' : 'bg-red-100 text-red-700'">
                                                Aktif
                                            </div>
                                        </div>
                                        
                                        <!-- Violation Date -->
                                        <div class="text-xs" :class="isNight ? 'text-red-300' : 'text-red-600'">
                                            <span class="font-medium">Tanggal Pelanggaran:</span> {{ formatDate(sanction.violation_date) }}
                                        </div>
                                        
                                        <!-- Violation Location -->
                                        <div class="text-xs" :class="isNight ? 'text-red-300' : 'text-red-600'">
                                            <span class="font-medium">Tempat Terjadi Pelanggaran:</span> {{ sanction.location }}
                                        </div>
                                        
                                        <!-- Violation Details -->
                                        <div class="text-xs" :class="isNight ? 'text-red-300' : 'text-red-600'">
                                            <span class="font-medium">Detail Pelanggaran:</span> {{ sanction.violation_details }}
                                        </div>
                                        
                                        <!-- Effective Date -->
                                        <div class="text-xs" :class="isNight ? 'text-red-300' : 'text-red-600'">
                                            <span class="font-medium">Tanggal Berlaku:</span> {{ formatDate(action.effective_date) }}
                                        </div>
                                        
                                        <!-- End Date -->
                                        <div class="text-xs" :class="isNight ? 'text-red-300' : 'text-red-600'">
                                            <span class="font-medium">Tanggal Berakhir:</span> {{ formatDate(action.end_date) }}
                                        </div>
                                        
                                        <!-- Remarks -->
                                        <div v-if="action.remarks" class="text-xs" :class="isNight ? 'text-red-300' : 'text-red-600'">
                                            <span class="font-medium">Keterangan:</span> {{ action.remarks }}
                                        </div>
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
                        
                        <div v-if="loadingApprovals || loadingNotifications || loadingHrdApprovals || loadingCorrectionApprovals || loadingTrainingInvitations" class="text-center py-4">
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
                                            {{ approval.type === 'schedule' ? 'Koreksi Schedule' : approval.type === 'attendance' ? 'Koreksi Attendance' : 'Input Absen Manual' }} • {{ approval.nama_outlet }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                            {{ new Date(approval.tanggal).toLocaleDateString('id-ID') }} • {{ formatCorrectionValue(approval) }}
                                        </div>
                                        <!-- Reason Display -->
                                        <div v-if="approval.reason" class="mt-2 p-2 bg-blue-50 rounded text-xs border-l-4 border-blue-400">
                                            <div class="font-medium mb-1 text-blue-700">Alasan Koreksi:</div>
                                            <div class="text-xs text-blue-600">{{ approval.reason }}</div>
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
                            <div v-if="(pendingApprovals.length > 0 || pendingHrdApprovals.length > 0 || pendingCorrectionApprovals.length > 0)" class="text-center pt-2">
                                <button @click="showAllApprovals" class="text-sm bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 transition-colors">
                                    <i class="fa-solid fa-list-check mr-1"></i>
                                    Lihat Semua Approval ({{ pendingApprovals.length + pendingHrdApprovals.length + pendingCorrectionApprovals.length }})
                                </button>
                            </div>
                            
                        </div>
                    </div>
                </div>

                <!-- Coaching Approval Section -->
                <div v-if="pendingCoachingApprovals.length > 0" class="flex-shrink-0 mb-4">
                    <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                        :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-clipboard-check text-lg" :class="isNight ? 'text-blue-400' : 'text-blue-600'"></i>
                                <h3 class="text-lg font-semibold" :class="isNight ? 'text-white' : 'text-slate-800'">Coaching Menunggu Persetujuan</h3>
                            </div>
                            <div class="text-sm px-2 py-1 rounded-full" 
                                 :class="isNight ? 'bg-blue-700 text-blue-200' : 'bg-blue-100 text-blue-800'">
                                {{ pendingCoachingApprovals.length }} item
                            </div>
                        </div>
                        
                        <div class="space-y-3">
                            <div v-for="approval in pendingCoachingApprovals.slice(0, 3)" :key="approval.id" 
                                 class="p-3 rounded-lg border transition-all duration-200"
                                 :class="isNight ? 'bg-slate-700/50 border-slate-600' : 'bg-blue-50 border-blue-200'">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 mt-1">
                                        <div class="w-2 h-2 rounded-full bg-blue-500"></div>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="font-medium text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                                {{ approval.employee_name }}
                                            </div>
                                            <div class="text-xs px-2 py-1 rounded-full" 
                                                 :class="isNight ? 'bg-blue-700 text-blue-200' : 'bg-blue-100 text-blue-800'">
                                                Level {{ approval.approval_level }}
                                            </div>
                                        </div>
                                        <div class="text-xs space-y-1 mb-3" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                            <div class="flex justify-between">
                                                <span>Tanggal Pelanggaran:</span>
                                                <span>{{ formatDate(approval.violation_date) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Supervisor:</span>
                                                <span>{{ approval.supervisor_name }}</span>
                                            </div>
                                        </div>
                                        <div class="text-xs mb-3" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                            <span class="font-medium">Detail:</span>
                                            <div class="mt-1">{{ approval.violation_details }}</div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button @click="approveCoaching(approval.coaching_id, approval.id)"
                                                    class="px-3 py-1 text-xs rounded-full bg-green-500 hover:bg-green-600 text-white transition-colors">
                                                <i class="fa-solid fa-check mr-1"></i>Setujui
                                            </button>
                                            <button @click="rejectCoaching(approval.coaching_id, approval.id)"
                                                    class="px-3 py-1 text-xs rounded-full bg-red-500 hover:bg-red-600 text-white transition-colors">
                                                <i class="fa-solid fa-times mr-1"></i>Tolak
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="pendingCoachingApprovals.length > 3" class="text-center pt-2">
                                <button class="text-sm text-blue-500 hover:text-blue-700 font-medium">
                                    Lihat {{ pendingCoachingApprovals.length - 3 }} coaching lainnya...
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchase Requisition Approval Section -->
                <div v-if="prApprovalCount > 0" class="flex-shrink-0 mb-4">
                    <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                        :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-green-500 animate-pulse"></div>
                                <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    <i class="fa fa-shopping-cart mr-2 text-green-500"></i>
                                    Purchase Requisition Approval
                                </h3>
                            </div>
                            <div class="bg-green-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                                {{ prApprovalCount }}
                            </div>
                        </div>
                        
                        <div v-if="loadingPrApprovals" class="text-center py-4">
                            <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-green-500"></div>
                            <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
                        </div>
                        
                        <div v-else class="space-y-2">
                            <!-- Purchase Requisition Approvals -->
                            <div v-for="pr in pendingPrApprovals.slice(0, 3)" :key="'pr-approval-' + pr.id"
                                @click="showPrApprovalDetails(pr.id)"
                                class="p-3 rounded-lg cursor-pointer transition-all duration-200 hover:scale-105"
                                :class="isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-green-50 hover:bg-green-100'">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                            {{ pr.pr_number }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                            {{ pr.title }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                            {{ pr.division?.nama_divisi }} • Rp {{ new Intl.NumberFormat('id-ID').format(pr.amount) }}
                                        </div>
                                        <div v-if="pr.outlet?.nama_outlet" class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                            <i class="fa fa-map-marker-alt mr-1 text-blue-500"></i>{{ pr.outlet.nama_outlet }}
                                        </div>
                                    </div>
                                    <div class="text-xs text-green-500 font-medium">
                                        <i class="fa fa-shopping-cart mr-1"></i>PR Approval
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Show more button if there are more than 3 PRs -->
                            <div v-if="pendingPrApprovals.length > 3" class="text-center pt-2">
                                <button class="text-sm text-green-500 hover:text-green-700 font-medium">
                                    Lihat {{ pendingPrApprovals.length - 3 }} PR lainnya...
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchase Order Ops Approval Section -->
                <div v-if="poOpsApprovalCount > 0" class="flex-shrink-0 mb-4">
                    <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                        :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-orange-500 animate-pulse"></div>
                                <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                                    <i class="fa fa-file-invoice mr-2 text-orange-500"></i>
                                    Purchase Order Ops Approval
                                </h3>
                            </div>
                            <div class="bg-orange-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                                {{ poOpsApprovalCount }}
                            </div>
                        </div>
                        
                        <div v-if="loadingPoOpsApprovals" class="text-center py-4">
                            <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-orange-500"></div>
                            <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat data...</p>
                        </div>
                        
                        <div v-else class="space-y-2">
                            <!-- Purchase Order Ops Approvals -->
                            <div v-for="po in pendingPoOpsApprovals.slice(0, 3)" :key="'po-ops-approval-' + po.id"
                                @click="showPoOpsApprovalDetails(po.id)"
                                class="p-3 rounded-lg cursor-pointer transition-all duration-200 hover:scale-105"
                                :class="isNight ? 'bg-slate-700/50 hover:bg-slate-600/50' : 'bg-orange-50 hover:bg-orange-100'">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                            {{ po.number }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                            {{ po.supplier?.name || 'Unknown Supplier' }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                            Rp {{ new Intl.NumberFormat('id-ID').format(po.grand_total) }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                            <i class="fa fa-user mr-1 text-blue-500"></i>{{ po.creator?.nama_lengkap }}
                                        </div>
                                    </div>
                                    <div class="text-xs text-orange-500 font-medium">
                                        <i class="fa fa-file-invoice mr-1"></i>PO Ops
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Show more button if there are more than 3 PO Ops -->
                            <div v-if="pendingPoOpsApprovals.length > 3" class="text-center pt-2">
                                <button class="text-sm text-orange-500 hover:text-orange-700 font-medium">
                                    Lihat {{ pendingPoOpsApprovals.length - 3 }} PO Ops lainnya...
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section: Clock, Weather, Calendar, Notes, Birthday, and Announcements -->
                <div class="flex-1 grid grid-cols-1 lg:grid-cols-6 gap-4 min-h-0 mb-6 items-stretch px-4 md:px-6">
                    <!-- Left: Clock and Weather -->
                    <div class="lg:col-span-1 flex flex-col gap-4">
                        <!-- Clock Card -->
                        <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 hover:shadow-3xl flex-shrink-0"
                            :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                            <div class="flex justify-center">
                                <AnalogClock :date="time" class="scale-100 md:scale-110 animate-slide-in" />
                            </div>
                        </div>
                        
                        <!-- Weather Card -->
                        <div v-if="weather.city && weather.code" class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl flex-1 flex items-center justify-center"
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

                    <!-- Calendar Widget -->
                    <div class="lg:col-span-1 flex">
                        <div class="transition-all duration-500 hover:shadow-3xl w-full">
                            <CalendarWidget :is-night="isNight" />
                        </div>
                    </div>

                    <!-- Notes Widget -->
                    <div class="lg:col-span-1 flex">
                        <div class="w-full">
                            <NotesWidget :is-night="isNight" />
                        </div>
                    </div>

                    <!-- Birthday Widget -->
                    <div class="lg:col-span-1 flex">
                        <div class="w-full">
                            <BirthdayWidget :is-night="isNight" />
                        </div>
                    </div>

                    <!-- Right: Announcements -->
                    <div class="lg:col-span-2 flex">
                        <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 w-full overflow-y-auto transition-all duration-500 hover:shadow-3xl"
                            :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                            <AnnouncementList :is-night="isNight" @show-all="showAllAnnouncements" />
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Approval Detail Modal -->
        <div v-if="showApprovalModal && selectedApproval" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showApprovalModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-lg w-full max-w-lg mx-4 max-h-[90vh] overflow-hidden" @click.stop>
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        Detail Permohonan Izin/Cuti
                    </h3>
                    <button @click="showApprovalModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
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

                <!-- Modal Footer -->
                <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-t border-gray-200 dark:border-gray-600">
                    <div class="flex justify-end gap-3">
                        <!-- HRD Actions -->
                        <template v-if="user.division_id === 6 && selectedApproval.status === 'supervisor_approved'">
                            <button @click="hrdRejectRequest(selectedApproval.id)" 
                                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors">
                                Tolak HRD
                            </button>
                            <button @click="hrdApproveRequest(selectedApproval.id)" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors">
                                Setujui HRD
                            </button>
                        </template>
                        <!-- Supervisor Actions -->
                        <template v-else-if="selectedApproval.status === 'pending'">
                            <button @click="rejectRequest(selectedApproval.id)" 
                                    class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors">
                                Tolak
                            </button>
                            <button @click="approveRequest(selectedApproval.id)" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors">
                                Setujui
                            </button>
                        </template>
                        <!-- Close Button -->
                        <button @click="showApprovalModal = false" 
                                class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Purchase Requisition Approval Detail Modal -->
        <div v-if="showPrApprovalModal && selectedPrApproval" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showPrApprovalModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-shopping-cart mr-2 text-green-500"></i>
                        Detail Purchase Requisition
                    </h3>
                    <button @click="showPrApprovalModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>

                <div class="space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Informasi Dasar</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">PR Number</div>
                                <div class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedPrApproval.pr_number }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</div>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    {{ selectedPrApproval.status }}
                                </span>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Title</div>
                                <div class="text-gray-900 dark:text-white">{{ selectedPrApproval.title }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Division</div>
                                <div class="text-gray-900 dark:text-white">{{ selectedPrApproval.division?.nama_divisi }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Category</div>
                                <div class="text-gray-900 dark:text-white">{{ selectedPrApproval.category?.name }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Outlet</div>
                                <div class="text-gray-900 dark:text-white">{{ selectedPrApproval.outlet?.nama_outlet || '-' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Amount</div>
                                <div class="text-lg font-semibold text-gray-900 dark:text-white">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(selectedPrApproval.amount) }}
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Description</div>
                            <div class="text-gray-900 dark:text-white mt-1">{{ selectedPrApproval.description }}</div>
                        </div>
                    </div>

                    <!-- Budget Information -->
                    <div v-if="prApprovalBudgetInfo" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                            <i class="fa fa-chart-pie mr-2 text-blue-500"></i>
                            Informasi Budget - {{ getMonthName(prApprovalBudgetInfo.current_month) }} {{ prApprovalBudgetInfo.current_year }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg">
                                <div class="text-sm font-medium text-blue-600">Total Budget</div>
                                <div class="text-lg font-bold text-blue-800">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(prApprovalBudgetInfo.category_budget) }}
                                </div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg">
                                <div class="text-sm font-medium text-orange-600">Used This Month</div>
                                <div class="text-lg font-bold text-orange-800">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(prApprovalBudgetInfo.category_used_amount) }}
                                </div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg">
                                <div class="text-sm font-medium text-green-600">Remaining Budget</div>
                                <div class="text-lg font-bold" :class="prApprovalBudgetInfo.category_remaining_amount < 0 ? 'text-red-800' : 'text-green-800'">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(prApprovalBudgetInfo.category_remaining_amount) }}
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between text-sm text-gray-600 mb-2">
                                <span>Budget Usage</span>
                                <span>{{ Math.round((prApprovalBudgetInfo.category_used_amount / prApprovalBudgetInfo.category_budget) * 100) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="h-3 rounded-full transition-all duration-300"
                                     :class="getBudgetProgressColor(prApprovalBudgetInfo.category_used_amount, prApprovalBudgetInfo.category_budget)"
                                     :style="{ width: Math.min((prApprovalBudgetInfo.category_used_amount / prApprovalBudgetInfo.category_budget) * 100, 100) + '%' }">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items -->
                    <div v-if="selectedPrApproval.items && selectedPrApproval.items.length > 0" class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Items</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                <thead class="bg-gray-100 dark:bg-gray-600">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Item Name</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Qty</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Unit</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Unit Price</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-600">
                                    <tr v-for="item in selectedPrApproval.items" :key="item.id">
                                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ item.item_name }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ item.qty }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">{{ item.unit }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white">Rp {{ new Intl.NumberFormat('id-ID').format(item.unit_price) }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 dark:text-white text-right">Rp {{ new Intl.NumberFormat('id-ID').format(item.subtotal) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Approval Flow -->
                    <div v-if="selectedPrApproval.approval_flows && selectedPrApproval.approval_flows.length > 0" class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Approval Flow</h4>
                        <div class="space-y-3">
                            <div v-for="flow in selectedPrApproval.approval_flows" :key="flow.id"
                                 class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-600 rounded-lg"
                                 :class="getApprovalFlowClass(flow.status)">
                                <div class="flex items-center space-x-3">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        Level {{ flow.approval_level }}
                                    </span>
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ flow.approver?.nama_lengkap || flow.approver?.name }}</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ flow.approver?.email }}</div>
                                        <div v-if="flow.approver?.jabatan?.nama_jabatan" class="text-xs text-blue-600 font-medium">{{ flow.approver.jabatan.nama_jabatan }}</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium" :class="getApprovalStatusTextClass(flow.status)">
                                        {{ flow.status }}
                                    </div>
                                    <div v-if="flow.approved_at" class="text-xs text-gray-500">
                                        Approved: {{ new Date(flow.approved_at).toLocaleDateString('id-ID') }}
                                    </div>
                                    <div v-if="flow.rejected_at" class="text-xs text-gray-500">
                                        Rejected: {{ new Date(flow.rejected_at).toLocaleDateString('id-ID') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <button @click="showPrApprovalModal = false" 
                            class="px-4 py-2 text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Tutup
                    </button>
                    <button @click="approvePr(selectedPrApproval.id)" 
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                        <i class="fa fa-check mr-2"></i>Approve
                    </button>
                    <button @click="showRejectPrModal(selectedPrApproval.id)" 
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        <i class="fa fa-times mr-2"></i>Reject
                    </button>
                </div>
            </div>
        </div>

        <!-- Purchase Order Ops Approval Detail Modal -->
        <div v-if="showPoOpsApprovalModal && selectedPoOpsApproval" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showPoOpsApprovalModal = false">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                        <i class="fa fa-file-invoice mr-2 text-orange-500"></i>
                        Detail Purchase Order Ops
                    </h3>
                    <button @click="showPoOpsApprovalModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>

                <div class="space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Informasi Dasar</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">PO Number</label>
                                <p class="text-gray-900 dark:text-white font-semibold">{{ selectedPoOpsApproval.number }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Date</label>
                                <p class="text-gray-900 dark:text-white">{{ new Date(selectedPoOpsApproval.date).toLocaleDateString('id-ID') }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Supplier</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPoOpsApproval.supplier?.name || 'Unknown' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Status</label>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                      :class="getStatusColor(selectedPoOpsApproval.status)">
                                    {{ selectedPoOpsApproval.status.toUpperCase() }}
                                </span>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Grand Total</label>
                                <p class="text-gray-900 dark:text-white font-semibold text-lg">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(selectedPoOpsApproval.grand_total) }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Created By</label>
                                <p class="text-gray-900 dark:text-white">{{ selectedPoOpsApproval.creator?.nama_lengkap || 'Unknown' }}</p>
                            </div>
                        </div>
                        <div v-if="selectedPoOpsApproval.notes" class="mt-4">
                            <label class="text-sm font-medium text-gray-600 dark:text-gray-400">Notes</label>
                            <p class="text-gray-900 dark:text-white">{{ selectedPoOpsApproval.notes }}</p>
                        </div>
                    </div>

                    <!-- Budget Information -->
                    <div v-if="poOpsApprovalBudgetInfo" class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">
                            <i class="fa fa-chart-pie mr-2 text-blue-500"></i>
                            Informasi Budget - {{ getMonthName(poOpsApprovalBudgetInfo.current_month) }} {{ poOpsApprovalBudgetInfo.current_year }}
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg">
                                <div class="text-sm font-medium text-blue-600">Total Budget</div>
                                <div class="text-lg font-bold text-blue-800">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(poOpsApprovalBudgetInfo.category_budget) }}
                                </div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg">
                                <div class="text-sm font-medium text-orange-600">Used This Month</div>
                                <div class="text-lg font-bold text-orange-800">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(poOpsApprovalBudgetInfo.category_used_amount) }}
                                </div>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-3 rounded-lg">
                                <div class="text-sm font-medium text-green-600">Remaining Budget</div>
                                <div class="text-lg font-bold" :class="poOpsApprovalBudgetInfo.category_remaining_amount < 0 ? 'text-red-800' : 'text-green-800'">
                                    Rp {{ new Intl.NumberFormat('id-ID').format(poOpsApprovalBudgetInfo.category_remaining_amount) }}
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between text-sm text-gray-600 mb-2">
                                <span>Budget Usage</span>
                                <span>{{ Math.round((poOpsApprovalBudgetInfo.category_used_amount / poOpsApprovalBudgetInfo.category_budget) * 100) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="h-3 rounded-full transition-all duration-300"
                                     :class="getBudgetProgressColor(poOpsApprovalBudgetInfo.category_used_amount, poOpsApprovalBudgetInfo.category_budget)"
                                     :style="{ width: Math.min((poOpsApprovalBudgetInfo.category_used_amount / poOpsApprovalBudgetInfo.category_budget) * 100, 100) + '%' }">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items -->
                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Items</h4>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                <thead class="bg-gray-100 dark:bg-gray-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Item</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qty</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Unit</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Price</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-700 divide-y divide-gray-200 dark:divide-gray-600">
                                    <tr v-for="item in selectedPoOpsApproval.items" :key="item.id">
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">{{ item.item_name }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">{{ item.quantity }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">{{ item.unit }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-white">Rp {{ new Intl.NumberFormat('id-ID').format(item.price) }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-white font-semibold">Rp {{ new Intl.NumberFormat('id-ID').format(item.total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Approval Flow -->
                    <div v-if="selectedPoOpsApproval.approval_flows && selectedPoOpsApproval.approval_flows.length > 0" class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                        <h4 class="text-md font-semibold text-gray-900 dark:text-white mb-3">Approval Flow</h4>
                        <div class="space-y-3">
                            <div v-for="flow in selectedPoOpsApproval.approval_flows" :key="flow.id" 
                                 class="flex items-center justify-between p-3 bg-white dark:bg-gray-600 rounded-lg border border-gray-200 dark:border-gray-500">
                                <div class="flex items-center space-x-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        Level {{ flow.approval_level }}
                                    </span>
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ flow.approver?.nama_lengkap || flow.approver?.name }}</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ flow.approver?.email }}</div>
                                        <div v-if="flow.approver?.jabatan?.nama_jabatan" class="text-xs text-blue-600 font-medium">{{ flow.approver.jabatan.nama_jabatan }}</div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-medium" :class="getApprovalStatusTextClass(flow.status)">
                                        {{ flow.status }}
                                    </div>
                                    <div v-if="flow.approved_at" class="text-xs text-gray-500">
                                        Approved: {{ new Date(flow.approved_at).toLocaleDateString('id-ID') }}
                                    </div>
                                    <div v-if="flow.rejected_at" class="text-xs text-gray-500">
                                        Rejected: {{ new Date(flow.rejected_at).toLocaleDateString('id-ID') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-600">
                    <button @click="showPoOpsApprovalModal = false" 
                            class="px-4 py-2 text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Tutup
                    </button>
                    <button @click="approvePoOps(selectedPoOpsApproval.id)" 
                            class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                        <i class="fa fa-check mr-2"></i>Approve
                    </button>
                    <button @click="showRejectPoOpsModal(selectedPoOpsApproval.id)" 
                            class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                        <i class="fa fa-times mr-2"></i>Reject
                    </button>
                </div>
            </div>
        </div>

        <!-- Announcements Modal -->
        <div v-if="showAnnouncementsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeAnnouncementsModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-6xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
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
                                    <div class="flex-1 mr-4">
                                        <h4 class="font-semibold text-gray-900 dark:text-white">
                                            {{ announcement.title }}
                                        </h4>
                                        <div class="flex items-center gap-2 mt-1">
                                            <!-- Avatar User Pembuat -->
                                            <div v-if="announcement.creator_avatar" class="w-5 h-5 rounded-full overflow-hidden cursor-pointer hover:scale-110 transition-transform" @click="openImageModal(`/storage/${announcement.creator_avatar}`)">
                                                <img 
                                                    :src="`/storage/${announcement.creator_avatar}`" 
                                                    :alt="announcement.creator_name"
                                                    class="w-full h-full object-cover"
                                                />
                                            </div>
                                            <div 
                                                v-else 
                                                class="w-5 h-5 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold"
                                            >
                                                {{ getInitials(announcement.creator_name) }}
                                            </div>
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ announcement.creator_name || 'Unknown' }}
                                            </span>
                                        </div>
                                    </div>
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

        <!-- Training Detail Modal -->
        <div v-if="showTrainingDetailModal && selectedTrainingDetail" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeTrainingDetailModal">
            <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">Detail Training</h3>
                    <div class="flex items-center space-x-2">
                        <button @click="refreshTrainingDetail" :disabled="refreshingTrainingDetail" class="text-slate-500 hover:text-slate-700 disabled:opacity-50 disabled:cursor-not-allowed" title="Refresh Data">
                            <svg v-if="!refreshingTrainingDetail" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            <svg v-else class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </button>
                        <button @click="closeTrainingDetailModal" class="text-slate-500 hover:text-slate-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    <!-- Training Info -->
                    <div class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                        <h4 class="text-lg font-semibold text-slate-800 mb-3">{{ selectedTrainingDetail.course_title }}</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm font-medium text-slate-700 mb-1">Tanggal</div>
                                <div class="text-sm text-slate-900">{{ new Date(selectedTrainingDetail.scheduled_date).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-slate-700 mb-1">Waktu</div>
                                <div class="text-sm text-slate-900">{{ selectedTrainingDetail.start_time }} - {{ selectedTrainingDetail.end_time }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-slate-700 mb-1">Lokasi</div>
                                <div class="text-sm text-slate-900">{{ selectedTrainingDetail.outlet_name }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-medium text-slate-700 mb-1">Role</div>
                                <div class="text-sm text-slate-900">{{ selectedTrainingDetail.role }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Trainers Info -->
                    <div v-if="selectedTrainingDetail.trainers && selectedTrainingDetail.trainers.length > 0" class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h5 class="text-md font-semibold text-slate-800 mb-2">Trainer</h5>
                        <div class="space-y-2">
                            <div v-for="trainer in selectedTrainingDetail.trainers" :key="trainer.id" class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                    {{ trainer.trainer_name ? trainer.trainer_name.charAt(0).toUpperCase() : 'T' }}
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-slate-800">{{ trainer.trainer_name }}</div>
                                    <div class="text-xs text-slate-600">{{ trainer.trainer_type === 'internal' ? 'Internal' : 'External' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sessions -->
                    <div v-if="selectedTrainingDetail.sessions && selectedTrainingDetail.sessions.length > 0">
                        <h5 class="text-md font-semibold text-slate-800 mb-3">Sesi Training</h5>
                        <div class="space-y-4">
                            <div v-for="(session, sessionIndex) in selectedTrainingDetail.sessions" :key="session.id" 
                                 class="border border-slate-200 rounded-lg p-4 bg-slate-50">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-indigo-500 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                            {{ sessionIndex + 1 }}
                                        </div>
                                        <div>
                                            <h6 class="text-sm font-semibold text-slate-800">{{ session.session_title }}</h6>
                                            <div class="text-xs text-slate-600">
                                                {{ session.estimated_duration_minutes }} menit
                                                <span v-if="session.is_required" class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs rounded-full">Wajib</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span v-if="session.can_access" class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                            <i class="fa-solid fa-check mr-1"></i>Dapat Diakses
                                        </span>
                                        <span v-else class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">
                                            <i class="fa-solid fa-lock mr-1"></i>Terkunci
                                        </span>
                                    </div>
                                </div>
                                
                                <p v-if="session.session_description" class="text-sm text-slate-600 mb-3">{{ session.session_description }}</p>

                                <!-- Session Items -->
                                <div v-if="session.items && session.items.length > 0" class="ml-6 space-y-2">
                                    <!-- For trainers: only show materials, no interaction required -->
                                    <div v-if="isTrainer" class="space-y-2">
                                        <div v-for="(item, itemIndex) in session.items.filter(item => item.item_type === 'material')" :key="item.id" 
                                             class="flex items-center justify-between p-2 bg-white rounded border border-slate-200"
                                             :class="{
                                                 'cursor-pointer hover:bg-slate-50': item.can_access,
                                                 'cursor-not-allowed opacity-60': !item.can_access
                                             }"
                                             @click="item.can_access ? handleMaterialItemClick(item, session) : null">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-semibold bg-blue-500">
                                                    <i class="fa-solid fa-file text-xs"></i>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-slate-800">{{ item.title }}</div>
                                                    <div class="text-xs text-slate-600">
                                                        Material • {{ item.estimated_duration_minutes }} menit
                                                        <span v-if="item.is_required" class="ml-2 text-red-600">*</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <!-- Material completed status for trainer -->
                                                <span v-if="item.is_completed && item.completion_status" 
                                                      class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                                    <i class="fa-solid fa-check-circle mr-1"></i>
                                                    Selesai
                                                </span>
                                                <!-- Material accessible but not completed -->
                                                <span v-else-if="item.can_access" class="text-xs text-blue-600 font-medium">
                                                    <i class="fa-solid fa-eye mr-1"></i>
                                                    Lihat Materi
                                                </span>
                                                <!-- Material locked -->
                                                <span v-else class="text-xs text-gray-500 font-medium">
                                                    <i class="fa-solid fa-lock mr-1"></i>
                                                    Terkunci
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- Trainer notice -->
                                        <div v-if="session.items.filter(item => item.item_type === 'material').length > 0" 
                                             class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                            <div class="flex items-center gap-2">
                                                <i class="fa-solid fa-info-circle text-blue-500"></i>
                                                <span class="text-sm text-blue-700 font-medium">Sebagai Trainer</span>
                                            </div>
                                            <p class="text-xs text-blue-600 mt-1">
                                                Anda dapat melihat materi training di atas. Tidak perlu mengikuti sesi item lainnya.
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- For regular participants: show all items with full interaction -->
                                    <div v-else>
                                        <div v-for="(item, itemIndex) in session.items" :key="item.id" 
                                             class="flex items-center justify-between p-2 bg-white rounded border border-slate-200"
                                             :class="{
                                                 'cursor-pointer hover:bg-slate-50': item.can_access,
                                                 'cursor-not-allowed opacity-60': !item.can_access
                                             }"
                                             @click="handleSessionItemClick(item, session)">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-white text-xs font-semibold"
                                                 :class="{
                                                     'bg-green-500': item.can_access,
                                                     'bg-gray-400': !item.can_access
                                                 }">
                                                {{ itemIndex + 1 }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-slate-800">{{ item.title }}</div>
                                                <div class="text-xs text-slate-600">
                                                    {{ item.item_type }} • {{ item.estimated_duration_minutes }} menit
                                                    <span v-if="item.is_required" class="ml-2 text-red-600">*</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <!-- Quiz completed status -->
                                            <span v-if="item.item_type === 'quiz' && item.is_completed && item.completion_status" 
                                                  class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                                <i class="fa-solid fa-trophy mr-1"></i>
                                                Selesai ({{ item.completion_status.score }}%)
                                            </span>
                                            <!-- Material completed status -->
                                            <span v-else-if="item.item_type === 'material' && item.is_completed && item.completion_status" 
                                                  class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                                <i class="fa-solid fa-check-circle mr-1"></i>
                                                Selesai
                                            </span>
                                            <!-- Quiz accessible but not completed -->
                                            <span v-else-if="item.item_type === 'quiz' && item.can_access && !item.is_completed" 
                                                  class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                                <i class="fa-solid fa-play mr-1"></i>Mulai Quiz
                                            </span>
                                            <!-- Material accessible but not completed -->
                                            <span v-else-if="item.item_type === 'material' && item.can_access && !item.is_completed" 
                                                  class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                                <i class="fa-solid fa-file mr-1"></i>Buka Material
                                            </span>
                                            <!-- Other items accessible -->
                                            <span v-else-if="item.can_access" class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                                <i class="fa-solid fa-check mr-1"></i>Dapat Diakses
                                            </span>
                                            <!-- Items locked -->
                                            <span v-else class="px-2 py-1 bg-gray-100 text-gray-800 text-xs rounded-full">
                                                <i class="fa-solid fa-lock mr-1"></i>Terkunci
                                            </span>
                                        </div>
                                    </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Sessions -->
                    <div v-else class="text-center py-8 text-slate-500">
                        <i class="fa-solid fa-book-open text-4xl mb-2"></i>
                        <p>Belum ada sesi training yang tersedia</p>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex justify-between gap-3 mt-6 pt-4 border-t border-slate-200">
                    <div class="flex gap-2">
                        <!-- Check-in Button (show if not checked in) -->
                        <button v-if="!selectedTrainingDetail.check_in_time" 
                                @click="openTrainingCheckInModal" 
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            <i class="fa-solid fa-qrcode mr-2"></i>
                            Check-in QR Code
                        </button>
                        
                        <!-- Check-out Button (show if checked in but not checked out AND review already given) -->
                        <button v-if="selectedTrainingDetail.check_in_time && !selectedTrainingDetail.check_out_time && !selectedTrainingDetail.can_give_feedback" 
                                @click="openTrainingCheckOutModal" 
                                class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors">
                            <i class="fa-solid fa-sign-out-alt mr-2"></i>
                            Check-out QR Code
                        </button>
                        
                        <!-- Review Button (show if all items completed and can give feedback) -->
                        <button v-if="allSessionItemsCompleted && selectedTrainingDetail.can_give_feedback" 
                                @click="openTrainingReviewModal" 
                                class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors">
                            <i class="fa-solid fa-star mr-2"></i>
                            Berikan Review
                        </button>
                        
                        <!-- Review Button (show if checked out and can give feedback) - fallback -->
                        <button v-else-if="selectedTrainingDetail.check_out_time && selectedTrainingDetail.can_give_feedback" 
                                @click="openTrainingReviewModal" 
                                class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors">
                            <i class="fa-solid fa-star mr-2"></i>
                            Berikan Review
                        </button>
                        
                        <!-- Message: Review required before check-out -->
                        <div v-if="selectedTrainingDetail.check_in_time && !selectedTrainingDetail.check_out_time && selectedTrainingDetail.can_give_feedback" 
                             class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded-md border border-yellow-200 flex items-center">
                            <i class="fa-solid fa-exclamation-triangle mr-2"></i>
                            Berikan review terlebih dahulu sebelum check-out
                        </div>
                        
                        <!-- Review Already Given (show if checked out but already reviewed) -->
                        <div v-if="selectedTrainingDetail.check_out_time && !selectedTrainingDetail.can_give_feedback" 
                             class="px-4 py-2 bg-green-100 text-green-800 rounded-md border border-green-200 flex items-center">
                            <i class="fa-solid fa-check-circle mr-2"></i>
                            Review Sudah Diberikan
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button @click="closeTrainingDetailModal" 
                                class="px-4 py-2 text-slate-600 border border-slate-300 rounded-md hover:bg-slate-50 transition-colors">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Training Check-in QR Code Modal -->
        <div v-if="showTrainingCheckInModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeTrainingCheckInModal">
            <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">Check-in Training</h3>
                    <button @click="closeTrainingCheckInModal" class="text-slate-500 hover:text-slate-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Training Info -->
                    <div v-if="selectedTrainingDetail" class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                        <h4 class="text-md font-semibold text-slate-800 mb-2">{{ selectedTrainingDetail.course_title }}</h4>
                        <div class="text-sm text-slate-600 space-y-1">
                            <div>{{ new Date(selectedTrainingDetail.scheduled_date).toLocaleDateString('id-ID') }}</div>
                            <div>{{ selectedTrainingDetail.start_time }} - {{ selectedTrainingDetail.end_time }}</div>
                            <div>{{ selectedTrainingDetail.outlet_name }}</div>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="text-xs font-medium">Status Training:</span>
                                <span class="px-2 py-1 text-xs rounded-full" :class="{
                                    'bg-green-100 text-green-800': selectedTrainingDetail.training_status === 'ongoing',
                                    'bg-yellow-100 text-yellow-800': selectedTrainingDetail.training_status === 'scheduled',
                                    'bg-red-100 text-red-800': selectedTrainingDetail.training_status === 'completed',
                                    'bg-gray-100 text-gray-800': !selectedTrainingDetail.training_status
                                }">
                                    {{ selectedTrainingDetail.training_status || 'Unknown' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code Input -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">QR Code Training</label>
                        <div class="flex gap-2">
                            <input 
                                v-model="qrCodeInput" 
                                type="text" 
                                placeholder="Scan atau masukkan QR Code training..."
                                class="flex-1 px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                @keyup.enter="processTrainingCheckIn"
                            />
                            <button 
                                @click="showCamera = true" 
                                class="bg-green-500 text-white px-3 py-2 rounded-md hover:bg-green-600 transition-colors"
                                :disabled="isProcessingCheckIn"
                            >
                                <i class="fa-solid fa-camera"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Status Message -->
                    <div v-if="checkInStatusMessage" class="p-3 rounded-lg" :class="{
                        'bg-green-50 text-green-800 border border-green-200': checkInStatusMessage.includes('Berhasil') || checkInStatusMessage.includes('berhasil'),
                        'bg-red-50 text-red-800 border border-red-200': checkInStatusMessage.includes('Error') || checkInStatusMessage.includes('Gagal') || checkInStatusMessage.includes('gagal'),
                        'bg-blue-50 text-blue-800 border border-blue-200': !checkInStatusMessage.includes('Berhasil') && !checkInStatusMessage.includes('Error') && !checkInStatusMessage.includes('Gagal')
                    }">
                        <div class="flex items-center gap-2">
                            <i v-if="checkInStatusMessage.includes('Berhasil') || checkInStatusMessage.includes('berhasil')" class="fa-solid fa-check-circle"></i>
                            <i v-else-if="checkInStatusMessage.includes('Error') || checkInStatusMessage.includes('Gagal') || checkInStatusMessage.includes('gagal')" class="fa-solid fa-exclamation-circle"></i>
                            <i v-else class="fa-solid fa-info-circle"></i>
                            <span class="text-sm font-medium">{{ checkInStatusMessage }}</span>
                        </div>
                    </div>

                    <!-- Warning Message for Non-Ongoing Training -->
                    <div v-if="selectedTrainingDetail && selectedTrainingDetail.training_status !== 'ongoing'" class="p-3 rounded-lg bg-yellow-50 text-yellow-800 border border-yellow-200">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-exclamation-triangle"></i>
                            <span class="text-sm font-medium">
                                Check-in hanya bisa dilakukan saat training berstatus "ongoing". 
                                Status saat ini: {{ selectedTrainingDetail.training_status || 'Unknown' }}
                            </span>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h5 class="font-semibold text-blue-800 mb-2">Cara Check-in:</h5>
                        <ul class="text-sm text-blue-700 space-y-1">
                            <li>• Scan QR Code training dengan kamera</li>
                            <li>• Atau masukkan QR Code secara manual</li>
                            <li>• Pastikan Anda sudah terdaftar sebagai peserta training</li>
                            <li>• Check-in hanya bisa dilakukan saat training berstatus "ongoing"</li>
                        </ul>
                    </div>

                    <!-- Debug Info (only in development) -->
                    <div v-if="isDevelopment" class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <h6 class="font-semibold text-gray-800 mb-2">Debug Info:</h6>
                        <div class="text-xs text-gray-600 space-y-1">
                            <div>User ID: {{ user.id }}</div>
                            <div>User Name: {{ user.nama_lengkap }}</div>
                            <div>Training ID: {{ selectedTrainingDetail?.schedule_id }}</div>
                            <div>QR Code: {{ qrCodeInput.substring(0, 50) }}...</div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3">
                        <button 
                            @click="processTrainingCheckIn" 
                            :disabled="!qrCodeInput.trim() || isProcessingCheckIn || selectedTrainingDetail?.training_status !== 'ongoing'"
                            class="flex-1 px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <i v-if="isProcessingCheckIn" class="fa-solid fa-spinner fa-spin mr-2"></i>
                            <i v-else class="fa-solid fa-check mr-2"></i>
                            {{ isProcessingCheckIn ? 'Memproses...' : 'Check-in' }}
                        </button>
                        <button 
                            @click="closeTrainingCheckInModal" 
                            class="px-4 py-2 text-slate-600 border border-slate-300 rounded-md hover:bg-slate-50 transition-colors"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Scanner Camera Modal -->
        <div v-if="showCamera" class="fixed inset-0 z-[60] flex items-center justify-center bg-black bg-opacity-40">
            <div class="bg-white rounded-xl shadow-lg p-6 relative w-full max-w-md">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-slate-800">Scan QR Code</h4>
                    <button @click="closeCamera" class="text-slate-500 hover:text-slate-700">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
                
                <div v-if="cameras.length > 1" class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Pilih Kamera</label>
                    <select v-model="selectedCameraId" @change="switchCamera" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option v-for="cam in cameras" :key="cam.id" :value="cam.id">{{ cam.label }}</option>
                    </select>
                </div>
                
                <div id="training-qr-reader" style="width: 100%"></div>
                
                <div class="mt-4 text-center">
                    <button @click="closeCamera" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                        Tutup Kamera
                    </button>
                </div>
            </div>
        </div>

        <!-- Training Check-out QR Code Modal -->
        <div v-if="showTrainingCheckOutModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeTrainingCheckOutModal">
            <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">Check-out Training</h3>
                    <button @click="closeTrainingCheckOutModal" class="text-slate-500 hover:text-slate-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Training Info -->
                    <div v-if="selectedTrainingDetail" class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                        <h4 class="text-md font-semibold text-slate-800 mb-2">{{ selectedTrainingDetail.course_title }}</h4>
                        <div class="text-sm text-slate-600 space-y-1">
                            <div>{{ new Date(selectedTrainingDetail.scheduled_date).toLocaleDateString('id-ID') }}</div>
                            <div>{{ selectedTrainingDetail.start_time }} - {{ selectedTrainingDetail.end_time }}</div>
                            <div>{{ selectedTrainingDetail.outlet_name }}</div>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="text-xs font-medium">Status Training:</span>
                                <span class="px-2 py-1 text-xs rounded-full" :class="{
                                    'bg-green-100 text-green-800': selectedTrainingDetail.training_status === 'ongoing',
                                    'bg-yellow-100 text-yellow-800': selectedTrainingDetail.training_status === 'scheduled',
                                    'bg-red-100 text-red-800': selectedTrainingDetail.training_status === 'completed',
                                    'bg-gray-100 text-gray-800': !selectedTrainingDetail.training_status
                                }">
                                    {{ selectedTrainingDetail.training_status || 'Unknown' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- QR Code Input -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">QR Code Training</label>
                        <div class="flex gap-2">
                            <input 
                                v-model="qrCodeCheckOutInput" 
                                type="text" 
                                placeholder="Scan atau masukkan QR Code training..."
                                class="flex-1 px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                @keyup.enter="processTrainingCheckOut"
                            />
                            <button 
                                @click="showCheckOutCamera = true" 
                                class="bg-orange-500 text-white px-3 py-2 rounded-md hover:bg-orange-600 transition-colors"
                                :disabled="isProcessingCheckOut"
                            >
                                <i class="fa-solid fa-camera"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Status Message -->
                    <div v-if="checkOutStatusMessage" class="p-3 rounded-lg" :class="{
                        'bg-green-50 text-green-800 border border-green-200': checkOutStatusMessage.includes('Berhasil') || checkOutStatusMessage.includes('berhasil'),
                        'bg-red-50 text-red-800 border border-red-200': checkOutStatusMessage.includes('Error') || checkOutStatusMessage.includes('Gagal') || checkOutStatusMessage.includes('gagal'),
                        'bg-blue-50 text-blue-800 border border-blue-200': !checkOutStatusMessage.includes('Berhasil') && !checkOutStatusMessage.includes('Error') && !checkOutStatusMessage.includes('Gagal')
                    }">
                        <div class="flex items-center gap-2">
                            <i v-if="checkOutStatusMessage.includes('Berhasil') || checkOutStatusMessage.includes('berhasil')" class="fa-solid fa-check-circle"></i>
                            <i v-else-if="checkOutStatusMessage.includes('Error') || checkOutStatusMessage.includes('Gagal') || checkOutStatusMessage.includes('gagal')" class="fa-solid fa-exclamation-circle"></i>
                            <i v-else class="fa-solid fa-info-circle"></i>
                            <span class="text-sm font-medium">{{ checkOutStatusMessage }}</span>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                        <h5 class="font-semibold text-orange-800 mb-2">Cara Check-out:</h5>
                        <ul class="text-sm text-orange-700 space-y-1">
                            <li>• Scan QR Code training dengan kamera</li>
                            <li>• Atau masukkan QR Code secara manual</li>
                            <li>• Pastikan Anda sudah check-in sebelumnya</li>
                            <li>• Check-out menandai selesainya kehadiran training</li>
                        </ul>
                    </div>

                    <!-- Debug Info (only in development) -->
                    <div v-if="isDevelopment" class="bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <h6 class="font-semibold text-gray-800 mb-2">Debug Info:</h6>
                        <div class="text-xs text-gray-600 space-y-1">
                            <div>User ID: {{ user.id }}</div>
                            <div>User Name: {{ user.nama_lengkap }}</div>
                            <div>Training ID: {{ selectedTrainingDetail?.schedule_id }}</div>
                            <div>QR Code: {{ qrCodeCheckOutInput.substring(0, 50) }}...</div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3">
                        <button 
                            @click="processTrainingCheckOut" 
                            :disabled="!qrCodeCheckOutInput.trim() || isProcessingCheckOut"
                            class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <i v-if="isProcessingCheckOut" class="fa-solid fa-spinner fa-spin mr-2"></i>
                            <i v-else class="fa-solid fa-sign-out-alt mr-2"></i>
                            {{ isProcessingCheckOut ? 'Memproses...' : 'Check-out' }}
                        </button>
                        <button 
                            @click="closeTrainingCheckOutModal" 
                            class="px-4 py-2 text-slate-600 border border-slate-300 rounded-md hover:bg-slate-50 transition-colors"
                        >
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Check-out QR Scanner Camera Modal -->
        <div v-if="showCheckOutCamera" class="fixed inset-0 z-[60] flex items-center justify-center bg-black bg-opacity-40">
            <div class="bg-white rounded-xl shadow-lg p-6 relative w-full max-w-md">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-semibold text-slate-800">Scan QR Code Check-out</h4>
                    <button @click="showCheckOutCamera = false" class="text-slate-500 hover:text-slate-700">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
                
                <div v-if="checkOutCameras.length > 1" class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Pilih Kamera</label>
                    <select v-model="selectedCheckOutCameraId" @change="switchCheckOutCamera" class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option v-for="cam in checkOutCameras" :key="cam.id" :value="cam.id">{{ cam.label }}</option>
                    </select>
                </div>
                
                <div id="training-checkout-qr-reader" style="width: 100%"></div>
                
                <div class="mt-4 text-center">
                    <button @click="showCheckOutCamera = false" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                        Tutup Kamera
                    </button>
                </div>
            </div>
        </div>

        <!-- Training Review Modal -->
        <div v-if="showTrainingReviewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeTrainingReviewModal">
            <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-slate-800">Review Training</h3>
                    <button @click="closeTrainingReviewModal" class="text-slate-500 hover:text-slate-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="space-y-6">
                    <!-- Training Info -->
                    <div v-if="selectedTrainingDetail" class="bg-slate-50 p-4 rounded-lg border border-slate-200">
                        <h4 class="text-lg font-semibold text-slate-800 mb-2">{{ selectedTrainingDetail.course_title }}</h4>
                        <div class="text-sm text-slate-600 space-y-1">
                            <div>{{ new Date(selectedTrainingDetail.scheduled_date).toLocaleDateString('id-ID') }}</div>
                            <div>{{ selectedTrainingDetail.start_time }} - {{ selectedTrainingDetail.end_time }}</div>
                            <div>{{ selectedTrainingDetail.outlet_name }}</div>
                            <div v-if="selectedTrainingDetail.trainers && selectedTrainingDetail.trainers.length > 0" class="mt-2">
                                <span class="font-medium">Trainer:</span>
                                <span v-for="(trainer, index) in selectedTrainingDetail.trainers" :key="trainer.id">
                                    {{ trainer.trainer_name }}{{ index < selectedTrainingDetail.trainers.length - 1 ? ', ' : '' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Trainer Section -->
                    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                        <h4 class="text-lg font-semibold text-blue-800 mb-4 flex items-center">
                            <i class="fa-solid fa-chalkboard-teacher mr-2"></i>
                            Penilaian Trainer
                        </h4>
                        
                        <!-- Penguasaan Terhadap Materi -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Penguasaan Terhadap Materi *</label>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-slate-600">Buruk</span>
                                <div class="flex space-x-1">
                                    <button v-for="rating in 5" :key="rating" 
                                            @click="reviewForm.trainer_mastery = rating"
                                            class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition-colors"
                                            :class="{
                                                'bg-blue-400 border-blue-400 text-white': rating <= reviewForm.trainer_mastery,
                                                'bg-white border-slate-300 text-slate-400 hover:border-blue-300': rating > reviewForm.trainer_mastery
                                            }">
                                        <i class="fa-solid fa-star text-xs"></i>
                                    </button>
                                </div>
                                <span class="text-sm text-slate-600">Sangat Baik</span>
                            </div>
                        </div>

                        <!-- Penggunaan bahasa yang mudah dipahami -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Penggunaan bahasa yang mudah dipahami *</label>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-slate-600">Buruk</span>
                                <div class="flex space-x-1">
                                    <button v-for="rating in 5" :key="rating" 
                                            @click="reviewForm.trainer_language = rating"
                                            class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition-colors"
                                            :class="{
                                                'bg-blue-400 border-blue-400 text-white': rating <= reviewForm.trainer_language,
                                                'bg-white border-slate-300 text-slate-400 hover:border-blue-300': rating > reviewForm.trainer_language
                                            }">
                                        <i class="fa-solid fa-star text-xs"></i>
                                    </button>
                                </div>
                                <span class="text-sm text-slate-600">Sangat Baik</span>
                            </div>
                        </div>

                        <!-- Intonasi dan nada suara -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Intonasi dan nada suara *</label>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-slate-600">Buruk</span>
                                <div class="flex space-x-1">
                                    <button v-for="rating in 5" :key="rating" 
                                            @click="reviewForm.trainer_intonation = rating"
                                            class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition-colors"
                                            :class="{
                                                'bg-blue-400 border-blue-400 text-white': rating <= reviewForm.trainer_intonation,
                                                'bg-white border-slate-300 text-slate-400 hover:border-blue-300': rating > reviewForm.trainer_intonation
                                            }">
                                        <i class="fa-solid fa-star text-xs"></i>
                                    </button>
                                </div>
                                <span class="text-sm text-slate-600">Sangat Baik</span>
                            </div>
                        </div>

                        <!-- Gaya penyampaian yang menarik -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Gaya penyampaian yang menarik *</label>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-slate-600">Buruk</span>
                                <div class="flex space-x-1">
                                    <button v-for="rating in 5" :key="rating" 
                                            @click="reviewForm.trainer_presentation = rating"
                                            class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition-colors"
                                            :class="{
                                                'bg-blue-400 border-blue-400 text-white': rating <= reviewForm.trainer_presentation,
                                                'bg-white border-slate-300 text-slate-400 hover:border-blue-300': rating > reviewForm.trainer_presentation
                                            }">
                                        <i class="fa-solid fa-star text-xs"></i>
                                    </button>
                                </div>
                                <span class="text-sm text-slate-600">Sangat Baik</span>
                            </div>
                        </div>

                        <!-- Kesempatan Tanya Jawab & Diskusi -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Kesempatan Tanya Jawab & Diskusi *</label>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-slate-600">Buruk</span>
                                <div class="flex space-x-1">
                                    <button v-for="rating in 5" :key="rating" 
                                            @click="reviewForm.trainer_qna = rating"
                                            class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition-colors"
                                            :class="{
                                                'bg-blue-400 border-blue-400 text-white': rating <= reviewForm.trainer_qna,
                                                'bg-white border-slate-300 text-slate-400 hover:border-blue-300': rating > reviewForm.trainer_qna
                                            }">
                                        <i class="fa-solid fa-star text-xs"></i>
                                    </button>
                                </div>
                                <span class="text-sm text-slate-600">Sangat Baik</span>
                            </div>
                        </div>

                    </div>

                    <!-- Training Material Section -->
                    <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                        <h4 class="text-lg font-semibold text-green-800 mb-4 flex items-center">
                            <i class="fa-solid fa-book mr-2"></i>
                            Penilaian Materi Training
                        </h4>
                        
                        <!-- Manfaat training bagi pekerjaan / kehidupan -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Manfaat training bagi pekerjaan / kehidupan *</label>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-slate-600">Buruk</span>
                                <div class="flex space-x-1">
                                    <button v-for="rating in 5" :key="rating" 
                                            @click="reviewForm.material_benefit = rating"
                                            class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition-colors"
                                            :class="{
                                                'bg-green-400 border-green-400 text-white': rating <= reviewForm.material_benefit,
                                                'bg-white border-slate-300 text-slate-400 hover:border-green-300': rating > reviewForm.material_benefit
                                            }">
                                        <i class="fa-solid fa-star text-xs"></i>
                                    </button>
                                </div>
                                <span class="text-sm text-slate-600">Sangat Baik</span>
                            </div>
                        </div>

                        <!-- Kejelasan & kelengkapan materi -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Kejelasan & kelengkapan materi *</label>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-slate-600">Buruk</span>
                                <div class="flex space-x-1">
                                    <button v-for="rating in 5" :key="rating" 
                                            @click="reviewForm.material_clarity = rating"
                                            class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition-colors"
                                            :class="{
                                                'bg-green-400 border-green-400 text-white': rating <= reviewForm.material_clarity,
                                                'bg-white border-slate-300 text-slate-400 hover:border-green-300': rating > reviewForm.material_clarity
                                            }">
                                        <i class="fa-solid fa-star text-xs"></i>
                                    </button>
                                </div>
                                <span class="text-sm text-slate-600">Sangat Baik</span>
                            </div>
                        </div>

                        <!-- Tampilan materi pelatihan -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Tampilan materi pelatihan *</label>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-slate-600">Buruk</span>
                                <div class="flex space-x-1">
                                    <button v-for="rating in 5" :key="rating" 
                                            @click="reviewForm.material_display = rating"
                                            class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition-colors"
                                            :class="{
                                                'bg-green-400 border-green-400 text-white': rating <= reviewForm.material_display,
                                                'bg-white border-slate-300 text-slate-400 hover:border-green-300': rating > reviewForm.material_display
                                            }">
                                        <i class="fa-solid fa-star text-xs"></i>
                                    </button>
                                </div>
                                <span class="text-sm text-slate-600">Sangat Baik</span>
                            </div>
                        </div>

                        <!-- Saran dan perbaikan untuk kualitas Pemateri dan Materi -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Saran dan perbaikan untuk kualitas Pemateri dan Materi</label>
                            <textarea v-model="reviewForm.material_suggestions" 
                                      rows="3" 
                                      placeholder="Bagikan saran untuk perbaikan trainer dan materi..."
                                      class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                        </div>

                        <!-- Materi yang dibutuhkan untuk menunjang pekerjaan/pribadi -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Materi yang dibutuhkan untuk menunjang pekerjaan/pribadi</label>
                            <textarea v-model="reviewForm.material_needs" 
                                      rows="3" 
                                      placeholder="Jelaskan materi apa yang Anda butuhkan untuk menunjang pekerjaan atau kehidupan pribadi..."
                                      class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                        </div>
                    </div>


                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4 border-t border-slate-200">
                        <button @click="submitTrainingReview" 
                                :disabled="isSubmittingReview"
                                class="flex-1 px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            <i v-if="isSubmittingReview" class="fa-solid fa-spinner fa-spin mr-2"></i>
                            <i v-else class="fa-solid fa-paper-plane mr-2"></i>
                            {{ isSubmittingReview ? 'Mengirim...' : 'Kirim Review' }}
                        </button>
                        <button @click="closeTrainingReviewModal" 
                                class="px-4 py-2 text-slate-600 border border-slate-300 rounded-md hover:bg-slate-50 transition-colors">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Training History Modal -->
        <div v-if="showTrainingHistoryModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeTrainingHistoryModal">
            <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-semibold text-slate-800">Training History</h3>
                    <button @click="closeTrainingHistoryModal" class="text-slate-500 hover:text-slate-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Loading State -->
                    <div v-if="loadingTrainingHistory" class="text-center py-8">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-gray-500"></div>
                        <p class="text-sm mt-2 text-gray-600">Memuat riwayat training...</p>
                    </div>

                    <!-- Empty State -->
                    <div v-else-if="trainingHistory.length === 0" class="text-center py-8">
                        <div class="mb-4 text-gray-400">
                            <i class="fa-solid fa-graduation-cap text-6xl"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-600 mb-2">Belum Ada Training History</h4>
                        <p class="text-gray-500">Anda belum menyelesaikan training apapun. Selesaikan training dan berikan review untuk melihat riwayat di sini.</p>
                    </div>

                    <!-- Training History List -->
                    <div v-else class="space-y-4">
                        <div v-for="training in trainingHistory" :key="training.schedule_id" 
                             class="border border-slate-200 rounded-lg p-4 bg-slate-50 hover:bg-slate-100 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                                            <i class="fa-solid fa-check"></i>
                                        </div>
                                        <div>
                                            <h4 class="text-lg font-semibold text-slate-800">{{ training.course_title }}</h4>
                                            <div class="text-sm text-slate-600">
                                                {{ new Date(training.scheduled_date).toLocaleDateString('id-ID') }} • 
                                                {{ training.start_time }} - {{ training.end_time }}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="ml-13 space-y-2">
                                        <div class="text-sm text-slate-600">
                                            <i class="fa-solid fa-map-marker-alt mr-2"></i>
                                            {{ training.nama_outlet }}
                                        </div>
                                        
                                        <div class="text-sm text-slate-600">
                                            <i class="fa-solid fa-chalkboard-teacher mr-2"></i>
                                            Trainer: {{ training.trainer_name }}
                                        </div>
                                        
                                        <div v-if="training.type" class="text-sm text-slate-600">
                                            <i class="fa-solid fa-tag mr-2"></i>
                                            Tipe: {{ training.type }}
                                        </div>
                                        
                                        <div v-if="training.specification" class="text-sm text-slate-600">
                                            <i class="fa-solid fa-cog mr-2"></i>
                                            Spesifikasi: {{ training.specification }}
                                        </div>
                                        
                                        <div v-if="training.course_type" class="text-sm text-slate-600">
                                            <i class="fa-solid fa-graduation-cap mr-2"></i>
                                            Jenis: {{ training.course_type }}
                                        </div>
                                        
                                        <div class="text-sm text-slate-600">
                                            <i class="fa-solid fa-sign-in-alt mr-2"></i>
                                            Check-in: {{ new Date(training.check_in_time).toLocaleString('id-ID') }}
                                        </div>
                                        
                                        <div class="text-sm text-slate-600">
                                            <i class="fa-solid fa-sign-out-alt mr-2"></i>
                                            Check-out: {{ new Date(training.check_out_time).toLocaleString('id-ID') }}
                                        </div>
                                        
                                        <div class="text-sm text-slate-600">
                                            <i class="fa-solid fa-star mr-2"></i>
                                            Review: {{ new Date(training.review_date).toLocaleString('id-ID') }}
                                        </div>
                                        
                                        <!-- Certificate Section -->
                                        <div v-if="training.certificate" class="text-sm text-slate-600">
                                            <i class="fa-solid fa-certificate mr-2 text-yellow-500"></i>
                                            Sertifikat: {{ training.certificate.certificate_number }}
                                            <div class="flex items-center gap-2 mt-1">
                                                <button @click="previewCertificate(training.certificate)" 
                                                        class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600 transition-colors">
                                                    <i class="fa-solid fa-eye mr-1"></i>
                                                    Preview
                                                </button>
                                                <button @click="downloadCertificate(training.certificate)" 
                                                        class="text-xs bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600 transition-colors">
                                                    <i class="fa-solid fa-download mr-1"></i>
                                                    Download
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Training Materials Section -->
                                    <div v-if="training.sessions && training.sessions.length > 0" class="mt-4 ml-13">
                                        <div class="flex items-center gap-2 mb-3">
                                            <i class="fa-solid fa-book text-blue-500"></i>
                                            <h5 class="text-sm font-semibold text-slate-700">Materi Training</h5>
                                        </div>
                                        
                                        <div class="space-y-3">
                                            <div v-for="session in training.sessions" :key="session.id" 
                                                 class="bg-white rounded-lg border border-slate-200 p-3">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <div class="w-6 h-6 bg-blue-500 rounded-full flex items-center justify-center text-white text-xs font-semibold">
                                                        {{ session.session_number }}
                                                    </div>
                                                    <h6 class="text-sm font-medium text-slate-800">{{ session.session_title }}</h6>
                                                </div>
                                                
                                                <!-- Session Items -->
                                                <div v-if="session.items && session.items.length > 0" class="ml-8 space-y-2">
                                                    <!-- For trainers: only show materials -->
                                                    <div v-if="isTrainer">
                                                        <div v-for="item in session.items.filter(item => item.item_type === 'material')" :key="item.id" 
                                                             class="flex items-center justify-between p-2 bg-slate-50 rounded border border-slate-100"
                                                             :class="{
                                                                 'cursor-pointer hover:bg-slate-100': item.can_access,
                                                                 'cursor-not-allowed opacity-60': !item.can_access
                                                             }"
                                                             @click="item.can_access ? handleMaterialItemClick(item, session) : null">
                                                            <div class="flex items-center space-x-3">
                                                                <div class="w-5 h-5 rounded-full flex items-center justify-center text-white text-xs font-semibold bg-blue-500">
                                                                    <i class="fa-solid fa-file text-xs"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="text-sm font-medium text-slate-800">{{ item.title }}</div>
                                                                    <div class="text-xs text-slate-600">
                                                                        Material • {{ item.estimated_duration_minutes }} menit
                                                                        <span v-if="item.is_required" class="ml-2 text-red-600">*</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <div class="flex items-center space-x-2">
                                                                <!-- Material completed status for trainer -->
                                                                <span v-if="item.is_completed && item.completion_status" 
                                                                      class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                                                    <i class="fa-solid fa-check-circle mr-1"></i>
                                                                    Selesai
                                                                </span>
                                                                <!-- Material accessible but not completed -->
                                                                <span v-else-if="item.can_access" class="text-xs text-green-600 font-medium">
                                                                    <i class="fa-solid fa-eye mr-1"></i>
                                                                    Lihat Materi
                                                                </span>
                                                                <!-- Material locked -->
                                                                <span v-else class="text-xs text-gray-500 font-medium">
                                                                    <i class="fa-solid fa-lock mr-1"></i>
                                                                    Terkunci
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- For regular participants: show all items -->
                                                    <div v-else>
                                                        <div v-for="item in session.items" :key="item.id" 
                                                             class="flex items-center justify-between p-2 bg-slate-50 rounded border border-slate-100"
                                                             :class="{
                                                                 'cursor-pointer hover:bg-slate-100': item.can_access && item.item_type === 'material',
                                                                 'cursor-not-allowed opacity-60': !item.can_access
                                                             }"
                                                             @click="item.can_access && item.item_type === 'material' ? handleMaterialItemClick(item, session) : null">
                                                        <div class="flex items-center space-x-3">
                                                            <div class="w-5 h-5 rounded-full flex items-center justify-center text-white text-xs font-semibold"
                                                                 :class="{
                                                                     'bg-green-500': item.can_access && item.item_type === 'material',
                                                                     'bg-blue-500': item.item_type === 'quiz',
                                                                     'bg-purple-500': item.item_type === 'questionnaire',
                                                                     'bg-gray-400': !item.can_access
                                                                 }">
                                                                <i v-if="item.item_type === 'material'" class="fa-solid fa-file text-xs"></i>
                                                                <i v-else-if="item.item_type === 'quiz'" class="fa-solid fa-question text-xs"></i>
                                                                <i v-else-if="item.item_type === 'questionnaire'" class="fa-solid fa-clipboard-list text-xs"></i>
                                                                <i v-else class="fa-solid fa-circle text-xs"></i>
                                                            </div>
                                                            <div>
                                                                <div class="text-sm font-medium text-slate-800">{{ item.title }}</div>
                                                                <div class="text-xs text-slate-600">
                                                                    {{ item.item_type }} • {{ item.estimated_duration_minutes }} menit
                                                                    <span v-if="item.is_required" class="ml-2 text-red-600">*</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div v-if="item.can_access && item.item_type === 'material'" class="flex items-center space-x-2">
                                                            <!-- Material completed status -->
                                                            <span v-if="item.is_completed && item.completion_status" 
                                                                  class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                                                <i class="fa-solid fa-check-circle mr-1"></i>
                                                                Selesai
                                                            </span>
                                                            <!-- Material not completed -->
                                                            <span v-else class="text-xs text-green-600 font-medium">
                                                                <i class="fa-solid fa-download mr-1"></i>
                                                                Buka Materi
                                                            </span>
                                                        </div>
                                                    </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex flex-col items-end space-y-2">
                                    <!-- Training Rating -->
                                    <div class="flex items-center space-x-1">
                                        <span class="text-sm text-slate-600">Training:</span>
                                        <div class="flex space-x-1">
                                            <i v-for="star in 5" :key="star" 
                                               class="fa-solid fa-star text-sm"
                                               :class="star <= training.training_rating ? 'text-yellow-400' : 'text-gray-300'">
                                            </i>
                                        </div>
                                        <span class="text-sm font-medium text-slate-700">{{ training.training_rating }}/5</span>
                                    </div>
                                    
                                    <!-- Overall Satisfaction -->
                                    <div class="flex items-center space-x-1">
                                        <span class="text-sm text-slate-600">Kepuasan:</span>
                                        <div class="flex space-x-1">
                                            <i v-for="heart in 5" :key="heart" 
                                               class="fa-solid fa-heart text-sm"
                                               :class="heart <= training.overall_satisfaction ? 'text-red-400' : 'text-gray-300'">
                                            </i>
                                        </div>
                                        <span class="text-sm font-medium text-slate-700">{{ training.overall_satisfaction }}/5</span>
                                    </div>
                                    
                                    <!-- Status Badge -->
                                    <span class="px-3 py-1 bg-green-100 text-green-800 text-xs rounded-full font-medium">
                                        <i class="fa-solid fa-check-circle mr-1"></i>
                                        Selesai & Review
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-200">
                    <button @click="closeTrainingHistoryModal" 
                            class="px-4 py-2 text-slate-600 border border-slate-300 rounded-md hover:bg-slate-50 transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>

        <!-- Available Trainings Modal -->
        <div v-if="showAvailableTrainingsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeAvailableTrainingsModal">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-6xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-medium text-gray-900 dark:text-white">
                        Training Tersedia untuk Anda
                    </h3>
                    <button @click="closeAvailableTrainingsModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>

                <!-- User Info -->
                <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200 mb-2">Profil Anda</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <span class="text-blue-600 dark:text-blue-300">Divisi:</span>
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">{{ userDivisi }}</span>
                                </div>
                                <div>
                                    <span class="text-blue-600 dark:text-blue-300">Jabatan:</span>
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">{{ userJabatan }}</span>
                                </div>
                                <div>
                                    <span class="text-blue-600 dark:text-blue-300">Outlet:</span>
                                    <span class="ml-2 text-gray-700 dark:text-gray-300">{{ userOutlet }}</span>
                                </div>
                            </div>
                </div>

                <!-- Stats Summary -->
                        <div class="mb-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-emerald-600">{{ availableTrainingsStats.total }}</div>
                                <div class="text-sm text-emerald-600">Total Training</div>
                            </div>
                            <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">{{ availableTrainingsStats.completed }}</div>
                                <div class="text-sm text-green-600">Selesai</div>
                            </div>
                            <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                                <div class="text-2xl font-bold text-yellow-600">{{ availableTrainingsStats.invited }}</div>
                                <div class="text-sm text-yellow-600">Diundang</div>
                            </div>
                            <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="text-2xl font-bold text-gray-600">{{ availableTrainingsStats.available }}</div>
                                <div class="text-sm text-gray-600">Tersedia</div>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-if="loadingAvailableTrainings" class="text-center py-8">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-500"></div>
                            <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat training tersedia...</p>
                        </div>

                        <!-- Empty State -->
                        <div v-else-if="availableTrainings.length === 0" class="text-center py-8">
                            <div class="mb-4 text-gray-400 dark:text-gray-500">
                                <i class="fas fa-graduation-cap text-6xl"></i>
                            </div>
                            <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-400 mb-2">Tidak Ada Training Tersedia</h4>
                            <p class="text-gray-500 dark:text-gray-400">Tidak ada training yang sesuai dengan divisi, jabatan, atau outlet Anda saat ini.</p>
                        </div>

                        <!-- Training List -->
                        <div v-else class="space-y-4 max-h-96 overflow-y-auto">
                            <div v-for="training in availableTrainings" :key="training.id" 
                                 class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 hover:shadow-md transition-shadow"
                                 :class="training.is_completed ? 'opacity-75' : ''">
                                
                                <!-- Banner -->
                                <div class="w-full h-36 rounded-lg overflow-hidden bg-gray-100 shadow-lg mb-4">
                                    <img v-if="training.thumbnail_url" 
                                         :src="training.thumbnail_url" 
                                         :alt="training.title"
                                         class="w-full h-full object-contain bg-gray-50"
                                         @error="$event.target.style.display='none'">
                                    <div v-else class="w-full h-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                                        <i class="fas fa-graduation-cap text-white text-4xl"></i>
                                    </div>
                                </div>
                                
                                <!-- Training Information Below Banner -->
                                <div class="mb-4">
                                    <!-- Title and Status -->
                                    <div class="flex items-start justify-between mb-2">
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-bold text-gray-900 dark:text-white text-lg mb-1">
                                                {{ training.title }}
                                            </h4>
                                            <p v-if="training.short_description" class="text-sm text-gray-600 dark:text-gray-300">
                                                {{ training.short_description }}
                                            </p>
                                        </div>
                                        <span :class="getTrainingStatusBadge(training).class" 
                                              class="px-3 py-1 text-xs rounded-full border flex-shrink-0">
                                            <i :class="getTrainingStatusBadge(training).icon" class="mr-1"></i>
                                            {{ getTrainingStatusBadge(training).text }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Training Details -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                    <div>
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Durasi:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-white">{{ training.duration_formatted }}</span>
                                    </div>
                                    <div>
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Kategori:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-white">{{ training.category?.name || 'Umum' }}</span>
                                    </div>
                                    <div v-if="training.difficulty_level">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Tingkat Kesulitan:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-white">{{ training.difficulty_level }}</span>
                                    </div>
                                    <div v-if="training.type">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Tipe:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-white">{{ training.type }}</span>
                                    </div>
                                    <div v-if="training.specification">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Spesifikasi:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-white">{{ training.specification }}</span>
                                    </div>
                                    <div v-if="training.course_type">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Jenis Kursus:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-white">{{ training.course_type }}</span>
                                    </div>
                                </div>


                                <!-- Current Invitations -->
                                <div v-if="training.current_invitations && training.current_invitations.length > 0" class="mb-3">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Jadwal Training:</span>
                                    <div class="mt-1 space-y-1">
                                        <div v-for="invitation in training.current_invitations" :key="invitation.scheduled_date" 
                                             class="text-sm text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-700 p-2 rounded">
                                            <div class="flex items-center justify-between">
                                                <div>
                                                    {{ new Date(invitation.scheduled_date).toLocaleDateString('id-ID') }} • 
                                                    {{ invitation.start_time }} - {{ invitation.end_time }}
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span v-if="invitation.is_checked_out" class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                                        Selesai
                                                    </span>
                                                    <span v-else-if="invitation.is_checked_in" class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                                        Sedang Berlangsung
                                                    </span>
                                                    <span v-else class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full">
                                                        Diundang
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex justify-end gap-2">
                                    <button v-if="training.participation_status === 'available'" 
                                            class="px-3 py-1 bg-emerald-500 text-white text-sm rounded hover:bg-emerald-600 transition-colors">
                                        <i class="fa-solid fa-info-circle mr-1"></i>
                                        Info Detail
                                    </button>
                                    <button v-else-if="training.participation_status === 'invited'" 
                                            class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600 transition-colors">
                                        <i class="fa-solid fa-calendar mr-1"></i>
                                        Lihat Jadwal
                                    </button>
                                    <button v-else 
                                            class="px-3 py-1 bg-green-500 text-white text-sm rounded hover:bg-green-600 transition-colors">
                                        <i class="fa-solid fa-check mr-1"></i>
                                        Sudah Selesai
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- Modal Actions -->
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-slate-200">
                    <button @click="closeAvailableTrainingsModal" 
                            class="px-4 py-2 text-slate-600 border border-slate-300 rounded-md hover:bg-slate-50 transition-colors">
                        Tutup
                    </button>
                </div>
            </div>

    <!-- Lightbox for Document Images -->
    <VueEasyLightbox
        :visible="lightboxVisible"
        :imgs="lightboxImages"
        :index="lightboxIndex"
        @hide="lightboxVisible = false"
    />

    <!-- Certificate Preview Modal -->
    <div v-if="showCertificateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeCertificateModal">
        <div class="bg-white rounded-lg p-6 w-full max-w-5xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-semibold text-slate-800">
                    <i class="fas fa-certificate mr-2 text-yellow-500"></i>
                    Preview Sertifikat
                </h3>
                <button @click="closeCertificateModal" class="text-slate-500 hover:text-slate-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Certificate Preview -->
            <div class="max-w-4xl mx-auto">
                <div class="relative bg-white border-2 border-gray-300 shadow-lg overflow-hidden" 
                     style="aspect-ratio: 4/3; width: 100%;">
                    <!-- Background Image -->
                    <img v-if="selectedCertificate?.template?.background_image" 
                         :src="`/storage/${selectedCertificate.template.background_image}`"
                         alt="Certificate Background"
                         class="absolute inset-0 w-full h-full object-cover object-center">
                    
                    <!-- Certificate Content - Hardcoded Styling -->
                    <div class="relative z-10 h-full flex flex-col justify-center items-center p-8">
                        <!-- Title -->
                        <div class="text-center mb-8">
                            <h1 class="text-4xl font-bold text-gray-800 mb-2">
                                SERTIFIKAT
                            </h1>
                            <div class="w-24 h-1 bg-blue-600 mx-auto"></div>
                        </div>

                        <!-- Certificate Text -->
                        <div class="text-center space-y-6">
                            <!-- Participant Name -->
                            <div class="text-3xl font-bold text-gray-900">
                                {{ selectedCertificate?.user?.nama_lengkap || 'John Doe' }}
                            </div>

                            <!-- Course Title -->
                            <div class="text-xl text-gray-700">
                                {{ selectedCertificate?.course?.title || 'Sample Training Course' }}
                            </div>

                            <!-- Completion Date -->
                            <div class="text-lg text-gray-600">
                                {{ selectedCertificate?.issued_at ? new Date(selectedCertificate.issued_at).toLocaleDateString('id-ID', { 
                                    day: 'numeric', 
                                    month: 'long', 
                                    year: 'numeric' 
                                }) : '18 September 2025' }}
                            </div>

                            <!-- Training Location -->
                            <div class="text-base text-gray-600">
                                {{ selectedCertificate?.training_location || 'Lokasi Training' }}
                            </div>
                        </div>

                        <!-- Bottom Section -->
                        <div class="absolute bottom-8 left-0 right-0 flex justify-between items-end px-8">
                            <!-- Certificate Number -->
                            <div class="text-sm text-gray-500">
                                {{ selectedCertificate?.certificate_number || 'CERT-SAMPLE-001' }}
                            </div>

                            <!-- Instructor Name -->
                            <div class="text-base text-gray-700">
                                {{ selectedCertificate?.instructor_name || 'Jane Smith' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end gap-3 mt-6">
                <button @click="downloadCertificate(selectedCertificate)" 
                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <i class="fas fa-download mr-2"></i>
                    Download PDF
                </button>
                <button @click="closeCertificateModal" 
                        class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <!-- Training Cards - Moved to Bottom -->
    <div class="px-4 md:px-6 space-y-4 mb-6">
        <!-- Training Section (Invitations + History) -->
        <div class="flex-shrink-0 mb-4">
            <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-indigo-500 animate-pulse"></div>
                        <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                            Training
                        </h3>
                    </div>
                    <div v-if="trainingInvitations.length > 0" class="bg-indigo-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                        {{ trainingInvitations.length }}
                    </div>
                </div>
                
                <div v-if="loadingTrainingInvitations" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-500"></div>
                    <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat training...</p>
                </div>
                
                <div v-else class="space-y-2">
                    <!-- Training Invitations -->
                    <div v-if="trainingInvitations.length > 0">
                        <div v-for="invitation in trainingInvitations.slice(0, 3)" :key="'training-' + invitation.id"
                        @click="handleTrainingInvitationClick(invitation)"
                        class="p-3 rounded-lg cursor-pointer transition-all duration-200 hover:scale-105"
                        :class="{
                            // Checked-out training (orange theme)
                            'bg-orange-50 hover:bg-orange-100 border border-orange-200': invitation.check_out_time && !isNight,
                            'bg-orange-800/50 hover:bg-orange-700/50 border border-orange-600/50': invitation.check_out_time && isNight,
                            // Checked-in training (green theme)
                            'bg-green-50 hover:bg-green-100 border border-green-200': invitation.is_checked_in && !invitation.check_out_time && !isNight,
                            'bg-green-800/50 hover:bg-green-700/50 border border-green-600/50': invitation.is_checked_in && !invitation.check_out_time && isNight,
                            // Invited training (blue theme)
                            'bg-indigo-50 hover:bg-indigo-100 border border-indigo-200': !invitation.is_checked_in && !isNight,
                            'bg-slate-700/50 hover:bg-slate-600/50 border border-slate-600/50': !invitation.is_checked_in && isNight
                        }">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                        {{ invitation.course_title }}
                                    </div>
                                    <!-- Check-in/Check-out Status Badge -->
                                    <span v-if="invitation.check_out_time" 
                                          class="px-2 py-1 text-xs rounded-full bg-orange-100 text-orange-800 border border-orange-200">
                                        <i class="fa-solid fa-sign-out-alt mr-1"></i>Checked-out
                                    </span>
                                    <span v-else-if="invitation.is_checked_in" 
                                          class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800 border border-green-200">
                                        <i class="fa-solid fa-check mr-1"></i>Checked-in
                                    </span>
                                    <span v-else 
                                          class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                                        <i class="fa-solid fa-clock mr-1"></i>Invited
                                    </span>
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                    {{ invitation.role }} • {{ invitation.outlet_name }}
                                </div>
                                <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                    {{ new Date(invitation.scheduled_date).toLocaleDateString('id-ID') }} • {{ invitation.start_time }} - {{ invitation.end_time }}
                                </div>
                                <!-- Check-in/Check-out Time -->
                                <div v-if="invitation.check_in_time" 
                                     class="text-xs font-medium mt-1 space-y-1">
                                    <div class="text-green-600">
                                        <i class="fa-solid fa-sign-in-alt mr-1"></i>
                                        Check-in: {{ new Date(invitation.check_in_time).toLocaleString('id-ID') }}
                                    </div>
                                    <div v-if="invitation.check_out_time" class="text-orange-600">
                                        <i class="fa-solid fa-sign-out-alt mr-1"></i>
                                        Check-out: {{ new Date(invitation.check_out_time).toLocaleString('id-ID') }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-xs font-medium" 
                                 :class="{
                                     'text-orange-500': invitation.check_out_time,
                                     'text-green-500': invitation.is_checked_in && !invitation.check_out_time,
                                     'text-indigo-500': !invitation.is_checked_in
                                 }">
                                Klik untuk detail
                            </div>
                        </div>
                    </div>
                    </div>
                    
                    <!-- Empty State jika tidak ada training invitations -->
                    <div v-else class="text-center py-8">
                        <div class="mb-4 text-gray-400">
                            <i class="fa-solid fa-graduation-cap text-4xl"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-600 mb-2">Belum Ada Training</h4>
                        <p class="text-gray-500">Anda belum mendapat undangan training apapun.</p>
                    </div>
                    
                    <!-- Tombol untuk training history -->
                    <div class="text-center pt-2">
                        <button @click="openTrainingHistoryModal" class="text-sm bg-gray-500 text-white px-3 py-1 rounded hover:bg-gray-600 transition-colors">
                            <i class="fa-solid fa-history mr-1"></i>
                            Training History
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Trainings Section -->
        <div class="flex-shrink-0 mb-4">
            <div class="backdrop-blur-md rounded-2xl shadow-2xl border p-4 transition-all duration-500 animate-fade-in hover:shadow-3xl"
                :class="isNight ? 'bg-slate-800/90 border-slate-600/50' : 'bg-white/90 border-white/20'">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></div>
                        <h3 class="text-lg font-bold" :class="isNight ? 'text-white' : 'text-slate-800'">
                            Training Tersedia
                        </h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="bg-emerald-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                            {{ availableTrainingsStats.total }}
                        </div>
                        <button @click="openAvailableTrainingsModal" class="text-sm text-emerald-500 hover:text-emerald-700 font-medium">
                            Lihat Semua
                        </button>
                    </div>
                </div>
                
                <div v-if="loadingAvailableTrainings" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-emerald-500"></div>
                    <p class="text-sm mt-2" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Memuat training tersedia...</p>
                </div>
                
                <div v-else class="space-y-3">
                    <!-- Training Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                        <div class="text-center p-3 rounded-lg" :class="isNight ? 'bg-slate-700/50' : 'bg-emerald-50'">
                            <div class="text-lg font-bold text-emerald-600">{{ availableTrainingsStats.total }}</div>
                            <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Total</div>
                        </div>
                        <div class="text-center p-3 rounded-lg" :class="isNight ? 'bg-slate-700/50' : 'bg-green-50'">
                            <div class="text-lg font-bold text-green-600">{{ availableTrainingsStats.completed }}</div>
                            <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Selesai</div>
                        </div>
                        <div class="text-center p-3 rounded-lg" :class="isNight ? 'bg-slate-700/50' : 'bg-yellow-50'">
                            <div class="text-lg font-bold text-yellow-600">{{ availableTrainingsStats.invited }}</div>
                            <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Diundang</div>
                        </div>
                        <div class="text-center p-3 rounded-lg" :class="isNight ? 'bg-slate-700/50' : 'bg-gray-50'">
                            <div class="text-lg font-bold text-gray-600">{{ availableTrainingsStats.available }}</div>
                            <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Tersedia</div>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium" :class="isNight ? 'text-slate-300' : 'text-slate-600'">Progress Training</span>
                            <span class="text-sm font-medium text-emerald-600">{{ availableTrainingsStats.completionRate }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2" :class="isNight ? 'bg-slate-600' : 'bg-gray-200'">
                            <div class="bg-emerald-500 h-2 rounded-full transition-all duration-300" 
                                 :style="{ width: availableTrainingsStats.completionRate + '%' }"></div>
                        </div>
                    </div>
                    
                    <!-- Sample Available Trainings -->
                    <div v-if="availableTrainings.length > 0" class="space-y-2">
                        <div v-for="training in availableTrainings.slice(0, 3)" :key="'available-' + training.id"
                             class="p-3 rounded-lg border transition-all duration-200 max-w-sm mx-auto"
                             :class="[
                                 isNight ? 'bg-slate-700/50 border-slate-600/50' : 'bg-slate-50 border-slate-200',
                                 training.is_completed ? 'opacity-75' : ''
                             ]">
                            <!-- Banner -->
                            <div class="w-full h-36 rounded-lg overflow-hidden bg-gray-100 shadow-md">
                                <img v-if="training.thumbnail_url" 
                                     :src="training.thumbnail_url" 
                                     :alt="training.title"
                                     class="w-full h-full object-contain bg-gray-50"
                                     @error="$event.target.style.display='none'">
                                <div v-else class="w-full h-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                                    <i class="fas fa-graduation-cap text-white text-4xl"></i>
                                </div>
                            </div>
                            
                            <!-- Training Information Below Banner -->
                            <div class="mt-3 space-y-2">
                                <!-- Title and Status -->
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-semibold text-sm truncate" :class="isNight ? 'text-white' : 'text-slate-800'">
                                            {{ training.title }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                            {{ training.duration_formatted }} • {{ training.category?.name || 'Umum' }}
                                        </div>
                                    </div>
                                    <span :class="getTrainingStatusBadge(training).class" 
                                          class="px-2 py-1 text-xs rounded-full border flex-shrink-0">
                                        <i :class="getTrainingStatusBadge(training).icon" class="mr-1"></i>
                                        {{ getTrainingStatusBadge(training).text }}
                                    </span>
                                </div>
                                
                                <!-- Training Details -->
                                <div class="flex flex-wrap gap-2">
                                    <span v-if="training.difficulty_level" class="inline-flex items-center px-2 py-1 bg-gray-100 rounded-full text-xs">
                                        <i class="fas fa-signal mr-1"></i>{{ training.difficulty_level }}
                                    </span>
                                    <span v-if="training.type" class="inline-flex items-center px-2 py-1 bg-gray-100 rounded-full text-xs">
                                        <i class="fas fa-tag mr-1"></i>{{ training.type }}
                                    </span>
                                    <span v-if="training.specification" class="inline-flex items-center px-2 py-1 bg-gray-100 rounded-full text-xs">
                                        <i class="fas fa-cog mr-1"></i>{{ training.specification }}
                                    </span>
                                    <span v-if="training.course_type" class="inline-flex items-center px-2 py-1 bg-gray-100 rounded-full text-xs">
                                        <i class="fas fa-graduation-cap mr-1"></i>{{ training.course_type }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Show more button -->
                        <div v-if="availableTrainings.length > 3" class="text-center pt-2">
                            <button class="text-sm text-emerald-500 hover:text-emerald-700 font-medium">
                                Lihat {{ availableTrainings.length - 3 }} training lainnya...
                            </button>
                        </div>
                    </div>
                    
                    <!-- Empty State -->
                    <div v-else class="text-center py-8">
                        <div class="mb-4 text-gray-400">
                            <i class="fa-solid fa-graduation-cap text-4xl"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-gray-600 mb-2">Tidak Ada Training Tersedia</h4>
                        <p class="text-gray-500">Tidak ada training yang sesuai dengan divisi, jabatan, atau outlet Anda saat ini.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="px-4 md:px-6 py-4 text-center">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Crafted with ❤️ by IT Department-Justus Group © 2025
        </p>
    </div>

    <!-- All Approvals Modal -->
    <div v-if="showAllApprovalsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showAllApprovalsModal = false">
        <div class="bg-white dark:bg-gray-800 rounded-lg w-full max-w-4xl mx-4 max-h-[90vh] overflow-hidden" @click.stop>
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    <i class="fa-solid fa-list-check mr-2 text-blue-500"></i>
                    Semua Approval Pending
                </h3>
                <button @click="showAllApprovalsModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-140px)]">
                <!-- Loading State -->
                <div v-if="loadingAllApprovals" class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                    <p class="text-sm mt-2 text-gray-600 dark:text-gray-400">Memuat data...</p>
                </div>

                <!-- Empty State -->
                <div v-else-if="allApprovals.length === 0" class="text-center py-8">
                    <i class="fa-solid fa-check-circle text-4xl text-green-500 mb-4"></i>
                    <p class="text-gray-600 dark:text-gray-400">Tidak ada approval pending</p>
                </div>

                <!-- Approvals List -->
                <div v-else class="space-y-4">
                    <div v-for="approval in allApprovals" :key="`all-approval-${approval.type}-${approval.id}`" 
                         class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        
                        <!-- Approval Header -->
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                    <i v-if="approval.type === 'leave'" class="fa-solid fa-calendar-times text-blue-600 dark:text-blue-400"></i>
                                    <i v-else-if="approval.type === 'hrd_leave'" class="fa-solid fa-user-check text-blue-600 dark:text-blue-400"></i>
                                    <i v-else-if="approval.type === 'correction'" class="fa-solid fa-edit text-blue-600 dark:text-blue-400"></i>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-white">
                                        {{ approval.employee_name || approval.user?.nama_lengkap }}
                                    </div>
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        {{ approval.typeLabel }}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ new Date(approval.created_at || approval.tanggal).toLocaleDateString('id-ID') }}
                                </div>
                                <div class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ new Date(approval.created_at || approval.tanggal).toLocaleTimeString('id-ID') }}
                                </div>
                            </div>
                        </div>

                        <!-- Approval Details -->
                        <div class="mb-3">
                            <!-- Leave Details -->
                            <div v-if="approval.type === 'leave' || approval.type === 'hrd_leave'">
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-medium">{{ approval.leave_type?.name }}</span> • 
                                    {{ approval.duration_text }} • 
                                    <span v-if="approval.type === 'hrd_leave'" class="text-purple-600 dark:text-purple-400">HRD Approval</span>
                                    <span v-else class="text-blue-600 dark:text-blue-400">Supervisor Approval</span>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ new Date(approval.date_from).toLocaleDateString('id-ID') }} - {{ new Date(approval.date_to).toLocaleDateString('id-ID') }}
                                </div>
                            </div>

                            <!-- Correction Details -->
                            <div v-else-if="approval.type === 'correction'">
                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ approval.nama_outlet }} • {{ new Date(approval.tanggal).toLocaleDateString('id-ID') }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ formatCorrectionValue(approval) }}
                                </div>
                                
                                <!-- Detailed Correction Info -->
                                <div v-if="approval.type === 'attendance'" class="mt-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Detail Koreksi:</div>
                                    <div class="space-y-2">
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600 dark:text-gray-400">Dari:</span>
                                            <span class="text-sm font-medium text-red-600 dark:text-red-400">
                                                {{ getFormattedCorrectionTime(approval.old_value) }}
                                            </span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-sm text-gray-600 dark:text-gray-400">Ke:</span>
                                            <span class="text-sm font-medium text-green-600 dark:text-green-400">
                                                {{ getFormattedCorrectionTime(approval.new_value) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Reason Display -->
                                <div v-if="approval.reason" class="mt-2 p-2 bg-blue-50 rounded text-xs border-l-4 border-blue-400">
                                    <div class="font-medium mb-1 text-blue-700">Alasan Koreksi:</div>
                                    <div class="text-xs text-blue-600">{{ approval.reason }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex gap-2">
                            <button @click="handleApprovalAction(approval)" 
                                    class="flex-1 bg-green-500 text-white px-3 py-2 rounded-md hover:bg-green-600 transition-colors text-sm">
                                <i class="fa-solid fa-check mr-1"></i>Setujui
                            </button>
                            <button @click="handleRejectionAction(approval)" 
                                    class="flex-1 bg-red-500 text-white px-3 py-2 rounded-md hover:bg-red-600 transition-colors text-sm">
                                <i class="fa-solid fa-times mr-1"></i>Tolak
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-t border-gray-200 dark:border-gray-600">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Total: {{ allApprovals.length }} approval pending
                    </div>
                    <button @click="showAllApprovalsModal = false" 
                            class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
    </AppLayout>
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