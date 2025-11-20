<template>
    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Live Support Admin Panel</h1>
                                <p class="text-gray-600 mt-1">Manage customer support conversations</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-sm text-gray-500">
                                    <span class="font-medium">{{ totalConversations }}</span> conversations
                                </div>
                                <button @click="refreshData" 
                                        class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600 transition-colors"
                                        :class="{ 'animate-spin': refreshing }">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                            <!-- Search -->
                            <div class="lg:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                                <div class="relative">
                                    <input v-model="filters.search" 
                                           @input="debouncedSearch"
                                           type="text" 
                                           placeholder="Search conversations, users, messages..."
                                           class="w-full px-3 py-2 pl-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Date From -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                                <input v-model="filters.dateFrom" 
                                       @change="applyFilters"
                                       type="date" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <!-- Date To -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                                <input v-model="filters.dateTo" 
                                       @change="applyFilters"
                                       type="date" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select v-model="filters.status" @change="applyFilters" 
                                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="all">All Status</option>
                                    <option value="open">Open</option>
                                    <option value="pending">Pending</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                                <select v-model="filters.priority" @change="applyFilters" 
                                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="all">All Priority</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
                                <select v-model="filters.perPage" @change="applyFilters" 
                                        class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="10">10</option>
                                    <option value="15">15</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button @click="clearFilters" 
                                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                                    Clear Filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Conversations List -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="space-y-4">
                            <div v-for="conversation in filteredConversations" 
                                 :key="conversation.id"
                                 @click="selectConversation(conversation)"
                                 class="border border-gray-200 rounded-lg p-4 cursor-pointer hover:bg-gray-50 transition-colors"
                                 :class="{ 'ring-2 ring-blue-500 bg-blue-50': selectedConversation?.id === conversation.id }">
                                
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-2">
                                            <h3 class="font-semibold text-gray-900 truncate">{{ getSubjectWithIcon(conversation.subject) }}</h3>
                                            <span :class="getStatusColor(conversation.status)" 
                                                  class="text-xs px-2 py-1 rounded-full">
                                                {{ conversation.status }}
                                            </span>
                                            <span :class="getPriorityColor(conversation.priority)" 
                                                  class="text-xs px-2 py-1 rounded-full">
                                                {{ conversation.priority }}
                                            </span>
                                        </div>
                                        
                                        <p class="text-sm text-gray-600 mb-1">{{ conversation.customer_name }} ({{ conversation.customer_email }})</p>
                                        
                                        <!-- User Info: Outlet, Divisi, Jabatan -->
                                        <div class="flex items-center gap-3 mb-2 text-xs text-gray-500">
                                            <span v-if="conversation.customer_outlet" class="flex items-center gap-1">
                                                <i class="fas fa-store"></i>
                                                {{ conversation.customer_outlet }}
                                            </span>
                                            <span v-if="conversation.customer_divisi" class="flex items-center gap-1">
                                                <i class="fas fa-building"></i>
                                                {{ conversation.customer_divisi }}
                                            </span>
                                            <span v-if="conversation.customer_jabatan" class="flex items-center gap-1">
                                                <i class="fas fa-user-tie"></i>
                                                {{ conversation.customer_jabatan }}
                                            </span>
                                        </div>
                                        
                                        <p class="text-sm text-gray-700 truncate">{{ conversation.last_message }}</p>
                                        
                                        <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                                            <span>Created: {{ formatDate(conversation.created_at) }}</span>
                                            <span>Last: {{ formatDate(conversation.last_message_at) }}</span>
                                            <span v-if="conversation.last_sender_type === 'admin'" class="text-green-600">
                                                Replied by: {{ conversation.last_sender_name }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div v-if="conversation.unread_count > 0" 
                                         class="bg-red-500 text-white text-xs rounded-full h-6 w-6 flex items-center justify-center font-bold">
                                        {{ conversation.unread_count }}
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="filteredConversations.length === 0" class="text-center py-8 text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                </svg>
                                <p>No conversations found</p>
                            </div>
                        </div>
                        
                        <!-- Pagination -->
                        <div v-if="pagination && pagination.last_page > 1" class="mt-6 flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} results
                            </div>
                            <div class="flex items-center space-x-2">
                                <!-- Previous Button -->
                                <button @click="goToPage(pagination.current_page - 1)" 
                                        :disabled="pagination.current_page <= 1"
                                        class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Previous
                                </button>
                                
                                <!-- Page Numbers -->
                                <template v-for="page in getPageNumbers()" :key="page">
                                    <button v-if="page !== '...'" 
                                            @click="goToPage(page)"
                                            :class="[
                                                'px-3 py-2 text-sm font-medium rounded-md',
                                                page === pagination.current_page 
                                                    ? 'bg-blue-500 text-white' 
                                                    : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'
                                            ]">
                                        {{ page }}
                                    </button>
                                    <span v-else class="px-3 py-2 text-sm text-gray-500">...</span>
                                </template>
                                
                                <!-- Next Button -->
                                <button @click="goToPage(pagination.current_page + 1)" 
                                        :disabled="pagination.current_page >= pagination.last_page"
                                        class="px-3 py-2 text-sm font-medium text-gray-500 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conversation Detail Modal -->
        <div v-if="showConversationModal && selectedConversation" 
             class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg w-full max-w-4xl mx-4 max-h-[90vh] flex flex-col">
                <!-- Modal Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">{{ selectedConversation.subject }}</h2>
                            <p class="text-sm text-gray-600 mt-1">
                                {{ selectedConversation.customer_name }} ({{ selectedConversation.customer_email }})
                            </p>
                            
                            <!-- User Info: Outlet, Divisi, Jabatan -->
                            <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                                <span v-if="selectedConversation.customer_outlet" class="flex items-center gap-1">
                                    <i class="fas fa-store"></i>
                                    {{ selectedConversation.customer_outlet }}
                                </span>
                                <span v-if="selectedConversation.customer_divisi" class="flex items-center gap-1">
                                    <i class="fas fa-building"></i>
                                    {{ selectedConversation.customer_divisi }}
                                </span>
                                <span v-if="selectedConversation.customer_jabatan" class="flex items-center gap-1">
                                    <i class="fas fa-user-tie"></i>
                                    {{ selectedConversation.customer_jabatan }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <select v-model="selectedConversation.status" 
                                    @change="updateConversationStatus"
                                    class="px-3 py-1 border border-gray-300 rounded-md text-sm">
                                <option value="open">Open</option>
                                <option value="pending">Pending</option>
                                <option value="closed">Closed</option>
                            </select>
                            <select v-model="selectedConversation.priority" 
                                    @change="updateConversationPriority"
                                    class="px-3 py-1 border border-gray-300 rounded-md text-sm">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                            <button @click="closeConversationModal" 
                                    class="text-gray-500 hover:text-gray-700">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <div class="flex-1 overflow-y-auto p-6 space-y-4">
                    <div v-for="message in messages" 
                         :key="message.id"
                         class="flex"
                         :class="message.sender_type === 'admin' ? 'justify-end' : 'justify-start'">
                        <div class="max-w-xs lg:max-w-md">
                            <div class="flex items-end gap-2"
                                 :class="message.sender_type === 'admin' ? 'flex-row-reverse' : 'flex-row'">
                                <!-- Avatar -->
                                <div class="w-8 h-8 rounded-full bg-gray-300 flex items-center justify-center flex-shrink-0">
                                    <img v-if="message.sender_avatar" 
                                         :src="`/storage/${message.sender_avatar}`" 
                                         class="w-8 h-8 rounded-full object-cover"
                                         alt="Avatar">
                                    <span v-else class="text-xs font-medium text-gray-600">
                                        {{ getInitials(message.sender_name) }}
                                    </span>
                                </div>
                                
                                <!-- Message Bubble -->
                                <div class="rounded-lg px-3 py-2"
                                     :class="message.sender_type === 'admin' 
                                        ? 'bg-blue-500 text-white' 
                                        : 'bg-gray-200 text-gray-800'">
                                    <!-- Admin name for admin messages -->
                                    <div v-if="message.sender_type === 'admin' && message.sender_name" 
                                         class="text-xs font-medium mb-1"
                                         :class="message.sender_type === 'admin' ? 'text-blue-100' : 'text-gray-600'">
                                        {{ message.sender_name }}
                                    </div>
                                    <p class="text-sm">{{ message.message }}</p>
                                    
                                    <!-- File Attachments -->
                                    <div v-if="message.file_path" class="mt-2">
                                        <div v-for="(file, index) in getFileAttachments(message.file_path)" 
                                             :key="`file-${message.id}-${index}`"
                                             class="mb-2">
                                            <!-- Image thumbnail -->
                                            <div v-if="file && file.original_name && file.file_path && isImageFile(file.original_name)" class="relative">
                                                <img :src="`/storage/${file.file_path}`" 
                                                     @click="openLightbox(`/storage/${file.file_path}`)"
                                                     class="max-w-full h-32 object-cover rounded cursor-pointer hover:opacity-80 transition-opacity border border-gray-300"
                                                     :alt="file.original_name || 'Attachment'"
                                                     @error="handleImageError">
                                            </div>
                                            <!-- File icon -->
                                            <div v-else-if="file && file.original_name && file.file_path" class="flex items-center gap-2 p-2 bg-white bg-opacity-20 rounded">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                <span class="text-xs flex-1 truncate">{{ file.original_name }}</span>
                                                <a :href="`/storage/${file.file_path}`" 
                                                   target="_blank" 
                                                   class="text-xs hover:underline">
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <p class="text-xs mt-1 opacity-75">{{ formatTime(message.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reply Input -->
                <div class="p-6 border-t border-gray-200">
                    <!-- File Preview -->
                    <div v-if="selectedFiles.length > 0" class="mb-4">
                        <div class="flex flex-wrap gap-2">
                            <div v-for="(file, index) in selectedFiles" :key="index" 
                                 class="flex items-center gap-2 bg-gray-100 p-2 rounded-lg">
                                <!-- Image thumbnail -->
                                <div v-if="file.type.startsWith('image/')" class="relative">
                                    <img :src="getImageSrc(file)" 
                                         @click="openLightbox(getImageSrc(file))"
                                         class="w-8 h-8 object-cover rounded cursor-pointer hover:opacity-80 transition-opacity"
                                         alt="Thumbnail">
                                </div>
                                <!-- File icon -->
                                <svg v-else class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <span class="text-xs text-gray-700">{{ file.name }}</span>
                                <button @click="removeFile(index)" class="text-red-500 hover:text-red-700">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex gap-2">
                        <!-- File Upload Button -->
                        <input ref="fileInput" 
                               @change="handleFileUpload" 
                               type="file" 
                               multiple 
                               accept="image/*,.pdf,.doc,.docx,.txt,.xlsx,.xls"
                               class="hidden">
                        <button @click="$refs.fileInput.click()" 
                                class="flex-shrink-0 px-3 py-2 text-gray-600 hover:text-gray-800 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                        </button>
                        
                        <!-- Camera Button -->
                        <button @click="captureFromCamera" 
                                class="flex-shrink-0 px-3 py-2 text-gray-600 hover:text-gray-800 border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </button>
                        
                        <!-- Message Input -->
                        <input v-model="replyMessage" 
                               @keyup.enter="sendReply"
                               type="text" 
                               placeholder="Type your reply..."
                               class="flex-1 min-w-0 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        
                        <!-- Send Button -->
                        <button @click="sendReply" 
                                :disabled="(!replyMessage.trim() && selectedFiles.length === 0) || sending"
                                class="flex-shrink-0 bg-blue-500 text-white px-3 py-2 rounded-md hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg v-if="sending" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lightbox Modal -->
        <div v-if="showLightbox" 
             class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50" 
             @click="closeLightbox">
            <div class="relative max-w-4xl max-h-full p-4">
                <button @click="closeLightbox" 
                        class="absolute top-2 right-2 text-white text-2xl hover:text-gray-300 z-10">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                <img :src="lightboxImage" 
                     class="max-w-full max-h-full object-contain rounded-lg"
                     @click.stop
                     alt="Full size image">
            </div>
        </div>

        <!-- Camera Modal -->
        <div v-if="showCameraModal" 
             class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50">
            <div class="relative bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold">Capture Photo</h3>
                    <button @click="closeCamera" 
                            class="text-gray-500 hover:text-gray-700">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="mb-4">
                    <video ref="cameraVideo" 
                           autoplay 
                           playsinline 
                           class="w-full h-64 bg-gray-200 rounded-lg object-cover"
                           v-show="!capturedPhoto">
                    </video>
                    <div v-if="capturedPhoto" class="w-full h-64 bg-gray-200 rounded-lg flex items-center justify-center">
                        <img :src="capturedPhoto" class="w-full h-full object-cover rounded-lg" alt="Captured photo">
                    </div>
                </div>
                
                <div class="flex gap-2 justify-center">
                    <button v-if="!capturedPhoto" 
                            @click="capturePhoto" 
                            class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                        Capture
                    </button>
                    <button v-if="capturedPhoto" 
                            @click="useCapturedPhoto" 
                            class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600">
                        Use Photo
                    </button>
                    <button v-if="capturedPhoto" 
                            @click="retakePhoto" 
                            class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        Retake
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

// Reactive data
const conversations = ref([]);
const selectedConversation = ref(null);
const messages = ref([]);
const replyMessage = ref('');
const sending = ref(false);
const refreshing = ref(false);
const showConversationModal = ref(false);
const showLightbox = ref(false);
const lightboxImage = ref('');
const pagination = ref(null);
const searchTimeout = ref(null);

// File upload and camera
const selectedFiles = ref([]);
const showCameraModal = ref(false);
const capturedPhoto = ref(null);
const cameraStream = ref(null);

const filters = ref({
    status: 'all',
    priority: 'all',
    search: '',
    dateFrom: '',
    dateTo: '',
    perPage: 15,
    page: 1
});

// Computed
const totalConversations = computed(() => pagination.value?.total || 0);

const filteredConversations = computed(() => conversations.value);

// Methods
const fetchConversations = async () => {
    try {
        refreshing.value = true;
        const params = new URLSearchParams();
        
        // Add filters to params
        Object.keys(filters.value).forEach(key => {
            if (filters.value[key] && filters.value[key] !== 'all') {
                params.append(key, filters.value[key]);
            }
        });
        
        const response = await axios.get(`/api/support/admin/conversations?${params.toString()}`);
        
        if (response.data.data) {
            conversations.value = response.data.data;
            pagination.value = response.data.pagination;
        } else {
            // Fallback for old response format
            conversations.value = response.data;
            pagination.value = null;
        }
    } catch (error) {
        console.error('Error fetching conversations:', error);
    } finally {
        refreshing.value = false;
    }
};

const debouncedSearch = () => {
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
    }
    searchTimeout.value = setTimeout(() => {
        filters.value.page = 1; // Reset to first page when searching
        applyFilters();
    }, 500);
};

const applyFilters = () => {
    filters.value.page = 1; // Reset to first page when applying filters
    fetchConversations();
};

const clearFilters = () => {
    filters.value = {
        status: 'all',
        priority: 'all',
        search: '',
        dateFrom: '',
        dateTo: '',
        perPage: 15,
        page: 1
    };
    fetchConversations();
};

const goToPage = (page) => {
    if (page >= 1 && page <= pagination.value.last_page) {
        filters.value.page = page;
        fetchConversations();
    }
};

const getPageNumbers = () => {
    if (!pagination.value) return [];
    
    const current = pagination.value.current_page;
    const last = pagination.value.last_page;
    const pages = [];
    
    if (last <= 7) {
        for (let i = 1; i <= last; i++) {
            pages.push(i);
        }
    } else {
        if (current <= 4) {
            for (let i = 1; i <= 5; i++) {
                pages.push(i);
            }
            pages.push('...');
            pages.push(last);
        } else if (current >= last - 3) {
            pages.push(1);
            pages.push('...');
            for (let i = last - 4; i <= last; i++) {
                pages.push(i);
            }
        } else {
            pages.push(1);
            pages.push('...');
            for (let i = current - 1; i <= current + 1; i++) {
                pages.push(i);
            }
            pages.push('...');
            pages.push(last);
        }
    }
    
    return pages;
};

const selectConversation = async (conversation) => {
    selectedConversation.value = conversation;
    showConversationModal.value = true;
    await fetchMessages(conversation.id);
};

const fetchMessages = async (conversationId) => {
    try {
        const response = await axios.get(`/api/support/conversations/${conversationId}/messages`);
        messages.value = response.data;
        
        // Debug: Log messages with attachments
        messages.value.forEach(msg => {
            if (msg.file_path) {
                console.log('Message with file_path:', {
                    id: msg.id,
                    file_path: msg.file_path,
                    file_path_type: typeof msg.file_path,
                    is_array: Array.isArray(msg.file_path),
                    parsed: getFileAttachments(msg.file_path)
                });
            }
        });
    } catch (error) {
        console.error('Error fetching messages:', error);
    }
};

const sendReply = async () => {
    if ((!replyMessage.value.trim() && selectedFiles.value.length === 0) || !selectedConversation.value) return;
    
    sending.value = true;
    try {
        const formData = new FormData();
        formData.append('message', replyMessage.value || 'File attachment');
        
        // Add files to form data
        selectedFiles.value.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });
        
        const response = await axios.post(`/api/support/admin/conversations/${selectedConversation.value.id}/reply`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        
        messages.value.push(response.data);
        replyMessage.value = '';
        selectedFiles.value = [];
        
        // Update conversation in list
        const convIndex = conversations.value.findIndex(c => c.id === selectedConversation.value.id);
        if (convIndex !== -1) {
            conversations.value[convIndex].last_message = response.data.message;
            conversations.value[convIndex].last_message_at = response.data.created_at;
            conversations.value[convIndex].last_sender_name = response.data.sender_name;
            conversations.value[convIndex].last_sender_type = response.data.sender_type;
        }
    } catch (error) {
        console.error('Error sending reply:', error);
    } finally {
        sending.value = false;
    }
};

// File upload methods
const handleFileUpload = (event) => {
    const files = Array.from(event.target.files);
    files.forEach(file => {
        if (file.size <= 10 * 1024 * 1024) { // 10MB limit
            selectedFiles.value.push(file);
        } else {
            alert(`File ${file.name} is too large. Maximum size is 10MB.`);
        }
    });
    event.target.value = ''; // Reset input
};

const removeFile = (index) => {
    selectedFiles.value.splice(index, 1);
};

const getImageSrc = (file) => {
    return URL.createObjectURL(file);
};

// Camera methods
const captureFromCamera = async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'environment' // Use back camera if available
            } 
        });
        
        cameraStream.value = stream;
        showCameraModal.value = true;
        
        // Wait for next tick to ensure video element is rendered
        await nextTick();
        const video = document.querySelector('video');
        if (video) {
            video.srcObject = stream;
        }
    } catch (error) {
        console.error('Error accessing camera:', error);
        alert('Unable to access camera. Please check permissions.');
    }
};

