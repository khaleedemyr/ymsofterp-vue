<template>
  <AppLayout>
    <template #default>
      <div class="page">
        <div class="header">
          <div>
            <div class="title">Google Reviews</div>
            <div class="subtitle">Scrape & tampilkan review Google Maps per outlet (pagination + export).</div>
          </div>
        </div>

        <div class="panel">
          <div class="controls">
            <div class="control">
              <div class="label">Outlet</div>
              <select v-model="selectedOutlet" class="select" required>
                <option value="" disabled>Pilih Outlet</option>
                <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.place_id">
                  {{ outlet.nama_outlet }}
                </option>
              </select>
            </div>

            <div class="control">
              <div class="label">Per halaman</div>
              <select v-model.number="perPage" class="select" :disabled="!datasetId || loadingItems">
                <option :value="10">10</option>
                <option :value="20">20</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
                <option :value="200">200</option>
              </select>
            </div>

            <div class="actions">
              <button type="button" class="btn btn-primary" :disabled="!selectedOutlet || loading" @click="fetchReviews">
                Ambil Review API
              </button>
              <button type="button" class="btn btn-success" :disabled="loading" @click="fetchScrapedReviews">
                Ambil Review Scraper
              </button>
              <button type="button" class="btn btn-apify" :disabled="!selectedOutlet || loading" @click="fetchApifyReviews">
                Ambil Review Apify
              </button>
              <button
                v-if="datasetId"
                type="button"
                class="btn btn-outline"
                :disabled="exporting"
                @click="exportExcel"
              >
                {{ exporting ? 'Exporting…' : 'Export Excel (CSV)' }}
              </button>
            </div>
          </div>

          <div v-if="loading" class="notice notice-loading">
            <span class="spinner" aria-hidden="true"></span>
            <span>Proses scrape berjalan…</span>
          </div>
          <div v-if="error" class="notice notice-error">{{ error }}</div>
        </div>

        <div v-if="placeInfo" class="panel place">
          <div class="place-title">{{ placeInfo.name }}</div>
          <div class="place-meta">
            <div v-if="placeInfo.address" class="muted">Alamat: {{ placeInfo.address }}</div>
            <div v-if="placeInfo.rating" class="muted">Rating: {{ placeInfo.rating }}</div>
            <div v-if="meta.total" class="muted">Total review: {{ meta.total }}</div>
            <div v-if="datasetId" class="muted">Dataset: {{ datasetId }}</div>
          </div>
        </div>

        <div v-if="reviews.length" class="panel">
          <div class="table-head">
            <div class="table-title">Reviews</div>
            <div class="pager">
              <button type="button" class="pager-btn" :disabled="meta.page <= 1 || loadingItems" @click="goToPage(meta.page - 1)">
                Prev
              </button>
              <div class="pager-info">
                Hal {{ meta.page }} / {{ meta.lastPage }}
              </div>
              <button type="button" class="pager-btn" :disabled="meta.page >= meta.lastPage || loadingItems" @click="goToPage(meta.page + 1)">
                Next
              </button>
            </div>
          </div>

          <div v-if="loadingItems" class="notice notice-loading">
            <span class="spinner" aria-hidden="true"></span>
            <span>Loading halaman review…</span>
          </div>

          <div class="review-list">
            <div v-for="review in reviews" :key="reviewKey(review)" class="review-card">
              <div class="review-header">
                <div class="avatar">
                  <img v-if="review.profile_photo" :src="review.profile_photo" alt="profile" />
                  <div v-else class="avatar-fallback">{{ initials(review.author) }}</div>
                </div>
                <div class="info">
                  <div class="top">
                    <div class="author">{{ review.author || '-' }}</div>
                    <div class="rating">
                      <span v-for="n in 5" :key="n" class="star" :class="{ off: n > Math.floor(Number(review.rating) || 0) }">
                        ★
                      </span>
                      <span class="rating-num">{{ review.rating ? `(${review.rating})` : '' }}</span>
                    </div>
                  </div>
                  <div class="date">{{ review.date }}</div>
                </div>
              </div>
              <div class="review-text">{{ review.text || '-' }}</div>
            </div>
          </div>

          <div class="table-foot">
            <div class="muted">
              Menampilkan {{ reviews.length }} item (per halaman: {{ meta.perPage }}).
            </div>
            <div class="pager">
              <button type="button" class="pager-btn" :disabled="meta.page <= 1 || loadingItems" @click="goToPage(1)">
                First
              </button>
              <button type="button" class="pager-btn" :disabled="meta.page >= meta.lastPage || loadingItems" @click="goToPage(meta.lastPage)">
                Last
              </button>
            </div>
          </div>
        </div>

        <div v-else-if="datasetId && !loadingItems" class="panel empty">
          Belum ada data review untuk ditampilkan.
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const page = usePage()
const outlets = page.props.outlets || []
const selectedOutlet = ref('')
const loading = ref(false)
const error = ref('')
const result = ref(page.props.result || null)

