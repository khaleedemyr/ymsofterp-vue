<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
    
    <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-2xl font-bold text-white">{{ training.course?.title || 'Training Detail' }}</h3>
        <button @click="$emit('close')" class="text-white/70 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
      
      <!-- Training Info Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Left Column -->
        <div class="space-y-4">
          <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-lg font-semibold text-white">Informasi Training</h4>
              <span :class="getStatusColor(training.status)" 
                    class="px-3 py-1 rounded-full text-sm font-medium flex items-center gap-2">
                <i :class="getStatusIcon(training.status)" class="text-xs"></i>
                {{ getStatusText(training.status) }}
              </span>
            </div>
            <div class="space-y-3">
              <div class="flex items-center space-x-3">
                <i class="fas fa-calendar text-blue-400 w-5"></i>
                <span class="text-white">{{ formatDate(training.scheduled_date) }}</span>
              </div>
              <div class="flex items-center space-x-3">
                <i class="fas fa-clock text-green-400 w-5"></i>
                <span class="text-white">{{ training.start_time }} - {{ training.end_time }}</span>
              </div>
              <div class="flex items-center space-x-3">
                <i class="fas fa-map-marker-alt text-red-400 w-5"></i>
                <span class="text-white">{{ training.outlet?.nama_outlet || 'Venue tidak ditentukan' }}</span>
              </div>
              <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-3">
                  <!-- Avatar untuk internal trainer, icon untuk external -->
                  <div v-if="!training.external_trainer_name && training.course?.instructor?.avatar" 
                       class="w-8 h-8 rounded-full overflow-hidden">
                    <img :src="'/storage/' + training.course.instructor.avatar" 
                         :alt="training.trainer_name"
                         class="w-full h-full object-cover" />
                  </div>
                  <i v-else class="fas fa-user text-purple-400 w-5"></i>
                </div>
                <div class="flex flex-col">
                  <span class="text-white font-medium">{{ training.trainer_name }}</span>
                  <span class="text-white/70 text-sm">{{ training.external_trainer_name ? 'External Trainer' : 'Internal Trainer' }}</span>
                </div>
              </div>
            </div>
          </div>
          
                     <!-- Status & Actions -->
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
             <div class="flex items-center justify-between mb-3">
               <h4 class="text-lg font-semibold text-white">Status Training</h4>
               <span :class="getStatusColor(training.status)" 
                     class="px-3 py-1 rounded-full text-sm font-medium flex items-center gap-2">
                 <i :class="getStatusIcon(training.status)" class="text-xs"></i>
                 {{ getStatusText(training.status) }}
               </span>
             </div>
             
                           <!-- Training Status Stats -->
              <div class="grid grid-cols-3 gap-4 mb-4">
                <div class="text-center">
                  <div class="text-2xl font-bold text-blue-300">{{ training.invitations?.length || 0 }}</div>
                  <div class="text-xs text-white/70">Terdaftar</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-green-300">{{ training.invitations?.filter(inv => inv.status === 'attended').length || 0 }}</div>
                  <div class="text-xs text-white/70">Hadir</div>
                </div>
                <div class="text-center">
                  <div class="text-2xl font-bold text-red-300">{{ training.invitations?.filter(inv => inv.status === 'absent').length || 0 }}</div>
                  <div class="text-xs text-white/70">Tidak Hadir</div>
                </div>
              </div>
             
                           <!-- Progress Bar -->
              <div class="space-y-2">
                <div class="flex justify-between text-xs text-white/70">
                  <span>Kehadiran</span>
                  <span>{{ training.invitations?.length > 0 ? Math.round((training.invitations.filter(inv => inv.status === 'attended').length / training.invitations.length) * 100) : 0 }}%</span>
                </div>
                <div class="w-full bg-white/10 rounded-full h-2">
                  <div class="bg-gradient-to-r from-green-400 to-green-500 h-2 rounded-full transition-all duration-300"
                       :style="{ width: training.invitations?.length > 0 ? (training.invitations.filter(inv => inv.status === 'attended').length / training.invitations.length) * 100 + '%' : '0%' }">
                  </div>
                </div>
              </div>
           </div>
        </div>
        
        <!-- Right Column -->
        <div class="space-y-4">
                     <!-- QR Code Section -->
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
             <h4 class="text-lg font-semibold text-white mb-3">QR Code Training</h4>
                           <div class="text-center">
                <div class="bg-white p-4 rounded-lg inline-block cursor-pointer hover:scale-105 transition-transform duration-200"
                     @click="showQRCodeModal = true">
                  <div class="w-32 h-32">
                    <img v-if="qrCodeDataUrl && !qrCodeError" 
                         :src="qrCodeDataUrl" 
                         alt="QR Code" 
                         class="w-full h-full"
                         @error="handleQRCodeError"
                         @load="handleQRCodeLoad" />
                    <div v-else class="w-full h-full bg-gray-200 rounded flex items-center justify-center">
                      <div class="text-center">
                        <i class="fas fa-qrcode text-gray-400 text-4xl mb-2"></i>
                        <p class="text-gray-500 text-xs">QR Code</p>
                        <p class="text-gray-400 text-xs">Training #{{ training.id }}</p>
                      </div>
                    </div>
                  </div>
                </div>
                <p class="text-white/70 text-sm mt-2">QR Code untuk training ini</p>
                <p class="text-white/50 text-xs mt-1">Klik untuk memperbesar</p>
              </div>
           </div>
          
                     <!-- Quick Actions -->
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
             <h4 class="text-lg font-semibold text-white mb-3">Aksi Cepat</h4>
             <div class="grid grid-cols-2 gap-3">
               <button v-if="canInvite" @click="$emit('invite')"
                       class="px-4 py-2 bg-blue-500/20 border border-blue-500/30 rounded-lg text-blue-200 hover:bg-blue-500/30 transition-all">
                 <i class="fas fa-user-plus mr-2"></i>
                 Undang Peserta
               </button>
               <button v-if="canInvite" @click="$emit('invite-trainer')"
                       class="px-4 py-2 bg-purple-500/20 border border-purple-500/30 rounded-lg text-purple-200 hover:bg-purple-500/30 transition-all">
                 <i class="fas fa-chalkboard-teacher mr-2"></i>
                 Undang Trainer
               </button>
             </div>
           </div>
           
           <!-- Status Management -->
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
             <h4 class="text-lg font-semibold text-white mb-3">Kelola Status Training</h4>
             <div class="grid grid-cols-2 gap-3">
               <button @click="updateTrainingStatus('ongoing')"
                       class="px-4 py-2 bg-orange-500/20 border border-orange-500/30 rounded-lg text-orange-200 hover:bg-orange-500/30 transition-all">
                 <i class="fas fa-play mr-2"></i>
                 Set Ongoing
               </button>
               <button @click="updateTrainingStatus('completed')"
                       class="px-4 py-2 bg-green-500/20 border border-green-500/30 rounded-lg text-green-200 hover:bg-green-500/30 transition-all">
                 <i class="fas fa-check mr-2"></i>
                 Set Completed
               </button>
               <button @click="updateTrainingStatus('cancelled')"
                       class="px-4 py-2 bg-red-500/20 border border-red-500/30 rounded-lg text-red-200 hover:bg-red-500/30 transition-all">
                 <i class="fas fa-times mr-2"></i>
                 Set Cancelled
               </button>
               <button v-if="training.status === 'completed'" @click="showTrainerRatings"
                       class="px-4 py-2 bg-indigo-500/20 border border-indigo-500/30 rounded-lg text-indigo-200 hover:bg-indigo-500/30 transition-all">
                 <i class="fas fa-chalkboard-teacher mr-2"></i>
                 List Rating Trainer
               </button>
             </div>
           </div>
        </div>
      </div>
      
      <!-- Trainers List -->
      <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
        <h4 class="text-lg font-semibold text-white mb-3">Daftar Trainer</h4>
        <div class="overflow-x-auto">
          <table class="w-full text-white">
            <thead>
              <tr class="border-b border-white/20">
                <th class="text-left py-2 px-2 min-w-[120px]">Nama</th>
                <th class="text-left py-2 px-2 min-w-[150px]">Jabatan</th>
                <th class="text-left py-2 px-2 min-w-[180px]">Divisi</th>
                <th class="text-left py-2 px-2 min-w-[100px]">Status</th>
                <th class="text-left py-2 px-2 min-w-[100px]">Jam Mengajar</th>
                <th class="text-left py-2 px-2 min-w-[120px]">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="scheduleTrainer in training.scheduleTrainers" :key="scheduleTrainer.id" 
                  class="border-b border-white/10">
                <td class="py-2 px-2">
                  <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-full overflow-hidden bg-white/10 flex items-center justify-center">
                      <img 
                        v-if="scheduleTrainer.trainer_type === 'internal' && scheduleTrainer.trainer?.avatar" 
                        :src="'/storage/' + scheduleTrainer.trainer.avatar" 
                        :alt="scheduleTrainer.trainer_name"
                        class="w-full h-full object-cover"
                      />
                      <i v-else class="fas fa-user text-white/50 text-xs"></i>
                    </div>
                    <div class="flex flex-col">
                      <span>{{ scheduleTrainer.trainer_name || scheduleTrainer.trainer?.nama_lengkap || 'Trainer tidak ditemukan' }}</span>
                      <div class="flex items-center space-x-2">
                        <span :class="getTrainerTypeColor(scheduleTrainer.trainer_type)" 
                              class="px-2 py-1 text-xs rounded-full">
                          {{ scheduleTrainer.trainer_type === 'external' ? 'External' : 'Internal' }}
                        </span>
                        <span v-if="scheduleTrainer.is_primary_trainer" 
                              class="px-2 py-1 bg-yellow-500/20 text-yellow-200 text-xs rounded-full">
                          Primary
                        </span>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="py-2 px-2">{{ scheduleTrainer.trainer?.jabatan?.nama_jabatan || '-' }}</td>
                <td class="py-2 px-2">{{ scheduleTrainer.trainer?.divisi?.nama_divisi || '-' }}</td>
                <td class="py-2 px-2">
                  <span :class="getTrainerStatusColor(scheduleTrainer.status)" 
                        class="px-2 py-1 rounded-full text-xs flex items-center gap-1">
                    <i :class="getTrainerStatusIcon(scheduleTrainer.status)" class="text-xs"></i>
                    {{ getTrainerStatusText(scheduleTrainer.status) }}
                  </span>
                </td>
                <td class="py-2 px-2">{{ scheduleTrainer.hours_taught || '0' }} jam</td>
                <td class="py-2 px-2">
                  <div class="flex items-center space-x-2">
                    <button v-if="canInvite && !scheduleTrainer.is_primary_trainer" 
                            @click="setPrimaryTrainer(scheduleTrainer.id)"
                            class="px-2 py-1 bg-yellow-500/20 border border-yellow-500/30 rounded text-xs text-yellow-200 hover:bg-yellow-500/30 transition-colors">
                      Set Primary
                    </button>
                    <button v-if="canInvite" 
                            @click="removeTrainer(scheduleTrainer.id)"
                            class="px-2 py-1 bg-red-500/20 border border-red-500/30 rounded text-xs text-red-200 hover:bg-red-500/30 transition-colors">
                      Hapus
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="!training.scheduleTrainers || training.scheduleTrainers.length === 0">
                <td colspan="6" class="py-8 text-center text-white/50">
                  <i class="fas fa-chalkboard-teacher text-4xl mb-2"></i>
                  <p>Belum ada trainer yang diundang</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Participants List -->
      <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
        <h4 class="text-lg font-semibold text-white mb-3">Daftar Peserta</h4>
        <div class="overflow-x-auto">
          <table class="w-full text-white">
            <thead>
              <tr class="border-b border-white/20">
                <th class="text-left py-2 px-2 min-w-[120px]">Nama</th>
                <th class="text-left py-2 px-2 min-w-[150px]">Jabatan</th>
                <th class="text-left py-2 px-2 min-w-[180px]">Divisi</th>
                <th class="text-left py-2 px-2 min-w-[80px]">Status</th>
                <th class="text-left py-2 px-2 min-w-[100px]">Check-in</th>
                <th class="text-left py-2 px-2 min-w-[120px]">Aksi</th>
              </tr>
            </thead>
                          <tbody>
                <tr v-for="participant in training.invitations" :key="participant.id" 
                    class="border-b border-white/10">
                  <td class="py-2 px-2">{{ participant.user?.nama_lengkap || 'User tidak ditemukan' }}</td>
                  <td class="py-2 px-2">{{ participant.user?.jabatan?.nama_jabatan || '-' }}</td>
                  <td class="py-2 px-2">{{ participant.user?.divisi?.nama_divisi || '-' }}</td>
                  <td class="py-2 px-2">
                    <span :class="getParticipantStatusColor(participant.status)" 
                          class="px-2 py-1 rounded-full text-xs flex items-center gap-1">
                      <i :class="getParticipantStatusIcon(participant.status)" class="text-xs"></i>
                      {{ getParticipantStatusText(participant.status) }}
                    </span>
                  </td>
                  <td class="py-2 px-2">{{ participant.check_in_time_formatted || '-' }}</td>
                  <td class="py-2 px-2">
                    <div class="flex items-center space-x-2">
                      <button v-if="participant.status === 'invited' && canInvite" 
                              @click="markAttended(participant.id)"
                              class="px-2 py-1 bg-green-500/20 border border-green-500/30 rounded text-xs text-green-200 hover:bg-green-500/30 transition-colors">
                        Hadir
                      </button>
                      <button v-if="canInvite" 
                              @click="removeParticipant(participant.id)"
                              class="px-2 py-1 bg-red-500/20 border border-red-500/30 rounded text-xs text-red-200 hover:bg-red-500/30 transition-colors">
                        Hapus
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
          </table>
        </div>
             </div>
     </div>
     
     <!-- Review List Modal -->
     <div v-if="showReviewListModal" class="fixed inset-0 z-[60] flex items-center justify-center">
       <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="closeReviewListModal"></div>
       
       <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6 max-w-6xl w-full mx-4 max-h-[90vh] overflow-y-auto relative">
         <!-- Close Button -->
         <button @click="closeReviewListModal" 
                 class="absolute top-4 right-4 text-white/70 hover:text-white transition-colors">
           <i class="fas fa-times text-xl"></i>
         </button>
         
         <!-- Modal Header -->
         <div class="mb-6">
           <h4 class="text-2xl font-bold text-white mb-2">Review Training</h4>
           <div v-if="reviewData.training" class="text-white/70">
             <p class="text-lg font-semibold">{{ reviewData.training.course_title }}</p>
             <p class="text-sm">
               {{ new Date(reviewData.training.scheduled_date).toLocaleDateString('id-ID') }} • 
               {{ reviewData.training.start_time }} - {{ reviewData.training.end_time }} • 
               {{ reviewData.training.outlet_name }}
             </p>
           </div>
         </div>

         <!-- Statistics -->
         <div v-if="reviewData.statistics" class="grid grid-cols-4 gap-4 mb-6">
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 text-center">
             <div class="text-2xl font-bold text-blue-300">{{ reviewData.statistics.total_reviews }}</div>
             <div class="text-xs text-white/70">Total Review</div>
           </div>
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 text-center">
             <div class="text-2xl font-bold text-yellow-300">{{ reviewData.statistics.average_training_rating }}/5</div>
             <div class="text-xs text-white/70">Rating Training</div>
           </div>
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 text-center">
             <div class="text-2xl font-bold text-orange-300">{{ reviewData.statistics.average_trainer_rating }}/5</div>
             <div class="text-xs text-white/70">Rating Trainer</div>
           </div>
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 text-center">
             <div class="text-2xl font-bold text-red-300">{{ reviewData.statistics.average_satisfaction }}/5</div>
             <div class="text-xs text-white/70">Kepuasan Rata-rata</div>
           </div>
         </div>

         <!-- Loading State -->
         <div v-if="loadingReviews" class="text-center py-8">
           <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-white"></div>
           <p class="text-sm mt-2 text-white/70">Memuat review...</p>
         </div>

         <!-- Empty State -->
         <div v-else-if="!reviewData.reviews || reviewData.reviews.length === 0" class="text-center py-8">
           <div class="mb-4 text-white/50">
             <i class="fas fa-star text-6xl"></i>
           </div>
           <h4 class="text-lg font-semibold text-white/70 mb-2">Belum Ada Review</h4>
           <p class="text-white/50">Belum ada peserta yang memberikan review untuk training ini.</p>
         </div>

         <!-- Reviews List -->
         <div v-else class="space-y-4">
           <div v-for="review in reviewData.reviews" :key="review.review_id" 
                class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
             <div class="flex items-start justify-between mb-3">
               <div class="flex items-center space-x-3">
                 <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                   {{ review.user_name.charAt(0).toUpperCase() }}
                 </div>
                 <div>
                   <h5 class="text-white font-semibold">{{ review.user_name }}</h5>
                   <p class="text-white/70 text-sm">{{ review.nama_jabatan }} • {{ review.nama_divisi }}</p>
                 </div>
               </div>
               <div class="text-right">
                 <div class="text-white/70 text-sm">{{ new Date(review.review_date).toLocaleDateString('id-ID') }}</div>
                 <div class="text-white/50 text-xs">{{ new Date(review.review_date).toLocaleTimeString('id-ID') }}</div>
               </div>
             </div>
             
             <div class="grid grid-cols-3 gap-4 mb-3">
               <div>
                 <div class="text-white/70 text-sm mb-1">Rating Training</div>
                 <div class="flex items-center space-x-1">
                   <div class="flex space-x-1">
                     <i v-for="star in 5" :key="star" 
                        class="fas fa-star text-sm"
                        :class="star <= review.training_rating ? 'text-yellow-400' : 'text-gray-300'">
                     </i>
                   </div>
                   <span class="text-white text-sm font-medium ml-2">{{ review.training_rating }}/5</span>
                 </div>
               </div>
               <div>
                 <div class="text-white/70 text-sm mb-1">Rating Trainer</div>
                 <div class="flex items-center space-x-1">
                   <div class="flex space-x-1">
                     <i v-for="star in 5" :key="star" 
                        class="fas fa-star text-sm"
                        :class="star <= review.trainer_rating ? 'text-yellow-400' : 'text-gray-300'">
                     </i>
                   </div>
                   <span class="text-white text-sm font-medium ml-2">{{ review.trainer_rating }}/5</span>
                 </div>
               </div>
               <div>
                 <div class="text-white/70 text-sm mb-1">Kepuasan Keseluruhan</div>
                 <div class="flex items-center space-x-1">
                   <div class="flex space-x-1">
                     <i v-for="heart in 5" :key="heart" 
                        class="fas fa-heart text-sm"
                        :class="heart <= review.overall_satisfaction ? 'text-red-400' : 'text-gray-300'">
                     </i>
                   </div>
                   <span class="text-white text-sm font-medium ml-2">{{ review.overall_satisfaction }}/5</span>
                 </div>
               </div>
             </div>
             
             <div v-if="review.training_feedback" class="mt-3">
               <div class="text-white/70 text-sm mb-1">Feedback Training</div>
               <div class="text-white bg-white/5 rounded-lg p-3 text-sm">
                 {{ review.training_feedback }}
               </div>
             </div>
             
             <div v-if="review.trainer_feedback" class="mt-3">
               <div class="text-white/70 text-sm mb-1">Feedback Trainer</div>
               <div class="text-white bg-white/5 rounded-lg p-3 text-sm">
                 {{ review.trainer_feedback }}
               </div>
             </div>
             
             <div v-if="review.improvement_suggestions" class="mt-3">
               <div class="text-white/70 text-sm mb-1">Saran Perbaikan</div>
               <div class="text-white bg-white/5 rounded-lg p-3 text-sm">
                 {{ review.improvement_suggestions }}
               </div>
             </div>
             
             <div class="mt-3 pt-3 border-t border-white/10">
               <div class="text-white/50 text-xs">
                 <i class="fas fa-chalkboard-teacher mr-1"></i>
                 Trainer: {{ review.trainer_name_final }}
               </div>
             </div>
           </div>
         </div>
       </div>
     </div>

     <!-- Trainer Ratings Modal -->
     <div v-if="showTrainerRatingsModal" class="fixed inset-0 z-[60] flex items-center justify-center">
       <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="closeTrainerRatingsModal"></div>
       
       <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-6 max-w-6xl w-full mx-4 max-h-[90vh] overflow-y-auto relative">
         <!-- Close Button -->
         <button @click="closeTrainerRatingsModal" 
                 class="absolute top-4 right-4 text-white/70 hover:text-white transition-colors">
           <i class="fas fa-times text-xl"></i>
         </button>
         
         <!-- Modal Header -->
         <div class="mb-6">
           <h4 class="text-2xl font-bold text-white mb-2">Rating Trainer</h4>
           <div v-if="trainerRatingsData.training" class="text-white/70">
             <p class="text-lg font-semibold">{{ trainerRatingsData.training.course_title }}</p>
             <p class="text-sm">
               {{ new Date(trainerRatingsData.training.scheduled_date).toLocaleDateString('id-ID') }} • 
               {{ trainerRatingsData.training.start_time }} - {{ trainerRatingsData.training.end_time }} • 
               {{ trainerRatingsData.training.outlet_name }}
             </p>
           </div>
         </div>

         <!-- Statistics -->
         <div v-if="trainerRatingsData.statistics" class="grid grid-cols-2 gap-4 mb-6">
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 text-center">
             <div class="text-2xl font-bold text-blue-300">{{ trainerRatingsData.statistics.total_ratings }}</div>
             <div class="text-xs text-white/70">Total Rating</div>
           </div>
           <div class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4 text-center">
             <div class="text-2xl font-bold text-yellow-300">{{ trainerRatingsData.statistics.average_trainer_rating }}/5</div>
             <div class="text-xs text-white/70">Rating Rata-rata</div>
           </div>
         </div>

         <!-- Rating Distribution -->
         <div v-if="trainerRatingsData.statistics && trainerRatingsData.statistics.rating_distribution" class="mb-6">
           <h5 class="text-lg font-semibold text-white mb-3">Distribusi Rating</h5>
           <div class="space-y-2">
             <div v-for="dist in trainerRatingsData.statistics.rating_distribution" :key="dist.rating" 
                  class="flex items-center space-x-3">
               <div class="w-8 text-center text-white/70">{{ dist.rating }}</div>
               <div class="flex-1 bg-white/10 rounded-full h-2">
                 <div class="bg-yellow-400 h-2 rounded-full transition-all duration-500" 
                      :style="{ width: dist.percentage + '%' }"></div>
               </div>
               <div class="w-16 text-right text-white/70 text-sm">{{ dist.count }} ({{ dist.percentage }}%)</div>
             </div>
           </div>
         </div>

         <!-- Loading State -->
         <div v-if="loadingTrainerRatings" class="text-center py-8">
           <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-white"></div>
           <p class="text-sm mt-2 text-white/70">Memuat rating trainer...</p>
         </div>

         <!-- Empty State -->
         <div v-else-if="!trainerRatingsData.trainer_ratings || trainerRatingsData.trainer_ratings.length === 0" 
              class="text-center py-8">
           <i class="fas fa-chalkboard-teacher text-6xl text-white/30 mb-4"></i>
           <p class="text-white/70">Belum ada rating trainer untuk training ini</p>
         </div>

         <!-- Trainer Ratings List -->
         <div v-else class="space-y-4">
           <div v-for="rating in trainerRatingsData.trainer_ratings" :key="rating.review_id" 
                class="backdrop-blur-sm bg-white/5 border border-white/10 rounded-xl p-4">
             
             <!-- User Info -->
             <div class="flex items-center justify-between mb-3">
               <div class="flex items-center space-x-3">
                 <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-full flex items-center justify-center">
                   <i class="fas fa-user text-white text-sm"></i>
                 </div>
                 <div>
                   <div class="text-white font-medium">{{ rating.user_name }}</div>
                   <div class="text-white/60 text-sm">{{ rating.nama_jabatan }} • {{ rating.nama_divisi }}</div>
                 </div>
               </div>
               <div class="text-white/50 text-xs">
                 {{ new Date(rating.review_date).toLocaleDateString('id-ID', { 
                   day: 'numeric', 
                   month: 'short', 
                   year: 'numeric',
                   hour: '2-digit',
                   minute: '2-digit'
                 }) }}
               </div>
             </div>

             <!-- Trainer Rating -->
             <div class="mb-3">
               <div class="text-white/70 text-sm mb-1">Rating Trainer</div>
               <div class="flex items-center space-x-1">
                 <div class="flex space-x-1">
                   <i v-for="star in 5" :key="star" 
                      class="fas fa-star text-sm"
                      :class="star <= rating.trainer_rating ? 'text-yellow-400' : 'text-gray-300'">
                   </i>
                 </div>
                 <span class="text-white text-sm font-medium ml-2">{{ rating.trainer_rating }}/5</span>
               </div>
             </div>

             <!-- Trainer Feedback -->
             <div v-if="rating.trainer_feedback" class="mt-3">
               <div class="text-white/70 text-sm mb-1">Feedback Trainer</div>
               <div class="text-white bg-white/5 rounded-lg p-3 text-sm">
                 {{ rating.trainer_feedback }}
               </div>
             </div>

             <!-- Trainer Info -->
             <div class="mt-3 pt-3 border-t border-white/10">
               <div class="text-white/50 text-xs">
                 <i class="fas fa-chalkboard-teacher mr-1"></i>
                 Trainer: {{ rating.trainer_name_final }}
               </div>
             </div>
           </div>
         </div>
       </div>
     </div>

     <!-- QR Code Modal -->
     <div v-if="showQRCodeModal" class="fixed inset-0 z-[60] flex items-center justify-center">
       <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showQRCodeModal = false"></div>
       
       <div class="backdrop-blur-xl bg-white/10 border border-white/20 rounded-2xl p-8 max-w-md w-full mx-4 relative">
         <!-- Close Button -->
         <button @click="showQRCodeModal = false" 
                 class="absolute top-4 right-4 text-white/70 hover:text-white transition-colors">
           <i class="fas fa-times text-xl"></i>
         </button>
         
         <!-- QR Code Title -->
         <h4 class="text-xl font-bold text-white text-center mb-6">QR Code Training</h4>
         
         <!-- Large QR Code -->
         <div class="text-center">
           <div class="bg-white p-6 rounded-lg inline-block">
             <div class="w-64 h-64">
               <img v-if="qrCodeDataUrl && !qrCodeError" 
                    :src="qrCodeDataUrl" 
                    alt="QR Code" 
                    class="w-full h-full"
                    @error="handleQRCodeError"
                    @load="handleQRCodeLoad" />
               <div v-else class="w-full h-full bg-gray-200 rounded flex items-center justify-center">
                 <div class="text-center">
                   <i class="fas fa-qrcode text-gray-400 text-6xl mb-4"></i>
                   <p class="text-gray-500 text-sm">QR Code</p>
                   <p class="text-gray-400 text-xs">Training #{{ training.id }}</p>
                 </div>
               </div>
             </div>
           </div>
           
                       <!-- Training Info -->
            <div class="mt-6 text-white/80 text-center">
              <p class="font-medium">{{ training.course?.title || 'Training' }}</p>
              <p class="text-sm">{{ formatDate(training.scheduled_date) }}</p>
              <p class="text-xs text-white/60">{{ training.start_time }} - {{ training.end_time }}</p>
              <p class="text-xs text-white/40 mt-2">YMSoft ERP</p>
            </div>
           
           
         </div>
       </div>
     </div>
   </div>
 </template>