const capturePhoto = () => {
    const video = document.querySelector('video');
    if (video) {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);
        capturedPhoto.value = canvas.toDataURL('image/jpeg');
    }
};

const useCapturedPhoto = () => {
    if (capturedPhoto.value) {
        // Convert data URL to file
        fetch(capturedPhoto.value)
            .then(res => res.blob())
            .then(blob => {
                const file = new File([blob], `camera_${Date.now()}.jpg`, { type: 'image/jpeg' });
                selectedFiles.value.push(file);
                closeCamera();
            });
    }
};

const retakePhoto = () => {
    capturedPhoto.value = null;
};

const closeCamera = () => {
    if (cameraStream.value) {
        cameraStream.value.getTracks().forEach(track => track.stop());
        cameraStream.value = null;
    }
    showCameraModal.value = false;
    capturedPhoto.value = null;
};

const updateConversationStatus = async () => {
    try {
        await axios.put(`/api/support/admin/conversations/${selectedConversation.value.id}/status`, {
            status: selectedConversation.value.status,
            priority: selectedConversation.value.priority
        });
        
        // Update in list
        const convIndex = conversations.value.findIndex(c => c.id === selectedConversation.value.id);
        if (convIndex !== -1) {
            conversations.value[convIndex].status = selectedConversation.value.status;
        }
    } catch (error) {
        console.error('Error updating status:', error);
    }
};

