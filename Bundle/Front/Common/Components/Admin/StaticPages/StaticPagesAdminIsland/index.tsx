/**
 * Экран статических страниц: вкладки, загрузка списков, общее состояние.
 *
 * Файл называется index, чтобы путь импорта остался прежним:
 * приложение реэкспортирует остров по
 * @common/Components/StaticPages/StaticPagesAdminIsland, и превращение
 * файла в папку не должно этого касаться.
 */
import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import {ConfirmModal} from '@common/Components/Feedback/ConfirmModal';
import {TabNav, TabDef} from '@common/Components/Layout/Navigation/TabNav';
import {useSending} from '@common/hooks/data/useSending';
import {useConfirm} from '@common/hooks/ui/useConfirm';
import {PageHeader} from '@common/Components/Layout/PageHeader';
import {FileText} from 'lucide-react';
import {StaticPage, Snippet, Props, PageTabInfo, SnippetTabInfo} from './types';
import {StaticPagesList} from './StaticPagesList';
import {PageEditorTab} from './Pages/PageEditorTab';
import {SnippetsListPanel} from './Snippets/SnippetsListPanel';
import {SnippetEditorTab} from './Snippets/SnippetEditorTab';

function toTabDef(tab: PageTabInfo | SnippetTabInfo): TabDef {
    return {id: tab.id, label: tab.title, closeable: true};
}

type ShellProps = Props & ReturnType<typeof useSending> & ReturnType<typeof useConfirm>;

type ShellState = {
    pages: StaticPage[];
    snippets: Snippet[];
    templateVariables: string[];
    showCreateForm: boolean;
    newSlug: string;
    newTitle: string;
    loaded: boolean;
    snippetsLoaded: boolean;
    pageTabs: PageTabInfo[];
    snippetTabs: SnippetTabInfo[];
    activeTabId: string | null;
};

class StaticPagesAdmin extends AntiHookComponent<ShellProps> {
    private readonly ui = new Carburetor<ShellState>({
        pages: [], snippets: [], templateVariables: [], showCreateForm: false,
        newSlug: '', newTitle: '', loaded: false, snippetsLoaded: false,
        pageTabs: [], snippetTabs: [], activeTabId: null,
    });

    private readonly view = this.connectSelection(() => this.ui, state => state);

    public componentDidMount(): void {
        super.componentDidMount();
        void this.loadPages();
        void this.loadVariables();
        void this.loadSnippets();
    }

