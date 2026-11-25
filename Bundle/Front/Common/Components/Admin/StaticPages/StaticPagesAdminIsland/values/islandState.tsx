import {TabDef} from '@common/Components/Layout/Navigation/TabNav';
import {Props, StaticPage, Snippet, PageTabInfo, SnippetTabInfo} from
    '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/types';
import {useSending} from '@common/hooks/data/useSending';
import {useConfirm} from '@common/hooks/ui/useConfirm';

/** Converts an editor tab into the navigation shape. */
function toTabDef(tab: PageTabInfo | SnippetTabInfo): TabDef {
    return {id: tab.id, label: tab.title, closeable: true};
}

export type ShellProps = Props & ReturnType<typeof useSending> & ReturnType<typeof useConfirm>;

export type ShellState = {
    pages: StaticPage[]; snippets: Snippet[]; templateVariables: string[];
    showCreateForm: boolean; newSlug: string; newTitle: string; loaded: boolean; snippetsLoaded: boolean;
    pageTabs: PageTabInfo[]; snippetTabs: SnippetTabInfo[]; activeTabId: string | null;
};

import * as React from 'react';
import {ConfirmModal} from '@common/Components/Feedback/ConfirmModal';
import {TabNav} from '@common/Components/Layout/Navigation/TabNav';
import {PageHeader} from '@common/Components/Layout/PageHeader';
import {FileText} from 'lucide-react';
import {StaticPagesList} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/StaticPagesList';
import {PageEditorTab} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/Pages/PageEditorTab';
import {SnippetsListPanel} from
    '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/Snippets/SnippetsListPanel';
import {SnippetEditorTab} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/Snippets/SnippetEditorTab';

interface ShellHandlers {
    openPageTab: (page: StaticPage) => void;
    openSnippetTab: (snippet: Snippet) => void;
    handleCreatePage: () => void;
    handleTogglePublish: (page: StaticPage) => void;
    handleDeletePage: (page: StaticPage) => Promise<void>;
    handlePageUpdated: (page: StaticPage) => void;
    handlePageDeleted: (pageId: number) => void;
    handleSnippetUpdated: (snippet: Snippet) => void;
    handleSnippetDeleted: (snippetId: number) => void;
    handleTabSelect: (id: string) => void;
    handleTabClose: (id: string) => void;
    toggleCreateForm: () => void;
    cancelCreateForm: () => void;
    changeSlug: React.ChangeEventHandler<HTMLInputElement>;
    changeTitle: React.ChangeEventHandler<HTMLInputElement>;
    handleSnippetCreated: (snippet: Snippet) => void;
    handleToggleSnippet: (snippet: Snippet) => void;
}

