import {Labels} from '../types';

export const ITEM_TYPES = ['link', 'page', 'divider'] as const;

export const ITEM_TYPE_LABELS: Record<string, (l: Labels) => string> = {
    link: l => l.snippetsItemTypeLink,
    page: l => l.snippetsItemTypePage,
    divider: l => l.snippetsItemTypeDivider,
};
