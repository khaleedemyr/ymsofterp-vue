<script setup>
import { ref, watch, onMounted, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  inspections: Object, // { data, links, meta }
  filters: Object,
  statistics: Object,
  outlets: Array,
  departemenOptions: Array,
  statusOptions: Array,
});

// Get current user from page props
const page = usePage();
const currentUser = computed(() => page.props.auth?.user || {});
const isOutletUser = computed(() => currentUser.value.id_outlet && currentUser.value.id_outlet !== 1);


const search = ref(props.filters?.search || '');
const outletId = ref(props.filters?.outlet_id || '');
const departemen = ref(props.filters?.departemen || '');
const status = ref(props.filters?.status || '');
const perPage = ref(props.filters?.per_page || 15);

// Lightbox functionality
const showImageModal = ref(false);
const selectedImageUrl = ref('');

// Summary modal
const showSummary = ref(false);
const summaryData = ref({});
const expandedOutlets = ref(new Set());
const expandedDepartemens = ref(new Set());
const selectedMonth = ref(new Date().getMonth());
const selectedYear = ref(new Date().getFullYear());

// Debounced search
const debouncedSearch = debounce((value) => {
  search.value = value;
}, 300);

function handleSearch(event) {
  debouncedSearch(event.target.value);
}

function filter() {
  router.get(route('inspections.index'), {
    search: search.value,
    outlet_id: outletId.value,
    departemen: departemen.value,
    status: status.value,
    per_page: perPage.value,
  }, {
    preserveState: true,
    replace: true,
  });
}

function clearFilters() {
  search.value = '';
  outletId.value = '';
  departemen.value = '';
  status.value = '';
  perPage.value = 15;
  filter();
}

function reload() {
  router.reload();
}

// Get initials from name (copied from Home.vue)
function getInitials(name) {
  if (!name) return 'U';
  return name.split(' ').map(word => word.charAt(0)).join('').toUpperCase().slice(0, 2);
}

// Lightbox methods
function getImageUrl(avatar) {
  return `/storage/${avatar}`;
}

function openImageModal(imageUrl) {
  selectedImageUrl.value = imageUrl;
  showImageModal.value = true;
}

function closeImageModal() {
  showImageModal.value = false;
  selectedImageUrl.value = '';
}

// CPA function
function openCPA(inspection) {
  router.visit(route('inspections.cpa', inspection.id));
}

// Computed properties for button visibility
const shouldShowCPAButton = (inspection) => {
  return inspection.status === 'Completed';
};

const shouldShowOtherButtons = (inspection) => {
  return !isOutletUser.value;
};

