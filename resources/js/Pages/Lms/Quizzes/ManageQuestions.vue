<template>
  <AppLayout>
    <div class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 relative overflow-hidden">
      <!-- Animated Background Elements -->
      <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-purple-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-2000"></div>
        <div class="absolute top-40 left-40 w-80 h-80 bg-pink-500 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-blob animation-delay-4000"></div>
      </div>

      <div class="relative z-10 py-8 px-6">
        <!-- Header Section -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-3xl shadow-2xl mb-8">
          <div class="p-8 bg-gradient-to-r from-blue-600/80 via-purple-600/80 to-pink-600/80 rounded-3xl">
            <div class="flex items-center justify-between">
              <div class="space-y-4">
                <h1 class="text-4xl font-bold text-white drop-shadow-lg">
                  Kelola Pertanyaan Quiz
                </h1>
                <p class="text-xl text-white/90 drop-shadow-md">
                  {{ quiz.title }}
                </p>
              </div>
                                                           <div class="flex items-center space-x-4">
                                   <button
                    @click="showAddQuestionModal = true"
                    class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
                    :disabled="loading"
                  >
                    <i class="fas fa-plus mr-2"></i>
                    Tambah Pertanyaan
                  </button>
                                  <Link
                    :href="route('lms.quizzes.show', quiz.id)"
                    class="bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg"
                    :class="{ 'opacity-50 pointer-events-none': loading }"
                  >
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali ke Quiz
                  </Link>
               </div>
            </div>
          </div>
        </div>

                 <!-- Quiz Statistics -->
         <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 mb-6">
           <h3 class="text-xl font-bold text-white mb-4 flex items-center">
             <i class="fas fa-chart-bar mr-2 text-green-400"></i>
             Statistik Quiz
           </h3>
           
           <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
             <div class="text-center">
               <div class="text-3xl font-bold text-white">{{ quiz.questions_count || 0 }}</div>
               <div class="text-sm text-white/60">Pertanyaan</div>
             </div>
             <div class="text-center">
               <div class="text-3xl font-bold text-white">{{ quiz.attempts_count || 0 }}</div>
               <div class="text-sm text-white/60">Percobaan</div>
             </div>
             <div class="text-center">
               <div class="text-3xl font-bold text-white">{{ quiz.average_score || 0 }}%</div>
               <div class="text-sm text-white/60">Rata-rata Nilai</div>
             </div>
             <div class="text-center">
               <div class="text-3xl font-bold text-white">{{ quiz.pass_rate || 0 }}%</div>
               <div class="text-sm text-white/60">Tingkat Kelulusan</div>
             </div>
           </div>
         </div>

         <!-- Questions List -->
         <div class="space-y-6">
          <div
            v-for="(question, index) in questions"
            :key="question.id"
            class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6"
          >
            <div class="flex items-start justify-between mb-4">
              <div class="flex-1">
                <div class="flex items-center space-x-3 mb-2">
                  <span class="bg-blue-500/20 text-blue-300 px-3 py-1 rounded-full text-sm font-semibold">
                    Pertanyaan {{ index + 1 }}
                  </span>
                  <span
                    :class="{
                      'bg-green-500/20 text-green-300': question.question_type === 'multiple_choice',
                      'bg-purple-500/20 text-purple-300': question.question_type === 'essay',
                      'bg-orange-500/20 text-orange-300': question.question_type === 'true_false'
                    }"
                    class="px-2 py-1 rounded-full text-xs font-semibold"
                  >
                    {{ getQuestionTypeText(question.question_type) }}
                  </span>
                  <span class="bg-yellow-500/20 text-yellow-300 px-2 py-1 rounded-full text-xs font-semibold">
                    {{ question.points }} poin
                  </span>
                </div>
                <h3 class="text-lg font-semibold text-white mb-2">{{ question.question_text }}</h3>
                
                                 <!-- Question Image -->
                 <div v-if="question.image_path" class="mb-3">
                   <img 
                     :src="question.image_url" 
                     :alt="question.image_alt_text || 'Gambar pertanyaan'"
                     class="w-full max-w-md h-auto rounded-lg border border-white/20"
                     @error="handleImageError"
                   />
                                       <p v-if="question.image_alt_text" class="text-white/60 text-sm mt-1">{{ question.image_alt_text }}</p>
                 </div>
              </div>
              <div class="flex items-center space-x-2">
                                 <button
                   @click="editQuestion(question)"
                   class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                   :disabled="loading"
                 >
                   <i class="fas fa-edit mr-1"></i>
                   Edit
                 </button>
                 <button
                   @click="deleteQuestion(question.id)"
                   class="bg-gradient-to-r from-red-500 to-pink-600 hover:from-red-600 hover:to-pink-700 text-white px-3 py-2 rounded-lg text-sm font-semibold transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                   :disabled="loading"
                 >
                   <i class="fas fa-trash mr-1"></i>
                   Hapus
                 </button>
              </div>
            </div>

                         <!-- Options for Multiple Choice -->
             <div v-if="question.question_type === 'multiple_choice' && question.options && question.options.length > 0" class="space-y-3">
               <div
                 v-for="option in question.options"
                 :key="option.id"
                 class="p-3 rounded-lg"
                 :class="option.is_correct ? 'bg-green-500/20 border border-green-500/30' : 'bg-white/5'"
               >
                 <div class="flex items-center space-x-3 mb-2">
                   <div class="w-4 h-4 rounded-full border-2 border-white/30 flex items-center justify-center">
                     <div v-if="option.is_correct" class="w-2 h-2 bg-green-400 rounded-full"></div>
                   </div>
                   <span class="text-white/80 flex-1">{{ option.option_text }}</span>
                   <span v-if="option.is_correct" class="text-green-400 text-xs font-semibold">
                     Jawaban Benar
                   </span>
                 </div>
                 
                 <!-- Option Image -->
                 <div v-if="option.image_path" class="ml-7">
                   <img 
                     :src="option.image_url" 
                     :alt="option.image_alt_text || 'Gambar opsi'"
                     class="w-full max-w-xs h-auto rounded-lg border border-white/20"
                     @error="handleImageError"
                   />
                                       <p v-if="option.image_alt_text" class="text-white/60 text-sm mt-1">{{ option.image_alt_text }}</p>
                 </div>
               </div>
             </div>

            <!-- Essay Answer Preview -->
            <div v-else-if="question.question_type === 'essay'" class="bg-white/5 p-3 rounded-lg">
              <p class="text-white/60 text-sm">Jawaban essay akan dinilai secara manual</p>
            </div>

            <!-- True/False Options -->
            <div v-else-if="question.question_type === 'true_false'" class="space-y-2">
              <div class="flex items-center space-x-3 p-3 rounded-lg bg-white/5">
                <div class="w-4 h-4 rounded-full border-2 border-white/30 flex items-center justify-center">
                  <div v-if="question.options && question.options.find(o => o.option_text === 'Benar' && o.is_correct)" class="w-2 h-2 bg-green-400 rounded-full"></div>
                </div>
                <span class="text-white/80">Benar</span>
                <span v-if="question.options && question.options.find(o => o.option_text === 'Benar' && o.is_correct)" class="text-green-400 text-xs font-semibold">
                  Jawaban Benar
                </span>
              </div>
              <div class="flex items-center space-x-3 p-3 rounded-lg bg-white/5">
                <div class="w-4 h-4 rounded-full border-2 border-white/30 flex items-center justify-center">
                  <div v-if="question.options && question.options.find(o => o.option_text === 'Salah' && o.is_correct)" class="w-2 h-2 bg-green-400 rounded-full"></div>
                </div>
                <span class="text-white/80">Salah</span>
                <span v-if="question.options && question.options.find(o => o.option_text === 'Salah' && o.is_correct)" class="text-green-400 text-xs font-semibold">
                  Jawaban Benar
                </span>
              </div>
            </div>
          </div>

          <!-- Empty State -->
          <div
            v-if="questions.length === 0"
            class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-12 text-center"
          >
            <div class="text-6xl text-white/30 mb-4">
              <i class="fas fa-question-circle"></i>
            </div>
            <h3 class="text-2xl font-bold text-white mb-2">Belum ada pertanyaan</h3>
            <p class="text-white/70 mb-6">
              Mulai menambahkan pertanyaan untuk quiz ini
            </p>
                         <button
               @click="showAddQuestionModal = true"
               class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-6 py-3 rounded-xl font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
               :disabled="loading"
             >
               <i class="fas fa-plus mr-2"></i>
               Tambah Pertanyaan Pertama
             </button>
          </div>
        </div>
      </div>

             <!-- Add/Edit Question Modal -->
       <div
         v-if="showAddQuestionModal || showEditQuestionModal"
         class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4"
       >
         <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto relative">
           <!-- Loading Overlay -->
           <div v-if="loading" class="absolute inset-0 bg-black/30 backdrop-blur-sm rounded-2xl flex items-center justify-center z-10">
             <div class="text-center">
               <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white mx-auto mb-4"></div>
               <p class="text-white font-semibold">Menyimpan pertanyaan...</p>
             </div>
           </div>
          <div class="flex items-center justify-between mb-6">
            <h3 class="text-2xl font-bold text-white">
              {{ showEditQuestionModal ? 'Edit Pertanyaan' : 'Tambah Pertanyaan Baru' }}
            </h3>
                         <button
               @click="closeQuestionModal"
               class="text-white/60 hover:text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
               :disabled="loading"
             >
               <i class="fas fa-times text-xl"></i>
             </button>
          </div>

          <form @submit.prevent="saveQuestion" class="space-y-6">
                         <!-- Question Type -->
             <div>
               <label class="block text-sm font-medium text-gray-300 mb-2">Tipe Pertanyaan <span class="text-red-400">*</span></label>
               <select
                 v-model="questionForm.question_type"
                 required
                 class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent [&>option]:bg-slate-800 [&>option]:text-white disabled:opacity-50 disabled:cursor-not-allowed"
                 :disabled="loading"
               >
                 <option value="multiple_choice">Pilihan Ganda</option>
                 <option value="essay">Essay</option>
                 <option value="true_false">Benar/Salah</option>
               </select>
             </div>

             <!-- Question Text -->
             <div>
               <label class="block text-sm font-medium text-gray-300 mb-2">Pertanyaan <span class="text-red-400">*</span></label>
               <textarea
                 v-model="questionForm.question_text"
                 rows="3"
                 required
                 class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                 placeholder="Masukkan pertanyaan..."
                 :disabled="loading"
               ></textarea>
             </div>

             <!-- Points -->
             <div>
               <label class="block text-sm font-medium text-gray-300 mb-2">Poin <span class="text-red-400">*</span></label>
               <input
                 v-model="questionForm.points"
                 type="number"
                 min="1"
                 required
                 class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                 placeholder="1"
                 :disabled="loading"
               />
             </div>

             <!-- Question Image -->
             <div>
               <label class="block text-sm font-medium text-gray-300 mb-2">Gambar Pertanyaan (Opsional)</label>
               <div class="space-y-3">
                 <!-- Image Preview -->
                 <div v-if="questionForm.image_preview || questionForm.image_path" class="relative">
                   <img 
                     :src="questionForm.image_preview || questionForm.image_url" 
                     alt="Preview gambar pertanyaan"
                     class="w-full max-w-md h-auto rounded-lg border border-white/20"
                   />
                   <button
                     v-if="questionForm.image_preview || questionForm.image_path"
                     @click="removeImage"
                     type="button"
                     class="absolute top-2 right-2 bg-red-500 hover:bg-red-600 text-white rounded-full w-8 h-8 flex items-center justify-center transition-colors"
                   >
                     <i class="fas fa-times"></i>
                   </button>
                 </div>
                 
                 <!-- File Input -->
                 <div class="flex items-center space-x-3">
                   <input
                     ref="imageInput"
                     type="file"
                     accept="image/*"
                     @change="handleImageChange"
                     class="hidden"
                     :disabled="loading"
                   />
                   <button
                     @click="$refs.imageInput.click()"
                     type="button"
                     class="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white hover:bg-white/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                     :disabled="loading"
                   >
                     <i class="fas fa-upload mr-2"></i>
                     {{ questionForm.image_preview || questionForm.image_path ? 'Ganti Gambar' : 'Upload Gambar' }}
                   </button>
                 </div>
                 
                 <!-- Alt Text -->
                 <div v-if="questionForm.image_preview || questionForm.image_path">
                   <input
                     v-model="questionForm.image_alt_text"
                     type="text"
                     class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                     placeholder="Deskripsi gambar untuk aksesibilitas..."
                     :disabled="loading"
                   />
                 </div>
               </div>
             </div>

            <!-- Options for Multiple Choice -->
            <div v-if="questionForm.question_type === 'multiple_choice'">
              <label class="block text-sm font-medium text-gray-300 mb-2">Opsi Jawaban <span class="text-red-400">*</span></label>
              <div class="space-y-3">
                                 <div
                   v-for="(option, index) in questionForm.options"
                   :key="index"
                   class="space-y-3 p-4 border border-white/10 rounded-lg"
                 >
                   <!-- Option Text -->
                   <div class="flex items-center space-x-3">
                     <input
                       v-model="option.option_text"
                       type="text"
                       required
                       class="flex-1 px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                       :placeholder="`Opsi ${index + 1}`"
                       :disabled="loading"
                     />
                     <input
                       v-model="option.is_correct"
                       type="radio"
                       :name="`correct_option_${questionForm.id || 'new'}`"
                       :value="true"
                       class="w-4 h-4 text-blue-600 bg-white/10 border-white/20 focus:ring-blue-500 focus:ring-2 disabled:opacity-50 disabled:cursor-not-allowed"
                       :disabled="loading"
                     />
                     <span class="text-white/60 text-sm">Benar</span>
                     <button
                       v-if="questionForm.options.length > 2"
                       @click="removeOption(index)"
                       type="button"
                       class="text-red-400 hover:text-red-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                       :disabled="loading"
                     >
                       <i class="fas fa-trash"></i>
                     </button>
                   </div>
                   
                   <!-- Option Image -->
                   <div>
                     <label class="block text-sm font-medium text-gray-300 mb-2">Gambar Opsi (Opsional)</label>
                     <div class="space-y-3">
                       <!-- Image Preview -->
                       <div v-if="option.image_preview || option.image_path" class="relative">
                         <img 
                           :src="option.image_preview || option.image_url" 
                           alt="Preview gambar opsi"
                           class="w-full max-w-xs h-auto rounded-lg border border-white/20"
                         />
                         <button
                           @click="removeOptionImage(index)"
                           type="button"
                           class="absolute top-2 right-2 bg-red-500 hover:bg-red-600 text-white rounded-full w-6 h-6 flex items-center justify-center transition-colors text-xs"
                         >
                           <i class="fas fa-times"></i>
                         </button>
                       </div>
                       
                       <!-- File Input -->
                       <div class="flex items-center space-x-3">
                         <input
                           :ref="`optionImageInput${index}`"
                           type="file"
                           accept="image/*"
                           @change="(event) => handleOptionImageChange(event, index)"
                           class="hidden"
                           :disabled="loading"
                         />
                         <button
                           @click="() => clickOptionImageInput(index)"
                           type="button"
                           class="px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white hover:bg-white/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                           :disabled="loading"
                         >
                           <i class="fas fa-upload mr-1"></i>
                           {{ option.image_preview || option.image_path ? 'Ganti Gambar' : 'Upload Gambar' }}
                         </button>
                       </div>
                       
                       <!-- Alt Text -->
                       <div v-if="option.image_preview || option.image_path">
                         <input
                           v-model="option.image_alt_text"
                           type="text"
                           class="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed text-sm"
                           placeholder="Deskripsi gambar opsi..."
                           :disabled="loading"
                         />
                       </div>
                     </div>
                   </div>
                 </div>
                                 <button
                   @click="addOption"
                   type="button"
                   class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                   :disabled="loading"
                 >
                   <i class="fas fa-plus mr-1"></i>
                   Tambah Opsi
                 </button>
              </div>
            </div>

            <!-- Options for True/False -->
            <div v-if="questionForm.question_type === 'true_false'">
              <label class="block text-sm font-medium text-gray-300 mb-2">Jawaban Benar <span class="text-red-400">*</span></label>
              <div class="space-y-3">
                                 <div class="flex items-center space-x-3">
                   <input
                     v-model="questionForm.correct_answer"
                     type="radio"
                     name="correct_answer"
                     value="true"
                     class="w-4 h-4 text-blue-600 bg-white/10 border-white/20 focus:ring-blue-500 focus:ring-2 disabled:opacity-50 disabled:cursor-not-allowed"
                     :disabled="loading"
                   />
                   <span class="text-white">Benar</span>
                 </div>
                 <div class="flex items-center space-x-3">
                   <input
                     v-model="questionForm.correct_answer"
                     type="radio"
                     name="correct_answer"
                     value="false"
                     class="w-4 h-4 text-blue-600 bg-white/10 border-white/20 focus:ring-blue-500 focus:ring-2 disabled:opacity-50 disabled:cursor-not-allowed"
                     :disabled="loading"
                   />
                   <span class="text-white">Salah</span>
                 </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex space-x-3 pt-6">
                             <button
                 type="button"
                 @click="closeQuestionModal"
                 class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-lg transition-colors duration-200 text-center disabled:opacity-50 disabled:cursor-not-allowed"
                 :disabled="loading"
               >
                 Batal
               </button>
              <button
                type="submit"
                :disabled="loading || !isQuestionFormValid"
                class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white px-4 py-3 rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed relative overflow-hidden"
              >
                <span v-if="loading" class="flex items-center justify-center">
                  <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-2"></div>
                  Menyimpan...
                </span>
                <span v-else class="flex items-center justify-center">
                  <i class="fas fa-save mr-2"></i>
                  {{ showEditQuestionModal ? 'Update Pertanyaan' : 'Simpan Pertanyaan' }}
                </span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, getCurrentInstance, nextTick } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Swal from 'sweetalert2'

