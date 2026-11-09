import { useMemo } from 'react';
import { ACCEPTED_IMAGE_TYPES, knowiiApiClient, MAX_UPLOAD_SIZE_BYTES } from '@knowii/common';
import type { ImageUploader } from '@/Components/ui';

/**
 * Image uploads for the Markdown editor, stored in the community (#1178).
 */
export function useImageUploader(communityCuid: string): ImageUploader {
  return useMemo(
    () => ({
      maxSizeBytes: MAX_UPLOAD_SIZE_BYTES,
      acceptedTypes: ACCEPTED_IMAGE_TYPES,
      upload: async (file: File, alt: string) => {
        const result = await knowiiApiClient.communities.uploads.image(communityCuid, file, alt);
        return result.ok ? { url: result.data.url } : { error: result.message };
      },
    }),
    [communityCuid],
  );
}
