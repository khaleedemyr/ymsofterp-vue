<template>
    <AppLayout>
        <Head title="Live Support Admin" />
        <div class="relative min-h-[calc(100vh-4rem)] overflow-hidden">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-slate-100 via-indigo-50/70 to-sky-100"></div>
            <div class="pointer-events-none absolute -top-24 -left-16 h-72 w-72 rounded-full bg-indigo-400/20 blur-3xl"></div>
            <div class="pointer-events-none absolute top-24 right-0 h-80 w-80 rounded-full bg-sky-400/20 blur-3xl"></div>
            <div class="pointer-events-none absolute bottom-0 left-1/3 h-64 w-64 rounded-full bg-violet-300/20 blur-3xl"></div>

            <div class="relative py-6">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mb-6 overflow-hidden rounded-3xl border border-white/70 bg-white/70 p-6 shadow-[0_24px_60px_-28px_rgba(15,23,42,0.45)] backdrop-blur-xl">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-4">
                                <div class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-sky-500 text-white shadow-[0_12px_24px_-8px_rgba(79,70,229,0.8)]">
                                    <div class="absolute inset-0 rounded-2xl bg-white/20"></div>
                                    <svg class="relative h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">Live Support Admin Panel</h1>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ mentionOnly ? 'Percakapan yang menandai Anda' : 'Kelola percakapan customer dengan cepat dan rapi' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/80 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-inner">
                                    {{ totalConversations }} percakapan
                                </div>
                                <button
                                    @click="refreshData"
                                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-sky-500 text-white shadow-[0_10px_20px_-8px_rgba(79,70,229,0.9)] transition hover:-translate-y-0.5 hover:shadow-[0_16px_28px_-10px_rgba(14,165,233,0.8)]"
                                    :class="{ 'animate-spin': refreshing }"
                                    title="Refresh"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="!mentionOnly" class="mb-6 rounded-3xl border border-white/70 bg-white/75 p-5 shadow-[0_18px_40px_-24px_rgba(15,23,42,0.35)] backdrop-blur-xl">
                        <div class="mb-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div class="lg:col-span-2">
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Cari</label>
                                <div class="relative">
                                    <input
                                        v-model="filters.search"
                                        @input="debouncedSearch"
                                        type="text"
                                        placeholder="Cari percakapan, user, pesan..."
                                        class="w-full rounded-2xl border border-slate-200 bg-white/90 py-2.5 pl-11 pr-4 text-sm shadow-inner outline-none transition focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                                    >
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                                        <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Dari tanggal</label>
                                <input
                                    v-model="filters.dateFrom"
                                    @change="applyFilters"
                                    type="date"
                                    class="w-full rounded-2xl border border-slate-200 bg-white/90 px-3 py-2.5 text-sm shadow-inner outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                                >
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sampai tanggal</label>
                                <input
                                    v-model="filters.dateTo"
                                    @change="applyFilters"
                                    type="date"
                                    class="w-full rounded-2xl border border-slate-200 bg-white/90 px-3 py-2.5 text-sm shadow-inner outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                                >
                            </div>
                        </div>

                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                                <select v-model="filters.status" @change="applyFilters" class="rounded-2xl border border-slate-200 bg-white/90 px-3 py-2.5 text-sm shadow-inner outline-none focus:border-indigo-400">
                                    <option value="all">Semua status</option>
                                    <option value="open">Open</option>
                                    <option value="pending">Pending</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Prioritas</label>
                                <select v-model="filters.priority" @change="applyFilters" class="rounded-2xl border border-slate-200 bg-white/90 px-3 py-2.5 text-sm shadow-inner outline-none focus:border-indigo-400">
                                    <option value="all">Semua prioritas</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">Per halaman</label>
                                <select v-model="filters.perPage" @change="applyFilters" class="rounded-2xl border border-slate-200 bg-white/90 px-3 py-2.5 text-sm shadow-inner outline-none focus:border-indigo-400">
                                    <option value="10">10</option>
                                    <option value="15">15</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                            <button
                                @click="clearFilters"
                                class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-50"
                            >
                                Reset filter
                            </button>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/70 bg-white/70 p-4 shadow-[0_24px_50px_-28px_rgba(15,23,42,0.4)] backdrop-blur-xl sm:p-6">
                        <div class="space-y-3">
                            <div
                                v-for="conversation in filteredConversations"
                                :key="conversation.id"
                                @click="selectConversation(conversation)"
                                class="group relative cursor-pointer overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-[0_10px_24px_-16px_rgba(15,23,42,0.45)] transition duration-300 hover:-translate-y-1 hover:border-indigo-200 hover:shadow-[0_22px_40px_-18px_rgba(79,70,229,0.45)]"
                                :class="{ 'ring-2 ring-indigo-400/70': selectedConversation?.id === conversation.id }"
                            >
                                <div class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white to-transparent opacity-80"></div>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="mb-2 flex flex-wrap items-center gap-2">
                                            <h3 class="truncate text-base font-semibold text-slate-900">{{ getSubjectWithIcon(conversation.subject) }}</h3>
                                            <span :class="getStatusColor(conversation.status)" class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide">
                                                {{ conversation.status }}
                                            </span>
                                            <span :class="getPriorityColor(conversation.priority)" class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide">
                                                {{ conversation.priority }}
                                            </span>
                                        </div>
                                        <p class="mb-1 text-sm font-medium text-slate-700">{{ conversation.customer_name }} <span class="font-normal text-slate-400">({{ conversation.customer_email }})</span></p>
                                        <div class="mb-2 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                            <span v-if="conversation.customer_outlet" class="inline-flex items-center gap-1">
                                                <i class="fas fa-store"></i>
                                                {{ conversation.customer_outlet }}
                                            </span>
                                            <span v-if="conversation.customer_divisi" class="inline-flex items-center gap-1">
                                                <i class="fas fa-building"></i>
                                                {{ conversation.customer_divisi }}
                                            </span>
                                            <span v-if="conversation.customer_jabatan" class="inline-flex items-center gap-1">
                                                <i class="fas fa-user-tie"></i>
                                                {{ conversation.customer_jabatan }}
                                            </span>
                                        </div>
                                        <p class="truncate text-sm text-slate-600">{{ conversation.last_message }}</p>
                                        <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-slate-400">
                                            <span>Created: {{ formatDate(conversation.created_at) }}</span>
                                            <span>Last: {{ formatDate(conversation.last_message_at) }}</span>
                                            <span v-if="conversation.last_sender_type === 'admin'" class="font-medium text-emerald-600">
                                                Replied by: {{ conversation.last_sender_name }}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        v-if="conversation.unread_count > 0"
                                        class="flex h-8 min-w-8 items-center justify-center rounded-full bg-gradient-to-br from-rose-500 to-orange-400 px-2 text-xs font-bold text-white shadow-[0_8px_16px_-6px_rgba(244,63,94,0.9)]"
                                    >
                                        {{ conversation.unread_count }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="filteredConversations.length === 0" class="py-14 text-center text-slate-500">
                                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-3xl bg-slate-100 shadow-inner">
                                    <svg class="h-8 w-8 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                    </svg>
                                </div>
                                <p>Tidak ada percakapan</p>
                            </div>
                        </div>

                        <div v-if="pagination && pagination.last_page > 1" class="mt-6 flex flex-col items-center justify-between gap-3 sm:flex-row">
                            <div class="text-sm text-slate-500">
                                Menampilkan {{ pagination.from }}–{{ pagination.to }} dari {{ pagination.total }}
                            </div>
                            <div class="flex items-center space-x-2">
                                <button
                                    @click="goToPage(pagination.current_page - 1)"
                                    :disabled="pagination.current_page <= 1"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Previous
                                </button>
                                <template v-for="page in getPageNumbers()" :key="page">
                                    <button
                                        v-if="page !== '...'"
                                        @click="goToPage(page)"
                                        :class="[
                                            'rounded-xl px-3 py-2 text-sm font-medium shadow-sm',
                                            page === pagination.current_page
                                                ? 'bg-gradient-to-br from-indigo-600 to-sky-500 text-white'
                                                : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                                        ]"
                                    >
                                        {{ page }}
                                    </button>
                                    <span v-else class="px-3 py-2 text-sm text-slate-400">...</span>
                                </template>
                                <button
                                    @click="goToPage(pagination.current_page + 1)"
                                    :disabled="pagination.current_page >= pagination.last_page"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showConversationModal && selectedConversation" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
            <div class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-3xl border border-white/40 bg-white/95 shadow-[0_40px_80px_-28px_rgba(15,23,42,0.65)]">
                <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50/80 to-sky-50/70 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-slate-900">{{ selectedConversation.subject }}</h2>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ selectedConversation.customer_name }}
                                <span class="text-slate-400">({{ selectedConversation.customer_email }})</span>
                            </p>
                            <div class="mt-2 flex flex-wrap items-center gap-4 text-xs text-slate-500">
                                <span v-if="selectedConversation.customer_outlet" class="inline-flex items-center gap-1">
                                    <i class="fas fa-store"></i>
                                    {{ selectedConversation.customer_outlet }}
                                </span>
                                <span v-if="selectedConversation.customer_divisi" class="inline-flex items-center gap-1">
                                    <i class="fas fa-building"></i>
                                    {{ selectedConversation.customer_divisi }}
                                </span>
                                <span v-if="selectedConversation.customer_jabatan" class="inline-flex items-center gap-1">
                                    <i class="fas fa-user-tie"></i>
                                    {{ selectedConversation.customer_jabatan }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <template v-if="!mentionOnly">
                                <select v-model="selectedConversation.status" @change="updateConversationStatus" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm shadow-inner">
                                    <option value="open">Open</option>
                                    <option value="pending">Pending</option>
                                    <option value="closed">Closed</option>
                                </select>
                                <select v-model="selectedConversation.priority" @change="updateConversationPriority" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-sm shadow-inner">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </template>
                            <button @click="closeConversationModal" class="rounded-xl p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex-1 space-y-4 overflow-y-auto bg-[radial-gradient(circle_at_top_right,rgba(99,102,241,0.08),transparent_32%),radial-gradient(circle_at_bottom_left,rgba(14,165,233,0.08),transparent_28%)] p-5">
                    <div
                        v-for="message in messages"
                        :key="message.id"
                        class="flex"
                        :class="message.sender_type === 'admin' ? 'justify-end' : 'justify-start'"
                    >
                        <div class="max-w-xs lg:max-w-md">
                            <div class="flex items-end gap-2" :class="message.sender_type === 'admin' ? 'flex-row-reverse' : 'flex-row'">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-slate-200 shadow-md">
                                    <img
                                        v-if="message.sender_avatar"
                                        :src="`/storage/${message.sender_avatar}`"
                                        class="h-8 w-8 rounded-full object-cover"
                                        alt="Avatar"
                                    >
                                    <span v-else class="text-xs font-medium text-slate-600">
                                        {{ getInitials(message.sender_name) }}
                                    </span>
                                </div>
                                <div
                                    class="rounded-2xl px-3.5 py-2.5 shadow-[0_10px_20px_-12px_rgba(15,23,42,0.45)]"
                                    :class="message.sender_type === 'admin'
                                        ? 'rounded-br-md bg-gradient-to-br from-indigo-600 to-sky-500 text-white'
                                        : 'rounded-bl-md border border-white/80 bg-white text-slate-800'"
                                >
                                    <div
                                        v-if="message.sender_type === 'admin' && message.sender_name"
                                        class="mb-1 text-xs font-medium text-indigo-100"
                                    >
                                        {{ message.sender_name }}
                                    </div>
                                    <p class="whitespace-pre-wrap text-sm" v-html="renderMessageHtml(message.message, message.sender_type === 'admin')"></p>
                                    <div v-if="message.file_path" class="mt-2">
                                        <div v-for="(file, index) in getFileAttachments(message.file_path)" :key="`file-${message.id}-${index}`" class="mb-2">
                                            <div v-if="file && file.original_name && file.file_path && isImageFile(file.original_name)" class="relative">
                                                <img
                                                    :src="`/storage/${file.file_path}`"
                                                    @click="openLightbox(`/storage/${file.file_path}`)"
                                                    class="h-32 max-w-full cursor-pointer rounded-xl object-cover transition hover:opacity-80"
                                                    :alt="file.original_name || 'Attachment'"
                                                    @error="handleImageError"
                                                >
                                            </div>
                                            <div v-else-if="file && file.original_name && file.file_path" class="flex items-center gap-2 rounded-xl bg-black/10 p-2">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                <span class="flex-1 truncate text-xs">{{ file.original_name }}</span>
                                                <a :href="`/storage/${file.file_path}`" target="_blank" class="text-xs hover:underline">
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                    </svg>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs opacity-75">{{ formatTime(message.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="!mentionOnly" class="border-t border-slate-100 bg-white/90 p-4">
                    <div v-if="selectedFiles.length > 0" class="mb-3">
                        <div class="flex flex-wrap gap-2">
                            <div v-for="(file, index) in selectedFiles" :key="index" class="flex items-center gap-2 rounded-xl bg-slate-100 p-2">
                                <div v-if="file.type.startsWith('image/')" class="relative">
                                    <img :src="getImageSrc(file)" @click="openLightbox(getImageSrc(file))" class="h-8 w-8 cursor-pointer rounded object-cover hover:opacity-80" alt="Thumbnail">
                                </div>
                                <svg v-else class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <span class="text-xs text-slate-700">{{ file.name }}</span>
                                <button @click="removeFile(index)" class="text-rose-500 hover:text-rose-700">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="pendingMentions.length" class="mb-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="user in pendingMentions"
                            :key="user.id"
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700"
                        >
                            @{{ user.name }}
                            <button type="button" class="text-indigo-400 hover:text-indigo-700" @click="removePendingMention(user.id)">×</button>
                        </span>
                    </div>

                    <div class="flex items-end gap-2">
                        <input
                            ref="fileInput"
                            @change="handleFileUpload"
                            type="file"
                            multiple
                            accept="image/*,.pdf,.doc,.docx,.txt,.xlsx,.xls"
                            class="hidden"
                        >
                        <button @click="$refs.fileInput.click()" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                        </button>
                        <button @click="captureFromCamera" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-200 text-slate-500 transition hover:bg-slate-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </button>

                        <div class="relative min-w-0 flex-1">
                            <div
                                v-if="mentionMenuOpen && mentionUsers.length > 0"
                                class="absolute bottom-full left-0 right-0 z-30 mb-2 max-h-56 overflow-y-auto rounded-2xl border border-indigo-100 bg-white py-1 shadow-2xl"
                            >
                                <p class="border-b border-indigo-50 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-indigo-600">
                                    Mention user (@)
                                </p>
                                <button
                                    v-for="(user, idx) in mentionUsers"
                                    :key="user.id"
                                    type="button"
                                    class="flex w-full items-start gap-2 px-3 py-2 text-left hover:bg-indigo-50"
                                    :class="idx === mentionHighlight ? 'bg-indigo-50' : ''"
                                    @mousedown.prevent="applyMention(user)"
                                >
                                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">
                                        {{ getInitials(user.name) }}
                                    </div>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-slate-900">{{ user.name }}</span>
                                        <span class="mt-0.5 block truncate text-xs text-slate-500">{{ mentionUserMeta(user) }}</span>
                                    </span>
                                </button>
                            </div>
                            <p
                                v-else-if="mentionMenuOpen && !mentionLoading"
                                class="absolute bottom-full left-0 mb-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                            >
                                Tidak ada user yang cocok.
                            </p>
                            <textarea
                                v-model="replyMessage"
                                rows="2"
                                placeholder="Tulis balasan... ketik @ untuk mention user"
                                class="w-full resize-none rounded-2xl border border-slate-200 bg-white px-3 py-2.5 text-sm shadow-inner outline-none focus:border-indigo-400 focus:ring-4 focus:ring-indigo-100"
                                @input="onReplyInput"
                                @keydown="onReplyKeydown"
                            ></textarea>
                        </div>

                        <button
                            @click="sendReply"
                            :disabled="(!replyMessage.trim() && selectedFiles.length === 0) || sending"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-600 to-sky-500 text-white shadow-[0_10px_20px_-8px_rgba(79,70,229,0.9)] disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <svg v-if="sending" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        </button>
                    </div>
                    <p class="mt-2 text-[11px] text-slate-400">Ketik @nama lalu pilih user. Orang yang di-mention akan menerima notifikasi.</p>
                </div>
                <div v-else class="border-t border-slate-100 bg-slate-50 px-5 py-3 text-center text-xs text-slate-500">
                    Anda di-mention pada percakapan ini. Mode lihat saja.
                </div>
            </div>
        </div>

        <div v-if="showLightbox" class="fixed inset-0 z-50 flex items-center justify-center bg-black/90" @click="closeLightbox">
            <div class="relative max-h-full max-w-4xl p-4">
                <button @click="closeLightbox" class="absolute right-2 top-2 z-10 text-2xl text-white hover:text-gray-300">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                <img :src="lightboxImage" class="max-h-full max-w-full rounded-lg object-contain" @click.stop alt="Full size image">
            </div>
        </div>

        <div v-if="showCameraModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/90">
            <div class="relative mx-4 w-full max-w-md rounded-2xl bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold">Capture Photo</h3>
                    <button @click="closeCamera" class="text-gray-500 hover:text-gray-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="mb-4">
                    <video ref="cameraVideo" autoplay playsinline class="h-64 w-full rounded-lg bg-gray-200 object-cover" v-show="!capturedPhoto"></video>
                    <div v-if="capturedPhoto" class="flex h-64 w-full items-center justify-center rounded-lg bg-gray-200">
                        <img :src="capturedPhoto" class="h-full w-full rounded-lg object-cover" alt="Captured photo">
                    </div>
                </div>
                <div class="flex justify-center gap-2">
                    <button v-if="!capturedPhoto" @click="capturePhoto" class="rounded-md bg-blue-500 px-4 py-2 text-white hover:bg-blue-600">Capture</button>
                    <button v-if="capturedPhoto" @click="useCapturedPhoto" class="rounded-md bg-green-500 px-4 py-2 text-white hover:bg-green-600">Use Photo</button>
                    <button v-if="capturedPhoto" @click="retakePhoto" class="rounded-md bg-gray-500 px-4 py-2 text-white hover:bg-gray-600">Retake</button>
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

const props = defineProps({
    openConversationId: { type: [Number, String], default: null },
    mentionOnly: { type: Boolean, default: false },
});

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
const selectedFiles = ref([]);
const showCameraModal = ref(false);
const capturedPhoto = ref(null);
const cameraStream = ref(null);

const mentionMenuOpen = ref(false);
const mentionQuery = ref('');
const mentionUsers = ref([]);
const mentionHighlight = ref(0);
const mentionLoading = ref(false);
const mentionTimeout = ref(null);
const pendingMentions = ref([]);

const filters = ref({
    status: 'all',
    priority: 'all',
    search: '',
    dateFrom: '',
    dateTo: '',
    perPage: 15,
    page: 1
});

const totalConversations = computed(() => pagination.value?.total || 0);
const filteredConversations = computed(() => conversations.value);

const fetchConversations = async (extra = {}) => {
    try {
        refreshing.value = true;
        const params = new URLSearchParams();
        const keyMap = { dateFrom: 'date_from', dateTo: 'date_to', perPage: 'per_page' };

        Object.keys(filters.value).forEach(key => {
            if (filters.value[key] && filters.value[key] !== 'all') {
                params.append(keyMap[key] || key, filters.value[key]);
            }
        });

        if (extra.conversation) {
            params.set('conversation', extra.conversation);
        } else if (props.mentionOnly && props.openConversationId) {
            params.set('conversation', String(props.openConversationId));
        }

        const response = await axios.get(`/api/support/admin/conversations?${params.toString()}`);

        if (response.data.data) {
            conversations.value = response.data.data;
            pagination.value = response.data.pagination;
        } else {
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
        filters.value.page = 1;
        applyFilters();
    }, 500);
};

const applyFilters = () => {
    filters.value.page = 1;
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
    } else if (current <= 4) {
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

        selectedFiles.value.forEach((file, index) => {
            formData.append(`files[${index}]`, file);
        });

        pendingMentions.value.forEach((user) => {
            formData.append('mentioned_user_ids[]', String(user.id));
        });

        const response = await axios.post(`/api/support/admin/conversations/${selectedConversation.value.id}/reply`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        messages.value.push(response.data);
        replyMessage.value = '';
        selectedFiles.value = [];
        pendingMentions.value = [];
        mentionMenuOpen.value = false;

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

const searchMentionUsers = async (query) => {
    mentionLoading.value = true;
    try {
        const response = await axios.get('/api/support/admin/mention-users', { params: { q: query } });
        mentionUsers.value = response.data.users || [];
        mentionHighlight.value = 0;
    } catch (error) {
        mentionUsers.value = [];
    } finally {
        mentionLoading.value = false;
    }
};

const onReplyInput = () => {
    const match = replyMessage.value.match(/@([^\n@]*)$/);
    if (!match) {
        mentionMenuOpen.value = false;
        mentionQuery.value = '';
        return;
    }

    mentionMenuOpen.value = true;
    mentionQuery.value = match[1].trim();
    if (mentionTimeout.value) clearTimeout(mentionTimeout.value);
    mentionTimeout.value = setTimeout(() => {
        searchMentionUsers(mentionQuery.value);
    }, 220);
};

const onReplyKeydown = (e) => {
    if (mentionMenuOpen.value && mentionUsers.value.length > 0) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            mentionHighlight.value = Math.min(mentionHighlight.value + 1, mentionUsers.value.length - 1);
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            mentionHighlight.value = Math.max(mentionHighlight.value - 1, 0);
            return;
        }
        if (e.key === 'Escape') {
            e.preventDefault();
            mentionMenuOpen.value = false;
            return;
        }
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            applyMention(mentionUsers.value[mentionHighlight.value]);
            return;
        }
    }

    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendReply();
    }
};

