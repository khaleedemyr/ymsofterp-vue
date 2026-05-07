const siteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY;

let recaptchaScriptPromise = null;

function ensureScriptLoaded() {
    if (!siteKey) {
        return Promise.resolve();
    }

    if (window.grecaptcha?.ready) {
        return Promise.resolve();
    }

    if (!recaptchaScriptPromise) {
        recaptchaScriptPromise = new Promise((resolve, reject) => {
            const existingScript = document.querySelector('script[data-recaptcha="true"]');
            if (existingScript) {
                existingScript.addEventListener('load', () => resolve(), { once: true });
                existingScript.addEventListener('error', () => reject(new Error('Failed to load reCAPTCHA script.')), { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = `https://www.google.com/recaptcha/api.js?render=${siteKey}`;
            script.async = true;
            script.defer = true;
            script.dataset.recaptcha = 'true';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Failed to load reCAPTCHA script.'));
            document.head.appendChild(script);
        });
    }

    return recaptchaScriptPromise;
}

export async function getRecaptchaToken(action) {
    if (!siteKey) {
        return null;
    }

    await ensureScriptLoaded();

    return new Promise((resolve, reject) => {
        window.grecaptcha.ready(async () => {
            try {
                const token = await window.grecaptcha.execute(siteKey, { action });
                resolve(token);
            } catch (error) {
                reject(error);
            }
        });
    });
}

export function isRecaptchaEnabled() {
    return Boolean(siteKey);
}
