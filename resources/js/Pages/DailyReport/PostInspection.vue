<script setup>
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  dailyReport: Object,
  users: Array,
});

const isMobile = ref(window.innerWidth <= 768);
const sidebarOpen = ref(!isMobile.value);
const activeSection = ref('briefing');
const loading = ref(false);

// Form data
const briefingForm = ref({
  briefing_type: '',
  time_of_conduct: '',
  participant: '',
  service_in_charge: '',
  bar_in_charge: '',
  kitchen_in_charge: '',
  so_product: '',
  product_up_selling: '',
  commodity_issue: '',
  oe_issue: '',
  guest_reservation_pax: '',
  daily_revenue_target: '',
  promotion_program_campaign: '',
  guest_comment_target: '',
  trip_advisor_target: '',
  other_preparation: '',
});

const productivityForm = ref({
  product_knowledge_test: '',
  sos_hospitality_role_play: '',
  employee_daily_coaching: '',
  others_activity: '',
});

const visitTableForm = ref({
  guest_name: '',
  table_no: '',
  no_of_pax: '',
  guest_experience: '',
});

const summaryForm = ref({
  summary_type: '',
  notes: '',
});

// Visit tables array
const visitTables = ref([]);

// Participant autocomplete
const participantSearch = ref('');
const selectedParticipants = ref([]);
const filteredUsers = ref([]);
const showParticipantDropdown = ref(false);

// Computed properties
const isKitchen = computed(() => props.dailyReport.department.nama_departemen.toLowerCase() === 'kitchen');
const isService = computed(() => props.dailyReport.department.nama_departemen.toLowerCase() === 'service');
const isBar = computed(() => props.dailyReport.department.nama_departemen.toLowerCase() === 'bar');
const isLunch = computed(() => props.dailyReport.inspection_time === 'lunch');
const isDinner = computed(() => props.dailyReport.inspection_time === 'dinner');

const showProductivity = computed(() => {
  return isKitchen.value && isLunch.value || isService.value && isLunch.value || isBar.value && isLunch.value;
});

const showServiceFields = computed(() => {
  return isService.value;
});

const showBarFields = computed(() => {
  return isBar.value;
});

const showMorningBriefing = computed(() => {
  return isLunch.value;
});

const showAfternoonBriefing = computed(() => {
  return isDinner.value;
});

const summaryType = computed(() => {
  return isLunch.value ? 'summary_1' : 'summary_2';
});

// Sidebar sections
const sections = computed(() => {
  const sectionsList = [
    { id: 'briefing', name: isLunch.value ? 'Morning Briefing' : 'Afternoon Briefing', icon: 'fa-solid fa-users' }
  ];
  
  if (showProductivity.value) {
    sectionsList.push({ id: 'productivity', name: 'Employee Productivity', icon: 'fa-solid fa-chart-line' });
  }
  
  sectionsList.push(
    { id: 'visit-table', name: 'Visit Table', icon: 'fa-solid fa-table' },
    { id: 'summary', name: 'Report Summary', icon: 'fa-solid fa-clipboard-list' }
  );
  
  return sectionsList;
});

// Check if section has data
const isSectionCompleted = computed(() => {
  return (sectionId) => {
    switch (sectionId) {
      case 'briefing':
        return briefingForm.value.time_of_conduct || briefingForm.value.participant || 
               briefingForm.value.service_in_charge || briefingForm.value.bar_in_charge || 
               briefingForm.value.kitchen_in_charge || briefingForm.value.so_product ||
               briefingForm.value.product_up_selling || briefingForm.value.commodity_issue ||
               briefingForm.value.oe_issue || briefingForm.value.guest_reservation_pax ||
               briefingForm.value.daily_revenue_target || briefingForm.value.promotion_program_campaign ||
               briefingForm.value.guest_comment_target || briefingForm.value.trip_advisor_target ||
               briefingForm.value.other_preparation;
      case 'productivity':
        return productivityForm.value.product_knowledge_test || productivityForm.value.sos_hospitality_role_play ||
               productivityForm.value.employee_daily_coaching || productivityForm.value.others_activity;
      case 'visit-table':
        return visitTables.value.length > 0;
      case 'summary':
        return summaryForm.value.notes;
      default:
        return false;
    }
  };
});

