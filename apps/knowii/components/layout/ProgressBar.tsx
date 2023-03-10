import NextNProgress from 'nextjs-progressbar';
import { customTheme } from '../../chakra-ui.config';

const ProgressBar = () => {
  return <NextNProgress height={4} color={customTheme.colors.primary.DEFAULT} showOnShallow={true} />;
};

export default ProgressBar;
