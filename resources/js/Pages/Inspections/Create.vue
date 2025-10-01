<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  outlets: Array,
  departemenOptions: Array,
  selectedOutlet: Number,
  selectedDepartemen: String,
  users: Array,
  guidances: Array,
});

const form = ref({
  outlet_id: props.selectedOutlet || '',
  departemen: props.selectedDepartemen || '',
  guidance_id: '',
  inspection_mode: 'product',
  inspection_date: new Date().toISOString().split('T')[0],
  auditees: [],
});

// Auditee autocomplete (like daily task participant)
const auditeeSearch = ref('');
const selectedAuditees = ref([]);
const filteredAuditees = ref([]);
const showAuditeeDropdown = ref(false);

const errors = ref({});
const isSubmitting = ref(false);

// Computed untuk filter users berdasarkan outlet yang dipilih
const filteredUsers = computed(() => {
  if (!form.value.outlet_id) return [];
  return props.users.filter(user => user.id_outlet == form.value.outlet_id);
});

// Computed untuk filter guidances berdasarkan departemen
const filteredGuidances = computed(() => {
  if (!form.value.departemen) return [];
  return props.guidances.filter(guidance => guidance.departemen === form.value.departemen);
});

// Methods untuk auditee autocomplete
function searchAuditees() {
  if (auditeeSearch.value.length < 2) {
    filteredAuditees.value = [];
    showAuditeeDropdown.value = false;
    return;
  }
  
  filteredAuditees.value = filteredUsers.value.filter(user => 
    user.nama_lengkap.toLowerCase().includes(auditeeSearch.value.toLowerCase()) &&
    !selectedAuditees.value.some(selected => selected.id === user.id)
  );
  showAuditeeDropdown.value = filteredAuditees.value.length > 0;
}

function selectAuditee(user) {
  selectedAuditees.value.push(user);
  auditeeSearch.value = '';
  showAuditeeDropdown.value = false;
  filteredAuditees.value = [];
  
  // Update form auditees
  form.value.auditees = selectedAuditees.value.map(a => a.id);
}

function removeAuditee(index) {
  selectedAuditees.value.splice(index, 1);
  form.value.auditees = selectedAuditees.value.map(a => a.id);
}

function hideAuditeeDropdown() {
  setTimeout(() => {
    showAuditeeDropdown.value = false;
  }, 200);
}




function submit() {
  isSubmitting.value = true;
  errors.value = {};

  router.post(route('inspections.store'), form.value, {
    onSuccess: () => {
      // Success handled by controller redirect
    },
    onError: (errs) => {
      errors.value = errs;
    },
    onFinish: () => {
      isSubmitting.value = false;
    }
  });
}

function back() {
  router.visit('/inspections');
}

function viewInspection(inspectionId) {
  router.visit(`/inspections/${inspectionId}`);
}

function continueInspection(inspectionId) {
  router.visit(`/inspections/${inspectionId}/add-finding`);
}
</script>

