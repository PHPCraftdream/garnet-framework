import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import {useBodyScrollLock} from '@common/hooks/ui/internal/useBodyScrollLock';
import {appUrl} from '@common/Utils/Url/appUrl';
import {Portal} from '@common/Components/Layout/Portal';

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

interface ExtendedProfile {
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
    type: string;
    avatar?: string | null;
    extendedProfile: ExtendedProfile | null;
    stats: UserStats;
}

interface Props {
    userId: number;
    initialName?: string;
    previewUrl?: string;
    profileUrl?: string;
    messageUrl?: string;
    labels: PreviewLabels;
    extraSection?: React.ReactNode;
    onClose: () => void;
}

const ScrollLock: React.FC = () => {
    useBodyScrollLock(true);
    return null;
};

type PreviewState = {user: PreviewUser | null; loading: boolean; error: string | null};

/** Return the first uppercase character in a word. */
function firstInitial(word: string): string {
    return word[0]?.toUpperCase() || '';
}

/** Render one user statistic and its label. */
function renderTile(value: number, label: string) {
    return <div className="user-preview-stat"><div className="user-preview-stat-value">{value}</div><div className="user-preview-stat-label">{label}</div></div>;
}

export class UserPreviewModal extends AntiHookComponent<Props> {
    private readonly store = new Carburetor<PreviewState>({user: null, loading: true, error: null});
    private request = 0;

    /** Install keyboard handling and fetch the preview data. */
    public componentDidMount(): void {
        super.componentDidMount();
        document.addEventListener('keydown', this.onKey);
        this.load();
    }

    /** Reload preview data when request inputs change. */
    public componentDidUpdate(previous: Readonly<Props>): void {
        super.componentDidUpdate(previous);
        if (previous.userId !== this.props.userId ||
            previous.previewUrl !== this.props.previewUrl ||
            previous.labels.loadError !== this.props.labels.loadError) {
            this.load();
        }
    }

    /** Invalidate pending requests and remove keyboard handling. */
    public componentWillUnmount(): void {
        this.request++;
        document.removeEventListener('keydown', this.onKey);
        super.componentWillUnmount();
    }

    /** Fetch the user's preview and update loading and error state. */
    private load(): void {
        const request = ++this.request;
        const {userId, previewUrl = '/users/~preview', labels} = this.props;
        this.store.setData({...this.store.getData(), loading: true, error: null});
        sendPost<{user_id: number}, {user: PreviewUser}>(previewUrl, {user_id: userId})
            .then(resp => {
                if (request !== this.request) return;
                const data: any = (resp as any)?.data ?? resp;
                this.store.setData({...this.store.getData(), user: (data?.user as PreviewUser) ?? null});
            })
            .catch((err: unknown) => {
                if (request !== this.request) return;
                const error = err && typeof err === 'object' && 'message' in err &&
                    typeof (err as {message?: unknown}).message === 'string'
                    ? (err as {message: string}).message
                    : labels.loadError;
                this.store.setData({...this.store.getData(), error});
            })
            .finally(() => {
                if (request === this.request) this.store.setData({...this.store.getData(), loading: false});
            });
    }

    /** Close the modal when Escape is pressed. */
    private readonly onKey = (event: KeyboardEvent): void => {
        if (event.key === 'Escape') this.props.onClose();
    };
    /** Close the modal when its backdrop is clicked. */
    private readonly onOverlay = (event: React.MouseEvent<HTMLDivElement>): void => {
        if (event.target === event.currentTarget) this.props.onClose();
    };

    /** Render the current preview state. */
    public render() {
        const {user, loading, error} = this.useCarburetor(this.store);
        const snapshot: PreviewUser | null = user ? {
            id: user.id, name: user.name, type: user.type, avatar: user.avatar,
            extendedProfile: user.extendedProfile ? {
                display_name: user.extendedProfile.display_name,
                specialization: user.extendedProfile.specialization,
                bio: user.extendedProfile.bio,
                rating: user.extendedProfile.rating,
            } : null,
            stats: {
                conducted: user.stats.conducted, totalBookings: user.stats.totalBookings,
                cancellations: user.stats.cancellations, completedBookings: user.stats.completedBookings,
            },
        } : null;
        return this.renderModal(snapshot, loading, error);
    }