const applyMention = (user) => {
    if (!user) return;
    replyMessage.value = replyMessage.value.replace(/@([^\n@]*)$/, `@${user.name} `);
    if (!pendingMentions.value.some((item) => Number(item.id) === Number(user.id))) {
        pendingMentions.value.push({ id: user.id, name: user.name });
    }
    mentionMenuOpen.value = false;
    mentionUsers.value = [];
};

const removePendingMention = (id) => {
    pendingMentions.value = pendingMentions.value.filter((user) => Number(user.id) !== Number(id));
};

const mentionUserMeta = (user) => {
    return [user.jabatan, user.outlet, user.email].filter(Boolean).join(' · ');
};

const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');

const renderMessageHtml = (text, isAdmin) => {
    const cls = isAdmin
        ? 'font-semibold bg-white/20 text-white px-1.5 py-0.5 rounded-md'
        : 'font-semibold bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded-md';
    return escapeHtml(text).replace(
        /@([A-Za-zÀ-ÿ0-9._'-]+(?:\s+[A-Za-zÀ-ÿ0-9._'-]+){0,4})/g,
        `<span class="${cls}">@$1</span>`
    );
};

const handleFileUpload = (event) => {
    const files = Array.from(event.target.files);
    files.forEach(file => {
        if (file.size <= 10 * 1024 * 1024) {
            selectedFiles.value.push(file);
        } else {
            alert(`File ${file.name} is too large. Maximum size is 10MB.`);
        }
    });
    event.target.value = '';
};

