/**
 * Формы данных и подписи, общие для всех частей экрана статических страниц.
 */
export interface StaticPage {
    id: number;
    slug: string;
    title: string;
    /** Заголовок с подставленными переменными — только для чтения в списке. */
    title_rendered?: string;
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

export interface Snippet {
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

export interface PageBlock {
    id: number;
    page_id: number;
    block_type: string;
    content: string;
    sort_order: number;
    is_hidden: number;
    visibility: string;
    created_at: number;
}

export interface Labels {
    title: string;
    empty: string;
    create: string;
    /**
     * Надпись на кнопке, которая только ОТКРЫВАЕТ форму. Отдельная от `create`,
     * потому что раньше оба действия — открыть форму и отправить её — назывались
     * одним словом, и владелец не понимала, почему страница не создаётся (D-110).
     * Не задана — остаётся прежнее поведение.
     */
    createOpen?: string;
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
    /** То же разделение, что и `createOpen`, но для компонентов. */
    snippetsCreateOpen?: string;
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

export interface Props {
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

export interface PageTabInfo {
    id: string;
    pageId: number;
    title: string;
}

export interface SnippetTabInfo {
    id: string;
    snippetId: number;
    title: string;
}

/** Identifier of a built-in static-pages tab. */
export type StaticTabId = typeof import('./values/staticTabs').STATIC_TABS[number];