<script setup>
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import QRCode from 'qrcode'
import Swal from 'sweetalert2'
const props = defineProps({
  training: Object,
  canInvite: {
    type: Boolean,
    default: true
  },
  canEdit: {
    type: Boolean,
    default: true
  },
  availableParticipants: {
    type: Array,
    default: () => []
  },
  divisions: {
    type: Array,
    default: () => []
  },
  jabatans: {
    type: Array,
    default: () => []
  },
  levels: {
    type: Array,
    default: () => []
  },
  certificateTemplates: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'edit', 'invite', 'invite-trainer', 'qr-code', 'refresh'])

const qrCodeError = ref(false)
const qrCodeDataUrl = ref('')
const showQRCodeModal = ref(false)

// Review list modal state
const showReviewListModal = ref(false)
const reviewData = ref({
  training: null,
  reviews: [],
  statistics: null
})
const loadingReviews = ref(false)

// Trainer ratings modal state
const showTrainerRatingsModal = ref(false)
const trainerRatingsData = ref({
  training: null,
  trainer_ratings: [],
  statistics: null
})
const loadingTrainerRatings = ref(false)

const getStatusColor = (status) => {
  const colors = {
    'draft': 'bg-gray-500/20 text-gray-200 border-gray-500/30',
    'published': 'bg-blue-500/20 text-blue-200 border-blue-500/30',
    'ongoing': 'bg-orange-500/20 text-orange-200 border-orange-500/30',
    'completed': 'bg-green-500/20 text-green-200 border-green-500/30',
    'cancelled': 'bg-red-500/20 text-red-200 border-red-500/30'
  }
  return colors[status] || colors['draft']
}

