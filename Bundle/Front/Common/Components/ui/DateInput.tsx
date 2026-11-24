import * as React from 'react';
import {Portal} from '../Layout/Portal';
import {AntiHookComponent} from '@react-carburetor/core';
import {DateInputState} from './DateInputState';

type DateInputType = 'date' | 'time' | 'datetime-local' | 'month' | 'week';

type FieldTemplate = React.ReactElement<React.InputHTMLAttributes<HTMLInputElement> & React.RefAttributes<HTMLInputElement>>;

const DATE_FIELD: FieldTemplate = <input className="custom-date-input" />;
const NATIVE_FIELD: FieldTemplate = <input className="cdp-native-input" />;

interface Props extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type' | 'className'> {
    type?: DateInputType;
    /** Template for the rendered input, e.g. `<input className="my-function" />`; defaults to the framework look. */
    field?: FieldTemplate;
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
export const DateInput = React.forwardRef<HTMLInputElement, Props>(function DateInput(props, ref) {
    return <DateInputView {...props} inputRef={ref} />;
});

interface DateInputViewProps extends Props {
    inputRef: React.ForwardedRef<HTMLInputElement>;
}

function assignInputRef(ref: React.ForwardedRef<HTMLInputElement>, input: HTMLInputElement | null): void {
    if (typeof ref === 'function') ref(input);
    else if (ref) ref.current = input;
}

class DateInputView extends AntiHookComponent<DateInputViewProps> {
    private readonly ui = new DateInputState();
    private readonly view = this.connectSelection(() => this.ui.store, state => state);
    private wrapper: HTMLDivElement | null = null;
    private input: HTMLInputElement | null = null;
    private popup: HTMLDivElement | null = null;

    public componentDidMount(): void {
        super.componentDidMount();
        document.addEventListener('mousedown', this.outsideClick);
        document.addEventListener('keydown', this.documentKey);
        window.addEventListener('scroll', this.reposition, true);
        window.addEventListener('resize', this.reposition);
    }

    public componentWillUnmount(): void {
        document.removeEventListener('mousedown', this.outsideClick);
        document.removeEventListener('keydown', this.documentKey);
        window.removeEventListener('scroll', this.reposition, true);
        window.removeEventListener('resize', this.reposition);
        super.componentWillUnmount();
    }

    private readonly setInput = (input: HTMLInputElement | null): void => {
        this.input = input;
        assignInputRef(this.props.inputRef, input);
    };

    public componentDidUpdate(previous: Readonly<DateInputViewProps>): void {
        super.componentDidUpdate(previous);
        if ((previous.type ?? 'date') === 'date' && this.input && previous.inputRef !== this.props.inputRef) {
            assignInputRef(previous.inputRef, null);
            assignInputRef(this.props.inputRef, this.input);
        }
    }
    private readonly setWrapper = (wrapper: HTMLDivElement | null): void => { this.wrapper = wrapper; };
    private readonly setPopup = (popup: HTMLDivElement | null): void => {
        this.popup = popup;
        if (popup) this.reposition();
    };
    private readonly reposition = (): void => {
        if (!this.ui.store.getData().open || !this.input || !this.popup) return;
        const rect = this.input.getBoundingClientRect();
        const height = this.popup.offsetHeight;
        const top = rect.bottom + 4 + height <= window.innerHeight
            ? rect.bottom + 4 : Math.max(8, rect.top - 4 - height);
        const state = this.ui.store.getData();
        if (!state.positioned || state.top !== top || state.left !== rect.left) this.ui.setPosition(top, rect.left);
    };
    private readonly outsideClick = (event: MouseEvent): void => {
        if (!this.ui.store.getData().open) return;
        const target = event.target as Node;
        if (!this.wrapper?.contains(target) && !this.popup?.contains(target)) this.ui.close();
    };
    private readonly documentKey = (event: KeyboardEvent): void => {
        if (event.key === 'Escape' && this.ui.store.getData().open) this.ui.close();
    };
    private readonly openPopup = (): void => {
        if (!this.ui.store.getData().open) this.ui.open(0, 0);
    };
    private readonly closePopup = (): void => this.ui.close();
    private readonly mouseDown = (event: React.MouseEvent<HTMLInputElement>): void => {
        event.preventDefault();
        if (this.ui.store.getData().open) this.closePopup();
        else this.openPopup();
        this.input?.focus();
    };
    private readonly click = (event: React.MouseEvent<HTMLInputElement>): void => {
        event.preventDefault();
        this.props.onClick?.(event);
    };
    private readonly keyDown = (event: React.KeyboardEvent<HTMLInputElement>): void => {
        if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
            event.preventDefault();
            this.openPopup();
        } else if (event.key === 'Escape') this.closePopup();
    };
    private readonly pickDate = (iso: string): void => {
        const el = this.input;
        if (!el) return;
        const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
        if (setter) setter.call(el, iso);
        else el.value = iso;
        el.dispatchEvent(new Event('input', {bubbles: true}));
        el.dispatchEvent(new Event('change', {bubbles: true}));
        this.closePopup();
    };

    public render() {
        return this.renderField(this.view());
    }

    private renderField({open, positioned, top, left}: ReturnType<DateInputState['store']['getData']>) {
        const {inputRef, field, type = 'date', onFocus, onClick, onChange, onBlur, value, defaultValue, name, min, max, ...rest} = this.props;
        if (type !== 'date') {
            return <LegacyNativeInput type={type} field={field} onFocus={onFocus} onClick={onClick} onChange={onChange} onBlur={onBlur} value={value} defaultValue={defaultValue} name={name} min={min} max={max} ref={inputRef} {...rest} />;
        }
        const currentIsoValue = String(value ?? defaultValue ?? '');

        return (
        <div ref={this.setWrapper} className="cdp-wrapper" data-custom-datepicker>
            {React.cloneElement(field ?? DATE_FIELD, {
                ref: this.setInput,
                type: 'date',
                value: value as string | number | readonly string[] | undefined,
                defaultValue: defaultValue as string | number | readonly string[] | undefined,
                name,
                min,
                max,
                onMouseDown: this.mouseDown,
                onClick: this.click,
                onKeyDown: this.keyDown,
                onFocus,
                onChange,
                onBlur,
                ...rest,
            })}
            {open && (
                <Portal>
                    <div
                        ref={this.setPopup}
                        className="cdp-popup"
                        style={positioned ? {top, left} : {visibility: 'hidden'}}
                    >
                        <CalendarPopup
                            isoValue={currentIsoValue}
                            minIso={typeof min === 'string' ? min : ''}
                            maxIso={typeof max === 'string' ? max : ''}
                            onPick={this.pickDate}
                            onClose={this.closePopup}
                        />
                    </div>
                </Portal>
            )}
        </div>
        );
    }
}

