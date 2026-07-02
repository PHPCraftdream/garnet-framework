import {DomObserver} from '@common/Dom/DomObserver';
import {goTo} from '@common/Dom/Nav/GoTo';
import {hotClickInit} from '@common/Dom/Nav/HotClickInit';
import {createElement} from 'react';
import {createRoot} from 'react-dom/client';
import {GlobalToastRenderer, showToast} from '@common/Components/GlobalToast';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

// Dev-only quick-login panel. Loaded dynamically and ONLY when the
// backend explicitly marked this response as dev (__GARNET_IS_DEV__ === true).
// Without this gate the panel — including a `reset db` button — was
// shipping to production every build.
if ((window as unknown as { __GARNET_IS_DEV__?: boolean }).__GARNET_IS_DEV__) {
    import('./AuthDev');
}

// Mount the global toast renderer once (framework level). Idempotent: hot
// navigation preserves the existing #global-toast node across page swaps, so
// re-running this boot must NOT create a second root.
if (!document.getElementById('global-toast')) {
    const toastRoot = document.createElement('div');
    toastRoot.id = 'global-toast';
    document.body.appendChild(toastRoot);
    createRoot(toastRoot).render(createElement(GlobalToastRenderer));
}

// Surface backend errors as a toast instead of a silent "Uncaught (in promise)"
// in the console. Fires only for rejections that the call site didn't handle —
// handled errors (a caller's own catch/toast) never reach here, so no doubling.
// We act only on OUR request errors (RespError / ApiError carry a numeric
// `status` and/or a `response`), leaving unrelated rejections alone. Maintenance
// (503) is already toasted by the API layer, so it's skipped.
window.addEventListener('unhandledrejection', (event) => {
    const r = event.reason as
        | {name?: string; message?: string; status?: number; response?: {status?: number; maintenance?: boolean}}
        | null;
    if (!r || typeof r !== 'object') return;

    const isApiError = typeof r.status === 'number'
        || typeof r.response?.status === 'number'
        || r.name === 'ApiError'
        || r.name === 'RespError'
        || 'response' in r;
    if (!isApiError) return;

    if (r.status === 503 || r.response?.status === 503 || r.response?.maintenance) return;

    const msg = typeof r.message === 'string' ? r.message : '';
    showToast(msg && msg !== 'Common_RequestError' ? msg : I18nFramework.Common_RequestError(), 'danger');
});

const observer = DomObserver?.init();

observer?.defineAddClassHandler('hot-click-container-init', hotClickInit);

window.addEventListener('popstate', () => {
    goTo(window.location.href);
});
