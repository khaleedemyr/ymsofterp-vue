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
        <!-- Course Header -->
        <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-3xl shadow-2xl mb-8">
          <div class="p-8 bg-gradient-to-r from-blue-600/80 via-purple-600/80 to-pink-600/80 rounded-3xl">
            <div class="flex items-start justify-between">
              <div class="flex-1 space-y-4">
                <div class="flex items-center space-x-4 mb-4">
                  <Link :href="route('lms.courses.index')" 
                        class="px-4 py-2 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all duration-300 transform hover:scale-105">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali ke Training
                  </Link>
                  <span class="px-3 py-1 text-sm rounded-full font-semibold backdrop-blur-sm bg-green-500/20 text-green-200 border border-green-500/30">
                    Program Internal
                  </span>
                </div>
                
                <h1 class="text-4xl font-bold text-white drop-shadow-lg">
                  {{ course.title }}
                </h1>
                
                <p class="text-xl text-white/90 drop-shadow-md max-w-3xl">
                  {{ course.description }}
                </p>
                
                  <div class="flex items-center space-x-2">
                    <i class="fas fa-clock"></i>
                    <span>{{ course.duration_formatted }}</span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <i class="fas fa-users"></i>
                    <span>{{ course.enrollments_count }} peserta terdaftar</span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <i class="fas fa-star text-yellow-400"></i>
                    <span>4.5 (120 ulasan)</span>
                  </div>
                </div>
              </div>
              
              <div class="text-right space-y-4">
                                 <div class="text-center">
                   <div class="text-6xl font-bold text-white drop-shadow-lg animate-pulse">
                     {{ course.sessions?.length || 0 }}
                   </div>
                   <div class="text-lg text-white/90 drop-shadow-md">Sesi Training</div>
                 </div>
                 
                 <!-- Quick Schedule Button -->
                 <div v-if="canScheduleTraining" class="space-y-3">
                   <Link :href="route('lms.schedules.create', { course_id: course.id })" 
                         class="block w-full px-8 py-4 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl font-bold hover:from-green-600 hover:to-green-700 transform hover:scale-105 transition-all duration-300 shadow-lg text-center">
                     <i class="fas fa-calendar-plus mr-2"></i>
                     Jadwalkan Training
                   </Link>
                   <Link :href="route('lms.schedules.index')" 
                         class="block w-full px-6 py-3 bg-white/20 backdrop-blur-sm border border-white/30 rounded-xl text-white font-semibold hover:bg-white/30 transition-all duration-300 text-center">
                     <i class="fas fa-calendar-alt mr-2"></i>
                     Lihat Jadwal
                   </Link>
                 </div>
                
                
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-8">
          <!-- Course Content -->
          <div class="space-y-8">
            <!-- Course Sessions -->
            <div class="backdrop-blur-xl bg-gradient-to-br from-white/10 via-purple-500/5 to-blue-500/5 border border-white/20 rounded-3xl shadow-2xl overflow-hidden">
              <div class="p-8 border-b border-white/20 bg-gradient-to-r from-blue-600/20 via-purple-600/20 to-pink-600/20">
                <div class="flex items-center justify-between">
                  <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 via-purple-500 to-pink-500 rounded-2xl flex items-center justify-center shadow-lg transform rotate-3 hover:rotate-0 transition-transform duration-300">
                      <i class="fas fa-rocket text-2xl text-white drop-shadow-lg"></i>
                    </div>
                    <div>
                      <h3 class="text-3xl font-bold text-white drop-shadow-lg">
                        Sesi Training
                      </h3>
                      <div class="flex items-center space-x-4 mt-2">
                        <span class="px-4 py-2 bg-gradient-to-r from-blue-500/20 to-purple-500/20 border border-blue-400/30 rounded-full text-blue-200 font-semibold text-sm">
                          {{ course.sessions?.length || 0 }} Sesi
                        </span>
                        <span class="px-4 py-2 bg-gradient-to-r from-green-500/20 to-emerald-500/20 border border-green-400/30 rounded-full text-green-200 font-semibold text-sm">
                          {{ course.sessions?.reduce((total, session) => total + (session.items?.length || 0), 0) }} Item
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="text-right">
                    <div class="text-4xl font-bold text-white drop-shadow-lg">
                      {{ course.sessions?.reduce((total, session) => total + (session.estimated_duration_minutes || 0), 0) }}
                    </div>
                    <div class="text-white/80 font-medium">Total Menit</div>
                  </div>
                </div>
              </div>
              
              <div class="p-8">
                <!-- New Sessions Structure -->
                <div v-if="course.sessions && course.sessions.length > 0" class="space-y-8">
                  <div v-for="(session, sessionIndex) in course.sessions" :key="session.id" 
                       class="group relative">
                    <!-- Session Progress Bar -->
                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-full overflow-hidden">
                      <div class="h-full bg-gradient-to-r from-blue-500 to-purple-500 rounded-full transition-all duration-1000 ease-out"
                           :style="{ width: `${((sessionIndex + 1) / course.sessions.length) * 100}%` }"></div>
                    </div>
                    
                    <!-- Session Card -->
                    <div class="backdrop-blur-xl bg-gradient-to-br from-white/10 via-white/5 to-white/10 border border-white/20 rounded-2xl p-8 hover:bg-gradient-to-br hover:from-white/15 hover:via-purple-500/10 hover:to-white/15 transition-all duration-500 transform hover:scale-[1.02] hover:shadow-2xl group-hover:shadow-purple-500/25">
                      <!-- Session Header -->
                      <div class="flex items-center justify-between mb-6">
                                                   <div class="flex items-center space-x-6">
                             <div class="relative">
                               <div class="w-16 h-16 bg-gradient-to-br from-blue-500 via-purple-500 to-pink-500 rounded-2xl flex items-center justify-center shadow-xl transform group-hover:rotate-6 transition-transform duration-300">
                                 <i class="fas fa-play text-white text-xl"></i>
                               </div>
                               <div class="absolute -top-2 -right-2 w-8 h-8 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full flex items-center justify-center shadow-lg">
                                 <i class="fas fa-star text-white text-sm"></i>
                               </div>
                             </div>
                             <div class="flex-1">
                               <h4 class="text-2xl font-bold text-white drop-shadow-lg mb-2">{{ session.session_title }}</h4>
                               <p v-if="session.session_description" class="text-white/70 text-lg leading-relaxed max-w-2xl">{{ session.session_description }}</p>
                               <div class="flex items-center space-x-4 mt-3">
                                 <div class="flex items-center space-x-2 px-3 py-1 bg-blue-500/20 border border-blue-400/30 rounded-full">
                                   <i class="fas fa-clock text-blue-300"></i>
                                   <span class="text-blue-200 font-medium">{{ session.estimated_duration_minutes || 0 }} menit</span>
                                 </div>
                                 <div class="flex items-center space-x-2 px-3 py-1 bg-purple-500/20 border border-purple-400/30 rounded-full">
                                   <i class="fas fa-layer-group text-purple-300"></i>
                                   <span class="text-purple-200 font-medium">{{ session.items?.length || 0 }} item</span>
                                 </div>
                               </div>
                             </div>
                           </div>
                        </div>
                      </div>
                      
                      <!-- Session Items -->
                      <div v-if="session.items && session.items.length > 0" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <div v-for="(item, itemIndex) in session.items" :key="item.id" 
                               class="group/item relative backdrop-blur-xl bg-gradient-to-br from-white/10 via-white/5 to-white/10 border border-white/20 rounded-xl p-6 hover:bg-gradient-to-br hover:from-white/20 hover:via-purple-500/10 hover:to-white/20 transition-all duration-300 transform hover:scale-105 hover:shadow-lg">
                            <!-- Item Icon & Content -->
                            <div class="flex items-start space-x-4 mb-4">
                              <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg transform group-hover/item:rotate-12 transition-transform duration-300"
                                   :class="{
                                     'bg-gradient-to-br from-purple-500 to-pink-500': item.item_type === 'quiz',
                                     'bg-gradient-to-br from-blue-500 to-cyan-500': item.item_type === 'material',
                                     'bg-gradient-to-br from-green-500 to-emerald-500': item.item_type === 'questionnaire'
                                   }">
                                <i :class="{
                                  'fas fa-question-circle': item.item_type === 'quiz',
                                  'fas fa-file-alt': item.item_type === 'material',
                                  'fas fa-clipboard-list': item.item_type === 'questionnaire'
                                } + ' text-white text-lg'"></i>
                              </div>
                              
                              <div class="flex-1 space-y-3">
                                <!-- Item Title & Description -->
                                <div>
                                  <div class="font-bold text-white text-lg drop-shadow-md mb-1">
                                    <span v-if="item.item_type === 'quiz' && item.quiz_data">
                                      {{ item.quiz_data.title || 'Quiz' }}
                                    </span>
                                    <span v-else-if="item.item_type === 'questionnaire' && item.questionnaire_data">
                                      {{ item.questionnaire_data.title || 'Kuesioner' }}
                                    </span>
                                    <span v-else>
                                      {{ item.title || `${item.item_type.charAt(0).toUpperCase() + item.item_type.slice(1)} ${item.order_number}` }}
                                    </span>
                                  </div>
                                  <div v-if="item.description" class="text-white/70 text-sm leading-relaxed">{{ item.description }}</div>
                                  <div v-if="item.item_type === 'quiz' && item.quiz_data && item.quiz_data.description" class="text-white/60 text-sm mt-1">
                                    {{ item.quiz_data.description }}
                                  </div>
                                  <div v-if="item.item_type === 'questionnaire' && item.questionnaire_data && item.questionnaire_data.description" class="text-white/60 text-sm mt-1">
                                    {{ item.questionnaire_data.description }}
                                  </div>
                                  
                                  <!-- Quiz & Questionnaire Enhanced Display -->
                                  <div v-if="item.item_type === 'quiz' && item.quiz_data" class="mt-3 space-y-2">
                                    <!-- Quiz Badges -->
                                    <div class="flex flex-wrap items-center gap-2">
                                      <span class="px-3 py-1 bg-purple-500/20 border border-purple-400/30 rounded-full text-purple-200 text-sm font-medium">
                                        Quiz
                                      </span>
                                      <span v-if="item.estimated_duration_minutes" class="px-3 py-1 bg-blue-500/20 border border-blue-400/30 rounded-full text-blue-200 text-sm font-medium">
                                        {{ item.estimated_duration_minutes }} menit
                                      </span>
                                      <span v-if="item.quiz_data.passing_score" class="px-3 py-1 bg-green-500/20 border border-green-400/30 rounded-full text-green-200 text-sm font-medium">
                                        Pass: {{ item.quiz_data.passing_score }}%
                                      </span>
                                      <span v-if="item.quiz_data.time_limit_minutes" class="px-3 py-1 bg-orange-500/20 border border-orange-400/30 rounded-full text-orange-200 text-sm font-medium">
                                        ⏱️ {{ item.quiz_data.time_limit_minutes }}m
                                      </span>
                                      <span v-if="item.quiz_data.max_attempts" class="px-3 py-1 bg-yellow-500/20 border border-yellow-400/30 rounded-full text-yellow-200 text-sm font-medium">
                                        🔄 {{ item.quiz_data.max_attempts }}x
                                      </span>
                                    </div>
                                    
                                    <!-- Quiz Instructions -->
                                    <div v-if="item.quiz_data.instructions" class="bg-gradient-to-br from-purple-500/10 to-purple-600/10 border border-purple-400/20 rounded-lg p-3">
                                      <div class="flex items-start space-x-2">
                                        <i class="fas fa-info-circle text-purple-300 mt-1"></i>
                                        <div class="text-purple-200 text-sm">
                                          <div class="font-medium mb-1">Instruksi Quiz:</div>
                                          <div>{{ item.quiz_data.instructions }}</div>
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                  
                                  <div v-if="item.item_type === 'questionnaire' && item.questionnaire_data" class="mt-3 space-y-2">
                                    <!-- Questionnaire Badges -->
                                    <div class="flex flex-wrap items-center gap-2">
                                      <span class="px-3 py-1 bg-cyan-500/20 border border-cyan-400/30 rounded-full text-cyan-200 text-sm font-medium">
                                        Kuesioner
                                      </span>
                                      <span v-if="item.estimated_duration_minutes" class="px-3 py-1 bg-blue-500/20 border border-blue-400/30 rounded-full text-blue-200 text-sm font-medium">
                                        {{ item.estimated_duration_minutes }} menit
                                      </span>
                                      <span v-if="item.questionnaire_data.is_anonymous" class="px-3 py-1 bg-green-500/20 border border-green-400/30 rounded-full text-green-200 text-sm font-medium">
                                        🔒 Anonim
                                      </span>
                                      <span v-if="item.questionnaire_data.allow_multiple_responses" class="px-3 py-1 bg-yellow-500/20 border border-yellow-400/30 rounded-full text-yellow-200 text-sm font-medium">
                                        📝 Multi Response
                                      </span>
                                    </div>
                                    
                                    <!-- Questionnaire Instructions -->
                                    <div v-if="item.questionnaire_data.instructions" class="bg-gradient-to-br from-cyan-500/10 to-cyan-600/10 border border-cyan-400/20 rounded-lg p-3">
                                      <div class="flex items-start space-x-2">
                                        <i class="fas fa-info-circle text-cyan-300 mt-1"></i>
                                        <div class="text-cyan-200 text-sm">
                                          <div class="font-medium mb-1">Instruksi Kuesioner:</div>
                                          <div>{{ item.questionnaire_data.instructions }}</div>
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                
                                <!-- Material Content (if material type) -->
                                <div v-if="item.item_type === 'material' && item.material_data" class="mt-4 space-y-4">
                                  <!-- Material Type Badge -->
                                  <div class="flex items-center space-x-2 mb-3">
                                    <span class="px-3 py-1 bg-blue-500/20 border border-blue-400/30 rounded-full text-blue-200 text-sm font-medium">
                                      {{ getMaterialTypeText(item.material_data.primary_file_type || item.material_data.file_type) }}
                                    </span>
                                    <span v-if="item.material_data.estimated_duration_minutes" class="px-3 py-1 bg-purple-500/20 border border-purple-400/30 rounded-full text-purple-200 text-sm font-medium">
                                      {{ item.material_data.estimated_duration_minutes }} menit
                                    </span>
                                    <span v-if="item.material_data.files_count > 1" class="px-3 py-1 bg-green-500/20 border border-green-400/30 rounded-full text-green-200 text-sm font-medium">
                                      {{ item.material_data.files_count }} File
                                    </span>
                                  </div>
                                  
                                  <!-- Material Content Layout -->
                                  <div class="bg-gradient-to-br from-white/5 to-white/10 border border-white/20 rounded-xl p-4">
                                    <!-- Material Header -->
                                    <div class="flex items-start justify-between mb-4">
                                      <div class="flex-1">
                                        <h4 class="text-lg font-semibold text-white mb-2">{{ item.material_data.title }}</h4>
                                        <p v-if="item.material_data.description" class="text-white/70 text-sm leading-relaxed">
                                          {{ item.material_data.description }}
                                        </p>
                                      </div>
                                    </div>
                                    
                                    <!-- Multiple Files Display -->
                                    <div v-if="item.material_data.files && item.material_data.files.length > 0" class="space-y-4">
                                      <!-- Files Grid -->
                                      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-2 gap-4">
                                        <div v-for="(file, fileIndex) in item.material_data.files" :key="file.id" 
                                             class="group/file relative backdrop-blur-sm bg-gradient-to-br from-white/5 to-white/10 border border-white/20 rounded-xl p-5 hover:bg-gradient-to-br hover:from-white/10 hover:via-blue-500/10 hover:to-white/10 transition-all duration-300 transform hover:scale-[1.02] hover:shadow-xl">
                                          
                                          <!-- File Header -->
                                          <div class="flex items-center justify-between mb-3">
                                            <div class="flex items-center space-x-2">
                                              <span v-if="file.is_primary" class="w-2 h-2 bg-yellow-400 rounded-full"></span>
                                              <span class="text-xs text-white/60 font-medium">File {{ fileIndex + 1 }}</span>
                                            </div>
                                            <span v-if="file.is_primary" class="px-2 py-1 bg-yellow-500/20 border border-yellow-400/30 rounded-full text-yellow-200 text-xs font-medium">
                                              Primary
                                            </span>
                                          </div>
                                          
                                          <!-- File Thumbnail/Icon -->
                                          <div class="flex justify-center mb-4">
                                            <div v-if="file.file_type === 'image'" 
                                                 @click="openImageLightbox(file)"
                                                 class="cursor-pointer group relative">
                                              <img :src="`/storage/${file.file_path}`" 
                                                   :alt="file.file_name"
                                                   class="w-24 h-24 object-cover rounded-xl border border-white/20 shadow-lg group-hover:scale-105 transition-transform duration-300">
                                              <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-xl flex items-center justify-center">
                                                <i class="fas fa-expand text-white text-lg"></i>
                                              </div>
                                            </div>
                                            
                                            <div v-else-if="file.file_type === 'video'" 
                                                 @click="openVideoPlayer(file)"
                                                 class="cursor-pointer group relative">
                                              <div class="w-24 h-24 bg-gradient-to-br from-purple-500 to-pink-600 rounded-xl border border-white/20 shadow-lg group-hover:scale-105 transition-transform duration-300 flex items-center justify-center">
                                                <i class="fas fa-play-circle text-white text-3xl"></i>
                                              </div>
                                            </div>
                                            
                                            <div v-else-if="file.file_type === 'pdf'" 
                                                 @click="openPdfViewer(file)"
                                                 class="cursor-pointer group relative">
                                              <div class="w-24 h-24 bg-gradient-to-br from-red-500 to-orange-600 rounded-xl border border-white/20 shadow-lg group-hover:scale-105 transition-transform duration-300 flex items-center justify-center">
                                                <i class="fas fa-file-pdf text-white text-3xl"></i>
                                              </div>
                                              <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-xl flex items-center justify-center">
                                                <i class="fas fa-eye text-white text-lg"></i>
                                              </div>
                                            </div>
                                            
                                            <div v-else class="w-24 h-24 bg-gradient-to-br from-gray-500 to-gray-600 rounded-xl border border-white/20 shadow-lg flex items-center justify-center">
                                              <i class="fas fa-file text-white text-3xl"></i>
                                            </div>
                                          </div>
                                          
                                          <!-- File Info -->
                                          <div class="text-center space-y-2">
                                            <div class="text-sm font-medium text-white truncate" :title="file.file_name">
                                              {{ file.file_name }}
                                            </div>
                                            <div class="text-xs text-white/60 space-y-2">
                                              <div class="flex items-center justify-center">
                                                <span class="px-3 py-1 bg-white/10 border border-white/20 rounded-full text-white/70 text-xs font-medium">
                                                  {{ file.file_type?.toUpperCase() || 'FILE' }}
                                                </span>
                                              </div>
                                              <div v-if="file.file_size" class="text-white/50 text-center">
                                                {{ formatFileSize(file.file_size) }}
                                              </div>
                                            </div>
                                          </div>
                                          
                                          <!-- File Actions -->
                                          <div class="mt-4 flex justify-center">
                                            <!-- Preview Button -->
                                            <button v-if="file.file_type === 'image'" 
                                                    @click="openImageLightbox(file)"
                                                    class="px-4 py-2 bg-gradient-to-r from-blue-500 to-cyan-600 text-white rounded-xl font-medium hover:from-blue-600 hover:to-cyan-700 transform hover:scale-105 transition-all duration-300 shadow-lg text-sm">
                                              <i class="fas fa-expand mr-2"></i>
                                              Lihat
                                            </button>
                                            
                                            <button v-else-if="file.file_type === 'video'" 
                                                    @click="openVideoPlayer(file)"
                                                    class="px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-xl font-medium hover:from-purple-600 hover:to-pink-700 transform hover:scale-105 transition-all duration-300 shadow-lg text-sm">
                                              <i class="fas fa-play mr-2"></i>
                                              Putar
                                            </button>
                                            
                                            <button v-else-if="file.file_type === 'pdf'" 
                                                    @click="openPdfViewer(file)"
                                                    class="px-4 py-2 bg-gradient-to-r from-red-500 to-orange-600 text-white rounded-xl font-medium hover:from-red-600 hover:to-orange-700 transform hover:scale-105 transition-all duration-300 shadow-lg text-sm">
                                              <i class="fas fa-eye mr-2"></i>
                                              Lihat
                                            </button>
                                            
                                            <button v-else 
                                                    @click="downloadFile(file)"
                                                    class="px-4 py-2 bg-gradient-to-r from-gray-500 to-gray-600 text-white rounded-xl font-medium hover:from-gray-600 hover:to-gray-700 transform hover:scale-105 transition-all duration-300 shadow-lg text-sm">
                                              <i class="fas fa-download mr-2"></i>
                                              Download
                                            </button>
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                            
                            <!-- Item Details -->
                            <div class="flex items-center justify-between pt-4 border-t border-white/10">
                              <div class="flex items-center space-x-3">
                                <span class="px-3 py-1 bg-white/10 border border-white/20 rounded-full text-white/80 text-sm font-medium">
                                  {{ item.item_type.charAt(0).toUpperCase() + item.item_type.slice(1) }}
                                </span>
                                <span v-if="item.estimated_duration_minutes" class="px-3 py-1 bg-blue-500/20 border border-blue-400/30 rounded-full text-blue-200 text-sm font-medium">
                                  {{ item.estimated_duration_minutes }} menit
                                </span>
                              </div>
                              <div class="flex items-center space-x-2">
                                <span class="w-8 h-8 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-lg">
                                  {{ item.order_number }}
                                </span>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      <div v-else class="text-center py-8">
                        <div class="w-20 h-20 mx-auto mb-4 bg-white/10 rounded-full flex items-center justify-center">
                          <i class="fas fa-plus-circle text-3xl text-white/50"></i>
                        </div>
                        <p class="text-white/60 text-lg">Belum ada item dalam sesi ini</p>
                        <button class="mt-4 px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transform hover:scale-105 transition-all duration-300 shadow-lg">
                          <i class="fas fa-plus mr-2"></i>
                          Tambah Item
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Legacy Lessons Structure (fallback) -->
            <div v-if="!course.sessions || course.sessions.length === 0" class="space-y-4">
              <div v-if="course.lessons && course.lessons.length > 0" class="space-y-4">
                <div v-for="lesson in course.lessons" :key="lesson.id" 
                     class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 hover:bg-white/10 transition-all duration-300">
                  <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                      <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-500 rounded-lg flex items-center justify-center">
                        <i :class="getLessonIcon(lesson.type) + ' text-white'"></i>
                      </div>
                      <div>
                        <h4 class="font-semibold text-white drop-shadow-md">{{ lesson.title }}</h4>
                        <p class="text-sm text-white/60">{{ lesson.duration_formatted }}</p>
                      </div>
                    </div>
                    <div class="flex items-center space-x-2">
                      <span v-if="lesson.is_preview" 
                            class="text-xs bg-blue-500/20 text-blue-200 border border-blue-500/30 rounded-full px-2 py-1">
                        Preview
                      </span>
                      <i class="fas fa-chevron-right text-white/50"></i>
                    </div>
                  </div>
                </div>
              </div>
              
              <div v-else class="text-center py-12">
                <div class="w-24 h-24 mx-auto mb-6 bg-white/10 rounded-full flex items-center justify-center">
                  <i class="fas fa-book text-4xl text-white/50"></i>
                </div>
                <p class="text-white/70 text-lg">Belum ada sesi training tersedia</p>
              </div>
            </div>

            <!-- Course Info Section -->
            <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6">
              <h4 class="text-xl font-bold text-white drop-shadow-lg mb-6">Informasi Training</h4>
              <div class="space-y-5">
                <div class="flex items-center justify-between py-2">
                  <span class="text-white/70 font-medium">Kategori</span>
                  <span class="text-white font-semibold">{{ course.category?.name || 'Tidak ditentukan' }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                  <span class="text-white/70 font-medium">Level Kesulitan</span>
                  <span v-if="course.difficulty_level || course.difficulty_text" :class="{
                    'px-3 py-1.5 text-xs rounded-full font-semibold': true,
                    'bg-blue-500/20 text-blue-200 border border-blue-500/30': course.difficulty_level === 'beginner',
                    'bg-yellow-500/20 text-yellow-200 border border-yellow-500/30': course.difficulty_level === 'intermediate',
                    'bg-red-500/20 text-red-200 border border-red-500/30': course.difficulty_level === 'advanced',
                    'bg-gray-500/20 text-gray-200 border border-gray-500/30': !course.difficulty_level
                  }">
                    {{ course.difficulty_text || getDifficultyText(course.difficulty_level) || 'Tidak ditentukan' }}
                  </span>
                  <span v-else class="text-white/50 text-sm">Tidak ditentukan</span>
                </div>
                <div class="flex items-center justify-between py-2">
                  <span class="text-white/70 font-medium">Durasi</span>
                  <span class="text-white font-semibold">{{ course.duration_formatted || 'Tidak ditentukan' }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                  <span class="text-white/70 font-medium">Tipe Training</span>
                  <span :class="{
                    'px-3 py-1.5 text-xs rounded-full font-semibold': true,
                    'bg-blue-500/20 text-blue-200 border border-blue-500/30': course.type === 'online',
                    'bg-green-500/20 text-green-200 border border-green-500/30': course.type === 'offline'
                  }">
                    <i :class="{
                      'fas fa-video mr-1': course.type === 'online',
                      'fas fa-users mr-1': course.type === 'offline'
                    }"></i>
                    {{ course.type === 'online' ? 'Online' : 'Offline' }}
                  </span>
                </div>
                <div class="flex items-center justify-between py-2">
                  <span class="text-white/70 font-medium">Sesi</span>
                                          <span class="text-white font-semibold">{{ course.sessions?.length || 0 }}</span>
                </div>
                <div class="flex items-center justify-between py-2">
                  <span class="text-white/70 font-medium">Peserta</span>
                  <span class="text-white font-semibold">{{ course.enrollments_count || 0 }}</span>
                </div>
                <!-- Target Divisi Section -->
                <div v-if="(course.target_divisions && course.target_divisions.length > 0) || course.target_type === 'all'" class="border-t border-white/20 pt-5">
                  <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-2">
                      <i class="fas fa-building text-blue-400"></i>
                      <span class="text-white/70 font-semibold">Target Divisi</span>
                    </div>
                    <span v-if="course.target_type" class="text-xs px-3 py-1.5 rounded-full bg-blue-500/20 text-blue-200 border border-blue-500/30 font-medium">
                      {{ course.target_type === 'single' ? '1 Divisi' : course.target_type === 'multiple' ? 'Multi Divisi' : 'Semua Divisi' }}
                    </span>
                  </div>
                  <div v-if="course.target_type === 'all'" class="px-4 py-3 bg-blue-500/10 border border-blue-500/20 rounded-lg">
                    <div class="flex items-center space-x-2">
                      <i class="fas fa-check-circle text-blue-400"></i>
                      <span class="text-white/90 text-sm font-medium">Course ini tersedia untuk semua divisi</span>
                    </div>
                  </div>
                  <div v-else-if="course.target_divisions && course.target_divisions.length > 0" class="space-y-3">
                    <div v-for="division in course.target_divisions" :key="division.id" 
                         class="flex items-center space-x-3 px-4 py-3 bg-blue-500/10 border border-blue-500/20 rounded-lg hover:bg-blue-500/15 transition-all duration-300 hover:scale-[1.02]">
                      <div class="w-2.5 h-2.5 bg-blue-400 rounded-full"></div>
                      <span class="text-white/90 text-sm font-medium">{{ division.nama_divisi }}</span>
                    </div>
                  </div>
                </div>

                <!-- Target Jabatan Section -->
                <div v-if="course.target_jabatans && course.target_jabatans.length > 0" class="border-t border-white/20 pt-5">
                  <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-2">
                      <i class="fas fa-user-tie text-green-400"></i>
                      <span class="text-white/70 font-semibold">Target Jabatan</span>
                    </div>
                    <span class="text-xs px-3 py-1.5 rounded-full bg-green-500/20 text-green-200 border border-green-500/30 font-medium">
                      {{ course.target_jabatans.length }} Jabatan
                    </span>
                  </div>
                  <div class="space-y-3">
                    <div v-for="jabatan in course.target_jabatans" :key="jabatan.id_jabatan" 
                         class="flex items-center space-x-3 px-4 py-3 bg-green-500/10 border border-green-500/20 rounded-lg hover:bg-green-500/15 transition-all duration-300 hover:scale-[1.02]">
                      <div class="w-2.5 h-2.5 bg-green-400 rounded-full"></div>
                      <div class="flex-1">
                        <span class="text-white/90 text-sm font-medium">{{ jabatan.nama_jabatan }}</span>
                        <div v-if="jabatan.divisi" class="text-xs text-white/50">{{ jabatan.divisi.nama_divisi }}</div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Target Level Section -->
                <div v-if="course.target_levels && course.target_levels.length > 0" class="border-t border-white/20 pt-5">
                  <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-2">
                      <i class="fas fa-layer-group text-purple-400"></i>
                      <span class="text-white/70 font-semibold">Target Level</span>
                    </div>
                    <span class="text-xs px-3 py-1.5 rounded-full bg-purple-500/20 text-purple-200 border border-purple-500/30 font-medium">
                      {{ course.target_levels.length }} Level
                    </span>
                  </div>
                  <div class="space-y-3">
                    <div v-for="level in course.target_levels" :key="level.id" 
                         class="flex items-center space-x-3 px-4 py-3 bg-purple-500/10 border border-purple-500/20 rounded-lg hover:bg-purple-500/15 transition-all duration-300 hover:scale-[1.02]">
                      <div class="w-2.5 h-2.5 bg-purple-400 rounded-full"></div>
                      <span class="text-white/90 text-sm font-medium">{{ level.nama_level }}</span>
                    </div>
                  </div>
                </div>

                <!-- Fallback for old data format -->
                <div v-if="!course.target_divisions && course.target_division_name && course.target_division_name !== 'Divisi tidak ditentukan'" class="border-t border-white/20 pt-5">
                  <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-2">
                      <i class="fas fa-building text-blue-400"></i>
                      <span class="text-white/70 font-semibold">Target Divisi</span>
                    </div>
                    <span class="text-xs px-3 py-1.5 rounded-full bg-blue-500/20 text-blue-200 border border-blue-500/30 font-medium">
                      1 Divisi
                    </span>
                  </div>
                  <div class="px-4 py-3 bg-blue-500/10 border border-blue-500/20 rounded-lg hover:bg-blue-500/15 transition-all duration-300 hover:scale-[1.02]">
                    <span class="text-white/90 text-sm font-medium">{{ course.target_division_name }}</span>
                  </div>
                </div>

                <div v-if="!course.target_jabatans && course.target_jabatan_names && course.target_jabatan_names !== 'Jabatan tidak ditentukan'" class="border-t border-white/20 pt-5">
                  <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-2">
                      <i class="fas fa-user-tie text-green-400"></i>
                      <span class="text-white/70 font-semibold">Target Jabatan</span>
                    </div>
                    <span class="text-xs px-3 py-1.5 rounded-full bg-green-500/20 text-green-200 border border-green-500/30 font-medium">
                      Multiple
                    </span>
                  </div>
                  <div class="px-4 py-3 bg-green-500/10 border border-green-500/20 rounded-lg hover:bg-green-500/15 transition-all duration-300 hover:scale-[1.02]">
                    <span class="text-white/90 text-sm font-medium">{{ course.target_jabatan_names }}</span>
                  </div>
                </div>

                <div v-if="!course.target_outlets && course.target_outlet_names && course.target_outlet_names !== 'Outlet tidak ditentukan'" class="border-t border-white/20 pt-5">
                  <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-2">
                      <i class="fas fa-store text-purple-400"></i>
                      <span class="text-white/70 font-semibold">Target Outlet</span>
                    </div>
                    <span class="text-xs px-3 py-1.5 rounded-full bg-purple-500/20 text-purple-200 border border-purple-500/30 font-medium">
                      Multiple
                    </span>
                  </div>
                  <div class="px-4 py-3 bg-purple-500/10 border border-purple-500/20 rounded-lg hover:bg-purple-500/15 transition-all duration-300 hover:scale-[1.02]">
                    <span class="text-white/90 text-sm font-medium">{{ course.target_outlet_names }}</span>
                  </div>
                </div>

                <!-- Competencies Section -->
                <div v-if="course.competencies && course.competencies.length > 0" class="border-t border-white/20 pt-5">
                  <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center space-x-2">
                      <i class="fas fa-star text-yellow-400"></i>
                      <span class="text-white/70 font-semibold">Kompetensi</span>
                    </div>
                    <span class="text-xs px-3 py-1.5 rounded-full bg-yellow-500/20 text-yellow-200 border border-yellow-500/30 font-medium">
                      {{ course.competencies.length }} Kompetensi
                    </span>
                  </div>
                  <div class="space-y-3">
                    <div v-for="competency in course.competencies" :key="competency.id" 
                         class="flex items-center space-x-3 px-4 py-3 bg-yellow-500/10 border border-yellow-500/20 rounded-lg hover:bg-yellow-500/15 transition-all duration-300 hover:scale-[1.02]">
                      <div class="w-2.5 h-2.5 bg-yellow-400 rounded-full"></div>
                      <span class="text-white/90 text-sm font-medium">{{ competency.name }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
       
    

    <!-- Add Quiz Modal -->
    <div v-if="showAddQuizModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto relative">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-2xl font-bold text-white">
            Tambah Quiz ke Course
          </h3>
          <button
            @click="showAddQuizModal = false"
            class="text-white/60 hover:text-white transition-colors"
          >
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>

        <div class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Pilih Quiz</label>
            <select
              v-model="selectedQuizId"
              class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent [&>option]:bg-slate-800 [&>option]:text-white"
            >
              <option value="">Pilih quiz...</option>
              <option v-for="quiz in availableQuizzes" :key="quiz.id" :value="quiz.id">
                {{ quiz.title }}
              </option>
            </select>
          </div>

          <div class="flex space-x-3 pt-6">
            <button
              @click="showAddQuizModal = false"
              class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-lg transition-colors duration-200 text-center"
            >
              Batal
            </button>
            <button
              @click="addQuizToCourse"
              :disabled="!selectedQuizId"
              class="flex-1 bg-gradient-to-r from-purple-500 to-pink-600 hover:from-purple-600 hover:to-pink-700 text-white px-4 py-3 rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i class="fas fa-plus mr-2"></i>
              Tambah Quiz
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Questionnaire Modal -->
    <div v-if="showAddQuestionnaireModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl shadow-2xl p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto relative">
        <div class="flex items-center justify-between mb-6">
          <h3 class="text-2xl font-bold text-white">
            Tambah Kuesioner ke Course
          </h3>
          <button
            @click="showAddQuestionnaireModal = false"
            class="text-white/60 hover:text-white transition-colors"
          >
            <i class="fas fa-times text-xl"></i>
          </button>
        </div>

        <div class="space-y-6">
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Pilih Kuesioner</label>
            <select
              v-model="selectedQuestionnaireId"
              class="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent [&>option]:bg-slate-800 [&>option]:text-white"
            >
              <option value="">Pilih kuesioner...</option>
              <option v-for="questionnaire in availableQuestionnaires" :key="questionnaire.id" :value="questionnaire.id">
                {{ questionnaire.title }}
              </option>
            </select>
          </div>

          <div class="flex space-x-3 pt-6">
            <button
              @click="showAddQuestionnaireModal = false"
              class="flex-1 bg-gray-600 hover:bg-gray-700 text-white px-4 py-3 rounded-lg transition-colors duration-200 text-center"
            >
              Batal
            </button>
            <button
              @click="addQuestionnaireToCourse"
              :disabled="!selectedQuestionnaireId"
              class="flex-1 bg-gradient-to-r from-blue-500 to-cyan-600 hover:from-blue-600 hover:to-cyan-700 text-white px-4 py-3 rounded-lg transition-all duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <i class="fas fa-plus mr-2"></i>
              Tambah Kuesioner
            </button>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Image Lightbox Modal -->
    <div v-if="showImageLightbox" 
         @click="closeImageLightbox"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-sm">
      <div class="relative max-w-4xl max-h-full p-4">
        <button @click="closeImageLightbox" 
                class="absolute top-4 right-4 z-10 w-10 h-10 bg-black/50 hover:bg-black/70 text-white rounded-full flex items-center justify-center transition-colors">
          <i class="fas fa-times text-xl"></i>
        </button>
        <img :src="`/storage/${currentMaterial?.file_path}`" 
             :alt="currentMaterial?.file_name || currentMaterial?.title"
             class="max-w-full max-h-full object-contain rounded-lg shadow-2xl">
        <div class="absolute bottom-4 left-4 right-4 text-center">
          <h3 class="text-white text-lg font-semibold bg-black/50 px-4 py-2 rounded-lg">
            {{ currentMaterial?.file_name || currentMaterial?.title }}
          </h3>
          <div v-if="currentMaterial?.file_size" class="text-white/80 text-sm mt-2">
            {{ formatFileSize(currentMaterial.file_size) }}
          </div>
        </div>
      </div>
    </div>
    
    <!-- Video Player Modal -->
    <div v-if="showVideoPlayer" 
         @click="closeVideoPlayer"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-sm">
      <div class="relative max-w-4xl max-h-full p-4">
        <button @click="closeVideoPlayer" 
                class="absolute top-4 right-4 z-10 w-10 h-10 bg-black/50 hover:bg-black/70 text-white rounded-full flex items-center justify-center transition-colors">
          <i class="fas fa-times text-xl"></i>
        </button>
        <div class="bg-black rounded-lg overflow-hidden">
          <video :src="`/storage/${currentMaterial?.file_path}`" 
                 controls 
                 autoplay
                 class="max-w-full max-h-[80vh]">
            Your browser does not support the video tag.
          </video>
        </div>
        <div class="absolute bottom-4 left-4 right-4 text-center">
          <h3 class="text-white text-lg font-semibold bg-black/50 px-4 py-2 rounded-lg">
            {{ currentMaterial?.file_name || currentMaterial?.title }}
          </h3>
          <div v-if="currentMaterial?.file_size" class="text-white/80 text-sm mt-2">
            {{ formatFileSize(currentMaterial.file_size) }}
          </div>
        </div>
      </div>
    </div>
    
    <!-- PDF Viewer Modal -->
    <div v-if="showPdfViewer" 
         @click="closePdfViewer"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-sm">
      <div class="relative w-full h-full p-4">
        <button @click="closePdfViewer" 
                class="absolute top-4 right-4 z-10 w-10 h-10 bg-black/50 hover:bg-black/70 text-white rounded-full flex items-center justify-center transition-colors">
          <i class="fas fa-times text-xl"></i>
        </button>
        <div class="w-full h-full bg-white rounded-lg overflow-hidden">
          <iframe :src="`/storage/${currentMaterial?.file_path}`" 
                  class="w-full h-full border-0"
                  title="PDF Viewer">
          </iframe>
        </div>
        <div class="absolute bottom-4 left-4 right-4 text-center">
          <h3 class="text-white text-lg font-semibold bg-black/50 px-4 py-2 rounded-lg">
            {{ currentMaterial?.file_name || currentMaterial?.title }}
          </h3>
          <div v-if="currentMaterial?.file_size" class="text-white/80 text-sm mt-2">
            {{ formatFileSize(currentMaterial.file_size) }}
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  course: Object,
  canScheduleTraining: {
    type: Boolean,
    default: false
  },
  availableQuizzes: {
    type: Array,
    default: () => []
  },
  availableQuestionnaires: {
    type: Array,
    default: () => []
  }
})

