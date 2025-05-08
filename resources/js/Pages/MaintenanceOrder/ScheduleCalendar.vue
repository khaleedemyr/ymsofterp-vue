<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto py-8">
      <h2 class="text-2xl font-bold mb-4 text-blue-800">Maintenance Task Schedule</h2>
      <!-- Tombol Screenshot & Print -->
      <div class="flex gap-3 mb-4">
        <button @click="takeScreenshot" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 shadow">
          <i class="fas fa-camera mr-2"></i> Screenshot
        </button>
        <button @click="printCalendar" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700 shadow">
          <i class="fas fa-print mr-2"></i> Print to PDF
        </button>
      </div>
      <!-- Legend -->
      <div class="flex flex-wrap gap-4 mb-4 items-center">
        <div class="flex items-center gap-2">
          <span class="inline-block w-4 h-4 rounded bg-red-400 border border-red-500"></span>
          <span class="text-sm">Overdue</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-block w-4 h-4 rounded bg-blue-500 border border-blue-700"></span>
          <span class="text-sm">In Progress</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-block w-4 h-4 rounded bg-yellow-400 border border-yellow-600"></span>
          <span class="text-sm">In Review</span>
        </div>
        <div class="flex items-center gap-2">
          <span class="inline-block w-4 h-4 rounded bg-green-400 border border-green-600"></span>
          <span class="text-sm">Other Status</span>
        </div>
      </div>
      <!-- Kalender dibungkus agar bisa di-screenshot/print -->
      <div ref="calendarWrapper">
        <div ref="calendarEl"></div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import html2canvas from 'html2canvas';

let calendar = null;
const calendarEl = ref(null);
const calendarWrapper = ref(null);

onMounted(async () => {
  document.title = 'Schedule Maintenance - YMSoft';
  // Load CSS FullCalendar via CDN
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = 'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/main.min.css';
  document.head.appendChild(link);
  const link2 = document.createElement('link');
  link2.rel = 'stylesheet';
  link2.href = 'https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.8/main.min.css';
  document.head.appendChild(link2);

  try {
    const res = await fetch('/maintenance-schedule');
    if (!res.ok) {
      throw new Error(`HTTP error! status: ${res.status}`);
    }
    const data = await res.json();
    const events = data.map(task => ({
      id: task.id,
      title: task.title,
      start: task.start,
      end: task.end,
      extendedProps: {
        assignees: task.assignees,
        status: task.status,
        label: task.label,
        label_color: task.label_color
      },
      color: task.color
    }));
    if (calendar) calendar.destroy();
    calendar = new Calendar(calendarEl.value, {
      plugins: [dayGridPlugin],
      initialView: 'dayGridMonth',
      height: 650,
      events,
      eventContent: function(arg) {
        // Custom render: title, label, assignees
        const titleEl = document.createElement('div');
        titleEl.innerHTML = arg.event.title;
        const frag = document.createDocumentFragment();
        frag.appendChild(titleEl);
        if (arg.event.extendedProps.label) {
          const labelEl = document.createElement('div');
          labelEl.innerHTML = arg.event.extendedProps.label;
          labelEl.style.background = arg.event.extendedProps.label_color || '#eee';
          labelEl.style.color = '#fff';
          labelEl.style.fontSize = '11px';
          labelEl.style.borderRadius = '4px';
          labelEl.style.padding = '0 4px';
          labelEl.style.marginTop = '2px';
          labelEl.style.display = 'inline-block';
          frag.appendChild(labelEl);
        }
        return { domNodes: [frag] };
      },
      eventDidMount: function(info) {
        const tooltip = `${info.event.title}<br>Kategori: ${info.event.extendedProps.label || '-'}<br>Assignee: ${info.event.extendedProps.assignees}<br>Status: ${info.event.extendedProps.status}`;
        info.el.setAttribute('title', '');
        info.el.onmouseenter = () => {
          showTooltip(info.el, tooltip);
        };
        info.el.onmouseleave = () => {
          hideTooltip();
        };
      },
    });
    calendar.render();
  } catch (e) {
    console.error('Gagal load kalender:', e);
    alert('Gagal load kalender: ' + e.message);
  }
});

// Screenshot
function takeScreenshot() {
  if (!calendarWrapper.value) return;
  html2canvas(calendarWrapper.value).then(canvas => {
    const link = document.createElement('a');
    link.download = 'maintenance-schedule.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
  });
}
// Print
function printCalendar() {
  window.print();
}

// Tooltip helpers
let tooltipDiv = null;
function showTooltip(target, html) {
  hideTooltip();
  tooltipDiv = document.createElement('div');
  tooltipDiv.innerHTML = html;
  tooltipDiv.className = 'fixed z-50 bg-white border border-blue-300 rounded shadow-lg px-3 py-2 text-sm text-blue-900';
  document.body.appendChild(tooltipDiv);
  const rect = target.getBoundingClientRect();
  tooltipDiv.style.left = rect.left + 'px';
  tooltipDiv.style.top = (rect.bottom + 8) + 'px';
}
function hideTooltip() {
  if (tooltipDiv) {
    tooltipDiv.remove();
    tooltipDiv = null;
  }
}
</script>

<style>
@media print {
  body * {
    visibility: hidden !important;
  }
  .max-w-7xl, .max-w-7xl * {
    visibility: visible !important;
  }
  .max-w-7xl {
    position: absolute !important;
    left: 0; top: 0; width: 100vw;
    background: white;
    box-shadow: none;
  }
  .btn, button, .sidebar, nav, .legend, .mb-4:not(:first-child) {
    display: none !important;
  }
}
</style> 