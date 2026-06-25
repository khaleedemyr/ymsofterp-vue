<template>
  <AppLayout>
    <div class="max-w-4xl mx-auto py-8 px-4">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-user-graduate text-blue-600"></i>
            {{ isEdit ? 'Edit' : 'Tambah' }} Employee Coaching
          </h1>
        </div>
        <Link :href="route('employee-coaching.index')" class="text-gray-600 hover:text-gray-800">
          <i class="fa-solid fa-arrow-left mr-1"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit" class="space-y-6">
        <!-- Employee Search -->
        <div class="bg-white rounded-xl shadow p-6 space-y-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
              Karyawan <span class="text-red-500">*</span>
            </label>
            <div class="relative">
              <input
                id="employee-search-input"
                v-model="employeeSearch"
                type="text"
                placeholder="Cari nama karyawan..."
                autocomplete="off"
                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                @input="onEmployeeSearch"
                @focus="onEmployeeFocus"
                @blur="onEmployeeBlur"
              />
              <Teleport to="body">
                <div
                  v-if="showEmployeeDropdown && employeeSuggestions.length"
                  :style="employeeDropdownStyle"
                  class="fixed z-[99999] bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                >
                  <button
                    v-for="emp in employeeSuggestions"
                    :key="emp.id"
                    type="button"
                    class="w-full text-left px-3 py-2 hover:bg-blue-50 border-b border-gray-100 last:border-b-0"
                    @mousedown.prevent="selectEmployee(emp)"
                  >
                    <div class="font-medium text-gray-800">{{ emp.nama_lengkap }}</div>
                    <div class="text-xs text-gray-500">{{ emp.jabatan_name }} · {{ emp.outlet_name }}</div>
                  </button>
                </div>
              </Teleport>
            </div>
            <p v-if="form.errors.employee_id" class="text-sm text-red-600 mt-1">{{ form.errors.employee_id }}</p>
          </div>

          <div v-if="selectedEmployee" class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-gray-100">
            <div>
              <div class="text-xs text-gray-500 uppercase tracking-wide">Outlet</div>
              <div class="font-semibold text-gray-800">{{ selectedEmployee.outlet_name || '-' }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500 uppercase tracking-wide">Jabatan</div>
              <div class="font-semibold text-gray-800">{{ selectedEmployee.jabatan_name || '-' }}</div>
            </div>
            <div>
              <div class="text-xs text-gray-500 uppercase tracking-wide">Divisi</div>
              <div class="font-semibold text-gray-800">{{ selectedEmployee.division_name || '-' }}</div>
            </div>
          </div>
        </div>

        <!-- Point of Concern -->
        <div class="bg-white rounded-xl shadow p-6 space-y-4">
          <div>
            <h2 class="text-lg font-semibold text-gray-800">Point of Concern, Issue, or Incident involving</h2>
            <p class="text-sm italic text-gray-500">Hal yang diperhatikan, masalah / kendala, keterlibatan kejadian</p>
          </div>

          <div class="space-y-4">
            <div
              v-for="option in concernOptions"
              :key="option.code"
              class="border border-gray-200 rounded-lg p-4"
            >
              <label class="flex items-start gap-3 cursor-pointer">
                <input
                  v-model="concernState[option.code].checked"
                  type="checkbox"
                  class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  @change="onConcernToggle(option.code)"
                />
                <div>
                  <div class="font-medium text-gray-800">{{ option.label_en }}</div>
                  <div class="text-sm italic text-gray-500">{{ option.label_id }}</div>
                </div>
              </label>

              <div v-if="concernState[option.code].checked" class="mt-3 pl-7 space-y-3">
                <div v-if="option.code === 'other'">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Lain-Lain (sebutkan)</label>
                  <input
                    v-model="concernState[option.code].other_label"
                    type="text"
                    placeholder="Tulis kategori lain..."
                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Comment <span class="text-red-500">*</span></label>
                  <textarea
                    v-model="concernState[option.code].comment"
                    rows="3"
                    placeholder="Tulis comment..."
                    class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500 text-sm"
                  ></textarea>
                </div>
              </div>
            </div>
          </div>
          <p v-if="form.errors.concerns" class="text-sm text-red-600">{{ form.errors.concerns }}</p>
        </div>

        <!-- Performance Description -->
        <div class="bg-white rounded-xl shadow p-6">
          <label class="block text-sm font-semibold text-gray-800 mb-1">
            Describe specific performance concern or issue
          </label>
          <p class="text-sm italic text-gray-500 mb-3">Jelaskan masalah atau kinerja yang menjadi perhatian khusus</p>
          <textarea
            v-model="form.performance_description"
            rows="5"
            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            placeholder="Tulis deskripsi..."
          ></textarea>
        </div>

        <!-- Action Taken -->
        <div class="bg-white rounded-xl shadow p-6 space-y-4">
          <div>
            <label class="block text-sm font-semibold text-gray-800 mb-1">
              Action taken to improve performance & Due Date
            </label>
            <p class="text-sm italic text-gray-500 mb-3">Tindak lanjut perbaikan kinerja & batas waktu</p>
            <textarea
              v-model="form.action_taken"
              rows="4"
              class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
              placeholder="Tulis tindak lanjut..."
            ></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
            <input
              v-model="form.action_due_date"
              type="date"
              class="w-full md:w-64 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
            />
          </div>
        </div>

        <!-- Performance Review Plan Date -->
        <div class="bg-white rounded-xl shadow p-6">
          <label class="block text-sm font-semibold text-gray-800 mb-1">
            Performance review plan date
          </label>
          <p class="text-sm italic text-gray-500 mb-3">Tanggal rencana peninjauan kinerja</p>
          <input
            v-model="form.performance_review_plan_date"
            type="date"
            class="w-full md:w-64 rounded-lg border-gray-300 focus:border-blue-500 focus:ring-blue-500"
          />
        </div>

        <div class="flex justify-end gap-3">
          <Link
            :href="route('employee-coaching.index')"
            class="px-6 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700"
          >
            Batal
          </Link>
          <button
            type="submit"
            :disabled="form.processing"
            class="px-6 py-2.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
          >
            {{ form.processing ? 'Menyimpan...' : (isEdit ? 'Update' : 'Simpan') }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  record: { type: Object, default: null },
  concernOptions: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.record?.id);

const concernState = reactive({});
props.concernOptions.forEach((option) => {
  concernState[option.code] = {
    checked: false,
    comment: '',
    other_label: '',
  };
});

const form = useForm({
  employee_id: props.record?.employee_id || '',
  performance_description: props.record?.performance_description || '',
  action_taken: props.record?.action_taken || '',
  action_due_date: formatDateInput(props.record?.action_due_date),
  performance_review_plan_date: formatDateInput(props.record?.performance_review_plan_date),
  concerns: [],
});

const employeeSearch = ref(props.record?.employee_name || '');
const selectedEmployee = ref(null);
const employeeSuggestions = ref([]);
const showEmployeeDropdown = ref(false);
const employeeDropdownStyle = ref({});
let searchTimer = null;

function formatDateInput(value) {
  if (!value) return '';
  const str = String(value);
  return str.length >= 10 ? str.slice(0, 10) : str;
}

function initFromRecord() {
  if (!props.record) return;

  selectedEmployee.value = {
    id: props.record.employee_id,
    nama_lengkap: props.record.employee_name,
    jabatan_name: props.record.jabatan_name,
    outlet_name: props.record.outlet_name,
    division_name: props.record.division_name,
  };

  (props.record.concerns || []).forEach((item) => {
    if (!concernState[item.concern_code]) return;
    concernState[item.concern_code].checked = true;
    concernState[item.concern_code].comment = item.comment || '';
    concernState[item.concern_code].other_label = item.other_label || '';
  });
}

function onEmployeeSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(async () => {
    const q = employeeSearch.value.trim();
    if (q.length < 2) {
      employeeSuggestions.value = [];
      showEmployeeDropdown.value = false;
      return;
    }
    try {
      const res = await axios.get('/api/employee-coaching/search-employees', { params: { q } });
      employeeSuggestions.value = res.data.employees || [];
      showEmployeeDropdown.value = employeeSuggestions.value.length > 0;
      await nextTick();
      updateDropdownPosition();
    } catch {
      employeeSuggestions.value = [];
      showEmployeeDropdown.value = false;
    }
  }, 300);
}

