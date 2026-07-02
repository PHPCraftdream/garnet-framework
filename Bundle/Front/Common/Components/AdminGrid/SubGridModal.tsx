import * as React from 'react';
import {useEffect, useRef} from 'react';
import {useBodyScrollLock} from '../../hooks/useBodyScrollLock';
import {GlobalRenders, GridConfig} from './types';
import {AdminGrid} from './AdminGrid';

interface Props {
    title: string;
    rows: unknown[];
    gridConfig: GridConfig;
    globalRenders?: GlobalRenders;
    onClose: () => void;
}

export const SubGridModal: React.FC<Props> = ({title, rows, gridConfig, globalRenders = {}, onClose}) => {
    useBodyScrollLock(true);
    const overlayRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const handleKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [onClose]);

    const handleOverlayClick = (e: React.MouseEvent) => {
        if (e.target === overlayRef.current) onClose();
    };

    return (
        <div
            ref={overlayRef}
            className="fixed inset-0 flex items-center justify-center z-50 bg-black/45"
            onClick={handleOverlayClick}
        >
            <div
                className="bg-surface rounded-lg shadow-xl flex flex-col"
                style={{maxWidth: '90vw', maxHeight: '85vh', minWidth: '480px'}}
                onClick={e => e.stopPropagation()}
            >
                <div className="flex items-center justify-between px-5 py-3 border-b border-default">
                    <h5 className="font-semibold text-on-surface m-0">{title}</h5>
                    <button type="button" className="btn btn-sm btn-outline-secondary" title="Close" onClick={onClose}>&times;</button>
                </div>
                <div className="overflow-auto p-4 flex-1">
                    <AdminGrid
                        rows={rows}
                        config={gridConfig}
                        globalRenders={globalRenders}
                        rowKey={r => (r as {id: number}).id}
                    />
                </div>
            </div>
        </div>
    );
};
