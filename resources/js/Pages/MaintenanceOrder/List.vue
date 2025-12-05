<template>
  <div class="w-full px-4 sm:px-6 lg:px-8 py-6">
    <!-- Header -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-blue-900">Maintenance Order List</h1>
      <div class="flex gap-3">
        <a href="/maintenance-order" class="btn-secondary">
          <i class="fas fa-columns mr-2"></i> Kanban View
        </a>
        <button @click="exportData" class="btn-primary">
          <i class="fas fa-download mr-2"></i> Export
        </button>
      </div>
    </div>

    <!-- Global Filters -->
    <div class="bg-white rounded-lg shadow p-4 sm:p-6 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-8 gap-4">
        <!-- Outlet Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Outlet</label>
          <select v-model="filters.outlet" @change="onOutletChange" class="w-full border rounded-lg px-3 py-2">
            <option value="">Semua Outlet</option>
            <option v-for="outlet in outlets" :key="outlet.id_outlet" :value="outlet.id_outlet">
              {{ outlet.nama_outlet }}
            </option>
          </select>
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
          <select v-model="filters.status" @change="applyFilters" class="w-full border rounded-lg px-3 py-2">
            <option value="">Semua Status</option>
            <option value="TASK">To Do</option>
            <option value="PR">Purchase Requisition</option>
            <option value="PO">Purchase Order</option>
            <option value="IN_PROGRESS">In Progress</option>
            <option value="IN_REVIEW">In Review</option>
            <option value="DONE">Done</option>
          </select>
        </div>

        <!-- Search -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
          <input 
            v-model="filters.search" 
            @input="applyFilters"
            type="text" 
            placeholder="Search tasks..."
            class="w-full border rounded-lg px-3 py-2"
          >
        </div>

        <!-- Actions -->
        <div class="flex items-end gap-2">
          <button @click="clearFilters" class="px-4 py-2 text-gray-600 hover:text-gray-800">
            Clear
          </button>
          <button @click="applyFilters" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
            Apply
          </button>
        </div>
      </div>
    </div>

    <!-- Status Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
      <div v-for="status in statusSummary" :key="status.code" 
           class="bg-white rounded-lg shadow p-4 text-center cursor-pointer hover:shadow-lg transition-shadow"
           @click="filterByStatus(status.code)">
        <div class="text-2xl font-bold" :class="status.color">{{ status.count }}</div>
        <div class="text-sm text-gray-600">{{ status.label }}</div>
      </div>
    </div>

    <!-- Tasks Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <div class="overflow-x-auto min-w-full">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">
                Task Info
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                Status
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                Priority
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-48">
                Location
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                Due Date
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">
                Members
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                Comments
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                Media
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="task in filteredTasks" :key="task.id" class="hover:bg-gray-50">
              <!-- Task Info -->
              <td class="px-6 py-4">
                <div class="flex items-start">
                  <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                      <i class="fas fa-tools text-blue-600"></i>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900">
                      {{ task.title }}
                    </div>
                    <div class="text-sm text-gray-500">
                      {{ task.task_number }}
                    </div>
                    <div class="text-sm text-gray-500 mt-1">
                      {{ task.description }}
                    </div>
                  </div>
                </div>
              </td>

              <!-- Status with Dropdown -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="relative">
                  <button @click="showStatusDropdown(task.id)" 
                          class="flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium hover:bg-gray-100"
                          :class="getStatusBadgeClass(task.status)">
                    {{ getStatusLabel(task.status) }}
                    <i class="fas fa-chevron-down text-xs"></i>
                  </button>
                  
                  <!-- Status Dropdown -->
                  <div v-if="activeStatusDropdown === task.id" 
                       class="absolute z-10 mt-1 w-48 bg-white rounded-md shadow-lg border">
                    <div class="py-1">
                      <button v-for="status in availableStatuses" :key="status.value"
                              @click="changeTaskStatus(task, status.value)"
                              class="w-full text-left px-4 py-2 text-sm hover:bg-gray-100 flex items-center gap-2"
                              :class="getStatusBadgeClass(status.value)">
                        {{ status.label }}
                      </button>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Priority -->
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getPriorityBadgeClass(task.priority_name)" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full shadow-sm">
                  <span class="w-2 h-2 rounded-full mr-2" :class="getPriorityDotClass(task.priority_name)"></span>
                  {{ task.priority_name }}
                </span>
              </td>

              <!-- Location -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">{{ getOutletName(task.id_outlet) }}</div>
                <div v-if="task.id_ruko" class="text-sm text-gray-500">{{ getRukoName(task.id_ruko) }}</div>
              </td>

                             <!-- Due Date -->
               <td class="px-6 py-4 whitespace-nowrap">
                 <div class="text-sm font-medium" :class="getDueDateColor(task.due_date)">
                   {{ formatDate(task.due_date) }}
                 </div>
                 <div v-if="isOverdue(task.due_date)" class="text-sm text-red-600 font-medium">
                   Overdue
                 </div>
                 <div v-else-if="isDueSoon(task.due_date)" class="text-sm text-orange-600 font-medium">
                   Due Soon
                 </div>
               </td>

              <!-- Members -->
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex flex-wrap gap-1">
                  <!-- Creator -->
                  <div v-if="task.created_by_name" 
                       class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800 border border-purple-200">
                    <i class="fas fa-crown mr-1 text-purple-600"></i>
                    {{ task.created_by_name }}
                    <span class="ml-1 text-xs text-purple-600">(Creator)</span>
                  </div>
                  
                                     <!-- Assigned Members (exclude creator) -->
                   <div v-for="member in getAssignedMembersOnly(task)" :key="member.id" 
                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200">
                     <i class="fas fa-user mr-1 text-blue-600"></i>
                     {{ member.nama_lengkap }}
                     <span v-if="member.role" class="ml-1 text-xs text-blue-600 capitalize">({{ member.role }})</span>
                   </div>
                  
                                     <!-- No Members Message -->
                   <div v-if="getAssignedMembersOnly(task).length === 0 && !task.created_by_name" class="text-gray-400 text-xs">
                     No members assigned
                   </div>
                </div>
              </td>

              <!-- Comments -->
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <div class="relative group">
                  <button 
                    @click="openCommentModalDirect(task)"
                    @keydown.enter="openCommentModalDirect(task)"
                    @keydown.space="openCommentModalDirect(task)"
                    class="comment-button flex items-center gap-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-3 py-2 rounded-lg transition-all duration-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 relative"
                    :title="`View/Add Comments for task ${task.task_number}${task.comment_count > 0 ? ` (${task.comment_count} comments)` : ''}`"
                    :disabled="task.loading_comments"
                    :aria-label="`View or add comments for task ${task.task_number}. Currently has ${task.comment_count || 0} comments.`"
                    role="button"
                    tabindex="0"
                  >
                    <i v-if="task.loading_comments" class="fas fa-spinner fa-spin text-blue-600" aria-hidden="true"></i>
                    <i v-else class="far fa-comment text-lg" aria-hidden="true"></i>
                    <span class="font-medium">{{ task.comment_count || 0 }}</span>
                    <span class="text-xs text-blue-500">{{ task.comment_count === 1 ? 'comment' : 'comments' }}</span>
                    
                    <!-- New comment indicator -->
                    <span v-if="task.has_new_comments" 
                          class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full animate-pulse"
                          title="New comments available"
                          aria-label="New comments available">
                    </span>
                  </button>
                  
                  <!-- Quick comment preview tooltip -->
                  <div v-if="task.comment_count > 0 && task.latest_comment" 
                       class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10 max-w-xs">
                    <div class="font-medium mb-1">Latest comment:</div>
                    <div class="truncate">{{ task.latest_comment }}</div>
                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                  </div>
                </div>
              </td>

              <!-- Media -->
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <div class="relative group">
                  <button 
                    @click="openMediaModal(task)"
                    @keydown.enter="openMediaModal(task)"
                    @keydown.space="openMediaModal(task)"
                    class="media-button flex items-center gap-2 text-blue-600 hover:text-blue-800 hover:bg-blue-50 px-3 py-2 rounded-lg transition-all duration-200 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 relative"
                    :title="`View Media for task ${task.task_number}${task.media_count > 0 ? ` (${task.media_count} media)` : ''}`"
                    :disabled="task.loading_media"
                    :aria-label="`View or add media for task ${task.task_number}. Currently has ${task.media_count || 0} media.`"
                    role="button"
                    tabindex="0"
                  >
                    <i v-if="task.loading_media" class="fas fa-spinner fa-spin text-blue-600" aria-hidden="true"></i>
                    <i v-else class="far fa-images text-lg" aria-hidden="true"></i>
                    <span class="font-medium">{{ task.media_count || 0 }}</span>
                    <span class="text-xs text-blue-500">{{ (task.media_count || 0) === 1 ? 'media' : 'media' }}</span>
                    
                    <!-- New media indicator -->
                    <span v-if="task.has_new_media" 
                          class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full animate-pulse"
                          title="New media available"
                          aria-label="New media available">
                    </span>
                  </button>
                  
                  <!-- Quick media preview tooltip -->
                  <div v-if="task.media_count > 0 && task.latest_media" 
                       class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10 max-w-xs">
                    <div class="font-medium mb-1">Latest media:</div>
                    <div class="truncate">{{ task.latest_media }}</div>
                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                  </div>
                </div>
              </td>

              <!-- Actions -->
              <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <div class="flex gap-2">
                  <button @click="viewTask(task)" class="text-blue-600 hover:text-blue-900" title="View Task">
                    <i class="fas fa-eye"></i>
                  </button>
                  <button @click="editTask(task)" class="text-green-600 hover:text-green-900" title="Edit Task">
                    <i class="fas fa-edit"></i>
                  </button>
                  <button @click="showTaskMenu(task)" class="text-gray-600 hover:text-gray-900" title="More Actions">
                    <i class="fas fa-ellipsis-v"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Task Action Menu Modal -->
    <div v-if="showTaskMenuModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Task Actions</h3>
          <div class="space-y-2">
            <button @click="openCommentModal" class="w-full text-left px-4 py-2 hover:bg-blue-50 text-gray-700 flex items-center gap-2">
              <i class="fas fa-comment"></i> Add Comment
              <span v-if="selectedTask?.comment_count > 0" class="ml-auto bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                {{ selectedTask.comment_count }}
              </span>
            </button>
            <button @click="openAssignMemberModal" class="w-full text-left px-4 py-2 hover:bg-blue-50 text-gray-700 flex items-center gap-2">
              <i class="fas fa-user-plus"></i> Assign Member
            </button>
            <button @click="openActionPlanModal" class="w-full text-left px-4 py-2 hover:bg-blue-50 text-gray-700 flex items-center gap-2">
              <i class="fas fa-tasks"></i> Action Plan
            </button>
            <button @click="openRetailModal" class="w-full text-left px-4 py-2 hover:bg-blue-50 text-gray-700 flex items-center gap-2">
              <i class="fas fa-shopping-cart"></i> Retail Items
            </button>
            <button @click="openTimelineModal" class="w-full text-left px-4 py-2 hover:bg-blue-50 text-gray-700 flex items-center gap-2">
              <i class="fas fa-stream"></i> Timeline
            </button>
          </div>
          <div class="mt-4 flex justify-end">
            <button @click="closeTaskMenu" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
              Close
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Status Change Confirmation Modal -->
    <div v-if="showStatusModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Change Task Status</h3>
          <p class="text-sm text-gray-600 mb-4">
            Are you sure you want to change the status of task <strong>{{ selectedTask?.task_number }}</strong> 
            from <strong>{{ getStatusLabel(selectedTask?.status) }}</strong> to <strong>{{ getStatusLabel(pendingStatusChange) }}</strong>?
          </p>
          <div class="flex justify-end gap-2">
            <button @click="cancelStatusChange" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
              Cancel
            </button>
            <button @click="confirmStatusChange" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
              Confirm
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Assign Member Modal -->
    <div v-if="showAssignMemberModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Assign Members</h3>
          
                     <!-- Current Members -->
           <div v-if="currentTask && (getAssignedMembersOnly(currentTask).length > 0 || currentTask.created_by_name)" class="mb-4">
             <h4 class="text-sm font-medium text-gray-700 mb-2">Current Members:</h4>
             <div class="space-y-2">
               <!-- Creator -->
               <div v-if="currentTask.created_by_name" 
                    class="flex items-center justify-between p-2 bg-purple-50 rounded-md border border-purple-200">
                 <div class="flex items-center gap-2">
                   <i class="fas fa-crown text-purple-600"></i>
                   <span class="text-sm text-gray-900 font-medium">{{ currentTask.created_by_name }}</span>
                   <span class="text-xs text-purple-600 font-medium">(Creator)</span>
                 </div>
                 <span class="text-xs text-purple-600 bg-purple-100 px-2 py-1 rounded-full">Cannot Remove</span>
               </div>
               
                               <!-- Assigned Members (exclude creator) -->
                <div v-for="member in getAssignedMembersOnly(currentTask)" :key="member.id" 
                     class="flex items-center justify-between p-2 bg-blue-50 rounded-md border border-blue-200">
                  <div class="flex items-center gap-2">
                    <i class="fas fa-user text-blue-600"></i>
                    <span class="text-sm text-gray-900">{{ member.nama_lengkap }}</span>
                    <span class="text-xs text-blue-600 capitalize">({{ member.role }})</span>
                  </div>
                  <button @click="removeMember(member.id)" 
                          class="text-xs text-red-600 hover:text-red-800 hover:bg-red-50 px-2 py-1 rounded-full transition-colors">
                    Remove
                  </button>
                </div>
             </div>
           </div>

          <!-- Assign New Members -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Select Members:</label>
            <div class="max-h-40 overflow-y-auto border border-gray-300 rounded-md">
              <div v-for="user in assignableUsers" :key="user.id" 
                   class="flex items-center p-2 hover:bg-gray-50 border-b border-gray-100 last:border-b-0">
                <input 
                  type="checkbox" 
                  :id="'user-' + user.id"
                  :value="user.id"
                  v-model="selectedUserIds"
                  class="mr-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                >
                <label :for="'user-' + user.id" class="text-sm text-gray-900 cursor-pointer">
                  {{ user.nama_lengkap }}
                  <span class="text-xs text-gray-500 block">{{ user.email }}</span>
                </label>
              </div>
            </div>
          </div>

          <!-- Loading State -->
          <div v-if="assigningMembers" class="text-center py-2">
            <i class="fas fa-spinner fa-spin text-blue-600"></i>
            <span class="ml-2 text-sm text-gray-600">Assigning members...</span>
          </div>

          <!-- Error Message -->
          <div v-if="assignMemberError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
            <p class="text-sm text-red-700">{{ assignMemberError }}</p>
          </div>

          <div class="mt-6 flex justify-end gap-3">
            <button @click="closeAssignMemberModal" class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">
              Cancel
            </button>
            <button @click="assignMembers" :disabled="assigningMembers || selectedUserIds.length === 0" 
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
              <i v-if="assigningMembers" class="fas fa-spinner fa-spin mr-2"></i>
              {{ assigningMembers ? 'Assigning...' : 'Assign Members' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Comment Modal -->
    <CommentModal 
      v-if="canShowCommentModal"
      :task-id="commentModalTaskId"
      @close="closeCommentModal"
      @comment-added="onCommentAdded"
    />

    <!-- Retail Modal -->
    <div v-if="showRetailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
      <div class="relative top-10 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium text-gray-900">Retail Management</h3>
          <button @click="closeRetailModal" class="text-gray-400 hover:text-gray-500">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <div class="mb-4">
          <button @click="showAddRetailForm = true" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            <i class="fas fa-plus mr-2"></i> Add Retail Item
          </button>
        </div>

        <!-- Add Retail Form -->
        <div v-if="showAddRetailForm" class="mb-6 p-4 border rounded-lg bg-gray-50">
          <AddRetailItem 
            :task-id="selectedTask?.id"
            @saved="onRetailSaved"
          />
        </div>

        <!-- Retail List -->
        <div v-if="!showAddRetailForm">
          <RetailList 
            ref="retailList"
            :task-id="selectedTask?.id" 
          />
        </div>
      </div>
    </div>

    <!-- Media Modal with Lightbox -->
    <div v-if="showMediaModal" class="fixed inset-0 bg-gray-900 bg-opacity-95 overflow-y-auto h-full w-full z-[9999]">
      <div class="relative min-h-full flex items-center justify-center p-4">
        <!-- Close Button -->
        <button @click="closeMediaModal" class="absolute top-4 right-4 text-white hover:text-gray-300 text-2xl z-10">
          <i class="fas fa-times"></i>
        </button>
        
        <!-- Navigation Arrows -->
        <button v-if="currentMediaIndex > 0" @click="previousMedia" class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 text-3xl z-10">
          <i class="fas fa-chevron-left"></i>
        </button>
        <button v-if="currentMediaIndex < (taskMedia.length - 1)" @click="nextMedia" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 text-3xl z-10">
          <i class="fas fa-chevron-right"></i>
        </button>
        
        <!-- Media Content -->
        <div class="max-w-4xl max-h-full mx-auto">
          <!-- Image -->
          <img v-if="currentMedia && currentMedia.mediaType === 'image'" 
               :src="currentMedia.url" 
               :alt="currentMedia.filename || 'Image'"
               class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl"
               @click="nextMedia" />
          
          <!-- Video -->
          <video v-else-if="currentMedia && currentMedia.mediaType === 'video'" 
                 :src="currentMedia.url" 
                 controls
                 class="max-w-full max-h-[80vh] rounded-lg shadow-2xl"
                 @click.stop />
          
          <!-- Media Info -->
          <div class="text-center mt-4 text-white">
            <p class="text-lg font-medium">{{ currentMedia?.filename || 'Media' }}</p>
            <p class="text-sm text-gray-300">{{ currentMediaIndex + 1 }} of {{ taskMedia.length }}</p>
          </div>
        </div>
        
        <!-- Thumbnail Navigation -->
        <div v-if="taskMedia.length > 1" class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex gap-2 max-w-4xl overflow-x-auto px-4">
          <button v-for="(media, index) in taskMedia" 
                  :key="index"
                  @click="goToMedia(index)"
                  class="flex-shrink-0 w-20 h-20 rounded-lg overflow-hidden border-2 transition-all hover:scale-110"
                  :class="index === currentMediaIndex ? 'border-blue-400 scale-110 shadow-lg' : 'border-gray-600 hover:border-gray-400'">
            
            <!-- Image Thumbnail -->
            <img v-if="media.mediaType === 'image'" 
                 :src="media.url" 
                 :alt="media.filename"
                 class="w-full h-full object-cover" />
            
            <!-- Video Thumbnail -->
            <div v-else-if="media.mediaType === 'video'" class="w-full h-full bg-gray-800 flex items-center justify-center relative">
              <i class="fas fa-play text-white text-xl"></i>
              <div class="absolute bottom-1 right-1 bg-black bg-opacity-70 text-white text-xs px-1 rounded">
                <i class="fas fa-video mr-1"></i>
              </div>
            </div>
            
            <!-- Document Thumbnail -->
            <div v-else class="w-full h-full bg-gray-700 flex items-center justify-center">
              <i class="fas fa-file-alt text-white text-xl"></i>
              <div class="absolute bottom-1 right-1 bg-black bg-opacity-70 text-white text-xs px-1 rounded">
                <i class="fas fa-file mr-1"></i>
              </div>
            </div>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import Swal from 'sweetalert2';
import CommentModal from './CommentModal.vue';
import AddRetailItem from './AddRetailItem.vue';
import RetailList from './RetailList.vue';

// Reactive data
const tasks = ref([]);
const outlets = ref([]);
const rukos = ref([]);
const activeStatusDropdown = ref(null);
const showTaskMenuModal = ref(false);
const showStatusModal = ref(false);
const showAssignMemberModal = ref(false);
const showCommentModal = ref(false);
const selectedTask = ref(null);
const pendingStatusChange = ref('');
const currentTask = ref(null);
const assignableUsers = ref([]);
const selectedUserIds = ref([]);
const assigningMembers = ref(false);
const assignMemberError = ref('');
const commentLoading = ref(false);

// Retail modal state
const showRetailModal = ref(false);
const showAddRetailForm = ref(false);
const retailList = ref(null);

// Media modal state
const showMediaModal = ref(false);
const taskMedia = ref([]); // Array of media objects for the current task
const currentMediaIndex = ref(0); // Index of the currently displayed media
const currentMedia = ref(null); // The media object currently being displayed

// Filters
const filters = ref({
  outlet: '',
  status: '',
  search: ''
});

// Computed properties
const page = usePage();
const userOutlet = computed(() => page.props.auth?.user?.id_outlet || '');

const filteredTasks = computed(() => {
  let filtered = [...tasks.value];

  if (filters.value.outlet) {
    filtered = filtered.filter(task => task.id_outlet == filters.value.outlet);
  }

  if (filters.value.status) {
    filtered = filtered.filter(task => task.status === filters.value.status);
  }

  if (filters.value.search) {
    const search = filters.value.search.toLowerCase();
    filtered = filtered.filter(task => 
      task.title.toLowerCase().includes(search) ||
      task.description.toLowerCase().includes(search) ||
      task.task_number.toLowerCase().includes(search)
    );
  }

  return filtered;
});

const statusSummary = computed(() => {
  const summary = [
    { code: 'TASK', label: 'To Do', count: 0, color: 'text-gray-600' },
    { code: 'PR', label: 'PR', count: 0, color: 'text-yellow-600' },
    { code: 'PO', label: 'PO', count: 0, color: 'text-blue-600' },
    { code: 'IN_PROGRESS', label: 'In Progress', count: 0, color: 'text-purple-600' },
    { code: 'IN_REVIEW', label: 'In Review', count: 0, color: 'text-orange-600' },
    { code: 'DONE', label: 'Done', count: 0, color: 'text-green-600' }
  ];

  tasks.value.forEach(task => {
    const status = summary.find(s => s.code === task.status);
    if (status) status.count++;
  });

  return summary;
});

const availableStatuses = [
  { value: 'TASK', label: 'To Do' },
  { value: 'PR', label: 'Purchase Requisition' },
  { value: 'PO', label: 'Purchase Order' },
  { value: 'IN_PROGRESS', label: 'In Progress' },
  { value: 'IN_REVIEW', label: 'In Review' },
  { value: 'DONE', label: 'Done' }
];

  const canShowCommentModal = computed(() => {
    return showCommentModal.value && selectedTask.value && selectedTask.value.id;
  });

  const commentModalTaskId = computed(() => {
    return selectedTask.value?.id;
  });

// Methods
async function fetchData() {
  try {
    const [outletsRes, tasksRes] = await Promise.all([
      axios.get('/api/outlet/active'),
      axios.get('/api/maintenance-order-list')
    ]);

    outlets.value = outletsRes.data;
    tasks.value = tasksRes.data;

    // Fetch comment count for each task
    await fetchCommentCounts();

    // Fetch media count for each task
    await fetchMediaCounts();

    if (userOutlet.value && userOutlet.value != 1) {
      filters.value.outlet = userOutlet.value;
    }
  } catch (error) {
    console.error('Error fetching data:', error);
  }
}

async function fetchCommentCounts() {
  try {
    const commentCountPromises = tasks.value.map(async (task) => {
      try {
        const response = await axios.get(`/api/maintenance-comments/${task.id}/count`);
        return { taskId: task.id, count: response.data.count };
      } catch (error) {
        console.error(`Error fetching comment count for task ${task.id}:`, error);
        return { taskId: task.id, count: 0 };
      }
    });

    const commentCounts = await Promise.all(commentCountPromises);
    
    // Update tasks with comment counts
    commentCounts.forEach(({ taskId, count }) => {
      const taskIndex = tasks.value.findIndex(t => t.id === taskId);
      if (taskIndex !== -1) {
        tasks.value[taskIndex].comment_count = count;
      }
    });
  } catch (error) {
    console.error('Error fetching comment counts:', error);
  }
}

async function fetchMediaCounts() {
  try {
    const mediaCountPromises = tasks.value.map(async (task) => {
      try {
        const response = await axios.get(`/api/maintenance-order/${task.id}/media`);
        return { taskId: task.id, count: response.data.success ? response.data.data.length : 0 };
      } catch (error) {
        console.error(`Error fetching media count for task ${task.id}:`, error);
        return { taskId: task.id, count: 0 };
      }
    });

    const mediaCounts = await Promise.all(mediaCountPromises);
    
    // Update tasks with media counts
    mediaCounts.forEach(({ taskId, count }) => {
      const taskIndex = tasks.value.findIndex(t => t.id === taskId);
      if (taskIndex !== -1) {
        tasks.value[taskIndex].media_count = count;
      }
    });
  } catch (error) {
    console.error('Error fetching media counts:', error);
  }
}

function onOutletChange() {
  applyFilters();
}

function applyFilters() {
  // Filter logic is handled by computed property
}

function clearFilters() {
  filters.value = {
    outlet: userOutlet.value && userOutlet.value != 1 ? userOutlet.value : '',
    status: '',
    search: ''
  };
}

function filterByStatus(statusCode) {
  filters.value.status = statusCode;
}

function showStatusDropdown(taskId) {
  activeStatusDropdown.value = activeStatusDropdown.value === taskId ? null : taskId;
}

function changeTaskStatus(task, newStatus) {
  // Validasi status transition
  if (!canChangeStatus(task.status, newStatus)) {
    Swal.fire('Invalid Status Change', 'This status transition is not allowed.', 'warning');
    return;
  }

  // Validasi khusus untuk status tertentu
  if (newStatus === 'DONE') {
    // Cek apakah ada evidence
    if (!task.evidence || task.evidence.length === 0) {
      Swal.fire('Cannot Complete Task', 'Please upload evidence of completed work first.', 'warning');
      return;
    }
  }

  selectedTask.value = task;
  pendingStatusChange.value = newStatus;
  showStatusModal.value = true;
  activeStatusDropdown.value = null;
}

function canChangeStatus(currentStatus, newStatus) {
  const allowedTransitions = {
    'TASK': ['PR', 'IN_PROGRESS'],
    'PR': ['PO', 'TASK'],
    'PO': ['IN_PROGRESS', 'PR'],
    'IN_PROGRESS': ['IN_REVIEW', 'PO'],
    'IN_REVIEW': ['DONE', 'IN_PROGRESS'],
    'DONE': ['IN_REVIEW']
  };

  return allowedTransitions[currentStatus]?.includes(newStatus) || false;
}

async function confirmStatusChange() {
  try {
    const response = await axios.patch(`/api/maintenance-order/${selectedTask.value.id}`, {
      status: pendingStatusChange.value
    });

    if (response.data.success) {
      // Update local task status
      const taskIndex = tasks.value.findIndex(t => t.id === selectedTask.value.id);
      if (taskIndex !== -1) {
        tasks.value[taskIndex].status = pendingStatusChange.value;
      }

      Swal.fire('Success', 'Task status updated successfully!', 'success');
    } else {
      throw new Error(response.data.error || 'Failed to update status');
    }
  } catch (error) {
    console.error('Error updating status:', error);
    Swal.fire('Error', 'Failed to update task status', 'error');
  } finally {
    showStatusModal.value = false;
    selectedTask.value = null;
    pendingStatusChange.value = '';
  }
}

function cancelStatusChange() {
  showStatusModal.value = false;
  selectedTask.value = null;
  pendingStatusChange.value = '';
}

function showTaskMenu(task) {
  selectedTask.value = task;
  showTaskMenuModal.value = true;
}

function closeTaskMenu() {
  showTaskMenuModal.value = false;
  selectedTask.value = null;
}

// Placeholder functions for future implementation
function openCommentModal() {
  // Pastikan selectedTask sudah diatur sebelum membuka modal
  if (!selectedTask.value) {
    console.error('No task selected for comment modal');
    Swal.fire({
      title: 'Error',
      text: 'No task selected. Please try again.',
      icon: 'error'
    });
    return;
  }
  
  showCommentModal.value = true;
  closeTaskMenu();
}

async function openCommentModalDirect(task) {
  // Validasi task
  if (!task || !task.id) {
    console.error('Invalid task for comment modal:', task);
    Swal.fire({
      title: 'Error',
      text: 'Invalid task selected. Please try again.',
      icon: 'error'
    });
    return;
  }
  
  // Set selectedTask first
  selectedTask.value = task;
  
  // Wait for next tick to ensure reactivity
  await nextTick();
  
  // Now set showCommentModal
  showCommentModal.value = true;
  
  // Wait for next tick again
  await nextTick();
}

function closeCommentModal() {
  showCommentModal.value = false;
  selectedTask.value = null;
}

function onCommentAdded() {
  // Refresh comment count for the current task
  if (selectedTask.value) {
    const taskIndex = tasks.value.findIndex(t => t.id === selectedTask.value.id);
    if (taskIndex !== -1) {
      // Fetch updated comment count
      axios.get(`/api/maintenance-comments/${selectedTask.value.id}/count`)
        .then(response => {
          tasks.value[taskIndex].comment_count = response.data.count;
          
          // Show success notification
          Swal.fire({
            title: 'Success!',
            text: 'Comment added successfully!',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
          });
        })
        .catch(error => {
          console.error('Error refreshing comment count:', error);
          handleCommentError(error, selectedTask.value.id);
        });
    }
  }
}

// Function to refresh comment count for a specific task
async function refreshCommentCount(taskId) {
  try {
    const response = await axios.get(`/api/maintenance-comments/${taskId}/count`);
    const taskIndex = tasks.value.findIndex(t => t.id === taskId);
    if (taskIndex !== -1) {
      tasks.value[taskIndex].comment_count = response.data.count;
    }
  } catch (error) {
    console.error('Error refreshing comment count:', error);
    // Show user-friendly error message
    Swal.fire({
      title: 'Error',
      text: 'Failed to refresh comment count. Please try again.',
      icon: 'warning',
      confirmButtonText: 'OK'
    });
  }
}

// Function to handle comment errors
function handleCommentError(error, taskId) {
  console.error('Comment error:', error);
  
  let errorMessage = 'An error occurred while processing your comment.';
  
  if (error.response?.status === 413) {
    errorMessage = 'File size is too large. Please use smaller files.';
  } else if (error.response?.status === 422) {
    errorMessage = 'Invalid comment data. Please check your input.';
  } else if (error.response?.status === 500) {
    errorMessage = 'Server error. Please try again later.';
  }
  
  Swal.fire({
    title: 'Error',
    text: errorMessage,
    icon: 'error',
    confirmButtonText: 'OK'
  });
}

async function openAssignMemberModal() {
  try {
    currentTask.value = selectedTask.value;
    selectedUserIds.value = [];
    assignMemberError.value = '';
    
    // Load assignable users
    const response = await axios.get('/api/maintenance-order/assignable-users');
    assignableUsers.value = response.data;
    
    // Pre-select current assignees
    if (currentTask.value.members) {
      const currentAssigneeIds = currentTask.value.members
        .filter(member => member.role === 'ASSIGNEE')
        .map(member => member.id);
      selectedUserIds.value = currentAssigneeIds;
    }
    
    showAssignMemberModal.value = true;
    closeTaskMenu();
  } catch (error) {
    console.error('Error loading assignable users:', error);
    Swal.fire('Error', 'Failed to load assignable users', 'error');
  }
}

function closeAssignMemberModal() {
  showAssignMemberModal.value = false;
  currentTask.value = null;
  assignableUsers.value = [];
  selectedUserIds.value = [];
  assignMemberError.value = '';
}

async function assignMembers() {
  if (!currentTask.value || selectedUserIds.value.length === 0) {
    assignMemberError.value = 'Please select at least one member to assign';
    return;
  }

  try {
    assigningMembers.value = true;
    assignMemberError.value = '';

    const response = await axios.post(`/api/maintenance-order/${currentTask.value.id}/assign-members`, {
      task_id: currentTask.value.id,
      user_ids: selectedUserIds.value
    });

    if (response.data.success) {
      // Update local task data
      const taskIndex = tasks.value.findIndex(t => t.id === currentTask.value.id);
      if (taskIndex !== -1) {
        // Reload task data to get updated members
        const taskResponse = await axios.get(`/api/maintenance-order/${currentTask.value.id}`);
        tasks.value[taskIndex] = taskResponse.data;
        // Update currentTask untuk modal
        currentTask.value = taskResponse.data;
      }

      Swal.fire('Success', 'Members assigned successfully!', 'success');
      // Refresh assignable users dan selectedUserIds
      selectedUserIds.value = [];
    } else {
      throw new Error(response.data.error || 'Failed to assign members');
    }
  } catch (error) {
    console.error('Error assigning members:', error);
    assignMemberError.value = error.response?.data?.error || 'Failed to assign members';
  } finally {
    assigningMembers.value = false;
  }
}

async function removeMember(memberId) {
  try {
    const response = await axios.delete(`/api/maintenance-order/${currentTask.value.id}/remove-member/${memberId}`);
    
    if (response.data.success) {
      // Update local task data
      const taskIndex = tasks.value.findIndex(t => t.id === currentTask.value.id);
      if (taskIndex !== -1) {
        // Reload task data to get updated members
        const taskResponse = await axios.get(`/api/maintenance-order/${currentTask.value.id}`);
        tasks.value[taskIndex] = taskResponse.data;
        // Update currentTask untuk modal
        currentTask.value = taskResponse.data;
      }
      
      Swal.fire('Success', 'Member removed successfully!', 'success');
    } else {
      throw new Error(response.data.error || 'Failed to remove member');
    }
  } catch (error) {
    console.error('Error removing member:', error);
    Swal.fire('Error', 'Failed to remove member', 'error');
  }
}

function openActionPlanModal() {
  console.log('Open action plan modal for task:', selectedTask.value?.id);
  closeTaskMenu();
}

function openRetailModal() {
  console.log('Open retail modal for task:', selectedTask.value?.id);
  showRetailModal.value = true;
  showAddRetailForm.value = false;
  closeTaskMenu();
}

function closeRetailModal() {
  showRetailModal.value = false;
  showAddRetailForm.value = false;
}

function onRetailSaved() {
  showAddRetailForm.value = false;
  if (retailList.value?.loadRetailData) {
    retailList.value.loadRetailData();
  }
  Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: 'Data retail berhasil disimpan',
    timer: 1500,
    showConfirmButton: false
  });
}

