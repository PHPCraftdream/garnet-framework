import {PageEvents} from '@common/Utils/PageEvents';
import {ECommonEvents} from '@common/Enums';

export class PageLoader {
    static DEFAULT_TIMEOUT = 30000; // 30 seconds

    static loadScripts(resources: string[], timeout: number = PageLoader.DEFAULT_TIMEOUT): Promise<string[]> {
        if (!resources?.length) {
            return Promise.resolve([]);
        }

        const promises: Promise<string>[] = [];

        for (const resource of resources) {
            const scripts = document.scripts;
            let loaded = false;

            for (let i = 0; i < scripts.length; i++) {
                const loadedSrc: string = scripts[i].src;

                if (loadedSrc.includes(resource)) {
                    loaded = true;
                    break;
                }
            }

            if (loaded) {
                continue;
            }

            const promise = new Promise<string>((resolve, reject) => {
                const script = document.createElement('script');
                script.src = resource;

                const timeoutId = setTimeout(() => {
                    script.remove();
                    reject(new Error(`Timeout loading script: ${resource}`));
                }, timeout);

                script.onload = () => {
                    clearTimeout(timeoutId);
                    resolve(resource);
                };
                script.onerror = () => {
                    clearTimeout(timeoutId);
                    reject(new Error(`Failed to load script: ${resource}`));
                };
                document.head.appendChild(script);
            });

            promises.push(promise);
        }

        return Promise.all(promises);
    }

    static loadStyles(resources: string[], timeout: number = PageLoader.DEFAULT_TIMEOUT): Promise<string[]> {
        const promises: Promise<string>[] = [];

        if (!resources?.length) {
            return Promise.resolve([]);
        }

        const linkElements = Array.from(document.querySelectorAll('link[rel="stylesheet"][href]')) as HTMLElement[];
        const styles: string[] = [];

        for (let linkElement of linkElements) {
            styles.push(linkElement.getAttribute('href'));
        }

        for (const resource of resources) {
            let loaded = false;

            for (const loadedHref of styles) {
                if (loadedHref && loadedHref.includes(resource)) {
                    loaded = true;
                    break;
                }
            }

            if (loaded) {
                continue;
            }

            const promise = new Promise<string>((resolve, reject) => {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = resource;

                const timeoutId = setTimeout(() => {
                    link.remove();
                    reject(new Error(`Timeout loading style: ${resource}`));
                }, timeout);

                link.onload = () => {
                    clearTimeout(timeoutId);
                    resolve(resource);
                };
                link.onerror = () => {
                    clearTimeout(timeoutId);
                    reject(new Error(`Failed to load style: ${resource}`));
                };
                document.head.appendChild(link);
            });

            promises.push(promise);
        }

        return Promise.all(promises);
    }

    static updatePage(html: string): Promise<void> {
        // Single DOMParser pass: regex-extraction of <body>...</body> was
        // brittle (responses with multiline data-* attrs, escaped quotes, or
        // inline JSON containing `</script>` could yield an empty/truncated
        // body — that was the magic-link white-screen bug).
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        // ---- head: title + meta merge ------------------------------------
        const newTitle = doc.querySelector('title');
        if (newTitle) {
            document.title = newTitle.textContent ?? '';
        }

        const newMetas = Array.from(doc.head.querySelectorAll('meta[name]'));
        for (const meta of newMetas) {
            const name = meta.getAttribute('name');
            if (!name) {
                continue;
            }
            const existing = document.head.querySelector(`meta[name="${name}"]`) as HTMLMetaElement | null;
            if (existing) {
                existing.content = (meta as HTMLMetaElement).content;
            } else {
                document.head.appendChild(meta.cloneNode(true));
            }
        }

        // ---- collect external resources to load --------------------------
        const stylesToLoad: string[] = [];
        for (const link of Array.from(doc.querySelectorAll('link[rel="stylesheet"][href]'))) {
            const href = link.getAttribute('href');
            if (href) {
                stylesToLoad.push(href);
            }
        }

        const scriptsToLoad: string[] = [];
        for (const scriptEl of Array.from(doc.querySelectorAll('script[src]'))) {
            const src = scriptEl.getAttribute('src');
            if (src) {
                scriptsToLoad.push(src);
            }
        }

        // ---- head: inline <style id="..."> / <script id="..."> merge -----
        for (const style of Array.from(doc.querySelectorAll('style[id]'))) {
            if (!document.getElementById(style.id)) {
                document.head.appendChild(style.cloneNode(true));
            }
        }
        for (const scriptEl of Array.from(doc.querySelectorAll('script[id]:not([src])'))) {
            if (!document.getElementById(scriptEl.id)) {
                // Re-create via createElement so the browser executes it —
                // a cloned/innerHTML-inserted <script> stays inert.
                const fresh = document.createElement('script');
                for (const attr of Array.from(scriptEl.attributes)) {
                    fresh.setAttribute(attr.name, attr.value);
                }
                fresh.textContent = scriptEl.textContent;
                document.head.appendChild(fresh);
            }
        }

        return PageLoader.loadStyles(stylesToLoad)
            .then(() => PageLoader.swapBody(doc, scriptsToLoad));
    }