// Methods
function toggleSidebar() {
  sidebarOpen.value = !sidebarOpen.value;
}

function selectSection(sectionId) {
  activeSection.value = sectionId;
  if (isMobile.value) {
    sidebarOpen.value = false;
  }
}

function searchParticipants() {
  if (participantSearch.value.length < 2) {
    filteredUsers.value = [];
    showParticipantDropdown.value = false;
    return;
  }
  
  filteredUsers.value = props.users.filter(user => 
    user.nama_lengkap.toLowerCase().includes(participantSearch.value.toLowerCase()) &&
    !selectedParticipants.value.some(selected => selected.id === user.id)
  );
  showParticipantDropdown.value = filteredUsers.value.length > 0;
}

function selectParticipant(user) {
  selectedParticipants.value.push(user);
  participantSearch.value = '';
  showParticipantDropdown.value = false;
  filteredUsers.value = [];
  
  // Update participant field
  briefingForm.value.participant = selectedParticipants.value.map(p => p.nama_lengkap).join(',');
}

function removeParticipant(index) {
  selectedParticipants.value.splice(index, 1);
  briefingForm.value.participant = selectedParticipants.value.map(p => p.nama_lengkap).join(',');
}

function hideParticipantDropdown() {
  setTimeout(() => {
    showParticipantDropdown.value = false;
  }, 200);
}

async function saveBriefing() {
  loading.value = true;
  try {
    const response = await axios.post(`/daily-report/${props.dailyReport.id}/save-briefing`, briefingForm.value);
    if (response.data.success) {
      Swal.fire('Berhasil!', response.data.message, 'success');
    }
  } catch (error) {
    Swal.fire('Error!', error.response?.data?.message || 'Gagal menyimpan briefing', 'error');
  } finally {
    loading.value = false;
  }
}

async function saveProductivity() {
  loading.value = true;
  try {
    const response = await axios.post(`/daily-report/${props.dailyReport.id}/save-productivity`, productivityForm.value);
    if (response.data.success) {
      Swal.fire('Berhasil!', response.data.message, 'success');
    }
  } catch (error) {
    Swal.fire('Error!', error.response?.data?.message || 'Gagal menyimpan productivity program', 'error');
  } finally {
    loading.value = false;
  }
}

function addVisitTable() {
  visitTables.value.push({
    guest_name: visitTableForm.value.guest_name,
    table_no: visitTableForm.value.table_no,
    no_of_pax: visitTableForm.value.no_of_pax,
    guest_experience: visitTableForm.value.guest_experience,
  });
  
  // Reset form
  visitTableForm.value = {
    guest_name: '',
    table_no: '',
    no_of_pax: '',
    guest_experience: '',
  };
}

function removeVisitTable(index) {
  visitTables.value.splice(index, 1);
}

async function saveVisitTable() {
  loading.value = true;
  try {
    for (const table of visitTables.value) {
      await axios.post(`/daily-report/${props.dailyReport.id}/save-visit-table`, table);
    }
    Swal.fire('Berhasil!', 'Visit tables berhasil disimpan', 'success');
  } catch (error) {
    Swal.fire('Error!', error.response?.data?.message || 'Gagal menyimpan visit tables', 'error');
  } finally {
    loading.value = false;
  }
}

async function saveSummary() {
  loading.value = true;
  try {
    const response = await axios.post(`/daily-report/${props.dailyReport.id}/save-summary`, {
      ...summaryForm.value,
      summary_type: summaryType.value
    });
    if (response.data.success) {
      Swal.fire('Berhasil!', response.data.message, 'success');
    }
  } catch (error) {
    Swal.fire('Error!', error.response?.data?.message || 'Gagal menyimpan summary', 'error');
  } finally {
    loading.value = false;
  }
}

function goBack() {
  router.visit('/daily-report');
}

async function forceCompleteReport() {
  const result = await Swal.fire({
    title: 'Complete Report?',
    text: 'Are you sure you want to mark this report as completed? You can still edit it later.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#16a34a',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Yes, Complete!',
    cancelButtonText: 'Cancel',
  });
  
  if (!result.isConfirmed) return;
  
  loading.value = true;
  
  try {
    const response = await axios.post(`/daily-report/${props.dailyReport.id}/force-complete`);
    if (response.data.success) {
      Swal.fire('Success!', response.data.message, 'success');
      router.visit('/daily-report');
    }
  } catch (error) {
    Swal.fire('Error!', error.response?.data?.message || 'Failed to complete report.', 'error');
  } finally {
    loading.value = false;
  }
}

