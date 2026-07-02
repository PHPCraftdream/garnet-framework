import * as React from 'react';
import {PreviewContext} from './PreviewContext';
import {UserPreviewModal, PreviewLabels} from './UserPreviewModal';

interface Props {
    labels: PreviewLabels;
    previewUrl?: string;
    extraSection?: (userId: number) => React.ReactNode;
    children: React.ReactNode;
}

/**
 * Wrap an island's root in <PreviewProvider> to enable inline user-preview modals.
 *
 * Children call `usePreview().openPreview(userId, name?)` to open a modal that
 * fetches public profile data from `previewUrl`.
 */
export const PreviewProvider: React.FC<Props> = ({labels, previewUrl, extraSection, children}) => {
    const [target, setTarget] = React.useState<{userId: number; name?: string} | null>(null);

    const openPreview = React.useCallback((userId: number, name?: string) => {
        if (!userId) return;
        setTarget({userId, name});
    }, []);

    const close = React.useCallback(() => setTarget(null), []);

    return (
        <PreviewContext.Provider value={{openPreview}}>
            {children}
            {target && (
                <UserPreviewModal
                    userId={target.userId}
                    initialName={target.name}
                    previewUrl={previewUrl}
                    labels={labels}
                    extraSection={extraSection?.(target.userId)}
                    onClose={close}
                />
            )}
        </PreviewContext.Provider>
    );
};