const shouldShowDeleteButton = (inspection) => {
  return !isOutletUser.value && inspection.created_by === currentUser.value.id;
};

    // Get unique inspectors from inspection details
    function getUniqueInspectors(inspection) {
      if (!inspection.details || inspection.details.length === 0) {
        return [inspection.created_by_user].filter(Boolean);
      }
      
      const inspectors = new Map();
      
      // Add main creator
      if (inspection.created_by_user) {
        inspectors.set(inspection.created_by_user.id, inspection.created_by_user);
      }
      
      // Add inspectors from details
      inspection.details.forEach(detail => {
        if (detail.created_by_user) {
          inspectors.set(detail.created_by_user.id, detail.created_by_user);
        }
      });
      
      return Array.from(inspectors.values());
    }

    // Get star rating based on score
    function getStarRating(score) {
      if (score >= 90) return 5; // 5 stars
      if (score >= 80) return 4; // 4 stars
      if (score >= 70) return 3; // 3 stars
      if (score >= 60) return 2; // 2 stars
      if (score >= 50) return 1; // 1 star
      return 0; // 0 stars
    }

    // Get passing score for departemen
    function getPassingScore(departemen) {
      const passingScores = {
        'Kitchen': 85,
        'Bar': 90,
        'Service': 95
      };
      return passingScores[departemen] || 80; // Default 80% if not found
    }

    // Generate summary data with outlet and departemen grouping
    function generateSummary() {
      const summary = {};
      
      props.inspections.data.forEach(inspection => {
        const inspectionDate = new Date(inspection.inspection_date);
        const inspectionMonth = inspectionDate.getMonth();
        const inspectionYear = inspectionDate.getFullYear();
        
        // Only include inspections from selected month and year
        if (inspectionMonth === selectedMonth.value && inspectionYear === selectedYear.value) {
          const outletId = inspection.outlet_id;
          const outletName = inspection.outlet?.nama_outlet || 'Unknown Outlet';
          const departemen = inspection.departemen;
          
          // Initialize outlet if not exists
          if (!summary[outletId]) {
            summary[outletId] = {
              outlet_name: outletName,
              total_inspections: 0,
              total_findings: 0,
              total_points: 0,
              average_score: 0,
              departemens: {}
            };
          }
          
          // Initialize departemen if not exists
          if (!summary[outletId].departemens[departemen]) {
            summary[outletId].departemens[departemen] = {
              departemen_name: departemen,
              total_inspections: 0,
              total_findings: 0,
              total_points: 0,
              average_score: 0,
              passing_score: getPassingScore(departemen),
              inspections: []
            };
          }
          
          // Add to outlet totals
          summary[outletId].total_inspections++;
          summary[outletId].total_findings += inspection.total_findings || 0;
          summary[outletId].total_points += inspection.total_points || 0;
          
          // Add to departemen totals
          summary[outletId].departemens[departemen].total_inspections++;
          summary[outletId].departemens[departemen].total_findings += inspection.total_findings || 0;
          summary[outletId].departemens[departemen].total_points += inspection.total_points || 0;
          summary[outletId].departemens[departemen].inspections.push(inspection);
        }
      });
      
      // Calculate average scores for outlet and departemen
      Object.keys(summary).forEach(outletId => {
        const outlet = summary[outletId];
        let totalScore = 0;
        let totalInspections = 0;
        
        // Calculate outlet average
        Object.keys(outlet.departemens).forEach(departemen => {
          const dept = outlet.departemens[departemen];
          if (dept.total_inspections > 0) {
            const deptTotalScore = dept.inspections.reduce((sum, inspection) => {
              return sum + (inspection.score || 0);
            }, 0);
            dept.average_score = Math.round(deptTotalScore / dept.total_inspections);
            totalScore += deptTotalScore;
            totalInspections += dept.total_inspections;
          }
        });
        
        if (totalInspections > 0) {
          outlet.average_score = Math.round(totalScore / totalInspections);
        }
      });
      
      summaryData.value = summary;
    }

    // Toggle outlet expansion
    function toggleOutlet(outletId) {
      if (expandedOutlets.value.has(outletId)) {
        expandedOutlets.value.delete(outletId);
        // Also close all departemens in this outlet
        Object.keys(summaryData.value[outletId]?.departemens || {}).forEach(dept => {
          expandedDepartemens.value.delete(`${outletId}-${dept}`);
        });
      } else {
        expandedOutlets.value.add(outletId);
      }
    }

    // Toggle departemen expansion
    function toggleDepartemen(outletId, departemen) {
      const key = `${outletId}-${departemen}`;
      if (expandedDepartemens.value.has(key)) {
        expandedDepartemens.value.delete(key);
      } else {
        expandedDepartemens.value.add(key);
      }
    }

    // Month options
    const monthOptions = [
      { value: 0, label: 'January' },
      { value: 1, label: 'February' },
      { value: 2, label: 'March' },
      { value: 3, label: 'April' },
      { value: 4, label: 'May' },
      { value: 5, label: 'June' },
      { value: 6, label: 'July' },
      { value: 7, label: 'August' },
      { value: 8, label: 'September' },
      { value: 9, label: 'October' },
      { value: 10, label: 'November' },
      { value: 11, label: 'December' }
    ];

    // Year options (current year ± 2 years)
    const yearOptions = computed(() => {
      const currentYear = new Date().getFullYear();
      const years = [];
      for (let i = currentYear - 2; i <= currentYear + 2; i++) {
        years.push({ value: i, label: i.toString() });
      }
      return years;
    });

function view(inspection) {
  router.visit(route('inspections.show', inspection.id));
}

function edit(inspection) {
  router.visit(route('inspections.add-finding', inspection.id));
}

