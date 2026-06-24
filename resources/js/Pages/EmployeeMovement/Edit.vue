<script setup>
import { ref, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmPageLayout from './components/EmPageLayout.vue';
import axios from 'axios';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.min.css';
import './styles/em-theme.css';

const employmentTypes = [
  { value: 'extend_contract_without_adjustment', label: 'Extend contract tanpa adjustment', icon: 'fa-file-contract' },
  { value: 'extend_contract_with_adjustment', label: 'Extend contract dengan adjustment', icon: 'fa-file-signature' },
  { value: 'promotion', label: 'Promotion', icon: 'fa-arrow-up' },
  { value: 'demotion', label: 'Demotion', icon: 'fa-arrow-down' },
  { value: 'mutation', label: 'Mutation', icon: 'fa-exchange-alt' },
  { value: 'termination', label: 'Termination', icon: 'fa-user-times' },
];

const props = defineProps({
  movement: Object,
});

const form = useForm({
  employee_id: props.movement.employee_id || '',
  employee_name: props.movement.employee_name || '',
  employee_position: props.movement.employee_position || '',
  employee_division: props.movement.employee_division || '',
  employee_unit_property: props.movement.employee_unit_property || '',
  employee_join_date: props.movement.employee_join_date || '',
  employment_type: props.movement.employment_type || '',
  employment_effective_date: props.movement.employment_effective_date || '',
  kpi_required: props.movement.kpi_required || false,
  kpi_date: props.movement.kpi_date || '',
  psikotest_required: props.movement.psikotest_required || false,
  psikotest_score: props.movement.psikotest_score || '',
  psikotest_date: props.movement.psikotest_date || '',
  training_attendance_required: props.movement.training_attendance_required || false,
  training_attendance_date: props.movement.training_attendance_date || '',
  position_change: props.movement.position_change || false,
  position_from: props.movement.position_from || '',
  position_to: props.movement.position_to || '',
  level_change: props.movement.level_change || false,
  level_from: props.movement.level_from || '',
  level_to: props.movement.level_to || '',
  salary_change: props.movement.salary_change || false,
  salary_from: props.movement.salary_from || '',
  salary_to: props.movement.salary_to || '',
  gaji_pokok_to: '',
  tunjangan_to: '',
  department_change: props.movement.department_change || false,
  department_from: props.movement.department_from || '',
  department_to: props.movement.department_to || '',
  division_change: props.movement.division_change || false,
  division_from: props.movement.division_from || '',
  division_to: props.movement.division_to || '',
  unit_property_change: props.movement.unit_property_change || false,
  unit_property_from: props.movement.unit_property_from || '',
  unit_property_to: props.movement.unit_property_to || '',
  adjustment_effective_date: props.movement.adjustment_effective_date || '',
  comments: props.movement.comments || '',
  kpi_attachment: null,
  psikotest_attachment: null,
  training_attachment: null,
  other_attachments: [],
  hod_approver_id: props.movement.hod_approver_id || '', // Keep for backward compatibility
  gm_approver_id: props.movement.gm_approver_id || '', // Keep for backward compatibility
  gm_hr_approver_id: props.movement.gm_hr_approver_id || '', // Keep for backward compatibility
  bod_approver_id: props.movement.bod_approver_id || '', // Keep for backward compatibility
  approvers: [], // New approval flow system
  status: props.movement.status || 'draft',
});

const searchQuery = ref(props.movement.employee_name || '');
const searchResults = ref([]);
const showSearchResults = ref(false);
const selectedEmployee = ref(null);
const isLoading = ref(false);

// Dropdown data
const positions = ref([]);
const levels = ref([]);
const divisions = ref([]);
const outlets = ref([]);
const approvers = ref([]); // For dropdown selection

// Approval flow
const approverSearch = ref('');
const approverResults = ref([]);
const showApproverDropdown = ref(false);

// Salary formatting function
const formatCurrency = (value) => {
  if (!value) return '';
  // Remove all non-numeric characters
  const numericValue = value.toString().replace(/\D/g, '');
  // Format with thousand separator
  return numericValue.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
};

const unformatCurrency = (value) => {
  if (!value) return '';
  return value.toString().replace(/\D/g, '');
};

// Fetch dropdown data
const fetchDropdownData = async () => {
  try {
    console.log('Fetching dropdown data...');
    const url = route('employee-movements.dropdown-data');
    console.log('Request URL:', url);
    const response = await axios.get(url);
    console.log('Dropdown data response:', response.data);
    if (response.data.success) {
      positions.value = response.data.positions;
      levels.value = response.data.levels;
      divisions.value = response.data.divisions;
      outlets.value = response.data.outlets;
      console.log('Positions loaded:', positions.value.length);
      console.log('Levels loaded:', levels.value.length);
      console.log('Divisions loaded:', divisions.value.length);
      console.log('Outlets loaded:', outlets.value.length);
    } else {
      console.error('API returned success: false');
    }
  } catch (error) {
    console.error('Error fetching dropdown data:', error);
    console.error('Error details:', error.response?.data);
  }
};

// Fetch approvers data
const fetchApprovers = async () => {
  try {
    console.log('Fetching approvers data...');
    const url = route('employee-movements.approvers');
    const response = await axios.get(url);
    console.log('Approvers data response:', response.data);
    if (response.data.success) {
      approvers.value = response.data.approvers;
      console.log('Approvers loaded:', approvers.value.length);
    } else {
      console.error('API returned success: false');
    }
  } catch (error) {
    console.error('Error fetching approvers data:', error);
    console.error('Error details:', error.response?.data);
  }
};

// Load approval flows from movement
const loadApprovalFlows = () => {
  const approvalFlows = props.movement.approval_flows || props.movement.approvalFlows;
  
  if (approvalFlows && Array.isArray(approvalFlows) && approvalFlows.length > 0) {
    const approversList = approvalFlows
      .sort((a, b) => (a.approval_level || 0) - (b.approval_level || 0))
      .map(flow => {
        const approver = flow.approver;
        if (!approver) return null;
        
        let jabatanName = '';
        if (approver.jabatan) {
          if (typeof approver.jabatan === 'object' && approver.jabatan.nama_jabatan) {
            jabatanName = approver.jabatan.nama_jabatan;
          } else if (typeof approver.jabatan === 'string') {
            jabatanName = approver.jabatan;
          }
        }
        
        return {
          id: approver.id,
          name: approver.nama_lengkap || approver.name || approver.nik || '',
          email: approver.email || '',
          jabatan: jabatanName
        };
      })
      .filter(approver => approver !== null && approver !== undefined && approver.id);
    
    if (approversList.length > 0) {
      form.approvers.splice(0, form.approvers.length);
      approversList.forEach(approver => {
        form.approvers.push(approver);
      });
    }
  }
};

// Approval flow functions
const loadApprovers = async (search = '') => {
  try {
    const response = await axios.get('/employee-movements/approvers', {
      params: { search },
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json'
      }
    });
    
    if (response.data.success) {
      approverResults.value = response.data.approvers.map(approver => ({
        id: approver.id,
        name: approver.nama_lengkap,
        email: approver.email || '',
        jabatan: approver.jabatan?.nama_jabatan || ''
      }));
      showApproverDropdown.value = true;
    }
  } catch (error) {
    console.error('Failed to load approvers:', error);
    approverResults.value = [];
  }
};

