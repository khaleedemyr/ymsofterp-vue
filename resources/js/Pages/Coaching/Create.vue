<template>
  <AppLayout>
    <div class="w-full min-h-screen py-8 px-2 md:px-6">
      <h1 class="text-3xl font-bold mb-8 text-blue-800 flex items-center gap-3">
        <i class="fa-solid fa-user-graduate text-blue-500"></i> Coaching Form
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
              <label class="block text-sm font-medium text-gray-700 mb-2">Nama *</label>
              <multiselect
                v-model="selectedEmployee"
                :options="employees"
                :searchable="true"
                placeholder="Cari nama karyawan..."
                label="nama_lengkap"
                track-by="id"
                :show-labels="false"
                :close-on-select="true"
                :clear-on-select="false"
                :preserve-search="true"
                @select="selectEmployee"
                @clear="clearEmployee"
                required
              >
                <template slot="singleLabel" slot-scope="{ option }">
                  <div class="flex items-center">
                    <div class="flex-1">
                      <div class="font-medium text-gray-900">{{ option.nama_lengkap }}</div>
                      <div class="text-sm text-gray-600">{{ option.jabatan?.nama_jabatan || 'N/A' }} - {{ option.divisi?.nama_divisi || 'N/A' }}</div>
                      <div class="text-sm text-gray-500">{{ option.outlet?.nama_outlet || 'N/A' }}</div>
                    </div>
                  </div>
                </template>
                <template slot="option" slot-scope="{ option }">
                  <div class="flex items-center">
                    <div class="flex-1">
                      <div class="font-medium text-gray-900">{{ option.nama_lengkap }}</div>
                      <div class="text-sm text-gray-600">{{ option.jabatan?.nama_jabatan || 'N/A' }} - {{ option.divisi?.nama_divisi || 'N/A' }}</div>
                      <div class="text-sm text-gray-500">{{ option.outlet?.nama_outlet || 'N/A' }}</div>
                    </div>
                  </div>
                </template>
                <template slot="noOptions">
                  <div class="text-center py-2 text-gray-500">
                    <i class="fa-solid fa-search mr-2"></i>
                    Ketik untuk mencari karyawan...
                  </div>
                </template>
                <template slot="noResult">
                  <div class="text-center py-2 text-gray-500">
                    <i class="fa-solid fa-user-slash mr-2"></i>
                    Tidak ada karyawan ditemukan
                  </div>
                </template>
              </multiselect>
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

        <!-- Tiered Signature Section -->
        <div class="bg-white rounded-lg border border-gray-200 p-6">
          <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
            <i class="fa-solid fa-signature text-orange-500 mr-2"></i>
            Tanda Tangan Berjenjang
          </h3>
          <p class="text-sm text-gray-600 mb-4">Tambahkan penandatangan berurutan dari jabatan terendah ke tertinggi. Penandatangan pertama adalah jabatan terendah, dan yang terakhir adalah jabatan tertinggi.</p>
          
          <!-- Add Approver Input -->
          <div class="mb-4">
            <multiselect
              v-model="selectedApprover"
              :options="allUsers"
              :searchable="true"
              placeholder="Cari pengguna berdasarkan nama, email, atau jabatan..."
              label="nama_lengkap"
              track-by="id"
              :show-labels="false"
              :close-on-select="true"
              :clear-on-select="false"
              :preserve-search="true"
              @select="addApprover"
              @clear="clearApproverSearch"
            >
              <template slot="singleLabel" slot-scope="{ option }">
                <div class="flex items-center">
                  <div class="flex-1">
                    <div class="font-medium text-gray-900">{{ option.nama_lengkap }}</div>
                    <div class="text-sm text-gray-600">{{ option.email }}</div>
                    <div v-if="option.jabatan?.nama_jabatan" class="text-xs text-blue-600 font-medium">{{ option.jabatan.nama_jabatan }}</div>
                  </div>
                </div>
              </template>
              <template slot="option" slot-scope="{ option }">
                <div class="flex items-center">
                  <div class="flex-1">
                    <div class="font-medium text-gray-900">{{ option.nama_lengkap }}</div>
                    <div class="text-sm text-gray-600">{{ option.email }}</div>
                    <div v-if="option.jabatan?.nama_jabatan" class="text-xs text-blue-600 font-medium">{{ option.jabatan.nama_jabatan }}</div>
                  </div>
                </div>
              </template>
              <template slot="noOptions">
                <div class="text-center py-2 text-gray-500">
                  <i class="fa-solid fa-search mr-2"></i>
                  Ketik untuk mencari pengguna...
                </div>
              </template>
              <template slot="noResult">
                <div class="text-center py-2 text-gray-500">
                  <i class="fa-solid fa-user-slash mr-2"></i>
                  Tidak ada pengguna ditemukan
                </div>
              </template>
            </multiselect>
          </div>

          <!-- Approvers List -->
          <div v-if="form.approvers.length > 0" class="space-y-2">
            <h4 class="font-medium text-gray-700">Urutan Penandatangan (Terendah ke Tertinggi):</h4>
            <div
              v-for="(approver, index) in form.approvers"
              :key="approver.id"
              class="flex items-center justify-between p-3 bg-gray-50 border border-gray-200 rounded-md"
            >
              <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-2">
                  <button
                    v-if="index > 0"
                    @click="reorderApprover(index, index - 1)"
                    class="p-1 text-gray-500 hover:text-gray-700"
                    title="Pindah ke atas"
                  >
                    <i class="fa fa-arrow-up"></i>
                  </button>
                  <button
                    v-if="index < form.approvers.length - 1"
                    @click="reorderApprover(index, index + 1)"
                    class="p-1 text-gray-500 hover:text-gray-700"
                    title="Pindah ke bawah"
                  >
                    <i class="fa fa-arrow-down"></i>
                  </button>
                </div>
                <div class="flex items-center space-x-2">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    Level {{ index + 1 }}
                  </span>
                  <div>
                    <div class="font-medium">{{ approver.nama_lengkap }}</div>
                    <div class="text-sm text-gray-600">{{ approver.email }}</div>
                    <div v-if="approver.jabatan?.nama_jabatan" class="text-xs text-blue-600 font-medium">{{ approver.jabatan.nama_jabatan }}</div>
                  </div>
                </div>
              </div>
              <button
                @click="removeApprover(index)"
                class="p-1 text-red-500 hover:text-red-700"
                title="Hapus Penandatangan"
              >
                <i class="fa fa-times"></i>
              </button>
            </div>
          </div>
        </div>

        <!-- Submit Buttons -->
        <div class="flex gap-4 justify-end">
          <a 
            :href="route('coaching.index')"
            class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            Cancel
          </a>
          <button 
            type="submit" 
            :disabled="!form.employee_id || isSubmitting"
            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            <i v-if="isSubmitting" class="fa-solid fa-spinner fa-spin"></i>
            <i v-else class="fa-solid fa-save"></i>
            {{ isSubmitting ? 'Menyimpan...' : 'Simpan Coaching' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<style src="vue-multiselect/dist/vue-multiselect.css"></style>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import Multiselect from 'vue-multiselect';

const props = defineProps({
  user: Object,
  employees: Array,
  outlets: Array,
  allUsers: Array
});

// Register components
const components = {
  Multiselect
};

// Form data
const form = reactive({
  employee_id: null,
  employee_search: '',
  jabatan: '',
  divisi: '',
  unit: '',
  tanggal_mulai_bekerja: '',
  violation_date: '',
  location: '',
  violation_details: '',
  supervisor_comments: '',
  employee_response: '',
  disciplinary_actions: [],
  approvers: []
});

// Employee search
const selectedEmployee = ref(null);
const selectedLocation = ref(null);
const selectedAction = ref(null);

// Tiered signature
const selectedApprover = ref(null);

// Signatures
const supervisorSignature = ref(null);
const employeeSignature = ref(null);

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


// Select employee
const selectEmployee = (employee) => {
  if (!employee) return;
  
  selectedEmployee.value = employee;
  form.employee_id = employee.id;
  form.jabatan = employee.jabatan?.nama_jabatan || '';
  form.divisi = employee.divisi?.nama_divisi || '';
  form.unit = employee.outlet?.nama_outlet || '';
  form.tanggal_mulai_bekerja = employee.tanggal_masuk || '';
  
  // Set employee signature
  if (employee.signature_path) {
    employeeSignature.value = `/storage/${employee.signature_path}`;
  }
};

// Clear employee
const clearEmployee = () => {
  selectedEmployee.value = null;
  form.employee_id = null;
  form.jabatan = '';
  form.divisi = '';
  form.unit = '';
  form.tanggal_mulai_bekerja = '';
  employeeSignature.value = null;
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


// Add approver to tiered signature
const addApprover = (user) => {
  if (!user) return;
  
  // Check if user is already added
  const existingIndex = form.approvers.findIndex(approver => approver.id === user.id);
  if (existingIndex === -1) {
    form.approvers.push({
      id: user.id,
      nama_lengkap: user.nama_lengkap,
      email: user.email,
      jabatan: user.jabatan
    });
  }
  
  selectedApprover.value = null;
};

// Clear approver search
const clearApproverSearch = () => {
  selectedApprover.value = null;
};

// Remove approver from tiered signature
const removeApprover = (index) => {
  form.approvers.splice(index, 1);
};

// Reorder approvers
const reorderApprover = (fromIndex, toIndex) => {
  const approver = form.approvers.splice(fromIndex, 1)[0];
  form.approvers.splice(toIndex, 0, approver);
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

// Set supervisor signature (current user)
const setSupervisorSignature = () => {
  if (props.user?.signature_path) {
    supervisorSignature.value = `/storage/${props.user.signature_path}`;
  }
};


// Submit form
const submitForm = async () => {
  if (!form.employee_id) {
    Swal.fire({
      title: 'Peringatan!',
      text: 'Pilih karyawan terlebih dahulu!',
      icon: 'warning',
      confirmButtonText: 'OK'
    });
    return;
  }

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
      employee_id: form.employee_id,
      violation_date: form.violation_date,
      location: form.location,
      violation_details: form.violation_details,
      disciplinary_actions: disciplinaryActionsData,
      supervisor_comments: form.supervisor_comments,
      employee_response: form.employee_response,
      approvers: form.approvers
    };

    const response = await fetch(route('coaching.store'), {
      method: 'POST',
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
        text: 'Coaching berhasil disimpan!',
        icon: 'success',
        timer: 2000,
        timerProgressBar: true,
        showConfirmButton: false
      }).then(() => {
        router.visit(route('coaching.index'));
      });
    } else {
      const error = await response.json();
      Swal.fire({
        title: 'Error!',
        text: error.message || 'Gagal menyimpan coaching!',
        icon: 'error',
        confirmButtonText: 'OK'
      });
    }
  } catch (error) {
    console.error('Error submitting form:', error);
    Swal.fire({
      title: 'Error!',
      text: 'Terjadi kesalahan saat menyimpan coaching!',
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
  
  // Reset all actions first
  disciplinaryActions.value.forEach(action => {
    action.effective_date = '';
    action.end_date = '';
    action.remarks = '';
  });
  
  // If a new action is selected, initialize it
  if (newValue) {
    const selectedActionData = disciplinaryActions.value.find(action => action.name === newValue);
    if (selectedActionData) {
      console.log('Initializing selected action:', selectedActionData);
    }
  }
});

onMounted(() => {
  setSupervisorSignature();
});
</script>
