import {liveCountsState} from './liveCountsState';

/** Uses the shared subscribeLiveCounts operation for all counter consumers. */
export const subscribeLiveCounts = liveCountsState.subscribeLiveCounts;
