<template>
    <!-- Floating Chat Button -->
    <div 
        ref="widgetContainer"
        class="fixed z-50"
        :class="{ 'dragging': isDragging }"
        :style="{ 
            left: widgetPosition.x + 'px', 
            bottom: widgetPosition.y + 'px',
            cursor: isDragging ? 'grabbing' : 'grab'
        }"
        @mousedown="startDrag"
        @touchstart="startDrag"
        @touchend="handleTouchEnd"
        @dblclick="resetWidgetPosition"
    >
        <!-- Chat Button with Label -->
        <div class="flex flex-col items-end gap-2 group">
            <!-- Live Support Label -->
            <div v-if="!isOpen" 
                 class="bg-gray-800 text-white text-xs px-3 py-1 rounded-full shadow-lg whitespace-nowrap flex items-center gap-1 cursor-move"
                 :class="{ 'opacity-80': isDragging }">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                </svg>
                Live Support
            </div>
            
            <!-- Chat Button -->
            <button 
                @click="toggleChat"
                @touchstart="handleButtonTouchStart"
                class="bg-blue-500 hover:bg-blue-600 text-white rounded-full p-4 shadow-lg transition-all duration-300 hover:scale-110 relative select-none group"
                :class="{ 
                    'animate-pulse': hasUnreadMessages,
                    'opacity-80': isDragging,
                    'scale-105': isDragging,
                    'shadow-2xl': isDragging
                }"
                :title="!isOpen ? 'Click to open chat, drag to move, or double-click to reset position' : 'Click to close chat'"
            >
                <!-- Chat Icon -->
                <svg v-if="!isOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                
                <!-- Close Icon -->
                <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                
                <!-- Unread Badge - Only show if there are unread messages -->
                <span v-if="unreadCount > 0" 
                      class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-6 w-6 flex items-center justify-center font-bold animate-pulse">
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </button>
        </div>
    </div>

    <!-- Chat Window -->
    <div v-if="isOpen" 
         class="fixed bottom-24 right-6 w-[450px] h-[600px] bg-white rounded-lg shadow-2xl border border-gray-200 z-50 flex flex-col">
        
        <!-- Chat Header -->
        <div class="bg-blue-500 text-white p-4 rounded-t-lg flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                <h3 class="font-semibold">Live Support</h3>
            </div>
            <div class="flex items-center gap-2">
                <button @click="refreshConversations" 
                        class="text-white hover:text-gray-200 transition-colors"
                        :class="{ 'animate-spin': refreshing }">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </button>
                <button @click="toggleChat" class="text-white hover:text-gray-200 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Chat Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Conversations List -->
            <div v-if="!selectedConversation" class="flex-1 overflow-y-auto">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="font-semibold text-gray-800">Your Conversations</h4>
                        <button @click="showNewConversationModal = true" 
                                class="bg-blue-500 text-white px-3 py-1 rounded-md text-sm hover:bg-blue-600 transition-colors">
                            + New Chat
                        </button>
                    </div>
                    
                    <!-- Conversations -->
                    <div class="space-y-2">
                        <div v-for="conversation in conversations" 
                             :key="conversation.id"
                             @click="selectConversation(conversation)"
                             class="p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1 min-w-0">
                                    <h5 class="font-medium text-gray-800 truncate">{{ conversation.subject }}</h5>
                                    <p class="text-sm text-gray-600 truncate mt-1">{{ conversation.last_message }}</p>
                                    <div class="flex items-center gap-2 mt-2">
                                        <span class="text-xs text-gray-500">{{ formatDate(conversation.last_message_at) }}</span>
                                        <span :class="getStatusColor(conversation.status)" 
                                              class="text-xs px-2 py-1 rounded-full">
                                            {{ conversation.status }}
                                        </span>
                                        <span :class="getPriorityColor(conversation.priority)" 
                                              class="text-xs px-2 py-1 rounded-full">
                                            {{ conversation.priority }}
                                        </span>
                                    </div>
                                </div>
                                <div v-if="conversation.unread_count > 0" 
                                     class="bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">
                                    {{ conversation.unread_count }}
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="conversations.length === 0" class="text-center py-8 text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <p>No conversations yet</p>
                            <p class="text-sm">Start a new chat to get help</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages View -->
            <div v-else class="flex-1 flex flex-col h-full">
                <!-- Conversation Header -->
                <div class="p-4 border-b border-gray-200 bg-gray-50 flex-shrink-0">
                    <div class="flex items-center justify-between">
                        <div>
                            <h4 class="font-semibold text-gray-800">{{ selectedConversation.subject }}</h4>
                            <div class="flex items-center gap-2 mt-1">
                                <span :class="getStatusColor(selectedConversation.status)" 
                                      class="text-xs px-2 py-1 rounded-full">
                                    {{ selectedConversation.status }}
                                </span>
                                <span :class="getPriorityColor(selectedConversation.priority)" 
                                      class="text-xs px-2 py-1 rounded-full">
                                    {{ selectedConversation.priority }}
                                </span>
                            </div>
                        </div>
                        <button @click="selectedConversation = null" 
                                class="text-gray-500 hover:text-gray-700">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-3 min-h-0 scroll-smooth">
                    <div v-for="message in messages" 
                         :key="message.id"
                         class="flex"
                         :class="message.sender_type === 'user' ? 'justify-end' : 'justify-start'">
                        <div class="max-w-xs lg:max-w-md">
                            <div class="flex items-end gap-2"
                                 :class="message.sender_type === 'user' ? 'flex-row-reverse' : 'flex-row'">
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
                                     :class="message.sender_type === 'user' 
                                        ? 'bg-blue-500 text-white' 
                                        : 'bg-gray-200 text-gray-800'">
                                    <!-- Admin name for admin messages -->
                                    <div v-if="message.sender_type === 'admin' && message.sender_name" 
                                         class="text-xs font-medium text-gray-600 mb-1">
                                        {{ message.sender_name }}
                                    </div>
                                    <p class="text-sm">{{ message.message }}</p>
                                    
                                    <!-- File Attachments -->
                                    <div v-if="message.file_path" class="mt-2">
                                        <div v-for="(file, index) in getFileAttachments(message.file_path)" :key="index" 
                                             class="mb-2">
                                            <!-- Image thumbnail -->
                                            <div v-if="isImageFile(file.original_name)" class="relative">
                                                <img :src="`/api/support/conversations/${selectedConversation.id}/messages/${message.id}/files/${index}`" 
                                                     @click="openLightbox(`/api/support/conversations/${selectedConversation.id}/messages/${message.id}/files/${index}`)"
                                                     class="max-w-full h-32 object-cover rounded cursor-pointer hover:opacity-80 transition-opacity"
                                                     alt="Attachment">
                                            </div>
                                            <!-- File icon -->
                                            <div v-else class="flex items-center gap-2 p-2 bg-white bg-opacity-20 rounded">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                <span class="text-xs flex-1 truncate">{{ file.original_name }}</span>
                                                <a :href="`/api/support/conversations/${selectedConversation.id}/messages/${message.id}/files/${index}`" 
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
                    
                    <div v-if="messages.length === 0" class="text-center py-8 text-gray-500">
                        <p>No messages yet</p>
                    </div>
                </div>

                <!-- Message Input -->
                <div class="p-4 border-t border-gray-200 flex-shrink-0">
                    <!-- File Upload Section -->
                    <div v-if="selectedFiles.length > 0" class="mb-3">
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
                    
                    <div class="flex gap-2 items-end">
                        <!-- File Upload Button -->
                        <label class="flex items-center gap-1 bg-gray-100 text-gray-700 px-2 py-2 rounded-md cursor-pointer hover:bg-gray-200 transition-colors flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                            <input type="file" @change="handleFileUpload" multiple accept="image/*,.pdf,.doc,.docx,.txt,.xlsx,.xls" class="hidden">
                        </label>
                        
                        <!-- Camera Button -->
                        <button @click="captureFromCamera" 
                                class="flex items-center gap-1 bg-green-100 text-green-700 px-2 py-2 rounded-md hover:bg-green-200 transition-colors flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </button>
                        
                        <input v-model="newMessage" 
                               @keyup.enter="sendMessage"
                               type="text" 
                               placeholder="Type your message..."
                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 min-w-0">
                        <button @click="sendMessage" 
                                :disabled="(!newMessage.trim() && selectedFiles.length === 0) || sending"
                                class="bg-blue-500 text-white px-3 py-2 rounded-md hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed flex-shrink-0">
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
    </div>

    <!-- New Conversation Modal -->
    <div v-if="showNewConversationModal" 
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
            <h3 class="text-lg font-semibold mb-4">Start New Conversation</h3>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <select v-model="newConversation.subject" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Pilih subjek...</option>
                        <option value="Laporan Bug">🐛 Laporan Bug</option>
                        <option value="Permintaan Fitur">💡 Permintaan Fitur</option>
                        <option value="Dukungan Teknis">🔧 Dukungan Teknis</option>
                        <option value="Masalah Data">📊 Masalah Data</option>
                        <option value="Masalah Login">🔐 Masalah Login</option>
                        <option value="Masalah Izin">👤 Masalah Izin</option>
                        <option value="Laporan Error">📋 Laporan Error</option>
                        <option value="Masalah Performa">⚡ Masalah Performa</option>
                        <option value="Masalah Integrasi">🔗 Masalah Integrasi</option>
                        <option value="Permintaan Pelatihan">🎓 Permintaan Pelatihan</option>
                        <option value="Pertanyaan Umum">❓ Pertanyaan Umum</option>
                        <option value="Lainnya">📝 Lainnya</option>
                    </select>
                    <input v-if="newConversation.subject === 'Lainnya'" 
                           v-model="newConversation.customSubject"
                           type="text" 
                           placeholder="Silakan jelaskan..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 mt-2">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <select v-model="newConversation.priority" 
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                    <textarea v-model="newConversation.message" 
                              rows="4" 
                              placeholder="Describe your issue or question..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                
                <!-- File Upload Section for New Conversation -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Attachments (Optional)</label>
                    
                    <!-- File Preview -->
                    <div v-if="newConversationFiles.length > 0" class="mb-3">
                        <div class="flex flex-wrap gap-2">
                            <div v-for="(file, index) in newConversationFiles" :key="index" 
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
                                <button @click="removeNewConversationFile(index)" class="text-red-500 hover:text-red-700">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- File Upload Buttons -->
                    <div class="flex gap-2">
                        <!-- File Upload Button -->
                        <label class="flex items-center gap-1 bg-gray-100 text-gray-700 px-3 py-2 rounded-md cursor-pointer hover:bg-gray-200 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                            <span class="text-sm">Upload File</span>
                            <input type="file" @change="handleNewConversationFileUpload" multiple accept="image/*,.pdf,.doc,.docx,.txt,.xlsx,.xls" class="hidden">
                        </label>
                        
                        <!-- Camera Button -->
                        <button @click="captureFromCameraForNewConversation" 
                                class="flex items-center gap-1 bg-green-100 text-green-700 px-3 py-2 rounded-md hover:bg-green-200 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span class="text-sm">Camera</span>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-2 mt-6">
                <button @click="showNewConversationModal = false" 
                        class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                    Cancel
                </button>
                <button @click="createConversation" 
                        :disabled="!newConversation.subject.trim() || (!newConversation.message.trim() && newConversationFiles.length === 0) || (newConversation.subject === 'Other' && !newConversation.customSubject.trim()) || creating"
                        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ creating ? 'Creating...' : 'Start Chat' }}
                </button>
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
         class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold">Capture Photo</h3>
                <button @click="closeCamera" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <div class="relative">
                <!-- Camera Preview -->
                <video v-if="!capturedImage" 
                       ref="videoElement" 
                       autoplay 
                       class="w-full h-64 bg-gray-200 rounded-lg object-cover">
                </video>
                
                <!-- Captured Image Preview -->
                <div v-if="capturedImage" class="relative">
                    <img :src="capturedImage" 
                         class="w-full h-64 object-cover rounded-lg"
                         alt="Captured">
                    <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                        <div class="text-white text-center">
                            <p class="text-lg font-semibold mb-2">Photo Captured!</p>
                            <p class="text-sm">Click "Use Photo" to add to message</p>
                        </div>
                    </div>
                </div>
                
                <!-- Camera Controls -->
                <div class="flex justify-center gap-4 mt-4">
                    <button v-if="!capturedImage" 
                            @click="capturePhoto" 
                            class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Capture
                    </button>
                    
                    <button v-if="capturedImage" 
                            @click="useCapturedPhoto" 
                            class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition-colors">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Use Photo
                    </button>
                    
                    <button v-if="capturedImage" 
                            @click="retakePhoto" 
                            class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Retake
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';

// Reactive data
const isOpen = ref(false);
const conversations = ref([]);
const selectedConversation = ref(null);
const messages = ref([]);
const newMessage = ref('');
const sending = ref(false);
const refreshing = ref(false);
const hasUnreadMessages = ref(false);
const unreadCount = ref(0);
const showNewConversationModal = ref(false);
const showLightbox = ref(false);
const lightboxImage = ref('');
const creating = ref(false);
const selectedFiles = ref([]);
const showCameraModal = ref(false);
const capturedImage = ref('');
const videoElement = ref(null);
const stream = ref(null);

// Draggable state
const isDragging = ref(false);
const dragStart = ref({ x: 0, y: 0 });
const widgetPosition = ref({ x: 24, y: 24 }); // Default position (bottom-right)
const widgetContainer = ref(null);
const dragThreshold = 5; // Minimum distance to consider as drag
const hasDragged = ref(false);

const newConversation = ref({
    subject: '',
    customSubject: '',
    message: '',
    priority: 'medium'
});

const newConversationFiles = ref([]);

// Polling interval
let pollingInterval = null;

// Drag methods
const startDrag = (event) => {
    // Only allow dragging when chat is closed
    if (isOpen.value) return;
    
    // If this is a button touch, don't start drag
    if (event.target.closest('button')) {
        return;
    }
    
    hasDragged.value = false;
    
    // Get initial mouse/touch position
    const clientX = event.type === 'touchstart' ? event.touches[0].clientX : event.clientX;
    const clientY = event.type === 'touchstart' ? event.touches[0].clientY : event.clientY;
    
    dragStart.value = {
        x: clientX - widgetPosition.value.x,
        y: clientY - widgetPosition.value.y,
        startX: clientX,
        startY: clientY,
        startTime: Date.now(),
        isButtonTouch: false
    };
    
    // Add event listeners
    document.addEventListener('mousemove', handleDrag);
    document.addEventListener('mouseup', stopDrag);
    document.addEventListener('touchmove', handleDrag, { passive: false });
    document.addEventListener('touchend', stopDrag);
};

const handleDrag = (event) => {
    const clientX = event.type === 'touchmove' ? event.touches[0].clientX : event.clientX;
    const clientY = event.type === 'touchmove' ? event.touches[0].clientY : event.clientY;
    
    // Check if we've moved enough to start dragging
    const deltaX = Math.abs(clientX - dragStart.value.startX);
    const deltaY = Math.abs(clientY - dragStart.value.startY);
    
    if (!isDragging.value && (deltaX > dragThreshold || deltaY > dragThreshold)) {
        isDragging.value = true;
        hasDragged.value = true;
        event.preventDefault(); // Only prevent default when actually dragging
    }
    
    if (!isDragging.value) return;
    
    event.preventDefault();
    
    // Calculate new position
    let newX = clientX - dragStart.value.x;
    let newY = clientY - dragStart.value.y;
    
    // Get viewport dimensions
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    // Get widget dimensions (approximate)
    const widgetWidth = 80; // Approximate width of the button
    const widgetHeight = 80; // Approximate height of the button
    
    // Constrain to viewport boundaries
    newX = Math.max(0, Math.min(newX, viewportWidth - widgetWidth));
    newY = Math.max(0, Math.min(newY, viewportHeight - widgetHeight));
    
    widgetPosition.value = { x: newX, y: newY };
    
    // Add haptic feedback on mobile if available
    if (navigator.vibrate && (deltaX > dragThreshold || deltaY > dragThreshold)) {
        navigator.vibrate(10);
    }
    
    // Add visual feedback when reaching boundaries
    if (newX === 0 || newX === viewportWidth - widgetWidth || 
        newY === 0 || newY === viewportHeight - widgetHeight) {
        // Add subtle bounce effect
        if (widgetContainer.value) {
            widgetContainer.value.style.transform = 'scale(1.05)';
            setTimeout(() => {
                widgetContainer.value.style.transform = 'scale(1)';
            }, 100);
        }
        
        // Add haptic feedback for boundary hit
        if (navigator.vibrate) {
            navigator.vibrate(20);
        }
    }
    
    // Add visual feedback for successful drag
    if (isDragging.value && !hasDragged.value) {
        hasDragged.value = true;
        // Add subtle glow effect
        if (widgetContainer.value) {
            widgetContainer.value.style.boxShadow = '0 0 20px rgba(59, 130, 246, 0.5)';
            setTimeout(() => {
                widgetContainer.value.style.boxShadow = '';
            }, 200);
        }
    }
};

const stopDrag = (event) => {
    // Remove event listeners
    document.removeEventListener('mousemove', handleDrag);
    document.removeEventListener('mouseup', stopDrag);
    document.removeEventListener('touchmove', handleDrag);
    document.removeEventListener('touchend', stopDrag);
    
    if (isDragging.value) {
        isDragging.value = false;
        
        // Save position to localStorage
        saveWidgetPosition();
    }
    
    // Reset drag state after a short delay to prevent accidental clicks
    setTimeout(() => {
        hasDragged.value = false;
    }, 100);
};

const handleTouchEnd = (event) => {
    // If we were dragging, don't trigger click
    if (hasDragged.value || isDragging.value) {
        event.preventDefault();
        return;
    }
    
    // If this was a button touch, don't handle here
    if (dragStart.value.isButtonTouch) {
        return;
    }
    
    // If it's a quick tap, trigger the chat toggle
    if (dragStart.value.startTime) {
        const tapDuration = Date.now() - dragStart.value.startTime;
        if (tapDuration < 200) { // Quick tap
            toggleChat(event);
        }
    }
};

const handleButtonTouchStart = (event) => {
    // Prevent the container's touchstart from interfering
    event.stopPropagation();
    
    // Set a flag to indicate this is a button touch
    dragStart.value.isButtonTouch = true;
    
    // Set start time for button touch
    dragStart.value.startTime = Date.now();
    
    // Add touchend listener for button
    event.target.addEventListener('touchend', handleButtonTouchEnd, { once: true });
};

const handleButtonTouchEnd = (event) => {
    // If this was a quick tap on button, trigger chat
    if (dragStart.value.startTime) {
        const tapDuration = Date.now() - dragStart.value.startTime;
        if (tapDuration < 200) { // Quick tap
            toggleChat(event);
        }
    }
};

const saveWidgetPosition = () => {
    localStorage.setItem('liveSupportWidgetPosition', JSON.stringify(widgetPosition.value));
};

const loadWidgetPosition = () => {
    const saved = localStorage.getItem('liveSupportWidgetPosition');
    if (saved) {
        try {
            const position = JSON.parse(saved);
            // Validate position is within viewport
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const widgetWidth = 80;
            const widgetHeight = 80;
            
            const newX = Math.max(0, Math.min(position.x, viewportWidth - widgetWidth));
            const newY = Math.max(0, Math.min(position.y, viewportHeight - widgetHeight));
            
            widgetPosition.value = { x: newX, y: newY };
            
            // If position was corrected, save it
            if (newX !== position.x || newY !== position.y) {
                saveWidgetPosition();
            }
        } catch (e) {
            console.error('Error loading widget position:', e);
            // Reset to default position on error
            widgetPosition.value = { x: 24, y: 24 };
        }
    }
};

const resetWidgetPosition = () => {
    // Add visual feedback
    if (widgetContainer.value) {
        widgetContainer.value.style.transform = 'scale(0.9)';
        setTimeout(() => {
            widgetContainer.value.style.transform = 'scale(1)';
        }, 150);
    }
    
    widgetPosition.value = { x: 24, y: 24 };
    saveWidgetPosition();
};

// Methods
const toggleChat = async (event) => {
    // Prevent click if we were dragging
    if (hasDragged.value) {
        event.preventDefault();
        return;
    }
    
    // For mobile, check if this is a quick tap (not a drag)
    if (event.type === 'touchend' && dragStart.value.startTime) {
        const tapDuration = Date.now() - dragStart.value.startTime;
        if (tapDuration > 200) { // If touch lasted more than 200ms, it might be a drag
            event.preventDefault();
            return;
        }
    }
    
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        await fetchConversations();
        startPolling();
        
        // If there's a selected conversation, scroll to bottom after opening
        if (selectedConversation.value) {
            await nextTick();
            setTimeout(() => {
                scrollToBottom();
            }, 100); // Small delay to ensure DOM is updated
        }
    } else {
        stopPolling();
        // Reset unread count when chat is closed
        unreadCount.value = 0;
        hasUnreadMessages.value = false;
    }
};

const fetchConversations = async () => {
    try {
        const response = await axios.get('/api/support/conversations');
        conversations.value = response.data;
        
        // Calculate unread count - only count conversations with unread messages
        unreadCount.value = conversations.value.reduce((total, conv) => {
            // Only count if there are unread messages AND the conversation is not closed/resolved
            if (conv.unread_count && 
                typeof conv.unread_count === 'number' && 
                conv.unread_count > 0 && 
                conv.status !== 'closed' && 
                conv.status !== 'resolved') {
                return total + conv.unread_count;
            }
            return total;
        }, 0);
        
        // Ensure unreadCount is a valid number and greater than 0
        unreadCount.value = unreadCount.value > 0 ? unreadCount.value : 0;
        hasUnreadMessages.value = unreadCount.value > 0;
    } catch (error) {
        console.error('Error fetching conversations:', error);
    }
};

const selectConversation = async (conversation) => {
    selectedConversation.value = conversation;
    await fetchMessages(conversation.id);
    
    // Mark messages as read when conversation is selected
    if (conversation.unread_count > 0) {
        try {
            await axios.post(`/api/support/conversations/${conversation.id}/mark-read`);
            // Refresh conversations to update unread count
            await fetchConversations();
        } catch (error) {
            console.error('Error marking messages as read:', error);
        }
    }
    
    // Ensure scroll to bottom after messages are loaded
    await nextTick();
    setTimeout(() => {
        scrollToBottom();
    }, 150); // Slightly longer delay to ensure all messages are rendered
};

const fetchMessages = async (conversationId) => {
    try {
        const response = await axios.get(`/api/support/conversations/${conversationId}/messages`);
        messages.value = response.data;
        
        // Scroll to bottom
        await nextTick();
        scrollToBottom();
    } catch (error) {
        console.error('Error fetching messages:', error);
    }
};

const sendMessage = async () => {
    if ((!newMessage.value.trim() && selectedFiles.value.length === 0) || !selectedConversation.value) return;
    
    sending.value = true;
    try {
        const formData = new FormData();
        formData.append('message', newMessage.value || '');
        
        // Add files to form data
        selectedFiles.value.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });
        
        const response = await axios.post(`/api/support/conversations/${selectedConversation.value.id}/messages`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        
        messages.value.push(response.data);
        newMessage.value = '';
        selectedFiles.value = []; // Clear selected files
        
        // Scroll to bottom after sending message
        await nextTick();
        setTimeout(() => {
            scrollToBottom();
        }, 100);
    } catch (error) {
        console.error('Error sending message:', error);
        
        // Handle conversation closed error
        if (error.response?.data?.conversation_closed) {
            alert('Percakapan ini telah ditutup oleh tim support. Silakan buat percakapan baru jika Anda memerlukan bantuan lebih lanjut.');
            // Optionally refresh conversations to show updated status
            await fetchConversations();
        } else if (error.response?.data?.error) {
            alert(error.response.data.error);
        }
    } finally {
        sending.value = false;
    }
};

const createConversation = async () => {
    if (!newConversation.value.subject.trim() || (!newConversation.value.message.trim() && newConversationFiles.value.length === 0)) return;
    
    creating.value = true;
    try {
        // Use custom subject if "Other" is selected
        const subject = newConversation.value.subject === 'Other' 
            ? newConversation.value.customSubject.trim() 
            : newConversation.value.subject;
            
        if (!subject) {
            alert('Silakan tentukan subjek percakapan');
            return;
        }
        
        const formData = new FormData();
        formData.append('subject', subject);
        formData.append('message', newConversation.value.message || '');
        formData.append('priority', newConversation.value.priority);
        
        // Add files to form data
        newConversationFiles.value.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });
        
        const response = await axios.post('/api/support/conversations', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        
        conversations.value.unshift(response.data);
        selectedConversation.value = response.data;
        await fetchMessages(response.data.id);
        
        newConversation.value = {
            subject: '',
            customSubject: '',
            message: '',
            priority: 'medium'
        };
        newConversationFiles.value = []; // Clear files
        showNewConversationModal.value = false;
    } catch (error) {
        console.error('Error creating conversation:', error);
    } finally {
        creating.value = false;
    }
};

const refreshConversations = async () => {
    refreshing.value = true;
    await fetchConversations();
    refreshing.value = false;
};

const scrollToBottom = () => {
    const container = messagesContainer.value;
    if (container) {
        // Scroll to the very bottom of the container
        container.scrollTop = container.scrollHeight;
    }
};

const startPolling = () => {
    pollingInterval = setInterval(async () => {
        if (isOpen.value) {
            const previousMessageCount = messages.value.length;
            await fetchConversations();
            if (selectedConversation.value) {
                await fetchMessages(selectedConversation.value.id);
                
                // If new messages were added, scroll to bottom
                if (messages.value.length > previousMessageCount) {
                    await nextTick();
                    setTimeout(() => {
                        scrollToBottom();
                    }, 100);
                }
            }
        }
    }, 5000); // Poll every 5 seconds
};

const stopPolling = () => {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
};

const openLightbox = (imageSrc) => {
    lightboxImage.value = imageSrc;
    showLightbox.value = true;
};

const closeLightbox = () => {
    showLightbox.value = false;
    lightboxImage.value = '';
};

// File handling methods
const handleFileUpload = (event) => {
    const files = Array.from(event.target.files);
    files.forEach(file => {
        if (file.size <= 10 * 1024 * 1024) { // 10MB limit
            selectedFiles.value.push(file);
        } else {
            alert(`File ${file.name} terlalu besar. Ukuran maksimal adalah 10MB.`);
        }
    });
    event.target.value = ''; // Reset input
};

const removeFile = (index) => {
    selectedFiles.value.splice(index, 1);
};

// New conversation file handling methods
const handleNewConversationFileUpload = (event) => {
    const files = Array.from(event.target.files);
    files.forEach(file => {
        if (file.size <= 10 * 1024 * 1024) { // 10MB limit
            newConversationFiles.value.push(file);
        } else {
            alert(`File ${file.name} terlalu besar. Ukuran maksimal adalah 10MB.`);
        }
    });
    event.target.value = ''; // Reset input
};

const removeNewConversationFile = (index) => {
    newConversationFiles.value.splice(index, 1);
};

const captureFromCameraForNewConversation = async () => {
    try {
        showCameraModal.value = true;
        await nextTick();
        
        stream.value = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'environment' // Use back camera if available
            } 
        });
        
        if (videoElement.value) {
            videoElement.value.srcObject = stream.value;
        }
    } catch (error) {
        console.error('Error accessing camera:', error);
        alert('Tidak dapat mengakses kamera. Silakan periksa izin kamera.');
        showCameraModal.value = false;
    }
};

const getImageSrc = (file) => {
    return URL.createObjectURL(file);
};

const getFileAttachments = (filePath) => {
    try {
        return JSON.parse(filePath);
    } catch (e) {
        // Fallback for old single file format
        return [{
            original_name: 'attachment',
            file_path: filePath,
            file_size: 0,
            mime_type: 'application/octet-stream'
        }];
    }
};

const isImageFile = (fileName) => {
    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp'];
    const extension = fileName.toLowerCase().substring(fileName.lastIndexOf('.'));
    return imageExtensions.includes(extension);
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Camera methods
const captureFromCamera = async () => {
    try {
        showCameraModal.value = true;
        await nextTick();
        
        stream.value = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                facingMode: 'environment' // Use back camera if available
            } 
        });
        
        if (videoElement.value) {
            videoElement.value.srcObject = stream.value;
        }
    } catch (error) {
        console.error('Error accessing camera:', error);
        alert('Tidak dapat mengakses kamera. Silakan periksa izin kamera.');
        showCameraModal.value = false;
    }
};

const capturePhoto = () => {
    if (videoElement.value) {
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        
        canvas.width = videoElement.value.videoWidth;
        canvas.height = videoElement.value.videoHeight;
        
        context.drawImage(videoElement.value, 0, 0);
        capturedImage.value = canvas.toDataURL('image/jpeg', 0.8);
        
        // Stop the camera stream
        if (stream.value) {
            stream.value.getTracks().forEach(track => track.stop());
        }
    }
};

const useCapturedPhoto = () => {
    if (capturedImage.value) {
        // Convert data URL to File object
        const byteString = atob(capturedImage.value.split(',')[1]);
        const mimeString = capturedImage.value.split(',')[0].split(':')[1].split(';')[0];
        const ab = new ArrayBuffer(byteString.length);
        const ia = new Uint8Array(ab);
        
        for (let i = 0; i < byteString.length; i++) {
            ia[i] = byteString.charCodeAt(i);
        }
        
        const file = new File([ab], `camera_${Date.now()}.jpg`, { type: mimeString });
        
        // Add to appropriate file list based on context
        if (showNewConversationModal.value) {
            newConversationFiles.value.push(file);
        } else {
            selectedFiles.value.push(file);
        }
        
        closeCamera();
    }
};

const retakePhoto = () => {
    capturedImage.value = '';
    captureFromCamera();
};

const closeCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
    }
    showCameraModal.value = false;
    capturedImage.value = '';
    stream.value = null;
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    
    if (diff < 60000) return 'Just now';
    if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
    if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
    if (diff < 604800000) return `${Math.floor(diff / 86400000)}d ago`;
    
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
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

// Lifecycle
onMounted(() => {
    // Check for unread messages on page load
    fetchConversations();
    
    // Load saved widget position
    loadWidgetPosition();
    
    // Handle window resize to keep widget in bounds
    window.addEventListener('resize', handleWindowResize);
    
    // Handle orientation change on mobile
    window.addEventListener('orientationchange', () => {
        // Small delay to allow viewport to update
        setTimeout(handleWindowResize, 100);
    });
});

const handleWindowResize = () => {
    // Re-validate position after window resize
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const widgetWidth = 80;
    const widgetHeight = 80;
    
    const newX = Math.max(0, Math.min(widgetPosition.value.x, viewportWidth - widgetWidth));
    const newY = Math.max(0, Math.min(widgetPosition.value.y, viewportHeight - widgetHeight));
    
    // Only update if position actually changed
    if (newX !== widgetPosition.value.x || newY !== widgetPosition.value.y) {
        widgetPosition.value = { x: newX, y: newY };
        saveWidgetPosition();
    }
};

onUnmounted(() => {
    stopPolling();
    
    // Remove event listeners
    window.removeEventListener('resize', handleWindowResize);
    window.removeEventListener('orientationchange', handleWindowResize);
    document.removeEventListener('mousemove', handleDrag);
    document.removeEventListener('mouseup', stopDrag);
    document.removeEventListener('touchmove', handleDrag);
    document.removeEventListener('touchend', stopDrag);
    
    // Cleanup camera stream
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
    }
    
    // Cleanup file URLs
    selectedFiles.value.forEach(file => {
        if (file.type.startsWith('image/')) {
            URL.revokeObjectURL(getImageSrc(file));
        }
    });
    
    newConversationFiles.value.forEach(file => {
        if (file.type.startsWith('image/')) {
            URL.revokeObjectURL(getImageSrc(file));
        }
    });
});
</script>

<style scoped>
/* Custom scrollbar */
.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 2px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Draggable widget styles */
.fixed {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    touch-action: manipulation;
}

/* Prevent text selection during drag */
.fixed * {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}

/* Mobile touch improvements */
@media (hover: none) and (pointer: coarse) {
    .fixed {
        touch-action: manipulation;
    }
    
    .fixed button {
        touch-action: manipulation;
    }
}

/* Smooth transitions for non-dragging interactions */
.fixed button {
    transition: all 0.2s ease;
}

.fixed {
    transition: transform 0.15s ease;
}

/* Disable transitions during drag for better performance */
.fixed.dragging * {
    transition: none !important;
}
</style>