const addApprover = (user) => {
  if (!form.approvers.find(approver => approver.id === user.id)) {
    form.approvers.push(user);
  }
  approverSearch.value = '';
  showApproverDropdown.value = false;
};

const removeApprover = (index) => {
  form.approvers.splice(index, 1);
};

const reorderApprover = (fromIndex, toIndex) => {
  const approver = form.approvers.splice(fromIndex, 1)[0];
  form.approvers.splice(toIndex, 0, approver);
};

// Initialize on mount
onMounted(() => {
  console.log('Component mounted, fetching dropdown data...');
  fetchDropdownData();
  fetchApprovers();
  loadApprovalFlows(); // Load existing approval flows
  
  // Debug: Check data after a delay
  setTimeout(() => {
    console.log('Positions after delay:', positions.value);
    console.log('Levels after delay:', levels.value);
    console.log('Divisions after delay:', divisions.value);
    console.log('Outlets after delay:', outlets.value);
    console.log('Approvers after delay:', approvers.value);
    console.log('Form approvers:', form.approvers);
  }, 2000);
});

const searchEmployees = async () => {
  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    showSearchResults.value = false;
    return;
  }

  isLoading.value = true;
  try {
    const response = await axios.get(route('employee-movements.search-employee'), {
      params: { search: searchQuery.value }
    });
    searchResults.value = response.data;
    showSearchResults.value = true;
  } catch (error) {
    console.error('Error searching employees:', error);
  } finally {
    isLoading.value = false;
  }
};

