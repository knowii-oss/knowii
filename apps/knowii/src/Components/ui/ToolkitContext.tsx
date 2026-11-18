import type { AnchorHTMLAttributes, ComponentType, MouseEvent, ReactNode } from 'react';
import { createContext, useContext, useMemo } from 'react';
import type { PageVideo } from '@knowii/common';
import { safeHttpUrl } from '@knowii/common';

/**
 * The method of a link. A link with another method than GET sends a request: it renders as a button (`as="button"`).
 */
export type ToolkitLinkMethod = 'get' | 'post' | 'put' | 'patch' | 'delete';

// HTML events whose names the Inertia link uses for the callbacks of its visit: a link of the toolkit does not take them
type VisitCallbackNames = 'onCancel' | 'onError' | 'onProgress';

/**
 * The properties that the toolkit gives to its link component. The application gives a component that takes them (the
 * Inertia `Link`). The options of the visit are optional without `undefined`, as in the props of the Inertia `Link`.
 */
export interface ToolkitLinkProps extends Omit<AnchorHTMLAttributes<HTMLElement>, 'href' | 'onClick' | 'type' | VisitCallbackNames> {
  href: string;
  // A button for a request that is not a GET (a link cannot send it)
  as?: 'a' | 'button';
  method?: ToolkitLinkMethod;
  // Visit options of the application: the default link ignores them
  preserveState?: boolean;
  preserveScroll?: boolean;
  replace?: boolean;
  onClick?: (event: MouseEvent) => void;
}

/**
 * Renders the click-to-load player of a video of member content
 */
export type ToolkitVideoRenderer = (video: PageVideo, title: string) => ReactNode;

/**
 * The parts of the application that the toolkit uses. The toolkit imports nothing of the application (#657): the
 * application gives them once, with a ToolkitProvider around the pages.
 */
export interface ToolkitValues {
  /**
   * The link of `LinkButton`, `Breadcrumb` and the action of a toast. Default: a plain `<a>` (a `<button>` with
   * `as="button"`), which leaves the page
   */
  linkComponent: ComponentType<ToolkitLinkProps>;
  /**
   * The player of a video block of `MarkdownContent`, with the title for screen readers. Default: a plain link to the
   * video
   */
  videoRenderer: ToolkitVideoRenderer;
}

/**
 * The default link: a plain element. The visit options are for the link of the application: they are no HTML attributes.
 */
function PlainLink({ as, method, preserveState, preserveScroll, replace, href, children, ...attributes }: ToolkitLinkProps) {
  return as === 'button' ? (
    <button type="button" {...attributes}>
      {children}
    </button>
  ) : (
    <a href={href} {...attributes}>
      {children}
    </a>
  );
}

// The default video: the link of the block, as the server writes it for a browser without JavaScript
function plainVideoLink(video: PageVideo): ReactNode {
  const url = safeHttpUrl(video.url);
  return url ? <a href={url}>{url}</a> : null;
}

/**
 * The defaults: the toolkit works without the application (the stories, a test, a library later)
 */
export const defaultToolkitValues: ToolkitValues = {
  linkComponent: PlainLink,
  videoRenderer: plainVideoLink,
};

const ToolkitContext = createContext<ToolkitValues>(defaultToolkitValues);

export interface ToolkitProviderProps extends Partial<ToolkitValues> {
  children?: ReactNode;
}

/**
 * Gives the parts of the application to the components of the toolkit below it. A value that is not given comes from
 * the provider above, else from the defaults.
 */
export function ToolkitProvider({ linkComponent, videoRenderer, children }: ToolkitProviderProps) {
  const parent = useContext(ToolkitContext);
  const value = useMemo<ToolkitValues>(
    () => ({ linkComponent: linkComponent ?? parent.linkComponent, videoRenderer: videoRenderer ?? parent.videoRenderer }),
    [linkComponent, videoRenderer, parent],
  );
  return <ToolkitContext.Provider value={value}>{children}</ToolkitContext.Provider>;
}

/**
 * The parts of the application for a component of the toolkit
 */
export function useToolkit(): ToolkitValues {
  return useContext(ToolkitContext);
}
