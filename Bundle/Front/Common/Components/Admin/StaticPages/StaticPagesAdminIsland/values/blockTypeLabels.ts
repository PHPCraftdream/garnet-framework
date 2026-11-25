import type {Labels} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/types';

/** Maps each editable block kind to its localized interface label. */
export const BLOCK_TYPE_LABELS: Record<string, (labels: Labels) => string> = {
    text: labels => labels.blockTypeText,
    gallery: labels => labels.blockTypeGallery,
};
