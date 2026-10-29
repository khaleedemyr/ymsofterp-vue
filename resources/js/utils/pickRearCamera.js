/**
 * Prefer back/rear camera from Html5Qrcode.getCameras() list.
 */
export function pickRearCameraId(cameras = []) {
  if (!Array.isArray(cameras) || !cameras.length) return '';

  const scored = cameras.map((cam, index) => {
    const label = String(cam.label || '').toLowerCase();
    let score = 0;
    if (/(back|rear|environment|belakang|world|posterior)/i.test(label)) score += 20;
    if (/(front|user|face|depan|anterior|selfie)/i.test(label)) score -= 20;
    // On many phones rear cam appears later in the list
    score += index;
    return { id: cam.id, score, label };
  });

  scored.sort((a, b) => b.score - a.score);
  return scored[0]?.id || cameras[0].id;
}
