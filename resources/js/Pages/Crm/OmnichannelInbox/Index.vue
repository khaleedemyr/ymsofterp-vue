<template>
  <AppLayout>
    <div class="flex h-[calc(100vh-7rem)] min-h-[520px] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:flex-row">
      <div class="border-b border-slate-200 bg-white p-2 lg:hidden">
        <div class="grid grid-cols-4 gap-1">
          <button
            type="button"
            class="rounded-md px-2 py-1.5 text-[11px] font-medium"
            :class="mobilePanel === 'filters' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700'"
            @click="mobilePanel = 'filters'"
          >
            Filter
          </button>
          <button
            type="button"
            class="rounded-md px-2 py-1.5 text-[11px] font-medium"
            :class="mobilePanel === 'list' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700'"
            @click="mobilePanel = 'list'"
          >
            Daftar
          </button>
          <button
            type="button"
            class="rounded-md px-2 py-1.5 text-[11px] font-medium disabled:opacity-50"
            :class="mobilePanel === 'chat' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700'"
            :disabled="!selectedConversation"
            @click="mobilePanel = 'chat'"
          >
            Chat
          </button>
          <button
            type="button"
            class="rounded-md px-2 py-1.5 text-[11px] font-medium disabled:opacity-50"
            :class="mobilePanel === 'detail' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700'"
            :disabled="!selectedConversation"
            @click="mobilePanel = 'detail'"
          >
            Detail
          </button>
        </div>
      </div>
      <!-- Kolom 1: filter inbox & lifecycle -->
      <nav
        class="w-full shrink-0 flex-col border-r border-slate-200 bg-slate-50 lg:flex lg:w-52"
        :class="mobilePanel === 'filters' ? 'flex' : 'hidden'"
      >
        <div class="border-b border-slate-200 p-3">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Inbox</p>
          <div class="mt-2 space-y-1">
            <button
              v-for="opt in inboxMenuOptions"
              :key="opt.value"
              type="button"
              class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm"
              :class="inbox === opt.value ? 'bg-white font-medium text-emerald-800 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:bg-white/80'"
              :title="opt.hint || undefined"
              @click="setInbox(opt.value)"
            >
              <i :class="opt.icon" class="w-4 text-center text-slate-400" />
              {{ opt.label }}
            </button>
          </div>
        </div>
        <div class="border-b border-slate-200 p-3">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Kanal</p>
          <div class="mt-2 space-y-1">
            <button
              v-for="opt in channelMenuOptions"
              :key="opt.value ?? 'all'"
              type="button"
              class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm"
              :class="channelFilter === opt.value ? 'bg-white font-medium text-emerald-800 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:bg-white/80'"
              @click="setChannelFilter(opt.value)"
            >
              <i :class="[opt.icon, 'w-4 text-center', opt.iconColor || 'text-slate-400']" />
              {{ opt.label }}
            </button>
          </div>
        </div>
        <div class="flex-1 overflow-y-auto p-3">
          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Tahap lead</p>
          <div class="mt-2 space-y-0.5">
            <button
              type="button"
              class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs"
              :class="!leadStageFilter ? 'bg-emerald-50 font-medium text-emerald-900' : 'text-slate-600 hover:bg-white'"
              @click="setLeadStage(null)"
            >
              <span class="h-2 w-2 shrink-0 rounded-full bg-slate-300" />
              Semua tahap
            </button>
            <button
              v-for="st in leadStages"
              :key="st.value"
              type="button"
              class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs"
              :class="leadStageFilter === st.value ? 'bg-emerald-50 font-medium text-emerald-900' : 'text-slate-600 hover:bg-white'"
              @click="setLeadStage(st.value)"
            >
              <span class="h-2 w-2 shrink-0 rounded-full" :class="stageDotClass(st.color)" />
              {{ st.label }}
            </button>
          </div>
        </div>
      </nav>

      <!-- Kolom 2: daftar percakapan -->
      <aside
        class="w-full shrink-0 flex-col border-r border-slate-200 bg-white lg:flex lg:w-72"
        :class="mobilePanel === 'list' ? 'flex' : 'hidden'"
      >
        <div class="border-b border-slate-200 px-3 py-2">
          <input
            v-model="search"
            type="text"
            placeholder="Cari nama / nomor..."
            class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
          />
        </div>
        <div class="flex-1 overflow-y-auto">
          <button
            v-for="conv in filteredConversations"
            :key="conv.id"
            type="button"
            class="flex w-full gap-2 border-b border-slate-100 px-3 py-2.5 text-left hover:bg-slate-50"
            :class="selectedId === conv.id ? 'bg-emerald-50/80 ring-1 ring-inset ring-emerald-200' : ''"
            @click.stop="selectConversation(conv.id)"
          >
            <div
              class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full"
              :class="channelAvatarClass(conv.channel)"
            >
              <img
                v-if="conv.contact_avatar_url"
                :src="conv.contact_avatar_url"
                alt=""
                class="h-full w-full object-cover"
              />
              <i v-else :class="channelIcon(conv.channel)" class="text-sm" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex w-full items-center justify-between gap-1">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1">
                  <span class="truncate text-sm font-medium text-slate-900">{{ conv.contact_name || conv.display_phone }}</span>
                  <span
                    v-if="conv.channel_account_label"
                    class="inline-flex shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-semibold ring-1"
                    :class="channelAccountBadgeClass(conv.channel)"
                    :title="channelAccountBadgeTitle(conv)"
                  >
                    {{ conv.channel_account_label }}
                  </span>
                  <span
                    v-if="conv.member"
                    class="inline-flex shrink-0 items-center gap-0.5 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800 ring-1 ring-emerald-200"
                    title="Terdaftar di member app"
                  >
                    Member<span v-if="conv.member.member_level"> · {{ formatMemberTier(conv.member.member_level) }}</span>
                    <span v-if="conv.member.is_exclusive_member" class="text-amber-600" title="Eksklusif">★</span>
                  </span>
                </div>
                <span v-if="conv.unread_count > 0" class="shrink-0 rounded-full bg-emerald-600 px-1.5 text-[10px] font-bold text-white">
                  {{ conv.unread_count }}
                </span>
              </div>
              <p class="truncate text-[11px] text-slate-500">{{ conv.display_phone }}</p>
              <p class="truncate text-[11px] text-slate-400">{{ conv.last_message_preview || '—' }}</p>
              <p v-if="conv.assignees?.length" class="truncate text-[10px] text-indigo-600">
                {{ conv.assignees.map((a) => formatUserOptionLabel(a)).join(', ') }}
              </p>
              <p v-if="conv.assigned_teams?.length" class="truncate text-[10px] text-sky-700">
                <i class="fa-solid fa-people-group mr-0.5" />
                {{ conv.assigned_teams.map((t) => t.name).join(', ') }}
              </p>
            </div>
          </button>
          <p v-if="filteredConversations.length === 0" class="px-3 py-6 text-center text-xs text-slate-400">
            {{ emptyListHint }}
          </p>
        </div>
      </aside>

      <!-- Kolom 3: thread chat -->
      <section
        class="min-w-0 flex-1 flex-col bg-[#e5ddd5] lg:flex"
        :class="mobilePanel === 'chat' ? 'flex' : 'hidden'"
      >
        <template v-if="selectedConversation">
          <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-white px-4 py-2">
            <div class="flex min-w-0 flex-1 items-center gap-2">
              <button
                type="button"
                class="mr-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 lg:hidden"
                @click="mobilePanel = 'list'"
              >
                <i class="fa-solid fa-arrow-left" />
              </button>
              <div
                class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full"
                :class="channelAvatarClass(selectedConversation.channel)"
              >
                <img
                  v-if="selectedConversation.contact_avatar_url"
                  :src="selectedConversation.contact_avatar_url"
                  alt=""
                  class="h-full w-full object-cover"
                />
                <i v-else :class="channelIcon(selectedConversation.channel)" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="truncate font-semibold text-slate-900">
                    {{ selectedConversation.contact_name || selectedConversation.display_phone }}
                  </p>
                  <span
                    v-if="selectedConversation.channel_account_label"
                    class="inline-flex shrink-0 rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1"
                    :class="channelAccountBadgeClass(selectedConversation.channel)"
                    :title="channelAccountBadgeTitle(selectedConversation)"
                  >
                    {{ selectedConversation.channel_account_label }}
                  </span>
                  <span
                    v-if="selectedConversation.member"
                    class="inline-flex shrink-0 items-center gap-0.5 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 ring-1 ring-emerald-200"
                  >
                    Member<span v-if="selectedConversation.member.member_level"> · {{ formatMemberTier(selectedConversation.member.member_level) }}</span>
                    <span v-if="selectedConversation.member.is_exclusive_member" class="text-amber-600" title="Eksklusif">★</span>
                  </span>
                </div>
                <p class="truncate text-xs text-slate-500">
                  {{ selectedConversation.display_phone }}
                  <span v-if="selectedConversation.member" class="text-emerald-600"> · {{ selectedConversation.member.nama_lengkap }}</span>
                </p>
                <p v-if="selectedConversation.assignees?.length" class="mt-0.5 truncate text-[10px] text-indigo-700">
                  <i class="fa-solid fa-user-tag mr-0.5" />
                  {{ selectedConversation.assignees.map((a) => formatUserOptionLabel(a)).join(', ') }}
                </p>
                <p v-if="selectedConversation.assigned_teams?.length" class="mt-0.5 truncate text-[10px] text-sky-800">
                  <i class="fa-solid fa-people-group mr-0.5" />
                  {{ selectedConversation.assigned_teams.map((t) => t.name).join(', ') }}
                </p>
              </div>
            </div>
            <button
              v-if="selectedConversation.active_flow && !selectedConversation.automation_paused"
              type="button"
              class="shrink-0 rounded-lg border border-violet-200 bg-violet-50 px-2 py-1 text-[11px] font-medium text-violet-800 hover:bg-violet-100"
              :disabled="pausingAutomation"
              @click="pauseAutomation"
            >
              {{ pausingAutomation ? '...' : 'Hentikan otomasi' }}
            </button>
            <button
              type="button"
              class="shrink-0 rounded-lg border border-red-200 bg-red-50 px-2 py-1 text-[11px] font-medium text-red-700 hover:bg-red-100 disabled:opacity-50"
              :disabled="archivingConversation"
              @click="archiveSelectedConversation"
            >
              <i class="fa-regular fa-trash-can mr-1" />
              {{ archivingConversation ? 'Menghapus...' : 'Delete chat' }}
            </button>
          </div>

          <div
            v-if="selectedConversation.active_flow && !selectedConversation.automation_paused"
            class="border-b border-violet-200 bg-violet-50 px-4 py-1.5 text-center text-[11px] text-violet-900"
          >
            <i class="fa-solid fa-diagram-project mr-1" />
            Otomasi berjalan: <strong>{{ selectedConversation.active_flow.flow_name || 'Flow' }}</strong>
          </div>
          <div
            v-else-if="selectedConversation.automation_paused"
            class="border-b border-slate-200 bg-slate-100 px-4 py-1.5 text-center text-[11px] text-slate-600"
          >
            Otomasi dijeda untuk chat ini
          </div>

          <div
            v-if="waWindowBanner"
            class="border-b px-4 py-2 text-center text-xs"
            :class="waWindowBanner.expired ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-sky-200 bg-sky-50 text-sky-900'"
          >
            {{ waWindowBanner.text }}
          </div>

          <div ref="messagesEl" class="flex-1 space-y-2 overflow-y-auto px-3 py-3" @scroll.passive="onMessagesScroll">
            <div
              v-if="hasMoreOlder"
              class="py-1 text-center text-[11px] text-slate-500"
            >
              <span v-if="loadingOlder">Memuat pesan lama…</span>
              <span v-else>↑ Gulir ke atas untuk muat pesan lebih lama</span>
            </div>
            <div
              v-for="msg in localMessages"
              :key="msg.id"
              class="flex"
              :class="bubbleAlign(msg)"
            >
              <div
                class="max-w-[82%] rounded-lg px-3 py-2 text-sm shadow-sm"
                :class="bubbleClass(msg)"
              >
                <p v-if="msg.direction === 'internal'" class="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">
                  Catatan internal{{ msg.author_name ? ' · ' + msg.author_name : '' }}
                </p>
                <div
                  v-if="isStoryReplyMessage(msg)"
                  class="mb-2 overflow-hidden rounded-xl border border-purple-200/90 bg-gradient-to-b from-purple-50/90 to-white"
                >
                  <p class="px-3 pt-2 text-[11px] font-medium italic text-purple-900">
                    {{ storyReplyLabel(msg) }}
                  </p>
                  <div
                    v-if="storyReplyMediaUrl(msg)"
                    class="relative mx-2 mt-1 overflow-hidden rounded-lg bg-slate-900"
                  >
                    <video
                      v-if="storyReplyIsVideo(msg)"
                      :src="storyReplyMediaUrl(msg)"
                      controls
                      playsinline
                      preload="metadata"
                      referrerpolicy="no-referrer"
                      class="max-h-52 w-full object-contain"
                      @error="onVideoLoadError(msg)"
                    />
                    <button
                      v-else
                      type="button"
                      class="block w-full"
                      @click="openImageLightbox(msg)"
                    >
                      <img
                        :src="storyReplyMediaUrl(msg)"
                        alt="Preview story"
                        referrerpolicy="no-referrer"
                        class="max-h-52 w-full object-cover"
                        loading="lazy"
                        @error="onImageLoadError(msg)"
                      />
                    </button>
                  </div>
                  <button
                    v-else-if="needsMediaResolve(msg)"
                    type="button"
                    class="mx-2 mb-1 flex w-[calc(100%-1rem)] items-center gap-2 rounded-lg bg-white/80 px-3 py-2 text-left text-xs text-slate-700"
                    :disabled="mediaResolving[msg.id]"
                    @click="resolveMessageMedia(msg)"
                  >
                    <i class="fa-brands fa-instagram text-purple-600" />
                    {{ mediaResolving[msg.id] ? 'Memuat story…' : 'Muat preview story' }}
                  </button>
                  <a
                    v-if="storyViewUrl(msg)"
                    :href="storyViewUrl(msg)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mx-3 mb-2 mt-1 inline-block text-xs font-semibold text-blue-600 hover:text-blue-800 hover:underline"
                  >
                    View story
                  </a>
                </div>
                <button
                  v-else-if="msg.media_url && isImageLikeMessage(msg)"
                  type="button"
                  class="mb-1 block max-w-full overflow-hidden rounded-lg ring-0 transition hover:ring-2 hover:ring-emerald-400/80 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                  @click="openImageLightbox(msg)"
                >
                  <img
                    :src="msg.media_url"
                    alt="Lampiran gambar"
                    referrerpolicy="no-referrer"
                    class="max-h-56 max-w-full cursor-zoom-in object-cover"
                    loading="lazy"
                    @error="onImageLoadError(msg)"
                  />
                </button>
                <div v-else-if="msg.media_url && isVideoLikeMessage(msg)" class="mb-1 max-w-full">
                  <video
                    :src="msg.media_url"
                    controls
                    playsinline
                    preload="metadata"
                    referrerpolicy="no-referrer"
                    class="max-h-56 max-w-full rounded-lg bg-black/5"
                    @error="onVideoLoadError(msg)"
                  />
                  <button
                    v-if="mediaResolveFailed[msg.id]"
                    type="button"
                    class="mt-1 text-xs text-orange-700 underline"
                    @click="resolveMessageMedia(msg)"
                  >
                    Gagal memutar — coba muat ulang
                  </button>
                </div>
                <a
                  v-else-if="msg.media_url && isCachedMediaUrl(msg.media_url)"
                  :href="msg.media_url"
                  target="_blank"
                  rel="noopener"
                  download
                  class="mb-1 inline-flex max-w-full items-center gap-1 rounded-md bg-white/60 px-2 py-1 text-xs font-medium text-emerald-900 underline"
                >
                  <i class="fa-solid fa-paperclip shrink-0" />
                  <span class="truncate">{{ msg.media_filename || 'Buka lampiran' }}</span>
                </a>
                <button
                  v-else-if="needsMediaResolve(msg)"
                  type="button"
                  class="mb-1 flex w-full items-center gap-1.5 rounded-md px-2 py-1.5 text-left text-xs hover:bg-white/80"
                  :class="mediaResolveFailed[msg.id] ? 'bg-orange-50 text-orange-800' : 'bg-white/50 text-slate-700'"
                  :disabled="mediaResolving[msg.id]"
                  @click="resolveMessageMedia(msg)"
                >
                  <i
                    class="shrink-0"
                    :class="
                      mediaResolving[msg.id]
                        ? 'fa-solid fa-spinner fa-spin text-slate-500'
                        : mediaResolveFailed[msg.id]
                          ? 'fa-solid fa-rotate-right text-orange-600'
                          : isVideoLikeMessage(msg)
                            ? 'fa-solid fa-video text-slate-500'
                            : 'fa-regular fa-image text-slate-500'
                    "
                  />
                  {{
                    mediaResolving[msg.id]
                      ? 'Memuat…'
                      : mediaResolveFailed[msg.id]
                        ? 'Gagal memuat — ketuk coba lagi'
                        : mediaPlaceholderLabel(msg)
                  }}
                </button>
                <p v-if="displayMessageBody(msg)" class="whitespace-pre-wrap break-words">{{ displayMessageBody(msg) }}</p>
                <p
                  v-if="msg.direction === 'internal' && msg.mentioned_users?.length"
                  class="mt-1.5 text-[10px] text-amber-800"
                >
                  <i class="fa-solid fa-at mr-0.5" />
                  {{ msg.mentioned_users.map((u) => formatUserOptionLabel(u)).join(', ') }}
                </p>
                <p class="mt-1 text-right text-[10px] leading-relaxed opacity-85">
                  <span
                    v-if="msg.direction === 'outbound' && msg.author_name"
                    class="mb-0.5 block text-left font-medium text-slate-700"
                  >
                    Dibalas oleh {{ msg.author_name }}
                  </span>
                  <span class="block text-slate-600">
                    {{ formatTime(msg.sent_at) }}
                    <span v-if="msg.direction === 'outbound' && msg.status"> · {{ msg.status }}</span>
                  </span>
                </p>
                <div
                  v-if="msg.direction !== 'internal'"
                  class="mt-1.5 flex flex-wrap items-center justify-end gap-1"
                >
                  <button
                    v-if="composerMode === 'reply'"
                    type="button"
                    class="rounded bg-white/70 px-2 py-0.5 text-[10px] font-medium text-slate-600 hover:bg-white hover:text-slate-900"
                    @click="prepareReply(msg)"
                  >
                    <i class="fa-solid fa-reply mr-1" />Reply
                  </button>
                  <button
                    type="button"
                    class="rounded bg-white/70 px-2 py-0.5 text-[10px] font-medium text-slate-600 hover:bg-white hover:text-slate-900"
                    @click="forwardMessage(msg)"
                  >
                    <i class="fa-solid fa-share mr-1" />Forward
                  </button>
                  <button
                    type="button"
                    class="rounded bg-white/70 px-2 py-0.5 text-[10px] font-medium text-slate-600 hover:bg-white hover:text-slate-900"
                    @click="copyMessage(msg)"
                  >
                    <i class="fa-regular fa-copy mr-1" />Copy
                  </button>
                  <button
                    v-if="msg.direction === 'outbound'"
                    type="button"
                    class="rounded bg-white/70 px-2 py-0.5 text-[10px] font-medium text-slate-600 hover:bg-white hover:text-slate-900"
                    title="Ubah isi sebagai draft baru (pesan lama tidak bisa diedit)"
                    @click="editMessageDraft(msg)"
                  >
                    <i class="fa-regular fa-pen-to-square mr-1" />Ubah Draft
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="border-t border-slate-200 bg-white overflow-visible">
            <div class="flex border-b border-slate-100 px-2">
              <button
                type="button"
                class="px-4 py-2 text-sm font-medium"
                :class="composerMode === 'reply' ? 'border-b-2 border-emerald-600 text-emerald-800' : 'text-slate-500'"
                @click="setComposerMode('reply')"
              >
                Balas
              </button>
              <button
                type="button"
                class="px-4 py-2 text-sm font-medium"
                :class="composerMode === 'internal' ? 'border-b-2 border-amber-600 text-amber-900' : 'text-slate-500'"
                @click="setComposerMode('internal')"
              >
                Catatan internal
              </button>
            </div>
            <form class="flex flex-col gap-2 p-3" @submit.prevent="submitComposer">
              <div
                v-if="editSourceMessage && composerMode === 'reply'"
                class="rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-900"
              >
                <div class="mb-1 flex items-center justify-between gap-2">
                  <p class="font-semibold">
                    <i class="fa-regular fa-pen-to-square mr-1" />
                    Ubah draft dari pesan sebelumnya (pesan lama tetap, ini kirim baru)
                  </p>
                  <button
                    type="button"
                    class="text-blue-700 hover:text-red-600"
                    title="Batal edit draft"
                    @click="clearEditSource"
                  >
                    <i class="fa-solid fa-xmark" />
                  </button>
                </div>
                <p class="line-clamp-2 text-[11px] text-blue-800">
                  {{ replySnippet(editSourceMessage) }}
                </p>
              </div>
              <div
                v-if="replyToMessage && composerMode === 'reply'"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900"
              >
                <div class="mb-1 flex items-center justify-between gap-2">
                  <p class="font-semibold">
                    <i class="fa-solid fa-reply mr-1" />
                    Membalas {{ replyTargetLabel(replyToMessage) }}
                  </p>
                  <button
                    type="button"
                    class="text-emerald-700 hover:text-red-600"
                    title="Batal reply"
                    @click="clearReplyTarget"
                  >
                    <i class="fa-solid fa-xmark" />
                  </button>
                </div>
                <p class="line-clamp-2 text-[11px] text-emerald-800">
                  {{ replySnippet(replyToMessage) }}
                </p>
              </div>
              <div
                v-if="pendingAttachments.length"
                class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2"
              >
                <div class="mb-1.5 flex items-center justify-between gap-2 text-xs text-slate-600">
                  <span>
                    {{ pendingAttachments.length }} lampiran
                    <span v-if="pendingAttachments.length >= maxComposerAttachments" class="text-amber-700">
                      (maks. {{ maxComposerAttachments }})
                    </span>
                  </span>
                  <button type="button" class="text-slate-500 hover:text-red-600" @click="clearAttachments">
                    Hapus semua
                  </button>
                </div>
                <div class="flex flex-wrap gap-2">
                  <div
                    v-for="(file, idx) in pendingAttachments"
                    :key="`${file.name}-${file.size}-${idx}`"
                    class="group relative flex max-w-[7rem] flex-col items-center gap-1"
                  >
                    <img
                      v-if="attachmentPreviewUrls[idx]"
                      :src="attachmentPreviewUrls[idx]"
                      :alt="file.name"
                      class="h-16 w-16 rounded-lg border border-slate-200 object-cover"
                    />
                    <div
                      v-else
                      class="flex h-16 w-16 items-center justify-center rounded-lg border border-slate-200 bg-white px-1 text-center text-[10px] text-slate-600"
                    >
                      <i class="fa-solid fa-file mb-0.5 text-emerald-700" />
                    </div>
                    <span class="line-clamp-2 w-full text-center text-[10px] text-slate-600">{{ file.name }}</span>
                    <button
                      type="button"
                      class="absolute -right-1 -top-1 flex h-5 w-5 items-center justify-center rounded-full bg-slate-800 text-white opacity-90 hover:bg-red-600"
                      :title="`Hapus ${file.name}`"
                      @click="removeAttachment(idx)"
                    >
                      <i class="fa-solid fa-xmark text-[10px]" />
                    </button>
                  </div>
                </div>
              </div>
              <div
                v-if="pendingTemplateSendLabel"
                class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900"
              >
                <i class="fa-brands fa-whatsapp" />
                <span class="min-w-0 flex-1">{{ pendingTemplateSendLabel }}</span>
                <button type="button" class="text-emerald-700 hover:text-red-600" @click="clearPendingTemplateSend">
                  <i class="fa-solid fa-xmark" />
                </button>
              </div>
              <div class="flex items-end gap-2">
              <input
                ref="imageInputRef"
                type="file"
                accept="image/*"
                multiple
                class="hidden"
                @change="onPickImages"
              />
              <input ref="fileInputRef" type="file" class="hidden" @change="onPickFile" />
              <div class="flex shrink-0 flex-col gap-1">
                <button
                  type="button"
                  class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100"
                  title="Gambar (bisa pilih beberapa)"
                  :disabled="sending"
                  @click="imageInputRef?.click()"
                >
                  <i class="fa-solid fa-image" />
                </button>
                <button
                  type="button"
                  class="flex h-9 w-9 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100"
                  title="Lampiran"
                  :disabled="sending"
                  @click="fileInputRef?.click()"
                >
                  <i class="fa-solid fa-paperclip" />
                </button>
              </div>
              <div class="relative min-w-0 flex-1">
                <div
                  v-if="mentionMenuOpen && composerMode === 'internal' && filteredMentionUsers.length > 0"
                  class="absolute bottom-full left-0 right-0 z-30 mb-1 max-h-56 overflow-y-auto rounded-lg border border-amber-200 bg-white py-1 shadow-lg"
                >
                  <p class="border-b border-amber-100 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800">
                    Tag rekan (@)
                  </p>
                  <button
                    v-for="(u, idx) in filteredMentionUsers"
                    :key="u.id"
                    type="button"
                    class="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-amber-50"
                    :class="idx === mentionMenuHighlight ? 'bg-amber-50' : ''"
                    @mousedown.prevent="applyMention(u)"
                  >
                    <span class="font-medium text-slate-900">{{ u.name }}</span>
                    <span class="mt-0.5 text-xs text-slate-500">{{ formatUserOptionLabel(u) }}</span>
                  </button>
                </div>
                <p
                  v-else-if="mentionMenuOpen && composerMode === 'internal'"
                  class="absolute bottom-full left-0 mb-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                >
                  Tidak ada user yang cocok.
                </p>
                <div
                  v-else-if="templateMenuOpen && filteredTemplates.length > 0"
                  class="absolute bottom-full left-0 right-0 z-20 mb-1 max-h-48 overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
                >
                  <p class="border-b border-slate-100 px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                    Template balasan
                  </p>
                  <button
                    v-for="(tpl, idx) in filteredTemplates"
                    :key="tpl.id"
                    type="button"
                    class="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-emerald-50"
                    :class="idx === templateMenuHighlight ? 'bg-emerald-50' : ''"
                    @mousedown.prevent="applyTemplate(tpl)"
                  >
                    <span class="font-medium text-slate-900">
                      {{ tpl.title }}
                      <span v-if="tpl.shortcut" class="font-mono text-xs text-emerald-700">/{{ tpl.shortcut }}</span>
                    </span>
                    <span v-if="templateModeSummary(tpl)" class="mt-0.5 text-[10px] font-medium text-emerald-700">
                      {{ templateModeSummary(tpl) }}
                    </span>
                    <span class="mt-0.5 line-clamp-2 text-xs text-slate-500">{{ tpl.body }}</span>
                  </button>
                </div>
                <p
                  v-else-if="templateMenuOpen && messageTemplates.length === 0"
                  class="absolute bottom-full left-0 mb-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900"
                >
                  Belum ada template. Buat di menu Tim Inbox Omnichannel.
                </p>
                <textarea
                  ref="composerEl"
                  v-model="replyText"
                  rows="1"
                  class="w-full min-h-[2.5rem] resize-none overflow-y-hidden rounded-2xl border border-slate-200 py-2 pl-4 pr-10 text-sm leading-snug focus:outline-none focus:ring-1"
                  :class="composerMode === 'internal'
                    ? 'focus:border-amber-500 focus:ring-amber-500'
                    : 'focus:border-emerald-500 focus:ring-emerald-500'"
                  :placeholder="composerPlaceholder"
                  :disabled="sending || grammarChecking"
                  :spellcheck="composerSpellcheckEnabled"
                  lang="id"
                  autocapitalize="sentences"
                  autocomplete="off"
                  @input="onComposerInput"
                  @keydown="onComposerKeydown"
                />
                <OmniEmojiPickerButton
                  v-model:open="emojiPickerOpen"
                  class="absolute bottom-1.5 right-1.5"
                  teleport
                  panel-size="lg"
                  panel-width="22rem"
                  placement="top"
                  :disabled="sending"
                  @select="insertEmoji"
                />
              </div>
              <button
                type="submit"
                class="shrink-0 rounded-full px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                :class="composerMode === 'internal' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                :disabled="sending || grammarChecking || !canSubmitComposer"
              >
                <i v-if="sending || grammarChecking" class="fa-solid fa-spinner fa-spin" />
                <span v-else>{{ sendButtonLabel }}</span>
              </button>
              </div>
              <div
                v-if="composerMode === 'reply' && (composerSpellcheckEnabled || showAiWriting)"
                class="flex flex-wrap items-center gap-x-3 gap-y-1 px-1 text-[10px] text-slate-500"
              >
                <label
                  v-if="showAiWriting"
                  class="inline-flex cursor-pointer items-center gap-1.5"
                >
                  <input
                    v-model="autoGrammarOnSend"
                    type="checkbox"
                    class="rounded border-slate-300 text-violet-600"
                    :disabled="sending || grammarChecking"
                  />
                  <span>Perbaiki typo otomatis saat kirim (AI)</span>
                </label>
                <span v-if="composerSpellcheckEnabled" class="text-slate-400">
                  <i class="fa-solid fa-spell-check mr-0.5" /> Garis merah = ejaan browser
                </span>
                <button
                  v-if="showAiWriting && replyText.trim()"
                  type="button"
                  class="font-medium text-violet-700 hover:text-violet-900 hover:underline disabled:opacity-50"
                  :disabled="aiLoading || grammarChecking || sending"
                  @click="runAiAssist('grammar')"
                >
                  Perbaiki ejaan sekarang
                </button>
              </div>
            </form>
            <div v-if="showAiWriting" class="relative border-t border-slate-100 px-3 py-2">
              <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-sm font-medium text-violet-900 hover:bg-violet-100 disabled:opacity-50"
                :disabled="sending || aiLoading"
                @click="aiMenuOpen = !aiMenuOpen"
              >
                <i class="fa-solid fa-wand-magic-sparkles" />
                <span>{{ aiLoading ? 'Memproses AI...' : 'AI Writing Assistant' }}</span>
                <i class="fa-solid fa-chevron-up text-xs transition" :class="aiMenuOpen ? 'rotate-180' : ''" />
              </button>
              <div
                v-if="aiMenuOpen"
                class="absolute bottom-full left-3 right-3 z-50 mb-1 max-h-[min(20rem,50vh)] overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl"
              >
                <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-violet-900 hover:bg-violet-50 disabled:opacity-50" :disabled="aiLoading" @click="runAiAssist('grammar')">
                  <i class="fa-solid fa-spell-check w-4 text-violet-600" /> Perbaiki ejaan & tata bahasa
                </button>
                <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-violet-50 disabled:opacity-50" :disabled="aiLoading" @click="runAiAssist('simplify')">
                  <i class="fa-solid fa-wand-magic-sparkles w-4 text-slate-400" /> Sederhanakan teks
                </button>
                <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-violet-50 disabled:opacity-50" :disabled="aiLoading" @click="runAiAssist('expand')">
                  <i class="fa-solid fa-up-down w-4 text-slate-400" /> Perluas teks
                </button>
                <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-violet-50 disabled:opacity-50" :disabled="aiLoading" @click="runAiAssist('shorten')">
                  <i class="fa-solid fa-align-left w-4 text-slate-400" /> Persingkat teks
                </button>
                <div class="border-t border-slate-100 px-2 py-1">
                  <p class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Ubah jadi daftar</p>
                  <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-violet-50 disabled:opacity-50" :disabled="aiLoading" @click="runAiAssist('to_list', { listStyle: 'bullet' })">
                    <i class="fa-solid fa-list-ul w-4 text-slate-400" /> Bullet list
                  </button>
                  <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-violet-50 disabled:opacity-50" :disabled="aiLoading" @click="runAiAssist('to_list', { listStyle: 'numbered' })">
                    <i class="fa-solid fa-list-ol w-4 text-slate-400" /> Daftar bernomor
                  </button>
                </div>
                <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-violet-50 disabled:opacity-50" :disabled="aiLoading" @click="openAiCustomPrompt">
                  <i class="fa-solid fa-pen w-4 text-slate-400" /> Instruksi kustom
                </button>
                <div class="border-t border-slate-100 px-2 py-1">
                  <p class="px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Lainnya</p>
                  <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-violet-50 disabled:opacity-50" :disabled="aiLoading" @click="runAiAssist('translate_to_en')">
                    <i class="fa-solid fa-language w-4 text-slate-400" /> Terjemahkan ke Inggris
                  </button>
                  <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-violet-50 disabled:opacity-50" :disabled="aiLoading" @click="runAiAssist('translate_to_id')">
                    <i class="fa-solid fa-language w-4 text-slate-400" /> Terjemahkan ke Indonesia
                  </button>
                  <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-violet-50 disabled:opacity-50" :disabled="aiLoading" @click="runAiAssist('tone', { tone: 'professional' })">
                    Nada: profesional
                  </button>
                  <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-slate-700 hover:bg-violet-50 disabled:opacity-50" :disabled="aiLoading" @click="runAiAssist('tone', { tone: 'friendly' })">
                    Nada: ramah
                  </button>
                </div>
              </div>
            </div>
            <p v-if="aiError" class="px-3 pb-1 text-xs text-red-600">{{ aiError }}</p>
            <p v-if="sendError" class="px-3 pb-2 text-xs text-red-600">{{ sendError }}</p>
          </div>
        </template>

        <div v-else class="flex flex-1 flex-col items-center justify-center text-slate-500">
          <i class="fa-brands fa-whatsapp mb-2 text-3xl text-emerald-400" />
          <p class="text-sm">Pilih percakapan</p>
        </div>
      </section>

      <!-- Kolom 4: informasi detail kontak + CRM -->
      <aside
        v-if="selectedConversation"
        class="w-full shrink-0 flex-col border-l border-slate-200 bg-slate-50 lg:flex lg:w-80"
        :class="mobilePanel === 'detail' ? 'flex' : 'hidden'"
      >
        <div class="border-b border-slate-200 px-3 py-2">
          <p class="text-xs font-semibold text-slate-800">Informasi detail kontak</p>
          <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px]">
            <Link
              v-if="canManageOmnichannelTeams"
              href="/crm/omnichannel-teams"
              class="font-medium text-emerald-700 hover:text-emerald-900 hover:underline"
            >
              Kelola tim & template
            </Link>
            <Link
              v-if="canManageOmnichannelFlows"
              href="/crm/omnichannel-flows"
              class="font-medium text-violet-700 hover:text-violet-900 hover:underline"
            >
              Otomasi flow
            </Link>
          </div>
        </div>
        <div class="flex-1 space-y-3 overflow-y-auto p-3 text-sm">
          <div class="rounded-lg border border-slate-200 bg-white p-3 text-xs shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ channelLabel(selectedConversation.channel) }}</p>
            <p class="mt-1 font-medium text-slate-900">{{ selectedConversation.contact_name || '—' }}</p>
            <dl class="mt-2 space-y-1.5 text-slate-700">
              <template v-if="selectedConversation.channel === 'whatsapp'">
                <div class="flex justify-between gap-2">
                  <dt class="shrink-0 text-slate-500">Nomor (lokal)</dt>
                  <dd class="text-right font-mono">{{ selectedConversation.display_phone || '—' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                  <dt class="shrink-0 text-slate-500">Nomor (internasional)</dt>
                  <dd class="text-right font-mono text-[11px]">{{ selectedConversation.display_phone_international || '—' }}</dd>
                </div>
              </template>
              <template v-else>
                <div v-if="selectedConversation.channel_account_label" class="flex justify-between gap-2">
                  <dt class="shrink-0 text-slate-500">Akun bisnis</dt>
                  <dd
                    class="text-right font-medium"
                    :class="selectedConversation.channel === 'messenger' || selectedConversation.channel === 'facebook' ? 'text-blue-900' : 'text-purple-900'"
                  >
                    {{ selectedConversation.channel_account_label }}
                  </dd>
                </div>
                <div class="flex justify-between gap-2">
                  <dt class="shrink-0 text-slate-500">ID pelanggan</dt>
                  <dd class="text-right font-mono text-[11px]">{{ selectedConversation.external_contact_id || '—' }}</dd>
                </div>
                <div v-if="selectedConversation.display_phone" class="flex justify-between gap-2">
                  <dt class="shrink-0 text-slate-500">Kanal</dt>
                  <dd class="text-right text-[11px]">{{ selectedConversation.display_phone }}</dd>
                </div>
              </template>
              <div class="border-t border-slate-100 pt-1.5">
                <dt class="text-slate-500">Penanggung jawab</dt>
                <dd class="mt-0.5 font-medium text-slate-900">{{ contactOwnerLabel }}</dd>
              </div>
              <template v-if="selectedConversation.contact_profile">
                <div
                  v-if="selectedConversation.contact_profile.marital_status_label"
                  class="flex justify-between gap-2 border-t border-slate-100 pt-1.5"
                >
                  <dt class="shrink-0 text-slate-500">Status marital</dt>
                  <dd class="text-right">{{ selectedConversation.contact_profile.marital_status_label }}</dd>
                </div>
                <div
                  v-if="selectedConversation.contact_profile.preferred_outlet_name"
                  class="flex justify-between gap-2"
                >
                  <dt class="shrink-0 text-slate-500">Outlet pilihan</dt>
                  <dd class="text-right">{{ selectedConversation.contact_profile.preferred_outlet_name }}</dd>
                </div>
                <div
                  v-if="selectedConversation.contact_profile.preferred_area"
                  class="flex justify-between gap-2"
                >
                  <dt class="shrink-0 text-slate-500">Area</dt>
                  <dd class="text-right">{{ selectedConversation.contact_profile.preferred_area }}</dd>
                </div>
              </template>
            </dl>
          </div>

          <div
            v-if="selectedConversation.member"
            class="rounded-lg border border-emerald-200 bg-emerald-50/80 px-3 py-2 text-xs text-emerald-900"
          >
            <p class="font-semibold text-emerald-950">
              Member app
              <span v-if="selectedConversation.member.member_level" class="font-normal text-emerald-800">
                — {{ formatMemberTier(selectedConversation.member.member_level) }}
              </span>
              <span v-if="selectedConversation.member.is_exclusive_member" class="text-amber-700"> · Eksklusif</span>
            </p>
            <p class="mt-0.5 text-emerald-800">{{ selectedConversation.member.nama_lengkap }}</p>
            <p v-if="selectedConversation.member.mobile_phone" class="mt-0.5 text-emerald-800">
              {{ selectedConversation.member.mobile_phone }}
            </p>
            <p v-if="selectedConversation.member.member_id" class="mt-0.5 font-mono text-[11px] text-emerald-700">
              ID: {{ selectedConversation.member.member_id }}
            </p>
          </div>

          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Profil kontak</p>
          <div class="space-y-2 rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
            <div>
              <label class="text-[10px] font-semibold uppercase text-slate-500">Status marital</label>
              <select
                v-model="crmForm.marital_status"
                class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"
              >
                <option value="">— Belum diisi —</option>
                <option v-for="opt in maritalStatusOptions" :key="opt.value" :value="opt.value">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="text-[10px] font-semibold uppercase text-slate-500">Outlet pilihan</label>
              <select
                v-model="crmForm.preferred_outlet_id"
                class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"
              >
                <option :value="null">— Pilih outlet —</option>
                <option v-for="o in outletOptions" :key="o.id" :value="o.id">
                  {{ o.name }}
                </option>
              </select>
            </div>
            <div>
              <label class="text-[10px] font-semibold uppercase text-slate-500">Area / wilayah</label>
              <input
                v-model="crmForm.preferred_area"
                type="text"
                maxlength="255"
                class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm"
                placeholder="Contoh: Jakarta Selatan, BSD, dll."
              />
            </div>
            <p class="text-[10px] text-slate-500">
              Disimpan per kontak (berlaku untuk semua chat kanal nomor/ID yang sama).
            </p>
          </div>

          <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">Data CRM (tersimpan)</p>
          <div>
            <label class="text-[10px] font-semibold uppercase text-slate-500">Tahap lead</label>
            <select v-model="crmForm.lead_stage" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm">
              <option v-for="st in leadStages" :key="st.value" :value="st.value">{{ st.label }}</option>
            </select>
          </div>
          <div>
            <label class="text-[10px] font-semibold uppercase text-slate-500">Assign ke (bisa banyak)</label>
            <Multiselect
              v-model="crmForm.assignees"
              :options="assignableUsers"
              :custom-label="formatUserOptionLabel"
              :multiple="true"
              :close-on-select="false"
              :clear-on-select="false"
              :searchable="true"
              :preserve-search="true"
              :show-labels="false"
              placeholder="Ketik nama untuk mencari..."
              label="name"
              track-by="id"
              class="omni-assign-multiselect mt-1 text-sm"
            >
              <template #noResult>
                <span class="px-2 py-1 text-xs text-slate-500">Tidak ada user</span>
              </template>
            </Multiselect>
          </div>
          <div>
            <label class="text-[10px] font-semibold uppercase text-slate-500">Tim (opsional)</label>
            <Multiselect
              v-model="crmForm.assigned_teams"
              :options="assignableTeams"
              :multiple="true"
              :close-on-select="false"
              :clear-on-select="false"
              :searchable="true"
              :preserve-search="true"
              :show-labels="false"
              placeholder="Pilih tim..."
              label="name"
              track-by="id"
              class="omni-assign-multiselect mt-1 text-sm"
            >
              <template #noResult>
                <span class="px-2 py-1 text-xs text-slate-500">Tidak ada tim</span>
              </template>
            </Multiselect>
          </div>
          <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 bg-white p-2 text-xs text-slate-700">
            <input v-model="notifyAssigneesOnSave" type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600" />
            <span>Kirim notifikasi in-app ke semua yang ditugaskan saat klik <strong>Simpan detail</strong></span>
          </label>
          <div>
            <label class="text-[10px] font-semibold uppercase text-slate-500">Memo</label>
            <textarea v-model="crmForm.memo" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-sm" placeholder="Catatan internal CRM..." />
          </div>
          <button
            type="button"
            class="w-full rounded-lg bg-slate-800 py-2 text-sm font-medium text-white hover:bg-slate-900 disabled:opacity-50"
            :disabled="crmSaving"
            @click="saveCrmPanel"
          >
            {{ crmSaving ? 'Menyimpan...' : 'Simpan detail' }}
          </button>
          <p v-if="crmSaveError" class="text-xs text-red-600">{{ crmSaveError }}</p>
        </div>
      </aside>
    </div>

    <VueEasyLightbox
      :visible="lightboxVisible"
      :imgs="lightboxImages"
      :index="lightboxIndex"
      @hide="lightboxVisible = false"
    />
  </AppLayout>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import Swal from 'sweetalert2'
import VueEasyLightbox from 'vue-easy-lightbox'
import Multiselect from 'vue-multiselect'
import 'vue-multiselect/dist/vue-multiselect.min.css'
import AppLayout from '@/Layouts/AppLayout.vue'
import { autoResizeTextarea, insertEmojiIntoTextarea } from '@/utils/omniEmojiPicker.js'
import { inferSendMessageMode } from '@/utils/omniFlowGraph'
import OmniEmojiPickerButton from '@/Components/Omnichannel/OmniEmojiPickerButton.vue'

const props = defineProps({
  conversations: { type: Array, default: () => [] },
  selectedConversation: { type: Object, default: null },
  messages: { type: Array, default: () => [] },
  messagesHasMoreOlder: { type: Boolean, default: false },
  messagesOldestId: { type: Number, default: null },
  inbox: { type: String, default: 'all' },
  channelFilter: { type: String, default: null },
  leadStageFilter: { type: String, default: null },
  leadStages: { type: Array, default: () => [] },
  assignableUsers: { type: Array, default: () => [] },
  assignableTeams: { type: Array, default: () => [] },
  canSeeAllChats: { type: Boolean, default: false },
  canManageOmnichannelTeams: { type: Boolean, default: false },
  canManageOmnichannelFlows: { type: Boolean, default: false },
  messageTemplates: { type: Array, default: () => [] },
  aiWritingEnabled: { type: Boolean, default: true },
  composerSpellcheck: { type: Boolean, default: true },
  autoGrammarOnSendDefault: { type: Boolean, default: true },
  autoGrammarMaxChars: { type: Number, default: 2500 },
  autoGrammarMinChars: { type: Number, default: 4 },
  maritalStatusOptions: { type: Array, default: () => [] },
  outletOptions: { type: Array, default: () => [] },
})

/** Kunci partial reload Inertia agar daftar chat, pesan, dan hak "lihat semua" selalu sinkron (satu sumber kebenaran). */
const inboxPartialReloadKeys = [
  'conversations',
  'selectedConversation',
  'messages',
  'messagesHasMoreOlder',
  'messagesOldestId',
  'inbox',
  'channelFilter',
  'leadStageFilter',
  'leadStages',
  'assignableUsers',
  'assignableTeams',
  'canSeeAllChats',
  'canManageOmnichannelTeams',
  'canManageOmnichannelFlows',
  'messageTemplates',
]

function sortMessages(msgs) {
  const list = Array.isArray(msgs) ? [...msgs] : []
  return list.sort((a, b) => {
    const ta = new Date(a.sent_at || 0).getTime()
    const tb = new Date(b.sent_at || 0).getTime()
    if (ta !== tb) {
      return ta - tb
    }
    return (Number(a.id) || 0) - (Number(b.id) || 0)
  })
}

const channelMenuOptions = [
  { value: null, label: 'Semua kanal', icon: 'fa-solid fa-layer-group', iconColor: 'text-slate-400' },
  { value: 'whatsapp', label: 'WhatsApp', icon: 'fa-brands fa-whatsapp', iconColor: 'text-emerald-600' },
  { value: 'instagram', label: 'Instagram', icon: 'fa-brands fa-instagram', iconColor: 'text-pink-600' },
  { value: 'messenger', label: 'Messenger', icon: 'fa-brands fa-facebook-messenger', iconColor: 'text-blue-600' },
]

const inboxMenuOptions = computed(() => {
  const restricted = !props.canSeeAllChats
  if (restricted) {
    return [
      {
        value: 'mine',
        label: 'Ditugaskan ke saya',
        icon: 'fa-solid fa-user',
        hint: 'Hanya chat yang ditugaskan langsung ke Anda (user assignee).',
      },
      {
        value: 'all',
        label: 'Tim saya',
        icon: 'fa-solid fa-users',
        hint: 'Chat yang ditugaskan ke tim omnichannel Anda (selain tab Ditugaskan ke saya).',
      },
    ]
  }

  return [
    {
      value: 'all',
      label: 'Semua',
      icon: 'fa-solid fa-inbox',
      hint: 'Semua percakapan (Anda ada di daftar lihat semua inbox)',
    },
    { value: 'mine', label: 'Ditugaskan ke saya', icon: 'fa-solid fa-user', hint: '' },
    { value: 'unassigned', label: 'Belum ditugaskan', icon: 'fa-solid fa-user-slash', hint: '' },
  ]
})

const search = ref('')
const mobilePanel = ref(props.selectedConversation?.id ? 'chat' : 'list')
const selectedId = ref(props.selectedConversation?.id ?? null)
const localMessages = ref(sortMessages(props.messages || []))
/** ID percakapan yang isi localMessages saat ini — cegah merge pesan chat lain saat pindah kontak. */
const localMessagesConversationId = ref(props.selectedConversation?.id ?? null)
const hasMoreOlder = ref(!!props.messagesHasMoreOlder)
const oldestMessageId = ref(props.messagesOldestId ?? null)
const loadingOlder = ref(false)
const stickScrollBottom = ref(false)
const replyText = ref('')
const composerMode = ref('reply')
const sending = ref(false)
const sendError = ref('')
const messagesEl = ref(null)
const composerEl = ref(null)
const replyToMessage = ref(null)
const editSourceMessage = ref(null)

function resizeComposer() {
  nextTick(() => autoResizeTextarea(composerEl.value))
}

watch(replyText, resizeComposer)
const templateMenuOpen = ref(false)
const templateQuery = ref('')
const templateMenuHighlight = ref(0)
const mentionMenuOpen = ref(false)
const mentionQuery = ref('')
const mentionMenuHighlight = ref(0)
/** @type {import('vue').Ref<number[]>} */
const pendingMentionUserIds = ref([])
const emojiPickerOpen = ref(false)
const maxComposerAttachments = 10
/** @type {import('vue').Ref<File[]>} */
const pendingAttachments = ref([])
const attachmentPreviewUrls = ref([])
/** @type {import('vue').Ref<Record<string, unknown>|null>} */
const pendingTemplateSend = ref(null)

const pendingTemplateSendLabel = computed(() => {
  const cfg = pendingTemplateSend.value
  if (!cfg) return ''
  const mode = String(cfg.message_mode || 'text')
  if (mode === 'quick_reply') return 'Template WA · tombol balas'
  if (mode === 'cta_url') {
    const label = cfg.cta_url?.display_text || 'link'
    return `Template WA · tombol [${label}]`
  }
  if (mode === 'image') return 'Template WA · gambar'
  if (mode === 'document') return 'Template WA · PDF'
  return ''
})

const canSubmitComposer = computed(() => {
  if (replyText.value.trim() || pendingAttachments.value.length > 0) return true
  const mode = pendingTemplateSend.value?.message_mode
  return mode === 'image' || mode === 'document'
})

const imageInputRef = ref(null)
const fileInputRef = ref(null)
const pausingAutomation = ref(false)
const archivingConversation = ref(false)
const aiMenuOpen = ref(false)
const aiLoading = ref(false)
const aiError = ref('')
const grammarChecking = ref(false)
const LS_AUTO_GRAMMAR = 'omni_inbox_auto_grammar_send'
const autoGrammarOnSend = ref(props.autoGrammarOnSendDefault !== false)
/** ID chat yang sedang dipilih user; cegah poll mengembalikan chat lama dari URL. */
const pendingConversationId = ref(null)
const crmSaving = ref(false)
const crmSaveError = ref('')
const notifyAssigneesOnSave = ref(true)
const crmForm = ref({
  lead_stage: 'new_lead',
  assignees: [],
  assigned_teams: [],
  memo: '',
  marital_status: '',
  preferred_outlet_id: null,
  preferred_area: '',
})

let pollTimer = null
let pollKickTimer = null
/** Hindari dua router.reload poll bersamaan (tab focus + interval). */
let pollInFlight = false

/** Polling JSON (bukan Inertia) — tab aktif ~8s; background lebih jarang. */
const POLL_MS_TAB_VISIBLE = 8000
const POLL_MS_TAB_HIDDEN = 30000

const inbox = computed(() => props.inbox || 'all')
const channelFilter = computed(() => props.channelFilter || null)
const leadStageFilter = computed(() => props.leadStageFilter || null)

const emptyListHint = computed(() => {
  if (search.value.trim()) {
    return 'Tidak ada percakapan yang cocok dengan pencarian.'
  }
  const labels = {
    whatsapp: 'WhatsApp',
    instagram: 'Instagram',
    messenger: 'Messenger',
  }
  const ch = channelFilter.value
  if (ch && labels[ch]) {
    return `Tidak ada percakapan ${labels[ch]}.`
  }
  return 'Tidak ada percakapan.'
})

/** Tombol AI di composer — default aktif kecuali server kirim false eksplisit. */
const showAiWriting = computed(() => props.aiWritingEnabled !== false)
const composerSpellcheckEnabled = computed(() => props.composerSpellcheck !== false)

const sendButtonLabel = computed(() => {
  if (grammarChecking.value) return 'Memeriksa ejaan...'
  if (sending.value) return composerMode.value === 'internal' ? 'Menyimpan...' : 'Mengirim...'
  return composerMode.value === 'internal' ? 'Simpan' : 'Kirim'
})

/** Diperbarui poll axios + sinkron dari props saat navigasi Inertia. */
const liveConversations = ref([...(props.conversations || [])])
const liveSelectedConversation = ref(props.selectedConversation ?? null)

watch(
  () => props.conversations,
  (v) => {
    liveConversations.value = v ?? []
  },
  { deep: true }
)

watch(
  () => props.selectedConversation,
  (v) => {
    if (!isStaleConversationProps(v?.id)) {
      liveSelectedConversation.value = v ?? null
    }
  },
  { deep: true }
)

const selectedConversation = computed(
  () => liveSelectedConversation.value ?? props.selectedConversation
)

const contactOwnerLabel = computed(() => {
  const c = selectedConversation.value
  if (!c) return '—'
  if (c.assignee) return formatUserOptionLabel(c.assignee)
  const first = c.assignees?.[0]
  return first ? formatUserOptionLabel(first) : '—'
})

const filteredConversations = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return liveConversations.value
  return liveConversations.value.filter((c) => {
    const name = (c.contact_name || '').toLowerCase()
    const phone = (c.display_phone || '').toLowerCase()
    const memberName = (c.member?.nama_lengkap || '').toLowerCase()
    const tier = (c.member?.member_level || '').toLowerCase().replace(/_/g, ' ')
    const teamNames = (c.assigned_teams || []).map((t) => (t.name || '').toLowerCase()).join(' ')
    const assigneeBits = (c.assignees || [])
      .map((a) => [a.name, a.jabatan, a.outlet].filter(Boolean).join(' ').toLowerCase())
      .join(' ')
    return (
      name.includes(q) ||
      phone.includes(q) ||
      memberName.includes(q) ||
      tier.includes(q) ||
      teamNames.includes(q) ||
      assigneeBits.includes(q)
    )
  })
})

