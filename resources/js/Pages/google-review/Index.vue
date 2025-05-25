<template>
  <AppLayout>
    <template #default>
      <div>
        <h1>Ambil Review Google Maps</h1>
        <form @submit.prevent="fetchReviews">
          <select v-model="selectedOutlet" class="input" required>
            <option value="" disabled>Pilih Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id" :value="outlet.place_id">
              {{ outlet.nama_outlet }}
            </option>
          </select>
          <button type="submit" class="btn">Ambil Review API</button>
          <button type="button" class="btn btn-scraper" @click="fetchScrapedReviews">Ambil Review Scraper</button>
        </form>

        <div v-if="loading" class="mt-4">Loading...</div>
        <div v-if="error" class="mt-4 text-red-500">{{ error }}</div>

        <div v-if="result && result.success" class="mt-6">
          <h2 class="font-bold text-lg mb-2">{{ result.place.name }}</h2>
          <div class="mb-2">
            <span>Alamat: {{ result.place.address }}</span><br>
            <span v-if="result.place.rating">Rating: {{ result.place.rating }}</span>
          </div>
          <h3 class="font-semibold mb-1">Review:</h3>
          <div class="review-list">
            <div v-for="review in result.reviews" :key="review.time + review.author" class="review-card">
              <div class="review-header">
                <img
                  v-if="review.profile_photo"
                  :src="review.profile_photo"
                  alt="profile"
                  class="review-avatar"
                />
                <div>
                  <span class="review-author">{{ review.author }}</span>
                  <span class="review-rating">
                    <span v-for="n in Math.floor(review.rating)" :key="'f'+n" class="star">★</span>
                    <span v-for="n in 5 - Math.floor(review.rating)" :key="'e'+n" class="star star-empty">☆</span>
                    <span class="review-rating-number">({{ review.rating }})</span>
                  </span>
                  <div class="review-date">{{ review.date }}</div>
                </div>
              </div>
              <div class="review-text">{{ review.text }}</div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const page = usePage()
const outlets = page.props.outlets || []
const selectedOutlet = ref('')
const loading = ref(false)
const error = ref('')
const result = ref(page.props.result || null)

watch(
  () => page.props.result,
  (val) => {
    result.value = val
    loading.value = false
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
        result.value = {
          success: true,
          place: { name: 'Review Scraper', address: '', rating: '' },
          reviews: data.reviews
        }
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
</script>

<style scoped>
.input {
  border: 1px solid #ccc;
  padding: 8px;
  width: 350px;
  margin-right: 8px;
}
.btn {
  background: #2563eb;
  color: #fff;
  padding: 8px 16px;
  border: none;
  cursor: pointer;
  margin-right: 8px;
}
.btn-scraper {
  background: #059669;
}
.review-list {
  margin-top: 1.5rem;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
}
.review-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  box-shadow: 0 2px 8px 0 rgba(0,0,0,0.04);
  padding: 1.2rem 1.5rem;
  display: flex;
  flex-direction: column;
}
.review-header {
  display: flex;
  align-items: center;
  margin-bottom: 0.5rem;
}
.review-avatar {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 1rem;
  border: 1px solid #ddd;
}
.review-author {
  font-weight: bold;
  margin-right: 0.5rem;
}
.review-rating {
  color: #fbbf24;
  font-size: 1.1em;
  margin-left: 0.5rem;
}
.star {
  color: #fbbf24;
}
.star-empty {
  color: #ddd;
}
.review-rating-number {
  color: #888;
  font-size: 0.95em;
  margin-left: 0.2em;
}
.review-date {
  font-size: 0.9em;
  color: #888;
}
.review-text {
  margin-left: 3.5rem;
  font-size: 1.05em;
  color: #222;
}
</style> 