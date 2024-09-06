import React, { PropsWithChildren, useRef } from 'react';

interface Props {
  on: boolean;
  className?: string;
}

export default function ActionMessage({ on, className, children }: PropsWithChildren<Props>) {
  return (
    <div className={className}>
      <div className={`text-sm text-gray-600 transition-opacity ease-in-out duration-1000 ${on ? '' : 'opacity-0'}`}>{children}</div>
    </div>
  );
}
