import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { ESLint } from 'eslint';

// The boundary of the toolkit (#657): a file of Components/ui imports only React, Headless UI, the icons,
// @knowii/common, the class name helpers, date-fns and the other files of its folder. The spec lints small sources
// with the ESLint configuration of the project (eslint.config.mjs), as `nx lint knowii` does.

const projectRoot = resolve(import.meta.dirname, '../../..');
const eslint = new ESLint({ cwd: projectRoot });

/**
 * The boundary errors of a source, linted as the file `path` of the project
 */
async function boundaryErrors(source: string, path = 'src/Components/ui/Fixture.tsx'): Promise<string[]> {
  const [result] = await eslint.lintText(source, { filePath: resolve(projectRoot, path) });
  return (result?.messages ?? []).filter((message) => message.ruleId === 'no-restricted-imports').map((message) => message.message);
}

const allowed = [
  "import { useState } from 'react';",
  "import type { ReactNode } from 'react';",
  "import { createRoot } from 'react-dom/client';",
  "import { createPortal } from 'react-dom';",
  "import { Dialog } from '@headlessui/react';",
  "import { FaPlus } from 'react-icons/fa6';",
  "import { FaXmark } from 'react-icons/fa';",
  "import { safeHttpUrl } from '@knowii/common';",
  "import classNames from 'classnames';",
  "import { twMerge } from 'tailwind-merge';",
  "import { formatDistanceToNow } from 'date-fns';",
  "import { cn } from './cn';",
  "export * from './Button';",
];

const forbidden = [
  "import { Link } from '@inertiajs/react';",
  "import type { Page } from '@inertiajs/core';",
  "import { route } from 'ziggy-js';",
  "import { PageHeader } from '@/Components/PageHeader';",
  "import { VideoPlayer } from '../Video/VideoPlayer';",
  "import { cn } from './../ui/cn';",
  "import { z } from 'zod';",
  "import { formatDistanceToNow } from 'date-fns/formatDistanceToNow';",
  "export { Link } from '@inertiajs/react';",
];

// The first lint loads the configuration and its plugins: seconds on a busy machine
describe('the boundary of the toolkit (#657)', { timeout: 60_000 }, () => {
  it.each(allowed)('allows %s', async (line) => {
    expect(await boundaryErrors(line)).toEqual([]);
  });

  it.each(forbidden)('forbids %s, and says what to do', async (line) => {
    const errors = await boundaryErrors(line);
    expect(errors).toHaveLength(1);
    expect(errors[0]).toContain(
      'Components/ui is the toolkit: it imports only React, Headless UI, the icons and @knowii/common. ' +
        'Pass the part of the application as a property or through the toolkit context.',
    );
  });

  it('forbids each import of a file that holds several', async () => {
    expect(await boundaryErrors(forbidden.join('\n'))).toHaveLength(forbidden.length);
    expect(await boundaryErrors(allowed.join('\n'))).toEqual([]);
  });

  it('leaves the specs, the stories and the rest of the application free', async () => {
    const inertia = "import { Link } from '@inertiajs/react';";
    expect(await boundaryErrors(inertia, 'src/Components/ui/Fixture.spec.tsx')).toEqual([]);
    expect(await boundaryErrors(inertia, 'src/Components/ui/Fixture.stories.tsx')).toEqual([]);
    expect(await boundaryErrors(inertia, 'src/Components/Fixture.tsx')).toEqual([]);
  });
});
