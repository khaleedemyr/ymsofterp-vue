<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-orange-50/30 to-slate-100 py-6 px-4 lg:px-8">
      <div class="max-w-5xl mx-auto space-y-6">

        <!-- Header -->
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-extrabold text-slate-800 tracking-tight flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center shadow-lg shadow-orange-200">
                <i class="fa-solid fa-box-open text-white text-sm"></i>
              </div>
              {{ isEdit ? 'Edit' : 'Buat' }} Lost &amp; Breakage
            </h1>
            <p class="text-sm text-slate-500 mt-1 ml-[52px]">Catat barang asset yang hilang atau rusak</p>
          </div>
          <button @click="goBack" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
            <i class="fa fa-arrow-left text-xs"></i> Kembali
          </button>
        </div>

        <!-- Card: Header Info -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
            <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
              <i class="fa fa-info-circle text-orange-400"></i> Informasi Umum
            </h2>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Tanggal <span class="text-red-400">*</span></label>
                <input type="date" v-model="form.date" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-400/40 focus:border-orange-400 transition-all" required />
              </div>
              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Outlet <span class="text-red-400">*</span></label>
                <select v-model="form.outlet_id" :disabled="outletDisabled" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-400/40 focus:border-orange-400 transition-all disabled:opacity-60 disabled:cursor-not-allowed" required>
                  <option value="">Pilih Outlet</option>
                  <option v-for="o in props.outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
                </select>
              </div>
            </div>
            <div class="mt-4">
              <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Catatan Umum</label>
              <textarea v-model="form.notes" rows="2" placeholder="Catatan tambahan (opsional)..." class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-400/40 focus:border-orange-400 transition-all resize-none"></textarea>
            </div>
          </div>
        </div>

        <!-- Card: Items -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex items-center justify-between">
            <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
              <i class="fa fa-cubes text-orange-400"></i> Item Asset
              <span class="text-xs font-normal normal-case text-slate-400">({{ validItemCount }} item)</span>
            </h2>
            <button type="button" @click="addItem" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-orange-700 bg-orange-50 border border-orange-200 rounded-lg hover:bg-orange-100 transition-all">
              <i class="fa fa-plus text-[10px]"></i> Tambah
            </button>
          </div>
          <div class="p-6 space-y-4">
            <div v-if="form.items.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-400">
              <div class="w-16 h-16 rounded-full bg-slate-100 flex items-center justify-center mb-3">
                <i class="fa fa-box-open text-2xl text-slate-300"></i>
              </div>
              <p class="text-sm font-medium">Belum ada item</p>
              <p class="text-xs">Klik "Tambah" untuk menambahkan item asset</p>
            </div>

            <TransitionGroup name="item-list" tag="div" class="space-y-4">
              <div v-for="(item, idx) in form.items" :key="item._uid" class="group relative bg-gradient-to-br from-white to-slate-50/50 border border-slate-200 rounded-2xl p-5 hover:shadow-md hover:border-slate-300 transition-all duration-200">
                <!-- Item Number Badge + Remove -->
                <div class="flex items-center justify-between mb-4">
                  <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-7 h-7 text-xs font-bold text-orange-700 bg-orange-100 rounded-lg">{{ idx + 1 }}</span>
                    <span v-if="item.selectedItem" class="text-sm font-semibold text-slate-700">{{ item.selectedItem.name }}</span>
                    <span v-else class="text-sm text-slate-400 italic">Item belum dipilih</span>
                  </div>
                  <button type="button" @click="removeItem(idx)" v-if="form.items.length > 1" class="w-8 h-8 flex items-center justify-center rounded-lg text-red-400 hover:text-white hover:bg-red-500 transition-all opacity-0 group-hover:opacity-100">
                    <i class="fa fa-trash text-xs"></i>
                  </button>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                  <!-- Item Selector with Image -->
                  <div class="lg:col-span-12">
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Pilih Item Asset</label>
                    <multiselect
                      v-model="item.selectedItem"
                      :options="assetItems"
                      :searchable="true"
                      :close-on-select="true"
                      :show-labels="false"
                      placeholder="Cari item asset..."
                      label="name"
                      track-by="id"
                      @select="(sel) => onItemSelect(sel, idx)"
                      @remove="() => onItemRemove(idx)"
                      class="lb-multiselect"
                    >
                      <template #option="{ option }">
                        <div class="flex items-center gap-3 py-1">
                          <div class="w-10 h-10 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden flex-shrink-0">
                            <img v-if="option.image" :src="`/storage/${option.image}`" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center"><i class="fa fa-image text-slate-300 text-xs"></i></div>
                          </div>
                          <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-800 truncate">{{ option.name }}</div>
                            <div class="text-xs text-slate-400">{{ option.category_name }} &middot; {{ option.sku || '-' }}</div>
                          </div>
                        </div>
                      </template>
                      <template #singleLabel="{ option }">
                        <div class="flex items-center gap-2">
                          <div class="w-6 h-6 rounded bg-slate-100 overflow-hidden flex-shrink-0">
                            <img v-if="option.image" :src="`/storage/${option.image}`" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center"><i class="fa fa-image text-slate-300 text-[8px]"></i></div>
                          </div>
                          <span class="text-sm font-medium text-slate-800 truncate">{{ option.name }}</span>
                        </div>
                      </template>
                      <template #noResult>
                        <div class="text-center py-4 text-slate-400 text-sm"><i class="fa fa-search mr-2"></i>Tidak ditemukan</div>
                      </template>
                      <template #noOptions>
                        <div class="text-center py-4 text-slate-400 text-sm">Tidak ada item asset tersedia</div>
                      </template>
                    </multiselect>
                  </div>

                  <!-- Selected Item Image Preview -->
                  <div v-if="item.selectedItem && item.selectedItem.image" class="lg:col-span-3">
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Gambar Item</label>
                    <div class="relative w-full aspect-square max-w-[120px] rounded-xl overflow-hidden border border-slate-200 bg-slate-50 cursor-pointer group/img" @click="openLightbox(`/storage/${item.selectedItem.image}`)">
                      <img :src="`/storage/${item.selectedItem.image}`" class="w-full h-full object-cover transition-transform duration-300 group-hover/img:scale-110" />
                      <div class="absolute inset-0 bg-black/0 group-hover/img:bg-black/20 transition-all flex items-center justify-center">
                        <i class="fa fa-expand text-white opacity-0 group-hover/img:opacity-100 transition-all text-lg drop-shadow"></i>
                      </div>
                    </div>
                  </div>

                  <!-- Type + Qty + Unit -->
                  <div :class="item.selectedItem && item.selectedItem.image ? 'lg:col-span-5' : 'lg:col-span-4'">
                    <div class="grid grid-cols-3 gap-3">
                      <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Tipe <span class="text-red-400">*</span></label>
                        <select v-model="item.type" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-400/40 focus:border-orange-400 transition-all">
                          <option value="lost">Lost</option>
                          <option value="breakage">Breakage</option>
                        </select>
                      </div>
                      <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Qty <span class="text-red-400">*</span></label>
                        <input type="number" min="0.01" step="0.01" v-model.number="item.qty" :disabled="!item.item_id" placeholder="0" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-400/40 focus:border-orange-400 transition-all disabled:opacity-40" />
                      </div>
                      <div>
                        <label class="block text-xs font-semibold text-slate-500 mb-1.5">Unit <span class="text-red-400">*</span></label>
                        <select v-model="item.unit_id" :disabled="!item.item_id" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-400/40 focus:border-orange-400 transition-all disabled:opacity-40">
                          <option v-for="u in item.availableUnits" :key="u.id" :value="u.id">{{ u.name }} ({{ u.type }})</option>
                        </select>
                      </div>
                    </div>
                  </div>

                  <!-- Keterangan -->
                  <div :class="item.selectedItem && item.selectedItem.image ? 'lg:col-span-4' : 'lg:col-span-4'">
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">Keterangan</label>
                    <textarea v-model="item.note" rows="2" placeholder="Alasan lost/breakage..." class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-400/40 focus:border-orange-400 transition-all resize-none"></textarea>
                  </div>

                  <!-- Photo Bukti -->
                  <div class="lg:col-span-4">
                    <label class="block text-xs font-semibold text-slate-500 mb-1.5">
                      Foto Bukti
                      <span v-if="item.type === 'breakage'" class="text-red-400">*</span>
                      <span v-else class="text-slate-300 font-normal">(opsional)</span>
                    </label>
                    <div class="flex items-center gap-2 flex-wrap">
                      <label class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-slate-600 bg-slate-50 border border-slate-200 border-dashed rounded-xl hover:bg-slate-100 cursor-pointer transition-all">
                        <i class="fa fa-folder-open text-slate-400"></i>
                        <span>File</span>
                        <input type="file" accept="image/*" @change="(e) => handlePhoto(e, idx)" class="hidden" />
                      </label>
                      <button type="button" @click="openCamera(idx)" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-blue-600 bg-blue-50 border border-blue-200 border-dashed rounded-xl hover:bg-blue-100 transition-all">
                        <i class="fa fa-camera text-blue-400"></i>
                        <span>Kamera</span>
                      </button>
                      <div v-if="item.photoUploading" class="text-xs text-orange-500"><i class="fa fa-spinner fa-spin"></i></div>
                      <div v-if="item.photoPreview" class="relative w-10 h-10 rounded-lg overflow-hidden border border-slate-200 cursor-pointer group" @click="openLightbox(item.photoPreview)">
                        <img :src="item.photoPreview" class="w-full h-full object-cover" />
                        <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                          <i class="fa fa-search-plus text-white text-xs"></i>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </TransitionGroup>

            <div v-if="form.items.length > 0" class="flex justify-center pt-2">
              <button type="button" @click="addItem" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-orange-600 bg-orange-50 rounded-xl hover:bg-orange-100 border border-orange-200 transition-all">
                <i class="fa fa-plus text-[10px]"></i> Tambah Item Lainnya
              </button>
            </div>
          </div>
        </div>

        <!-- Card: Approval Flow -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
          <div class="px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
            <h2 class="text-sm font-bold text-slate-700 uppercase tracking-wider flex items-center gap-2">
              <i class="fa fa-user-check text-orange-400"></i> Approval Flow
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Urutkan dari level terendah ke tertinggi</p>
          </div>
          <div class="p-6 space-y-4">
            <!-- Search approver -->
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none"><i class="fa fa-search text-slate-300 text-xs"></i></div>
              <input v-model="approverSearch" type="text" placeholder="Cari user berdasarkan nama, email, atau jabatan..." class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-orange-400/40 focus:border-orange-400 transition-all" @input="handleApproverSearch" @focus="handleApproverFocus" @blur="handleApproverBlur" />
              <div v-if="showApproverDropdown && approverResults.length > 0" class="absolute z-50 w-full mt-1.5 bg-white border border-slate-200 rounded-xl shadow-xl shadow-slate-200/50 max-h-56 overflow-y-auto">
                <div v-for="u in approverResults" :key="u.id" @mousedown.prevent="addApprover(u)" class="px-4 py-3 hover:bg-orange-50 cursor-pointer border-b border-slate-100 last:border-b-0 transition-colors">
                  <div class="text-sm font-semibold text-slate-700">{{ u.name }}</div>
                  <div class="text-xs text-slate-400">{{ u.email }}</div>
                  <div v-if="u.jabatan" class="text-xs text-orange-600 font-medium mt-0.5">{{ u.jabatan }}</div>
                </div>
              </div>
            </div>

            <!-- Approvers List -->
            <div v-if="form.approvers.length > 0" class="space-y-2">
              <div v-for="(a, i) in form.approvers" :key="a.id" class="flex items-center justify-between p-3.5 bg-gradient-to-r from-slate-50 to-white border border-slate-200 rounded-xl hover:border-slate-300 transition-all">
                <div class="flex items-center gap-3">
                  <div class="flex flex-col gap-0.5">
                    <button v-if="i > 0" type="button" @click="reorderApprover(i, i - 1)" class="w-5 h-5 flex items-center justify-center rounded text-slate-300 hover:text-slate-600 hover:bg-slate-100 transition-all"><i class="fa fa-chevron-up text-[9px]"></i></button>
                    <button v-if="i < form.approvers.length - 1" type="button" @click="reorderApprover(i, i + 1)" class="w-5 h-5 flex items-center justify-center rounded text-slate-300 hover:text-slate-600 hover:bg-slate-100 transition-all"><i class="fa fa-chevron-down text-[9px]"></i></button>
                  </div>
                  <span class="inline-flex items-center justify-center w-8 h-8 text-xs font-bold text-orange-700 bg-gradient-to-br from-orange-100 to-amber-100 rounded-lg shadow-sm">{{ i + 1 }}</span>
                  <div>
                    <div class="text-sm font-semibold text-slate-700">{{ a.name }}</div>
                    <div class="text-xs text-slate-400">{{ a.email }}{{ a.jabatan ? ` · ${a.jabatan}` : '' }}</div>
                  </div>
                </div>
                <button type="button" @click="removeApprover(i)" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 transition-all"><i class="fa fa-times text-xs"></i></button>
              </div>
            </div>
            <div v-else class="flex items-center gap-2 px-4 py-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl">
              <i class="fa fa-exclamation-triangle"></i> Wajib menambahkan minimal 1 approver sebelum submit.
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 px-6 py-4">
          <div class="flex items-center justify-between">
            <div class="text-xs text-slate-400">
              <span v-if="isAutosaving"><i class="fa fa-spinner fa-spin mr-1"></i>Menyimpan...</span>
              <span v-else-if="lastSaved"><i class="fa fa-check text-green-500 mr-1"></i>Tersimpan {{ new Date(lastSaved).toLocaleTimeString('id-ID') }}</span>
            </div>
            <div class="flex items-center gap-3">
              <button type="button" @click="goBack" class="px-5 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors">Batal</button>
              <button type="button" @click="saveDraft" :disabled="loading" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold text-slate-700 bg-slate-100 border border-slate-200 rounded-xl hover:bg-slate-200 transition-all disabled:opacity-50">
                <i :class="loading && !submitting ? 'fa fa-spinner fa-spin' : 'fa fa-save'" class="text-xs"></i> Simpan Draft
              </button>
              <button type="button" @click="submitForm" :disabled="loading" class="inline-flex items-center gap-2 px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-orange-500 to-amber-500 rounded-xl hover:from-orange-600 hover:to-amber-600 shadow-lg shadow-orange-200 hover:shadow-orange-300 transition-all disabled:opacity-50">
                <i :class="submitting ? 'fa fa-spinner fa-spin' : 'fa fa-paper-plane'" class="text-xs"></i> Submit
              </button>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Camera Modal -->
    <Teleport to="body">
      <Transition name="lightbox-fade">
        <div v-if="cameraActive" class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-black/90 backdrop-blur-sm">
          <div class="relative w-full max-w-lg">
            <video ref="cameraVideo" autoplay playsinline class="w-full rounded-2xl shadow-2xl"></video>
            <canvas ref="cameraCanvas" class="hidden"></canvas>
          </div>
          <div class="flex items-center gap-4 mt-6">
            <button type="button" @click="capturePhoto" class="w-16 h-16 rounded-full bg-white flex items-center justify-center shadow-xl hover:scale-105 transition-transform">
              <div class="w-12 h-12 rounded-full border-4 border-slate-300"></div>
            </button>
            <button type="button" @click="closeCamera" class="w-12 h-12 rounded-full bg-white/20 text-white flex items-center justify-center hover:bg-white/40 transition-all">
              <i class="fa fa-times text-lg"></i>
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Lightbox -->
    <Teleport to="body">
      <Transition name="lightbox-fade">
        <div v-if="lightboxSrc" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-sm" @click.self="lightboxSrc = null">
          <button @click="lightboxSrc = null" class="absolute top-6 right-6 w-10 h-10 flex items-center justify-center rounded-full bg-white/20 text-white hover:bg-white/40 transition-all text-lg"><i class="fa fa-times"></i></button>
          <img :src="lightboxSrc" class="max-w-[90vw] max-h-[85vh] rounded-2xl shadow-2xl object-contain" />
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, computed, watch, nextTick } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'