const datasetId = ref('')
const placeInfo = ref(null)
const reviews = ref([])
const meta = ref({ page: 1, perPage: 20, total: 0, lastPage: 1 })
const perPage = ref(20)
const loadingItems = ref(false)
const exporting = ref(false)

watch(
  () => page.props.result,
  (val) => {
    result.value = val
    loading.value = false

    if (val && val.success && val.dataset_id) {
      datasetId.value = val.dataset_id
      placeInfo.value = val.place || null
      meta.value = {
        page: 1,
        perPage: perPage.value,
        total: Number(val.item_count || 0),
        lastPage: Math.max(1, Math.ceil(Number(val.item_count || 0) / perPage.value)),
      }
      goToPage(1)
      return
    }

    // API / Scraper legacy path still sets place+reviews directly.
    if (val && val.success && val.reviews) {
      datasetId.value = ''
      placeInfo.value = val.place || null
      reviews.value = Array.isArray(val.reviews) ? val.reviews : []
      meta.value = { page: 1, perPage: reviews.value.length || perPage.value, total: reviews.value.length || 0, lastPage: 1 }
    }
  }
)

function fetchReviews() {
  loading.value = true
  error.value = ''
  router.post(
    '/google-review/fetch',
    { place_id: selectedOutlet.value },
    {
      preserveState: true,
      onError: (err) => {
        error.value = err.error || 'Terjadi kesalahan'
        loading.value = false
      }
    }
  )
}

function fetchScrapedReviews() {
  loading.value = true
  error.value = ''
  fetch('/scraped-reviews')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        datasetId.value = ''
        placeInfo.value = { name: 'Review Scraper', address: '', rating: '' }
        reviews.value = Array.isArray(data.reviews) ? data.reviews : []
        meta.value = { page: 1, perPage: reviews.value.length || perPage.value, total: reviews.value.length || 0, lastPage: 1 }
      } else {
        error.value = data.error || 'Gagal ambil review scraper'
      }
      loading.value = false
    })
    .catch(() => {
      error.value = 'Gagal ambil review scraper'
      loading.value = false
    })
}

function fetchApifyReviews() {
  loading.value = true
  error.value = ''
  router.post(
    '/google-review/fetch-apify',
    { place_id: selectedOutlet.value, max_reviews: 500 },
    {
      preserveState: true,
      onError: (err) => {
        error.value = err.error || 'Gagal ambil review Apify'
        loading.value = false
      }
    }
  )
}

watch(perPage, () => {
  if (!datasetId.value) return
  goToPage(1)
})

function goToPage(pageNumber) {
  if (!datasetId.value) return
  loadingItems.value = true
  error.value = ''

  const params = new URLSearchParams({
    dataset_id: datasetId.value,
    page: String(pageNumber),
    per_page: String(perPage.value),
  })

  fetch(`/google-review/apify/items?${params.toString()}`)
    .then(res => res.json())
    .then((data) => {
      if (!data.success) {
        error.value = data.error || 'Gagal load items'
        return
      }
      reviews.value = Array.isArray(data.reviews) ? data.reviews : []
      meta.value = data.meta || meta.value
    })
    .catch(() => {
      error.value = 'Gagal load items'
    })
    .finally(() => {
      loadingItems.value = false
    })
}

function exportExcel() {
  if (!datasetId.value) return
  exporting.value = true
  const params = new URLSearchParams({ dataset_id: datasetId.value })
  window.location.href = `/google-review/apify/export?${params.toString()}`
  setTimeout(() => (exporting.value = false), 1500)
}

function reviewKey(review) {
  return `${review.time || ''}:${review.author || ''}:${(review.text || '').slice(0, 20)}`
}

function initials(name) {
  const n = String(name || '').trim()
  if (!n) return '?'
  const parts = n.split(/\s+/).slice(0, 2)
  return parts.map(p => p.charAt(0).toUpperCase()).join('')
}
</script>