function onEmployeeFocus() {
  if (employeeSuggestions.value.length) {
    showEmployeeDropdown.value = true;
    nextTick().then(updateDropdownPosition);
  }
}

function onEmployeeBlur() {
  setTimeout(() => {
    showEmployeeDropdown.value = false;
  }, 150);
}

function updateDropdownPosition() {
  const el = document.getElementById('employee-search-input');
  if (!el) return;
  const rect = el.getBoundingClientRect();
  employeeDropdownStyle.value = {
    top: `${rect.bottom + 4}px`,
    left: `${rect.left}px`,
    width: `${rect.width}px`,
  };
}

function selectEmployee(emp) {
  selectedEmployee.value = emp;
  form.employee_id = emp.id;
  employeeSearch.value = emp.nama_lengkap;
  showEmployeeDropdown.value = false;
}

function onConcernToggle(code) {
  if (!concernState[code].checked) {
    concernState[code].comment = '';
    concernState[code].other_label = '';
  }
}

function buildConcernsPayload() {
  return props.concernOptions
    .filter((option) => concernState[option.code].checked)
    .map((option) => ({
      code: option.code,
      comment: concernState[option.code].comment.trim(),
      other_label: option.code === 'other' ? (concernState[option.code].other_label || null) : null,
    }));
}

function validateClient() {
  if (!form.employee_id) {
    Swal.fire({ icon: 'warning', title: 'Karyawan wajib dipilih' });
    return false;
  }

  const concerns = buildConcernsPayload();
  if (!concerns.length) {
    Swal.fire({ icon: 'warning', title: 'Pilih minimal satu Point of Concern' });
    return false;
  }

  for (const item of concerns) {
    if (!item.comment) {
      Swal.fire({ icon: 'warning', title: 'Comment wajib diisi untuk setiap concern yang dipilih' });
      return false;
    }
    if (item.code === 'other' && !item.other_label?.trim()) {
      Swal.fire({ icon: 'warning', title: 'Isian Lain-Lain wajib diisi jika opsi Other dipilih' });
      return false;
    }
  }

  return true;
}

function submit() {
  if (!validateClient()) return;

  form.concerns = buildConcernsPayload();

  if (isEdit.value) {
    form.put(route('employee-coaching.update', props.record.id));
  } else {
    form.post(route('employee-coaching.store'));
  }
}

onMounted(() => {
  initFromRecord();
});
</script>
