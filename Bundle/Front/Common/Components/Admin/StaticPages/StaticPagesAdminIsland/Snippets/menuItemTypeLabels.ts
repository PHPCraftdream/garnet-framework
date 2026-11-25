import {Labels} from '@common/Components/Admin/StaticPages/StaticPagesAdminIsland/types';

/** Resolves display labels for supported menu item kinds. */
export const ITEM_TYPE_LABELS: Record<string, (l: Labels) => string> = {
    link: l => l.snippetsItemTypeLink,
    page: l => l.snippetsItemTypePage,
    divider: l => l.snippetsItemTypeDivider,
};
