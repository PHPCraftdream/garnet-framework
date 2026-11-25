import type {Labels} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/types';

/** Maps each snippet category to its localized interface label. */
export const SNIPPET_TYPE_LABELS: Record<string, (labels: Labels) => string> = {
    header: labels => labels.snippetsTypeHeader,
    footer: labels => labels.snippetsTypeFooter,
    variable: labels => labels.snippetsTypeVariable,
    block: labels => labels.snippetsTypeBlock,
};
