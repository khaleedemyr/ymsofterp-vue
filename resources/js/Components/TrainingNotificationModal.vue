<template>
  <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
      <!-- Header -->
      <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-xl font-bold">Training Notification</h2>
            <p class="text-white/80 mt-1">{{ notification.course_title }}</p>
          </div>
          <button @click="$emit('close')" class="text-white/80 hover:text-white">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
        <!-- Training Information -->
        <div class="mb-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-info-circle mr-2 text-blue-500"></i>
            Informasi Training
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-3">
              <div class="flex items-center space-x-3">
                <i class="fas fa-calendar text-blue-400 w-5"></i>
                <div>
                  <p class="text-sm text-gray-600">Tanggal</p>
                  <p class="font-semibold text-gray-800">{{ formatTrainingDate(notification.scheduled_date) }}</p>
                </div>
              </div>
              <div class="flex items-center space-x-3">
                <i class="fas fa-clock text-green-400 w-5"></i>
                <div>
                  <p class="text-sm text-gray-600">Waktu</p>
                  <p class="font-semibold text-gray-800">{{ formatTrainingTime(notification.start_time, notification.end_time) }}</p>
                </div>
              </div>
            </div>
            <div class="space-y-3">
              <div class="flex items-center space-x-3">
                <i class="fas fa-map-marker-alt text-red-400 w-5"></i>
                <div>
                  <p class="text-sm text-gray-600">Lokasi</p>
                  <p class="font-semibold text-gray-800">{{ notification.outlet_name }}</p>
                </div>
              </div>
              <div class="flex items-center space-x-3">
                <i class="fas fa-user-tag text-purple-400 w-5"></i>
                <div>
                  <p class="text-sm text-gray-600">Role</p>
                  <span :class="getRoleBadgeClass(notification.role)" 
                        class="px-3 py-1 rounded-full text-sm font-semibold">
                    {{ notification.role }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Course Sessions Information -->
        <div v-if="notification.sessions && notification.sessions.length > 0" class="mb-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-list-alt mr-2 text-indigo-500"></i>
            Sesi Training
          </h3>
          <div class="space-y-3">
            <div 
              v-for="session in notification.sessions" 
              :key="session.id"
              class="bg-gray-50 rounded-lg p-4 border border-gray-200"
            >
              <div class="flex items-start justify-between">
                <div class="flex-1">
                  <div class="flex items-center gap-2 mb-2">
                    <span :class="getSessionBadgeClass(session)" class="text-xs font-semibold px-2 py-1 rounded-full flex items-center gap-1">
                      <i :class="getSessionIcon(session)"></i>
                      Sesi {{ session.order_number }}
                    </span>
                    <span v-if="session.is_required" class="bg-red-100 text-red-800 text-xs font-semibold px-2 py-1 rounded-full">
                      Wajib
                    </span>
                    <span v-else class="bg-gray-100 text-gray-800 text-xs font-semibold px-2 py-1 rounded-full">
                      Opsional
                    </span>
                    <span v-if="!session.can_access" class="bg-gray-100 text-gray-500 text-xs font-semibold px-2 py-1 rounded-full flex items-center gap-1">
                      <i class="fas fa-lock"></i>
                      Terkunci
                    </span>
                  </div>
                  <h4 class="font-semibold text-gray-800 mb-1">{{ session.session_title }}</h4>
                  <p v-if="session.session_description" class="text-sm text-gray-600 mb-2">
                    {{ session.session_description }}
                  </p>
                  <div class="flex items-center gap-4 text-xs text-gray-500">
                    <span v-if="session.estimated_duration_minutes" class="flex items-center gap-1">
                      <i class="fas fa-clock"></i>
                      {{ formatDuration(session.estimated_duration_minutes) }}
                    </span>
                  </div>
                  
                  <!-- Session Items -->
                  <div v-if="session.items && session.items.length > 0" class="mt-3">
                    <div class="text-xs font-medium text-gray-700 mb-2">Materi Sesi:</div>
                    <div class="space-y-3">
                      <div 
                        v-for="item in session.items" 
                        :key="item.id"
                        :class="getItemCardClass(item)"
                        class="rounded-lg p-3 border transition-shadow"
                      >
                        <div class="flex items-start justify-between">
                          <div class="flex items-start gap-3 flex-1">
                            <div class="flex-shrink-0">
                              <span :class="getItemTypeBadgeClass(item.item_type)" 
                                    class="px-3 py-1 rounded-full text-xs font-semibold flex items-center gap-1">
                                <i :class="getItemTypeIcon(item.item_type)"></i>
                                {{ getItemTypeLabel(item.item_type) }}
                              </span>
                            </div>
                            <div class="flex-1">
                              <div class="flex items-center gap-2 mb-1">
                                <span class="text-sm font-medium text-gray-800">{{ item.title || 'Untitled' }}</span>
                                <span v-if="item.is_required" class="text-xs text-red-600 font-medium">*</span>
                              </div>
                              <p v-if="item.description" class="text-xs text-gray-500 mb-2">{{ item.description }}</p>
                              
                              <!-- Item Details -->
                              <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                                <span v-if="item.estimated_duration_minutes" class="flex items-center gap-1">
                                  <i class="fas fa-clock"></i>
                                  {{ formatDuration(item.estimated_duration_minutes) }}
                                </span>
                                <span v-if="item.passing_score" class="flex items-center gap-1">
                                  <i class="fas fa-trophy"></i>
                                  Min {{ item.passing_score }}%
                                </span>
                                <span v-if="item.max_attempts" class="flex items-center gap-1">
                                  <i class="fas fa-redo"></i>
                                  Max {{ item.max_attempts }} attempts
                                </span>
                              </div>
                            </div>
                          </div>
                          
                          <!-- Action Button -->
                          <div class="flex-shrink-0 ml-3">
                            <button 
                              v-if="!item.can_access"
                              class="px-3 py-1 bg-gray-100 text-gray-500 text-xs font-semibold rounded-full flex items-center gap-1 cursor-not-allowed"
                              disabled
                            >
                              <i class="fas fa-lock"></i>
                              Terkunci
                            </button>
                            <button 
                              v-else-if="item.progress && item.progress.status === 'completed'"
                              class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full flex items-center gap-1"
                            >
                              <i class="fas fa-check"></i>
                              Selesai
                            </button>
                            <button 
                              v-else-if="item.progress && item.progress.status === 'in_progress'"
                              class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded-full flex items-center gap-1"
                            >
                              <i class="fas fa-play"></i>
                              Lanjutkan
                            </button>
                            <button 
                              v-else
                              class="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full flex items-center gap-1 hover:bg-blue-200 transition-colors"
                            >
                              <i class="fas fa-play"></i>
                              Mulai
                            </button>
                          </div>
                        </div>
                        
                        <!-- Progress Bar for Item -->
                        <div v-if="item.progress" class="mt-3">
                          <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                            <span>Progress</span>
                            <span>{{ item.progress.completion_percentage || 0 }}%</span>
                          </div>
                          <div class="w-full bg-gray-200 rounded-full h-2">
                            <div 
                              :class="getProgressBarClass(item.progress.status)"
                              class="h-2 rounded-full transition-all duration-300"
                              :style="{ width: (item.progress.completion_percentage || 0) + '%' }"
                            ></div>
                          </div>
                          <div class="flex items-center justify-between text-xs text-gray-500 mt-1">
                            <span>{{ getProgressStatusText(item.progress.status) }}</span>
                            <span v-if="item.progress.attempts">Attempts: {{ item.progress.attempts }}</span>
                          </div>
                        </div>
                        
                        <!-- Quiz Interface -->
                        <div v-if="item.item_type === 'quiz' && item.quiz" class="mt-3">
                          <!-- Quiz Info -->
                          <div class="p-3 bg-blue-50 rounded-lg mb-3">
                            <div class="flex items-center gap-2 text-sm text-blue-800">
                              <i class="fas fa-question-circle"></i>
                              <span class="font-medium">Quiz: {{ item.quiz.title }}</span>
                            </div>
                            <p v-if="item.quiz.description" class="text-xs text-blue-600 mt-1">{{ item.quiz.description }}</p>
                            <div class="flex items-center gap-4 mt-2 text-xs text-blue-600">
                              <span>{{ item.quiz.questions?.length || 0 }} pertanyaan</span>
                              <span v-if="item.quiz.time_limit_minutes">{{ item.quiz.time_limit_minutes }} menit</span>
                              <span>Passing: {{ item.quiz.passing_score }}%</span>
                            </div>
                          </div>

                          <!-- Quiz Attempts Info -->
                          <div v-if="item.quiz.attempts && item.quiz.attempts.length > 0" class="mb-3">
                            <div class="text-xs text-gray-600 mb-2">Attempt History:</div>
                            <div class="space-y-1">
                              <div v-for="attempt in item.quiz.attempts" :key="attempt.id" 
                                   class="flex items-center justify-between p-2 bg-gray-50 rounded text-xs">
                                <span>Attempt {{ attempt.attempt_number }}</span>
                                <span v-if="attempt.status === 'completed'" 
                                      :class="attempt.is_passed ? 'text-green-600' : 'text-red-600'">
                                  {{ attempt.score }}% {{ attempt.is_passed ? '(Passed)' : '(Failed)' }}
                                </span>
                                <span v-else-if="attempt.status === 'in_progress'" class="text-yellow-600">
                                  In Progress
                                </span>
                                <span v-else class="text-gray-500">
                                  {{ attempt.status }}
                                </span>
                              </div>
                            </div>
                          </div>

                          <!-- Quiz Actions -->
                          <div class="flex gap-2">
                            <!-- Start/Continue Quiz Button -->
                            <button v-if="item.quiz.can_attempt && (!item.quiz.latest_attempt || item.quiz.latest_attempt.status !== 'in_progress')"
                                    @click="startQuiz(item)"
                                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm font-medium transition-colors">
                              <i class="fas fa-play mr-1"></i>
                              {{ item.quiz.latest_attempt ? 'Retry Quiz' : 'Start Quiz' }}
                            </button>
                            
                            <!-- Continue Quiz Button -->
                            <button v-if="item.quiz.latest_attempt && item.quiz.latest_attempt.status === 'in_progress'"
                                    @click="continueQuiz(item)"
                                    class="flex-1 bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-2 rounded text-sm font-medium transition-colors">
                              <i class="fas fa-play mr-1"></i>
                              Continue Quiz
                            </button>
                            
                            <!-- View Results Button -->
                            <button v-if="item.quiz.latest_attempt && item.quiz.latest_attempt.status === 'completed' && item.quiz.show_results"
                                    @click="viewQuizResults(item)"
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm font-medium transition-colors">
                              <i class="fas fa-chart-bar mr-1"></i>
                              View Results
                            </button>
                            
                            <!-- Max Attempts Reached -->
                            <div v-if="!item.quiz.can_attempt" class="flex-1 bg-gray-300 text-gray-600 px-3 py-2 rounded text-sm text-center">
                              <i class="fas fa-lock mr-1"></i>
                              Max Attempts Reached
                            </div>
                          </div>
                        </div>
                        
                        <!-- Questionnaire Interface -->
                        <div v-if="item.item_type === 'questionnaire' && item.questionnaire" class="mt-3">
                          <!-- Questionnaire Info -->
                          <div class="p-3 bg-green-50 rounded-lg mb-3">
                            <div class="flex items-center gap-2 text-sm text-green-800">
                              <i class="fas fa-clipboard-list"></i>
                              <span class="font-medium">Kuesioner: {{ item.questionnaire.title }}</span>
                            </div>
                            <p v-if="item.questionnaire.description" class="text-xs text-green-600 mt-1">{{ item.questionnaire.description }}</p>
                            <div class="flex items-center gap-4 mt-2 text-xs text-green-600">
                              <span>{{ item.questionnaire.questions?.length || 0 }} pertanyaan</span>
                              <span v-if="item.questionnaire.is_anonymous" class="flex items-center gap-1">
                                <i class="fas fa-user-secret"></i>
                                Anonymous
                              </span>
                              <span v-if="item.questionnaire.allow_multiple_responses" class="flex items-center gap-1">
                                <i class="fas fa-redo"></i>
                                Multiple Responses
                              </span>
                            </div>
                          </div>

                          <!-- Questionnaire Status -->
                          <div v-if="!item.questionnaire.is_active" class="mb-3 p-2 bg-red-50 rounded text-red-600 text-xs">
                            <i class="fas fa-clock mr-1"></i>
                            Kuesioner tidak aktif (di luar periode yang ditentukan)
                          </div>

                          <!-- Questionnaire Responses Info -->
                          <div v-if="item.questionnaire.responses && item.questionnaire.responses.length > 0" class="mb-3">
                            <div class="text-xs text-gray-600 mb-2">Response History:</div>
                            <div class="space-y-1">
                              <div v-for="response in item.questionnaire.responses" :key="response.id" 
                                   class="flex items-center justify-between p-2 bg-gray-50 rounded text-xs">
                                <span>Response {{ response.id }}</span>
                                <span v-if="response.submitted_at" class="text-green-600">
                                  Submitted {{ formatDate(response.submitted_at) }}
                                </span>
                                <span v-else class="text-yellow-600">
                                  Draft
                                </span>
                              </div>
                            </div>
                          </div>

                          <!-- Questionnaire Actions -->
                          <div class="flex gap-2">
                            <!-- Start/Continue Questionnaire Button -->
                            <button v-if="item.questionnaire.can_respond && (!item.questionnaire.latest_response || !item.questionnaire.latest_response.submitted_at)"
                                    @click="startQuestionnaire(item)"
                                    class="flex-1 bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded text-sm font-medium transition-colors">
                              <i class="fas fa-play mr-1"></i>
                              {{ item.questionnaire.latest_response ? 'Continue Questionnaire' : 'Start Questionnaire' }}
                            </button>
                            
                            <!-- View Results Button -->
                            <button v-if="item.questionnaire.latest_response && item.questionnaire.latest_response.submitted_at"
                                    @click="viewQuestionnaireResults(item)"
                                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm font-medium transition-colors">
                              <i class="fas fa-eye mr-1"></i>
                              View Response
                            </button>
                            
                            <!-- Cannot Respond -->
                            <div v-if="!item.questionnaire.can_respond" class="flex-1 bg-gray-300 text-gray-600 px-3 py-2 rounded text-sm text-center">
                              <i class="fas fa-lock mr-1"></i>
                              {{ item.questionnaire.is_active ? 'Already Responded' : 'Not Available' }}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- Session Progress -->
                  <div v-if="session.progress" class="mt-3">
                    <div class="text-xs font-medium text-gray-700 mb-2">Progress:</div>
                    <div class="flex items-center gap-2">
                      <div class="flex-1 bg-gray-200 rounded-full h-2">
                        <div 
                          :class="getProgressBarClass(session.progress.status)"
                          class="h-2 rounded-full transition-all duration-300"
                          :style="{ width: getProgressPercentage(session.progress.status) }"
                        ></div>
                      </div>
                      <span :class="getProgressTextClass(session.progress.status)" 
                            class="text-xs font-semibold">
                        {{ getProgressLabel(session.progress.status) }}
                      </span>
                    </div>
                    <div v-if="session.progress.score !== null" class="text-xs text-gray-600 mt-1">
                      Score: {{ session.progress.score }}%
                    </div>
                    <div v-if="session.progress.attempts > 0" class="text-xs text-gray-600">
                      Attempts: {{ session.progress.attempts }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Feedback Section -->
        <div v-if="props.notification.all_completed" class="mb-6">
          <div v-if="props.notification.can_give_feedback" class="p-6 bg-gradient-to-r from-green-50 to-blue-50 rounded-lg border border-green-200">
            <div class="text-center">
              <div class="text-6xl mb-4">🎉</div>
              <h3 class="text-2xl font-bold text-green-800 mb-2">Selamat! Training Selesai</h3>
              <p class="text-gray-600 mb-6">
                Anda telah menyelesaikan semua sesi dan item training. 
                Berikan feedback untuk membantu kami meningkatkan kualitas training.
              </p>
              <button @click="openFeedbackModal" 
                      class="bg-gradient-to-r from-green-600 to-blue-600 hover:from-green-700 hover:to-blue-700 text-white px-8 py-4 rounded-lg font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg">
                <i class="fas fa-star mr-2"></i>
                Berikan Feedback
              </button>
            </div>
          </div>

          <!-- Feedback Already Given -->
          <div v-else class="p-6 bg-gray-50 rounded-lg border border-gray-200">
            <div class="text-center">
              <div class="text-6xl mb-4">✅</div>
              <h3 class="text-2xl font-bold text-gray-800 mb-2">Feedback Sudah Diberikan</h3>
              <p class="text-gray-600">
                Terima kasih! Feedback Anda telah direkam dan akan membantu kami meningkatkan kualitas training.
              </p>
            </div>
          </div>
        </div>

        <!-- Check-in/Check-out Section -->
        <div class="mb-6">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-mobile-alt mr-2 text-orange-500"></i>
            Scan QR Code untuk Absensi
          </h3>
          
          <!-- Checkout Button -->
          <div v-if="canCheckout" class="mb-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
            <div class="flex items-center justify-between">
              <div>
                <h4 class="font-semibold text-blue-800">
                  {{ props.notification.role === 'Trainer' ? 'Training Selesai' : 'Training Selesai' }}
                </h4>
                <p class="text-sm text-blue-600">
                  {{ props.notification.role === 'Trainer' 
                    ? 'Klik tombol di bawah untuk checkout dan simpan history training sebagai trainer' 
                    : 'Klik tombol di bawah untuk checkout dan simpan history training' }}
                </p>
              </div>
              <button @click="checkoutTraining" 
                      class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-all duration-300 transform hover:scale-105 shadow-lg">
                <i class="fas fa-sign-out-alt mr-2"></i>
                Checkout Training
              </button>
            </div>
          </div>
          
          <!-- Mode Toggle -->
          <div class="flex bg-gray-100 rounded-lg p-1 mb-4">
            <button 
              @click="scanMode = 'checkin'"
              :class="scanMode === 'checkin' ? 'bg-blue-500 text-white' : 'text-gray-600'"
              class="flex-1 py-2 px-4 rounded-md text-sm font-medium transition-all"
            >
              <i class="fas fa-sign-in-alt mr-2"></i>
              Check In
            </button>
            <button 
              @click="scanMode = 'checkout'"
              :class="scanMode === 'checkout' ? 'bg-green-500 text-white' : 'text-gray-600'"
              class="flex-1 py-2 px-4 rounded-md text-sm font-medium transition-all"
            >
              <i class="fas fa-sign-out-alt mr-2"></i>
              Check Out
            </button>
          </div>

          <!-- Scanner Container -->
          <div class="relative">
            <!-- Camera Button -->
            <div v-if="!showScanner" class="text-center">
              <button @click="showScanner = true" 
                      class="w-full bg-green-500 hover:bg-green-600 text-white px-4 py-3 rounded-lg font-semibold transition-all">
                <i class="fas fa-camera mr-2"></i>
                Buka Kamera Scanner
              </button>
              <p class="text-gray-500 text-sm mt-2">Klik tombol di atas untuk membuka kamera</p>
            </div>
            
            <!-- Scanner Area -->
            <div v-if="showScanner" class="space-y-3">
              <!-- Camera Controls -->
              <div v-if="cameras.length > 1" class="flex items-center justify-between bg-gray-50 rounded-lg p-3">
                <div class="flex items-center space-x-2">
                  <i class="fas fa-camera text-gray-600"></i>
                  <span class="text-sm font-medium text-gray-700">Kamera:</span>
                  <span class="text-sm text-gray-600">{{ getCurrentCameraLabel() }}</span>
                </div>
                <button @click="switchCamera" 
                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded-lg text-sm font-medium transition-all">
                  <i class="fas fa-sync-alt mr-1"></i>
                  Switch
                </button>
              </div>
              
              <!-- QR Reader -->
              <div class="relative">
                <div id="qr-reader" class="w-full h-64 bg-gray-100 rounded-lg overflow-hidden"></div>
                
                <!-- Switch Camera Button (Overlay) -->
                <div v-if="cameras.length > 1" class="absolute top-2 right-2">
                  <button @click="switchCamera" 
                          class="bg-black/50 hover:bg-black/70 text-white p-2 rounded-full transition-all backdrop-blur-sm"
                          title="Switch Kamera">
                    <i class="fas fa-sync-alt text-sm"></i>
                  </button>
                </div>
              </div>
              
              <!-- Scanner Controls -->
              <div class="flex space-x-2">
                <button v-if="cameras.length > 1" @click="switchCamera" 
                        class="flex-1 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold transition-all">
                  <i class="fas fa-sync-alt mr-2"></i>
                  Switch Kamera
                </button>
                <button @click="closeScanner" 
                        class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-lg font-semibold transition-all">
                  <i class="fas fa-times mr-2"></i>
                  Tutup Scanner
                </button>
              </div>
            </div>
            
            <!-- Manual Input -->
            <div class="mt-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Atau masukkan QR Code manual:
              </label>
              <div class="flex space-x-2">
                <input 
                  v-model="manualQRCode"
                  type="text"
                  placeholder="Paste QR Code di sini..."
                  class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  @keyup.enter="processManualQR"
                />
                <button 
                  @click="processManualQR"
                  :disabled="!manualQRCode.trim() || processing"
                  class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <i v-if="processing" class="fas fa-spinner fa-spin"></i>
                  <i v-else class="fas fa-check"></i>
                </button>
              </div>
            </div>
          </div>

          <!-- Status -->
          <div v-if="status" class="mt-4 p-3 rounded-lg" :class="statusClasses">
            <div class="flex items-center">
              <i :class="statusIcon" class="mr-2"></i>
              <span class="font-medium">{{ status }}</span>
            </div>
            <p v-if="statusMessage" class="text-sm mt-1 opacity-80">{{ statusMessage }}</p>
          </div>

          <!-- Recent Activity -->
          <div v-if="recentActivity.length > 0" class="mt-4">
            <h4 class="text-sm font-medium text-gray-700 mb-2">Aktivitas Terbaru</h4>
            <div class="space-y-2 max-h-24 overflow-y-auto">
              <div 
                v-for="activity in recentActivity" 
                :key="activity.id"
                class="flex items-center justify-between p-2 bg-gray-50 rounded-lg text-sm"
              >
                <div>
                  <span class="font-medium">{{ activity.participant }}</span>
                  <span class="text-gray-500 ml-2">{{ activity.action }}</span>
                </div>
                <span class="text-xs text-gray-400">{{ activity.time }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Instructions -->
        <div class="bg-blue-50 rounded-lg p-4">
          <h4 class="font-semibold text-blue-800 mb-2">
            <i class="fas fa-info-circle mr-2"></i>
            Cara Penggunaan:
          </h4>
          <ul class="text-sm text-blue-700 space-y-1">
            <li>• Pilih mode Check In atau Check Out</li>
            <li>• Scan QR Code menggunakan kamera atau masukkan manual</li>
            <li>• QR Code akan memvalidasi kehadiran Anda</li>
            <li>• Status kehadiran akan terupdate otomatis</li>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <!-- Quiz Modal -->
  <div v-if="showQuizModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
      <!-- Quiz Header -->
      <div class="bg-blue-600 text-white p-4 flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold">{{ currentQuiz?.title }}</h3>
          <p class="text-blue-100 text-sm">{{ currentQuiz?.description }}</p>
        </div>
        <div class="flex items-center gap-4">
          <!-- Timer -->
          <div v-if="quizTimer > 0" class="text-right">
            <div class="text-sm text-blue-100">Time Remaining</div>
            <div class="text-lg font-mono font-bold">{{ formatTime(quizTimer) }}</div>
          </div>
          <!-- Question Counter -->
          <div class="text-right">
            <div class="text-sm text-blue-100">Question</div>
            <div class="text-lg font-bold">{{ currentQuestionIndex + 1 }} / {{ currentQuiz?.questions?.length }}</div>
          </div>
          <button @click="closeQuizModal" class="text-white hover:text-blue-200">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
      </div>

      <!-- Quiz Content -->
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
        <!-- Quiz Instructions -->
        <div v-if="currentQuiz?.instructions && !quizStarted" class="mb-6 p-4 bg-blue-50 rounded-lg">
          <h4 class="font-semibold text-blue-800 mb-2">Instructions:</h4>
          <p class="text-blue-700">{{ currentQuiz.instructions }}</p>
          <div class="mt-4 flex items-center gap-4 text-sm text-blue-600">
            <span><i class="fas fa-clock mr-1"></i>{{ currentQuiz.time_limit_minutes || 'No time limit' }} minutes</span>
            <span><i class="fas fa-percentage mr-1"></i>Passing score: {{ currentQuiz.passing_score }}%</span>
            <span><i class="fas fa-redo mr-1"></i>{{ currentQuiz.max_attempts || 'Unlimited' }} attempts</span>
          </div>
        </div>

        <!-- Quiz Questions -->
        <div v-if="quizStarted && currentQuestion" class="space-y-6">
          <!-- Question -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <div class="flex items-start gap-3">
              <div class="bg-blue-600 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold">
                {{ currentQuestionIndex + 1 }}
              </div>
              <div class="flex-1">
                <h4 class="font-semibold text-gray-800 mb-2">{{ currentQuestion.question_text }}</h4>
                <div class="text-sm text-gray-600 mb-4">
                  <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded">{{ currentQuestion.question_type.replace('_', ' ').toUpperCase() }}</span>
                  <span class="ml-2">{{ currentQuestion.points }} points</span>
                  <span v-if="currentQuestion.is_required" class="ml-2 text-red-600">*Required</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Multiple Choice Options -->
          <div v-if="currentQuestion.question_type === 'multiple_choice'" class="space-y-2">
            <label v-for="option in currentQuestion.options" :key="option.id" 
                   class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors"
                   :class="{ 'border-blue-500 bg-blue-50': quizAnswers[currentQuestion.id] === option.id }">
              <input type="radio" 
                     :name="'question_' + currentQuestion.id" 
                     :value="option.id"
                     v-model="quizAnswers[currentQuestion.id]"
                     class="mr-3 text-blue-600">
              <span class="flex-1">{{ option.option_text }}</span>
            </label>
          </div>

          <!-- True/False Options -->
          <div v-if="currentQuestion.question_type === 'true_false'" class="space-y-2">
            <label v-for="option in currentQuestion.options" :key="option.id" 
                   class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors"
                   :class="{ 'border-blue-500 bg-blue-50': quizAnswers[currentQuestion.id] === option.id }">
              <input type="radio" 
                     :name="'question_' + currentQuestion.id" 
                     :value="option.id"
                     v-model="quizAnswers[currentQuestion.id]"
                     class="mr-3 text-blue-600">
              <span class="flex-1">{{ option.option_text }}</span>
            </label>
          </div>

          <!-- Essay Question -->
          <div v-if="currentQuestion.question_type === 'essay'" class="space-y-2">
            <textarea v-model="quizAnswers[currentQuestion.id]"
                      placeholder="Type your answer here..."
                      class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                      rows="4"></textarea>
          </div>
        </div>

        <!-- Quiz Results -->
        <div v-if="showQuizResults" class="space-y-6">
          <div class="text-center">
            <div class="text-6xl mb-4">
              <i v-if="quizResult.is_passed" class="fas fa-check-circle text-green-500"></i>
              <i v-else class="fas fa-times-circle text-red-500"></i>
            </div>
            <h3 class="text-2xl font-bold mb-2" :class="quizResult.is_passed ? 'text-green-600' : 'text-red-600'">
              {{ quizResult.is_passed ? 'Congratulations!' : 'Try Again' }}
            </h3>
            <p class="text-gray-600 mb-4">
              You scored {{ quizResult.score }}% ({{ quizResult.is_passed ? 'Passed' : 'Failed' }})
            </p>
            <div class="bg-gray-50 p-4 rounded-lg">
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span class="text-gray-600">Total Questions:</span>
                  <span class="font-semibold ml-2">{{ currentQuiz?.questions?.length }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Correct Answers:</span>
                  <span class="font-semibold ml-2">{{ quizResult.correct_answers }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Time Taken:</span>
                  <span class="font-semibold ml-2">{{ formatTime(quizResult.time_taken) }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Passing Score:</span>
                  <span class="font-semibold ml-2">{{ currentQuiz?.passing_score }}%</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quiz Footer -->
      <div class="bg-gray-50 p-4 flex items-center justify-between">
        <div class="flex gap-2">
          <!-- Previous Question -->
          <button v-if="quizStarted && currentQuestionIndex > 0" 
                  @click="previousQuestion"
                  class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors">
            <i class="fas fa-arrow-left mr-1"></i>
            Previous
          </button>
        </div>

        <div class="flex gap-2">
          <!-- Start Quiz -->
          <button v-if="!quizStarted" 
                  @click="startQuizAttempt"
                  class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors">
            <i class="fas fa-play mr-1"></i>
            Start Quiz
          </button>

          <!-- Next Question / Submit -->
          <button v-if="quizStarted && !showQuizResults" 
                  @click="nextQuestion"
                  :disabled="!canProceed"
                  class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed">
            <i v-if="currentQuestionIndex < currentQuiz.questions.length - 1" class="fas fa-arrow-right mr-1"></i>
            <i v-else class="fas fa-check mr-1"></i>
            {{ currentQuestionIndex < currentQuiz.questions.length - 1 ? 'Next' : 'Submit Quiz' }}
          </button>

          <!-- Close Results -->
          <button v-if="showQuizResults" 
                  @click="closeQuizModal"
                  class="px-6 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors">
            <i class="fas fa-times mr-1"></i>
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Questionnaire Modal -->
  <div v-if="showQuestionnaireModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
      <!-- Questionnaire Header -->
      <div class="bg-green-600 text-white p-4 flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold">{{ currentQuestionnaire?.title }}</h3>
          <p class="text-green-100 text-sm">{{ currentQuestionnaire?.description }}</p>
        </div>
        <div class="flex items-center gap-4">
          <!-- Question Counter -->
          <div class="text-right">
            <div class="text-sm text-green-100">Question</div>
            <div class="text-lg font-bold">{{ currentQuestionIndex + 1 }} / {{ currentQuestionnaire?.questions?.length }}</div>
          </div>
          <button @click="closeQuestionnaireModal" class="text-white hover:text-green-200">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
      </div>

      <!-- Questionnaire Content -->
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
        <!-- Questionnaire Instructions -->
        <div v-if="currentQuestionnaire?.instructions && !questionnaireStarted" class="mb-6 p-4 bg-green-50 rounded-lg">
          <h4 class="font-semibold text-green-800 mb-2">Instructions:</h4>
          <p class="text-green-700">{{ currentQuestionnaire.instructions }}</p>
          <div class="mt-4 flex items-center gap-4 text-sm text-green-600">
            <span v-if="currentQuestionnaire.is_anonymous" class="flex items-center gap-1">
              <i class="fas fa-user-secret"></i>
              Anonymous Response
            </span>
            <span v-if="currentQuestionnaire.allow_multiple_responses" class="flex items-center gap-1">
              <i class="fas fa-redo"></i>
              Multiple Responses Allowed
            </span>
            <span v-if="currentQuestionnaire.start_date">
              <i class="fas fa-calendar-start mr-1"></i>
              Start: {{ formatDate(currentQuestionnaire.start_date) }}
            </span>
            <span v-if="currentQuestionnaire.end_date">
              <i class="fas fa-calendar-end mr-1"></i>
              End: {{ formatDate(currentQuestionnaire.end_date) }}
            </span>
          </div>
        </div>

        <!-- Questionnaire Questions -->
        <div v-if="questionnaireStarted && currentQuestion" class="space-y-6">
          <!-- Question -->
          <div class="bg-gray-50 p-4 rounded-lg">
            <div class="flex items-start gap-3">
              <div class="bg-green-600 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-bold">
                {{ currentQuestionIndex + 1 }}
              </div>
              <div class="flex-1">
                <h4 class="font-semibold text-gray-800 mb-2">{{ currentQuestion.question_text }}</h4>
                <div class="text-sm text-gray-600 mb-4">
                  <span class="bg-green-100 text-green-800 px-2 py-1 rounded">{{ currentQuestion.question_type.replace('_', ' ').toUpperCase() }}</span>
                  <span v-if="currentQuestion.is_required" class="ml-2 text-red-600">*Required</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Multiple Choice Options -->
          <div v-if="currentQuestion.question_type === 'multiple_choice'" class="space-y-2">
            <label v-for="option in currentQuestion.options" :key="option.id" 
                   class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors"
                   :class="{ 'border-green-500 bg-green-50': questionnaireAnswers[currentQuestion.id] === option.id }">
              <input type="radio" 
                     :name="'question_' + currentQuestion.id" 
                     :value="option.id"
                     v-model="questionnaireAnswers[currentQuestion.id]"
                     class="mr-3 text-green-600">
              <span class="flex-1">{{ option.option_text }}</span>
            </label>
          </div>

          <!-- True/False Options -->
          <div v-if="currentQuestion.question_type === 'true_false'" class="space-y-2">
            <label v-for="option in currentQuestion.options" :key="option.id" 
                   class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors"
                   :class="{ 'border-green-500 bg-green-50': questionnaireAnswers[currentQuestion.id] === option.id }">
              <input type="radio" 
                     :name="'question_' + currentQuestion.id" 
                     :value="option.id"
                     v-model="questionnaireAnswers[currentQuestion.id]"
                     class="mr-3 text-green-600">
              <span class="flex-1">{{ option.option_text }}</span>
            </label>
          </div>

          <!-- Checkbox Options -->
          <div v-if="currentQuestion.question_type === 'checkbox'" class="space-y-2">
            <label v-for="option in currentQuestion.options" :key="option.id" 
                   class="flex items-center p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition-colors"
                   :class="{ 'border-green-500 bg-green-50': (questionnaireAnswers[currentQuestion.id] || []).includes(option.id) }">
              <input type="checkbox" 
                     :value="option.id"
                     v-model="questionnaireAnswers[currentQuestion.id]"
                     class="mr-3 text-green-600">
              <span class="flex-1">{{ option.option_text }}</span>
            </label>
          </div>

          <!-- Rating Question -->
          <div v-if="currentQuestion.question_type === 'rating'" class="space-y-4">
            <div class="flex items-center justify-center space-x-2">
              <span class="text-sm text-gray-600">Poor</span>
              <div class="flex space-x-1">
                <label v-for="rating in [1,2,3,4,5]" :key="rating" 
                       class="cursor-pointer">
                  <input type="radio" 
                         :name="'rating_' + currentQuestion.id" 
                         :value="rating"
                         v-model="questionnaireAnswers[currentQuestion.id]"
                         class="sr-only">
                  <i class="fas fa-star text-2xl transition-colors"
                     :class="questionnaireAnswers[currentQuestion.id] >= rating ? 'text-yellow-400' : 'text-gray-300'"></i>
                </label>
              </div>
              <span class="text-sm text-gray-600">Excellent</span>
            </div>
            <div class="text-center text-sm text-gray-600">
              {{ questionnaireAnswers[currentQuestion.id] || 0 }} / 5
            </div>
          </div>

          <!-- Essay Question -->
          <div v-if="currentQuestion.question_type === 'essay'" class="space-y-2">
            <textarea v-model="questionnaireAnswers[currentQuestion.id]"
                      placeholder="Type your answer here..."
                      class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      rows="4"></textarea>
          </div>
        </div>

        <!-- Questionnaire Results -->
        <div v-if="showQuestionnaireResults" class="space-y-6">
          <div class="text-center">
            <div class="text-6xl mb-4">
              <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <h3 class="text-2xl font-bold mb-2 text-green-600">
              Thank You!
            </h3>
            <p class="text-gray-600 mb-4">
              Your response has been submitted successfully.
            </p>
            <div class="bg-gray-50 p-4 rounded-lg">
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span class="text-gray-600">Total Questions:</span>
                  <span class="font-semibold ml-2">{{ currentQuestionnaire?.questions?.length }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Answered Questions:</span>
                  <span class="font-semibold ml-2">{{ questionnaireResult.answered_questions }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Submitted At:</span>
                  <span class="font-semibold ml-2">{{ formatDate(questionnaireResult.submitted_at) }}</span>
                </div>
                <div>
                  <span class="text-gray-600">Response ID:</span>
                  <span class="font-semibold ml-2">#{{ questionnaireResult.response_id }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Questionnaire Footer -->
      <div class="bg-gray-50 p-4 flex items-center justify-between">
        <div class="flex gap-2">
          <!-- Previous Question -->
          <button v-if="questionnaireStarted && currentQuestionIndex > 0" 
                  @click="previousQuestion"
                  class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors">
            <i class="fas fa-arrow-left mr-1"></i>
            Previous
          </button>
        </div>

        <div class="flex gap-2">
          <!-- Start Questionnaire -->
          <button v-if="!questionnaireStarted" 
                  @click="startQuestionnaireAttempt"
                  class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors">
            <i class="fas fa-play mr-1"></i>
            Start Questionnaire
          </button>

          <!-- Next Question / Submit -->
          <button v-if="questionnaireStarted && !showQuestionnaireResults" 
                  @click="nextQuestion"
                  :disabled="!canProceed"
                  class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed">
            <i v-if="currentQuestionIndex < currentQuestionnaire.questions.length - 1" class="fas fa-arrow-right mr-1"></i>
            <i v-else class="fas fa-check mr-1"></i>
            {{ currentQuestionIndex < currentQuestionnaire.questions.length - 1 ? 'Next' : 'Submit Questionnaire' }}
          </button>

          <!-- Close Results -->
          <button v-if="showQuestionnaireResults" 
                  @click="closeQuestionnaireModal"
                  class="px-6 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors">
            <i class="fas fa-times mr-1"></i>
            Close
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Feedback Modal -->
  <div v-if="showFeedbackModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
      <!-- Feedback Header -->
      <div class="bg-gradient-to-r from-green-600 to-blue-600 text-white p-6">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-2xl font-bold">Berikan Feedback Training</h3>
            <p class="text-green-100 mt-1">{{ props.notification.course_title }}</p>
          </div>
          <button @click="closeFeedbackModal" class="text-white hover:text-green-200">
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>
      </div>

      <!-- Feedback Content -->
      <div class="p-6 overflow-y-auto max-h-[calc(90vh-200px)]">
        <!-- Training Rating -->
        <div class="mb-8">
          <h4 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-star mr-2 text-yellow-500"></i>
            Rating Training
          </h4>
          <div class="flex items-center justify-center space-x-2 mb-4">
            <span class="text-sm text-gray-600">Sangat Buruk</span>
            <div class="flex space-x-1">
              <label v-for="rating in [1,2,3,4,5]" :key="rating" class="cursor-pointer">
                <input type="radio" 
                       name="training_rating" 
                       :value="rating"
                       v-model="feedbackData.training_rating"
                       class="sr-only">
                <i class="fas fa-star text-3xl transition-colors"
                   :class="feedbackData.training_rating >= rating ? 'text-yellow-400' : 'text-gray-300'"></i>
              </label>
            </div>
            <span class="text-sm text-gray-600">Sangat Baik</span>
          </div>
          <div class="text-center text-sm text-gray-600">
            {{ feedbackData.training_rating || 0 }} / 5 bintang
          </div>
        </div>

        <!-- Trainer Ratings -->
        <div v-if="props.notification.trainers && props.notification.trainers.length > 0" class="mb-8">
          <h4 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-chalkboard-teacher mr-2 text-blue-500"></i>
            Rating Trainer
          </h4>
          <div v-for="trainer in props.notification.trainers" :key="trainer.id" class="mb-6 p-4 bg-gray-50 rounded-lg">
            <div class="flex items-center justify-between mb-3">
              <div>
                <h5 class="font-semibold text-gray-800">{{ trainer.nama_lengkap }}</h5>
                <p class="text-sm text-gray-600">{{ trainer.nama_jabatan }} - {{ trainer.nama_divisi }}</p>
                <span v-if="trainer.is_primary_trainer" class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full mt-1">
                  Primary Trainer
                </span>
              </div>
            </div>
            <div class="flex items-center justify-center space-x-2">
              <span class="text-sm text-gray-600">Sangat Buruk</span>
              <div class="flex space-x-1">
                <label v-for="rating in [1,2,3,4,5]" :key="rating" class="cursor-pointer">
                  <input type="radio" 
                         :name="'trainer_rating_' + trainer.id" 
                         :value="rating"
                         v-model="feedbackData.trainer_ratings[trainer.id]"
                         class="sr-only">
                  <i class="fas fa-star text-2xl transition-colors"
                     :class="feedbackData.trainer_ratings[trainer.id] >= rating ? 'text-yellow-400' : 'text-gray-300'"></i>
                </label>
              </div>
              <span class="text-sm text-gray-600">Sangat Baik</span>
            </div>
            <div class="text-center text-sm text-gray-600 mt-2">
              {{ feedbackData.trainer_ratings[trainer.id] || 0 }} / 5 bintang
            </div>
          </div>
        </div>

        <!-- Training Comments -->
        <div class="mb-6">
          <h4 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-comment mr-2 text-green-500"></i>
            Kesan dan Pesan
          </h4>
          <textarea v-model="feedbackData.comments"
                    placeholder="Bagikan kesan dan pesan Anda tentang training ini..."
                    class="w-full p-4 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    rows="6"></textarea>
        </div>

        <!-- Suggestions -->
        <div class="mb-6">
          <h4 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-lightbulb mr-2 text-yellow-500"></i>
            Saran untuk Training Selanjutnya
          </h4>
          <textarea v-model="feedbackData.suggestions"
                    placeholder="Berikan saran untuk meningkatkan kualitas training selanjutnya..."
                    class="w-full p-4 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    rows="4"></textarea>
        </div>
      </div>

      <!-- Feedback Footer -->
      <div class="bg-gray-50 p-6 flex items-center justify-between">
        <div class="text-sm text-gray-600">
          <i class="fas fa-info-circle mr-1"></i>
          Feedback Anda akan membantu kami meningkatkan kualitas training
        </div>
        <div class="flex gap-3">
          <button @click="closeFeedbackModal" 
                  class="px-6 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors">
            <i class="fas fa-times mr-1"></i>
            Batal
          </button>
          <button @click="submitFeedback" 
                  :disabled="!canSubmitFeedback"
                  class="px-6 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed">
            <i class="fas fa-paper-plane mr-1"></i>
            Kirim Feedback
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
  notification: Object
})

const emit = defineEmits(['close'])

// Reactive data
const scanMode = ref('checkin')
const manualQRCode = ref('')
const status = ref('')
const statusMessage = ref('')
const processing = ref(false)
const recentActivity = ref([])
const showScanner = ref(false)
const cameras = ref([])
const selectedCameraId = ref('')
let html5QrCode = null

// Quiz functionality
const showQuizModal = ref(false)
const currentQuiz = ref(null)
const currentQuestionIndex = ref(0)
const quizStarted = ref(false)
const quizAnswers = ref({})
const quizTimer = ref(0)
const quizStartTime = ref(null)
const showQuizResults = ref(false)
const quizResult = ref({})
const currentAttempt = ref(null)
let quizTimerInterval = null

// Questionnaire functionality
const showQuestionnaireModal = ref(false)
const currentQuestionnaire = ref(null)
const questionnaireStarted = ref(false)
const questionnaireAnswers = ref({})
const showQuestionnaireResults = ref(false)
const questionnaireResult = ref({})
const currentResponse = ref(null)

// Feedback functionality
const showFeedbackModal = ref(false)
const feedbackData = ref({
  training_rating: null,
  trainer_ratings: {},
  comments: '',
  suggestions: ''
})

// Computed properties
const statusClasses = computed(() => {
  if (status.value === 'Berhasil!') {
    return 'bg-green-100 text-green-800 border border-green-200'
  } else if (status.value === 'Error!') {
    return 'bg-red-100 text-red-800 border border-red-200'
  } else if (status.value === 'Memproses QR Code...') {
    return 'bg-blue-100 text-blue-800 border border-blue-200'
  }
  return 'bg-gray-100 text-gray-800 border border-gray-200'
})

const statusIcon = computed(() => {
  if (status.value === 'Berhasil!') {
    return 'fas fa-check-circle text-green-600'
  } else if (status.value === 'Error!') {
    return 'fas fa-exclamation-circle text-red-600'
  } else if (status.value === 'Memproses QR Code...') {
    return 'fas fa-spinner fa-spin text-blue-600'
  }
  return 'fas fa-info-circle text-gray-600'
})

// Quiz and Questionnaire computed properties
const currentQuestion = computed(() => {
  if (showQuizModal.value && currentQuiz.value?.questions) {
    return currentQuiz.value.questions[currentQuestionIndex.value]
  }
  if (showQuestionnaireModal.value && currentQuestionnaire.value?.questions) {
    return currentQuestionnaire.value.questions[currentQuestionIndex.value]
  }
  return null
})

const canProceed = computed(() => {
  if (!currentQuestion.value) return false
  
  // For required questions, check if answered
  if (currentQuestion.value.is_required) {
    const answer = showQuizModal.value ? 
      quizAnswers.value[currentQuestion.value.id] : 
      questionnaireAnswers.value[currentQuestion.value.id]
    
    if (currentQuestion.value.question_type === 'checkbox') {
      return answer && Array.isArray(answer) && answer.length > 0
    }
    
    return answer !== undefined && answer !== null && answer !== ''
  }
  
  return true
})

const isLastQuestion = computed(() => {
  if (showQuizModal.value) {
    return currentQuestionIndex.value === currentQuiz.value?.questions?.length - 1
  }
  if (showQuestionnaireModal.value) {
    return currentQuestionIndex.value === currentQuestionnaire.value?.questions?.length - 1
  }
  return false
})

// Feedback computed properties
const canSubmitFeedback = computed(() => {
  return feedbackData.value.training_rating !== null && feedbackData.value.training_rating > 0
})

// Checkout computed properties
const canCheckout = computed(() => {
  // User can checkout if they are checked in (for participants) or assigned (for trainers)
  if (props.notification.role === 'Peserta') {
    return props.notification.status === 'invited'
  } else if (props.notification.role === 'Trainer') {
    return true // Trainers can always checkout
  }
  return false
})

// Methods
const formatTrainingDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const formatTrainingTime = (startTime, endTime) => {
  return `${startTime} - ${endTime}`
}

const formatDuration = (minutes) => {
  if (!minutes) return 'Durasi tidak ditentukan'
  
  const hours = Math.floor(minutes / 60)
  const remainingMinutes = minutes % 60
  
  if (hours > 0 && remainingMinutes > 0) {
    return `${hours} jam ${remainingMinutes} menit`
  } else if (hours > 0) {
    return `${hours} jam`
  } else {
    return `${minutes} menit`
  }
}

const getRoleBadgeClass = (role) => {
  if (role === 'Primary Trainer') {
    return 'bg-yellow-100 text-yellow-800'
  } else if (role === 'Trainer') {
    return 'bg-purple-100 text-purple-800'
  } else if (role === 'Peserta') {
    return 'bg-blue-100 text-blue-800'
  }
  return 'bg-gray-100 text-gray-800'
}

const getItemTypeLabel = (itemType) => {
  const labels = {
    'quiz': 'Quiz',
    'material': 'Materi',
    'questionnaire': 'Kuesioner'
  }
  return labels[itemType] || itemType
}

const getItemTypeIcon = (itemType) => {
  const icons = {
    'quiz': 'fas fa-question-circle',
    'material': 'fas fa-file-alt',
    'questionnaire': 'fas fa-clipboard-list'
  }
  return icons[itemType] || 'fas fa-file'
}

const getItemTypeBadgeClass = (itemType) => {
  const classes = {
    'quiz': 'bg-blue-100 text-blue-800',
    'material': 'bg-green-100 text-green-800',
    'questionnaire': 'bg-purple-100 text-purple-800'
  }
  return classes[itemType] || 'bg-gray-100 text-gray-800'
}

const getProgressStatusText = (status) => {
  const statusMap = {
    'not_started': 'Belum Dimulai',
    'in_progress': 'Sedang Berlangsung',
    'completed': 'Selesai',
    'failed': 'Gagal'
  }
  return statusMap[status] || status
}

const getSessionIcon = (session) => {
  if (!session.can_access) {
    return 'fas fa-lock'
  } else if (session.progress && session.progress.status === 'completed') {
    return 'fas fa-check-circle'
  } else if (session.progress && session.progress.status === 'in_progress') {
    return 'fas fa-play-circle'
  } else {
    return 'fas fa-circle'
  }
}

const getSessionBadgeClass = (session) => {
  if (!session.can_access) {
    return 'bg-gray-100 text-gray-500'
  } else if (session.progress && session.progress.status === 'completed') {
    return 'bg-green-100 text-green-800'
  } else if (session.progress && session.progress.status === 'in_progress') {
    return 'bg-yellow-100 text-yellow-800'
  } else {
    return 'bg-indigo-100 text-indigo-800'
  }
}

const getItemCardClass = (item) => {
  if (!item.can_access) {
    return 'bg-gray-50 border-gray-300 opacity-60'
  } else if (item.progress && item.progress.status === 'completed') {
    return 'bg-green-50 border-green-200 hover:shadow-md'
  } else if (item.progress && item.progress.status === 'in_progress') {
    return 'bg-yellow-50 border-yellow-200 hover:shadow-md'
  } else {
    return 'bg-white border-gray-200 hover:shadow-md'
  }
}

const getProgressLabel = (status) => {
  const labels = {
    'not_started': 'Belum Dimulai',
    'in_progress': 'Sedang Berlangsung',
    'completed': 'Selesai',
    'passed': 'Lulus',
    'failed': 'Tidak Lulus'
  }
  return labels[status] || status
}

const getProgressPercentage = (status) => {
  const percentages = {
    'not_started': '0%',
    'in_progress': '50%',
    'completed': '100%',
    'passed': '100%',
    'failed': '100%'
  }
  return percentages[status] || '0%'
}

const getProgressBarClass = (status) => {
  const classes = {
    'not_started': 'bg-gray-400',
    'in_progress': 'bg-yellow-500',
    'completed': 'bg-blue-500',
    'passed': 'bg-green-500',
    'failed': 'bg-red-500'
  }
  return classes[status] || 'bg-gray-400'
}

const getProgressTextClass = (status) => {
  const classes = {
    'not_started': 'text-gray-600',
    'in_progress': 'text-yellow-600',
    'completed': 'text-blue-600',
    'passed': 'text-green-600',
    'failed': 'text-red-600'
  }
  return classes[status] || 'text-gray-600'
}



const closeScanner = () => {
  showScanner.value = false
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {})
  }
}

const setupCameras = async () => {
  if (!window.Html5Qrcode) return
  
  try {
    const devices = await window.Html5Qrcode.getCameras()
    cameras.value = devices
    
    // Find back camera
    const backCamera = devices.find(cam => 
      cam.label.toLowerCase().includes('back') || 
      cam.label.toLowerCase().includes('belakang')
    )
    
    selectedCameraId.value = backCamera?.id || devices[0]?.id || ''
    startScanner()
  } catch (err) {
    console.error('Error getting cameras:', err)
    status.value = 'Error!'
    statusMessage.value = 'Gagal mengakses kamera'
  }
}

const startScanner = () => {
  if (!window.Html5Qrcode || !selectedCameraId.value) return
  
  if (html5QrCode) {
    html5QrCode.stop().then(() => html5QrCode.clear())
  }
  
  html5QrCode = new window.Html5Qrcode('qr-reader')
  html5QrCode.start(
    selectedCameraId.value,
    { 
      fps: 10, 
      qrbox: 200,
      aspectRatio: 1.0
    },
    (decodedText) => {
      console.log('QR Code scanned:', decodedText)
      processQRCode(decodedText)
      closeScanner()
    },
    (errorMessage) => {
      // Ignore errors
    }
  )
}

const restartScanner = () => {
  startScanner()
}

const getCurrentCameraLabel = () => {
  const currentCamera = cameras.value.find(cam => cam.id === selectedCameraId.value)
  if (!currentCamera) return 'Unknown'
  
  // Simplify camera label
  const label = currentCamera.label.toLowerCase()
  if (label.includes('back') || label.includes('belakang')) {
    return 'Kamera Belakang'
  } else if (label.includes('front') || label.includes('depan')) {
    return 'Kamera Depan'
  } else {
    return currentCamera.label
  }
}

const switchCamera = () => {
  if (cameras.value.length <= 1) return
  
  // Show loading state
  status.value = 'Memproses...'
  statusMessage.value = 'Mengganti kamera...'
  
  const currentIndex = cameras.value.findIndex(cam => cam.id === selectedCameraId.value)
  const nextIndex = (currentIndex + 1) % cameras.value.length
  selectedCameraId.value = cameras.value[nextIndex].id
  
  // Restart scanner with new camera
  setTimeout(() => {
    restartScanner()
    // Clear status after a moment
    setTimeout(() => {
      status.value = ''
      statusMessage.value = ''
    }, 1000)
  }, 500)
}


const processManualQR = () => {
  if (manualQRCode.value.trim()) {
    processQRCode(manualQRCode.value.trim())
  }
}

const processQRCode = async (qrCode) => {
  try {
    status.value = 'Memproses QR Code...'
    statusMessage.value = ''
    processing.value = true

    const endpoint = scanMode.value === 'checkin' ? 'lms.schedules.checkin' : 'lms.schedules.checkout'
    
    const response = await router.post(route(endpoint), {
      qr_code: qrCode
    }, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        if (page.props.flash?.success) {
          status.value = 'Berhasil!'
          statusMessage.value = page.props.flash.success
          
          // Add to recent activity
          const activity = {
            id: Date.now(),
            participant: page.props.flash.participant || 'Unknown',
            action: scanMode.value === 'checkin' ? 'Check In' : 'Check Out',
            time: new Date().toLocaleTimeString('id-ID')
          }
          recentActivity.value.unshift(activity)
          
          // Keep only last 5 activities
          if (recentActivity.value.length > 5) {
            recentActivity.value = recentActivity.value.slice(0, 5)
          }

          // Show success notification
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: page.props.flash.success,
            timer: 2000,
            showConfirmButton: false
          })

          // Clear manual input
          manualQRCode.value = ''
        }
      },
      onError: (errors) => {
        status.value = 'Error!'
        statusMessage.value = Object.values(errors)[0] || 'Terjadi kesalahan'
        
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: statusMessage.value
        })
      }
    })

  } catch (error) {
    status.value = 'Error!'
    statusMessage.value = 'Terjadi kesalahan saat memproses QR Code'
    
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: statusMessage.value
    })
  } finally {
    processing.value = false
  }
}

