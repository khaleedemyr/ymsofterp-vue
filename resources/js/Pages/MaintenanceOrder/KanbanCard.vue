<template>
  <div 
    class="kanban-card bg-white rounded-xl shadow-lg p-4 cursor-move border border-gray-200 hover:shadow-xl transition-all duration-200 select-none flex flex-col gap-2 relative"
    draggable="true"
    @dragstart="onDragStart"
    @dragend="onDragEnd"
    :class="{
      'cursor-not-allowed': !canMoveTask
    }"
  >
    <!-- Loading Overlay -->
    <div v-if="isLoading" class="absolute inset-0 bg-white/50 backdrop-blur-sm rounded-xl flex items-center justify-center z-50">
      <div class="flex items-center gap-2">
        <div class="w-5 h-5 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
        <span class="text-sm text-blue-600">Moving...</span>
      </div>
    </div>

    <!-- Dropdown Toggle Button -->
    <div class="absolute top-2 right-2 z-10">
      <button @click.stop="showDropdown = !showDropdown" class="p-1 rounded-full hover:bg-gray-100 focus:outline-none">
        <i class="fas fa-ellipsis-v text-gray-500"></i>
      </button>
    </div>

    <!-- Dropdown Menu -->
    <div v-if="showDropdown" class="absolute right-0 top-8 w-40 bg-white border rounded shadow-lg z-20">
      <button 
        @click="showTimelineModal = true"
        class="w-full text-left px-4 py-2 hover:bg-blue-50 text-gray-700 flex items-center gap-2"
      >
        <i class="fas fa-stream"></i> Timeline
      </button>
      <button @click="openAssignMemberModal" class="w-full text-left px-4 py-2 hover:bg-blue-50 text-gray-700 flex items-center gap-2">
        <i class="fas fa-user-plus"></i> Assign Member
      </button>
      <ActionPlanButton 
        :task-id="task.id" 
        :user="user"
        @action-plan-created="onActionPlanCreated"
      />
      <button 
        v-if="canCreateRetail"
        @click="openRetailModal" 
        class="w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center"
      >
        <i class="fas fa-shopping-cart mr-2"></i>
        Retail
      </button>
      <button v-if="task.status === 'PR'" @click="showPRModal = true" class="w-full text-left px-4 py-2 hover:bg-blue-50 text-gray-700 flex items-center gap-2">
        <i class="fas fa-file-invoice"></i> Buat PR
      </button>
      <button 
        v-if="task.status === 'PO'" 
        @click="showPOTypeModal = true" 
        class="w-full text-left px-4 py-2 hover:bg-blue-50 text-gray-700 flex items-center gap-2"
      >
        <i class="fas fa-file-invoice-dollar"></i> Buat PO
      </button>
      <button v-if="task.status === 'PO'" @click="showBiddingHistory" class="w-full text-left px-4 py-2 hover:bg-blue-50 text-gray-700 flex items-center gap-2">
        <i class="fas fa-history mr-2"></i> History Bidding
      </button>
      <button 
        v-if="canAddEvidence && task.status === 'IN_REVIEW'"
        @click="showEvidenceModal = true"
        class="w-full text-left px-4 py-2 hover:bg-blue-50 text-gray-700 flex items-center gap-2"
      >
        <i class="fas fa-camera"></i> Evidence
      </button>
      <button
        @click="onDeleteTask"
        :disabled="isDeleting"
        class="w-full text-left px-4 py-2 hover:bg-red-50 text-red-600 flex items-center gap-2"
      >
        <i class="fas fa-trash"></i>
        <span v-if="!isDeleting">Delete Task</span>
        <span v-else>
          <i class="fas fa-spinner fa-spin"></i> Menghapus...
        </span>
      </button>
      <div class="border-t border-gray-200 my-1"></div>
      <div class="px-4 py-2 text-xs text-gray-500 font-semibold">Move Task</div>
      <button 
        v-for="status in ['TASK', 'PR', 'PO', 'IN_PROGRESS', 'IN_REVIEW', 'DONE']" 
        :key="status"
        @click="moveTask(status)"
        class="flex items-center gap-2 w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-blue-50"
        :disabled="!canMoveTask"
      >
        <i :class="getStatusIcon(status)"></i> {{ status === 'TASK' ? 'TO DO' : status }}
      </button>
    </div>
    <!-- Task Number & Meta -->
    <div class="text-sm text-blue-600 font-medium">{{ task.task_number }}</div>
    
    <!-- Creator & Date -->
    <div class="flex items-center gap-2 text-xs text-gray-500">
      <i class="fas fa-user"></i>
      <span>{{ task.created_by_name }}</span>
      <i class="fas fa-calendar ml-2"></i>
      <span>{{ formatDate(task.created_at) }}</span>
    </div>

    <!-- Due Date -->
    <div class="flex items-center gap-2 text-xs" :class="getDueDateColor">
      <i class="far fa-clock"></i>
      <span>Due: {{ formatDate(task.due_date) }}</span>
    </div>
    <!-- Finish Date -->
    <div v-if="task.status === 'DONE' && task.completed_at" class="flex items-center gap-2 text-xs mt-1" :class="getFinishDateColor">
      <i class="fas fa-flag-checkered"></i>
      <span>Finish: {{ formatDate(task.completed_at) }}</span>
      <span v-if="isLate" class="ml-2 text-red-500 font-medium">({{ lateDays }} hari telat)</span>
    </div>

    <!-- Labels -->
    <div class="flex flex-wrap gap-1">
      <span class="text-xs px-2 py-1 rounded" :class="{
        'bg-red-100 text-red-700': task.priority_name === 'IMPORTANT VS URGENT',
        'bg-yellow-100 text-yellow-700': task.priority_name === 'IMPORTANT NOT URGENT',
        'bg-blue-100 text-blue-700': task.priority_name === 'NOT IMPORTANT VS URGENT',
        'bg-green-100 text-green-700': task.priority_name === 'NOT IMPORTANT NOT URGENT',
      }">
        <i class="fas fa-exclamation-circle mr-1"></i>
        {{ task.priority_name }}
      </span>
      <span class="text-xs px-2 py-1 rounded flex items-center gap-1" 
            :style="{ backgroundColor: getCategoryColor(task.label_name) + '20', color: getCategoryColor(task.label_name) }">
        <i :class="['fas', getCategoryIcon(task.label_name)]"></i>
        {{ task.label_name }}
      </span>
    </div>

    <!-- Title & Description -->
    <div class="mt-1">
      <h3 class="font-bold text-gray-800">{{ task.title }}</h3>
      <p class="text-sm text-gray-600 mt-1" :class="{ 'line-clamp-2': !showMore }">{{ task.description }}</p>
      <button @click="showMore = !showMore" class="text-xs text-blue-600 hover:text-blue-800 mt-1">
        Show {{ showMore ? 'less' : 'more' }} <i :class="['fas', showMore ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
      </button>
    </div>

    <!-- Members -->
    <div class="mt-2">
      <div class="flex items-center gap-1 mb-1">
        <i class="fas fa-users text-blue-400"></i>
        <span class="text-xs text-gray-700 font-semibold">Members</span>
        <span class="text-xs text-gray-500">({{ task.members?.length || 0 }})</span>
      </div>
      <div class="flex items-center gap-1">
        <template v-for="(member, idx) in task.members" :key="member.id">
          <div :class="['w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold mr-1 cursor-pointer', getMemberColor(idx)]" :title="member.nama_lengkap">
            {{ getInitials(member.nama_lengkap) }}
        </div>
        </template>
      </div>
    </div>

    <!-- Attachments -->
    <div class="border-t border-gray-100 pt-2 mt-1">
      <button @click="toggleAttachments" class="w-full flex items-center justify-between text-sm text-gray-600 hover:bg-gray-50 rounded px-2 py-1">
        <div class="flex items-center gap-2">
          <i class="fas fa-paperclip"></i>
          <span>Attachments ({{ totalAttachments }})</span>
        </div>
        <i :class="['fas', showAttachments ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
      </button>
      
      <!-- Attachments Detail -->
      <div v-if="showAttachments" class="mt-2 space-y-2">
        <!-- Photos -->
        <div v-if="mediaFiles.images.length" class="space-y-1">
          <div class="text-xs text-gray-500">Photos ({{ mediaFiles.images.length }})</div>
          <div class="flex flex-wrap gap-2">
            <div v-for="image in displayedImages" :key="image.id" 
                 class="w-12 h-12 rounded border cursor-pointer overflow-hidden"
                 @click="openImagePreview(image)">
              <img :src="image.url" :alt="image.name" class="w-full h-full object-cover" />
            </div>
            <div v-if="mediaFiles.images.length > 3" 
                 class="w-12 h-12 rounded border bg-gray-100 flex items-center justify-center text-xs text-gray-600 cursor-pointer"
                 @click="openMediaSlider('image', 3)">
              +{{ mediaFiles.images.length - 3 }}
            </div>
          </div>
        </div>
        
        <!-- Videos -->
        <div v-if="mediaFiles.videos.length" class="space-y-1">
          <div class="text-xs text-gray-500">Videos ({{ mediaFiles.videos.length }})</div>
          <div class="flex flex-wrap gap-2">
            <div v-for="video in mediaFiles.videos" :key="video.id"
                 class="w-16 h-12 rounded bg-gray-100 flex items-center justify-center cursor-pointer relative group"
                 @click="openVideoPlayer(video)">
              <img v-if="video.thumbnail" :src="video.thumbnail" :alt="video.name" 
                   class="w-full h-full object-cover rounded" />
              <div class="absolute inset-0 bg-black/40 group-hover:bg-black/60 flex items-center justify-center transition-all">
                <i class="fas fa-play text-white"></i>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Documents -->
        <div v-if="documents.length" class="space-y-1">
          <div class="text-xs text-gray-500">Documents ({{ documents.length }})</div>
          <div class="flex flex-wrap gap-2">
            <div v-for="doc in documents" :key="doc.id" 
                 class="flex items-center gap-2 p-2 rounded bg-gray-50 text-xs cursor-pointer hover:bg-gray-100 group"
                 @click="openDocument(doc)">
              <i :class="getDocumentIcon(doc.type)"></i>
              <div>
                <div class="truncate max-w-[100px] group-hover:text-blue-600">{{ doc.name }}</div>
                <div class="text-gray-400">{{ formatFileSize(doc.size) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Action Plan (toggle) -->
    <div class="border-t border-gray-100 pt-2 mt-1">
      <button @click="toggleActionPlan" class="w-full flex items-center justify-between text-sm text-gray-600 hover:bg-gray-50 rounded px-2 py-1">
        <div class="flex items-center gap-2">
          <i class="fas fa-clipboard-list"></i>
          <span>Action Plan</span>
        </div>
        <i :class="['fas', showActionPlan ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
      </button>
      <div v-if="showActionPlan" class="mt-2">
        <ActionPlanList 
          ref="actionPlanList"
          :task-id="task.id" 
        />
      </div>
    </div>

    <!-- Evidence (toggle) -->
    <div class="border-t border-gray-100 pt-2 mt-1">
      <button @click="toggleEvidence" class="w-full flex items-center justify-between text-sm text-gray-600 hover:bg-gray-50 rounded px-2 py-1">
        <div class="flex items-center gap-2">
          <i class="fas fa-camera"></i>
          <span>Evidence</span>
        </div>
        <i :class="['fas', showEvidence ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
      </button>
      <div v-if="showEvidence" class="mt-2">
        <EvidenceList :task-id="task.id" />
      </div>
    </div>

    <!-- Retail Data (toggle) -->
    <div class="border-t border-gray-100 pt-2 mt-1">
      <button @click="toggleRetail" class="w-full flex items-center justify-between text-sm text-gray-600 hover:bg-gray-50 rounded px-2 py-1">
        <div class="flex items-center gap-2">
          <i class="fas fa-shopping-cart"></i>
          <span>Retail Data</span>
        </div>
        <i :class="['fas', showRetail ? 'fa-chevron-up' : 'fa-chevron-down']"></i>
      </button>
      <div v-if="showRetail" class="mt-2">
        <RetailList 
          ref="retailList"
          :task-id="task.id" 
        />
      </div>
    </div>

    <!-- Comments -->
    <button @click="showCommentModal = true" class="flex items-center justify-center gap-2 text-sm text-gray-600 hover:bg-gray-50 rounded px-2 py-1 mt-1">
      <div class="relative">
        <i class="far fa-comment"></i>
        <span v-if="commentCount > 0" 
              class="absolute -top-2 -right-2 bg-blue-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
          {{ commentCount }}
        </span>
      </div>
      <span>Comments</span>
    </button>

    <!-- Image Preview Modal -->
    <TransitionRoot appear :show="showImagePreview" as="template">
      <Dialog as="div" @close="closeImagePreview" class="relative z-50">
        <TransitionChild
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <DialogOverlay class="fixed inset-0 bg-black/75" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center p-4">
            <TransitionChild
              enter="duration-300 ease-out"
              enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100"
              leave="duration-200 ease-in"
              leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95"
            >
              <DialogPanel class="relative bg-white rounded-lg max-w-3xl">
                <button @click="closeImagePreview" class="absolute -top-10 right-0 text-white hover:text-gray-300">
                  <i class="fas fa-times text-xl"></i>
                </button>
                <img v-if="currentImage" 
                     :src="currentImage.url" 
                     :alt="currentImage.name"
                     class="rounded-lg" />
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>

    <!-- Media Slider Modal -->
    <TransitionRoot appear :show="showMediaSlider" as="template">
      <Dialog as="div" @close="closeMediaSlider" class="relative z-50">
        <TransitionChild
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <DialogOverlay class="fixed inset-0 bg-black/90" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center">
            <TransitionChild
              enter="duration-300 ease-out"
              enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100"
              leave="duration-200 ease-in"
              leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95"
            >
              <DialogPanel class="relative w-full max-w-4xl p-4">
                <!-- Navigation Buttons -->
                <button v-if="currentSlideIndex > 0"
                        @click="prevSlide" 
                        class="absolute left-4 top-1/2 -translate-y-1/2 text-white hover:text-gray-300 z-10">
                  <i class="fas fa-chevron-left text-3xl"></i>
                </button>
                <button v-if="currentSlideIndex < totalSlides - 1"
                        @click="nextSlide" 
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-white hover:text-gray-300 z-10">
                  <i class="fas fa-chevron-right text-3xl"></i>
                </button>

                <!-- Close Button -->
                <button @click="closeMediaSlider" 
                        class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
                  <i class="fas fa-times text-xl"></i>
                </button>

                <!-- Main Content -->
                <div class="relative aspect-video bg-black rounded-lg overflow-hidden">
                  <img v-if="currentSlide.mediaType === 'image'"
                       :src="currentSlide.url"
                       :alt="currentSlide.name"
                       class="w-full h-full object-contain" />
                  <video v-else-if="currentSlide.mediaType === 'video'"
                         :src="currentSlide.url"
                         :ref="el => { if (currentSlide.mediaType === 'video') videoPlayer = el }"
                         controls
                         class="w-full h-full" />
                </div>

                <!-- Thumbnails -->
                <div class="flex justify-center gap-2 mt-4">
                  <button v-for="(media, index) in allMedia" 
                          :key="media.id"
                          @click="goToSlide(index)"
                          :class="[
                            'w-16 h-12 rounded overflow-hidden border-2',
                            currentSlideIndex === index ? 'border-white' : 'border-transparent'
                          ]">
                    <img v-if="media.mediaType === 'image'"
                         :src="media.url"
                         :alt="media.name"
                         class="w-full h-full object-cover" />
                    <div v-else
                         class="w-full h-full bg-gray-800 flex items-center justify-center">
                      <i class="fas fa-play text-white"></i>
                    </div>
                  </button>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>

    <!-- Video Player Modal -->
    <TransitionRoot appear :show="showVideoPlayer" as="template">
      <Dialog as="div" @close="closeVideoPlayer" class="relative z-50">
        <TransitionChild
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <DialogOverlay class="fixed inset-0 bg-black/75" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center p-4">
            <TransitionChild
              enter="duration-300 ease-out"
              enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100"
              leave="duration-200 ease-in"
              leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95"
            >
              <DialogPanel class="relative bg-black rounded-lg overflow-hidden max-w-3xl">
                <button @click="closeVideoPlayer" 
                        class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
                  <i class="fas fa-times text-xl"></i>
                </button>
                <video v-if="currentVideo"
                       :src="currentVideo.url"
                       ref="videoPlayer"
                       controls
                       class="w-full" />
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>

    <!-- Comment Modal -->
    <CommentModal 
      v-if="showCommentModal"
      :task-id="task.id"
      @close="showCommentModal = false"
      @comment-added="onCommentAdded"
    />
    <AssignMemberModal
      v-if="showAssignMemberModal"
      :show="showAssignMemberModal"
      :task-id="task.id"
      :on-close="closeAssignMemberModal"
      @saved="onAssignMemberSaved"
    />

    <!-- Retail Modal -->
    <TransitionRoot appear :show="showRetailModal" as="template">
      <Dialog as="div" @close="closeRetailModal" class="relative z-[8888]">
        <TransitionChild
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <DialogOverlay class="fixed inset-0 bg-black/40" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center p-4">
            <TransitionChild
              enter="duration-300 ease-out"
              enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100"
              leave="duration-200 ease-in"
              leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95"
            >
              <DialogPanel class="w-full max-w-3xl transform overflow-hidden rounded-lg bg-white p-6 text-left align-middle shadow-xl transition-all">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-lg font-medium leading-6 text-gray-900">
                    Buat Retail
                  </h3>
                  <button @click="closeRetailModal" class="text-gray-400 hover:text-gray-500">
                    <i class="fas fa-times"></i>
                  </button>
                </div>
                
                <AddRetailItem 
                  :task-id="task.id"
                  @saved="onRetailSaved"
                />
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>

    <TimelineModal :show="showTimelineModal" :task-id="task.id" :on-close="() => showTimelineModal = false" />
    <PurchaseRequisitionListModal :show="showPRModal" :task-id="task.id" @close="showPRModal = false" />
    <PurchaseOrderTypeModal 
      :show="showPOTypeModal" 
      :task-id="task.id" 
      @close="showPOTypeModal = false"
      @select-type="onPOTypeSelected"
      @open-direct-po="showPOModal = true"
    />
    <PurchaseOrderListModal 
      :show="showPOModal" 
      :task-id="task.id" 
      @close="showPOModal = false" 
    />
    <EvidenceModal 
      v-if="showEvidenceModal"
      :task-id="task.id"
      @close="showEvidenceModal = false"
      @evidence-added="onEvidenceAdded"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogOverlay,
  DialogPanel,
} from '@headlessui/vue';
import CommentModal from './CommentModal.vue';
import AssignMemberModal from './AssignMemberModal.vue';
import axios from 'axios';
import { format } from 'date-fns';
import { id } from 'date-fns/locale';
import ActionPlanButton from './ActionPlanButton.vue';
import ActionPlanList from './ActionPlanList.vue';
import AddRetailItem from './AddRetailItem.vue';
import { usePage } from '@inertiajs/vue3';
import RetailList from './RetailList.vue';
import { differenceInDays } from 'date-fns';
import { router } from '@inertiajs/vue3';
import TimelineModal from './TimelineModal.vue';
import PurchaseRequisitionListModal from './PurchaseRequisitionListModal.vue';
import Swal from 'sweetalert2';
import PurchaseOrderTypeModal from './PurchaseOrderTypeModal.vue';
import PurchaseOrderListModal from './PurchaseOrderListModal.vue';
import EvidenceModal from './EvidenceModal.vue';
import EvidenceList from './EvidenceList.vue';

