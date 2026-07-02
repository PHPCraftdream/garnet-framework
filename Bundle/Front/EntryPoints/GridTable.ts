import {DomObserver} from '@common/Dom/DomObserver';
import {GridTable} from '@common/Dom/GridTable/GridTable';

const observer = DomObserver?.init();

observer?.defineAddClassHandler('grid-table-init', (element: HTMLElement) => {
    new GridTable(element);
});

