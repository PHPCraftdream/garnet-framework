import {makeFormData} from '@common/Api/makeFormData';
import {Component} from '@common/Dom/Component';
import {DomEl} from '@common/Dom/DomEl';
import {updateProgress} from '@common/Dom/updateProgress';
import {sendPostFormData} from '@common/Api/sendPostFormData';
import {TFormErrors, TFromMap} from '@common/Models';
import {escapeHtml} from '@common/Utils/Str/EscapeHtml';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

const isFormErrors = (data: any): data is TFormErrors => {
    return !!data?.errors || !!data?.commonErrors;
}

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

export class FormTool {
    protected commonErrors: DomEl<HTMLElement>;
    protected progress: DomEl<HTMLElement>;
    protected fields: Record<string, DomEl<any>> = {};
    protected readOnly: Record<string, boolean> = {};
    protected controls: DomEl<any>[] = [];
    protected validateWatchEnabled = false;
    protected handleGetDefaultObj: null | (() => TFromMap) = null;
    protected defaultValues: TFromMap = {};

    constructor(protected form: Component) {
    }

    public setDefaultValue = (name: string, value: string | number): FormTool => {
        this.defaultValues[name] = value;
        return this;
    }

    public setCommonErrors = (commonErrors: DomEl<HTMLElement>): FormTool => {
        this.commonErrors = commonErrors;
        return this;
    }

    public setHandleGetDefaultObj = (handle: null | (() => TFromMap)): FormTool => {
        this.handleGetDefaultObj = handle;
        return this;
    }

    public setProgress = (progress: DomEl<HTMLElement>): FormTool => {
        this.progress = progress;
        return this;
    }

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

    protected checkCommonErrors = (): void => {
        const hasFieldErrors = Object.values(this.fields).some(f => {
            const parent = f.getEl()?.parentNode;
            return parent?.querySelector('.garnet-form-error');
        });

        if (!hasFieldErrors && this.commonErrors) {
            this.commonErrors.then((_, el) => {
                const errors = el.querySelectorAll('.alert-danger');
                errors.forEach(e => e.remove());
            });
        }
    };

    protected debouncedValidate = debounce((): void => {
        this.validate();
    }, 300);

    public addControl = (el: DomEl<any>): FormTool => {
        this.controls.push(el);
        return this;
    };

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

    protected makeCommonErrorEl = (error: string) => `<div class="alert alert-danger mb-2" role="alert">${escapeHtml(error)}</div>`;

    public addCommonError = (error: string) => {
        this.commonErrors?.then((_, el) => {
            el.innerHTML += this.makeCommonErrorEl(error);
        });
    }

    public addCommonErrors = (errors: string[]) => {
        this.commonErrors?.then((_, el) => {
            el.innerHTML += errors.map(this.makeCommonErrorEl).join('');
        });
    }

    public clearErrors = (): FormTool => {
        this.commonErrors?.then((_, el) => {
            el.innerHTML = '';
        });

        for (let el of this.form.items('.garnet-form-error')) {
            el.remove();
        }

        return this;
    };

    public validate = (): FormData | false => {
        let valid = true;
        const resultObj: TFromMap = {
            ...this.defaultValues,
            ...(this.handleGetDefaultObj ?  this.handleGetDefaultObj(): {})
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

    protected handleEndSendForm = () => {
        this.enableForm();
        this.progress?.toggle(false);
        this.progressShown = false;
    };

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

        for (let [field, errors] of Object.entries(e.errors)) {
            const el = this.fields[field];

            if (!el) {
                continue
            }

            if (Array.isArray(errors)) {
                for (let error of errors) {
                    el.appendError(error);
                }

                continue;
            }

            if (typeof errors === 'string') {
                el.appendError(errors);
            }
        }
    }

    protected handleFailSendForm = (e: Error) => {
        this.handleEndSendForm();
        this.addCommonError(I18nFramework.Common_RequestError());

        throw e;
    };

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
