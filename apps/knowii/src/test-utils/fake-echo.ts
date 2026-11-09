type Handler = (payload: unknown) => void;

const listeners = new Map<string, Map<string, Set<Handler>>>();

/**
 * A fake Laravel Echo for the tests (#1180): components subscribe through useSocket, and tests send events with
 * emitSocketEvent(). Only private channels are used by the app.
 */
export const fakeEcho = {
  private(channel: string) {
    const events = listeners.get(channel) ?? new Map<string, Set<Handler>>();
    listeners.set(channel, events);
    return {
      listen(event: string, handler: Handler) {
        const handlers = events.get(event) ?? new Set<Handler>();
        handlers.add(handler);
        events.set(event, handlers);
        return this;
      },
      stopListening(event: string, handler: Handler) {
        events.get(event)?.delete(handler);
        return this;
      },
    };
  },
  leaveChannel(): void {
    // The listeners are removed by stopListening
  },
};

/**
 * Send an event as Reverb would (the channel name without the private- prefix, the event name without the dot)
 */
export function emitSocketEvent(channel: string, event: string, payload: unknown): void {
  listeners
    .get(channel)
    ?.get(`.${event}`)
    ?.forEach((handler) => handler(payload));
}

export function resetFakeEcho(): void {
  listeners.clear();
}
