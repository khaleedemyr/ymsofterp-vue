<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import VueEasyLightbox from 'vue-easy-lightbox';

const props = defineProps({
  report: Object,
  inspectionStats: Object,
  permissions: {
    type: Object,
    default: () => ({
      can_edit: false,
      current_user_id: null,
    }),
  },
});

// Lightbox refs
const visibleRef = ref(false);
const indexRef = ref(0);
const imgsRef = ref([]);

// Computed properties
const inspectionTimeText = computed(() => {
  return props.report.inspection_time === 'lunch' ? 'Lunch' : 'Dinner';
});

const statusText = computed(() => {
  switch (props.report.status) {
    case 'draft':
      return 'Draft';
    case 'completed':
      return 'Completed';
    default:
      return 'Unknown';
  }
});

const statusColor = computed(() => {
  switch (props.report.status) {
    case 'draft':
      return 'bg-yellow-100 text-yellow-800';
    case 'completed':
      return 'bg-green-100 text-green-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
});

const progressPercentage = computed(() => {
  if (!props.report.report_areas) return 0;
  const totalAreas = props.report.progress?.length || 0;
  const completedAreas = props.report.report_areas.length;
  return totalAreas > 0 ? Math.round((completedAreas / totalAreas) * 100) : 0;
});

const canEditReport = computed(() => {
  // User can edit if they have admin role OR if they are the creator
  return props.permissions.can_edit || props.report.user_id === props.permissions.current_user_id;
});

function goBack() {
  router.visit('/daily-report');
}

function editReport() {
  router.visit(`/daily-report/${props.report.id}/inspect`);
}


// Lightbox functions
function showLightbox(images, index = 0) {
  imgsRef.value = images;
  indexRef.value = index;
  visibleRef.value = true;
}

function hideLightbox() {
  visibleRef.value = false;
}
</script>

<template>
  <AppLayout title="Daily Report Detail">
    <div class="max-w-6xl mx-auto py-8 px-4">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-clipboard-list text-blue-500"></i>
            Daily Report Detail
          </h1>
          <p class="text-gray-600 mt-1">View detailed information about this daily report</p>
        </div>
        <div class="flex gap-3">
          <button @click="goBack" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Reports
          </button>
          <button v-if="report.status === 'draft' && canEditReport" @click="editReport" class="btn btn-primary">
            <i class="fa-solid fa-edit"></i>
            Continue Inspection
          </button>
        </div>
      </div>

      <!-- Report Info Card -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Report Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-500 mb-1">Outlet</label>
            <p class="text-lg font-semibold text-gray-800">{{ report.outlet?.nama_outlet || '-' }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500 mb-1">Department</label>
            <p class="text-lg font-semibold text-gray-800">{{ report.department?.nama_departemen || '-' }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500 mb-1">Inspection Time</label>
            <p class="text-lg font-semibold text-gray-800">{{ inspectionTimeText }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
            <span :class="['px-3 py-1 rounded-full text-sm font-semibold', statusColor]">
              {{ statusText }}
            </span>
          </div>
          <div v-if="report.status === 'completed'">
            <label class="block text-sm font-medium text-gray-500 mb-1">Rating</label>
            <div class="flex flex-col items-start">
              <div class="flex items-center gap-2 mb-1">
                <span class="text-2xl font-bold text-blue-600">{{ inspectionStats?.rating || 0 }}%</span>
                <div class="flex space-x-1">
                  <i 
                    v-for="star in 5" 
                    :key="star"
                    :class="[
                      'fa-star text-sm',
                      star <= (inspectionStats?.star_rating || 0) ? 'fa-solid text-yellow-400' : 'fa-regular text-gray-300'
                    ]"
                  ></i>
                </div>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-1.5">
                <div 
                  class="bg-blue-600 h-1.5 rounded-full transition-all duration-300" 
                  :style="{ width: (inspectionStats?.rating || 0) + '%' }"
                ></div>
              </div>
            </div>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
          <div>
            <label class="block text-sm font-medium text-gray-500 mb-1">Created By</label>
            <div>
              <p class="text-lg font-semibold text-gray-800">{{ report.user?.nama_lengkap || '-' }}</p>
              <p v-if="report.user?.jabatan?.nama_jabatan" class="text-sm text-gray-600">{{ report.user.jabatan.nama_jabatan }}</p>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-500 mb-1">Created At</label>
            <p class="text-lg font-semibold text-gray-800">
              {{ new Date(report.created_at).toLocaleDateString('id-ID', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
              }) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Progress Card -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Inspection Progress</h2>
        <div class="flex items-center gap-4 mb-4">
          <div class="flex-1 bg-gray-200 rounded-full h-3">
            <div 
              class="bg-blue-600 h-3 rounded-full transition-all duration-300"
              :style="{ width: progressPercentage + '%' }"
            ></div>
          </div>
          <span class="text-sm font-medium text-gray-600">{{ progressPercentage }}%</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
          <div class="bg-blue-50 p-4 rounded-lg">
            <p class="text-2xl font-bold text-blue-600">{{ report.report_areas?.length || 0 }}</p>
            <p class="text-sm text-blue-800">Completed Areas</p>
          </div>
          <div class="bg-gray-50 p-4 rounded-lg">
            <p class="text-2xl font-bold text-gray-600">{{ report.progress?.length || 0 }}</p>
            <p class="text-sm text-gray-800">Total Areas</p>
          </div>
          <div class="bg-green-50 p-4 rounded-lg">
            <p class="text-2xl font-bold text-green-600">{{ (report.progress?.length || 0) - (report.report_areas?.length || 0) }}</p>
            <p class="text-sm text-green-800">Remaining</p>
          </div>
        </div>
      </div>

      <!-- Inspection Rating -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
          <i class="fa-solid fa-star text-yellow-500"></i>
          Inspection Rating
        </h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <!-- Overall Rating -->
          <div class="text-center">
            <div class="flex justify-center items-center mb-2">
              <div class="flex space-x-1">
                <i 
                  v-for="star in 5" 
                  :key="star"
                  :class="[
                    'text-2xl',
                    star <= (inspectionStats?.star_rating || 0) 
                      ? 'fa-solid fa-star text-yellow-400' 
                      : 'fa-regular fa-star text-gray-300'
                  ]"
                ></i>
              </div>
            </div>
            <p class="text-sm text-gray-600">Overall Rating</p>
            <p class="text-xs text-gray-500 mt-1">{{ inspectionStats?.rating || 0 }}% ({{ inspectionStats?.star_rating || 0 }}/5 stars)</p>
          </div>

          <!-- Good Areas -->
          <div class="text-center">
            <div class="text-3xl font-bold text-green-600 mb-2">
              {{ inspectionStats?.good_areas || 0 }}
            </div>
            <p class="text-sm text-gray-600">Good Areas</p>
            <div class="flex items-center justify-center mt-2">
              <i class="fa-solid fa-check-circle text-green-500 mr-1"></i>
              <span class="text-xs text-gray-500">Passed</span>
            </div>
          </div>

          <!-- Not Good Areas -->
          <div class="text-center">
            <div class="text-3xl font-bold text-red-600 mb-2">
              {{ inspectionStats?.not_good_areas || 0 }}
            </div>
            <p class="text-sm text-gray-600">Not Good Areas</p>
            <div class="flex items-center justify-center mt-2">
              <i class="fa-solid fa-times-circle text-red-500 mr-1"></i>
              <span class="text-xs text-gray-500">Failed</span>
            </div>
          </div>

          <!-- Not Available Areas -->
          <div class="text-center">
            <div class="text-3xl font-bold text-gray-600 mb-2">
              {{ inspectionStats?.not_available_areas || 0 }}
            </div>
            <p class="text-sm text-gray-600">Not Available</p>
            <div class="flex items-center justify-center mt-2">
              <i class="fa-solid fa-question-circle text-gray-500 mr-1"></i>
              <span class="text-xs text-gray-500">Excluded</span>
            </div>
          </div>
        </div>

        <!-- Rating Summary -->
        <div class="mt-6 p-4 bg-gray-50 rounded-lg">
          <div class="flex items-center justify-between text-sm">
            <span class="text-gray-600">
              Total Inspected Areas: <strong>{{ inspectionStats?.inspected_areas || 0 }}</strong> 
              ({{ inspectionStats?.good_areas || 0 }} Good + {{ inspectionStats?.not_good_areas || 0 }} Not Good)
            </span>
            <span class="text-gray-600">
              Total Areas: <strong>{{ inspectionStats?.total_areas || 0 }}</strong>
            </span>
          </div>
          <div class="mt-2 text-xs text-gray-500">
            <i class="fa-solid fa-info-circle mr-1"></i>
            Rating calculated from Good and Not Good areas only (Not Available areas excluded)
          </div>
        </div>
      </div>

      <!-- Areas Inspection Results -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Areas Inspection Results</h2>
        <div v-if="report.report_areas && report.report_areas.length > 0" class="space-y-4">
          <div 
            v-for="area in report.report_areas" 
            :key="area.id"
            class="border border-gray-200 rounded-lg p-4"
          >
            <div class="flex items-center justify-between mb-3">
              <h3 class="text-lg font-semibold text-gray-800">{{ area.area?.nama_area || 'Unknown Area' }}</h3>
              <span :class="[
                'px-3 py-1 rounded-full text-sm font-semibold',
                area.status === 'G' ? 'bg-green-100 text-green-800' :
                area.status === 'NG' ? 'bg-red-100 text-red-800' :
                'bg-gray-100 text-gray-800'
              ]">
                {{ area.status === 'G' ? 'Good' : area.status === 'NG' ? 'Not Good' : 'Not Available' }}
              </span>
            </div>
            
            <div v-if="area.finding_problem" class="mb-3">
              <label class="block text-sm font-medium text-gray-500 mb-1">Finding Problem</label>
              <p class="text-gray-800">{{ area.finding_problem }}</p>
            </div>
            
            <div v-if="area.dept_concern" class="mb-3">
              <label class="block text-sm font-medium text-gray-500 mb-1">Department Concern</label>
              <p class="text-gray-800">{{ area.dept_concern.nama_divisi }}</p>
            </div>
            
            <div v-if="area.documentation && area.documentation.length > 0" class="mb-3">
              <label class="block text-sm font-medium text-gray-500 mb-1">Documentation</label>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <div 
                  v-for="(doc, index) in area.documentation" 
                  :key="index"
                  class="relative group cursor-pointer"
                  @click="showLightbox(area.documentation, index)"
                >
                  <img 
                    :src="doc" 
                    :alt="`Documentation ${index + 1}`"
                    class="w-full h-24 object-cover rounded-lg border border-gray-200"
                  />
                  <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 rounded-lg flex items-center justify-center">
                    <div class="opacity-0 group-hover:opacity-100 bg-white bg-opacity-80 rounded-full p-2 transition-all duration-200">
                      <i class="fa-solid fa-expand text-gray-700"></i>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          <i class="fa-solid fa-clipboard-list text-4xl mb-4"></i>
          <p>No areas have been inspected yet.</p>
        </div>
      </div>

      <!-- Post-Inspection Data -->
      <div v-if="report.briefing || report.productivity || report.visit_tables || report.summaries" class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Post-Inspection Data</h2>
        
        <!-- Briefing -->
        <div v-if="report.briefing" class="mb-6">
          <h3 class="text-lg font-semibold text-gray-700 mb-3">
            {{ report.briefing.briefing_type === 'morning' ? 'Morning Briefing' : 'Afternoon Briefing' }}
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-if="report.briefing.time_of_conduct">
              <label class="block text-sm font-medium text-gray-500 mb-1">Time of Conduct</label>
              <p class="text-gray-800">{{ report.briefing.time_of_conduct }}</p>
            </div>
            <div v-if="report.briefing.participant">
              <label class="block text-sm font-medium text-gray-500 mb-1">Participant</label>
              <p class="text-gray-800">{{ report.briefing.participant }}</p>
            </div>
            <div v-if="report.briefing.service_in_charge">
              <label class="block text-sm font-medium text-gray-500 mb-1">Service In Charge</label>
              <p class="text-gray-800">{{ report.briefing.service_in_charge }}</p>
            </div>
            <div v-if="report.briefing.bar_in_charge">
              <label class="block text-sm font-medium text-gray-500 mb-1">Bar In Charge</label>
              <p class="text-gray-800">{{ report.briefing.bar_in_charge }}</p>
            </div>
            <div v-if="report.briefing.kitchen_in_charge">
              <label class="block text-sm font-medium text-gray-500 mb-1">Kitchen In Charge</label>
              <p class="text-gray-800">{{ report.briefing.kitchen_in_charge }}</p>
            </div>
            <div v-if="report.briefing.daily_revenue_target">
              <label class="block text-sm font-medium text-gray-500 mb-1">Daily Revenue Target</label>
              <p class="text-gray-800">{{ new Intl.NumberFormat('id-ID').format(report.briefing.daily_revenue_target) }}</p>
            </div>
          </div>
        </div>

        <!-- Productivity -->
        <div v-if="report.productivity" class="mb-6">
          <h3 class="text-lg font-semibold text-gray-700 mb-3">Employee Productivity Program</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-if="report.productivity.product_knowledge_test">
              <label class="block text-sm font-medium text-gray-500 mb-1">Product Knowledge Test</label>
              <p class="text-gray-800">{{ report.productivity.product_knowledge_test }}</p>
            </div>
            <div v-if="report.productivity.employee_daily_coaching">
              <label class="block text-sm font-medium text-gray-500 mb-1">Employee Daily Coaching</label>
              <p class="text-gray-800">{{ report.productivity.employee_daily_coaching }}</p>
            </div>
          </div>
        </div>

        <!-- Visit Tables -->
        <div v-if="report.visit_tables && report.visit_tables.length > 0" class="mb-6">
          <h3 class="text-lg font-semibold text-gray-700 mb-3">Visit Tables ({{ report.visit_tables.length }})</h3>
          <div class="space-y-3">
            <div 
              v-for="(table, index) in report.visit_tables" 
              :key="index"
              class="border border-gray-200 rounded-lg p-4"
            >
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-500 mb-1">Guest Name</label>
                  <p class="text-gray-800">{{ table.guest_name || '-' }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-500 mb-1">Table No</label>
                  <p class="text-gray-800">{{ table.table_no || '-' }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-500 mb-1">No of Pax</label>
                  <p class="text-gray-800">{{ table.no_of_pax || '-' }}</p>
                </div>
              </div>
              <div v-if="table.guest_experience" class="mt-3">
                <label class="block text-sm font-medium text-gray-500 mb-1">Guest Experience</label>
                <p class="text-gray-800">{{ table.guest_experience }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Summary -->
        <div v-if="report.summaries && report.summaries.length > 0">
          <h3 class="text-lg font-semibold text-gray-700 mb-3">Report Summary</h3>
          <div class="space-y-3">
            <div 
              v-for="summary in report.summaries" 
              :key="summary.id"
              class="border border-gray-200 rounded-lg p-4"
            >
              <h4 class="font-semibold text-gray-700 mb-2">
                {{ summary.summary_type === 'summary_1' ? 'Summary 1' : 'Summary 2' }}
              </h4>
              <p class="text-gray-800">{{ summary.notes || 'No notes provided.' }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Lightbox Component -->
    <VueEasyLightbox
      :visible="visibleRef"
      :imgs="imgsRef"
      :index="indexRef"
      @hide="hideLightbox"
    />
  </AppLayout>
</template>

<style scoped>
.btn {
  @apply inline-flex items-center gap-2 px-4 py-2 rounded-lg font-medium transition-all duration-200;
}

.btn-secondary {
  @apply bg-gray-500 text-white hover:bg-gray-600;
}

.btn-primary {
  @apply bg-blue-600 text-white hover:bg-blue-700;
}

.btn-purple {
  @apply bg-purple-600 text-white hover:bg-purple-700;
}
</style>