const props = defineProps({
  task: {
    type: Object,
    required: true
  }
});

const emit = defineEmits(['taskMoved', 'open-direct-po']);

const user = computed(() => usePage().props.auth.user);

// Computed properties
const canCreateRetail = computed(() => {
  return user.value.division_id === 20 && user.value.status === 'A' || 
         user.value.id_role === '5af56935b011a' && user.value.status === 'A';
});

const showMore = ref(false);
const showAttachments = ref(false);
const showImagePreview = ref(false);
const showMediaSlider = ref(false);
const showVideoPlayer = ref(false);
const currentImage = ref(null);
const currentVideo = ref(null);
const currentSlideIndex = ref(0);
const videoPlayer = ref(null);
const showCommentModal = ref(false);
const commentCount = ref(0);
const showDropdown = ref(false);
const showAssignMemberModal = ref(false);
const showActionPlan = ref(false);
const showRetail = ref(false);
const showRetailModal = ref(false);
const isLoading = ref(false);
const showTimelineModal = ref(false);
const showPRModal = ref(false);
const showPOTypeModal = ref(false);
const showPOModal = ref(false);
const showEvidenceModal = ref(false);
const showEvidence = ref(false);
const isDeleting = ref(false);

const actionPlanList = ref(null);
const retailList = ref(null);