<template>
  <AppLayout title="Create Inspection">
    <div class="w-full py-8 px-4">
      <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center gap-4 mb-8">
          <button @click="back" class="text-gray-500 hover:text-gray-700 transition">
            <i class="fa-solid fa-arrow-left text-xl"></i>
          </button>
          <h1 class="text-3xl font-bold text-gray-800 flex items-center gap-3">
            <i class="fa-solid fa-clipboard-check text-blue-500"></i>
            Create New Inspection
          </h1>
        </div>

        <!-- Form -->
        <div class="bg-white rounded-2xl shadow-lg p-8">
          <form @submit.prevent="submit" class="space-y-6">
            <!-- Outlet Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-store mr-2"></i>Outlet *
              </label>
              <select 
                v-model="form.outlet_id" 
                :class="[
                  'w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                  errors.outlet_id ? 'border-red-500' : 'border-gray-300'
                ]"
                required
              >
                <option value="">Select Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
              <p v-if="errors.outlet_id" class="mt-1 text-sm text-red-600">{{ errors.outlet_id }}</p>
            </div>

             <!-- Department Selection -->
             <div>
               <label class="block text-sm font-medium text-gray-700 mb-2">
                 <i class="fa-solid fa-building mr-2"></i>Department *
               </label>
               <select 
                 v-model="form.departemen" 
                 :class="[
                   'w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                   errors.departemen ? 'border-red-500' : 'border-gray-300'
                 ]"
                 required
               >
                 <option value="">Select Department</option>
                 <option v-for="dept in departemenOptions" :key="dept" :value="dept">
                   {{ dept }}
                 </option>
               </select>
               <p v-if="errors.departemen" class="mt-1 text-sm text-red-600">{{ errors.departemen }}</p>
             </div>

             <!-- Guidance Selection -->
             <div>
               <label class="block text-sm font-medium text-gray-700 mb-2">
                 <i class="fa-solid fa-clipboard-list mr-2"></i>Guidance *
               </label>
               <select 
                 v-model="form.guidance_id" 
                 :class="[
                   'w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                   errors.guidance_id ? 'border-red-500' : 'border-gray-300'
                 ]"
                 :disabled="!form.departemen"
                 required
               >
                 <option value="">Select Guidance</option>
                 <option v-for="guidance in filteredGuidances" :key="guidance.id" :value="guidance.id">
                   {{ guidance.title }}
                 </option>
               </select>
               <p v-if="errors.guidance_id" class="mt-1 text-sm text-red-600">{{ errors.guidance_id }}</p>
               <p v-if="!form.departemen" class="mt-1 text-sm text-gray-500">Select department first</p>
             </div>

             <!-- Inspection Mode Selection -->
             <div>
               <label class="block text-sm font-medium text-gray-700 mb-2">
                 <i class="fa-solid fa-cogs mr-2"></i>Inspection Mode *
               </label>
               <div class="grid grid-cols-2 gap-4">
                 <label class="flex items-center p-4 border rounded-xl cursor-pointer hover:bg-blue-50 transition" :class="form.inspection_mode === 'product' ? 'border-blue-500 bg-blue-50' : 'border-gray-300'">
                   <input 
                     type="radio" 
                     v-model="form.inspection_mode" 
                     value="product" 
                     class="mr-3 text-blue-600 focus:ring-blue-500"
                   />
                   <div>
                     <div class="font-medium text-gray-800">Product Mode</div>
                     <div class="text-sm text-gray-500">Direct point assignment</div>
                   </div>
                 </label>
                 
                 <label class="flex items-center p-4 border rounded-xl cursor-pointer hover:bg-green-50 transition" :class="form.inspection_mode === 'cleanliness' ? 'border-green-500 bg-green-50' : 'border-gray-300'">
                   <input 
                     type="radio" 
                     v-model="form.inspection_mode" 
                     value="cleanliness" 
                     class="mr-3 text-green-600 focus:ring-green-500"
                   />
                   <div>
                     <div class="font-medium text-gray-800">Cleanliness Mode</div>
                     <div class="text-sm text-gray-500">Yes/No/NA rating</div>
                   </div>
                 </label>
               </div>
               <p v-if="errors.inspection_mode" class="mt-1 text-sm text-red-600">{{ errors.inspection_mode }}</p>
             </div>

             <!-- Auditee Selection -->
             <div>
               <label class="block text-sm font-medium text-gray-700 mb-2">
                 <i class="fa-solid fa-users mr-2"></i>Auditees
               </label>
               <div class="relative">
                 <input
                   v-model="auditeeSearch"
                   @input="searchAuditees"
                   @focus="searchAuditees"
                   @blur="hideAuditeeDropdown"
                   :disabled="!form.outlet_id"
                   :class="[
                     'w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                     errors.auditees ? 'border-red-500' : 'border-gray-300',
                     !form.outlet_id ? 'bg-gray-100 cursor-not-allowed' : ''
                   ]"
                   placeholder="Type to search auditees..."
                 />
                 
                 <!-- Selected Auditees -->
                 <div v-if="selectedAuditees.length > 0" class="mt-2">
                   <div class="flex flex-wrap gap-2">
                     <span 
                       v-for="(auditee, index) in selectedAuditees" 
                       :key="auditee.id"
                       class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-blue-100 text-blue-800"
                     >
                       {{ auditee.nama_lengkap }}
                       <button 
                         @click="removeAuditee(index)"
                         class="ml-2 text-blue-600 hover:text-blue-800"
                       >
                         <i class="fa-solid fa-times text-xs"></i>
                       </button>
                     </span>
                   </div>
                 </div>
                 
                 <!-- Dropdown -->
                 <div v-if="showAuditeeDropdown" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                   <div 
                     v-for="user in filteredAuditees" 
                     :key="user.id"
                     @click="selectAuditee(user)"
                     class="px-3 py-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0"
                   >
                     {{ user.nama_lengkap }}
                   </div>
                 </div>
               </div>
               <p v-if="errors.auditees" class="mt-1 text-sm text-red-600">{{ errors.auditees }}</p>
               <p class="mt-1 text-sm text-gray-500">
                 Type to search users from the chosen outlet to be audited
               </p>
             </div>


            <!-- Inspection Date -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fa-solid fa-calendar mr-2"></i>Inspection Date *
              </label>
              <input 
                v-model="form.inspection_date" 
                type="date" 
                :class="[
                  'w-full px-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500',
                  errors.inspection_date ? 'border-red-500' : 'border-gray-300'
                ]"
                required
              />
              <p v-if="errors.inspection_date" class="mt-1 text-sm text-red-600">{{ errors.inspection_date }}</p>
            </div>

            <!-- Session Info -->
            <div v-if="form.outlet_id && form.departemen" class="bg-blue-50 border border-blue-200 rounded-xl p-4">
              <h3 class="text-sm font-semibold text-blue-800 mb-2">
                <i class="fa-solid fa-info-circle mr-2"></i>Session Information
              </h3>
              <p class="text-sm text-blue-700">
                This outlet and department will be remembered for future inspections. 
                You can add multiple findings to this inspection session.
              </p>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-4 pt-6 border-t border-gray-200">
              <button 
                type="button" 
                @click="back" 
                class="px-6 py-3 bg-gray-500 hover:bg-gray-600 text-white rounded-xl font-medium transition"
              >
                <i class="fa-solid fa-times mr-2"></i>Cancel
              </button>
              <button 
                type="submit" 
                :disabled="isSubmitting || !form.outlet_id || !form.departemen"
                :class="[
                  'px-6 py-3 rounded-xl font-medium transition',
                  isSubmitting || !form.outlet_id || !form.departemen
                    ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                    : 'bg-blue-500 hover:bg-blue-600 text-white'
                ]"
              >
                <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin mr-2"></i>
                <i v-else class="fa-solid fa-arrow-right mr-2"></i>
                {{ isSubmitting ? 'Creating...' : 'Start Inspection' }}
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </AppLayout>
</template>