    private readonly setPages: React.Dispatch<React.SetStateAction<StaticPage[]>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, pages: typeof value === 'function' ? value(current.pages) : value});
    };

    private readonly setSnippets: React.Dispatch<React.SetStateAction<Snippet[]>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, snippets: typeof value === 'function' ? value(current.snippets) : value});
    };

    private readonly setTemplateVariables: React.Dispatch<React.SetStateAction<string[]>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, templateVariables: typeof value === 'function' ? value(current.templateVariables) : value});
    };

    private readonly setShowCreateForm: React.Dispatch<React.SetStateAction<boolean>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, showCreateForm: typeof value === 'function' ? value(current.showCreateForm) : value});
    };

    private readonly setNewSlug: React.Dispatch<React.SetStateAction<string>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, newSlug: typeof value === 'function' ? value(current.newSlug) : value});
    };

    private readonly setNewTitle: React.Dispatch<React.SetStateAction<string>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, newTitle: typeof value === 'function' ? value(current.newTitle) : value});
    };

    private readonly setLoaded: React.Dispatch<React.SetStateAction<boolean>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, loaded: typeof value === 'function' ? value(current.loaded) : value});
    };

    private readonly setSnippetsLoaded: React.Dispatch<React.SetStateAction<boolean>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, snippetsLoaded: typeof value === 'function' ? value(current.snippetsLoaded) : value});
    };

    private readonly setPageTabs: React.Dispatch<React.SetStateAction<PageTabInfo[]>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, pageTabs: typeof value === 'function' ? value(current.pageTabs) : value});
    };

    private readonly setSnippetTabs: React.Dispatch<React.SetStateAction<SnippetTabInfo[]>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, snippetTabs: typeof value === 'function' ? value(current.snippetTabs) : value});
    };

    private readonly setActiveTabId: React.Dispatch<React.SetStateAction<string | null>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, activeTabId: typeof value === 'function' ? value(current.activeTabId) : value});
    };

    private readonly loadPages = async () => {
        const {listUrl} = this.props;
        const {setPages, setLoaded} = this;
        try {
            const res = await sendPost<{}, {pages: StaticPage[]}>(listUrl, {});
            if ((res as any)?.error) return;
            setPages(res.pages ?? []);
            setLoaded(true);
        } catch {
            // silent
        }
    };

    private readonly loadVariables = async () => {
        const {variablesUrl} = this.props;
        const {setTemplateVariables} = this;
        try {
            const res = await sendPost<{}, {variables: string[]}>(variablesUrl, {});
            if ((res as any)?.error) return;
            setTemplateVariables(res.variables ?? []);
        } catch {
            // silent
        }
    };

    private readonly loadSnippets = async () => {
        const {snippetsListUrl} = this.props;
        const {setSnippets, setSnippetsLoaded} = this;
        try {
            const res = await sendPost<{}, {snippets: Snippet[]}>(snippetsListUrl, {});
            if ((res as any)?.error) return;
            setSnippets(res.snippets ?? []);
            setSnippetsLoaded(true);
        } catch {
            // silent
        }
    };

    private readonly openPageTab = (page: StaticPage) => {
        const {pageTabs} = this.ui.getData();
        const {setPageTabs, setActiveTabId} = this;
        const tabId = `page-${page.id}`;
        if (!pageTabs.find(t => t.id === tabId)) {
            setPageTabs(prev => [...prev, {id: tabId, pageId: page.id, title: page.title || page.slug}]);
        }
        setActiveTabId(tabId);
    };

    private readonly closePageTab = (tabId: string) => {
        const {activeTabId} = this.ui.getData();
        const {setPageTabs, setActiveTabId} = this;
        setPageTabs(prev => prev.filter(t => t.id !== tabId));
        if (activeTabId === tabId) {
            setActiveTabId(null);
        }
    };

    private readonly updatePageTabLabel = (pageId: number, title: string) => {
        const {setPageTabs} = this;
        setPageTabs(prev => prev.map(t => t.pageId === pageId ? {...t, title} : t));
    };

    private readonly openSnippetTab = (snippet: Snippet) => {
        const {snippetTabs} = this.ui.getData();
        const {setSnippetTabs, setActiveTabId} = this;
        const tabId = `snippet-${snippet.id}`;
        if (!snippetTabs.find(t => t.id === tabId)) {
            setSnippetTabs(prev => [...prev, {id: tabId, snippetId: snippet.id, title: snippet.name || snippet.slug}]);
        }
        setActiveTabId(tabId);
    };

    private readonly closeSnippetTab = (tabId: string) => {
        const {activeTabId} = this.ui.getData();
        const {setSnippetTabs, setActiveTabId} = this;
        setSnippetTabs(prev => prev.filter(t => t.id !== tabId));
        if (activeTabId === tabId) {
            setActiveTabId(null);
        }
    };

    private readonly updateSnippetTabLabel = (snippetId: number, title: string) => {
        const {setSnippetTabs} = this;
        setSnippetTabs(prev => prev.map(t => t.snippetId === snippetId ? {...t, title} : t));
    };

    private readonly handleCreatePage = () => {
        const {createUrl, labels, withSending} = this.props;
        const {newSlug, newTitle} = this.ui.getData();
        const {openPageTab, setPages, setShowCreateForm, setNewSlug, setNewTitle} = this;
        if (!newSlug.trim()) return;
        void withSending(async () => {
            try {
                const res = await sendPost<{slug: string; title: string}, {success: boolean; page: StaticPage}>(createUrl, {
                    slug: newSlug.trim(),
                    title: newTitle.trim(),
                });
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                setPages(prev => [...prev, res.page]);
                setNewSlug('');
                setNewTitle('');
                setShowCreateForm(false);
                showToast('OK', 'success');
                openPageTab(res.page);
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    private readonly handleTogglePublish = (page: StaticPage) => {
        const {updateUrl, labels, withSending} = this.props;
        const {setPages} = this;
        void withSending(async () => {
            const newPublished = page.is_published ? 0 : 1;
            try {
                const res = await sendPost<any, {success: boolean}>(updateUrl, {
                    id: page.id,
                    is_published: newPublished,
                });
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                const updated = {...page, is_published: newPublished};
                setPages(prev => prev.map(p => p.id === page.id ? updated : p));
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    private readonly handleDeletePage = async (page: StaticPage) => {
        const {deleteUrl, labels, withSending, confirm} = this.props;
        const {closePageTab, setPages} = this;
        const ok = await confirm(
            labels.deleteConfirm,
            {variant: 'danger', confirmLabel: labels.actionDelete},
        );
        if (!ok) return;
        void withSending(async () => {
            try {
                const res = await sendPost<{id: number}, {success: boolean}>(deleteUrl, {id: page.id});
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                setPages(prev => prev.filter(p => p.id !== page.id));
                const tabId = `page-${page.id}`;
                closePageTab(tabId);
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    private readonly handlePageUpdated = (updatedPage: StaticPage) => {
        const {updatePageTabLabel, setPages} = this;
        setPages(prev => prev.map(p => p.id === updatedPage.id ? updatedPage : p));
        updatePageTabLabel(updatedPage.id, updatedPage.title || updatedPage.slug);
    };

    private readonly handlePageDeleted = (pageId: number) => {
        const {closePageTab, setPages} = this;
        setPages(prev => prev.filter(p => p.id !== pageId));
        closePageTab(`page-${pageId}`);
    };

    private readonly handleSnippetUpdated = (updatedSnippet: Snippet) => {
        const {updateSnippetTabLabel, setSnippets} = this;
        setSnippets(prev => prev.map(s => s.id === updatedSnippet.id ? updatedSnippet : s));
        updateSnippetTabLabel(updatedSnippet.id, updatedSnippet.name || updatedSnippet.slug);
    };

    private readonly handleSnippetDeleted = (snippetId: number) => {
        const {closeSnippetTab, setSnippets} = this;
        setSnippets(prev => prev.filter(s => s.id !== snippetId));
        closeSnippetTab(`snippet-${snippetId}`);
    };

    private readonly handleTabSelect = (id: string) => {
        const {setActiveTabId} = this;
        if (id === 'pages' || id === 'snippets') {
            setActiveTabId(id === 'pages' ? null : id);
        } else {
            setActiveTabId(id);
        }
    };

    private readonly handleTabClose = (id: string) => {
        const {closePageTab, closeSnippetTab} = this;
        if (id.startsWith('snippet-')) {
            closeSnippetTab(id);
        } else {
            closePageTab(id);
        }
    };

    private readonly toggleCreateForm = () => this.setShowCreateForm(!this.ui.getData().showCreateForm);
    private readonly cancelCreateForm = () => this.setShowCreateForm(false);
    private readonly changeSlug = (event: React.ChangeEvent<HTMLInputElement>) => this.setNewSlug(event.target.value);
    private readonly changeTitle = (event: React.ChangeEvent<HTMLInputElement>) => this.setNewTitle(event.target.value);

    private readonly handleSnippetCreated = (snippet: Snippet) => {
        this.setSnippets(previous => [...previous, snippet]);
        this.openSnippetTab(snippet);
    };

    private readonly handleToggleSnippet = (snippet: Snippet) => {
        const {withSending, snippetUpdateUrl, labels} = this.props;
        const newActive = snippet.is_active ? 0 : 1;
        void withSending(async () => {
            try {
                const res = await sendPost<any, {success: boolean}>(snippetUpdateUrl, {id: snippet.id, is_active: newActive});
                if ((res as any)?.error) { showToast((res as any).error, 'danger'); return; }
                this.setSnippets(previous => previous.map(item => item.id === snippet.id ? {...item, is_active: newActive} : item));
            } catch (err: any) { showToast(err?.message ?? labels.error, 'danger'); }
        });
    };

    public render() {
        const state = this.view() as ShellState;
        return this.renderShell(state);
    }

    private renderShell(state: ShellState) {
        const {updateUrl, deleteUrl, blocksUrl, saveBlocksUrl, uploadImageUrl, deleteImageUrl, snippetCreateUrl, snippetUpdateUrl, snippetDeleteUrl, headerFooterSnippetsUrl, publicBaseUrl, labels, sending, withSending, confirm, confirmState, handleConfirm, handleCancel} = this.props;
        const {pages, snippets, templateVariables, showCreateForm, newSlug, newTitle, loaded, snippetsLoaded, pageTabs, snippetTabs, activeTabId} = state;
        const {openPageTab, openSnippetTab, handleCreatePage, handleTogglePublish, handleDeletePage, handlePageUpdated, handlePageDeleted, handleSnippetUpdated, handleSnippetDeleted, handleTabSelect, handleTabClose} = this;
    // ── TabNav setup ──

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
                        onClick={this.toggleCreateForm}
                        disabled={sending}
                    >
                        + {labels.createOpen || labels.create}
                    </button>
                )}
            </div>

            {currentActiveId === 'pages' && (
                <StaticPagesList labels={labels} sending={sending} showCreateForm={showCreateForm}
                    newSlug={newSlug} newTitle={newTitle} loaded={loaded} pages={pages} pageTabs={pageTabs}
                    publicBaseUrl={publicBaseUrl} handleCreatePage={handleCreatePage}
                    handleTogglePublish={handleTogglePublish} openPageTab={openPageTab} handleDeletePage={handleDeletePage}
                    changeSlug={this.changeSlug} changeTitle={this.changeTitle} cancelCreateForm={this.cancelCreateForm} />
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
                    onSnippetCreated={this.handleSnippetCreated}
                    onSnippetDeleted={handleSnippetDeleted}
                    onToggleActive={this.handleToggleSnippet}
                    onOpenSnippet={openSnippetTab}
                    snippetTabs={snippetTabs}
                />
            )}

            {pageTabs.map(tab => (
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
}

export const StaticPagesAdminIsland: React.FC<Props> = props => {
    const sending = useSending();
    const confirmation = useConfirm();
    return <StaticPagesAdmin {...props} {...sending} {...confirmation} />;
};
