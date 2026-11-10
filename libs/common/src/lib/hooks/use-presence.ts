import { useCallback, useEffect, useRef, useState, useSyncExternalStore } from 'react';
import type { ApiResult } from '../api/api-result';
import type { PresenceMember } from '../types/presence.schema';

/**
 * Presence (#1187, ADR 0027): who is online in a community, who is in a chat space, and who is typing.
 *
 * A member who shows their online status joins the presence channel (Reverb). A member who hides it never joins: the
 * hook reads the list through the API every 30 seconds instead, and typing indicators stay off for them (sending one
 * would show that they are online).
 */

// WARNING: aligned with WS_CHANNEL_COMMUNITY_ONLINE and WS_CHANNEL_COMMUNITY_SPACE_PRESENCE (Constants.php)
export const communityOnlineChannel = (communityCuid: string) => `community.${communityCuid}.online`;
export const spacePresenceChannel = (communityCuid: string, spaceCuid: string) => `community.${communityCuid}.space.${spaceCuid}.presence`;

// A page change leaves the channel and the next page joins it again: wait a little, so the others see no flicker
const LEAVE_DELAY_MS = 3000;
// A typing indicator goes when no new signal comes for this long (the sender repeats it while they type)
const TYPING_EXPIRES_MS = 6000;
// The sender repeats the signal at most this often
const TYPING_SEND_INTERVAL_MS = 3000;
const POLL_INTERVAL_MS = 30000;

const TYPING_EVENT = 'typing';

interface TypingSignal {
  // '' for the main conversation, the cuid of the first message for a thread
  scope: string;
  typing: boolean;
}

interface PresenceSnapshot {
  members: PresenceMember[];
  // scope → member cuid → expiry time
  typing: Record<string, Record<string, number>>;
}

interface WhisperingChannel {
  here: (callback: (members: PresenceMember[]) => void) => WhisperingChannel;
  joining: (callback: (member: PresenceMember) => void) => WhisperingChannel;
  leaving: (callback: (member: PresenceMember) => void) => WhisperingChannel;
  listenForWhisper: (event: string, callback: (data: TypingSignal, metadata?: { user_id?: string }) => void) => WhisperingChannel;
  whisper: (event: string, data: TypingSignal) => WhisperingChannel;
}

class PresenceStore {
  private snapshot: PresenceSnapshot = { members: [], typing: {} };
  private readonly listeners = new Set<() => void>();
  private references = 0;
  private leaveTimer: ReturnType<typeof setTimeout> | null = null;
  private expiryTimer: ReturnType<typeof setTimeout> | null = null;
  readonly channel: WhisperingChannel;

  constructor(private readonly name: string) {
    this.channel = window.Echo.join(name) as unknown as WhisperingChannel;
    this.channel
      .here((members) => this.update({ members: dedupe(members) }))
      .joining((member) => this.update({ members: dedupe([...this.snapshot.members, member]) }))
      .leaving((member) => {
        const typing = Object.fromEntries(
          Object.entries(this.snapshot.typing).map(([scope, typists]) => [scope, omit(typists, member.cuid)]),
        );
        this.update({ members: this.snapshot.members.filter((m) => m.cuid !== member.cuid), typing });
      })
      .listenForWhisper(TYPING_EVENT, (signal, metadata) => this.onTyping(signal, metadata?.user_id));
  }

  getSnapshot = () => this.snapshot;

  subscribe = (listener: () => void) => {
    this.listeners.add(listener);
    return () => {
      this.listeners.delete(listener);
    };
  };

  retain() {
    this.references += 1;
    if (this.leaveTimer) {
      clearTimeout(this.leaveTimer);
      this.leaveTimer = null;
    }
  }

  release(onLeave: () => void) {
    this.references -= 1;
    if (this.references > 0) {
      return;
    }
    this.leaveTimer = setTimeout(() => {
      if (this.references <= 0) {
        if (this.expiryTimer) {
          clearTimeout(this.expiryTimer);
        }
        window.Echo.leaveChannel(`presence-${this.name}`);
        onLeave();
      }
    }, LEAVE_DELAY_MS);
  }

  private onTyping(signal: TypingSignal, userCuid: string | undefined) {
    // Reverb adds the identifier of the sender to client events on presence channels: a member cannot type as another one
    if (!userCuid || typeof signal?.scope !== 'string' || !this.snapshot.members.some((m) => m.cuid === userCuid)) {
      return;
    }
    const typists = { ...(this.snapshot.typing[signal.scope] ?? {}) };
    if (signal.typing) {
      typists[userCuid] = Date.now() + TYPING_EXPIRES_MS;
    } else {
      delete typists[userCuid];
    }
    this.update({ typing: { ...this.snapshot.typing, [signal.scope]: typists } });
    this.scheduleExpiry();
  }