const props = defineProps({
  quiz: Object,
  questions: Array
})

// Get current instance to access refs
const instance = getCurrentInstance()

const loading = ref(false)
const showAddQuestionModal = ref(false)
const showEditQuestionModal = ref(false)

// Question form data
const questionForm = ref({
  id: null,
  question_text: '',
  question_type: 'multiple_choice',
  points: 1,
  image_path: null,
  image_preview: null,
  image_alt_text: '',
  options: [
    { option_text: '', is_correct: false, image_path: null, image_preview: null, image_alt_text: '' },
    { option_text: '', is_correct: false, image_path: null, image_preview: null, image_alt_text: '' }
  ],
  correct_answer: null
})

// Form validation
const isQuestionFormValid = computed(() => {
  console.log('Validating form:', {
    question_text: questionForm.value.question_text,
    points: questionForm.value.points,
    question_type: questionForm.value.question_type,
    options: questionForm.value.options,
    correct_answer: questionForm.value.correct_answer
  })

  // Check required fields
  if (!questionForm.value.question_text || !questionForm.value.question_text.trim()) {
    console.log('Validation failed: question_text is empty')
    return false
  }

  if (!questionForm.value.points || questionForm.value.points <= 0) {
    console.log('Validation failed: points is invalid', questionForm.value.points)
    return false
  }

  if (questionForm.value.question_type === 'multiple_choice') {
    const hasValidOptions = questionForm.value.options.length >= 2 && 
           questionForm.value.options.every(opt => opt.option_text && opt.option_text.trim()) &&
           questionForm.value.options.some(opt => opt.is_correct)
    console.log('Multiple choice validation:', hasValidOptions)
    return hasValidOptions
  }

  if (questionForm.value.question_type === 'true_false') {
    const hasCorrectAnswer = questionForm.value.correct_answer !== null && questionForm.value.correct_answer !== undefined
    console.log('True/false validation:', hasCorrectAnswer)
    return hasCorrectAnswer
  }

  console.log('Validation passed')
  return true
})

