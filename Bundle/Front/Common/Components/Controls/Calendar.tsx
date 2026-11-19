import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {appLocale, resolvedUserTz} from '@common/Utils/Time/DateUtils';

interface Props {
    startDate: string;
    endDate: string;
    dayNames: string[];
    isProposed: (date: string) => boolean;
    restrictedDates: Record<string, string>;
    availableDates: Record<string, string>;
    onDateClick?: (date: string) => void;
    idPrefix?: string;
    hideEmptyWeeks?: boolean;
    /** When hideEmptyWeeks is true, add N extra empty weeks before first and after last proposed week (default 0). */
    extraWeeks?: number;
    draggable?: boolean;
    onDrop?: (dateFrom: string, dateTo: string) => void;
}

type CalendarDayType = '' | 'proposed' | 'restricted' | 'available';
type DragState = {dragOverDate: string | null; dragSourceDate: string | null};
type CalendarRow = {cells: React.ReactNode[]; hasProposed: boolean};

const hasProposedDates = (row: CalendarRow) => row.hasProposed;
const renderWeekday = (day: string) => <th key={day} className="calendar-weekday">{day}</th>;
const renderWeek = (row: CalendarRow, index: number) => <tr key={index}>{row.cells}</tr>;

function pad(n: number): string {
    return String(n).padStart(2, '0');
}

/** Parse 'YYYY-MM-DD' without timezone shift (no Date constructor on ISO strings). */
function parseYMD(s: string): [number, number, number] {
    const parts = s.split('-');
    return [parseInt(parts[0], 10), parseInt(parts[1], 10), parseInt(parts[2], 10)];
}

function visibleWeeks(rows: CalendarRow[], hideEmptyWeeks: boolean, extraWeeks: number): CalendarRow[] {
    if (!hideEmptyWeeks) return rows;

    let lastProposedIdx = -1;
    for (let ri = rows.length - 1; ri >= 0; ri--) {
        if (rows[ri].hasProposed) {
            lastProposedIdx = ri;
            break;
        }
    }
    const firstProposedIdx = rows.findIndex(hasProposedDates);
    const visibleRows: CalendarRow[] = [];
    let extraAfter = 0;
    for (let ri = 0; ri < rows.length; ri++) {
        if (rows[ri].hasProposed) {
            visibleRows.push(rows[ri]);
            extraAfter = 0;
        } else if (firstProposedIdx >= 0 && ri < firstProposedIdx && extraWeeks > 0 && ri >= firstProposedIdx - extraWeeks) {
            visibleRows.push(rows[ri]);
        } else if (lastProposedIdx >= 0 && ri > lastProposedIdx && extraWeeks > 0 && extraAfter < extraWeeks) {
            visibleRows.push(rows[ri]);
            extraAfter++;
        }
    }
    return visibleRows;
}

export class Calendar extends AntiHookComponent<Props> {
    private readonly dragState = new Carburetor<DragState>({dragOverDate: null, dragSourceDate: null});

    // Date maps may change in place between parent renders.
    private readonly updateOnParentRender = true;

    public shouldComponentUpdate(nextProps: Readonly<Props>, nextState: Readonly<{}>): boolean {
        return super.shouldComponentUpdate(nextProps, nextState) || this.updateOnParentRender;
    }

    private readonly handleClick = (e: React.MouseEvent<HTMLTableCellElement>) => {
        const date = e.currentTarget.dataset.calendarDate;
        if (date) this.props.onDateClick?.(date);
    };

    private readonly handleDragStart = (e: React.DragEvent<HTMLTableCellElement>) => {
        const date = e.currentTarget.dataset.calendarDate!;
        e.dataTransfer.setData('text/plain', date);
        e.dataTransfer.effectAllowed = 'move';
        this.dragState.setData({...this.dragState.getData(), dragSourceDate: date});
    };

