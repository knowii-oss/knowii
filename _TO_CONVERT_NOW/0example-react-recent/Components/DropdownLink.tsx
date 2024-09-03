import React from 'react';
import { Link } from '@inertiajs/react';

interface DropdownLinkProps {
  href?: string;
  as?: 'a' | 'button';
  children: React.ReactNode;
}

const DropdownLink: React.FC<DropdownLinkProps> = ({ href = '#', as = 'link', children }) => {
  if (as === 'button') {
    return (
      <div>
        <button
          type="submit"
          className="block w-full px-4 py-2 text-sm leading-5 text-gray-700 transition duration-150 ease-in-out text-start dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800"
        >
          {children}
        </button>
      </div>
    );
  }

  if (as === 'a') {
    return (
      <div>
        <a
          href={href}
          className="block px-4 py-2 text-sm leading-5 text-gray-700 transition duration-150 ease-in-out dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800"
        >
          {children}
        </a>
      </div>
    );
  }

  return (
    <div>
      <Link
        href={href}
        className="block px-4 py-2 text-sm leading-5 text-gray-700 transition duration-150 ease-in-out dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800"
      >
        {children}
      </Link>
    </div>
  );
};

export default DropdownLink;