// Reactive variables
const showAddQuizModal = ref(false)
const showAddQuestionnaireModal = ref(false)
const selectedQuizId = ref('')
const selectedQuestionnaireId = ref('')

// Material viewer modals
const showImageLightbox = ref(false)
const showVideoPlayer = ref(false)
const showPdfViewer = ref(false)
const currentMaterial = ref(null)

const getLessonIcon = (type) => {
  const icons = {
    'video': 'fas fa-play',
    'document': 'fas fa-file-alt',
    'quiz': 'fas fa-question-circle',
    'assignment': 'fas fa-tasks',
    'discussion': 'fas fa-comments'
  }
  return icons[type] || 'fas fa-play'
}

const getQuizStatusText = (status) => {
  const statusMap = {
    'published': 'Published',
    'draft': 'Draft',
    'archived': 'Archived'
  }
  return statusMap[status] || status
}

const getQuestionnaireStatusText = (status) => {
  const statusMap = {
    'published': 'Published',
    'draft': 'Draft',
    'archived': 'Archived'
  }
  return statusMap[status] || status
}

const addQuizToCourse = () => {
  if (!selectedQuizId.value) return

  router.post(route('lms.courses.quizzes.attach', props.course.id), {
    quiz_id: selectedQuizId.value
  }, {
    onSuccess: () => {
      showAddQuizModal.value = false
      selectedQuizId.value = ''
    }
  })
}

