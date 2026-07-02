import * as React from 'react';
import {sendPost} from '@common/Api/sendPost';
import {useBodyScrollLock} from '@common/hooks/useBodyScrollLock';
import {appUrl} from '@common/Utils/appUrl';
import {Portal} from '@common/Components/Portal';

/**
 * Generic foreground user preview modal.
 *
 * Receives i18n labels as props (so the Framework/Bundle/Front/Common module stays app-agnostic).
 * Calls a configurable previewUrl (default '/users/~preview') with {user_id} and renders:
 *  - basic identity (id, display name, role badge)
 *  - expert profile section (specialization, bio, rating) when present
 *  - small stats grid (counts, no business-leaking history)
 *  - quick links: open profile, send message
 */

export interface PreviewLabels {
    title: string;
    loading: string;
    openProfile: string;
    sendMessage: string;
    specialization: string;
    bio: string;
    rating: string;
    conducted: string;
    totalBookings: string;
    cancellations: string;
    completedBookings: string;
    roleExpert: string;
    roleUser: string;
    close: string;
    loadError: string;
}

interface ExpertProfile {
    display_name: string;
    specialization: string;
    bio: string;
    rating: number;
}

interface UserStats {
    conducted?: number;
    totalBookings?: number;
    cancellations?: number;
    completedBookings?: number;
}

interface PreviewUser {
    id: number;
    name: string;
    type: 'user' | 'expert';
    avatar?: string | null;
    expertProfile: ExpertProfile | null;
    stats: UserStats;
}

interface Props {
    userId: number;
    initialName?: string;
    previewUrl?: string;
    labels: PreviewLabels;
    extraSection?: React.ReactNode;
    onClose: () => void;
}