/** Renders navigation, lists, editors, and confirmation UI for the island. */
export default function renderStaticPagesShell(props: ShellProps, state: ShellState, handlers: ShellHandlers) {
    const {
        updateUrl, deleteUrl, blocksUrl, saveBlocksUrl, uploadImageUrl, deleteImageUrl,
        snippetCreateUrl, snippetUpdateUrl, snippetDeleteUrl, headerFooterSnippetsUrl,
        publicBaseUrl, labels, sending, withSending, confirm, confirmState, handleConfirm,
        handleCancel,
    } = props;
    const {
        pages, snippets, templateVariables, showCreateForm, newSlug, newTitle, loaded,
        snippetsLoaded, pageTabs, snippetTabs, activeTabId,
    } = state;
    const {
        openPageTab, openSnippetTab, handleCreatePage, handleTogglePublish, handleDeletePage,
        handlePageUpdated, handlePageDeleted, handleSnippetUpdated, handleSnippetDeleted,
        handleTabSelect, handleTabClose, toggleCreateForm, cancelCreateForm, changeSlug,
        changeTitle, handleSnippetCreated, handleToggleSnippet,
    } = handlers;
    const currentActiveId = activeTabId ?? 'pages';
    const allTabs: TabDef[] = [
        {id: 'pages', label: labels.title, closeable: false},
        {id: 'snippets', label: labels.snippets, closeable: false},
        ...pageTabs.map(toTabDef),
        ...snippetTabs.map(toTabDef),
    ];

    return (
        <>
            <PageHeader title={labels.title} icon={<FileText size={22} aria-hidden="true" />} />
            <div className="section-soft" data-test-id="admin-static-pages">
            <div className="static-admin-toolbar">
                <div className="static-admin-tabs">
                    <TabNav
                        tabs={allTabs}
                        activeId={currentActiveId}
                        onSelect={handleTabSelect}
                        onClose={handleTabClose}
                    />
                </div>
                {currentActiveId === 'pages' && (
                    <button
                        type="button"
                        className="static-admin-create-open"
                        onClick={toggleCreateForm}
                        disabled={sending}
                    >
                        + {labels.createOpen || labels.create}
                    </button>
                )}
            </div>

            {currentActiveId === 'pages' && (
                <StaticPagesList
                    labels={labels}
                    sending={sending}
                    showCreateForm={showCreateForm}
                    newSlug={newSlug}
                    newTitle={newTitle}
                    loaded={loaded}
                    pages={pages}
                    pageTabs={pageTabs}
                    publicBaseUrl={publicBaseUrl}
                    handleCreatePage={handleCreatePage}
                    handleTogglePublish={handleTogglePublish}
                    openPageTab={openPageTab}
                    handleDeletePage={handleDeletePage}
                    changeSlug={changeSlug}
                    changeTitle={changeTitle}
                    cancelCreateForm={cancelCreateForm}
                />
            )}

            {currentActiveId === 'snippets' && (
                <SnippetsListPanel
                    snippets={snippets}
                    loaded={snippetsLoaded}
                    labels={labels}
                    snippetCreateUrl={snippetCreateUrl}
                    snippetUpdateUrl={snippetUpdateUrl}
                    snippetDeleteUrl={snippetDeleteUrl}
                    sending={sending}
                    withSending={withSending}
                    confirm={confirm}
                    onSnippetCreated={handleSnippetCreated}
                    onSnippetDeleted={handleSnippetDeleted}
                    onToggleActive={handleToggleSnippet}
                    onOpenSnippet={openSnippetTab}
                    snippetTabs={snippetTabs}
                />
            )}

            {pageTabs.map((tab: PageTabInfo) => (
                <div key={tab.id} style={{display: currentActiveId === tab.id ? 'block' : 'none'}}>
                    <PageEditorTab
                        pageId={tab.pageId}
                        initialPage={pages.find(p => p.id === tab.pageId) ?? null}
                        labels={labels}
                        updateUrl={updateUrl}
                        deleteUrl={deleteUrl}
                        blocksUrl={blocksUrl}
                        saveBlocksUrl={saveBlocksUrl}
                        uploadImageUrl={uploadImageUrl}
                        deleteImageUrl={deleteImageUrl}
                        headerFooterSnippetsUrl={headerFooterSnippetsUrl}
                        publicBaseUrl={publicBaseUrl}
                        templateVariables={templateVariables}
                        pages={pages}
                        onPageUpdated={handlePageUpdated}
                        onPageDeleted={handlePageDeleted}
                    />
                </div>
            ))}

            {snippetTabs.map(tab => (
                <div key={tab.id} style={{display: currentActiveId === tab.id ? 'block' : 'none'}}>
                    <SnippetEditorTab
                        snippetId={tab.snippetId}
                        initialSnippet={snippets.find(s => s.id === tab.snippetId) ?? null}
                        labels={labels}
                        snippetUpdateUrl={snippetUpdateUrl}
                        snippetDeleteUrl={snippetDeleteUrl}
                        uploadImageUrl={uploadImageUrl}
                        deleteImageUrl={deleteImageUrl}
                        pages={pages}
                        onSnippetUpdated={handleSnippetUpdated}
                        onSnippetDeleted={handleSnippetDeleted}
                    />
                </div>
            ))}

            <ConfirmModal state={confirmState} onConfirm={handleConfirm} onCancel={handleCancel} />
            </div>
        </>
    );
}