const filteredMentionUsers = computed(() => {
  const q = mentionQuery.value.trim().toLowerCase()
  const list = props.assignableUsers || []
  if (!q) {
    return list.slice(0, 25)
  }
  return list
    .filter((u) => {
      const name = (u.name || '').toLowerCase()
      const jabatan = (u.jabatan || '').toLowerCase()
      const outlet = (u.outlet || '').toLowerCase()
      return name.includes(q) || jabatan.includes(q) || outlet.includes(q)
    })
    .slice(0, 25)
})

const composerPlaceholder = computed(() => {
  if (composerMode.value === 'internal') {
    return 'Catatan untuk tim... ketik @ untuk tag rekan (nama · outlet · jabatan)'
  }
  const ch = selectedConversation.value?.channel
  if (ch === 'instagram') {
    return 'Kirim ke Instagram... (ketik / untuk template balasan)'
  }
  if (ch === 'messenger' || ch === 'facebook') {
    return 'Kirim ke Messenger... (ketik / untuk template balasan)'
  }
  return 'Kirim ke WhatsApp... (/ template · Ctrl+Shift+E perbaiki ejaan)'
})

const filteredTemplates = computed(() => {
  const q = templateQuery.value.trim().toLowerCase()
  const list = props.messageTemplates || []
  if (!q) {
    return list
  }
  return list.filter((tpl) => {
    const shortcut = (tpl.shortcut || '').toLowerCase()
    const title = (tpl.title || '').toLowerCase()
    return shortcut.includes(q) || title.includes(q)
  })
})

