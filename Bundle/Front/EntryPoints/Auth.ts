import {createIsland} from '@common/Islands/createIsland';

createIsland({className: 'auth2-container-init', lazy: () => import('@framework/auth/Auth2'), exportName: 'Auth2Island'});
