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
                placeholder: '🔍 ...'
            },
            pagination: {
                previous: '←️',
                next: '→️',
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