const selectEmployee = async (employee) => {
  selectedEmployee.value = employee;
  form.employee_id = employee.id;
  form.employee_name = employee.nama_lengkap;
  form.employee_position = employee.jabatan?.nama_jabatan || '';
  form.employee_division = employee.divisi?.nama_divisi || '';
  form.employee_unit_property = employee.outlet?.nama_outlet || '';
  form.employee_join_date = employee.tanggal_masuk || '';
  
  searchQuery.value = employee.nama_lengkap;
  showSearchResults.value = false;
  searchResults.value = [];
};

const submit = () => {
  // Calculate total salary from gaji_pokok and tunjangan
  const gajiPokok = parseInt(form.gaji_pokok_to) || 0;
  const tunjangan = parseInt(form.tunjangan_to) || 0;
  form.salary_to = gajiPokok + tunjangan;
  
  // Unformat salary_from (remove commas) before submit
  // IMPORTANT: Always process salary_from if salary_change is true, even if it's empty
  // This ensures the field is sent to backend and validation passes
  if (form.salary_change) {
    // Always ensure salary_from is a number, default to 0 if empty/null/undefined
    if (form.salary_from && form.salary_from !== '' && form.salary_from !== null) {
      const unformatted = unformatCurrency(form.salary_from);
      form.salary_from = unformatted ? parseInt(unformatted) || 0 : 0;
    } else {
      // If salary_from is empty/null/undefined, set to 0
      // This is required by backend when salary_change = true
      form.salary_from = 0;
    }
  } else {
    // If salary_change is false, set to null (backend will handle this)
    form.salary_from = null;
  }
  
  // Extract string values from multiselect objects
  const positionToValue = form.position_to?.id || '';
  const levelToValue = form.level_to?.id || '';
  const divisionToValue = form.division_to?.id || '';
  const unitPropertyToValue = form.unit_property_to?.id || '';

  // Create summary for confirmation
  const summary = {
    employee: form.employee_name,
    employment_type: form.employment_type,
    effective_date: form.employment_effective_date,
    position_change: form.position_change ? 'Yes' : 'No',
    position_to: form.position_to?.name || '-',
    level_change: form.level_change ? 'Yes' : 'No',
    level_to: form.level_to?.name || '-',
    salary_change: form.salary_change ? 'Yes' : 'No',
    salary_to: form.salary_to ? formatCurrency(form.salary_to) : '-',
    division_change: form.division_change ? 'Yes' : 'No',
    division_to: form.division_to?.name || '-',
    unit_property_change: form.unit_property_change ? 'Yes' : 'No',
    unit_property_to: form.unit_property_to?.name || '-',
    adjustment_effective_date: form.adjustment_effective_date,
    comments: form.comments || '-'
  };

  // Update form values to use string IDs instead of objects
  form.position_to = positionToValue;
  form.level_to = levelToValue;
  form.division_to = divisionToValue;
  form.unit_property_to = unitPropertyToValue;
  
  // Convert approvers array to IDs for submission
  form.approvers = form.approvers.map(approver => approver.id);

  // Show confirmation dialog with summary
  Swal.fire({
    title: 'Confirm Employee Movement Update',
    html: `
      <div class="text-left">
        <h3 class="font-bold text-lg mb-3">Summary:</h3>
        <div class="space-y-2 text-sm">
          <div><strong>Employee:</strong> ${summary.employee}</div>
          <div><strong>Employment Type:</strong> ${summary.employment_type}</div>
          <div><strong>Effective Date:</strong> ${summary.effective_date || '-'}</div>
          <hr class="my-2">
          <div><strong>Position Change:</strong> ${summary.position_change}</div>
          <div><strong>New Position:</strong> ${summary.position_to}</div>
          <div><strong>Level Change:</strong> ${summary.level_change}</div>
          <div><strong>New Level:</strong> ${summary.level_to}</div>
          <div><strong>Salary Change:</strong> ${summary.salary_change}</div>
          <div><strong>New Salary:</strong> ${summary.salary_to}</div>
          <div><strong>Division Change:</strong> ${summary.division_change}</div>
          <div><strong>New Division:</strong> ${summary.division_to}</div>
          <div><strong>Unit/Property Change:</strong> ${summary.unit_property_change}</div>
          <div><strong>New Unit/Property:</strong> ${summary.unit_property_to}</div>
          <div><strong>Adjustment Effective Date:</strong> ${summary.adjustment_effective_date || '-'}</div>
          <hr class="my-2">
          <div><strong>Comments:</strong> ${summary.comments}</div>
        </div>
      </div>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, Update Employee Movement',
    cancelButtonText: 'Cancel',
    showLoaderOnConfirm: true,
    preConfirm: () => {
      return new Promise((resolve) => {
        form.put(route('employee-movements.update', props.movement.id), {
          onSuccess: () => {
            resolve();
          },
          onError: (errors) => {
            Swal.showValidationMessage(`Request failed: ${Object.values(errors).join(', ')}`);
            resolve(false);
          },
          forceFormData: true,
        });
      });
    },
    allowOutsideClick: () => !Swal.isLoading()
  }).then((result) => {
    if (result.isConfirmed) {
      Swal.fire({
        title: 'Success!',
        text: 'Employee Movement has been updated successfully.',
        icon: 'success',
        confirmButtonText: 'OK'
      }).then(() => {
        router.visit('/employee-movements');
      });
    }
  });
};

const handleOtherAttachments = (event) => {
  const files = Array.from(event.target.files);
  form.other_attachments = [...form.other_attachments, ...files];
};

const removeOtherAttachment = (index) => {
  form.other_attachments.splice(index, 1);
};

const goBack = () => {
  router.visit('/employee-movements');
};
</script>

<template>
  <AppLayout title="Edit Employee Movement">
    <EmPageLayout
      title="Edit Employee Movement"
      subtitle="Perbarui data perubahan karyawan"
      show-back
      @back="goBack"
    >
      <form class="em-form" @submit.prevent="submit">
        <!-- Employee Search Section -->
        <div class="em-section">
          <div class="em-section-header">
            <div class="em-section-icon"><i class="fas fa-user"></i></div>
            <div>
              <h3 class="em-section-title">Data Karyawan</h3>
              <p class="em-section-desc">Cari dan pilih karyawan yang akan diajukan perubahan</p>
            </div>
          </div>
          <div class="em-section-body">
            <div class="mb-4">
              <label class="em-label">Cari Karyawan</label>
              <div class="relative em-search-wrap">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Nama, NIK, atau email..."
                  class="em-input !pl-9"
                  @input="searchEmployees"
                />
                <div
                  v-if="showSearchResults && searchResults.length > 0"
                  class="em-dropdown"
                >
                  <div
                    v-for="employee in searchResults"
                    :key="employee.id"
                    class="em-dropdown-item"
                    @click="selectEmployee(employee)"
                  >
                    <div class="font-semibold text-slate-800">{{ employee.nama_lengkap }}</div>
                    <div class="text-sm text-slate-500 mt-0.5">
                      NIK: {{ employee.nik }} · {{ employee.jabatan?.nama_jabatan || 'No Position' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="em-employee-card">
              <div class="em-employee-field">
                <label>Posisi</label>
                <input v-model="form.employee_position" type="text" readonly class="em-input em-input--readonly" />
              </div>
              <div class="em-employee-field">
                <label>Divisi</label>
                <input v-model="form.employee_division" type="text" readonly class="em-input em-input--readonly" />
              </div>
              <div class="em-employee-field">
                <label>Unit / Property</label>
                <input v-model="form.employee_unit_property" type="text" readonly class="em-input em-input--readonly" />
              </div>
              <div class="em-employee-field">
                <label>Tanggal Masuk</label>
                <input v-model="form.employee_join_date" type="date" readonly class="em-input em-input--readonly" />
              </div>
            </div>
          </div>
        </div>

        <!-- Employment & Renewal Section -->
        <div class="em-section">
          <div class="em-section-header">
            <div class="em-section-icon"><i class="fas fa-briefcase"></i></div>
            <div>
              <h3 class="em-section-title">Employment & Renewal</h3>
              <p class="em-section-desc">Pilih jenis perubahan dan tanggal efektif berlaku</p>
            </div>
          </div>
          <div class="em-section-body em-section-body--muted">
            <div class="em-choice-grid">
              <label
                v-for="type in employmentTypes"
                :key="type.value"
                class="em-choice-card"
                :class="{ 'em-choice-card--active': form.employment_type === type.value }"
              >
                <input v-model="form.employment_type" type="radio" :value="type.value" />
                <span class="em-choice-card-icon"><i class="fas" :class="type.icon"></i></span>
                <span class="em-choice-card-label">{{ type.label }}</span>
              </label>
            </div>
            <div class="mt-5 max-w-xs">
              <label class="em-label">Effective Date</label>
              <input v-model="form.employment_effective_date" type="date" class="em-input" />
            </div>
          </div>
        </div>

        <!-- Supporting Documents Section -->
        <div class="em-section">
          <div class="em-section-header">
            <div class="em-section-icon"><i class="fas fa-paperclip"></i></div>
            <div>
              <h3 class="em-section-title">Supporting Documents</h3>
              <p class="em-section-desc">Lampiran KPI, psikotest, training, dan dokumen pendukung lainnya</p>
            </div>
          </div>
          <div class="em-section-body em-section-body--muted">
            <div class="space-y-3">
                   <!-- KPI Section -->
                   <div class="em-doc-block">
                     <div class="flex items-center justify-between mb-3">
                       <div class="flex items-center">
                         <input
                           v-model="form.kpi_required"
                           type="checkbox"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                         />
                         <label class="ml-2 text-sm font-medium text-gray-700">Key Performance Indicators (KPI)</label>
                       </div>
                       <input
                         v-model="form.kpi_date"
                         type="date"
                         class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                       />
                     </div>
                     <div class="ml-6">
                       <label class="block text-sm text-gray-600 mb-2">Upload KPI Document (PDF/Image)</label>
                       <input
                         @change="form.kpi_attachment = $event.target.files[0]"
                         type="file"
                         accept=".pdf,.jpg,.jpeg,.png"
                         class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                       />
                       <p class="text-xs text-gray-500 mt-1">Max file size: 2MB. Allowed formats: PDF, JPG, JPEG, PNG</p>
                       <!-- Show existing file if available -->
                       <div v-if="props.movement.kpi_attachment" class="mt-2 p-2 bg-green-50 border border-green-200 rounded">
                         <p class="text-sm text-green-700">Current file: {{ props.movement.kpi_attachment.split('/').pop() }}</p>
                       </div>
                     </div>
                   </div>

                   <!-- Psikotest Section -->
                   <div class="em-doc-block">
                     <div class="flex items-center justify-between mb-3">
                       <div class="flex items-center">
                         <input
                           v-model="form.psikotest_required"
                           type="checkbox"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                         />
                         <label class="ml-2 text-sm font-medium text-gray-700">Psikotest Result by Training Manager: score =</label>
                       </div>
                       <div class="flex space-x-2">
                         <input
                           v-model="form.psikotest_score"
                           type="text"
                           placeholder="Score"
                           class="px-3 py-2 border border-gray-300 rounded-md w-20 text-sm"
                         />
                         <input
                           v-model="form.psikotest_date"
                           type="date"
                           class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                         />
                       </div>
                     </div>
                     <div class="ml-6">
                       <label class="block text-sm text-gray-600 mb-2">Upload Psikotest Result (PDF/Image)</label>
                       <input
                         @change="form.psikotest_attachment = $event.target.files[0]"
                         type="file"
                         accept=".pdf,.jpg,.jpeg,.png"
                         class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                       />
                       <p class="text-xs text-gray-500 mt-1">Max file size: 2MB. Allowed formats: PDF, JPG, JPEG, PNG</p>
                       <!-- Show existing file if available -->
                       <div v-if="props.movement.psikotest_attachment" class="mt-2 p-2 bg-green-50 border border-green-200 rounded">
                         <p class="text-sm text-green-700">Current file: {{ props.movement.psikotest_attachment.split('/').pop() }}</p>
                       </div>
                     </div>
                   </div>

                   <!-- Training Attendance Section -->
                   <div class="em-doc-block">
                     <div class="flex items-center justify-between mb-3">
                       <div class="flex items-center">
                         <input
                           v-model="form.training_attendance_required"
                           type="checkbox"
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                         />
                         <label class="ml-2 text-sm font-medium text-gray-700">Training Attendance Record by Training Manager</label>
                       </div>
                       <input
                         v-model="form.training_attendance_date"
                         type="date"
                         class="px-3 py-2 border border-gray-300 rounded-md text-sm"
                       />
                     </div>
                     <div class="ml-6">
                       <label class="block text-sm text-gray-600 mb-2">Upload Training Record (PDF/Image)</label>
                       <input
                         @change="form.training_attachment = $event.target.files[0]"
                         type="file"
                         accept=".pdf,.jpg,.jpeg,.png"
                         class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                       />
                       <p class="text-xs text-gray-500 mt-1">Max file size: 2MB. Allowed formats: PDF, JPG, JPEG, PNG</p>
                       <!-- Show existing file if available -->
                       <div v-if="props.movement.training_attachment" class="mt-2 p-2 bg-green-50 border border-green-200 rounded">
                         <p class="text-sm text-green-700">Current file: {{ props.movement.training_attachment.split('/').pop() }}</p>
                       </div>
                     </div>
                   </div>

                   <!-- Other Attachments Section -->
                   <div>
                     <label class="block text-sm font-medium text-gray-700 mb-3">Other Supporting Documents</label>
                     <div class="ml-6">
                       <input
                         @change="handleOtherAttachments"
                         type="file"
                         multiple
                         accept=".pdf,.jpg,.jpeg,.png"
                         class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                       />
                       <p class="text-xs text-gray-500 mt-1">Max file size: 2MB per file. Allowed formats: PDF, JPG, JPEG, PNG</p>
                       
                       <!-- Display selected files -->
                       <div v-if="form.other_attachments.length > 0" class="mt-3">
                         <p class="text-sm font-medium text-gray-700 mb-2">Selected files:</p>
                         <ul class="text-sm text-gray-600 space-y-1">
                           <li v-for="(file, index) in form.other_attachments" :key="index" class="flex items-center justify-between">
                             <span>{{ file.name }}</span>
                             <button
                               @click="removeOtherAttachment(index)"
                               type="button"
                               class="text-red-600 hover:text-red-800 text-xs"
                             >
                               Remove
                             </button>
                           </li>
                         </ul>
                       </div>

                       <!-- Show existing files if available -->
                       <div v-if="props.movement.other_attachments" class="mt-3">
                         <p class="text-sm font-medium text-gray-700 mb-2">Current files:</p>
                         <ul class="text-sm text-green-600 space-y-1">
                           <li v-for="(file, index) in JSON.parse(props.movement.other_attachments || '[]')" :key="index">
                             {{ file.split('/').pop() }}
                           </li>
                         </ul>
                       </div>
                     </div>
                   </div>
                 </div>
          </div>
        </div>

        <!-- Adjustment & Movement Section -->
        <div class="em-section">
          <div class="em-section-header">
            <div class="em-section-icon"><i class="fas fa-sliders-h"></i></div>
            <div>
              <h3 class="em-section-title">Adjustment & Movement</h3>
              <p class="em-section-desc">Atur perubahan posisi, level, gaji, divisi, dan outlet</p>
            </div>
          </div>
          <div class="em-section-body em-section-body--muted">
            <div class="space-y-3">
                  <!-- Position -->
                  <div class="em-change-row">
                    <div class="em-change-toggle">
                      <input
                        v-model="form.position_change"
                        type="checkbox"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Position</label>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">From</label>
                      <input
                        v-model="form.position_from"
                        type="text"
                        readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">To</label>
                      <Multiselect
                        v-model="form.position_to"
                        :options="positions"
                        :searchable="true"
                        :clear-on-select="false"
                        :close-on-select="true"
                        :show-labels="false"
                        track-by="id"
                        label="name"
                        placeholder="Select position..."
                        class="w-full"
                        @open="() => console.log('Position multiselect opened, options:', positions)"
                      />
                    </div>
                  </div>
                  
                  <!-- Level -->
                  <div class="em-change-row">
                    <div class="em-change-toggle">
                      <input
                        v-model="form.level_change"
                        type="checkbox"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Level</label>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">From</label>
                      <input
                        v-model="form.level_from"
                        type="text"
                        readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">To</label>
                      <Multiselect
                        v-model="form.level_to"
                        :options="levels"
                        :searchable="true"
                        :clear-on-select="false"
                        :close-on-select="true"
                        :show-labels="false"
                        track-by="id"
                        label="name"
                        placeholder="Select level..."
                        class="w-full"
                        @open="() => console.log('Level multiselect opened, options:', levels)"
                      />
                    </div>
                  </div>
                  
                  <!-- Salary -->
                  <div class="em-change-row">
                    <div class="em-change-toggle">
                      <input
                        v-model="form.salary_change"
                        type="checkbox"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Salary</label>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">From</label>
                      <input
                        v-model="form.salary_from"
                        type="text"
                        readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                      />
                    </div>
                    <div class="space-y-2">
                      <label class="block text-xs text-gray-600 mb-1">To</label>
                      <div class="grid grid-cols-2 gap-2">
                        <div>
                          <label class="block text-xs text-gray-500 mb-1">Gaji Pokok</label>
                          <input
                            v-model.number="form.gaji_pokok_to"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                          />
                        </div>
                        <div>
                          <label class="block text-xs text-gray-500 mb-1">Tunjangan</label>
                          <input
                            v-model.number="form.tunjangan_to"
                            type="number"
                            min="0"
                            step="1"
                            placeholder="0"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Division -->
                  <div class="em-change-row">
                    <div class="em-change-toggle">
                      <input
                        v-model="form.division_change"
                        type="checkbox"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Division</label>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">From</label>
                      <input
                        v-model="form.division_from"
                        type="text"
                        readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">To</label>
                      <Multiselect
                        v-model="form.division_to"
                        :options="divisions"
                        :searchable="true"
                        :clear-on-select="false"
                        :close-on-select="true"
                        :show-labels="false"
                        track-by="id"
                        label="name"
                        placeholder="Select division..."
                        class="w-full"
                        @open="() => console.log('Division multiselect opened, options:', divisions)"
                      />
                    </div>
                  </div>
                  
                  <!-- Unit/Property -->
                  <div class="em-change-row">
                    <div class="em-change-toggle">
                      <input
                        v-model="form.unit_property_change"
                        type="checkbox"
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                      />
                      <label class="ml-2 text-sm text-gray-700">Unit/Property</label>
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">From</label>
                      <input
                        v-model="form.unit_property_from"
                        type="text"
                        readonly
                        class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                      />
                    </div>
                    <div>
                      <label class="block text-xs text-gray-600 mb-1">To</label>
                      <Multiselect
                        v-model="form.unit_property_to"
                        :options="outlets"
                        :searchable="true"
                        :clear-on-select="false"
                        :close-on-select="true"
                        :show-labels="false"
                        track-by="id"
                        label="name"
                        placeholder="Select outlet..."
                        class="w-full"
                        @open="() => console.log('Outlet multiselect opened, options:', outlets)"
                      />
                    </div>
                  </div>
                </div>
                
                <div class="mt-4 max-w-xs">
                  <label class="em-label">Adjustment Effective Date</label>
                  <input v-model="form.adjustment_effective_date" type="date" class="em-input" />
                </div>
          </div>
        </div>

            <!-- Comments Section -->
            <div class="em-section">
              <div class="em-section-header">
                <div class="em-section-icon"><i class="fas fa-comment-alt"></i></div>
                <div>
                  <h3 class="em-section-title">Comments</h3>
                  <p class="em-section-desc">Jelaskan alasan perubahan karyawan</p>
                </div>
              </div>
              <div class="em-section-body">
                <label class="em-label">Alasan perubahan</label>
                <textarea
                  v-model="form.comments"
                  rows="4"
                  class="em-input"
                  placeholder="Tulis alasan perubahan di sini..."
                ></textarea>
              </div>
            </div>

            <!-- Approval Flow Section -->
            <div class="em-section">
              <div class="em-section-header">
                <div class="em-section-icon"><i class="fas fa-check-double"></i></div>
                <div>
                  <h3 class="em-section-title">Approval Flow</h3>
                  <p class="em-section-desc">Tambahkan approver dari level terendah ke tertinggi</p>
                </div>
              </div>
              <div class="em-section-body">
              <!-- Add Approver Input -->
              <div class="mb-4">
                <div class="relative em-search-wrap">
                  <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                  <input
                    v-model="approverSearch"
                    type="text"
                    placeholder="Cari user berdasarkan nama, email, atau jabatan..."
                    class="em-input !pl-9"
                    @input="approverSearch.length >= 2 && loadApprovers(approverSearch)"
                    @focus="approverSearch.length >= 2 && loadApprovers(approverSearch)"
                  />
                  <div v-if="showApproverDropdown && approverResults.length > 0" class="em-dropdown">
                    <div
                      v-for="user in approverResults"
                      :key="user.id"
                      class="em-dropdown-item"
                      @click="addApprover(user)"
                    >
                      <div class="font-semibold text-slate-800">{{ user.name }}</div>
                      <div class="text-sm text-slate-500">{{ user.email }}</div>
                      <div v-if="user.jabatan" class="text-xs text-indigo-600 font-medium mt-0.5">{{ user.jabatan }}</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Approvers List -->
              <div v-if="form.approvers.length > 0" class="space-y-2">
                <h4 class="text-sm font-semibold text-slate-600 mb-2">Urutan Approval (terendah → tertinggi)</h4>
                
                <div
                  v-for="(approver, index) in form.approvers"
                  :key="approver.id"
                  class="em-approver-item"
                >
                  <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1">
                      <button
                        v-if="index > 0"
                        type="button"
                        class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded"
                        title="Move Up"
                        @click="reorderApprover(index, index - 1)"
                      >
                        <i class="fa fa-arrow-up text-xs"></i>
                      </button>
                      <button
                        v-if="index < form.approvers.length - 1"
                        type="button"
                        class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded"
                        title="Move Down"
                        @click="reorderApprover(index, index + 1)"
                      >
                        <i class="fa fa-arrow-down text-xs"></i>
                      </button>
                    </div>
                    <span class="em-level-badge">Level {{ index + 1 }}</span>
                    <div>
                      <div class="font-semibold text-slate-800 text-sm">{{ approver.name }}</div>
                      <div class="text-xs text-slate-500">{{ approver.email }}</div>
                      <div v-if="approver.jabatan" class="text-xs text-indigo-600 font-medium">{{ approver.jabatan }}</div>
                    </div>
                  </div>
                  <button
                    type="button"
                    class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded"
                    title="Remove Approver"
                    @click="removeApprover(index)"
                  >
                    <i class="fa fa-times"></i>
                  </button>
                </div>
              </div>
              </div>
            </div>

            <!-- Form Actions -->
            <div class="em-form-footer">
              <button type="button" class="em-btn em-btn-secondary" @click="goBack">
                Batal
              </button>
              <button type="submit" class="em-btn em-btn-primary" :disabled="form.processing">
                <i v-if="form.processing" class="fas fa-spinner fa-spin"></i>
                <i v-else class="fas fa-save"></i>
                {{ form.processing ? 'Menyimpan...' : 'Update Employee Movement' }}
              </button>
            </div>
          </form>
    </EmPageLayout>
  </AppLayout>
</template>