const getStatusIcon = (status) => {
  const icons = {
    'draft': 'fas fa-edit',
    'published': 'fas fa-bullhorn',
    'ongoing': 'fas fa-play-circle',
    'completed': 'fas fa-check-circle',
    'cancelled': 'fas fa-times-circle'
  }
  return icons[status] || icons['draft']
}

const getStatusText = (status) => {
  const texts = {
    'draft': 'Draft',
    'published': 'Dipublikasi',
    'ongoing': 'Sedang Berlangsung',
    'completed': 'Selesai',
    'cancelled': 'Dibatalkan'
  }
  return texts[status] || texts['draft']
}

const getParticipantStatusColor = (status) => {
  const colors = {
    'invited': 'bg-blue-500/20 text-blue-200 border-blue-500/30',
    'confirmed': 'bg-yellow-500/20 text-yellow-200 border-yellow-500/30',
    'attended': 'bg-green-500/20 text-green-200 border-green-500/30',
    'absent': 'bg-red-500/20 text-red-200 border-red-500/30'
  }
  return colors[status] || colors['invited']
}

const getParticipantStatusIcon = (status) => {
  const icons = {
    'invited': 'fas fa-envelope',
    'confirmed': 'fas fa-check',
    'attended': 'fas fa-user-check',
    'absent': 'fas fa-user-times'
  }
  return icons[status] || icons['invited']
}

