import { useRouter } from 'next/router';
import { useEffect } from 'react';
import { IS_BROWSER } from '../../constants';

/**
 * React hook that forces a scroll reset to a particular set of coordinates in the document
 * after `next/router` finishes transitioning to a new page client side. It smoothly scrolls back to
 * the top by default.
 *
 * @see https://github.com/vercel/next.js/issues/3249
 * @see https://github.com/vercel/next.js/issues/15206
 * @see https://developer.mozilla.org/en-US/docs/Web/API/ScrollToOptions
 * @param {ScrollOptions} [options={}] Hook options.
 * @param {ScrollBehavior} [options.behavior='smooth'] Specifies whether the scrolling should animate smoothly,
 *  or happen instantly in a single jump.
 * @param {number} [options.left=0] Specifies the number of pixels along the X axis to scroll the window.
 * @param {number} [options.top=0] Specifies the number of pixels along the Y axis to scroll the window.
 */
function useRouterScroll({
  behavior = 'smooth',
  left = 0,
  top = 0,
  idOfElementToScroll,
}: {
  behavior?: ScrollBehavior;
  left?: number;
  top?: number;
  idOfElementToScroll?: string;
}) {
  const router = useRouter();

  let elementToScroll: HTMLElement | null = null;
  if (idOfElementToScroll && IS_BROWSER) {
    elementToScroll = document.getElementById(idOfElementToScroll);
    if (!elementToScroll) {
      throw new Error('Invalid id provided');
    }
  }

  useEffect(() => {
    // Scroll to given coordinates when router finishes navigating
    // This fixes an inconsistent behaviour between `<Link/>` and `next/router`
    // See https://github.com/vercel/next.js/issues/3249
    const handleRouteChangeComplete = () => {
      if (!elementToScroll) {
        window.scrollTo({ top, left, behavior });
      } else {
        elementToScroll.scrollTo({ top, left, behavior });
      }
    };

    router.events.on('routeChangeComplete', handleRouteChangeComplete);

    // If the component is unmounted, unsubscribe from the event
    return () => {
      router.events.off('routeChangeComplete', handleRouteChangeComplete);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [behavior, left, top]);
}

export default useRouterScroll;