function openTimelineModal() {
  console.log('Open timeline modal for task:', selectedTask.value?.id);
  closeTaskMenu();
}

function getStatusLabel(status) {
  const labels = {
    'TASK': 'To Do',
    'PR': 'Purchase Requisition',
    'PO': 'Purchase Order',
    'IN_PROGRESS': 'In Progress',
    'IN_REVIEW': 'In Review',
    'DONE': 'Done'
  };
  return labels[status] || status;
}

function getStatusBadgeClass(status) {
  const classes = {
    'TASK': 'bg-gray-100 text-gray-800',
    'PR': 'bg-yellow-100 text-yellow-800',
    'PO': 'bg-blue-100 text-blue-800',
    'IN_PROGRESS': 'bg-purple-100 text-purple-800',
    'IN_REVIEW': 'bg-orange-100 text-orange-800',
    'DONE': 'bg-green-100 text-green-800'
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
}

function getPriorityBadgeClass(priority) {
  const classes = {
    'Low': 'bg-green-100 text-green-800 border border-green-200',
    'Medium': 'bg-yellow-100 text-yellow-800 border border-yellow-200',
    'High': 'bg-red-100 text-red-800 border border-red-200',
    'Critical': 'bg-purple-100 text-purple-800 border border-purple-200',
    'IMPORTANT VS URGENT': 'bg-red-100 text-red-800 border border-red-200',
    'IMPORTANT VS NOT URGENT': 'bg-yellow-100 text-yellow-800 border border-yellow-200',
    'NOT IMPORTANT VS URGENT': 'bg-blue-100 text-blue-800 border border-blue-200',
    'NOT IMPORTANT VS NOT URGENT': 'bg-gray-100 text-gray-800 border border-gray-200'
  };
  return classes[priority] || 'bg-gray-100 text-gray-800 border border-gray-200';
}

function getPriorityDotClass(priority) {
  const classes = {
    'Low': 'bg-green-500',
    'Medium': 'bg-yellow-500',
    'High': 'bg-red-500',
    'Critical': 'bg-purple-500',
    'IMPORTANT VS URGENT': 'bg-red-500',
    'IMPORTANT VS NOT URGENT': 'bg-yellow-500',
    'NOT IMPORTANT VS URGENT': 'bg-blue-500',
    'NOT IMPORTANT VS NOT URGENT': 'bg-gray-500'
  };
  return classes[priority] || 'bg-gray-500';
}

function getOutletName(outletId) {
  const outlet = outlets.value.find(o => o.id_outlet == outletId);
  return outlet ? outlet.nama_outlet : 'Unknown';
}

function getRukoName(rukoId) {
  const ruko = rukos.value.find(r => r.id_ruko == rukoId);
  return ruko ? ruko.nama_ruko : '';
}

// Filter members untuk exclude creator
function getAssignedMembersOnly(task) {
  if (!task.members || !task.created_by) return task.members || [];
  
  return task.members.filter(member => member.id != task.created_by);
}

// Warna due date berdasarkan kedekatan
function getDueDateColor(dueDate) {
  if (!dueDate) return 'text-gray-500';
  
  const today = new Date();
  const due = new Date(dueDate);
  const diffTime = due - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  if (diffDays < 0) {
    // Overdue - Merah gelap
    return 'text-red-700';
  } else if (diffDays === 0) {
    // Due today - Merah terang
    return 'text-red-600';
  } else if (diffDays <= 1) {
    // Due tomorrow - Merah muda
    return 'text-red-500';
  } else if (diffDays <= 3) {
    // Due dalam 3 hari - Orange
    return 'text-orange-600';
  } else if (diffDays <= 7) {
    // Due dalam seminggu - Kuning
    return 'text-yellow-600';
  } else if (diffDays <= 14) {
    // Due dalam 2 minggu - Biru
    return 'text-blue-600';
  } else {
    // Masih lama - Hijau
    return 'text-green-600';
  }
}

function formatDate(date) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString('id-ID');
}