// Watch for mode changes
watch(scanMode, () => {
  status.value = ''
  statusMessage.value = ''
})

// Lifecycle
onMounted(() => {
  // Modal ready
})

onUnmounted(() => {
  if (html5QrCode) {
    try {
      html5QrCode.stop().then(() => html5QrCode.clear()).catch(() => {})
    } catch (e) {
      // ignore
    }
  }
  
  // Cleanup quiz timer
  if (quizTimerInterval) {
    clearInterval(quizTimerInterval)
    quizTimerInterval = null
  }
})

// Quiz methods
const startQuiz = (item) => {
  currentQuiz.value = item.quiz
  showQuizModal.value = true
  resetQuizState()
}

const continueQuiz = (item) => {
  currentQuiz.value = item.quiz
  showQuizModal.value = true
  
  // Load existing answers if any
  if (item.quiz.answers) {
    quizAnswers.value = { ...item.quiz.answers }
  }
  
  // Find current question index
  const answeredQuestions = Object.keys(quizAnswers.value).length
  currentQuestionIndex.value = Math.min(answeredQuestions, item.quiz.questions.length - 1)
  
  quizStarted.value = true
  startQuizTimer()
}

const viewQuizResults = (item) => {
  currentQuiz.value = item.quiz
  showQuizModal.value = true
  showQuizResults.value = true
  
  // Load result data
  if (item.quiz.latest_attempt) {
    quizResult.value = {
      score: item.quiz.latest_attempt.score,
      is_passed: item.quiz.latest_attempt.is_passed,
      correct_answers: Math.round((item.quiz.latest_attempt.score / 100) * item.quiz.questions.length),
      time_taken: item.quiz.latest_attempt.completed_at ? 
        new Date(item.quiz.latest_attempt.completed_at) - new Date(item.quiz.latest_attempt.started_at) : 0
    }
  }
}

