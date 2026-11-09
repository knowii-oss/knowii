import { Link } from '@inertiajs/react';
import type { CommunityResource } from '@knowii/common';
import { MEMBER_URL, resourceLevelSchemaOptions, resourceTypeSchemaOptions, safeHttpUrl, useAppData } from '@knowii/common';
import { FaArrowUpRightFromSquare, FaLink } from 'react-icons/fa6';
import { Button, buttonClasses, RelativeTime } from '@/Components/ui';
import { ResourceTypeIcon } from './ResourceTypeIcon';

export interface ResourceDetailsProps {
  communitySlug: string;
  resource: CommunityResource;
  // The address to share: the page of the resource in the community
  shareUrl: string;
}

const chipClasses = 'rounded-full border border-border px-2 py-0.5';

function hostOf(url: string | null): string | null {
  try {
    return url ? new URL(url).hostname.replace(/^www\./, '') : null;
  } catch {
    return null;
  }
}

/**
 * The page of a resource (#1133): what the community says about it, what the page says about itself, who added it,
 * and the links to open and share it. The captured article itself waits for the capture design (#1230).
 */
export function ResourceDetails({ communitySlug, resource, shareUrl }: ResourceDetailsProps) {
  const { showToast } = useAppData();
  const page = resource.resource;
  const name = resource.name || page.name;
  const articleUrl = safeHttpUrl(page.url);
  const host = hostOf(articleUrl);
  const level = resourceLevelSchemaOptions.find((option) => option.level === page.level);
  const type = resourceTypeSchemaOptions.find((option) => option.type === page.type);
  const readingTime = resource.resource_text_article?.reading_time ?? null;
  const curator = resource.curator;

  const copyLink = async () => {
    try {
      await navigator.clipboard.writeText(shareUrl);
      showToast({ severity: 'success', summary: 'Link copied' });
    } catch {
      showToast({ severity: 'error', summary: 'Could not copy the link', detail: shareUrl });
    }
  };

  return (
    <article className="flex flex-col gap-5">
      {page.cover_image_thumbnail_url && (
        <img
          src={page.cover_image_thumbnail_url}
          alt={page.cover_image_alt ?? ''}
          className="max-h-72 w-full rounded-lg bg-overlay/10 object-cover"
        />
      )}

      <header className="flex flex-col gap-2">
        <h1 className="text-2xl font-extrabold">{name}</h1>
        {host && (
          <p className="flex items-center gap-2 text-sm text-muted">
            {page.favicon_url && <img src={page.favicon_url} alt="" className="size-4 rounded-sm" />}
            {host}
          </p>
        )}
        <p className="flex flex-wrap gap-2 text-xs text-muted">
          {level && (
            <span className={chipClasses}>
              <span aria-hidden="true">{level.icon} </span>
              {level.name}
            </span>
          )}
          {type && (
            <span className={`${chipClasses} inline-flex items-center gap-1`}>
              <ResourceTypeIcon type={type.type} />
              <span aria-hidden="true">{type.name}</span>
            </span>
          )}
          {readingTime !== null && readingTime > 0 && <span className={chipClasses}>{readingTime} min read</span>}
        </p>
      </header>

      {resource.description && <p className="text-base">{resource.description}</p>}

      {(page.description || page.name !== name) && (
        <section aria-labelledby="resource-from-the-page" className="rounded-lg border border-border bg-well p-4">
          <h2 id="resource-from-the-page" className="mb-2 text-sm font-bold text-muted">
            From the page
          </h2>
          {page.name !== name && <p className="font-bold">{page.name}</p>}
          {page.description && <p className="text-sm text-muted">{page.description}</p>}
        </section>
      )}

      {page.keywords.length > 0 && (
        <ul aria-label="Keywords" className="flex flex-wrap gap-2 text-xs">
          {page.keywords.map((keyword) => (
            <li key={keyword} className={chipClasses}>
              {keyword}
            </li>
          ))}
        </ul>
      )}

      <p className="text-sm text-muted">
        Added by{' '}
        {curator ? (
          <>
            {curator.username ? (
              <Link
                href={route(MEMBER_URL, { communitySlug, username: curator.username })}
                preserveState={true}
                className="font-bold text-primary underline"
              >
                {curator.name}
              </Link>
            ) : (
              <span className="font-bold text-primary">{curator.name}</span>
            )}{' '}
          </>
        ) : (
          // The curator deleted the account (#1261): the resource stays
          <span className="font-bold">Deleted member </span>
        )}
        <RelativeTime date={resource.created_at} />
        {page.published_at && (
          <>
            {' '}
            · published <RelativeTime date={page.published_at} />
          </>
        )}
      </p>

      <div className="flex flex-wrap gap-2">
        {articleUrl && (
          <a href={articleUrl} target="_blank" rel="noreferrer" className={buttonClasses({ variant: 'primary' })}>
            <FaArrowUpRightFromSquare aria-hidden="true" />
            Open the article
          </a>
        )}
        <Button variant="secondary" onClick={copyLink}>
          <FaLink aria-hidden="true" />
          Copy the link
        </Button>
      </div>
    </article>
  );
}