const removeFile = (index) => {
    selectedFiles.value.splice(index, 1);
};

const getImageSrc = (file) => {
    return URL.createObjectURL(file);
};

const captureFromCamera = async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment'
            }
        });

        cameraStream.value = stream;
        showCameraModal.value = true;

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
    pendingMentions.value = [];
    mentionMenuOpen.value = false;
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
        open: 'bg-emerald-100 text-emerald-700 shadow-[inset_0_1px_0_rgba(255,255,255,0.7)]',
        closed: 'bg-slate-100 text-slate-600',
        pending: 'bg-amber-100 text-amber-700'
    };
    return colors[status] || 'bg-slate-100 text-slate-600';
};

const getPriorityColor = (priority) => {
    const colors = {
        low: 'bg-sky-100 text-sky-700',
        medium: 'bg-amber-100 text-amber-700',
        high: 'bg-orange-100 text-orange-700',
        urgent: 'bg-rose-100 text-rose-700 shadow-[0_0_12px_rgba(244,63,94,0.25)]'
    };
    return colors[priority] || 'bg-slate-100 text-slate-600';
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

    if (Array.isArray(filePath)) {
        return filePath;
    }

    if (typeof filePath === 'string') {
        try {
            const parsed = JSON.parse(filePath);
            if (Array.isArray(parsed)) {
                return parsed;
            }
            if (parsed && typeof parsed === 'object') {
                return [parsed];
            }
        } catch (e) {
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
    event.target.style.display = 'none';
};

const isImageFile = (fileName) => {
    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.bmp', '.webp'];
    const extension = fileName.toLowerCase().substring(fileName.lastIndexOf('.'));
    return imageExtensions.includes(extension);
};

const openFromQuery = async () => {
    const fromQuery = Number(new URLSearchParams(window.location.search).get('conversation') || 0);
    const targetId = Number(props.openConversationId || fromQuery || 0);
    if (!targetId) return;

    let conv = conversations.value.find((item) => Number(item.id) === targetId);
    if (!conv) {
        await fetchConversations({ conversation: targetId });
        conv = conversations.value.find((item) => Number(item.id) === targetId);
        if (!props.mentionOnly) {
            await fetchConversations();
            conv = conversations.value.find((item) => Number(item.id) === targetId) || conv;
        }
    }
    if (conv) {
        await selectConversation(conv);
    }
};

onMounted(async () => {
    await fetchConversations();
    await openFromQuery();
});

onUnmounted(() => {
    if (cameraStream.value) {
        cameraStream.value.getTracks().forEach(track => track.stop());
    }
});
</script>