// ── Legacy native input (time / datetime-local / month / week) ───────────────

function tryShow(input: HTMLInputElement): void {
    if (typeof input.showPicker === 'function') {
        try { input.showPicker(); } catch { /* Браузер может отказать. */ }
    }
}

type NativeProps = Props;
class NativeDateField extends AntiHookComponent<NativeProps & {inputRef: React.ForwardedRef<HTMLInputElement>}> {
    private readonly focus = (event: React.FocusEvent<HTMLInputElement>) => {
        tryShow(event.currentTarget);
        this.props.onFocus?.(event);
    };
    private readonly click = (event: React.MouseEvent<HTMLInputElement>) => {
        tryShow(event.currentTarget);
        this.props.onClick?.(event);
    };

    render() {
        const {inputRef, field, type, onFocus: _onFocus, onClick: _onClick, ...rest} = this.props;
        return React.cloneElement(field ?? NATIVE_FIELD, {ref: inputRef, type, onFocus: this.focus, onClick: this.click, ...rest});
    }
}

const LegacyNativeInput = React.forwardRef<HTMLInputElement, NativeProps>((props, inputRef) =>
    <NativeDateField {...props} inputRef={inputRef} />,
);

// ── Calendar popup ──────────────────────────────────────────────────────────

interface PopupProps {
    isoValue: string;
    minIso: string;
    maxIso: string;
    onPick: (iso: string) => void;
    onClose: () => void;
}

const upperCase = (value: string) => value.toUpperCase();
const preventMouseDown = (event: React.MouseEvent) => event.preventDefault();
const renderWeekday = (day: string) => <span key={day} className="cdp-weekday">{day}</span>;

class CalendarPopup extends AntiHookComponent<PopupProps> {
    private readonly ui = new DateInputState();
    private readonly view = this.connectSelection(() => this.ui.store, state => state);
    private readonly today = new Date();

    public constructor(props: PopupProps) {
        super(props);
        const initial = parseIsoDate(props.isoValue) ?? parseIsoDate(props.minIso) ?? this.today;
        this.ui.open(initial.getFullYear(), initial.getMonth());
    }

    private readonly monthBack = (): void => this.ui.moveMonth(-1);
    private readonly monthFwd = (): void => this.ui.moveMonth(1);

    private readonly pick = (event: React.MouseEvent<HTMLButtonElement>) => this.props.onPick(event.currentTarget.dataset.iso ?? '');
    private readonly clear = () => this.props.onPick('');

    private renderDays(cells: CalendarCell[]) {
        const {isoValue} = this.props;
        const selected = parseIsoDate(isoValue);
        const selectedIso = selected ? formatIsoDate(selected) : '';
        const todayIso = formatIsoDate(this.today);
        const days: React.ReactNode[] = [];
        for (const cell of cells) {
            days.push(<button key={cell.iso} type="button" className="cdp-day" data-iso={cell.iso}
                data-outside={cell.outside} data-today={cell.iso === todayIso} data-selected={cell.iso === selectedIso}
                data-disabled={cell.disabled} disabled={cell.disabled} onClick={this.pick}>{cell.day}</button>);
        }
        return <div className="cdp-grid">{days}</div>;
    }

    public render() {
        const {minIso, maxIso} = this.props;
        const {visibleYear, visibleMonth: month} = this.view();
        const date = new Date(visibleYear, month, 1);
        const english = uiLang() === 'EN';
        const locale = english ? 'en' : 'ru';
        const label = new Intl.DateTimeFormat(locale, {month: 'long', year: 'numeric'}).format(date).replace(/^\p{Letter}/u, upperCase);
        return <div role="dialog" data-test-id="custom-datepicker-popup" onMouseDown={preventMouseDown}>
            <div className="cdp-header">
                <button type="button" className="cdp-nav" aria-label="Prev month" onClick={this.monthBack}>‹</button>
                <span className="cdp-title">{label}</span>
                <button type="button" className="cdp-nav" aria-label="Next month" onClick={this.monthFwd}>›</button>
            </div>
            <div className="cdp-weekdays">{buildWeekdayLabels(locale).map(renderWeekday)}</div>
            {this.renderDays(buildMonthCells(date, minIso, maxIso))}
            <div className="cdp-footer">
                <button type="button" className="cdp-foot-link" data-iso={formatIsoDate(this.today)} onClick={this.pick}>{english ? 'Today' : 'Сегодня'}</button>
                <button type="button" className="cdp-foot-link" onClick={this.clear}>{english ? 'Clear' : 'Очистить'}</button>
            </div>
        </div>;
    }
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
        out.push(raw.replace(/^\p{Letter}/u, upperCase));
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
