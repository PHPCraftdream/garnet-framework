import * as React from 'react';
import {useEffect, useRef} from 'react';
import {useBodyScrollLock} from '../../hooks/useBodyScrollLock';
import {DetailSection, GlobalRenders} from './types';
import {AdminGrid} from './AdminGrid';

interface Props {
    title: string;
    sections: DetailSection[];
    globalRenders?: GlobalRenders;
    onClose: () => void;
}

export const DetailViewModal: React.FC<Props> = ({title, sections, globalRenders = {}, onClose}) => {
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
            className="fixed inset-0 flex items-start justify-center z-50 overflow-y-auto py-8 bg-black/45"
            onClick={handleOverlayClick}
        >
            <div
                className="bg-surface rounded-lg shadow-xl flex flex-col w-full"
                style={{maxWidth: '92vw'}}
                onClick={e => e.stopPropagation()}
            >
                <div className="flex items-center justify-between px-5 py-3 border-b border-default sticky top-0 bg-surface rounded-t-lg">
                    <h5 className="font-semibold text-on-surface m-0">{title}</h5>
                    <button type="button" className="btn btn-sm btn-outline-secondary" title="Close" onClick={onClose}>&times;</button>
                </div>

                <div className="p-5 flex flex-col gap-6">
                    {sections.map((section, i) => (
                        <div key={i}>
                            <h6 className="font-semibold text-secondary mb-2 pb-1 border-b border-subtle">
                                {section.title}
                                <span className="text-xs font-normal text-muted ml-2">({section.rows.length})</span>
                            </h6>
                            <AdminGrid
                                rows={section.rows}
                                config={section.gridConfig}
                                globalRenders={globalRenders}
                                rowKey={r => (r as {id?: number}).id ?? i}
                            />
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};
