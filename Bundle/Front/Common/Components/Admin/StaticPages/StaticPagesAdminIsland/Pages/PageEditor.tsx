import type {Labels, PageBlock, Snippet, StaticPage} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/types';

export interface PageEditorProps {
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

export {PageEditorView as PageEditor} from './PageEditorView';