const getParticipantStatusText = (status) => {
  const texts = {
    'invited': 'Diundang',
    'confirmed': 'Konfirmasi',
    'attended': 'Hadir',
    'absent': 'Tidak Hadir'
  }
  return texts[status] || texts['invited']
}

const getTrainerStatusColor = (status) => {
  const colors = {
    'invited': 'bg-blue-500/20 text-blue-200 border-blue-500/30',
    'confirmed': 'bg-yellow-500/20 text-yellow-200 border-yellow-500/30',
    'attended': 'bg-green-500/20 text-green-200 border-green-500/30',
    'absent': 'bg-red-500/20 text-red-200 border-red-500/30'
  }
  return colors[status] || colors['invited']
}

const getTrainerStatusIcon = (status) => {
  const icons = {
    'invited': 'fas fa-envelope',
    'confirmed': 'fas fa-check',
    'attended': 'fas fa-chalkboard-teacher',
    'absent': 'fas fa-user-times'
  }
  return icons[status] || icons['invited']
}

const getTrainerStatusText = (status) => {
  const texts = {
    'invited': 'Diundang',
    'confirmed': 'Konfirmasi',
    'attended': 'Hadir',
    'absent': 'Tidak Hadir'
  }
  return texts[status] || texts['invited']
}

const getTrainerTypeColor = (type) => {
  const colors = {
    'internal': 'bg-blue-500/20 text-blue-200 border-blue-500/30',
    'external': 'bg-purple-500/20 text-purple-200 border-purple-500/30'
  }
  return colors[type] || colors['internal']
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

const issueCertificates = async () => {
  const templates = props.certificateTemplates || []
  
  if (templates.length === 0) {
    Swal.fire({ 
      icon: 'warning', 
      title: 'Tidak Ada Template', 
      text: 'Buat template sertifikat terlebih dahulu di menu Certificate Templates.' 
    })
    return
  }

  // Check if course has default template
  const courseDefaultTemplate = props.training.course?.certificate_template_id
  const courseDefaultTemplateName = courseDefaultTemplate 
    ? templates.find(t => t.id == courseDefaultTemplate)?.name 
    : null

  let templateId = null
  let shouldSelectTemplate = true

  // If course has default template, show option to use it or select different one
  if (courseDefaultTemplate && courseDefaultTemplateName) {
    const useDefaultResult = await Swal.fire({
      title: 'Template Sertifikat',
      html: `
        <p class="mb-4">Course ini memiliki template default:</p>
        <p class="font-semibold text-blue-600 mb-4">${courseDefaultTemplateName}</p>
        <p>Gunakan template default atau pilih template lain?</p>
      `,
      icon: 'question',
      showCancelButton: true,
      showDenyButton: true,
      confirmButtonText: 'Gunakan Default',
      denyButtonText: 'Pilih Template Lain',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#3B82F6',
      denyButtonColor: '#F59E0B',
      cancelButtonColor: '#6B7280'
    })

    if (useDefaultResult.isDismissed) return // User cancelled

    if (useDefaultResult.isConfirmed) {
      // Use default template
      templateId = courseDefaultTemplate
      shouldSelectTemplate = false
    }
    // If isDenied, proceed to template selection
  }

  // Show template selection if needed
  if (shouldSelectTemplate) {
    const templateOptions = templates.reduce((acc, template) => {
      acc[template.id] = template.name
      return acc
    }, {})

    const { value: selectedTemplateId } = await Swal.fire({
      title: 'Pilih Template Sertifikat',
      input: 'select',
      inputOptions: templateOptions,
      inputPlaceholder: 'Pilih template...',
      inputValue: courseDefaultTemplate || '', // Pre-select course default if available
      showCancelButton: true,
      inputValidator: (value) => {
        if (!value) {
          return 'Pilih template terlebih dahulu!'
        }
      }
    })

    if (!selectedTemplateId) return
    templateId = selectedTemplateId
  }

  // Final confirmation
  const selectedTemplateName = templates.find(t => t.id == templateId)?.name || 'Template'
  const result = await Swal.fire({
    title: 'Terbitkan Sertifikat?',
    html: `
      <p class="mb-2">Sertifikat akan diterbitkan untuk peserta berstatus Hadir.</p>
      <p class="text-sm text-gray-600">Template: <span class="font-semibold">${selectedTemplateName}</span></p>
    `,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3B82F6',
    cancelButtonColor: '#6B7280',
    confirmButtonText: 'Terbitkan'
  })
  if (!result.isConfirmed) return

  // Issue certificates
  const requestData = templateId ? { template_id: templateId } : {}
  
  await router.post(route('lms.schedules.issue-certificates', { schedule: props.training.id }), requestData, {
    onSuccess: () => {
      Swal.fire({ icon: 'success', title: 'Berhasil', text: 'Sertifikat diterbitkan.' })
      emit('refresh')
    },
    onError: (e) => {
      Swal.fire({ icon: 'error', title: 'Gagal', text: Object.values(e)[0] || 'Gagal menerbitkan' })
    }
  })
}

const markAttended = async (participantId) => {
  try {
    console.log('Mark attended called for participant:', participantId);
    console.log('Training ID:', props.training.id);
    
    const result = await Swal.fire({
      title: 'Konfirmasi Kehadiran',
      text: 'Apakah Anda yakin ingin menandai peserta ini sebagai hadir?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#10B981',
      cancelButtonColor: '#6B7280',
      confirmButtonText: 'Ya, Tandai Hadir',
      cancelButtonText: 'Batal'
    })

    if (result.isConfirmed) {
      console.log('User confirmed, calling API...');
      
      // Show loading modal
      Swal.fire({
        title: 'Sabar Bu Ghea....',
        text: 'Antosan sakedap Bu Ghea, Nuju loding',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        backdrop: true,
        didOpen: () => {
          Swal.showLoading()
        }
      })
      
      // Call backend API to mark as attended
      await router.put(route('lms.schedules.mark-attended', {
        schedule: props.training.id,
        invitation: participantId
      }), {}, {
        onSuccess: () => {
          // Close loading modal first
          Swal.close()
          
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Peserta berhasil ditandai sebagai hadir',
            timer: 2000,
            showConfirmButton: false
          })
          // Refresh the page to update status
          window.location.reload()
        },
        onError: (errors) => {
          console.error('API Error for mark attended:', errors);
          
          // Close loading modal first
          Swal.close()
          
          Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: 'Gagal menandai peserta sebagai hadir: ' + Object.values(errors)[0]
          })
        }
      })
    }
  } catch (error) {
    console.error('Error marking attended:', error)
    console.error('Error details:', {
      message: error.message,
      stack: error.stack,
      name: error.name
    })
    
    // Close loading modal first
    Swal.close()
    
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat menandai peserta sebagai hadir'
    })
  }
}