const getDueDateColor = computed(() => {
  if (!props.task.due_date) return 'text-gray-500';
  
  const today = new Date();
  const dueDate = new Date(props.task.due_date);
  const daysDiff = differenceInDays(dueDate, today);

  if (daysDiff < 0) {
    // Sudah lewat due date
    return 'text-red-500 font-medium';
  } else if (daysDiff <= 2) {
    // Kurang dari atau sama dengan 2 hari
    return 'text-yellow-500 font-medium';
  } else {
    // Lebih dari 2 hari
    return 'text-green-500';
  }
});

const isLate = computed(() => {
  if (!props.task.completed_at || !props.task.due_date) return false;
  const completedDate = new Date(props.task.completed_at);
  const dueDate = new Date(props.task.due_date);
  return completedDate > dueDate;
});

const lateDays = computed(() => {
  if (!isLate.value) return 0;
  const completedDate = new Date(props.task.completed_at);
  const dueDate = new Date(props.task.due_date);
  const diffTime = Math.abs(completedDate - dueDate);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  return diffDays;
});

const getFinishDateColor = computed(() => {
  if (!props.task.completed_at || !props.task.due_date) return 'text-gray-500';
  return isLate.value ? 'text-red-500 font-medium' : 'text-lime-600 font-medium';
});