const waWindowBanner = computed(() => {
  const c = selectedConversation.value
  if (!c?.last_customer_message_at || c.channel !== 'whatsapp') return null
  const start = new Date(c.last_customer_message_at).getTime()
  const end = start + 24 * 60 * 60 * 1000
  const now = Date.now()
  if (now >= end) {
    return { expired: true, text: 'Jendela 24 jam WhatsApp untuk membalas bebas sudah berakhir. Gunakan template pesan resmi jika perlu.' }
  }
  const left = end - now
  const h = Math.floor(left / (60 * 60 * 1000))
  const m = Math.floor((left % (60 * 60 * 1000)) / (60 * 1000))
  return { expired: false, text: `Jendela percakapan WhatsApp: ±${h} jam ${m} menit lagi untuk balasan bebas (24 jam dari pesan terakhir pelanggan).` }
})

function channelIcon(channel) {
  if (channel === 'instagram') return 'fa-brands fa-instagram'
  if (channel === 'messenger' || channel === 'facebook') return 'fa-brands fa-facebook-messenger'
  return 'fa-brands fa-whatsapp'
}

function channelAvatarClass(channel) {
  if (channel === 'instagram') return 'bg-gradient-to-br from-purple-600 via-pink-500 to-orange-400 text-white'
  if (channel === 'messenger' || channel === 'facebook') return 'bg-blue-100 text-blue-700'
  return 'bg-emerald-100 text-emerald-700'
}