    private readonly handleDragOver = (e: React.DragEvent<HTMLTableCellElement>) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const date = e.currentTarget.dataset.calendarDate!;
        if (this.dragState.getData().dragOverDate !== date) {
            this.dragState.setData({...this.dragState.getData(), dragOverDate: date});
        }
    };

    private readonly handleDragLeave = () => {
        this.dragState.setData({...this.dragState.getData(), dragOverDate: null});
    };

    private readonly handleDrop = (e: React.DragEvent<HTMLTableCellElement>) => {
        e.preventDefault();
        const dateFrom = e.dataTransfer.getData('text/plain');
        const dateTo = e.currentTarget.dataset.calendarDate!;
        this.dragState.setData({dragOverDate: null, dragSourceDate: null});
        if (dateFrom && dateFrom !== dateTo && this.props.onDrop) {
            this.props.onDrop(dateFrom, dateTo);
        }
    };

    private readonly handleDragEnd = () => {
        this.dragState.setData({dragOverDate: null, dragSourceDate: null});
    };

    private renderDay(year: number, month: number, day: number, drag: Readonly<DragState>): {cell: React.ReactNode; proposed: boolean} {
        const {startDate, endDate, isProposed, restrictedDates, availableDates, onDateClick, draggable = false} = this.props;
        const dateStr = `${year}-${pad(month)}-${pad(day)}`;
        let titleAttr = '';
        let clickable = false;
        let proposed = false;
        let dayType: CalendarDayType = '';
        if (dateStr >= startDate && dateStr <= endDate) {
            if (isProposed(dateStr)) {
                clickable = !!onDateClick;
                proposed = true;
                dayType = 'proposed';
            } else if (restrictedDates[dateStr]) {
                titleAttr = restrictedDates[dateStr];
                dayType = 'restricted';
            } else if (availableDates[dateStr] !== undefined) {
                clickable = !!onDateClick;
                dayType = 'available';
            }
        }
        const isDraggable = draggable && proposed;
        const isDropTarget = draggable && dateStr >= startDate && dateStr <= endDate && !restrictedDates[dateStr];
        const isOver = drag.dragOverDate === dateStr && dateStr !== drag.dragSourceDate;
        const cell = (
            <td
                key={dateStr}
                className={dayType === 'proposed'
                    ? (clickable ? (isDraggable ? 'calendar-proposed-drag-action' : 'calendar-proposed-action') : isDraggable ? 'calendar-proposed-drag' : 'calendar-proposed-day')
                    : dayType === 'restricted' ? 'calendar-restricted-day'
                    : dayType === 'available' ? (clickable ? 'calendar-available-action' : 'calendar-available-day')
                    : 'calendar-normal-day'}
                data-over={isOver ? 'true' : undefined}
                data-calendar-date={dateStr}
                title={titleAttr || undefined}
                data-day-type={dayType || undefined}
                data-click-date={clickable ? dateStr : undefined}
                onClick={clickable ? this.handleClick : undefined}
                draggable={isDraggable || undefined}
                onDragStart={isDraggable ? this.handleDragStart : undefined}
                onDragEnd={isDraggable ? this.handleDragEnd : undefined}
                onDragOver={isDropTarget ? this.handleDragOver : undefined}
                onDragLeave={isDropTarget ? this.handleDragLeave : undefined}
                onDrop={isDropTarget ? this.handleDrop : undefined}
            >
                {day}
            </td>
        );
        return {cell, proposed};
    }

    private monthWeeks(year: number, month: number, drag: Readonly<DragState>): CalendarRow[] {
        const month0 = month - 1;
        const firstDay = new Date(year, month0, 1).getDay();
        const daysInMonth = new Date(year, month0 + 1, 0).getDate();
        const rows: CalendarRow[] = [];
        let row: React.ReactNode[] = [];
        let rowHasProposed = false;
        for (let j = 0; j < firstDay; j++) {
            row.push(<td key={`e-${j}`} className="calendar-empty-cell" />);
        }
        for (let day = 1; day <= daysInMonth; day++) {
            const result = this.renderDay(year, month, day, drag);
            if (result.proposed) rowHasProposed = true;
            row.push(result.cell);
            if (row.length === 7) {
                rows.push({cells: row, hasProposed: rowHasProposed});
                row = [];
                rowHasProposed = false;
            }
        }
        if (row.length > 0) rows.push({cells: row, hasProposed: rowHasProposed});
        return rows;
    }

    private renderMonth(year: number, month: number, drag: Readonly<DragState>): React.ReactElement | null {
        const {dayNames, hideEmptyWeeks = false, extraWeeks = 0} = this.props;
        // Use the UI locale, not the browser locale, for month headings.
        const title = new Intl.DateTimeFormat(appLocale(), {timeZone: resolvedUserTz(), month: 'long', year: 'numeric'}).format(new Date(year, month - 1, 1));
        const rows = visibleWeeks(this.monthWeeks(year, month, drag), hideEmptyWeeks, extraWeeks);
        if (hideEmptyWeeks && rows.length === 0) return null;
        return (
            <div key={`${year}-${month}`} className="calendar-month">
                <h6>{title}</h6>
                <table className="calendar-month-table">
                    <thead>
                        <tr>{dayNames.map(renderWeekday)}</tr>
                    </thead>
                    <tbody>{rows.map(renderWeek)}</tbody>
                </table>
            </div>
        );
    }

    public render() {
        const {startDate, endDate, idPrefix = 'calendar'} = this.props;
        const {dragOverDate, dragSourceDate} = this.useCarburetor(this.dragState);
        if (!startDate || !endDate) return <div id={idPrefix} />;

        const [startY, startM] = parseYMD(startDate);
        const [endY, endM] = parseYMD(endDate);
        const months: React.ReactNode[] = [];
        const drag = {dragOverDate, dragSourceDate};
        let curY = startY;
        let curM = startM;
        while (curY < endY || (curY === endY && curM <= endM)) {
            const month = this.renderMonth(curY, curM, drag);
            if (month !== null) months.push(month);
            curM++;
            if (curM > 12) {
                curM = 1;
                curY++;
            }
        }
        return <div id={idPrefix}>{months}</div>;
    }
}
