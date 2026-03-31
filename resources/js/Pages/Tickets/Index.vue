<template>
  <AppLayout title="Ticketing System">
    <div class="w-full py-8 px-4 space-y-6">
      <div class="relative overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-r from-blue-600 to-indigo-700 p-6 text-white shadow-lg">
        <div class="absolute -right-8 -top-8 h-28 w-28 rounded-full bg-white/10"></div>
        <div class="absolute right-8 bottom-0 h-20 w-20 rounded-full bg-white/10"></div>
        <div class="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-2xl font-bold flex items-center gap-2">
              <i class="fa-solid fa-ticket-alt text-blue-100"></i> Ticketing System
            </h1>
            <p class="mt-1 text-sm text-blue-100">
              Pantau ticket, prioritas, outlet, dan SLA secara real-time.
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              @click="downloadImportTemplate"
              class="bg-white/90 text-indigo-700 px-4 py-2 rounded-xl shadow hover:shadow-lg transition-all font-semibold"
            >
              <i class="fa-solid fa-file-excel mr-2"></i> Download Template
            </button>
            <button
              type="button"
              @click="openImportFilePicker"
              class="bg-white/90 text-emerald-700 px-4 py-2 rounded-xl shadow hover:shadow-lg transition-all font-semibold"
            >
              <i class="fa-solid fa-file-import mr-2"></i> Import Excel
            </button>
            <button @click="openCreate" class="bg-white text-blue-700 px-4 py-2 rounded-xl shadow hover:shadow-lg transition-all font-semibold">
              <i class="fa-solid fa-plus mr-2"></i> Buat Ticket Baru
            </button>
            <input
              ref="importFileInput"
              type="file"
              accept=".xlsx,.xls"
              class="hidden"
              @change="handleImportFileChange"
            />
          </div>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total -->
        <button
          type="button"
          @click="setStatusFilter('all')"
          :class="[
            'rounded-xl shadow-lg p-6 border-l-4 bg-white hover:shadow-xl transition-all text-left',
            status === 'all' ? 'border-blue-600 ring-2 ring-blue-100' : 'border-blue-500'
          ]"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Total Tickets</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.total }}</p>
            </div>
            <i class="fa-solid fa-ticket-alt text-4xl text-blue-300"></i>
          </div>
        </button>
        <!-- Open -->
        <button
          type="button"
          @click="setStatusFilter('open')"
          :class="[
            'rounded-xl shadow-lg p-6 border-l-4 bg-white hover:shadow-xl transition-all text-left',
            status === 'open' ? 'border-yellow-600 ring-2 ring-yellow-100' : 'border-yellow-500'
          ]"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Open</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.open }}</p>
            </div>
            <i class="fa-solid fa-clock text-4xl text-yellow-300"></i>
          </div>
        </button>
        <!-- In Progress -->
        <button
          type="button"
          @click="setStatusFilter('in_progress')"
          :class="[
            'rounded-xl shadow-lg p-6 border-l-4 bg-white hover:shadow-xl transition-all text-left',
            status === 'in_progress' ? 'border-orange-600 ring-2 ring-orange-100' : 'border-orange-500'
          ]"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">In Progress</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.in_progress }}</p>
            </div>
            <i class="fa-solid fa-spinner text-4xl text-orange-300"></i>
          </div>
        </button>
        <!-- Closed -->
        <button
          type="button"
          @click="setStatusFilter('closed')"
          :class="[
            'rounded-xl shadow-lg p-6 border-l-4 bg-white hover:shadow-xl transition-all text-left',
            status === 'closed' ? 'border-green-600 ring-2 ring-green-100' : 'border-green-500'
          ]"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-gray-600">Closed</p>
              <p class="text-2xl font-bold text-gray-900">{{ statistics.closed }}</p>
            </div>
            <i class="fa-solid fa-check-circle text-4xl text-green-300"></i>
          </div>
        </button>
      </div>

      <!-- Filter and Search -->
      <div class="sticky top-2 z-10 rounded-2xl border border-blue-100 bg-white/95 backdrop-blur p-4 shadow-sm">
        <div class="flex flex-col md:flex-row gap-4">
        <input
          type="text"
          v-model="search"
          @input="onSearchInput"
          placeholder="Cari ticket number, title..."
          class="flex-1 px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        />
        <select
          v-model="status"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="all">Semua Status</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="resolved">Resolved</option>
          <option value="closed">Closed</option>
        </select>
        <select
          v-model="priority"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="all">Semua Prioritas</option>
          <option v-for="p in filterOptions.priorities" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <select
          v-model="division"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="all">Semua Divisi</option>
          <option v-for="d in filterOptions.divisions" :key="d.id" :value="d.id">{{ d.nama_divisi }}</option>
        </select>
        <select
          v-model="outlet"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="all">Semua Outlet</option>
          <option v-for="o in filterOptions.outlets" :key="o.id_outlet" :value="o.id_outlet">{{ o.nama_outlet }}</option>
        </select>
        <select
          v-model="paymentStatus"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="all">Semua Payment</option>
          <option value="paid">Paid</option>
          <option value="on_process">On Process</option>
          <option value="with_pr">Sudah ada PR</option>
          <option value="no_pr">Belum ada PR</option>
        </select>
        <select
          v-model="perPage"
          @change="debouncedSearch"
          class="w-full md:w-auto px-4 py-2 rounded-xl border border-blue-200 shadow focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition"
        >
          <option value="15">15 Per Halaman</option>
          <option value="30">30 Per Halaman</option>
          <option value="50">50 Per Halaman</option>
        </select>
        </div>

        <div v-if="hasActiveFilters" class="mt-3 flex flex-wrap items-center gap-2 text-xs">
          <span class="font-semibold text-gray-600">Filter aktif:</span>
          <span v-if="status !== 'all'" class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-blue-700">
            Status: {{ readableStatus(status) }}
          </span>
          <span v-if="priority !== 'all'" class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-1 text-yellow-700">
            Prioritas: {{ getPriorityLabel(priority) }}
          </span>
          <span v-if="division !== 'all'" class="inline-flex items-center rounded-full bg-purple-100 px-2.5 py-1 text-purple-700">
            Divisi: {{ getDivisionLabel(division) }}
          </span>
          <span v-if="outlet !== 'all'" class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-green-700">
            Outlet: {{ getOutletLabel(outlet) }}
          </span>
          <span v-if="paymentStatus !== 'all'" class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-700">
            Payment: {{ getPaymentFilterLabel(paymentStatus) }}
          </span>
          <button
            type="button"
            @click="resetFilters"
            class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 hover:bg-gray-200"
          >
            Reset
          </button>
        </div>
      </div>

      <!-- Tickets Table -->
      <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-100">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ticket</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Divisi / Outlet</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Team</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comments</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="ticket in data.data" :key="ticket.id" class="hover:bg-blue-50/40 transition-colors">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="space-y-1">
                    <div class="text-sm font-medium text-gray-900">{{ ticket.ticket_number }}</div>
                    <span
                      v-if="getIssueTypeLabel(ticket)"
                      :class="[
                        'inline-flex px-2 py-1 text-[10px] font-semibold rounded-full',
                        getIssueTypeClass(ticket)
                      ]"
                    >
                      {{ getIssueTypeLabel(ticket) }}
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="text-sm text-gray-900 whitespace-normal break-words">{{ ticket.title }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="space-y-1">
                    <span :class="[
                      'inline-flex px-2 py-1 text-xs font-semibold rounded-full',
                      'bg-blue-100 text-blue-800'
                    ]">
                      {{ ticket.category?.name || '-' }}
                    </span>
                    <span :class="[
                      'inline-flex px-2 py-1 text-[10px] font-semibold rounded-full',
                      getPriorityColor(ticket.priority?.level)
                    ]">
                      {{ ticket.priority?.name || '-' }}
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <select
                    v-if="ticketStatuses.length"
                    :value="String(ticket.status_id)"
                    :disabled="statusUpdatingId === ticket.id"
                    title="Ubah status tanpa buka halaman edit"
                    @change="quickUpdateStatus(ticket, $event)"
                    class="max-w-[14rem] cursor-pointer rounded-lg border border-gray-200/80 py-1.5 pl-2 pr-8 text-xs font-semibold shadow-sm focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:cursor-not-allowed disabled:opacity-55"
                    :class="getStatusColor(ticket.status?.slug)"
                  >
                    <option v-for="s in ticketStatuses" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                  </select>
                  <span
                    v-else
                    :class="['inline-flex px-2 py-1 text-xs font-semibold rounded-full', getStatusColor(ticket.status?.slug)]"
                  >
                    {{ ticket.status?.name || '-' }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <div class="space-y-1">
                    <div class="text-xs font-semibold text-gray-800">{{ ticket.outlet?.nama_outlet || '-' }}</div>
                    <div class="text-xs text-gray-500">{{ ticket.divisi?.nama_divisi || '-' }}</div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <div class="flex items-center gap-2 mb-1">
                    <img
                      v-if="getCreatorAvatar(ticket.creator)"
                      :src="getCreatorAvatar(ticket.creator)"
                      :alt="ticket.creator?.nama_lengkap || 'Creator Avatar'"
                      class="h-8 w-8 rounded-full object-cover ring-2 ring-white shadow-sm"
                    />
                    <div
                      v-else
                      class="h-8 w-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-xs font-bold ring-2 ring-white shadow-sm"
                    >
                      {{ getInitials(ticket.creator?.nama_lengkap) }}
                    </div>
                    <span class="text-sm text-gray-900">{{ ticket.creator?.nama_lengkap || '-' }}</span>
                  </div>
                  <div class="text-xs text-gray-500">
                    {{ formatDateTime(ticket.created_at) }}
                  </div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                  <div v-if="ticket.assigned_users?.length" class="space-y-2">
                    <div class="flex items-center gap-2">
                      <img
                        v-if="getUserAvatar(getPrimaryAssignee(ticket))"
                        :src="getUserAvatar(getPrimaryAssignee(ticket))"
                        :alt="getPrimaryAssignee(ticket)?.nama_lengkap || 'PIC Avatar'"
                        class="h-7 w-7 rounded-full object-cover ring-2 ring-white shadow-sm"
                      />
                      <div
                        v-else
                        class="h-7 w-7 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-[10px] font-bold ring-2 ring-white shadow-sm"
                      >
                        {{ getInitials(getPrimaryAssignee(ticket)?.nama_lengkap) }}
                      </div>
                      <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-1 text-[10px] font-bold text-amber-700 uppercase tracking-wide">
                        <i class="fa-solid fa-crown mr-1"></i>PIC
                      </span>
                      <span class="text-xs font-semibold text-gray-800">
                        {{ getPrimaryAssignee(ticket)?.nama_lengkap || '-' }}
                      </span>
                    </div>
                    <div class="flex flex-wrap gap-1">
                      <span
                        v-for="user in getSecondaryAssignees(ticket).slice(0, 2)"
                        :key="`assignee-${ticket.id}-${user.id}`"
                        class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2 py-1 text-xs font-medium text-indigo-700"
                      >
                        <img
                          v-if="getUserAvatar(user)"
                          :src="getUserAvatar(user)"
                          :alt="user.nama_lengkap || 'Member Avatar'"
                          class="h-4 w-4 rounded-full object-cover"
                        />
                        <span
                          v-else
                          class="h-4 w-4 rounded-full bg-indigo-200 text-indigo-800 flex items-center justify-center text-[9px] font-bold"
                        >
                          {{ getInitials(user.nama_lengkap) }}
                        </span>
                        {{ user.nama_lengkap }}
                      </span>
                      <button
                        v-if="getSecondaryAssignees(ticket).length > 2"
                        type="button"
                        @click="showAllAssignees(ticket)"
                        class="inline-flex items-center rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700"
                        title="Lihat semua assigned team"
                      >
                        +{{ getSecondaryAssignees(ticket).length - 2 }}
                      </button>
                    </div>
                  </div>
                  <span v-else class="text-xs text-gray-400">Belum di-assign</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <div v-if="ticket.due_date" class="flex items-center gap-2">
                    <span class="text-gray-900">{{ new Date(ticket.due_date).toLocaleDateString('id-ID') }}</span>
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
                          :class="getDueDateBadgeClass(ticket.due_date, ticket.status?.slug)">
                      <i class="fas fa-clock mr-1"></i>
                      {{ getDueDateStatus(ticket.due_date) }}
                    </span>
                  </div>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                  <div v-if="ticket.payment_info?.total_pr" class="space-y-1">
                    <span
                      :class="[
                        'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold',
                        getTicketPaymentStatusClass(ticket)
                      ]"
                    >
                      {{ getTicketPaymentStatusLabel(ticket) }}
                    </span>
                    <div class="text-xs text-gray-600">
                      PR: {{ ticket.payment_info.total_pr }} | Paid: {{ ticket.payment_info.total_paid_pr || 0 }}
                    </div>
                  </div>
                  <span v-else class="text-xs text-gray-400">Belum ada PR/Payment</span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  <button
                    type="button"
                    @click="openCommentsModal(ticket)"
                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium hover:opacity-80"
                    :class="getCommentBadgeClass(ticket.comments_count)"
                  >
                    <i class="fa-solid fa-comment mr-1"></i>
                    {{ ticket.comments_count || 0 }}
                  </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <div class="flex gap-2">
                    <button @click="viewTicket(ticket)" class="text-blue-600 hover:text-blue-900" title="View">
                      <i class="fa-solid fa-eye"></i>
                    </button>
                    <button @click="editTicket(ticket)" class="text-green-600 hover:text-green-900" title="Edit">
                      <i class="fa-solid fa-edit"></i>
                    </button>
                    <button @click="openAssignTeam(ticket)" class="text-indigo-600 hover:text-indigo-900" title="Assign Team">
                      <i class="fa-solid fa-users"></i>
                    </button>
                    <button @click="openCreatePayment(ticket)" class="text-emerald-600 hover:text-emerald-900" title="Create Payment dari Ticket">
                      <i class="fa-solid fa-money-bill-wave"></i>
                    </button>
                    <button @click="deleteTicket(ticket)" class="text-red-600 hover:text-red-900" title="Delete">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Empty State -->
        <div v-if="data.data.length === 0" class="text-center py-12">
          <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-ticket-alt text-3xl text-gray-400"></i>
          </div>
          <h3 class="text-lg font-medium text-gray-600 mb-2">No Tickets Found</h3>
          <p class="text-gray-500 mb-6">Start by creating your first ticket</p>
          <button @click="openCreate" class="bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 transition-colors">
            <i class="fa-solid fa-plus mr-2"></i>
            Create New Ticket
          </button>
        </div>
      </div>

      <!-- Pagination -->
      <div class="mt-4 flex flex-col sm:flex-row justify-between items-center gap-4">
        <!-- Pagination Info -->
        <div class="text-sm text-gray-600">
          Menampilkan {{ data.from || 0 }} sampai {{ data.to || 0 }} dari {{ data.total || 0 }} tickets
        </div>
        
        <!-- Pagination Navigation -->
        <nav class="flex rounded-lg shadow-sm -space-x-px" aria-label="Pagination">
          <button 
            @click.stop.prevent="goToPage(data.first_page_url)" 
            :disabled="!data.first_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 rounded-l-lg transition-colors',
              !data.first_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50 cursor-pointer'
            ]"
          >
            First
          </button>
          <button 
            @click.stop.prevent="goToPage(data.prev_page_url)" 
            :disabled="!data.prev_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 transition-colors',
              !data.prev_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50 cursor-pointer'
            ]"
          >
            Previous
          </button>
          <template v-for="(link, i) in data.links" :key="i">
            <button 
              v-if="link.url" 
              @click.stop.prevent="goToPage(link.url)" 
              :disabled="link.active"
              :class="[
                'px-3 py-2 text-sm border border-gray-300 transition-colors',
                link.active 
                  ? 'bg-blue-600 text-white border-blue-600 cursor-default' 
                  : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300 cursor-pointer'
              ]" 
              v-html="link.label"
            ></button>
            <span 
              v-else 
              class="px-3 py-2 text-sm border border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed" 
              v-html="link.label"
            ></span>
          </template>
          <button 
            @click.stop.prevent="goToPage(data.next_page_url)" 
            :disabled="!data.next_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 transition-colors',
              !data.next_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50 cursor-pointer'
            ]"
          >
            Next
          </button>
          <button 
            @click.stop.prevent="goToPage(data.last_page_url)" 
            :disabled="!data.last_page_url"
            :class="[
              'px-3 py-2 text-sm border border-gray-300 rounded-r-lg transition-colors',
              !data.last_page_url 
                ? 'bg-gray-100 text-gray-400 cursor-not-allowed' 
                : 'bg-white text-gray-700 hover:bg-gray-50 cursor-pointer'
            ]"
          >
            Last
          </button>
        </nav>
      </div>

      <!-- Comment Modal -->
      <div
        v-if="commentModal.open"
        class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
        @click="closeCommentsModal"
      >
        <div class="w-full max-w-3xl bg-white rounded-2xl shadow-2xl" @click.stop>
          <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">
              Komentar {{ commentModal.ticket?.ticket_number || '' }}
            </h3>
            <button type="button" class="text-gray-500 hover:text-gray-700" @click="closeCommentsModal">
              <i class="fa-solid fa-times"></i>
            </button>
          </div>

          <div class="p-5 space-y-4">
            <div class="max-h-72 overflow-auto pr-1 space-y-2">
              <div
                v-for="comment in commentModal.comments"
                :key="`modal-comment-${comment.id}`"
                class="border border-gray-200 rounded-xl p-3 bg-white"
              >
                <div class="flex items-start justify-between mb-1 gap-2">
                  <div class="flex items-center gap-2 min-w-0">
                    <img
                      v-if="getUserAvatar(comment.user)"
                      :src="getUserAvatar(comment.user)"
                      :alt="comment.user?.nama_lengkap || 'User Avatar'"
                      class="h-7 w-7 rounded-full object-cover ring-2 ring-white shadow-sm flex-shrink-0"
                    />
                    <div
                      v-else
                      class="h-7 w-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-[10px] font-bold ring-2 ring-white shadow-sm flex-shrink-0"
                    >
                      {{ getInitials(comment.user?.nama_lengkap || 'U') }}
                    </div>
                    <p class="text-xs font-semibold text-gray-800 truncate">{{ comment.user?.nama_lengkap || 'Unknown' }}</p>
                  </div>
                  <p class="text-[11px] text-gray-500 whitespace-nowrap">{{ formatDateTime(comment.created_at) }}</p>
                </div>
                <p v-if="comment.comment" class="text-sm text-gray-700 whitespace-pre-wrap">{{ comment.comment }}</p>
                <div v-if="comment.attachments?.length" class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2">
                  <a
                    v-for="attachment in comment.attachments"
                    :key="`modal-comment-file-${attachment.id}`"
                    :href="attachment.file_path.startsWith('/storage/') ? attachment.file_path : `/storage/${attachment.file_path}`"
                    target="_blank"
                    class="border border-gray-200 rounded-lg p-2 hover:bg-gray-50"
                  >
                    <img
                      v-if="attachment.mime_type && attachment.mime_type.startsWith('image/')"
                      :src="attachment.file_path.startsWith('/storage/') ? attachment.file_path : `/storage/${attachment.file_path}`"
                      :alt="attachment.file_name"
                      class="w-full h-20 object-cover rounded"
                    />
                    <div v-else class="flex items-center gap-2 text-xs text-gray-700">
                      <i class="fa-solid fa-file"></i>
                      <span class="break-words">{{ attachment.file_name }}</span>
                    </div>
                    <p v-if="attachment.mime_type && attachment.mime_type.startsWith('image/')" class="text-[10px] text-gray-600 mt-1 break-words">
                      {{ attachment.file_name }}
                    </p>
                  </a>
                </div>
              </div>
              <div v-if="!commentModal.loading && commentModal.comments.length === 0" class="text-xs text-gray-500">
                Belum ada komentar.
              </div>
              <div v-if="commentModal.loading" class="text-xs text-gray-500">Loading komentar...</div>
            </div>

            <div class="border-t border-gray-200 pt-4">
              <textarea
                v-model="commentModal.newComment"
                rows="3"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 focus:border-blue-400"
                placeholder="Tulis komentar..."
              ></textarea>
              <div class="mt-2 flex flex-wrap items-center gap-2">
                <button type="button" @click="openIndexCommentFileUpload" class="px-3 py-1.5 text-xs rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200">
                  <i class="fa-solid fa-upload mr-1"></i> Upload
                </button>
                <button type="button" @click="openIndexCommentCamera" class="px-3 py-1.5 text-xs rounded-md bg-blue-100 text-blue-700 hover:bg-blue-200">
                  <i class="fa-solid fa-camera mr-1"></i> Camera
                </button>
                <input
                  ref="indexCommentFileInput"
                  type="file"
                  multiple
                  accept="image/*,.pdf,.doc,.docx"
                  class="hidden"
                  @change="handleIndexCommentFileUpload"
                />
              </div>

              <div v-if="commentModal.attachments.length > 0" class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2">
                <div
                  v-for="(attachment, idx) in commentModal.attachments"
                  :key="`index-comment-attachment-${idx}`"
                  class="relative border border-gray-200 rounded-lg p-2"
                >
                  <button
                    type="button"
                    @click="removeIndexCommentAttachment(idx)"
                    class="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-red-500 text-white text-[10px]"
                  >
                    <i class="fa-solid fa-times"></i>
                  </button>
                  <img v-if="attachment.type.startsWith('image/')" :src="attachment.preview" :alt="attachment.name" class="w-full h-20 object-cover rounded" />
                  <div v-else class="text-xs text-gray-700 break-words">
                    <i class="fa-solid fa-file mr-1"></i>{{ attachment.name }}
                  </div>
                </div>
              </div>

              <div class="mt-3 flex justify-end gap-2">
                <button type="button" class="px-3 py-1.5 text-xs rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200" @click="closeCommentsModal">
                  Tutup
                </button>
                <button
                  type="button"
                  :disabled="!canSubmitIndexComment || commentModal.saving"
                  @click="submitCommentFromModal"
                  class="px-3 py-1.5 text-xs rounded-md bg-blue-600 text-white hover:bg-blue-700 disabled:bg-gray-300"
                >
                  <i v-if="commentModal.saving" class="fa-solid fa-spinner fa-spin mr-1"></i>
                  Kirim Komentar
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  data: Object,
  filters: Object,
  filterOptions: Object,
  assignableUsers: {
    type: Array,
    default: () => []
  },
  statistics: {
    type: Object,
    default: () => ({
      total: 0,
      open: 0,
      in_progress: 0,
      closed: 0
    })
  },
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || 'all');
const priority = ref(props.filters?.priority || 'all');
const division = ref(props.filters?.division || 'all');
const outlet = ref(props.filters?.outlet || 'all');
const paymentStatus = ref(props.filters?.payment_status || 'all');
const perPage = ref(props.filters?.per_page || 15);
const importFileInput = ref(null);
const statusUpdatingId = ref(null);
const ticketStatuses = computed(() => props.filterOptions?.statuses ?? []);
const commentModal = ref({
  open: false,
  loading: false,
  saving: false,
  ticket: null,
  comments: [],
  newComment: '',
  attachments: []
});
const indexCommentFileInput = ref(null);
const hasActiveFilters = computed(() => {
  return (
    status.value !== 'all' ||
    priority.value !== 'all' ||
    division.value !== 'all' ||
    outlet.value !== 'all' ||
    paymentStatus.value !== 'all' ||
    (search.value && search.value.trim() !== '')
  );
});
const canSubmitIndexComment = computed(() => {
  return !!commentModal.value.newComment?.trim() || commentModal.value.attachments.length > 0;
});

