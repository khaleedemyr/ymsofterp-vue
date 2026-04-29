<template>
  <AppLayout>
    <div class="page">
      <div class="head">
        <div>
          <h1>Manual Google Review</h1>
          <p>Input review manual admin yang tetap bisa diproses lewat pipeline AI yang sama.</p>
        </div>
        <div class="actions">
          <a href="/google-review" class="btn btn-outline">Kembali</a>
        </div>
      </div>

      <div class="card">
        <h3>Tambah Manual Review</h3>
        <form class="grid" @submit.prevent="submitCreate">
          <select v-model="form.id_outlet">
            <option value="">Pilih Outlet (opsional)</option>
            <option v-for="o in outlets" :key="o.id" :value="String(o.id)">{{ o.nama_outlet }}</option>
          </select>
          <input v-model="form.author" placeholder="Nama reviewer" required />
          <input v-model.number="form.rating" type="number" min="1" max="5" step="0.1" placeholder="Rating 1-5" required />
          <input v-model="form.review_date" type="date" required />
          <input v-model="form.profile_photo" placeholder="URL foto profil (opsional)" />
          <select v-model="form.is_active">
            <option :value="true">Aktif</option>
            <option :value="false">Nonaktif</option>
          </select>
          <textarea v-model="form.text" rows="4" placeholder="Isi review" required />
          <button class="btn btn-primary" :disabled="submitting">{{ submitting ? 'Menyimpan...' : 'Simpan' }}</button>
        </form>
      </div>

      <div class="card">
        <h3>Klasifikasi AI dari Manual Review</h3>
        <div class="row">
          <label><input v-model="selectAll" type="checkbox" @change="toggleAll" /> Pilih semua halaman ini</label>
          <button class="btn btn-ai" :disabled="aiSubmitting || selectedIds.length === 0" @click="startManualAi">
            {{ aiSubmitting ? 'Mengirim...' : `Klasifikasi AI (${selectedIds.length})` }}
          </button>
        </div>
      </div>

      <div class="card">
        <h3>Daftar Manual Review</h3>
        <table>
          <thead>
            <tr>
              <th></th>
              <th>Outlet</th>
              <th>Author</th>
              <th>Rating</th>
              <th>Tanggal</th>
              <th>Review</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in reviews.data" :key="r.id">
              <td><input v-model="selectedIds" type="checkbox" :value="r.id" /></td>
              <td>{{ r.nama_outlet || '-' }}</td>
              <td>{{ r.author }}</td>
              <td>{{ r.rating }}</td>
              <td>{{ r.review_date }}</td>
              <td class="text">{{ r.text }}</td>
              <td>{{ Number(r.is_active) === 1 ? 'Aktif' : 'Nonaktif' }}</td>
              <td>
                <div class="action-row">
                  <button class="btn btn-outline btn-sm" type="button" @click="openEdit(r)">Edit</button>
                  <button class="btn btn-danger btn-sm" type="button" @click="removeReview(r.id)">Hapus</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="editOpen" class="modal-backdrop" @click.self="editOpen = false">
        <div class="modal-card">
          <h3>Edit Manual Review</h3>
          <form class="grid" @submit.prevent="submitEdit">
            <select v-model="editForm.id_outlet">
              <option value="">Pilih Outlet (opsional)</option>
              <option v-for="o in outlets" :key="o.id" :value="String(o.id)">{{ o.nama_outlet }}</option>
            </select>
            <input v-model="editForm.author" placeholder="Nama reviewer" required />
            <input v-model.number="editForm.rating" type="number" min="1" max="5" step="0.1" placeholder="Rating 1-5" required />
            <input v-model="editForm.review_date" type="date" required />
            <input v-model="editForm.profile_photo" placeholder="URL foto profil (opsional)" />
            <select v-model="editForm.is_active">
              <option :value="true">Aktif</option>
              <option :value="false">Nonaktif</option>
            </select>
            <textarea v-model="editForm.text" rows="4" placeholder="Isi review" required />
            <div class="modal-actions">
              <button class="btn btn-outline" type="button" @click="editOpen = false">Batal</button>
              <button class="btn btn-primary" :disabled="editSubmitting">{{ editSubmitting ? 'Menyimpan...' : 'Simpan perubahan' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const page = usePage()
const outlets = computed(() => page.props.outlets || [])
const reviews = computed(() => page.props.reviews || { data: [] })
const submitting = ref(false)
const aiSubmitting = ref(false)
const editSubmitting = ref(false)
const editOpen = ref(false)
const editingId = ref(null)
const selectAll = ref(false)
const selectedIds = ref([])

const form = ref({
  id_outlet: '',
  author: '',
  rating: 5,
  review_date: '',
  text: '',
  profile_photo: '',
  is_active: true,
})

const editForm = ref({
  id_outlet: '',
  author: '',
  rating: 5,
  review_date: '',
  text: '',
  profile_photo: '',
  is_active: true,
})

async function submitCreate() {
  submitting.value = true
  try {
    await axios.post('/google-review/manual', form.value, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    form.value = {
      id_outlet: '',
      author: '',
      rating: 5,
      review_date: '',
      text: '',
      profile_photo: '',
      is_active: true,
    }
    await router.reload({ preserveScroll: true })
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal menyimpan manual review'
    window.alert(message)
  } finally {
    submitting.value = false
  }
}

function toggleAll() {
  if (selectAll.value) {
    selectedIds.value = (reviews.value?.data || []).map((r) => r.id)
    return
  }
  selectedIds.value = []
}

async function startManualAi() {
  if (selectedIds.value.length === 0) return
  aiSubmitting.value = true
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
  try {
    const res = await fetch('/google-review/ai/reports', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': token,
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        source: 'manual_db',
        place: { name: 'Manual Google Review' },
        manual_review_ids: selectedIds.value,
      }),
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok || !data.success) throw new Error(data.error || `HTTP ${res.status}`)
    router.visit(`/google-review/ai/reports/${data.id}`)
  } catch (e) {
    window.alert(e.message || 'Gagal membuat laporan AI')
  } finally {
    aiSubmitting.value = false
  }
}

function openEdit(row) {
  editingId.value = row.id
  editForm.value = {
    id_outlet: row.id_outlet ? String(row.id_outlet) : '',
    author: row.author || '',
    rating: Number(row.rating || 5),
    review_date: row.review_date || '',
    text: row.text || '',
    profile_photo: row.profile_photo || '',
    is_active: Number(row.is_active) === 1,
  }
  editOpen.value = true
}

async function submitEdit() {
  if (!editingId.value) return
  editSubmitting.value = true
  try {
    await axios.put(`/google-review/manual/${editingId.value}`, editForm.value, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    editOpen.value = false
    await router.reload({ preserveScroll: true })
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal update manual review'
    window.alert(message)
  } finally {
    editSubmitting.value = false
  }
}

async function removeReview(id) {
  if (!window.confirm('Hapus manual review ini?')) return
  try {
    await axios.delete(`/google-review/manual/${id}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    await router.reload({ preserveScroll: true })
  } catch (error) {
    const message = error?.response?.data?.message || 'Gagal menghapus manual review'
    window.alert(message)
  }
}
</script>

<style scoped>
.page { max-width: 1100px; margin: 0 auto; padding: 18px 16px 36px; }
.head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
h1 { margin: 0; font-size: 22px; font-weight: 700; }
p { margin: 4px 0 0; color: #6b7280; font-size: 13px; }
.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; margin-bottom: 12px; }
.grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
input, select, textarea { border: 1px solid #d1d5db; border-radius: 10px; padding: 10px 12px; width: 100%; }
textarea { grid-column: span 3; resize: vertical; min-height: 90px; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; vertical-align: top; }
.text { max-width: 460px; white-space: pre-wrap; word-break: break-word; }
.btn { border: 1px solid transparent; border-radius: 10px; padding: 9px 12px; font-weight: 600; cursor: pointer; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-outline { background: #fff; border-color: #d1d5db; color: #111827; text-decoration: none; }
.btn-ai { background: linear-gradient(135deg, #6366f1, #7c3aed); color: #fff; }
.btn-danger { background: #dc2626; color: #fff; }
.btn-sm { padding: 6px 10px; font-size: 12px; }
.row { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
.action-row { display: flex; gap: 6px; }
.modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); display: flex; align-items: center; justify-content: center; z-index: 100; padding: 16px; }
.modal-card { width: min(860px, 96vw); background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 14px; }
.modal-actions { grid-column: span 3; display: flex; justify-content: flex-end; gap: 8px; }
@media (max-width: 900px) { .grid { grid-template-columns: 1fr; } textarea { grid-column: span 1; } }
</style>

