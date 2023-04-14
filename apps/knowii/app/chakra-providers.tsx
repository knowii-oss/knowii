'use client';

import { CacheProvider } from '@chakra-ui/next-js';
import { ChakraBaseProvider, ColorModeScript } from '@chakra-ui/react';
import { cookieStorageManagerSSR, localStorageManager } from '@chakra-ui/react';

import { customTheme, defaultToastOptions } from '../chakra-ui.config';
import React, { PropsWithChildren } from 'react';
import { NextApiRequestCookies } from 'next/dist/server/api-utils';

/**
 * Configure Chakra providers in a way that is compatible with Next.js 13+
 * @param props
 * @constructor
 */
export function ChakraProviders(props: PropsWithChildren<{ cookies?: NextApiRequestCookies }>) {
  const { cookies } = props;
  const colorModeManager = typeof cookies === 'string' ? cookieStorageManagerSSR(cookies) : localStorageManager;

  return (
    <CacheProvider>
      <ChakraBaseProvider
        theme={customTheme}
        colorModeManager={colorModeManager}
        toastOptions={{
          defaultOptions: defaultToastOptions,
        }}
      >
        <ColorModeScript initialColorMode={customTheme.config.initialColorMode} />
        {props.children}
      </ChakraBaseProvider>
    </CacheProvider>
  );
}

export default ChakraProviders;