const props = defineProps({ outlets: Array, items: Array, units: Array, header: Object, details: Array, approvalFlows: Array, isEdit: Boolean })

const page = usePage()
const userOutletId = computed(() => page.props.auth?.user?.id_outlet || '')
const outletDisabled = computed(() => userOutletId.value != 1)
const isEdit = computed(() => props.isEdit || false)
const assetItems = computed(() => props.items || [])
const lightboxSrc = ref(null)
const cameraActive = ref(false)
const cameraItemIdx = ref(null)
const cameraVideo = ref(null)
const cameraCanvas = ref(null)
let cameraStream = null

function openLightbox(src) { lightboxSrc.value = src }

let uidCounter = 0
function newItem() {
  return { _uid: ++uidCounter, item_id: '', selectedItem: null, type: 'lost', qty: '', unit_id: '', note: '', photo: '', photoPreview: '', photoUploading: false, availableUnits: [] }
}

function buildUnitsForItem(d) {
  const u = []
  if (d.small_unit_id)  u.push({ id: d.small_unit_id, name: d.small_unit_name, type: 'small' })
  if (d.medium_unit_id) u.push({ id: d.medium_unit_id, name: d.medium_unit_name, type: 'medium' })
  if (d.large_unit_id)  u.push({ id: d.large_unit_id, name: d.large_unit_name, type: 'large' })
  return u
}