const resetQuizState = () => {
  currentQuestionIndex.value = 0
  quizStarted.value = false
  quizAnswers.value = {}
  quizTimer.value = 0
  quizStartTime.value = null
  showQuizResults.value = false
  quizResult.value = {}
  currentAttempt.value = null
  
  if (quizTimerInterval) {
    clearInterval(quizTimerInterval)
    quizTimerInterval = null
  }
}

const startQuizAttempt = async () => {
  try {
    // Create new attempt
    const response = await fetch('/api/quiz/start-attempt', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        quiz_id: currentQuiz.value.id,
        schedule_id: props.notification.schedule_id
      })
    })
    
    if (response.ok) {
      const data = await response.json()
      currentAttempt.value = data.attempt
      quizStarted.value = true
      quizStartTime.value = new Date()
      
      // Start timer if quiz has time limit
      if (currentQuiz.value.time_limit_minutes) {
        quizTimer.value = currentQuiz.value.time_limit_minutes * 60
        startQuizTimer()
      }
    } else {
      throw new Error('Failed to start quiz attempt')
    }
  } catch (error) {
    console.error('Error starting quiz:', error)
    Swal.fire('Error', 'Failed to start quiz. Please try again.', 'error')
  }
}

const startQuizTimer = () => {
  if (quizTimerInterval) {
    clearInterval(quizTimerInterval)
  }
  
  quizTimerInterval = setInterval(() => {
    if (quizTimer.value > 0) {
      quizTimer.value--
    } else {
      // Time's up - auto submit
      submitQuiz()
    }
  }, 1000)
}


