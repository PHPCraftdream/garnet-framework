import {Carburetor} from '@react-carburetor/core';

interface DateInputSnapshot {
    open: boolean;
    positioned: boolean;
    top: number;
    left: number;
    visibleYear: number;
    visibleMonth: number;
}

export class DateInputState {
    public readonly store = new Carburetor<DateInputSnapshot>({
        open: false,
        positioned: false,
        top: 0,
        left: 0,
        visibleYear: 0,
        visibleMonth: 0,
    });

    public readonly open = (year: number, month: number): void => {
        this.store.setData({
            ...this.store.getData(),
            open: true,
            positioned: false,
            visibleYear: year,
            visibleMonth: month,
        });
    };

    public readonly close = (): void => {
        this.store.setData({...this.store.getData(), open: false, positioned: false});
    };

    public readonly setPosition = (top: number, left: number): void => {
        this.store.setData({...this.store.getData(), positioned: true, top, left});
    };

    public readonly moveMonth = (offset: number): void => {
        const state = this.store.getData();
        const total = state.visibleYear * 12 + state.visibleMonth + offset;
        const year = Math.floor(total / 12);
        this.store.setData({...state, visibleYear: year, visibleMonth: total - year * 12});
    };
}