function channelLabel(channel) {
  if (channel === 'instagram') return 'Instagram'
  if (channel === 'messenger' || channel === 'facebook') return 'Messenger'
  return 'WhatsApp'
}

function channelAccountBadgeClass(channel) {
  if (channel === 'messenger' || channel === 'facebook') {
    return 'bg-blue-100 text-blue-900 ring-blue-200/80'
  }
  if (channel === 'instagram') {
    return 'bg-gradient-to-r from-purple-100 to-pink-100 text-purple-900 ring-purple-200/80'
  }
  return 'bg-emerald-50 text-emerald-900 ring-emerald-200/80'
}

function channelAccountBadgeTitle(conv) {
  if (!conv?.channel_account_label) return ''
  const id = conv.channel_account_id
  const label = conv.channel_account_label
  const ch = conv.channel
  if (ch === 'messenger' || ch === 'facebook') {
    return id
      ? `DM masuk ke Facebook Page (${label}) · Page ID: ${id}`
      : `DM masuk ke Facebook Page (${label})`
  }
  if (ch === 'instagram') {
    return id
      ? `DM masuk ke akun Instagram (${label}) · ID: ${id}`
      : `DM masuk ke akun Instagram (${label})`
  }
  return id ? `${label} · ${id}` : label
}

function bubbleAlign(msg) {
  if (msg.direction === 'internal') return 'justify-center'
  return msg.direction === 'outbound' ? 'justify-end' : 'justify-start'
}

