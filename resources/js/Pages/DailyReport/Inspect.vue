<script setup>
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';
import DailyReportCameraModal from '@/Components/DailyReportCameraModal.vue';

const props = defineProps({
  dailyReport: Object,
  areas: Array,
  divisions: Array,
  categories: Array,
  priorities: Array,
});

const isMobile = ref(window.innerWidth <= 768);
const sidebarOpen = ref(false);
const currentAreaId = ref(null);
const form = ref({
  status: '',
  finding_problem: '',
  dept_concern_id: '',
  documentation: []
});
const loading = ref(false);
const autoSaveInterval = ref(null);
const hasUnsavedChanges = ref(false);

// Camera related refs
const showCameraModal = ref(false);

// Convert to ticket refs
const existingTickets = ref([]);
const ticketCommentDrafts = ref({});
const submittingTicketComment = ref(null);
const ticketForm = ref({
  title: '',
  description: '',
  issue_type: '',
  category_id: '',
  priority_id: '',
  divisi_id: '',
  due_date: ''
});

// Computed properties
const currentArea = computed(() => {
  return props.areas.find(area => area.id === currentAreaId.value);
});

const filteredExistingTickets = computed(() => existingTickets.value);

const proposedTicketTitle = computed(() => {
  if (!currentArea.value) {
    return '';
  }

  const problem = String(form.value.finding_problem || '').trim();
  if (!problem) {
    return '';
  }

  return `${currentArea.value.nama_area} - ${problem}`;
});

const duplicateOpenTickets = computed(() => {
  return existingTickets.value.filter((ticket) => ticket.is_same_title);
});

const hasDuplicateTicket = computed(() => duplicateOpenTickets.value.length > 0);

const isFormValid = computed(() => {
  return form.value.status && form.value.status.trim() !== '';
});

const currentProgress = computed(() => {
  return props.dailyReport.progress.find(p => p.area_id === currentAreaId.value);
});

const completedCount = computed(() => {
  if (!props.dailyReport || !props.dailyReport.report_areas) {
    return 0;
  }
  // Count areas that have been saved (have data in report_areas)
  return props.dailyReport.report_areas.length;
});

const totalCount = computed(() => {
  if (!props.dailyReport || !props.dailyReport.progress) {
    return 0;
  }
  return props.dailyReport.progress.length;
});

const progressPercentage = computed(() => {
  return totalCount.value > 0 ? Math.round((completedCount.value / totalCount.value) * 100) : 0;
});

// Methods
function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value;
}

function selectArea(areaId) {
  // Always allow navigation - auto-save will handle unsaved changes
  loadAreaData(areaId);
}

function loadAreaData(areaId) {
  // Auto-save current area before switching
  if (currentAreaId.value && hasUnsavedChanges.value) {
    autoSave();
  }
  
  currentAreaId.value = areaId;
  
  // Check if area has been saved (exists in report_areas)
  const savedArea = props.dailyReport.report_areas?.find(ra => ra.area_id === areaId);
  
  if (savedArea) {
    // Load saved data
    form.value = {
      status: savedArea.status || '',
      finding_problem: savedArea.finding_problem || '',
      dept_concern_id: savedArea.dept_concern_id || '',
      documentation: savedArea.documentation || []
    };
  } else {
    // Check if there's draft data in progress
    const progress = props.dailyReport.progress.find(p => p.area_id === areaId);
    if (progress && progress.form_data) {
      form.value = {
        status: progress.form_data.status || '',
        finding_problem: progress.form_data.finding_problem || '',
        dept_concern_id: progress.form_data.dept_concern_id || '',
        documentation: progress.form_data.documentation || []
      };
    } else {
      form.value = {
        status: '',
        finding_problem: '',
        dept_concern_id: '',
        documentation: []
      };
    }
  }
  
  hasUnsavedChanges.value = false;

  ticketCommentDrafts.value = {};

  if (form.value.status === 'NG') {
    loadExistingTickets();
  } else {
    existingTickets.value = [];
  }
  
  if (isMobile.value) {
    sidebarOpen.value = false;
  }
}

function getAreaStatusClass(areaId) {
  // Check if area has been saved (exists in report_areas)
  const savedArea = props.dailyReport.report_areas?.find(ra => ra.area_id === areaId);
  
  // Current active area - prioritize active over status
  if (areaId === currentAreaId.value) {
    if (savedArea) {
      return 'active-completed'; // Active area that's already saved
    }
    return 'active'; // Active area that's not saved yet
  }
  
  // Non-active areas
  if (savedArea) {
    return 'completed'; // Area that has been saved
  }
  
  return 'pending'; // Area that hasn't been saved yet
}

function goToNextArea() {
  const currentIndex = props.dailyReport.progress.findIndex(p => p.area_id === currentAreaId.value);
  if (currentIndex < props.dailyReport.progress.length - 1) {
    const nextArea = props.dailyReport.progress[currentIndex + 1];
    selectArea(nextArea.area_id);
  } else {
    // All areas completed
    Swal.fire('Selesai!', 'Semua area telah diinspeksi. Silakan selesaikan laporan.', 'info');
  }
}

function goToPreviousArea() {
  const currentIndex = props.dailyReport.progress.findIndex(p => p.area_id === currentAreaId.value);
  if (currentIndex > 0) {
    const prevArea = props.dailyReport.progress[currentIndex - 1];
    selectArea(prevArea.area_id);
  }
}

function getStatusIcon(areaId) {
  const progress = props.dailyReport.progress.find(p => p.area_id === areaId);
  if (!progress) return 'fa-clock text-gray-400';
  
  switch (progress.progress_status) {
    case 'completed':
      return 'fa-check-circle text-green-500';
    case 'in_progress':
      return 'fa-spinner text-blue-500';
    case 'skipped':
      return 'fa-forward text-yellow-500';
    default:
      return 'fa-clock text-gray-400';
  }
}