const getQuestionTypeText = (type) => {
  const typeMap = {
    'multiple_choice': 'Pilihan Ganda',
    'essay': 'Essay',
    'true_false': 'Benar/Salah'
  }
  return typeMap[type] || type
}

const addOption = () => {
  questionForm.value.options.push({ 
    option_text: '', 
    is_correct: false, 
    image_path: null, 
    image_preview: null, 
    image_alt_text: '' 
  })
}

const removeOption = (index) => {
  questionForm.value.options.splice(index, 1)
}

const handleImageChange = (event) => {
  const file = event.target.files[0]
  if (file) {
    // Create preview URL
    questionForm.value.image_preview = URL.createObjectURL(file)
    questionForm.value.image_path = null // Clear existing path when new image is selected
  }
}

const removeImage = () => {
  questionForm.value.image_preview = null
  questionForm.value.image_path = null
  questionForm.value.image_alt_text = ''
  // Reset file input using refs
  if (instance.refs.imageInput) {
    instance.refs.imageInput.value = ''
  }
}

const handleOptionImageChange = (event, index) => {
  const file = event.target.files[0]
  if (file) {
    // Create preview URL
    questionForm.value.options[index].image_preview = URL.createObjectURL(file)
    questionForm.value.options[index].image_path = null // Clear existing path when new image is selected
  }
}

