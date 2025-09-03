import { ref } from 'vue'

export function useToast() {
  const toasts = ref([])

  const showToast = (message, type = 'info', duration = 3000) => {
    const id = Date.now()
    const toast = {
      id,
      message,
      type,
      duration
    }

    toasts.value.push(toast)

    // Auto remove after duration
    setTimeout(() => {
      removeToast(id)
    }, duration)

    return id
  }

  const removeToast = (id) => {
    const index = toasts.value.findIndex(toast => toast.id === id)
    if (index > -1) {
      toasts.value.splice(index, 1)
    }
  }

  const success = (message, duration) => showToast(message, 'success', duration)
  const error = (message, duration) => showToast(message, 'error', duration)
  const warning = (message, duration) => showToast(message, 'warning', duration)
  const info = (message, duration) => showToast(message, 'info', duration)

  return {
    toasts,
    showToast,
    removeToast,
    success,
    error,
    warning,
    info
  }
}
