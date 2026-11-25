import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
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
export class PreviewProvider extends AntiHookComponent<Props> {
    private readonly store = new Carburetor<{target: {userId: number; name?: string} | null}>({target: null});
    /** Open a preview for the selected user. */
    private readonly openPreview = (userId: number, name?: string): void => {
        if (userId) this.store.setData({target: {userId, name}});
    };
    /** Close the active user preview. */
    private readonly close = (): void => {
        this.store.setData({target: null});
    };
    private readonly contextValue = {openPreview: this.openPreview};

    /** Render children with the preview context and optional modal. */
    public render() {
        const {target} = this.useCarburetor(this.store);
        const {labels, previewUrl, extraSection, children} = this.props;
        return (
            <PreviewContext.Provider value={this.contextValue}>
                {children}
                {target && (
                    <UserPreviewModal
                        userId={target.userId}
                        initialName={target.name}
                        previewUrl={previewUrl}
                        labels={labels}
                        extraSection={extraSection?.(target.userId)}
                        onClose={this.close}
                    />
                )}
            </PreviewContext.Provider>
        );
    }
}
