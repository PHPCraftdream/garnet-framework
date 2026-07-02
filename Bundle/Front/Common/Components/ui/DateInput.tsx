import * as React from 'react';
import {Portal} from '../Portal';

type DateInputType = 'date' | 'time' | 'datetime-local' | 'month' | 'week';

interface Props extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
    type?: DateInputType;
}

/**
 * Date input with a localised popup calendar.
 *
 * The underlying element stays `<input type="date">` so the value model is
 * the unambiguous ISO `YYYY-MM-DD` string everyone in this codebase already
 * uses — Playwright `.fill('YYYY-MM-DD')` still drives it, FormData submit
 * still picks it up, server-side validation is untouched.
 *
 * What changed: the native picker UI was rendered by the browser engine,
 * which means it switched language with the OS / browser settings (English
 * months on a Russian-mode site in Chrome on an English Windows), looked
 * different in Firefox vs Chromium, and refused to be styled. We hide that
 * native chrome entirely and paint our own popup. Locale comes from the
 * site's i18n (`window.__GARNET_UI_LANG__`, RU/EN), formatting from
 * `Intl.DateTimeFormat` — so month / weekday names match exactly the same
 * language the user already sees in the rest of the UI, in every browser.
 *
 * The popup is portalled into <body> with `position: fixed`, so any
 * ancestor form / dialog / scroll container can't clip it or grow a
 * scrollbar around it. Position is recomputed on scroll/resize, and the
 * popup flips above the input when there isn't enough room below.
 *
 * For `type` other than `date` the legacy native input is kept — those
 * widgets (time, datetime-local, month, week) are less language-sensitive
 * and the rewrite stays scoped to the most-visible offender.
 */
export const DateInput = React.forwardRef<HTMLInputElement, Props>(function DateInput(
    {type = 'date', onFocus, onClick, onChange, onBlur, className, value, defaultValue, name, min, max, ...rest},
    forwardedRef,
) {
    if (type !== 'date') {
        return <LegacyNativeInput type={type} className={className} onFocus={onFocus} onClick={onClick} onChange={onChange} onBlur={onBlur} value={value} defaultValue={defaultValue} name={name} min={min} max={max} ref={forwardedRef} {...rest} />;
    }

    const wrapperRef = React.useRef<HTMLDivElement>(null);
    const innerRef = React.useRef<HTMLInputElement>(null);
    const popupRef = React.useRef<HTMLDivElement>(null);
    // Forward the inner input to the caller so existing refs keep working.
    React.useImperativeHandle(forwardedRef, () => innerRef.current as HTMLInputElement, []);

    const [open, setOpen] = React.useState(false);
    const [pos, setPos] = React.useState<{top: number; left: number} | null>(null);

    // The controlled / uncontrolled string value of the underlying input.
    const currentIsoValue = String(value ?? defaultValue ?? '');

    const closePopup = React.useCallback(() => setOpen(false), []);

    // Close on outside click / Escape. Popup lives in a portal on <body>, so
    // an "outside" click is anywhere outside BOTH the input wrapper and the
    // portalled popup itself — checking only the wrapper would close the
    // popup the moment the user clicks a day cell.
    React.useEffect(() => {
        if (!open) return undefined;
        const onDocMouseDown = (e: MouseEvent): void => {
            const t = e.target as Node;
            if (wrapperRef.current?.contains(t)) return;
            if (popupRef.current?.contains(t)) return;
            closePopup();
        };
        const onKey = (e: KeyboardEvent): void => {
            if (e.key === 'Escape') closePopup();
        };
        document.addEventListener('mousedown', onDocMouseDown);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onDocMouseDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open, closePopup]);

    // Recompute the popup position on open and whenever the page scrolls /
    // resizes. `scroll` is captured (third arg `true`) so nested scroll
    // containers also trigger the reflow, not just the document.
    React.useLayoutEffect(() => {
        if (!open) return undefined;
        const recompute = (): void => {
            const el = innerRef.current;
            if (!el) return;
            const r = el.getBoundingClientRect();
            const popupH = popupRef.current?.offsetHeight ?? 320;
            const gap = 4;
            const fitsBelow = r.bottom + gap + popupH <= window.innerHeight;
            const top = fitsBelow ? r.bottom + gap : Math.max(8, r.top - gap - popupH);
            setPos({top, left: r.left});
        };
        recompute();
        window.addEventListener('scroll', recompute, true);
        window.addEventListener('resize', recompute);
        return () => {
            window.removeEventListener('scroll', recompute, true);
            window.removeEventListener('resize', recompute);
        };
    }, [open]);

    const pickDate = React.useCallback((iso: string): void => {
        const el = innerRef.current;
        if (!el) return;
        // Drive the controlled / uncontrolled field through the native value
        // setter so React notices the change and existing onChange handlers
        // fire — identical to a hand-typed value.
        const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
        if (setter) {
            setter.call(el, iso);
        } else {
            el.value = iso;
        }
        el.dispatchEvent(new Event('input', {bubbles: true}));
        el.dispatchEvent(new Event('change', {bubbles: true}));
        closePopup();
    }, [closePopup]);

    const inputCls = ['form-control', 'cursor-pointer', 'custom-date-input', className].filter(Boolean).join(' ');

    return (
        <div ref={wrapperRef} className="relative inline-block w-full" data-custom-datepicker>
            <input
                ref={innerRef}
                type="date"
                className={inputCls}
                value={value as string | number | readonly string[] | undefined}
                defaultValue={defaultValue as string | number | readonly string[] | undefined}
                name={name}
                min={min}
                max={max}
                // Block the native picker — we paint our own. preventDefault on
                // mousedown stops Chromium's calendar dropdown from opening.
                onMouseDown={e => { e.preventDefault(); setOpen(o => !o); innerRef.current?.focus(); }}
                onClick={e => { e.preventDefault(); onClick?.(e); }}
                onKeyDown={e => {
                    if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        setOpen(true);
                    } else if (e.key === 'Escape') {
                        closePopup();
                    }
                }}
                onFocus={onFocus}
                onChange={onChange}
                onBlur={onBlur}
                {...rest}
            />
            {open && (
                <Portal>
                    <div
                        ref={popupRef}
                        className="cdp-popup"
                        style={pos ? {top: pos.top, left: pos.left} : {visibility: 'hidden'}}
                    >
                        <CalendarPopup
                            isoValue={currentIsoValue}
                            minIso={typeof min === 'string' ? min : ''}
                            maxIso={typeof max === 'string' ? max : ''}
                            onPick={pickDate}
                            onClose={closePopup}
                        />
                    </div>
                </Portal>
            )}
        </div>
    );
});