const debouncedSearch = debounce(() => {
  router.get('/tickets', {
    search: search.value,
    status: status.value,
    priority: priority.value,
    division: division.value,
    outlet: outlet.value,
    payment_status: paymentStatus.value,
    per_page: perPage.value,
  }, { preserveState: true, replace: true });
}, 400);

function onSearchInput() {
  debouncedSearch();
}

function goToPage(url) {
  if (!url) return;
  
  try {
    // Handle relative URLs
    const fullUrl = url.startsWith('http') ? url : window.location.origin + url;
    const urlObj = new URL(fullUrl);
    
    // Preserve current filters
    urlObj.searchParams.set('search', search.value);
    urlObj.searchParams.set('status', status.value);
    urlObj.searchParams.set('priority', priority.value);
    urlObj.searchParams.set('division', division.value);
    urlObj.searchParams.set('outlet', outlet.value);
    urlObj.searchParams.set('payment_status', paymentStatus.value);
    urlObj.searchParams.set('per_page', perPage.value);
    
    // Extract pathname and query string
    const path = urlObj.pathname + '?' + urlObj.searchParams.toString();
    
    router.visit(path, { preserveState: true, replace: true });
  } catch (error) {
    console.error('Error in goToPage:', error);
    // Fallback: use router.get with query params
    router.get('/tickets', {
      page: url.match(/page=(\d+)/)?.[1] || 1,
      search: search.value,
      status: status.value,
      priority: priority.value,
      division: division.value,
      outlet: outlet.value,
      payment_status: paymentStatus.value,
      per_page: perPage.value,
    }, { preserveState: true, replace: true });
  }
}