const removeParticipant = async (participantId) => {
  try {
    console.log('Remove participant called for participant:', participantId);
    console.log('Training ID:', props.training.id);
    
    const result = await Swal.fire({
      title: 'Konfirmasi Hapus',
      text: 'Apakah Anda yakin ingin menghapus peserta ini dari training?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#EF4444',
      cancelButtonColor: '#6B7280',
      confirmButtonText: 'Ya, Hapus',
      cancelButtonText: 'Batal'
    })

    if (result.isConfirmed) {
      console.log('User confirmed, calling API...');
      
      // Show loading modal
      Swal.fire({
        title: 'Sabar Bu Ghea....',
        text: 'Antosan sakedap Bu Ghea, Nuju loding',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        backdrop: true,
        didOpen: () => {
          Swal.showLoading()
        }
      })
      
      await router.delete(route('lms.schedules.remove-participant', {
        schedule: props.training.id,
        invitation: participantId
      }), {
        onSuccess: () => {
          // Close loading modal first
          Swal.close()
          
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Peserta berhasil dihapus dari training',
            timer: 2000,
            showConfirmButton: false
          })
          // Refresh the page to update data
          window.location.reload()
        },
        onError: (errors) => {
          console.error('API Error for remove participant:', errors);
          
          // Close loading modal first
          Swal.close()
          
          Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: 'Gagal menghapus peserta: ' + Object.values(errors)[0]
          })
        }
      })
    }
  } catch (error) {
    console.error('Error removing participant:', error)
    console.error('Error details:', {
      message: error.message,
      stack: error.stack,
      name: error.name
    })
    
    // Close loading modal first
    Swal.close()
    
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat menghapus peserta'
    })
  }
}