// ── Legacy native input (time / datetime-local / month / week) ───────────────

const LegacyNativeInput = React.forwardRef<HTMLInputElement, Props>(function LegacyNativeInput(
    {type, onFocus, onClick, className, ...rest},
    ref,
) {
    const tryShow = (el: HTMLInputElement | null): void => {
        if (!el) return;
        const w = el as HTMLInputElement & {showPicker?: () => void};
        if (typeof w.showPicker === 'function') {
            try { w.showPicker(); } catch { /* user gesture / disabled / not supported */ }
        }
    };
    const handleFocus = (e: React.FocusEvent<HTMLInputElement>): void => { tryShow(e.currentTarget); onFocus?.(e); };
    const handleClick = (e: React.MouseEvent<HTMLInputElement>): void => { tryShow(e.currentTarget); onClick?.(e); };
    const cls = ['form-control', 'cursor-pointer', className].filter(Boolean).join(' ');
    return <input ref={ref} type={type} className={cls} onFocus={handleFocus} onClick={handleClick} {...rest} />;
});

// ── Calendar popup ──────────────────────────────────────────────────────────

interface PopupProps {
    isoValue: string;
    minIso: string;
    maxIso: string;
    onPick: (iso: string) => void;
    onClose: () => void;
}

function CalendarPopup({isoValue, minIso, maxIso, onPick}: PopupProps): JSX.Element {
    const today = React.useMemo(() => new Date(), []);
    const selected = React.useMemo(() => parseIsoDate(isoValue), [isoValue]);
    const initialMonth = selected ?? parseIsoDate(minIso) ?? today;

    const [visibleMonth, setVisibleMonth] = React.useState<Date>(() => new Date(initialMonth.getFullYear(), initialMonth.getMonth(), 1));

    const lang = uiLang();
    const locale = lang === 'EN' ? 'en' : 'ru';

    // Month + year header — Intl gives us the right month name in the right
    // language without dragging in date-fns.
    const monthLabel = React.useMemo(() => {
        const fmt = new Intl.DateTimeFormat(locale, {month: 'long', year: 'numeric'});
        const raw = fmt.format(visibleMonth);
        // Capitalise — `ru` Intl returns lowercase ("июнь 2026"). Match the
        // Cyrillic-friendly substring rather than the first char so the year
        // suffix is left alone if it ever ends up at the front.
        return raw.replace(/^(\p{Letter})/u, c => c.toUpperCase());
    }, [locale, visibleMonth]);

    // Weekday header, Monday-first (matches the rest of the app's calendars).
    const weekdays = React.useMemo(() => buildWeekdayLabels(locale), [locale]);

    // Day grid — 6 rows × 7 cols starting from the Monday on/before the 1st.
    const cells = React.useMemo(() => buildMonthCells(visibleMonth, minIso, maxIso), [visibleMonth, minIso, maxIso]);

    const monthBack = (): void => setVisibleMonth(m => new Date(m.getFullYear(), m.getMonth() - 1, 1));
    const monthFwd = (): void => setVisibleMonth(m => new Date(m.getFullYear(), m.getMonth() + 1, 1));

    const todayIso = formatIsoDate(today);
    const selectedIso = selected ? formatIsoDate(selected) : '';

    return (
        <div role="dialog" data-test-id="custom-datepicker-popup" onMouseDown={e => e.preventDefault()}>
            <div className="cdp-header">
                <button type="button" className="cdp-nav" aria-label="Prev month" onClick={monthBack}>‹</button>
                <span className="cdp-title">{monthLabel}</span>
                <button type="button" className="cdp-nav" aria-label="Next month" onClick={monthFwd}>›</button>
            </div>
            <div className="cdp-weekdays">
                {weekdays.map(w => <span key={w} className="cdp-weekday">{w}</span>)}
            </div>
            <div className="cdp-grid">
                {cells.map(cell => {
                    const cls = ['cdp-day'];
                    if (cell.outside) cls.push('cdp-day-outside');
                    if (cell.iso === todayIso) cls.push('cdp-day-today');
                    if (cell.iso === selectedIso) cls.push('cdp-day-selected');
                    if (cell.disabled) cls.push('cdp-day-disabled');
                    return (
                        <button
                            key={cell.iso}
                            type="button"
                            className={cls.join(' ')}
                            disabled={cell.disabled}
                            onClick={() => onPick(cell.iso)}
                            data-iso={cell.iso}
                        >
                            {cell.day}
                        </button>
                    );
                })}
            </div>
            <div className="cdp-footer">
                <button type="button" className="cdp-foot-link" onClick={() => onPick(todayIso)}>
                    {lang === 'EN' ? 'Today' : 'Сегодня'}
                </button>
                <button type="button" className="cdp-foot-link" onClick={() => onPick('')}>
                    {lang === 'EN' ? 'Clear' : 'Очистить'}
                </button>
            </div>
        </div>
    );
}

