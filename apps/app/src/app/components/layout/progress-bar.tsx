'use client';

import NextNProgress from 'nextjs-progressbar';

import { theme } from '../../../../theme';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface ProgressBarProps {}

export function ProgressBar(_props: ProgressBarProps) {
  return <NextNProgress height={4} color={theme.colors.primary.DEFAULT} showOnShallow={true} />;
}

export default ProgressBar;
