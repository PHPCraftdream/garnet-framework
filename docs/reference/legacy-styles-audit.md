# Legacy styles audit (#610)

Scope: `Bundle/Front/Styles/modules/components/01-base.css` through `07-admin-screens.css`. Counts are original unique class tokens **per stylesheet**, not rule or selector counts. The same token can count in more than one file.

| Stylesheet | Original | FW TS/TSX | FW Twig/PHP/JS | IRabi-only | Repo CSS-only | Risk / unresolved | Removed |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| `01-base.css` | 26 | 11 | 0 | 12 | 1 | 2 | 0 |
| `02-controls.css` | 81 | 24 | 1 | 29 | 6 | 21 | 0 |
| `03-navigation.css` | 56 | 13 | 1 | 26 | 0 | 13 | 3 |
| `04-admin-panel.css` | 66 | 7 | 0 | 47 | 0 | 1 | 11 |
| `05-app-screens.css` | 89 | 1 | 1 | 62 | 0 | 22 | 3 |
| `06-features.css` | 77 | 20 | 4 | 42 | 0 | 11 | 0 |
| `07-admin-screens.css` | 85 | 20 | 14 | 3 | 4 | 30 | 14 |

## How to read the census

- The evidence buckets are exclusive: each retained token goes in the first applicable column from left to right. FW means framework TS/TSX or Twig/PHP/JS markup. IRabi-only means a literal or style reference in the current tracked/nonignored source of either IRabi app, without an earlier FW hit. Repo CSS-only includes references in peer stylesheets, not just the file defining the token. A token hit is conservative evidence, not proof that a class is emitted at runtime.
- Match complete class tokens at CSS identifier boundaries, ignoring selector comments. Look in the framework, peer stylesheets, and both IRabi apps, including their source and their own stylesheet `@apply` references. Trace template literals (`${…}`), JSX variant maps, concatenation and trailing suffixes, Twig `{{…}}` and `~`, PHP interpolation, `.` and `sprintf`, and DOM/state paths such as `classList`, `className`, `innerHTML` and `data-state`.
- **Risk / unresolved** is a retention bucket, not a list of dynamic classes. It includes actual dynamic producers, generated-JS consumers, third-party and public compatibility classes, opaque consumers, and tokens with no conclusive external evidence. Generated CSS copies are definitions, not independent consumers.

| Retention reason | Examples |
| --- | --- |
| Constructed classes | `cal-nav-badge--${k}` is a producer in nested IRabi source; `im-bubble${suffix}` and `support-system-line${suffix}` are actual producers of suffix variants. |
| Generated calendar variants | Generated IRabi JS contains concrete `cal-nav-badge--` variants, not the interpolation. |
| Older source still consumes the classes | The `common-pick-lightbox*` attachment classes remain in both IRabi HEADs and nested copies despite changes to working TSX. They are not removals. |
| Compatibility and runtime states | Keep generic Bootstrap classes and account for third-party states. SPA transition classes such as `.page-leaving` and `.page-swapping` also stay; their FW TS hits put them in the FW column, not the fallback. |
| Artifact-only evidence | `.col-form-label` and `.main-label` were observed in generated IRabi JS at `Public/assets/IRabi/gen/js/4860.bf4523ed4d3d9014.gen.js:1`. |

## IRabi-only tokens and removals by stylesheet

Keep every retained IRabi-only app compatibility token below until **both IRabi apps ship the replacements**. The lists do not cover artifact-only or opaque consumers. The removals below account for **31 whole rules**, with no selector-list joins and no observed independent consumer in the audited sources, stylesheets, or artifacts. This is the audit scope, not a claim of runtime coverage.

### `01-base.css`

| Bucket | Tokens |
| --- | --- |
| IRabi-only | `admin-filter-reset`, `btn-outline-warning-full`, `compact-outline-action`, `compact-success-action`, `external-primary-action`, `external-secondary-action`, `form-control`, `form-control-sm`, `form-label`, `form-select`, `form-select-sm`, `primary-action-button` |
| Removed | None |

### `02-controls.css`

