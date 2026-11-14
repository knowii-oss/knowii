import { z } from 'zod';
import {
  COURSE_MATERIAL_EXTENSIONS,
  MAX_LENGTH_COURSE_MATERIAL_TITLE,
  MAX_LENGTH_SPACE_URL,
  MIN_LENGTH_COURSE_MATERIAL_TITLE,
} from '../constants';

// WARNING: aligned with KnowiiCourseMaterialKind.php
export const COURSE_MATERIAL_KINDS = ['file', 'link'] as const;
export const courseMaterialKindSchema = z.enum(COURSE_MATERIAL_KINDS);
export type CourseMaterialKind = z.infer<typeof courseMaterialKindSchema>;

/**
 * A supplementary material of a course space (#1336, ADR 0037): a file or a link, for the whole course (page_cuid
 * null), a section or a lesson. A file has no address here: download it through the API.
 * WARNING: aligned with CourseMaterialResource.php
 */
export const courseMaterialSchema = z.object({
  cuid: z.string(),
  kind: courseMaterialKindSchema,
  title: z.string(),
  position: z.number().int(),
  page_cuid: z.string().nullable(),
  url: z.string().nullable(),
  file_name: z.string().nullable(),
  mime_type: z.string().nullable(),
  size_bytes: z.number().int().nullable(),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});

export type CourseMaterial = z.infer<typeof courseMaterialSchema>;

const titleSchema = z
  .string()
  .trim()
  .min(MIN_LENGTH_COURSE_MATERIAL_TITLE, { error: 'Give the material a title' })
  .max(MAX_LENGTH_COURSE_MATERIAL_TITLE, { error: `The title is too long (maximum ${MAX_LENGTH_COURSE_MATERIAL_TITLE} characters)` });

// WARNING: aligned with CreateCourseMaterial.php
export const courseMaterialLinkFormSchema = z.object({
  title: titleSchema,
  url: z
    .string()
    .trim()
    .max(MAX_LENGTH_SPACE_URL, { error: 'The address is too long' })
    .refine((value) => /^https?:\/\/\S+$/i.test(value), { error: 'Enter an address that starts with http:// or https://' }),
});

export const courseMaterialTitleFormSchema = z.object({ title: titleSchema });

/**
 * Whether the server accepts a file with this name (the server also reads the type from the content).
 */
export function isCourseMaterialFileName(name: string): boolean {
  const extension = name.includes('.') ? (name.split('.').pop() ?? '').toLowerCase() : '';
  return (COURSE_MATERIAL_EXTENSIONS as readonly string[]).includes(extension);
}

/**
 * A size for people: 950 B, 12 KB, 3.4 MB.
 */
export function formatFileSize(bytes: number): string {
  if (bytes < 1024) {
    return `${bytes} B`;
  }
  if (bytes < 1024 * 1024) {
    return `${Math.round(bytes / 1024)} KB`;
  }
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
