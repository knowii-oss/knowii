import { replaceInFile } from 'replace-in-file';

// This script fixes the paths in the Vite manifest file
// This is necessary, because the paths in the manifest file are relative to the Vite build directory, which is under apps/knowii
// This script makes sure that the paths are relative to the root of the monorepo, so that Inertia is able to locate the pages (cfr app.blade.php)

// Reference: https://www.npmjs.com/package/replace-in-file
const options = {
  files: './public/build/.vite/manifest.json',
  from: /"src\//g,
  to: '"apps/knowii/src/',
};

try {
  const results = await replaceInFile(options);
} catch (error) {
  console.error('Error occurred while fixing the Vite manifest:', error);
}
