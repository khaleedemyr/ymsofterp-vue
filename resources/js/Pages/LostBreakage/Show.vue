<template>
  <AppLayout>
    <div class="max-w-5xl mx-auto py-8 px-2">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold flex items-center gap-2">
          <i class="fa-solid fa-box-open text-orange-500"></i> Detail Lost &amp; Breakage
        </h1>
        <button @click="goBack" class="btn btn-ghost px-4 py-2"><i class="fa fa-arrow-left mr-1"></i> Kembali</button>
      </div>

      <!-- Header Info -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <div class="mb-3"><span class="text-xs font-bold text-gray-500 uppercase">Nomor</span><div class="text-lg font-semibold" :class="header.number?.startsWith('DRAFT-') ? 'text-orange-600' : 'text-blue-600'">{{ header.number || '-' }}</div></div>
            <div class="mb-3"><span class="text-xs font-bold text-gray-500 uppercase">Tanggal</span><div>{{ formatDate(header.date) }}</div></div>
            <div class="mb-3"><span class="text-xs font-bold text-gray-500 uppercase">Outlet</span><div>{{ header.outlet_name || '-' }}</div></div>
          </div>
          <div>
            <div class="mb-3"><span class="text-xs font-bold text-gray-500 uppercase">Status</span><div><span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium" :class="statusBadge(header.status)">{{ statusLabel(header.status) }}</span></div></div>
            <div class="mb-3"><span class="text-xs font-bold text-gray-500 uppercase">Dibuat oleh</span><div>{{ header.creator_name || '-' }}</div></div>
            <div class="mb-3"><span class="text-xs font-bold text-gray-500 uppercase">Catatan</span><div>{{ header.notes || '-' }}</div></div>
          </div>
        </div>
      </div>

      <!-- Items -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="font-bold text-gray-800 mb-4"><i class="fa fa-list mr-2 text-orange-500"></i>Detail Item &amp; Penggantian</h2>
        <p v-if="header.status === 'APPROVED' && !canRecordReplacements" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-4">
          Tabel penggantian belum tersedia di database. Jalankan <code class="text-xs bg-white px-1 rounded">database/sql/lost_breakage_replacements.sql</code> untuk mengaktifkan pencatatan penggantian.
        </p>
        <div class="overflow-x-auto">
          <table class="w-full border text-sm">
            <thead>
              <tr class="bg-gray-100">
                <th class="px-3 py-2 border text-left">#</th>
                <th class="px-3 py-2 border text-left">Item</th>
                <th class="px-3 py-2 border text-center">Tipe</th>
                <th class="px-3 py-2 border text-right">Qty L/B</th>
                <th class="px-3 py-2 border text-left">Unit</th>
                <th class="px-3 py-2 border text-right">Sudah ganti</th>
                <th class="px-3 py-2 border text-right">Sisa</th>
                <th class="px-3 py-2 border text-center">Status</th>
                <th class="px-3 py-2 border text-left">Keterangan</th>
                <th class="px-3 py-2 border text-center">Foto</th>
                <th v-if="canRecordReplacements" class="px-3 py-2 border text-center">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="(d, i) in details" :key="d.id">
                <tr class="hover:bg-gray-50 align-top">
                  <td class="px-3 py-2 border">{{ i + 1 }}</td>
                  <td class="px-3 py-2 border font-medium">{{ d.item_name }}</td>
                  <td class="px-3 py-2 border text-center">
                    <span :class="d.type === 'breakage' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'" class="px-2 py-0.5 rounded-full text-xs font-semibold capitalize">{{ d.type || 'lost' }}</span>
                  </td>
                  <td class="px-3 py-2 border text-right">{{ formatNumber(d.qty) }}</td>
                  <td class="px-3 py-2 border">{{ d.unit_name }}</td>
                  <td class="px-3 py-2 border text-right">{{ formatNumber(d.replaced_qty_total) }}</td>
                  <td class="px-3 py-2 border text-right font-medium" :class="d.remaining_qty > 0 ? 'text-amber-700' : 'text-green-700'">{{ formatNumber(d.remaining_qty) }}</td>
                  <td class="px-3 py-2 border text-center">
                    <span class="px-2 py-0.5 rounded text-xs font-semibold" :class="fulfillmentBadge(d.replacement_fulfillment)">{{ fulfillmentLabel(d.replacement_fulfillment) }}</span>
                  </td>
                  <td class="px-3 py-2 border max-w-[140px]">{{ d.note || '-' }}</td>
                  <td class="px-3 py-2 border text-center">
                    <a v-if="d.photo" :href="`/storage/${d.photo}`" target="_blank">
                      <img :src="`/storage/${d.photo}`" class="w-12 h-12 object-cover rounded border inline-block" />
                    </a>
                    <span v-else class="text-gray-400">-</span>
                  </td>
                  <td v-if="canRecordReplacements" class="px-3 py-2 border text-center whitespace-nowrap">
                    <button
                      v-if="Number(d.remaining_qty) > 0"
                      type="button"
                      class="text-xs font-semibold text-white bg-orange-500 hover:bg-orange-600 px-2 py-1 rounded-lg"
                      @click="openRepModal(d)"
                    >
                      + Ganti
                    </button>
                    <span v-else class="text-xs text-gray-400">—</span>
                  </td>
                </tr>
                <tr v-if="d.replacements && d.replacements.length" class="bg-slate-50/80">
                  <td :colspan="canRecordReplacements ? 11 : 10" class="px-3 py-2 border-t-0 border text-xs text-gray-600">
                    <div class="font-semibold text-gray-700 mb-1"><i class="fa fa-rotate mr-1 text-orange-500"></i>Riwayat penggantian</div>
                    <ul class="space-y-1 pl-1">
                      <li v-for="r in d.replacements" :key="r.id" class="flex flex-wrap gap-x-3 gap-y-0.5 border-b border-slate-100 last:border-0 pb-1 last:pb-0">
                        <span>{{ formatDateTime(r.created_at) }}</span>
                        <span class="font-medium text-gray-800">{{ formatNumber(r.qty_replaced) }} {{ r.replacement_unit_name || d.unit_name }}</span>
                        <span>
                          <template v-if="r.replacement_item_id">→ {{ r.replacement_item_name }} <span v-if="r.replacement_item_sku" class="text-gray-400">({{ r.replacement_item_sku }})</span></template>
                          <template v-else>→ <em>identik</em> ({{ d.item_name }})</template>
                        </span>
                        <span class="text-gray-500">oleh {{ r.replaced_by_name }}</span>
                        <span v-if="r.note" class="text-gray-500 italic w-full">“{{ r.note }}”</span>
                      </li>
                    </ul>
                  </td>
                </tr>
              </template>
              <tr v-if="!details || details.length === 0">
                <td :colspan="canRecordReplacements ? 11 : 10" class="text-center py-6 text-gray-400">Tidak ada item.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Approval Flow -->
      <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="font-bold text-gray-800 mb-4"><i class="fa fa-check-double mr-2 text-orange-500"></i>Approval Flow</h2>
        <div v-if="approvalFlows && approvalFlows.length > 0" class="space-y-3">
          <div v-for="f in approvalFlows" :key="f.id" class="flex items-center justify-between p-4 rounded-lg border" :class="flowBorderClass(f.status)">
            <div class="flex items-center gap-3">
              <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" :class="flowBadge(f.status)">Level {{ f.approval_level }}</span>
              <div>
                <div class="font-medium">{{ f.approver_name }}</div>
                <div class="text-xs text-gray-500">{{ f.approver_jabatan || f.approver_email || '' }}</div>
              </div>
            </div>
            <div class="text-right">
              <div class="text-sm font-semibold" :class="flowTextClass(f.status)">{{ f.status }}</div>
              <div v-if="f.approved_at" class="text-xs text-gray-500">{{ formatDateTime(f.approved_at) }}</div>
              <div v-if="f.rejected_at" class="text-xs text-gray-500">{{ formatDateTime(f.rejected_at) }}</div>
              <div v-if="f.comments" class="text-xs text-gray-600 mt-1 italic">"{{ f.comments }}"</div>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-6 text-gray-400">Belum ada approval flow.</div>
      </div>

      <!-- Approve/Reject Buttons -->
      <div v-if="currentApprover && header.status === 'SUBMITTED'" class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h2 class="font-bold text-gray-800 mb-4"><i class="fa fa-gavel mr-2 text-orange-500"></i>Tindakan Approval</h2>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Catatan (opsional)</label>
          <textarea v-model="approvalNote" class="w-full px-3 py-2 border border-gray-300 rounded-md" rows="3" placeholder="Catatan approval / alasan reject..."></textarea>
        </div>
        <div class="flex gap-3">
          <button @click="doApprove" :disabled="actionLoading" class="px-6 py-2 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition">
            <i :class="actionLoading ? 'fa fa-spinner fa-spin' : 'fa fa-check'" class="mr-1"></i> Approve
          </button>
          <button @click="doReject" :disabled="actionLoading" class="px-6 py-2 bg-red-600 text-white rounded-lg font-semibold hover:bg-red-700 transition">
            <i :class="actionLoading ? 'fa fa-spinner fa-spin' : 'fa fa-times'" class="mr-1"></i> Reject
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: catat penggantian -->
    <Teleport to="body">
      <div v-if="repModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50" @click.self="repModal = null">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6" @click.stop>
          <h3 class="text-lg font-bold text-gray-800 mb-1">Catat penggantian</h3>
          <p class="text-sm text-gray-500 mb-4">{{ repModal?.item_name }} — sisa {{ formatNumber(repModal?.remaining_qty) }} {{ repModal?.unit_name }}</p>
          <div class="space-y-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Qty diganti <span class="text-red-500">*</span></label>
              <input v-model.number="repQty" type="number" min="0.01" step="0.01" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Barang pengganti</label>
              <p class="text-[11px] text-gray-400 mb-1">Kosongkan = barang identik dengan baris L&amp;B</p>
              <Multiselect
                v-model="repReplacementItem"
                :options="assetItems"
                :internal-search="false"
                :loading="assetItemsLoading"
                :searchable="true"
                :close-on-select="true"
                :show-labels="false"
                :allow-empty="true"
                placeholder="Cari item asset pengganti..."
                label="name"
                track-by="id"
                @search-change="onAssetSearch"
                class="lb-multiselect"
              >
                <template #option="{ option }">
                  <div class="text-sm font-medium">{{ option.name }}</div>
                  <div class="text-xs text-gray-400">{{ option.sku || '-' }}</div>
                </template>
                <template #singleLabel="{ option }">
                  <span class="text-sm">{{ option.name }}</span>
                </template>
              </Multiselect>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan (opsional)</label>
              <textarea v-model="repNote" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Mis. supplier / no. GRN"></textarea>
            </div>
          </div>
          <div class="flex justify-end gap-2 mt-6">
            <button type="button" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg" @click="repModal = null">Batal</button>
            <button type="button" class="px-4 py-2 text-sm font-semibold text-white bg-orange-500 hover:bg-orange-600 rounded-lg disabled:opacity-50" :disabled="repSaving" @click="submitReplacement">
              <i v-if="repSaving" class="fa fa-spinner fa-spin mr-1"></i>Simpan
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({
  header: Object,
  details: Array,
  approvalFlows: Array,
  currentApprover: Object,
  canRecordReplacements: { type: Boolean, default: false }
})

