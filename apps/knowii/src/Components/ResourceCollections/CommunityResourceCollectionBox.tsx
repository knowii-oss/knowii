import React from 'react';
import { CommunityResourceCollection } from '@knowii/common';
import { FaChevronRight } from 'react-icons/fa';
import { Link } from '@inertiajs/react';

interface Props {
  resourceCollection: CommunityResourceCollection;
  clickHandler?: () => void;
  link?: string;
}

const CommunityResourceCollectionBox: React.FC<Props> = ({ resourceCollection, clickHandler, link }) => {
  const handleClick = (e: React.MouseEvent) => {
    if (clickHandler) {
      e.preventDefault();
      clickHandler();
    }
  };

  const content = (
    <div className="flex items-center justify-between p-3 h-20">
      <div className="flex-grow pr-2 min-w-0 overflow-hidden">
        <h3 className="text-base font-semibold text-primary-500 leading-tight truncate mb-1">{resourceCollection.name}</h3>
        <p className="text-xs text-gray-600 line-clamp-2">{resourceCollection.description}</p>
      </div>
      <div className="flex-shrink-0 w-6 h-6 bg-primary-500 rounded-full flex items-center justify-center text-white ml-2">
        <FaChevronRight className="w-3 h-3" />
      </div>
    </div>
  );

  const boxContent = link ? (
    <Link href={link} preserveState={true} onClick={handleClick} className="block h-full">
      {content}
    </Link>
  ) : (
    <div onClick={handleClick} className="h-full">
      {content}
    </div>
  );

  return (
    <div className="w-full min-w-full md:w-72 lg:w-96 h-20 shadow-sm hover:shadow-md border border-gray-200 hover:border-primary-500 cursor-pointer rounded-md overflow-hidden transition-all duration-200">
      {boxContent}
    </div>
  );
};

export default CommunityResourceCollectionBox;
