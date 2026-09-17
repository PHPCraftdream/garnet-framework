import {createIsland} from '@common/Islands/createIsland';

createIsland({className: 'admin-dashboard-init', lazy: () => import('../Islands/AdminDashboard'), exportName: 'AdminDashboard'});