function isOverdue(date) {
  if (!date) return false;
  return new Date(date) < new Date();
}

function isDueSoon(date) {
  if (!date) return false;
  const today = new Date();
  const due = new Date(date);
  const diffTime = due - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  // Due dalam 3 hari ke depan (tidak termasuk overdue)
  return diffDays >= 0 && diffDays <= 3;
}

function viewTask(task) {
  // Navigate to task detail
  window.location.href = `/maintenance-order/${task.id}`;
}

function editTask(task) {
  // Navigate to edit page
  window.location.href = `/maintenance-order/${task.id}/edit`;
}

function exportData() {
  // Implement export functionality
  console.log('Export data');
}

// Close dropdowns when clicking outside
function handleClickOutside(event) {
  if (!event.target.closest('.relative')) {
    activeStatusDropdown.value = null;
  }
}

// Keyboard shortcuts for comment modal
function handleKeydown(event) {
  // Ctrl/Cmd + Enter to open comment modal for selected task
  if ((event.ctrlKey || event.metaKey) && event.key === 'Enter' && selectedTask.value) {
    event.preventDefault();
    openCommentModal();
  }
  
  // Escape to close comment modal
  if (event.key === 'Escape' && showCommentModal.value) {
    closeCommentModal();
  }
  
  // Escape to close media modal
  if (event.key === 'Escape' && showMediaModal.value) {
    closeMediaModal();
  }
  
  // Arrow keys for media navigation
  if (showMediaModal.value && taskMedia.value.length > 1) {
    if (event.key === 'ArrowRight') {
      event.preventDefault();
      nextMedia();
    } else if (event.key === 'ArrowLeft') {
      event.preventDefault();
      previousMedia();
    }
  }
}

