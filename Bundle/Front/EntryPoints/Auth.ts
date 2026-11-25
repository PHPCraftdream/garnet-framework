import {createIsland} from '@common/Islands/createIsland';

/** Registers the auth island entry point. */
createIsland({
    className: 'auth2-container-init',
    lazy: () => import('@framework/auth/Auth2'),
    exportName: 'Auth2Island',
});
