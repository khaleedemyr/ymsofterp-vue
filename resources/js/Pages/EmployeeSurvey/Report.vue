<template>
    <Head title="Employee Survey Report" />
    
    <AppLayout>
        <div class="max-w-7xl w-full mx-auto py-8 px-2">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-chart-bar text-blue-500"></i> Employee Survey Report
                </h1>
                <Link 
                    :href="route('employee-survey.index')"
                    class="bg-gray-500 hover:bg-gray-700 text-white px-4 py-2 rounded-xl shadow-lg hover:shadow-2xl transition-all font-semibold"
                >
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali ke Survey
                </Link>
            </div>

            <!-- Filters -->
            <div class="mb-6 bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                <div class="flex flex-wrap gap-4 items-center">
                    <!-- Date From -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">Dari:</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-calendar text-gray-400"></i>
                            </div>
                            <input
                                v-model="dateFrom"
                                @input="onDateInput"
                                type="date"
                                class="pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>
                    </div>

                    <!-- Date To -->
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium text-gray-700">Sampai:</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fa-solid fa-calendar text-gray-400"></i>
                            </div>
                            <input
                                v-model="dateTo"
                                @input="onDateInput"
                                type="date"
                                class="pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                            />
                        </div>
                    </div>

                    <!-- Quick Date Range Buttons -->
                    <div class="flex gap-2">
                        <button
                            @click="setDateRange('today')"
                            class="px-3 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 transition-all duration-300 text-sm"
                        >
                            Hari Ini
                        </button>
                        <button
                            @click="setDateRange('week')"
                            class="px-3 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 transition-all duration-300 text-sm"
                        >
                            Minggu Ini
                        </button>
                        <button
                            @click="setDateRange('month')"
                            class="px-3 py-2 bg-blue-500 text-white rounded-lg shadow hover:bg-blue-600 transition-all duration-300 text-sm"
                        >
                            Bulan Ini
                        </button>
                        <button
                            @click="clearDateFilters"
                            class="px-3 py-2 bg-gray-500 text-white rounded-lg shadow hover:bg-gray-600 transition-all duration-300 text-sm flex items-center gap-1"
                        >
                            <i class="fas fa-times"></i>
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Overall Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fa-solid fa-clipboard-list text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Survey</p>
                            <p class="text-2xl font-bold text-gray-900">{{ totalSurveys }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fa-solid fa-comments text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Responses</p>
                            <p class="text-2xl font-bold text-gray-900">{{ totalResponses }}</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fa-solid fa-star text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Average Score</p>
                            <p class="text-2xl font-bold text-gray-900">{{ overallAverage }}/5</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-100">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fa-solid fa-percentage text-2xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Overall Percentage</p>
                            <p class="text-2xl font-bold text-gray-900">{{ overallPercentage }}%</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Charts -->
            <div class="space-y-8">
                <div v-for="(category, index) in categories" :key="index" class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
                    <!-- Category Header -->
                    <div 
                        class="p-6 cursor-pointer hover:bg-gray-50 transition-colors"
                        @click="toggleCategory(index)"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                    <i class="fa-solid fa-chart-pie text-xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800">{{ category.name }}</h3>
                                    <p class="text-sm text-gray-600">{{ category.total_responses }} responses</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-blue-600">{{ category.average_score }}/5</p>
                                    <p class="text-sm text-gray-600">{{ category.percentage }}%</p>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-16 h-16 relative">
                                        <svg class="w-16 h-16 transform -rotate-90" viewBox="0 0 36 36">
                                            <path
                                                class="text-gray-200"
                                                stroke="currentColor"
                                                stroke-width="3"
                                                fill="none"
                                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                            />
                                            <path
                                                class="text-blue-500"
                                                stroke="currentColor"
                                                stroke-width="3"
                                                fill="none"
                                                stroke-dasharray="100, 100"
                                                :stroke-dashoffset="100 - category.percentage"
                                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                            />
                                        </svg>
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <span class="text-sm font-bold text-gray-700">{{ category.percentage }}%</span>
                                        </div>
                                    </div>
                                    <i 
                                        :class="[
                                            'fa-solid text-gray-400 text-xl ml-2 transition-transform',
                                            expandedCategories.includes(index) ? 'fa-chevron-up' : 'fa-chevron-down'
                                        ]"
                                    ></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Category Chart -->
                    <div v-if="expandedCategories.includes(index)" class="px-6 pb-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Category Overview Chart -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h4 class="text-lg font-semibold mb-4 text-gray-800">Category Overview</h4>
                                <apexchart 
                                    type="donut" 
                                    :options="getCategoryChartOptions(category)" 
                                    :series="getCategoryChartSeries(category)"
                                    height="300"
                                />
                            </div>

                            <!-- Category Stats -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h4 class="text-lg font-semibold mb-4 text-gray-800">Statistics</h4>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Average Score:</span>
                                        <span class="font-semibold text-blue-600">{{ category.average_score }}/5</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Percentage:</span>
                                        <span class="font-semibold text-blue-600">{{ category.percentage }}%</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Total Responses:</span>
                                        <span class="font-semibold text-gray-800">{{ category.total_responses }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">Total Surveys:</span>
                                        <span class="font-semibold text-gray-800">{{ category.total_surveys }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Questions Detail -->
                        <div class="mt-6">
                            <h4 class="text-lg font-semibold mb-4 text-gray-800">Questions Detail</h4>
                            <div class="space-y-4">
                                <div v-for="(question, qIndex) in category.questions" :key="qIndex" class="border rounded-lg p-4 bg-white">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex-1">
                                            <h5 class="font-medium text-gray-800 mb-2">{{ question.text }}</h5>
                                            <div class="flex items-center gap-4 text-sm text-gray-600">
                                                <span>Avg: {{ question.average_score }}/5</span>
                                                <span>Percentage: {{ question.percentage }}%</span>
                                                <span>Responses: {{ question.total_responses }}</span>
                                            </div>
                                        </div>
                                        <button 
                                            @click="toggleQuestion(index, qIndex)"
                                            class="text-blue-500 hover:text-blue-700 transition-colors"
                                        >
                                            <i :class="[
                                                'fa-solid',
                                                expandedQuestions[`${index}-${qIndex}`] ? 'fa-chevron-up' : 'fa-chevron-down'
                                            ]"></i>
                                        </button>
                                    </div>

                                    <!-- Question Chart -->
                                    <div v-if="expandedQuestions[`${index}-${qIndex}`]" class="mt-4">
                                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                            <div>
                                                <h6 class="text-sm font-medium text-gray-700 mb-2">Score Distribution</h6>
                                                <apexchart 
                                                    type="bar" 
                                                    :options="getQuestionChartOptions(question)" 
                                                    :series="getQuestionChartSeries(question)"
                                                    height="200"
                                                />
                                            </div>
                                            <div>
                                                <h6 class="text-sm font-medium text-gray-700 mb-2">Score Breakdown</h6>
                                                <div class="space-y-2">
                                                    <div v-for="score in [5, 4, 3, 2, 1]" :key="score" class="flex items-center justify-between">
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-sm text-gray-600">{{ score }}:</span>
                                                            <div class="w-20 bg-gray-200 rounded-full h-2">
                                                                <div 
                                                                    class="bg-blue-500 h-2 rounded-full transition-all duration-500"
                                                                    :style="{ width: getScorePercentage(question.score_distribution[score], question.total_responses) + '%' }"
                                                                ></div>
                                                            </div>
                                                        </div>
                                                        <span class="text-sm font-medium text-gray-800">
                                                            {{ question.score_distribution[score] }} ({{ getScorePercentage(question.score_distribution[score], question.total_responses) }}%)
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="categories.length === 0" class="text-center py-12">
                <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fa-solid fa-chart-bar text-gray-400 text-3xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Belum ada data survey</h3>
                <p class="text-gray-500 mb-6">Tidak ada survey yang tersedia untuk periode yang dipilih</p>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref, onMounted, nextTick, computed, watch } from 'vue'