// Initialize form data
onMounted(() => {
  // Load existing data if available
  if (props.dailyReport.briefing) {
    briefingForm.value = { ...briefingForm.value, ...props.dailyReport.briefing };
    
    // Parse participant string back to selectedParticipants array
    if (briefingForm.value.participant && briefingForm.value.participant.trim()) {
      const participantNames = briefingForm.value.participant.split(',').map(name => name.trim()).filter(name => name);
      
      selectedParticipants.value = participantNames.map(name => {
        // Find user by name in props.users
        const user = props.users.find(u => u.nama_lengkap === name);
        return user || { id: Date.now() + Math.random(), nama_lengkap: name };
      });
      
      // Debug
      console.log('Participant loaded:', briefingForm.value.participant);
      console.log('Selected participants:', selectedParticipants.value);
    }
  }
  
  if (props.dailyReport.productivity) {
    productivityForm.value = { ...productivityForm.value, ...props.dailyReport.productivity };
  }
  
  if (props.dailyReport.visit_tables) {
    visitTables.value = [...props.dailyReport.visit_tables];
  }
  
  if (props.dailyReport.summaries) {
    const summary = props.dailyReport.summaries.find(s => s.summary_type === summaryType.value);
    if (summary) {
      summaryForm.value = { ...summaryForm.value, ...summary };
    }
  }
  
  // Set briefing type based on inspection time
  briefingForm.value.briefing_type = isLunch.value ? 'morning' : 'afternoon';
  briefingForm.value.summary_type = summaryType.value;
  
  // Handle window resize
  window.addEventListener('resize', () => {
    isMobile.value = window.innerWidth <= 768;
  });
});
</script>