function getStatusBadge(areaId) {
  const savedArea = props.dailyReport.report_areas?.find(ra => ra.area_id === areaId);
  if (!savedArea || !savedArea.status) {
    return null;
  }
  
  switch (savedArea.status) {
    case 'G':
      return { text: 'Good', class: 'bg-green-100 text-green-800 border-green-300' };
    case 'NG':
      return { text: 'NG', class: 'bg-red-100 text-red-800 border-red-300' };
    case 'NA':
      return { text: 'NA', class: 'bg-gray-100 text-gray-800 border-gray-300' };
    default:
      return null;
  }
}

function getDeptConcern(areaId) {
  const savedArea = props.dailyReport.report_areas?.find(ra => ra.area_id === areaId);
  if (!savedArea || !savedArea.dept_concern_id) {
    return null;
  }
  
  if (!props.divisions || !Array.isArray(props.divisions)) {
    return null;
  }
  
  const divisi = props.divisions.find(d => d.id == savedArea.dept_concern_id);
  return divisi ? divisi.nama_divisi : null;
}

function getStatusColor(status) {
  return {
    'pending': 'text-gray-400',
    'in_progress': 'text-blue-500',
    'completed': 'text-green-500',
    'skipped': 'text-yellow-500'
  }[status] || 'text-gray-400';
}

async function autoSave() {
  if (!currentAreaId.value || !hasUnsavedChanges.value) return;
  
  try {
    await axios.post(`/daily-report/${props.dailyReport.id}/auto-save`, {
      area_id: currentAreaId.value,
      ...form.value
    });
  } catch (error) {
    console.error('Auto save failed:', error);
  }
}