    /** Render the modal shell and its loading, error, or user content. */
    private renderModal(user: PreviewUser | null, loading: boolean, error: string | null) {
        const {labels, onClose} = this.props;
        return (
            <Portal><ScrollLock /><div className="fg-modal-overlay-high" onClick={this.onOverlay} data-test-id="preview-modal">
                <div className="fg-modal-card-flush-lg" role="dialog" aria-modal="true">
                    <div className="user-preview-modal-header">
                        <h3 className="user-preview-modal-title">{labels.title}</h3>
                        <button type="button" className="user-preview-modal-close" onClick={onClose}
                            title={labels.close} aria-label={labels.close} data-test-id="preview-close">&times;</button>
                    </div>
                    <div className="user-preview-modal-body">
                        {loading
                            ? <div className="user-preview-loading" data-test-id="preview-loading">
                                {labels.loading}
                            </div>
                            : error ? <div className="user-preview-error">{error}</div>
                            : user ? this.renderUser(user) : null}
                    </div>
                </div>
            </div></Portal>
        );
    }

    /** Render identity, profile, statistics, and actions for one user. */
    private renderUser(user: PreviewUser) {
        const {userId, initialName, labels, extraSection} = this.props;
        const displayName = user.extendedProfile?.display_name?.trim() || user.name || initialName || `#${userId}`;
        const initials = (displayName || '?').split(' ').map(firstInitial).slice(0, 2).join('');
        const expert = user.type === 'expert';
        return <>
            <div className="user-preview-header">
                {user.avatar
                    ? <img src={user.avatar} alt={displayName} className="user-preview-avatar-image"
                        data-test-id="preview-avatar" />
                    : <div className="user-preview-avatar" data-test-id="preview-avatar-fallback">{initials}</div>}
                <div className="user-preview-identity">
                    <div className="user-preview-name">{displayName}</div>
                    <div className="user-preview-role">{expert ? labels.roleExpert : labels.roleUser}</div>
                </div>
            </div>
            {user.extendedProfile && this.renderProfile(user.extendedProfile)}
            {this.renderStats(user.stats, expert)}
            {extraSection && <div className="user-preview-section">{extraSection}</div>}
            {this.renderActions(expert)}
        </>;
    }

    /** Render available expert profile details. */
    private renderProfile(profile: ExtendedProfile) {
        const {labels} = this.props;
        return <div className="user-preview-section">
            {profile.specialization && <div className="user-preview-row">
                <span className="user-preview-label">{labels.specialization}:</span>{' '}
                <span>{profile.specialization}</span>
            </div>}
            {profile.rating > 0 && <div className="user-preview-row">
                <span className="user-preview-label">{labels.rating}:</span>{' '}
                <span>{profile.rating.toFixed(2)}</span>
            </div>}
            {profile.bio && <div className="user-preview-bio">
                <span className="user-preview-label">{labels.bio}:</span>{' '}
                <span>{profile.bio}</span>
            </div>}
        </div>;
    }

    /** Render role-specific booking statistics. */
    private renderStats(stats: UserStats, expert: boolean) {
        const {labels} = this.props;
        return <div className="user-preview-stats">
            {expert
                ? renderTile(stats.conducted ?? 0, labels.conducted)
                : renderTile(stats.totalBookings ?? 0, labels.totalBookings)}
            {expert
                ? renderTile(stats.totalBookings ?? 0, labels.totalBookings)
                : renderTile(stats.completedBookings ?? 0, labels.completedBookings)}
            {renderTile(stats.cancellations ?? 0, labels.cancellations)}
        </div>;
    }

    /** Render profile and messaging links. */
    private renderActions(expert: boolean) {
        const {userId, profileUrl, messageUrl, labels} = this.props;
        const profileHref = appUrl(profileUrl ?? (expert ? `/expert/id~${userId}` : `/user/id~${userId}`));
        return <div className="user-preview-actions">
            <a href={profileHref} className="user-preview-action"
                data-test-id="preview-open-profile">{labels.openProfile}</a>
            <a href={appUrl(messageUrl ?? `/im/#to=${userId}`)} className="user-preview-action" data-test-id="preview-message">{labels.sendMessage}</a>
        </div>;
    }
}
