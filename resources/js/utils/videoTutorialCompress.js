/** Selaras validasi Laravel: max:102400 (kilobyte) ≈ 100 MiB */
export const VIDEO_TUTORIAL_MAX_BYTES = 100 * 1024 * 1024;

/** Batas sumber untuk kompresi di browser (hindari OOM). */
const ABSOLUTE_MAX_SOURCE_BYTES = 900 * 1024 * 1024;

const CORE_VERSION = '0.12.10';
const CORE_BASE = `https://unpkg.com/@ffmpeg/core@${CORE_VERSION}/dist/esm`;

let ffmpegInstance = null;
let ffmpegLoadPromise = null;

function formatMb(bytes) {
    return (bytes / (1024 * 1024)).toFixed(1);
}

function inputSuffix(name) {
    const m = /\.(mp4|webm|avi|mov|mkv)$/i.exec(name || '');
    return m ? `.${m[1].toLowerCase()}` : '.mp4';
}

async function getLoadedFFmpeg() {
    if (ffmpegInstance?.loaded) {
        return ffmpegInstance;
    }
    if (ffmpegLoadPromise) {
        return ffmpegLoadPromise;
    }

    const { FFmpeg } = await import('@ffmpeg/ffmpeg');
    const { toBlobURL } = await import('@ffmpeg/util');

    ffmpegInstance = new FFmpeg();
    ffmpegLoadPromise = (async () => {
        await ffmpegInstance.load({
            coreURL: await toBlobURL(`${CORE_BASE}/ffmpeg-core.js`, 'text/javascript'),
            wasmURL: await toBlobURL(`${CORE_BASE}/ffmpeg-core.wasm`, 'application/wasm'),
        });
        return ffmpegInstance;
    })();

    return ffmpegLoadPromise;
}

/**
 * Jika file sudah di bawah maxBytes, dikembalikan apa adanya.
 * Jika lebih besar, dikompres di browser (ffmpeg.wasm) hingga ≤ maxBytes bila memungkinkan.
 *
 * @param {File} file
 * @param {{ maxBytes?: number, onProgress?: (info: { phase: string; message: string; ratio: number }) => void }} options
 * @returns {Promise<File>}
 */
export async function ensureVideoTutorialFileUnderMax(file, options = {}) {
    const maxBytes = options.maxBytes ?? VIDEO_TUTORIAL_MAX_BYTES;
    const onProgress = options.onProgress;

    if (file.size <= maxBytes) {
        return file;
    }

    if (file.size > ABSOLUTE_MAX_SOURCE_BYTES) {
        throw new Error(
            `Video terlalu besar (${formatMb(file.size)} MB). Untuk kompresi otomatis maksimal ${formatMb(ABSOLUTE_MAX_SOURCE_BYTES)} MB — silakan potong atau kompres di komputer Anda.`,
        );
    }

    const { fetchFile } = await import('@ffmpeg/util');
    const ffmpeg = await getLoadedFFmpeg();
    const id = `${Date.now()}_${Math.random().toString(36).slice(2, 9)}`;
    const inName = `in_${id}${inputSuffix(file.name)}`;

    onProgress?.({ phase: 'init', message: 'Memuat kompresor…', ratio: 0.02 });

    await ffmpeg.writeFile(inName, await fetchFile(file));

    onProgress?.({ phase: 'read', message: 'Mengompres video (browser)…', ratio: 0.08 });

    const presets = [
        { scale: '1280', crf: '26' },
        { scale: '1280', crf: '30' },
        { scale: '854', crf: '28' },
        { scale: '854', crf: '32' },
        { scale: '640', crf: '32' },
        { scale: '640', crf: '36' },
        { scale: '480', crf: '34' },
        { scale: '480', crf: '38' },
    ];

    let lastErr = null;
    const progressHandler = ({ progress }) => {
        const p = typeof progress === 'number' ? progress : 0;
        onProgress?.({
            phase: 'encode',
            message: 'Mengompres video…',
            ratio: Math.min(0.94, 0.08 + p * 0.86),
        });
    };
    ffmpeg.on('progress', progressHandler);

    try {
        for (let i = 0; i < presets.length; i++) {
            const { scale, crf } = presets[i];
            const outName = `out_${id}_${i}.mp4`;

            onProgress?.({
                phase: 'encode',
                message: `Mengompres… langkah ${i + 1}/${presets.length}`,
                ratio: 0.08 + (0.86 * i) / presets.length,
            });

            const code = await ffmpeg.exec([
                '-i',
                inName,
                '-vf',
                `scale='min(${scale},iw)':-2`,
                '-c:v',
                'libx264',
                '-crf',
                crf,
                '-preset',
                'veryfast',
                '-c:a',
                'aac',
                '-b:a',
                '96k',
                '-movflags',
                '+faststart',
                outName,
            ]);

            if (code !== 0) {
                lastErr = new Error(`Kompresi gagal (kode ${code}).`);
                try {
                    await ffmpeg.deleteFile(outName);
                } catch (_) {
                    /* ignore */
                }
                continue;
            }

            const data = await ffmpeg.readFile(outName);
            const u8 = data instanceof Uint8Array ? data : new Uint8Array(data);
            const blob = new Blob([u8], { type: 'video/mp4' });

            try {
                await ffmpeg.deleteFile(outName);
            } catch (_) {
                /* ignore */
            }

            if (blob.size <= maxBytes) {
                try {
                    await ffmpeg.deleteFile(inName);
                } catch (_) {
                    /* ignore */
                }
                const baseName = (file.name || 'video').replace(/\.[^.]+$/, '') || 'video';
                onProgress?.({ phase: 'done', message: 'Selesai', ratio: 1 });
                return new File([blob], `${baseName}-compressed.mp4`, {
                    type: 'video/mp4',
                    lastModified: Date.now(),
                });
            }
        }
    } finally {
        ffmpeg.off('progress', progressHandler);
        try {
            await ffmpeg.deleteFile(inName);
        } catch (_) {
            /* ignore */
        }
    }

    throw (
        lastErr ||
        new Error(
            `Setelah dikompres, video masih di atas ${formatMb(maxBytes)} MB. Coba video lebih pendek atau kompres manual.`,
        )
    );
}
