import type { KeyboardEvent, PointerEvent } from 'react';
import { useRef } from 'react';
import { Dialog as HeadlessDialog, DialogBackdrop, DialogPanel, DialogTitle, Transition } from '@headlessui/react';
import { FaArrowUpRightFromSquare, FaChevronLeft, FaChevronRight, FaXmark } from 'react-icons/fa6';
import { useReturnFocus } from './use-return-focus';

export interface ViewerImage {
  src: string;
  /**
   * The alternative text of the image: it is also its caption
   */
  alt: string;
}

export interface ImageViewerProps {
  open: boolean;
  images: ViewerImage[];
  /**
   * The image on screen (0 is the first)
   */
  index: number;
  onIndexChange: (index: number) => void;
  onClose: () => void;
}

// A horizontal move of this many pixels is a swipe
const SWIPE_DISTANCE = 50;

const controlClasses =
  'flex size-11 shrink-0 cursor-pointer items-center justify-center rounded-lg text-white transition-colors hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white';

/**
 * The viewer of the images of a gallery (#1207, ADR 0039): one image on the whole screen with its alternative text
 * as the caption. Previous and next work with the buttons, the arrow keys (Home and End for the ends) and a swipe.
 * Built on the Headless UI Dialog: it keeps the focus inside, closes with Escape, and gives the focus back to the
 * image that opened it. There is no custom zoom: the zoom of the browser works.
 */
export function ImageViewer({ open, images, index, onIndexChange, onClose }: ImageViewerProps) {
  useReturnFocus(open);
  const swipeStart = useRef<number | null>(null);
  const count = images.length;
  const image = images[index];
  const several = count > 1;

  // After the last image comes the first one
  const show = (next: number) => onIndexChange((next + count) % count);

  const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
    if (!several) {
      return;
    }
    const target: Record<string, number> = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: count - 1 };
    const next = target[event.key];
    if (next !== undefined) {
      event.preventDefault();
      show(next);
    }
  };

  const onPointerUp = (event: PointerEvent<HTMLDivElement>) => {
    const start = swipeStart.current;
    swipeStart.current = null;
    if (start === null || !several) {
      return;
    }
    const distance = event.clientX - start;
    if (Math.abs(distance) >= SWIPE_DISTANCE) {
      show(distance < 0 ? index + 1 : index - 1);
    }
  };

  return (
    <Transition show={open && image !== undefined}>
      <HeadlessDialog onClose={onClose} className="relative z-50">
        <DialogBackdrop
          transition
          className="fixed inset-0 bg-black/90 transition-opacity duration-200 ease-out data-closed:opacity-0 motion-reduce:transition-none"
        />
        <DialogPanel
          transition
          onKeyDown={onKeyDown}
          className="fixed inset-0 flex flex-col text-white transition duration-200 ease-out data-closed:opacity-0 motion-reduce:transition-none"
        >
          <div className="flex items-center justify-between gap-2 px-3 py-2">
            <DialogTitle className="text-sm font-bold">
              {/* Screen readers hear "Image 3 of 7" when the image changes; the eyes get "3 / 7" */}
              <span className="sr-only" aria-live="polite">{`Image ${index + 1} of ${count}`}</span>
              <span aria-hidden="true">{`${index + 1} / ${count}`}</span>
            </DialogTitle>
            <div className="flex items-center gap-1">
              {image && (
                <a
                  href={image.src}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-bold text-white transition-colors hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                >
                  <FaArrowUpRightFromSquare aria-hidden="true" />
                  Open the original
                  <span className="sr-only"> (new tab)</span>
                </a>
              )}
              <button type="button" onClick={onClose} aria-label="Close the viewer" data-autofocus className={controlClasses}>
                <FaXmark aria-hidden="true" className="size-5" />
              </button>
            </div>
          </div>

          <div
            className="flex min-h-0 grow touch-pan-y items-center justify-center gap-1 px-1 sm:gap-3 sm:px-3"
            onPointerDown={(event) => {
              swipeStart.current = event.clientX;
            }}
            onPointerUp={onPointerUp}
            onPointerCancel={() => {
              swipeStart.current = null;
            }}
          >
            {several && (
              <button type="button" onClick={() => show(index - 1)} aria-label="Previous image" className={controlClasses}>
                <FaChevronLeft aria-hidden="true" className="size-5" />
              </button>
            )}
            {/* The image takes the room between the buttons, so they stay at the edges for every image */}
            <div className="flex min-h-0 min-w-0 grow items-center justify-center self-stretch">
              {image && (
                <img
                  // A new element per image: the old one does not stay on screen while the next one loads
                  key={image.src}
                  src={image.src}
                  alt={image.alt}
                  draggable={false}
                  className="max-h-full max-w-full object-contain select-none"
                />
              )}
            </div>
            {several && (
              <button type="button" onClick={() => show(index + 1)} aria-label="Next image" className={controlClasses}>
                <FaChevronRight aria-hidden="true" className="size-5" />
              </button>
            )}
          </div>

          {/* The caption is the alternative text: screen readers already read it on the image */}
          <p aria-hidden="true" className="mx-auto max-w-prose px-4 py-3 text-center text-sm">
            {image?.alt}
          </p>
        </DialogPanel>
      </HeadlessDialog>
    </Transition>
  );
}