import { debounce } from 'lodash'
import VueApexCharts from 'vue3-apexcharts'

const props = defineProps({
    categories: Array,
    totalSurveys: Number,
    totalResponses: Number,
    overallAverage: Number,
    overallPercentage: Number,
    filters: Object
})

// Filter states
const dateFrom = ref(props.filters?.date_from || '')
const dateTo = ref(props.filters?.date_to || '')

// Expanded states
const expandedCategories = ref([])
const expandedQuestions = ref({})

// Debounced search
const debouncedSearch = debounce(() => {
    router.get('/employee-survey-report', {
        date_from: dateFrom.value,
        date_to: dateTo.value,
    }, { preserveState: true, replace: true })
}, 400)

// Watch for changes
watch([dateFrom, dateTo], () => {
    debouncedSearch()
})

// Filter functions
function onDateInput() {
    // Validate date range
    if (dateFrom.value && dateTo.value && dateFrom.value > dateTo.value) {
        // Swap dates if from date is after to date
        const temp = dateFrom.value
        dateFrom.value = dateTo.value
        dateTo.value = temp
    }
    debouncedSearch()
}

function clearDateFilters() {
    dateFrom.value = ''
    dateTo.value = ''
    debouncedSearch()
}

function setDateRange(range) {
    const today = new Date()
    const todayStr = today.toISOString().split('T')[0]
    
    switch (range) {
        case 'today':
            dateFrom.value = todayStr
            dateTo.value = todayStr
            break
        case 'week':
            const weekAgo = new Date()
            weekAgo.setDate(weekAgo.getDate() - 7)
            dateFrom.value = weekAgo.toISOString().split('T')[0]
            dateTo.value = todayStr
            break
        case 'month':
            const monthAgo = new Date()
            monthAgo.setDate(monthAgo.getDate() - 30)
            dateFrom.value = monthAgo.toISOString().split('T')[0]
            dateTo.value = todayStr
            break
    }
    debouncedSearch()
}

