import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import type {AsyncIconState, UseAsyncIconOptions} from '@common/hooks/ui/useAsyncIcon';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import {isMaintenanceError} from '@common/Api/maintenance503';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {AsyncIcon} from './AsyncIcon';

export interface AsyncIconButtonProps extends UseAsyncIconOptions {
    /** Idle glyph (Lucide node or `<i className="bi …"/>`). */
    icon: React.ReactNode;
    /** The request to run on click. The button shows a loader while it's pending. */
    onAction: () => unknown | Promise<unknown>;
    /** Optional text beside the icon. */
    label?: React.ReactNode;
    /** Render as an anchor instead of a button (still intercepts the click). */
    href?: string;
    variant?: 'muted-outline' | 'accent-outline';
    title?: string;
    ariaLabel?: string;
    testId?: string;
    disabled?: boolean;
    iconSize?: number;
    errorIcon?: React.ReactNode;
}

/** A button or link that shows loading and error states while its action runs. */
export class AsyncIconButton extends AntiHookComponent<AsyncIconButtonProps> {
    private readonly iconState = new Carburetor<{state: AsyncIconState}>({state: 'idle'});
    private timer: ReturnType<typeof setTimeout> | null = null;
    private mounted = true;

    public componentDidMount(): void {
        super.componentDidMount();
        this.mounted = true;
    }

    public componentWillUnmount(): void {
        this.mounted = false;
        this.clearTimer();
        super.componentWillUnmount();
    }

    private clearTimer(): void {
        if (this.timer !== null) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    }

    private async runAction(action: () => unknown | Promise<unknown>, options: UseAsyncIconOptions): Promise<void> {
        const {errorToast, errorMs = 2000, onError, rethrow = false} = options;
        this.clearTimer();
        this.iconState.setData({state: 'loading'});
        try {
            await action();
            if (this.mounted) this.iconState.setData({state: 'idle'});
        } catch (error) {
            if (!isMaintenanceError(error)) {
                showToast(errorToast ?? I18nFramework.Common_RequestError(), 'danger');
            }
            onError?.(error);
            if (this.mounted) {
                this.iconState.setData({state: 'error'});
                this.timer = setTimeout(this.clearError, errorMs);
            }
            if (rethrow) throw error;
        }
    }

    private readonly clearError = (): void => {
        this.timer = null;
        if (this.mounted) this.iconState.setData({state: 'idle'});
    };

    private readonly handleClick = (event: React.MouseEvent<HTMLElement>): void => {
        event.preventDefault();
        const {disabled = false, onAction, errorToast, errorMs, onError, rethrow} = this.props;
        if (disabled || this.iconState.getData().state === 'loading') return;
        void this.runAction(onAction, {errorToast, errorMs, onError, rethrow});
    };

    private renderInner(state: AsyncIconState) {
        const {icon, label, iconSize = 18, errorIcon} = this.props;
        return (
            <>
                <AsyncIcon state={state} icon={icon} size={iconSize} errorIcon={errorIcon} />
                {label != null && <span>{label}</span>}
            </>
        );
    }

    private sharedProps(isLoading: boolean) {
        const {title, ariaLabel, testId} = this.props;
        return {
            title,
            'aria-label': ariaLabel ?? title,
            'aria-busy': isLoading || undefined,
            'data-test-id': testId,
            onClick: this.handleClick,
        };
    }

    public render() {
        const {href, variant, disabled = false} = this.props;
        const {state} = this.useCarburetor(this.iconState);
        const isLoading = state === 'loading';
        const inner = this.renderInner(state);
        const shared = this.sharedProps(isLoading);
        if (href != null) {
            return (
                <a
                    href={href}
                    className={variant === 'accent-outline' ? 'async-accent-action' : variant === 'muted-outline' ? 'async-muted-action' : undefined}
                    aria-disabled={disabled || isLoading || undefined}
                    {...shared}
                >
                    {inner}
                </a>
            );
        }
        return (
            <button
                type="button"
                className={variant === 'accent-outline' ? 'async-accent-action' : variant === 'muted-outline' ? 'async-muted-action' : undefined}
                disabled={disabled || isLoading}
                {...shared}
            >
                {inner}
            </button>
        );
    }
}
