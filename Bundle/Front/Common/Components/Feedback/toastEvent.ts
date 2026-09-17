/**
 * Toast event contract — intentionally React-free.
 *
 * The low-level API layer (asyncJsonThen / sendPostFormData / …) needs to raise
 * toasts on a 503, but it must NOT pull a React component module into its
 * chunk: importing GlobalToast.tsx from the API layer dragged React/JSX into
 * the deepest shared chunk and broke island hydration on the auth/registration
 * pages with a minified "element type is undefined" (React #130). Keeping just
 * the event name + payload type here lets both sides agree without GlobalToast.
 */
export type ToastType = 'primary' | 'success' | 'danger' | 'warning';

export const TOAST_EVENT = 'garnet:toast';

export interface ToastEventDetail {
    message: string;
    type?: ToastType;
}