function setStatusFilter(nextStatus) {
  status.value = nextStatus;
  debouncedSearch();
}

function resetFilters() {
  search.value = '';
  status.value = 'all';
  priority.value = 'all';
  division.value = 'all';
  outlet.value = 'all';
  paymentStatus.value = 'all';
  perPage.value = 15;
  debouncedSearch();
}

function openCreate() {
  router.visit('/tickets/create');
}

function downloadImportTemplate() {
  window.open('/tickets/import/template', '_blank');
}

function openImportFilePicker() {
  if (importFileInput.value) {
    importFileInput.value.value = '';
    importFileInput.value.click();
  }
}

async function handleImportFileChange(event) {
  const file = event.target?.files?.[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('file', file);

  const loadingSwal = Swal.fire({
    title: 'Importing...',
    text: 'Sedang memproses file Excel',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading();
    },
  });

  try {
    const response = await axios.post('/tickets/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    await loadingSwal;
    Swal.close();

    const errors = response.data?.errors || [];
    if (errors.length > 0) {
      await Swal.fire({
        icon: 'warning',
        title: 'Import selesai dengan catatan',
        html: `
          <div style="text-align:left;max-height:240px;overflow:auto;">
            <p><strong>${response.data?.message || 'Import selesai'}</strong></p>
            <ul style="margin-top:8px;padding-left:18px;">
              ${errors.map((err) => `<li>${escapeHtml(err)}</li>`).join('')}
            </ul>
          </div>
        `,
      });
    } else {
      await Swal.fire('Berhasil', response.data?.message || 'Import berhasil', 'success');
    }

    router.reload();
  } catch (error) {
    Swal.close();
    const apiErrors = error.response?.data?.errors;
    if (Array.isArray(apiErrors) && apiErrors.length > 0) {
      Swal.fire({
        icon: 'error',
        title: 'Import gagal',
        html: `
          <div style="text-align:left;max-height:240px;overflow:auto;">
            <p><strong>${escapeHtml(error.response?.data?.message || 'Validasi import gagal')}</strong></p>
            <ul style="margin-top:8px;padding-left:18px;">
              ${apiErrors.map((err) => `<li>${escapeHtml(err)}</li>`).join('')}
            </ul>
          </div>
        `,
      });
      return;
    }

    Swal.fire('Error', error.response?.data?.message || 'Gagal import data dari Excel', 'error');
  }
}

async function quickUpdateStatus(ticket, event) {
  const select = event?.target;
  if (!select) return;
  const newId = select.value;
  if (String(newId) === String(ticket.status_id)) return;

  const previousId = String(ticket.status_id);
  statusUpdatingId.value = ticket.id;
  try {
    const response = await axios.patch(`/tickets/${ticket.id}/status`, {
      status_id: newId,
    });
    if (response.data?.success) {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: response.data.message || 'Status diperbarui',
        showConfirmButton: false,
        timer: 1800,
      });
      router.reload({ preserveScroll: true });
    }
  } catch (error) {
    select.value = previousId;
    const msg =
      error.response?.data?.message ||
      (error.response?.data?.errors?.status_id?.[0] ?? null) ||
      'Gagal mengubah status';
    Swal.fire('Error', msg, 'error');
  } finally {
    statusUpdatingId.value = null;
  }
}