// ── helpers ─────────────────────────────────────────────────────────────────

function uiLang(): 'RU' | 'EN' {
    if (typeof window === 'undefined') return 'RU';
    const v = (window as Window & {__GARNET_UI_LANG__?: string}).__GARNET_UI_LANG__;
    return v === 'EN' ? 'EN' : 'RU';
}

function parseIsoDate(s: string): Date | null {
    if (!s) return null;
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s);
    if (!m) return null;
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return Number.isNaN(d.getTime()) ? null : d;
}

function formatIsoDate(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function buildWeekdayLabels(locale: string): string[] {
    // Render Monday=0 .. Sunday=6 short names using Intl. Capitalise.
    const ref = new Date(2024, 0, 1); // 2024-01-01 was a Monday
    const fmt = new Intl.DateTimeFormat(locale, {weekday: 'short'});
    const out: string[] = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(ref);
        d.setDate(ref.getDate() + i);
        const raw = fmt.format(d).replace(/\.$/, '');
        out.push(raw.replace(/^(\p{Letter})/u, c => c.toUpperCase()));
    }
    return out;
}

interface CalendarCell {
    iso: string;
    day: number;
    outside: boolean;
    disabled: boolean;
}

function buildMonthCells(visibleMonth: Date, minIso: string, maxIso: string): CalendarCell[] {
    const year = visibleMonth.getFullYear();
    const month = visibleMonth.getMonth();
    const firstDow = (new Date(year, month, 1).getDay() + 6) % 7; // 0=Mon
    const gridStart = new Date(year, month, 1 - firstDow);

    const min = minIso ? parseIsoDate(minIso) : null;
    const max = maxIso ? parseIsoDate(maxIso) : null;

    const cells: CalendarCell[] = [];
    for (let i = 0; i < 42; i++) {
        const d = new Date(gridStart);
        d.setDate(gridStart.getDate() + i);
        const iso = formatIsoDate(d);
        const outside = d.getMonth() !== month;
        const disabled = (min && d < min) || (max && d > max) || false;
        cells.push({iso, day: d.getDate(), outside, disabled});
    }
    return cells;
}
