import Link from 'next/link';
import { HOME_URL } from '@knowii/common';
import React from 'react';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface LogoProps {}

export function Logo(_props: LogoProps) {
  return (
    <Link href={HOME_URL}>
      <div className="font-bold text-4xl md:text-5xl lg:text-6xl leading-none tracking-tight p-4 bg-gray-800 text-white text-3xl font-bold">
        Know<span className="text-primary-500 font-extrabold">ii</span>
      </div>
    </Link>
  );
}

export default Logo;