// Media modal methods
async function openMediaModal(task) {
  console.log('openMediaModal called with task:', task);
  
  if (!task || !task.id) {
    console.error('Invalid task for media modal:', task);
    return;
  }
  
  try {
    selectedTask.value = task;
    showMediaModal.value = true;
    console.log('Modal opened, fetching media for task:', task.id);
    
    // Fetch media data for this task
    const response = await axios.get(`/api/maintenance-order/${task.id}/media`);
    console.log('Media API response:', response.data);
    
    if (response.data.success) {
      taskMedia.value = response.data.data.map(media => ({
        id: media.id,
        url: media.url,
        filename: media.filename,
        mediaType: media.media_type || 'image', // Default to image if not specified
        mimeType: media.mime_type,
        size: media.size,
        created_at: media.created_at
      }));
      
      console.log('Processed media data:', taskMedia.value);
      
      if (taskMedia.value.length > 0) {
        currentMediaIndex.value = 0;
        currentMedia.value = taskMedia.value[0];
        console.log('Media modal ready with media:', currentMedia.value);
      } else {
        // No media available
        console.log('No media available for this task');
        Swal.fire({
          icon: 'info',
          title: 'No Media',
          text: 'This task has no media attachments.',
          timer: 2000,
          showConfirmButton: false
        });
        closeMediaModal();
      }
    } else {
      throw new Error(response.data.error || 'Failed to fetch media');
    }
  } catch (error) {
    console.error('Error fetching media:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Failed to load media for this task.',
    });
    closeMediaModal();
  }
}