const submitQuiz = async () => {
  try {
    if (quizTimerInterval) {
      clearInterval(quizTimerInterval)
      quizTimerInterval = null
    }
    
    const response = await fetch('/api/quiz/submit-attempt', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        attempt_id: currentAttempt.value.id,
        answers: quizAnswers.value
      })
    })
    
    if (response.ok) {
      const data = await response.json()
      quizResult.value = data.result
      showQuizResults.value = true
      
      // Update the quiz data in the notification
      if (props.notification.sessions) {
        props.notification.sessions.forEach(session => {
          session.items.forEach(item => {
            if (item.item_type === 'quiz' && item.quiz && item.quiz.id === currentQuiz.value.id) {
              item.quiz.attempts = data.updated_attempts
              item.quiz.latest_attempt = data.latest_attempt
              item.quiz.can_attempt = data.can_attempt
            }
          })
        })
      }
    } else {
      throw new Error('Failed to submit quiz')
    }
  } catch (error) {
    console.error('Error submitting quiz:', error)
    Swal.fire('Error', 'Failed to submit quiz. Please try again.', 'error')
  }
}

const closeQuizModal = () => {
  showQuizModal.value = false
  resetQuizState()
}

const formatTime = (seconds) => {
  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = seconds % 60
  return `${minutes.toString().padStart(2, '0')}:${remainingSeconds.toString().padStart(2, '0')}`
}

