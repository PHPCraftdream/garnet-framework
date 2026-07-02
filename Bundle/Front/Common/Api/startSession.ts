import {IGarnetWindow} from '@common/Models';

const w: IGarnetWindow = window as IGarnetWindow;

export interface StartSessionPayload {
    consent_pd: '1';
    consent_marketing?: '1';
}

export interface StartSessionResponse {
    success: true;
    csrf: string;
}

export const startSession = async (consentMarketing: boolean): Promise<string> => {
    const payload: StartSessionPayload & { action: 'start-session' } = {
        action: 'start-session',
        consent_pd: '1',
    };
    if (consentMarketing) payload.consent_marketing = '1';

    const res = await fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
    });
    if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body?.message || 'Consent request failed');
    }
    const body: StartSessionResponse = await res.json();
    (w as any).__GARNET_CSRF__ = body.csrf;
    return body.csrf;
};
