import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {showToast} from '@common/Components/Feedback/GlobalToast';
import {useSending} from '@common/hooks/data/useSending';
import {useConfirm} from '@common/hooks/ui/useConfirm';
import {StaticPage, Snippet, Props, PageTabInfo, SnippetTabInfo} from './types';
import renderStaticPagesShell, {type ShellProps, type ShellState} from './values/islandState';

class StaticPagesAdmin extends AntiHookComponent<ShellProps> {
        /** Holds the island's page, snippet, and tab state. */
        private readonly ui = new Carburetor<ShellState>({
        pages: [], snippets: [], templateVariables: [], showCreateForm: false,
        newSlug: '', newTitle: '', loaded: false, snippetsLoaded: false,
        pageTabs: [], snippetTabs: [], activeTabId: null,
    });

        /** Selects the state fields consumed by the rendered shell. */
        private readonly view = this.connectSelection(
        () => this.ui,
        state => ({
            pages: state.pages, snippets: state.snippets, templateVariables: state.templateVariables,
            showCreateForm: state.showCreateForm, newSlug: state.newSlug, newTitle: state.newTitle,
            loaded: state.loaded, snippetsLoaded: state.snippetsLoaded, pageTabs: state.pageTabs,
            snippetTabs: state.snippetTabs, activeTabId: state.activeTabId,
        }),
    );

        /** Loads the page, template-variable, and snippet collections on mount. */
        public componentDidMount(): void {
        super.componentDidMount();
        void this.loadPages();
        void this.loadVariables();
        void this.loadSnippets();
    }

