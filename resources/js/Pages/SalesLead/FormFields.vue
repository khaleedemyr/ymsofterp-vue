<template>
  <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/80">
      <h2 class="text-sm font-semibold text-gray-800">Lead Info</h2>
    </div>
    <div class="p-5 space-y-5">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-5 gap-y-4">
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Company Name <span class="text-red-500">*</span></label>
          <input v-model="form.company_name" type="text" required :class="inputClass" placeholder="Nama perusahaan" />
          <p v-if="form.errors?.company_name" class="text-sm text-red-600 mt-1">{{ form.errors.company_name }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Segment 1</label>
          <select v-model="form.segment_1" :class="inputClass">
            <option value="">Pilih segment 1</option>
            <option v-for="s in segment1Options" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Segment 2</label>
          <select v-model="form.segment_2" :class="inputClass">
            <option value="">Pilih segment 2</option>
            <option v-for="s in segment2Options" :key="s" :value="s">{{ s }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Type of Event</label>
          <select v-model="form.type_of_event" :class="inputClass">
            <option value="">Pilih type of event</option>
            <option v-for="t in typeOfEvents" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Date</label>
          <input v-model="form.event_date" type="date" :class="inputClass" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Pax</label>
          <input v-model="form.pax" type="number" min="0" :class="inputClass" placeholder="Jumlah pax" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Outlet</label>
          <select v-model="form.outlet_id" :class="inputClass">
            <option value="">Pilih outlet</option>
            <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Room Requirement</label>
          <select v-model="form.room_requirement" :class="inputClass">
            <option value="">Pilih room requirement</option>
            <option v-for="r in roomRequirements" :key="r" :value="r">{{ r }}</option>
          </select>
          <p v-if="form.errors?.room_requirement" class="text-sm text-red-600 mt-1">{{ form.errors.room_requirement }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Status <span class="text-red-500">*</span></label>
          <select v-model="form.status" required :class="inputClass">
            <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Revenue</label>
          <input v-model="form.revenue" type="number" min="0" step="0.01" :class="inputClass" placeholder="0" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Sales</label>
          <input :value="salesUser?.nama_lengkap || '-'" type="text" readonly :class="inputClass + ' bg-gray-50 text-gray-600'" />
          <p class="text-xs text-gray-500 mt-1">Otomatis: user yang input.</p>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Attachment</label>
        <input
          type="file"
          :class="inputClass + ' file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100'"
          accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.zip"
          @change="onAttachmentChange"
        />
        <p class="text-xs text-gray-500 mt-1">PDF, Office, gambar, atau ZIP. Maks. 10 MB.</p>
        <p v-if="form.errors?.attachment" class="text-sm text-red-600 mt-1">{{ form.errors.attachment }}</p>

        <div
          v-if="existingAttachmentUrl && !form.remove_attachment && !form.attachment"
          class="mt-2 flex flex-wrap items-center gap-3 text-sm"
        >
          <a
            :href="existingAttachmentUrl"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-1.5 text-emerald-700 hover:text-emerald-900"
          >
            <i class="fa-solid fa-paperclip"></i>
            {{ existingAttachmentName || 'File terlampir' }}
          </a>
          <label class="inline-flex items-center gap-1.5 text-red-600 cursor-pointer">
            <input v-model="form.remove_attachment" type="checkbox" class="rounded border-gray-300 text-red-600 focus:ring-red-500" />
            Hapus file
          </label>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1.5">Remarks</label>
        <textarea v-model="form.remarks" rows="4" :class="inputClass" placeholder="Catatan..."></textarea>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  form: { type: Object, required: true },
  segment1Options: { type: Array, default: () => [] },
  segment2Options: { type: Array, default: () => [] },
  typeOfEvents: { type: Array, default: () => [] },
  roomRequirements: { type: Array, default: () => [] },
  statuses: { type: Object, default: () => ({}) },
  outlets: { type: Array, default: () => [] },
  salesUser: { type: Object, default: () => ({}) },
  existingAttachmentUrl: { type: String, default: null },
  existingAttachmentName: { type: String, default: null },
});

const inputClass =
  'w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-800 shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500';

const onAttachmentChange = (event) => {
  const file = event.target.files?.[0] || null;
  props.form.attachment = file;
  if (file) {
    props.form.remove_attachment = false;
  }
};
</script>
