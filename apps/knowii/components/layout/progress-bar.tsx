import NextNProgress from 'nextjs-progressbar';
import { customTheme } from '../../chakra-ui.config';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface ProgressBarProps {}

export function ProgressBar(_props: ProgressBarProps) {
  return <NextNProgress height={4} color={customTheme.colors.primary.DEFAULT} showOnShallow={true} />;
}

export default ProgressBar;