// Toggle functions
function toggleCategory(index) {
    const categoryIndex = expandedCategories.value.indexOf(index)
    if (categoryIndex > -1) {
        expandedCategories.value.splice(categoryIndex, 1)
    } else {
        expandedCategories.value.push(index)
    }
}

function toggleQuestion(categoryIndex, questionIndex) {
    const key = `${categoryIndex}-${questionIndex}`
    expandedQuestions.value[key] = !expandedQuestions.value[key]
}

// ApexCharts options and series functions
function getCategoryChartOptions(category) {
    return {
        chart: {
            type: 'donut',
            height: 300,
            toolbar: { show: true },
            animations: { enabled: true, easing: 'easeinout', speed: 800 }
        },
        labels: ['Score', 'Remaining'],
        colors: ['#3B82F6', '#E5E7EB'],
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        name: {
                            show: true,
                            fontSize: '14px',
                            fontWeight: 600,
                            color: '#374151'
                        },
                        value: {
                            show: true,
                            fontSize: '16px',
                            fontWeight: 700,
                            color: '#1F2937',
                            formatter: function (val) {
                                return val + '%'
                            }
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            label: 'Total',
                            fontSize: '14px',
                            fontWeight: 600,
                            color: '#374151',
                            formatter: function (w) {
                                return w.globals.seriesTotals.reduce((a, b) => a + b, 0) + '%'
                            }
                        }
                    }
                }
            }
        },
        dataLabels: {
            enabled: false
        },
        legend: {
            position: 'bottom',
            fontSize: '14px',
            fontWeight: 600
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val + '%'
                }
            }
        }
    }
}

function getCategoryChartSeries(category) {
    return [category.percentage, 100 - category.percentage]
}

function getQuestionChartOptions(question) {
    return {
        chart: {
            type: 'bar',
            height: 200,
            toolbar: { show: true },
            animations: { enabled: true, easing: 'easeinout', speed: 800 }
        },
        xaxis: {
            categories: ['Score 5', 'Score 4', 'Score 3', 'Score 2', 'Score 1'],
            title: { text: 'Score' },
            labels: { 
                style: { fontWeight: 600 },
                rotate: -45
            }
        },
        yaxis: {
            title: { text: 'Responses' },
            min: 0,
            labels: { 
                style: { fontWeight: 600 },
                formatter: (value) => Math.round(value)
            }
        },
        colors: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#6B7280'],
        plotOptions: {
            bar: {
                borderRadius: 4,
                columnWidth: '60%'
            }
        },
        dataLabels: {
            enabled: false
        },
        legend: {
            show: false
        },
        grid: {
            borderColor: '#e5e7eb',
            strokeDashArray: 4
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val + ' responses'
                }
            }
        }
    }
}

function getQuestionChartSeries(question) {
    const scores = [5, 4, 3, 2, 1]
    const counts = scores.map(score => question.score_distribution[score])
    
    return [{
        name: 'Responses',
        data: counts
    }]
}

// Utility functions
function getScorePercentage(count, total) {
    if (total === 0) return 0
    return Math.round((count / total) * 100)
}

// Initialize charts for expanded categories
onMounted(() => {
    // Auto-expand first category if available
    if (props.categories.length > 0) {
        expandedCategories.value.push(0)
    }
})
</script>

<script>
export default {
    components: {
        apexchart: VueApexCharts
    }
}
</script>
