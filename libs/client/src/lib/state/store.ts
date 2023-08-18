import { create, StateCreator } from 'zustand';

import { CommunityPageState, createCommunityPageState } from './community-page.state';
import { immer } from 'zustand/middleware/immer';

export type AppState = {
  communityPage: CommunityPageState;
};

// FIXME find how to correctly define this
// Reference: https://github.com/pmndrs/zustand/issues/508
// @ts-ignore
export type StateSlice<T> = StateCreator<AppState, [['zustand/immer', never]], [['zustand/persist', Partial<T>]], T>;

export const useAppState = create<AppState>()(
  immer((...api) => ({
    communityPage: createCommunityPageState(...api),
  })),

  // TODO implement persistence
  // WARNING: Need to be careful about rehydration
  // References:
  // https://blog.abdulsamad.dev/how-to-use-zustands-persist-middleware-in-nextjs
  // https://stackoverflow.com/questions/72649255/nextjs-zustand-persist-state
  //
  // persist(
  //   immer((...api) => ({
  //     communityPage: createCommunityPageState(...api),
  //   })),
  //   {
  //     name: 'knowii-state-store',
  //     partialize: (state) =>
  //       <AppState>{
  //         // Include the keys you want to persist in here
  //         communityPage: state.communityPage,
  //       },
  //     merge: (persistedState, currentState) => {
  //       // persistedState is unknown, so we need to cast it
  //       const typedPersistedState = persistedState as AppState | undefined;
  //
  //       return {
  //         communityPage: {
  //           // We need to do a deep merge here because the default merge strategy is a
  //           // shallow merge. Without doing this, our actions would not be included in
  //           // our merged state, resulting in unexpected behavior.
  //           ...currentState.communityPage,
  //           ...(typedPersistedState?.communityPage || {}),
  //         },
  //       };
  //     },
  //   },
  // ),
);
