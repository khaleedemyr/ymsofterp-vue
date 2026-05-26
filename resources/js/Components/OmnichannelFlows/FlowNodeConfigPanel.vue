<template>
  <div v-if="!node" class="flex h-full items-center justify-center p-4 text-center text-sm text-slate-500">
    Klik node di canvas untuk mengatur detail langkah.
  </div>
  <div v-else-if="node.data.nodeType === 'trigger'" class="p-4 text-sm text-slate-600">
    <p class="font-semibold text-slate-800">Pemicu: Pesan masuk</p>
    <p class="mt-2 text-xs">Setiap pesan masuk dari pelanggan dapat memicu flow ini (sesuai prioritas & channel).</p>
  </div>
  <div
    v-else-if="isAssignPicker"
    class="omni-flow-config-panel space-y-3 p-4"
  >
    <p class="text-sm font-semibold text-slate-800">{{ nodeTitle }}</p>

    <div v-if="node.data.nodeType === 'assign_team'">
      <Multiselect
        v-model="node.data.config._teams"
        :options="teams"
        :multiple="true"
        label="name"
        track-by="id"
        placeholder="Pilih tim..."
        open-direction="top"
        :max-height="240"
        class="omni-flow-multiselect text-sm"
      />
    </div>

    <div v-else-if="node.data.nodeType === 'assign_users'">
      <Multiselect
        v-model="node.data.config._users"
        :options="users"
        :custom-label="formatUserLabel"
        :multiple="true"
        label="name"
        track-by="id"
        placeholder="Pilih user..."
        open-direction="top"
        :max-height="240"
        class="omni-flow-multiselect text-sm"
      />
    </div>

    <button
      type="button"
      class="w-full rounded-lg border border-red-200 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
      @click="$emit('remove-node', node.id)"
    >
      Hapus node
    </button>
  </div>

  <div v-else class="omni-flow-config-panel min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
    <p class="text-sm font-semibold text-slate-800">{{ nodeTitle }}</p>

    <div v-if="node.data.nodeType === 'condition'" class="space-y-2">
      <select v-model="node.data.config.match" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
        <option value="all">Semua aturan harus cocok</option>
        <option value="any">Salah satu aturan cocok</option>
      </select>
      <div
        v-for="(rule, ri) in node.data.config.rules"
        :key="ri"
        class="space-y-1 rounded-lg border border-slate-200 bg-slate-50 p-2"
      >
        <select
          v-model="rule.field"
          class="w-full rounded border border-slate-200 px-2 py-1 text-xs"
          @change="onRuleFieldChange(rule)"
        >
          <option v-for="cf in conditionFields" :key="cf.value" :value="cf.value">{{ cf.label }}</option>
        </select>
        <template v-if="rule.field === 'hour_between'">
          <div class="flex flex-wrap items-center gap-2">
            <input
              v-model="rule.from"
              type="time"
              step="60"
              class="min-w-[6.5rem] rounded border border-slate-200 px-2 py-1 text-xs"
            />
            <span class="text-xs text-slate-500">–</span>
            <input
              v-model="rule.to"
              type="time"
              step="60"
              class="min-w-[6.5rem] rounded border border-slate-200 px-2 py-1 text-xs"
            />
            <span class="text-[10px] text-slate-500">WIB</span>
          </div>
          <p class="text-[10px] text-slate-500">Format jam:menit. Rentang melewati tengah malam (mis. 22:00–07:00) didukung.</p>
        </template>
        <template v-else-if="rule.field === 'no_assignee'">
          <span class="text-xs text-slate-500">Belum ada penugasan</span>
        </template>
        <template v-else-if="rule.field === 'has_member'">
          <select v-model="rule.value" class="w-full rounded border px-2 py-1 text-xs">
            <option :value="true">Ya</option>
            <option :value="false">Tidak</option>
          </select>
        </template>
        <template v-else-if="rule.field === 'lead_stage'">
          <select v-model="rule.value" class="w-full rounded border px-2 py-1 text-xs">
            <option v-for="ls in leadStages" :key="ls.value" :value="ls.value">{{ ls.label }}</option>
          </select>
        </template>
        <template v-else>
          <input v-model="rule.value" type="text" class="w-full rounded border px-2 py-1 text-xs" placeholder="nilai" />
        </template>
        <button type="button" class="text-xs text-red-600" @click="node.data.config.rules.splice(ri, 1)">Hapus aturan</button>
      </div>
      <button type="button" class="text-xs font-medium text-emerald-700" @click="addRule">+ Aturan</button>
      <p class="text-[10px] text-slate-500">Cabang <strong>Ya</strong> / <strong>Tidak</strong> di node kondisi.</p>
    </div>

    <div v-else-if="node.data.nodeType === 'send_message'" class="space-y-3">
      <p class="text-[10px] text-slate-500">Variabel: {{nama}}, {{nomor}}, {{nama_depan}} (bisa di teks & URL)</p>

      <div>
        <label class="text-[10px] font-semibold uppercase text-slate-500">Tipe pesan</label>
        <select
          v-model="node.data.config.message_mode"
          class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"
        >
          <option value="text">Teks biasa</option>
          <option value="quick_reply">Teks + tombol balas (maks. 3)</option>
          <option value="cta_url">Teks + tombol buka link</option>
          <option value="image">Gambar (+ caption opsional)</option>
          <option value="document">PDF / dokumen (+ caption opsional)</option>
        </select>
      </div>

      <div
        v-if="node.data.config.message_mode === 'image' || node.data.config.message_mode === 'document'"
        class="space-y-2 rounded-lg border border-violet-200 bg-violet-50/60 p-2"
      >
        <p class="text-[10px] text-violet-900">
          Unggah {{ node.data.config.message_mode === 'image' ? 'gambar (JPG, PNG, WebP, maks. 16 MB)' : 'PDF (maks. 16 MB)' }}.
          Hanya WhatsApp.
        </p>
        <input
          type="file"
          :accept="node.data.config.message_mode === 'image' ? 'image/jpeg,image/png,image/webp,image/gif' : 'application/pdf'"
          class="w-full text-xs"
          :disabled="mediaUploading"
          @change="onFlowMediaSelected"
        />
        <p v-if="mediaUploading" class="text-[10px] text-violet-800">Mengunggah...</p>
        <div v-if="node.data.config.media_filename" class="rounded border border-violet-100 bg-white p-2 text-xs">
          <p class="font-medium text-slate-800">{{ node.data.config.media_filename }}</p>
          <a
            v-if="node.data.config.media_url"
            :href="node.data.config.media_url"
            target="_blank"
            rel="noopener"
            class="text-[10px] text-emerald-700 hover:underline"
          >
            Pratinjau berkas
          </a>
          <button type="button" class="mt-1 block text-[10px] text-red-600" @click="clearFlowMedia">
            Hapus lampiran
          </button>
        </div>
        <img
          v-if="node.data.config.message_mode === 'image' && node.data.config.media_url"
          :src="node.data.config.media_url"
          alt="Pratinjau"
          class="max-h-28 w-full rounded object-contain"
        />
      </div>

      <div v-if="showMessageBodyField" class="relative">
        <label class="text-[10px] font-semibold uppercase text-slate-500">
          {{ isMediaMode ? 'Caption (opsional)' : 'Isi pesan' }}
        </label>
        <textarea
          ref="sendMsgBodyEl"
          v-model="node.data.config.body"
          rows="4"
          class="mt-1 w-full rounded-lg border border-slate-200 py-2 pl-2 pr-10 text-sm"
          :placeholder="isMediaMode ? 'Teks di bawah gambar/PDF (opsional)...' : 'Isi pesan WhatsApp...'"
        />
        <OmniEmojiPickerButton
          class="absolute bottom-2 right-2"
          button-size="sm"
          panel-width="16rem"
          :teleport="true"
          placement="top"
          @select="insertSendMessageEmoji"
        />
      </div>

      <div v-if="node.data.config.message_mode === 'quick_reply'" class="space-y-2 rounded-lg border border-emerald-200 bg-emerald-50/60 p-2">
        <p class="text-[10px] text-emerald-900">
          Tombol balas cepat — pelanggan mengetuk lalu WA mengirim teks tombol (bukan membuka link). Maks. 3 tombol, label maks. 20 karakter.
        </p>
        <div
          v-for="(btn, bi) in node.data.config.buttons"
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
          <input
            v-model="btn.id"
            type="text"
            maxlength="64"
            class="w-full rounded border border-slate-200 px-2 py-1 text-[10px] text-slate-600"
            placeholder="ID internal (opsional, untuk otomasi lanjutan)"
          />
          <button
            v-if="node.data.config.buttons.length > 1"
            type="button"
            class="text-[10px] text-red-600"
            @click="node.data.config.buttons.splice(bi, 1)"
          >
            Hapus tombol
          </button>
        </div>
        <button
          v-if="node.data.config.buttons.length < 3"
          type="button"
          class="text-xs font-medium text-emerald-800"
          @click="addQuickReplyButton"
        >
          + Tambah tombol
        </button>
      </div>

      <div v-else-if="node.data.config.message_mode === 'cta_url'" class="space-y-2 rounded-lg border border-sky-200 bg-sky-50/60 p-2">
        <p class="text-[10px] text-sky-900">Satu tombol yang membuka URL (harus https://). Cocok untuk menu, reservasi, atau form.</p>
        <input
          v-model="node.data.config.cta_url.display_text"
          type="text"
          maxlength="20"
          class="w-full rounded border border-slate-200 px-2 py-1.5 text-sm"
          placeholder="Teks tombol, mis. Buka reservasi"
        />
        <input
          v-model="node.data.config.cta_url.url"
          type="url"
          class="w-full rounded border border-slate-200 px-2 py-1.5 text-sm"
          placeholder="https://..."
        />
      </div>
    </div>

    <div v-else-if="node.data.nodeType === 'set_lead_stage'">
      <select v-model="node.data.config.lead_stage" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
        <option v-for="ls in leadStages" :key="ls.value" :value="ls.value">{{ ls.label }}</option>
      </select>
    </div>

    <div v-else-if="node.data.nodeType === 'append_memo'">
      <textarea v-model="node.data.config.text" rows="3" class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" />
    </div>

    <div v-else-if="node.data.nodeType === 'notify_assignees'">
      <input
        v-model="node.data.config.message"
        type="text"
        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"
        placeholder="Teks notifikasi..."
      />
    </div>

    <button
      v-if="node.data.nodeType !== 'trigger'"
      type="button"
      class="w-full rounded-lg border border-red-200 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
      @click="$emit('remove-node', node.id)"
    >
      Hapus node
    </button>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import OmniEmojiPickerButton from '@/Components/Omnichannel/OmniEmojiPickerButton.vue'
import { insertEmojiIntoTextarea } from '@/utils/omniEmojiPicker.js'
import { ensureHourBetweenRule } from '@/utils/omniFlowTime'
import { nodeTypeLabel } from '@/utils/omniFlowGraph'

const props = defineProps({
  node: { type: Object, default: null },
  teams: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  leadStages: { type: Array, default: () => [] },
  conditionFields: { type: Array, default: () => [] },
})

defineEmits(['remove-node'])

const sendMsgBodyEl = ref(null)
const mediaUploading = ref(false)

const nodeTitle = computed(() => nodeTypeLabel(props.node?.data?.nodeType))

const isMediaMode = computed(() => {
  const m = props.node?.data?.config?.message_mode
  return m === 'image' || m === 'document'
})

const showMessageBodyField = computed(() => true)

function insertSendMessageEmoji(emoji) {
  const config = props.node?.data?.config
  if (!config) return
  const bodyRef = {
    get value() {
      return config.body ?? ''
    },
    set value(v) {
      config.body = v
    },
  }
  insertEmojiIntoTextarea(sendMsgBodyEl.value, bodyRef, emoji)
}

const isAssignPicker = computed(() => {
  const t = props.node?.data?.nodeType
  return t === 'assign_team' || t === 'assign_users'
})

function formatUserLabel(opt) {
  if (!opt) return ''
  const bits = [opt.jabatan, opt.outlet].filter(Boolean)
  return bits.length ? `${opt.name} — ${bits.join(' · ')}` : opt.name
}

function normalizeConditionRules() {
  const rules = props.node?.data?.config?.rules
  if (!Array.isArray(rules)) return
  rules.forEach((rule) => ensureHourBetweenRule(rule))
}

watch(() => props.node?.id, normalizeConditionRules, { immediate: true })

function onRuleFieldChange(rule) {
  if (rule.field === 'hour_between') {
    ensureHourBetweenRule(rule)
  }
}

function addRule() {
  if (!props.node?.data?.config) return
  if (!Array.isArray(props.node.data.config.rules)) {
    props.node.data.config.rules = []
  }
  props.node.data.config.rules.push({ field: 'message_contains', value: '' })
}

function addQuickReplyButton() {
  const cfg = props.node?.data?.config
  if (!cfg) return
  if (!Array.isArray(cfg.buttons)) {
    cfg.buttons = []
  }
  if (cfg.buttons.length >= 3) return
  cfg.buttons.push({ id: `btn_${cfg.buttons.length + 1}`, title: '' })
}

function clearFlowMedia() {
  const cfg = props.node?.data?.config
  if (!cfg) return
  cfg.media_path = ''
  cfg.media_url = ''
  cfg.media_filename = ''
  cfg.media_mime = ''
}

async function onFlowMediaSelected(event) {
  const file = event.target?.files?.[0]
  const cfg = props.node?.data?.config
  if (!file || !cfg) return

  mediaUploading.value = true
  const formData = new FormData()
  formData.append('file', file)

  try {
    const { data } = await axios.post('/crm/omnichannel-flows/upload-media', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    if (!data?.success) {
      throw new Error(data?.message || 'Gagal mengunggah')
    }
    cfg.media_path = data.media_path
    cfg.media_url = data.media_url
    cfg.media_filename = data.media_filename
    cfg.media_mime = data.media_mime
    if (data.media_kind === 'image' && cfg.message_mode === 'document') {
      cfg.message_mode = 'image'
    }
    if (data.media_kind === 'document' && cfg.message_mode === 'image') {
      cfg.message_mode = 'document'
    }
  } catch (err) {
    const msg = err.response?.data?.message || err.message || 'Gagal mengunggah berkas'
    window.alert(msg)
  } finally {
    mediaUploading.value = false
    if (event.target) {
      event.target.value = ''
    }
  }
}

defineExpose({ normalizeConditionRules })
</script>

<style scoped>
.omni-flow-multiselect :deep(.multiselect__tags) {
  min-height: 38px;
  padding-top: 6px;
}
.omni-flow-multiselect :deep(.multiselect__input),
.omni-flow-multiselect :deep(.multiselect__single) {
  font-size: 0.875rem;
}
.omni-flow-config-panel :deep(.multiselect--active) {
  z-index: 60;
}
.omni-flow-config-panel :deep(.multiselect__content-wrapper) {
  z-index: 70;
  max-height: 240px;
}
</style>