<template>
  <AppLayout title="Post-Inspection Form">
    <!-- Mobile Header -->
    <div class="mobile-header" v-if="isMobile">
      <button @click="toggleSidebar" class="menu-btn">
        <i class="fa-solid fa-bars"></i>
      </button>
      <h1>Post Inspection</h1>
      <div class="progress-indicator">
        {{ sections.find(s => s.id === activeSection)?.name || 'Form' }}
      </div>
    </div>

    <div class="post-inspection-container">

      <!-- Sidebar (Section List) -->
      <aside :class="['sidebar', { 'sidebar-open': sidebarOpen }]">
        <div class="sidebar-header">
          <h3>Form Sections</h3>
          <button @click="toggleSidebar" class="close-btn" v-if="isMobile">
            <i class="fa-solid fa-times"></i>
          </button>
        </div>
        
        <div class="section-list">
          <div 
            v-for="section in sections" 
            :key="section.id"
            @click="selectSection(section.id)"
            :class="['section-item', { 
              'active': activeSection === section.id,
              'completed': isSectionCompleted(section.id)
            }]"
          >
            <div class="section-icon">
              <i :class="section.icon"></i>
            </div>
            <div class="section-info">
              <div class="section-name">{{ section.name }}</div>
            </div>
            <div class="section-status">
              <i v-if="isSectionCompleted(section.id)" class="fa-solid fa-check-circle text-green-500"></i>
              <i v-else class="fa-solid fa-circle text-gray-300"></i>
            </div>
          </div>
        </div>
        
        <div class="sidebar-footer">
          <button @click="forceCompleteReport" :disabled="loading" class="btn btn-success btn-large mb-3">
            <div v-if="loading" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
            <i v-else class="fa-solid fa-flag-checkered"></i>
            {{ loading ? 'Completing...' : 'Complete Report' }}
          </button>
          <button @click="goBack" class="btn btn-secondary btn-large">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Reports
          </button>
        </div>
      </aside>

      <!-- Main Content (Form) -->
      <main :class="['main-content', { 'main-content-shifted': sidebarOpen && !isMobile }]">
        <div class="form-container">
          <!-- Header -->
          <div class="form-header">
            <h1 class="form-title">Post-Inspection Form</h1>
            <p class="form-subtitle">
              Outlet: {{ dailyReport.outlet.nama_outlet }} | 
              Department: {{ dailyReport.department.nama_departemen }} | 
              Time: {{ dailyReport.inspection_time }}
            </p>
          </div>

          <!-- Briefing Section -->
          <div v-if="activeSection === 'briefing'" class="form-section">
            <h2 class="section-title">
              {{ isLunch ? 'II. MORNING BRIEFING' : 'II. AFTERNOON BRIEFING' }}
            </h2>
            
            <form @submit.prevent="saveBriefing">
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Time of Conduct</label>
                  <input 
                    type="time" 
                    v-model="briefingForm.time_of_conduct"
                    class="form-input"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Participant</label>
                  <div class="autocomplete-container">
                    <input 
                      type="text" 
                      v-model="participantSearch"
                      @input="searchParticipants"
                      @focus="searchParticipants"
                      @blur="hideParticipantDropdown"
                      placeholder="Search participants..."
                      class="form-input"
                    />
                    
                    <!-- Selected Participants -->
                    <div v-if="selectedParticipants.length > 0" class="selected-participants">
                      <span 
                        v-for="(participant, index) in selectedParticipants" 
                        :key="participant.id"
                        class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800"
                      >
                        {{ participant.nama_lengkap }}
                        <button 
                          @click="removeParticipant(index)"
                          class="ml-2 text-blue-600 hover:text-blue-800"
                        >
                          <i class="fa-solid fa-times text-xs"></i>
                        </button>
                      </span>
                    </div>
                    
                    <!-- Dropdown -->
                    <div v-if="showParticipantDropdown" class="autocomplete-dropdown">
                      <div 
                        v-for="user in filteredUsers" 
                        :key="user.id"
                        @click="selectParticipant(user)"
                        class="px-3 py-2 hover:bg-gray-100 cursor-pointer"
                      >
                        {{ user.nama_lengkap }}
                      </div>
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label class="form-label">Service In Charge</label>
                  <input 
                    type="text" 
                    v-model="briefingForm.service_in_charge"
                    class="form-input"
                    placeholder="Enter service in charge"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Bar In Charge</label>
                  <input 
                    type="text" 
                    v-model="briefingForm.bar_in_charge"
                    class="form-input"
                    placeholder="Enter bar in charge"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Kitchen In Charge</label>
                  <input 
                    type="text" 
                    v-model="briefingForm.kitchen_in_charge"
                    class="form-input"
                    placeholder="Enter kitchen in charge"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">SO Product</label>
                  <input 
                    type="text" 
                    v-model="briefingForm.so_product"
                    class="form-input"
                    placeholder="Enter SO product"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Product Up-Selling</label>
                  <input 
                    type="text" 
                    v-model="briefingForm.product_up_selling"
                    class="form-input"
                    placeholder="Enter product up-selling"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Commodity Issue</label>
                  <input 
                    type="text" 
                    v-model="briefingForm.commodity_issue"
                    class="form-input"
                    placeholder="Enter commodity issue"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">OE Issue</label>
                  <input 
                    type="text" 
                    v-model="briefingForm.oe_issue"
                    class="form-input"
                    placeholder="Enter OE issue"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Guest Reservation Pax</label>
                  <input 
                    type="number" 
                    v-model="briefingForm.guest_reservation_pax"
                    class="form-input"
                    placeholder="Enter guest reservation pax"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Daily Revenue Target</label>
                  <input 
                    type="number" 
                    v-model="briefingForm.daily_revenue_target"
                    class="form-input"
                    placeholder="Enter daily revenue target"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Promotion Program Campaign</label>
                  <input 
                    type="text" 
                    v-model="briefingForm.promotion_program_campaign"
                    class="form-input"
                    placeholder="Enter promotion program campaign"
                  />
                </div>

                <!-- Service-specific fields -->
                <template v-if="showServiceFields">
                  <div class="form-group">
                    <label class="form-label">Guest Comment Target</label>
                    <input 
                      type="text" 
                      v-model="briefingForm.guest_comment_target"
                      class="form-input"
                      placeholder="Enter guest comment target"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">Trip Advisor Target</label>
                    <input 
                      type="text" 
                      v-model="briefingForm.trip_advisor_target"
                      class="form-input"
                      placeholder="Enter trip advisor target"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">Other Preparation</label>
                    <input 
                      type="text" 
                      v-model="briefingForm.other_preparation"
                      class="form-input"
                      placeholder="Enter other preparation"
                    />
                  </div>
                </template>

                <!-- Bar-specific fields -->
                <template v-if="showBarFields">
                  <div class="form-group">
                    <label class="form-label">Guest Comment Target</label>
                    <input 
                      type="text" 
                      v-model="briefingForm.guest_comment_target"
                      class="form-input"
                      placeholder="Enter guest comment target"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">Trip Advisor Target</label>
                    <input 
                      type="text" 
                      v-model="briefingForm.trip_advisor_target"
                      class="form-input"
                      placeholder="Enter trip advisor target"
                    />
                  </div>

                  <div class="form-group">
                    <label class="form-label">Other Preparation</label>
                    <input 
                      type="text" 
                      v-model="briefingForm.other_preparation"
                      class="form-input"
                      placeholder="Enter other preparation"
                    />
                  </div>
                </template>
              </div>

              <div class="form-actions">
                <button type="submit" :disabled="loading" class="btn btn-primary">
                  <div v-if="loading" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                  <i v-else class="fa-solid fa-save"></i>
                  {{ loading ? 'Saving...' : 'Save Briefing' }}
                </button>
              </div>
            </form>
          </div>

          <!-- Productivity Section -->
          <div v-if="activeSection === 'productivity'" class="form-section">
            <h2 class="section-title">III. EMPLOYEE PRODUCTIVITY PROGRAM</h2>
            
            <form @submit.prevent="saveProductivity">
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Product Knowledge Test</label>
                  <textarea 
                    v-model="productivityForm.product_knowledge_test"
                    rows="3"
                    class="form-textarea"
                    placeholder="Masukkan Product Knowledge Test"
                  ></textarea>
                </div>

                <div v-if="showServiceFields" class="form-group">
                  <label class="form-label">SOS & Hospitality Role Play</label>
                  <textarea 
                    v-model="productivityForm.sos_hospitality_role_play"
                    rows="3"
                    class="form-textarea"
                    placeholder="Masukkan SOS & Hospitality Role Play"
                  ></textarea>
                </div>

                <div v-if="showBarFields" class="form-group">
                  <label class="form-label">SOS & Hospitality Role Play</label>
                  <textarea 
                    v-model="productivityForm.sos_hospitality_role_play"
                    rows="3"
                    class="form-textarea"
                    placeholder="Masukkan SOS & Hospitality Role Play"
                  ></textarea>
                </div>

                <div class="form-group">
                  <label class="form-label">Employee Daily Coaching</label>
                  <textarea 
                    v-model="productivityForm.employee_daily_coaching"
                    rows="3"
                    class="form-textarea"
                    placeholder="Masukkan Employee Daily Coaching"
                  ></textarea>
                </div>

                <div class="form-group">
                  <label class="form-label">Others Activity</label>
                  <textarea 
                    v-model="productivityForm.others_activity"
                    rows="3"
                    class="form-textarea"
                    placeholder="Masukkan Others Activity"
                  ></textarea>
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" :disabled="loading" class="btn btn-primary">
                  <div v-if="loading" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                  <i v-else class="fa-solid fa-save"></i>
                  {{ loading ? 'Saving...' : 'Save Productivity' }}
                </button>
              </div>
            </form>
          </div>

          <!-- Visit Table Section -->
          <div v-if="activeSection === 'visit-table'" class="form-section">
            <h2 class="section-title">IV. VISIT TABLE</h2>
            
            <form @submit.prevent="addVisitTable">
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label">Guest Name</label>
                  <input 
                    type="text" 
                    v-model="visitTableForm.guest_name"
                    class="form-input"
                    placeholder="Enter guest name"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Table No</label>
                  <input 
                    type="text" 
                    v-model="visitTableForm.table_no"
                    class="form-input"
                    placeholder="Enter table number"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">No of Pax</label>
                  <input 
                    type="number" 
                    v-model="visitTableForm.no_of_pax"
                    class="form-input"
                    placeholder="Enter number of pax"
                  />
                </div>

                <div class="form-group">
                  <label class="form-label">Guest Experience</label>
                  <textarea 
                    v-model="visitTableForm.guest_experience"
                    rows="3"
                    class="form-textarea"
                    placeholder="Enter guest experience"
                  ></textarea>
                </div>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn btn-secondary">
                  <i class="fa-solid fa-plus"></i>
                  Add Visit Table
                </button>
                <button type="button" @click="saveVisitTable" :disabled="loading || visitTables.length === 0" class="btn btn-primary">
                  <div v-if="loading" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                  <i v-else class="fa-solid fa-save"></i>
                  {{ loading ? 'Saving...' : 'Save All Tables' }}
                </button>
              </div>
            </form>

            <!-- Visit Tables List -->
            <div v-if="visitTables.length > 0" class="visit-tables-list">
              <h3 class="subsection-title">Visit Tables ({{ visitTables.length }})</h3>
              <div class="table-list">
                <div v-for="(table, index) in visitTables" :key="index" class="table-item">
                  <div class="table-info">
                    <div class="table-guest">{{ table.guest_name }}</div>
                    <div class="table-details">Table {{ table.table_no }} • {{ table.no_of_pax }} pax</div>
                    <div class="table-experience">{{ table.guest_experience }}</div>
                  </div>
                  <button @click="removeVisitTable(index)" class="remove-btn">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Summary Section -->
          <div v-if="activeSection === 'summary'" class="form-section">
            <h2 class="section-title">
              {{ isLunch ? 'REPORT SUMMARY 1 / NOTES' : 'REPORT SUMMARY 2 / NOTES' }}
            </h2>
            
            <form @submit.prevent="saveSummary">
              <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea 
                  v-model="summaryForm.notes"
                  rows="6"
                  class="form-textarea"
                  placeholder="Enter your notes and summary..."
                ></textarea>
              </div>

              <div class="form-actions">
                <button type="submit" :disabled="loading" class="btn btn-primary">
                  <div v-if="loading" class="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></div>
                  <i v-else class="fa-solid fa-save"></i>
                  {{ loading ? 'Saving...' : 'Save Summary' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </main>
    </div>
  </AppLayout>
</template>

<style scoped>
/* Base styles */
.post-inspection-container {
  display: flex;
  min-height: calc(100vh - 4rem);
  background-color: #f3f4f6;
}


/* Sidebar */
.sidebar {
  position: fixed;
  top: 4rem; /* Start below main app header */
  left: 18rem; /* Start after main app sidebar */
  bottom: 0;
  width: 18rem;
  background-color: #ffffff;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  z-index: 20; /* Lower than main app sidebar */
  transition: transform 0.3s ease-in-out;
}

.sidebar-open {
  transform: translateX(0);
}

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 4rem;
  border-bottom: 1px solid #e5e7eb;
  padding: 0 1rem;
}

.sidebar-header h3 {
  font-size: 1.125rem;
  font-weight: 600;
  color: #1f2937;
}

.menu-btn, .close-btn {
  color: #6b7280;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 1.125rem;
}

.section-list {
  flex: 1;
  overflow-y: auto;
  padding: 1rem 0;
}

.section-item {
  display: flex;
  align-items: center;
  padding: 0.75rem 1rem;
  cursor: pointer;
  transition: all 0.2s ease;
  border-left: 3px solid transparent;
}

.section-item:hover {
  background-color: #f3f4f6;
}

.section-item.active {
  background-color: #dbeafe;
  border-left-color: #2563eb;
  color: #1d4ed8;
}

.section-item.completed {
  background-color: #f0fdf4;
  border-left-color: #16a34a;
}

.section-item.completed.active {
  background-color: #dcfce7;
  border-left-color: #16a34a;
}

.section-icon {
  margin-right: 0.75rem;
  color: #6b7280;
}

.section-item.active .section-icon {
  color: #2563eb;
}

.section-item.completed .section-icon {
  color: #16a34a;
}

.section-info {
  flex: 1;
}

.section-name {
  font-weight: 500;
  color: #374151;
}

.section-item.active .section-name {
  color: #1d4ed8;
  font-weight: 600;
}

.section-item.completed .section-name {
  color: #15803d;
}

.section-status {
  margin-left: 0.5rem;
}

.sidebar-footer {
  padding: 1rem;
  border-top: 1px solid #e5e7eb;
}

/* Main Content */
.main-content {
  flex: 1;
  margin-left: 36rem; /* 18rem (main sidebar) + 18rem (post-inspection sidebar) */
  transition: margin-left 0.3s ease-in-out;
  padding-top: 0; /* No extra padding since sidebar starts below header */
}

.main-content-shifted {
  margin-left: 18rem; /* Only shift by main sidebar width */
}

.form-container {
  max-width: 4xl;
  margin: 0 auto;
  padding: 2rem;
}

.form-header {
  margin-bottom: 2rem;
}

.form-title {
  font-size: 2rem;
  font-weight: 700;
  color: #1f2937;
  margin-bottom: 0.5rem;
}

.form-subtitle {
  color: #6b7280;
  font-size: 1rem;
}

.form-section {
  background-color: #ffffff;
  border-radius: 0.75rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  padding: 2rem;
  margin-bottom: 2rem;
}

.section-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 1.5rem;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid #e5e7eb;
}

