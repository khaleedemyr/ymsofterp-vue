<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8 px-4">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
            <i class="fa-solid fa-server text-blue-600"></i>
            Server Performance Monitoring
          </h1>
          <p class="text-gray-600 mt-1">Real-time monitoring server performance dan database queries</p>
        </div>
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2" :class="isMonitoring ? 'bg-green-50' : 'bg-gray-50'" :style="{ padding: '8px 16px', borderRadius: '8px' }">
            <div class="w-3 h-3 rounded-full" :class="isMonitoring ? 'bg-green-500 animate-pulse' : 'bg-gray-400'"></div>
            <span class="text-sm font-semibold" :class="isMonitoring ? 'text-green-700' : 'text-gray-700'">
              {{ isMonitoring ? 'Live' : 'Paused' }}
            </span>
          </div>
          <button
            @click="toggleMonitoring"
            :class="isMonitoring ? 'bg-red-500 hover:bg-red-600' : 'bg-blue-500 hover:bg-blue-600'"
            class="text-white px-4 py-2 rounded-lg transition-colors"
          >
            {{ isMonitoring ? 'Stop' : 'Start' }} Monitoring
          </button>
          <select
            v-model="refreshInterval"
            @change="updateInterval"
            class="border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          >
            <option :value="2">2 detik</option>
            <option :value="5">5 detik</option>
            <option :value="10">10 detik</option>
            <option :value="30">30 detik</option>
          </select>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <!-- MySQL Connections -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 text-sm font-medium">Threads Connected</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ mysqlStatus.Threads_connected || 0 }}</p>
              <p class="text-xs text-gray-500 mt-1">Max: {{ mysqlStatus.Max_used_connections || 0 }}</p>
            </div>
            <div class="bg-blue-100 p-4 rounded-full">
              <i class="fa-solid fa-plug text-blue-600 text-2xl"></i>
            </div>
          </div>
        </div>

        <!-- Threads Running -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4" :class="getThreadsRunningClass()">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 text-sm font-medium">Threads Running</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ mysqlStatus.Threads_running || 0 }}</p>
              <p class="text-xs text-gray-500 mt-1">Active queries</p>
            </div>
            <div class="p-4 rounded-full" :class="getThreadsRunningBgClass()">
              <i class="fa-solid fa-spinner text-2xl" :class="getThreadsRunningIconClass()"></i>
            </div>
          </div>
        </div>

        <!-- Slow Queries -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 text-sm font-medium">Slow Queries</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ mysqlStatus.Slow_queries || 0 }}</p>
              <p class="text-xs text-gray-500 mt-1">Total since start</p>
            </div>
            <div class="bg-yellow-100 p-4 rounded-full">
              <i class="fa-solid fa-clock text-yellow-600 text-2xl"></i>
            </div>
          </div>
        </div>

        <!-- Total Questions -->
        <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-gray-600 text-sm font-medium">Total Questions</p>
              <p class="text-3xl font-bold text-gray-900 mt-2">{{ formatNumber(mysqlStatus.Questions) }}</p>
              <p class="text-xs text-gray-500 mt-1">Queries executed</p>
            </div>
            <div class="bg-green-100 p-4 rounded-full">
              <i class="fa-solid fa-database text-green-600 text-2xl"></i>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-xl shadow-lg mb-6">
        <div class="border-b border-gray-200">
          <nav class="flex -mb-px">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="activeTab === tab.id ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
              class="px-6 py-4 text-sm font-medium border-b-2 transition-colors"
            >
              <i :class="tab.icon" class="mr-2"></i>
              {{ tab.label }}
            </button>
          </nav>
        </div>

        <div class="p-6">
          <!-- Active Users Tab -->
          <div v-if="activeTab === 'active-users'" class="space-y-4">
            <div class="flex justify-between items-center mb-4">
              <div>
                <h3 class="text-lg font-semibold">Active Users</h3>
                <p class="text-sm text-gray-600">Users yang sedang aktif dalam 15 menit terakhir</p>
              </div>
              <button
                @click="loadActiveUsers"
                class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors"
              >
                <i class="fa-solid fa-refresh mr-2"></i>Refresh
              </button>
            </div>
            <div v-if="loading.activeUsers" class="text-center py-8">
              <i class="fa-solid fa-spinner fa-spin text-3xl text-blue-500"></i>
              <p class="mt-2 text-gray-600">Loading active users...</p>
            </div>
            <div v-else-if="activeUsers.length === 0" class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-users text-4xl mb-2"></i>
              <p>No active users</p>
            </div>
            <div v-else class="space-y-4">
              <!-- Summary Cards -->
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-xs text-gray-600 font-medium">Total Active Users</p>
                      <p class="text-2xl font-bold text-blue-700 mt-1">{{ activeUsers.length }}</p>
                    </div>
                    <div class="bg-blue-100 p-3 rounded-full">
                      <i class="fa-solid fa-users text-blue-600 text-xl"></i>
                    </div>
                  </div>
                </div>
                <div
                  v-for="(app, index) in activeUsersBreakdown"
                  :key="index"
                  class="border rounded-lg p-4"
                  :class="getBreakdownCardClass(app.application)"
                >
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-xs text-gray-600 font-medium">{{ app.application }}</p>
                      <p class="text-2xl font-bold mt-1" :class="getBreakdownTextClass(app.application)">
                        {{ app.count }}
                      </p>
                      <p class="text-xs text-gray-500 mt-1">
                        {{ ((app.count / activeUsers.length) * 100).toFixed(1) }}% dari total
                      </p>
                    </div>
                    <div class="p-3 rounded-full" :class="getBreakdownIconBgClass(app.application)">
                      <i :class="[getApplicationIcon(app.application), getBreakdownIconClass(app.application)]"></i>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Breakdown Table -->
              <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Breakdown per Aplikasi</h4>
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aplikasi</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Jumlah User</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Persentase</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Progress</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <tr v-for="(app, index) in activeUsersBreakdown" :key="index" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm">
                          <div class="flex items-center gap-2">
                            <i :class="[getApplicationIcon(app.application), getBreakdownIconClass(app.application)]"></i>
                            <span class="font-medium">{{ app.application }}</span>
                          </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-center font-semibold">{{ app.count }}</td>
                        <td class="px-4 py-3 text-sm text-center text-gray-600">
                          {{ ((app.count / activeUsers.length) * 100).toFixed(1) }}%
                        </td>
                        <td class="px-4 py-3 text-sm">
                          <div class="w-full bg-gray-200 rounded-full h-2">
                            <div
                              class="h-2 rounded-full transition-all"
                              :class="getBreakdownProgressClass(app.application)"
                              :style="{ width: ((app.count / activeUsers.length) * 100) + '%' }"
                            ></div>
                          </div>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Application</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Activity</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Action</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                      <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="(user, index) in activeUsers" :key="index" class="hover:bg-gray-50">
                      <td class="px-4 py-3 text-sm">
                        <div>
                          <div class="font-semibold text-gray-900">{{ user.name }}</div>
                          <div class="text-xs text-gray-500">{{ user.email || user.member_id || '-' }}</div>
                        </div>
                      </td>
                      <td class="px-4 py-3 text-sm">
                        <span class="px-2 py-1 rounded text-xs font-medium" :class="getApplicationClass(user.application)">
                          <i :class="getApplicationIcon(user.application)" class="mr-1"></i>
                          {{ user.application }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-sm text-gray-600">
                        {{ formatDate(user.last_activity) }}
                        <div class="text-xs text-gray-400">{{ getTimeAgo(user.last_activity) }}</div>
                      </td>
                      <td class="px-4 py-3 text-sm">
                        <div v-if="user.last_action || user.last_route" class="space-y-1">
                          <div v-if="user.last_action" class="text-gray-700 font-medium">
                            {{ user.last_action }}
                          </div>
                          <div v-if="user.last_route" class="text-xs text-gray-500">
                            <i class="fa-solid fa-link mr-1"></i>
                            <span v-if="user.last_method" class="font-mono text-blue-600">{{ user.last_method }}</span>
                            <span class="text-gray-600">{{ user.last_route }}</span>
                          </div>
                          <div v-else-if="!user.last_action" class="text-gray-400">-</div>
                        </div>
                        <span v-else class="text-gray-400">-</span>
                      </td>
                      <td class="px-4 py-3 text-sm text-gray-600">{{ user.ip_address || '-' }}</td>
                      <td class="px-4 py-3 text-sm">
                        <span class="px-2 py-1 rounded text-xs" :class="getTypeClass(user.type)">
                          {{ user.type }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Active Processes Tab -->
          <div v-if="activeTab === 'processes'" class="space-y-4">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold">Active MySQL Processes</h3>
              <button
                @click="loadProcesses"
                class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors"
              >
                <i class="fa-solid fa-refresh mr-2"></i>Refresh
              </button>
            </div>
            <div v-if="loading.processes" class="text-center py-8">
              <i class="fa-solid fa-spinner fa-spin text-3xl text-blue-500"></i>
              <p class="mt-2 text-gray-600">Loading processes...</p>
            </div>
            <div v-else-if="processes.length === 0" class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-check-circle text-4xl mb-2"></i>
              <p>No active processes</p>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">DB</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Command</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time (s)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">State</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Query</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr
                    v-for="process in processes"
                    :key="process.id"
                    :class="process.time > 5 ? 'bg-red-50' : process.time > 2 ? 'bg-yellow-50' : ''"
                  >
                    <td class="px-4 py-3 text-sm">{{ process.id }}</td>
                    <td class="px-4 py-3 text-sm">{{ process.user }}</td>
                    <td class="px-4 py-3 text-sm">{{ process.db || '-' }}</td>
                    <td class="px-4 py-3 text-sm">
                      <span class="px-2 py-1 rounded text-xs font-medium" :class="getCommandClass(process.command)">
                        {{ process.command }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold" :class="getTimeClass(process.time)">
                      {{ process.time }}
                    </td>
                    <td class="px-4 py-3 text-sm">{{ process.state || '-' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600 max-w-md truncate" :title="process.query_preview">
                      {{ process.query_preview || '-' }}
                    </td>
                    <td class="px-4 py-3 text-sm">
                      <button
                        v-if="process.time > 5"
                        @click="killProcess(process.id)"
                        class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition-colors text-xs"
                      >
                        Kill
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Slow Queries Tab -->
          <div v-if="activeTab === 'slow-queries'" class="space-y-4">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold">Recent Slow Queries</h3>
              <div class="flex gap-2">
                <select
                  v-model="slowQueryLimit"
                  @change="loadSlowQueries"
                  class="border border-gray-300 rounded-lg px-3 py-2 text-sm"
                >
                  <option :value="10">10 queries</option>
                  <option :value="20">20 queries</option>
                  <option :value="50">50 queries</option>
                </select>
                <button
                  @click="loadSlowQueries"
                  class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors"
                >
                  <i class="fa-solid fa-refresh mr-2"></i>Refresh
                </button>
              </div>
            </div>
            <div v-if="loading.slowQueries" class="text-center py-8">
              <i class="fa-solid fa-spinner fa-spin text-3xl text-blue-500"></i>
              <p class="mt-2 text-gray-600">Loading slow queries...</p>
            </div>
            <div v-else-if="slowQueries.length === 0" class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-check-circle text-4xl mb-2"></i>
              <p>No slow queries found</p>
              <p class="text-sm mt-2">Slow query log might be disabled or empty</p>
            </div>
            <div v-else class="space-y-4">
              <div
                v-for="(query, index) in slowQueries"
                :key="index"
                class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
              >
                <div class="flex justify-between items-start mb-2">
                  <div class="flex gap-4">
                    <span class="text-sm font-semibold text-gray-700">
                      Time: <span class="text-red-600">{{ parseFloat(query.query_time).toFixed(3) }}s</span>
                    </span>
                    <span class="text-sm text-gray-600">
                      Rows Examined: <span class="font-semibold">{{ formatNumber(query.rows_examined) }}</span>
                    </span>
                    <span class="text-sm text-gray-600">
                      Rows Sent: <span class="font-semibold">{{ formatNumber(query.rows_sent) }}</span>
                    </span>
                  </div>
                  <span class="text-xs text-gray-500">{{ formatDate(query.created_at) }}</span>
                </div>
                <pre class="bg-gray-50 p-3 rounded text-xs overflow-x-auto">{{ query.sql_text }}</pre>
              </div>
            </div>
          </div>

          <!-- Slow Queries Summary Tab -->
          <div v-if="activeTab === 'summary'" class="space-y-4">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold">Slow Queries Summary</h3>
              <button
                @click="loadSlowQueriesSummary"
                class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors"
              >
                <i class="fa-solid fa-refresh mr-2"></i>Refresh
              </button>
            </div>
            <div v-if="loading.summary" class="text-center py-8">
              <i class="fa-solid fa-spinner fa-spin text-3xl text-blue-500"></i>
              <p class="mt-2 text-gray-600">Loading summary...</p>
            </div>
            <div v-else-if="slowQueriesSummary.length === 0" class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-check-circle text-4xl mb-2"></i>
              <p>No slow queries summary available</p>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Query</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Call Count</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Time (s)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Max Time (s)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Rows</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="(summary, index) in slowQueriesSummary" :key="index">
                    <td class="px-4 py-3 text-sm">
                      <pre class="bg-gray-50 p-2 rounded text-xs max-w-2xl overflow-x-auto">{{ summary.sql_text }}</pre>
                    </td>
                    <td class="px-4 py-3 text-sm text-center font-semibold">{{ summary.call_count }}</td>
                    <td class="px-4 py-3 text-sm text-center" :class="getTimeClass(parseFloat(summary.avg_query_time))">
                      {{ parseFloat(summary.avg_query_time).toFixed(3) }}
                    </td>
                    <td class="px-4 py-3 text-sm text-center text-red-600 font-semibold">
                      {{ parseFloat(summary.max_query_time).toFixed(3) }}
                    </td>
                    <td class="px-4 py-3 text-sm text-center">{{ formatNumber(summary.avg_rows_examined) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Table Access Stats Tab -->
          <div v-if="activeTab === 'tables'" class="space-y-4">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold">Table Access Statistics</h3>
              <button
                @click="loadTableStats"
                class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors"
              >
                <i class="fa-solid fa-refresh mr-2"></i>Refresh
              </button>
            </div>
            <div v-if="loading.tableStats" class="text-center py-8">
              <i class="fa-solid fa-spinner fa-spin text-3xl text-blue-500"></i>
              <p class="mt-2 text-gray-600">Loading table stats...</p>
            </div>
            <div v-else-if="tableStats.length === 0" class="text-center py-8 text-gray-500">
              <i class="fa-solid fa-check-circle text-4xl mb-2"></i>
              <p>No table access statistics available</p>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Table Name</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Access Count</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Time (s)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Max Time (s)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Rows Examined</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="(stat, index) in tableStats" :key="index">
                    <td class="px-4 py-3 text-sm font-semibold">{{ stat.table_name }}</td>
                    <td class="px-4 py-3 text-sm text-center">{{ formatNumber(stat.access_count) }}</td>
                    <td class="px-4 py-3 text-sm text-center">{{ parseFloat(stat.avg_query_time).toFixed(3) }}</td>
                    <td class="px-4 py-3 text-sm text-center text-red-600 font-semibold">
                      {{ parseFloat(stat.max_query_time).toFixed(3) }}
                    </td>
                    <td class="px-4 py-3 text-sm text-center">{{ formatNumber(stat.total_rows_examined) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const isMonitoring = ref(false)
const refreshInterval = ref(5)
let monitoringInterval = null

const tabs = [
  { id: 'active-users', label: 'Active Users', icon: 'fa-solid fa-users' },
  { id: 'processes', label: 'Active Processes', icon: 'fa-solid fa-list' },
  { id: 'slow-queries', label: 'Slow Queries', icon: 'fa-solid fa-clock' },
  { id: 'summary', label: 'Summary', icon: 'fa-solid fa-chart-bar' },
  { id: 'tables', label: 'Table Stats', icon: 'fa-solid fa-table' }
]
const activeTab = ref('active-users')

const loading = ref({
  activeUsers: false,
  processes: false,
  slowQueries: false,
  summary: false,
  tableStats: false
})

const mysqlStatus = ref({})
const activeUsers = ref([])
const activeUsersBreakdown = ref([])
const processes = ref([])
const slowQueries = ref([])
const slowQueriesSummary = ref([])
const tableStats = ref([])
const slowQueryLimit = ref(10)

const loadMySQLStatus = async () => {
  try {
    const response = await axios.get('/api/monitoring/mysql-status')
    if (response.data.success) {
      mysqlStatus.value = response.data.data
    }
  } catch (error) {
    console.error('Error loading MySQL status:', error)
  }
}

const loadActiveUsers = async () => {
  loading.value.activeUsers = true
  try {
    const response = await axios.get('/api/monitoring/active-users')
    if (response.data.success) {
      activeUsers.value = response.data.data
      activeUsersBreakdown.value = response.data.breakdown || []
    }
  } catch (error) {
    console.error('Error loading active users:', error)
  } finally {
    loading.value.activeUsers = false
  }
}

const loadProcesses = async () => {
  loading.value.processes = true
  try {
    const response = await axios.get('/api/monitoring/mysql-processes')
    if (response.data.success) {
      processes.value = response.data.data
    }
  } catch (error) {
    console.error('Error loading processes:', error)
  } finally {
    loading.value.processes = false
  }
}

const loadSlowQueries = async () => {
  loading.value.slowQueries = true
  try {
    const response = await axios.get('/api/monitoring/slow-queries', {
      params: { limit: slowQueryLimit.value }
    })
    if (response.data.success) {
      slowQueries.value = response.data.data
    }
  } catch (error) {
    console.error('Error loading slow queries:', error)
  } finally {
    loading.value.slowQueries = false
  }
}

const loadSlowQueriesSummary = async () => {
  loading.value.summary = true
  try {
    const response = await axios.get('/api/monitoring/slow-queries-summary')
    if (response.data.success) {
      slowQueriesSummary.value = response.data.data
    }
  } catch (error) {
    console.error('Error loading slow queries summary:', error)
  } finally {
    loading.value.summary = false
  }
}

const loadTableStats = async () => {
  loading.value.tableStats = true
  try {
    const response = await axios.get('/api/monitoring/table-stats')
    if (response.data.success) {
      tableStats.value = response.data.data
    }
  } catch (error) {
    console.error('Error loading table stats:', error)
  } finally {
    loading.value.tableStats = false
  }
}

const killProcess = async (processId) => {
  if (!confirm(`Are you sure you want to kill process ${processId}?`)) {
    return
  }

  try {
    const response = await axios.post('/api/monitoring/kill-process', {
      process_id: processId
    })
    if (response.data.success) {
      alert('Process killed successfully')
      loadProcesses()
    }
  } catch (error) {
    alert('Error killing process: ' + (error.response?.data?.error || error.message))
  }
}

const toggleMonitoring = () => {
  isMonitoring.value = !isMonitoring.value
  if (isMonitoring.value) {
    startMonitoring()
  } else {
    stopMonitoring()
  }
}

const startMonitoring = () => {
  // Load initial data
  loadMySQLStatus()
  if (activeTab.value === 'processes') loadProcesses()
  if (activeTab.value === 'slow-queries') loadSlowQueries()
  if (activeTab.value === 'summary') loadSlowQueriesSummary()
  if (activeTab.value === 'tables') loadTableStats()

  // Start interval
  monitoringInterval = setInterval(() => {
    loadMySQLStatus()
    if (activeTab.value === 'active-users') loadActiveUsers()
    if (activeTab.value === 'processes') loadProcesses()
  }, refreshInterval.value * 1000)
}

const stopMonitoring = () => {
  if (monitoringInterval) {
    clearInterval(monitoringInterval)
    monitoringInterval = null
  }
}

const updateInterval = () => {
  if (isMonitoring.value) {
    stopMonitoring()
    startMonitoring()
  }
}

const formatNumber = (num) => {
  if (!num) return '0'
  return new Intl.NumberFormat('id-ID').format(num)
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString('id-ID')
}

const getTimeClass = (time) => {
  if (time > 5) return 'text-red-600 font-semibold'
  if (time > 2) return 'text-yellow-600 font-semibold'
  return 'text-gray-600'
}

const getCommandClass = (command) => {
  const classes = {
    Query: 'bg-blue-100 text-blue-800',
    Connect: 'bg-green-100 text-green-800',
    Sleep: 'bg-gray-100 text-gray-800'
  }
  return classes[command] || 'bg-gray-100 text-gray-800'
}

const getThreadsRunningClass = () => {
  const threads = mysqlStatus.value.Threads_running || 0
  if (threads > 10) return 'border-red-500'
  if (threads > 5) return 'border-yellow-500'
  return 'border-green-500'
}

const getThreadsRunningBgClass = () => {
  const threads = mysqlStatus.value.Threads_running || 0
  if (threads > 10) return 'bg-red-100'
  if (threads > 5) return 'bg-yellow-100'
  return 'bg-green-100'
}

const getThreadsRunningIconClass = () => {
  const threads = mysqlStatus.value.Threads_running || 0
  if (threads > 10) return 'text-red-600'
  if (threads > 5) return 'text-yellow-600'
  return 'text-green-600'
}

const getApplicationClass = (app) => {
  if (app.includes('Web')) return 'bg-blue-100 text-blue-800'
  if (app.includes('Member')) return 'bg-purple-100 text-purple-800'
  if (app.includes('YMSoft App')) return 'bg-green-100 text-green-800'
  if (app.includes('POS')) return 'bg-orange-100 text-orange-800'
  return 'bg-gray-100 text-gray-800'
}

const getApplicationIcon = (app) => {
  if (app.includes('Web')) return 'fa-solid fa-globe'
  if (app.includes('Member')) return 'fa-solid fa-mobile-alt'
  if (app.includes('YMSoft App')) return 'fa-solid fa-mobile-screen-button'
  if (app.includes('POS')) return 'fa-solid fa-desktop'
  return 'fa-solid fa-question-circle'
}

const getTypeClass = (type) => {
  if (type === 'web_session') return 'bg-blue-100 text-blue-800'
  if (type === 'api_token') return 'bg-green-100 text-green-800'
  if (type === 'member_token') return 'bg-purple-100 text-purple-800'
  return 'bg-gray-100 text-gray-800'
}

const getTimeAgo = (dateString) => {
  if (!dateString) return '-'
  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  
  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`
  const diffDays = Math.floor(diffHours / 24)
  return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`
}

const getBreakdownCardClass = (app) => {
  if (app.includes('Web')) return 'bg-blue-50 border-blue-200'
  if (app.includes('Member')) return 'bg-purple-50 border-purple-200'
  if (app.includes('YMSoft App')) return 'bg-green-50 border-green-200'
  if (app.includes('POS')) return 'bg-orange-50 border-orange-200'
  return 'bg-gray-50 border-gray-200'
}

const getBreakdownTextClass = (app) => {
  if (app.includes('Web')) return 'text-blue-700'
  if (app.includes('Member')) return 'text-purple-700'
  if (app.includes('YMSoft App')) return 'text-green-700'
  if (app.includes('POS')) return 'text-orange-700'
  return 'text-gray-700'
}

const getBreakdownIconBgClass = (app) => {
  if (app.includes('Web')) return 'bg-blue-100'
  if (app.includes('Member')) return 'bg-purple-100'
  if (app.includes('YMSoft App')) return 'bg-green-100'
  if (app.includes('POS')) return 'bg-orange-100'
  return 'bg-gray-100'
}

const getBreakdownIconClass = (app) => {
  if (app.includes('Web')) return 'text-blue-600 text-xl'
  if (app.includes('Member')) return 'text-purple-600 text-xl'
  if (app.includes('YMSoft App')) return 'text-green-600 text-xl'
  if (app.includes('POS')) return 'text-orange-600 text-xl'
  return 'text-gray-600 text-xl'
}

const getBreakdownProgressClass = (app) => {
  if (app.includes('Web')) return 'bg-blue-500'
  if (app.includes('Member')) return 'bg-purple-500'
  if (app.includes('YMSoft App')) return 'bg-green-500'
  if (app.includes('POS')) return 'bg-orange-500'
  return 'bg-gray-500'
}

onMounted(() => {
  loadMySQLStatus()
  loadActiveUsers()
  loadProcesses()
})

onUnmounted(() => {
  stopMonitoring()
})
</script>