async function saveArea() {
  if (!currentAreaId.value) return;
  
  loading.value = true;
  try {
    // Prepare save data
    const saveData = {
      area_id: currentAreaId.value,
      ...form.value
    };
    
    const willCreateTicket = form.value.status === 'NG'
      && form.value.dept_concern_id
      && ticketForm.value.category_id
      && ticketForm.value.priority_id;

    if (willCreateTicket) {
      await loadExistingTickets();

      if (hasDuplicateTicket.value) {
        const duplicateList = duplicateOpenTickets.value
          .map((ticket) => `<li><strong>${ticket.ticket_number}</strong> — ${ticket.title}</li>`)
          .join('');

        const duplicateConfirm = await Swal.fire({
          title: 'Ticket dengan judul sama sudah ada',
          html: `<p class="text-sm text-gray-600 mb-2">Area dan outlet ini sudah memiliki ticket open dengan judul serupa:</p><ul class="text-left text-sm">${duplicateList}</ul><p class="text-sm text-gray-600 mt-3">Lanjutkan membuat ticket baru?</p>`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Tetap buat ticket baru',
          cancelButtonText: 'Batal',
          reverseButtons: true,
        });

        if (!duplicateConfirm.isConfirmed) {
          return;
        }
      }

      saveData.create_ticket = true;
      saveData.ticket_data = {
        ...ticketForm.value,
        title: proposedTicketTitle.value,
        description: form.value.finding_problem,
        divisi_id: form.value.dept_concern_id,
      };
    }
    
    const response = await axios.post(`/daily-report/${props.dailyReport.id}/save-area`, saveData);
    
    if (response.data.success) {
      hasUnsavedChanges.value = false;
      
      // Update report_areas data locally
      const existingAreaIndex = props.dailyReport.report_areas?.findIndex(ra => ra.area_id === currentAreaId.value);
      if (existingAreaIndex !== undefined && existingAreaIndex >= 0) {
        // Update existing area
        props.dailyReport.report_areas[existingAreaIndex] = {
          ...props.dailyReport.report_areas[existingAreaIndex],
          status: form.value.status,
          finding_problem: form.value.finding_problem,
          dept_concern_id: form.value.dept_concern_id,
          documentation: form.value.documentation
        };
      } else {
        // Add new area to report_areas
        if (!props.dailyReport.report_areas) {
          props.dailyReport.report_areas = [];
        }
        props.dailyReport.report_areas.push({
          area_id: currentAreaId.value,
          status: form.value.status,
          finding_problem: form.value.finding_problem,
          dept_concern_id: form.value.dept_concern_id,
          documentation: form.value.documentation
        });
      }
      
            // Show success message
            let successMessage = response.data.message || 'Area berhasil disimpan';
            if (form.value.dept_concern_id && response.data.ticket_created) {
              successMessage += '\n\nTicket berhasil dibuat dan notifikasi dikirim ke divisi terkait.';
            }
      
      Swal.fire('Berhasil!', successMessage, 'success');
      
            // Reset ticket form if ticket was created
            if (form.value.dept_concern_id && response.data.ticket_created) {
              ticketForm.value = {
                title: '',
                description: '',
                issue_type: '',
                category_id: '',
                priority_id: '',
                divisi_id: '',
                due_date: ''
              };
              // Reload existing tickets
              await loadExistingTickets();
            }
      
      // Don't auto-navigate - let user decide
    } else {
      Swal.fire('Error!', response.data.message || 'Gagal menyimpan area', 'error');
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menyimpan area', 'error');
  } finally {
    loading.value = false;
  }
}

async function skipArea() {
  if (!currentAreaId.value) return;
  
  const result = await Swal.fire({
    title: 'Skip Area?',
    text: 'Apakah Anda yakin ingin melewatkan area ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, Skip',
    cancelButtonText: 'Batal'
  });
  
  if (!result.isConfirmed) return;
  
  loading.value = true;
  try {
    const response = await axios.post(`/daily-report/${props.dailyReport.id}/skip-area`, {
      area_id: currentAreaId.value
    });
    
    if (response.data.success) {
      // Remove from report_areas if exists (skipped areas are not saved)
      if (props.dailyReport.report_areas) {
        const areaIndex = props.dailyReport.report_areas.findIndex(ra => ra.area_id === currentAreaId.value);
        if (areaIndex >= 0) {
          props.dailyReport.report_areas.splice(areaIndex, 1);
        }
      }
      
      // Reset form
      form.value = {
        status: '',
        finding_problem: '',
        dept_concern_id: '',
        documentation: []
      };
      hasUnsavedChanges.value = false;
      
      Swal.fire('Berhasil!', 'Area berhasil dilewati', 'success');
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal skip area', 'error');
  } finally {
    loading.value = false;
  }
}

function openCamera() {
  showCameraModal.value = true;
}

async function uploadFile(event) {
  const file = event.target.files[0];
  if (!file) return;
  
  if (form.value.documentation.length >= 5) {
    Swal.fire('Error', 'Maksimal 5 file per area', 'error');
    return;
  }
  
  const formData = new FormData();
  formData.append('file', file);
  
  try {
    const response = await axios.post('/daily-report/upload-documentation', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    
    if (response.data.success) {
      form.value.documentation.push(response.data.data.url);
      // Don't set hasUnsavedChanges here - let the form watcher handle it
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal upload file', 'error');
  }
}

function removePhoto(index) {
  form.value.documentation.splice(index, 1);
  // Don't set hasUnsavedChanges here - let the form watcher handle it
}

function closeCamera() {
  showCameraModal.value = false;
}

async function onPhotoCapture(dataUrl) {
  if (form.value.documentation.length >= 5) {
    Swal.fire('Error', 'Maksimal 5 file per area', 'error');
    return;
  }
  
  try {
    // Convert dataURL to blob
    const response = await fetch(dataUrl);
    const blob = await response.blob();
    const file = new File([blob], `photo_${Date.now()}.jpg`, { type: 'image/jpeg' });
    
    // Upload the photo
    const formData = new FormData();
    formData.append('file', file);
    
    const uploadResponse = await axios.post('/daily-report/upload-documentation', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    
    if (uploadResponse.data.success) {
      form.value.documentation.push(uploadResponse.data.data.url);
      // Don't set hasUnsavedChanges here - let the form watcher handle it
      Swal.fire('Berhasil', 'Foto berhasil diambil dan diunggah', 'success');
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal upload foto', 'error');
  }
  
  closeCamera();
}

async function completeReport() {
  const result = await Swal.fire({
    title: 'Selesaikan Report?',
    text: 'Apakah Anda yakin ingin menyelesaikan daily report ini?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Ya, Selesaikan',
    cancelButtonText: 'Batal'
  });
  
  if (!result.isConfirmed) return;
  
  loading.value = true;
  try {
    const response = await axios.post(`/daily-report/${props.dailyReport.id}/complete`);
    
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      // Redirect to post-inspection form
      if (response.data.redirect_to) {
        router.visit(response.data.redirect_to);
      } else {
        router.visit('/daily-report');
      }
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menyelesaikan report', 'error');
  } finally {
    loading.value = false;
  }
}

function goBackToIndex() {
  router.visit('/daily-report');
}

// Convert to ticket methods
async function onDivisionConcernChange() {
  if (form.value.status === 'NG') {
    await loadExistingTickets();
  }
}


async function loadExistingTickets() {
  if (!currentAreaId.value || form.value.status !== 'NG') {
    existingTickets.value = [];
    return;
  }

  try {
    const response = await axios.get(`/tickets/by-area/${currentAreaId.value}`, {
      params: {
        outlet_id: props.dailyReport.outlet_id,
        title: proposedTicketTitle.value || undefined,
      },
    });
    existingTickets.value = response.data.tickets || [];
  } catch (error) {
    console.error('Error loading existing tickets:', error);
    existingTickets.value = [];
  }
}

function onCategoryChange() {
  // Category changed, no specific action needed
}

function normalizeText(value) {
  return String(value || '').toLowerCase().replace(/[_-]/g, ' ').trim();
}

function findCategoryByIssueType(type) {
  const normalizedType = normalizeText(type);
  return (props.categories || []).find((category) => {
    const categoryName = normalizeText(category?.name);
    if (normalizedType === 'defect') {
      return categoryName.includes('defect');
    }
    if (normalizedType === 'ops issue') {
      return categoryName.includes('ops issue') || categoryName.includes('ops') || categoryName.includes('operation');
    }
    return false;
  });
}

function onIssueTypeChange() {
  if (!ticketForm.value.issue_type) return;
  const matchedCategory = findCategoryByIssueType(ticketForm.value.issue_type);
  if (matchedCategory) {
    ticketForm.value.category_id = matchedCategory.id;
  }
}

function onPriorityChange() {
  if (ticketForm.value.priority_id) {
    const priority = props.priorities.find(p => p.id == ticketForm.value.priority_id);
    if (priority && priority.max_days) {
      const dueDate = new Date();
      dueDate.setDate(dueDate.getDate() + priority.max_days);
      // Format to date only (YYYY-MM-DD)
      ticketForm.value.due_date = dueDate.toISOString().slice(0, 10);
    }
  }
}

function getSelectedPriority() {
  if (!ticketForm.value.priority_id) return null;
  return props.priorities.find((p) => String(p.id) === String(ticketForm.value.priority_id)) || null;
}

function viewTicket(ticketId) {
  window.open(`/tickets/${ticketId}`, '_blank');
}

function updateTicketCommentDraft(ticketId, value) {
  ticketCommentDrafts.value[ticketId] = value;
}

function canSubmitTicketComment(ticketId) {
  return String(ticketCommentDrafts.value[ticketId] || '').trim().length > 0
    && submittingTicketComment.value !== ticketId;
}

function fillCommentFromFinding(ticketId) {
  const problem = String(form.value.finding_problem || '').trim();
  if (!problem) {
    return;
  }

  const areaName = currentArea.value?.nama_area || '';
  const text = areaName
    ? `[Daily Report] ${areaName}: ${problem}`
    : `[Daily Report] ${problem}`;
  ticketCommentDrafts.value[ticketId] = text;
}

async function addTicketComment(ticketId) {
  const comment = String(ticketCommentDrafts.value[ticketId] || '').trim();
  if (!comment) {
    return;
  }

  submittingTicketComment.value = ticketId;
  try {
    const response = await axios.post(`/tickets/${ticketId}/comments`, { comment });
    if (response.data.success) {
      ticketCommentDrafts.value[ticketId] = '';
      Swal.fire({
        icon: 'success',
        title: 'Berhasil',
        text: 'Komentar berhasil ditambahkan ke ticket',
        timer: 2000,
        showConfirmButton: false,
      });
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menambahkan komentar', 'error');
  } finally {
    submittingTicketComment.value = null;
  }
}

function getStatusBadgeClass(statusSlug) {
  const statusClasses = {
    'open': 'bg-blue-100 text-blue-800',
    'in_progress': 'bg-yellow-100 text-yellow-800',
    'pending': 'bg-orange-100 text-orange-800',
    'resolved': 'bg-green-100 text-green-800',
    'closed': 'bg-gray-100 text-gray-800',
    'cancelled': 'bg-red-100 text-red-800'
  };
  return statusClasses[statusSlug] || 'bg-gray-100 text-gray-800';
}

function getTicketReporterName(ticket) {
  return ticket?.creator?.nama_lengkap
    || ticket?.creator?.email
    || 'Tidak diketahui';
}

function formatTicketReportedAt(value) {
  if (!value) {
    return '-';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return date.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}



// Watchers
watch(form, () => {
  hasUnsavedChanges.value = true;
}, { deep: true });

watch(() => form.value.status, async (status) => {
  if (status === 'NG' && currentAreaId.value) {
    await loadExistingTickets();
    return;
  }

  existingTickets.value = [];
  ticketCommentDrafts.value = {};
});

watch(() => form.value.finding_problem, async () => {
  if (form.value.status === 'NG' && currentAreaId.value) {
    await loadExistingTickets();
  }
});

// Lifecycle
onMounted(() => {
  // Debug: Check data structure
  console.log('Daily Report Data:', props.dailyReport);
  console.log('Progress Data:', props.dailyReport?.progress);
  console.log('Areas Data:', props.areas);
  console.log('Categories Data:', props.categories);
  console.log('Priorities Data:', props.priorities);
  
  // Set first area as current if none selected
  if (!currentAreaId.value && props.dailyReport?.progress?.length > 0) {
    const firstArea = props.dailyReport.progress.find(p => p.progress_status === 'pending');
    if (firstArea) {
      loadAreaData(firstArea.area_id);
    } else {
      loadAreaData(props.dailyReport.progress[0].area_id);
    }
  }
  
  // Start auto save interval
  autoSaveInterval.value = setInterval(autoSave, 30000); // Every 30 seconds
  
  // Handle window resize
  window.addEventListener('resize', () => {
    isMobile.value = window.innerWidth <= 768;
  });
});

onUnmounted(() => {
  if (autoSaveInterval.value) {
    clearInterval(autoSaveInterval.value);
  }
  window.removeEventListener('resize', () => {
    isMobile.value = window.innerWidth <= 768;
  });
});
</script>

<template>
  <AppLayout title="Daily Report Inspection">
    <div class="inspection-container">
      <!-- Mobile Header -->
      <div class="mobile-header" v-if="isMobile">
        <button @click="toggleSidebar" class="menu-btn">
          <i class="fa-solid fa-bars"></i>
        </button>
        <h1>Daily Inspection</h1>
        <div class="progress-indicator">
          {{ completedCount }}/{{ totalCount }}
        </div>
      </div>

      <!-- Sidebar -->
      <div class="sidebar" :class="{ open: sidebarOpen }">
        <div class="sidebar-header">
          <h3>Area List</h3>
          <button @click="toggleSidebar" class="close-btn" v-if="isMobile">
            <i class="fa-solid fa-times"></i>
          </button>
        </div>
        
        <div class="progress-bar">
          <div class="progress-fill" :style="{ width: progressPercentage + '%' }"></div>
        </div>
        <div class="progress-text">{{ progressPercentage }}% Complete</div>
        
        <div class="area-list">
          <div 
            v-for="progress in dailyReport.progress" 
            :key="progress.area_id"
            @click="selectArea(progress.area_id)"
            class="area-item"
            :class="[
              getAreaStatusClass(progress.area_id),
              { active: currentAreaId === progress.area_id }
            ]"
          >
            <div class="area-icon">
              <i class="fa-solid fa-map-marker-alt"></i>
            </div>
            <div class="area-info">
              <div class="area-name">{{ progress.area.nama_area }}</div>
              <div v-if="getDeptConcern(progress.area_id)" class="area-dept-concern">
                <i class="fa-solid fa-exclamation-triangle text-orange-500"></i>
                {{ getDeptConcern(progress.area_id) }}
              </div>
            </div>
            <div class="area-status">
              <!-- Status Badge -->
              <div v-if="getStatusBadge(progress.area_id)" class="status-badge" :class="getStatusBadge(progress.area_id).class">
                {{ getStatusBadge(progress.area_id).text }}
              </div>
              <!-- Status Icon -->
              <i :class="getStatusIcon(progress.area_id)"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="main-content">
        <!-- Back Button -->
        <div class="back-button-container">
          <button @click="goBackToIndex" class="back-button">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Daily Reports</span>
          </button>
        </div>

        <div v-if="!currentAreaId" class="no-area-selected">
          <i class="fa-solid fa-map-marker-alt text-6xl text-gray-300 mb-4"></i>
          <h2 class="text-xl font-semibold text-gray-600 mb-2">Pilih Area untuk Di-inspect</h2>
          <p class="text-gray-500">Klik area di sidebar untuk memulai inspection</p>
        </div>

        <div v-else class="inspection-form">
          <!-- Area Header -->
          <div class="area-header">
            <h2 class="area-title">{{ currentArea.nama_area }}</h2>
            <div class="area-meta">
              <span class="area-department">{{ dailyReport.department.nama_departemen }}</span>
            </div>
          </div>

          <!-- Form -->
          <form @submit.prevent="saveArea">
            <!-- Status Selection -->
            <div class="form-section">
              <h3>Status</h3>
              <div class="radio-group">
                <label class="radio-item">
                  <input type="radio" v-model="form.status" value="G">
                  <span class="radio-custom"></span>
                  <span class="radio-label">Good</span>
                </label>
                <label class="radio-item">
                  <input type="radio" v-model="form.status" value="NG">
                  <span class="radio-custom"></span>
                  <span class="radio-label">Not Good</span>
                </label>
                <label class="radio-item">
                  <input type="radio" v-model="form.status" value="NA">
                  <span class="radio-custom"></span>
                  <span class="radio-label">Not Available</span>
                </label>
              </div>
            </div>

            <!-- Open tickets in this area (NG only) -->
            <div v-if="form.status === 'NG'" class="form-section open-tickets-panel">
              <div class="open-tickets-header">
                <div class="open-tickets-header-icon">
                  <i class="fa-solid fa-ticket-alt"></i>
                </div>
                <div>
                  <h3>Ticket Open di Area Ini</h3>
                  <p class="open-tickets-subtitle">
                    Ticket belum selesai di outlet &amp; area yang sama — cek sebelum membuat ticket baru.
                  </p>
                </div>
                <span v-if="filteredExistingTickets.length > 0" class="open-tickets-count">
                  {{ filteredExistingTickets.length }} open
                </span>
              </div>

              <div v-if="hasDuplicateTicket" class="open-tickets-alert">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Ada ticket dengan <strong>judul yang sama</strong>. Hindari membuat ticket ganda.</span>
              </div>

              <div v-if="filteredExistingTickets.length > 0" class="open-tickets-list">
                <div
                  v-for="ticket in filteredExistingTickets"
                  :key="ticket.id"
                  class="open-ticket-card"
                  :class="{ 'open-ticket-card--duplicate': ticket.is_same_title }"
                >
                  <div class="open-ticket-card-top open-ticket-clickable" @click="viewTicket(ticket.id)">
                    <div class="open-ticket-card-title-wrap">
                      <div class="open-ticket-card-badges">
                        <span class="open-ticket-number">{{ ticket.ticket_number }}</span>
                        <span
                          v-if="ticket.is_same_title"
                          class="open-ticket-duplicate-badge"
                        >
                          Judul sama
                        </span>
                      </div>
                      <p class="open-ticket-title">{{ ticket.title }}</p>
                    </div>
                    <span class="open-ticket-status" :class="getStatusBadgeClass(ticket.status?.slug)">
                      {{ ticket.status?.name }}
                    </span>
                  </div>

                  <div class="open-ticket-meta open-ticket-clickable" @click="viewTicket(ticket.id)">
                    <div class="open-ticket-meta-item">
                      <i class="fa-solid fa-user"></i>
                      <span>Dilaporkan oleh <strong>{{ getTicketReporterName(ticket) }}</strong></span>
                    </div>
                    <div class="open-ticket-meta-item">
                      <i class="fa-solid fa-clock"></i>
                      <span>{{ formatTicketReportedAt(ticket.created_at) }}</span>
                    </div>
                  </div>

                  <div class="open-ticket-comment" @click.stop>
                    <label class="open-ticket-comment-label">
                      <i class="fa-solid fa-comment"></i>
                      Tambah komentar
                    </label>
                    <textarea
                      :value="ticketCommentDrafts[ticket.id] || ''"
                      class="open-ticket-comment-input"
                      placeholder="Tulis komentar untuk ticket ini..."
                      rows="2"
                      @input="updateTicketCommentDraft(ticket.id, $event.target.value)"
                    ></textarea>
                    <div class="open-ticket-comment-actions">
                      <button
                        v-if="form.finding_problem?.trim()"
                        type="button"
                        class="open-ticket-comment-fill"
                        @click="fillCommentFromFinding(ticket.id)"
                      >
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        Gunakan finding problem
                      </button>
                      <button
                        type="button"
                        class="open-ticket-comment-submit"
                        :disabled="!canSubmitTicketComment(ticket.id)"
                        @click="addTicketComment(ticket.id)"
                      >
                        <i
                          class="fa-solid"
                          :class="submittingTicketComment === ticket.id ? 'fa-spinner fa-spin' : 'fa-paper-plane'"
                        ></i>
                        {{ submittingTicketComment === ticket.id ? 'Mengirim...' : 'Kirim' }}
                      </button>
                    </div>
                  </div>

                  <div class="open-ticket-action open-ticket-clickable" @click="viewTicket(ticket.id)">
                    <span>Klik untuk lihat detail ticket</span>
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                  </div>
                </div>
              </div>

              <div v-else class="open-tickets-empty">
                <i class="fa-regular fa-circle-check"></i>
                <p>Belum ada ticket open di area ini.</p>
              </div>

              <p v-if="filteredExistingTickets.length > 0" class="open-tickets-footer">
                {{ filteredExistingTickets.length }} ticket open ditemukan
                <span v-if="hasDuplicateTicket"> • {{ duplicateOpenTickets.length }} dengan judul sama</span>
              </p>
            </div>

            <!-- Finding Problem -->
            <div class="form-section">
              <h3>Finding Problem</h3>
              <textarea 
                v-model="form.finding_problem"
                placeholder="Describe any issues found..."
                class="form-textarea"
                rows="4"
              ></textarea>
            </div>

            <!-- Department Concern -->
            <div class="form-section">
              <h3>Dept. Concern (To be Follow Up)</h3>
              <select v-model="form.dept_concern_id" class="form-select" @change="onDivisionConcernChange">
                <option value="">Select Department</option>
                <option v-for="division in divisions" :key="division.id" :value="division.id">
                  {{ division.nama_divisi }}
                </option>
              </select>
              
              <!-- Ticket Creation Section -->
              <div v-if="form.dept_concern_id && form.finding_problem" class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                <h4 class="text-lg font-semibold text-blue-800 mb-4 flex items-center gap-2">
                  <i class="fa-solid fa-ticket-alt"></i>
                  Create Ticket from This Issue
                </h4>

                <div v-if="proposedTicketTitle" class="mb-4 rounded-lg border border-blue-200 bg-white px-3 py-2">
                  <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Judul ticket yang akan dibuat</p>
                  <p class="text-sm font-medium text-gray-800">{{ proposedTicketTitle }}</p>
                </div>
                
                <!-- Category and Priority -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Issue</label>
                    <select v-model="ticketForm.issue_type" class="form-select" @change="onIssueTypeChange">
                      <option value="">Select Issue Type</option>
                      <option value="defect">Defect</option>
                      <option value="ops_issue">Ops Issue</option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select v-model="ticketForm.category_id" class="form-select" @change="onCategoryChange">
                      <option value="">Select Category</option>
                      <option v-for="category in categories" :key="category.id" :value="category.id">
                        {{ category.name }}
                      </option>
                    </select>
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Priority</label>
                    <select v-model="ticketForm.priority_id" class="form-select" @change="onPriorityChange">
                      <option value="">Select Priority</option>
                      <option v-for="priority in priorities" :key="priority.id" :value="priority.id">
                        {{ priority.name }}
                      </option>
                    </select>
                    <div v-if="getSelectedPriority()" class="mt-2 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2">
                      <p class="text-xs font-semibold text-blue-800">
                        Max Days: {{ getSelectedPriority().max_days ?? '-' }} hari
                      </p>
                      <p v-if="getSelectedPriority().description" class="mt-1 text-xs text-blue-700">
                        {{ getSelectedPriority().description }}
                      </p>
                    </div>
                  </div>
                </div>
                
                <!-- Due Date -->
                <div v-if="ticketForm.priority_id" class="mb-4">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Due Date</label>
                  <input 
                    v-model="ticketForm.due_date"
                    type="date" 
                    class="form-input"
                    readonly
                  />
                  <p class="text-xs text-gray-500 mt-1">Auto-calculated based on priority</p>
                </div>
              </div>
            </div>

            <!-- Documentation -->
            <div class="form-section">
              <h3>Documentation (Max 5 files)</h3>
              <div class="documentation-actions">
                <button type="button" @click="openCamera" class="btn btn-camera">
                  <i class="fa-solid fa-camera"></i>
                  Camera
                </button>
                <label for="file-input" class="btn btn-upload">
                  <i class="fa-solid fa-upload"></i>
                  Upload
                </label>
                <input 
                  id="file-input" 
                  type="file" 
                  accept="image/*" 
                  @change="uploadFile" 
                  class="hidden"
                >
              </div>
              
              <div class="photo-grid" v-if="form.documentation.length > 0">
                <div 
                  v-for="(photo, index) in form.documentation" 
                  :key="index"
                  class="photo-item"
                >
                  <img :src="photo" :alt="`Photo ${index + 1}`">
                  <button type="button" @click="removePhoto(index)" class="remove-btn">
                    <i class="fa-solid fa-times"></i>
                  </button>
                </div>
              </div>
              
            </div>

            <!-- Camera Modal -->
            <DailyReportCameraModal 
              v-if="showCameraModal"
              @close="closeCamera"
              @capture="onPhotoCapture"
            />

            <!-- Action Buttons -->
            <div class="form-actions">
              <button type="button" @click="goToPreviousArea" class="btn btn-secondary" :disabled="loading">
                <i class="fa-solid fa-arrow-left"></i>
                Previous Area
              </button>
              <button type="button" @click="skipArea" class="btn btn-warning" :disabled="loading">
                Skip
              </button>
              <button type="submit" class="btn btn-primary" :disabled="loading || !isFormValid">
                <div v-if="loading" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                <i v-else class="fa-solid fa-save"></i>
                {{ loading ? 'Saving...' : 'Save' }}
              </button>
              <button type="button" @click="goToNextArea" class="btn btn-secondary" :disabled="loading">
                <i class="fa-solid fa-arrow-right"></i>
                Next Area
              </button>
            </div>
          </form>

          <!-- Complete Report Button -->
          <div class="complete-section" v-if="completedCount === totalCount">
            <button @click="completeReport" class="btn btn-success btn-large">
              <i class="fa-solid fa-check-circle"></i>
              Complete Report
            </button>
          </div>
        </div>
      </div>
    </div>

  </AppLayout>
</template>

<style scoped>
.inspection-container {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 1rem;
  height: 100vh;
}

.mobile-header {
  display: none;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  background: white;
  border-bottom: 1px solid #e5e7eb;
}

.menu-btn {
  padding: 0.5rem;
  border-radius: 0.5rem;
  background: #f3f4f6;
  border: none;
  cursor: pointer;
}

.sidebar {
  background: white;
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  height: 100vh;
  overflow-y: auto;
}

.sidebar-header {
  padding: 1rem;
  border-bottom: 1px solid #e5e7eb;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.close-btn {
  padding: 0.5rem;
  border-radius: 0.5rem;
  background: #f3f4f6;
  border: none;
  cursor: pointer;
}

.progress-bar {
  margin: 1rem;
  height: 0.5rem;
  background: #e5e7eb;
  border-radius: 0.25rem;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: #3b82f6;
  transition: width 0.3s ease;
}

.progress-text {
  text-align: center;
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 1rem;
}

.area-list {
  flex: 1;
  overflow-y: auto;
}

.area-item {
  display: flex;
  align-items: center;
  padding: 1rem;
  border-bottom: 1px solid #f3f4f6;
  cursor: pointer;
  transition: background-color 0.2s;
}

.area-item:hover {
  background: #f9fafb;
}

.area-item.active {
  background: #dbeafe; /* Light blue */
  border-left: 4px solid #2563eb; /* Blue border */
  font-weight: 600;
  color: #1e40af; /* Dark blue text */
}

.area-item.active-completed {
  background: #fef3c7; /* Light yellow/amber */
  border-left: 4px solid #f59e0b; /* Amber border */
  font-weight: 600;
  color: #92400e; /* Dark amber text */
}

.area-item.completed {
  background: #dcfce7; /* Light green */
  border-left: 3px solid #16a34a; /* Green border */
}

.area-item.in_progress {
  background: #dbeafe;
  border-left: 3px solid #2563eb;
}

.area-item.skipped {
  background: #fef3c7; /* Light yellow */
  border-left: 3px solid #d97706; /* Yellow border */
}

.area-item.pending {
  background: #f9fafb; /* Light gray */
  border-left: 3px solid #9ca3af; /* Gray border */
}

.area-icon {
  margin-right: 0.75rem;
  color: #6b7280;
}

.area-info {
  flex: 1;
}

.area-code {
  font-family: monospace;
  font-size: 0.75rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
}

.area-name {
  font-weight: 500;
  color: #374151;
}

.area-dept-concern {
  font-size: 0.75rem;
  color: #f59e0b;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  margin-top: 0.25rem;
}

.area-status {
  margin-left: 0.5rem;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.25rem;
}

.status-badge {
  font-size: 0.625rem;
  font-weight: 600;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  border: 1px solid;
  text-transform: uppercase;
  letter-spacing: 0.025em;
}

.main-content {
  padding: 1rem;
  overflow-y: auto;
  height: 100vh;
}

.back-button-container {
  margin-bottom: 1rem;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 1rem;
  background-color: #f3f4f6;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  color: #374151;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
}

.back-button:hover {
  background-color: #e5e7eb;
  border-color: #9ca3af;
}

.back-button i {
  font-size: 0.875rem;
}

.no-area-selected {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  text-align: center;
}

.area-header {
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e5e7eb;
}

.area-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #111827;
  margin-bottom: 0.5rem;
}

.area-meta {
  display: flex;
  gap: 1rem;
  font-size: 0.875rem;
  color: #6b7280;
}

.area-code {
  font-family: monospace;
  background: #f3f4f6;
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
}

.form-section {
  margin-bottom: 2rem;
}

.form-section h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 1rem;
}

.radio-group {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.radio-item {
  display: flex;
  align-items: center;
  cursor: pointer;
}

.radio-item input[type="radio"] {
  display: none;
}

.radio-custom {
  width: 1.25rem;
  height: 1.25rem;
  border: 2px solid #d1d5db;
  border-radius: 50%;
  margin-right: 0.75rem;
  position: relative;
  transition: border-color 0.2s;
}

.radio-item input[type="radio"]:checked + .radio-custom {
  border-color: #3b82f6;
}

.radio-item input[type="radio"]:checked + .radio-custom::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 0.5rem;
  height: 0.5rem;
  background: #3b82f6;
  border-radius: 50%;
}

.radio-label {
  font-weight: 500;
  color: #374151;
}

.form-textarea {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  resize: vertical;
  font-family: inherit;
}

.form-textarea:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-select {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  background: white;
}

.form-select:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.documentation-actions {
  display: flex;
  gap: 1rem;
  margin-bottom: 1rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border-radius: 0.5rem;
  font-weight: 500;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-camera {
  background: #10b981;
  color: white;
}

.btn-camera:hover {
  background: #059669;
}

.btn-upload {
  background: #6b7280;
  color: white;
}

.btn-upload:hover {
  background: #4b5563;
}

.photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: 1rem;
}

.photo-item {
  position: relative;
  aspect-ratio: 1;
  border-radius: 0.5rem;
  overflow: hidden;
  border: 1px solid #e5e7eb;
}

.photo-item img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.remove-btn {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  width: 1.5rem;
  height: 1.5rem;
  background: rgba(239, 68, 68, 0.8);
  color: white;
  border: none;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

.form-actions {
  display: flex;
  gap: 1rem;
  padding-top: 2rem;
  border-top: 1px solid #e5e7eb;
}

.btn-secondary {
  background: #6b7280;
  color: white;
}

.btn-secondary:hover {
  background: #4b5563;
}

.btn-warning {
  background: #f59e0b;
  color: white;
}

.btn-warning:hover {
  background: #d97706;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover {
  background: #2563eb;
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.complete-section {
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid #e5e7eb;
  text-align: center;
}

.btn-success {
  background: #10b981;
  color: white;
}

.btn-success:hover {
  background: #059669;
}


.btn-large {
  padding: 1rem 2rem;
  font-size: 1.125rem;
}

.open-tickets-panel {
  border: 2px solid #fb923c !important;
  background: linear-gradient(135deg, #fff7ed 0%, #fffbeb 100%);
  box-shadow: 0 10px 30px -12px rgba(249, 115, 22, 0.45);
}

.open-tickets-header {
  display: flex;
  align-items: flex-start;
  gap: 0.875rem;
  margin-bottom: 1rem;
}

.open-tickets-header h3 {
  margin: 0;
  font-size: 1.125rem;
  font-weight: 700;
  color: #9a3412;
}

.open-tickets-header-icon {
  display: flex;
  height: 2.75rem;
  width: 2.75rem;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  border-radius: 0.875rem;
  background: linear-gradient(135deg, #f97316, #ea580c);
  color: white;
  font-size: 1.125rem;
  box-shadow: 0 8px 20px -8px rgba(234, 88, 12, 0.8);
}

.open-tickets-subtitle {
  margin: 0.25rem 0 0;
  font-size: 0.875rem;
  color: #9a3412;
  opacity: 0.85;
}

.open-tickets-count {
  margin-left: auto;
  flex-shrink: 0;
  border-radius: 9999px;
  background: #ea580c;
  padding: 0.35rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: white;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.open-tickets-alert {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  margin-bottom: 1rem;
  border: 1px solid #f59e0b;
  border-radius: 0.75rem;
  background: #fef3c7;
  padding: 0.75rem 0.875rem;
  font-size: 0.875rem;
  color: #92400e;
}

.open-tickets-list {
  display: flex;
  max-height: 18rem;
  flex-direction: column;
  gap: 0.75rem;
  overflow-y: auto;
}

.open-ticket-card {
  cursor: pointer;
  border: 1px solid #fdba74;
  border-radius: 0.875rem;
  background: white;
  padding: 0.875rem 1rem;
  transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.open-ticket-card:hover {
  transform: translateY(-1px);
  border-color: #f97316;
  box-shadow: 0 8px 24px -12px rgba(249, 115, 22, 0.55);
}

.open-ticket-card--duplicate {
  border-color: #f59e0b;
  background: #fffbeb;
  box-shadow: inset 0 0 0 1px #fcd34d;
}

.open-ticket-card-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.75rem;
}

.open-ticket-card-badges {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.open-ticket-number {
  font-size: 0.875rem;
  font-weight: 800;
  color: #1f2937;
}

.open-ticket-duplicate-badge {
  border-radius: 9999px;
  background: #fbbf24;
  padding: 0.15rem 0.5rem;
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #78350f;
}

.open-ticket-title {
  margin: 0.35rem 0 0;
  font-size: 0.95rem;
  font-weight: 600;
  line-height: 1.4;
  color: #374151;
}

.open-ticket-status {
  flex-shrink: 0;
  border-radius: 9999px;
  padding: 0.25rem 0.625rem;
  font-size: 0.75rem;
  font-weight: 700;
}

.open-ticket-meta {
  display: grid;
  grid-template-columns: 1fr;
  gap: 0.5rem;
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px dashed #fed7aa;
}

.open-ticket-meta-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8125rem;
  color: #6b7280;
}

.open-ticket-meta-item i {
  width: 1rem;
  color: #ea580c;
}

.open-ticket-meta-item strong {
  color: #111827;
}

.open-ticket-clickable {
  cursor: pointer;
}

.open-ticket-comment {
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px dashed #fdba74;
}

.open-ticket-comment-label {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin-bottom: 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #9a3412;
}

.open-ticket-comment-input {
  width: 100%;
  border: 1px solid #fdba74;
  border-radius: 0.625rem;
  background: rgba(255, 255, 255, 0.9);
  padding: 0.625rem 0.75rem;
  font-size: 0.8125rem;
  color: #111827;
  resize: vertical;
  min-height: 3.5rem;
}

.open-ticket-comment-input:focus {
  outline: none;
  border-color: #ea580c;
  box-shadow: 0 0 0 2px rgba(234, 88, 12, 0.15);
}

.open-ticket-comment-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.open-ticket-comment-fill {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  border: none;
  border-radius: 0.5rem;
  background: rgba(255, 255, 255, 0.85);
  padding: 0.375rem 0.625rem;
  font-size: 0.6875rem;
  font-weight: 600;
  color: #9a3412;
  cursor: pointer;
}

.open-ticket-comment-fill:hover {
  background: #fff7ed;
}

.open-ticket-comment-submit {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  border: none;
  border-radius: 0.5rem;
  background: #ea580c;
  padding: 0.4375rem 0.875rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #fff;
  cursor: pointer;
}

.open-ticket-comment-submit:hover:not(:disabled) {
  background: #c2410c;
}

.open-ticket-comment-submit:disabled {
  background: #fdba74;
  cursor: not-allowed;
}

.open-ticket-action {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 0.75rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #ea580c;
}

.open-tickets-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
  border: 1px dashed #fdba74;
  border-radius: 0.875rem;
  background: rgba(255, 255, 255, 0.7);
  padding: 1.25rem;
  color: #9a3412;
}

.open-tickets-empty i {
  font-size: 1.5rem;
  color: #22c55e;
}

.open-tickets-footer {
  margin: 0.75rem 0 0;
  font-size: 0.75rem;
  color: #9a3412;
}

/* Mobile Styles */
@media (max-width: 768px) {
  .inspection-container {
    display: flex;
    flex-direction: column;
    height: 100vh;
  }
  
  .mobile-header {
    display: flex;
  }
  
  .sidebar {
    position: fixed;
    top: 0;
    left: -100%;
    width: 80%;
    height: 100vh;
    background: white;
    z-index: 1000;
    transition: left 0.3s ease;
  }
  
  .sidebar.open {
    left: 0;
  }
  
  .main-content {
    width: 100%;
    padding: 1rem;
    height: calc(100vh - 60px);
  }
  
  .back-button {
    width: 100%;
    justify-content: center;
  }
  
  .radio-group {
    flex-direction: row;
    flex-wrap: wrap;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .documentation-actions {
    flex-direction: column;
  }
}
</style>

