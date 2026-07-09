<template>
  <AppLayout>
    <div class="w-full max-w-none py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-file-circle-check text-indigo-500"></i>
            SOP Development Completion
          </h1>
          <p class="text-sm text-gray-500 mt-1">Kelola pengembangan SOP, upload dokumen, dan ajukan approval</p>
        </div>
        <button
          type="button"
          @click="openCreateModal"
          class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg shadow hover:bg-indigo-700 transition"
        >
          <i class="fa-solid fa-plus"></i>
          Buat SOP Baru
        </button>
      </div>

      <div class="bg-white rounded-xl shadow p-4 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
          <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Cari</label>
            <input
              v-model="search"
              type="text"
              placeholder="Judul atau deskripsi SOP..."
              class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
              @input="onSearchInput"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
            <select v-model="statusFilter" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" @change="applyFilters">
              <option value="all">Semua</option>
              <option value="draft">Draft</option>
              <option value="pending">Menunggu Approval</option>
              <option value="approved">Selesai</option>
              <option value="rejected">Ditolak</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Per Halaman</label>
            <select v-model="perPage" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" @change="applyFilters">
              <option :value="10">10</option>
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Judul SOP</th>
              <th v-if="isSuperAdmin" class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Pembuat</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Due Date</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Approval Flow</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Status</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">File</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="!records.data?.length">
              <td colspan="7" class="px-4 py-8 text-center text-gray-500">Belum ada data SOP development.</td>
            </tr>
            <tr v-for="record in records.data" :key="record.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <div class="font-semibold text-gray-800">{{ record.title }}</div>
                <div v-if="record.description" class="text-xs text-gray-500 line-clamp-2 mt-1">{{ record.description }}</div>
              </td>
              <td v-if="isSuperAdmin" class="px-4 py-3 text-sm text-gray-700">{{ record.user?.nama_lengkap || '-' }}</td>
              <td class="px-4 py-3 text-sm">
                <span :class="record.is_overdue ? 'text-red-600 font-semibold' : 'text-gray-700'">
                  {{ formatDateOnly(record.due_date) }}
                </span>
                <span v-if="record.is_overdue" class="ml-1 text-xs text-red-500">(Overdue)</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">
                <div v-if="record.approval_flows?.length" class="space-y-1">
                  <div v-for="flow in record.approval_flows" :key="flow.id" class="text-xs">
                    <span class="font-semibold text-indigo-600">L{{ flow.approval_level }}</span>
                    {{ flow.approver?.nama_lengkap || '-' }}
                    <span :class="flowStatusClass(flow.status)" class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold">{{ flow.status }}</span>
                  </div>
                </div>
                <span v-else>-</span>
              </td>
              <td class="px-4 py-3">
                <span :class="statusBadgeClass(record.status)" class="px-2 py-1 rounded-full text-xs font-bold">
                  {{ record.status_text }}
                </span>
              </td>
              <td class="px-4 py-3 text-sm">
                <a
                  v-if="record.file_path"
                  :href="`/sop-development-completion/${record.id}/file`"
                  target="_blank"
                  class="text-indigo-600 hover:text-indigo-800"
                >
                  <i class="fa-solid fa-file-arrow-down mr-1"></i>
                  {{ record.file_original_name || 'Download' }}
                </a>
                <span v-else class="text-gray-400">-</span>
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex justify-end gap-2 flex-wrap">
                  <button
                    v-if="record.status === 'draft'"
                    type="button"
                    @click="openEditModal(record)"
                    class="text-blue-600 hover:text-blue-800 text-sm"
                  >
                    <i class="fa-solid fa-pen"></i>
                  </button>
                  <button
                    v-if="record.status === 'draft' || record.status === 'rejected'"
                    type="button"
                    @click="openSubmitModal(record)"
                    class="text-indigo-600 hover:text-indigo-800 text-sm font-semibold"
                  >
                    <i class="fa-solid fa-paper-plane mr-1"></i>
                    {{ record.status === 'rejected' ? 'Upload Ulang' : 'Submit' }}
                  </button>
                  <button
                    v-if="record.status === 'draft'"
                    type="button"
                    @click="deleteRecord(record)"
                    class="text-red-600 hover:text-red-800 text-sm"
                  >
                    <i class="fa-solid fa-trash"></i>
                  </button>
                  <button
                    type="button"
                    @click="openDetailModal(record)"
                    class="text-gray-600 hover:text-gray-800 text-sm"
                  >
                    <i class="fa-solid fa-eye"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="records.last_page > 1" class="mt-4 flex justify-center gap-2 flex-wrap">
        <button
          v-for="link in records.links"
          :key="link.label"
          type="button"
          :disabled="!link.url"
          @click="goToPage(link.url)"
          class="px-3 py-1 rounded border text-sm"
          :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
          v-html="link.label"
        />
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <Teleport to="body">
      <div v-if="showFormModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999]" @click="closeFormModal">
        <div class="bg-white rounded-xl p-6 w-full max-w-lg mx-4" @click.stop>
          <h3 class="text-lg font-bold text-gray-800 mb-4">
            {{ formMode === 'create' ? 'Buat SOP Development' : 'Edit SOP Development' }}
          </h3>
          <form @submit.prevent="saveForm" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Judul SOP *</label>
              <input v-model="form.title" type="text" required maxlength="255" class="w-full rounded-lg border-gray-300" placeholder="Contoh: SOP Penyajian Makanan" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi</label>
              <textarea v-model="form.description" rows="3" maxlength="2000" class="w-full rounded-lg border-gray-300" placeholder="Jelaskan SOP apa yang akan dibuat..."></textarea>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Due Date *</label>
              <input v-model="form.due_date" type="date" required class="w-full rounded-lg border-gray-300" :min="formMode === 'create' ? today : undefined" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="closeFormModal" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200">Batal</button>
              <button type="submit" :disabled="isSaving" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">
                {{ isSaving ? 'Menyimpan...' : 'Simpan' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Submit Approval Modal -->
    <Teleport to="body">
      <div v-if="showSubmitModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999]" @click="closeSubmitModal">
        <div class="bg-white rounded-xl p-6 w-full max-w-lg mx-4" @click.stop>
          <h3 class="text-lg font-bold text-gray-800 mb-1">
            {{ selectedRecord?.status === 'rejected' ? 'Upload Ulang & Ajukan Approval' : 'Submit untuk Approval' }}
          </h3>
          <p class="text-sm text-gray-500 mb-4">{{ selectedRecord?.title }}</p>

          <div v-if="selectedRecord?.status === 'rejected' && selectedRecord?.approval_notes" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <strong>Alasan penolakan:</strong> {{ selectedRecord.approval_notes }}
          </div>

          <form @submit.prevent="submitApproval" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">File SOP *</label>
              <input type="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" class="w-full text-sm" @change="onFileChange" />
              <p class="text-xs text-gray-500 mt-1">PDF, Word, Excel, PowerPoint. Maks. 20MB.</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Approval Flow *</label>
              <p class="text-xs text-gray-500 mb-2">Pilih approver secara berurutan (level 1 = pertama disetujui)</p>
              <input
                v-model="approverSearch"
                type="text"
                placeholder="Cari nama approver..."
                class="w-full rounded-lg border-gray-300 mb-2"
                @input="searchApprovers"
              />
              <div v-if="approverLoading" class="text-sm text-gray-500">Mencari...</div>
              <div v-else-if="approverResults.length" class="max-h-32 overflow-y-auto border rounded-lg divide-y mb-3">
                <button
                  v-for="user in approverResults"
                  :key="user.id"
                  type="button"
                  class="w-full text-left px-3 py-2 hover:bg-indigo-50 text-sm"
                  @click="addApprover(user)"
                >
                  <div>{{ user.name }}</div>
                  <div class="text-xs text-gray-500">{{ user.jabatan || user.email }}</div>
                </button>
              </div>
              <div v-if="selectedApprovers.length" class="space-y-2">
                <div
                  v-for="(approver, index) in selectedApprovers"
                  :key="approver.id"
                  class="flex items-center gap-3 p-2 rounded-lg bg-indigo-50 border border-indigo-100"
                >
                  <span class="w-6 h-6 rounded-full bg-indigo-600 text-white text-xs font-bold flex items-center justify-center">{{ index + 1 }}</span>
                  <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium truncate">{{ approver.name }}</div>
                    <div class="text-xs text-gray-500 truncate">{{ approver.jabatan || approver.email }}</div>
                  </div>
                  <button type="button" class="text-red-500 hover:text-red-700" @click="removeApprover(index)">
                    <i class="fa-solid fa-times"></i>
                  </button>
                </div>
              </div>
              <p v-else class="text-sm text-gray-400">Belum ada approver dipilih.</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="closeSubmitModal" class="px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200">Batal</button>
              <button type="submit" :disabled="isSubmitting || !submitForm.file || !selectedApprovers.length" class="px-4 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">
                {{ isSubmitting ? 'Mengirim...' : 'Ajukan Approval' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- Detail Modal -->
    <Teleport to="body">
      <div v-if="showDetailModal && detailRecord" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[9999]" @click="closeDetailModal">
        <div class="bg-white rounded-xl p-6 w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" @click.stop>
          <div class="flex justify-between items-start mb-4">
            <h3 class="text-lg font-bold text-gray-800">Detail SOP Development</h3>
            <button type="button" @click="closeDetailModal" class="text-gray-400 hover:text-gray-600"><i class="fa fa-times"></i></button>
          </div>
          <div class="space-y-4 text-sm">
            <div class="grid grid-cols-2 gap-4">
              <div><span class="text-gray-500">Judul:</span><p class="font-semibold">{{ detailRecord.title }}</p></div>
              <div><span class="text-gray-500">Status:</span><p><span :class="statusBadgeClass(detailRecord.status)" class="px-2 py-1 rounded-full text-xs font-bold">{{ detailRecord.status_text }}</span></p></div>
              <div><span class="text-gray-500">Due Date:</span><p>{{ formatDateOnly(detailRecord.due_date) }}</p></div>
              <div v-if="detailRecord.submitted_at"><span class="text-gray-500">Diajukan:</span><p>{{ formatDateTime(detailRecord.submitted_at) }}</p></div>
              <div v-if="detailRecord.approved_at"><span class="text-gray-500">Disetujui:</span><p>{{ formatDateTime(detailRecord.approved_at) }}</p></div>
            </div>
            <div v-if="detailRecord.approval_flows?.length">
              <span class="text-gray-500">Approval Flow:</span>
              <div class="mt-2 space-y-2">
                <div v-for="flow in detailRecord.approval_flows" :key="flow.id" class="flex items-center gap-2 text-sm p-2 bg-gray-50 rounded-lg">
                  <span class="w-6 h-6 rounded-full bg-indigo-600 text-white text-xs font-bold flex items-center justify-center">{{ flow.approval_level }}</span>
                  <span class="flex-1">{{ flow.approver?.nama_lengkap || '-' }}</span>
                  <span :class="flowStatusClass(flow.status)" class="px-2 py-0.5 rounded text-xs font-bold">{{ flow.status }}</span>
                </div>
              </div>
            </div>
            <div v-if="detailRecord.description">
              <span class="text-gray-500">Deskripsi:</span>
              <p class="whitespace-pre-wrap mt-1">{{ detailRecord.description }}</p>
            </div>
            <div v-if="detailRecord.approval_notes && detailRecord.status === 'rejected'">
              <span class="text-gray-500">Catatan Penolakan:</span>
              <p class="text-red-700 mt-1">{{ detailRecord.approval_notes }}</p>
            </div>
            <div v-if="detailRecord.file_path">
              <a :href="`/sop-development-completion/${detailRecord.id}/file`" target="_blank" class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800">
                <i class="fa-solid fa-file-arrow-down"></i>
                {{ detailRecord.file_original_name || 'Download File SOP' }}
              </a>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
  records: Object,
  filters: Object,
  isSuperAdmin: Boolean,
});

const search = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || 'all');
const perPage = ref(props.filters?.per_page || 15);

const showFormModal = ref(false);
const showSubmitModal = ref(false);
const showDetailModal = ref(false);
const formMode = ref('create');
const selectedRecord = ref(null);
const detailRecord = ref(null);
const isSaving = ref(false);
const isSubmitting = ref(false);

const form = ref({ title: '', description: '', due_date: '' });
const submitForm = ref({ file: null });
const approverSearch = ref('');
const approverResults = ref([]);
const selectedApprovers = ref([]);
const approverLoading = ref(false);

const today = computed(() => new Date().toISOString().split('T')[0]);

const debouncedFilter = debounce(() => applyFilters(), 400);

function onSearchInput() {
  debouncedFilter();
}

function applyFilters() {
  router.get('/sop-development-completion', {
    search: search.value || undefined,
    status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}

function goToPage(url) {
  if (!url) return;
  router.get(url, {}, { preserveState: true });
}

function formatDateOnly(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatDateTime(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function statusBadgeClass(status) {
  const map = {
    draft: 'bg-gray-100 text-gray-700 border border-gray-300',
    pending: 'bg-yellow-100 text-yellow-800 border border-yellow-300',
    approved: 'bg-green-100 text-green-800 border border-green-300',
    rejected: 'bg-red-100 text-red-800 border border-red-300',
  };
  return map[status] || 'bg-gray-100 text-gray-700';
}

function flowStatusClass(status) {
  const map = {
    PENDING: 'bg-yellow-100 text-yellow-800',
    APPROVED: 'bg-green-100 text-green-800',
    REJECTED: 'bg-red-100 text-red-800',
  };
  return map[status] || 'bg-gray-100 text-gray-700';
}

function openCreateModal() {
  formMode.value = 'create';
  form.value = { title: '', description: '', due_date: '' };
  showFormModal.value = true;
}

function openEditModal(record) {
  formMode.value = 'edit';
  selectedRecord.value = record;
  form.value = {
    title: record.title,
    description: record.description || '',
    due_date: record.due_date?.substring?.(0, 10) || record.due_date,
  };
  showFormModal.value = true;
}

function closeFormModal() {
  showFormModal.value = false;
  selectedRecord.value = null;
}

async function saveForm() {
  isSaving.value = true;
  try {
    const payload = { ...form.value };
    let response;
    if (formMode.value === 'create') {
      response = await axios.post('/api/sop-development-completion', payload);
    } else {
      response = await axios.put(`/api/sop-development-completion/${selectedRecord.value.id}`, payload);
    }
    if (response.data?.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      closeFormModal();
      router.reload({ only: ['records'] });
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menyimpan data', 'error');
  } finally {
    isSaving.value = false;
  }
}

function openSubmitModal(record) {
  selectedRecord.value = record;
  submitForm.value = { file: null };
  selectedApprovers.value = (record.approval_flows || [])
    .sort((a, b) => a.approval_level - b.approval_level)
    .map((flow) => ({
      id: flow.approver_id,
      name: flow.approver?.nama_lengkap || `User #${flow.approver_id}`,
      jabatan: flow.approver?.jabatan || '',
      email: flow.approver?.email || '',
    }));
  approverSearch.value = '';
  approverResults.value = [];
  showSubmitModal.value = true;
}

function closeSubmitModal() {
  showSubmitModal.value = false;
  selectedRecord.value = null;
  selectedApprovers.value = [];
}

function onFileChange(event) {
  submitForm.value.file = event.target.files?.[0] || null;
}

const searchApprovers = debounce(async () => {
  approverLoading.value = true;
  try {
    const response = await axios.get('/api/sop-development-completion/approvers', {
      params: { search: approverSearch.value },
    });
    approverResults.value = response.data?.users || [];
  } catch {
    approverResults.value = [];
  } finally {
    approverLoading.value = false;
  }
}, 300);

function addApprover(user) {
  if (!selectedApprovers.value.find((a) => a.id === user.id)) {
    selectedApprovers.value.push(user);
  }
  approverSearch.value = '';
  approverResults.value = [];
}

function removeApprover(index) {
  selectedApprovers.value.splice(index, 1);
}

async function submitApproval() {
  if (!selectedRecord.value || !submitForm.value.file || !selectedApprovers.value.length) {
    Swal.fire('Error', 'File SOP dan minimal satu approver wajib diisi.', 'error');
    return;
  }
  isSubmitting.value = true;
  try {
    const formData = new FormData();
    formData.append('file', submitForm.value.file);
    selectedApprovers.value.forEach((approver, index) => {
      formData.append(`approvers[${index}]`, approver.id);
    });
    const response = await axios.post(
      `/api/sop-development-completion/${selectedRecord.value.id}/submit-approval`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } }
    );
    if (response.data?.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      closeSubmitModal();
      router.reload({ only: ['records'] });
    }
  } catch (error) {
    const errors = error.response?.data?.errors;
    const msg = errors ? Object.values(errors).flat().join(', ') : (error.response?.data?.message || 'Gagal mengajukan approval');
    Swal.fire('Error', msg, 'error');
  } finally {
    isSubmitting.value = false;
  }
}

async function deleteRecord(record) {
  const result = await Swal.fire({
    title: 'Hapus SOP?',
    text: `Yakin ingin menghapus "${record.title}"?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    confirmButtonText: 'Hapus',
    cancelButtonText: 'Batal',
  });
  if (!result.isConfirmed) return;
  try {
    const response = await axios.delete(`/api/sop-development-completion/${record.id}`);
    if (response.data?.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      router.reload({ only: ['records'] });
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menghapus', 'error');
  }
}

function openDetailModal(record) {
  detailRecord.value = record;
  showDetailModal.value = true;
}

function closeDetailModal() {
  showDetailModal.value = false;
  detailRecord.value = null;
}
</script>