function bubbleClass(msg) {
  if (msg.direction === 'internal') return 'border border-amber-200 bg-amber-50 text-amber-950'
  if (msg.direction === 'outbound') return 'bg-[#dcf8c6] text-slate-900'
  return 'bg-white text-slate-900'
}

const lightboxVisible = ref(false)
const lightboxImages = ref([])
const lightboxIndex = ref(0)
const imageLoadFailed = ref({})
const mediaResolving = ref({})
const mediaResolveFailed = ref({})

const MEDIA_PLACEHOLDER_BODIES = ['[Gambar]', '[Lampiran]', '[Video]', '[Audio]', '[Berkas]', '[Dokumen]']

function isEphemeralMessage(msg) {
  const body = String(msg?.body || '')
  return msg?.message_type === 'ephemeral' || body.includes('sekali lihat')
}

function isCachedMediaUrl(url) {
  if (!url) return false
  const u = String(url).toLowerCase()
  return u.includes('/storage/') || u.includes('/omni-inbound/')
}

function isVideoLikeMessage(msg) {
  if (!msg?.media_url || imageLoadFailed.value[msg.id]) {
    return false
  }
  if (msg.message_type === 'video') {
    return true
  }
  if (msg.media_mime && String(msg.media_mime).startsWith('video/')) {
    return true
  }
  const url = String(msg.media_url).toLowerCase()
  if (/\.(mp4|webm|mov|m4v)(\?|$)/.test(url)) {
    return true
  }
  return String(msg.body || '').trim() === '[Video]'
}

function needsMediaResolve(msg) {
  if (!msg?.id || isEphemeralMessage(msg)) {
    return false
  }
  if (msg.media_url && isCachedMediaUrl(msg.media_url) && !imageLoadFailed.value[msg.id]) {
    return false
  }
  if (msg.media_url && !isCachedMediaUrl(msg.media_url) && !imageLoadFailed.value[msg.id]) {
    return isVideoLikeMessage(msg) || isImageLikeMessage(msg) || MEDIA_PLACEHOLDER_BODIES.includes(String(msg.body || '').trim())
  }
  const type = msg.message_type
  if (['image', 'video', 'document', 'audio', 'attachment', 'sticker', 'story_reply'].includes(type)) {
    return true
  }
  if (isStoryReplyMessage(msg) && !storyReplyMediaUrl(msg)) {
    return true
  }
  const body = String(msg.body || '').trim()
  return MEDIA_PLACEHOLDER_BODIES.includes(body) || body.startsWith('[PDF:')
}

function isStoryReplyMessage(msg) {
  return msg?.message_type === 'story_reply' || !!(msg?.story_reply?.story_url || msg?.story_reply?.story_id)
}

function storyReplyLabel(msg) {
  return msg?.story_reply?.label || 'Membalas story Anda'
}

function storyReplyMediaUrl(msg) {
  return msg?.media_url || msg?.story_reply?.story_url || null
}

function storyReplyIsVideo(msg) {
  if (isVideoLikeMessage(msg)) {
    return true
  }
  const url = storyReplyMediaUrl(msg)
  return url ? /\.(mp4|webm|mov|m4v)(\?|$)/i.test(url) : false
}

function storyViewUrl(msg) {
  return storyReplyMediaUrl(msg)
}

function isImageLikeMessage(msg) {
  if (isStoryReplyMessage(msg)) {
    return false
  }
  if (!msg?.media_url || imageLoadFailed.value[msg.id]) {
    return false
  }
  if (msg.message_type === 'image' || msg.message_type === 'sticker') {
    return true
  }
  if (msg.media_mime && String(msg.media_mime).startsWith('image/')) {
    return true
  }
  const url = String(msg.media_url).toLowerCase()
  if (/\.(jpe?g|png|gif|webp)(\?|$)/.test(url)) {
    return true
  }
  const body = String(msg.body || '').trim()
  if (body === '[Gambar]') {
    return true
  }
  if (isVideoLikeMessage(msg)) {
    return false
  }
  return false
}

function displayMessageBody(msg) {
  if (msg?.message_type === 'reaction') {
    const emoji = (msg.body || '').trim()
    return emoji && emoji !== '[reaction]' && emoji !== '[Reaksi]' ? emoji : 'Reaksi'
  }
  const body = (msg.body || '').trim()
  if (!body) return ''
  if (isEphemeralMessage(msg)) {
    return body
  }
  if (needsMediaResolve(msg) && !isStoryReplyMessage(msg)) {
    return ''
  }
  if (isStoryReplyMessage(msg)) {
    if (!body || body === '[Balasan story]') {
      return ''
    }
    return body
  }
  if (msg.media_url && (isImageLikeMessage(msg) || isVideoLikeMessage(msg)) && MEDIA_PLACEHOLDER_BODIES.includes(body)) {
    return ''
  }
  return body
}

function conversationImageUrls() {
  return localMessages.value
    .filter((m) => isImageLikeMessage(m) && m.media_url)
    .map((m) => m.media_url)
}