// Questionnaire methods
const startQuestionnaire = (item) => {
  currentQuestionnaire.value = item.questionnaire
  showQuestionnaireModal.value = true
  resetQuestionnaireState()
}

const viewQuestionnaireResults = (item) => {
  currentQuestionnaire.value = item.questionnaire
  showQuestionnaireModal.value = true
  showQuestionnaireResults.value = true
  
  // Load result data
  if (item.questionnaire.latest_response) {
    questionnaireResult.value = {
      response_id: item.questionnaire.latest_response.id,
      submitted_at: item.questionnaire.latest_response.submitted_at,
      answered_questions: Object.keys(item.questionnaire.answers || {}).length
    }
  }
}

const resetQuestionnaireState = () => {
  currentQuestionIndex.value = 0
  questionnaireStarted.value = false
  questionnaireAnswers.value = {}
  showQuestionnaireResults.value = false
  questionnaireResult.value = {}
  currentResponse.value = null
}

const startQuestionnaireAttempt = async () => {
  try {
    // Create new response
    const response = await fetch('/api/questionnaire/start-response', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        questionnaire_id: currentQuestionnaire.value.id,
        schedule_id: props.notification.schedule_id
      })
    })
    
    if (response.ok) {
      const data = await response.json()
      currentResponse.value = data.response
      questionnaireStarted.value = true
      
      // Load existing answers if any
      if (data.existing_answers) {
        questionnaireAnswers.value = { ...data.existing_answers }
      }
    } else {
      throw new Error('Failed to start questionnaire response')
    }
  } catch (error) {
    console.error('Error starting questionnaire:', error)
    Swal.fire('Error', 'Failed to start questionnaire. Please try again.', 'error')
  }
}