const clickOptionImageInput = (index) => {
  // Access the file input using nextTick to ensure DOM is updated
  nextTick(() => {
    const optionImageInput = instance.refs[`optionImageInput${index}`]
    if (optionImageInput && optionImageInput[0]) {
      optionImageInput[0].click()
    } else {
      console.warn(`Option ${index} file input not found`)
    }
  })
}

const removeOptionImage = (index) => {
  questionForm.value.options[index].image_preview = null
  questionForm.value.options[index].image_path = null
  questionForm.value.options[index].image_alt_text = ''
  // Reset file input using refs
  nextTick(() => {
    const optionImageInput = instance.refs[`optionImageInput${index}`]
    if (optionImageInput && optionImageInput[0]) {
      optionImageInput[0].value = ''
    }
  })
}

const handleImageError = (event) => {
  console.error('Image failed to load:', event.target.src)
  // Replace broken image with placeholder
  event.target.src = '/images/placeholder-image.png'
}



const editQuestion = (question) => {
  console.log('Editing question:', question)
  
  questionForm.value = {
    id: question.id,
    question_text: question.question_text || '',
    question_type: question.question_type || 'multiple_choice',
    points: question.points || 1,
    image_path: question.image_path,
    image_preview: null,
    image_alt_text: question.image_alt_text || '',
    options: question.options ? question.options.map(opt => ({
      ...opt,
      image_preview: null // Reset preview when editing
    })) : [
      { option_text: '', is_correct: false, image_path: null, image_preview: null, image_alt_text: '' },
      { option_text: '', is_correct: false, image_path: null, image_preview: null, image_alt_text: '' }
    ],
    correct_answer: question.question_type === 'true_false' && question.options ? 
      (question.options.find(o => o.is_correct)?.option_text === 'Benar' ? 'true' : 'false') : null
  }
  
  console.log('Form data after edit:', questionForm.value)
  showEditQuestionModal.value = true
}