const approvalNote = ref('')
const actionLoading = ref(false)

const repModal = ref(null)
const repQty = ref('')
const repReplacementItem = ref(null)
const repNote = ref('')
const repSaving = ref(false)
const assetItems = ref([])
const assetItemsLoading = ref(false)
let assetSearchTimer = null

function goBack() { router.visit('/lost-breakage') }

function formatDate(d) { return d ? new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '-' }
function formatDateTime(d) { return d ? new Date(d).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-' }
function formatNumber(v) { if (v == null) return '-'; const n = Number(v); return n % 1 === 0 ? String(n) : n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 4 }) }

function statusLabel(s) { return { DRAFT: 'Draft', SUBMITTED: 'Menunggu Approval', APPROVED: 'Disetujui', REJECTED: 'Ditolak' }[s] || s }
function statusBadge(s) { return { DRAFT: 'bg-gray-100 text-gray-800', SUBMITTED: 'bg-yellow-100 text-yellow-800', APPROVED: 'bg-green-100 text-green-800', REJECTED: 'bg-red-100 text-red-800' }[s] || 'bg-gray-100 text-gray-800' }
function flowBadge(s) { return { PENDING: 'bg-yellow-100 text-yellow-800', APPROVED: 'bg-green-100 text-green-800', REJECTED: 'bg-red-100 text-red-800' }[s] || 'bg-gray-100 text-gray-800' }
function flowBorderClass(s) { return { APPROVED: 'border-green-200 bg-green-50', REJECTED: 'border-red-200 bg-red-50', PENDING: 'border-yellow-200 bg-yellow-50' }[s] || 'border-gray-200' }
function flowTextClass(s) { return { APPROVED: 'text-green-600', REJECTED: 'text-red-600', PENDING: 'text-yellow-600' }[s] || 'text-gray-600' }

