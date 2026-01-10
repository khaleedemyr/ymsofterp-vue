<template>
  <AppLayout>
    <Head title="Marketing Dashboard - Customer Behavior Analysis" />
    
    <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50">
      <!-- Header dengan Glassmorphism -->
      <div class="sticky top-0 z-50 backdrop-blur-xl bg-white/80 border-b border-white/20 shadow-lg">
        <div class="w-full px-4 sm:px-6 lg:px-8 py-4">
          <div class="flex items-center justify-between">
            <div>
              <h1 class="text-3xl font-bold bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">
                <i class="fa-solid fa-chart-line mr-2"></i>
                Marketing Dashboard
              </h1>
              <p class="text-gray-600 mt-1">Customer Behavior Analysis & Strategy Insights</p>
            </div>
            
            <!-- Filter Section -->
            <div class="flex items-center gap-4">
              <div class="flex items-center gap-2 bg-white/60 backdrop-blur-sm rounded-xl px-4 py-2 shadow-md">
                <i class="fa-solid fa-calendar text-blue-500"></i>
                <input 
                  type="date" 
                  v-model="filters.date_from" 
                  @change="loadData"
                  class="bg-transparent border-none outline-none text-sm"
                />
                <span class="text-gray-400">to</span>
                <input 
                  type="date" 
                  v-model="filters.date_to" 
                  @change="loadData"
                  class="bg-transparent border-none outline-none text-sm"
                />
              </div>
              
              <button 
                @click="loadData"
                :disabled="loading"
                class="px-6 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 text-white rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200 disabled:opacity-50"
              >
                <i v-if="loading" class="fa fa-spinner fa-spin mr-2"></i>
                <i v-else class="fa-solid fa-sync-alt mr-2"></i>
                Refresh
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content -->
      <div class="w-full px-4 sm:px-6 lg:px-8 py-8">
        <!-- Loading Overlay -->
        <div v-if="loading && !behaviorData" class="flex items-center justify-center min-h-screen">
          <div class="text-center">
            <div class="inline-block animate-spin rounded-full h-16 w-16 border-t-4 border-b-4 border-blue-500 mb-4"></div>
            <p class="text-gray-600 text-lg">Loading dashboard data...</p>
          </div>
        </div>

        <!-- Error State -->
        <div v-if="error" class="bg-red-50 border-l-4 border-red-500 p-4 rounded-lg mb-6">
          <div class="flex">
            <i class="fa-solid fa-exclamation-circle text-red-500 mr-3"></i>
            <p class="text-red-700">{{ error }}</p>
          </div>
        </div>

        <!-- Dashboard Content -->
        <div v-if="behaviorData && !loading" class="space-y-6">
          <!-- Key Metrics Cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Member Revenue Card -->
            <div class="group relative overflow-hidden bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all duration-300">
              <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
              <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                  <div class="bg-white/20 backdrop-blur-sm rounded-lg p-3">
                    <i class="fa-solid fa-users text-2xl"></i>
                  </div>
                  <div class="text-right">
                    <div class="text-sm opacity-90">Member Revenue</div>
                    <div class="text-2xl font-bold">{{ formatCurrency(memberRevenue) }}</div>
                  </div>
                </div>
                <div class="flex items-center text-sm opacity-90">
                  <i class="fa-solid fa-user-check mr-2"></i>
                  {{ memberCount }} members
                </div>
              </div>
            </div>

            <!-- Non-Member Revenue Card -->
            <div class="group relative overflow-hidden bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all duration-300">
              <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
              <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                  <div class="bg-white/20 backdrop-blur-sm rounded-lg p-3">
                    <i class="fa-solid fa-user-slash text-2xl"></i>
                  </div>
                  <div class="text-right">
                    <div class="text-sm opacity-90">Non-Member Revenue</div>
                    <div class="text-2xl font-bold">{{ formatCurrency(nonMemberRevenue) }}</div>
                  </div>
                </div>
                <div class="flex items-center text-sm opacity-90">
                  <i class="fa-solid fa-users-slash mr-2"></i>
                  {{ nonMemberCount }} customers
                </div>
              </div>
            </div>

            <!-- AOV Comparison Card -->
            <div class="group relative overflow-hidden bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all duration-300">
              <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
              <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                  <div class="bg-white/20 backdrop-blur-sm rounded-lg p-3">
                    <i class="fa-solid fa-shopping-cart text-2xl"></i>
                  </div>
                  <div class="text-right">
                    <div class="text-sm opacity-90">Avg Order Value</div>
                    <div class="text-2xl font-bold">{{ formatCurrency(avgAOV) }}</div>
                  </div>
                </div>
                <div class="flex items-center text-sm opacity-90">
                  <i :class="(aovDifference > 0 ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-down') + ' mr-2'"></i>
                  Member {{ aovDifference > 0 ? '+' : '' }}{{ formatCurrency(Math.abs(aovDifference)) }} vs Non-Member
                </div>
              </div>
            </div>

            <!-- Average Per Pax Card -->
            <div class="group relative overflow-hidden bg-gradient-to-br from-orange-500 to-red-500 rounded-2xl p-6 text-white shadow-xl hover:shadow-2xl transform hover:scale-105 transition-all duration-300">
              <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-16 -mt-16"></div>
              <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                  <div class="bg-white/20 backdrop-blur-sm rounded-lg p-3">
                    <i class="fa-solid fa-users-line text-2xl"></i>
                  </div>
                  <div class="text-right">
                    <div class="text-sm opacity-90">Average Per Pax</div>
                    <div class="text-2xl font-bold">{{ formatCurrency(avgPerPax) }}</div>
                  </div>
                </div>
                <div class="flex items-center text-sm opacity-90">
                  <i :class="(avgPerPaxDifference > 0 ? 'fa-solid fa-arrow-up': 'fa-solid fa-arrow-down') + ' mr-2'"></i>
                  Member {{ avgPerPaxDifference > 0 ? '+' : '' }}{{ formatCurrency(Math.abs(avgPerPaxDifference)) }} vs Non-Member
                </div>
              </div>
            </div>
          </div>

          <!-- Charts Row -->
          <div class="grid grid-cols-1 gap-6">
            <!-- Revenue Comparison Chart -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-gray-800">
                  <i class="fa-solid fa-chart-pie text-blue-500 mr-2"></i>
                  Revenue Distribution
                </h3>
                <div class="flex gap-2">
                  <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">Member</span>
                  <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-semibold">Non-Member</span>
                </div>
              </div>
              <apexchart
                v-if="revenueChartOptions"
                type="donut"
                height="300"
                :options="revenueChartOptions"
                :series="revenueChartSeries"
              ></apexchart>
            </div>

            <!-- RFM Segmentation Chart -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                  <h3 class="text-xl font-bold text-gray-800 mb-2">
                    <i class="fa-solid fa-users-gear text-indigo-500 mr-2"></i>
                    RFM Segmentation
                  </h3>
                  <!-- RFM Brief Explanation -->
                  <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                    <p class="text-sm text-gray-700 mb-2">
                      <strong class="text-blue-700">RFM Analysis</strong> adalah metode segmentasi customer berdasarkan:
                    </p>
                    <ul class="text-xs text-gray-600 space-y-1 ml-4 list-disc">
                      <li><strong>R (Recency):</strong> Kapan terakhir kali customer berbelanja</li>
                      <li><strong>F (Frequency):</strong> Seberapa sering customer berbelanja</li>
                      <li><strong>M (Monetary):</strong> Berapa total nilai pembelian customer</li>
                    </ul>
                    <p class="text-xs text-gray-600 mt-2">
                      <strong>Champions:</strong> Customer terbaik (belanja baru-baru ini, sering, dan besar)<br>
                      <strong>Loyal:</strong> Customer setia yang sering belanja<br>
                      <strong>At Risk:</strong> Customer yang dulu sering belanja tapi sudah lama tidak belanja<br>
                      <strong>Lost:</strong> Customer yang sudah lama tidak belanja dan jarang belanja
                    </p>
                    <p class="text-xs text-blue-600 mt-2 italic">
                      <i class="fa-solid fa-info-circle mr-1"></i>Klik bar untuk melihat detail dan penjelasan lengkap tentang RFM Score
                    </p>
                  </div>
                </div>
              </div>
              <apexchart
                v-if="rfmChartOptions"
                type="bar"
                height="300"
                :options="rfmChartOptions"
                :series="rfmChartSeries"
                @dataPointSelection="handleRFMBarClick"
              ></apexchart>
            </div>
          </div>

          <!-- Time Pattern & Product Preference -->
          <div class="grid grid-cols-1 gap-6">
            <!-- Peak Hours Chart -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <h3 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fa-solid fa-clock text-green-500 mr-2"></i>
                Peak Hours Analysis
              </h3>
              <apexchart
                v-if="peakHoursChartOptions"
                type="area"
                height="300"
                :options="peakHoursChartOptions"
                :series="peakHoursChartSeries"
              ></apexchart>
            </div>

            <!-- Product Preferences -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <h3 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fa-solid fa-star text-yellow-500 mr-2"></i>
                Top 10 Products (Member vs Non-Member)
              </h3>
              <apexchart
                v-if="productChartOptions"
                type="bar"
                height="300"
                :options="productChartOptions"
                :series="productChartSeries"
              ></apexchart>
            </div>
          </div>

          <!-- Strategy Suggestions Section -->
          <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-8 shadow-2xl text-white">
            <div class="flex items-center justify-between mb-6">
              <div>
                <h2 class="text-3xl font-bold mb-2">
                  <i class="fa-solid fa-lightbulb mr-3"></i>
                  AI-Powered Marketing Strategies
                </h2>
                <p class="text-indigo-100">Actionable insights to boost your revenue</p>
              </div>
              <button 
                @click="loadStrategies"
                :disabled="loadingStrategies"
                class="px-6 py-3 bg-white/20 backdrop-blur-sm rounded-xl hover:bg-white/30 transition-all duration-200"
              >
                <i v-if="loadingStrategies" class="fa fa-spinner fa-spin mr-2"></i>
                <i v-else class="fa-solid fa-magic mr-2"></i>
                Generate Strategies
              </button>
            </div>

            <!-- Strategies Grid -->
            <div v-if="strategies && strategies.strategies" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              <div 
                v-for="(strategy, index) in strategies.strategies" 
                :key="index"
                class="bg-white/10 backdrop-blur-md rounded-xl p-6 border border-white/20 hover:bg-white/20 transition-all duration-300 transform hover:scale-105"
              >
                <div class="flex items-start justify-between mb-4">
                  <div class="flex-1">
                    <div class="flex items-center gap-2 mb-2">
                      <span 
                        :class="{
                          'bg-red-500/30': strategy.priority === 'high',
                          'bg-yellow-500/30': strategy.priority === 'medium',
                          'bg-green-500/30': strategy.priority === 'low'
                        }"
                        class="px-3 py-1 rounded-full text-xs font-semibold"
                      >
                        {{ strategy.priority.toUpperCase() }}
                      </span>
                      <span class="px-3 py-1 bg-white/20 rounded-full text-xs">
                        {{ strategy.target_segment }}
                      </span>
                    </div>
                    <h4 class="text-lg font-bold mb-2">{{ strategy.title }}</h4>
                    <p class="text-sm text-indigo-100 mb-4">{{ strategy.description }}</p>
                  </div>
                </div>
                
                <div class="space-y-2">
                  <div class="text-xs font-semibold text-indigo-200 mb-2">Action Items:</div>
                  <ul class="space-y-1">
                    <li 
                      v-for="(action, i) in strategy.action_items.slice(0, 3)" 
                      :key="i"
                      class="text-xs flex items-start gap-2"
                    >
                      <i class="fa-solid fa-check-circle mt-1 text-green-300"></i>
                      <span>{{ action }}</span>
                    </li>
                  </ul>
                </div>

                <div class="mt-4 pt-4 border-t border-white/20">
                  <div class="flex items-center justify-between text-xs">
                    <span class="text-indigo-200">
                      <i class="fa-solid fa-clock mr-1"></i>
                      {{ strategy.timeline }}
                    </span>
                    <span class="font-semibold text-green-300">
                      {{ strategy.expected_impact }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-8">
              <i class="fa-solid fa-robot text-4xl mb-4 opacity-50"></i>
              <p class="text-indigo-100">Click "Generate Strategies" to get AI-powered marketing recommendations</p>
            </div>
          </div>

          <!-- Priority Actions & Promo Analytics -->
          <div class="space-y-6">
            <!-- Priority Actions -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <h3 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fa-solid fa-bolt text-yellow-500 mr-2"></i>
                Priority Actions
              </h3>
              <div v-if="strategies && strategies.priority_actions" class="space-y-3">
                <div 
                  v-for="(action, index) in strategies.priority_actions" 
                  :key="index"
                  class="p-4 rounded-xl border-l-4"
                  :class="{
                    'bg-red-50 border-red-500': action.priority === 'high',
                    'bg-yellow-50 border-yellow-500': action.priority === 'medium',
                    'bg-green-50 border-green-500': action.priority === 'low'
                  }"
                >
                  <div class="flex items-start gap-3">
                    <i 
                      :class="{
                        'fa-exclamation-circle text-red-500': action.priority === 'high',
                        'fa-info-circle text-yellow-500': action.priority === 'medium',
                        'fa-check-circle text-green-500': action.priority === 'low'
                      }"
                      class="fa-solid mt-1"
                    ></i>
                    <div class="flex-1">
                      <div class="font-semibold text-gray-800 mb-1">{{ action.action }}</div>
                      <div class="text-sm text-gray-600">{{ action.reason }}</div>
                    </div>
                  </div>
                </div>
              </div>
              <div v-else class="text-center py-8 text-gray-400">
                <i class="fa-solid fa-info-circle text-2xl mb-2"></i>
                <p>Generate strategies to see priority actions</p>
              </div>
            </div>

            <!-- Promo Analytics -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <h3 class="text-xl font-bold text-gray-800 mb-4">
                <i class="fa-solid fa-tags text-purple-500 mr-2"></i>
                Promo Analytics
              </h3>
              
              <div v-if="promoAnalytics && promoAnalytics.overall" class="space-y-4">
                <!-- Overall Stats -->
                <div class="grid grid-cols-3 gap-4 mb-4">
                  <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Promo Usage</div>
                    <div class="text-2xl font-bold text-purple-600">{{ promoAnalytics.overall.promo_usage_percentage }}%</div>
                    <div class="text-xs text-gray-500 mt-1">
                      {{ promoAnalytics.overall.orders_with_promo }} / {{ promoAnalytics.overall.total_orders }} orders
                    </div>
                  </div>
                  <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Promo Discount</div>
                    <div class="text-2xl font-bold text-green-600">{{ formatCurrency(promoAnalytics.overall.total_promo_discount) }}</div>
                    <div class="text-xs text-gray-500 mt-1">From active promos</div>
                  </div>
                  <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Manual Discount</div>
                    <div class="text-2xl font-bold text-orange-600">{{ formatCurrency(promoAnalytics.overall.total_manual_discount) }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                      {{ promoAnalytics.overall.orders_with_manual_discount }} orders
                    </div>
                  </div>
                </div>
                
                <!-- Total Discount Summary -->
                <div v-if="promoAnalytics.overall.total_discount > 0" class="bg-indigo-50 rounded-xl p-3 mb-4 border border-indigo-200">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-semibold text-indigo-800">Total Discount (Promo + Manual)</span>
                    <span class="text-lg font-bold text-indigo-600">{{ formatCurrency(promoAnalytics.overall.total_discount) }}</span>
                  </div>
                </div>

                <!-- Revenue Comparison -->
                <div class="bg-gray-50 rounded-xl p-4 mb-4">
                  <div class="text-sm font-semibold text-gray-700 mb-3">Revenue Comparison</div>
                  <div class="space-y-2">
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">With Promo</span>
                      <span class="font-semibold text-purple-600">{{ formatCurrency(promoAnalytics.overall.revenue_with_promo) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">Without Promo</span>
                      <span class="font-semibold text-gray-600">{{ formatCurrency(promoAnalytics.overall.revenue_without_promo) }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-gray-200">
                      <span class="text-sm font-semibold text-gray-700">Avg Order Value</span>
                      <div class="text-right">
                        <div class="text-xs text-purple-600">With: {{ formatCurrency(promoAnalytics.overall.avg_order_value_with_promo) }}</div>
                        <div class="text-xs text-gray-600">Without: {{ formatCurrency(promoAnalytics.overall.avg_order_value_without_promo) }}</div>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Member vs Non-Member -->
                <div v-if="promoAnalytics.member_vs_non_member && promoAnalytics.member_vs_non_member.length > 0" class="bg-blue-50 rounded-xl p-4">
                  <div class="text-sm font-semibold text-gray-700 mb-3">Member vs Non-Member</div>
                  <div class="space-y-2">
                    <div 
                      v-for="item in promoAnalytics.member_vs_non_member" 
                      :key="item.customer_type"
                      class="flex items-center justify-between"
                    >
                      <span class="text-sm text-gray-600 capitalize">{{ item.customer_type.replace('_', ' ') }}</span>
                      <div class="text-right">
                        <span class="font-semibold text-blue-600">{{ item.promo_usage_percentage }}%</span>
                        <span class="text-xs text-gray-500 ml-2">({{ item.orders_with_promo }}/{{ item.total_orders }})</span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Top Promos & Manual Discounts -->
                <div class="mt-4 space-y-4">
                  <!-- Top Promos -->
                  <div v-if="promoAnalytics.top_promos && promoAnalytics.top_promos.length > 0">
                    <div class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                      <i class="fa-solid fa-tag text-purple-500"></i>
                      Top Promos
                    </div>
                    <div class="space-y-2">
                      <div 
                        v-for="(promo, index) in promoAnalytics.top_promos" 
                        :key="promo.id"
                        @click="openPromoModal(promo)"
                        class="flex items-center justify-between p-3 bg-gradient-to-r from-purple-50 to-purple-100 rounded-lg hover:from-purple-100 hover:to-purple-200 transition-all border border-purple-200 cursor-pointer"
                      >
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                          <div class="flex-shrink-0 w-8 h-8 bg-purple-500 rounded-lg flex items-center justify-center text-white font-bold text-xs">
                            #{{ index + 1 }}
                          </div>
                          <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-gray-800 truncate">{{ promo.name }}</div>
                            <div class="text-xs text-gray-600 mt-0.5">{{ promo.code }} • {{ promo.type }}</div>
                          </div>
                        </div>
                        <div class="text-right ml-3 flex-shrink-0">
                          <div class="text-xs text-gray-500 mb-1">Usage</div>
                          <div class="text-sm font-bold text-purple-600">{{ promo.usage_count }}x</div>
                          <div class="text-xs text-gray-500 mt-2 mb-1">Discount</div>
                          <div class="text-sm font-bold text-green-600">{{ formatCurrency(promo.total_discount) }}</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Top Manual Discounts -->
                  <div v-if="promoAnalytics.top_manual_discounts && promoAnalytics.top_manual_discounts.length > 0">
                    <div class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2">
                      <i class="fa-solid fa-hand-holding-dollar text-orange-500"></i>
                      Top Manual Discounts
                    </div>
                    <div class="space-y-2">
                      <div 
                        v-for="(discount, index) in promoAnalytics.top_manual_discounts" 
                        :key="discount.reason"
                        @click="openManualDiscountModal(discount)"
                        class="flex items-center justify-between p-3 bg-gradient-to-r from-orange-50 to-orange-100 rounded-lg hover:from-orange-100 hover:to-orange-200 transition-all border border-orange-200 cursor-pointer"
                      >
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                          <div class="flex-shrink-0 w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center text-white font-bold text-xs">
                            #{{ index + 1 }}
                          </div>
                          <div class="flex-1 min-w-0">
                            <div class="text-sm font-bold text-gray-800">{{ discount.reason }}</div>
                            <div class="text-xs text-gray-600 mt-0.5">{{ discount.usage_count }} orders</div>
                          </div>
                        </div>
                        <div class="text-right ml-3 flex-shrink-0">
                          <div class="text-xs text-gray-500 mb-1">Total Discount</div>
                          <div class="text-sm font-bold text-orange-600">{{ formatCurrency(discount.total_discount) }}</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-tags text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">No promo data available</p>
              </div>
            </div>
          </div>

          <!-- Customer Value Analysis Section -->
          <div class="space-y-6">
            <!-- Customer Lifetime Value (CLV) -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <div class="flex items-start justify-between mb-4">
                <div>
                  <h3 class="text-xl font-bold text-gray-800 mb-2">
                    <i class="fa-solid fa-chart-line text-indigo-500 mr-2"></i>
                    Customer Lifetime Value (CLV)
                  </h3>
                  <p class="text-sm text-gray-600">
                    CLV mengukur total revenue yang dihasilkan oleh seorang customer selama seluruh hubungannya dengan bisnis. 
                    Metrik ini membantu mengidentifikasi customer yang paling berharga dan mengalokasikan budget marketing dengan lebih efektif.
                  </p>
                </div>
              </div>
              
              <div v-if="clvData && clvData.member" class="space-y-4">
                <!-- Member CLV Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Avg CLV (Member)</div>
                    <div class="text-2xl font-bold text-indigo-600">{{ formatCurrency(clvData.member.statistics.avg_clv) }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ clvData.member.statistics.total_customers }} customers</div>
                    <div class="text-xs text-gray-400 mt-1 italic">Rata-rata nilai customer seumur hidup</div>
                  </div>
                  <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Median CLV</div>
                    <div class="text-2xl font-bold text-purple-600">{{ formatCurrency(clvData.member.statistics.median_clv) }}</div>
                    <div class="text-xs text-gray-500 mt-1">Middle value</div>
                    <div class="text-xs text-gray-400 mt-1 italic">Nilai tengah (tidak terpengaruh outlier)</div>
                  </div>
                  <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Total CLV</div>
                    <div class="text-2xl font-bold text-green-600">{{ formatCurrency(clvData.member.statistics.total_clv) }}</div>
                    <div class="text-xs text-gray-500 mt-1">All members</div>
                    <div class="text-xs text-gray-400 mt-1 italic">Total nilai semua customer</div>
                  </div>
                  <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Avg Orders</div>
                    <div class="text-2xl font-bold text-blue-600">{{ clvData.member.statistics.avg_orders_per_customer }}</div>
                    <div class="text-xs text-gray-500 mt-1">Per customer</div>
                    <div class="text-xs text-gray-400 mt-1 italic">Rata-rata jumlah pesanan</div>
                  </div>
                </div>

                <!-- CLV Range -->
                <div class="bg-gray-50 rounded-xl p-4">
                  <div class="text-sm font-semibold text-gray-700 mb-3">CLV Range</div>
                  <div class="flex items-center justify-between">
                    <div class="text-center">
                      <div class="text-xs text-gray-500 mb-1">Minimum</div>
                      <div class="text-sm font-bold text-gray-700">{{ formatCurrency(clvData.member.statistics.min_clv) }}</div>
                    </div>
                    <div class="flex-1 mx-4">
                      <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div 
                          class="h-full bg-gradient-to-r from-indigo-400 to-purple-500 rounded-full"
                          :style="{ width: '100%' }"
                        ></div>
                      </div>
                    </div>
                    <div class="text-center">
                      <div class="text-xs text-gray-500 mb-1">Maximum</div>
                      <div class="text-sm font-bold text-gray-700">{{ formatCurrency(clvData.member.statistics.max_clv) }}</div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else-if="loadingValueAnalysis" class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-spinner fa-spin text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">Loading CLV data...</p>
              </div>
              <div v-else class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-chart-line text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">No CLV data available for selected period</p>
              </div>
            </div>

            <!-- Repeat Purchase Rate -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <div class="flex items-start justify-between mb-4">
                <div>
                  <h3 class="text-xl font-bold text-gray-800 mb-2">
                    <i class="fa-solid fa-repeat text-green-500 mr-2"></i>
                    Repeat Purchase Rate
                  </h3>
                  <p class="text-sm text-gray-600">
                    Repeat Purchase Rate menunjukkan persentase customer yang melakukan pembelian lebih dari sekali. 
                    Semakin tinggi rate ini, semakin loyal customer base Anda. Fokus pada meningkatkan rate ini dapat meningkatkan revenue jangka panjang.
                  </p>
                </div>
              </div>
              
              <div v-if="repeatRateData && repeatRateData.member" class="space-y-4">
                <!-- Main Stats -->
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                  <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Repeat Rate</div>
                    <div class="text-3xl font-bold text-green-600">{{ repeatRateData.member.repeat_rate }}%</div>
                    <div class="text-xs text-gray-500 mt-1">
                      {{ repeatRateData.member.repeat_customers }} / {{ repeatRateData.member.total_customers }} customers
                    </div>
                    <div class="text-xs text-gray-400 mt-1 italic">% customer yang kembali membeli</div>
                  </div>
                  <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">One-Time Rate</div>
                    <div class="text-3xl font-bold text-red-600">{{ repeatRateData.member.one_time_rate }}%</div>
                    <div class="text-xs text-gray-500 mt-1">Single purchase only</div>
                    <div class="text-xs text-gray-400 mt-1 italic">% customer yang hanya beli sekali</div>
                  </div>
                  <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Avg Orders</div>
                    <div class="text-3xl font-bold text-blue-600">{{ repeatRateData.member.avg_orders_per_customer }}</div>
                    <div class="text-xs text-gray-500 mt-1">Per customer</div>
                    <div class="text-xs text-gray-400 mt-1 italic">Rata-rata pesanan per customer</div>
                  </div>
                </div>

                <!-- Segments -->
                <div v-if="repeatRateData.member.segments" class="bg-gray-50 rounded-xl p-4">
                  <div class="text-sm font-semibold text-gray-700 mb-1">Customer Segments by Order Frequency</div>
                  <div class="text-xs text-gray-500 mb-3">Segmentasi customer berdasarkan frekuensi pembelian untuk strategi marketing yang lebih targeted</div>
                  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div class="bg-white rounded-lg p-3 border border-gray-200">
                      <div class="text-xs text-gray-500 mb-1">1 Order</div>
                      <div class="text-lg font-bold text-gray-700">{{ repeatRateData.member.segments.one_time || 0 }}</div>
                    </div>
                    <div class="bg-white rounded-lg p-3 border border-gray-200">
                      <div class="text-xs text-gray-500 mb-1">2-5 Orders</div>
                      <div class="text-lg font-bold text-blue-600">{{ repeatRateData.member.segments.repeat_2_5 || 0 }}</div>
                    </div>
                    <div class="bg-white rounded-lg p-3 border border-gray-200">
                      <div class="text-xs text-gray-500 mb-1">6-10 Orders</div>
                      <div class="text-lg font-bold text-green-600">{{ repeatRateData.member.segments.repeat_6_10 || 0 }}</div>
                    </div>
                    <div class="bg-white rounded-lg p-3 border border-gray-200">
                      <div class="text-xs text-gray-500 mb-1">11+ Orders</div>
                      <div class="text-lg font-bold text-purple-600">{{ repeatRateData.member.segments.repeat_11_plus || 0 }}</div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else-if="loadingValueAnalysis" class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-spinner fa-spin text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">Loading repeat purchase data...</p>
              </div>
              <div v-else class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-repeat text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">No repeat purchase data available for selected period</p>
              </div>
            </div>

            <!-- Average Days Between Orders -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <div class="flex items-start justify-between mb-4">
                <div>
                  <h3 class="text-xl font-bold text-gray-800 mb-2">
                    <i class="fa-solid fa-calendar-days text-orange-500 mr-2"></i>
                    Average Days Between Orders
                  </h3>
                  <p class="text-sm text-gray-600">
                    Rata-rata hari antar pesanan menunjukkan seberapa sering customer kembali membeli. 
                    Interval yang semakin panjang bisa menjadi early warning untuk customer yang berisiko churn. 
                    Gunakan data ini untuk timing campaign re-engagement yang tepat.
                  </p>
                </div>
              </div>
              
              <div v-if="avgDaysData" class="space-y-4">
                <!-- Main Stats -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Average Days</div>
                    <div class="text-2xl font-bold text-orange-600">{{ avgDaysData.avg_days_between_orders }}</div>
                    <div class="text-xs text-gray-500 mt-1">days</div>
                    <div class="text-xs text-gray-400 mt-1 italic">Rata-rata hari antar pesanan</div>
                  </div>
                  <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Median Days</div>
                    <div class="text-2xl font-bold text-blue-600">{{ avgDaysData.median_days_between_orders }}</div>
                    <div class="text-xs text-gray-500 mt-1">days</div>
                    <div class="text-xs text-gray-400 mt-1 italic">Nilai tengah (lebih akurat)</div>
                  </div>
                  <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Min Days</div>
                    <div class="text-2xl font-bold text-green-600">{{ avgDaysData.min_days }}</div>
                    <div class="text-xs text-gray-500 mt-1">days</div>
                    <div class="text-xs text-gray-400 mt-1 italic">Interval terpendek</div>
                  </div>
                  <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Max Days</div>
                    <div class="text-2xl font-bold text-red-600">{{ avgDaysData.max_days }}</div>
                    <div class="text-xs text-gray-500 mt-1">days</div>
                    <div class="text-xs text-gray-400 mt-1 italic">Interval terpanjang</div>
                  </div>
                </div>

                <!-- Segments by Interval -->
                <div v-if="avgDaysData.segments" class="bg-gray-50 rounded-xl p-4">
                  <div class="text-sm font-semibold text-gray-700 mb-1">Distribution by Interval</div>
                  <div class="text-xs text-gray-500 mb-3">Distribusi interval antar pesanan membantu mengidentifikasi pola pembelian dan timing optimal untuk re-engagement campaign</div>
                  <div class="space-y-2">
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">0-7 days</span>
                      <div class="flex items-center gap-2 flex-1 mx-4">
                        <div class="flex-1 h-3 bg-gray-200 rounded-full overflow-hidden">
                          <div 
                            class="h-full bg-green-500 rounded-full"
                            :style="{ width: avgDaysData.total_intervals > 0 ? (avgDaysData.segments['0_7_days'] / avgDaysData.total_intervals * 100) + '%' : '0%' }"
                          ></div>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 w-12 text-right">{{ avgDaysData.segments['0_7_days'] || 0 }}</span>
                      </div>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">8-14 days</span>
                      <div class="flex items-center gap-2 flex-1 mx-4">
                        <div class="flex-1 h-3 bg-gray-200 rounded-full overflow-hidden">
                          <div 
                            class="h-full bg-blue-500 rounded-full"
                            :style="{ width: avgDaysData.total_intervals > 0 ? (avgDaysData.segments['8_14_days'] / avgDaysData.total_intervals * 100) + '%' : '0%' }"
                          ></div>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 w-12 text-right">{{ avgDaysData.segments['8_14_days'] || 0 }}</span>
                      </div>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">15-30 days</span>
                      <div class="flex items-center gap-2 flex-1 mx-4">
                        <div class="flex-1 h-3 bg-gray-200 rounded-full overflow-hidden">
                          <div 
                            class="h-full bg-yellow-500 rounded-full"
                            :style="{ width: avgDaysData.total_intervals > 0 ? (avgDaysData.segments['15_30_days'] / avgDaysData.total_intervals * 100) + '%' : '0%' }"
                          ></div>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 w-12 text-right">{{ avgDaysData.segments['15_30_days'] || 0 }}</span>
                      </div>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">31-60 days</span>
                      <div class="flex items-center gap-2 flex-1 mx-4">
                        <div class="flex-1 h-3 bg-gray-200 rounded-full overflow-hidden">
                          <div 
                            class="h-full bg-orange-500 rounded-full"
                            :style="{ width: avgDaysData.total_intervals > 0 ? (avgDaysData.segments['31_60_days'] / avgDaysData.total_intervals * 100) + '%' : '0%' }"
                          ></div>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 w-12 text-right">{{ avgDaysData.segments['31_60_days'] || 0 }}</span>
                      </div>
                    </div>
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">61+ days</span>
                      <div class="flex items-center gap-2 flex-1 mx-4">
                        <div class="flex-1 h-3 bg-gray-200 rounded-full overflow-hidden">
                          <div 
                            class="h-full bg-red-500 rounded-full"
                            :style="{ width: avgDaysData.total_intervals > 0 ? (avgDaysData.segments['61_plus_days'] / avgDaysData.total_intervals * 100) + '%' : '0%' }"
                          ></div>
                        </div>
                        <span class="text-sm font-semibold text-gray-700 w-12 text-right">{{ avgDaysData.segments['61_plus_days'] || 0 }}</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else-if="loadingValueAnalysis" class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-spinner fa-spin text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">Loading average days data...</p>
              </div>
              <div v-else class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-calendar-days text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">No average days data available for selected period</p>
              </div>
            </div>
          </div>

          <!-- Advanced Analytics Section -->
          <div class="space-y-6">
            <!-- Basket Analysis / Product Affinity -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <div class="flex items-start justify-between mb-4">
                <div>
                  <h3 class="text-xl font-bold text-gray-800 mb-2">
                    <i class="fa-solid fa-shopping-basket text-pink-500 mr-2"></i>
                    Basket Analysis / Product Affinity
                  </h3>
                  <p class="text-sm text-gray-600">
                    Analisis produk yang sering dibeli bersamaan untuk rekomendasi cross-sell dan upsell. 
                    Gunakan data ini untuk membuat bundle offers dan meningkatkan average order value.
                  </p>
                </div>
              </div>
              
              <div v-if="basketData && basketData.top_pairs && basketData.top_pairs.length > 0" class="space-y-4">
                <!-- Summary -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                  <div class="bg-gradient-to-br from-pink-50 to-pink-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Orders Analyzed</div>
                    <div class="text-2xl font-bold text-pink-600">{{ basketData.total_orders_analyzed }}</div>
                    <div class="text-xs text-gray-500 mt-1">Total orders</div>
                  </div>
                  <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Multi-Item Orders</div>
                    <div class="text-2xl font-bold text-purple-600">{{ basketData.total_orders_with_multiple_items }}</div>
                    <div class="text-xs text-gray-500 mt-1">Orders with 2+ items</div>
                  </div>
                </div>

                <!-- Top Product Pairs -->
                <div class="bg-gray-50 rounded-xl p-4">
                  <div class="text-sm font-semibold text-gray-700 mb-3">Top Product Pairs (Frequently Bought Together)</div>
                  <div class="space-y-2">
                    <div 
                      v-for="(pair, index) in basketData.top_pairs.slice(0, 10)" 
                      :key="index"
                      class="flex items-center justify-between p-3 bg-white rounded-lg border border-gray-200 hover:border-pink-300 transition-all"
                    >
                      <div class="flex items-center gap-3 flex-1">
                        <div class="flex-shrink-0 w-8 h-8 bg-pink-500 rounded-lg flex items-center justify-center text-white font-bold text-xs">
                          #{{ index + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                          <div class="text-sm font-bold text-gray-800">{{ pair.product1 }}</div>
                          <div class="text-xs text-gray-500 flex items-center gap-1">
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                            {{ pair.product2 }}
                          </div>
                        </div>
                      </div>
                      <div class="text-right ml-3 flex-shrink-0">
                        <div class="text-xs text-gray-500 mb-1">Frequency</div>
                        <div class="text-sm font-bold text-pink-600">{{ pair.frequency }}x</div>
                        <div class="text-xs text-gray-500 mt-1">Revenue</div>
                        <div class="text-sm font-bold text-green-600">{{ formatCurrency(pair.total_revenue) }}</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else-if="loadingAdvancedAnalytics" class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-spinner fa-spin text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">Loading basket analysis...</p>
              </div>
              <div v-else class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-shopping-basket text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">No basket analysis data available</p>
              </div>
            </div>

            <!-- Peak Hours & Day Analysis -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <div class="flex items-start justify-between mb-4">
                <div>
                  <h3 class="text-xl font-bold text-gray-800 mb-2">
                    <i class="fa-solid fa-clock text-cyan-500 mr-2"></i>
                    Peak Hours & Day Analysis
                  </h3>
                  <p class="text-sm text-gray-600">
                    Identifikasi jam-jam sibuk dan hari paling produktif untuk optimasi timing promosi dan staffing. 
                    Gunakan data ini untuk menjadwalkan campaign marketing pada waktu yang tepat.
                  </p>
                </div>
              </div>
              
              <div v-if="peakHoursData" class="space-y-4">
                <!-- Top Peak Hours -->
                <div v-if="peakHoursData.top_peak_hours && peakHoursData.top_peak_hours.length > 0">
                  <div class="text-sm font-semibold text-gray-700 mb-3">Top 5 Peak Hours (by Revenue)</div>
                  <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                    <div 
                      v-for="(hour, index) in peakHoursData.top_peak_hours" 
                      :key="index"
                      class="bg-gradient-to-br from-cyan-50 to-cyan-100 rounded-xl p-4 text-center"
                    >
                      <div class="text-xs text-gray-600 mb-1">{{ hour.hour_label }}</div>
                      <div class="text-xl font-bold text-cyan-600">{{ formatCurrency(hour.total_revenue) }}</div>
                      <div class="text-xs text-gray-500 mt-1">{{ hour.total_orders }} orders</div>
                    </div>
                  </div>
                </div>

                <!-- Day of Week Stats -->
                <div v-if="peakHoursData.day_of_week_stats && peakHoursData.day_of_week_stats.length > 0" class="bg-gray-50 rounded-xl p-4">
                  <div class="text-sm font-semibold text-gray-700 mb-3">Day of Week Performance</div>
                  <div class="space-y-2">
                    <div 
                      v-for="day in peakHoursData.day_of_week_stats" 
                      :key="day.day_of_week"
                      class="flex items-center justify-between p-3 bg-white rounded-lg border border-gray-200"
                    >
                      <div class="flex items-center gap-3">
                        <div class="w-24 text-sm font-semibold text-gray-700">{{ day.day_name }}</div>
                        <div class="flex-1">
                          <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div 
                              class="h-full bg-gradient-to-r from-cyan-400 to-blue-500 rounded-full"
                              :style="{ width: getDayPercentage(day.total_revenue) + '%' }"
                            ></div>
                          </div>
                        </div>
                      </div>
                      <div class="text-right ml-4 flex-shrink-0">
                        <div class="text-sm font-bold text-cyan-600">{{ formatCurrency(day.total_revenue) }}</div>
                        <div class="text-xs text-gray-500">{{ day.total_orders }} orders</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else-if="loadingAdvancedAnalytics" class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-spinner fa-spin text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">Loading peak hours analysis...</p>
              </div>
              <div v-else class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-clock text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">No peak hours data available</p>
              </div>
            </div>

            <!-- Customer Acquisition Trends -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <div class="flex items-start justify-between mb-4">
                <div>
                  <h3 class="text-xl font-bold text-gray-800 mb-2">
                    <i class="fa-solid fa-user-plus text-teal-500 mr-2"></i>
                    Customer Acquisition Trends
                  </h3>
                  <p class="text-sm text-gray-600">
                    Tracking pertumbuhan customer base dengan melihat jumlah customer baru per periode. 
                    Monitor growth rate untuk mengukur efektivitas strategi acquisition marketing.
                  </p>
                </div>
              </div>
              
              <div v-if="acquisitionData && acquisitionData.trends && acquisitionData.trends.length > 0" class="space-y-4">
                <!-- Summary -->
                <div class="grid grid-cols-3 gap-4 mb-4">
                  <div class="bg-gradient-to-br from-teal-50 to-teal-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Total New Customers</div>
                    <div class="text-2xl font-bold text-teal-600">{{ acquisitionData.summary.total_new_customers }}</div>
                    <div class="text-xs text-gray-500 mt-1">In selected period</div>
                  </div>
                  <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Avg per Period</div>
                    <div class="text-2xl font-bold text-blue-600">{{ acquisitionData.summary.avg_new_customers_per_period }}</div>
                    <div class="text-xs text-gray-500 mt-1">Customers</div>
                  </div>
                  <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4">
                    <div class="text-sm text-gray-600 mb-1">Total Periods</div>
                    <div class="text-2xl font-bold text-green-600">{{ acquisitionData.summary.total_periods }}</div>
                    <div class="text-xs text-gray-500 mt-1">Periods analyzed</div>
                  </div>
                </div>

                <!-- Trends Chart/Table -->
                <div class="bg-gray-50 rounded-xl p-4">
                  <div class="text-sm font-semibold text-gray-700 mb-3">Acquisition Trends (Last 10 Periods)</div>
                  <div class="space-y-2 max-h-64 overflow-y-auto">
                    <div 
                      v-for="(trend, index) in acquisitionData.trends.slice(-10)" 
                      :key="index"
                      class="flex items-center justify-between p-3 bg-white rounded-lg border border-gray-200"
                    >
                      <div class="flex items-center gap-3">
                        <div class="w-24 text-sm font-medium text-gray-700">{{ trend.period_label }}</div>
                        <div class="flex items-center gap-2">
                          <i 
                            :class="{
                              'fa-arrow-up text-green-500': trend.trend === 'up',
                              'fa-arrow-down text-red-500': trend.trend === 'down',
                              'fa-minus text-gray-400': trend.trend === 'stable'
                            }"
                            class="fa-solid text-xs"
                          ></i>
                          <span 
                            :class="{
                              'text-green-600': trend.trend === 'up',
                              'text-red-600': trend.trend === 'down',
                              'text-gray-500': trend.trend === 'stable'
                            }"
                            class="text-xs font-semibold"
                          >
                            {{ trend.growth_rate > 0 ? '+' : '' }}{{ trend.growth_rate }}%
                          </span>
                        </div>
                      </div>
                      <div class="text-right">
                        <div class="text-sm font-bold text-teal-600">{{ trend.new_customers }}</div>
                        <div class="text-xs text-gray-500">new customers</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-else-if="loadingAdvancedAnalytics" class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-spinner fa-spin text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">Loading acquisition trends...</p>
              </div>
              <div v-else class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-user-plus text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">No acquisition trends data available</p>
              </div>
            </div>
          </div>

          <!-- Region & Outlet Analysis Section -->
          <div class="space-y-6">
            <!-- Analysis by Region -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <div class="flex items-start justify-between mb-4">
                <div>
                  <h3 class="text-xl font-bold text-gray-800 mb-2">
                    <i class="fa-solid fa-map-marked-alt text-red-500 mr-2"></i>
                    Analysis by Region
                  </h3>
                  <p class="text-sm text-gray-600">
                    Performa marketing per region untuk mengidentifikasi region dengan potensi terbesar dan area yang perlu perhatian lebih.
                  </p>
                </div>
              </div>
              
              <div v-if="regionData && regionData.length > 0" class="space-y-4">
                <!-- Region Stats Table -->
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Region</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Orders</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unique Customers</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Order Value</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Promo Usage</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Discount</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <tr 
                        v-for="(region, index) in regionData" 
                        :key="region.region_id"
                        class="hover:bg-gray-50 cursor-pointer"
                        @click="selectedRegionId = region.region_id; loadOutletData()"
                      >
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="flex-shrink-0 w-8 h-8 bg-red-500 rounded-lg flex items-center justify-center text-white font-bold text-xs mr-3">
                              {{ index + 1 }}
                            </div>
                            <div class="text-sm font-medium text-gray-900">{{ region.region_name }}</div>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ region.total_orders }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ region.unique_customers }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{{ formatCurrency(region.total_revenue) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatCurrency(region.avg_order_value) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold" :class="region.promo_usage_percentage >= 50 ? 'text-green-600' : region.promo_usage_percentage >= 30 ? 'text-yellow-600' : 'text-red-600'">
                              {{ region.promo_usage_percentage }}%
                            </span>
                            <span class="text-xs text-gray-500">({{ region.orders_with_promo }}/{{ region.total_orders }})</span>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-orange-600">{{ formatCurrency(region.total_discount + region.total_manual_discount) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div v-else-if="loadingRegionOutlet" class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-spinner fa-spin text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">Loading region analysis...</p>
              </div>
              <div v-else class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-map-marked-alt text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">No region data available</p>
              </div>
            </div>

            <!-- Analysis by Outlet -->
            <div class="bg-white/80 backdrop-blur-sm rounded-2xl p-6 shadow-xl border border-white/20">
              <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                  <h3 class="text-xl font-bold text-gray-800 mb-2">
                    <i class="fa-solid fa-store text-blue-500 mr-2"></i>
                    Analysis by Outlet
                  </h3>
                  <p class="text-sm text-gray-600">
                    Performa marketing per outlet untuk melihat outlet terbaik dan yang perlu ditingkatkan. 
                    <span v-if="selectedRegionId" class="font-semibold text-blue-600">Filtered by selected region.</span>
                    <span v-else>Showing all outlets.</span>
                  </p>
                </div>
                <button 
                  v-if="selectedRegionId"
                  @click="selectedRegionId = null; loadOutletData()"
                  class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition text-sm"
                >
                  <i class="fa-solid fa-times mr-2"></i>
                  Clear Filter
                </button>
              </div>
              
              <div v-if="outletData && outletData.length > 0" class="space-y-4">
                <!-- Outlet Stats Table -->
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                      <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Region</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Orders</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unique Customers</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Order Value</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Per Pax</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Promo Usage</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Discount</th>
                      </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                      <tr 
                        v-for="(outlet, index) in outletData" 
                        :key="outlet.outlet_id"
                        class="hover:bg-gray-50"
                      >
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center">
                            <div class="flex-shrink-0 w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center text-white font-bold text-xs mr-3">
                              {{ index + 1 }}
                            </div>
                            <div>
                              <div class="text-sm font-medium text-gray-900">{{ outlet.outlet_name }}</div>
                              <div class="text-xs text-gray-500">{{ outlet.outlet_code }}</div>
                            </div>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ outlet.region_name || 'N/A' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ outlet.total_orders }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ outlet.unique_customers }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{{ formatCurrency(outlet.total_revenue) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatCurrency(outlet.avg_order_value) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatCurrency(outlet.total_pax > 0 ? outlet.total_revenue / outlet.total_pax : 0) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                          <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold" :class="outlet.promo_usage_percentage >= 50 ? 'text-green-600' : outlet.promo_usage_percentage >= 30 ? 'text-yellow-600' : 'text-red-600'">
                              {{ outlet.promo_usage_percentage }}%
                            </span>
                            <span class="text-xs text-gray-500">({{ outlet.orders_with_promo }}/{{ outlet.total_orders }})</span>
                          </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-orange-600">{{ formatCurrency(outlet.total_discount + outlet.total_manual_discount) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div v-else-if="loadingRegionOutlet" class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-spinner fa-spin text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">Loading outlet analysis...</p>
              </div>
              <div v-else class="text-center py-8 text-gray-500">
                <i class="fa-solid fa-store text-4xl mb-2 opacity-50"></i>
                <p class="text-sm">No outlet data available</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- RFM Detail Modal -->
    <div v-if="showRFMModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click.self="closeRFMModal">
      <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-6xl shadow-lg rounded-2xl bg-white">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h3 class="text-2xl font-bold text-gray-800">
              <i class="fa-solid fa-users-gear text-indigo-500 mr-2"></i>
              Detail {{ selectedSegment }} Customers
            </h3>
            <p class="text-sm text-gray-500 mt-1">
              RFM Score menunjukkan kombinasi Recency, Frequency, dan Monetary value customer
            </p>
          </div>
          <button @click="closeRFMModal" class="text-gray-400 hover:text-gray-600">
            <i class="fa-solid fa-times text-2xl"></i>
          </button>
        </div>

        <!-- RFM Score Explanation in Modal -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4 mb-4">
          <div class="mb-3">
            <p class="text-sm font-semibold text-blue-700 mb-2">
              <i class="fa-solid fa-info-circle mr-2"></i>Penjelasan Lengkap RFM Score
            </p>
            <p class="text-xs text-gray-700 mb-3">
              <strong>RFM Analysis</strong> adalah metode segmentasi customer berdasarkan 3 dimensi, masing-masing dengan skor 1-4:
            </p>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
            <!-- Recency -->
            <div class="bg-white rounded-lg p-3 border border-blue-100">
              <p class="text-xs font-bold text-blue-700 mb-2">R (Recency)</p>
              <p class="text-xs text-gray-600 mb-2">Kapan terakhir kali customer berbelanja</p>
              <ul class="text-xs text-gray-600 space-y-1">
                <li><strong>Score 4:</strong> Baru-baru ini (Q1 - 25% teratas)</li>
                <li><strong>Score 3:</strong> Cukup baru (Q2)</li>
                <li><strong>Score 2:</strong> Sudah agak lama (Q3)</li>
                <li><strong>Score 1:</strong> Sudah lama sekali (Q4 - 25% terbawah)</li>
              </ul>
              <p class="text-xs text-gray-500 mt-2 italic">Semakin rendah hari sejak belanja terakhir = Score lebih tinggi</p>
            </div>
            
            <!-- Frequency -->
            <div class="bg-white rounded-lg p-3 border border-green-100">
              <p class="text-xs font-bold text-green-700 mb-2">F (Frequency)</p>
              <p class="text-xs text-gray-600 mb-2">Seberapa sering customer berbelanja</p>
              <ul class="text-xs text-gray-600 space-y-1">
                <li><strong>Score 4:</strong> Sangat sering (Q4 - 25% teratas)</li>
                <li><strong>Score 3:</strong> Sering (Q3)</li>
                <li><strong>Score 2:</strong> Cukup sering (Q2)</li>
                <li><strong>Score 1:</strong> Jarang (Q1 - 25% terbawah)</li>
              </ul>
              <p class="text-xs text-gray-500 mt-2 italic">Semakin banyak order = Score lebih tinggi</p>
            </div>
            
            <!-- Monetary -->
            <div class="bg-white rounded-lg p-3 border border-purple-100">
              <p class="text-xs font-bold text-purple-700 mb-2">M (Monetary)</p>
              <p class="text-xs text-gray-600 mb-2">Total nilai pembelian customer</p>
              <ul class="text-xs text-gray-600 space-y-1">
                <li><strong>Score 4:</strong> Sangat besar (Q4 - 25% teratas)</li>
                <li><strong>Score 3:</strong> Besar (Q3)</li>
                <li><strong>Score 2:</strong> Sedang (Q2)</li>
                <li><strong>Score 1:</strong> Kecil (Q1 - 25% terbawah)</li>
              </ul>
              <p class="text-xs text-gray-500 mt-2 italic">Semakin besar total belanja = Score lebih tinggi</p>
            </div>
          </div>
          
          <div class="bg-white rounded-lg p-3 border border-yellow-200 mb-3">
            <p class="text-xs font-bold text-yellow-700 mb-2">
              <i class="fa-solid fa-lightbulb mr-2"></i>Format Score & Contoh
            </p>
            <p class="text-xs text-gray-700 mb-2">
              <strong>Format Score:</strong> <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded font-mono">R4F3M4</span> = Recency Score 4, Frequency Score 3, Monetary Score 4
            </p>
            <p class="text-xs text-gray-700">
              <strong>Contoh:</strong> Score <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded font-mono">R4F3M4</span> berarti customer belanja baru-baru ini (R4), cukup sering (F3), dan nilai belanja besar (M4) - termasuk kategori <strong>Champions</strong>.
            </p>
          </div>
          
          <div class="bg-white rounded-lg p-3 border border-indigo-200">
            <p class="text-xs font-bold text-indigo-700 mb-2">
              <i class="fa-solid fa-users mr-2"></i>Segmentasi Customer
            </p>
            <ul class="text-xs text-gray-600 space-y-1">
              <li><strong>Champions (R≥3, F≥3, M≥3):</strong> Customer terbaik - belanja baru, sering, dan besar. Prioritaskan untuk program VIP/exclusive.</li>
              <li><strong>Loyal (R≥2, F≥3):</strong> Customer setia yang sering belanja. Berikan reward/loyalty program untuk meningkatkan nilai transaksi.</li>
              <li><strong>At Risk (R≤2, F≥2):</strong> Dulu sering belanja tapi sudah lama tidak. Butuh re-engagement campaign untuk mengembalikan.</li>
              <li><strong>Lost (R≤2, F≤2):</strong> Sudah lama tidak belanja dan jarang. Fokus pada win-back campaign atau mungkin sudah pindah ke kompetitor.</li>
            </ul>
          </div>
          
          <div class="mt-3 text-xs text-gray-500 italic bg-white rounded p-2 border border-gray-200">
            <p><strong>Catatan:</strong> Score dihitung menggunakan quartile (Q1, Q2, Q3, Q4) dari semua customer dalam periode yang dipilih. Setiap score (1-4) mewakili quartile dari distribusi data customer.</p>
          </div>
        </div>

        <!-- Filters & Sort Section -->
        <div class="bg-gray-50 rounded-lg p-4 mb-4 border border-gray-200">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <!-- Search -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
              <input 
                type="text" 
                v-model="rfmFilters.search" 
                @input="debounceLoadRFMDetail"
                placeholder="Nama atau Phone..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              />
            </div>
            
            <!-- Revenue Range -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Revenue Range</label>
              <div class="flex gap-2">
                <input 
                  type="number" 
                  v-model.number="rfmFilters.min_revenue" 
                  @input="debounceLoadRFMDetail"
                  placeholder="Min"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                />
                <input 
                  type="number" 
                  v-model.number="rfmFilters.max_revenue" 
                  @input="debounceLoadRFMDetail"
                  placeholder="Max"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </div>
            
            <!-- Orders Range -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Orders Range</label>
              <div class="flex gap-2">
                <input 
                  type="number" 
                  v-model.number="rfmFilters.min_orders" 
                  @input="debounceLoadRFMDetail"
                  placeholder="Min"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                />
                <input 
                  type="number" 
                  v-model.number="rfmFilters.max_orders" 
                  @input="debounceLoadRFMDetail"
                  placeholder="Max"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                />
              </div>
            </div>
          </div>
          
          <!-- Sort & Per Page -->
          <div class="flex justify-between items-end gap-4">
            <div class="flex gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                <select 
                  v-model="rfmSort.sort_by" 
                  @change="loadRFMDetail"
                  class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="total_revenue">Total Revenue</option>
                  <option value="total_orders">Total Orders</option>
                  <option value="last_order_date">Last Order</option>
                  <option value="recency_days">Recency Days</option>
                  <option value="avg_order_value">Avg Order Value</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Order</label>
                <select 
                  v-model="rfmSort.sort_order" 
                  @change="loadRFMDetail"
                  class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
                >
                  <option value="desc">Descending</option>
                  <option value="asc">Ascending</option>
                </select>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
              <select 
                v-model.number="rfmPerPage" 
                @change="rfmPagination.current_page = 1; loadRFMDetail()"
                class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500"
              >
                <option :value="10">10</option>
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
              </select>
            </div>
          </div>
        </div>

        <div v-if="rfmDetailLoading" class="flex justify-center items-center py-20">
          <i class="fa fa-spinner fa-spin text-4xl text-blue-500"></i>
        </div>

        <div v-else-if="rfmDetailData.length === 0" class="text-center py-20 text-gray-500">
          <i class="fa-solid fa-inbox text-6xl mb-4 opacity-50"></i>
          <p class="text-lg">Tidak ada data customer untuk segment ini</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Customer</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Orders</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Revenue</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Order</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Order Value</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RFM Score</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="(customer, index) in rfmDetailData" :key="customer.id || index" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ (rfmPagination.current_page - 1) * rfmPagination.per_page + index + 1 }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ customer.name || 'N/A' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ customer.phone || 'N/A' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ customer.total_orders || 0 }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(customer.total_revenue || 0) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ customer.last_order_date || 'N/A' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(customer.avg_order_value || 0) }}</td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-xs font-semibold rounded-full" :class="getRFMScoreClass(customer.rfm_score)">
                    {{ customer.rfm_score || 'N/A' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="rfmPagination.total_pages > 1" class="mt-6 flex items-center justify-between">
          <div class="text-sm text-gray-700">
            Menampilkan {{ rfmPagination.from }} sampai {{ rfmPagination.to }} dari {{ rfmPagination.total }} customer
          </div>
          <div class="flex gap-2">
            <button 
              @click="rfmPagination.current_page = 1; loadRFMDetail()"
              :disabled="rfmPagination.current_page === 1"
              class="px-3 py-2 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              <i class="fa-solid fa-angle-double-left"></i>
            </button>
            <button 
              @click="rfmPagination.current_page--; loadRFMDetail()"
              :disabled="rfmPagination.current_page === 1"
              class="px-3 py-2 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              <i class="fa-solid fa-angle-left"></i>
            </button>
            <span class="px-4 py-2 text-sm text-gray-700">
              Page {{ rfmPagination.current_page }} of {{ rfmPagination.total_pages }}
            </span>
            <button 
              @click="rfmPagination.current_page++; loadRFMDetail()"
              :disabled="rfmPagination.current_page >= rfmPagination.total_pages"
              class="px-3 py-2 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              <i class="fa-solid fa-angle-right"></i>
            </button>
            <button 
              @click="rfmPagination.current_page = rfmPagination.total_pages; loadRFMDetail()"
              :disabled="rfmPagination.current_page >= rfmPagination.total_pages"
              class="px-3 py-2 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              <i class="fa-solid fa-angle-double-right"></i>
            </button>
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <button @click="closeRFMModal" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
            Tutup
          </button>
        </div>
      </div>
    </div>

    <!-- Transaction Modal (Promo & Manual Discount) -->
    <div v-if="showTransactionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50" @click.self="closeTransactionModal">
      <div class="relative top-20 mx-auto p-5 border w-11/12 shadow-lg rounded-xl bg-white">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-2xl font-bold text-gray-800">
            <i class="fa-solid fa-receipt mr-2" :class="transactionModalType === 'promo' ? 'text-purple-500' : 'text-orange-500'"></i>
            {{ transactionModalType === 'promo' ? 'Promo Transactions' : 'Manual Discount Transactions' }}
          </h3>
          <button @click="closeTransactionModal" class="text-gray-400 hover:text-gray-600">
            <i class="fa-solid fa-times text-2xl"></i>
          </button>
        </div>

        <!-- Modal Header Info -->
        <div v-if="transactionModalType === 'promo' && selectedPromo" class="mb-4 p-4 bg-purple-50 rounded-lg border border-purple-200">
          <div class="text-sm font-semibold text-purple-800 mb-1">{{ selectedPromo.name }}</div>
          <div class="text-xs text-purple-600">{{ selectedPromo.code }} • {{ selectedPromo.type }} • Usage: {{ selectedPromo.usage_count }}x</div>
        </div>
        <div v-if="transactionModalType === 'manual_discount' && selectedManualDiscount" class="mb-4 p-4 bg-orange-50 rounded-lg border border-orange-200">
          <div class="text-sm font-semibold text-orange-800 mb-1">{{ selectedManualDiscount.reason }}</div>
          <div class="text-xs text-orange-600">Usage: {{ selectedManualDiscount.usage_count }} orders • Total Discount: {{ formatCurrency(selectedManualDiscount.total_discount) }}</div>
        </div>

        <!-- Filters -->
        <div class="mb-4 grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input 
              v-model="transactionFilters.search"
              @input="debounceLoadTransactions"
              type="text"
              placeholder="Order No / Member ID"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Min Amount</label>
            <input 
              v-model="transactionFilters.min_amount"
              @input="debounceLoadTransactions"
              type="number"
              placeholder="0"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Max Amount</label>
            <input 
              v-model="transactionFilters.max_amount"
              @input="debounceLoadTransactions"
              type="number"
              placeholder="0"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Per Page</label>
            <select 
              v-model="transactionPerPage"
              @change="transactionPagination.current_page = 1; loadTransactions()"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
              <option :value="10">10</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="100">100</option>
            </select>
          </div>
        </div>

        <!-- Sort -->
        <div class="mb-4 flex items-center gap-4">
          <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700">Sort By:</label>
            <select 
              v-model="transactionSort.sort_by"
              @change="loadTransactions()"
              class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
              <option value="created_at">Date</option>
              <option value="grand_total">Amount</option>
              <option value="discount">Discount</option>
              <option value="nomor">Order No</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700">Order:</label>
            <select 
              v-model="transactionSort.sort_order"
              @change="loadTransactions()"
              class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
              <option value="desc">Descending</option>
              <option value="asc">Ascending</option>
            </select>
          </div>
        </div>

        <!-- Loading State -->
        <div v-if="transactionLoading" class="text-center py-20">
          <i class="fa-solid fa-spinner fa-spin text-4xl text-purple-500 mb-4"></i>
          <p class="text-gray-500">Loading transactions...</p>
        </div>

        <!-- Transaction Table -->
        <div v-else-if="transactionData.length > 0" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order No</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet Code</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Outlet Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grand Total</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                <th v-if="transactionModalType === 'manual_discount'" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Manual Discount</th>
                <th v-if="transactionModalType === 'manual_discount'" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="(transaction, index) in transactionData" :key="transaction.id || index" class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ (transactionPagination.current_page - 1) * transactionPagination.per_page + index + 1 }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ transaction.nomor || 'N/A' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ transaction.created_at ? new Date(transaction.created_at).toLocaleString('id-ID') : 'N/A' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ transaction.member_id || 'Non-Member' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ transaction.kode_outlet || 'N/A' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-700">{{ transaction.nama_outlet || 'N/A' }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ formatCurrency(transaction.grand_total || 0) }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">{{ formatCurrency(transaction.discount || 0) }}</td>
                <td v-if="transactionModalType === 'manual_discount'" class="px-6 py-4 whitespace-nowrap text-sm text-orange-600">{{ formatCurrency(transaction.manual_discount_amount || 0) }}</td>
                <td v-if="transactionModalType === 'manual_discount'" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ transaction.manual_discount_reason || 'N/A' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else-if="!transactionLoading" class="text-center py-20 text-gray-500">
          <i class="fa-solid fa-inbox text-4xl mb-2 opacity-50"></i>
          <p>No transactions found</p>
        </div>

        <!-- Pagination -->
        <div v-if="transactionPagination.total_pages > 1" class="mt-6 flex items-center justify-between">
          <div class="text-sm text-gray-700">
            Menampilkan {{ transactionPagination.from }} sampai {{ transactionPagination.to }} dari {{ transactionPagination.total }} transaksi
          </div>
          <div class="flex gap-2">
            <button 
              @click="transactionPagination.current_page = 1; loadTransactions()"
              :disabled="transactionPagination.current_page === 1"
              class="px-3 py-2 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              <i class="fa-solid fa-angle-double-left"></i>
            </button>
            <button 
              @click="transactionPagination.current_page--; loadTransactions()"
              :disabled="transactionPagination.current_page === 1"
              class="px-3 py-2 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              <i class="fa-solid fa-angle-left"></i>
            </button>
            <span class="px-4 py-2 text-sm text-gray-700">
              Page {{ transactionPagination.current_page }} of {{ transactionPagination.total_pages }}
            </span>
            <button 
              @click="transactionPagination.current_page++; loadTransactions()"
              :disabled="transactionPagination.current_page >= transactionPagination.total_pages"
              class="px-3 py-2 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              <i class="fa-solid fa-angle-right"></i>
            </button>
            <button 
              @click="transactionPagination.current_page = transactionPagination.total_pages; loadTransactions()"
              :disabled="transactionPagination.current_page >= transactionPagination.total_pages"
              class="px-3 py-2 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
            >
              <i class="fa-solid fa-angle-double-right"></i>
            </button>
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <button @click="closeTransactionModal" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition">
            Tutup
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
  behaviorData: Object,
  strategies: Object,
  filters: Object,
  error: String
});

