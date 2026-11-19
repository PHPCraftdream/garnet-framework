import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {EntityHistoryButton} from '@common/Components/Admin/EntityHistory/EntityHistoryButton';
import {formatTs} from '@common/Utils/Time/DateUtils';
import type {Labels, PageTabInfo, StaticPage} from './types';

interface Props {
    labels: Labels;
    sending: boolean;
    showCreateForm: boolean;
    newSlug: string;
    newTitle: string;
    loaded: boolean;
    pages: StaticPage[];
    pageTabs: PageTabInfo[];
    publicBaseUrl: string;
    handleCreatePage: () => void;
    handleTogglePublish: (page: StaticPage) => void;
    openPageTab: (page: StaticPage) => void;
    handleDeletePage: (page: StaticPage) => Promise<void>;
    changeSlug: React.ChangeEventHandler<HTMLInputElement>;
    changeTitle: React.ChangeEventHandler<HTMLInputElement>;
    cancelCreateForm: () => void;
}

export class StaticPagesList extends AntiHookComponent<Props> {
    render() {
        return this.renderList();
    }

    private pageFor(event: React.MouseEvent<HTMLButtonElement>): StaticPage | undefined {
        const id = Number(event.currentTarget.dataset.pageId);
        return this.props.pages.find(page => page.id === id);
    }

    private readonly togglePage = (event: React.MouseEvent<HTMLButtonElement>) => {
        const page = this.pageFor(event);
        if (page) this.props.handleTogglePublish(page);
    };

    private readonly openPage = (event: React.MouseEvent<HTMLButtonElement>) => {
        const page = this.pageFor(event);
        if (page) this.props.openPageTab(page);
    };

    private readonly deletePage = (event: React.MouseEvent<HTMLButtonElement>) => {
        const page = this.pageFor(event);
        if (page) void this.props.handleDeletePage(page);
    };

    private readonly renderRow = (page: StaticPage) => {
        const {pageTabs, sending, labels, publicBaseUrl} = this.props;
        const isOpenInTab = pageTabs.some(t => t.pageId === page.id);
        return (
            <tr key={page.id} className={isOpenInTab ? 'static-admin-row-open' : undefined}>
                <td className="static-admin-slug-cell">{page.slug}</td>
                <td className="static-admin-cell">{page.title_rendered || page.title}</td>
                <td className="static-admin-cell">
                    <button
                        type="button"
                        className="static-admin-status" data-published={!!page.is_published}
                        data-page-id={page.id} onClick={this.togglePage}
                        disabled={sending}
                    >
                        {page.is_published ? labels.published : labels.draft}
                    </button>
                </td>
                <td className="static-admin-time-cell">{formatTs(page.updated_at)}</td>
                <td className="static-admin-cell">
                    <div className="static-admin-row-actions">
                        <button
                            type="button"
                            className="static-admin-link"
                            data-page-id={page.id} onClick={this.openPage}
                        >
                            {labels.editPage}
                        </button>
                        <a
                            href={publicBaseUrl + page.slug}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="static-admin-link"
                        >
                            {labels.openPage}
                        </a>
                        <button
                            type="button"
                            className="static-admin-delete"
                            data-page-id={page.id} onClick={this.deletePage}
                            disabled={sending}
                        >
                            {labels.actionDelete}
                        </button>
                        <EntityHistoryButton
                            entityType="static_page"
                            entityId={page.id}
                            className="static-admin-link"
                            testIdSuffix={`page-${page.id}`}
                        />
                    </div>
                </td>
            </tr>
        );
    };

    private renderList() {
        const {labels, sending, showCreateForm, newSlug, newTitle, loaded, pages, handleCreatePage} = this.props;
        return (
        <div className="static-admin-pages">
            {showCreateForm && (
                <section className="static-admin-create-panel">
                    <h2 className="static-admin-create-title">{labels.createTitle}</h2>
                    <div className="static-admin-fields">
                        <label className="static-admin-label">
                            <span className="static-admin-caption">{labels.slug}</span>
                            <input
                                className="static-admin-input"
                                value={newSlug}
                                onChange={this.props.changeSlug}
                            />
                            <span className="static-admin-hint">{labels.slugHint}</span>
                        </label>
                        <label className="static-admin-label">
                            <span className="static-admin-caption">{labels.pageTitle}</span>
                            <input
                                className="static-admin-input"
                                value={newTitle}
                                onChange={this.props.changeTitle}
                            />
                        </label>
                    </div>
                    <div className="static-admin-create-actions">
                        <button
                            type="button"
                            className="static-admin-create-submit"
                            onClick={handleCreatePage}
                            disabled={sending || !newSlug.trim()}
                        >
                            {labels.create}
                        </button>
                        <button
                            type="button"
                            className="static-admin-cancel"
                            onClick={this.props.cancelCreateForm}
                        >
                            {labels.actionCancel}
                        </button>
                    </div>
                </section>
            )}

            {loaded && pages.length === 0 && (
                <div className="static-admin-empty">{labels.empty}</div>
            )}
            {pages.length > 0 && (
                <div className="static-admin-table-scroll">
                    <table className="static-admin-table">
                        <thead>
                            <tr className="static-admin-heading-row">
                                <th className="static-admin-cell">{labels.slug}</th>
                                <th className="static-admin-cell">{labels.pageTitle}</th>
                                <th className="static-admin-cell"></th>
                                <th className="static-admin-cell"></th>
                                <th className="static-admin-cell"></th>
                            </tr>
                        </thead>
                        <tbody className="static-admin-table-body">
                            {pages.map(this.renderRow)}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
        );
    }
}