export const UserPreviewModal: React.FC<Props> = ({
    userId, initialName, previewUrl = '/users/~preview', labels, extraSection, onClose,
}) => {
    useBodyScrollLock(true);
    const [user, setUser] = React.useState<PreviewUser | null>(null);
    const [loading, setLoading] = React.useState(true);
    const [error, setError] = React.useState<string | null>(null);

    React.useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError(null);
        sendPost<{user_id: number}, {user: PreviewUser}>(previewUrl, {user_id: userId})
            .then((resp) => {
                if (cancelled) return;
                const data: any = (resp as any)?.data ?? resp;
                setUser((data?.user as PreviewUser) ?? null);
            })
            .catch((err: unknown) => {
                if (cancelled) return;
                const msg = (err && typeof err === 'object' && 'message' in err && typeof (err as {message?: unknown}).message === 'string')
                    ? (err as {message: string}).message
                    : labels.loadError;
                setError(msg);
            })
            .finally(() => { if (!cancelled) setLoading(false); });
        return () => { cancelled = true; };
    }, [userId, previewUrl, labels.loadError]);

    React.useEffect(() => {
        const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [onClose]);

    const onOverlay = (e: React.MouseEvent<HTMLDivElement>) => {
        if (e.target === e.currentTarget) onClose();
    };

    const displayName = user?.expertProfile?.display_name?.trim()
        || user?.name
        || initialName
        || `#${userId}`;

    const initials = (displayName || '?')
        .split(' ')
        .map(w => w[0]?.toUpperCase() || '')
        .slice(0, 2)
        .join('');

    const isExpert = user?.type === 'expert';
    const profileHref = appUrl(isExpert ? `/expert/id~${userId}` : `/user/id~${userId}`);

    return (
        <Portal><div
            className="fg-modal-overlay-high"
            onClick={onOverlay}
            data-test-id="preview-modal"
        >
            <div className="fg-modal-card-flush fg-modal-card-lg" role="dialog" aria-modal="true">
                <div className="fg-modal-flush-header">
                    <h3 className="fg-modal-title">{labels.title}</h3>
                    <button
                        type="button"
                        className="fg-modal-close-x"
                        onClick={onClose}
                        title={labels.close}
                        aria-label={labels.close}
                        data-test-id="preview-close"
                    >
                        &times;
                    </button>
                </div>

                <div className="fg-modal-flush-body">
                    {loading ? (
                        <div className="muted-empty-state" data-test-id="preview-loading">{labels.loading}</div>
                    ) : error ? (
                        <div className="text-danger text-sm">{error}</div>
                    ) : user ? (
                        <>
                            <div className="preview-header-row">
                                {user.avatar ? (
                                    <img src={user.avatar} alt={displayName} className="avatar-circle-lg-img" data-test-id="preview-avatar" />
                                ) : (
                                    <div className="avatar-circle-lg" data-test-id="preview-avatar-fallback">{initials}</div>
                                )}
                                <div className="preview-identity">
                                    <div className="preview-name">{displayName}</div>
                                    <div className="role-badge preview-role-badge">
                                        {isExpert ? labels.roleExpert : labels.roleUser}
                                    </div>
                                </div>
                            </div>

                            {user.expertProfile && (
                                <div className="preview-section">
                                    {user.expertProfile.specialization && (
                                        <div className="preview-row">
                                            <span className="preview-label">{labels.specialization}:</span>{' '}
                                            <span>{user.expertProfile.specialization}</span>
                                        </div>
                                    )}
                                    {user.expertProfile.rating > 0 && (
                                        <div className="preview-row">
                                            <span className="preview-label">{labels.rating}:</span>{' '}
                                            <span>{user.expertProfile.rating.toFixed(2)}</span>
                                        </div>
                                    )}
                                    {user.expertProfile.bio && (
                                        <div className="preview-row preview-bio">
                                            <span className="preview-label">{labels.bio}:</span>{' '}
                                            <span>{user.expertProfile.bio}</span>
                                        </div>
                                    )}
                                </div>
                            )}

                            <div className="preview-stats-grid">
                                {isExpert ? (
                                    <>
                                        <div className="stat-tile">
                                            <div className="stat-tile-value">{user.stats.conducted ?? 0}</div>
                                            <div className="stat-tile-label">{labels.conducted}</div>
                                        </div>
                                        <div className="stat-tile">
                                            <div className="stat-tile-value">{user.stats.totalBookings ?? 0}</div>
                                            <div className="stat-tile-label">{labels.totalBookings}</div>
                                        </div>
                                        <div className="stat-tile">
                                            <div className="stat-tile-value">{user.stats.cancellations ?? 0}</div>
                                            <div className="stat-tile-label">{labels.cancellations}</div>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <div className="stat-tile">
                                            <div className="stat-tile-value">{user.stats.totalBookings ?? 0}</div>
                                            <div className="stat-tile-label">{labels.totalBookings}</div>
                                        </div>
                                        <div className="stat-tile">
                                            <div className="stat-tile-value">{user.stats.completedBookings ?? 0}</div>
                                            <div className="stat-tile-label">{labels.completedBookings}</div>
                                        </div>
                                        <div className="stat-tile">
                                            <div className="stat-tile-value">{user.stats.cancellations ?? 0}</div>
                                            <div className="stat-tile-label">{labels.cancellations}</div>
                                        </div>
                                    </>
                                )}
                            </div>

                            {extraSection && (
                                <div className="preview-section">{extraSection}</div>
                            )}

                            <div className="preview-actions">
                                <a
                                    href={profileHref}
                                    className="btn btn-sm btn-outline-primary"
                                    data-test-id="preview-open-profile"
                                >
                                    {labels.openProfile}
                                </a>
                                <a
                                    href={appUrl(`/im/#to=${userId}`)}
                                    className="btn btn-sm btn-outline-primary"
                                    data-test-id="preview-message"
                                >
                                    {labels.sendMessage}
                                </a>
                            </div>
                        </>
                    ) : null}
                </div>
            </div>
        </div></Portal>
    );
};