const loading = ref(false);
const loadingStrategies = ref(false);
const behaviorData = ref(props.behaviorData);
const strategies = ref(props.strategies);
const error = ref(props.error);

// RFM Modal State
const showRFMModal = ref(false);
const selectedSegment = ref(null);
const rfmDetailLoading = ref(false);
const rfmDetailData = ref([]);
const rfmPagination = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
  total_pages: 0,
  from: 0,
  to: 0
});

// RFM Modal Filters & Sort
const rfmFilters = ref({
  search: '',
  min_revenue: '',
  max_revenue: '',
  min_orders: '',
  max_orders: ''
});
const rfmSort = ref({
  sort_by: 'total_revenue',
  sort_order: 'desc'
});
const rfmPerPage = ref(10);

// Transaction Modal State (for Promo & Manual Discount)
const showTransactionModal = ref(false);
const transactionModalType = ref(null); // 'promo' or 'manual_discount'
const selectedPromo = ref(null);
const selectedManualDiscount = ref(null);
const transactionLoading = ref(false);
const transactionData = ref([]);
const transactionPagination = ref({
  current_page: 1,
  per_page: 10,
  total: 0,
  total_pages: 0,
  from: 0,
  to: 0
});
const transactionFilters = ref({
  search: '',
  min_amount: '',
  max_amount: ''
});
const transactionSort = ref({
  sort_by: 'created_at',
  sort_order: 'desc'
});
const transactionPerPage = ref(10);