function viewTicket(ticket) {
  router.visit(`/tickets/${ticket.id}`);
}

function editTicket(ticket) {
  router.visit(`/tickets/${ticket.id}/edit`);
}

function openCreatePayment(ticket) {
  router.visit(`/purchase-requisitions/create?mode=purchase_payment&ticket_id=${encodeURIComponent(ticket.id)}`);
}

async function deleteTicket(ticket) {
  const result = await Swal.fire({
    title: 'Hapus Ticket?',
    text: `Yakin ingin menghapus ticket ${ticket.ticket_number}?`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: 'Ya, Hapus!',
    cancelButtonText: 'Batal',
  });
  
  if (!result.isConfirmed) return;
  
  try {
    const response = await axios.delete(`/tickets/${ticket.id}`);
    if (response.data.success) {
      Swal.fire('Berhasil', response.data.message, 'success');
      router.reload();
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menghapus ticket', 'error');
  }
}

async function openAssignTeam(ticket) {
  const divisionUsers = (props.assignableUsers || []).filter(
    (user) => String(user.division_id) === String(ticket.divisi_id)
  );
  const users = divisionUsers.length ? divisionUsers : (props.assignableUsers || []);
  const selectedIds = (ticket.assigned_users || []).map((user) => String(user.id));
  const primarySelected = String(
    (ticket.assigned_users || []).find((user) => user?.pivot?.is_primary)?.id || selectedIds[0] || ''
  );

  const usersHtml = users.map((user) => {
    const checked = selectedIds.includes(String(user.id)) ? 'checked' : '';
    const primaryChecked = String(user.id) === primarySelected ? 'checked' : '';
    return `
      <div class="assign-user-item" data-user-id="${user.id}" style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 10px;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:8px;">
        <label style="display:flex;align-items:center;gap:8px;flex:1;cursor:pointer;">
          <input type="checkbox" class="assign-user-checkbox" value="${user.id}" ${checked} />
          <span style="font-size:13px;color:#111827;">${escapeHtml(user.nama_lengkap || 'Unknown')}</span>
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#4b5563;cursor:pointer;">
          <input type="radio" name="assign-primary-user" class="assign-user-primary" value="${user.id}" ${primaryChecked} />
          PIC
        </label>
      </div>
    `;
  }).join('');

  const { isConfirmed, value } = await Swal.fire({
    title: `Assign Team - ${ticket.ticket_number}`,
    html: `
      <div style="text-align:left">
        <label style="display:block;font-size:12px;margin-bottom:8px;color:#6b7280;">Pilih anggota tim (klik biasa, tidak perlu Ctrl)</label>
        <div id="assign-users-list" style="max-height:260px;overflow:auto;padding-right:4px;">
          ${usersHtml || '<div style="font-size:12px;color:#6b7280;">Tidak ada user tersedia</div>'}
        </div>
      </div>
    `,
    focusConfirm: false,
    didOpen: () => {
      const checkboxes = Array.from(document.querySelectorAll('.assign-user-checkbox'));
      const radios = Array.from(document.querySelectorAll('.assign-user-primary'));

      const syncPrimaryState = () => {
        const checkedIds = checkboxes.filter((cb) => cb.checked).map((cb) => String(cb.value));
        radios.forEach((radio) => {
          const isCheckedUser = checkedIds.includes(String(radio.value));
          radio.disabled = !isCheckedUser;
          if (!isCheckedUser) radio.checked = false;
        });

        const selectedPrimary = radios.find((radio) => radio.checked && !radio.disabled);
        if (!selectedPrimary && checkedIds.length > 0) {
          const firstCheckedRadio = radios.find((radio) => String(radio.value) === checkedIds[0]);
          if (firstCheckedRadio) firstCheckedRadio.checked = true;
        }
      };

      checkboxes.forEach((cb) => cb.addEventListener('change', syncPrimaryState));
      syncPrimaryState();
    },
    preConfirm: () => {
      const userIds = Array.from(document.querySelectorAll('.assign-user-checkbox'))
        .filter((cb) => cb.checked)
        .map((cb) => Number(cb.value));
      const selectedPrimary = Array.from(document.querySelectorAll('.assign-user-primary'))
        .find((radio) => radio.checked && !radio.disabled);
      const primaryUserId = selectedPrimary ? Number(selectedPrimary.value) : null;

      if (!userIds.length) {
        Swal.showValidationMessage('Minimal pilih 1 anggota tim');
        return null;
      }

      return {
        user_ids: userIds,
        primary_user_id: primaryUserId
      };
    },
    showCancelButton: true,
    confirmButtonText: 'Simpan Assign',
    cancelButtonText: 'Batal'
  });

  if (!isConfirmed || !value) return;

  try {
    const response = await axios.post(`/tickets/${ticket.id}/assign-team`, value);
    if (response.data.success) {
      const updatedAssignedUsers = response.data.data?.assigned_users || [];
      ticket.assigned_users = updatedAssignedUsers;
      Swal.fire('Berhasil', response.data.message || 'Team berhasil di-assign', 'success');
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal assign team', 'error');
  }
}

async function openCommentsModal(ticket) {
  commentModal.value.open = true;
  commentModal.value.loading = true;
  commentModal.value.ticket = ticket;
  commentModal.value.comments = [];
  commentModal.value.newComment = '';
  commentModal.value.attachments = [];
  try {
    const response = await axios.get(`/tickets/${ticket.id}/comments`);
    commentModal.value.comments = response.data?.data || [];
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal memuat komentar', 'error');
    closeCommentsModal();
  } finally {
    commentModal.value.loading = false;
  }
}

function closeCommentsModal() {
  commentModal.value.open = false;
  commentModal.value.loading = false;
  commentModal.value.saving = false;
  commentModal.value.ticket = null;
  commentModal.value.comments = [];
  commentModal.value.newComment = '';
  commentModal.value.attachments = [];
}

function openIndexCommentFileUpload() {
  indexCommentFileInput.value?.click();
}

function handleIndexCommentFileUpload(event) {
  const files = Array.from(event.target.files || []);
  files.forEach((file) => {
    if (file.size > 10 * 1024 * 1024) return;
    const attachment = {
      file,
      name: file.name,
      type: file.type || '',
      preview: null
    };
    if (file.type?.startsWith('image/')) {
      const reader = new FileReader();
      reader.onload = (e) => {
        attachment.preview = e.target.result;
      };
      reader.readAsDataURL(file);
    }
    commentModal.value.attachments.push(attachment);
  });
  event.target.value = '';
}

function removeIndexCommentAttachment(index) {
  commentModal.value.attachments.splice(index, 1);
}

function openIndexCommentCamera() {
  const modal = document.createElement('div');
  modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60]';
  modal.innerHTML = `
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-semibold">Capture Camera</h3>
        <button id="index-comment-camera-close" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
      </div>
      <video id="index-comment-camera-video" class="w-full h-64 bg-gray-200 rounded" autoplay></video>
      <div class="mt-3 flex gap-2">
        <button id="index-comment-camera-capture" class="flex-1 bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">Capture</button>
        <button id="index-comment-camera-cancel" class="flex-1 bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600">Cancel</button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  let streamRef = null;

  const closeModal = () => {
    if (streamRef) streamRef.getTracks().forEach((track) => track.stop());
    modal.remove();
  };

  modal.querySelector('#index-comment-camera-close')?.addEventListener('click', closeModal);
  modal.querySelector('#index-comment-camera-cancel')?.addEventListener('click', closeModal);

  navigator.mediaDevices.getUserMedia({ video: true }).then((stream) => {
    streamRef = stream;
    const video = modal.querySelector('#index-comment-camera-video');
    video.srcObject = stream;
    modal.querySelector('#index-comment-camera-capture')?.addEventListener('click', () => {
      const canvas = document.createElement('canvas');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0);
      canvas.toBlob((blob) => {
        if (!blob) return;
        const file = new File([blob], `comment-camera-${Date.now()}.jpg`, { type: 'image/jpeg' });
        commentModal.value.attachments.push({
          file,
          name: file.name,
          type: file.type,
          preview: canvas.toDataURL('image/jpeg', 0.8)
        });
        closeModal();
      }, 'image/jpeg', 0.8);
    });
  }).catch(() => {
    Swal.fire('Error', 'Camera tidak dapat diakses', 'error');
    closeModal();
  });
}

async function submitCommentFromModal() {
  if (!canSubmitIndexComment.value || !commentModal.value.ticket) return;
  commentModal.value.saving = true;
  try {
    const formData = new FormData();
    formData.append('comment', commentModal.value.newComment?.trim() || '');
    commentModal.value.attachments.forEach((attachment, index) => {
      formData.append(`attachments[${index}]`, attachment.file);
    });

    const response = await axios.post(`/tickets/${commentModal.value.ticket.id}/comments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    if (response.data?.success) {
      commentModal.value.comments.unshift(response.data.data);
      commentModal.value.newComment = '';
      commentModal.value.attachments = [];
      commentModal.value.ticket.comments_count = Number(commentModal.value.ticket.comments_count || 0) + 1;
    }
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Gagal menambahkan komentar', 'error');
  } finally {
    commentModal.value.saving = false;
  }
}