function openImageLightbox(msg) {
  const urls = conversationImageUrls()
  const idx = urls.indexOf(msg.media_url)
  lightboxImages.value = urls.length ? urls : [msg.media_url]
  lightboxIndex.value = idx >= 0 ? idx : 0
  lightboxVisible.value = true
}

function onImageLoadError(msg) {
  if (!msg?.id || mediaResolving.value[msg.id] || mediaResolveFailed.value[msg.id]) {
    return
  }
  imageLoadFailed.value = { ...imageLoadFailed.value, [msg.id]: true }
  resolveMessageMedia(msg)
}

function onVideoLoadError(msg) {
  if (!msg?.id || mediaResolving.value[msg.id] || mediaResolveFailed.value[msg.id]) {
    return
  }
  imageLoadFailed.value = { ...imageLoadFailed.value, [msg.id]: true }
  resolveMessageMedia(msg)
}

function applyResolvedMessage(msgId, patch) {
  const idx = localMessages.value.findIndex((m) => m.id === msgId)
  if (idx < 0) {
    return false
  }
  localMessages.value = localMessages.value.map((m, i) => (i === idx ? { ...m, ...patch } : m))
  const failedImg = { ...imageLoadFailed.value }
  delete failedImg[msgId]
  imageLoadFailed.value = failedImg
  const failedMedia = { ...mediaResolveFailed.value }
  delete failedMedia[msgId]
  mediaResolveFailed.value = failedMedia
  return true
}

async function resolveMessageMedia(msg) {
  if (!msg?.id || mediaResolving.value[msg.id]) {
    return
  }
  mediaResolving.value = { ...mediaResolving.value, [msg.id]: true }
  try {
    const { data } = await axios.get(`/crm/omnichannel-inbox/messages/${msg.id}/media`)
    const url = data?.media_url || data?.message?.media_url
    if (url) {
      const patch = data?.message ? { ...data.message } : { media_url: url }
      applyResolvedMessage(msg.id, patch)
    } else {
      mediaResolveFailed.value = { ...mediaResolveFailed.value, [msg.id]: true }
    }
  } catch (e) {
    console.warn('resolveMessageMedia', e)
    mediaResolveFailed.value = { ...mediaResolveFailed.value, [msg.id]: true }
  } finally {
    const next = { ...mediaResolving.value }
    delete next[msg.id]
    mediaResolving.value = next
  }
}

/** Muat otomatis lampiran yang masih placeholder (maks 8 per batch). */
function autoResolvePendingMedia() {
  const pending = localMessages.value.filter(
    (m) =>
      needsMediaResolve(m) &&
      !mediaResolving.value[m.id] &&
      !mediaResolveFailed.value[m.id]
  )
  const recent = [...localMessages.value].slice(-3)
  recent
    .filter(
      (m) =>
        needsMediaResolve(m) &&
        !mediaResolving.value[m.id] &&
        !mediaResolveFailed.value[m.id]
    )
    .forEach((m) => resolveMessageMedia(m))
}

function mediaPlaceholderLabel(msg) {
  if (msg.message_type === 'video') return 'Video'
  if (msg.message_type === 'audio') return 'Audio'
  if (msg.message_type === 'document') return msg.media_filename || 'Berkas'
  return 'Gambar'
}

function openMediaUrl(url) {
  if (url) {
    window.open(url, '_blank', 'noopener')
  }
}

watch(selectedId, () => {
  imageLoadFailed.value = {}
  mediaResolveFailed.value = {}
  clearMentionState()
  resizeComposer()
})

function stageDotClass(color) {
  const map = {
    red: 'bg-red-500',
    orange: 'bg-orange-500',
    yellow: 'bg-yellow-500',
    blue: 'bg-blue-500',
    green: 'bg-green-500',
    purple: 'bg-purple-500',
    slate: 'bg-slate-500',
  }
  return map[color] || 'bg-slate-400'
}

function listQuery(extra = {}) {
  const q = { inbox: extra.inbox ?? inbox.value }
  if ('channel' in extra) {
    if (extra.channel) q.channel = extra.channel
  } else if (channelFilter.value) {
    q.channel = channelFilter.value
  }
  if ('lead_stage' in extra) {
    if (extra.lead_stage) q.lead_stage = extra.lead_stage
  } else if (leadStageFilter.value) {
    q.lead_stage = leadStageFilter.value
  }
  if (extra.conversation !== undefined) {
    if (extra.conversation !== null && extra.conversation !== '') q.conversation = extra.conversation
  } else if (selectedId.value) {
    q.conversation = selectedId.value
  }
  return q
}

function setInbox(val) {
  router.get('/crm/omnichannel-inbox', listQuery({ inbox: val }), {
    preserveState: true,
    preserveScroll: true,
    only: inboxPartialReloadKeys,
  })
}

function setLeadStage(val) {
  router.get('/crm/omnichannel-inbox', listQuery({ lead_stage: val }), {
    preserveState: true,
    preserveScroll: true,
    only: inboxPartialReloadKeys,
  })
}

function setChannelFilter(val) {
  router.get('/crm/omnichannel-inbox', listQuery({ channel: val }), {
    preserveState: true,
    preserveScroll: true,
    only: inboxPartialReloadKeys,
  })
}

/** Abaikan respons Inertia lama (URL/poll) saat user sudah pilih chat lain di sidebar. */
function isStaleConversationProps(convId) {
  if (convId == null || selectedId.value == null) {
    return false
  }

  return convId !== selectedId.value
}

function selectConversation(id) {
  pendingConversationId.value = id
  mobilePanel.value = 'chat'
  clearReplyTarget()
  clearEditSource()
  selectedId.value = id
  localMessagesConversationId.value = null
  localMessages.value = []
  hasMoreOlder.value = false
  oldestMessageId.value = null
  stickScrollBottom.value = true
  aiMenuOpen.value = false
  sendError.value = ''
  aiError.value = ''

  router.get('/crm/omnichannel-inbox', listQuery({ conversation: id }), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
    only: inboxPartialReloadKeys,
    onFinish: () => {
      if (pendingConversationId.value === id) {
        pendingConversationId.value = null
      }
    },
    onCancel: () => {
      if (pendingConversationId.value === id) {
        pendingConversationId.value = null
      }
    },
  })
}

function syncCrmFormFromConversation(conv) {
  if (!conv) return
  const profile = conv.contact_profile || {}
  crmForm.value = {
    lead_stage: conv.lead_stage || 'new_lead',
    assignees: Array.isArray(conv.assignees) ? [...conv.assignees] : [],
    assigned_teams: Array.isArray(conv.assigned_teams) ? [...conv.assigned_teams] : [],
    memo: conv.memo || '',
    marital_status: profile.marital_status || '',
    preferred_outlet_id: profile.preferred_outlet_id ?? null,
    preferred_area: profile.preferred_area || '',
  }
}

/** User sedang membaca riwayat di atas — jangan auto-scroll kecuali sudah dekat bawah. */
function shouldStickToBottom() {
  const el = messagesEl.value
  if (!el) {
    return true
  }
  return el.scrollHeight - el.scrollTop - el.clientHeight < 100
}

/**
 * Gabungkan pesan poll (jendela terbaru) + riwayat lama yang sudah di-load saat scroll atas.
 */
function mergeMessagesFromProps(serverMsgs, conversationId) {
  const next = sortMessages(serverMsgs ?? [])
  const convKey = conversationId ?? null

  if (convKey !== localMessagesConversationId.value) {
    localMessagesConversationId.value = convKey
    return next
  }

  const prev = localMessages.value
  if (prev.length === 0) {
    return next
  }

  const nextOldestId = next[0]?.id
  const olderKept =
    nextOldestId != null ? prev.filter((m) => m.id != null && m.id < nextOldestId) : []

  const byId = new Map()
  for (const m of olderKept) {
    byId.set(m.id, m)
  }
  for (const m of next) {
    if (m.id != null) {
      byId.set(m.id, m)
    }
  }
  for (const m of prev) {
    if (m.id == null) {
      continue
    }
    const server = byId.get(m.id)
    if (!server) {
      byId.set(m.id, m)
      continue
    }
    if (!server.media_url && m.media_url) {
      byId.set(m.id, {
        ...server,
        media_url: m.media_url,
        message_type: m.message_type || server.message_type,
        media_mime: m.media_mime || server.media_mime,
        media_filename: m.media_filename || server.media_filename,
        story_reply: m.story_reply || server.story_reply,
      })
    }
  }
  return sortMessages([...byId.values()])
}

async function loadOlderMessages() {
  if (!selectedId.value || loadingOlder.value || !hasMoreOlder.value || !oldestMessageId.value) {
    return
  }
  loadingOlder.value = true
  const el = messagesEl.value
  const prevHeight = el?.scrollHeight ?? 0
  const prevTop = el?.scrollTop ?? 0
  try {
    const { data } = await axios.get(
      `/crm/omnichannel-inbox/conversations/${selectedId.value}/messages`,
      { params: { before_id: oldestMessageId.value, limit: 40, no_enrich: 1 } }
    )
    const older = sortMessages(data.messages ?? [])
    if (older.length > 0) {
      const existingIds = new Set(localMessages.value.map((m) => m.id))
      const toPrepend = older.filter((m) => m.id != null && !existingIds.has(m.id))
      localMessages.value = sortMessages([...toPrepend, ...localMessages.value])
      oldestMessageId.value = data.oldest_message_id ?? older[0]?.id ?? oldestMessageId.value
      hasMoreOlder.value = !!data.has_more_older
      nextTick(() => {
        requestAnimationFrame(() => {
          const box = messagesEl.value
          if (!box) {
            return
          }
          box.scrollTop = box.scrollHeight - prevHeight + prevTop
        })
      })
    } else {
      hasMoreOlder.value = false
    }
  } catch {
    /* ignore */
  } finally {
    loadingOlder.value = false
  }
}

function onMessagesScroll() {
  const el = messagesEl.value
  if (!el || loadingOlder.value || !hasMoreOlder.value) {
    return
  }
  if (el.scrollTop < 100) {
    loadOlderMessages()
  }
}

watch(
  () => [props.selectedConversation?.id, props.messages],
  ([convId, val]) => {
    if (isStaleConversationProps(convId)) {
      return
    }
    const prev = localMessages.value
    const merged = mergeMessagesFromProps(val, convId)
    const prevLastId = prev[prev.length - 1]?.id
    const nextLastId = merged[merged.length - 1]?.id
    localMessages.value = merged
    const newTail = nextLastId !== prevLastId || merged.length > prev.length
    if (stickScrollBottom.value || (shouldStickToBottom() && newTail)) {
      scrollToBottom()
      stickScrollBottom.value = false
    }
    nextTick(() => autoResolvePendingMedia())
  },
  { deep: true }
)

watch(
  () => props.selectedConversation?.id,
  (newId, prevId) => {
    if (isStaleConversationProps(newId)) {
      return
    }
    const conv = props.selectedConversation
    selectedId.value = newId ?? null
    liveSelectedConversation.value = conv ?? null
    syncCrmFormFromConversation(conv)
    sendError.value = ''
    if (newId !== prevId) {
      localMessagesConversationId.value = newId ?? null
      localMessages.value = sortMessages(props.messages || [])
      hasMoreOlder.value = !!props.messagesHasMoreOlder
      oldestMessageId.value = props.messagesOldestId ?? null
      stickScrollBottom.value = true
      composerMode.value = 'reply'
      clearReplyTarget()
      clearEditSource()
      templateMenuOpen.value = false
      emojiPickerOpen.value = false
      clearAttachments()
      mediaResolveFailed.value = {}
      nextTick(() => {
        scrollToBottom()
        stickScrollBottom.value = false
        autoResolvePendingMedia()
      })
    }
    if (!newId && mobilePanel.value !== 'list') {
      mobilePanel.value = 'list'
    }
  },
  { immediate: true }
)

function setComposerMode(mode) {
  composerMode.value = mode
  if (mode !== 'reply') {
    clearReplyTarget()
    clearEditSource()
  }
  templateMenuOpen.value = false
  mentionMenuOpen.value = false
  mentionQuery.value = ''
  pendingMentionUserIds.value = []
  emojiPickerOpen.value = false
  aiMenuOpen.value = false
  nextTick(() => {
    composerEl.value?.focus()
    resizeComposer()
  })
}

function clearMentionState() {
  mentionMenuOpen.value = false
  mentionQuery.value = ''
  mentionMenuHighlight.value = 0
  pendingMentionUserIds.value = []
}

function replyTargetLabel(msg) {
  if (!msg) return 'pesan'
  if (msg.direction === 'outbound') return msg.author_name ? `${msg.author_name}` : 'tim'
  return selectedConversation.value?.contact_name || selectedConversation.value?.display_phone || 'pelanggan'
}

