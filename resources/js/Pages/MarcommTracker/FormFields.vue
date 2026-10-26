<template>
  <div class="space-y-6">
    <div class="bg-white rounded-xl shadow p-6">
      <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-4">Job Info</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Job Category *</label>
          <select v-model="form.job_category" required class="w-full rounded-lg border-gray-300">
            <option value="" disabled>Pilih category</option>
            <option v-for="c in jobCategories" :key="c" :value="c">{{ c }}</option>
          </select>
          <p v-if="form.errors?.job_category" class="text-sm text-red-600 mt-1">{{ form.errors.job_category }}</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">PIC *</label>
          <select v-model="form.pic_user_id" required class="w-full rounded-lg border-gray-300">
            <option value="" disabled>Pilih PIC (divisi 7 & 17)</option>
            <option v-for="u in picUsers" :key="u.id" :value="u.id">
              {{ u.nama_lengkap }} (div {{ u.division_id }})
            </option>
          </select>
          <p v-if="form.errors?.pic_user_id" class="text-sm text-red-600 mt-1">{{ form.errors.pic_user_id }}</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Due Date</label>
          <input v-model="form.due_date" type="date" class="w-full rounded-lg border-gray-300" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Status *</label>
          <select v-model="form.job_status" required class="w-full rounded-lg border-gray-300">
            <option v-for="(label, key) in jobStatuses" :key="key" :value="key">{{ label }}</option>
          </select>
          <p class="text-xs text-gray-500 mt-1">Submitted / Approved otomatis dari approval flow.</p>
        </div>
      </div>

      <div class="mt-4">
        <label class="block text-xs font-semibold text-gray-600 mb-1">
          Remark Status — {{ jobStatuses[form.job_status] || form.job_status }}
        </label>
        <textarea
          v-model="form.status_remarks[form.job_status]"
          rows="3"
          class="w-full rounded-lg border-gray-300"
          :placeholder="`Remark untuk ${jobStatuses[form.job_status] || form.job_status}`"
        ></textarea>
        <div class="mt-2 flex flex-wrap gap-2 text-xs text-gray-500">
          <span
            v-for="(label, key) in jobStatuses"
            :key="key"
            class="px-2 py-0.5 rounded-full border"
            :class="key === form.job_status ? 'border-pink-400 bg-pink-50 text-pink-700' : 'border-gray-200'"
          >
            {{ label }}: {{ form.status_remarks[key] ? 'ada remark' : 'kosong' }}
          </span>
        </div>
      </div>

      <div class="mt-4">
        <label class="block text-xs font-semibold text-gray-600 mb-1">Task</label>
        <RichTextEditor v-model="form.task" min-height="160px" placeholder="Deskripsi task..." />
      </div>

      <div class="mt-4">
        <div class="flex items-center justify-between mb-2">
          <label class="block text-xs font-semibold text-gray-600">Output Link</label>
          <button
            type="button"
            class="text-xs px-2 py-1 rounded bg-pink-100 text-pink-700 hover:bg-pink-200"
            @click="addLink"
          >
            <i class="fa-solid fa-plus mr-1"></i>Tambah Link
          </button>
        </div>
        <div v-if="!form.output_links.length" class="text-sm text-gray-400">Belum ada link. Klik Tambah Link.</div>
        <div v-for="(link, idx) in form.output_links" :key="idx" class="flex gap-2 mb-2">
          <input
            v-model="form.output_links[idx]"
            type="url"
            placeholder="https://..."
            class="flex-1 rounded-lg border-gray-300"
          />
          <button type="button" class="px-3 py-2 rounded-lg bg-red-100 text-red-700" @click="removeLink(idx)">
            <i class="fa-solid fa-trash"></i>
          </button>
        </div>
      </div>

      <div class="mt-4">
        <label class="block text-xs font-semibold text-gray-600 mb-1">Remarks</label>
        <textarea v-model="form.remarks" rows="3" class="w-full rounded-lg border-gray-300" placeholder="Catatan umum..."></textarea>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow p-6">
      <h2 class="text-lg font-semibold text-gray-800 mb-1">Approval Flow</h2>
      <p class="text-sm text-gray-500 mb-4">
        Tambahkan approver dari level terendah ke tertinggi. Setelah ada approver, status workflow otomatis
        <strong>Submitted</strong>. Setelah semua approve → <strong>Approved</strong>.
      </p>

      <input
        v-model="approverSearch"
        type="text"
        placeholder="Cari nama / jabatan approver..."
        class="w-full rounded-lg border-gray-300 mb-4"
        @input="searchApprovers"
      />

      <div v-if="approverResults.length > 0" class="border rounded-lg mb-4 max-h-48 overflow-y-auto divide-y">
        <button
          v-for="user in approverResults"
          :key="user.id"
          type="button"
          class="w-full text-left px-4 py-2 hover:bg-pink-50 text-sm"
          @click="addApprover(user)"
        >
          <div class="font-medium text-gray-800">{{ user.nama_lengkap }}</div>
          <div class="text-xs text-gray-500">{{ user.jabatan || '-' }} · {{ user.email }}</div>
        </button>
      </div>

      <div
        v-if="form.approvers.length === 0"
        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-3"
      >
        Belum ada approver — tracker akan tersimpan sebagai Draft.
      </div>

      <div v-else class="space-y-2">
        <div
          v-for="(approver, index) in form.approvers"
          :key="approver.id"
          class="flex items-center justify-between rounded-lg border px-4 py-2 bg-gray-50"
        >
          <div>
            <div class="text-sm font-medium text-gray-800">
              Level {{ index + 1 }} · {{ approver.nama_lengkap || approver.name }}
            </div>
            <div class="text-xs text-gray-500">{{ approver.jabatan || '-' }} · {{ approver.email }}</div>
          </div>
          <button type="button" class="text-red-600 hover:text-red-800" @click="removeApprover(index)">
            <i class="fa-solid fa-times"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import RichTextEditor from '@/Components/RichTextEditor.vue';
import axios from 'axios';
import { ref } from 'vue';

const props = defineProps({
  form: { type: Object, required: true },
  jobCategories: { type: Array, default: () => [] },
  jobStatuses: { type: Object, default: () => ({}) },
  picUsers: { type: Array, default: () => [] },
});

const approverSearch = ref('');
const approverResults = ref([]);
let searchTimer = null;

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