function getStatusColor(status) {
  return {
    'open': 'bg-blue-100 text-blue-800',
    'in_progress': 'bg-yellow-100 text-yellow-800',
    'pending': 'bg-purple-100 text-purple-800',
    'resolved': 'bg-green-100 text-green-800',
    'closed': 'bg-gray-100 text-gray-800',
    'cancelled': 'bg-red-100 text-red-800'
  }[status] || 'bg-gray-100 text-gray-800';
}

function getCommentBadgeClass(commentCount) {
  if (!commentCount || commentCount === 0) {
    return 'bg-gray-100 text-gray-500';
  } else if (commentCount <= 2) {
    return 'bg-blue-100 text-blue-600';
  } else if (commentCount <= 5) {
    return 'bg-yellow-100 text-yellow-600';
  } else {
    return 'bg-red-100 text-red-600';
  }
}

function getPaymentFilterLabel(value) {
  if (value === 'paid') return 'Paid';
  if (value === 'on_process') return 'On Process';
  if (value === 'with_pr') return 'Sudah ada PR';
  if (value === 'no_pr') return 'Belum ada PR';
  return 'Semua Payment';
}

function getTicketPaymentStatusLabel(ticket) {
  const info = ticket?.payment_info;
  if (!info || !info.total_pr) return 'Belum ada PR';
  if ((info.total_paid_pr || 0) > 0) return 'Sudah Paid';
  if ((info.total_processing_pr || 0) > 0) return 'Payment Proses';
  return 'PR Dibuat';
}

