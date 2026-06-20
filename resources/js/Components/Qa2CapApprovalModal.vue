<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import VueEasyLightbox from 'vue-easy-lightbox'

const props = defineProps({
  show: Boolean,
  auditId: [Number, String],
})

const emit = defineEmits(['close', 'processed'])

const loading = ref(false)
const detail = ref(null)
const lightboxVisible = ref(false)
const lightboxIndex = ref(0)
const lightboxItems = ref([])

async function loadDetail(id) {
  if (!id) return
  loading.value = true
  detail.value = null
  try {
    const { data } = await axios.get(`/api/qa2-audits/${id}/cap-approval-details`)
    if (data.success) {
      detail.value = data
    }
  } catch (e) {
    await Swal.fire('Error', e.response?.data?.message || 'Gagal memuat detail CAP', 'error')
    emit('close')
  } finally {
    loading.value = false
  }
}

watch(
  () => [props.show, props.auditId],
  ([show, id]) => {
    if (show && id) {
      loadDetail(id)
    }
  },
)

function openMedia(mediaList, index) {
  lightboxItems.value = (mediaList || []).map((m) => ({
    src: m.url,
    title: m.media_type === 'video' ? 'Video' : 'Foto',
  }))
  lightboxIndex.value = index
  lightboxVisible.value = true
}

async function approve() {
  const { value: note } = await Swal.fire({
    title: 'Approve CAP?',
    input: 'textarea',
    inputLabel: 'Catatan (opsional)',
    showCancelButton: true,
    confirmButtonText: 'Approve',
  })
  if (note === undefined) return

  try {
    const { data } = await axios.post(`/qa2-audits/${props.auditId}/cap-approve`, { note })
    await Swal.fire('Berhasil', data.message, 'success')
    emit('processed')
    emit('close')
  } catch (e) {
    await Swal.fire('Gagal', e.response?.data?.message || 'Approve gagal', 'error')
  }
}

async function reject() {
  const { value: note } = await Swal.fire({
    title: 'Reject CAP?',
    input: 'textarea',
    inputLabel: 'Alasan penolakan',
    inputValidator: (v) => (!v?.trim() ? 'Alasan wajib diisi' : undefined),
    showCancelButton: true,
    confirmButtonText: 'Reject',
    confirmButtonColor: '#dc2626',
  })
  if (!note) return

  try {
    const { data } = await axios.post(`/qa2-audits/${props.auditId}/cap-reject`, { note })
    await Swal.fire('Ditolak', data.message, 'success')
    emit('processed')
    emit('close')
  } catch (e) {
    await Swal.fire('Gagal', e.response?.data?.message || 'Reject gagal', 'error')
  }
}
</script>

<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="emit('close')">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col">
      <div class="p-5 border-b flex justify-between items-start">
        <div>
          <h3 class="text-lg font-bold text-gray-900">Approval CAP QA2</h3>
          <p v-if="detail?.audit" class="text-sm text-gray-600 mt-1">
            {{ detail.audit.audit_number }} — {{ detail.audit.outlet_name }} — {{ detail.audit.template_name }}
          </p>
        </div>
        <button type="button" class="text-gray-400 hover:text-gray-600 text-xl" @click="emit('close')">×</button>
      </div>

      <div class="flex-1 overflow-y-auto p-5">
        <div v-if="loading" class="text-center py-10 text-gray-500">Memuat detail...</div>

        <template v-else-if="detail">
          <div class="grid grid-cols-2 gap-3 mb-4 text-sm">
            <div><span class="text-gray-500">Submitter:</span> {{ detail.audit.submitter_name || '-' }}</div>
            <div><span class="text-gray-500">Submit:</span> {{ detail.audit.cap_submitted_at || '-' }}</div>
          </div>

          <div class="space-y-4">
            <div
              v-for="item in detail.cap_items"
              :key="item.id"
              class="rounded-lg border border-rose-200 bg-rose-50/30 p-4"
            >
              <div class="mb-2">
                <span class="text-xs font-semibold text-gray-500">{{ item.parameter_code }}</span>
                <p class="font-medium text-gray-900">{{ item.parameter_text }}</p>
                <p class="text-xs text-gray-500">{{ item.category_name }} / {{ item.subcategory_name }}</p>
              </div>

              <div class="mb-3 rounded bg-white border p-3 text-sm">
                <p class="text-xs font-semibold text-gray-500 mb-1">Temuan Auditor</p>
                <p class="whitespace-pre-wrap">{{ item.comment || '-' }}</p>
                <p v-if="item.due_date" class="text-xs text-gray-500 mt-1">Due: {{ item.due_date }}</p>
                <div v-if="item.auditor_media?.length" class="mt-2 flex flex-wrap gap-2">
                  <button
                    v-for="(m, mi) in item.auditor_media"
                    :key="'am-'+m.id"
                    type="button"
                    class="w-16 h-16 rounded border overflow-hidden"
                    @click="openMedia(item.auditor_media, mi)"
                  >
                    <img v-if="m.media_type === 'photo' || m.media_type === 'image'" :src="m.url" class="w-full h-full object-cover" alt="">
                    <span v-else class="text-xs p-2">Video</span>
                  </button>
                </div>
              </div>

              <div class="rounded bg-white border p-3 text-sm">
                <p class="text-xs font-semibold text-rose-700 mb-1">Action Plan</p>
                <p class="whitespace-pre-wrap">{{ item.cap?.action_plan || '-' }}</p>
                <div class="mt-2 text-xs text-gray-600 flex gap-4">
                  <span v-if="item.cap?.target_date">Target: {{ item.cap.target_date }}</span>
                  <span v-if="item.cap?.status">Status: {{ item.cap.status }}</span>
                </div>
                <div v-if="item.cap?.media?.length" class="mt-3">
                  <p class="text-xs font-semibold text-rose-700 mb-2">Media CAP</p>
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-for="(m, mi) in item.cap.media"
                      :key="'cm-'+m.id"
                      type="button"
                      class="w-20 h-20 rounded border overflow-hidden"
                      @click="openMedia(item.cap.media, mi)"
                    >
                      <img v-if="m.media_type === 'photo' || m.media_type === 'image'" :src="m.url" class="w-full h-full object-cover" alt="">
                      <span v-else class="text-xs p-2">Video</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>

      <div class="p-4 border-t flex justify-end gap-2">
        <button type="button" class="px-4 py-2 rounded border text-sm" @click="emit('close')">Tutup</button>
        <button type="button" class="px-4 py-2 rounded bg-red-600 text-white text-sm hover:bg-red-700" @click="reject">Reject</button>
        <button type="button" class="px-4 py-2 rounded bg-emerald-600 text-white text-sm hover:bg-emerald-700" @click="approve">Approve</button>
      </div>
    </div>

    <VueEasyLightbox
      :visible="lightboxVisible"
      :imgs="lightboxItems"
      :index="lightboxIndex"
      @hide="lightboxVisible = false"
    />
  </div>
</template>