| Bucket | Tokens |
| --- | --- |
| IRabi-only | `bg-danger`, `bg-primary`, `bg-secondary`, `bg-success`, `bg-warning`, `border-success`, `border-warning`, `btn-danger`, `btn-lg`, `btn-outline-danger`, `btn-outline-primary`, `btn-outline-secondary`, `btn-outline-warning`, `btn-secondary`, `btn-sm`, `card-body`, `card-text`, `card-title`, `chip`, `chip-active`, `chip-count`, `compact-success-action`, `label-mini`, `table-bordered`, `table-sm`, `table-warning`, `text-bg-primary`, `text-warning`, `time-slot-card` |
| Removed | None |

### `03-navigation.css`

| Bucket | Tokens |
| --- | --- |
| IRabi-only | `admin-dash-activity-action`, `admin-dash-activity-actor`, `admin-dash-activity-arrow`, `admin-dash-activity-head`, `admin-dash-activity-item`, `admin-dash-activity-time`, `admin-dash-activity-users`, `admin-dash-card`, `admin-dash-card-header`, `admin-dash-card-title`, `admin-dash-card-title-mb`, `admin-dash-count`, `admin-dash-empty`, `admin-dash-link-footer`, `admin-dash-link-primary-truncate`, `admin-dash-list`, `admin-dash-list-row`, `admin-dash-list-tight`, `admin-dash-meta`, `admin-dash-meta-mt`, `admin-dash-row-aside`, `admin-dash-row-main`, `admin-dash-stat-label`, `admin-dash-stat-tile`, `admin-dash-stat-value`, `admin-dash-stats-grid` |
| Removed | `.mobile-topbar-brand`, `.mobile-topbar-utility`, `.mobile-balance-pill` |

### `04-admin-panel.css`

| Bucket | Tokens |
| --- | --- |
| IRabi-only | `admin-cell-date`, `admin-cell-id`, `admin-cell-mono`, `admin-cell-note`, `admin-detail-error`, `admin-detail-loading`, `admin-detail-pane`, `admin-detail-table`, `admin-filter-bar`, `admin-filter-btn`, `admin-filter-btn-active`, `admin-filter-count`, `admin-floating-toast`, `admin-header-actions`, `admin-header-badges`, `admin-link-btn`, `admin-link-btn-md`, `admin-link-btn-strong`, `admin-section-title`, `admin-tabnav`, `admin-tabnav-btn`, `admin-tabnav-btn-active`, `admin-tabnav-count`, `admin-tabnav-item`, `admin-unread-pill`, `admin-user-avatar-fallback`, `admin-user-avatar-img`, `admin-user-balance`, `admin-user-balance-amount`, `admin-user-balance-label`, `admin-user-header`, `admin-user-meta`, `avatar-circle`, `avatar-circle-img`, `avatar-circle-lg`, `avatar-circle-lg-img`, `count-badge-accent`, `count-badge-success`, `empty-state-card`, `news-group-suffix`, `page-narrow`, `section-header-row`, `section-heading`, `stat-tile`, `stat-tile-label`, `stat-tile-value`, `view-all-link` |
| Removed | `.admin-profile-card`, `.admin-profile-row`, `.time-slot-row`, `.time-slot-group`, `.time-slot-group-header`, `.time-slot-group-title`, `.time-slot-group-meta`, `.time-slot-group-count`, `.time-slot-group-list`, `.quick-link-pill`, `.quick-link-tile` |

### `05-app-screens.css`

