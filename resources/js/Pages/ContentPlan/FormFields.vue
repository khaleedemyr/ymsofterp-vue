<template>
  <div class="space-y-5">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80">
        <h2 class="text-sm font-semibold text-gray-800">Flow 1 · Input Pemberi Tugas</h2>
        <p class="text-xs text-gray-500 mt-0.5">Field PIC (penerima tugas) menyusul di flow berikutnya.</p>
      </div>
      <div class="p-5 space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-5 gap-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nomor TP</label>
            <input :value="meta.number || meta.number_preview || 'Auto'" type="text" readonly :class="inputClass + ' bg-gray-50 text-gray-600'" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Created Date</label>
            <input :value="meta.created_at || '-'" type="text" readonly :class="inputClass + ' bg-gray-50 text-gray-600'" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Created By</label>
            <input :value="meta.created_by_name || '-'" type="text" readonly :class="inputClass + ' bg-gray-50 text-gray-600'" />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">PIC <span class="text-red-500">*</span></label>
            <select v-model="form.pic_user_id" required :class="inputClass">
              <option value="" disabled>Pilih PIC</option>
              <option v-for="u in picUsers" :key="u.id" :value="u.id">{{ u.nama_lengkap }}</option>
            </select>
            <p v-if="form.errors?.pic_user_id" class="text-sm text-red-600 mt-1">{{ form.errors.pic_user_id }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Brand <span class="text-red-500">*</span></label>
            <select v-model="form.brand_id" required :class="inputClass">
              <option value="" disabled>Pilih brand</option>
              <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.brand }}</option>
            </select>
            <p v-if="form.errors?.brand_id" class="text-sm text-red-600 mt-1">{{ form.errors.brand_id }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Platform <span class="text-red-500">*</span></label>
            <select v-model="form.platform" required :class="inputClass">
              <option value="" disabled>Pilih platform</option>
              <option v-for="p in platforms" :key="p" :value="p">{{ p }}</option>
            </select>
            <p v-if="form.errors?.platform" class="text-sm text-red-600 mt-1">{{ form.errors.platform }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Deadline Content</label>
            <input v-model="form.deadline_content" type="date" :class="inputClass" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Date Post</label>
            <input v-model="form.date_post" type="date" :class="inputClass" />
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
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Objective</label>
          <textarea v-model="form.objective" rows="3" :class="inputClass" placeholder="Objective..."></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Brief</label>
          <textarea v-model="form.brief" rows="4" :class="inputClass" placeholder="Brief..."></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Reference Link</label>
          <input v-model="form.reference" type="text" :class="inputClass" placeholder="https://... atau catatan referensi" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Reference Image</label>
          <div v-if="existingImages.length" class="flex flex-wrap gap-3 mb-3">
            <div
              v-for="(img, idx) in existingImages"
              :key="'ref-img-'+idx"
              class="relative w-24 h-24 rounded-lg overflow-hidden border border-gray-200 bg-gray-50"
            >
              <img :src="storageUrl(img)" :alt="'Reference '+ (idx + 1)" class="w-full h-full object-cover" />
              <button
                type="button"
                class="absolute top-1 right-1 w-6 h-6 rounded-full bg-red-600 text-white text-xs hover:bg-red-700"
                title="Hapus"
                @click="removeExistingImage(idx)"
              >
                <i class="fa-solid fa-times"></i>
              </button>
            </div>
          </div>
          <input
            type="file"
            accept="image/*"
            multiple
            class="block w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100"
            @change="onReferenceImagesChange"
          />
          <p v-if="pendingImageNames.length" class="text-xs text-gray-500 mt-1">
            File baru: {{ pendingImageNames.join(', ') }}
          </p>
          <p v-if="form.errors?.reference_images" class="text-sm text-red-600 mt-1">{{ form.errors.reference_images }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Caption</label>
          <textarea v-model="form.caption" rows="4" :class="inputClass" placeholder="Caption..."></textarea>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Prioritas <span class="text-red-500">*</span></label>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="(label, key) in priorities"
              :key="key"
              type="button"
              class="px-3.5 py-2 rounded-lg text-sm font-medium border transition"
              :class="form.priority === key ? priorityActiveClass(key) : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
              @click="form.priority = key"
            >
              {{ label }}
            </button>
          </div>
          <p v-if="form.errors?.priority" class="text-sm text-red-600 mt-1">{{ form.errors.priority }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Remarks</label>
          <textarea v-model="form.remarks" rows="3" :class="inputClass" placeholder="Catatan umum..."></textarea>
        </div>

        <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
          <div class="font-medium mb-1">Approval</div>
          <p class="text-xs text-slate-500 mb-2">
            Status otomatis: tanpa approver = Draft · ada approver = <span class="font-semibold">SUBMITTED</span> · semua approve = <span class="font-semibold">APPROVED</span>.
          </p>
          <div class="flex flex-wrap gap-2">
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">SUBMITTED</span>
            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">APPROVED</span>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-visible">
      <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80 rounded-t-xl">
        <h2 class="text-sm font-semibold text-gray-800">Approval Flow</h2>
        <p class="text-xs text-gray-500 mt-0.5">Level terendah → tertinggi. Ada approver = Submitted; semua approve = Approved.</p>
      </div>
      <div class="p-5 overflow-visible">
        <div class="relative mb-4 z-30">
          <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
          <input
            v-model="approverSearch"
            type="text"
            placeholder="Cari nama / jabatan approver..."
            class="w-full pl-9 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500"
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
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
  form: { type: Object, required: true },
  brands: { type: Array, default: () => [] },
  picUsers: { type: Array, default: () => [] },
  platforms: { type: Array, default: () => [] },
  funnels: { type: Array, default: () => [] },
  pillars: { type: Array, default: () => [] },
  formats: { type: Array, default: () => [] },
  priorities: { type: Object, default: () => ({}) },
  meta: { type: Object, default: () => ({}) },
});

const inputClass =
  'w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-800 shadow-sm focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-violet-500';

const existingImages = computed(() => props.form.existing_reference_images || []);
const pendingImageNames = computed(() =>
  (props.form.reference_images || []).map((f) => f?.name || 'image').filter(Boolean)
);

const storageUrl = (path) => {
  if (!path) return '';
  if (String(path).startsWith('http') || String(path).startsWith('/')) return path;
  return `/storage/${path}`;
};

const onReferenceImagesChange = (event) => {
  const files = Array.from(event.target.files || []);
  props.form.reference_images = files;
};

const removeExistingImage = (idx) => {
  props.form.existing_reference_images.splice(idx, 1);
};

const priorityActiveClass = (key) => {
  const map = {
    low: 'bg-slate-700 border-slate-700 text-white',
    medium: 'bg-blue-600 border-blue-600 text-white',
    high: 'bg-amber-500 border-amber-500 text-white',
    urgent: 'bg-red-600 border-red-600 text-white',
  };
  return map[key] || 'bg-violet-600 border-violet-600 text-white';
};

const approverSearch = ref('');
const approverResults = ref([]);
let searchTimer = null;

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