async function complete(inspection) {
  const result = await Swal.fire({
    title: 'Complete Inspection?',
    text: `Are you sure you want to complete this inspection?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, Complete!',
    cancelButtonText: 'Cancel',
  });
  if (!result.isConfirmed) return;
  
  try {
    await router.patch(route('inspections.complete', inspection.id), {}, {
      onSuccess: () => {
        Swal.fire('Success', 'Inspection completed successfully!', 'success');
      },
      onError: () => {
        Swal.fire('Error', 'Failed to complete inspection', 'error');
      }
    });
  } catch (error) {
    Swal.fire('Error', 'Failed to complete inspection', 'error');
  }
}

async function hapus(inspection) {
  const result = await Swal.fire({
    title: 'Delete Inspection?',
    text: `Are you sure you want to delete this inspection?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Yes, Delete!',
    cancelButtonText: 'Cancel',
  });
  if (!result.isConfirmed) return;
  
  router.delete(route('inspections.destroy', inspection.id), {
    onSuccess: () => Swal.fire('Success', 'Inspection deleted successfully!', 'success'),
  });
}

// Watch for filter changes
watch([outletId, departemen, status, perPage], () => {
  filter();
});

// Watch for summary modal
watch(showSummary, (newValue) => {
  if (newValue) {
    generateSummary();
  }
});

// Watch for month/year changes
watch([selectedMonth, selectedYear], () => {
  if (showSummary.value) {
    generateSummary();
  }
});

onMounted(() => {
  // Auto-filter on mount if filters are set
  if (search.value || outletId.value || departemen.value || status.value) {
    filter();
  }
});
</script>

