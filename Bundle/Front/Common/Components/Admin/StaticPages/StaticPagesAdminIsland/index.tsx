/**
 * Экран статических страниц: вкладки, загрузка списков, общее состояние.
 *
 * Файл называется index, чтобы путь импорта остался прежним:
 * приложение реэкспортирует остров по
 * @common/Components/StaticPages/StaticPagesAdminIsland, и превращение
 * файла в папку не должно этого касаться.
 */
import * as React from 'react';
import {sendPost} from '@common/Api/Send/sendPost';
import {sendPostFormData} from '@common/Api/Send/sendPostFormData';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import {ConfirmModal} from '@common/Components/Feedback/ConfirmModal';
import {EntityHistoryButton} from '@common/Components/Admin/EntityHistory/EntityHistoryButton';
import {TabNav, TabDef} from '@common/Components/Layout/Navigation/TabNav';
import {useSending} from '@common/hooks/data/useSending';
import {useConfirm} from '@common/hooks/ui/useConfirm';
import {formatTs} from '@common/Utils/Time/DateUtils';
import {markdownToHtml} from '@common/Utils/Ui/markdownToHtml';
import {PageHeader} from '@common/Components/Layout/PageHeader';
import {ImageUploadArea} from '@common/Components/Controls/ImageUploadArea';
import {ImageUploadField} from '@common/Components/Controls/ImageUploadField';
import {FileText} from 'lucide-react';
import {StaticPage, Snippet, PageBlock, Labels, Props, PageTabInfo, SnippetTabInfo, STATIC_TABS, StaticTabId, SNIPPET_TYPES} from './types';
import {PageEditorTab} from './Pages/PageEditorTab';
import {SnippetsListPanel} from './Snippets/SnippetsListPanel';
import {SnippetEditorTab} from './Snippets/SnippetEditorTab';

