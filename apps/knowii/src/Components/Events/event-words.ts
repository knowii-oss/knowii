import type { EventAnswerValue } from '@knowii/common';

/**
 * The three answers, in the words of the design (3.6)
 */
const ANSWER_NAMES: Record<EventAnswerValue, string> = { yes: 'Yes', replay: 'Replay only', no: 'No' };

/**
 * "12 members", "1 member"
 */
const members = (count: number) => (count === 1 ? '1 member' : `${count} members`);

/**
 * The words of the screens of events (#1514, #1515, ADR 0053), in one place. A text marked [Q] is a text where the
 * design is silent: the owner confirmed these words on 2026-11-20. A text marked [W] is a text of the screens of the
 * answers (#1515) that the design does not give: a proposal that waits for the owner. A word changes here, and nowhere
 * else. The specs read these values.
 */
export const EVENT_WORDS = {
  // The list of an events space (design 6.1)
  newEvent: 'New event',
  upcoming: 'Upcoming',
  past: 'Past',
  scopesLabel: 'Events',
  // The names of the two lists, for screen readers
  upcomingList: 'Upcoming events',
  pastList: 'Past events',
  loadOlder: 'Load older',
  // [Q] The design names only "Load older" (the past list)
  loadMore: 'Load more',
  // "20 more loaded": the real number of new cards
  loaded: (count: number) => `${count} more loaded`,
  // What the status says after a press on "Upcoming" or "Past": the name of the list that opened, and how many events
  // it holds. [Q] The form of the count
  scopeOpened: (name: string, count: number) => `${name}: ${count}`,
  // [Q] The four texts of the empty lists
  emptyUpcomingTitle: 'No upcoming events',
  emptyUpcomingDescription: 'Events that are planned will show here.',
  emptyPastTitle: 'No past events',
  emptyPastDescription: 'Events that ended will show here.',

  // The labels of a state: always text, never a color alone (design 11.2)
  draft: 'Draft',
  cancelled: 'Cancelled',
  happeningNow: 'Happening now',
  over: 'This event is over',
  // [Q] Who sees a draft, on its page
  draftNotice: 'Draft. Only the host, the owner and the admins see it.',

  // The host
  hostedBy: (name: string) => `Hosted by ${name}`,
  deletedMember: 'Deleted member',
  host: 'Host',

  // The times (design 4.3)
  // The time follows it, in a `time` element: "The host's time: Wednesday 18 November, 17:00 Central European ..."
  hostTimeLabel: "The host's time:",

  // The meeting block (design 3.7, 6.2 point 4)
  meeting: 'Meeting',
  joinMeeting: 'Join the meeting',
  opensInNewTab: 'Opens in a new tab',
  place: 'Place',
  meetingToCome: 'Meeting address to come',
  meetingForMembers: 'The meeting address is for members.',
  signIn: 'Sign in',
  // [W] A member of an event whose address is for the members who answer Yes, while the event takes answers
  meetingForYes: 'The meeting address is for the members who answer Yes.',
  // [W] The same member, when the event takes no answers any more (cancelled, over)
  meetingForYesClosed: 'The meeting address is for the members who answered Yes.',

  // The answer of a member (#1515, design 3.6, 6.4 and 11.4)
  yourAnswer: 'Your answer',
  answerNames: ANSWER_NAMES,
  // What the status says after a save: "Saved: Yes"
  saved: (answer: EventAnswerValue) => `Saved: ${ANSWER_NAMES[answer]}`,
  // Who sees the answer (design 10.1 point 3, in the words of the approved policy sentence: question 4 of the E2 brief)
  answerPrivacy: 'The host and the team see your answer (Yes or Replay only). Other members do not.',
  signInToAnswer: 'Sign in to answer',
  // [W] A radio cannot be unchecked: a button clears the answer
  clearAnswer: 'Clear my answer',
  // [W] What the status says after a clear
  answerCleared: 'Answer cleared',
  // The question before a member with a Yes on a full event leaves it (the words of the owner, 2026-11-20)
  giveUpPlaceQuestion: 'Give up your place?',
  giveUpPlaceExplanation: 'This event is full. If you change your answer, another member can take your place.',
  giveUpPlaceConfirm: 'Give up my place',
  keepPlace: 'Keep my place',
  // [W] The old answer of a member, as text, on an event that takes no answers: "Your answer: Yes"
  oldAnswer: (answer: EventAnswerValue) => `Your answer: ${ANSWER_NAMES[answer]}`,
  // The places that are left, with a limit (design 7.6 point 4). [W] "1 place left"
  placesLeft: (count: number) => (count === 1 ? '1 place left' : `${count} places left`),
  full: 'Full',

  // The labels of a card and of "Next events" (design 6.1 point 4). [W] "You said Replay only" and "You said No"
  youSaid: (answer: EventAnswerValue) => `You said ${ANSWER_NAMES[answer]}`,

  // "Answers", for the host, the owner and the admins (design 6.4 point 3)
  answers: 'Answers',
  // [W] Everything of the dialog below
  answersDescription: 'The host, the owner and the admins see this list. Other members do not.',
  answersFilter: 'Answers to show',
  // "Yes (12)", "Replay only (3)"
  answersOf: (answer: EventAnswerValue, count: number) => `${ANSWER_NAMES[answer]} (${count})`,
  placesTaken: (taken: number, limit: number) => `${taken} of ${limit} places taken`,
  answeredNo: (count: number) => (count === 0 ? 'No member answered No.' : `${members(count)} answered No.`),
  nobodyAnswered: (answer: EventAnswerValue) => `Nobody answered ${ANSWER_NAMES[answer]} yet`,
  // For screen readers, while the names load
  answersLoading: 'Loading the answers',
  // The name of the list of the members: "Members who answered Yes"
  answersList: (answer: EventAnswerValue) => `Members who answered ${ANSWER_NAMES[answer]}`,

  // The actions of the event page. [Q] The design puts the menu in the form, which a moderator cannot open
  edit: 'Edit',
  publish: 'Publish',
  actionsMenu: 'Event actions',
  cancelEvent: 'Cancel event',
  publishAgain: 'Publish again',
  backToDraft: 'Back to draft',
  duplicate: 'Duplicate',
  delete: 'Delete',
  // [Q] The two questions
  cancelQuestion: 'Cancel this event?',
  cancelExplanation: 'It stays visible with the label Cancelled.',
  cancelConfirm: 'Cancel event',
  deleteQuestion: 'Delete this event?',
  deleteExplanation: 'This cannot be undone.',
  deleteConfirm: 'Delete',
  keep: 'Keep the event',
  eventDeleted: 'Event deleted',
  eventCancelled: 'Event cancelled',
  eventBackToDraft: 'Event back to draft',
  eventDuplicated: 'Event duplicated. This is the copy.',

  // The form of the host (design 4.2, 6.3, 11.1)
  newEventTitle: 'New event',
  editEventTitle: 'Edit the event',
  fieldTitle: 'Title',
  fieldDescription: 'Description',
  // [Q] The cover and its description
  fieldCover: 'Cover',
  chooseCover: 'Choose an image',
  changeCover: 'Change the image',
  removeCover: 'Remove the cover',
  coverFormats: 'JPEG, PNG, WebP or GIF, up to 10 MB. It shows in a 16:9 box.',
  coverUploaded: 'Cover added',
  coverRemoved: 'Cover removed',
  fieldCoverAlt: 'Cover description',
  coverAltHint: 'Leave empty when the image is decoration',
  // [Q] The description that the upload needs when the host typed none (never stored as the description of the cover)
  coverUploadAlt: 'Cover of an event',
  when: 'When',
  startDate: 'Start date',
  startTime: 'Start time',
  endTime: 'End time',
  // [Q] Shown only when the end time is not after the start time, set to the next day
  endDate: 'End date',
  endDateHint: 'The end time is not after the start time: the event ends on this day.',
  timeZone: 'Time zone',
  timeZonePlaceholder: 'Choose a time zone',
  // [Q] The design writes "In your zone:" in 4.2 and "For you:" in 4.5: one wording
  inYourZone: (time: string) => `In your zone: ${time}.`,
  inYourZoneTheSame: 'In your zone: the same.',
  skippedTime: (before: string, after: string) =>
    `This time does not exist in this zone. Choose ${before} or earlier, or ${after} or later.`,
  // The clocks go forward at midnight (the Azores, Cairo, Santiago, Havana): the last time before the gap is on the
  // day before the date of the field
  skippedTimeDayBefore: (before: string, after: string) =>
    `This time does not exist in this zone. Choose ${before} the day before or earlier, or ${after} or later.`,
  // What the status of "When" says when the question below appears
  twiceStatus: 'This time exists twice on this day. Choose summer time or winter time below.',
  twiceQuestion: (time: string, summerOffset: string, winterOffset: string) =>
    `${time} in summer time (${summerOffset}) or in winter time (${winterOffset})?`,
  twiceSummer: (offset: string) => `Summer time (${offset})`,
  twiceWinter: (offset: string) => `Winter time (${offset})`,
  twiceStart: 'The start',
  twiceEnd: 'The end',
  twiceMissing: 'Choose summer time or winter time',
  endBeforeStart: 'The end must be after the start',
  tooLong: (hours: number) => `An event lasts ${hours} hours at most`,
  fieldMeetingUrl: 'Meeting address',
  meetingUrlHint: 'An address that starts with https://. Only members see it.',
  // [W] The hint when the address is for the members who answer Yes
  meetingUrlHintGoing: 'An address that starts with https://. Only members who answer Yes see it.',
  fieldPlace: 'Place',
  placeHint: 'For an event in a room. Only members see it.',
  // [W]
  placeHintGoing: 'For an event in a room. Only members who answer Yes see it.',
  // The three fields of the answers (#1515, design 6.3 point 2). [W] Every text below, except "A replay will exist"
  fieldLocationVisibility: 'Who sees the meeting address and the place',
  locationVisibilityMembers: 'Every member',
  locationVisibilityGoing: 'Members who answer Yes',
  locationVisibilityGoingHint: 'The host, the owner and the admins always see them.',
  fieldCapacity: 'Limit of places',
  capacityHint: 'Leave empty for no limit. Only the Yes answers take a place.',
  // "12 members answered Yes.", under the limit of a published or cancelled event
  answeredYes: (count: number) => `${members(count)} answered Yes.`,
  // The same line when the typed limit is under that number: a hint, the save is not stopped
  answeredYesOverLimit: (count: number) => `${members(count)} answered Yes. Nobody is removed. No other member can answer Yes.`,
  replayExpected: 'A replay will exist',
  replayExpectedHint: 'Members can then answer Replay only.',
  fieldHost: 'Host',
  hostHint: 'One member. Type a name, then choose with the arrows and Enter.',
  hostPlaceholder: 'Name or username',
  hostChosenPlaceholder: 'Remove the host to choose another member',
  hostMissing: 'Choose a host',
  // A draft that its creator gives to another host is no longer theirs to see
  hostHandOver: (name: string) =>
    `If you save this as a draft, only ${name}, the owner and the admins see it. You will not see it any more.`,
  saveAsDraft: 'Save as draft',
  saveChanges: 'Save changes',
  // [Q] "Publish now" and its hint
  publishNow: 'Publish now',
  publishNowHint: 'Without it, members are told after 5 minutes.',
  cancel: 'Cancel',
  // [Q] The three texts after a save
  draftSaved: 'Draft saved',
  eventPublished: 'Event published',
  changesSaved: 'Changes saved',
  fixTheFields: 'Not saved. Some fields need a change: see the message next to each one.',
  // [Q] A publication takes two or three requests. What is saved when a later one fails, after the message of the server
  savedNotPublished: 'Your changes are saved. The event is not published.',
  publishedHostNotChanged: 'The event is published. The host did not change.',

  // "Next events" on the home and on the public page of a community (design 6.7)
  nextEvents: 'Next events',
} as const;