const closeQuestionModal = () => {
  showAddQuestionModal.value = false
  showEditQuestionModal.value = false
  resetQuestionForm()
}

const resetQuestionForm = () => {
  questionForm.value = {
    id: null,
    question_text: '',
    question_type: 'multiple_choice',
    points: 1,
    image_path: null,
    image_preview: null,
    image_alt_text: '',
    options: [
      { option_text: '', is_correct: false, image_path: null, image_preview: null, image_alt_text: '' },
      { option_text: '', is_correct: false, image_path: null, image_preview: null, image_alt_text: '' }
    ],
    correct_answer: null
  }
}

// ... existing code ...

const saveQuestion = async () => {
  console.log('Saving question, form valid:', isQuestionFormValid.value)
  console.log('Form data:', questionForm.value)
  
  if (!isQuestionFormValid.value) {
    console.log('Form validation failed')
    Swal.fire({
      icon: 'error',
      title: 'Validasi Error',
      text: 'Mohon lengkapi semua field yang wajib diisi!'
    })
    return
  }

  // Show loading modal
  Swal.fire({
    title: 'Sabar Bu Ghea....',
    text: 'Antosan sakedap Bu Ghea, Nuju loding',
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
    didOpen: () => {
      Swal.showLoading()
    }
  })

  // Set loading state
  loading.value = true

  try {
    const formData = new FormData()
    
    // Debug: Check form values before appending
    console.log('Form values before appending:')
    console.log('question_text:', questionForm.value.question_text)
    console.log('question_type:', questionForm.value.question_type)
    console.log('points:', questionForm.value.points)
    console.log('image_alt_text:', questionForm.value.image_alt_text)
    
    // Ensure values are not undefined or null
    formData.append('question_text', questionForm.value.question_text || '')
    formData.append('question_type', questionForm.value.question_type || 'multiple_choice')
    formData.append('points', questionForm.value.points || 1)
    formData.append('image_alt_text', questionForm.value.image_alt_text || '')
    
         // Handle image upload
     if (questionForm.value.image_preview) {
       // Get file from question image file input using ref
       const questionImageInput = instance.refs.imageInput
       if (questionImageInput && questionImageInput.files && questionImageInput.files[0]) {
         formData.append('image', questionImageInput.files[0])
         console.log('Image file added to FormData:', questionImageInput.files[0])
       } else {
         console.warn('No file found in question image input')
       }
     } else {
       console.log('No image preview, skipping image upload')
     }
    
    // Handle options
    if (questionForm.value.question_type === 'multiple_choice') {
      // Handle options with images
      const optionsData = questionForm.value.options.map((option, index) => {
        const optionData = {
          option_text: option.option_text,
          is_correct: option.is_correct,
          image_alt_text: option.image_alt_text
        }
        
                 // Add image file if exists (new image)
         if (option.image_preview) {
           // Get the file input element using refs
           const optionImageInput = instance.refs[`optionImageInput${index}`]
           if (optionImageInput && optionImageInput[0] && optionImageInput[0].files && optionImageInput[0].files[0]) {
             formData.append(`option_image_${index}`, optionImageInput[0].files[0])
             console.log(`Option ${index} image file added:`, optionImageInput[0].files[0])
           } else {
             console.warn(`Option ${index} file input not found or no file`)
           }
         }
        // Keep existing image path if no new image
        else if (option.image_path) {
          optionData.image_path = option.image_path
        }
        
        return optionData
      })
      
      formData.append('options', JSON.stringify(optionsData))
    } else if (questionForm.value.question_type === 'true_false') {
      formData.append('correct_answer', questionForm.value.correct_answer)
    }
    
    // Debug: Log FormData contents
    console.log('FormData contents:')
    for (let [key, value] of formData.entries()) {
      console.log(`${key}:`, value)
    }

    if (showEditQuestionModal.value) {
      // Try using JSON data instead of FormData for debugging
      const jsonData = {
        question_text: questionForm.value.question_text || '',
        question_type: questionForm.value.question_type || 'multiple_choice',
        points: questionForm.value.points || 1,
        image_alt_text: questionForm.value.image_alt_text || '',
        options: questionForm.value.question_type === 'multiple_choice' ? 
          questionForm.value.options.map(opt => ({
            option_text: opt.option_text || '',
            is_correct: opt.is_correct || false,
            image_alt_text: opt.image_alt_text || ''
          })) : null,
        correct_answer: questionForm.value.question_type === 'true_false' ? questionForm.value.correct_answer : null
      }
      
      console.log('Sending JSON data:', jsonData)
      
      await router.put(route('lms.quizzes.questions.update', [props.quiz.id, questionForm.value.id]), jsonData, {
        onSuccess: (response) => {
          console.log('Update success response:', response)
          Swal.close()
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Pertanyaan berhasil diperbarui!'
          })
          closeQuestionModal()
        },
        onError: (errors) => {
          console.error('Update error:', errors)
          Swal.close()
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: Object.values(errors)[0] || 'Terjadi kesalahan saat memperbarui pertanyaan'
          })
        }
      })
    } else {
      await router.post(route('lms.quizzes.questions.store', props.quiz.id), formData, {
        onSuccess: (response) => {
          console.log('Create success response:', response)
          Swal.close()
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Pertanyaan berhasil ditambahkan!'
          })
          closeQuestionModal()
        },
        onError: (errors) => {
          console.error('Create error:', errors)
          Swal.close()
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: Object.values(errors)[0] || 'Terjadi kesalahan saat memperbarui pertanyaan'
          })
        }
      })
    }
  } catch (error) {
    console.error('Error saving question:', error)
    Swal.close()
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat menyimpan pertanyaan'
    })
  } finally {
    // Reset loading state
    loading.value = false
  }
}