function replySnippet(msg) {
  if (!msg) return ''
  const body = displayMessageBody(msg)
  if (body) return body
  if (msg.message_type === 'image') return '[Gambar]'
  if (msg.message_type === 'video') return '[Video]'
  if (msg.message_type === 'audio') return '[Audio]'
  if (msg.message_type === 'document') return `[Dokumen${msg.media_filename ? `: ${msg.media_filename}` : ''}]`
  return '[Lampiran]'
}

function clearReplyTarget() {
  replyToMessage.value = null
}

function clearEditSource() {
  editSourceMessage.value = null
}

function prepareReply(msg) {
  if (!msg || composerMode.value !== 'reply') return
  clearEditSource()
  replyToMessage.value = {
    id: msg.id,
    direction: msg.direction,
    author_name: msg.author_name || null,
    message_type: msg.message_type || null,
    media_filename: msg.media_filename || null,
    body: msg.body || '',
    sent_at: msg.sent_at || null,
  }
  nextTick(() => composerEl.value?.focus())
}

async function copyMessage(msg) {
  const text = replySnippet(msg)
  if (!text) return
  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(text)
    } else {
      const temp = document.createElement('textarea')
      temp.value = text
      temp.setAttribute('readonly', '')
      temp.style.position = 'absolute'
      temp.style.left = '-9999px'
      document.body.appendChild(temp)
      temp.select()
      document.execCommand('copy')
      document.body.removeChild(temp)
    }
    Swal.fire({ icon: 'success', title: 'Tersalin', text: 'Pesan berhasil disalin', timer: 1200, showConfirmButton: false })
  } catch {
    Swal.fire('Info', 'Gagal menyalin otomatis. Coba salin manual.', 'info')
  }
}

function forwardMessage(msg) {
  if (composerMode.value !== 'reply') {
    setComposerMode('reply')
  }
  clearReplyTarget()
  clearEditSource()
  clearPendingTemplateSend()
  clearAttachments()
  const snippet = replySnippet(msg)
  replyText.value = snippet ? `↪️ Forwarded message\n${snippet}` : '↪️ Forwarded message'
  nextTick(() => {
    composerEl.value?.focus()
    resizeComposer()
  })
}

function editMessageDraft(msg) {
  if (!msg || msg.direction !== 'outbound') return
  if (composerMode.value !== 'reply') {
    setComposerMode('reply')
  }
  clearReplyTarget()
  clearPendingTemplateSend()
  clearAttachments()
  editSourceMessage.value = {
    id: msg.id,
    direction: msg.direction,
    author_name: msg.author_name || null,
    message_type: msg.message_type || null,
    media_filename: msg.media_filename || null,
    body: msg.body || '',
    sent_at: msg.sent_at || null,
  }
  replyText.value = displayMessageBody(msg) || ''
  Swal.fire({
    icon: 'info',
    title: 'Mode Ubah Draft',
    text: 'Pesan yang sudah terkirim tidak bisa di-edit via channel. Ini akan membuat draft baru untuk dikirim ulang.',
    timer: 2200,
    showConfirmButton: false,
  })
  nextTick(() => {
    composerEl.value?.focus()
    resizeComposer()
  })
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
}

function normalizeTextForCompare(str) {
  return String(str).replace(/\s+/g, ' ').trim().toLowerCase()
}

async function requestGrammarCorrection(text) {
  const { data } = await axios.post('/crm/omnichannel-inbox/ai-assist', {
    action: 'grammar',
    text,
  })
  if (data.success && data.text) {
    return String(data.text).trim()
  }
  return text
}

/**
 * Jika opsi aktif & teks berubah setelah AI, tanya user sebelum kirim ke pelanggan.
 * @returns {Promise<string|null>} teks untuk dikirim, atau null jika dibatalkan
 */
async function resolveOutboundBodyBeforeSend(rawBody) {
  if (
    !autoGrammarOnSend.value
    || !showAiWriting.value
    || composerMode.value !== 'reply'
    || !rawBody
  ) {
    return rawBody
  }
  const minLen = props.autoGrammarMinChars ?? 4
  const maxLen = props.autoGrammarMaxChars ?? 2500
  if (rawBody.length < minLen || rawBody.length > maxLen) {
    return rawBody
  }

  grammarChecking.value = true
  try {
    const corrected = await requestGrammarCorrection(rawBody)
    if (normalizeTextForCompare(corrected) === normalizeTextForCompare(rawBody)) {
      return rawBody
    }

    const result = await Swal.fire({
      title: 'Perbaikan ejaan disarankan',
      html: `<p class="text-left text-sm text-slate-600 mb-2">Sistem mendeteksi kemungkinan typo. Pilih versi yang akan dikirim ke pelanggan:</p>
        <p class="text-left text-xs font-semibold text-slate-500 mb-1">Asli</p>
        <div class="text-left text-sm bg-slate-50 border border-slate-200 p-2 rounded-lg mb-3 whitespace-pre-wrap max-h-32 overflow-y-auto">${escapeHtml(rawBody)}</div>
        <p class="text-left text-xs font-semibold text-emerald-800 mb-1">Disarankan AI</p>
        <div class="text-left text-sm bg-emerald-50 border border-emerald-200 p-2 rounded-lg whitespace-pre-wrap max-h-32 overflow-y-auto">${escapeHtml(corrected)}</div>`,
      icon: 'info',
      width: 520,
      showCancelButton: true,
      showDenyButton: true,
      confirmButtonText: 'Kirim yang diperbaiki',
      denyButtonText: 'Kirim teks asli',
      cancelButtonText: 'Batal',
      confirmButtonColor: '#059669',
      denyButtonColor: '#64748b',
    })

    if (result.isConfirmed) {
      return corrected
    }
    if (result.isDenied) {
      return rawBody
    }
    return null
  } catch (e) {
    aiError.value = e.response?.data?.message || 'Gagal memeriksa ejaan. Kirim manual atau coba lagi.'
    return null
  } finally {
    grammarChecking.value = false
  }
}

async function runAiAssist(action, opts = {}) {
  if (!replyText.value.trim()) {
    aiError.value = 'Tulis dulu teks di kotak balasan.'
    return
  }
  aiLoading.value = true
  aiError.value = ''
  aiMenuOpen.value = false
  try {
    let text = null
    if (action === 'grammar') {
      text = await requestGrammarCorrection(replyText.value)
    } else {
      const { data } = await axios.post('/crm/omnichannel-inbox/ai-assist', {
        action,
        text: replyText.value,
        tone: opts.tone ?? null,
        list_style: opts.listStyle ?? null,
        custom_prompt: opts.customPrompt ?? null,
        conversation_id: selectedId.value ?? null,
      })
      if (data.success && data.text) {
        text = data.text
      } else {
        aiError.value = data.message || 'AI tidak mengembalikan teks.'
      }
    }
    if (text) {
      replyText.value = text
      nextTick(() => {
        composerEl.value?.focus()
        resizeComposer()
      })
    }
  } catch (e) {
    aiError.value = e.response?.data?.message || 'Gagal memproses AI Writing Assistant.'
  } finally {
    aiLoading.value = false
  }
}

async function openAiCustomPrompt() {
  aiMenuOpen.value = false
  const result = await Swal.fire({
    title: 'Instruksi kustom',
    input: 'textarea',
    inputPlaceholder: 'Contoh: buat balasan penawaran promo member, maksimal 3 kalimat...',
    inputAttributes: { 'aria-label': 'Instruksi' },
    showCancelButton: true,
    confirmButtonText: 'Proses',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#7c3aed',
  })
  if (result.isConfirmed && result.value) {
    await runAiAssist('custom', { customPrompt: String(result.value).trim() })
  }
}

function insertEmoji(emoji) {
  insertEmojiIntoTextarea(composerEl.value, replyText, emoji, () => {
    emojiPickerOpen.value = false
    resizeComposer()
  })
}

function syncAttachmentPreviews() {
  attachmentPreviewUrls.value.forEach((url) => {
    if (url) {
      URL.revokeObjectURL(url)
    }
  })
  attachmentPreviewUrls.value = pendingAttachments.value.map((file) =>
    file.type.startsWith('image/') ? URL.createObjectURL(file) : null
  )
}

function addPendingAttachments(files) {
  if (!files.length) {
    return
  }
  const onlyImages = files.every((f) => f.type.startsWith('image/'))
  if (!onlyImages) {
    Swal.fire('Info', 'Pilih beberapa file sekaligus hanya untuk gambar. Untuk PDF, gunakan ikon lampiran.', 'info')
    return
  }
  const room = maxComposerAttachments - pendingAttachments.value.length
  if (room <= 0) {
    Swal.fire('Info', `Maksimal ${maxComposerAttachments} gambar per kirim.`, 'info')
    return
  }
  const next = [...pendingAttachments.value, ...files.slice(0, room)]
  if (files.length > room) {
    Swal.fire('Info', `Hanya ${room} gambar ditambahkan (maks. ${maxComposerAttachments} per kirim).`, 'info')
  }
  pendingAttachments.value = next
  clearPendingTemplateSend()
  syncAttachmentPreviews()
}

function onPickImages(e) {
  const files = Array.from(e.target?.files || []).filter((f) => f.type.startsWith('image/'))
  addPendingAttachments(files)
  if (e.target) {
    e.target.value = ''
  }
}

function onPickFile(e) {
  const file = e.target?.files?.[0]
  if (file) {
    pendingAttachments.value = [file]
    clearPendingTemplateSend()
    syncAttachmentPreviews()
  }
  if (e.target) {
    e.target.value = ''
  }
}

function removeAttachment(index) {
  const url = attachmentPreviewUrls.value[index]
  if (url) {
    URL.revokeObjectURL(url)
  }
  pendingAttachments.value = pendingAttachments.value.filter((_, i) => i !== index)
  syncAttachmentPreviews()
}

function clearAttachments() {
  attachmentPreviewUrls.value.forEach((url) => {
    if (url) {
      URL.revokeObjectURL(url)
    }
  })
  pendingAttachments.value = []
  attachmentPreviewUrls.value = []
}

function onComposerInput() {
  const text = replyText.value
  if (composerMode.value === 'internal') {
    const mentionMatch = text.match(/@([^\n@]*)$/)
    if (mentionMatch) {
      mentionMenuOpen.value = true
      mentionQuery.value = mentionMatch[1]
      mentionMenuHighlight.value = 0
      templateMenuOpen.value = false
      templateQuery.value = ''
      return
    }
    mentionMenuOpen.value = false
    mentionQuery.value = ''
  }
  const match = text.match(/\/([\p{L}\p{N}_-]*)$/u)
  if (match && composerMode.value !== 'internal') {
    templateMenuOpen.value = true
    templateQuery.value = match[1]
    templateMenuHighlight.value = 0
    return
  }
  templateMenuOpen.value = false
  templateQuery.value = ''
}

function onComposerKeydown(e) {
  if (
    showAiWriting.value
    && composerMode.value === 'reply'
    && e.ctrlKey
    && e.shiftKey
    && (e.key === 'E' || e.key === 'e')
  ) {
    e.preventDefault()
    runAiAssist('grammar')
    return
  }
  if (mentionMenuOpen.value && composerMode.value === 'internal' && filteredMentionUsers.value.length > 0) {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      mentionMenuHighlight.value = Math.min(
        mentionMenuHighlight.value + 1,
        filteredMentionUsers.value.length - 1
      )
      return
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault()
      mentionMenuHighlight.value = Math.max(mentionMenuHighlight.value - 1, 0)
      return
    }
    if (e.key === 'Escape') {
      e.preventDefault()
      mentionMenuOpen.value = false
      return
    }
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      const u = filteredMentionUsers.value[mentionMenuHighlight.value]
      if (u) {
        applyMention(u)
      }
      return
    }
  }
  if (!templateMenuOpen.value || filteredTemplates.value.length === 0) {
    return
  }
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    templateMenuHighlight.value = Math.min(
      templateMenuHighlight.value + 1,
      filteredTemplates.value.length - 1
    )
    return
  }
  if (e.key === 'ArrowUp') {
    e.preventDefault()
    templateMenuHighlight.value = Math.max(templateMenuHighlight.value - 1, 0)
    return
  }
  if (e.key === 'Escape') {
    e.preventDefault()
    templateMenuOpen.value = false
    return
  }
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    const tpl = filteredTemplates.value[templateMenuHighlight.value]
    if (tpl) {
      applyTemplate(tpl)
    }
  }
}

function applyMention(user) {
  if (!user?.id) return
  const label = user.name || 'User'
  replyText.value = replyText.value.replace(/@([^\n@]*)$/, `@${label} `)
  if (!pendingMentionUserIds.value.includes(user.id)) {
    pendingMentionUserIds.value = [...pendingMentionUserIds.value, user.id]
  }
  mentionMenuOpen.value = false
  mentionQuery.value = ''
  nextTick(() => {
    composerEl.value?.focus()
    resizeComposer()
  })
}

