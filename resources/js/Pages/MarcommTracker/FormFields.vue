<template>
  <div class="space-y-5">
    <!-- Job Info -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80">
        <h2 class="text-sm font-semibold text-gray-800">Job Info</h2>
      </div>
      <div class="p-5 space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Job Category <span class="text-red-500">*</span></label>
            <select v-model="form.job_category" required :class="inputClass">
              <option value="" disabled>Pilih category</option>
              <option v-for="c in jobCategories" :key="c" :value="c">{{ c }}</option>
            </select>
            <p v-if="form.errors?.job_category" class="text-sm text-red-600 mt-1">{{ form.errors.job_category }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">PIC <span class="text-red-500">*</span></label>
            <select v-model="form.pic_user_id" required :class="inputClass">
              <option value="" disabled>Pilih PIC</option>
              <option v-for="u in picUsers" :key="u.id" :value="u.id">{{ u.nama_lengkap }}</option>
            </select>
            <p v-if="form.errors?.pic_user_id" class="text-sm text-red-600 mt-1">{{ form.errors.pic_user_id }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Due Date</label>
            <input v-model="form.due_date" type="date" :class="inputClass" />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Status <span class="text-red-500">*</span></label>
            <select v-model="form.job_status" required :class="inputClass">
              <option v-for="(label, key) in jobStatuses" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
        </div>

        <!-- Remark per status -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Remark Status</label>
          <div class="flex flex-wrap gap-1.5 mb-3">
            <button
              v-for="(label, key) in jobStatuses"
              :key="key"
              type="button"
              class="px-3 py-1.5 rounded-md text-sm font-medium transition"
              :class="remarkTab === key
                ? 'bg-rose-600 text-white shadow-sm'
                : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
              @click="remarkTab = key"
            >
              {{ label }}
              <span
                v-if="form.status_remarks[key]"
                class="ml-1 inline-block w-1.5 h-1.5 rounded-full align-middle"
                :class="remarkTab === key ? 'bg-white' : 'bg-rose-500'"
              ></span>
            </button>
          </div>
          <textarea
            v-model="form.status_remarks[remarkTab]"
            rows="3"
            :class="inputClass"
            :placeholder="`Remark untuk ${jobStatuses[remarkTab] || remarkTab}`"
          ></textarea>
          <p class="text-xs text-gray-500 mt-1.5">
            Status aktif job: <span class="font-medium text-gray-700">{{ jobStatuses[form.job_status] }}</span>.
            Remark tiap status tersimpan terpisah.
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Task</label>
          <RichTextEditor v-model="form.task" min-height="140px" placeholder="Deskripsi task..." />
        </div>

        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-sm font-medium text-gray-700">Output Link</label>
            <button
              type="button"
              class="inline-flex items-center gap-1 text-sm font-medium text-rose-600 hover:text-rose-700"
              @click="addLink"
            >
              <i class="fa-solid fa-plus text-xs"></i> Tambah
            </button>
          </div>
          <div v-if="!form.output_links.length" class="text-sm text-gray-400 py-2">Belum ada link.</div>
          <div class="space-y-2">
            <div v-for="(link, idx) in form.output_links" :key="idx" class="flex gap-2 items-center">
              <input
                v-model="form.output_links[idx]"
                type="url"
                placeholder="https://..."
                :class="inputClass"
              />
              <button
                type="button"
                class="shrink-0 w-10 h-10 rounded-lg border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50 transition"
                title="Hapus"
                @click="removeLink(idx)"
              >
                <i class="fa-solid fa-trash text-sm"></i>
              </button>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Remarks</label>
          <textarea
            v-model="form.remarks"
            rows="3"
            :class="inputClass"
            placeholder="Catatan umum..."
          ></textarea>
        </div>
      </div>
    </div>

    <!-- Approval Flow -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-visible">
      <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80 rounded-t-xl">
        <h2 class="text-sm font-semibold text-gray-800">Approval Flow</h2>
        <p class="text-xs text-gray-500 mt-0.5">
          Level terendah → tertinggi. Ada approver = Submitted; semua approve = Approved.
        </p>
      </div>
      <div class="p-5 overflow-visible">
        <div class="relative mb-4 z-30">
          <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
          <input
            v-model="approverSearch"
            type="text"
            placeholder="Cari nama / jabatan approver..."
            class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-rose-500"
            @input="searchApprovers"
          />
          <div
            v-if="approverResults.length > 0"
            class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-xl max-h-60 overflow-y-auto"
          >
            <button
              v-for="user in approverResults"
              :key="user.id"
              type="button"
              class="w-full text-left px-4 py-2.5 hover:bg-rose-50 text-sm border-b border-gray-100 last:border-0"
              @click="addApprover(user)"
            >
              <div class="font-medium text-gray-800">{{ user.nama_lengkap }}</div>
              <div class="text-xs text-gray-500">{{ user.jabatan || '-' }} · {{ user.email }}</div>
            </button>
          </div>
        </div>

        <div
          v-if="form.approvers.length === 0"
          class="rounded-lg border border-dashed border-amber-300 bg-amber-50/60 px-4 py-3 text-sm text-amber-800"
        >
          Belum ada approver — akan tersimpan sebagai Draft.
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="(approver, index) in form.approvers"
            :key="approver.id"
            class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2.5 bg-white"
          >
            <span class="shrink-0 w-7 h-7 rounded-full bg-rose-100 text-rose-700 text-xs font-bold flex items-center justify-center">
              {{ index + 1 }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-gray-800 truncate">{{ approver.nama_lengkap || approver.name }}</div>
              <div class="text-xs text-gray-500 truncate">{{ approver.jabatan || '-' }} · {{ approver.email }}</div>
            </div>
            <button
              type="button"
              class="shrink-0 w-8 h-8 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 transition"
              @click="removeApprover(index)"
            >
              <i class="fa-solid fa-times"></i>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import RichTextEditor from '@/Components/RichTextEditor.vue';
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps({
  form: { type: Object, required: true },
  jobCategories: { type: Array, default: () => [] },
  jobStatuses: { type: Object, default: () => ({}) },
  picUsers: { type: Array, default: () => [] },
});

const inputClass =
  'w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-800 shadow-sm focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-rose-500';

const remarkTab = ref(props.form.job_status || 'on_progress');
const approverSearch = ref('');
const approverResults = ref([]);
let searchTimer = null;

watch(
  () => props.form.job_status,
  (val) => {
    if (val) remarkTab.value = val;
  }
);

const addLink = () => {
  props.form.output_links.push('');
};

const removeLink = (idx) => {
  props.form.output_links.splice(idx, 1);
};

const searchApprovers = () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(async () => {
    const q = approverSearch.value.trim();
    if (q.length < 2) {
      approverResults.value = [];
      return;
    }
    try {
      const { data } = await axios.get('/marcomm-trackers/approvers', { params: { search: q } });
      if (data.success) {
        approverResults.value = data.users || [];
      }
    } catch (e) {
      approverResults.value = [];
    }
  }, 250);
};

const addApprover = (user) => {
  if (!user?.id) return;
  if (props.form.approvers.find((a) => a.id === user.id)) return;
  props.form.approvers.push({
    id: user.id,
    nama_lengkap: user.nama_lengkap,
    name: user.nama_lengkap,
    email: user.email,
    jabatan: user.jabatan,
  });
  approverSearch.value = '';
  approverResults.value = [];
};

const removeApprover = (index) => {
  props.form.approvers.splice(index, 1);
};
</script>