const updateConversationPriority = async () => {
    try {
        await axios.put(`/api/support/admin/conversations/${selectedConversation.value.id}/status`, {
            status: selectedConversation.value.status,
            priority: selectedConversation.value.priority
        });
        
        // Update in list
        const convIndex = conversations.value.findIndex(c => c.id === selectedConversation.value.id);
        if (convIndex !== -1) {
            conversations.value[convIndex].priority = selectedConversation.value.priority;
        }
    } catch (error) {
        console.error('Error updating priority:', error);
    }
};

const closeConversationModal = () => {
    showConversationModal.value = false;
    selectedConversation.value = null;
    messages.value = [];
    replyMessage.value = '';
};

const refreshData = async () => {
    await fetchConversations();
};

const openLightbox = (imageSrc) => {
    lightboxImage.value = imageSrc;
    showLightbox.value = true;
};

const closeLightbox = () => {
    showLightbox.value = false;
    lightboxImage.value = '';
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const formatTime = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit'
    });
};

const getStatusColor = (status) => {
    const colors = {
        open: 'bg-green-100 text-green-800',
        closed: 'bg-gray-100 text-gray-800',
        pending: 'bg-yellow-100 text-yellow-800'
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
};

const getPriorityColor = (priority) => {
    const colors = {
        low: 'bg-blue-100 text-blue-800',
        medium: 'bg-yellow-100 text-yellow-800',
        high: 'bg-orange-100 text-orange-800',
        urgent: 'bg-red-100 text-red-800'
    };
    return colors[priority] || 'bg-gray-100 text-gray-800';
};

const getInitials = (name) => {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
};

const getSubjectWithIcon = (subject) => {
    const subjectIcons = {
        'Bug Report': '🐛',
        'Feature Request': '💡',
        'Technical Support': '🔧',
        'Data Issue': '📊',
        'Login Problem': '🔐',
        'Permission Issue': '👤',
        'Report Error': '📋',
        'Performance Issue': '⚡',
        'Integration Problem': '🔗',
        'Training Request': '🎓',
        'General Question': '❓',
        'Other': '📝'
    };
    
    const icon = subjectIcons[subject] || '📝';
    return `${icon} ${subject}`;
};

const getFileAttachments = (filePath) => {
    if (!filePath) {
        return [];
    }
    
    // If already an array, return it directly
    if (Array.isArray(filePath)) {
        return filePath;
    }
    
    // If it's a string, try to parse it
    if (typeof filePath === 'string') {
        try {
            const parsed = JSON.parse(filePath);
            // Ensure it's an array
            if (Array.isArray(parsed)) {
                return parsed;
            }
            // If it's an object, wrap it in array
            if (parsed && typeof parsed === 'object') {
                return [parsed];
            }
        } catch (e) {
            console.error('Error parsing file_path:', e, filePath);
            // Fallback for old single file format
            return [{
                original_name: 'attachment',
                file_path: filePath,
                file_size: 0,
                mime_type: 'application/octet-stream'
            }];
        }
    }
    
    return [];
};

const handleImageError = (event) => {
    console.error('Error loading attachment image:', event.target.src);
    // Optionally show error message or placeholder
    event.target.style.display = 'none';
};

const isImageFile = (fileName) => {
    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp'];
    const extension = fileName.toLowerCase().substring(fileName.lastIndexOf('.'));
    return imageExtensions.includes(extension);
};

// Lifecycle
onMounted(() => {
    fetchConversations();
});

onUnmounted(() => {
    // Stop camera stream if active
    if (cameraStream.value) {
        cameraStream.value.getTracks().forEach(track => track.stop());
    }
});
</script>