function fulfillmentLabel(f) {
  return { none: 'Belum', partial: 'Sebagian', complete: 'Lengkap' }[f] || f
}
function fulfillmentBadge(f) {
  return { none: 'bg-gray-100 text-gray-600', partial: 'bg-amber-100 text-amber-800', complete: 'bg-green-100 text-green-800' }[f] || 'bg-gray-100 text-gray-600'
}

function openRepModal(d) {
  repModal.value = d
  repQty.value = ''
  repReplacementItem.value = null
  repNote.value = ''
  fetchAssetItems('')
}

function onAssetSearch(q) {
  if (assetSearchTimer) clearTimeout(assetSearchTimer)
  assetSearchTimer = setTimeout(() => fetchAssetItems(q || ''), 280)
}

async function fetchAssetItems(search) {
  assetItemsLoading.value = true
  try {
    const res = await axios.get('/lost-breakage/asset-items-json', { params: { search: search || undefined, limit: 80 } })
    assetItems.value = res.data.items || []
  } catch (e) {
    console.error(e)
    assetItems.value = []
  } finally {
    assetItemsLoading.value = false
  }
}

async function submitReplacement() {
  const d = repModal.value
  if (!d) return
  const qty = Number(repQty.value)
  if (!qty || qty <= 0) {
    Swal.fire({ icon: 'warning', title: 'Qty wajib diisi', text: 'Masukkan jumlah yang diganti.' })
    return
  }
  if (qty > Number(d.remaining_qty) + 1e-9) {
    Swal.fire({ icon: 'warning', title: 'Qty terlalu besar', text: 'Melebihi sisa yang boleh diganti.' })
    return
  }
  repSaving.value = true
  try {
    await axios.post(`/lost-breakage/${props.header.id}/details/${d.id}/replacements`, {
      qty_replaced: qty,
      unit_id: d.unit_id,
      replacement_item_id: repReplacementItem.value?.id ?? null,
      note: repNote.value || null
    })
    repModal.value = null
    Swal.fire({ icon: 'success', title: 'Tersimpan', text: 'Penggantian tercatat.', timer: 1600, showConfirmButton: false })
    router.reload()
  } catch (e) {
    Swal.fire({ icon: 'error', title: 'Gagal', text: e.response?.data?.message || e.message })
  } finally {
    repSaving.value = false
  }
}