  private scheduleExpiry() {
    if (this.expiryTimer) {
      clearTimeout(this.expiryTimer);
    }
    const expiries = Object.values(this.snapshot.typing).flatMap((typists) => Object.values(typists));
    if (expiries.length === 0) {
      return;
    }
    const next = Math.max(0, Math.min(...expiries) - Date.now());
    this.expiryTimer = setTimeout(() => {
      const now = Date.now();
      const typing = Object.fromEntries(
        Object.entries(this.snapshot.typing).map(([scope, typists]) => [
          scope,
          Object.fromEntries(Object.entries(typists).filter(([, expiry]) => expiry > now)),
        ]),
      );
      this.update({ typing });
      this.scheduleExpiry();
    }, next + 50);
  }

  private update(changes: Partial<PresenceSnapshot>) {
    this.snapshot = { ...this.snapshot, ...changes };
    this.listeners.forEach((listener) => listener());
  }
}

const dedupe = (members: PresenceMember[]) => [...new Map(members.map((member) => [member.cuid, member])).values()];
const omit = (record: Record<string, number>, key: string) => Object.fromEntries(Object.entries(record).filter(([k]) => k !== key));

const stores = new Map<string, PresenceStore>();

function acquire(name: string): PresenceStore {
  let store = stores.get(name);
  if (!store) {
    store = new PresenceStore(name);
    stores.set(name, store);
  }
  store.retain();
  return store;
}

/**
 * Leave every presence channel at once, for example right after the member hides their online status
 */
export function leaveAllPresenceChannels(): void {
  for (const name of stores.keys()) {
    window.Echo.leaveChannel(`presence-${name}`);
  }
  stores.clear();
}

const EMPTY: PresenceSnapshot = { members: [], typing: {} };
const noopSubscribe = () => () => undefined;

export interface UsePresenceOptions {
  /**
   * The presence channel (communityOnlineChannel, spacePresenceChannel); null: nothing to follow
   */
  channel: string | null;
  /**
   * The current member shows their online status: join the channel. False: read the list through `fetchMembers`
   */
  visible: boolean;
  fetchMembers: () => Promise<ApiResult<PresenceMember[]>>;
  currentUserCuid: string | null;
}

export interface Presence {
  members: PresenceMember[];
  /**
   * The other members who type in a scope ('' for the main conversation, the cuid of the first message for a thread)
   */
  typists: (scope: string) => PresenceMember[];
  /**
   * The current member types in a scope: tell the others (throttled). Does nothing while hidden.
   */
  notifyTyping: (scope: string) => void;
  /**
   * The current member sent the message or emptied the field
   */
  stopTyping: (scope: string) => void;
}

export function usePresence({ channel, visible, fetchMembers, currentUserCuid }: UsePresenceOptions): Presence {
  const live = channel !== null && visible;
  const [store, setStore] = useState<PresenceStore | null>(null);

  useEffect(() => {
    if (!live || channel === null) {
      setStore(null);
      return;
    }
    const acquired = acquire(channel);
    setStore(acquired);
    return () => acquired.release(() => stores.delete(channel));
  }, [live, channel]);

  const snapshot = useSyncExternalStore(store?.subscribe ?? noopSubscribe, store?.getSnapshot ?? (() => EMPTY));

  // Hidden: the list through the API, now and every 30 seconds
  const [polled, setPolled] = useState<PresenceMember[]>([]);
  const fetchRef = useRef(fetchMembers);
  fetchRef.current = fetchMembers;
  useEffect(() => {
    if (live || channel === null) {
      setPolled([]);
      return;
    }
    let active = true;
    const load = () =>
      void fetchRef.current().then((result) => {
        if (active && result.ok) {
          setPolled(result.data);
        }
      });
    load();
    const timer = setInterval(load, POLL_INTERVAL_MS);
    return () => {
      active = false;
      clearInterval(timer);
    };
  }, [live, channel]);

  const lastSent = useRef<Record<string, number>>({});

  const notifyTyping = useCallback(
    (scope: string) => {
      if (!store) {
        return;
      }
      const now = Date.now();
      if (now - (lastSent.current[scope] ?? 0) < TYPING_SEND_INTERVAL_MS) {
        return;
      }
      lastSent.current[scope] = now;
      store.channel.whisper(TYPING_EVENT, { scope, typing: true });
    },
    [store],
  );

  const stopTyping = useCallback(
    (scope: string) => {
      if (!store || !lastSent.current[scope]) {
        return;
      }
      delete lastSent.current[scope];
      store.channel.whisper(TYPING_EVENT, { scope, typing: false });
    },
    [store],
  );

  const members = live ? snapshot.members : polled;
  const typists = useCallback(
    (scope: string) => {
      const now = Date.now();
      const typing = snapshot.typing[scope] ?? {};
      return snapshot.members.filter((member) => member.cuid !== currentUserCuid && (typing[member.cuid] ?? 0) > now);
    },
    [snapshot, currentUserCuid],
  );

  return { members, typists, notifyTyping, stopTyping };
}