function onDragStart(e) {
  console.log('Task data:', props.task);
  const taskId = props.task.id;
  if (!taskId) {
    console.error('Task ID is missing:', props.task);
    return;
  }
  console.log('Starting drag for task:', taskId);
  e.dataTransfer.setData('text/plain', taskId.toString());
  e.target.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}

function onDragEnd(e) {
  e.target.classList.remove('dragging');
}

function formatDate(dateString) {
  const date = new Date(dateString);
  return new Intl.DateTimeFormat('id-ID', { 
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  }).format(date);
}

function toggleAttachments() {
  showAttachments.value = !showAttachments.value;
}

const mediaFiles = computed(() => {
  if (!props.task.media) return { images: [], videos: [] };
  
  return props.task.media.reduce((acc, media) => {
    if (media.file_type.startsWith('image/')) {
      acc.images.push({
        id: media.id,
        url: `/storage/${media.file_path}`,
        name: media.file_name,
        type: media.file_type
      });
    } else if (media.file_type.startsWith('video/')) {
      acc.videos.push({
        id: media.id,
        url: `/storage/${media.file_path}`,
        name: media.file_name,
        type: media.file_type,
        thumbnail: null // TODO: Implementasi thumbnail untuk video
      });
    }
    return acc;
  }, { images: [], videos: [] });
});

