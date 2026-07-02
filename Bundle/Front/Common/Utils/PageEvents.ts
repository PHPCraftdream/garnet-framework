import {FEventHandler, IEvent, IEventHandler, IGarnetWindow, IPageEvents, TEventParams} from '@common/Models';
import {uid} from '@common/Utils/Str/Uid';

const w: IGarnetWindow = window as IGarnetWindow;

export class PageEvents implements IPageEvents {
    static init = (): IPageEvents => {
        if (!w.__GARNET_PAGE_EVENTS__) {
            w.__GARNET_PAGE_EVENTS__ = new PageEvents();
        }

        return w.__GARNET_PAGE_EVENTS__;
    }

    protected subscribers: Record<string, IEventHandler[]> = {};
    protected eventsQueue: Record<string, IEvent[]> = {};

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
