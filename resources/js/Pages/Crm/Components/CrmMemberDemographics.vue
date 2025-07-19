<template>
  <div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
      <i class="fa-solid fa-chart-pie text-purple-500"></i>
      Demografi Member per Region
    </h3>
    <p class="text-sm text-gray-600 mb-4">
      Distribusi member berdasarkan region transaksi pertama
    </p>
    
    <div v-if="memberDemographics.length === 0" class="text-center py-8 text-gray-500">
      <i class="fa-solid fa-chart-pie text-4xl mb-4"></i>
      <p>Tidak ada data demografi member</p>
    </div>
    
    <div v-else>
      <!-- Chart Container -->
      <div id="memberDemographicsChart" class="w-full h-64"></div>
      
      <!-- Legend -->
      <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-2">
        <div
          v-for="(item, index) in memberDemographics"
          :key="index"
          class="flex items-center gap-2 text-sm"
        >
          <div
            class="w-3 h-3 rounded-full"
            :style="{ backgroundColor: chartColors[index % chartColors.length] }"
          ></div>
          <span class="font-medium">{{ item.region || 'Region Tidak Diketahui' }}</span>
          <span class="text-gray-500">({{ item.member_count }} member)</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import ApexCharts from 'apexcharts'

export default {
  name: 'CrmMemberDemographics',
  props: {
    memberDemographics: {
      type: Array,
      default: () => []
    }
  },
  data() {
    return {
      chart: null,
      chartColors: [
        '#3B82F6', // blue
        '#10B981', // emerald
        '#F59E0B', // amber
        '#EF4444', // red
        '#8B5CF6', // violet
        '#06B6D4', // cyan
        '#84CC16', // lime
        '#F97316', // orange
        '#EC4899', // pink
        '#6366F1', // indigo
      ]
    }
  },
  watch: {
    memberDemographics: {
      handler() {
        this.renderChart()
      },
      deep: true
    }
  },
  mounted() {
    this.renderChart()
  },
  beforeUnmount() {
    if (this.chart) {
      this.chart.destroy()
    }
  },
  methods: {
    renderChart() {
      if (this.memberDemographics.length === 0) return

      // Prepare chart data
      const series = this.memberDemographics.map(item => item.member_count)
      const labels = this.memberDemographics.map(item => item.region || 'Region Tidak Diketahui')
      const colors = this.memberDemographics.map((_, index) => this.chartColors[index % this.chartColors.length])

      // Calculate total for percentage
      const total = series.reduce((sum, value) => sum + value, 0)

      // Chart options
      const options = {
        series: series,
        chart: {
          type: 'pie',
          height: 250,
          toolbar: {
            show: false
          }
        },
        labels: labels,
        colors: colors,
        legend: {
          show: false
        },
        dataLabels: {
          enabled: true,
          formatter: function (val, opts) {
            const percentage = val.toFixed(1)
            const count = opts.w.globals.series[opts.seriesIndex]
            return `${percentage}%\n(${count})`
          },
          style: {
            fontSize: '12px',
            fontWeight: 'bold'
          },
          dropShadow: {
            enabled: true,
            opacity: 0.3,
            blur: 3,
            color: '#000'
          }
        },
        plotOptions: {
          pie: {
            donut: {
              size: '60%',
              labels: {
                show: true,
                total: {
                  show: true,
                  label: 'Total Member',
                  fontSize: '16px',
                  fontWeight: 'bold',
                  color: '#263238'
                },
                value: {
                  show: true,
                  fontSize: '20px',
                  fontWeight: 'bold',
                  color: '#263238'
                }
              }
            }
          }
        },
        tooltip: {
          y: {
            formatter: function (value) {
              const percentage = ((value / total) * 100).toFixed(1)
              return `${value} member (${percentage}%)`
            }
          }
        },
        responsive: [
          {
            breakpoint: 480,
            options: {
              chart: {
                height: 200
              },
              legend: {
                position: 'bottom'
              }
            }
          }
        ]
      }

      // Destroy existing chart if exists
      if (this.chart) {
        this.chart.destroy()
      }

      // Create new chart
      this.chart = new ApexCharts(document.querySelector('#memberDemographicsChart'), options)
      this.chart.render()
    }
  }
}
</script> 