// Customer Value Analysis Data
const clvData = ref(null);
const repeatRateData = ref(null);
const avgDaysData = ref(null);
const loadingValueAnalysis = ref(false);

// Advanced Analytics Data
const basketData = ref(null);
const peakHoursData = ref(null);
const acquisitionData = ref(null);
const loadingAdvancedAnalytics = ref(false);

// Region & Outlet Analysis Data
const regionData = ref(null);
const outletData = ref(null);
const selectedRegionId = ref(null);
const loadingRegionOutlet = ref(false);

// Helper function untuk mendapatkan tanggal awal bulan
const getFirstDayOfMonth = () => {
  const now = new Date();
  return new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
};

// Helper function untuk mendapatkan tanggal hari ini
const getToday = () => {
  return new Date().toISOString().split('T')[0];
};

const filters = ref({
  date_from: props.filters?.date_from || getFirstDayOfMonth(),
  date_to: props.filters?.date_to || getToday(),
  outlet_code: props.filters?.outlet_code || null
});

// Computed properties
const memberRevenue = computed(() => {
  const revenue = behaviorData.value?.summary?.member_insights?.total_revenue;
  return revenue ? parseFloat(revenue) : 0;
});

const nonMemberRevenue = computed(() => {
  const revenue = behaviorData.value?.summary?.non_member_insights?.total_revenue;
  return revenue ? parseFloat(revenue) : 0;
});