.subsection-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 1rem;
}

.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  font-weight: 500;
  color: #374151;
  margin-bottom: 0.5rem;
}

.form-input, .form-textarea {
  padding: 0.75rem;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  font-size: 1rem;
  transition: border-color 0.2s ease;
}

.form-input:focus, .form-textarea:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-textarea {
  resize: vertical;
  min-height: 100px;
}

/* Autocomplete */
.autocomplete-container {
  position: relative;
}

.selected-participants {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.autocomplete-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background-color: #ffffff;
  border: 1px solid #d1d5db;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  z-index: 10;
  max-height: 200px;
  overflow-y: auto;
}

/* Visit Tables */
.visit-tables-list {
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid #e5e7eb;
}

.table-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.table-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem;
  background-color: #f9fafb;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
}

.table-info {
  flex: 1;
}

.table-guest {
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 0.25rem;
}

.table-details {
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 0.25rem;
}

.table-experience {
  font-size: 0.875rem;
  color: #374151;
}

.remove-btn {
  color: #dc2626;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0.5rem;
  border-radius: 0.25rem;
  transition: background-color 0.2s ease;
}

.remove-btn:hover {
  background-color: #fef2f2;
}

/* Buttons */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  font-weight: 500;
  text-decoration: none;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-primary {
  background-color: #2563eb;
  color: #ffffff;
}