function getTicketPaymentStatusClass(ticket) {
  const info = ticket?.payment_info;
  if (!info || !info.total_pr) return 'bg-gray-100 text-gray-700';
  if ((info.total_paid_pr || 0) > 0) return 'bg-emerald-100 text-emerald-700';
  if ((info.total_processing_pr || 0) > 0) return 'bg-amber-100 text-amber-700';
  return 'bg-blue-100 text-blue-700';
}

function getDueDateStatus(dueDate) {
  if (!dueDate) return 'No Due Date';
  
  const today = new Date();
  const due = new Date(dueDate);
  const diffTime = due - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  if (diffDays < 0) {
    return 'Overdue';
  } else if (diffDays === 0) {
    return 'Due Today';
  } else if (diffDays === 1) {
    return 'Due Tomorrow';
  } else if (diffDays <= 3) {
    return `${diffDays} days left`;
  } else {
    return `${diffDays} days left`;
  }
}

function getDueDateBadgeClass(dueDate, status) {
  if (!dueDate) return 'bg-gray-100 text-gray-500';

  const today = new Date();
  const due = new Date(dueDate);
  const diffTime = due - today;
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

  if (diffDays < 0) {
    // Overdue should always be red regardless of status
    return 'bg-red-100 text-red-600';
  } else if (status === 'closed' || status === 'resolved') {
    // Completed and not overdue
    return 'bg-green-100 text-green-600';
  } else if (diffDays === 0) {
    // Due today - Orange
    return 'bg-orange-100 text-orange-600';
  } else if (diffDays === 1) {
    // Due tomorrow - Yellow
    return 'bg-yellow-100 text-yellow-600';
  } else if (diffDays <= 3) {
    // Due in 2-3 days - Light yellow
    return 'bg-yellow-50 text-yellow-700';
  } else {
    // More than 3 days - Green
    return 'bg-green-100 text-green-600';
  }
}

