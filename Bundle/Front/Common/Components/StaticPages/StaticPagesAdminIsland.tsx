import * as React from 'react';
import {sendPost} from '@common/Api/sendPost';
import {sendPostFormData} from '@common/Api/sendPostFormData';
import {showToast} from '@common/Components/GlobalToast';
import {ConfirmModal} from '@common/Components/ConfirmModal';
import {EntityHistoryButton} from '@common/Components/EntityHistory/EntityHistoryButton';
import {TabNav, TabDef} from '@common/Components/Navigation/TabNav';
import {useSending} from '@common/hooks/useSending';
import {useConfirm} from '@common/hooks/useConfirm';
import {formatTs} from '@common/Utils/DateUtils';
import {markdownToHtml} from '@common/Utils/markdownToHtml';
import {PageHeader} from '@common/Components/PageHeader';
import {ImageUploadArea, ImageUploadField} from '@common/Components/ImageUploadField';
import {FileText} from 'lucide-react';

interface StaticPage {
    id: number;
    slug: string;
    title: string;
    is_published: number;
    meta_description: string;
    seo_title?: string;
    og_image?: string;
    max_width: string;
    visibility: string;
    sort_order: number;
    updated_at: number;
    updated_by: number;
    created_at: number;
    header_snippet_id: number | null;
    footer_snippet_id: number | null;
}

interface Snippet {
    id: number;
    slug: string;
    name: string;
    snippet_type: string;
    content: string;
    is_active: number;
    sort_order: number;
    updated_at: number;
    created_at: number;
}

interface PageBlock {
    id: number;
    page_id: number;
    block_type: string;
    content: string;
    sort_order: number;
    is_hidden: number;
    visibility: string;
    created_at: number;
}

interface Labels {
    title: string;
    empty: string;
    create: string;
    createTitle: string;
    slug: string;
    slugHint: string;
    pageTitle: string;
    metaDescription: string;
    published: string;
    draft: string;
    publish: string;
    unpublish: string;
    deleteConfirm: string;
    blocks: string;
    addBlock: string;
    blockTypeHeading: string;
    blockTypeText: string;
    blockTypeImage: string;
    blockTypeGallery: string;
    imageAlt: string;
    imageLightbox: string;
    galleryRows: string;
    uploadImage: string;
    removeImage: string;
    blockHidden: string;
    blockVisible: string;
    deleteBlockConfirm: string;
    variables: string;
    moveUp: string;
    moveDown: string;
    openPage: string;
    savePage: string;
    editPage: string;
    maxWidth: string;
    visibility: string;
    visibilityAll: string;
    visibilityAuth: string;
    visibilityGuest: string;
    visibilityModerator: string;
    blockVisibility: string;
    actionDelete: string;
    actionCancel: string;
    actionClose: string;
    error: string;
    // Snippets
    snippets: string;
    snippetsEmpty: string;
    snippetsCreate: string;
    snippetsCreateTitle: string;
    snippetsName: string;
    snippetsSlug: string;
    snippetsType: string;
    snippetsTypeHeader: string;
    snippetsTypeFooter: string;
    snippetsTypeVariable: string;
    snippetsTypeBlock: string;
    snippetsActive: string;
    snippetsInactive: string;
    snippetsDeleteConfirm: string;
    snippetsUsageHint: string;
    snippetsEditTitle: string;
    headerSnippet: string;
    footerSnippet: string;
    noSnippet: string;
    snippetsFilterAll: string;
    // Structured snippet editor labels
    snippetsLogo: string;
    snippetsLogoAlt: string;
    snippetsLogoLink: string;
    snippetsLogoHeight: string;
    snippetsMenuItems: string;
    snippetsAddItem: string;
    snippetsItemTypeLink: string;
    snippetsItemTypePage: string;
    snippetsItemTypeDivider: string;
    snippetsItemLabel: string;
    snippetsItemUrl: string;
    snippetsItemExternal: string;
    snippetsLayout: string;
    snippetsLayoutLeft: string;
    snippetsLayoutCenter: string;
    snippetsLayoutMinimal: string;
    snippetsSticky: string;
    snippetsColumns: string;
    snippetsAddColumn: string;
    snippetsColumnTitle: string;
    snippetsCopyright: string;
    snippetsLayoutColumns: string;
    snippetsLayoutSimple: string;
    snippetsRemoveColumn: string;
    snippetsSelectPage: string;
}

interface Props {
    listUrl: string;
    createUrl: string;
    updateUrl: string;
    deleteUrl: string;
    blocksUrl: string;
    saveBlocksUrl: string;
    variablesUrl: string;
    uploadImageUrl: string;
    deleteImageUrl: string;
    snippetsListUrl: string;
    snippetCreateUrl: string;
    snippetUpdateUrl: string;
    snippetDeleteUrl: string;
    headerFooterSnippetsUrl: string;
    publicBaseUrl: string;
    labels: Labels;
    // Legacy props (still passed from backend, unused by frontend)
    addBlockUrl?: string;
    updateBlockUrl?: string;
    deleteBlockUrl?: string;
    reorderBlocksUrl?: string;
}

// ── Tab infrastructure ──

interface PageTabInfo {
    id: string;
    pageId: number;
    title: string;
}

interface SnippetTabInfo {
    id: string;
    snippetId: number;
    title: string;
}

const STATIC_TABS = ['pages', 'snippets'] as const;
type StaticTabId = typeof STATIC_TABS[number];

const SNIPPET_TYPES = ['header', 'footer', 'variable', 'block'] as const;

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
                        + {labels.create}
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
                                                <td className="px-4 py-3">{page.title}</td>
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

// ── PageEditorTab — self-contained per-tab editor with local-first editing ──

interface PageEditorTabProps {
    pageId: number;
    initialPage: StaticPage | null;
    labels: Labels;
    updateUrl: string;
    deleteUrl: string;
    blocksUrl: string;
    saveBlocksUrl: string;
    uploadImageUrl: string;
    deleteImageUrl: string;
    headerFooterSnippetsUrl: string;
    publicBaseUrl: string;
    templateVariables: string[];
    pages: StaticPage[];
    onPageUpdated: (page: StaticPage) => void;
    onPageDeleted: (pageId: number) => void;
}

