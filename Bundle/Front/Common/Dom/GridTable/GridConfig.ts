import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';

// Plain glyphs, not emoji: emoji are someone else's artwork — a different
// picture on every platform, coloured whatever the font decides.
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
                navigate: (page, pages) => `${page} / ${pages}`,
                page: (page) => `${page}`,
                showing: ' ',
                of: ' [',
                to: ' ... ',
                results: ']',
            }
        }
    };
}