const addQuestionnaireToCourse = () => {
  if (!selectedQuestionnaireId.value) return

  router.post(route('lms.courses.questionnaires.attach', props.course.id), {
    questionnaire_id: selectedQuestionnaireId.value
  }, {
    onSuccess: () => {
      showAddQuestionnaireModal.value = false
      selectedQuestionnaireId.value = ''
    }
  })
}

// Material handling methods
const getMaterialTypeText = (fileType) => {
  const typeMap = {
    'image': 'Gambar',
    'video': 'Video',
    'pdf': 'PDF',
    'document': 'Dokumen',
    'audio': 'Audio',
    'link': 'Link'
  }
  return typeMap[fileType] || 'File'
}

// Difficulty level helper
const getDifficultyText = (difficultyLevel) => {
  const difficultyMap = {
    'beginner': 'Pemula',
    'intermediate': 'Menengah',
    'advanced': 'Lanjutan'
  }
  return difficultyMap[difficultyLevel] || 'Tidak ditentukan'
}

const downloadFile = (file) => {
  // Create a temporary link element
  const link = document.createElement('a')
  link.href = `/storage/${file.file_path}`
  link.download = file.file_name || 'download'
  link.target = '_blank'
  
  // Append to body, click, and remove
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
}

const formatFileSize = (bytes) => {
  if (!bytes) return '0 B'
  
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const getMaterialThumbnail = (material) => {
  if (material.file_type === 'image') {
    return material.file_url
  } else if (material.file_type === 'video') {
    // For video, you could generate a thumbnail or use a default
    return '/images/video-thumbnail.jpg' // Default video thumbnail
  }
  return null
}

const openImageLightbox = (file) => {
  currentMaterial.value = file
  showImageLightbox.value = true
}

const closeImageLightbox = () => {
  showImageLightbox.value = false
  currentMaterial.value = null
}

const openVideoPlayer = (file) => {
  currentMaterial.value = file
  showVideoPlayer.value = true
}

const closeVideoPlayer = () => {
  showVideoPlayer.value = false
  currentMaterial.value = null
}

const openPdfViewer = (file) => {
  currentMaterial.value = file
  showPdfViewer.value = true
}

const closePdfViewer = () => {
  showPdfViewer.value = false
  currentMaterial.value = null
}

const getInitials = (name) => {
  if (!name) return '';
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0,2);
}


