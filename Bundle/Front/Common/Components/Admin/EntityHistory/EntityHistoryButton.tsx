import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
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
export class EntityHistoryButton extends AntiHookComponent<Props> {
    private readonly store = new Carburetor({open: false});

    private readonly open = (): void => {
        this.store.setData({open: true});
    };
    private readonly close = (): void => {
        this.store.setData({open: false});
    };

    private renderButton(label: string, testId: string, title: string) {
        const {className} = this.props;
        if (className != null) {
            // carburetor-disable-next-line require-function-class
            return <button type="button" className={className} onClick={this.open} data-test-id={testId} title={title}>{label}</button>;
        }
        return <button type="button" className="entity-history-button" onClick={this.open} data-test-id={testId} title={title}>{label}</button>;
    }

    public render() {
        const {entityType, entityId, label, title, listUrl, testIdSuffix} = this.props;
        const {open} = this.useCarburetor(this.store);
        const buttonLabel = label ?? t.EntityHistory_DefaultLabel();
        const testId = testIdSuffix ? `entity-history-btn-${testIdSuffix}` : `entity-history-btn-${entityType}-${entityId}`;
        return <>
            {this.renderButton(buttonLabel, testId, title ?? buttonLabel)}
            {open && <EntityHistoryDrawer entityType={entityType} entityId={entityId} title={title} listUrl={listUrl} onClose={this.close} />}
        </>;
    }
}
