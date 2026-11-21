import { describe, expect, it } from 'vitest';
import { COMMUNITY_SPACE_EVENT_CALENDAR_FILE_API_PATH, MY_EVENTS_URL, USER_EVENTS_API_PATH } from './constants';

describe('the constants of "My events" and of the calendar file (#1515)', () => {
  // WARNING: aligned with ApiRoutes::COMMUNITY_SPACE_EVENT_CALENDAR_FILE, ApiRoutes::USER_EVENTS and routes/web.php
  it('mirror the paths of the API and the name of the page', () => {
    expect(COMMUNITY_SPACE_EVENT_CALENDAR_FILE_API_PATH).toBe(
      '/api/v1/communities/{communityCuid}/spaces/{spaceCuid}/events/{eventCuid}/calendar-file',
    );
    expect(USER_EVENTS_API_PATH).toBe('/api/v1/user/events');
    expect(MY_EVENTS_URL).toBe('events.index');
  });
});