const documents = computed(() => {
  if (!props.task.documents) return [];
  
  return props.task.documents.map(doc => ({
    id: doc.id,
    url: `/storage/${doc.file_path}`,
    name: doc.file_name,
    type: doc.file_type,
    size: doc.file_size
  }));
});

const totalAttachments = computed(() => {
  return (
    mediaFiles.value.images.length + 
    mediaFiles.value.videos.length + 
    documents.value.length
  );
});

const displayedImages = computed(() => {
  return mediaFiles.value.images.slice(0, 3);
});

const allMedia = computed(() => {
  const images = mediaFiles.value.images.map(img => ({ ...img, mediaType: 'image' }));
  const videos = mediaFiles.value.videos.map(vid => ({ ...vid, mediaType: 'video' }));
  return [...images, ...videos];
});

const totalSlides = computed(() => allMedia.value.length);

const currentSlide = computed(() => allMedia.value[currentSlideIndex.value] || {});

function getDocumentIcon(fileType) {
  if (fileType.includes('pdf')) {
    return 'fas fa-file-pdf text-red-500';
  } else if (fileType.includes('spreadsheet') || fileType.includes('excel') || fileType.includes('csv')) {
    return 'fas fa-file-excel text-green-500';
  } else if (fileType.includes('document') || fileType.includes('word')) {
    return 'fas fa-file-word text-blue-500';
  }
  return 'fas fa-file text-gray-500';
}

