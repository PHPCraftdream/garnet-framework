import {
    TFormFileValue,
    TFromMap,
    TFromValue,
    TGetValueHandler,
    TOptionalCallback,
    TValidateCallback
} from '@common/Support/Models';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

const wrapDomElement = <T extends HTMLElement>(el: T) => new DomEl<T>(el);

/**
 * Wrapper around an element for fluent chaining of DOM operations.
 */
export class DomEl<T extends HTMLElement> {
    /** Wraps an element for fluent DOM operations. */
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

    /** Returns the wrapped element. */
    getEl(): T {
        return this.el;
    }

    /** Focuses the wrapped element. */
    protected _focus = () => {
        this.el.focus();
    }

    /** Focuses the element after a short delay. */
    focus(): DomEl<T> {
        setTimeout(this._focus, 450);

        return this;
    }

    /** Removes the wrapped element from its parent. */
    remove(): void {
        this.el.remove();
    }

    /** Tracks whether the wrapper considers the element hidden. */
    protected hidden: boolean = false;

    /** Reports whether the wrapper considers the element hidden. */
    isHidden = () => this.hidden;

    /** Sets visibility and synchronizes the `d-none` class. */
    toggle(visibility?: boolean): DomEl<T> {
        this.hidden = !visibility;
        this.el.classList.toggle('d-none', !visibility);

        return this;
    }

    /** Toggles one class and tracks `d-none` visibility. */
    toggleClass(className: string, force?: boolean) {
        this.el.classList.toggle(className, force);

        if (className.trim().toLowerCase() === 'd-none') {
            this.hidden = this.el.classList.contains(className);
        }

        return this;
    }

    /** Toggles each supplied class and tracks `d-none` visibility. */
    toggleClasses(classNames: readonly string[], force?: boolean) {
        for (let i = 0; i < classNames.length; i++) {
            const className = classNames[i];
            this.el.classList.toggle(className, force);

            if (className.trim().toLowerCase() === 'd-none') {
                this.hidden = this.el.classList.contains(className);
            }
        }

        return this;
    }

    /** Applies class toggles from a class-to-state map. */
    toggleClassesMap(classMap: Record<string, boolean | null>) {
        for (let [key, value] of Object.entries(classMap)) {
            this.el.classList.toggle(key, value);
        }

        return this;
    }

    //------------------------------------------------------------------------------------------------------------------

    /** Callback invoked when the element is disabled. */
    protected disableHandler: TOptionalCallback = null;

    /** Sets the callback invoked when disabling the element. */
    setDisableHandler = (handler: TOptionalCallback): DomEl<T> => {
        this.disableHandler = handler;

        return this;
    }

    /** Disables the element and invokes its callback. */
    disable(): DomEl<T> {
        this.el.setAttribute('disabled', 'true');
        this.disableHandler?.();

        return this;
    }

    //------------------------------------------------------------------------------------------------------------------

    /** Callback invoked when the element is enabled. */
    protected enableHandler: TOptionalCallback = null;

    /** Sets the callback invoked when enabling the element. */
    setEnableHandler = (handler: TOptionalCallback): DomEl<T> => {
        this.enableHandler = handler;

        return this;
    }

    /** Enables the element and invokes its callback. */
    enable(): DomEl<T> {
        this.el.removeAttribute('disabled');
        this.enableHandler?.();

        return this;
    }

    //------------------------------------------------------------------------------------------------------------------

    /** Returns the element HTML. */
    getHtml(): string {
        return this.el.innerHTML;
    }

    /** Replaces the element HTML. */
    setHtml(content: string): DomEl<T> {
        this.el.innerHTML = content;

        return this;
    }

    /** Returns the element text content. */
    text(): string {
        return this.el.textContent || '';
    }

    /** Replaces the element text content. */
    setText(content: string): DomEl<T> {
        this.el.textContent = content;

        return this;
    }

    /** Sets one element attribute. */
    setProp(prop: string, value: string): DomEl<T> {
        this.el.setAttribute(prop, value);

        return this;
    }

    /** Sets element attributes from a map. */
    setProps(props: Record<string, string>): DomEl<T> {
        for (let [key, value] of Object.entries(props)) {
            this.el.setAttribute(key, value);
        }

        return this;
    }

    /** Reads an element attribute. */
    getProp(prop: string): string | null {
        return this.el.getAttribute(prop);
    }

    /** Removes an element attribute. */
    removeProp(prop: string): DomEl<T> {
        this.el.removeAttribute(prop);

        return this;
    }

    //------------------------------------------------------------------------------------------------------------------

    /** Custom value reader, when configured. */
    protected getValueHandler: TGetValueHandler = null;

    /** Configures custom value retrieval. */
    setGetValueHandler = (handler: TGetValueHandler): DomEl<T> => {
        this.getValueHandler = handler;

        return this;
    }

    /** Reads the value using the configured handler or native input state. */
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

    /** Custom validation callback, when configured. */
    protected validateHandler: TValidateCallback = null;

    /** Configures custom validation. */
    setValidateHandler = (handler: TValidateCallback): DomEl<T> => {
        this.validateHandler = handler;

        return this;
    }

    /** Validates the element and appends a localized error on failure. */
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

    /** Sets the native input value. */
    setValue(value: string): DomEl<T> {
        const el = this.el as unknown as HTMLInputElement;
        el.value = value;

        return this;
    }

    /** Removes the adjacent validation message. */
    clearErrors = (): void => {
        const parent = this.el?.parentNode;
        const errorBlock = parent?.querySelector('.garnet-form-error');

        errorBlock?.remove();
    };

    /** Appends a validation message beside the element. */
    appendError(error: string): DomEl<T> {
        const parent = this.el?.parentNode;
        const errorBlock = document.createElement('div');

        errorBlock.classList.add('garnet-form-error', 'small', 'fs-7', 'text-danger');
        errorBlock.textContent = error;
        parent.append(errorBlock);

        return this;
    }

    //------------------------------------------------------------------------------------------------------------------

    /** Finds a descendant and wraps it, or returns null. */
    get = <U extends HTMLElement>(selector: string): DomEl<U> | null => {
        if (!this.el || typeof this.el?.querySelector !== 'function') {
            return null;
        }

        let el = this.el.querySelector(selector) as unknown as U;

        return el ? new DomEl<U>(el) : null;
    }

    /** Finds and wraps all matching descendants. */
    items = <U extends HTMLElement>(selector: string): DomEl<U>[] => {
        const items: U[] = Array.from(this.el.querySelectorAll(selector) || []);

        return Array.from(items).map(wrapDomElement<U>);
    };
}
