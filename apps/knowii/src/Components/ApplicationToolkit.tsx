import type { ReactNode } from 'react';
import { Link } from '@inertiajs/react';
import type { PageVideo } from '@knowii/common';
import type { ToolkitValues } from '@/Components/ui';
import { ToolkitProvider } from '@/Components/ui';
import { VideoPlayer } from '@/Components/Video/VideoPlayer';

function renderVideoPlayer(video: PageVideo, title: string): ReactNode {
  return <VideoPlayer video={video} title={title} />;
}

/**
 * The parts of the application that the toolkit (Components/ui) uses, #657: the Inertia link, so that a link of the
 * toolkit is an Inertia visit, and the click-to-load player of the videos of member content
 */
export const applicationToolkit: ToolkitValues = {
  linkComponent: Link,
  videoRenderer: renderVideoPlayer,
};

/**
 * Gives the parts of the application to the toolkit. Rendered once, where the Inertia app starts (main.tsx); a test
 * that needs the Inertia link or the video player renders it too.
 */
export function ApplicationToolkitProvider({ children }: { children?: ReactNode }) {
  return <ToolkitProvider {...applicationToolkit}>{children}</ToolkitProvider>;
}
