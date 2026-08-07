<template>
  <AppLayout>
    <div class="w-full max-w-4xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
            <i class="fa-solid fa-truck text-cyan-600"></i>
            {{ isEdit ? 'Edit Logbook Driver' : 'Buat Logbook Driver' }}
          </h1>
          <p class="text-sm text-gray-500 mt-1">
            {{ isEdit ? record.number : 'Driver otomatis dari user login' }}
          </p>
        </div>
        <Link
          :href="route('logbook-drivers.index')"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200"
        >
          <i class="fa-solid fa-arrow-left"></i> Kembali
        </Link>
      </div>

      <form class="space-y-6" @submit.prevent="submit">
        <div class="bg-white rounded-xl shadow p-6 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Driver</label>
              <input
                type="text"
                :value="driver?.name || ''"
                readonly
                class="w-full rounded-lg border-gray-300 bg-gray-50 text-gray-700"
              />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Tanggal</label>
              <input
                type="date"
                :value="form.log_date"
                readonly
                class="w-full rounded-lg border-gray-300 bg-gray-50 text-gray-700"
              />
              <p class="text-[11px] text-gray-400 mt-1">Tanggal otomatis (tidak bisa diubah)</p>
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Outlet <span class="text-red-500">*</span></label>
              <select
                v-model="form.outlet_id"
                required
                class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500"
              >
                <option value="">Pilih outlet</option>
                <option v-for="o in outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
              </select>
              <p v-if="form.errors.outlet_id" class="text-xs text-red-600 mt-1">{{ form.errors.outlet_id }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Catatan (opsional)</label>
              <textarea
                v-model="form.notes"
                rows="2"
                class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500"
                placeholder="Catatan tambahan..."
              />
              <p v-if="form.errors.notes" class="text-xs text-red-600 mt-1">{{ form.errors.notes }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-xl shadow overflow-hidden">
          <div class="px-6 py-4 border-b bg-gradient-to-r from-cyan-50 to-white flex items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-semibold text-gray-800">Baris Log</h2>
              <p class="text-xs text-gray-500">Minimal 1 baris dengan keterangan</p>
            </div>
            <button
              type="button"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-cyan-600 text-white text-sm hover:bg-cyan-700"
              @click="addItem"
            >
              <i class="fa-solid fa-plus"></i> Tambah Baris
            </button>
          </div>

          <div class="p-4 space-y-4">
            <p v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</p>

            <div
              v-for="(item, index) in form.items"
              :key="item._key"
              class="border border-gray-200 rounded-xl p-4 space-y-3"
            >
              <div class="flex items-center justify-between gap-2">
                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-cyan-100 text-cyan-700 text-xs font-bold">
                  {{ index + 1 }}
                </span>
                <button
                  type="button"
                  class="text-red-600 hover:text-red-800 text-sm"
                  :disabled="form.items.length <= 1"
                  @click="removeItem(index)"
                >
                  <i class="fa-solid fa-trash"></i> Hapus
                </button>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Jam</label>
                  <input
                    v-model="item.log_time"
                    type="time"
                    class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500"
                  />
                </div>
                <div class="md:col-span-3">
                  <label class="block text-xs font-semibold text-gray-600 mb-1">Keterangan <span class="text-red-500">*</span></label>
                  <textarea
                    v-model="item.description"
                    rows="2"
                    required
                    class="w-full rounded-lg border-gray-300 focus:border-cyan-500 focus:ring-cyan-500"
                    placeholder="Contoh: Berangkat dari gudang / Sampai outlet / Muat barang..."
                  />
                  <p v-if="form.errors[`items.${index}.description`]" class="text-xs text-red-600 mt-1">
                    {{ form.errors[`items.${index}.description`] }}
                  </p>
                </div>
              </div>

              <div class="flex flex-wrap items-start gap-3">
                <div v-if="item.preview_url" class="relative w-28 h-28 rounded-lg overflow-hidden border bg-gray-100">
                  <img :src="item.preview_url" alt="Preview" class="w-full h-full object-cover" />
                  <button
                    type="button"
                    class="absolute top-1 right-1 w-7 h-7 rounded-full bg-black/60 text-white text-xs"
                    title="Hapus foto"
                    @click="clearPhoto(index)"
                  >
                    <i class="fa-solid fa-xmark"></i>
                  </button>
                </div>
                <div class="flex flex-wrap gap-2">
                  <button
                    type="button"
                    class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-800 text-white text-sm hover:bg-slate-900"
                    @click="openCamera(index)"
                  >
                    <i class="fa-solid fa-camera"></i> Kamera
                  </button>
                  <label class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-100 text-gray-700 text-sm hover:bg-gray-200 cursor-pointer">
                    <i class="fa-solid fa-upload"></i> Upload
                    <input type="file" accept="image/*" class="hidden" @change="onFileSelected($event, index)" />
                  </label>
                </div>
                <p v-if="form.errors[`items.${index}.photo`]" class="text-xs text-red-600 w-full">
                  {{ form.errors[`items.${index}.photo`] }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2">
          <Link
            :href="route('logbook-drivers.index')"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200"
          >
            Batal
          </Link>
          <button
            type="submit"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-cyan-600 text-white hover:bg-cyan-700 disabled:opacity-60"
            :disabled="form.processing"
          >
            <i class="fa-solid fa-save"></i>
            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
          </button>
        </div>
      </form>
    </div>

    <CameraModal
      v-if="showCameraModal"
      mode="photo"
      @close="closeCameraModal"
      @capture="onLiveCameraCapture"
    />
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import CameraModal from '@/Components/CameraModal.vue'

const props = defineProps({
  record: Object,
  outlets: Array,
  driver: Object,
})

const isEdit = computed(() => !!props.record?.id)

let keySeq = 0
function nextKey() {
  keySeq += 1
  return `item-${keySeq}`
}

function currentTimeHHmm() {
  const now = new Date()
  return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
}

function mapExistingItems() {
  const items = props.record?.items || []
  if (!items.length) {
    return [blankItem()]
  }
  return items.map((item) => ({
    _key: nextKey(),
    id: item.id,
    log_time: normalizeTime(item.log_time) || currentTimeHHmm(),
    description: item.description || '',
    photo: null,
    keep_photo: !!item.photo_path,
    preview_url: item.photo_url || null,
  }))
}

function blankItem() {
  return {
    _key: nextKey(),
    id: null,
    log_time: currentTimeHHmm(),
    description: '',
    photo: null,
    keep_photo: false,
    preview_url: null,
  }
}

function normalizeTime(value) {
  if (!value) return ''
  const s = String(value)
  return s.length >= 5 ? s.slice(0, 5) : s
}

const form = useForm({
  log_date: props.record?.log_date
    ? String(props.record.log_date).slice(0, 10)
    : new Date().toISOString().slice(0, 10),
  outlet_id: props.record?.outlet_id || '',
  notes: props.record?.notes || '',
  items: mapExistingItems(),
})

const showCameraModal = ref(false)
const cameraTarget = ref(null)

function addItem() {
  form.items.push(blankItem())
}

function removeItem(index) {
  if (form.items.length <= 1) return
  form.items.splice(index, 1)
}

function openCamera(index) {
  cameraTarget.value = index
  showCameraModal.value = true
}

function closeCameraModal() {
  showCameraModal.value = false
  cameraTarget.value = null
}

function dataUrlToFile(dataUrl, filename = `capture-${Date.now()}.jpg`) {
  const arr = dataUrl.split(',')
  const mime = arr[0].match(/:(.*?);/)?.[1] || 'image/jpeg'
  const bstr = atob(arr[1])
  let n = bstr.length
  const u8arr = new Uint8Array(n)
  while (n--) {
    u8arr[n] = bstr.charCodeAt(n)
  }
  return new File([u8arr], filename, { type: mime })
}

function onLiveCameraCapture(payload) {
  const index = cameraTarget.value
  showCameraModal.value = false
  cameraTarget.value = null

  if (index === null || index === undefined) return
  if (typeof payload !== 'string') {
    alert('Format capture tidak dikenali. Gunakan mode foto.')
    return
  }

  const file = dataUrlToFile(payload)
  applyPhoto(index, file)
}

function onFileSelected(event, index) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  applyPhoto(index, file)
}

function applyPhoto(index, file) {
  if (form.items[index].preview_url && form.items[index].preview_url.startsWith('blob:')) {
    URL.revokeObjectURL(form.items[index].preview_url)
  }
  form.items[index].photo = file
  form.items[index].keep_photo = true
  form.items[index].preview_url = URL.createObjectURL(file)
}

function clearPhoto(index) {
  if (form.items[index].preview_url && form.items[index].preview_url.startsWith('blob:')) {
    URL.revokeObjectURL(form.items[index].preview_url)
  }
  form.items[index].photo = null
  form.items[index].keep_photo = false
  form.items[index].preview_url = null
}

function submit() {
  form.transform((data) => ({
    ...data,
    items: data.items.map(({ _key, preview_url, ...rest }) => rest),
    _method: isEdit.value ? 'put' : undefined,
  }))

  const opts = { forceFormData: true }
  if (isEdit.value) {
    form.post(route('logbook-drivers.update', props.record.id), opts)
  } else {
    form.post(route('logbook-drivers.store'), opts)
  }
}
</script>