// ... existing code ...

const deleteQuestion = (questionId) => {
  Swal.fire({
    title: 'Konfirmasi Hapus',
    text: 'Apakah Anda yakin ingin menghapus pertanyaan ini?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal'
  }).then((result) => {
    if (result.isConfirmed) {
      // Set loading state for delete
      loading.value = true
      
      console.log('Deleting question:', questionId, 'from quiz:', props.quiz.id)
      console.log('Route:', route('lms.quizzes.questions.destroy', [props.quiz.id, questionId]))
      
      router.delete(route('lms.quizzes.questions.destroy', [props.quiz.id, questionId]), {
        onSuccess: (response) => {
          loading.value = false
          console.log('Delete success:', response)
          Swal.fire(
            'Terhapus!',
            'Pertanyaan berhasil dihapus.',
            'success'
          )
        },
        onError: (errors) => {
          loading.value = false
          console.error('Delete error:', errors)
          Swal.fire(
            'Error!',
            'Gagal menghapus pertanyaan: ' + (Object.values(errors)[0] || 'Unknown error'),
            'error'
          )
        }
      })
    }
  })
}


</script>

<style scoped>
.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

@keyframes blob {
  0% {
    transform: translate(0px, 0px) scale(1);
  }
  33% {
    transform: translate(30px, -50px) scale(1.1);
  }
  66% {
    transform: translate(-20px, 20px) scale(0.9);
  }
  100% {
    transform: translate(0px, 0px) scale(1);
  }
}

 /* Global dropdown styling */
 :deep(select option) {
   background-color: #1e293b !important;
   color: white !important;
 }

 :deep(select option:hover) {
   background-color: #334155 !important;
 }

 :deep(select option:checked) {
   background-color: #3b82f6 !important;
 }

 /* Loading state styling */
 .disabled\:opacity-50:disabled {
   opacity: 0.5;
 }

 .disabled\:cursor-not-allowed:disabled {
   cursor: not-allowed;
 }

 .pointer-events-none {
   pointer-events: none;
 }
</style>