function applyTemplateBody(body) {
  let text = body
  const c = selectedConversation.value
  if (c) {
    const nama = c.contact_name || c.contact_first_name || c.display_phone || ''
    text = text
      .replace(/\{\{nama\}\}/gi, nama)
      .replace(/\{\{nomor\}\}/gi, c.display_phone || '')
      .replace(/\{\{nama_depan\}\}/gi, c.contact_first_name || nama)
  }
  return text
}

function templateModeSummary(tpl) {
  const mode = tpl.message_mode || inferSendMessageMode({ ...(tpl.config || {}), body: tpl.body })
  if (mode === 'quick_reply') return 'WA · tombol balas'
  if (mode === 'cta_url') {
    const label = tpl.config?.cta_url?.display_text?.trim() || 'link'
    return `WA · [${label}]`
  }
  if (mode === 'image') return 'WA · gambar'
  if (mode === 'document') return 'WA · PDF'
  return ''
}

function clearPendingTemplateSend() {
  pendingTemplateSend.value = null
}

function applyTemplate(tpl) {
  const body = applyTemplateBody(tpl.body)
  replyText.value = replyText.value.replace(/\/[\p{L}\p{N}_-]*$/u, body)
  const mode = tpl.message_mode || inferSendMessageMode({ ...(tpl.config || {}), body: tpl.body })
  if (mode !== 'text' && selectedConversation.value?.channel === 'whatsapp') {
    pendingTemplateSend.value = {
      message_mode: mode,
      ...(tpl.config || {}),
    }
  } else {
    pendingTemplateSend.value = null
    if (mode !== 'text' && selectedConversation.value?.channel !== 'whatsapp') {
      Swal.fire('Info', 'Tombol & lampiran template hanya untuk chat WhatsApp. Teks tetap dimasukkan.', 'info')
    }
  }
  templateMenuOpen.value = false
  templateQuery.value = ''
  nextTick(() => {
    composerEl.value?.focus()
    resizeComposer()
  })
}

async function submitComposer() {
  if (mentionMenuOpen.value && filteredMentionUsers.value.length > 0) {
    return
  }
  if (templateMenuOpen.value && filteredTemplates.value.length > 0) {
    return
  }
  if (!selectedId.value) return
  if (pendingAttachments.value.length > 1 && pendingTemplateSend.value) {
    sendError.value = 'Template WA tidak bisa digabung dengan beberapa gambar. Hapus template atau kirim satu gambar.'
    return
  }
  let body = replyText.value.trim()
  const tplMode = pendingTemplateSend.value?.message_mode
  const tplMediaOnly = tplMode === 'image' || tplMode === 'document'
  if (!body && pendingAttachments.value.length === 0 && !tplMediaOnly) return
  sendError.value = ''
  const isInternal = composerMode.value === 'internal'
  const mentionIds = isInternal ? [...pendingMentionUserIds.value] : []

  if (!isInternal && body) {
    const resolved = await resolveOutboundBodyBeforeSend(body)
    if (resolved === null) {
      return
    }
    body = resolved
    replyText.value = resolved
  }

  if (!isInternal && replyToMessage.value) {
    const label = replyTargetLabel(replyToMessage.value)
    const snippet = replySnippet(replyToMessage.value)
    const quoteHeader = `↪️ Reply ke ${label}`
    const quoteBody = `> ${snippet}`.split('\n').join('\n> ')
    body = body
      ? `${quoteHeader}\n${quoteBody}\n\n${body}`
      : `${quoteHeader}\n${quoteBody}`
  }

  sending.value = true
  try {
    const formData = new FormData()
    if (body) {
      formData.append('body', body)
    }
    if (pendingAttachments.value.length === 1) {
      formData.append('attachment', pendingAttachments.value[0])
    } else if (pendingAttachments.value.length > 1) {
      pendingAttachments.value.forEach((file) => {
        formData.append('attachments[]', file)
      })
    }
    if (!isInternal && pendingTemplateSend.value && selectedConversation.value?.channel === 'whatsapp' && pendingAttachments.value.length === 0) {
      formData.append('send_config', JSON.stringify(pendingTemplateSend.value))
    }
    if (isInternal) {
      mentionIds.forEach((id) => formData.append('mentioned_user_ids[]', String(id)))
    }
    const url = isInternal
      ? `/crm/omnichannel-inbox/conversations/${selectedId.value}/internal-notes`
      : `/crm/omnichannel-inbox/conversations/${selectedId.value}/messages`
    const { data } = await axios.post(url, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    const newMessages = data.messages?.length
      ? data.messages
      : data.message
        ? [data.message]
        : []
    if (newMessages.length) {
      localMessages.value = sortMessages([...localMessages.value, ...newMessages])
    }
    if (data.conversation) {
      liveSelectedConversation.value = data.conversation
    }
    replyText.value = ''
    clearEditSource()
    clearReplyTarget()
    clearAttachments()
    clearPendingTemplateSend()
    clearMentionState()
    scrollToBottom()
    await router.reload({
      only: isInternal && mentionIds.length > 0
        ? ['conversations', 'selectedConversation']
        : isInternal
          ? ['conversations']
          : inboxPartialReloadKeys,
      preserveState: true,
      preserveScroll: true,
    })
  } catch (e) {
    sendError.value = e.response?.data?.message || 'Gagal mengirim.'
  } finally {
    sending.value = false
  }
}

async function pauseAutomation() {
  if (!selectedId.value) return
  pausingAutomation.value = true
  try {
    const { data } = await axios.post(
      `/crm/omnichannel-inbox/conversations/${selectedId.value}/pause-automation`
    )
    if (data.conversation) {
      await router.reload({
        only: ['conversations', 'selectedConversation'],
        preserveState: true,
        preserveScroll: true,
      })
    }
  } catch {
    /* ignore */
  } finally {
    pausingAutomation.value = false
  }
}

async function archiveSelectedConversation() {
  if (!selectedId.value || archivingConversation.value) return
  const result = await Swal.fire({
    title: 'Delete chat ini?',
    text: 'Chat akan diarsipkan dan hilang dari inbox aktif.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, delete',
    cancelButtonText: 'Batal',
    confirmButtonColor: '#dc2626',
  })
  if (!result.isConfirmed) return

  archivingConversation.value = true
  try {
    await axios.delete(`/crm/omnichannel-inbox/conversations/${selectedId.value}`)
    clearReplyTarget()
    clearEditSource()
    replyText.value = ''
    localMessages.value = []
    selectedId.value = null
    mobilePanel.value = 'list'

    await router.reload({
      only: inboxPartialReloadKeys,
      preserveState: true,
      preserveScroll: true,
    })
  } catch (e) {
    Swal.fire('Error', e.response?.data?.message || 'Gagal menghapus chat.', 'error')
  } finally {
    archivingConversation.value = false
  }
}

async function saveCrmPanel() {
  if (!selectedId.value) return
  crmSaving.value = true
  crmSaveError.value = ''
  const f = crmForm.value
  const payload = {
    lead_stage: f.lead_stage,
    memo: f.memo,
    assigned_user_ids: (f.assignees || []).map((a) => a.id),
    assigned_team_ids: (f.assigned_teams || []).map((t) => t.id),
    notify_assignees: notifyAssigneesOnSave.value,
    marital_status: f.marital_status || null,
    preferred_outlet_id: f.preferred_outlet_id || null,
    preferred_area: f.preferred_area?.trim() || null,
  }
  try {
    await axios.patch(`/crm/omnichannel-inbox/conversations/${selectedId.value}`, payload)
    await router.reload({
      only: inboxPartialReloadKeys,
      preserveState: true,
      preserveScroll: true,
    })
  } catch (e) {
    crmSaveError.value = e.response?.data?.message || 'Gagal menyimpan.'
  } finally {
    crmSaving.value = false
  }
}

/** Segarkan daftar & pesan via JSON — tidak memicu reload halaman Inertia (tidak "kedip"). */
async function pollInbox() {
  if (pollInFlight || pendingConversationId.value != null) {
    return
  }
  pollInFlight = true
  try {
    const { data } = await axios.get('/crm/omnichannel-inbox/poll', { params: listQuery() })
    liveConversations.value = data.conversations ?? []
    if (data.can_see_all_chats === false && inbox.value === 'unassigned') {
      setInbox('mine')
    }

    const sel = data.selected_conversation
    const convId = sel?.id ?? null
    if (sel && convId === selectedId.value && !isStaleConversationProps(convId)) {
      liveSelectedConversation.value = sel
      const prev = localMessages.value
      const merged = mergeMessagesFromProps(data.messages, convId)
      const prevLastId = prev[prev.length - 1]?.id
      const nextLastId = merged[merged.length - 1]?.id
      localMessages.value = merged
      if (data.has_more_older != null) {
        hasMoreOlder.value = !!data.has_more_older
      }
      if (data.oldest_message_id != null) {
        const polledOldest = data.oldest_message_id
        if (
          oldestMessageId.value == null
          || polledOldest < oldestMessageId.value
        ) {
          oldestMessageId.value = polledOldest
        }
      } else if (merged.length > 0 && oldestMessageId.value == null) {
        oldestMessageId.value = merged[0]?.id ?? null
      }
      const newTail = nextLastId !== prevLastId || merged.length > prev.length
      if (shouldStickToBottom() && newTail) {
        scrollToBottom()
      }
    }
  } catch {
    /* ignore */
  } finally {
    pollInFlight = false
  }
}

function pollIntervalMs() {
  return document.visibilityState === 'visible' ? POLL_MS_TAB_VISIBLE : POLL_MS_TAB_HIDDEN
}

function restartInboxPollTimer() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
  pollTimer = setInterval(pollInbox, pollIntervalMs())
}

/** Satu reload singkat setelah user kembali ke tab (debounce agar tidak double dengan focus). */
function kickPollInboxSoon() {
  clearTimeout(pollKickTimer)
  pollKickTimer = setTimeout(() => pollInbox(), 350)
}

function onInboxVisibilityChange() {
  restartInboxPollTimer()
  if (document.visibilityState === 'visible') {
    kickPollInboxSoon()
  }
}

function onInboxWindowFocus() {
  if (document.visibilityState === 'visible') {
    kickPollInboxSoon()
  }
}

function scrollToBottom() {
  const run = () => {
    const el = messagesEl.value
    if (!el) {
      return
    }
    el.scrollTop = el.scrollHeight
  }
  nextTick(() => {
    run()
    requestAnimationFrame(() => {
      run()
      requestAnimationFrame(run)
    })
  })
}

function formatUserOptionLabel(opt) {
  if (!opt) return ''
  const bits = [opt.jabatan, opt.outlet].filter(Boolean)
  return bits.length ? `${opt.name} — ${bits.join(' · ')}` : opt.name
}

function formatMemberTier(level) {
  if (!level) return ''
  const s = String(level).replace(/_/g, ' ')
  return s.length ? s.charAt(0).toUpperCase() + s.slice(1) : ''
}

function formatTime(iso) {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  // Selalu WIB: tanpa ini, jam mengikuti zona waktu OS/browser (sering terasa "ngaco" vs WhatsApp).
  return d.toLocaleString('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  })
}

watch(autoGrammarOnSend, (v) => {
  try {
    localStorage.setItem(LS_AUTO_GRAMMAR, v ? '1' : '0')
  } catch {
    /* ignore */
  }
})

onMounted(() => {
  try {
    const stored = localStorage.getItem(LS_AUTO_GRAMMAR)
    if (stored !== null) {
      autoGrammarOnSend.value = stored === '1'
    }
  } catch {
    /* ignore */
  }
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (csrf) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf
  }
  hasMoreOlder.value = !!props.messagesHasMoreOlder
  oldestMessageId.value = props.messagesOldestId ?? (localMessages.value[0]?.id ?? null)
  if (localMessages.value.length > 0) {
    stickScrollBottom.value = true
    scrollToBottom()
    stickScrollBottom.value = false
  }
  restartInboxPollTimer()
  document.addEventListener('visibilitychange', onInboxVisibilityChange)
  window.addEventListener('focus', onInboxWindowFocus)
  resizeComposer()
})

onUnmounted(() => {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
  clearTimeout(pollKickTimer)
  document.removeEventListener('visibilitychange', onInboxVisibilityChange)
  window.removeEventListener('focus', onInboxWindowFocus)
  clearAttachments()
})
</script>

<style scoped>
.omni-assign-multiselect :deep(.multiselect__tags) {
  min-height: 38px;
  padding-top: 6px;
  font-size: 0.875rem;
}
.omni-assign-multiselect :deep(.multiselect__input),
.omni-assign-multiselect :deep(.multiselect__single) {
  font-size: 0.875rem;
}
.omni-assign-multiselect :deep(.multiselect__content-wrapper) {
  max-height: 220px;
}
</style>
