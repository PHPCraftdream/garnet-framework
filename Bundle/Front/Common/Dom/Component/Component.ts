import {DomEl} from '@common/Dom/El/DomEl';
import {DomObserver} from '@common/Dom/El/DomObserver';

const wrapDomElement = <T extends HTMLElement>(el: T) => new DomEl<T>(el);
const removeErrorElement = (item: DomEl<HTMLElement>) => item.remove();

/** Base class for DOM-backed components with lifecycle and element helpers. */
export class Component {
    /** Identifier for the registered removal callback. */
    protected removeHandlerId: string | null = null;

    /** Wrapped root element for the component. */
    protected mainDomEl: DomEl<HTMLElement>;

    /** Shared observer used to detect removal of the root element. */
    protected domObserver: DomObserver;

    /**
     * Creates a component for the provided root element and schedules initialization.
     *
     * @param mainElement Root DOM element owned by the component.
     */
    constructor(protected mainElement: HTMLElement) {
        this.mainDomEl = new DomEl(mainElement);
        this.domObserver = DomObserver.init() as DomObserver;
        this.removeHandlerId = this.domObserver.registerElementRemoval(mainElement, this.onRemove.bind(this));

        setTimeout(() => {
            this.init();
        }, 0);
    }

    /** Initializes the component after construction. */
    init() {}

    /** Called when the component's root element is removed. */
    onRemove() {}

    /** Returns the wrapped root DOM element. */
    getMainDomEl(): DomEl<HTMLElement> {
        return this.mainDomEl;
    }

    /** Applies a callback to the first descendant matching a selector. */
    apply = (selector: string, fn: (el: HTMLElement) => void) => {
        const el = this.mainElement.querySelector(selector) as HTMLElement | undefined;

        if (el) {
            fn(el);
        }
    };

    /** Finds and wraps a descendant matching a selector. */
    get = <T extends HTMLElement>(selector: string, container: HTMLElement = null): DomEl<T> | null => {
        const el = (container || this.mainElement).querySelector(selector) as unknown as T;

        return el ? new DomEl<T>(el) : null;
    };

    /** Finds and wraps all descendants matching a selector. */
    items = <T extends HTMLElement>(selector: string): DomEl<T>[] => {
        const items: T[] = Array.from(this.mainElement.querySelectorAll(selector) || []);

        return Array.from(items).map(wrapDomElement<T>);
    };

    /** Removes all form validation error elements inside the component. */
    clearErrors = () => {
        const errors = this.items('.garnet-form-error');
        errors.forEach(removeErrorElement);
    };
}
