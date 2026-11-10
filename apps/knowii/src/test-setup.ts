import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach } from 'vitest';
// Only this module: importing all of @knowii/common here would load Inertia before the specs mock it
import { leaveAllPresenceChannels } from '../../../libs/common/src/lib/hooks/use-presence';
import { fakeEcho, resetFakeEcho } from './test-utils/fake-echo';

// jsdom lacks the browser APIs that Headless UI uses for anchored popovers and transitions
class ResizeObserverStub {
  observe(): void {
    // Not needed in tests
  }
  unobserve(): void {
    // Not needed in tests
  }
  disconnect(): void {
    // Not needed in tests
  }
}

if (!('ResizeObserver' in globalThis)) {
  Object.defineProperty(globalThis, 'ResizeObserver', { value: ResizeObserverStub, writable: true });
}

if (!Element.prototype.getAnimations) {
  Element.prototype.getAnimations = () => [];
}

// Components subscribe to Reverb through useSocket (#1180): a fake Echo, unless a test defines its own
if (!('Echo' in window)) {
  Object.defineProperty(window, 'Echo', { value: fakeEcho, writable: true, configurable: true });
}

afterEach(() => {
  cleanup();
  // The presence channels stay joined a little after a page leaves them (#1187): never between two tests
  leaveAllPresenceChannels();
  resetFakeEcho();
});
