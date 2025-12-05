<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <h1 class="text-3xl font-bold mb-8 text-blue-800 flex items-center gap-3">
        <i class="fa-solid fa-user-graduate text-blue-500"></i> Edit Coaching
      </h1>

      <form @submit.prevent="submitForm" class="max-w-6xl mx-auto">
        <!-- Employee Details -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-user text-blue-600"></i>
            Employee Details
          </h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Nama</label>
              <input 
                v-model="form.employee_name" 
                type="text" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" 
                readonly
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Jabatan</label>
              <input 
                v-model="form.jabatan" 
                type="text" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" 
                readonly
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Divisi</label>
              <input 
                v-model="form.divisi" 
                type="text" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" 
                readonly
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Unit</label>
              <input 
                v-model="form.unit" 
                type="text" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" 
                readonly
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai Bekerja</label>
              <input 
                v-model="form.tanggal_mulai_bekerja" 
                type="date" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 bg-gray-100" 
                readonly
              />
            </div>
          </div>
        </div>

        <!-- Violation Record -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-exclamation-triangle text-red-600"></i>
            Violation Record
          </h2>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Pelanggaran *</label>
              <input 
                v-model="form.violation_date" 
                type="date" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Lokasi</label>
              <multiselect
                v-model="selectedLocation"
                :options="outlets"
                :searchable="true"
                placeholder="Pilih lokasi pelanggaran..."
                label="name"
                track-by="id"
                :show-labels="false"
                :close-on-select="true"
                :clear-on-select="false"
                :preserve-search="true"
                @select="selectLocation"
                @clear="clearLocation"
              >
                <template slot="singleLabel" slot-scope="{ option }">
                  <div class="flex items-center">
                    <div class="flex-1">
                      <div class="font-medium text-gray-900">{{ option.name }}</div>
                    </div>
                  </div>
                </template>
                <template slot="option" slot-scope="{ option }">
                  <div class="flex items-center">
                    <div class="flex-1">
                      <div class="font-medium text-gray-900">{{ option.name }}</div>
                    </div>
                  </div>
                </template>
                <template slot="noOptions">
                  <div class="text-center py-2 text-gray-500">
                    <i class="fa-solid fa-search mr-2"></i>
                    Ketik untuk mencari lokasi...
                  </div>
                </template>
                <template slot="noResult">
                  <div class="text-center py-2 text-gray-500">
                    <i class="fa-solid fa-map-marker-slash mr-2"></i>
                    Tidak ada lokasi ditemukan
                  </div>
                </template>
              </multiselect>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Perincian Pelanggaran *</label>
              <textarea 
                v-model="form.violation_details" 
                rows="4"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                placeholder="Jelaskan detail pelanggaran yang dilakukan"
                required
              ></textarea>
            </div>
          </div>
        </div>

        <!-- Disciplinary Action -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-gavel text-orange-600"></i>
            Disciplinary Action
          </h2>
          
          <!-- Radio buttons for selecting disciplinary action -->
          <div class="space-y-3 mb-6">
            <div v-for="(action, index) in disciplinaryActions" :key="index" 
                 class="flex items-center gap-3 p-3 border border-gray-300 rounded-lg hover:bg-gray-50">
              <input 
                v-model="selectedAction"
                :value="action.name"
                type="radio"
                class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500"
              />
              <span class="font-medium text-gray-800">{{ action.name }}</span>
              <span v-if="action.auto_calculate" class="text-xs bg-orange-100 text-orange-800 px-2 py-1 rounded-full">
                Auto 3 bulan
              </span>
            </div>
          </div>

          <!-- Form fields for selected disciplinary action -->
          <div v-if="selectedAction && selectedActionData" class="border border-blue-500 rounded-lg p-4 bg-blue-50">
            <h3 class="text-lg font-semibold text-blue-800 mb-4">{{ selectedAction }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Berlaku *</label>
                <input 
                  v-model="selectedActionData.effective_date"
                  type="date"
                  class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  @change="calculateEndDate"
                  @input="console.log('Effective date changed:', selectedActionData.effective_date)"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Berakhir *</label>
                <input 
                  v-model="selectedActionData.end_date"
                  type="date"
                  class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  :disabled="selectedActionData.auto_calculate"
                  :class="selectedActionData.auto_calculate ? 'bg-gray-100' : ''"
                  :title="selectedActionData.auto_calculate ? 'Tanggal berakhir dihitung otomatis 3 bulan dari tanggal berlaku' : ''"
                  @input="console.log('End date changed:', selectedActionData.end_date)"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
                <input 
                  v-model="selectedActionData.remarks"
                  type="text"
                  class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Keterangan tambahan (opsional)"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Comments and Signatures -->
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
          <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-comments text-green-600"></i>
            Comments & Signatures
          </h2>
          
          <div class="space-y-6">
            <!-- Supervisor Comments -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Komentar Atasan</label>
              <div class="relative">
                <textarea 
                  v-model="form.supervisor_comments" 
                  rows="4"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Masukkan komentar atasan..."
                ></textarea>
                <div class="absolute bottom-2 right-2 text-xs text-gray-500">TTD</div>
              </div>
              <!-- Supervisor Signature -->
              <div v-if="supervisorSignature" class="mt-2">
                <img :src="supervisorSignature" alt="Supervisor Signature" class="h-16 w-auto border border-gray-300 rounded" />
              </div>
            </div>

            <!-- Employee Response -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggapan Karyawan</label>
              <div class="relative">
                <textarea 
                  v-model="form.employee_response" 
                  rows="4"
                  class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                  placeholder="Masukkan tanggapan karyawan..."
                ></textarea>
                <div class="absolute bottom-2 right-2 text-xs text-gray-500">TTD</div>
              </div>
              <!-- Employee Signature -->
              <div v-if="employeeSignature" class="mt-2">
                <img :src="employeeSignature" alt="Employee Signature" class="h-16 w-auto border border-gray-300 rounded" />
              </div>
            </div>
          </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex gap-4 justify-end">
          <a 
            :href="route('coaching.show', coaching.id)"
            class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            Cancel
          </a>
          <button 
            type="submit" 
            :disabled="isSubmitting"
            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin"></i>
            <i v-else class="fa-solid fa-save"></i>
            {{ isSubmitting ? 'Menyimpan...' : 'Update Coaching' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';

const props = defineProps({
  coaching: Object,
  user: Object,
  employees: Array,
  outlets: Array
});

// Helper function to format date for input
function formatDateForInput(dateString) {
  if (!dateString) return '';
  
  // If it's already in YYYY-MM-DD format, return as is
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
    return dateString;
  }
  
  // Try to parse and format the date
  try {
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';
    
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    
    return `${year}-${month}-${day}`;
  } catch (error) {
    console.error('Error formatting date:', error);
    return '';
  }
}

// Form data
const form = reactive({
  employee_id: props.coaching.employee_id,
  employee_name: props.coaching.employee?.nama_lengkap || '',
  jabatan: props.coaching.employee?.jabatan?.nama_jabatan || '',
  divisi: props.coaching.employee?.divisi?.nama_divisi || '',
  unit: props.coaching.employee?.outlet?.nama_outlet || '',
  tanggal_mulai_bekerja: props.coaching.employee?.tanggal_masuk || '',
  violation_date: formatDateForInput(props.coaching.violation_date),
  location: props.coaching.location || '',
  violation_details: props.coaching.violation_details || '',
  supervisor_comments: props.coaching.supervisor_comments || '',
  employee_response: props.coaching.employee_response || ''
});

// Signatures
const supervisorSignature = ref(null);
const employeeSignature = ref(null);

// Location selection
const selectedLocation = ref(null);
const selectedAction = ref(null);

// Disciplinary actions
const disciplinaryActions = ref([
  { name: 'Teguran (berlaku 3 bulan)', selected: false, effective_date: '', end_date: '', remarks: '', auto_calculate: true },
  { name: 'Surat Peringatan 1 (berlaku 3 bulan)', selected: false, effective_date: '', end_date: '', remarks: '', auto_calculate: true },
  { name: 'Surat Peringatan 2 (berlaku 3 bulan)', selected: false, effective_date: '', end_date: '', remarks: '', auto_calculate: true },
  { name: 'Surat Peringatan 3 (berlaku 3 bulan)', selected: false, effective_date: '', end_date: '', remarks: '', auto_calculate: true },
  { name: 'Skors', selected: false, effective_date: '', end_date: '', remarks: '', auto_calculate: false },
  { name: 'PHK', selected: false, effective_date: '', end_date: '', remarks: '', auto_calculate: false },
  { name: 'Lainnya', selected: false, effective_date: '', end_date: '', remarks: '', auto_calculate: false }
]);

// Computed property to get selected action data
const selectedActionData = computed(() => {
  if (!selectedAction.value) {
    return null;
  }
  return disciplinaryActions.value.find(action => action.name === selectedAction.value);
});

const isSubmitting = ref(false);

// Initialize disciplinary actions from existing data
const initializeDisciplinaryActions = () => {
  let disciplinaryActionsData = props.coaching.disciplinary_actions;
  
  // Parse JSON string if needed
  if (typeof disciplinaryActionsData === 'string') {
    try {
      disciplinaryActionsData = JSON.parse(disciplinaryActionsData);
    } catch (error) {
      console.error('Error parsing disciplinary actions:', error);
      return;
    }
  }
  
  if (disciplinaryActionsData && Array.isArray(disciplinaryActionsData) && disciplinaryActionsData.length > 0) {
    const existingAction = disciplinaryActionsData[0]; // Only one action since we use radio buttons
    const actionIndex = disciplinaryActions.value.findIndex(action => action.name === existingAction.name);
    if (actionIndex !== -1) {
      disciplinaryActions.value[actionIndex] = {
        ...disciplinaryActions.value[actionIndex],
        effective_date: existingAction.effective_date || '',
        end_date: existingAction.end_date || '',
        remarks: existingAction.remarks || ''
      };
      selectedAction.value = existingAction.name;
    }
  }
};

// Set signatures
const setSignatures = () => {
  if (props.coaching.supervisor_signature) {
    supervisorSignature.value = `/storage/${props.coaching.supervisor_signature}`;
  } else if (props.user?.signature_path) {
    supervisorSignature.value = `/storage/${props.user.signature_path}`;
  }
  
  if (props.coaching.employee_signature) {
    employeeSignature.value = `/storage/${props.coaching.employee_signature}`;
  } else if (props.coaching.employee?.signature_path) {
    employeeSignature.value = `/storage/${props.coaching.employee.signature_path}`;
  }
};

// Select location
const selectLocation = (location) => {
  if (!location) return;
  selectedLocation.value = location;
  form.location = location.name;
};

// Clear location
const clearLocation = () => {
  selectedLocation.value = null;
  form.location = '';
};

// Calculate end date automatically for 3-month sanctions
const calculateEndDate = () => {
  if (selectedActionData.value && selectedActionData.value.auto_calculate && selectedActionData.value.effective_date) {
    const effectiveDate = new Date(selectedActionData.value.effective_date);
    const endDate = new Date(effectiveDate);
    endDate.setMonth(endDate.getMonth() + 3);
    
    // Format to YYYY-MM-DD
    const year = endDate.getFullYear();
    const month = String(endDate.getMonth() + 1).padStart(2, '0');
    const day = String(endDate.getDate()).padStart(2, '0');
    
    selectedActionData.value.end_date = `${year}-${month}-${day}`;
  }
};

// Submit form
const submitForm = async () => {
  if (!form.violation_date) {
    Swal.fire({
      title: 'Peringatan!',
      text: 'Pilih tanggal pelanggaran!',
      icon: 'warning',
      confirmButtonText: 'OK'
    });
    return;
  }

  if (!form.violation_details.trim()) {
    Swal.fire({
      title: 'Peringatan!',
      text: 'Isi perincian pelanggaran!',
      icon: 'warning',
      confirmButtonText: 'OK'
    });
    return;
  }

  isSubmitting.value = true;

  try {
    // Debug log
    console.log('Selected Action:', selectedAction.value);
    console.log('Selected Action Data:', selectedActionData.value);
    console.log('All Disciplinary Actions:', disciplinaryActions.value);
    
    const disciplinaryActionsData = selectedActionData.value ? [{
      name: selectedActionData.value.name,
      effective_date: selectedActionData.value.effective_date,
      end_date: selectedActionData.value.end_date,
      remarks: selectedActionData.value.remarks
    }] : [];

    console.log('Disciplinary Actions Data to send:', disciplinaryActionsData);

    const formData = {
      violation_date: form.violation_date,
      location: form.location,
      violation_details: form.violation_details,
      disciplinary_actions: disciplinaryActionsData,
      supervisor_comments: form.supervisor_comments,
      employee_response: form.employee_response
    };

    const response = await fetch(route('coaching.update', props.coaching.id), {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify(formData)
    });

    if (response.ok) {
      const result = await response.json();
      Swal.fire({
        title: 'Berhasil!',
        text: 'Coaching berhasil diperbarui!',
        icon: 'success',
        timer: 2000,
        timerProgressBar: true,
        showConfirmButton: false
      }).then(() => {
        router.visit(route('coaching.show', props.coaching.id));
      });
    } else {
      const error = await response.json();
      Swal.fire({
        title: 'Error!',
        text: error.message || 'Gagal memperbarui coaching!',
        icon: 'error',
        confirmButtonText: 'OK'
      });
    }
  } catch (error) {
    console.error('Error submitting form:', error);
    Swal.fire({
      title: 'Error!',
      text: 'Terjadi kesalahan saat memperbarui coaching!',
      icon: 'error',
      confirmButtonText: 'OK'
    });
  } finally {
    isSubmitting.value = false;
  }
};

// Watch for selectedAction changes to update the disciplinary actions
watch(selectedAction, (newValue, oldValue) => {
  console.log('Selected action changed from', oldValue, 'to', newValue);
  
  // If a new action is selected, make sure it's properly initialized
  if (newValue) {
    const selectedActionData = disciplinaryActions.value.find(action => action.name === newValue);
    if (selectedActionData) {
      console.log('Selected action data:', selectedActionData);
    }
  }
});

onMounted(() => {
  // Debug log
  console.log('Coaching data:', props.coaching);
  console.log('Violation date:', props.coaching.violation_date);
  console.log('Disciplinary actions:', props.coaching.disciplinary_actions);
  
  initializeDisciplinaryActions();
  setSignatures();
  
  // Initialize selected location if coaching has location
  if (props.coaching.location) {
    const outlet = props.outlets.find(o => o.name === props.coaching.location);
    if (outlet) {
      selectedLocation.value = outlet;
    }
  }
});
</script>

<style src="vue-multiselect/dist/vue-multiselect.css"></style>
