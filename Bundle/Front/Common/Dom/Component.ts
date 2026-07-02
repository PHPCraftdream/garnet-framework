import {DomEl} from '@common/Dom/DomEl';
import {DomObserver} from '@common/Dom/DomObserver';

type FilterNonNullable<T> = T extends [infer H, ...infer R]
    ? [NonNullable<H>, ...FilterNonNullable<R>]
    : [];
type TTupleFunc<T extends any[]> = (...args: T) => void;
type TEachFunc<T extends any[]> = <Args extends T>(...args: Args) => (func: TTupleFunc<FilterNonNullable<Args>>) => void;

export class Component {
    protected removeHandlerId: string | null = null;
    protected mainDomEl: DomEl<HTMLElement>;
    protected domObserver: DomObserver;

    constructor(protected mainElement: HTMLElement) {
        this.mainDomEl = new DomEl(mainElement);
        this.domObserver = DomObserver.init() as DomObserver;

        this.removeHandlerId = this.domObserver.registerElementRemoval(mainElement, this.onRemove.bind(this));

        setTimeout(() => {
            this.init();
        }, 0);
    }

    init() {

    }

    onRemove() {

    }

    getMainDomEl(): DomEl<HTMLElement> {
        return this.mainDomEl;
    }

    apply = (selector: string, fn: (el: HTMLElement) => void) => {
        const el = this.mainElement.querySelector(selector) as HTMLElement | undefined;

        if (el) {
            fn(el);
        }
    }

    get = <T extends HTMLElement>(selector: string, container: HTMLElement = null): DomEl<T> | null => {
        let el = (container || this.mainElement).querySelector(selector) as unknown as T;

        return el ? new DomEl<T>(el) : null;
    }

    items = <T extends HTMLElement>(selector: string): DomEl<T>[] => {
        const items: T[] = Array.from(this.mainElement.querySelectorAll(selector) || []);

        return Array.from(items).map((el) => new DomEl<T>(el));
    };

    clearErrors = () => {
        const errors = this.items('.garnet-form-error');
        errors.forEach(item => item.remove());
    };
}