const nextQuestion = () => {
  if (isLastQuestion.value) {
    if (showQuizModal.value) {
      submitQuiz()
    } else if (showQuestionnaireModal.value) {
      submitQuestionnaire()
    }
  } else {
    currentQuestionIndex.value++
  }
}

const previousQuestion = () => {
  if (currentQuestionIndex.value > 0) {
    currentQuestionIndex.value--
  }
}

const submitQuestionnaire = async () => {
  try {
    const response = await fetch('/api/questionnaire/submit-response', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        response_id: currentResponse.value.id,
        answers: questionnaireAnswers.value
      })
    })
    
    if (response.ok) {
      const data = await response.json()
      questionnaireResult.value = data.result
      showQuestionnaireResults.value = true
      
      // Update the questionnaire data in the notification
      if (props.notification.sessions) {
        props.notification.sessions.forEach(session => {
          session.items.forEach(item => {
            if (item.item_type === 'questionnaire' && item.questionnaire && item.questionnaire.id === currentQuestionnaire.value.id) {
              item.questionnaire.responses = data.updated_responses
              item.questionnaire.latest_response = data.latest_response
              item.questionnaire.can_respond = data.can_respond
            }
          })
        })
      }
    } else {
      throw new Error('Failed to submit questionnaire')
    }
  } catch (error) {
    console.error('Error submitting questionnaire:', error)
    Swal.fire('Error', 'Failed to submit questionnaire. Please try again.', 'error')
  }
}