export const StaticPagesAdminIsland: React.FC<Props> = (props) => {
    const {
        listUrl, createUrl, updateUrl, deleteUrl,
        blocksUrl, saveBlocksUrl, variablesUrl, uploadImageUrl, deleteImageUrl,
        snippetsListUrl, snippetCreateUrl, snippetUpdateUrl, snippetDeleteUrl,
        headerFooterSnippetsUrl,
        publicBaseUrl, labels,
    } = props;

    const [pages, setPages] = React.useState<StaticPage[]>([]);
    const [snippets, setSnippets] = React.useState<Snippet[]>([]);
    const [templateVariables, setTemplateVariables] = React.useState<string[]>([]);
    const [showCreateForm, setShowCreateForm] = React.useState(false);
    const [newSlug, setNewSlug] = React.useState('');
    const [newTitle, setNewTitle] = React.useState('');
    const [loaded, setLoaded] = React.useState(false);
    const [snippetsLoaded, setSnippetsLoaded] = React.useState(false);

    const {sending, withSending} = useSending();
    const {confirmState, confirm, handleConfirm, handleCancel} = useConfirm();

    // ── Tab state ──
    const [pageTabs, setPageTabs] = React.useState<PageTabInfo[]>([]);
    const [snippetTabs, setSnippetTabs] = React.useState<SnippetTabInfo[]>([]);
    const [activeTabId, setActiveTabId] = React.useState<string | null>(null);

    React.useEffect(() => {
        void loadPages();
        void loadVariables();
        void loadSnippets();
    }, []);

    const loadPages = async () => {
        try {
            const res = await sendPost<{}, {pages: StaticPage[]}>(listUrl, {});
            if ((res as any)?.error) return;
            setPages(res.pages ?? []);
            setLoaded(true);
        } catch {
            // silent
        }
    };

    const loadVariables = async () => {
        try {
            const res = await sendPost<{}, {variables: string[]}>(variablesUrl, {});
            if ((res as any)?.error) return;
            setTemplateVariables(res.variables ?? []);
        } catch {
            // silent
        }
    };

    const loadSnippets = async () => {
        try {
            const res = await sendPost<{}, {snippets: Snippet[]}>(snippetsListUrl, {});
            if ((res as any)?.error) return;
            setSnippets(res.snippets ?? []);
            setSnippetsLoaded(true);
        } catch {
            // silent
        }
    };

    // ── Tab operations ──

    const openPageTab = (page: StaticPage) => {
        const tabId = `page-${page.id}`;
        if (!pageTabs.find(t => t.id === tabId)) {
            setPageTabs(prev => [...prev, {id: tabId, pageId: page.id, title: page.title || page.slug}]);
        }
        setActiveTabId(tabId);
    };

    const closePageTab = (tabId: string) => {
        setPageTabs(prev => prev.filter(t => t.id !== tabId));
        if (activeTabId === tabId) {
            setActiveTabId(null);
        }
    };

    const updatePageTabLabel = (pageId: number, title: string) => {
        setPageTabs(prev => prev.map(t => t.pageId === pageId ? {...t, title} : t));
    };

    // ── Snippet tab operations ──

    const openSnippetTab = (snippet: Snippet) => {
        const tabId = `snippet-${snippet.id}`;
        if (!snippetTabs.find(t => t.id === tabId)) {
            setSnippetTabs(prev => [...prev, {id: tabId, snippetId: snippet.id, title: snippet.name || snippet.slug}]);
        }
        setActiveTabId(tabId);
    };

    const closeSnippetTab = (tabId: string) => {
        setSnippetTabs(prev => prev.filter(t => t.id !== tabId));
        if (activeTabId === tabId) {
            setActiveTabId(null);
        }
    };

    const updateSnippetTabLabel = (snippetId: number, title: string) => {
        setSnippetTabs(prev => prev.map(t => t.snippetId === snippetId ? {...t, title} : t));
    };

    // ── Create page ──

    const handleCreatePage = () => {
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

    // ── Toggle publish from list ──

    const handleTogglePublish = (page: StaticPage) => {
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

    // ── Delete from list ──

    const handleDeletePage = async (page: StaticPage) => {
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

    // ── Callbacks from PageEditorTab ──

    const handlePageUpdated = (updatedPage: StaticPage) => {
        setPages(prev => prev.map(p => p.id === updatedPage.id ? updatedPage : p));
        updatePageTabLabel(updatedPage.id, updatedPage.title || updatedPage.slug);
    };

    const handlePageDeleted = (pageId: number) => {
        setPages(prev => prev.filter(p => p.id !== pageId));
        closePageTab(`page-${pageId}`);
    };

    // ── Snippet callbacks ──

    const handleSnippetUpdated = (updatedSnippet: Snippet) => {
        setSnippets(prev => prev.map(s => s.id === updatedSnippet.id ? updatedSnippet : s));
        updateSnippetTabLabel(updatedSnippet.id, updatedSnippet.name || updatedSnippet.slug);
    };

    const handleSnippetDeleted = (snippetId: number) => {
        setSnippets(prev => prev.filter(s => s.id !== snippetId));
        closeSnippetTab(`snippet-${snippetId}`);
    };

    // ── TabNav setup ──

    const currentActiveId = activeTabId ?? 'pages';

    const allTabs: TabDef[] = [
        {id: 'pages', label: labels.title, closeable: false},
        {id: 'snippets', label: labels.snippets, closeable: false},
        ...pageTabs.map(t => ({id: t.id, label: t.title, closeable: true})),
        ...snippetTabs.map(t => ({id: t.id, label: t.title, closeable: true})),
    ];

    const handleTabSelect = (id: string) => {
        if (id === 'pages' || id === 'snippets') {
            setActiveTabId(id === 'pages' ? null : id);
        } else {
            setActiveTabId(id);
        }
    };

    const handleTabClose = (id: string) => {
        if (id.startsWith('snippet-')) {
            closeSnippetTab(id);
        } else {
            closePageTab(id);
        }
    };

    return (
        <>
            <PageHeader title={labels.title} icon={<FileText size={22} aria-hidden="true" />} />
            <div className="section-soft" data-test-id="admin-static-pages">
            <div className="flex items-center justify-between gap-3">
                <div className="flex-1 min-w-0">
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
                        className="btn btn-primary btn-sm shrink-0 disabled:opacity-60 relative -top-2.5"
                        onClick={() => setShowCreateForm(!showCreateForm)}
                        disabled={sending}
                    >
                        + {labels.createOpen || labels.create}
                    </button>
                )}
            </div>

            {currentActiveId === 'pages' && (
                <div className="space-y-6">
                    {showCreateForm && (
                        <section className="rounded-lg border border-default bg-surface p-5">
                            <h2 className="text-lg font-semibold text-on-surface mb-3">{labels.createTitle}</h2>
                            <div className="grid gap-4 md:grid-cols-2">
                                <label className="block">
                                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.slug}</span>
                                    <input
                                        className="form-control w-full border-default"
                                        value={newSlug}
                                        onChange={e => setNewSlug(e.target.value)}
                                    />
                                    <span className="text-xs text-muted mt-1 block">{labels.slugHint}</span>
                                </label>
                                <label className="block">
                                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.pageTitle}</span>
                                    <input
                                        className="form-control w-full border-default"
                                        value={newTitle}
                                        onChange={e => setNewTitle(e.target.value)}
                                    />
                                </label>
                            </div>
                            <div className="mt-4 flex gap-2">
                                <button
                                    type="button"
                                    className="btn btn-primary disabled:opacity-60"
                                    onClick={handleCreatePage}
                                    disabled={sending || !newSlug.trim()}
                                >
                                    {labels.create}
                                </button>
                                <button
                                    type="button"
                                    className="btn btn-secondary"
                                    onClick={() => setShowCreateForm(false)}
                                >
                                    {labels.actionCancel}
                                </button>
                            </div>
                        </section>
                    )}

                    {loaded && pages.length === 0 && (
                        <div className="text-center text-muted py-8">{labels.empty}</div>
                    )}
                    {pages.length > 0 && (
                        <div className="overflow-x-auto rounded-lg border border-default">
                            <table className="admin-table">
                                <thead>
                                    <tr className="border-b border-subtle text-left">
                                        <th className="px-4 py-3">{labels.slug}</th>
                                        <th className="px-4 py-3">{labels.pageTitle}</th>
                                        <th className="px-4 py-3"></th>
                                        <th className="px-4 py-3"></th>
                                        <th className="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-subtle bg-surface">
                                    {pages.map(page => {
                                        const isOpenInTab = pageTabs.some(t => t.pageId === page.id);
                                        return (
                                            <tr key={page.id} className={isOpenInTab ? 'bg-accent-subtle' : ''}>
                                                <td className="px-4 py-3 font-mono text-xs">{page.slug}</td>
                                                <td className="px-4 py-3">{page.title_rendered || page.title}</td>
                                                <td className="px-4 py-3">
                                                    <button
                                                        type="button"
                                                        className={`inline-block rounded px-2 py-0.5 text-xs font-medium ${page.is_published ? 'status-success' : 'status-muted'}`}
                                                        onClick={() => handleTogglePublish(page)}
                                                        disabled={sending}
                                                    >
                                                        {page.is_published ? labels.published : labels.draft}
                                                    </button>
                                                </td>
                                                <td className="px-4 py-3 text-muted text-xs">{formatTs(page.updated_at)}</td>
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center gap-2">
                                                        <button
                                                            type="button"
                                                            className="text-accent hover:text-on-surface text-xs underline"
                                                            onClick={() => openPageTab(page)}
                                                        >
                                                            {labels.editPage}
                                                        </button>
                                                        <a
                                                            href={publicBaseUrl + page.slug}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-accent hover:text-on-surface text-xs underline"
                                                        >
                                                            {labels.openPage}
                                                        </a>
                                                        <button
                                                            type="button"
                                                            className="text-danger hover:text-on-surface text-xs underline"
                                                            onClick={() => void handleDeletePage(page)}
                                                            disabled={sending}
                                                        >
                                                            {labels.actionDelete}
                                                        </button>
                                                        <EntityHistoryButton
                                                            entityType="static_page"
                                                            entityId={page.id}
                                                            className="text-accent hover:text-on-surface text-xs underline"
                                                            testIdSuffix={`page-${page.id}`}
                                                        />
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
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
                    onSnippetCreated={(s: Snippet) => {
                        setSnippets(prev => [...prev, s]);
                        openSnippetTab(s);
                    }}
                    onSnippetDeleted={handleSnippetDeleted}
                    onToggleActive={(s: Snippet) => {
                        const newActive = s.is_active ? 0 : 1;
                        void withSending(async () => {
                            try {
                                const res = await sendPost<any, {success: boolean}>(snippetUpdateUrl, {id: s.id, is_active: newActive});
                                if ((res as any)?.error) { showToast((res as any).error, 'danger'); return; }
                                setSnippets(prev => prev.map(x => x.id === s.id ? {...x, is_active: newActive} : x));
                            } catch (err: any) { showToast(err?.message ?? labels.error, 'danger'); }
                        });
                    }}
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
};
