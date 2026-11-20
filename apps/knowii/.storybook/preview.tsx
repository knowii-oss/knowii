// The same font and styles as the application (main.tsx)
import '@fontsource-variable/noto-sans/wght.css';
import '@fontsource-variable/noto-sans/wght-italic.css';
import '../src/styles.css';

import type { Decorator, Preview } from '@storybook/react-vite';
import type { Theme } from '../src/Components/ui/theme';
import { applyTheme } from '../src/Components/ui/theme';

/**
 * Sets the theme of the toolbar on <html data-theme>, like the theme switch of the application (theme.ts).
 * The choice is not saved: it lives in the toolbar.
 */
const withTheme: Decorator = (Story, context) => {
  const theme: Theme = context.globals['theme'] === 'light' ? 'light' : 'dark';
  applyTheme(theme, false);
  if (context.viewMode === 'docs') {
    // The docs page has its own light background: the story gets the background of the theme
    return (
      <div className="rounded-lg bg-background p-4 text-primary">
        <Story />
      </div>
    );
  }
  return <Story />;
};

const preview: Preview = {
  tags: ['autodocs'],
  decorators: [withTheme],
  globalTypes: {
    theme: {
      description: 'Theme of the application',
      toolbar: {
        title: 'Theme',
        icon: 'contrast',
        items: [
          { value: 'dark', title: 'Dark', icon: 'moon' },
          { value: 'light', title: 'Light', icon: 'sun' },
        ],
        dynamicTitle: true,
      },
    },
  },
  initialGlobals: {
    // Dark is the default theme of the application
    theme: 'dark',
  },
  parameters: {
    layout: 'padded',
    // The order of the list: the introduction, the rule pages, then the components. The pages are the .mdx files of
    // Components/ui/docs, and the title of a component comes from its path (Components/ui/<Name>)
    options: {
      storySort: {
        order: ['Introduction', 'Rules', 'Components'],
      },
    },
    // The theme sets the background (styles.css)
    backgrounds: { disable: true },
    // The two widths that every screen must support (definition of done in documentation/conventions/rules.md)
    viewport: {
      options: {
        phone: { name: 'Phone (360 px)', styles: { width: '360px', height: '800px' }, type: 'mobile' },
        large: { name: 'Large screen (1920 px)', styles: { width: '1920px', height: '1080px' }, type: 'desktop' },
      },
    },
  },
};

export default preview;