| Bucket | Tokens |
| --- | --- |
| IRabi-only | `fg-modal-actions`, `fg-modal-card`, `fg-modal-card-3xl`, `fg-modal-card-flush`, `fg-modal-card-lg`, `fg-modal-card-md`, `fg-modal-close-x`, `fg-modal-error`, `fg-modal-flush-body`, `fg-modal-flush-header`, `fg-modal-header-row`, `fg-modal-overlay`, `fg-modal-subtitle`, `fg-modal-title`, `news-row-actions`, `profile-admin-link`, `profile-card`, `profile-header`, `profile-stat-cell`, `profile-stat-value`, `registration-form-card`, `role-badge`, `support-back-btn`, `support-conv-header`, `support-conv-header-row`, `support-conv-meta`, `support-conv-title`, `support-empty-flex`, `support-im-fab`, `support-layout`, `support-layout-detail`, `support-list-header`, `support-list-pagination`, `support-list-pagination-bottom`, `support-list-panel`, `support-new-btn`, `support-new-form`, `support-new-form-title`, `support-system-line`, `support-thread-actions`, `support-thread-body`, `support-thread-input`, `support-thread-panel`, `support-ticket-row`, `support-ticket-row-active`, `support-ticket-row-head`, `support-ticket-row-inactive`, `support-ticket-row-meta`, `support-unread-badge`, `support-widget-fab`, `support-widget-im-link`, `toggle-pill`, `toggle-pill-off`, `toggle-pill-on`, `user-balance-amount`, `user-balance-card`, `user-dash-stat-value-accent`, `user-dash-stat-value-danger`, `user-dash-stat-value-success`, `user-dash-stat-value-warning`, `welcome-card`, `welcome-card-row` |
| Removed | `.stat-value-success`, `.stat-value-accent`, `.stat-value-warning` |

### `06-features.css`

| Bucket | Tokens |
| --- | --- |
| IRabi-only | `comment-author`, `comment-body`, `comment-card`, `comment-card-row`, `comment-delete-btn`, `comment-form`, `comment-list`, `comment-time`, `common-attach-file`, `common-attach-file-inner`, `common-attach-image`, `common-attach-image-img`, `common-attach-list`, `common-link-admin-tag`, `common-pick-grid`, `common-pick-remove`, `common-pick-tile`, `common-pick-tile-fallback`, `common-status-pill`, `im-avatar`, `im-avatar-sm`, `im-bubble`, `im-bubble-author`, `im-bubble-body`, `im-bubble-mine`, `im-bubble-row`, `im-bubble-theirs`, `im-bubble-time`, `im-combobox-chevron`, `im-combobox-content`, `im-combobox-empty`, `im-combobox-list`, `im-combobox-option`, `im-combobox-option-active`, `im-combobox-search-input`, `im-combobox-search-wrap`, `im-combobox-trigger`, `im-combobox-trigger-empty`, `im-conv-snippet`, `im-conv-time`, `im-conv-you-prefix`, `im-list-header` |
| Removed | None |

### `07-admin-screens.css`

| Bucket | Tokens |
| --- | --- |
| IRabi-only | `admin-bookings-filters`, `blk-fmt-btn`, `sp-notfound-home` (see caveats: no live consumer) |
| Removed | `.admin-dash-page`, `.admin-dash-topbar`, `.admin-dash-topbar-inner`, `.admin-dash-title`, `.admin-dash-main`, `.admin-dash-panel`, `.admin-dash-panel-row`, `.admin-dash-panel-actions`, `.admin-dash-panel-header`, `.admin-dash-panel-label`, `.admin-dash-panel-hint`, `.admin-dash-panel-mini-action`, `.admin-dash-select`, `.terminal-panel` |

## Migration order

1. Move app-specific styling to app-owned styles or shared framework components. Update markup, `@apply` references, and dynamic variants in both apps. Keep the app's `user-dash-stat-value-*` variants distinct from the removed `.stat-value-*` rules.
2. Ship both IRabi migrations before dropping shared rules. Check deployed bundles and supported older versions for stale references, particularly calendar badges and the attachment lightbox.
3. Re-audit peer CSS and public compatibility before further pruning. Review affected screens and states in both apps, including SPA transitions and third-party widgets.

## Census caveats

The counts above come from literal-token matching and are an inventory aid, not a proof of use:

- `sp-notfound-home` is counted as IRabi-only, but IRabi's not-found spec (`Tests/specs/framework-bundle/pages/notfound-page.spec.ts`) asserts that no such element exists. It has no live consumer; it was kept only because nothing proves it dead everywhere. A safe removal candidate once both apps are re-checked.
- `.banner` is counted under framework TS/TSX from prose and test-id mentions; `Feedback/Banner.tsx` emits `feedback-banner-*` classes instead. Treat it as unverified before relying on that bucket.