function initItems() {
  if (props.details?.length > 0) {
    return props.details.map(d => {
      const m = assetItems.value.find(i => i.id == d.item_id)
      return { _uid: ++uidCounter, item_id: d.item_id, selectedItem: m || { id: d.item_id, name: d.item_name }, type: d.type || 'lost', qty: Number(d.qty), unit_id: d.unit_id, note: d.note || '', photo: d.photo || '', photoPreview: d.photo ? `/storage/${d.photo}` : '', photoUploading: false, availableUnits: m ? buildUnitsForItem(m) : buildUnitsForItem(d) }
    })
  }
  return [newItem()]
}

const form = ref({
  header_id: props.header?.id || null,
  date: props.header?.date || new Date().toISOString().split('T')[0],
  outlet_id: props.header?.outlet_id || (userOutletId.value == 1 ? '' : userOutletId.value),
  notes: props.header?.notes || '',
  items: initItems(),
  approvers: (props.approvalFlows || []).map(f => ({ id: f.approver_id, name: f.approver_name, email: f.approver_email || '', jabatan: '' }))
})

const loading = ref(false)
const submitting = ref(false)
const isAutosaving = ref(false)
const lastSaved = ref(null)
const validItemCount = computed(() => form.value.items.filter(i => i.item_id).length)