// Trainer management methods
const setPrimaryTrainer = async (scheduleTrainerId) => {
  try {
    const result = await Swal.fire({
      title: 'Set Primary Trainer',
      text: 'Apakah Anda yakin ingin menjadikan trainer ini sebagai primary trainer?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#F59E0B',
      cancelButtonColor: '#6B7280',
      confirmButtonText: 'Ya, Set Primary',
      cancelButtonText: 'Batal'
    })

    if (result.isConfirmed) {
      // Show loading modal
      Swal.fire({
        title: 'Sabar Bu Ghea....',
        text: 'Antosan sakedap Bu Ghea, Nuju loding',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        backdrop: true,
        didOpen: () => {
          Swal.showLoading()
        }
      })

      await router.put(route('lms.schedules.set-primary-trainer', {
        schedule: props.training.id,
        trainer: scheduleTrainerId
      }), {}, {
        onSuccess: () => {
          // Close loading modal first
          Swal.close()
          
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Primary trainer berhasil diupdate',
            timer: 2000,
            showConfirmButton: false
          })
          emit('refresh')
        },
        onError: (errors) => {
          // Close loading modal first
          Swal.close()
          
          Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: 'Gagal mengupdate primary trainer: ' + Object.values(errors)[0]
          })
        }
      })
    }
  } catch (error) {
    // Close loading modal first
    Swal.close()
    
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat mengupdate primary trainer'
    })
  }
}

