import {FEventHandler, IEvent, IEventHandler, IGarnetWindow, IPageEvents, TEventParams} from '@common/Support/Models';
import {uid} from '@common/Utils/Str/Uid';

const w: IGarnetWindow = window as IGarnetWindow;

export class PageEvents implements IPageEvents {
    /**
     * Initialize the page-event singleton on the browser window.
     *
     * @returns The shared page-event dispatcher.
     */
    static init = (): IPageEvents => {
        if (!w.__GARNET_PAGE_EVENTS__) {
            w.__GARNET_PAGE_EVENTS__ = new PageEvents();
        }

        return w.__GARNET_PAGE_EVENTS__;
    }

    protected subscribers: Record<string, IEventHandler[]> = {};
    protected eventsQueue: Record<string, IEvent[]> = {};

    /**
     * Subscribe a handler to an event, replaying any queued emissions.
     *
     * @param name - Event name to subscribe to.
     * @param handler - Callback invoked with each event's parameters.
     * @returns The subscription identifier used to unsubscribe.
     */
    subscribe = (name: string, handler: FEventHandler): string => {
        const evObj = {id: uid('event'), handler};

        if (this.subscribers[name]) {
            this.subscribers[name].push(evObj);
        } else {
            this.subscribers[name] = [evObj];
        }

        if (this.eventsQueue[name]) {
            for (let item of this.eventsQueue[name]) {
                handler(item.params);
            }
            this.eventsQueue[name] = [];
        }

        return evObj.id;
    };

    /**
     * Remove a subscription by its identifier.
     *
     * @param id - Subscription identifier returned from {@link subscribe}.
     * @returns Whether a matching subscription was removed.
     */
    unsubscribe = (id: string): boolean => {
        for (const name of Object.keys(this.subscribers)) {
            const handlers = this.subscribers[name];
            const index = handlers.findIndex(h => h.id === id);

            if (index !== -1) {
                handlers.splice(index, 1);

                if (handlers.length === 0) {
                    delete this.subscribers[name];
                }

                return true;
            }
        }

        return false;
    };

    /**
     * Emit an event, queuing it when there are no current subscribers.
     *
     * @param name - Event name to emit.
     * @param params - Data passed to subscribed handlers.
     */
    emmit = (name: string, params: TEventParams): void => {
        if (this.subscribers[name]) {
            for (let handler of this.subscribers[name]) {
                handler.handler(params);
            }

            return;
        }

        const evObj: IEvent = {name, params};

        if (this.eventsQueue[name]) {
            this.eventsQueue[name].push(evObj);
            return;
        }

        this.eventsQueue[name] = [evObj];
    };
}