function addItem() { form.value.items.push(newItem()) }
function removeItem(idx) { if (form.value.items.length > 1) form.value.items.splice(idx, 1) }

function onItemSelect(sel, idx) {
  const item = form.value.items[idx]
  item.item_id = sel.id
  item.availableUnits = buildUnitsForItem(sel)
  if (item.availableUnits.length > 0) item.unit_id = item.availableUnits[0].id
}
function onItemRemove(idx) { Object.assign(form.value.items[idx], { item_id: '', unit_id: '', availableUnits: [] }) }

async function handlePhoto(e, idx) {
  const file = e.target.files[0]
  if (!file) return
  const item = form.value.items[idx]
  item.photoUploading = true
  try {
    const fd = new FormData(); fd.append('photo', file)
    const res = await axios.post('/lost-breakage/upload-photo', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    if (res.data.success) { item.photo = res.data.path; item.photoPreview = res.data.url }
  } catch (err) { Swal.fire({ icon: 'error', title: 'Upload gagal', text: err.response?.data?.message || err.message }) }
  finally { item.photoUploading = false }
}

async function openCamera(idx) {
  cameraItemIdx.value = idx
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1280 }, height: { ideal: 720 } } })
    cameraActive.value = true
    await nextTick()
    if (cameraVideo.value) cameraVideo.value.srcObject = cameraStream
  } catch (err) {
    Swal.fire({ icon: 'warning', title: 'Kamera tidak tersedia', text: 'Pastikan browser memiliki izin akses kamera, atau gunakan tombol File untuk upload foto.' })
  }
}

