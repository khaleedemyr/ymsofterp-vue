<template>
  <AppLayout>
    <div class="max-w-2xl mx-auto py-10 px-2">
      <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2 mb-6">
        <i class="fa-brands fa-google text-blue-500"></i> Scrapper Google Review
      </h1>
      <form @submit.prevent="scrap" class="bg-white rounded-xl shadow p-6 mb-6 flex flex-col gap-4">
        <label class="font-semibold text-gray-700">URL Google Maps Bisnis</label>
        <input v-model="url" type="text" class="input input-bordered w-full" placeholder="https://www.google.com/maps/place/..." required />
        <div class="flex gap-2 justify-end">
          <button type="submit" class="btn btn-primary bg-blue-600 hover:bg-blue-700 text-white" :disabled="loading">
            <span v-if="loading" class="animate-spin mr-2"><i class="fa fa-spinner"></i></span>
            Scrap Review
          </button>
        </div>
        <div v-if="error" class="text-red-600">{{ error }}</div>
      </form>
      <div v-if="reviews.length" class="bg-white rounded-xl shadow p-6">
        <h2 class="font-bold text-blue-700 mb-4">Hasil Review (max 10)</h2>
        <div v-for="r in reviews" :key="r.author + r.date" class="border-b last:border-b-0 py-3">
          <div class="flex items-center gap-2 mb-1">
            <span class="font-semibold text-gray-800">{{ r.author }}</span>
            <span class="text-yellow-500"><i class="fa fa-star"></i> {{ r.rating }}</span>
            <span class="text-xs text-gray-400">{{ r.date }}</span>
          </div>
          <div class="text-gray-700">{{ r.text }}</div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import axios from 'axios'

const url = ref('')
const reviews = ref([])
const loading = ref(false)
const error = ref('')

function scrap() {
  error.value = ''
  reviews.value = []
  loading.value = true
  axios.post('/scrapper-google-review/scrap', { url: url.value })
    .then(res => {
      reviews.value = res.data.reviews
      if (!reviews.value.length) error.value = 'Tidak ada review ditemukan.'
    })
    .catch(e => {
      error.value = e.response?.data?.error || 'Gagal scrap review.'
    })
    .finally(() => loading.value = false)
}
</script>

<style scoped>
.input {
  @apply border rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-300;
}
.btn {
  @apply px-4 py-2 rounded font-semibold shadow transition;
}
.btn-primary {
  @apply bg-blue-600 text-white hover:bg-blue-700;
}
</style> 