<style scoped>
.page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 18px 16px 40px;
}
.header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  margin-bottom: 14px;
}
.title {
  font-size: 22px;
  font-weight: 700;
  color: #111827;
}
.subtitle {
  margin-top: 2px;
  font-size: 13px;
  color: #6b7280;
}
.panel {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 14px;
  box-shadow: 0 2px 12px rgba(17, 24, 39, 0.04);
  margin-bottom: 12px;
}
.controls {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.control {
  min-width: 280px;
}
.label {
  font-size: 12px;
  color: #6b7280;
  margin-bottom: 6px;
}
.select {
  width: 100%;
  border: 1px solid #d1d5db;
  border-radius: 10px;
  padding: 10px 12px;
  background: #fff;
  outline: none;
}
.select:focus {
  border-color: #93c5fd;
  box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
}
.actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.btn {
  border: 1px solid transparent;
  border-radius: 10px;
  padding: 10px 12px;
  font-weight: 600;
  cursor: pointer;
  transition: transform 0.05s ease, opacity 0.2s ease, box-shadow 0.2s ease;
  user-select: none;
}
.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.btn:active:not(:disabled) {
  transform: translateY(1px);
}
.btn-primary {
  background: #2563eb;
  color: #fff;
  box-shadow: 0 6px 18px rgba(37, 99, 235, 0.18);
}
.btn-success {
  background: #059669;
  color: #fff;
  box-shadow: 0 6px 18px rgba(5, 150, 105, 0.16);
}
.btn-apify {
  background: #7c3aed;
  color: #fff;
  box-shadow: 0 6px 18px rgba(124, 58, 237, 0.16);
}
.btn-outline {
  background: #fff;
  border-color: #d1d5db;
  color: #111827;
}
.notice {
  margin-top: 12px;
  font-size: 13px;
  color: #374151;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 10px 12px;
}
.notice-loading {
  display: flex;
  align-items: center;
  gap: 10px;
}
.spinner {
  width: 16px;
  height: 16px;
  border-radius: 999px;
  border: 2px solid rgba(17, 24, 39, 0.18);
  border-top-color: rgba(37, 99, 235, 0.9);
  animation: spin 0.85s linear infinite;
  flex: 0 0 auto;
}
@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
.notice-error {
  background: #fef2f2;
  border-color: #fecaca;
  color: #991b1b;
}
.place-title {
  font-size: 16px;
  font-weight: 700;
  color: #111827;
}
.place-meta {
  margin-top: 6px;
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}
.muted {
  font-size: 12px;
  color: #6b7280;
}
.table-head,
.table-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}
.table-title {
  font-weight: 700;
  color: #111827;
}
.pager {
  display: flex;
  align-items: center;
  gap: 8px;
}
.pager-btn {
  border: 1px solid #d1d5db;
  background: #fff;
  border-radius: 10px;
  padding: 8px 10px;
  font-weight: 600;
  cursor: pointer;
}
.pager-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.pager-info {
  font-size: 12px;
  color: #6b7280;
  min-width: 92px;
  text-align: center;
}
.review-list {
  margin-top: 12px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.review-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 12px 14px;
}
.review-header {
  display: flex;
  gap: 12px;
  align-items: center;
}
.avatar {
  width: 44px;
  height: 44px;
  border-radius: 999px;
  overflow: hidden;
  border: 1px solid #e5e7eb;
  background: #f3f4f6;
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: center;
}
.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.avatar-fallback {
  font-weight: 800;
  color: #374151;
  font-size: 14px;
}
.info {
  min-width: 0;
  flex: 1;
}
.top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}
.author {
  font-weight: 800;
  color: #111827;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.rating {
  display: flex;
  align-items: center;
  gap: 2px;
  color: #f59e0b;
  font-size: 12px;
  flex: 0 0 auto;
}
.star.off {
  color: #e5e7eb;
}
.rating-num {
  color: #6b7280;
  margin-left: 6px;
  font-size: 12px;
}
.date {
  margin-top: 2px;
  font-size: 12px;
  color: #6b7280;
}
.review-text {
  margin-top: 10px;
  font-size: 13px;
  color: #111827;
  line-height: 1.45;
  white-space: pre-wrap;
  word-break: break-word;
}
.empty {
  color: #6b7280;
  font-size: 13px;
}
</style> 