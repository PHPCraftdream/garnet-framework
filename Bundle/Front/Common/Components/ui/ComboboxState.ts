import {Carburetor} from '@react-carburetor/core';

export class ComboboxState {
    public readonly store = new Carburetor({open: false, search: ''});

    public readonly setOpen = (open: boolean): void => {
        this.store.setData({...this.store.getData(), open});
    };

    public readonly setSearch = (search: string): void => {
        this.store.setData({...this.store.getData(), search});
    };

    public readonly clearAndClose = (): void => {
        this.store.setData({open: false, search: ''});
    };
}
