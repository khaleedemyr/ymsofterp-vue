const siteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY;

let recaptchaScriptPromise = null;

function waitForGrecaptcha(timeoutMs = 10000) {
    return new Promise((resolve, reject) => {
        const started = Date.now();

        const check = () => {
            if (window.grecaptcha && typeof window.grecaptcha.ready === 'function') {
                resolve(window.grecaptcha);
                return;
            }

            if (Date.now() - started >= timeoutMs) {
                reject(new Error('reCAPTCHA tidak siap. Periksa koneksi ke Google atau domain site key.'));
                return;
            }

            window.setTimeout(check, 50);
        };

        check();
    });
}

function appendRecaptchaScript(src) {
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.defer = true;
    script.dataset.recaptcha = 'true';
    document.head.appendChild(script);
    return script;
}

function ensureScriptLoaded() {
    if (!siteKey) {
        return Promise.resolve(null);
    }

    if (window.grecaptcha && typeof window.grecaptcha.ready === 'function') {
        return Promise.resolve(window.grecaptcha);
    }

    if (!recaptchaScriptPromise) {
        recaptchaScriptPromise = new Promise((resolve, reject) => {
            let settled = false;

            const finishOk = async () => {
                if (settled) return;
                settled = true;
                try {
                    resolve(await waitForGrecaptcha());
                } catch (error) {
                    reject(error);
                }
            };

            const finishErr = (error) => {
                if (settled) return;
                settled = true;
                reject(error instanceof Error ? error : new Error('Failed to load reCAPTCHA script.'));
            };

            const bindScript = (script, { allowFallback = false } = {}) => {
                script.addEventListener('load', () => {
                    script.dataset.recaptchaState = 'loaded';
                    finishOk();
                }, { once: true });

                script.addEventListener('error', () => {
                    script.dataset.recaptchaState = 'error';
                    if (!allowFallback) {
                        finishErr(new Error('Failed to load reCAPTCHA script.'));
                        return;
                    }

                    const fallback = appendRecaptchaScript(
                        `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`,
                    );
                    bindScript(fallback, { allowFallback: false });
                }, { once: true });

                if (script.dataset.recaptchaState === 'loaded') {
                    finishOk();
                } else if (script.dataset.recaptchaState === 'error' && !allowFallback) {
                    finishErr(new Error('Failed to load reCAPTCHA script.'));
                }
            };

            const existingScript = document.querySelector('script[data-recaptcha="true"]');
            if (existingScript) {
                if (window.grecaptcha && typeof window.grecaptcha.ready === 'function') {
                    finishOk();
                    return;
                }
                bindScript(existingScript, { allowFallback: false });
                return;
            }

            const primary = appendRecaptchaScript(
                `https://www.recaptcha.net/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`,
            );
            bindScript(primary, { allowFallback: true });
        }).catch((error) => {
            recaptchaScriptPromise = null;
            throw error;
        });
    }

    return recaptchaScriptPromise;
}

/**
 * Preload script sedini mungkin (mis. saat modal daftar dibuka).
 */
export function preloadRecaptcha() {
    if (!siteKey) {
        return Promise.resolve(null);
    }

    return ensureScriptLoaded().catch(() => null);
}

export async function getRecaptchaToken(action) {
    if (!siteKey) {
        return null;
    }

    const grecaptcha = await ensureScriptLoaded();
    if (!grecaptcha || typeof grecaptcha.ready !== 'function') {
        throw new Error('reCAPTCHA belum tersedia di halaman ini.');
    }

    return new Promise((resolve, reject) => {
        try {
            grecaptcha.ready(async () => {
                try {
                    const token = await grecaptcha.execute(siteKey, { action: action || 'submit' });
                    if (!token) {
                        reject(new Error('Token reCAPTCHA kosong.'));
                        return;
                    }
                    resolve(token);
                } catch (error) {
                    reject(error);
                }
            });
        } catch (error) {
            reject(error);
        }
    });
}

export function isRecaptchaEnabled() {
    return Boolean(siteKey);
}
