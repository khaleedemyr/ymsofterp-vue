<script setup>
import { ref, computed, onMounted, watch, nextTick, onBeforeUnmount } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AnalogClock from '@/Components/AnalogClock.vue';
import CalendarWidget from '@/Components/CalendarWidget.vue';
import NotesWidget from '@/Components/NotesWidget.vue';
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

// Training invitations
const trainingInvitations = ref([]);
const loadingTrainingInvitations = ref(false);

// Available trainings
const availableTrainings = ref([]);
const loadingAvailableTrainings = ref(false);
const showAvailableTrainingsModal = ref(false);

// Training detail modal
const showTrainingDetailModal = ref(false);
const selectedTrainingDetail = ref(null);

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
    training_rating: 5,
    training_feedback: '',
    trainer_rating: 5,
    trainer_feedback: '',
    overall_satisfaction: 5,
    improvement_suggestions: ''
});

// Training history modal
const showTrainingHistoryModal = ref(false);
const trainingHistory = ref([]);
const loadingTrainingHistory = ref(false);

// Computed property for development mode
const isDevelopment = computed(() => {
    return import.meta.env.DEV;
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
const totalNotificationsCount = computed(() => {
    const approvalCount = pendingApprovals.value.length;
    const hrdApprovalCount = pendingHrdApprovals.value.length;
    const correctionApprovalCount = pendingCorrectionApprovals.value.length;
    const leaveNotificationCount = leaveNotifications.value.filter(n => !n.is_read && (n.type === 'leave_approved' || n.type === 'leave_rejected')).length;
    return approvalCount + hrdApprovalCount + correctionApprovalCount + leaveNotificationCount;
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

function handleTrainingInvitationClick(invitation) {
    // Set selected training detail and show modal
    selectedTrainingDetail.value = invitation;
    showTrainingDetailModal.value = true;
}

function showAllTrainingInvitations() {
    // Redirect to training schedules page
    window.open('/lms/schedules', '_blank');
}

function closeTrainingDetailModal() {
    showTrainingDetailModal.value = false;
    selectedTrainingDetail.value = null;
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
function handleMaterialItemClick(item, session) {
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
            window.open(file.file_url, '_blank');
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
    if (html5QrCode) {
        html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {});
    }
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
        training_rating: 5,
        training_feedback: '',
        trainer_rating: 5,
        trainer_feedback: '',
        overall_satisfaction: 5,
        improvement_suggestions: ''
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
            training_rating: reviewForm.value.training_rating,
            training_feedback: reviewForm.value.training_feedback,
            trainer_rating: reviewForm.value.trainer_rating,
            trainer_feedback: reviewForm.value.trainer_feedback,
            overall_satisfaction: reviewForm.value.overall_satisfaction,
            improvement_suggestions: reviewForm.value.improvement_suggestions,
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
                
                if (page.props.flash?.success) {
                    checkInStatusMessage.value = page.props.flash.success;
                    
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

                    // Close modal after success
                    setTimeout(() => {
                        closeTrainingCheckInModal();
                    }, 2000);
                } else {
                    checkInStatusMessage.value = 'Check-in berhasil tapi tidak ada data training';
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
                isProcessingCheckIn.value = false;
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
    if (approval.type === 'schedule') {
        return `Dari: ${approval.old_value} → Ke: ${approval.new_value}`;
    } else if (approval.type === 'attendance') {
        try {
            // Try to parse JSON data for new format
            const oldData = JSON.parse(approval.old_value);
            const newData = JSON.parse(approval.new_value);
            
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
            
            const inoutMode = oldData.inoutmode === 1 ? 'Masuk' : 'Keluar';
            
            return `Waktu ${inoutMode}: ${oldTime} → ${newTime}`;
        } catch (error) {
            // Fallback for old format (non-JSON)
            return `Dari: ${approval.old_value} → Ke: ${approval.new_value}`;
        }
    }
    return `Dari: ${approval.old_value} → Ke: ${approval.new_value}`;
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
    loadTrainingInvitations();
    loadAvailableTrainings();
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
                            <div v-if="user.avatar" class="w-24 h-24 rounded-full overflow-hidden border-4 border-white shadow-xl">
                                <img :src="user.avatar ? `/storage/${user.avatar}` : '/images/avatar-default.png'" alt="Avatar" class="w-full h-full object-cover" />
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
                                            {{ approval.type === 'schedule' ? 'Koreksi Schedule' : 'Koreksi Attendance' }} • {{ approval.nama_outlet }}
                                        </div>
                                        <div class="text-xs" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                            {{ new Date(approval.tanggal).toLocaleDateString('id-ID') }} • {{ formatCorrectionValue(approval) }}
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
                            
                                <!-- Tombol untuk melihat semua training -->
                                <div v-if="trainingInvitations.length > 3" class="text-center pt-2">
                                    <button class="text-sm text-indigo-500 hover:text-indigo-700 font-medium">
                                        Lihat {{ trainingInvitations.length - 3 }} training lainnya...
                                    </button>
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
                            
                            <!-- Tombol untuk melihat semua training dan history -->
                            <div class="text-center pt-2 space-y-2">
                                <button v-if="trainingInvitations.length > 0" @click="showAllTrainingInvitations" class="text-sm bg-indigo-500 text-white px-3 py-1 rounded hover:bg-indigo-600 transition-colors">
                                    <i class="fa-solid fa-graduation-cap mr-1"></i>
                                    Lihat Semua Training ({{ trainingInvitations.length }})
                                </button>
                                <div>
                                    <button @click="openTrainingHistoryModal" class="text-sm bg-gray-500 text-white px-3 py-1 rounded hover:bg-gray-600 transition-colors">
                                        <i class="fa-solid fa-history mr-1"></i>
                                        Training History
                                    </button>
                                </div>
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
                                     class="p-3 rounded-lg border transition-all duration-200"
                                     :class="[
                                         isNight ? 'bg-slate-700/50 border-slate-600/50' : 'bg-slate-50 border-slate-200',
                                         training.is_completed ? 'opacity-75' : ''
                                     ]">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <div class="font-semibold text-sm" :class="isNight ? 'text-white' : 'text-slate-800'">
                                                    {{ training.title }}
                                                </div>
                                                <span :class="getTrainingStatusBadge(training).class" 
                                                      class="px-2 py-1 text-xs rounded-full border">
                                                    <i :class="getTrainingStatusBadge(training).icon" class="mr-1"></i>
                                                    {{ getTrainingStatusBadge(training).text }}
                                                </span>
                                            </div>
                                            <div class="text-xs" :class="isNight ? 'text-slate-300' : 'text-slate-600'">
                                                {{ training.duration_formatted }} • {{ training.category?.name || 'Umum' }}
                                            </div>
                                            <div class="text-xs mt-1" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                                <span v-if="training.difficulty_level" class="mr-2">
                                                    <i class="fas fa-signal mr-1"></i>{{ training.difficulty_level }}
                                                </span>
                                                <span v-if="training.type" class="mr-2">
                                                    <i class="fas fa-tag mr-1"></i>{{ training.type }}
                                                </span>
                                                <span v-if="training.course_type" class="mr-2">
                                                    <i class="fas fa-graduation-cap mr-1"></i>{{ training.course_type }}
                                                </span>
                                            </div>
                                            <div class="text-xs mt-1" :class="isNight ? 'text-slate-400' : 'text-slate-500'">
                                                {{ getTargetDisplayText(training) }}
                                            </div>
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

                <!-- Bottom Section: Clock, Weather, Calendar, Notes, and Announcements -->
                <div class="flex-1 grid grid-cols-1 lg:grid-cols-5 gap-4 min-h-0 mb-6 items-stretch px-4 md:px-6">
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
                            <CalendarWidget />
                        </div>
                    </div>

                    <!-- Notes Widget -->
                    <div class="lg:col-span-1 flex">
                        <div class="w-full">
                            <NotesWidget />
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

            <!-- Footer -->
            <div class="flex-shrink-0 text-center text-xs opacity-90 py-4 mt-6 px-4 md:px-6" :class="isNight ? 'text-indigo-300' : 'text-indigo-500'">
                {{ t('home.powered') }} &copy; {{ new Date().getFullYear() }}
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

        <!-- Training Detail Modal -->
        <div v-if="showTrainingDetailModal && selectedTrainingDetail" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="closeTrainingDetailModal">
            <div class="bg-white rounded-lg p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-slate-800">Detail Training</h3>
                    <button @click="closeTrainingDetailModal" class="text-slate-500 hover:text-slate-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
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
                                            <!-- Quiz accessible but not completed -->
                                            <span v-else-if="item.item_type === 'quiz' && item.can_access && !item.is_completed" 
                                                  class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                                                <i class="fa-solid fa-play mr-1"></i>Mulai Quiz
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
                        
                        <!-- Check-out Button (show if checked in but not checked out) -->
                        <button v-if="selectedTrainingDetail.check_in_time && !selectedTrainingDetail.check_out_time" 
                                @click="openTrainingCheckOutModal" 
                                class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition-colors">
                            <i class="fa-solid fa-sign-out-alt mr-2"></i>
                            Check-out QR Code
                        </button>
                        
                        <!-- Review Button (show if checked out and can give feedback) -->
                        <button v-if="selectedTrainingDetail.check_out_time && selectedTrainingDetail.can_give_feedback" 
                                @click="openTrainingReviewModal" 
                                class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors">
                            <i class="fa-solid fa-star mr-2"></i>
                            Berikan Review
                        </button>
                        
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

                    <!-- Training Rating -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-3">Rating Training *</label>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-slate-600">Buruk</span>
                            <div class="flex space-x-1">
                                <button v-for="rating in 5" :key="rating" 
                                        @click="reviewForm.training_rating = rating"
                                        class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition-colors"
                                        :class="{
                                            'bg-yellow-400 border-yellow-400 text-white': rating <= reviewForm.training_rating,
                                            'bg-white border-slate-300 text-slate-400 hover:border-yellow-300': rating > reviewForm.training_rating
                                        }">
                                    <i class="fa-solid fa-star text-xs"></i>
                                </button>
                            </div>
                            <span class="text-sm text-slate-600">Sangat Baik</span>
                        </div>
                    </div>

                    <!-- Training Feedback -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Feedback Training</label>
                        <textarea v-model="reviewForm.training_feedback" 
                                  rows="3" 
                                  placeholder="Bagikan pengalaman Anda tentang training ini..."
                                  class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500"></textarea>
                    </div>

                    <!-- Trainer Rating -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-3">Rating Trainer</label>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-slate-600">Buruk</span>
                            <div class="flex space-x-1">
                                <button v-for="rating in 5" :key="rating" 
                                        @click="reviewForm.trainer_rating = rating"
                                        class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition-colors"
                                        :class="{
                                            'bg-blue-400 border-blue-400 text-white': rating <= reviewForm.trainer_rating,
                                            'bg-white border-slate-300 text-slate-400 hover:border-blue-300': rating > reviewForm.trainer_rating
                                        }">
                                    <i class="fa-solid fa-star text-xs"></i>
                                </button>
                            </div>
                            <span class="text-sm text-slate-600">Sangat Baik</span>
                        </div>
                    </div>

                    <!-- Trainer Feedback -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Feedback Trainer</label>
                        <textarea v-model="reviewForm.trainer_feedback" 
                                  rows="3" 
                                  placeholder="Bagikan pendapat Anda tentang trainer..."
                                  class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                    </div>

                    <!-- Overall Satisfaction -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-3">Kepuasan Keseluruhan *</label>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-slate-600">Tidak Puas</span>
                            <div class="flex space-x-1">
                                <button v-for="rating in 5" :key="rating" 
                                        @click="reviewForm.overall_satisfaction = rating"
                                        class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition-colors"
                                        :class="{
                                            'bg-green-400 border-green-400 text-white': rating <= reviewForm.overall_satisfaction,
                                            'bg-white border-slate-300 text-slate-400 hover:border-green-300': rating > reviewForm.overall_satisfaction
                                        }">
                                    <i class="fa-solid fa-heart text-xs"></i>
                                </button>
                            </div>
                            <span class="text-sm text-slate-600">Sangat Puas</span>
                        </div>
                    </div>


                    <!-- Improvement Suggestions -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Saran Perbaikan</label>
                        <textarea v-model="reviewForm.improvement_suggestions" 
                                  rows="3" 
                                  placeholder="Bagikan saran Anda untuk perbaikan training..."
                                  class="w-full px-3 py-2 border border-slate-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"></textarea>
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
        <div v-if="showAvailableTrainingsModal" class="fixed inset-0 z-50 overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <!-- Background overlay -->
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeAvailableTrainingsModal"></div>

                <!-- Modal panel -->
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-6xl sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
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
                                
                                <!-- Header -->
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                            {{ training.title }}
                                            <span :class="getTrainingStatusBadge(training).class" 
                                                  class="px-2 py-1 text-xs rounded-full border">
                                                <i :class="getTrainingStatusBadge(training).icon" class="mr-1"></i>
                                                {{ getTrainingStatusBadge(training).text }}
                                            </span>
                                        </h4>
                                        <p v-if="training.short_description" class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                            {{ training.short_description }}
                                        </p>
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
                                    <div v-if="training.course_type">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Jenis Kursus:</span>
                                        <span class="ml-2 text-sm text-gray-900 dark:text-white">{{ training.course_type }}</span>
                                    </div>
                                </div>

                                <!-- Target Information -->
                                <div class="mb-3">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Target:</span>
                                    <span class="ml-2 text-sm text-gray-900 dark:text-white">{{ getTargetDisplayText(training) }}</span>
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
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button @click="closeAvailableTrainingsModal" 
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm">
                            Tutup
                        </button>
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