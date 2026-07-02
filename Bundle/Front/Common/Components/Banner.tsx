import * as React from 'react';

export type BannerVariant = 'info' | 'warn' | 'danger' | 'success';

interface Props {
    variant?: BannerVariant;
    icon?: React.ReactNode;
    title?: React.ReactNode;
    children: React.ReactNode;
    /** When set, replaces the default `banner` testid for targeting in specs. */
    dataTestId?: string;
}

const DEFAULT_ICONS: Record<BannerVariant, string> = {
    info: 'ℹ️',
    warn: '⚠️',
    danger: '⛔',
    success: '✅',
};

/**
 * Inline notice strip. Use for any page-level message: deprecation hints,
 * timezone notices, error summaries, success confirmations. Variants share
 * padding/radius/layout — only the colour changes.
 */
export const Banner: React.FC<Props> = ({variant = 'info', icon, title, children, dataTestId}) => {
    const variantClass = `banner-${variant}`;
    const role = variant === 'warn' || variant === 'danger' ? 'alert' : undefined;
    return (
        <div className={`banner ${variantClass}`} role={role} data-test-id={dataTestId ?? `banner-${variant}`}>
            <span aria-hidden="true" className="banner-icon">{icon ?? DEFAULT_ICONS[variant]}</span>
            <div className="min-w-0 flex-1">
                {title && <strong className="banner-title block">{title}</strong>}
                <span>{children}</span>
            </div>
        </div>
    );
};
