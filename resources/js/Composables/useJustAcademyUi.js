import Swal from 'sweetalert2';
import { router, usePage } from '@inertiajs/vue3';
import { onMounted, watch } from 'vue';

const swalDefaults = {
  buttonsStyling: false,
  customClass: {
    popup: 'ja-swal-popup',
    title: 'ja-swal-title',
    htmlContainer: 'ja-swal-text',
    confirmButton: 'ja-swal-confirm',
    cancelButton: 'ja-swal-cancel',
  },
};

export const jaUi = {
  page: 'min-h-full bg-gradient-to-br from-slate-50 via-white to-indigo-50/50',
  container: 'max-w-[100rem] w-full mx-auto px-4 sm:px-6 lg:px-8 py-8',
  containerNarrow: 'max-w-4xl mx-auto px-4 sm:px-6 py-8',
  header: 'mb-8',
  title: 'text-2xl sm:text-3xl font-bold text-slate-800 tracking-tight',
  subtitle: 'text-sm text-slate-500 mt-1',
  iconBadge: 'flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 text-lg text-white shadow-lg shadow-indigo-500/25',
  card: 'rounded-2xl border border-slate-200/70 bg-white/90 shadow-sm shadow-slate-200/60 backdrop-blur-sm',
  cardBody: 'p-6',
  tableWrap: 'overflow-hidden rounded-2xl border border-slate-200/70 bg-white/90 shadow-sm shadow-slate-200/60 backdrop-blur-sm',
  table: 'min-w-full text-sm',
  thead: 'bg-slate-50/90 text-left text-xs font-semibold uppercase tracking-wide text-slate-500',
  th: 'px-5 py-3.5',
  tr: 'border-t border-slate-100 transition-colors hover:bg-indigo-50/30',
  td: 'px-5 py-3.5 text-slate-700',
  input: 'w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm placeholder:text-slate-400 transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100',
  select: 'rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-sm transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100',
  search: 'max-w-md rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm shadow-sm transition focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100',
  btnPrimary: 'inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-500/25 transition hover:from-indigo-700 hover:to-violet-700 hover:shadow-lg disabled:opacity-60',
  btnSecondary: 'inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50',
  btnDanger: 'text-sm font-medium text-rose-600 transition hover:text-rose-700',
  btnLink: 'text-sm font-medium text-indigo-600 transition hover:text-indigo-800',
  btnSuccess: 'inline-flex items-center justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700',
  badgeActive: 'inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700',
  badgeInactive: 'inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500',
  empty: 'px-4 py-12 text-center text-sm text-slate-500',
  modalOverlay: 'fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-sm',
  modal: 'w-full max-w-md space-y-4 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xl',
  label: 'mb-1.5 block text-sm font-medium text-slate-700',
};

export function jaConfirmDelete(options = {}) {
  const {
    title = 'Hapus data?',
    text = 'Tindakan ini tidak dapat dibatalkan.',
    html,
    confirmText = 'Ya, hapus',
  } = options;

  return Swal.fire({
    ...swalDefaults,
    icon: 'warning',
    title,
    text: html ? undefined : text,
    html,
    showCancelButton: true,
    confirmButtonText: confirmText,
    cancelButtonText: 'Batal',
    reverseButtons: true,
    focusCancel: true,
  });
}

export function jaConfirm(options = {}) {
  return Swal.fire({
    ...swalDefaults,
    icon: options.icon || 'question',
    title: options.title,
    text: options.text,
    html: options.html,
    showCancelButton: true,
    confirmButtonText: options.confirmText || 'Ya',
    cancelButtonText: options.cancelText || 'Batal',
    reverseButtons: true,
  });
}

export function jaToastSuccess(message, title = 'Berhasil') {
  return Swal.fire({
    ...swalDefaults,
    icon: 'success',
    title,
    text: message,
    timer: 2200,
    timerProgressBar: true,
    showConfirmButton: false,
    toast: true,
    position: 'top-end',
  });
}

export function jaToastError(message, title = 'Gagal') {
  return Swal.fire({
    ...swalDefaults,
    icon: 'error',
    title,
    text: message,
    confirmButtonText: 'OK',
  });
}

export function jaFormErrors(errors) {
  const message = Object.values(errors || {}).flat().join('\n');
  if (message) jaToastError(message);
}

/** Hapus record via Inertia — pakai ini, jangan useForm().delete() di dalam callback */
export function jaDelete(url, options = {}) {
  const { successMessage, onSuccess } = options;

  return router.delete(url, {
    preserveScroll: true,
    onSuccess: () => {
      if (successMessage) jaToastSuccess(successMessage);
      onSuccess?.();
    },
    onError: (errors) => jaFormErrors(errors),
  });
}

/** Dialog progress (kompresi / upload) — kembalikan { setProgress, setMessage, close } */
export function jaOpenProgressDialog(title, subtitle = 'Mohon tunggu…') {
  const uid = `ja-progress-${Date.now()}`;
  Swal.fire({
    ...swalDefaults,
    title,
    html: `
      <p id="${uid}-msg" class="text-sm text-slate-600 mb-3">${subtitle}</p>
      <div class="w-full rounded-full bg-slate-200 h-2.5 overflow-hidden">
        <div id="${uid}-bar" class="h-2.5 rounded-full bg-gradient-to-r from-indigo-500 to-violet-600 transition-all duration-200" style="width:0%"></div>
      </div>
      <p id="${uid}-pct" class="mt-2 text-xs font-medium text-slate-500">0%</p>
    `,
    allowOutsideClick: false,
    allowEscapeKey: false,
    showConfirmButton: false,
  });

  return {
    setProgress(percent, message) {
      const bar = document.getElementById(`${uid}-bar`);
      const pct = document.getElementById(`${uid}-pct`);
      const msg = document.getElementById(`${uid}-msg`);
      const p = Math.min(100, Math.max(0, Math.round(percent)));
      if (bar) bar.style.width = `${p}%`;
      if (pct) pct.textContent = `${p}%`;
      if (message && msg) msg.textContent = message;
    },
    close: () => Swal.close(),
  };
}

export function jaHandleUploadError(error) {
  const status = error?.response?.status;
  if (status === 413) {
    jaToastError(
      'File terlalu besar untuk server (413). Untuk video, sistem akan mengompres otomatis — pastikan hasil kompresi di bawah 100 MB, atau gunakan field URL.',
      'Upload gagal',
    );
    return;
  }
  if (status === 422 && error.response?.data?.errors) {
    jaFormErrors(error.response.data.errors);
    return;
  }
  jaToastError(error?.response?.data?.message || error?.message || 'Upload gagal');
}

export function useJustAcademyFlash() {
  const page = usePage();

  const handleFlash = (flash) => {
    if (!flash) return;
    if (flash.success) jaToastSuccess(flash.success);
    if (flash.error) jaToastError(flash.error);
  };

  const handleErrors = (errors) => {
    if (errors && Object.keys(errors).length > 0) {
      jaFormErrors(errors);
    }
  };

  onMounted(() => {
    handleFlash(page.props.flash);
    handleErrors(page.props.errors);
  });

  watch(() => page.props.flash, handleFlash, { deep: true });
  watch(() => page.props.errors, handleErrors, { deep: true });
}