function getPriorityColor(level) {
  return {
    1: 'bg-green-100 text-green-800', // Low
    2: 'bg-yellow-100 text-yellow-800', // Medium
    3: 'bg-orange-100 text-orange-800', // High
    4: 'bg-red-100 text-red-800' // Critical
  }[level] || 'bg-gray-100 text-gray-800';
}

function normalizeIssueText(value) {
  return String(value || '').toLowerCase().replace(/[_-]/g, ' ').trim();
}

function inferIssueType(ticket) {
  const issueType = normalizeIssueText(ticket?.issue_type);
  if (issueType === 'defect') return 'defect';
  if (issueType === 'ops issue') return 'ops_issue';

  const categoryName = normalizeIssueText(ticket?.category?.name);
  if (categoryName.includes('defect')) return 'defect';
  if (categoryName.includes('ops issue') || categoryName.includes('ops') || categoryName.includes('operation')) return 'ops_issue';
  return '';
}

function getIssueTypeLabel(ticket) {
  const issueType = inferIssueType(ticket);
  if (issueType === 'defect') return 'Defect';
  if (issueType === 'ops_issue') return 'Ops Issue';
  return '';
}

function getIssueTypeClass(ticket) {
  const issueType = inferIssueType(ticket);
  if (issueType === 'defect') return 'bg-rose-100 text-rose-700';
  if (issueType === 'ops_issue') return 'bg-cyan-100 text-cyan-700';
  return 'bg-gray-100 text-gray-600';
}

