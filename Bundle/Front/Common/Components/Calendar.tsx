import * as React from 'react';
import {useState, useCallback} from 'react';
import {resolvedUserTz} from '@common/Utils/DateUtils';

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

function pad(n: number): string {
    return String(n).padStart(2, '0');
}

/** Parse 'YYYY-MM-DD' without timezone shift (no Date constructor on ISO strings). */
function parseYMD(s: string): [number, number, number] {
    const parts = s.split('-');
    return [parseInt(parts[0], 10), parseInt(parts[1], 10), parseInt(parts[2], 10)];
}

export const Calendar: React.FC<Props> = ({
    startDate,
    endDate,
    dayNames,
    isProposed,
    restrictedDates,
    availableDates,
    onDateClick,
    idPrefix = 'calendar',
    hideEmptyWeeks = false,
    extraWeeks = 0,
    draggable = false,
    onDrop,
}) => {
    const [dragOverDate, setDragOverDate] = useState<string | null>(null);
    const [dragSourceDate, setDragSourceDate] = useState<string | null>(null);

    const handleDragStart = useCallback((e: React.DragEvent<HTMLTableCellElement>, dateStr: string) => {
        e.dataTransfer.setData('text/plain', dateStr);
        e.dataTransfer.effectAllowed = 'move';
        setDragSourceDate(dateStr);
    }, []);

    const handleDragOver = useCallback((e: React.DragEvent<HTMLTableCellElement>, dateStr: string) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (dragOverDate !== dateStr) {
            setDragOverDate(dateStr);
        }
    }, [dragOverDate]);

    const handleDragLeave = useCallback(() => {
        setDragOverDate(null);
    }, []);

    const handleDrop = useCallback((e: React.DragEvent<HTMLTableCellElement>, dateTo: string) => {
        e.preventDefault();
        const dateFrom = e.dataTransfer.getData('text/plain');
        setDragOverDate(null);
        setDragSourceDate(null);
        if (dateFrom && dateFrom !== dateTo && onDrop) {
            onDrop(dateFrom, dateTo);
        }
    }, [onDrop]);

    const handleDragEnd = useCallback(() => {
        setDragOverDate(null);
        setDragSourceDate(null);
    }, []);

    if (!startDate || !endDate) return <div id={idPrefix} />;

    const [startY, startM] = parseYMD(startDate);
    const [endY, endM] = parseYMD(endDate);
    const months: React.ReactNode[] = [];

    let curY = startY;
    let curM = startM;

    while (curY < endY || (curY === endY && curM <= endM)) {
        const month0 = curM - 1; // 0-indexed for Date constructor
        const firstDay = new Date(curY, month0, 1).getDay();
        const daysInMonth = new Date(curY, month0 + 1, 0).getDate();
        const title = new Intl.DateTimeFormat(undefined, {timeZone: resolvedUserTz(), month: 'long', year: 'numeric'}).format(new Date(curY, month0, 1));

        const rows: { cells: React.ReactNode[]; hasProposed: boolean }[] = [];
        let row: React.ReactNode[] = [];
        let rowHasProposed = false;

        for (let j = 0; j < firstDay; j++) {
            row.push(<td key={`e-${j}`} className="bg-surface-alt text-center text-sm py-1 px-1.5 border border-default" />);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${curY}-${pad(curM)}-${pad(day)}`;
            let cls = 'bg-surface-alt';
            let titleAttr = '';
            let clickable = false;
            let proposed = false;

            let dayType = '';
            if (dateStr >= startDate && dateStr <= endDate) {
                if (isProposed(dateStr)) {
                    cls = 'bg-accent text-accent-text font-bold';
                    clickable = !!onDateClick;
                    proposed = true;
                    dayType = 'proposed';
                } else if (restrictedDates[dateStr]) {
                    cls = 'bg-danger-subtle cursor-help';
                    titleAttr = restrictedDates[dateStr];
                    dayType = 'restricted';
                } else if (availableDates[dateStr] !== undefined) {
                    cls = 'bg-success-subtle';
                    clickable = !!onDateClick;
                    dayType = 'available';
                }
            }

            if (proposed) rowHasProposed = true;

            const hoverCls = clickable ? (proposed ? 'cursor-pointer hover:bg-accent-hover' : 'cursor-pointer hover:bg-accent-subtle') : '';
            const isDraggable = draggable && proposed;
            const isDropTarget = draggable && dateStr >= startDate && dateStr <= endDate && !restrictedDates[dateStr];
            const isOver = dragOverDate === dateStr && dateStr !== dragSourceDate;

            row.push(
                <td
                    key={dateStr}
                    className={`text-center text-sm py-1 px-1.5 border border-default ${proposed ? '' : 'text-on-surface'} ${cls} ${hoverCls}${isDraggable ? ' cursor-grab' : ''}${isOver ? ' bg-accent-subtle' : ''}`}
                    title={titleAttr || undefined}
                    data-day-type={dayType || undefined}
                    data-click-date={clickable ? dateStr : undefined}
                    onClick={clickable ? () => onDateClick?.(dateStr) : undefined}
                    draggable={isDraggable || undefined}
                    onDragStart={isDraggable ? (e) => handleDragStart(e, dateStr) : undefined}
                    onDragEnd={isDraggable ? handleDragEnd : undefined}
                    onDragOver={isDropTarget ? (e) => handleDragOver(e, dateStr) : undefined}
                    onDragLeave={isDropTarget ? handleDragLeave : undefined}
                    onDrop={isDropTarget ? (e) => handleDrop(e, dateStr) : undefined}
                >
                    {day}
                </td>
            );

            if (row.length === 7) {
                rows.push({ cells: row, hasProposed: rowHasProposed });
                row = [];
                rowHasProposed = false;
            }
        }

        if (row.length > 0) rows.push({ cells: row, hasProposed: rowHasProposed });

        let visibleRows: typeof rows;
        if (hideEmptyWeeks) {
            // Find the last row index that has proposed dates
            let lastProposedIdx = -1;
            for (let ri = rows.length - 1; ri >= 0; ri--) {
                if (rows[ri].hasProposed) {
                    lastProposedIdx = ri;
                    break;
                }
            }
            // Keep proposed rows + up to extraWeeks empty rows before first and after last proposed row
            const firstProposedIdx = rows.findIndex(r => r.hasProposed);
            visibleRows = [];
            let extraBefore = 0;
            let extraAfter = 0;
            for (let ri = 0; ri < rows.length; ri++) {
                if (rows[ri].hasProposed) {
                    visibleRows.push(rows[ri]);
                    extraAfter = 0;
                } else if (firstProposedIdx >= 0 && ri < firstProposedIdx && extraWeeks > 0 && ri >= firstProposedIdx - extraWeeks) {
                    // Extra empty weeks BEFORE first proposed
                    visibleRows.push(rows[ri]);
                    extraBefore++;
                } else if (lastProposedIdx >= 0 && ri > lastProposedIdx && extraWeeks > 0 && extraAfter < extraWeeks) {
                    // Extra empty weeks AFTER last proposed
                    visibleRows.push(rows[ri]);
                    extraAfter++;
                }
            }
        } else {
            visibleRows = rows;
        }

        if (!hideEmptyWeeks || visibleRows.length > 0) {
            months.push(
                <div key={`${curY}-${curM}`} className="mb-3">
                    <h6>{title}</h6>
                    <table className="w-full border-collapse">
                        <thead>
                            <tr>
                                {dayNames.map(d => <th key={d} className="text-center text-xs text-muted py-1 px-1.5 border border-default bg-surface-alt">{d}</th>)}
                            </tr>
                        </thead>
                        <tbody>
                            {visibleRows.map((r, i) => <tr key={i}>{r.cells}</tr>)}
                        </tbody>
                    </table>
                </div>
            );
        }

        // Advance to next month
        curM++;
        if (curM > 12) {
            curM = 1;
            curY++;
        }
    }

    return (
        <div id={idPrefix}>
            {months}
        </div>
    );
};
