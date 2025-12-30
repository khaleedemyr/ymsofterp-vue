<template>
  <div class="space-y-6 mb-6">
    <!-- Q&A Chat Section -->
    <div class="bg-gradient-to-br from-white via-purple-50/30 to-white rounded-2xl shadow-2xl border border-purple-100/50 flex flex-col overflow-hidden backdrop-blur-sm" style="height: 700px;">
      <!-- Modern Header with Gradient -->
      <div class="relative bg-gradient-to-r from-purple-600 via-purple-500 to-indigo-600 p-5 border-b border-purple-300/30">
        <div class="absolute inset-0 bg-gradient-to-r from-purple-600/90 to-indigo-600/90 backdrop-blur-sm"></div>
        <div class="relative flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="relative">
              <div class="absolute inset-0 bg-purple-400 rounded-full blur-lg opacity-50 animate-pulse"></div>
              <div class="relative bg-gradient-to-br from-purple-500 to-indigo-600 p-3 rounded-2xl shadow-lg">
                <span class="text-2xl">🤖</span>
              </div>
            </div>
            <div>
              <h3 class="text-xl font-bold text-white flex items-center gap-2 drop-shadow-lg">
                Tanya YMSoft AI
              </h3>
              <p class="text-xs text-purple-100 flex items-center gap-1 mt-0.5">
                <i class="fa-solid fa-bolt text-yellow-300"></i>
                Powered By Claude Sonnet
              </p>
            </div>
          </div>
          <button
            @click="clearChat"
            :disabled="chatHistory.length === 0"
            class="relative px-4 py-2 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white rounded-xl disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-300 flex items-center gap-2 text-sm font-medium border border-white/30 shadow-lg hover:shadow-xl transform hover:scale-105"
            title="Hapus semua chat"
          >
            <i class="fa-solid fa-trash"></i>
            <span class="hidden sm:inline">Hapus Chat</span>
          </button>
        </div>
      </div>
      
      <!-- Chat History Container with Modern Scrollbar -->
      <div ref="chatContainer" class="flex-1 overflow-y-auto p-6 space-y-5 bg-gradient-to-b from-gray-50/50 to-white" style="scrollbar-width: thin; scrollbar-color: rgba(147, 51, 234, 0.3) transparent;">
        <!-- Debug Info (temporary) -->
        <div v-if="false" class="text-xs text-gray-500 p-2 bg-yellow-50 rounded mb-2">
          Debug: chatHistory.length = {{ chatHistory.length }}, 
          qaLoading = {{ qaLoading }}, 
          sessionId = {{ sessionId }}
        </div>
        
        <!-- Empty State -->
        <div v-if="chatHistory.length === 0 && !qaLoading" class="flex flex-col items-center justify-center h-full text-gray-400">
          <div class="relative mb-6">
            <div class="absolute inset-0 bg-purple-200 rounded-full blur-2xl opacity-30 animate-pulse"></div>
            <div class="relative bg-gradient-to-br from-purple-100 to-indigo-100 p-8 rounded-3xl shadow-xl">
              <i class="fa-solid fa-comments text-6xl text-purple-500"></i>
            </div>
          </div>
          <p class="text-xl font-semibold text-gray-600 mb-2">Mulai percakapan dengan AI</p>
          <p class="text-sm text-gray-500">Masukkan pertanyaan di bawah untuk mendapatkan jawaban yang cerdas</p>
        </div>
        
        <!-- Chat Messages -->
        <div
          v-for="(chat, index) in chatHistory"
          :key="chat.id || index"
          class="space-y-4 animate-fade-in"
        >
          <!-- User Question -->
          <div class="flex justify-end" :data-chat-id="chat.id">
            <div class="max-w-[85%] bg-gradient-to-br from-purple-600 to-indigo-600 text-white rounded-2xl rounded-tr-sm px-5 py-4 shadow-xl transform hover:scale-[1.02] transition-transform duration-200">
              <div class="flex items-center gap-2 mb-2">
                <div class="w-6 h-6 bg-white/20 rounded-full flex items-center justify-center">
                  <i class="fa-solid fa-user text-xs"></i>
                </div>
                <p class="text-xs font-semibold opacity-90">Anda</p>
              </div>
              <p class="text-sm whitespace-pre-line leading-relaxed">{{ chat.question }}</p>
              <p class="text-xs opacity-70 mt-3 flex items-center gap-1">
                <i class="fa-solid fa-clock text-xs"></i>
                {{ chat.created_at_formatted }}
              </p>
            </div>
          </div>
          
          <!-- AI Answer -->
          <div class="flex justify-start">
            <div class="max-w-[85%] bg-white border border-gray-200 rounded-2xl rounded-tl-sm px-5 py-4 shadow-xl hover:shadow-2xl transition-all duration-200 relative overflow-hidden">
              <!-- Decorative gradient line -->
              <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-purple-500 to-indigo-500"></div>
              
              <div class="flex items-center gap-2 mb-3">
                <div class="relative">
                  <div class="absolute inset-0 bg-purple-200 rounded-full blur-md opacity-50"></div>
                  <div class="relative bg-gradient-to-br from-purple-500 to-indigo-600 p-2 rounded-xl">
                    <i class="fa-solid fa-robot text-white text-sm"></i>
                  </div>
                </div>
                <div>
                  <p class="text-sm font-semibold text-gray-800">YMSoft AI</p>
                  <p class="text-xs text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-bolt text-yellow-500 text-xs"></i>
                    Claude Sonnet
                  </p>
                </div>
              </div>
              
              <div class="text-sm text-gray-700 whitespace-pre-line leading-relaxed prose prose-sm max-w-none" v-html="formatAnswer(chat.answer)"></div>
              
              <p class="text-xs text-gray-400 mt-4 flex items-center gap-1 pt-3 border-t border-gray-100">
                <i class="fa-solid fa-clock text-xs"></i>
                {{ chat.created_at_formatted }}
              </p>
            </div>
          </div>
        </div>
        
        <!-- Loading Indicator with Thinking Animation -->
        <div v-if="qaLoading" class="flex justify-start animate-fade-in">
          <div class="max-w-[85%] bg-white border border-gray-200 rounded-2xl rounded-tl-sm px-5 py-4 shadow-xl hover:shadow-2xl transition-all duration-200 relative overflow-hidden">
            <!-- Decorative gradient line -->
            <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-purple-500 to-indigo-500"></div>
            
            <div class="flex items-center gap-3">
              <div class="relative">
                <div class="absolute inset-0 bg-purple-200 rounded-full blur-md opacity-50 animate-pulse"></div>
                <div class="relative bg-gradient-to-br from-purple-500 to-indigo-600 p-2 rounded-xl">
                  <i class="fa-solid fa-robot text-white text-sm"></i>
                </div>
              </div>
              <div class="flex-1">
                <div class="flex items-center gap-2 mb-1">
                  <p class="text-sm font-semibold text-gray-800">YMSoft AI</p>
                  <span class="text-xs text-gray-500 flex items-center gap-1">
                    <i class="fa-solid fa-bolt text-yellow-500 text-xs"></i>
                    Claude Sonnet
                  </span>
                </div>
                <!-- Typing Animation -->
                <div class="flex items-center gap-1">
                  <span class="text-sm text-gray-600 font-medium">Sedang memikirkan</span>
                  <div class="flex gap-1 ml-1">
                    <span class="w-2 h-2 bg-purple-500 rounded-full animate-bounce" style="animation-delay: 0s;"></span>
                    <span class="w-2 h-2 bg-indigo-500 rounded-full animate-bounce" style="animation-delay: 0.2s;"></span>
                    <span class="w-2 h-2 bg-purple-500 rounded-full animate-bounce" style="animation-delay: 0.4s;"></span>
                  </div>
                </div>
                <!-- Thinking waves animation -->
                <div class="mt-2 flex items-center gap-1">
                  <div class="flex-1 h-1 bg-gradient-to-r from-purple-200 via-purple-400 to-purple-200 rounded-full overflow-hidden relative">
                    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/80 to-transparent" style="animation: shimmer 1.5s ease-in-out infinite;"></div>
                  </div>
                </div>
                <p class="text-xs text-gray-500 mt-2 flex items-center gap-1">
                  <i class="fa-solid fa-brain text-purple-500 animate-pulse"></i>
                  <span>Menganalisis data dashboard...</span>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Error Display -->
      <div v-if="qaError" class="mx-6 mb-4 bg-gradient-to-r from-red-50 to-pink-50 border border-red-200 rounded-xl p-4 shadow-lg animate-shake">
        <div class="flex items-center gap-3 text-red-800">
          <div class="bg-red-100 p-2 rounded-lg">
            <i class="fa-solid fa-exclamation-circle text-red-600"></i>
          </div>
          <p class="text-sm font-medium">{{ qaError }}</p>
        </div>
      </div>
      
      <!-- Modern Question Input -->
      <div class="p-5 bg-gradient-to-r from-white via-purple-50/30 to-white border-t border-purple-100/50 backdrop-blur-sm">
        <div class="flex gap-3">
          <div class="flex-1 relative">
            <div class="absolute inset-0 bg-gradient-to-r from-purple-100/50 to-indigo-100/50 rounded-2xl blur-sm"></div>
            <input
              v-model="question"
              @keyup.enter.prevent="askQuestion"
              @keydown.enter.prevent
              @submit.prevent
              type="text"
              placeholder="Tanyakan sesuatu tentang dashboard..."
              class="relative w-full px-5 py-3.5 bg-white border-2 border-purple-200 rounded-2xl focus:outline-none focus:ring-4 focus:ring-purple-300/50 focus:border-purple-400 transition-all duration-300 text-sm shadow-lg hover:shadow-xl"
              :disabled="qaLoading"
            />
          </div>
          <button
            type="button"
            @click="askQuestion"
            :disabled="qaLoading || !question || question.trim() === ''"
            class="relative px-6 py-3.5 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-2xl hover:from-purple-700 hover:to-indigo-700 disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed transition-all duration-300 flex items-center gap-2 font-semibold shadow-lg hover:shadow-xl transform hover:scale-105 disabled:transform-none group"
          >
            <div class="absolute inset-0 bg-white/20 rounded-2xl blur-md opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
            <i class="fa-solid fa-paper-plane relative z-10" :class="{ 'fa-spin': qaLoading }"></i>
            <span class="relative z-10 hidden sm:inline">{{ qaLoading ? 'Mengirim...' : 'Kirim' }}</span>
          </button>
        </div>
        <div class="flex items-center justify-between mt-3">
          <p class="text-xs text-gray-500 flex items-center gap-1.5">
            <i class="fa-solid fa-lightbulb text-yellow-500"></i>
            <span class="hidden sm:inline">Contoh: </span>
            <span class="text-gray-400">"Kenapa revenue turun?", "Item apa yang paling menguntungkan?"</span>
          </p>
          <div class="flex items-center gap-2 text-xs text-gray-400 bg-gray-50 px-3 py-1.5 rounded-full border border-gray-200">
            <i class="fa-solid fa-bolt text-yellow-500"></i>
            <span class="font-medium">Powered By Claude Sonnet</span>
          </div>
        </div>
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
import { ref, onMounted, nextTick, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  filters: {
    type: Object,
    required: true
  }
});

