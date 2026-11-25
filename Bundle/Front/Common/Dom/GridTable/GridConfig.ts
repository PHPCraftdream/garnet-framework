import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';

const navigate = (page: any, pages: any) => `${page} / ${pages}`;
const formatPage = (page: any) => `${page}`;

// Plain glyphs, not emoji: emoji are someone else's artwork — a different
// picture on every platform, coloured whatever the font decides.
/** Configures search, sorting, pagination, and translations for GridTable. */
export const gridConfig = () => {
    return {
        search: true,
        sort: true,
        pagination: {
            limit: 10,
            summary: true
        },
        language: {
            search: {
                placeholder: t.Grid_Search()
            },
            pagination: {
                previous: '‹',
                next: '›',
                navigate,
                page: formatPage,
                showing: ' ',
                of: ' [',
                to: ' ... ',
                results: ']',
            }
        }
    };
}