const closeQuestionnaireModal = () => {
  showQuestionnaireModal.value = false
  resetQuestionnaireState()
}

// Feedback methods
const openFeedbackModal = () => {
  showFeedbackModal.value = true
  resetFeedbackData()
}

const closeFeedbackModal = () => {
  showFeedbackModal.value = false
  resetFeedbackData()
}

const resetFeedbackData = () => {
  feedbackData.value = {
    training_rating: null,
    trainer_ratings: {},
    comments: '',
    suggestions: ''
  }
}

const submitFeedback = async () => {
  try {
    const response = await fetch('/api/training/feedback', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        schedule_id: props.notification.schedule_id,
        training_rating: feedbackData.value.training_rating,
        trainer_ratings: feedbackData.value.trainer_ratings,
        comments: feedbackData.value.comments,
        suggestions: feedbackData.value.suggestions
      })
    })
    
    if (response.ok) {
      const data = await response.json()
      
      // Show success message
      Swal.fire({
        icon: 'success',
        title: 'Feedback Terkirim!',
        text: 'Terima kasih atas feedback Anda. Ini akan membantu kami meningkatkan kualitas training.',
        confirmButtonText: 'OK'
      })
      
      // Update notification data
      props.notification.can_give_feedback = false
      
      // Close modal
      closeFeedbackModal()
    } else {
      throw new Error('Failed to submit feedback')
    }
  } catch (error) {
    console.error('Error submitting feedback:', error)
    Swal.fire('Error', 'Gagal mengirim feedback. Silakan coba lagi.', 'error')
  }
}

