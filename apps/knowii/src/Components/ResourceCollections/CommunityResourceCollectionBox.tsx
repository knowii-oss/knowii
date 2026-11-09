import type React from 'react';
import type { CommunityResourceCollection } from '@knowii/common';
import { FaChevronRight } from 'react-icons/fa';
import { Link } from '@inertiajs/react';

interface Props {
  resourceCollection: CommunityResourceCollection;
  clickHandler?: () => void;
  link?: string;
}

const boxClasses =
  'flex h-20 w-full min-w-full items-center justify-between gap-2 rounded-lg border border-border bg-well p-3 text-left transition-colors hover:border-overlay/40 hover:bg-overlay/5 md:w-72 md:min-w-0 lg:w-96';

const CommunityResourceCollectionBox: React.FC<Props> = ({ resourceCollection, clickHandler, link }) => {
  const content = (
    <>
      <div className="min-w-0 grow overflow-hidden">
        <h3 className="mb-1 truncate text-base leading-tight font-bold">{resourceCollection.name}</h3>
        <p className="line-clamp-2 text-xs text-muted">{resourceCollection.description}</p>
      </div>
      <span aria-hidden="true" className="flex size-6 shrink-0 items-center justify-center rounded-full bg-secondary text-white">
        <FaChevronRight className="size-3" />
      </span>
    </>
  );

  if (link) {
    return (
      <Link
        href={link}
        preserveState={true}
        className={boxClasses}
        onClick={(e: React.MouseEvent) => {
          if (clickHandler) {
            e.preventDefault();
            clickHandler();
          }
        }}
      >
        {content}
      </Link>
    );
  }

  return (
    <button type="button" onClick={clickHandler} className={`${boxClasses} cursor-pointer`}>
      {content}
    </button>
  );
};

export default CommunityResourceCollectionBox;
