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
const ticketForm = ref({
  title: '',
  description: '',
  category_id: '',
  priority_id: '',
  divisi_id: '',
  due_date: ''
});

// Computed properties
const currentArea = computed(() => {
  return props.areas.find(area => area.id === currentAreaId.value);
});

const filteredExistingTickets = computed(() => {
  if (!currentArea.value) return [];
  
  return existingTickets.value.filter(ticket => {
    // Filter by area name in title (since title contains area name)
    const areaName = currentArea.value.nama_area.toLowerCase();
    const ticketTitle = ticket.title.toLowerCase();
    return ticketTitle.includes(areaName) && 
           ticket.status?.slug !== 'closed' && 
           ticket.status?.slug !== 'cancelled';
  });
});

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
    
            // Add ticket creation data if division concern is filled
            if (form.value.dept_concern_id && ticketForm.value.category_id && ticketForm.value.priority_id) {
              saveData.create_ticket = true;
              saveData.ticket_data = {
                ...ticketForm.value,
                title: `${currentArea.value?.nama_area} - ${form.value.finding_problem.substring(0, 50)}...`,
                description: form.value.finding_problem,
                divisi_id: form.value.dept_concern_id
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
  console.log('Division concern changed:', form.value.dept_concern_id);
  if (form.value.dept_concern_id && form.value.finding_problem) {
    console.log('Loading existing tickets...');
    await loadExistingTickets();
  }
}


async function loadExistingTickets() {
  try {
    const response = await axios.get(`/tickets/by-area/${currentAreaId.value}`, {
      params: {
        outlet_id: props.dailyReport.outlet_id
      }
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

function viewTicket(ticketId) {
  window.open(`/tickets/${ticketId}`, '_blank');
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



// Watchers
watch(form, () => {
  hasUnsavedChanges.value = true;
}, { deep: true });

// Watch for finding_problem changes to load existing tickets
watch(() => form.value.finding_problem, async (newValue) => {
  if (newValue && form.value.dept_concern_id) {
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
                
                <!-- Category and Priority -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
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
                
                <!-- Existing Tickets in Same Area -->
                <div v-if="existingTickets.length > 0" class="mb-4">
                  <label class="block text-sm font-medium text-gray-700 mb-2">Existing Tickets in This Area</label>
                  <div class="max-h-32 overflow-y-auto border border-gray-200 rounded-lg">
                    <div v-for="ticket in filteredExistingTickets" :key="ticket.id" 
                         class="p-2 border-b border-gray-100 hover:bg-gray-50 cursor-pointer"
                         @click="viewTicket(ticket.id)">
                      <div class="flex items-center justify-between">
                        <div>
                          <span class="font-medium text-sm">{{ ticket.ticket_number }}</span>
                          <span class="text-xs text-gray-500 ml-2">{{ ticket.title }}</span>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full"
                              :class="getStatusBadgeClass(ticket.status?.slug)">
                          {{ ticket.status?.name }}
                        </span>
                      </div>
                    </div>
                  </div>
                  <p class="text-xs text-gray-500 mt-1">{{ filteredExistingTickets.length }} tickets found</p>
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

