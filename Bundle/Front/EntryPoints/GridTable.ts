import {DomObserver} from '@common/Dom/El/DomObserver';
import {GridTable} from '@common/Dom/GridTable/GridTable';

const observer = DomObserver?.init();

observer?.defineAddClassHandler('grid-table-init', (element: HTMLElement) => {
    void new GridTable(element);
});

