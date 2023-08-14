import { StateSlice } from './store';

/**
 * State of the community page
 */
export interface CommunityPageStateDefinition {
  /**
   * Should the "Recent resources section be hidden or not
   */
  showRecentResources: boolean;
}

export interface CommunityPageStateActions {
  /**
   * Toggle showing recent resources
   */
  toggleShowRecentResources: () => void;
}

export type CommunityPageState = CommunityPageStateDefinition & CommunityPageStateActions;

const initialCommunityPageState: CommunityPageState = {
  showRecentResources: false,
  toggleShowRecentResources: () => {},
};

export const createCommunityPageState: StateSlice<CommunityPageState> = (set, get) => ({
  ...initialCommunityPageState,
  showRecentResources: true,
  toggleShowRecentResources: () => {
    set((state) => ({
      communityPage: {
        ...state.communityPage,
        showRecentResources: !get().communityPage.showRecentResources,
      },
    }));
  },
});