const memberCount = computed(() => {
  return parseInt(behaviorData.value?.summary?.member_insights?.unique_customers || 0);
});

const nonMemberCount = computed(() => {
  return parseInt(behaviorData.value?.summary?.non_member_insights?.unique_customers || 0);
});

const avgAOV = computed(() => {
  const memberAOV = parseFloat(behaviorData.value?.summary?.member_insights?.avg_order_value || 0);
  const nonMemberAOV = parseFloat(behaviorData.value?.summary?.non_member_insights?.avg_order_value || 0);
  return (memberAOV + nonMemberAOV) / 2;
});

const aovDifference = computed(() => {
  const memberAOV = parseFloat(behaviorData.value?.summary?.member_insights?.avg_order_value || 0);
  const nonMemberAOV = parseFloat(behaviorData.value?.summary?.non_member_insights?.avg_order_value || 0);
  return memberAOV - nonMemberAOV;
});

const avgPerPax = computed(() => {
  // Overall average per pax dari semua transaksi (member + non-member)
  return parseFloat(behaviorData.value?.summary?.comparison?.overall_avg_pax || 0);
});

const avgPerPaxDifference = computed(() => {
  // Selisih average per pax member vs non-member
  return parseFloat(behaviorData.value?.summary?.comparison?.avg_pax_difference || 0);
});

