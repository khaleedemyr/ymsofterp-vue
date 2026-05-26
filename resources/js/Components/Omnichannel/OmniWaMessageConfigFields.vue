<template>
  <div class="space-y-3">
    <p class="text-[10px] text-slate-500">
      Tombol & lampiran hanya berlaku saat chat WhatsApp. Placeholder: {{nama}}, {{nomor}}, {{nama_depan}}.
    </p>

    <div>
      <label class="text-[10px] font-semibold uppercase text-slate-500">Tipe pesan</label>
      <select
        :value="messageMode"
        class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"
        @change="$emit('update:messageMode', $event.target.value)"
      >
        <option value="text">Teks biasa</option>
        <option value="quick_reply">Teks + tombol balas (maks. 3)</option>
        <option value="cta_url">Teks + tombol buka link</option>
        <option value="image">Gambar (+ caption opsional)</option>
        <option value="document">PDF / dokumen (+ caption opsional)</option>
      </select>
    </div>

    <div
      v-if="messageMode === 'image' || messageMode === 'document'"
      class="space-y-2 rounded-lg border border-violet-200 bg-violet-50/60 p-2"
    >
      <p class="text-[10px] text-violet-900">
        Unggah {{ messageMode === 'image' ? 'gambar (JPG, PNG, WebP, maks. 16 MB)' : 'PDF (maks. 16 MB)' }}.
      </p>
      <input
        type="file"
        :accept="messageMode === 'image' ? 'image/jpeg,image/png,image/webp,image/gif' : 'application/pdf'"
        class="w-full text-xs"
        :disabled="mediaUploading"
        @change="onMediaSelected"
      />
      <p v-if="mediaUploading" class="text-[10px] text-violet-800">Mengunggah...</p>
      <div v-if="config.media_filename" class="rounded border border-violet-100 bg-white p-2 text-xs">
        <p class="font-medium text-slate-800">{{ config.media_filename }}</p>
        <a
          v-if="config.media_url"
          :href="config.media_url"
          target="_blank"
          rel="noopener"
          class="text-[10px] text-emerald-700 hover:underline"
        >
          Pratinjau berkas
        </a>
        <button type="button" class="mt-1 block text-[10px] text-red-600" @click="clearMedia">
          Hapus lampiran
        </button>
      </div>
      <img
        v-if="messageMode === 'image' && config.media_url"
        :src="config.media_url"
        alt="Pratinjau"
        class="max-h-28 w-full rounded object-contain"
      />
    </div>

    <div v-if="messageMode === 'quick_reply'" class="space-y-2 rounded-lg border border-emerald-200 bg-emerald-50/60 p-2">
      <p class="text-[10px] text-emerald-900">Tombol balas cepat (maks. 3, label maks. 20 karakter).</p>
      <div
        v-for="(btn, bi) in config.buttons"
        :key="bi"
        class="space-y-1 rounded border border-emerald-100 bg-white p-2"
      >
        <input
          v-model="btn.title"
          type="text"
          maxlength="20"
          class="w-full rounded border border-slate-200 px-2 py-1 text-xs"
          :placeholder="`Label tombol ${bi + 1}`"
        />
        <button
          v-if="config.buttons.length > 1"
          type="button"
          class="text-[10px] text-red-600"
          @click="config.buttons.splice(bi, 1)"
        >
          Hapus tombol
        </button>
      </div>
      <button
        v-if="config.buttons.length < 3"
        type="button"
        class="text-xs font-medium text-emerald-800"
        @click="addButton"
      >
        + Tambah tombol
      </button>
    </div>

    <div v-else-if="messageMode === 'cta_url'" class="space-y-2 rounded-lg border border-sky-200 bg-sky-50/60 p-2">
      <p class="text-[10px] text-sky-900">Satu tombol yang membuka URL (harus https://).</p>
      <input
        v-model="config.cta_url.display_text"
        type="text"
        maxlength="20"
        class="w-full rounded border border-slate-200 px-2 py-1.5 text-sm"
        placeholder="Teks tombol, mis. Reservasi"
      />
      <input
        v-model="config.cta_url.url"
        type="url"
        class="w-full rounded border border-slate-200 px-2 py-1.5 text-sm"
        placeholder="https://..."
      />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'

const props = defineProps({
  messageMode: { type: String, default: 'text' },
  config: { type: Object, required: true },
  uploadUrl: {
    type: String,
    default: '/crm/omnichannel-teams/message-templates/upload-media',
  },
})

const emit = defineEmits(['update:messageMode'])

const mediaUploading = ref(false)

function ensureConfigShape() {
  if (!Array.isArray(props.config.buttons)) {
    props.config.buttons = [{ id: 'btn_1', title: '' }]
  }
  if (!props.config.cta_url || typeof props.config.cta_url !== 'object') {
    props.config.cta_url = { display_text: 'Buka link', url: '' }
  }
}

ensureConfigShape()

function addButton() {
  ensureConfigShape()
  if (props.config.buttons.length >= 3) return
  props.config.buttons.push({ id: `btn_${props.config.buttons.length + 1}`, title: '' })
}

function clearMedia() {
  props.config.media_path = ''
  props.config.media_url = ''
  props.config.media_filename = ''
  props.config.media_mime = ''
}

async function onMediaSelected(event) {
  const file = event.target?.files?.[0]
  if (!file) return

  mediaUploading.value = true
  const formData = new FormData()
  formData.append('file', file)

  try {
    const { data } = await axios.post(props.uploadUrl, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    if (!data?.success) {
      throw new Error(data?.message || 'Gagal mengunggah')
    }
    props.config.media_path = data.media_path
    props.config.media_url = data.media_url
    props.config.media_filename = data.media_filename
    props.config.media_mime = data.media_mime
    if (data.media_kind === 'image' && props.messageMode === 'document') {
      emit('update:messageMode', 'image')
    }
    if (data.media_kind === 'document' && props.messageMode === 'image') {
      emit('update:messageMode', 'document')
    }
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Gagal mengunggah berkas'
    Swal.fire('Gagal', msg, 'error')
  } finally {
    mediaUploading.value = false
    if (event.target) {
      event.target.value = ''
    }
  }
}

</script>
