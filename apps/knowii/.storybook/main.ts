import type { StorybookConfig } from '@storybook/react-vite';
import type { PluginOption } from 'vite';

// The Laravel plugin of the application (laravel-vite-plugin) adds these plugins. Storybook has no Laravel back-end
// and no manifest: they must not run here. Everything else of the application configuration stays: the React plugin,
// the path aliases (@/ and the TypeScript paths) and the PostCSS configuration of Tailwind CSS 4.
const LARAVEL_PLUGINS = new Set(['laravel', 'laravel:assets', 'laravel:fonts', 'vite-plugin-full-reload']);

async function withoutLaravelPlugins(plugins: PluginOption[]): Promise<PluginOption[]> {
  const kept: PluginOption[] = [];
  for (const option of plugins) {
    const plugin = await option;
    if (Array.isArray(plugin)) {
      kept.push(...(await withoutLaravelPlugins(plugin)));
    } else if (plugin && !LARAVEL_PLUGINS.has(plugin.name)) {
      if (plugin.name.startsWith('laravel')) {
        // A new version of the Laravel plugin added a plugin: add its name to the list above
        throw new Error(`Unknown Laravel plugin in the Vite configuration: ${plugin.name}`);
      }
      kept.push(plugin);
    }
  }
  return kept;
}

const config: StorybookConfig = {
  // The stories sit next to their component (#657)
  stories: ['../src/**/*.stories.tsx'],
  addons: ['@storybook/addon-a11y', '@storybook/addon-docs'],
  framework: {
    name: '@storybook/react-vite',
    options: {
      builder: {
        // The configuration of the application (vite.config.mts), without the Laravel plugin (see viteFinal)
        viteConfigPath: `${import.meta.dirname}/../vite.config.mts`,
      },
    },
  },
  core: {
    disableTelemetry: true,
    disableWhatsNewNotifications: true,
    // The dev server listens on 0.0.0.0 in the container. This list keeps the check of the Host header on:
    // only http://localhost:6006 reaches it
    allowedHosts: ['localhost'],
  },
  viteFinal: async (viteConfig) => ({
    ...viteConfig,
    plugins: await withoutLaravelPlugins(viteConfig.plugins ?? []),
  }),
};

export default config;
