import {initIntentTriggers} from '@common/Dom/Nav/IntentTriggers';
import {prefetchDocument} from './prefetchDocument';

const onIntent = (link: HTMLAnchorElement): void => {
    const key = link.getAttribute('href') || '';
    if (key) prefetchDocument(key);
};

const onCancelIntent = (): void => {};

/** Installs intent handlers that warm the document cache for eligible links. */
export const initDocumentPrefetchTriggers = (container: HTMLElement): (() => void) =>
    initIntentTriggers(container, {onIntent, onCancelIntent});