const PageEditorTab: React.FC<PageEditorTabProps> = ({
    pageId, initialPage, labels,
    updateUrl, deleteUrl, blocksUrl, saveBlocksUrl,
    uploadImageUrl, deleteImageUrl,
    headerFooterSnippetsUrl,
    publicBaseUrl, templateVariables, pages,
    onPageUpdated, onPageDeleted,
}) => {
    const [editingPage, setEditingPage] = React.useState<StaticPage | null>(initialPage ? {...initialPage} : null);
    const [localBlocks, setLocalBlocks] = React.useState<PageBlock[]>([]);
    const [nextTempId, setNextTempId] = React.useState(-1);
    const [blocksDirty, setBlocksDirty] = React.useState(false);
    const [headerSnippetOptions, setHeaderSnippetOptions] = React.useState<Snippet[]>([]);
    const [footerSnippetOptions, setFooterSnippetOptions] = React.useState<Snippet[]>([]);

    const {sending, withSending} = useSending();
    const {confirmState, confirm, handleConfirm, handleCancel} = useConfirm();

    // Sync initialPage when parent updates it (e.g. publish toggle from list)
    React.useEffect(() => {
        if (initialPage) {
            setEditingPage(prev => {
                if (!prev) return {...initialPage};
                // Only sync fields that are not user-editable locally (updated_at, is_published from list toggle)
                // Avoid overwriting in-progress edits
                return prev;
            });
        }
    }, [initialPage]);

    React.useEffect(() => {
        void loadBlocks();
        void loadHeaderFooterSnippets();
    }, [pageId]);

    const loadHeaderFooterSnippets = async () => {
        try {
            const res = await sendPost<{}, {headers: Snippet[]; footers: Snippet[]}>(headerFooterSnippetsUrl, {});
            if ((res as any)?.error) return;
            setHeaderSnippetOptions(res.headers ?? []);
            setFooterSnippetOptions(res.footers ?? []);
        } catch {
            // silent
        }
    };

    const loadBlocks = async () => {
        try {
            const res = await sendPost<{page_id: number}, {blocks: PageBlock[]}>(blocksUrl, {page_id: pageId});
            if ((res as any)?.error) return;
            setLocalBlocks(res.blocks ?? []);
            setBlocksDirty(false);
        } catch {
            // silent
        }
    };

    // ── Type picker state for "+" buttons ──
    const [addingAtPosition, setAddingAtPosition] = React.useState<number | null>(null);

    const addBlockAtPosition = (position: number, blockType: string) => {
        if (!editingPage) return;
        const newBlock: PageBlock = {
            id: nextTempId,
            page_id: editingPage.id,
            block_type: blockType,
            content: blockType === 'gallery' ? '{"images":[],"lightbox":true,"rows":2}' : '',
            sort_order: position,
            is_hidden: 0,
            visibility: 'all',
            created_at: 0,
        };
        setNextTempId(prev => prev - 1);

        const updated = [...localBlocks];
        updated.splice(position, 0, newBlock);
        // Reindex sort_order
        updated.forEach((b, i) => b.sort_order = i);
        setLocalBlocks(updated);
        setBlocksDirty(true);
        setAddingAtPosition(null);
    };

    // ── Local block operations ──

    const handleUpdateBlock = (block: PageBlock, fields: Partial<PageBlock>) => {
        setLocalBlocks(prev => prev.map(b => b.id === block.id ? {...b, ...fields} : b));
        setBlocksDirty(true);
    };

    const handleDeleteBlock = async (block: PageBlock) => {
        const ok = await confirm(
            labels.deleteBlockConfirm,
            {variant: 'danger', confirmLabel: labels.actionDelete},
        );
        if (!ok) return;
        setLocalBlocks(prev => {
            const filtered = prev.filter(b => b.id !== block.id);
            filtered.forEach((b, i) => b.sort_order = i);
            return filtered;
        });
        setBlocksDirty(true);
    };

    const handleMoveBlock = (index: number, direction: -1 | 1) => {
        const newBlocks = [...localBlocks];
        const targetIndex = index + direction;
        if (targetIndex < 0 || targetIndex >= newBlocks.length) return;
        [newBlocks[index], newBlocks[targetIndex]] = [newBlocks[targetIndex], newBlocks[index]];
        newBlocks.forEach((b, i) => b.sort_order = i);
        setLocalBlocks(newBlocks);
        setBlocksDirty(true);
    };

    const handleToggleBlockVisibility = (block: PageBlock) => {
        handleUpdateBlock(block, {is_hidden: block.is_hidden ? 0 : 1});
    };

    // ── Save: page metadata + all blocks ──

    const handleSave = () => {
        if (!editingPage) return;
        void withSending(async () => {
            try {
                // Save page fields
                const pageRes = await sendPost<any, {success: boolean}>(updateUrl, {
                    id: editingPage.id,
                    title: editingPage.title,
                    slug: editingPage.slug,
                    meta_description: editingPage.meta_description,
                    seo_title: editingPage.seo_title ?? '',
                    og_image: editingPage.og_image ?? '',
                    is_published: editingPage.is_published,
                    sort_order: editingPage.sort_order,
                    max_width: editingPage.max_width,
                    visibility: editingPage.visibility,
                    header_snippet_id: editingPage.header_snippet_id ?? '',
                    footer_snippet_id: editingPage.footer_snippet_id ?? '',
                });
                if ((pageRes as any)?.error) {
                    showToast((pageRes as any).error, 'danger');
                    return;
                }

                // Save blocks
                const blocksRes = await sendPost<any, {success: boolean; blocks: PageBlock[]}>(saveBlocksUrl, {
                    page_id: editingPage.id,
                    blocks: JSON.stringify(localBlocks),
                });
                if ((blocksRes as any)?.error) {
                    showToast((blocksRes as any).error, 'danger');
                    return;
                }
                if (blocksRes.blocks) {
                    setLocalBlocks(blocksRes.blocks);
                }
                setBlocksDirty(false);

                onPageUpdated(editingPage);
                showToast('OK', 'success');
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    const handleDeletePage = async () => {
        if (!editingPage) return;
        const ok = await confirm(
            labels.deleteConfirm,
            {variant: 'danger', confirmLabel: labels.actionDelete},
        );
        if (!ok) return;
        void withSending(async () => {
            try {
                const res = await sendPost<{id: number}, {success: boolean}>(deleteUrl, {id: editingPage.id});
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                onPageDeleted(editingPage.id);
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    if (!editingPage) {
        return <div className="text-center text-muted py-8">...</div>;
    }

    return (
        <>
            <PageEditor
                page={editingPage}
                blocks={localBlocks}
                templateVariables={templateVariables}
                labels={labels}
                sending={sending}
                blocksDirty={blocksDirty}
                uploadImageUrl={uploadImageUrl}
                deleteImageUrl={deleteImageUrl}
                publicBaseUrl={publicBaseUrl}
                headerSnippetOptions={headerSnippetOptions}
                footerSnippetOptions={footerSnippetOptions}
                pages={pages}
                addingAtPosition={addingAtPosition}
                onSetAddingAtPosition={setAddingAtPosition}
                onPageChange={setEditingPage}
                onSave={handleSave}
                onDelete={() => void handleDeletePage()}
                onAddBlock={addBlockAtPosition}
                onUpdateBlock={handleUpdateBlock}
                onDeleteBlock={handleDeleteBlock}
                onMoveBlock={handleMoveBlock}
                onToggleBlockVisibility={handleToggleBlockVisibility}
            />
            <ConfirmModal state={confirmState} onConfirm={handleConfirm} onCancel={handleCancel} />
        </>
    );
};

// ── Page Editor ──

interface PageEditorProps {
    page: StaticPage;
    blocks: PageBlock[];
    templateVariables: string[];
    labels: Labels;
    sending: boolean;
    blocksDirty: boolean;
    uploadImageUrl: string;
    deleteImageUrl: string;
    publicBaseUrl: string;
    headerSnippetOptions: Snippet[];
    footerSnippetOptions: Snippet[];
    pages: StaticPage[];
    addingAtPosition: number | null;
    onSetAddingAtPosition: (pos: number | null) => void;
    onPageChange: (page: StaticPage) => void;
    onSave: () => void;
    onDelete: () => void;
    onAddBlock: (position: number, blockType: string) => void;
    onUpdateBlock: (block: PageBlock, fields: Partial<PageBlock>) => void;
    onDeleteBlock: (block: PageBlock) => void;
    onMoveBlock: (index: number, direction: -1 | 1) => void;
    onToggleBlockVisibility: (block: PageBlock) => void;
}

const BLOCK_TYPE_LABELS: Record<string, (l: Labels) => string> = {
    text: l => l.blockTypeText,
    gallery: l => l.blockTypeGallery,
};

const PageEditor: React.FC<PageEditorProps> = ({
    page, blocks, templateVariables, labels, sending, blocksDirty,
    uploadImageUrl, deleteImageUrl, publicBaseUrl,
    headerSnippetOptions, footerSnippetOptions, pages,
    addingAtPosition, onSetAddingAtPosition,
    onPageChange, onSave, onDelete,
    onAddBlock, onUpdateBlock, onDeleteBlock, onMoveBlock, onToggleBlockVisibility,
}) => {
    const update = (field: keyof StaticPage, value: string | number) => {
        onPageChange({...page, [field]: value});
    };

    const renderAddSeparator = (position: number) => {
        if (addingAtPosition === position) {
            return (
                <div className="flex justify-center gap-2 my-2">
                    <button
                        type="button"
                        className="btn btn-sm btn-secondary"
                        onClick={() => onAddBlock(position, 'text')}
                    >
                        {labels.blockTypeText}
                    </button>
                    <button
                        type="button"
                        className="btn btn-sm btn-secondary"
                        onClick={() => onAddBlock(position, 'gallery')}
                    >
                        {labels.blockTypeGallery}
                    </button>
                    <button
                        type="button"
                        className="btn btn-sm btn-secondary"
                        onClick={() => onSetAddingAtPosition(null)}
                    >
                        {labels.actionCancel}
                    </button>
                </div>
            );
        }
        return (
            <div className="blk-add-separator my-1">
                <button
                    type="button"
                    className="blk-add-btn"
                    onClick={() => onSetAddingAtPosition(position)}
                    title={labels.addBlock}
                >
                    +
                </button>
            </div>
        );
    };

    return (
        <section className="section-soft rounded-lg border border-default p-5 space-y-5">
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <h2 className="text-lg font-semibold text-on-surface">
                        {labels.editPage}: {page.slug}
                    </h2>
                    <a
                        href={publicBaseUrl + page.slug}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-accent text-sm hover:underline"
                    >
                        {labels.openPage} &#8599;
                    </a>
                </div>
                <button
                    type="button"
                    className="btn btn-danger btn-sm"
                    onClick={onDelete}
                    disabled={sending}
                >
                    {labels.actionDelete}
                </button>
            </div>

            <div className="grid gap-4 md:grid-cols-2">
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.pageTitle}</span>
                    <input
                        className="form-control w-full border-default"
                        value={page.title}
                        onChange={e => update('title', e.target.value)}
                    />
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.slug}</span>
                    <input
                        className="form-control w-full border-default"
                        value={page.slug}
                        onChange={e => update('slug', e.target.value)}
                    />
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.metaDescription}</span>
                    <input
                        className="form-control w-full border-default"
                        value={page.meta_description}
                        onChange={e => update('meta_description', e.target.value)}
                    />
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">SEO title</span>
                    <input
                        className="form-control w-full border-default"
                        value={page.seo_title ?? ''}
                        placeholder={page.title}
                        onChange={e => update('seo_title', e.target.value)}
                    />
                </label>
                <div className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">OG image (social preview)</span>
                    <span className="mb-2 block text-xs text-secondary">preview image for Telegram/WhatsApp; if empty, the default image is used</span>
                    <ImageUploadField
                        value={page.og_image ?? ''}
                        onChange={url => update('og_image', url)}
                        uploadUrl={uploadImageUrl}
                        deleteUrl={deleteImageUrl}
                        uploadLabel={labels.uploadImage}
                        removeLabel={labels.removeImage}
                        removeConfirm={labels.removeImage}
                        errorLabel={labels.error}
                        previewAlt={page.title}
                        disabled={sending}
                    />
                </div>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.maxWidth}</span>
                    <select
                        className="form-control w-full border-default"
                        value={page.max_width || '3xl'}
                        onChange={e => update('max_width', e.target.value)}
                    >
                        <option value="xl">xl — 576px</option>
                        <option value="2xl">2xl — 672px</option>
                        <option value="3xl">3xl — 768px</option>
                        <option value="4xl">4xl — 896px</option>
                        <option value="5xl">5xl — 1024px</option>
                        <option value="6xl">6xl — 1152px</option>
                        <option value="7xl">7xl — 1280px</option>
                        <option value="full">full — 100%</option>
                    </select>
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.visibility}</span>
                    <select
                        className="form-control w-full border-default"
                        value={page.visibility || 'all'}
                        onChange={e => update('visibility', e.target.value)}
                    >
                        <option value="all">{labels.visibilityAll}</option>
                        <option value="guest">{labels.visibilityGuest}</option>
                        <option value="auth">{labels.visibilityAuth}</option>
                        <option value="moderator">{labels.visibilityModerator}</option>
                    </select>
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.headerSnippet}</span>
                    <select
                        className="form-control w-full border-default"
                        value={page.header_snippet_id ?? ''}
                        onChange={e => {
                            const val = e.target.value;
                            onPageChange({...page, header_snippet_id: val ? Number(val) : null});
                        }}
                    >
                        <option value="">{labels.noSnippet}</option>
                        {headerSnippetOptions.map(s => (
                            <option key={s.id} value={s.id}>{s.name || s.slug}</option>
                        ))}
                    </select>
                </label>
                <label className="block">
                    <span className="mb-1 block text-sm font-medium text-on-surface">{labels.footerSnippet}</span>
                    <select
                        className="form-control w-full border-default"
                        value={page.footer_snippet_id ?? ''}
                        onChange={e => {
                            const val = e.target.value;
                            onPageChange({...page, footer_snippet_id: val ? Number(val) : null});
                        }}
                    >
                        <option value="">{labels.noSnippet}</option>
                        {footerSnippetOptions.map(s => (
                            <option key={s.id} value={s.id}>{s.name || s.slug}</option>
                        ))}
                    </select>
                </label>
            </div>

            <div>
                <h3 className="text-base font-semibold text-on-surface mb-3">{labels.blocks}</h3>

                {renderAddSeparator(0)}

                {blocks.map((block, idx) => (
                    <React.Fragment key={block.id}>
                        <BlockEditor
                            block={block}
                            index={idx}
                            total={blocks.length}
                            labels={labels}
                            disabled={sending}
                            uploadImageUrl={uploadImageUrl}
                            deleteImageUrl={deleteImageUrl}
                            pages={pages}
                            onUpdate={onUpdateBlock}
                            onDelete={onDeleteBlock}
                            onMove={onMoveBlock}
                            onToggleVisibility={onToggleBlockVisibility}
                        />
                        {renderAddSeparator(idx + 1)}
                    </React.Fragment>
                ))}

                {blocks.length === 0 && (
                    <div className="text-center text-muted py-4 text-sm">{labels.empty}</div>
                )}
            </div>

            {templateVariables.length > 0 && (
                <div className="text-xs text-muted">
                    {labels.variables}
                </div>
            )}

            <div className="flex items-center gap-4 pt-4 border-t border-subtle">
                <button
                    type="button"
                    className="btn btn-primary btn-lg disabled:opacity-60"
                    onClick={onSave}
                    disabled={sending}
                >
                    {labels.savePage}
                </button>

                {blocksDirty && (
                    <span className="text-sm text-warning">&#9679;</span>
                )}

                <label className="flex items-center gap-2 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={!!page.is_published}
                        onChange={e => update('is_published', e.target.checked ? 1 : 0)}
                    />
                    <span className="text-sm font-medium text-on-surface">{labels.published}</span>
                </label>
            </div>
        </section>
    );
};

// ── Block Editor ──

interface BlockEditorProps {
    block: PageBlock;
    index: number;
    total: number;
    labels: Labels;
    disabled: boolean;
    uploadImageUrl: string;
    deleteImageUrl: string;
    pages?: StaticPage[];
    onUpdate: (block: PageBlock, fields: Partial<PageBlock>) => void;
    onDelete: (block: PageBlock) => void;
    onMove: (index: number, direction: -1 | 1) => void;
    onToggleVisibility: (block: PageBlock) => void;
}

// ── Block Editor ──

const BlockEditor: React.FC<BlockEditorProps> =({block, index, total, labels, disabled, uploadImageUrl, deleteImageUrl, pages, onUpdate, onDelete, onMove, onToggleVisibility}) => {
    const [localContent, setLocalContent] = React.useState(block.content);
    const [showPreview, setShowPreview] = React.useState(false);
    const [showPagePicker, setShowPagePicker] = React.useState(false);
    const [imgUploading, setImgUploading] = React.useState(false);
    const [pendingDeleteImg, setPendingDeleteImg] = React.useState<{url: string; action: () => Promise<void>} | null>(null);
    const textareaRef = React.useRef<HTMLTextAreaElement>(null);

    React.useEffect(() => {
        if (!showPagePicker) return;
        const close = () => setShowPagePicker(false);
        document.addEventListener('click', close);
        return () => document.removeEventListener('click', close);
    }, [showPagePicker]);

    React.useEffect(() => {
        setLocalContent(block.content);
    }, [block.content]);

    // Propagate local text changes to parent state
    const propagateContent = (content: string) => {
        setLocalContent(content);
        onUpdate(block, {content});
    };

    const insertMarkdown = (before: string, after: string) => {
        const ta = textareaRef.current;
        if (!ta) return;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = localContent.substring(start, end);
        const replacement = before + (selected || 'text') + after;
        const newContent = localContent.substring(0, start) + replacement + localContent.substring(end);
        propagateContent(newContent);
        requestAnimationFrame(() => {
            ta.focus();
            const cursorPos = start + before.length + (selected || 'text').length;
            ta.setSelectionRange(cursorPos, cursorPos);
        });
    };

    // ── Image block helpers ──
    const parseJsonContent = (content: string): Record<string, any> => {
        try { return JSON.parse(content) || {}; } catch { return {}; }
    };

    const handleImageUpload = async (files: File[]) => {
        const file = files[0];
        if (!file) return;
        setImgUploading(true);
        const fd = new FormData();
        fd.append('file', file);
        try {
            const res = await sendPostFormData<FormData, {success: boolean; url: string}>(uploadImageUrl, fd);
            if (res?.url) {
                const imgData = parseJsonContent(localContent);
                const data = {...imgData, url: res.url};
                const json = JSON.stringify(data);
                setLocalContent(json);
                onUpdate(block, {content: json});
            }
        } catch { showToast(labels.error, 'danger'); }
        finally { setImgUploading(false); }
    };

    const updateImageField = (field: string, value: string | boolean) => {
        const imgData = parseJsonContent(localContent);
        const data = {...imgData, [field]: value};
        const json = JSON.stringify(data);
        setLocalContent(json);
        onUpdate(block, {content: json});
    };

    const removeImage = (url: string) => {
        setPendingDeleteImg({
            url,
            action: async () => {
                const imgData = parseJsonContent(localContent);
                if (imgData.url) {
                    try { await sendPost(deleteImageUrl, {url: imgData.url}); } catch { /* silent */ }
                }
                const json = JSON.stringify({});
                setLocalContent(json);
                onUpdate(block, {content: json});
            },
        });
    };

    // ── Gallery block helpers ──
    const handleGalleryUpload = async (files: File[]) => {
        setImgUploading(true);
        try {
            const galData = parseJsonContent(localContent);
            let images: {url: string; alt: string}[] = galData.images || [];
            for (const file of files) {
                const fd = new FormData();
                fd.append('file', file);
                const res = await sendPostFormData<FormData, {success: boolean; url: string}>(uploadImageUrl, fd);
                if (res?.url) {
                    images = [...images, {url: res.url, alt: ''}];
                }
            }
            const data = {...galData, images};
            const json = JSON.stringify(data);
            setLocalContent(json);
            onUpdate(block, {content: json});
        } catch { showToast(labels.error, 'danger'); }
        finally { setImgUploading(false); }
    };

    const removeGalleryImage = (idx: number) => {
        const galData = parseJsonContent(localContent);
        const images: {url: string; alt: string}[] = galData.images || [];
        const img = images[idx];
        if (!img) return;
        setPendingDeleteImg({
            url: img.url,
            action: async () => {
                if (img.url) {
                    try { await sendPost(deleteImageUrl, {url: img.url}); } catch { /* silent */ }
                }
                const freshData = parseJsonContent(localContent);
                const freshImages: {url: string; alt: string}[] = freshData.images || [];
                const newImages = freshImages.filter((_: any, i: number) => i !== idx);
                const data = {...freshData, images: newImages};
                const json = JSON.stringify(data);
                setLocalContent(json);
                onUpdate(block, {content: json});
            },
        });
    };

    const updateGalleryField = (field: string, value: any) => {
        const galData = parseJsonContent(localContent);
        const data = {...galData, [field]: value};
        const json = JSON.stringify(data);
        setLocalContent(json);
        onUpdate(block, {content: json});
    };

    const updateGalleryImageAlt = (idx: number, alt: string) => {
        const galData = parseJsonContent(localContent);
        const images: {url: string; alt: string}[] = galData.images || [];
        const newImages = images.map((img: {url: string; alt: string}, i: number) => i === idx ? {...img, alt} : img);
        updateGalleryField('images', newImages);
    };

    // ── Block type label ──
    const blockTypeLabel = (BLOCK_TYPE_LABELS[block.block_type] ?? (() => block.block_type))(labels);

    // ── Render block body ──
    const renderBlockBody = () => {
        if (block.block_type === 'heading') {
            return (
                <input
                    className="form-control w-full border-default"
                    value={localContent}
                    onChange={e => propagateContent(e.target.value)}
                    disabled={disabled}
                />
            );
        }

        if (block.block_type === 'image') {
            const imgData = parseJsonContent(localContent);
            return imgData.url ? (
                <div className="space-y-2">
                    <div className="blk-img-preview">
                        <img src={imgData.url} alt={imgData.alt || ''} className="blk-img-preview-img" />
                        <button type="button" className="blk-img-preview-remove" onClick={() => removeImage(imgData.url)} title={labels.removeImage}>&#215;</button>
                    </div>
                    <input
                        className="form-control w-full border-default text-sm"
                        placeholder={labels.imageAlt}
                        value={imgData.alt || ''}
                        onChange={e => updateImageField('alt', e.target.value)}
                    />
                    <label className="flex items-center gap-2 text-sm text-secondary cursor-pointer">
                        <input type="checkbox" checked={!!imgData.lightbox} onChange={e => updateImageField('lightbox', e.target.checked)} />
                        {labels.imageLightbox}
                    </label>
                </div>
            ) : (
                <ImageUploadArea onUpload={files => void handleImageUpload(files)} uploading={imgUploading} label={labels.uploadImage} />
            );
        }

        if (block.block_type === 'gallery') {
            const galData = parseJsonContent(localContent);
            const images: {url: string; alt: string}[] = galData.images || [];
            return (
                <div className="space-y-3">
                    {images.length > 0 && (
                        <div className="blk-gal-grid">
                            {images.map((img: {url: string; alt: string}, idx: number) => (
                                <div key={idx} className="blk-gal-thumb">
                                    <img src={img.url} alt={img.alt || ''} className="blk-gal-thumb-img" />
                                    <button
                                        type="button"
                                        className="blk-gal-thumb-remove"
                                        onClick={() => void removeGalleryImage(idx)}
                                        title={labels.removeImage}
                                    >
                                        &#215;
                                    </button>
                                    <input
                                        className="form-control w-full border-default text-xs p-1"
                                        placeholder={labels.imageAlt}
                                        value={img.alt || ''}
                                        onChange={e => updateGalleryImageAlt(idx, e.target.value)}
                                    />
                                </div>
                            ))}
                        </div>
                    )}
                    <ImageUploadArea onUpload={files => void handleGalleryUpload(files)} uploading={imgUploading} label={labels.uploadImage} multiple />
                    <div className="flex items-center gap-4">
                        <label className="flex items-center gap-2 text-sm text-secondary">
                            {labels.galleryRows}
                            <input
                                type="number"
                                min={1}
                                max={10}
                                className="form-control form-control-sm w-16 border-default"
                                value={galData.rows ?? 2}
                                onChange={e => updateGalleryField('rows', Math.max(1, Math.min(10, parseInt(e.target.value, 10) || 2)))}
                            />
                        </label>
                        <label className="flex items-center gap-2 text-sm text-secondary cursor-pointer">
                            <input type="checkbox" checked={galData.lightbox !== false} onChange={e => updateGalleryField('lightbox', e.target.checked)} />
                            {labels.imageLightbox}
                        </label>
                    </div>
                </div>
            );
        }

        // Default: text block
        return (
            <>
                <div className="flex items-center gap-0.5 mb-1 flex-wrap">
                    <button type="button" className="blk-fmt-btn font-bold"
                        onClick={() => insertMarkdown('**', '**')} title="Bold">B</button>
                    <button type="button" className="blk-fmt-btn italic"
                        onClick={() => insertMarkdown('*', '*')} title="Italic">I</button>
                    <button type="button" className="blk-fmt-btn text-xs"
                        onClick={() => insertMarkdown('## ', '')} title="Heading 2">H2</button>
                    <button type="button" className="blk-fmt-btn text-xs"
                        onClick={() => insertMarkdown('### ', '')} title="Heading 3">H3</button>
                    <span className="mx-0.5 h-5 border-l border-subtle" />
                    <button type="button" className="blk-fmt-btn text-xs"
                        onClick={() => insertMarkdown('[', '](url)')} title="Link">Link</button>
                    {pages && pages.length > 0 && (
                        <div className="relative inline-block">
                            <button type="button" className="blk-fmt-btn text-xs"
                                onClick={(e) => { e.stopPropagation(); setShowPagePicker(!showPagePicker); }} title="Page link">
                                Page
                            </button>
                            {showPagePicker && (
                                <div className="absolute left-0 top-full mt-1 z-10 bg-surface border border-default rounded-lg shadow-lg py-1 min-w-48 max-h-48 overflow-y-auto">
                                    {pages.filter(p => p.is_published).map(p => (
                                        <button
                                            key={p.id}
                                            type="button"
                                            className="block w-full text-left px-3 py-1.5 text-sm text-on-surface hover:bg-surface-hover transition-colors"
                                            onClick={() => {
                                                insertMarkdown('{link:' + p.slug + '}', '');
                                                setShowPagePicker(false);
                                            }}
                                        >
                                            {p.title || p.slug}
                                        </button>
                                    ))}
                                    {pages.filter(p => p.is_published).length === 0 && (
                                        <div className="px-3 py-1.5 text-sm text-muted">{labels.empty}</div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                    <button type="button" className="blk-fmt-btn text-xs"
                        onClick={() => insertMarkdown('- ', '')} title="List">List</button>
                    <button type="button" className="blk-fmt-btn text-xs"
                        onClick={() => insertMarkdown('1. ', '')} title="Ordered list">OL</button>
                    <span className="mx-0.5 h-5 border-l border-subtle" />
                    <button type="button" className="blk-fmt-btn text-xs"
                        onClick={() => insertMarkdown('> ', '')} title="Quote">Quote</button>
                    <button type="button" className="blk-fmt-btn text-xs"
                        onClick={() => insertMarkdown('\n---\n', '')} title="Horizontal rule">HR</button>
                    <span className="mx-0.5 h-5 border-l border-subtle" />
                    <div className="blk-seg-toggle">
                        <button type="button"
                            className={`blk-seg-toggle-btn ${!showPreview ? 'blk-seg-toggle-active' : 'blk-seg-toggle-inactive'}`}
                            onClick={() => setShowPreview(false)}>
                            Edit
                        </button>
                        <button type="button"
                            className={`blk-seg-toggle-btn ${showPreview ? 'blk-seg-toggle-active' : 'blk-seg-toggle-inactive'}`}
                            onClick={() => setShowPreview(true)}>
                            Preview
                        </button>
                    </div>
                </div>
                {showPreview ? (
                    <div className="md-preview"
                         dangerouslySetInnerHTML={{__html: markdownToHtml(localContent)}} />
                ) : (
                    <textarea
                        ref={textareaRef}
                        className="form-control w-full border-default font-mono text-sm"
                        rows={8}
                        value={localContent}
                        onChange={e => propagateContent(e.target.value)}
                        disabled={disabled}
                    />
                )}
            </>
        );
    };

    return (
        <div className={`blk-card ${block.is_hidden ? 'blk-card-dimmed' : ''}`}>
            <div className="blk-header">
                <span className="text-xs font-medium text-secondary px-2 py-1">
                    {blockTypeLabel}
                </span>

                <button
                    type="button"
                    className="blk-icon-btn"
                    onClick={() => onMove(index, -1)}
                    disabled={disabled || index === 0}
                    title={labels.moveUp}
                >
                    &#8593;
                </button>
                <button
                    type="button"
                    className="blk-icon-btn"
                    onClick={() => onMove(index, 1)}
                    disabled={disabled || index === total - 1}
                    title={labels.moveDown}
                >
                    &#8595;
                </button>

                <div className="ml-auto flex items-center gap-1">
                    <select
                        className="form-select form-select-sm text-xs"
                        style={{width: 'auto', minWidth: '90px'}}
                        value={block.visibility || 'all'}
                        onChange={e => onUpdate(block, {visibility: e.target.value} as any)}
                        disabled={disabled}
                        title={labels.blockVisibility}
                    >
                        <option value="all">{labels.visibilityAll}</option>
                        <option value="guest">{labels.visibilityGuest}</option>
                        <option value="auth">{labels.visibilityAuth}</option>
                        <option value="moderator">{labels.visibilityModerator}</option>
                    </select>
                    <button
                        type="button"
                        className={`blk-vis-chip ${block.is_hidden ? 'blk-vis-chip-off' : 'blk-vis-chip-on'}`}
                        onClick={() => onToggleVisibility(block)}
                        disabled={disabled}
                    >
                        {block.is_hidden ? labels.blockHidden : labels.blockVisible}
                    </button>
                    <button
                        type="button"
                        className="blk-icon-btn-danger"
                        onClick={() => void onDelete(block)}
                        disabled={disabled}
                        title={labels.actionDelete}
                    >
                        &#215;
                    </button>
                </div>
            </div>

            {renderBlockBody()}

            {pendingDeleteImg && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" onClick={() => setPendingDeleteImg(null)}>
                    <div className="bg-surface rounded-lg border border-default p-5 max-w-sm w-full shadow-xl" onClick={e => e.stopPropagation()}>
                        <div className="flex justify-center mb-4">
                            <img src={pendingDeleteImg.url} alt="" className="max-h-40 rounded-lg" />
                        </div>
                        <p className="text-sm text-on-surface text-center mb-4">{labels.removeImage}?</p>
                        <div className="flex justify-center gap-2">
                            <button
                                type="button"
                                className="btn btn-danger"
                                onClick={() => { void pendingDeleteImg.action(); setPendingDeleteImg(null); }}
                            >
                                {labels.actionDelete}
                            </button>
                            <button type="button" className="btn btn-secondary" onClick={() => setPendingDeleteImg(null)}>
                                {labels.actionCancel}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

// ── Snippets List Panel ──

interface SnippetsListPanelProps {
    snippets: Snippet[];
    loaded: boolean;
    labels: Labels;
    snippetCreateUrl: string;
    snippetUpdateUrl: string;
    snippetDeleteUrl: string;
    sending: boolean;
    withSending: (fn: () => Promise<void>) => void;
    confirm: (message: string, opts?: {variant?: 'danger'; confirmLabel?: string}) => Promise<boolean>;
    onSnippetCreated: (s: Snippet) => void;
    onSnippetDeleted: (id: number) => void;
    onToggleActive: (s: Snippet) => void;
    onOpenSnippet: (s: Snippet) => void;
    snippetTabs: SnippetTabInfo[];
}

const SNIPPET_TYPE_LABELS: Record<string, (l: Labels) => string> = {
    header: l => l.snippetsTypeHeader,
    footer: l => l.snippetsTypeFooter,
    variable: l => l.snippetsTypeVariable,
    block: l => l.snippetsTypeBlock,
};

const SnippetsListPanel: React.FC<SnippetsListPanelProps> = ({
    snippets, loaded, labels,
    snippetCreateUrl, snippetDeleteUrl,
    sending, withSending, confirm,
    onSnippetCreated, onSnippetDeleted, onToggleActive, onOpenSnippet,
    snippetTabs,
}) => {
    const [showCreateForm, setShowCreateForm] = React.useState(false);
    const [newSlug, setNewSlug] = React.useState('');
    const [newName, setNewName] = React.useState('');
    const [newType, setNewType] = React.useState<string>('block');
    const [filterType, setFilterType] = React.useState<string>('');

    const handleCreate = () => {
        if (!newSlug.trim()) return;
        void withSending(async () => {
            try {
                const res = await sendPost<any, {success: boolean; snippet: Snippet}>(snippetCreateUrl, {
                    slug: newSlug.trim(),
                    name: newName.trim(),
                    snippet_type: newType,
                });
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                onSnippetCreated(res.snippet);
                setNewSlug('');
                setNewName('');
                setNewType('block');
                setShowCreateForm(false);
                showToast('OK', 'success');
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    const handleDelete = async (s: Snippet) => {
        const ok = await confirm(
            labels.snippetsDeleteConfirm,
            {variant: 'danger', confirmLabel: labels.actionDelete},
        );
        if (!ok) return;
        void withSending(async () => {
            try {
                const res = await sendPost<{id: number}, {success: boolean}>(snippetDeleteUrl, {id: s.id});
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                onSnippetDeleted(s.id);
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    const filtered = filterType ? snippets.filter(s => s.snippet_type === filterType) : snippets;

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold text-on-surface">{labels.snippets}</h1>
                <button
                    type="button"
                    className="btn btn-primary disabled:opacity-60"
                    onClick={() => setShowCreateForm(!showCreateForm)}
                    disabled={sending}
                >
                    + {labels.snippetsCreate}
                </button>
            </div>

            {showCreateForm && (
                <section className="rounded-lg border border-default bg-surface p-5">
                    <h2 className="text-lg font-semibold text-on-surface mb-3">{labels.snippetsCreateTitle}</h2>
                    <div className="grid gap-4 md:grid-cols-3">
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-on-surface">{labels.snippetsSlug}</span>
                            <input
                                className="form-control w-full border-default"
                                value={newSlug}
                                onChange={e => setNewSlug(e.target.value)}
                            />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-on-surface">{labels.snippetsName}</span>
                            <input
                                className="form-control w-full border-default"
                                value={newName}
                                onChange={e => setNewName(e.target.value)}
                            />
                        </label>
                        <label className="block">
                            <span className="mb-1 block text-sm font-medium text-on-surface">{labels.snippetsType}</span>
                            <select
                                className="form-control w-full border-default"
                                value={newType}
                                onChange={e => setNewType(e.target.value)}
                            >
                                {SNIPPET_TYPES.map(t => (
                                    <option key={t} value={t}>{(SNIPPET_TYPE_LABELS[t] ?? (() => t))(labels)}</option>
                                ))}
                            </select>
                        </label>
                    </div>
                    <div className="mt-4 flex gap-2">
                        <button
                            type="button"
                            className="btn btn-primary disabled:opacity-60"
                            onClick={handleCreate}
                            disabled={sending || !newSlug.trim()}
                        >
                            {labels.snippetsCreate}
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

            <div className="flex items-center gap-2 text-sm">
                <button
                    type="button"
                    className={`px-2 py-1 rounded text-xs font-medium ${!filterType ? 'status-active' : 'status-muted'}`}
                    onClick={() => setFilterType('')}
                >
                    {labels.snippetsFilterAll}
                </button>
                {SNIPPET_TYPES.map(t => (
                    <button
                        key={t}
                        type="button"
                        className={`px-2 py-1 rounded text-xs font-medium ${filterType === t ? 'status-active' : 'status-muted'}`}
                        onClick={() => setFilterType(filterType === t ? '' : t)}
                    >
                        {(SNIPPET_TYPE_LABELS[t] ?? (() => t))(labels)}
                    </button>
                ))}
            </div>

            {loaded && filtered.length === 0 && (
                <div className="text-center text-muted py-8">{labels.snippetsEmpty}</div>
            )}
            {filtered.length > 0 && (
                <div className="overflow-x-auto rounded-lg border border-default">
                    <table className="admin-table">
                        <thead>
                            <tr className="border-b border-subtle text-left">
                                <th className="px-4 py-3">{labels.snippetsSlug}</th>
                                <th className="px-4 py-3">{labels.snippetsName}</th>
                                <th className="px-4 py-3">{labels.snippetsType}</th>
                                <th className="px-4 py-3"></th>
                                <th className="px-4 py-3"></th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-subtle bg-surface">
                            {filtered.map(s => {
                                const isOpenInTab = snippetTabs.some(t => t.snippetId === s.id);
                                return (
                                    <tr key={s.id} className={isOpenInTab ? 'bg-accent-subtle' : ''}>
                                        <td className="px-4 py-3 font-mono text-xs">{s.slug}</td>
                                        <td className="px-4 py-3">{s.name}</td>
                                        <td className="px-4 py-3 text-xs">
                                            {(SNIPPET_TYPE_LABELS[s.snippet_type] ?? (() => s.snippet_type))(labels)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <button
                                                type="button"
                                                className={`inline-block rounded px-2 py-0.5 text-xs font-medium ${s.is_active ? 'status-success' : 'status-muted'}`}
                                                onClick={() => onToggleActive(s)}
                                                disabled={sending}
                                            >
                                                {s.is_active ? labels.snippetsActive : labels.snippetsInactive}
                                            </button>
                                        </td>
                                        <td className="px-4 py-3 text-muted text-xs">{formatTs(s.updated_at)}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    className="text-accent hover:text-on-surface text-xs underline"
                                                    onClick={() => onOpenSnippet(s)}
                                                >
                                                    {labels.editPage}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="text-danger hover:text-on-surface text-xs underline"
                                                    onClick={() => void handleDelete(s)}
                                                    disabled={sending}
                                                >
                                                    {labels.actionDelete}
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            <div className="text-xs text-muted">
                {labels.snippetsUsageHint}
            </div>
        </div>
    );
};

// ── Menu Item Editor (reused in header and footer) ──

interface MenuItemData {
    type: string;
    label?: string;
    url?: string;
    slug?: string;
    external?: boolean;
}

const ITEM_TYPES = ['link', 'page', 'divider'] as const;

const ITEM_TYPE_LABELS: Record<string, (l: Labels) => string> = {
    link: l => l.snippetsItemTypeLink,
    page: l => l.snippetsItemTypePage,
    divider: l => l.snippetsItemTypeDivider,
};

const MenuItemEditor: React.FC<{
    item: MenuItemData;
    index: number;
    total: number;
    onChange: (item: MenuItemData) => void;
    onDelete: () => void;
    onMove: (dir: -1 | 1) => void;
    pages: StaticPage[];
    labels: Labels;
    testId?: string;
}> = ({item, index, total, onChange, onDelete, onMove, pages, labels, testId = 'header-menu-item'}) => {
    const publishedPages = pages.filter(p => p.is_published);
    return (
        <div className="sp-menu-item-row" data-test-id={testId}>
            <select
                className="form-select form-select-sm w-28"
                value={item.type}
                onChange={e => {
                    const newType = e.target.value;
                    if (newType === 'divider') {
                        onChange({type: 'divider'});
                    } else if (newType === 'page') {
                        onChange({type: 'page', slug: '', label: ''});
                    } else {
                        onChange({type: 'link', label: item.label ?? '', url: item.url ?? '/', external: false});
                    }
                }}
            >
                {ITEM_TYPES.map(t => (
                    <option key={t} value={t}>{(ITEM_TYPE_LABELS[t] ?? (() => t))(labels)}</option>
                ))}
            </select>

            {item.type !== 'divider' && (
                <input
                    className="form-control form-control-sm"
                    style={{flex: '1 1 120px', minWidth: '120px'}}
                    placeholder={labels.snippetsItemLabel}
                    value={item.label ?? ''}
                    onChange={e => onChange({...item, label: e.target.value})}
                />
            )}

            {item.type === 'link' && (
                <>
                    <input
                        className="form-control form-control-sm"
                        style={{flex: '1 1 150px', minWidth: '150px'}}
                        placeholder={labels.snippetsItemUrl}
                        value={item.url ?? ''}
                        onChange={e => onChange({...item, url: e.target.value})}
                    />
                    <label className="flex items-center gap-1 text-xs text-secondary whitespace-nowrap cursor-pointer">
                        <input
                            type="checkbox"
                            checked={!!item.external}
                            onChange={e => onChange({...item, external: e.target.checked})}
                        />
                        {labels.snippetsItemExternal}
                    </label>
                </>
            )}

            {item.type === 'page' && (
                <select
                    className="form-select form-select-sm"
                    style={{flex: '1 1 180px', minWidth: '180px'}}
                    value={item.slug ?? ''}
                    onChange={e => onChange({...item, slug: e.target.value})}
                >
                    <option value="">{labels.snippetsSelectPage}</option>
                    {publishedPages.map(p => (
                        <option key={p.id} value={p.slug}>{p.title || p.slug}</option>
                    ))}
                </select>
            )}

            {item.type === 'divider' && <div className="flex-1" />}

            <button type="button" className="blk-icon-btn" data-test-id="move-up" onClick={() => onMove(-1)} disabled={index === 0} title={labels.moveUp}>&#8593;</button>
            <button type="button" className="blk-icon-btn" data-test-id="move-down" onClick={() => onMove(1)} disabled={index === total - 1} title={labels.moveDown}>&#8595;</button>
            <button type="button" className="blk-icon-btn-danger" data-test-id="delete-item" onClick={onDelete} title={labels.actionDelete}>&#215;</button>
        </div>
    );
};

// ── Header Editor ──

interface HeaderData {
    logo?: {url: string; alt: string; link: string; height: number};
    items: MenuItemData[];
    layout: string;
    sticky: boolean;
}

const HeaderEditor: React.FC<{
    data: HeaderData;
    onChange: (data: HeaderData) => void;
    labels: Labels;
    pages: StaticPage[];
    uploadImageUrl: string;
    deleteImageUrl: string;
}> = ({data, onChange, labels, pages, uploadImageUrl, deleteImageUrl}) => {
    const logo = data.logo || {url: '', alt: '', link: '/', height: 40};
    const items = data.items || [];
    const layout = data.layout || 'left';
    const sticky = data.sticky || false;
    const [logoUploading, setLogoUploading] = React.useState(false);

    const updateLogo = (field: string, value: string | number) => {
        onChange({...data, logo: {...logo, [field]: value}});
    };

    const handleLogoUpload = async (files: File[]) => {
        const file = files[0];
        if (!file) return;
        setLogoUploading(true);
        try {
            const fd = new FormData();
            fd.append('file', file);
            const res = await sendPostFormData<FormData, {success: boolean; url: string}>(uploadImageUrl, fd);
            if (res?.url) {
                onChange({...data, logo: {...logo, url: res.url}});
            }
        } catch { /* silent */ }
        finally { setLogoUploading(false); }
    };

    const handleLogoRemove = async () => {
        if (logo.url) {
            try { await sendPost(deleteImageUrl, {url: logo.url}); } catch { /* silent */ }
        }
        onChange({...data, logo: {...logo, url: ''}});
    };

    const updateItem = (idx: number, item: MenuItemData) => {
        const newItems = [...items];
        newItems[idx] = item;
        onChange({...data, items: newItems});
    };

    const deleteItem = (idx: number) => {
        onChange({...data, items: items.filter((_, i) => i !== idx)});
    };

    const moveItem = (idx: number, dir: -1 | 1) => {
        const target = idx + dir;
        if (target < 0 || target >= items.length) return;
        const newItems = [...items];
        [newItems[idx], newItems[target]] = [newItems[target], newItems[idx]];
        onChange({...data, items: newItems});
    };

    const addItem = () => {
        onChange({...data, items: [...items, {type: 'link', label: '', url: '/'}]});
    };

    return (
        <div className="space-y-4">
            {/* Logo section */}
            <div className="sp-editor-section" data-test-id="header-logo-section">
                <div className="sp-editor-section-title">{labels.snippetsLogo}</div>
                {logo.url ? (
                    <div className="space-y-2">
                        <div className="blk-img-preview">
                            <img src={logo.url} alt={logo.alt || ''} className="blk-img-preview-img" />
                            <button type="button" className="blk-img-preview-remove" onClick={() => void handleLogoRemove()} title={labels.removeImage}>&#215;</button>
                        </div>
                        <div className="grid gap-3 md:grid-cols-3">
                            <label className="block">
                                <span className="mb-1 block text-xs font-medium text-secondary">{labels.snippetsLogoAlt}</span>
                                <input className="form-control form-control-sm w-full" data-test-id="header-logo-alt" value={logo.alt} onChange={e => updateLogo('alt', e.target.value)} />
                            </label>
                            <label className="block">
                                <span className="mb-1 block text-xs font-medium text-secondary">{labels.snippetsLogoLink}</span>
                                <input className="form-control form-control-sm w-full" data-test-id="header-logo-link" value={logo.link} onChange={e => updateLogo('link', e.target.value)} />
                            </label>
                            <label className="block">
                                <span className="mb-1 block text-xs font-medium text-secondary">{labels.snippetsLogoHeight}</span>
                                <input type="number" className="form-control form-control-sm w-20" data-test-id="header-logo-height" min={10} max={200} value={logo.height} onChange={e => updateLogo('height', Math.max(10, Math.min(200, parseInt(e.target.value, 10) || 40)))} />
                            </label>
                        </div>
                    </div>
                ) : (
                    <ImageUploadArea onUpload={files => void handleLogoUpload(files)} uploading={logoUploading} label={labels.uploadImage} />
                )}
            </div>

            {/* Menu items */}
            <div className="sp-editor-section" data-test-id="header-menu-section">
                <div className="sp-editor-section-title">{labels.snippetsMenuItems}</div>
                {items.map((item, idx) => (
                    <MenuItemEditor
                        key={idx}
                        item={item}
                        index={idx}
                        total={items.length}
                        onChange={updated => updateItem(idx, updated)}
                        onDelete={() => deleteItem(idx)}
                        onMove={dir => moveItem(idx, dir)}
                        pages={pages}
                        labels={labels}
                    />
                ))}
                <button type="button" className="btn btn-sm btn-secondary" data-test-id="header-add-item" onClick={addItem}>+ {labels.snippetsAddItem}</button>
            </div>

            {/* Settings */}
            <div className="sp-editor-section">
                <div className="grid gap-4 md:grid-cols-2">
                    <label className="block">
                        <span className="mb-1 block text-xs font-medium text-secondary">{labels.snippetsLayout}</span>
                        <select
                            className="form-select form-select-sm w-full"
                            data-test-id="header-layout"
                            value={layout}
                            onChange={e => onChange({...data, layout: e.target.value})}
                        >
                            <option value="left">{labels.snippetsLayoutLeft}</option>
                            <option value="center">{labels.snippetsLayoutCenter}</option>
                            <option value="minimal">{labels.snippetsLayoutMinimal}</option>
                        </select>
                    </label>
                    <label className="flex items-center gap-2 cursor-pointer self-end pb-1">
                        <input type="checkbox" data-test-id="header-sticky" checked={sticky} onChange={e => onChange({...data, sticky: e.target.checked})} />
                        <span className="text-sm text-secondary">{labels.snippetsSticky}</span>
                    </label>
                </div>
            </div>
        </div>
    );
};

// ── Footer Editor ──

interface FooterData {
    columns: {title: string; items: MenuItemData[]}[];
    copyright: string;
    layout: string;
}

const FooterEditor: React.FC<{
    data: FooterData;
    onChange: (data: FooterData) => void;
    labels: Labels;
    pages: StaticPage[];
}> = ({data, onChange, labels, pages}) => {
    const columns = data.columns || [];
    const copyright = data.copyright || '';
    const layout = data.layout || 'columns';

    const updateColumn = (colIdx: number, field: string, value: any) => {
        const newCols = columns.map((c, i) => i === colIdx ? {...c, [field]: value} : c);
        onChange({...data, columns: newCols});
    };

    const addColumn = () => {
        onChange({...data, columns: [...columns, {title: '', items: []}]});
    };

    const removeColumn = (colIdx: number) => {
        onChange({...data, columns: columns.filter((_, i) => i !== colIdx)});
    };

    const updateColumnItem = (colIdx: number, itemIdx: number, item: MenuItemData) => {
        const col = columns[colIdx];
        const newItems = col.items.map((it, i) => i === itemIdx ? item : it);
        updateColumn(colIdx, 'items', newItems);
    };

    const deleteColumnItem = (colIdx: number, itemIdx: number) => {
        const col = columns[colIdx];
        updateColumn(colIdx, 'items', col.items.filter((_, i) => i !== itemIdx));
    };

    const moveColumnItem = (colIdx: number, itemIdx: number, dir: -1 | 1) => {
        const col = columns[colIdx];
        const target = itemIdx + dir;
        if (target < 0 || target >= col.items.length) return;
        const newItems = [...col.items];
        [newItems[itemIdx], newItems[target]] = [newItems[target], newItems[itemIdx]];
        updateColumn(colIdx, 'items', newItems);
    };

    const addColumnItem = (colIdx: number) => {
        const col = columns[colIdx];
        updateColumn(colIdx, 'items', [...col.items, {type: 'link', label: '', url: '/'}]);
    };

    return (
        <div className="space-y-4">
            {/* Columns */}
            <div className="sp-editor-section" data-test-id="footer-columns-section">
                <div className="sp-editor-section-title">{labels.snippetsColumns}</div>
                {columns.map((col, colIdx) => (
                    <div key={colIdx} className="sp-col-card" data-test-id="footer-column">
                        <div className="flex items-center gap-2">
                            <input
                                className="form-control form-control-sm flex-1"
                                placeholder={labels.snippetsColumnTitle}
                                value={col.title}
                                onChange={e => updateColumn(colIdx, 'title', e.target.value)}
                            />
                            <button
                                type="button"
                                className="btn btn-sm btn-danger"
                                onClick={() => removeColumn(colIdx)}
                            >
                                {labels.snippetsRemoveColumn}
                            </button>
                        </div>
                        {col.items.map((item, itemIdx) => (
                            <MenuItemEditor
                                key={itemIdx}
                                item={item}
                                index={itemIdx}
                                total={col.items.length}
                                onChange={updated => updateColumnItem(colIdx, itemIdx, updated)}
                                onDelete={() => deleteColumnItem(colIdx, itemIdx)}
                                onMove={dir => moveColumnItem(colIdx, itemIdx, dir)}
                                pages={pages}
                                testId="footer-col-item"
                                labels={labels}
                            />
                        ))}
                        <button type="button" className="btn btn-sm btn-secondary" data-test-id="footer-col-add-item" onClick={() => addColumnItem(colIdx)}>+ {labels.snippetsAddItem}</button>
                    </div>
                ))}
                <button type="button" className="btn btn-sm btn-secondary" data-test-id="footer-add-column" onClick={addColumn}>+ {labels.snippetsAddColumn}</button>
            </div>

            {/* Copyright */}
            <div className="sp-editor-section">
                <label className="block">
                    <span className="mb-1 block text-xs font-medium text-secondary">{labels.snippetsCopyright}</span>
                    <input
                        className="form-control form-control-sm w-full"
                        data-test-id="footer-copyright"
                        placeholder="&copy; {year} {title}"
                        value={copyright}
                        onChange={e => onChange({...data, copyright: e.target.value})}
                    />
                    <span className="text-xs text-muted mt-1 block">{'{year}'}, {'{title}'}, {'{base-url}'}</span>
                </label>
            </div>

            {/* Settings */}
            <div className="sp-editor-section">
                <label className="block">
                    <span className="mb-1 block text-xs font-medium text-secondary">{labels.snippetsLayout}</span>
                    <select
                        className="form-select form-select-sm w-48"
                        value={layout}
                        onChange={e => onChange({...data, layout: e.target.value})}
                    >
                        <option value="columns">{labels.snippetsLayoutColumns}</option>
                        <option value="simple">{labels.snippetsLayoutSimple}</option>
                    </select>
                </label>
            </div>
        </div>
    );
};

// ── Snippet Editor Tab ──

interface SnippetEditorTabProps {
    snippetId: number;
    initialSnippet: Snippet | null;
    labels: Labels;
    snippetUpdateUrl: string;
    snippetDeleteUrl: string;
    uploadImageUrl: string;
    deleteImageUrl: string;
    pages: StaticPage[];
    onSnippetUpdated: (snippet: Snippet) => void;
    onSnippetDeleted: (snippetId: number) => void;
}

const SnippetEditorTab: React.FC<SnippetEditorTabProps> = ({
    snippetId, initialSnippet, labels,
    snippetUpdateUrl, snippetDeleteUrl,
    uploadImageUrl, deleteImageUrl, pages,
    onSnippetUpdated, onSnippetDeleted,
}) => {
    const [snippet, setSnippet] = React.useState<Snippet | null>(initialSnippet ? {...initialSnippet} : null);
    const [localContent, setLocalContent] = React.useState(initialSnippet?.content ?? '');
    const [showPreview, setShowPreview] = React.useState(false);
    const textareaRef = React.useRef<HTMLTextAreaElement>(null);

    const {sending, withSending} = useSending();
    const {confirmState, confirm, handleConfirm, handleCancel} = useConfirm();

    React.useEffect(() => {
        if (initialSnippet) {
            setSnippet(prev => {
                if (!prev) return {...initialSnippet};
                return prev;
            });
        }
    }, [initialSnippet]);

    const isStructuredType = snippet?.snippet_type === 'header' || snippet?.snippet_type === 'footer';
    const isMarkdownType = !isStructuredType && snippet?.snippet_type !== 'variable';

    const parseStructuredContent = (): any => {
        try { return JSON.parse(localContent) || {}; } catch { return {}; }
    };

    const handleStructuredChange = (data: any) => {
        setLocalContent(JSON.stringify(data));
    };

    const insertMarkdown = (before: string, after: string) => {
        const ta = textareaRef.current;
        if (!ta) return;
        const start = ta.selectionStart;
        const end = ta.selectionEnd;
        const selected = localContent.substring(start, end);
        const replacement = before + (selected || 'text') + after;
        const newContent = localContent.substring(0, start) + replacement + localContent.substring(end);
        setLocalContent(newContent);
        requestAnimationFrame(() => {
            ta.focus();
            const cursorPos = start + before.length + (selected || 'text').length;
            ta.setSelectionRange(cursorPos, cursorPos);
        });
    };

    const handleSave = () => {
        if (!snippet) return;
        void withSending(async () => {
            try {
                const res = await sendPost<any, {success: boolean}>(snippetUpdateUrl, {
                    id: snippet.id,
                    name: snippet.name,
                    slug: snippet.slug,
                    snippet_type: snippet.snippet_type,
                    content: localContent,
                    is_active: snippet.is_active,
                    sort_order: snippet.sort_order,
                });
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                const updated = {...snippet, content: localContent, updated_at: Math.floor(Date.now() / 1000)};
                setSnippet(updated);
                onSnippetUpdated(updated);
                showToast('OK', 'success');
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    const handleDelete = async () => {
        if (!snippet) return;
        const ok = await confirm(
            labels.snippetsDeleteConfirm,
            {variant: 'danger', confirmLabel: labels.actionDelete},
        );
        if (!ok) return;
        void withSending(async () => {
            try {
                const res = await sendPost<{id: number}, {success: boolean}>(snippetDeleteUrl, {id: snippet.id});
                if ((res as any)?.error) {
                    showToast((res as any).error, 'danger');
                    return;
                }
                onSnippetDeleted(snippet.id);
            } catch (err: any) {
                showToast(err?.message ?? labels.error, 'danger');
            }
        });
    };

    if (!snippet) {
        return <div className="text-center text-muted py-8">...</div>;
    }

    const updateField = (field: keyof Snippet, value: string | number) => {
        setSnippet(prev => prev ? {...prev, [field]: value} : prev);
    };

    // When snippet_type changes to a structured type, initialize content if empty/invalid
    const handleTypeChange = (newType: string) => {
        updateField('snippet_type', newType);
        if (newType === 'header') {
            const parsed = (() => { try { return JSON.parse(localContent); } catch { return null; } })();
            if (!parsed || !Array.isArray(parsed?.items)) {
                setLocalContent(JSON.stringify({logo: {url: '', alt: '', link: '/', height: 40}, items: [], layout: 'left', sticky: false}));
            }
        } else if (newType === 'footer') {
            const parsed = (() => { try { return JSON.parse(localContent); } catch { return null; } })();
            if (!parsed || !Array.isArray(parsed?.columns)) {
                setLocalContent(JSON.stringify({columns: [], copyright: '', layout: 'columns'}));
            }
        }
    };

    const renderContentEditor = () => {
        if (snippet.snippet_type === 'header') {
            const data = parseStructuredContent();
            const headerData: HeaderData = {
                logo: data.logo || {url: '', alt: '', link: '/', height: 40},
                items: Array.isArray(data.items) ? data.items : [],
                layout: data.layout || 'left',
                sticky: !!data.sticky,
            };
            return (
                <HeaderEditor
                    data={headerData}
                    onChange={handleStructuredChange}
                    labels={labels}
                    pages={pages}
                    uploadImageUrl={uploadImageUrl}
                    deleteImageUrl={deleteImageUrl}
                />
            );
        }

        if (snippet.snippet_type === 'footer') {
            const data = parseStructuredContent();
            const footerData: FooterData = {
                columns: Array.isArray(data.columns) ? data.columns : [],
                copyright: data.copyright || '',
                layout: data.layout || 'columns',
            };
            return (
                <FooterEditor
                    data={footerData}
                    onChange={handleStructuredChange}
                    labels={labels}
                    pages={pages}
                />
            );
        }

        // Markdown / variable types
        return (
            <>
                {isMarkdownType && (
                    <div className="flex items-center gap-0.5 mb-1 flex-wrap">
                        <button type="button" className="blk-fmt-btn font-bold"
                            onClick={() => insertMarkdown('**', '**')} title="Bold">B</button>
                        <button type="button" className="blk-fmt-btn italic"
                            onClick={() => insertMarkdown('*', '*')} title="Italic">I</button>
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('## ', '')} title="Heading 2">H2</button>
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('### ', '')} title="Heading 3">H3</button>
                        <span className="mx-0.5 h-5 border-l border-subtle" />
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('[', '](url)')} title="Link">Link</button>
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('- ', '')} title="List">List</button>
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('1. ', '')} title="Ordered list">OL</button>
                        <span className="mx-0.5 h-5 border-l border-subtle" />
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('> ', '')} title="Quote">Quote</button>
                        <button type="button" className="blk-fmt-btn text-xs"
                            onClick={() => insertMarkdown('\n---\n', '')} title="Horizontal rule">HR</button>
                        <span className="mx-0.5 h-5 border-l border-subtle" />
                        <div className="blk-seg-toggle">
                            <button type="button"
                                className={`blk-seg-toggle-btn ${!showPreview ? 'blk-seg-toggle-active' : 'blk-seg-toggle-inactive'}`}
                                onClick={() => setShowPreview(false)}>
                                Edit
                            </button>
                            <button type="button"
                                className={`blk-seg-toggle-btn ${showPreview ? 'blk-seg-toggle-active' : 'blk-seg-toggle-inactive'}`}
                                onClick={() => setShowPreview(true)}>
                                Preview
                            </button>
                        </div>
                    </div>
                )}
                {showPreview && isMarkdownType ? (
                    <div className="md-preview"
                         dangerouslySetInnerHTML={{__html: markdownToHtml(localContent)}} />
                ) : (
                    <textarea
                        ref={textareaRef}
                        className="form-control w-full border-default font-mono text-sm"
                        rows={12}
                        value={localContent}
                        onChange={e => setLocalContent(e.target.value)}
                        disabled={sending}
                    />
                )}
            </>
        );
    };

    return (
        <>
            <section className="section-soft rounded-lg border border-default p-5 space-y-5">
                <div className="flex items-center justify-between gap-3">
                    <h2 className="text-lg font-semibold text-on-surface">
                        {labels.snippetsEditTitle}: {snippet.slug}
                    </h2>
                    <button
                        type="button"
                        className="btn btn-danger btn-sm"
                        onClick={() => void handleDelete()}
                        disabled={sending}
                    >
                        {labels.actionDelete}
                    </button>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium text-on-surface">{labels.snippetsName}</span>
                        <input
                            className="form-control w-full border-default"
                            value={snippet.name}
                            onChange={e => updateField('name', e.target.value)}
                        />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium text-on-surface">{labels.snippetsSlug}</span>
                        <input
                            className="form-control w-full border-default"
                            value={snippet.slug}
                            onChange={e => updateField('slug', e.target.value)}
                        />
                    </label>
                    <label className="block">
                        <span className="mb-1 block text-sm font-medium text-on-surface">{labels.snippetsType}</span>
                        <select
                            className="form-control w-full border-default"
                            value={snippet.snippet_type}
                            onChange={e => handleTypeChange(e.target.value)}
                        >
                            {SNIPPET_TYPES.map(t => (
                                <option key={t} value={t}>{(SNIPPET_TYPE_LABELS[t] ?? (() => t))(labels)}</option>
                            ))}
                        </select>
                    </label>
                </div>

                <div>
                    {renderContentEditor()}
                </div>

                <div className="flex items-center gap-4">
                    <button
                        type="button"
                        className="btn btn-primary btn-lg disabled:opacity-60"
                        onClick={handleSave}
                        disabled={sending}
                    >
                        {labels.savePage}
                    </button>
                    <label className="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={!!snippet.is_active}
                            onChange={e => updateField('is_active', e.target.checked ? 1 : 0)}
                        />
                        <span className="text-sm font-medium text-on-surface">{labels.snippetsActive}</span>
                    </label>
                </div>

                {!isStructuredType && (
                    <div className="text-xs text-muted">
                        {labels.snippetsUsageHint}
                    </div>
                )}
            </section>
            <ConfirmModal state={confirmState} onConfirm={handleConfirm} onCancel={handleCancel} />
        </>
    );
};