// Checkout methods
const checkoutTraining = async () => {
  try {
    // Show confirmation dialog
    const result = await Swal.fire({
      title: 'Checkout Training?',
      text: 'Apakah Anda yakin ingin checkout dari training ini? History training akan disimpan.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Ya, Checkout!',
      cancelButtonText: 'Batal'
    })

    if (!result.isConfirmed) {
      return
    }

    const response = await fetch('/api/training/checkout', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      },
      body: JSON.stringify({
        schedule_id: props.notification.schedule_id,
        user_type: props.notification.role === 'Trainer' ? 'trainer' : 'participant'
      })
    })
    
    if (response.ok) {
      const data = await response.json()
      
      // Show success message
      Swal.fire({
        icon: 'success',
        title: 'Checkout Berhasil!',
        text: `Training history telah disimpan. Completion: ${data.data.completion_percentage}%, Durasi: ${Math.round(data.data.user_duration_minutes / 60)} jam ${data.data.user_duration_minutes % 60} menit`,
        confirmButtonText: 'OK'
      })
      
      // Update notification status
      props.notification.status = 'checked_out'
      
      // Close modal
      emit('close')
    } else {
      throw new Error('Failed to checkout training')
    }
  } catch (error) {
    console.error('Error checking out training:', error)
    Swal.fire('Error', 'Gagal checkout training. Silakan coba lagi.', 'error')
  }
}

// Watch for scanner show/hide
watch(showScanner, async (val) => {
  if (val) {
    if (!window.Html5Qrcode) {
      const script = document.createElement('script')
      script.src = 'https://unpkg.com/html5-qrcode'
      script.onload = setupCameras
      document.body.appendChild(script)
    } else {
      setupCameras()
    }
  }
})
</script>

<style scoped>
/* Custom styles for QR scanner */
#qr-reader {
  border: 2px solid #e5e7eb;
}

#qr-reader video {
  border-radius: 0.5rem;
}

/* Hide html5-qrcode default elements */
#qr-reader__scan_region {
  background: transparent !important;
}
</style>
