<template>
  <div class="space-y-6 mb-6">
    <!-- Q&A Section -->
    <div class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-purple-500">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
          <span class="text-2xl">💬</span>
          Tanya AI tentang Dashboard
        </h3>
      </div>
      
      <!-- Question Input -->
      <div class="mb-4">
        <div class="flex gap-2">
          <input
            v-model="question"
            @keyup.enter="askQuestion"
            type="text"
            placeholder="Contoh: Kenapa revenue hari ini turun? Item apa yang paling menguntungkan?"
            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            :disabled="qaLoading"
          />
          <button
            @click="askQuestion"
            :disabled="qaLoading || !question || question.trim() === ''"
            class="px-6 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
          >
            <i class="fa-solid fa-paper-plane" :class="{ 'fa-spin': qaLoading }"></i>
            {{ qaLoading ? 'Mencari...' : 'Tanya' }}
          </button>
        </div>
        <p class="text-xs text-gray-500 mt-2">
          <i class="fa-solid fa-lightbulb mr-1"></i>
          Contoh: "Kenapa revenue turun?", "Item apa yang paling menguntungkan?", "Kapan jam paling sibuk?"
        </p>
      </div>
      
      <!-- Answer Display -->
      <div v-if="qaLoading" class="text-center py-8">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500"></div>
        <p class="mt-2 text-gray-600">Mencari jawaban...</p>
      </div>
      
      <div v-else-if="qaError" class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center gap-2 text-red-800">
          <i class="fa-solid fa-exclamation-circle"></i>
          <p>{{ qaError }}</p>
        </div>
      </div>
      
      <div v-else-if="answer" class="bg-gradient-to-r from-purple-50 to-pink-50 rounded-lg p-6 border-l-4 border-purple-500">
        <div class="mb-3">
          <p class="text-sm font-semibold text-gray-700 mb-2">
            <i class="fa-solid fa-question-circle mr-2"></i>
            Pertanyaan:
          </p>
          <p class="text-gray-800 font-medium">{{ question }}</p>
        </div>
        <div class="prose prose-sm max-w-none">
          <p class="text-sm font-semibold text-gray-700 mb-2">
            <i class="fa-solid fa-robot mr-2"></i>
            Jawaban:
          </p>
          <div class="whitespace-pre-line text-gray-700 leading-relaxed text-sm bg-white p-4 rounded-lg border border-gray-200">
            {{ answer }}
          </div>
        </div>
        <div class="mt-4 text-xs text-gray-500 flex items-center gap-2">
          <i class="fa-solid fa-clock"></i>
          <span>Dijawab: {{ qaLastUpdated }}</span>
        </div>
      </div>
      
      <div v-else class="text-center py-8 text-gray-500">
        <i class="fa-solid fa-comments text-4xl mb-2"></i>
        <p>Masukkan pertanyaan di atas untuk mendapatkan jawaban dari AI</p>
      </div>
    </div>
    
    <!-- Auto Insight Section (Optional - bisa di-hide jika tidak perlu) -->
    <div v-if="showInsight" class="bg-white rounded-lg shadow-lg p-6 border-l-4 border-blue-500">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-bold text-gray-800 flex items-center gap-2">
          <span class="text-2xl">🤖</span>
          AI Insight
        </h3>
        <button
          @click="loadInsight"
          :disabled="loading"
          class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
        >
          <i class="fa-solid fa-refresh" :class="{ 'fa-spin': loading }"></i>
          {{ loading ? 'Loading...' : 'Refresh' }}
        </button>
      </div>
      
      <div v-if="loading && !insight" class="text-center py-8">
        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
        <p class="mt-2 text-gray-600">Menganalisa data...</p>
      </div>
      
      <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="flex items-center gap-2 text-red-800">
          <i class="fa-solid fa-exclamation-circle"></i>
          <p>{{ error }}</p>
        </div>
      </div>
      
      <div v-else-if="insight" class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6 border-l-4 border-blue-500">
        <div class="prose prose-sm max-w-none">
          <div class="whitespace-pre-line text-gray-700 leading-relaxed text-sm">{{ insight }}</div>
        </div>
        <div class="mt-4 text-xs text-gray-500 flex items-center gap-2">
          <i class="fa-solid fa-clock"></i>
          <span>Terakhir diupdate: {{ lastUpdated }}</span>
        </div>
      </div>
      
      <div v-else class="text-center py-8 text-gray-500">
        <i class="fa-solid fa-robot text-4xl mb-2"></i>
        <p>Klik "Refresh" untuk mendapatkan insight AI</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  filters: {
    type: Object,
    required: true
  }
});

// Q&A State
const question = ref('');
const answer = ref(null);
const qaLoading = ref(false);
const qaError = ref(null);
const qaLastUpdated = ref(null);

// Auto Insight State (optional)
const showInsight = ref(false); // Set to true jika ingin tampilkan auto insight
const insight = ref(null);
const loading = ref(false);
const error = ref(null);
const lastUpdated = ref(null);

const loadInsight = async () => {
  loading.value = true;
  error.value = null;
  
  try {
    const response = await axios.get('/sales-outlet-dashboard/ai/insight', {
      params: {
        date_from: props.filters.date_from,
        date_to: props.filters.date_to
      },
      withCredentials: true
    });
    
    if (response.data.success) {
      insight.value = response.data.insight;
      lastUpdated.value = new Date().toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } else {
      error.value = response.data.message || 'Gagal memuat insight';
    }
  } catch (err) {
    console.error('AI Insight Error:', err);
    if (err.response && err.response.data && err.response.data.message) {
      error.value = err.response.data.message;
    } else {
      error.value = 'Gagal memuat insight. Silakan coba lagi nanti.';
    }
  } finally {
    loading.value = false;
  }
};

const askQuestion = async () => {
  if (!question.value || question.value.trim() === '') {
    return;
  }
  
  qaLoading.value = true;
  qaError.value = null;
  answer.value = null;
  
  try {
    const response = await axios.post('/sales-outlet-dashboard/ai/ask', {
      question: question.value,
      date_from: props.filters.date_from,
      date_to: props.filters.date_to
    }, {
      withCredentials: true
    });
    
    if (response.data.success) {
      answer.value = response.data.answer;
      qaLastUpdated.value = new Date().toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
    } else {
      qaError.value = response.data.message || 'Gagal mendapatkan jawaban';
    }
  } catch (err) {
    console.error('AI Q&A Error:', err);
    if (err.response && err.response.data && err.response.data.message) {
      qaError.value = err.response.data.message;
    } else {
      qaError.value = 'Gagal mendapatkan jawaban. Silakan coba lagi nanti.';
    }
  } finally {
    qaLoading.value = false;
  }
};

// Auto load insight saat component mount (jika showInsight = true)
onMounted(() => {
  if (showInsight.value) {
    loadInsight();
  }
});
</script>

<style scoped>
.prose {
  font-size: 14px;
}
</style>