async function doApprove() {
  const r = await Swal.fire({ title: 'Approve data ini?', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, Approve', cancelButtonText: 'Batal', confirmButtonColor: '#16a34a' })
  if (!r.isConfirmed) return
  actionLoading.value = true
  try {
    const res = await axios.post(`/lost-breakage/${props.header.id}/approve`, { note: approvalNote.value, approval_flow_id: props.currentApprover.id })
    Swal.fire({ icon: 'success', title: 'Berhasil', text: res.data.message, timer: 2000, showConfirmButton: false })
    setTimeout(() => router.reload(), 1500)
  } catch (e) { Swal.fire({ icon: 'error', title: 'Gagal', text: e.response?.data?.message || e.message }) }
  finally { actionLoading.value = false }
}

async function doReject() {
  const r = await Swal.fire({ title: 'Reject data ini?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Ya, Reject', cancelButtonText: 'Batal', confirmButtonColor: '#dc2626' })
  if (!r.isConfirmed) return
  actionLoading.value = true
  try {
    const res = await axios.post(`/lost-breakage/${props.header.id}/reject`, { comments: approvalNote.value, approval_flow_id: props.currentApprover.id })
    Swal.fire({ icon: 'success', title: 'Berhasil', text: res.data.message, timer: 2000, showConfirmButton: false })
    setTimeout(() => router.reload(), 1500)
  } catch (e) { Swal.fire({ icon: 'error', title: 'Gagal', text: e.response?.data?.message || e.message }) }
  finally { actionLoading.value = false }
}
</script>