const removeTrainer = async (scheduleTrainerId) => {
  try {
    const result = await Swal.fire({
      title: 'Konfirmasi Hapus',
      text: 'Apakah Anda yakin ingin menghapus trainer ini dari training?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#EF4444',
      cancelButtonColor: '#6B7280',
      confirmButtonText: 'Ya, Hapus',
      cancelButtonText: 'Batal'
    })

    if (result.isConfirmed) {
      // Show loading modal
      Swal.fire({
        title: 'Sabar Bu Ghea....',
        text: 'Antosan sakedap Bu Ghea, Nuju loding',
        icon: 'info',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false,
        backdrop: true,
        didOpen: () => {
          Swal.showLoading()
        }
      })

      await router.delete(route('lms.schedules.remove-trainer', {
        schedule: props.training.id,
        trainer: scheduleTrainerId
      }), {
        onSuccess: () => {
          // Close loading modal first
          Swal.close()
          
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: 'Trainer berhasil dihapus dari training',
            timer: 2000,
            showConfirmButton: false
          })
          emit('refresh')
        },
        onError: (errors) => {
          // Close loading modal first
          Swal.close()
          
          Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: 'Gagal menghapus trainer: ' + Object.values(errors)[0]
          })
        }
      })
    }
  } catch (error) {
    // Close loading modal first
    Swal.close()
    
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat menghapus trainer'
    })
  }
}