const promoAnalytics = computed(() => {
  return behaviorData.value?.promo_analytics || null;
});

// Chart Options
const revenueChartOptions = computed(() => {
  if (!behaviorData.value) return null;
  
  return {
    chart: {
      type: 'donut',
      fontFamily: 'Inter, sans-serif',
      animations: {
        enabled: true,
        speed: 800
      }
    },
    labels: ['Member', 'Non-Member'],
    colors: ['#3B82F6', '#A855F7'],
    legend: {
      position: 'bottom',
      fontSize: '14px',
      fontWeight: 600
    },
    dataLabels: {
      enabled: true,
      formatter: (val) => `${val.toFixed(1)}%`
    },
    plotOptions: {
      pie: {
        donut: {
          size: '65%',
          labels: {
            show: true,
              total: {
                show: true,
                label: 'Total Revenue',
                formatter: () => {
                  const total = parseFloat(memberRevenue.value || 0) + parseFloat(nonMemberRevenue.value || 0);
                  return formatCurrency(total);
                }
              }
          }
        }
      }
    },
    tooltip: {
      y: {
        formatter: (val) => formatCurrency(val)
      }
    }
  };
});

const revenueChartSeries = computed(() => {
  return [memberRevenue.value, nonMemberRevenue.value];
});

const rfmChartOptions = computed(() => {
  return {
    chart: {
      type: 'bar',
      fontFamily: 'Inter, sans-serif',
      animations: {
        enabled: true,
        speed: 800
      },
      toolbar: {
        show: false
      },
      events: {
        dataPointSelection: (event, chartContext, config) => {
          // Event handler akan dipanggil dari @dataPointSelection di template
        }
      }
    },
    colors: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444'],
    xaxis: {
      categories: ['Champions', 'Loyal', 'At Risk', 'Lost'],
      labels: {
        style: {
          fontSize: '12px',
          fontWeight: 600
        }
      }
    },
    yaxis: {
      title: {
        text: 'Number of Customers'
      }
    },
    dataLabels: {
      enabled: true,
      formatter: (val) => val
    },
    plotOptions: {
      bar: {
        borderRadius: 8,
        columnWidth: '60%'
      }
    }
  };
});

