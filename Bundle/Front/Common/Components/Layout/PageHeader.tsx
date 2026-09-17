import * as React from 'react';

interface PageHeaderProps {
    title: string;
    /** Optional one-line hint shown under the title. */
    subtitle?: string;
    /** Optional leading glyph (e.g. a Lucide icon). */
    icon?: React.ReactNode;
    /** Optional right-aligned actions (buttons, links, filters). */
    actions?: React.ReactNode;
    testId?: string;
}

/**
 * Shared page hero header — the styled title block at the top of a page.
 *
 * Renders a consistent gradient/glass panel (see .page-hero in components.css
 * + glass.css) so every page reads the same instead of ad-hoc bare <h1>s.
 */
export const PageHeader: React.FC<PageHeaderProps> = ({title, subtitle, icon, actions, testId}) => (
    <div className="page-hero" data-test-id={testId}>
        <div className="page-hero-row">
            <div className="page-hero-main">
                {icon && <span className="page-hero-icon" aria-hidden="true">{icon}</span>}
                <div className="min-w-0">
                    <h1 className="page-hero-title">{title}</h1>
                    {subtitle && <p className="page-hero-subtitle">{subtitle}</p>}
                </div>
            </div>
            {actions && <div className="page-hero-actions">{actions}</div>}
        </div>
    </div>
);

export default PageHeader;
