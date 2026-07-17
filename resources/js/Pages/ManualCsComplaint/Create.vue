<template>
  <AppLayout>
    <div class="w-full max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Input Complaint CS</h1>
          <p class="text-sm text-gray-500 mt-1">Data dapat langsung disync ke Customer Voice Command Center</p>
        </div>
        <Link :href="route('manual-cs-complaints.index')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700">
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </Link>
      </div>

      <form @submit.prevent="submit" class="bg-white rounded-xl shadow p-6 space-y-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Nama Pelanggan *</label>
            <input v-model="form.author_name" type="text" required class="w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Kontak Pelanggan</label>
            <input v-model="form.customer_contact" type="text" placeholder="No HP / WA" class="w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Email Pelanggan</label>
            <input v-model="form.customer_email" type="email" class="w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Outlet</label>
            <select v-model="form.id_outlet" class="w-full rounded-lg border-gray-300">
              <option value="">Pilih outlet</option>
              <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Channel Input *</label>
            <select v-model="form.input_channel" required class="w-full rounded-lg border-gray-300">
              <option v-for="opt in channelOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tanggal Kejadian *</label>
            <input v-model="form.event_at" type="datetime-local" required class="w-full rounded-lg border-gray-300" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Severity *</label>
            <select v-model="form.severity" required class="w-full rounded-lg border-gray-300">
              <option v-for="opt in severityOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Ringkasan Singkat</label>
            <input v-model="form.summary" type="text" maxlength="500" placeholder="Opsional, max 500 karakter" class="w-full rounded-lg border-gray-300" />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-2">Topik Komplain</label>
          <div class="flex flex-wrap gap-2">
            <label v-for="opt in topicOptions" :key="opt.value" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm">
              <input v-model="form.topics" type="checkbox" :value="opt.value" class="rounded border-gray-300 text-sky-600" />
              <span>{{ opt.label }}</span>
            </label>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Detail Komplain *</label>
          <textarea v-model="form.complaint_text" rows="6" required class="w-full rounded-lg border-gray-300" placeholder="Tuliskan detail komplain dari pelanggan..."></textarea>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan Internal</label>
          <textarea v-model="form.notes" rows="3" class="w-full rounded-lg border-gray-300" placeholder="Catatan tambahan untuk tim CS (opsional)"></textarea>
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
          <input v-model="form.sync_to_cvcc" type="checkbox" class="rounded border-gray-300 text-sky-600" />
          Langsung sync ke CVCC setelah disimpan
        </label>

        <div class="flex justify-end gap-3 pt-2">
          <Link :href="route('manual-cs-complaints.index')" class="px-5 py-2.5 rounded-lg bg-gray-100 hover:bg-gray-200">Batal</Link>
          <button type="submit" :disabled="form.processing" class="px-5 py-2.5 rounded-lg bg-sky-600 text-white hover:bg-sky-700 disabled:opacity-50">Simpan</button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
  outlets: { type: Array, default: () => [] },
  topicOptions: { type: Array, default: () => [] },
  channelOptions: { type: Array, default: () => [] },
  severityOptions: { type: Array, default: () => [] },
  now: { type: String, required: true },
});

const form = useForm({
  id_outlet: '',
  author_name: '',
  customer_contact: '',
  customer_email: '',
  input_channel: 'phone',
  event_at: props.now,
  severity: 'major',
  topics: [],
  summary: '',
  complaint_text: '',
  notes: '',
  sync_to_cvcc: true,
});

function submit() {
  form.transform((data) => ({
    ...data,
    id_outlet: data.id_outlet ? Number(data.id_outlet) : null,
    sync_to_cvcc: Boolean(data.sync_to_cvcc),
  })).post(route('manual-cs-complaints.store'));
}
</script>