function formatFileSize(bytes) {
  if (!bytes) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`;
}

function openImagePreview(image) {
  currentImage.value = image;
  showImagePreview.value = true;
}

function closeImagePreview() {
  showImagePreview.value = false;
  currentImage.value = null;
}

function openMediaSlider(type, startIndex = 0) {
  currentSlideIndex.value = startIndex;
  showMediaSlider.value = true;
}

function closeMediaSlider() {
  showMediaSlider.value = false;
  currentSlideIndex.value = 0;
}

function openVideoPlayer(video) {
  currentVideo.value = video;
  showVideoPlayer.value = true;
}

function closeVideoPlayer() {
  if (videoPlayer.value) {
    videoPlayer.value.pause();
  }
  showVideoPlayer.value = false;
  currentVideo.value = null;
}

function prevSlide() {
  if (currentSlideIndex.value > 0) {
    currentSlideIndex.value--;
  }
}

function nextSlide() {
  if (currentSlideIndex.value < totalSlides.value - 1) {
    currentSlideIndex.value++;
  }
}

function goToSlide(index) {
  currentSlideIndex.value = index;
}

function openDocument(doc) {
  window.open(doc.url, '_blank');
}

// Fungsi untuk mendapatkan warna kategori
function getCategoryColor(labelName) {
  const colorMap = {
    'Mechanical': '#FF4444',
    'Electrical': '#000080',
    'Plumbing': '#33B5E5',
    'Machinary': '#00C851',
    'Civil': '#AA66CC',
    'Others': '#8A8A8A'
  };
  return colorMap[labelName] || '#8A8A8A';
}

// Fungsi untuk mendapatkan icon kategori
function getCategoryIcon(labelName) {
  const iconMap = {
    'Mechanical': 'fa-cogs',
    'Electrical': 'fa-bolt',
    'Plumbing': 'fa-faucet',
    'Machinary': 'fa-industry',
    'Civil': 'fa-hard-hat',
    'Others': 'fa-tools'
  };
  return iconMap[labelName] || 'fa-tools';
}

// Fungsi untuk mengambil jumlah komentar
async function fetchCommentCount() {
  try {
    const response = await axios.get(`/api/maintenance-comments/${props.task.id}/count`);
    commentCount.value = response.data.count;
  } catch (error) {
    console.error('Error fetching comment count:', error);
  }
}

// Panggil fetchCommentCount saat komponen dimount
onMounted(() => {
  fetchCommentCount();
});

// Handler ketika komentar baru ditambahkan
function onCommentAdded() {
  fetchCommentCount();
}

function openAssignMemberModal() {
  showDropdown.value = false;
  showAssignMemberModal.value = true;
}
function closeAssignMemberModal() {
  showAssignMemberModal.value = false;
}
function onAssignMemberSaved() {
  // Bisa fetch ulang member jika ingin update tampilan
}

function getInitials(name) {
  if (!name) return '';
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0,2);
}

const memberColors = [
  'bg-blue-500 text-white',
  'bg-green-500 text-white',
  'bg-pink-500 text-white',
  'bg-yellow-500 text-white',
  'bg-purple-500 text-white',
  'bg-red-500 text-white',
  'bg-indigo-500 text-white',
  'bg-teal-500 text-white',
  'bg-orange-500 text-white',
];
function getMemberColor(idx) {
  return memberColors[idx % memberColors.length];
}

function onActionPlanCreated() {
  actionPlanList.value?.loadActionPlans();
}

function onRetailSaved() {
  closeRetailModal();
  retailList.value?.loadRetailData();
}

function toggleActionPlan() {
  showActionPlan.value = !showActionPlan.value;
}

function toggleRetail() {
  showRetail.value = !showRetail.value;
}

function openRetailModal() {
  showRetailModal.value = true;
  showDropdown.value = false;
}

function closeRetailModal() {
  showRetailModal.value = false;
}

function toggleEvidence() {
  showEvidence.value = !showEvidence.value;
}

const canMoveTask = computed(() => {
    const user = usePage().props.auth.user;
    return user.status === 'A' && (
        user.division_id === 20 ||
        user.id_role === '5af56935b011a' ||
        user.id_jabatan === 217
    );
});

const moveTask = async (newStatus) => {
    if (!canMoveTask.value) {
        alert('Anda tidak memiliki akses untuk memindahkan task');
        return;
    }

    const validStatuses = ['TASK', 'PR', 'PO', 'IN_PROGRESS', 'IN_REVIEW', 'DONE'];
    if (!validStatuses.includes(newStatus)) {
        alert('Status tidak valid');
        return;
    }

    // Jika task akan dipindah ke PO, cek status PR terlebih dahulu
    if (newStatus === 'PO') {
        try {
            // Ambil data PR untuk task ini
            const response = await axios.get(`/api/maintenance-tasks/${props.task.id}/purchase-requisitions`);
            const prs = response.data;

            // Cek apakah ada PR dengan status DRAFT
            const hasDraftPR = prs.some(pr => pr.status === 'DRAFT');
            
            if (hasDraftPR) {
                Swal.fire({
                    title: 'Tidak dapat memindahkan task',
                    text: 'Task tidak dapat dipindahkan ke PO karena masih ada PR dengan status DRAFT',
                    icon: 'warning'
                });
                return;
            }
        } catch (error) {
            console.error('Error checking PR status:', error);
            Swal.fire('Error', 'Gagal memeriksa status PR', 'error');
            return;
        }
    }

    // Jika task akan dipindah dari PO ke IN_PROGRESS, cek status PO terlebih dahulu
    if (newStatus === 'IN_PROGRESS') {
        try {
            // Ambil data PO untuk task ini
            const response = await axios.get(`/api/maintenance-tasks/${props.task.id}/purchase-orders`);
            const pos = response.data;

            // Cek apakah ada PO dengan status DRAFT
            const draftPOs = pos.filter(po => po.status === 'DRAFT');
            
            if (draftPOs.length > 0) {
                const poNumbers = draftPOs.map(po => po.po_number).join(', ');
                Swal.fire({
                    title: 'Tidak dapat memindahkan task',
                    html: `Task tidak dapat dipindahkan ke In Progress karena masih ada PO dengan status DRAFT:<br><br>${poNumbers}`,
                    icon: 'warning'
                });
                return;
            }
        } catch (error) {
            console.error('Error checking PO status:', error);
            Swal.fire('Error', 'Gagal memeriksa status PO', 'error');
            return;
        }
    }

    // VALIDASI EVIDENCE sebelum move ke DONE
    if (newStatus === 'DONE') {
        try {
            const { data } = await axios.get(`/api/maintenance-evidence/${props.task.id}`);
            if (!data || data.length === 0) {
                Swal.fire('Tidak bisa selesai', 'Harus upload dulu bukti pekerjaan telah selesai.', 'warning');
                return;
            }
        } catch (error) {
            Swal.fire('Error', 'Gagal memeriksa evidence', 'error');
            return;
        }
    }

    isLoading.value = true;
    router.post(route('maintenance-tasks.update-status', props.task.id), {
        status: newStatus,
        notify_members: true // Selalu kirim notifikasi untuk setiap perpindahan status
    }, {
        preserveScroll: true,
        onSuccess: () => {
            emit('taskMoved');
            showDropdown.value = false;
            isLoading.value = false;
        },
        onError: () => {
            isLoading.value = false;
        }
    });
};

const getStatusIcon = (status) => {
    const icons = {
        'TASK': 'fas fa-clipboard-list',
        'PR': 'fas fa-file-invoice',
        'PO': 'fas fa-file-invoice-dollar',
        'IN_PROGRESS': 'fas fa-spinner',
        'IN_REVIEW': 'fas fa-eye',
        'DONE': 'fas fa-check-circle'
    };
    return icons[status] || 'fas fa-circle';
};

function onPOTypeSelected(type) {
  if (type === 'bidding') {
    // TODO: Handle bidding type
    console.log('Bidding selected');
  }
}

const showBiddingHistory = async () => {
  try {
    const response = await axios.get('/api/maintenance-tasks/bidding-history', {
      params: { task_id: props.task.id }
    });
    
    // Tampilkan modal dengan data history
    Swal.fire({
      title: 'History Bidding',
      html: generateHistoryHtml(response.data),
      width: '80%',
      showCloseButton: true,
      showConfirmButton: false
    });
  } catch (error) {
    console.error('Error fetching bidding history:', error);
    Swal.fire('Error', 'Gagal mengambil data history bidding', 'error');
  }
};

const generateHistoryHtml = (history) => {
  if (!history || history.length === 0) {
    return '<p>Tidak ada data history bidding</p>';
  }

  // Group by item
  const groupedByItem = {};
  history.forEach(item => {
    if (!groupedByItem[item.item_name]) {
      groupedByItem[item.item_name] = [];
    }
    groupedByItem[item.item_name].push(item);
  });

  let html = '<div style="max-height:60vh;overflow-y:auto;">';
  html += '<table style="width:100%;border-collapse:collapse;">';
  html += '<thead><tr>' +
    '<th style="border:1px solid #eee;padding:6px;">Item</th>' +
    '<th style="border:1px solid #eee;padding:6px;">Supplier</th>' +
    '<th style="border:1px solid #eee;padding:6px;">Harga</th>' +
    '<th style="border:1px solid #eee;padding:6px;">Status</th>' +
    '<th style="border:1px solid #eee;padding:6px;">Tanggal</th>' +
    '<th style="border:1px solid #eee;padding:6px;">Oleh</th>' +
    '</tr></thead><tbody>';

  Object.keys(groupedByItem).forEach(itemName => {
    const offers = groupedByItem[itemName];
    offers.forEach((offer, idx) => {
      const statusClass = offer.status === 'selected' ? 'color: #38a169; font-weight:bold' : 
                         offer.status === 'rejected' ? 'color: #e53e3e;' : 'color: #718096;';
      const statusText = offer.status === 'selected' ? 'Terpilih' : 
                        offer.status === 'rejected' ? 'Tidak Terpilih' : 'Aktif';
      html += '<tr>';
      html += idx === 0 ? `<td style="border:1px solid #eee;padding:6px;vertical-align:top;" rowspan="${offers.length}"><b>${itemName}</b></td>` : '';
      html += `<td style="border:1px solid #eee;padding:6px;">${offer.supplier_name}</td>`;
      html += `<td style="border:1px solid #eee;padding:6px;">Rp ${Number(offer.price).toLocaleString('id-ID')}</td>`;
      html += `<td style="border:1px solid #eee;padding:6px;${statusClass}">${statusText}</td>`;
      html += `<td style="border:1px solid #eee;padding:6px;">${new Date(offer.created_at).toLocaleString('id-ID')}</td>`;
      html += `<td style="border:1px solid #eee;padding:6px;">${offer.created_by_name}</td>`;
      html += '</tr>';
    });
  });

  html += '</tbody></table></div>';
  return html;
};

const canAddEvidence = computed(() => {
    const user = usePage().props.auth.user;
    return user.status === 'A' && (
        user.id_role === '5af56935b011a' ||
        user.division_id === 20
    );
});

function onEvidenceAdded() {
  // Handle evidence added
  console.log('Evidence added');
}

async function onDeleteTask() {
  const result = await Swal.fire({
    title: 'Hapus Task?',
    text: 'Semua data terkait task ini akan dihapus permanen!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus!',
    cancelButtonText: 'Batal'
  });
  if (result.isConfirmed) {
    isDeleting.value = true;
    try {
      const res = await axios.delete(`/api/maintenance-tasks/${props.task.id}`);
      if (res.data.success) {
        Swal.fire('Berhasil', 'Task berhasil dihapus', 'success');
        emit('taskMoved');
      } else {
        Swal.fire('Gagal', res.data.message || 'Gagal menghapus task', 'error');
      }
    } catch (e) {
      Swal.fire('Error', e.response?.data?.message || 'Gagal menghapus task', 'error');
    } finally {
      isDeleting.value = false;
    }
  }
}
</script>

<style scoped>
.kanban-card {
  user-select: none;
  box-shadow: 0 2px 8px 0 rgba(80, 0, 200, 0.08);
  border-radius: 1rem;
  transition: all 0.2s;
  background: rgba(255,255,255,0.97);
}

.kanban-card:hover {
  box-shadow: 0 8px 24px 0 rgba(80, 0, 200, 0.13);
}

.kanban-card.dragging {
  opacity: 0.5;
  transform: scale(1.02);
  box-shadow: 0 12px 28px 0 rgba(80, 0, 200, 0.2);
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.history-container {
  max-height: 70vh;
  overflow-y: auto;
}

.history-item {
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1rem;
  margin-bottom: 1rem;
}

.history-item h5 {
  color: #4a5568;
  font-size: 1.1rem;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th {
  background-color: #f7fafc;
  font-weight: 600;
  text-align: left;
}

td, th {
  border: 1px solid #e2e8f0;
}

.text-green-600 {
  color: #38a169;
}

.text-red-600 {
  color: #e53e3e;
}

.text-gray-600 {
  color: #718096;
}

.text-lime-600 {
  color: #059669;
}

.text-red-500 {
  color: #ef4444;
}

.font-medium {
  font-weight: 500;
}

.mt-1 {
  margin-top: 0.25rem;
}

.ml-2 {
  margin-left: 0.5rem;
}
</style> 