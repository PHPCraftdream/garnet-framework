import {IDomObserver, IGarnetWindow, NodeHandler} from '@common/Models';
import {uid} from '@common/Utils/Str/Uid';

const w: IGarnetWindow = window as IGarnetWindow;

const ELEMENT_ID_PROP = '__GARNET_ELEMENT_ID__';

export interface IElementRemoveHandler {
    id: string;
    callback: () => void;
}

export class DomObserver implements IDomObserver {
    static init = (): IDomObserver => {
        if (!w.__GARNET_DOM_OBSERVER__) {
            w.__GARNET_DOM_OBSERVER__ = new DomObserver();
        }

        return w.__GARNET_DOM_OBSERVER__;
    }

    protected observer: MutationObserver;

    constructor() {
        this.observer = new MutationObserver(this.mutationHandler);
        this.observer.observe(document.body, {childList: true, subtree: true});
    }

    protected removeHandlers: Record<string, NodeHandler> = {};
    protected addHandlers: Record<string, NodeHandler> = {};
    protected addSelectors: string = '';

    protected elementRemoveHandlers: Map<string, IElementRemoveHandler> = new Map();

    public defineRemoveHandler = (name: string, handler: NodeHandler): void => {
        if (!this.removeHandlers[name]) {
            this.removeHandlers[name] = handler;
        }
    }

    public registerElementRemoval = (element: HTMLElement, callback: () => void): string => {
        const id = uid('el-rm');
        (element as any)[ELEMENT_ID_PROP] = id;
        this.elementRemoveHandlers.set(id, {id, callback});
        return id;
    }

    public unregisterElementRemoval = (id: string): void => {
        this.elementRemoveHandlers.delete(id);
    }

    public defineAddClassHandler = (className: string, handler: NodeHandler): void => {
        const newHandler = (element: HTMLElement): void => {
            element?.classList?.toggle(className, false);
            handler(element);
        };

        const elements = Array.from(document.getElementsByClassName(className)) as HTMLElement[];

        for (let element of elements) {
            newHandler(element);
        }

        this.addHandlers[className] = newHandler;
        this.addSelectors = Object.keys(this.addHandlers).map((s) => '.' + s).join(', ');
    }

    protected mutationHandler = (mutationsList: MutationRecord[]) => {
        for (let mutation of mutationsList) {
            if (mutation.type !== 'childList') {
                continue;
            }

            if (mutation.removedNodes.length > 0) {
                for (let removedNode of Array.from(mutation.removedNodes)) {
                    // A REPARENTED node (e.g. PageLoader.swapBody promoting the
                    // freshly-hydrated content out of its cross-fade layer into
                    // <body>) is reported as a removal even though it never left
                    // the document. Firing the removal handler here would unmount
                    // a live island and then re-hydrate it on the matching add —
                    // that flash is why the top bar sometimes vanished after a
                    // hot navigation. Only treat a node as removed once it is
                    // truly detached from the document.
                    if ((removedNode as Node).isConnected) {
                        continue;
                    }

                    const elementId = (removedNode as any)[ELEMENT_ID_PROP];
                    if (elementId && this.elementRemoveHandlers.has(elementId)) {
                        const handler = this.elementRemoveHandlers.get(elementId);
                        this.elementRemoveHandlers.delete(elementId);
                        handler?.callback();
                    }

                    for (let handler of Object.values(this.removeHandlers)) {
                        handler(removedNode as HTMLElement);
                    }
                }
            }

            if (mutation.addedNodes.length > 0) {
                document.querySelectorAll(this.addSelectors).forEach((element) => {
                    for (let [key, handler] of Object.entries(this.addHandlers)) {
                        if (element?.classList?.contains(key)) {
                            handler(element as HTMLElement);
                        }
                    }
                });
            }
        }
    };
}
