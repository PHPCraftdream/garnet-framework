import * as React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {DomObserver} from '@common/Dom/DomObserver';
import {ErrorBoundary} from '@common/Components/ErrorBoundary';

type LazyImport<P> = () => Promise<{ default: React.ComponentType<P> } | { [key: string]: React.ComponentType<P> }>;

/**
 * Register a React island that mounts LAZILY when a DOM element with the given class appears.
 *
 * The JS chunk is loaded ONLY when DomObserver detects the element — nothing is bundled upfront.
 * Every island is wrapped in ErrorBoundary for crash resilience.
 *
 * Usage:
 *   createIsland({className: 'my-init', lazy: () => import('./MyIsland'), exportName: 'MyIsland'})
 */
export function createIsland<P>(config: {
    className: string;
    lazy: LazyImport<P>;
    exportName?: string;
}): void {
    const observer = DomObserver.init();

    observer.defineAddClassHandler(config.className, (element: HTMLElement) => {
        let props = {} as P;

        const propsAttr = element.getAttribute('data-props');
        if (propsAttr) {
            try {
                props = JSON.parse(propsAttr) as P;
            } catch {
                // ignore parse errors
            }
        }

        config.lazy().then((mod) => {
            const exportName = config.exportName || 'default';
            const Component = (mod as any)[exportName] || (mod as any).default || Object.values(mod)[0];
            if (Component) {
                const root: Root = createRoot(element);
                root.render(<ErrorBoundary><Component {...props} /></ErrorBoundary>);
                observer.registerElementRemoval(element, () => {
                    root.unmount();
                });
            }
        });
    });
}