const handleQRCodeError = (event) => {
  console.error('QR Code failed to load:', event.target.src)
  qrCodeError.value = true
}

const handleQRCodeLoad = (event) => {
  console.log('QR Code loaded successfully:', event.target.src)
}



const generateQRCode = async () => {
  try {
    if (props.training) {
      // Format scheduled_date to Y-m-d format
      const scheduledDate = new Date(props.training.scheduled_date).toISOString().split('T')[0]
      
      // Generate hash using the same method as backend
      const hashInput = props.training.id + props.training.course_id + scheduledDate
      const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(hashInput))
      const hashArray = Array.from(new Uint8Array(hash))
      const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('')
      
      const data = {
        schedule_id: props.training.id,
        course_id: props.training.course_id,
        scheduled_date: scheduledDate,
        hash: hashHex
      }
      
      const qrData = JSON.stringify(data)
      qrCodeDataUrl.value = await QRCode.toDataURL(qrData, {
        width: 300,
        margin: 2,
        color: {
          dark: '#000000',
          light: '#FFFFFF'
        }
      })
      
      console.log('QR Code generated successfully:', qrCodeDataUrl.value)
      console.log('QR Code data:', data)
    }
  } catch (error) {
    console.error('Error generating QR code:', error)
    qrCodeError.value = true
  }
}

// Training status management
const updateTrainingStatus = async (newStatus) => {
  try {
    const statusText = {
      'scheduled': 'Terjadwal',
      'published': 'Dipublikasi',
      'ongoing': 'Sedang Berlangsung', 
      'completed': 'Selesai',
      'cancelled': 'Dibatalkan'
    }[newStatus] || 'Tidak Diketahui'

    const result = await Swal.fire({
      title: 'Konfirmasi Perubahan Status',
      text: `Apakah Anda yakin ingin mengubah status training menjadi "${statusText}"?`,
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: newStatus === 'cancelled' ? '#EF4444' : '#3B82F6',
      cancelButtonColor: '#6B7280',
      confirmButtonText: 'Ya, Ubah Status',
      cancelButtonText: 'Batal'
    })

    if (result.isConfirmed) {
      console.log('=== CALLING UPDATE TRAINING STATUS API ===', {
        training_id: props.training.id,
        new_status: newStatus,
        route: route('lms.schedules.update-status', props.training.id)
      });
      
      // Show prominent loading modal
      console.log('=== SHOWING LOADING MODAL ===')
      console.log('Swal object:', Swal)
      console.log('Swal.fire method:', typeof Swal.fire)
      
      try {
        // Show loading modal with simple approach
        Swal.fire({
          title: 'Sabar Bu Ghea....',
          text: 'Antosan sakedap Bu Ghea, Nuju loding',
          icon: 'info',
          showConfirmButton: false,
          allowOutsideClick: false,
          allowEscapeKey: false,
          backdrop: true,
          didOpen: () => {
            Swal.showLoading()
          }
        })
        console.log('Loading modal should be visible now')
      } catch (error) {
        console.error('Error showing loading modal:', error)
      }
      
      await router.put(route('lms.schedules.update-status', props.training.id), {
        status: newStatus
      }, {
        onSuccess: () => {
          // Close loading modal first
          Swal.close()
          
          Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: `Status training berhasil diubah menjadi "${statusText}"`,
            timer: 2000,
            showConfirmButton: false
          })
          emit('refresh')
        },
        onError: (errors) => {
          console.error('API Error for update training status:', errors);
          
          // Close loading modal first
          Swal.close()
          
          Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: 'Gagal mengubah status training: ' + Object.values(errors)[0]
          })
        }
      })
    }
  } catch (error) {
    console.error('Error updating training status:', error)
    
    // Close loading modal first
    Swal.close()
    
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat mengubah status training'
    })
  }
}

// Review list management
const showReviewList = async () => {
  showReviewListModal.value = true
  loadingReviews.value = true
  
  try {
    const response = await fetch(route('lms.schedules.reviews', props.training.id))
    const data = await response.json()
    
    if (data.success) {
      reviewData.value = data
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: data.message || 'Gagal memuat review training'
      })
    }
  } catch (error) {
    console.error('Error fetching training reviews:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat memuat review training'
    })
  } finally {
    loadingReviews.value = false
  }
}

const closeReviewListModal = () => {
  showReviewListModal.value = false
  reviewData.value = {
    training: null,
    reviews: [],
    statistics: null
  }
}

// Trainer ratings management
const showTrainerRatings = async () => {
  showTrainerRatingsModal.value = true
  loadingTrainerRatings.value = true
  
  try {
    const response = await fetch(route('lms.schedules.trainer-ratings', props.training.id))
    const data = await response.json()
    
    if (data.success) {
      trainerRatingsData.value = data
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Gagal!',
        text: data.message || 'Gagal memuat rating trainer'
      })
    }
  } catch (error) {
    console.error('Error fetching trainer ratings:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Terjadi kesalahan saat memuat rating trainer'
    })
  } finally {
    loadingTrainerRatings.value = false
  }
}

const closeTrainerRatingsModal = () => {
  showTrainerRatingsModal.value = false
  trainerRatingsData.value = {
    training: null,
    trainer_ratings: [],
    statistics: null
  }
}

onMounted(() => {
  generateQRCode()
})
</script>