const handleAvatarError = (event) => {
  // Hide the image and show fallback icon
  event.target.style.display = 'none'
  const parent = event.target.parentElement
  parent.innerHTML = '<i class="fas fa-user text-2xl text-white"></i>'
  parent.className = 'w-20 h-20 mx-auto bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center'
}

const handleHeaderAvatarError = (event) => {
  // Hide the image and show fallback icon for header avatar
  event.target.style.display = 'none'
  const parent = event.target.parentElement
  parent.innerHTML = '<i class="fas fa-user text-white"></i>'
  parent.className = 'w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center'
}
</script>

<style scoped>
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

.animate-blob {
  animation: blob 7s infinite;
}

.animation-delay-2000 {
  animation-delay: 2s;
}

.animation-delay-4000 {
  animation-delay: 4s;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.3);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.5);
}

/* Smooth animations */
* {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Enhanced hover effects for target sections */
.hover\:bg-blue-500\/15:hover {
  background-color: rgba(59, 130, 246, 0.15);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.hover\:bg-green-500\/15:hover {
  background-color: rgba(34, 197, 94, 0.15);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(34, 197, 94, 0.2);
}

.hover\:bg-purple-500\/15:hover {
  background-color: rgba(147, 51, 234, 0.15);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(147, 51, 234, 0.2);
}

/* Target section styling */
.target-section {
  border-top: 1px solid rgba(255, 255, 255, 0.2);
  padding-top: 1rem;
}

.target-section:first-of-type {
  border-top: none;
  padding-top: 0;
}

.target-item {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.target-item:hover {
  transform: translateY(-1px);
}

/* Badge styling */
.target-badge {
  font-size: 0.75rem;
  padding: 0.25rem 0.5rem;
  border-radius: 9999px;
  font-weight: 500;
  border: 1px solid;
}

/* Glassmorphism effect */
.backdrop-blur-xl {
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
}

/* 3D hover effects */
.transform:hover\:scale-105:hover {
  transform: scale(1.05) translateZ(10px);
}

/* Avatar styling */
.avatar-container {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.avatar-container:hover {
  transform: scale(1.05);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

.avatar-image {
  transition: all 0.3s ease;
}

.avatar-image:hover {
  transform: scale(1.1);
}
</style> 