function closeCamera() {
  cameraActive.value = false
  if (cameraStream) { cameraStream.getTracks().forEach(t => t.stop()); cameraStream = null }
}

async function capturePhoto() {
  if (!cameraVideo.value || !cameraCanvas.value) return
  const video = cameraVideo.value
  const canvas = cameraCanvas.value
  canvas.width = video.videoWidth
  canvas.height = video.videoHeight
  canvas.getContext('2d').drawImage(video, 0, 0)
  closeCamera()

  const idx = cameraItemIdx.value
  const item = form.value.items[idx]
  item.photoUploading = true
  try {
    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.85))
    const fd = new FormData()
    fd.append('photo', blob, `camera-${Date.now()}.jpg`)
    const res = await axios.post('/lost-breakage/upload-photo', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    if (res.data.success) { item.photo = res.data.path; item.photoPreview = res.data.url }
  } catch (err) { Swal.fire({ icon: 'error', title: 'Upload gagal', text: err.response?.data?.message || err.message }) }
  finally { item.photoUploading = false }
}

// Approver
const approverSearch = ref('')
const approverResults = ref([])
const showApproverDropdown = ref(false)
let approverTimeout = null
function handleApproverSearch() {
  clearTimeout(approverTimeout)
  approverTimeout = setTimeout(async () => {
    if (approverSearch.value.length < 2) { approverResults.value = []; return }
    try { const r = await axios.get('/lost-breakage/approvers', { params: { q: approverSearch.value } }); approverResults.value = (r.data.users || []).filter(u => !form.value.approvers.find(a => a.id === u.id)) } catch { approverResults.value = [] }
  }, 300)
}
function handleApproverFocus() { showApproverDropdown.value = true }
function handleApproverBlur() { setTimeout(() => { showApproverDropdown.value = false }, 200) }
function addApprover(u) { form.value.approvers.push({ id: u.id, name: u.name, email: u.email, jabatan: u.jabatan }); approverSearch.value = ''; approverResults.value = [] }
function removeApprover(i) { form.value.approvers.splice(i, 1) }
function reorderApprover(from, to) { const l = form.value.approvers; l.splice(to, 0, l.splice(from, 1)[0]) }