function closeMediaModal() {
  showMediaModal.value = false;
  selectedTask.value = null;
  taskMedia.value = [];
  currentMediaIndex.value = 0;
  currentMedia.value = null;
}

function nextMedia() {
  if (taskMedia.value.length > 0) {
    currentMediaIndex.value = (currentMediaIndex.value + 1) % taskMedia.value.length;
    currentMedia.value = taskMedia.value[currentMediaIndex.value];
  }
}

function previousMedia() {
  if (taskMedia.value.length > 0) {
    currentMediaIndex.value = (currentMediaIndex.value - 1 + taskMedia.value.length) % taskMedia.value.length;
    currentMedia.value = taskMedia.value[currentMediaIndex.value];
  }
}

function goToMedia(index) {
  currentMediaIndex.value = index;
  currentMedia.value = taskMedia.value[currentMediaIndex.value];
}

// Lifecycle
onMounted(() => {
  fetchData();
  document.title = 'Maintenance Order List - YMSoft';
  document.addEventListener('click', handleClickOutside);
  document.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
  document.removeEventListener('keydown', handleKeydown);
});
</script>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default {
  layout: AppLayout
}
</script>

<style scoped>
.btn-primary {
  @apply px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors;
}

.btn-secondary {
  @apply px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors;
}

/* Status summary card hover effects */
.status-card {
  @apply transition-all duration-200;
}

.status-card:hover {
  @apply transform scale-105;
}

/* Full width table optimization */
.table-container {
  @apply w-full overflow-x-auto;
}

.table-full-width {
  @apply w-full table-fixed;
}

/* Comment button styling */
.comment-button {
  @apply transition-all duration-200 ease-in-out;
}

.comment-button:hover {
  @apply transform scale-105;
}

/* Comment count badge */
.comment-count-badge {
  @apply bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full font-medium;
}

/* Responsive column widths */
@media (min-width: 1280px) {
  .col-task-info { width: 25%; }
  .col-status { width: 120px; }
  .col-priority { width: 140px; }
  .col-location { width: 200px; }
  .col-due-date { width: 140px; }
  .col-members { width: 180px; }
  .col-comments { width: 140px; }
  .col-media { width: 140px; }
  .col-actions { width: 120px; }
}
</style>