.btn-primary:hover:not(:disabled) {
  background-color: #1d4ed8;
}

.btn-secondary {
  background-color: #6b7280;
  color: #ffffff;
}

.btn-secondary:hover:not(:disabled) {
  background-color: #4b5563;
}

.btn-success {
  background-color: #16a34a;
  color: #ffffff;
}

.btn-success:hover:not(:disabled) {
  background-color: #15803d;
}

.btn-large {
  padding: 1rem 2rem;
  font-size: 1rem;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.form-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  margin-top: 2rem;
  padding-top: 2rem;
  border-top: 1px solid #e5e7eb;
}

/* Mobile Header */
.mobile-header {
  display: none;
}

/* Mobile styles */
@media (max-width: 768px) {
  .mobile-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: white;
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    z-index: 30;
  }

  .menu-btn {
    padding: 0.5rem;
    border-radius: 0.5rem;
    background: #f3f4f6;
    border: none;
    cursor: pointer;
  }

  .progress-indicator {
    font-size: 0.875rem;
    color: #6b7280;
  }

  .close-btn {
    padding: 0.5rem;
    border-radius: 0.5rem;
    background: #f3f4f6;
    border: none;
    cursor: pointer;
  }

  .sidebar {
    transform: translateX(-100%);
    top: 0; /* Full height on mobile */
    left: 0; /* Start from left edge on mobile */
    z-index: 40; /* Higher than main app sidebar on mobile */
    width: 100%; /* Full width on mobile */
  }
  
  .sidebar-open {
    transform: translateX(0);
  }

  .main-content {
    margin-left: 0 !important; /* No margin on mobile */
    padding-top: 0;
  }
  
  .main-content-shifted {
    margin-left: 0; /* No shift on mobile */
  }
  
  .form-container {
    padding: 1rem;
  }
  
  .form-grid {
    grid-template-columns: 1fr;
  }
  
  .form-actions {
    flex-direction: column;
  }
  
  .btn {
    justify-content: center;
  }
}
</style>