import {makeFormData} from '@common/Api/Send/makeFormData';
import {Component} from '@common/Dom/Component/Component';
import {DomEl} from '@common/Dom/El/DomEl';
import {updateProgress} from '@common/Dom/Progress/updateProgress';
import {sendPostFormData} from '@common/Api/Send/sendPostFormData';
import {TFormErrors, TFromMap} from '@common/Support/Models';
import {escapeHtml} from '@common/Utils/Str/EscapeHtml';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

/** Identifies form validation responses in an API result. */
const isFormErrors = (data: any): data is TFormErrors => {
    return !!data?.errors || !!data?.commonErrors;
}

/** Delays invoking a function until calls have stopped for the given interval. */
function debounce<T extends (...args: any[]) => any>(fn: T, delay: number): T {
    let timeoutId: ReturnType<typeof setTimeout> | null = null;

    return ((...args: Parameters<T>) => {
        if (timeoutId) {
            clearTimeout(timeoutId);
        }

        timeoutId = setTimeout(() => {
            fn(...args);
            timeoutId = null;
        }, delay);
    }) as T;
}

const hasFieldError = (field: DomEl<any>) => {
    const parent = field.getEl()?.parentNode;
    return parent?.querySelector('.garnet-form-error');
};

/** Removes one error alert element. */
const removeErrorAlert = (error: Element) => error.remove();

/** Removes all common error alerts from the configured container. */
const removeCommonErrorAlerts = (_: DomEl<HTMLElement>, el: HTMLElement): void => {
    const errors = el.querySelectorAll('.alert-danger');
    errors.forEach(removeErrorAlert);
};

/** Clears all content from the common-error container. */
const clearCommonErrorContent = (_: DomEl<HTMLElement>, el: HTMLElement): void => {
    el.innerHTML = '';
};

export class FormTool {
    protected commonErrors: DomEl<HTMLElement>;
    protected progress: DomEl<HTMLElement>;
    protected fields: Record<string, DomEl<any>> = {};
    protected readOnly: Record<string, boolean> = {};
    protected controls: DomEl<any>[] = [];
    protected validateWatchEnabled = false;
    protected handleGetDefaultObj: null | (() => TFromMap) = null;
    protected defaultValues: TFromMap = {};

    /** Creates form state around a DOM component. */
    constructor(protected form: Component) {
    }

    /** Sets a fallback value for a named field. */
    public setDefaultValue = (name: string, value: string | number): FormTool => {
        this.defaultValues[name] = value;
        return this;
    }

    /** Sets the container used for form-level errors. */
    public setCommonErrors = (commonErrors: DomEl<HTMLElement>): FormTool => {
        this.commonErrors = commonErrors;
        return this;
    }

    /** Sets a callback that supplies additional default values. */
    public setHandleGetDefaultObj = (handle: null | (() => TFromMap)): FormTool => {
        this.handleGetDefaultObj = handle;
        return this;
    }

    /** Sets the upload progress display. */
    public setProgress = (progress: DomEl<HTMLElement>): FormTool => {
        this.progress = progress;
        return this;
    }

    /** Registers a field and its live-validation listeners. */
    public addField = (name: string, el: DomEl<any>, readOnly = false): void => {
        this.fields[name] = el;

        if (readOnly) {
            this.readOnly[name] = true;
        }

        el.getEl().addEventListener('input', () => {
            if (this.validateWatchEnabled) {
                this.debouncedValidate();
            }
        });

        el.getEl().addEventListener('blur', () => {
            if (this.validateWatchEnabled) {
                this.validateField(name);
            }
        });
    };

    /** Validates one visible field and updates form-level errors. */
    protected validateField = (name: string): boolean => {
        const field = this.fields[name];

        if (!field || field.isHidden()) {
            return true;
        }

        field.clearErrors();

        const valid = field.validate();

        if (!valid) {
            this.checkCommonErrors();
        }

        return valid;
    };

    /** Removes form-level errors when no field errors remain. */
    protected checkCommonErrors = (): void => {
        const hasFieldErrors = Object.values(this.fields).some(hasFieldError);

        if (!hasFieldErrors && this.commonErrors) {
            this.commonErrors.then(removeCommonErrorAlerts);
        }
    };

    protected debouncedValidate = debounce((): void => {
        this.validate();
    }, 300);

    /** Registers a control disabled alongside form fields. */
    public addControl = (el: DomEl<any>): FormTool => {
        this.controls.push(el);
        return this;
    };

    /** Disables editable fields and registered controls. */
    public disableForm = (): FormTool => {
        for (let [name, el] of Object.entries(this.fields)) {
            if (!this.readOnly[name]) {
                el.disable();
            }
        }

        for (let el of this.controls) {
            el.disable();
        }

        return this;
    };