function buildPayload() {
  return { header_id: form.value.header_id, date: form.value.date, outlet_id: form.value.outlet_id, notes: form.value.notes, items: form.value.items.filter(i => i.item_id).map(i => ({ item_id: i.item_id, type: i.type, qty: i.qty, unit_id: i.unit_id, note: i.note, photo: i.photo })) }
}

async function saveDraft() {
  if (!form.value.date || !form.value.outlet_id) { Swal.fire({ icon: 'warning', title: 'Lengkapi data', text: 'Tanggal dan Outlet wajib diisi.' }); return }
  loading.value = true
  try {
    const r = await axios.post('/lost-breakage', buildPayload())
    if (r.data.success) { form.value.header_id = r.data.header_id; lastSaved.value = new Date(); Swal.fire({ icon: 'success', title: 'Draft tersimpan', timer: 1500, showConfirmButton: false }) }
  } catch (e) { Swal.fire({ icon: 'error', title: 'Gagal', text: e.response?.data?.message || e.message }) }
  finally { loading.value = false }
}

async function submitForm() {
  if (!form.value.date || !form.value.outlet_id) { Swal.fire({ icon: 'warning', title: 'Lengkapi data', text: 'Tanggal dan Outlet wajib diisi.' }); return }
  if (validItemCount.value === 0) { Swal.fire({ icon: 'warning', title: 'Item kosong', text: 'Minimal 1 item harus ditambahkan.' }); return }
  const breakageNoPhoto = form.value.items.filter(i => i.item_id && i.type === 'breakage' && !i.photo)
  if (breakageNoPhoto.length > 0) { Swal.fire({ icon: 'warning', title: 'Foto wajib untuk Breakage', text: 'Item bertipe Breakage wajib dilampirkan foto bukti.' }); return }
  if (form.value.approvers.length === 0) { Swal.fire({ icon: 'warning', title: 'Approver kosong', text: 'Wajib menambahkan minimal 1 approver.' }); return }
  submitting.value = true; loading.value = true
  try {
    const s = await axios.post('/lost-breakage', buildPayload())
    if (!s.data.success) throw new Error(s.data.message || 'Gagal menyimpan')
    form.value.header_id = s.data.header_id
    const r = await axios.post(`/lost-breakage/${s.data.header_id}/submit`, { approvers: form.value.approvers.map(a => a.id) })
    if (r.data.success) { Swal.fire({ icon: 'success', title: 'Berhasil', text: r.data.message, timer: 2000, showConfirmButton: false }); setTimeout(() => router.visit('/lost-breakage'), 1500) }
    else throw new Error(r.data.message)
  } catch (e) { Swal.fire({ icon: 'error', title: 'Gagal submit', text: e.response?.data?.message || e.message }) }
  finally { submitting.value = false; loading.value = false }
}

