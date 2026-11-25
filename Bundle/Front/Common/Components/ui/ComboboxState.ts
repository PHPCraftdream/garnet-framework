import {Carburetor} from '@react-carburetor/core';

export class ComboboxState {
    public readonly store = new Carburetor({open: false, search: ''});

    /** Sets whether the combobox is open. */
    public readonly setOpen = (open: boolean): void => {
        this.store.setData({...this.store.getData(), open});
    };

    /** Updates the search query. */
    public readonly setSearch = (search: string): void => {
        this.store.setData({...this.store.getData(), search});
    };

    /** Clears the search query and closes the combobox. */
    public readonly clearAndClose = (): void => {
        this.store.setData({open: false, search: ''});
    };
}