const rfmChartSeries = computed(() => {
  const summary = behaviorData.value?.rfm_segmentation?.summary || {};
  return [{
    name: 'Customers',
    data: [
      summary.champions_count || 0,
      summary.loyal_count || 0,
      summary.at_risk_count || 0,
      summary.lost_count || 0
    ]
  }];
});

const peakHoursChartOptions = computed(() => {
  const peakHours = behaviorData.value?.time_patterns?.member?.peak_hours || [];
  const hours = peakHours.map(h => `${h.hour}:00`);
  
  return {
    chart: {
      type: 'area',
      fontFamily: 'Inter, sans-serif',
      animations: {
        enabled: true,
        speed: 800
      },
      toolbar: {
        show: false
      }
    },
    colors: ['#10B981'],
    xaxis: {
      categories: hours,
      labels: {
        style: {
          fontSize: '12px'
        }
      }
    },
    yaxis: {
      title: {
        text: 'Revenue'
      }
    },
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.7,
        opacityTo: 0.3
      }
    },
    stroke: {
      curve: 'smooth',
      width: 3
    },
    tooltip: {
      y: {
        formatter: (val) => formatCurrency(val)
      }
    }
  };
});

const peakHoursChartSeries = computed(() => {
  const peakHours = behaviorData.value?.time_patterns?.member?.peak_hours || [];
  return [{
    name: 'Revenue',
    data: peakHours.map(h => h.revenue || 0)
  }];
});

