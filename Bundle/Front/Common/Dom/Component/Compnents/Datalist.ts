import {Component} from '@common/Dom/Component/Component';
import {IDataListItem} from '@common/Support/Models';
import {escapeValue} from '@common/Utils/Str/EscapeValue';
import {escapeHtml} from '@common/Utils/Str/EscapeHtml';

/**
 * Datalist — native <select> wrapper for large option lists.
 * Previously used TomSelect; now uses plain HTML select element.
 * Note: This class is currently unused and kept for backwards compatibility.
 */
export class Datalist extends Component {
    /** Creates the select wrapper for the supplied element, items, and value. */
    constructor(
        protected mainElement: HTMLSelectElement,
        protected dataListItems: IDataListItem[],
        protected value: string|null
    ) {
        super(mainElement);
    }

    /** Returns the currently selected value. */
    getValue = (): string => {
        return this.value;
    }

    /** Converts a data-list item to escaped option markup. */
    listItemMap = (item: IDataListItem): string => {
        return `<option value="${escapeValue(item.value)}">${escapeHtml(item.text)}</option>`;
    }

    /** Disables the underlying select element. */
    disable = () => {
        this.mainElement.disabled = true;
    };

    /** Enables the underlying select element. */
    enable = () => {
        this.mainElement.disabled = false;
    };

    /** Populates the select element and applies the initial value. */
    public override init = () => {
        this.mainElement.innerHTML = this.dataListItems.map(this.listItemMap).join('');
        this.mainElement.value = this.value;
    }
}
