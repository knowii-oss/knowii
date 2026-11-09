import { Link } from '@inertiajs/react';
import type { CommunityResource } from '@knowii/common';
import { getIconForResourceLevel, safeHttpUrl } from '@knowii/common';
import { FaArrowUpRightFromSquare } from 'react-icons/fa6';
import { buttonClasses } from '@/Components/ui';
import { ResourceTypeIcon } from './ResourceTypeIcon';

export interface ResourceCardProps {
  resource: CommunityResource;
  href: string;
}

/**
 * A resource in a list: cover thumbnail, icon of the site, name and description (#1063). The images come from the
 * Knowii origin (ADR 0016); they are decorative, the name says what the resource is.
 */
export function ResourceCard({ resource, href }: ResourceCardProps) {
  const name = resource.name || resource.resource.name;
  const description = resource.description || resource.resource.description;
  const externalUrl = safeHttpUrl(resource.resource.url);
  const cover = resource.resource.cover_image_thumbnail_url;
  const favicon = resource.resource.favicon_url;

  return (
    <div className="flex h-full items-start gap-3 rounded-lg border border-border bg-well p-3 transition-colors hover:border-overlay/40">
      {cover && <img src={cover} alt="" loading="lazy" className="h-16 w-24 shrink-0 rounded-md bg-overlay/10 object-cover" />}
      <Link href={href} preserveState={true} className="min-w-0 grow rounded">
        <h3 className="flex items-center gap-2 text-base font-bold">
          {favicon && <img src={favicon} alt="" loading="lazy" className="size-4 shrink-0 rounded-sm" />}
          <span aria-hidden="true">{getIconForResourceLevel(resource.level)}</span>
          <ResourceTypeIcon type={resource.type} className="shrink-0 text-sm text-muted" />
          <span className="min-w-0">{name}</span>
        </h3>
        {description && <p className="my-1 line-clamp-2 text-sm text-muted">{description}</p>}
      </Link>
      {externalUrl && (
        <a
          href={externalUrl}
          target="_blank"
          rel="noreferrer"
          aria-label={`Open "${name}" in a new tab`}
          className={buttonClasses({ variant: 'secondary', size: 'sm', iconOnly: true })}
        >
          <FaArrowUpRightFromSquare aria-hidden="true" />
        </a>
      )}
    </div>
  );
}