        /** Updates page state using a value or state updater. */
        private readonly setPages: React.Dispatch<React.SetStateAction<StaticPage[]>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, pages: typeof value === 'function' ? value(current.pages) : value});
    };

        /** Updates snippet state using a value or state updater. */
        private readonly setSnippets: React.Dispatch<React.SetStateAction<Snippet[]>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, snippets: typeof value === 'function' ? value(current.snippets) : value});
    };

        /** Updates template-variable state using a value or state updater. */
        private readonly setTemplateVariables: React.Dispatch<React.SetStateAction<string[]>> = value => {
        const current = this.ui.getData();
        this.ui.setData({
            ...current,
            templateVariables: typeof value === 'function' ? value(current.templateVariables) : value,
        });
    };

        /** Updates create-form visibility using a value or state updater. */
        private readonly setShowCreateForm: React.Dispatch<React.SetStateAction<boolean>> = value => {
        const current = this.ui.getData();
        this.ui.setData({
            ...current,
            showCreateForm: typeof value === 'function' ? value(current.showCreateForm) : value,
        });
    };

        /** Updates the draft page slug using a value or state updater. */
        private readonly setNewSlug: React.Dispatch<React.SetStateAction<string>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, newSlug: typeof value === 'function' ? value(current.newSlug) : value});
    };

        /** Updates the draft page title using a value or state updater. */
        private readonly setNewTitle: React.Dispatch<React.SetStateAction<string>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, newTitle: typeof value === 'function' ? value(current.newTitle) : value});
    };

        /** Updates page-loading state using a value or state updater. */
        private readonly setLoaded: React.Dispatch<React.SetStateAction<boolean>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, loaded: typeof value === 'function' ? value(current.loaded) : value});
    };

        /** Updates snippet-loading state using a value or state updater. */
        private readonly setSnippetsLoaded: React.Dispatch<React.SetStateAction<boolean>> = value => {
        const current = this.ui.getData();
        this.ui.setData({
            ...current,
            snippetsLoaded: typeof value === 'function' ? value(current.snippetsLoaded) : value,
        });
    };

        /** Updates the open page tabs using a value or state updater. */
        private readonly setPageTabs: React.Dispatch<React.SetStateAction<PageTabInfo[]>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, pageTabs: typeof value === 'function' ? value(current.pageTabs) : value});
    };

        /** Updates the open snippet tabs using a value or state updater. */
        private readonly setSnippetTabs: React.Dispatch<React.SetStateAction<SnippetTabInfo[]>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, snippetTabs: typeof value === 'function' ? value(current.snippetTabs) : value});
    };

        /** Updates the active tab identifier using a value or state updater. */
        private readonly setActiveTabId: React.Dispatch<React.SetStateAction<string | null>> = value => {
        const current = this.ui.getData();
        this.ui.setData({...current, activeTabId: typeof value === 'function' ? value(current.activeTabId) : value});
    };

        /** Fetches the static pages and marks them loaded when successful. */
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

        /** Fetches the available template variables. */
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

        /** Fetches the snippets and marks them loaded when successful. */
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

        /** Opens the page's editor tab, creating it when needed. */
        private readonly openPageTab = (page: StaticPage) => {
        const {pageTabs} = this.ui.getData();
        const {setPageTabs, setActiveTabId} = this;
        const tabId = `page-${page.id}`;
        if (!pageTabs.find(t => t.id === tabId)) {
            setPageTabs(prev => [...prev, {id: tabId, pageId: page.id, title: page.title || page.slug}]);
        }
        setActiveTabId(tabId);
    };

        /** Closes a page tab and clears selection when it was active. */
        private readonly closePageTab = (tabId: string) => {
        const {activeTabId} = this.ui.getData();
        const {setPageTabs, setActiveTabId} = this;
        setPageTabs(prev => prev.filter(t => t.id !== tabId));
        if (activeTabId === tabId) {
            setActiveTabId(null);
        }
    };

        /** Updates the displayed title for an open page tab. */
        private readonly updatePageTabLabel = (pageId: number, title: string) => {
        const {setPageTabs} = this;
        setPageTabs(prev => prev.map(t => t.pageId === pageId ? {...t, title} : t));
    };

        /** Opens the snippet's editor tab, creating it when needed. */
        private readonly openSnippetTab = (snippet: Snippet) => {
        const {snippetTabs} = this.ui.getData();
        const {setSnippetTabs, setActiveTabId} = this;
        const tabId = `snippet-${snippet.id}`;
        if (!snippetTabs.find(t => t.id === tabId)) {
            setSnippetTabs(prev => [...prev, {id: tabId, snippetId: snippet.id, title: snippet.name || snippet.slug}]);
        }
        setActiveTabId(tabId);
    };

        /** Closes a snippet tab and clears selection when it was active. */
        private readonly closeSnippetTab = (tabId: string) => {
        const {activeTabId} = this.ui.getData();
        const {setSnippetTabs, setActiveTabId} = this;
        setSnippetTabs(prev => prev.filter(t => t.id !== tabId));
        if (activeTabId === tabId) {
            setActiveTabId(null);
        }
    };

        /** Updates the displayed title for an open snippet tab. */
        private readonly updateSnippetTabLabel = (snippetId: number, title: string) => {
        const {setSnippetTabs} = this;
        setSnippetTabs(prev => prev.map(t => t.snippetId === snippetId ? {...t, title} : t));
    };

        /** Creates a page from the entered slug and title. */
        private readonly handleCreatePage = () => {
        const {createUrl, labels, withSending} = this.props;
        const {newSlug, newTitle} = this.ui.getData();
        const {openPageTab, setPages, setShowCreateForm, setNewSlug, setNewTitle} = this;
        if (!newSlug.trim()) return;
        void withSending(async () => {
            try {
                const res = await sendPost<
                    {slug: string; title: string},
                    {success: boolean; page: StaticPage}
                >(createUrl, {
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

        /** Toggles a page's published status on the server. */
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

        /** Confirms and deletes a page, removing its open tab as well. */
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

        /** Applies edited page data and refreshes its tab label. */
        private readonly handlePageUpdated = (updatedPage: StaticPage) => {
        const {updatePageTabLabel, setPages} = this;
        setPages(prev => prev.map(p => p.id === updatedPage.id ? updatedPage : p));
        updatePageTabLabel(updatedPage.id, updatedPage.title || updatedPage.slug);
    };

        /** Removes a deleted page and closes its tab. */
        private readonly handlePageDeleted = (pageId: number) => {
        const {closePageTab, setPages} = this;
        setPages(prev => prev.filter(p => p.id !== pageId));
        closePageTab(`page-${pageId}`);
    };

        /** Applies edited snippet data and refreshes its tab label. */
        private readonly handleSnippetUpdated = (updatedSnippet: Snippet) => {
        const {updateSnippetTabLabel, setSnippets} = this;
        setSnippets(prev => prev.map(s => s.id === updatedSnippet.id ? updatedSnippet : s));
        updateSnippetTabLabel(updatedSnippet.id, updatedSnippet.name || updatedSnippet.slug);
    };

        /** Removes a deleted snippet and closes its tab. */
        private readonly handleSnippetDeleted = (snippetId: number) => {
        const {closeSnippetTab, setSnippets} = this;
        setSnippets(prev => prev.filter(s => s.id !== snippetId));
        closeSnippetTab(`snippet-${snippetId}`);
    };

        /** Selects the requested navigation or editor tab. */
        private readonly handleTabSelect = (id: string) => {
        const {setActiveTabId} = this;
        if (id === 'pages' || id === 'snippets') {
            setActiveTabId(id === 'pages' ? null : id);
        } else {
            setActiveTabId(id);
        }
    };

        /** Routes a tab-close request to the matching tab collection. */
        private readonly handleTabClose = (id: string) => {
        const {closePageTab, closeSnippetTab} = this;
        if (id.startsWith('snippet-')) {
            closeSnippetTab(id);
        } else {
            closePageTab(id);
        }
    };

        /** Toggles the new-page form. */
        private readonly toggleCreateForm = () => this.setShowCreateForm(
            !this.ui.getData().showCreateForm,
        );
        /** Hides the new-page form. */
        private readonly cancelCreateForm = () => this.setShowCreateForm(false);
        /** Stores the edited page slug from its input event. */
        private readonly changeSlug = (event: React.ChangeEvent<HTMLInputElement>) =>
            this.setNewSlug(event.target.value);
        /** Stores the edited page title from its input event. */
        private readonly changeTitle = (event: React.ChangeEvent<HTMLInputElement>) =>
            this.setNewTitle(event.target.value);

        /** Adds a newly created snippet and opens its editor tab. */
        private readonly handleSnippetCreated = (snippet: Snippet) => {
        this.setSnippets(previous => [...previous, snippet]);
        this.openSnippetTab(snippet);
    };

        /** Toggles a snippet's active status on the server. */
        private readonly handleToggleSnippet = (snippet: Snippet) => {
        const {withSending, snippetUpdateUrl, labels} = this.props;
        const newActive = snippet.is_active ? 0 : 1;
        void withSending(async () => {
            try {
                const res = await sendPost<any, {success: boolean}>(snippetUpdateUrl, {
                    id: snippet.id,
                    is_active: newActive,
                });
                if ((res as any)?.error) { showToast((res as any).error, 'danger'); return; }
                this.setSnippets(previous => previous.map(item => (
                    item.id === snippet.id ? {...item, is_active: newActive} : item
                )));
            } catch (err: any) { showToast(err?.message ?? labels.error, 'danger'); }
        });
    };

        /** Renders the shell for the current island state. */
        public render() {
        const state = this.view() as ShellState;
        return renderStaticPagesShell(this.props, state, {
            openPageTab: this.openPageTab,
            openSnippetTab: this.openSnippetTab,
            handleCreatePage: this.handleCreatePage,
            handleTogglePublish: this.handleTogglePublish,
            handleDeletePage: this.handleDeletePage,
            handlePageUpdated: this.handlePageUpdated,
            handlePageDeleted: this.handlePageDeleted,
            handleSnippetUpdated: this.handleSnippetUpdated,
            handleSnippetDeleted: this.handleSnippetDeleted,
            handleTabSelect: this.handleTabSelect,
            handleTabClose: this.handleTabClose,
            toggleCreateForm: this.toggleCreateForm,
            cancelCreateForm: this.cancelCreateForm,
            changeSlug: this.changeSlug,
            changeTitle: this.changeTitle,
            handleSnippetCreated: this.handleSnippetCreated,
            handleToggleSnippet: this.handleToggleSnippet,
        });
    }
}

/** Provides the static-pages admin island with sending and confirmation hooks. */
export const StaticPagesAdminIsland: React.FC<Props> = props => {
    const sending = useSending();
    const confirmation = useConfirm();
    return <StaticPagesAdmin {...props} {...sending} {...confirmation} />;
};