function formatDateTime(datetime) {
  if (!datetime) return '-';
  return new Date(datetime).toLocaleString('id-ID', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false
  });
}

function readableStatus(statusValue) {
  return {
    open: 'Open',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    closed: 'Closed',
    pending: 'Pending'
  }[statusValue] || statusValue;
}

function getPriorityLabel(priorityId) {
  const item = props.filterOptions?.priorities?.find((p) => String(p.id) === String(priorityId));
  return item?.name || '-';
}

function getDivisionLabel(divisionId) {
  const item = props.filterOptions?.divisions?.find((d) => String(d.id) === String(divisionId));
  return item?.nama_divisi || '-';
}

function getCreatorAvatar(creator) {
  if (!creator?.avatar) return null;
  const avatar = String(creator.avatar).trim();
  if (!avatar) return null;
  if (avatar.startsWith('http://') || avatar.startsWith('https://') || avatar.startsWith('/')) {
    return avatar;
  }
  return `/storage/${avatar}`;
}

function getInitials(name) {
  if (!name) return 'NA';
  const words = String(name).trim().split(/\s+/).filter(Boolean);
  if (words.length === 0) return 'NA';
  if (words.length === 1) return words[0].slice(0, 2).toUpperCase();
  return `${words[0][0] || ''}${words[1][0] || ''}`.toUpperCase();
}

function getOutletLabel(outletId) {
  const item = props.filterOptions?.outlets?.find((o) => String(o.id_outlet) === String(outletId));
  return item?.nama_outlet || '-';
}

function getPrimaryAssignee(ticket) {
  if (!ticket?.assigned_users?.length) return null;
  return ticket.assigned_users.find((user) => user?.pivot?.is_primary) || ticket.assigned_users[0];
}

function getSecondaryAssignees(ticket) {
  if (!ticket?.assigned_users?.length) return [];
  const primary = getPrimaryAssignee(ticket);
  return ticket.assigned_users.filter((user) => String(user.id) !== String(primary?.id));
}

function getUserAvatar(user) {
  if (!user?.avatar) return null;
  const avatar = String(user.avatar).trim();
  if (!avatar) return null;
  if (avatar.startsWith('http://') || avatar.startsWith('https://') || avatar.startsWith('/')) {
    return avatar;
  }
  return `/storage/${avatar}`;
}

async function showAllAssignees(ticket) {
  const assignedUsers = ticket?.assigned_users || [];
  if (!assignedUsers.length) return;

  const rowsHtml = assignedUsers.map((user) => {
    const isPrimary = user?.pivot?.is_primary;
    const avatar = getUserAvatar(user);
    const avatarHtml = avatar
      ? `<img src="${escapeHtml(avatar)}" alt="${escapeHtml(user?.nama_lengkap || 'User')}" style="width:28px;height:28px;border-radius:9999px;object-fit:cover;" />`
      : `<div style="width:28px;height:28px;border-radius:9999px;background:#dbeafe;color:#1d4ed8;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;">${escapeHtml(getInitials(user?.nama_lengkap))}</div>`;

    const primaryBadge = isPrimary
      ? '<span style="font-size:10px;font-weight:700;background:#fef3c7;color:#b45309;padding:2px 8px;border-radius:9999px;">PIC</span>'
      : '';

    return `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:8px;">
        <div style="display:flex;align-items:center;gap:8px;">
          ${avatarHtml}
          <span style="font-size:13px;color:#111827;">${escapeHtml(user?.nama_lengkap || 'Unknown')}</span>
        </div>
        ${primaryBadge}
      </div>
    `;
  }).join('');

  await Swal.fire({
    title: `Assigned Team - ${ticket.ticket_number}`,
    html: `<div style="text-align:left;max-height:300px;overflow:auto;padding-right:4px;">${rowsHtml}</div>`,
    confirmButtonText: 'Tutup'
  });
}

function escapeHtml(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

watch([search, status, priority, division, outlet, paymentStatus, perPage], () => {
  debouncedSearch();
});
</script>