// Session ID untuk chat history
const sessionId = ref(null);

// Q&A Chat State
const question = ref('');
const chatHistory = ref([]);
const qaLoading = ref(false);
const qaError = ref(null);
const chatContainer = ref(null);

// Auto Insight State (optional)
const showInsight = ref(false); // Set to true jika ingin tampilkan auto insight
const insight = ref(null);
const loading = ref(false);
const error = ref(null);
const lastUpdated = ref(null);

// Format answer untuk markdown rendering
const formatAnswer = (text) => {
  if (!text) return '';
  
  // Convert markdown headers
  text = text.replace(/^### (.*$)/gim, '<h3 class="text-lg font-bold text-gray-800 mt-4 mb-2">$1</h3>');
  text = text.replace(/^## (.*$)/gim, '<h2 class="text-xl font-bold text-gray-900 mt-6 mb-3 border-b border-gray-200 pb-2">$1</h2>');
  text = text.replace(/^# (.*$)/gim, '<h1 class="text-2xl font-bold text-gray-900 mt-6 mb-4">$1</h1>');
  
  // Convert bold
  text = text.replace(/\*\*(.*?)\*\*/gim, '<strong class="font-semibold text-gray-900">$1</strong>');
  
  // Convert bullet points
  text = text.replace(/^\- (.*$)/gim, '<li class="ml-4 mb-1">$1</li>');
  text = text.replace(/^(\d+)\. (.*$)/gim, '<li class="ml-4 mb-1 list-decimal">$2</li>');
  
  // Wrap lists
  text = text.replace(/(<li.*<\/li>)/gim, '<ul class="list-disc space-y-1 my-2">$1</ul>');
  
  // Convert line breaks
  text = text.replace(/\n/gim, '<br>');
  
  return text;
};

// Get or create session ID
const getOrCreateSessionId = () => {
  if (!sessionId.value) {
    // Cek dari cookie atau localStorage
    const storedSessionId = localStorage.getItem('ai_chat_session_id');
    if (storedSessionId) {
      sessionId.value = storedSessionId;
    } else {
      // Generate new session ID
      sessionId.value = 'ai_chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
      localStorage.setItem('ai_chat_session_id', sessionId.value);
    }
  }
  return sessionId.value;
};

// Load chat history
const loadChatHistory = async () => {
  try {
    console.log('=== Loading Chat History ===');
    
    // Strategy: Load tanpa session_id untuk mendapatkan semua chat user
    // Backend akan otomatis mencari session_id terbaru atau menampilkan semua
    const response = await axios.get('/sales-outlet-dashboard/ai/chat-history', {
      params: {
        session_id: '' // Kosongkan untuk auto-detect dari backend
      },
      withCredentials: true
    });
    
    console.log('=== Chat History Response ===');
    console.log('Full response:', JSON.stringify(response.data, null, 2));
    console.log('Success:', response.data.success);
    console.log('History type:', typeof response.data.history);
    console.log('History is array:', Array.isArray(response.data.history));
    console.log('History count:', response.data.history ? response.data.history.length : 0);
    
    if (response.data.success) {
      const historyData = response.data.history || [];
      
      // Pastikan format data benar - harus array
      if (Array.isArray(historyData)) {
        chatHistory.value = historyData;
        console.log('✅ Chat history loaded successfully:', chatHistory.value.length, 'items');
        
        // Log first item untuk debugging
        if (chatHistory.value.length > 0) {
          console.log('First chat item:', chatHistory.value[0]);
        }
      } else {
        console.error('❌ History is not an array:', historyData);
        chatHistory.value = [];
      }
      
      // Update session_id dari server jika ada
      if (response.data.session_id) {
        sessionId.value = response.data.session_id;
        localStorage.setItem('ai_chat_session_id', response.data.session_id);
        console.log('✅ Updated session_id to:', response.data.session_id);
      }
      
      // Force Vue reactivity update
      await nextTick();
      scrollToBottom();
    } else {
      console.error('❌ Failed to load chat history:', response.data.message);
      chatHistory.value = [];
    }
  } catch (err) {
    console.error('❌ Load Chat History Error:', err);
    if (err.response) {
      console.error('Error response:', err.response.data);
      console.error('Error status:', err.response.status);
    }
    if (err.request) {
      console.error('Error request:', err.request);
    }
    chatHistory.value = [];
  }
};

// Clear chat history
const clearChat = async () => {
  if (!confirm('Apakah Anda yakin ingin menghapus semua chat?')) {
    return;
  }
  
  try {
    const currentSessionId = getOrCreateSessionId();
    await axios.delete('/sales-outlet-dashboard/ai/chat-history', {
      params: {
        session_id: currentSessionId
      },
      withCredentials: true
    });
    
    chatHistory.value = [];
    qaError.value = null;
  } catch (err) {
    console.error('Clear Chat Error:', err);
    qaError.value = 'Gagal menghapus chat history';
  }
};

// Scroll to bottom of chat
const scrollToBottom = () => {
  nextTick(() => {
    if (chatContainer.value) {
      chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    }
  });
};

// Scroll to specific question (scroll ke pertanyaan user, bukan ke bawah)
const scrollToQuestion = (chatId) => {
  nextTick(() => {
    if (chatContainer.value && chatId) {
      // Cari element pertanyaan user berdasarkan chatId
      const questionElement = chatContainer.value.querySelector(`[data-chat-id="${chatId}"]`);
      if (questionElement) {
        // Scroll ke pertanyaan user dengan offset ke atas agar user bisa baca dari awal
        questionElement.scrollIntoView({
          behavior: 'smooth',
          block: 'start',
          inline: 'nearest'
        });
        
        // Tambahkan sedikit offset ke atas setelah scroll
        setTimeout(() => {
          if (chatContainer.value) {
            chatContainer.value.scrollTop = Math.max(0, chatContainer.value.scrollTop - 30);
          }
        }, 300);
      }
      // Jika element tidak ditemukan, tidak perlu scroll (biarkan user di posisi sekarang)
    }
  });
};

// Watch chat history untuk auto scroll - DISABLED
// watch(chatHistory, () => {
//   scrollToBottom();
// }, { deep: true });

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

const askQuestion = async (event) => {
  // Prevent default form submission jika ada event
  if (event) {
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
  }
  
  if (!question.value || question.value.trim() === '') {
    return;
  }
  
  const currentQuestion = question.value.trim();
  const currentSessionId = getOrCreateSessionId();
  
  // Clear input
  question.value = '';
  
  qaLoading.value = true;
  qaError.value = null;
  
  try {
    // Pastikan menggunakan POST method secara eksplisit
    // Ambil CSRF token dari meta tag
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    const requestHeaders = {
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    };
    
    // Tambahkan CSRF token jika ada
    if (csrfToken) {
      requestHeaders['X-CSRF-TOKEN'] = csrfToken;
    }
    
    const requestData = {
      question: currentQuestion,
      date_from: props.filters.date_from,
      date_to: props.filters.date_to,
      session_id: currentSessionId
    };
    
    // Log request config untuk debugging
    console.log('🔵 AI Q&A Request Config (Before Axios):', {
      method: 'POST',
      url: '/sales-outlet-dashboard/ai/ask',
      hasData: !!requestData,
      dataKeys: Object.keys(requestData),
      hasCsrfToken: !!csrfToken,
      headers: requestHeaders
    });
    
    // Gunakan axios.post() langsung untuk memastikan method POST
    const response = await axios.post(
      '/sales-outlet-dashboard/ai/ask',
      requestData,
      {
        headers: requestHeaders,
        withCredentials: true,
        maxRedirects: 0,
        validateStatus: function (status) {
          return status >= 200 && status < 300;
        }
      }
    );
    
    console.log('🟢 AI Q&A Response received:', {
      status: response.status,
      success: response.data?.success
    });
    
    if (response.data.success) {
      // Update session ID if returned
      if (response.data.session_id) {
        sessionId.value = response.data.session_id;
        localStorage.setItem('ai_chat_session_id', response.data.session_id);
      }
      
      // Add to chat history
      const newChat = {
        id: response.data.chat_id,
        question: currentQuestion,
        answer: response.data.answer,
        date_from: response.data.date_from,
        date_to: response.data.date_to,
        created_at: new Date().toISOString(),
        created_at_formatted: new Date().toLocaleString('id-ID', {
          day: 'numeric',
          month: 'short',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        })
      };
      
      chatHistory.value.push(newChat);
      
      // Scroll ke posisi pertanyaan user (bukan ke paling bawah)
      // Biarkan user baca jawaban dari awal tanpa harus scroll ke atas
      await nextTick();
      scrollToQuestion(newChat.id);
    } else {
      qaError.value = response.data.message || 'Gagal mendapatkan jawaban';
    }
  } catch (err) {
    console.error('AI Q&A Error:', err);
    console.error('Error details:', {
      message: err.message,
      response: err.response,
      request: err.config,
      status: err.response?.status,
      statusText: err.response?.statusText
    });
    
    if (err.response && err.response.data && err.response.data.message) {
      qaError.value = err.response.data.message;
      
      // Jika error 405 (Method Not Allowed), beri pesan yang lebih jelas
      if (err.response.status === 405) {
        qaError.value = 'Error: Request menggunakan method yang salah. Silakan refresh halaman dan coba lagi.';
        console.error('Method error detected - request config:', err.config);
      }
    } else {
      qaError.value = 'Gagal mendapatkan jawaban. Silakan coba lagi nanti.';
    }
  } finally {
    qaLoading.value = false;
  }
};

// Auto load chat history saat component mount
onMounted(() => {
  loadChatHistory();
  if (showInsight.value) {
    loadInsight();
  }
});
</script>

<style scoped>
/* Custom Scrollbar */
.chat-container::-webkit-scrollbar {
  width: 8px;
}

.chat-container::-webkit-scrollbar-track {
  background: transparent;
}

.chat-container::-webkit-scrollbar-thumb {
  background: linear-gradient(to bottom, rgba(147, 51, 234, 0.3), rgba(99, 102, 241, 0.3));
  border-radius: 10px;
}

.chat-container::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(to bottom, rgba(147, 51, 234, 0.5), rgba(99, 102, 241, 0.5));
}

/* Animations */
@keyframes fade-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
  20%, 40%, 60%, 80% { transform: translateX(5px); }
}

@keyframes shimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

@keyframes thinking-pulse {
  0%, 100% {
    opacity: 0.4;
  }
  50% {
    opacity: 1;
  }
}

.animate-fade-in {
  animation: fade-in 0.3s ease-out;
}

.animate-shake {
  animation: shake 0.5s ease-in-out;
}

/* Prose styling untuk AI answers */
.prose {
  font-size: 14px;
}

.prose h1, .prose h2, .prose h3 {
  margin-top: 1.5em;
  margin-bottom: 0.5em;
}

.prose ul {
  margin: 1em 0;
  padding-left: 1.5em;
}

.prose li {
  margin: 0.5em 0;
}

.prose strong {
  font-weight: 600;
  color: #1f2937;
}
</style>