const productChartOptions = computed(() => {
  const memberProducts = behaviorData.value?.product_preferences?.member || [];
  const nonMemberProducts = behaviorData.value?.product_preferences?.non_member || [];
  
  // Gabungkan dan ambil top 10 products
  const topProducts = [...memberProducts.slice(0, 10), ...nonMemberProducts.slice(0, 10)]
    .reduce((acc, product) => {
      if (!acc.find(p => p.item_name === product.item_name)) {
        acc.push(product);
      }
      return acc;
    }, [])
    .slice(0, 10);
  
  return {
    chart: {
      type: 'bar',
      fontFamily: 'Inter, sans-serif',
      animations: {
        enabled: true,
        speed: 800
      },
      toolbar: {
        show: false
      }
    },
    colors: ['#3B82F6', '#A855F7'],
    xaxis: {
      categories: topProducts.map(p => {
        const name = p.item_name || 'Unknown';
        // Potong nama jika terlalu panjang untuk 10 items
        return name.length > 25 ? name.substring(0, 25) + '...' : name;
      }),
      labels: {
        style: {
          fontSize: '10px'
        },
        rotate: -45,
        maxHeight: 100
      }
    },
    yaxis: {
      title: {
        text: 'Revenue'
      }
    },
    legend: {
      position: 'top'
    },
    plotOptions: {
      bar: {
        horizontal: false,
        borderRadius: 8,
        columnWidth: '60%'
      }
    },
    tooltip: {
      y: {
        formatter: (val) => formatCurrency(val)
      }
    }
  };
});

const productChartSeries = computed(() => {
  const memberProducts = behaviorData.value?.product_preferences?.member || [];
  const nonMemberProducts = behaviorData.value?.product_preferences?.non_member || [];
  
  // Gabungkan dan ambil top 10 products
  const topProducts = [...memberProducts.slice(0, 10), ...nonMemberProducts.slice(0, 10)]
    .reduce((acc, product) => {
      if (!acc.find(p => p.item_name === product.item_name)) {
        acc.push(product);
      }
      return acc;
    }, [])
    .slice(0, 10);
  
  const memberData = topProducts.map(product => {
    const memberProduct = memberProducts.find(p => p.item_name === product.item_name);
    return memberProduct?.total_revenue || 0;
  });
  
  const nonMemberData = topProducts.map(product => {
    const nonMemberProduct = nonMemberProducts.find(p => p.item_name === product.item_name);
    return nonMemberProduct?.total_revenue || 0;
  });
  
  return [
    { name: 'Member', data: memberData },
    { name: 'Non-Member', data: nonMemberData }
  ];
});

// Methods
const formatNumber = (value) => {
  const numValue = typeof value === 'string' ? parseFloat(value) : (value || 0);
  if (isNaN(numValue)) return '0.00';
  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(numValue);
};

const formatCurrency = (value) => {
  // Pastikan value adalah number
  const numValue = typeof value === 'string' ? parseFloat(value) : (value || 0);
  if (isNaN(numValue)) return 'Rp 0';
  
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(numValue);
};

const loadData = async () => {
  loading.value = true;
  error.value = null;
  
  try {
    router.get('/marketing/dashboard', filters.value, {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        behaviorData.value = page.props.behaviorData;
        strategies.value = page.props.strategies;
      },
      onError: (errors) => {
        error.value = 'Error loading dashboard data';
      },
      onFinish: () => {
        loading.value = false;
      }
    });
    
    // Load additional value analysis data
    loadValueAnalysis();
    loadAdvancedAnalytics();
    loadRegionOutletAnalysis();
  } catch (err) {
    error.value = err.message;
    loading.value = false;
  }
};

const loadValueAnalysis = async () => {
  loadingValueAnalysis.value = true;
  try {
    // Load CLV
    try {
      const clvResponse = await axios.get('/api/marketing/customer-lifetime-value', {
        params: {
          date_from: filters.value.date_from,
          date_to: filters.value.date_to,
          outlet_code: filters.value.outlet_code
        }
      });
      if (clvResponse.data?.success) {
        clvData.value = clvResponse.data.data;
      } else {
        console.error('CLV API Error:', clvResponse.data);
        clvData.value = null;
      }
    } catch (err) {
      console.error('Error loading CLV:', err);
      clvData.value = null;
    }

    // Load Repeat Purchase Rate
    try {
      const repeatResponse = await axios.get('/api/marketing/repeat-purchase-rate', {
        params: {
          date_from: filters.value.date_from,
          date_to: filters.value.date_to,
          outlet_code: filters.value.outlet_code
        }
      });
      if (repeatResponse.data?.success) {
        repeatRateData.value = repeatResponse.data.data;
      } else {
        console.error('Repeat Purchase Rate API Error:', repeatResponse.data);
        repeatRateData.value = null;
      }
    } catch (err) {
      console.error('Error loading Repeat Purchase Rate:', err);
      repeatRateData.value = null;
    }

    // Load Average Days Between Orders
    try {
      const avgDaysResponse = await axios.get('/api/marketing/average-days-between-orders', {
        params: {
          date_from: filters.value.date_from,
          date_to: filters.value.date_to,
          outlet_code: filters.value.outlet_code
        }
      });
      if (avgDaysResponse.data?.success) {
        avgDaysData.value = avgDaysResponse.data.data;
      } else {
        console.error('Average Days API Error:', avgDaysResponse.data);
        avgDaysData.value = null;
      }
    } catch (err) {
      console.error('Error loading Average Days:', err);
      avgDaysData.value = null;
    }
  } catch (err) {
    console.error('Error loading value analysis:', err);
  } finally {
    loadingValueAnalysis.value = false;
  }
};

const loadAdvancedAnalytics = async () => {
  loadingAdvancedAnalytics.value = true;
  try {
    // Load Basket Analysis
    try {
      const basketResponse = await axios.get('/api/marketing/basket-analysis', {
        params: {
          date_from: filters.value.date_from,
          date_to: filters.value.date_to,
          outlet_code: filters.value.outlet_code,
          limit: 10
        }
      });
      if (basketResponse.data?.success) {
        basketData.value = basketResponse.data.data;
        console.log('Basket Analysis loaded:', basketData.value);
      } else {
        console.error('Basket Analysis API Error:', basketResponse.data);
        basketData.value = null;
      }
    } catch (err) {
      console.error('Error loading Basket Analysis:', err);
      console.error('Error details:', err.response?.data || err.message);
      basketData.value = null;
    }

    // Load Peak Hours & Day Analysis
    try {
      const peakResponse = await axios.get('/api/marketing/peak-hours-day-analysis', {
        params: {
          date_from: filters.value.date_from,
          date_to: filters.value.date_to,
          outlet_code: filters.value.outlet_code
        }
      });
      if (peakResponse.data?.success) {
        peakHoursData.value = peakResponse.data.data;
      } else {
        console.error('Peak Hours API Error:', peakResponse.data);
        peakHoursData.value = null;
      }
    } catch (err) {
      console.error('Error loading Peak Hours:', err);
      peakHoursData.value = null;
    }

    // Load Customer Acquisition Trends
    try {
      const acquisitionResponse = await axios.get('/api/marketing/customer-acquisition-trends', {
        params: {
          date_from: filters.value.date_from,
          date_to: filters.value.date_to,
          outlet_code: filters.value.outlet_code,
          group_by: 'day'
        }
      });
      if (acquisitionResponse.data?.success) {
        acquisitionData.value = acquisitionResponse.data.data;
      } else {
        console.error('Acquisition Trends API Error:', acquisitionResponse.data);
        acquisitionData.value = null;
      }
    } catch (err) {
      console.error('Error loading Acquisition Trends:', err);
      acquisitionData.value = null;
    }
  } catch (err) {
    console.error('Error loading advanced analytics:', err);
  } finally {
    loadingAdvancedAnalytics.value = false;
  }
};