    /** Enables editable fields and registered controls. */
    public enableForm = (): FormTool => {
        for (let [name, el] of Object.entries(this.fields)) {
            if (!this.readOnly[name]) {
                el.enable();
            }
        }

        for (let el of this.controls) {
            el.enable();
        }

        return this;
    };

    /** Escapes a form-level error into alert markup. */
    protected makeCommonErrorEl = (error: string) =>
        `<div class="alert alert-danger mb-2" role="alert">${escapeHtml(error)}</div>`;

    /** Appends one form-level error message. */
    public addCommonError = (error: string) => {
        this.commonErrors?.then((_, el) => {
            el.innerHTML += this.makeCommonErrorEl(error);
        });
    }

    /** Appends several form-level error messages. */
    public addCommonErrors = (errors: string[]) => {
        this.commonErrors?.then((_, el) => {
            el.innerHTML += errors.map(this.makeCommonErrorEl).join('');
        });
    }

    /** Clears field and form-level error messages. */
    public clearErrors = (): FormTool => {
        this.commonErrors?.then(clearCommonErrorContent);

        for (let el of this.form.items('.garnet-form-error')) {
            el.remove();
        }

        return this;
    };

    /** Validates all visible fields and serializes their values. */
    public validate = (): FormData | false => {
        let valid = true;
        const resultObj: TFromMap = {
            ...this.defaultValues,
            ...(this.handleGetDefaultObj ? this.handleGetDefaultObj() : {})
        };

        this.clearErrors();

        for (let [name, field] of Object.entries(this.fields)) {
            if (field.isHidden()) {
                continue;
            }

            let v = field.validate();

            if (v) {
                resultObj[name] = field.getValue(resultObj);
            } else {
                valid = false;
            }
        }

        if (!valid) {
            this.validateWatchEnabled = true;
            this.addCommonError(I18nFramework.Common_FromHasError());
            return false;
        }

        this.validateWatchEnabled = false;

        return makeFormData(resultObj);
    };

    protected progressShown = false;

    /** Updates the upload progress indicator. */
    handleUploadProgress = (progress: number): void => {
        if (!this.progress) {
            return;
        }

        if (progress < 100) {

            if (!this.progressShown) {
                this.progress?.toggle(true);
                this.progressShown = true;
            }

            updateProgress(this.progress.getEl(), progress);

            return;
        }

        this.progress?.toggle(false);
        this.progressShown = false;
    };

    /** Re-enables the form after a request completes. */
    protected handleEndSendForm = () => {
        this.enableForm();
        this.progress?.toggle(false);
        this.progressShown = false;
    };

    /** Displays validation errors returned by the server. */
    protected handleSendFormCatchErrors = (errors: TFormErrors = null) => {
        this.handleEndSendForm();

        if (!errors) {
            return;
        }

        const e = errors;

        if (Array.isArray(e.commonErrors)) {
            this.addCommonErrors(e.commonErrors);
        } else if (typeof e.commonErrors === 'string') {
            this.addCommonError(e.commonErrors);
        }

        if (typeof e.errors !== 'object') {
            return;
        }

        this.addCommonError(I18nFramework.Common_FromHasError());

        for (let [field, fieldErrors] of Object.entries(e.errors)) {
            const el = this.fields[field];

            if (!el) {
                // The server has something to say about a field this form does
                // not own — a photo drawn by the uploader, a field rendered by
                // an island. Dropping the message left the person staring at a
                // screen where nothing happened and nothing was said: they
                // read the silence as success. Showing it in a slightly wrong
                // place is always better than not showing it.
                const orphaned = Array.isArray(fieldErrors) ? fieldErrors : [String(fieldErrors)];
                this.addCommonErrors(orphaned.filter(Boolean));

                continue;
            }

            if (Array.isArray(fieldErrors)) {
                for (let error of fieldErrors) {
                    el.appendError(error);
                }

                continue;
            }

            if (typeof fieldErrors === 'string') {
                el.appendError(fieldErrors);
            }
        }
    }

    /** Restores form state and reports a failed request. */
    protected handleFailSendForm = (e: Error) => {
        this.handleEndSendForm();
        this.addCommonError(I18nFramework.Common_RequestError());

        throw e;
    };

    /** Validates and submits the form data to a URL. */
    public send = <T>(url: string): null | Promise<T> => {
        const formData = this.validate();

        if (!formData) {
            return null;
        }

        this.disableForm();

        return new Promise<T>((success, fail) => {
            sendPostFormData(url, formData, this.handleUploadProgress)
                .then((result: T) => {
                    this.handleEndSendForm();

                    if (isFormErrors(result)) {
                        this.handleSendFormCatchErrors(result);
                        fail();
                    } else {
                        success(result);
                    }
                }).catch(this.handleFailSendForm);
        })
    };
}
