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
  join(channel: string) {
    return fakeJoin(channel);
  },
  leaveChannel(): void {
    // The listeners are removed by stopListening
  },
};

interface FakePresence {
  here: Set<(members: unknown[]) => void>;
  joining: Set<(member: unknown) => void>;
  leaving: Set<(member: unknown) => void>;
  whispers: Map<string, Set<(data: unknown, metadata: { user_id: string }) => void>>;
  sent: Array<{ event: string; data: unknown }>;
}

const presenceChannels = new Map<string, FakePresence>();

/**
 * Presence channels (#1187): Echo.join(), with here, joining, leaving and whispers
 */
export function fakeJoin(channel: string) {
  const presence: FakePresence = presenceChannels.get(channel) ?? {
    here: new Set(),
    joining: new Set(),
    leaving: new Set(),
    whispers: new Map(),
    sent: [],
  };
  presenceChannels.set(channel, presence);
  const api = {
    here(callback: (members: unknown[]) => void) {
      presence.here.add(callback);
      return api;
    },
    joining(callback: (member: unknown) => void) {
      presence.joining.add(callback);
      return api;
    },
    leaving(callback: (member: unknown) => void) {
      presence.leaving.add(callback);
      return api;
    },
    listenForWhisper(event: string, callback: (data: unknown, metadata: { user_id: string }) => void) {
      const callbacks = presence.whispers.get(event) ?? new Set();
      callbacks.add(callback);
      presence.whispers.set(event, callbacks);
      return api;
    },
    whisper(event: string, data: unknown) {
      presence.sent.push({ event, data });
      return api;
    },
  };
  return api;
}

/**
 * The members in a presence channel, as Reverb sends them after the join
 */
export function emitPresenceHere(channel: string, members: unknown[]): void {
  presenceChannels.get(channel)?.here.forEach((callback) => callback(members));
}

/**
 * A client event of another member: Reverb adds their identifier (user_id)
 */
export function emitWhisper(channel: string, event: string, data: unknown, userCuid: string): void {
  presenceChannels
    .get(channel)
    ?.whispers.get(event)
    ?.forEach((callback) => callback(data, { user_id: userCuid }));
}

/**
 * The client events that the current member sent on a presence channel
 */
export function sentWhispers(channel: string): Array<{ event: string; data: unknown }> {
  return presenceChannels.get(channel)?.sent ?? [];
}

export function joinedPresenceChannels(): string[] {
  return [...presenceChannels.keys()];
}

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
  presenceChannels.clear();
}
