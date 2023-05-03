import { Preview } from '@storybook/react';

import './tailwind-imports.css';

import { RouterContext } from 'next/dist/shared/lib/router-context';

const preview: Preview = {
  parameters: {
    nextRouter: {
      Provider: RouterContext.Provider,
    },
  },
};

export default preview;
