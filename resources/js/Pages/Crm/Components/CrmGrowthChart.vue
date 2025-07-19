<script setup>
import { ref, onMounted, watch } from 'vue';

const props = defineProps({
  data: Array,
});

const chartContainer = ref(null);
let chart = null;

onMounted(() => {
  if (typeof ApexCharts !== 'undefined') {
    createChart();
  } else {
    // Load ApexCharts if not available
    const script = document.createElement('script');
    script.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
    script.onload = createChart;
    document.head.appendChild(script);
  }
});

watch(() => props.data, () => {
  if (chart && props.data) {
    chart.updateSeries([
      {
        name: 'Member Baru',
        data: props.data.map(item => item.newMembers)
      },
      {
        name: 'Total Member',
        data: props.data.map(item => item.totalMembers)
      }
    ]);
  }
}, { deep: true });

function createChart() {
  if (!props.data || !chartContainer.value) return;

  const options = {
    series: [
      {
        name: 'Member Baru',
        data: props.data.map(item => item.newMembers)
      },
      {
        name: 'Total Member',
        data: props.data.map(item => item.totalMembers)
      }
    ],
    chart: {
      type: 'line',
      height: 350,
      toolbar: {
        show: false
      },
      animations: {
        enabled: true,
        easing: 'easeinout',
        speed: 800,
        animateGradually: {
          enabled: true,
          delay: 150
        },
        dynamicAnimation: {
          enabled: true,
          speed: 350
        }
      }
    },
    colors: ['#8b5cf6', '#3b82f6'],
    stroke: {
      curve: 'smooth',
      width: [2, 3]
    },
    grid: {
      borderColor: '#e2e8f0',
      strokeDashArray: 4,
    },
    xaxis: {
      categories: props.data.map(item => item.month),
      labels: {
        style: {
          colors: '#6b7280',
          fontSize: '12px'
        }
      }
    },
    yaxis: [
      {
        title: {
          text: 'Member Baru',
          style: {
            color: '#8b5cf6',
            fontSize: '14px',
            fontWeight: 600
          }
        },
        labels: {
          style: {
            colors: '#6b7280',
            fontSize: '12px'
          },
          formatter: function(value) {
            return value.toLocaleString('id-ID');
          }
        }
      },
      {
        opposite: true,
        title: {
          text: 'Total Member',
          style: {
            color: '#3b82f6',
            fontSize: '14px',
            fontWeight: 600
          }
        },
        labels: {
          style: {
            colors: '#6b7280',
            fontSize: '12px'
          },
          formatter: function(value) {
            return value.toLocaleString('id-ID');
          }
        }
      }
    ],
    tooltip: {
      shared: true,
      intersect: false,
      y: {
        formatter: function(value) {
          return value.toLocaleString('id-ID');
        }
      }
    },
    legend: {
      position: 'top',
      horizontalAlign: 'right',
      fontSize: '14px',
      markers: {
        radius: 12
      }
    },
    dataLabels: {
      enabled: false
    },
    markers: {
      size: 5,
      hover: {
        size: 7
      }
    }
  };

  chart = new ApexCharts(chartContainer.value, options);
  chart.render();
}
</script>

<template>
  <div>
    <div ref="chartContainer" class="w-full h-80"></div>
    <div class="mt-4 grid grid-cols-2 gap-4 text-center">
      <div class="bg-purple-50 rounded-lg p-3">
        <p class="text-sm text-purple-600 font-medium">Rata-rata Member Baru/Bulan</p>
        <p class="text-lg font-bold text-purple-800">
          {{ data.length > 0 ? Math.round(data.reduce((sum, item) => sum + item.newMembers, 0) / data.length) : 0 }}
        </p>
      </div>
      <div class="bg-blue-50 rounded-lg p-3">
        <p class="text-sm text-blue-600 font-medium">Total Member Saat Ini</p>
        <p class="text-lg font-bold text-blue-800">
          {{ data.length > 0 ? data[data.length - 1].totalMembers.toLocaleString('id-ID') : 0 }}
        </p>
      </div>
    </div>
  </div>
</template> 