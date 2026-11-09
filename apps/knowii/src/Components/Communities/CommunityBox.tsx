import type { Community } from '@knowii/common';
import { communityVisibilityOptions } from '@knowii/common';
import { FaPlus } from 'react-icons/fa';
import { Link } from '@inertiajs/react';
import CommunityIcon from './CommunityIcon';
import { Card, cn } from '@/Components/ui';

interface Props {
  community?: Community;
  creationMode: boolean;
  clickHandler?: () => void;
  /**
   * Optional link to navigate to when the community box is clicked.
   * Link is only added if creationMode is false.
   */
  link?: string;
}

const boxSizeClasses = 'w-full sm:w-[450px] h-[250px]';

export default function CommunityBox(props: Props) {
  if (props.creationMode) {
    return (
      <button
        type="button"
        onClick={props.clickHandler}
        className={cn(
          boxSizeClasses,
          'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border-strong text-muted',
          'transition-colors hover:border-primary hover:bg-overlay/5 hover:text-primary',
        )}
      >
        <FaPlus aria-hidden="true" className="text-3xl" />
        <span className="text-xl font-bold">New community</span>
      </button>
    );
  }

  if (!props.community) {
    return null;
  }

  const visibilityLabel = communityVisibilityOptions.find((v) => v.visibility === props.community?.visibility)?.name ?? '';

  const content = (
    <>
      <h3 className="flex items-center gap-2 text-lg leading-tight font-extrabold">
        <CommunityIcon visibility={props.community.visibility} />
        <span className="truncate">{props.community.name}</span>
      </h3>
      <span aria-hidden="true" className="mt-2 block h-1 w-12 rounded-full bg-secondary" />
      <p className="mt-4 line-clamp-4 min-h-6 text-sm text-ellipsis text-muted md:line-clamp-5">{props.community.description}</p>
      <span className="absolute right-5 bottom-4 rounded-lg bg-overlay/10 px-2 py-0.5 text-xs font-medium">{visibilityLabel}</span>
    </>
  );

  return (
    <Card
      as="article"
      interactive={!!(props.link || props.clickHandler)}
      className={cn(boxSizeClasses, 'relative shrink-0 overflow-hidden')}
    >
      {props.link ? (
        <Link href={props.link} preserveState={true} className="block h-full rounded-xl p-5">
          {content}
        </Link>
      ) : props.clickHandler ? (
        <button type="button" onClick={props.clickHandler} className="block h-full w-full cursor-pointer rounded-xl p-5 text-left">
          {content}
        </button>
      ) : (
        <div className="h-full p-5">{content}</div>
      )}
    </Card>
  );
}
