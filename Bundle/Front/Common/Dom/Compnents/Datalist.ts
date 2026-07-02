import {Component} from '@common/Dom/Component';
import {IDataListItem} from '@common/Models';
import {escapeValue} from '@common/Utils/Str/EscapeValue';
import {escapeHtml} from '@common/Utils/Str/EscapeHtml';

/**
 * Datalist — native <select> wrapper for large option lists.
 * Previously used TomSelect; now uses plain HTML select element.
 * Note: This class is currently unused and kept for backwards compatibility.
 */
export class Datalist extends Component {
    constructor(
        protected mainElement: HTMLSelectElement,
        protected dataListItems: IDataListItem[],
        protected value: string|null
    ) {
        super(mainElement);
    }

    getValue = (): string => {
        return this.value;
    }

    listItemMap = (item: IDataListItem): string => {
        return `<option value="${escapeValue(item.value)}">${escapeHtml(item.text)}</option>`;
    }

    disable = () => {
        this.mainElement.disabled = true;
    };

    enable = () => {
        this.mainElement.disabled = false;
    };

    public override init = () => {
        this.mainElement.innerHTML = this.dataListItems.map(this.listItemMap).join('');
        this.mainElement.value = this.value;
    }
}
