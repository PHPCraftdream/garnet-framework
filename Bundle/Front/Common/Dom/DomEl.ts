import {
    TFormFileValue,
    TFromMap,
    TFromValue,
    TGetValueHandler,
    TOptionalCallback,
    TValidateCallback
} from '@common/Models';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

/**
 * Wrapper around an element for fluent chaining of DOM operations.
 */
export class DomEl<T extends HTMLElement> {
    constructor(protected el: T) {

    }

    /**
     * Convenience method for short-circuit calls instead of an if. Example:
     *
     * this.commonErrors?.then((_, el) => {
     *      el.innerHTML += this.makeCommonErrorEl(error);
     * });
     *
     * @param handle
     */
    then = (handle: (domEl: DomEl<T>, el: T) => void): DomEl<T> => {
        handle(this, this.el);

        return this;
    };

    getEl(): T {
        return this.el;
    }

    protected _focus = () => {
        this.el.focus();
    }

    focus(): DomEl<T> {
        setTimeout(this._focus, 450);

        return this;
    }

    remove(): void {
        this.el.remove();
    }

    protected hidden: boolean = false;

    isHidden = () => this.hidden;

    toggle(visibility?: boolean): DomEl<T> {
        this.hidden = !visibility;
        this.el.classList.toggle('d-none', !visibility);

        return this;
    }

    toggleClass(className: string, force?: boolean) {
        this.el.classList.toggle(className, force);

        if (className.trim().toLowerCase() === 'd-none') {
            this.hidden = this.el.classList.contains(className);
        }

        return this;
    }

    toggleClasses(classNames: string[], force?: boolean) {
        for (let i = 0; i < classNames.length; i++) {
            const className = classNames[i];
            this.el.classList.toggle(className, force);

            if (className.trim().toLowerCase() === 'd-none') {
                this.hidden = this.el.classList.contains(className);
            }
        }

        return this;
    }

    toggleClassesMap(classMap: Record<string, boolean | null>) {
        for (let [key, value] of Object.entries(classMap)) {
            this.el.classList.toggle(key, value);
        }

        return this;
    }

    //------------------------------------------------------------------------------------------------------------------

    protected disableHandler: TOptionalCallback = null;

    setDisableHandler = (handler: TOptionalCallback): DomEl<T> => {
        this.disableHandler = handler;

        return this;
    }

    disable(): DomEl<T> {
        this.el.setAttribute('disabled', 'true');
        this.disableHandler?.();

        return this;
    }

    //------------------------------------------------------------------------------------------------------------------

    protected enableHandler: TOptionalCallback = null;

    setEnableHandler = (handler: TOptionalCallback): DomEl<T> => {
        this.enableHandler = handler;

        return this;
    }

    enable(): DomEl<T> {
        this.el.removeAttribute('disabled');
        this.enableHandler?.();

        return this;
    }

    //------------------------------------------------------------------------------------------------------------------

    getHtml(): string {
        return this.el.innerHTML;
    }

    setHtml(content: string): DomEl<T> {
        this.el.innerHTML = content;

        return this;
    }

    text(): string {
        return this.el.textContent || '';
    }

    setText(content: string): DomEl<T> {
        this.el.textContent = content;

        return this;
    }

    setProp(prop: string, value: string): DomEl<T> {
        this.el.setAttribute(prop, value);

        return this;
    }

    setProps(props: Record<string, string>): DomEl<T> {
        for (let [key, value] of Object.entries(props)) {
            this.el.setAttribute(key, value);
        }

        return this;
    }

    getProp(prop: string): string | null {
        return this.el.getAttribute(prop);
    }

    removeProp(prop: string): DomEl<T> {
        this.el.removeAttribute(prop);

        return this;
    }

    //------------------------------------------------------------------------------------------------------------------

    protected getValueHandler: TGetValueHandler = null;

    setGetValueHandler = (handler: TGetValueHandler): DomEl<T> => {
        this.getValueHandler = handler;

        return this;
    }

    getValue(resultObj?: TFromMap): TFromValue | TFormFileValue | null {
        if (this.getValueHandler) {
            return this.getValueHandler(resultObj);
        }

        const el = this.el as unknown as HTMLInputElement;

        if (el.type === 'checkbox') {
            return el.checked ? 1 : 0;
        }

        return el.value || null;
    }

    //------------------------------------------------------------------------------------------------------------------

    protected validateHandler: TValidateCallback = null;

    setValidateHandler = (handler: TValidateCallback): DomEl<T> => {
        this.validateHandler = handler;

        return this;
    }

    validate(): boolean | null {
        const el = this.el as unknown as HTMLInputElement;
        let value = el?.validity?.valid;
        let isErrorsAppended = false;

        if (this.validateHandler) {
            let handlerRes = this.validateHandler(el.value, el);

            value = handlerRes === true;

            if (typeof handlerRes === 'string') {
                this.appendError(handlerRes);
                isErrorsAppended = true;
            }
        }

        if (!isErrorsAppended && !value) {
            this.appendError(I18nFramework.Common_IncorrectValue());
        }

        return !!value;
    }

    //------------------------------------------------------------------------------------------------------------------

    setValue(value: string): DomEl<T> {
        const el = this.el as unknown as HTMLInputElement;
        el.value = value;

        return this;
    }

    clearErrors = (): void => {
        const parent = this.el?.parentNode;
        const errorBlock = parent?.querySelector('.garnet-form-error');

        errorBlock?.remove();
    };

    appendError(error: string): DomEl<T> {
        const parent = this.el?.parentNode;
        const errorBlock = document.createElement('div');

        errorBlock.classList.add('garnet-form-error', 'small', 'fs-7', 'text-danger');
        errorBlock.textContent = error;
        parent.append(errorBlock);

        return this;
    }

    //------------------------------------------------------------------------------------------------------------------

    get = <T extends HTMLElement>(selector: string): DomEl<T> | null => {
        if (!this.el || typeof this.el?.querySelector !== 'function') {
            return null;
        }

        let el = this.el.querySelector(selector) as unknown as T;

        return el ? new DomEl<T>(el) : null;
    }

    items = <T extends HTMLElement>(selector: string): DomEl<T>[] => {
        const items: T[] = Array.from(this.el.querySelectorAll(selector) || []);

        return Array.from(items).map((el) => new DomEl<T>(el));
    };
}