// Autosave
let autoTimer = null
watch(() => [form.value.date, form.value.outlet_id, form.value.notes, form.value.items], () => {
  clearTimeout(autoTimer)
  if (!form.value.date || !form.value.outlet_id) return
  autoTimer = setTimeout(async () => {
    isAutosaving.value = true
    try { const r = await axios.post('/lost-breakage', { ...buildPayload(), autosave: true }); if (r.data.success) { form.value.header_id = r.data.header_id; lastSaved.value = new Date() } } catch {}
    finally { isAutosaving.value = false }
  }, 3000)
}, { deep: true })

function goBack() { router.visit('/lost-breakage') }
</script>

<style>
.lb-multiselect .multiselect__tags { border-radius: 0.75rem; border-color: #e2e8f0; background: #f8fafc; min-height: 44px; padding: 6px 40px 0 8px; }
.lb-multiselect .multiselect__tags:focus-within { border-color: #fb923c; box-shadow: 0 0 0 3px rgba(251,146,60,0.15); }
.lb-multiselect .multiselect__single { font-size: 0.875rem; padding: 4px 0; }
.lb-multiselect .multiselect__input { font-size: 0.875rem; }
.lb-multiselect .multiselect__content-wrapper { border-radius: 0.75rem; border-color: #e2e8f0; box-shadow: 0 20px 60px -15px rgba(0,0,0,0.15); margin-top: 4px; }
.lb-multiselect .multiselect__option--highlight { background: #fff7ed; color: #c2410c; }
.lb-multiselect .multiselect__option--selected { background: #f1f5f9; color: #334155; font-weight: 600; }

.item-list-enter-active { transition: all 0.3s ease; }
.item-list-leave-active { transition: all 0.2s ease; }
.item-list-enter-from { opacity: 0; transform: translateY(-10px); }
.item-list-leave-to { opacity: 0; transform: translateX(20px); }

.lightbox-fade-enter-active { transition: all 0.25s ease; }
.lightbox-fade-leave-active { transition: all 0.2s ease; }
.lightbox-fade-enter-from, .lightbox-fade-leave-to { opacity: 0; }
</style>
