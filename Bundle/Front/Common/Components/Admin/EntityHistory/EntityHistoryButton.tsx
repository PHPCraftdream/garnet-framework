import * as React from 'react';
import {useState} from 'react';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {EntityHistoryDrawer} from './EntityHistoryDrawer';

interface Props {
    entityType: string;
    entityId: string | number;
    /** Visible button label. Defaults to the framework i18n key. */
    label?: string;
    /** Modal heading. Defaults to derived from entityType + entityId. */
    title?: string;
    /** Override list endpoint. Defaults to /admin/entity-history/~list. */
    listUrl?: string;
    /** Optional className passed to the button. */
    className?: string;
    /** Optional data-test-id suffix; final id is `entity-history-btn-${suffix}` if provided. */
    testIdSuffix?: string;
}

/**
 * Drop-in button that opens a modal showing the audit log for a single
 * entity record. One liner usage:
 *
 *   <EntityHistoryButton entityType="static_page" entityId={pageId} />
 *
 * No extra plumbing needed — the modal fetches the rows itself.
 */
export const EntityHistoryButton: React.FC<Props> = ({
    entityType,
    entityId,
    label,
    title,
    listUrl,
    className,
    testIdSuffix,
}) => {
    const [open, setOpen] = useState<boolean>(false);
    const buttonLabel = label ?? t.EntityHistory_DefaultLabel();

    const testId = testIdSuffix
        ? `entity-history-btn-${testIdSuffix}`
        : `entity-history-btn-${entityType}-${entityId}`;

    return (
        <>
            <button
                type="button"
                className={className ?? 'btn btn-ghost btn-sm'}
                onClick={() => setOpen(true)}
                data-test-id={testId}
                title={title ?? buttonLabel}
            >
                {buttonLabel}
            </button>
            {open && (
                <EntityHistoryDrawer
                    entityType={entityType}
                    entityId={entityId}
                    title={title}
                    listUrl={listUrl}
                    onClose={() => setOpen(false)}
                />
            )}
        </>
    );
};
