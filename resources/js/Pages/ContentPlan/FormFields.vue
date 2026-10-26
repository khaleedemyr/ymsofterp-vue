<template>
  <div class="space-y-5">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80">
        <h2 class="text-sm font-semibold text-gray-800">Content Info</h2>
      </div>
      <div class="p-5 space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Platform <span class="text-red-500">*</span></label>
            <select v-model="form.platform" required :class="inputClass">
              <option value="" disabled>Pilih platform</option>
              <option v-for="p in platforms" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Date Post</label>
            <input v-model="form.date_post" type="date" :class="inputClass" @change="syncDay" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Day</label>
            <input :value="dayLabel" type="text" readonly :class="inputClass + ' bg-gray-50 text-gray-600'" placeholder="Otomatis dari Date Post" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Funnel</label>
            <select v-model="form.funnel" :class="inputClass">
              <option value="">Pilih funnel</option>
              <option v-for="f in funnels" :key="f" :value="f">{{ f }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Pillar</label>
            <select v-model="form.pillar" :class="inputClass">
              <option value="">Pilih pillar</option>
              <option v-for="p in pillars" :key="p" :value="p">{{ p }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Format</label>
            <select v-model="form.format" :class="inputClass">
              <option value="">Pilih format</option>
              <option v-for="f in formats" :key="f" :value="f">{{ f }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Status <span class="text-red-500">*</span></label>
            <select v-model="form.job_status" required :class="inputClass">
              <option v-for="(label, key) in jobStatuses" :key="key" :value="key">{{ label }}</option>
            </select>
            <p class="text-xs text-gray-500 mt-1">Submitted / Approved otomatis dari approval flow.</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Actual Post Date</label>
            <input v-model="form.actual_post_date" type="date" :class="inputClass" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Objective</label>
          <RichTextEditor v-model="form.objective" min-height="120px" placeholder="Objective..." />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Brief</label>
          <RichTextEditor v-model="form.brief" min-height="120px" placeholder="Brief..." />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Reference</label>
          <RichTextEditor v-model="form.reference" min-height="120px" placeholder="Reference..." />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Caption</label>
          <textarea v-model="form.caption" rows="4" :class="inputClass" placeholder="Caption..."></textarea>
        </div>

        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-sm font-medium text-gray-700">Final Output</label>
            <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-violet-600 hover:text-violet-700" @click="addLink('final_output_links')">
              <i class="fa-solid fa-plus text-xs"></i> Tambah
            </button>
          </div>
          <div class="space-y-2">
            <div v-for="(_, idx) in form.final_output_links" :key="'fo-'+idx" class="flex gap-2 items-center">
              <input v-model="form.final_output_links[idx]" type="url" placeholder="https://..." :class="inputClass" />
              <button type="button" class="shrink-0 w-10 h-10 rounded-lg border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50" @click="removeLink('final_output_links', idx)">
                <i class="fa-solid fa-trash text-sm"></i>
              </button>
            </div>
          </div>
        </div>

        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-sm font-medium text-gray-700">Actual Post Link</label>
            <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-violet-600 hover:text-violet-700" @click="addLink('actual_post_links')">
              <i class="fa-solid fa-plus text-xs"></i> Tambah
            </button>
          </div>
          <div class="space-y-2">
            <div v-for="(_, idx) in form.actual_post_links" :key="'ap-'+idx" class="flex gap-2 items-center">
              <input v-model="form.actual_post_links[idx]" type="url" placeholder="https://..." :class="inputClass" />
              <button type="button" class="shrink-0 w-10 h-10 rounded-lg border border-gray-200 text-gray-400 hover:text-red-600 hover:border-red-200 hover:bg-red-50" @click="removeLink('actual_post_links', idx)">
                <i class="fa-solid fa-trash text-sm"></i>
              </button>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Remarks</label>
          <textarea v-model="form.remarks" rows="3" :class="inputClass" placeholder="Catatan umum..."></textarea>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80">
        <h2 class="text-sm font-semibold text-gray-800">Approval Flow</h2>
        <p class="text-xs text-gray-500 mt-0.5">Level terendah → tertinggi. Ada approver = Submitted; semua approve = Approved.</p>
      </div>
      <div class="p-5">
        <div class="relative mb-4">
          <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
          <input
            v-model="approverSearch"
            type="text"
            placeholder="Cari nama / jabatan approver..."
            class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
            @input="searchApprovers"
          />
          <div v-if="approverResults.length > 0" class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-52 overflow-y-auto">
            <button
              v-for="user in approverResults"
              :key="user.id"
              type="button"
              class="w-full text-left px-4 py-2.5 hover:bg-violet-50 text-sm border-b border-gray-100 last:border-0"
              @click="addApprover(user)"
            >
              <div class="font-medium text-gray-800">{{ user.nama_lengkap }}</div>
              <div class="text-xs text-gray-500">{{ user.jabatan || '-' }} · {{ user.email }}</div>
            </button>
          </div>
        </div>

        <div v-if="form.approvers.length === 0" class="rounded-lg border border-dashed border-amber-300 bg-amber-50/60 px-4 py-3 text-sm text-amber-800">
          Belum ada approver — akan tersimpan sebagai Draft.
        </div>
        <div v-else class="space-y-2">
          <div v-for="(approver, index) in form.approvers" :key="approver.id" class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2.5 bg-white">
            <span class="shrink-0 w-7 h-7 rounded-full bg-violet-100 text-violet-700 text-xs font-bold flex items-center justify-center">{{ index + 1 }}</span>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium text-gray-800 truncate">{{ approver.nama_lengkap || approver.name }}</div>
              <div class="text-xs text-gray-500 truncate">{{ approver.jabatan || '-' }} · {{ approver.email }}</div>
            </div>
            <button type="button" class="shrink-0 w-8 h-8 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50" @click="removeApprover(index)">
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
import { computed, ref } from 'vue';

const props = defineProps({
  form: { type: Object, required: true },
  platforms: { type: Array, default: () => [] },
  funnels: { type: Array, default: () => [] },
  pillars: { type: Array, default: () => [] },
  formats: { type: Array, default: () => [] },
  jobStatuses: { type: Object, default: () => ({}) },
});

const inputClass =
  'w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-800 shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500';

const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const dayLabel = computed(() => {
  if (!props.form.date_post) return '';
  const d = new Date(`${props.form.date_post}T00:00:00`);
  if (Number.isNaN(d.getTime())) return '';
  return dayNames[d.getDay()];
});

const syncDay = () => {
  // day is computed server-side on save; UI shows via dayLabel
};

const approverSearch = ref('');
const approverResults = ref([]);
let searchTimer = null;

const addLink = (field) => {
  props.form[field].push('');
};

const removeLink = (field, idx) => {
  props.form[field].splice(idx, 1);
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
      const { data } = await axios.get('/content-plans/approvers', { params: { search: q } });
      if (data.success) approverResults.value = data.users || [];
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