    /**
     * Build the new body content (everything except <script src>, loaded
     * separately) into a detached fragment. Inline <script> tags are re-created
     * via createElement so the browser executes them — a cloned/innerHTML
     * <script> stays inert.
     */
    private static buildBodyFragment(doc: Document): DocumentFragment {
        const frag = document.createDocumentFragment();

        for (const node of Array.from(doc.body.childNodes)) {
            if (node.nodeType === Node.ELEMENT_NODE && (node as Element).tagName === 'SCRIPT') {
                continue;
            }
            frag.appendChild(document.importNode(node, true));
        }

        for (const oldScript of Array.from(doc.body.querySelectorAll('script:not([src])'))) {
            const fresh = document.createElement('script');
            for (const attr of Array.from(oldScript.attributes)) {
                fresh.setAttribute(attr.name, attr.value);
            }
            fresh.textContent = oldScript.textContent;
            frag.appendChild(fresh);
        }

        return frag;
    }

    /**
     * Swap the page body without the blank flash + un-hydrated flicker.
     * The new content is mounted in a layer stacked OVER the old
     * one (absolute, opacity 0), given a couple of frames to render and let its
     * islands hydrate, then cross-faded in — only after which the old content
     * is dropped. Falls back to a plain replacement when motion is reduced or
     * anything goes wrong.
     */
    private static swapBody(doc: Document, scriptsToLoad: string[]): Promise<void> {
        const body = document.body;

        // The toast root is created once at boot (Framework.ts) and is NOT part
        // of any page's server HTML — keep it across the swap so toasts (incl.
        // navigation-error toasts) keep working after a hot navigation.
        const persistent = document.getElementById('global-toast');
        const keep = (n: ChildNode): boolean => n === persistent;

        const emitReloaded = (): Promise<void> =>
            new Promise<void>((resolve) => {
                requestAnimationFrame(() => {
                    PageEvents.init().emmit(ECommonEvents.PAGE_RELOADED, null);
                    resolve();
                });
            });

        // Plain replacement (reduced-motion / fallback path).
        const directSwap = (): Promise<void> => {
            for (const n of Array.from(body.childNodes)) {
                if (!keep(n)) {
                    body.removeChild(n);
                }
            }
            body.appendChild(PageLoader.buildBodyFragment(doc));
            // Clear the click-time "leaving" dim so the new content isn't faded.
            body.classList.remove('page-leaving', 'page-swapping');
            return PageLoader.loadScripts(scriptsToLoad).then(emitReloaded);
        };

        const prefersReduce = typeof window !== 'undefined'
            && typeof window.matchMedia === 'function'
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (prefersReduce) {
            return directSwap();
        }

        try {
            // The old content to drop after the fade — everything except the
            // persistent toast root (kept so toasts survive the navigation).
            const outgoing = Array.from(body.childNodes).filter((n) => !keep(n));

            // Incoming layer: stacked over the old content, invisible until it
            // has rendered + hydrated.
            const incoming = document.createElement('div');
            incoming.className = 'page-swap-incoming';
            incoming.appendChild(PageLoader.buildBodyFragment(doc));

            body.classList.add('page-swapping');
            body.appendChild(incoming);

            return PageLoader.loadScripts(scriptsToLoad)
                .then(emitReloaded)
                .then(() => new Promise<void>((resolve) => {
                    let done = false;
                    const finalize = () => {
                        if (done) return;
                        done = true;
                        incoming.removeEventListener('transitionend', onEnd);
                        // Drop the old content, promote the new one into normal flow.
                        for (const n of outgoing) {
                            if (n.parentNode === body) {
                                body.removeChild(n);
                            }
                        }
                        while (incoming.firstChild) {
                            body.insertBefore(incoming.firstChild, incoming);
                        }
                        if (incoming.parentNode === body) {
                            body.removeChild(incoming);
                        }
                        body.classList.remove('page-swapping', 'page-leaving');
                        try { window.scrollTo(0, 0); } catch { /* noop */ }
                        resolve();
                    };
                    const onEnd = (e: TransitionEvent) => {
                        if (e.target === incoming && e.propertyName === 'opacity') {
                            finalize();
                        }
                    };

                    // Two frames: let the new islands mount before we reveal.
                    requestAnimationFrame(() => requestAnimationFrame(() => {
                        incoming.addEventListener('transitionend', onEnd);
                        incoming.classList.add('page-swap-in');
                        // Safety net if transitionend never fires.
                        setTimeout(finalize, 600);
                    }));
                }));
        } catch {
            return directSwap();
        }
    }
}