// Helper function untuk menghitung persentase day performance
const getDayPercentage = (dayRevenue) => {
  if (!peakHoursData.value || !peakHoursData.value.day_of_week_stats || peakHoursData.value.day_of_week_stats.length === 0) {
    return 0;
  }
  const maxRevenue = Math.max(...peakHoursData.value.day_of_week_stats.map(d => d.total_revenue));
  return maxRevenue > 0 ? (dayRevenue / maxRevenue) * 100 : 0;
};

// Load Region & Outlet Analysis
const loadRegionOutletAnalysis = async () => {
  loadingRegionOutlet.value = true;
  try {
    // Load Region Analysis
    try {
      const regionResponse = await axios.get('/api/marketing/analysis-by-region', {
        params: {
          date_from: filters.value.date_from,
          date_to: filters.value.date_to
        }
      });
      if (regionResponse.data?.success) {
        regionData.value = regionResponse.data.data;
      } else {
        console.error('Region Analysis API Error:', regionResponse.data);
        regionData.value = null;
      }
    } catch (err) {
      console.error('Error loading Region Analysis:', err);
      regionData.value = null;
    }

    // Load Outlet Analysis
    loadOutletData();
  } catch (err) {
    console.error('Error loading region/outlet analysis:', err);
  } finally {
    loadingRegionOutlet.value = false;
  }
};

const loadOutletData = async () => {
  try {
    const outletResponse = await axios.get('/api/marketing/analysis-by-outlet', {
      params: {
        date_from: filters.value.date_from,
        date_to: filters.value.date_to,
        region_id: selectedRegionId.value
      }
    });
    if (outletResponse.data?.success) {
      outletData.value = outletResponse.data.data;
    } else {
      console.error('Outlet Analysis API Error:', outletResponse.data);
      outletData.value = null;
    }
  } catch (err) {
    console.error('Error loading Outlet Analysis:', err);
    outletData.value = null;
  }
};

const loadStrategies = async () => {
  loadingStrategies.value = true;
  
  try {
    const response = await axios.get('/api/marketing/strategies', {
      params: filters.value
    });
    
    if (response.data.success) {
      strategies.value = response.data.data;
    }
  } catch (err) {
    console.error('Error loading strategies:', err);
  } finally {
    loadingStrategies.value = false;
  }
};

// RFM Modal Handlers
const handleRFMBarClick = (event, chartContext, config) => {
  try {
    const segmentIndex = config?.dataPointIndex ?? -1;
    const segments = ['Champions', 'Loyal', 'At Risk', 'Lost'];
    
    if (segmentIndex < 0 || segmentIndex >= segments.length) {
      console.error('Invalid segment index:', segmentIndex);
      return;
    }
    
    const segment = segments[segmentIndex];
    
    if (!segment) {
      console.error('Segment not found for index:', segmentIndex);
      return;
    }
    
    selectedSegment.value = segment;
    showRFMModal.value = true;
    
    // Reset filters and pagination
    rfmFilters.value = {
      search: '',
      min_revenue: '',
      max_revenue: '',
      min_orders: '',
      max_orders: ''
    };
    rfmSort.value = {
      sort_by: 'total_revenue',
      sort_order: 'desc'
    };
    rfmPagination.value = {
      current_page: 1,
      per_page: rfmPerPage.value,
      total: 0,
      total_pages: 0,
      from: 0,
      to: 0
    };
    
    // Load data after a short delay to ensure state is set
    setTimeout(() => {
      loadRFMDetail();
    }, 100);
  } catch (error) {
    console.error('Error in handleRFMBarClick:', error);
  }
};

const loadRFMDetail = async () => {
  if (!selectedSegment.value) {
    console.warn('Selected segment is not set');
    return;
  }
  
  rfmDetailLoading.value = true;
  try {
    const segmentName = String(selectedSegment.value || '').trim();
    
    if (!segmentName) {
      console.error('Segment name is empty');
      rfmDetailLoading.value = false;
      return;
    }
    
    // Convert segment name to API format
    // "At Risk" -> "at_risk", "Champions" -> "champions", etc.
    const segmentApi = segmentName.toLowerCase().replace(/\s+/g, '_');
    
    const params = {
      segment: segmentApi,
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
      outlet_code: filters.value.outlet_code || null,
      page: rfmPagination.value?.current_page || 1,
      per_page: rfmPerPage.value || 10,
      sort_by: rfmSort.value?.sort_by || 'total_revenue',
      sort_order: rfmSort.value?.sort_order || 'desc'
    };
    
    // Add filters if not empty
    if (rfmFilters.value?.search) params.search = rfmFilters.value.search;
    if (rfmFilters.value?.min_revenue) params.min_revenue = parseFloat(rfmFilters.value.min_revenue);
    if (rfmFilters.value?.max_revenue) params.max_revenue = parseFloat(rfmFilters.value.max_revenue);
    if (rfmFilters.value?.min_orders) params.min_orders = parseInt(rfmFilters.value.min_orders);
    if (rfmFilters.value?.max_orders) params.max_orders = parseInt(rfmFilters.value.max_orders);
    
    const response = await axios.get('/api/marketing/rfm-detail', { params });
    
    if (response.data?.success) {
      rfmDetailData.value = response.data.data || [];
      rfmPagination.value = response.data.pagination || rfmPagination.value;
    } else {
      console.error('API returned error:', response.data);
      rfmDetailData.value = [];
    }
  } catch (err) {
    console.error('Error loading RFM detail:', err);
    rfmDetailData.value = [];
    if (rfmPagination.value) {
      rfmPagination.value.total = 0;
      rfmPagination.value.total_pages = 0;
    }
  } finally {
    rfmDetailLoading.value = false;
  }
};

// Debounce untuk search input
let debounceTimer = null;
const debounceLoadRFMDetail = () => {
  clearTimeout(debounceTimer);
  rfmPagination.value.current_page = 1; // Reset to first page on filter change
  debounceTimer = setTimeout(() => {
    loadRFMDetail();
  }, 500);
};

const closeRFMModal = () => {
  showRFMModal.value = false;
  selectedSegment.value = null;
  rfmDetailData.value = [];
  // Reset filters
  rfmFilters.value = {
    search: '',
    min_revenue: '',
    max_revenue: '',
    min_orders: '',
    max_orders: ''
  };
  rfmPagination.value = {
    current_page: 1,
    per_page: 10,
    total: 0,
    total_pages: 0,
    from: 0,
    to: 0
  };
};

// Transaction Modal Handlers
const openPromoModal = (promo) => {
  transactionModalType.value = 'promo';
  selectedPromo.value = promo;
  selectedManualDiscount.value = null;
  showTransactionModal.value = true;
  
  // Reset filters and pagination
  transactionFilters.value = {
    search: '',
    min_amount: '',
    max_amount: ''
  };
  transactionSort.value = {
    sort_by: 'created_at',
    sort_order: 'desc'
  };
  transactionPerPage.value = 10;
  transactionPagination.value = {
    current_page: 1,
    per_page: 10,
    total: 0,
    total_pages: 0,
    from: 0,
    to: 0
  };
  
  loadTransactions();
};

const openManualDiscountModal = (discount) => {
  transactionModalType.value = 'manual_discount';
  selectedManualDiscount.value = discount;
  selectedPromo.value = null;
  showTransactionModal.value = true;
  
  // Reset filters and pagination
  transactionFilters.value = {
    search: '',
    min_amount: '',
    max_amount: ''
  };
  transactionSort.value = {
    sort_by: 'created_at',
    sort_order: 'desc'
  };
  transactionPerPage.value = 10;
  transactionPagination.value = {
    current_page: 1,
    per_page: 10,
    total: 0,
    total_pages: 0,
    from: 0,
    to: 0
  };
  
  loadTransactions();
};

const loadTransactions = async () => {
  transactionLoading.value = true;
  try {
    const params = {
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
      outlet_code: filters.value.outlet_code,
      page: transactionPagination.value.current_page,
      per_page: transactionPerPage.value,
      sort_by: transactionSort.value.sort_by,
      sort_order: transactionSort.value.sort_order
    };

    if (transactionFilters.value.search) params.search = transactionFilters.value.search;
    if (transactionFilters.value.min_amount) params.min_amount = parseFloat(transactionFilters.value.min_amount);
    if (transactionFilters.value.max_amount) params.max_amount = parseFloat(transactionFilters.value.max_amount);

    let endpoint = '';
    if (transactionModalType.value === 'promo' && selectedPromo.value) {
      endpoint = '/api/marketing/promo-transactions';
      params.promo_id = selectedPromo.value.id;
    } else if (transactionModalType.value === 'manual_discount' && selectedManualDiscount.value) {
      endpoint = '/api/marketing/manual-discount-transactions';
      params.reason = selectedManualDiscount.value.reason;
    } else {
      return;
    }

    const response = await axios.get(endpoint, { params });
    
    if (response.data?.success) {
      transactionData.value = response.data.data || [];
      transactionPagination.value = response.data.pagination || transactionPagination.value;
    } else {
      console.error('API returned error:', response.data);
      transactionData.value = [];
    }
  } catch (err) {
    console.error('Error loading transactions:', err);
    transactionData.value = [];
  } finally {
    transactionLoading.value = false;
  }
};

let debounceTransactionTimer = null;
const debounceLoadTransactions = () => {
  clearTimeout(debounceTransactionTimer);
  transactionPagination.value.current_page = 1; // Reset to first page on filter change
  debounceTransactionTimer = setTimeout(() => {
    loadTransactions();
  }, 500);
};

const closeTransactionModal = () => {
  showTransactionModal.value = false;
  transactionModalType.value = null;
  selectedPromo.value = null;
  selectedManualDiscount.value = null;
  transactionData.value = [];
  transactionFilters.value = {
    search: '',
    min_amount: '',
    max_amount: ''
  };
  transactionSort.value = {
    sort_by: 'created_at',
    sort_order: 'desc'
  };
  transactionPerPage.value = 10;
  transactionPagination.value = {
    current_page: 1,
    per_page: 10,
    total: 0,
    total_pages: 0,
    from: 0,
    to: 0
  };
};

const getRFMScoreClass = (score) => {
  if (!score) return 'bg-gray-200 text-gray-700';
  if (score.includes('Champion')) return 'bg-blue-100 text-blue-800';
  if (score.includes('Loyal')) return 'bg-green-100 text-green-800';
  if (score.includes('At Risk')) return 'bg-orange-100 text-orange-800';
  if (score.includes('Lost')) return 'bg-red-100 text-red-800';
  return 'bg-gray-200 text-gray-700';
};

onMounted(() => {
  if (!behaviorData.value) {
    loadData();
  } else {
    // Load value analysis even if behaviorData exists
    loadValueAnalysis();
    loadAdvancedAnalytics();
    loadRegionOutletAnalysis();
  }
  if (!strategies.value) {
    loadStrategies();
  }
});
</script>

<script>
import VueApexCharts from 'vue3-apexcharts';

export default {
  components: {
    apexchart: VueApexCharts
  }
}
</script>

<style scoped>
/* Smooth animations */
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.animate-fade-in {
  animation: fadeIn 0.6s ease-out;
}

/* Glassmorphism effect */
.backdrop-blur-xl {
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, #3B82F6, #8B5CF6);
  border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(180deg, #2563EB, #7C3AED);
}
</style>