<template>
  <AppLayout title="Inspections">
    <div class="w-full py-8 px-4">
      <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
          <div class="flex items-center gap-4">
            <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
              <i class="fa-solid fa-clipboard-check text-blue-500"></i>
              Inspections
            </h1>
          </div>
          <div class="flex gap-3">
            <button @click="reload" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-medium transition">
              <i class="fa-solid fa-refresh mr-2"></i>Refresh
            </button>
            <button 
              @click="showSummary = true"
              class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-xl font-medium transition"
            >
              <i class="fa-solid fa-chart-bar mr-2"></i>Summary
            </button>
            <a :href="route('inspections.create')" class="px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-medium transition">
              <i class="fa-solid fa-plus mr-2"></i>New Inspection
            </a>
          </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Inspections</p>
                <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
                <p class="text-sm text-gray-500">All inspections</p>
              </div>
              <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-clipboard-check text-blue-500 text-xl"></i>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-yellow-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Draft</p>
                <p class="text-2xl font-bold text-gray-900">{{ statistics.draft }}</p>
                <p class="text-sm text-gray-500">In progress</p>
              </div>
              <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-edit text-yellow-500 text-xl"></i>
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Completed</p>
                <p class="text-2xl font-bold text-gray-900">{{ statistics.completed }}</p>
                <p class="text-sm text-gray-500">Finished</p>
              </div>
              <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                <i class="fa-solid fa-check-circle text-green-500 text-xl"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
          <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
              <button @click="clearFilters" class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition">
                <i class="fa-solid fa-times mr-1"></i>Clear
              </button>
            </div>
            
            <div class="flex items-center gap-2">
              <select v-model="outletId" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Outlets</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">{{ outlet.nama_outlet }}</option>
              </select>
            </div>

            <div class="flex items-center gap-2">
              <select v-model="departemen" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Departments</option>
                <option v-for="dept in departemenOptions" :key="dept" :value="dept">{{ dept }}</option>
              </select>
            </div>

            <div class="flex items-center gap-2">
              <select v-model="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Status</option>
                <option v-for="statusOption in statusOptions" :key="statusOption" :value="statusOption">{{ statusOption }}</option>
              </select>
            </div>

            <div class="flex items-center gap-2">
              <select v-model="perPage" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="15">15 per page</option>
                <option value="25">25 per page</option>
                <option value="50">50 per page</option>
                <option value="100">100 per page</option>
              </select>
            </div>

            <div class="flex-1">
              <input 
                @input="handleSearch"
                :value="search"
                type="text" 
                placeholder="Search Inspector, Outlet, or Guidance..." 
                class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
          </div>
        </div>

        <!-- Inspection Cards -->
        <div v-if="inspections.data.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div 
            v-for="inspection in inspections.data" 
            :key="inspection.id" 
            class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100"
          >
            <!-- Card Header -->
            <div class="p-6 pb-4">
              <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-800 mb-1">{{ inspection.outlet?.nama_outlet || '-' }}</h3>
                  <p class="text-sm text-gray-600">{{ inspection.departemen }}</p>
                </div>
                <span :class="[
                  'px-3 py-1 rounded-full text-xs font-semibold',
                  inspection.status === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                ]">
                  {{ inspection.status }}
                </span>
              </div>

              <!-- Inspectors Info -->
              <div class="mb-4">
                <div class="flex items-center gap-2 mb-3">
                  <i class="fa-solid fa-users text-gray-500"></i>
                  <span class="text-sm font-medium text-gray-600">Inspectors ({{ getUniqueInspectors(inspection).length }})</span>
                </div>
                
                <div class="flex flex-wrap gap-2">
                  <div 
                    v-for="inspector in getUniqueInspectors(inspection)" 
                    :key="inspector.id"
                    class="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-2"
                  >
                    <div v-if="inspector.avatar" class="w-8 h-8 rounded-full overflow-hidden cursor-pointer hover:scale-110 transition-transform" @click="openImageModal(getImageUrl(inspector.avatar))">
                      <img :src="getImageUrl(inspector.avatar)" :alt="inspector.nama_lengkap" class="w-full h-full object-cover" />
                    </div>
                    <div v-else class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold">
                      {{ getInitials(inspector.nama_lengkap || 'U') }}
                    </div>
                    <span class="text-sm font-medium text-gray-700">{{ inspector.nama_lengkap }}</span>
                  </div>
                </div>
              </div>

              <!-- Auditees Info -->
              <div v-if="inspection.auditees && inspection.auditees.length > 0" class="mb-4">
                <div class="flex items-center gap-2 mb-3">
                  <i class="fa-solid fa-user-check text-gray-500"></i>
                  <span class="text-sm font-medium text-gray-600">Auditees ({{ inspection.auditees.length }})</span>
                </div>
                
                <div class="flex flex-wrap gap-2">
                  <div 
                    v-for="auditee in inspection.auditees" 
                    :key="auditee.id"
                    class="flex items-center gap-2 bg-blue-50 rounded-lg px-3 py-2"
                  >
                    <div v-if="auditee.avatar" class="w-8 h-8 rounded-full overflow-hidden cursor-pointer hover:scale-110 transition-transform" @click="openImageModal(getImageUrl(auditee.avatar))">
                      <img :src="getImageUrl(auditee.avatar)" :alt="auditee.nama_lengkap" class="w-full h-full object-cover" />
                    </div>
                    <div v-else class="w-8 h-8 rounded-full bg-gradient-to-br from-green-500 to-blue-600 flex items-center justify-center text-white text-xs font-bold">
                      {{ getInitials(auditee.nama_lengkap || 'U') }}
                    </div>
                    <span class="text-sm font-medium text-gray-700">{{ auditee.nama_lengkap }}</span>
                  </div>
                </div>
              </div>

              <!-- Guidance Info -->
              <div class="mb-4">
                <p class="text-sm font-medium text-gray-600 mb-1">Guidance</p>
                <p class="text-sm text-gray-800">{{ inspection.guidance?.title || '-' }}</p>
              </div>

              <!-- Stats -->
              <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="text-center">
                  <div class="text-2xl font-bold text-blue-600">{{ inspection.total_findings }}</div>
                  <div class="text-xs text-gray-500">Findings</div>
                </div>
                    <div class="text-center">
                      <div :class="[
                        'text-2xl font-bold',
                        inspection.score >= 80 ? 'text-green-600' : 
                        inspection.score >= 60 ? 'text-yellow-600' : 'text-red-600'
                      ]">{{ inspection.score || 0 }}%</div>
                      <div class="text-xs text-gray-500 mb-1">Score</div>
                      <!-- Star Rating -->
                      <div class="flex justify-center gap-1">
                        <i 
                          v-for="star in 5" 
                          :key="star"
                          :class="[
                            'text-sm',
                            star <= getStarRating(inspection.score) ? 'text-yellow-400' : 'text-gray-300'
                          ]"
                          class="fa-solid fa-star"
                        ></i>
                      </div>
                    </div>
              </div>

              <!-- Points Breakdown -->
              <div class="bg-gray-50 rounded-lg p-3 mb-4">
                <div class="grid grid-cols-2 gap-4 text-center">
                  <div>
                    <div class="text-lg font-bold text-green-600">{{ inspection.total_points }}</div>
                    <div class="text-xs text-gray-500">Inspection Points</div>
                  </div>
                  <div>
                    <div class="text-lg font-bold text-blue-600">{{ inspection.guidance_total_points || 0 }}</div>
                    <div class="text-xs text-gray-500">Guidance Points</div>
                  </div>
                </div>
              </div>

              <!-- Created Date -->
              <div class="flex items-center gap-2 text-sm text-gray-500">
                <i class="fa-solid fa-calendar"></i>
                <span>{{ new Date(inspection.inspection_date).toLocaleDateString('id-ID', { 
                  day: 'numeric', 
                  month: 'long', 
                  year: 'numeric' 
                }) }}</span>
              </div>
            </div>

            <!-- Actions -->
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
              <div class="flex items-center justify-between">
                <!-- Left side buttons -->
                <div class="flex items-center gap-2">
                  <!-- Regular buttons for non-outlet users -->
                  <template v-if="shouldShowOtherButtons(inspection)">
                    <button @click="view(inspection)" class="p-2 text-blue-600 hover:bg-blue-100 rounded-lg transition" title="View">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                    <button @click="edit(inspection)" class="p-2 text-yellow-600 hover:bg-yellow-100 rounded-lg transition" title="Add Finding">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button v-if="inspection.status === 'Draft'" @click="complete(inspection)" class="p-2 text-green-600 hover:bg-green-100 rounded-lg transition" title="Complete">
                      <i class="fa-solid fa-check"></i>
                    </button>
                  </template>
                  
                  <!-- CPA button for completed inspections -->
                  <button v-if="shouldShowCPAButton(inspection)" @click="openCPA(inspection)" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition font-medium" title="Corrective and Preventive Action">
                    <i class="fa-solid fa-clipboard-list mr-2"></i>CPA
                  </button>
                </div>
                
                <!-- Right side buttons -->
                <div class="flex items-center gap-2">
                  <!-- Delete button (only for creator) -->
                  <button v-if="shouldShowDeleteButton(inspection)" @click="hapus(inspection)" class="p-2 text-red-600 hover:bg-red-100 rounded-lg transition" title="Delete">
                    <i class="fa-solid fa-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="inspections.data.length === 0" class="text-center py-12">
          <i class="fa-solid fa-clipboard-check text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-lg font-semibold text-gray-500 mb-2">No Inspections Found</h3>
          <p class="text-gray-400 mb-6">Start by creating your first inspection</p>
          <a :href="route('inspections.create')" class="px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-medium transition">
            <i class="fa-solid fa-plus mr-2"></i>Create Inspection
          </a>
        </div>

        <!-- Pagination -->
        <div v-if="inspections.data.length > 0" class="mt-6 flex items-center justify-between">
          <div class="text-sm text-gray-700">
            Showing {{ inspections.from }} - {{ inspections.to }} of {{ inspections.total }} results
          </div>
          <div class="flex items-center gap-2">
            <template v-for="link in inspections.links" :key="link.label">
              <button 
                v-if="link.url"
                @click="router.visit(link.url)"
                v-html="link.label"
                :class="[
                  'px-3 py-2 text-sm rounded-lg transition',
                  link.active ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                ]"
              ></button>
              <span v-else v-html="link.label" class="px-3 py-2 text-sm text-gray-400"></span>
            </template>
          </div>
        </div>
      </div>

      <!-- Summary Modal -->
      <div v-if="showSummary" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-2xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-hidden">
          <!-- Modal Header -->
          <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6">
            <div class="flex justify-between items-center mb-4">
              <h2 class="text-2xl font-bold flex items-center">
                <i class="fa-solid fa-chart-bar mr-3"></i>
                Inspection Summary
              </h2>
              <button 
                @click="showSummary = false"
                class="text-white hover:text-gray-200 text-2xl"
              >
                <i class="fa-solid fa-times"></i>
              </button>
            </div>
            
            <!-- Month/Year Filter -->
            <div class="flex items-center gap-4">
              <div class="flex items-center gap-2">
                <label class="text-sm font-medium">Month:</label>
                <select 
                  v-model="selectedMonth"
                  class="px-3 py-1 rounded text-gray-800 text-sm focus:ring-2 focus:ring-white focus:outline-none"
                >
                  <option v-for="month in monthOptions" :key="month.value" :value="month.value">
                    {{ month.label }}
                  </option>
                </select>
              </div>
              
              <div class="flex items-center gap-2">
                <label class="text-sm font-medium">Year:</label>
                <select 
                  v-model="selectedYear"
                  class="px-3 py-1 rounded text-gray-800 text-sm focus:ring-2 focus:ring-white focus:outline-none"
                >
                  <option v-for="year in yearOptions" :key="year.value" :value="year.value">
                    {{ year.label }}
                  </option>
                </select>
              </div>
              
              <div class="text-sm text-green-100">
                {{ monthOptions[selectedMonth].label }} {{ selectedYear }}
              </div>
            </div>
          </div>

          <!-- Modal Content -->
          <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
            <div v-if="Object.keys(summaryData).length === 0" class="text-center py-8">
              <i class="fa-solid fa-inbox text-4xl text-gray-400 mb-4"></i>
              <p class="text-gray-500 text-lg">No inspections found for this month</p>
            </div>

            <div v-else class="space-y-4">
              <div 
                v-for="(outlet, outletId) in summaryData" 
                :key="outletId"
                class="bg-white border border-gray-200 rounded-xl shadow-sm"
              >
                <!-- Outlet Header -->
                <div 
                  @click="toggleOutlet(outletId)"
                  class="p-4 cursor-pointer hover:bg-gray-50 transition"
                >
                  <div class="flex justify-between items-center">
                    <div class="flex items-center">
                      <h3 class="text-lg font-semibold text-gray-800">{{ outlet.outlet_name }}</h3>
                      <span class="ml-3 px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full">
                        {{ outlet.total_inspections }} inspection{{ outlet.total_inspections > 1 ? 's' : '' }}
                      </span>
                    </div>
                    <div class="flex items-center gap-4">
                      <!-- Summary Stats -->
                      <div class="text-right">
                        <div class="text-sm text-gray-500">Avg Score</div>
                        <div :class="[
                          'text-lg font-bold',
                          outlet.average_score >= 80 ? 'text-green-600' : 
                          outlet.average_score >= 60 ? 'text-yellow-600' : 'text-red-600'
                        ]">{{ outlet.average_score }}%</div>
                      </div>
                      <div class="text-right">
                        <div class="text-sm text-gray-500">Total Findings</div>
                        <div class="text-lg font-bold text-blue-600">{{ outlet.total_findings }}</div>
                      </div>
                      <div class="text-right">
                        <div class="text-sm text-gray-500">Total Points</div>
                        <div class="text-lg font-bold text-purple-600">{{ outlet.total_points }}</div>
                      </div>
                      <i :class="[
                        'fa-solid transition-transform',
                        expandedOutlets.has(outletId) ? 'fa-chevron-up' : 'fa-chevron-down'
                      ]"></i>
                    </div>
                  </div>
                </div>

                <!-- Expanded Content -->
                <div v-if="expandedOutlets.has(outletId)" class="border-t border-gray-200">
                  <div class="p-4 space-y-4">
                    <!-- Departemen Groups -->
                    <div 
                      v-for="(departemen, deptName) in outlet.departemens" 
                      :key="deptName"
                      class="bg-gray-50 rounded-lg border border-gray-200"
                    >
                      <!-- Departemen Header -->
                      <div 
                        @click="toggleDepartemen(outletId, deptName)"
                        class="p-4 cursor-pointer hover:bg-gray-100 transition"
                      >
                        <div class="flex justify-between items-center">
                          <div class="flex items-center">
                            <h4 class="text-md font-semibold text-gray-800">{{ departemen.departemen_name }}</h4>
                            <span class="ml-3 px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                              {{ departemen.total_inspections }} inspection{{ departemen.total_inspections > 1 ? 's' : '' }}
                            </span>
                          </div>
                          <div class="flex items-center gap-4">
                            <div class="text-right">
                              <div class="text-xs text-gray-500">Avg Score</div>
                              <div :class="[
                                'text-sm font-bold',
                                departemen.average_score >= departemen.passing_score ? 'text-green-600' : 'text-red-600'
                              ]">{{ departemen.average_score }}%</div>
                              <div class="text-xs text-gray-400">
                                Pass: {{ departemen.passing_score }}%
                              </div>
                            </div>
                            <div class="text-right">
                              <div class="text-xs text-gray-500">Findings</div>
                              <div class="text-sm font-bold text-blue-600">{{ departemen.total_findings }}</div>
                            </div>
                            <div class="text-right">
                              <div class="text-xs text-gray-500">Points</div>
                              <div class="text-sm font-bold text-purple-600">{{ departemen.total_points }}</div>
                            </div>
                            <div class="text-right">
                              <div class="text-xs text-gray-500">Status</div>
                              <span :class="[
                                'px-2 py-1 text-xs rounded-full font-medium',
                                departemen.average_score >= departemen.passing_score ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                              ]">
                                {{ departemen.average_score >= departemen.passing_score ? 'PASS' : 'FAIL' }}
                              </span>
                            </div>
                            <i :class="[
                              'fa-solid transition-transform',
                              expandedDepartemens.has(`${outletId}-${deptName}`) ? 'fa-chevron-up' : 'fa-chevron-down'
                            ]"></i>
                          </div>
                        </div>
                      </div>

                      <!-- Departemen Inspections -->
                      <div v-if="expandedDepartemens.has(`${outletId}-${deptName}`)" class="border-t border-gray-200">
                        <div class="p-4 space-y-3">
                          <div 
                            v-for="inspection in departemen.inspections" 
                            :key="inspection.id"
                            class="bg-white rounded-lg p-4 hover:bg-gray-50 transition cursor-pointer border border-gray-100"
                            @click="view(inspection)"
                          >
                            <div class="flex justify-between items-center">
                              <div class="flex items-center gap-4">
                                <div>
                                  <div class="font-medium text-gray-800">
                                    {{ inspection.guidance?.title || 'No Guidance' }}
                                  </div>
                                  <div class="text-sm text-gray-500">
                                    {{ new Date(inspection.inspection_date).toLocaleDateString() }}
                                  </div>
                                </div>
                              </div>
                              <div class="flex items-center gap-4">
                                <div class="text-center">
                                  <div class="text-sm text-gray-500">Score</div>
                                  <div :class="[
                                    'font-bold',
                                    inspection.score >= getPassingScore(inspection.departemen) ? 'text-green-600' : 'text-red-600'
                                  ]">{{ inspection.score || 0 }}%</div>
                                  <div class="text-xs text-gray-400">
                                    Pass: {{ getPassingScore(inspection.departemen) }}%
                                  </div>
                                </div>
                                <div class="text-center">
                                  <div class="text-sm text-gray-500">Findings</div>
                                  <div class="font-bold text-blue-600">{{ inspection.total_findings || 0 }}</div>
                                </div>
                                <div class="text-center">
                                  <div class="text-sm text-gray-500">Points</div>
                                  <div class="font-bold text-purple-600">{{ inspection.total_points || 0 }}</div>
                                </div>
                                <div class="text-center">
                                  <div class="text-sm text-gray-500">Pass/Fail</div>
                                  <span :class="[
                                    'px-2 py-1 text-xs rounded-full font-medium',
                                    inspection.score >= getPassingScore(inspection.departemen) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                  ]">
                                    {{ inspection.score >= getPassingScore(inspection.departemen) ? 'PASS' : 'FAIL' }}
                                  </span>
                                </div>
                                <div class="text-center">
                                  <div class="text-sm text-gray-500">Status</div>
                                  <span :class="[
                                    'px-2 py-1 text-xs rounded-full',
                                    inspection.status === 'Completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                                  ]">{{ inspection.status }}</span>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Lightbox Modal -->
      <div v-if="showImageModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" @click="closeImageModal">
        <div class="relative max-w-4xl max-h-[90vh] p-4" @click.stop>
          <button 
            @click="closeImageModal"
            class="absolute -top-4 -right-4 bg-white rounded-full p-2 shadow-lg hover:bg-gray-100 transition-colors z-10"
          >
            <i class="fa-solid fa-times text-gray-600"></i>
          </button>
          <img 
            :src="selectedImageUrl" 
            :alt="'Avatar preview'"
            class="max-w-full max-h-full object-contain rounded-lg shadow-2xl"
          />
        </div>
      </div>
    </div>
  </AppLayout>
</template>
