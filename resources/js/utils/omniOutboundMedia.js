/**
 * Kompres gambar besar sebelum upload ke inbox (kurangi waktu kirim WA/IG).
 * Non-image files dikembalikan apa adanya.
 */
export async function compressImageFileForOutbound(
  file,
  { maxBytes = 1200 * 1024, maxDimension = 1600, quality = 0.82 } = {}
) {
  if (!(file instanceof File) || !file.type?.startsWith('image/')) {
    return file
  }
  if (file.size <= maxBytes) {
    return file
  }
  if (typeof createImageBitmap !== 'function' && typeof Image === 'undefined') {
    return file
  }

  try {
    const bitmap = await loadBitmap(file)
    const { width, height } = fitInside(bitmap.width, bitmap.height, maxDimension)
    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = height
    const ctx = canvas.getContext('2d')
    if (!ctx) {
      return file
    }
    ctx.drawImage(bitmap, 0, 0, width, height)
    if (typeof bitmap.close === 'function') {
      bitmap.close()
    }

    const blob = await canvasToBlob(canvas, 'image/jpeg', quality)
    if (!blob || blob.size >= file.size) {
      return file
    }

    const base = file.name.replace(/\.[^.]+$/, '') || 'image'
    return new File([blob], `${base}.jpg`, { type: 'image/jpeg', lastModified: Date.now() })
  } catch {
    return file
  }
}

function fitInside(w, h, maxDim) {
  if (w <= maxDim && h <= maxDim) {
    return { width: w, height: h }
  }
  const ratio = Math.min(maxDim / w, maxDim / h)
  return {
    width: Math.max(1, Math.round(w * ratio)),
    height: Math.max(1, Math.round(h * ratio)),
  }
}

async function loadBitmap(file) {
  if (typeof createImageBitmap === 'function') {
    return createImageBitmap(file)
  }
  const url = URL.createObjectURL(file)
  try {
    const img = await new Promise((resolve, reject) => {
      const el = new Image()
      el.onload = () => resolve(el)
      el.onerror = reject
      el.src = url
    })
    return img
  } finally {
    URL.revokeObjectURL(url)
  }
}

function canvasToBlob(canvas, type, quality) {
  return new Promise((resolve) => {
    canvas.toBlob((blob) => resolve(blob), type, quality)
  })
}
