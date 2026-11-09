import { useCallback, useMemo, useState } from 'react';
import { Link } from '@inertiajs/react';
import type { Community, CommunityResource, Identifiable, Paginated, Space } from '@knowii/common';
import {
  getIconForResourceLevel,
  knowiiApiClient,
  RESOURCE_URL,
  RESOURCES_PER_PAGE,
  safeHttpUrl,
  useAppData,
  useSocket,
} from '@knowii/common';
import { FaArrowUpRightFromSquare, FaPlus } from 'react-icons/fa6';
import { Button, buttonClasses, EmptyState } from '@/Components/ui';
import { CreateResourceDialog } from './CreateResourceDialog';

export interface ResourceListProps {
  community: Community;
  space: Space;
  initialResources: Paginated<CommunityResource>;
  canContribute: boolean;
}

/**
 * The resources of a resources space, newest first (ADR 0015). Members who can contribute add resources; the list
 * follows the resource events of the space channel.
 */
export function ResourceList({ community, space, initialResources, canContribute }: ResourceListProps) {
  const { showToast } = useAppData();
  const [resources, setResources] = useState<CommunityResource[]>(initialResources.items);
  const [nextOffset, setNextOffset] = useState<number | null>(initialResources.pagination.nextOffset);
  const [loading, setLoading] = useState(false);
  const [dialogOpen, setDialogOpen] = useState(false);

  const addResource = useCallback((resource: CommunityResource) => {
    setResources((current) => (current.some((item) => item.cuid === resource.cuid) ? current : [resource, ...current]));
  }, []);

  const removeResource = useCallback((deleted: Identifiable) => {
    setResources((current) => current.filter((item) => item.cuid !== deleted.cuid));
  }, []);

  const channel = useMemo(
    () => ({ type: 'space' as const, communityCuid: community.cuid, spaceCuid: space.cuid }),
    [community.cuid, space.cuid],
  );

  useSocket({
    channel,
    event: ['community.resource.created', 'community.resource.deleted'],
    callback: (event, payload) => {
      if (event === 'community.resource.created') {
        addResource(payload as CommunityResource);
      } else {
        removeResource(payload);
      }
    },
  });

  const loadMore = async () => {
    if (nextOffset === null) {
      return;
    }
    setLoading(true);
    const result = await knowiiApiClient.resources.list(community.cuid, space.cuid, RESOURCES_PER_PAGE, nextOffset);
    setLoading(false);
    if (result.ok) {
      // A resource can move between pages when new ones arrive: skip the ones already shown
      setResources((current) => [...current, ...result.data.items.filter((item) => !current.some((shown) => shown.cuid === item.cuid))]);
      setNextOffset(result.data.pagination.nextOffset);
    } else {
      showToast({ severity: 'error', summary: result.message });
    }
  };

  const href = (resource: CommunityResource) =>
    route(RESOURCE_URL, { communitySlug: community.slug, spaceSlug: space.slug, resourceSlug: resource.slug });

  return (
    <section aria-label={`Resources in ${space.name}`} className="flex flex-col gap-4">
      {canContribute && (
        <div className="flex justify-end">
          <Button onClick={() => setDialogOpen(true)}>
            <FaPlus aria-hidden="true" />
            Add a resource
          </Button>
        </div>
      )}

      {resources.length === 0 ? (
        <EmptyState
          icon="🔗"
          title="No resources yet"
          description={canContribute ? 'Share the first useful article with the community.' : 'Curated articles will show here.'}
        />
      ) : (
        <ul className="grid grid-cols-1 gap-3 lg:grid-cols-2">
          {resources.map((resource) => {
            const name = resource.name || resource.resource.name;
            const description = resource.description || resource.resource.description;
            const externalUrl = safeHttpUrl(resource.resource.url);

            return (
              <li
                key={resource.cuid}
                className="flex items-start gap-2 rounded-lg border border-border bg-well p-3 transition-colors hover:border-overlay/40"
              >
                <Link href={href(resource)} preserveState={true} className="min-w-0 grow rounded">
                  <h3 className="text-base font-bold">
                    <span aria-hidden="true">{getIconForResourceLevel(resource.resource.level) + ' '}</span>
                    {name}
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
              </li>
            );
          })}
        </ul>
      )}

      {nextOffset !== null && (
        <div className="flex justify-center">
          <Button variant="secondary" onClick={loadMore} loading={loading}>
            Load more resources
          </Button>
        </div>
      )}

      {canContribute && (
        <CreateResourceDialog
          open={dialogOpen}
          communityCuid={community.cuid}
          space={space}
          onClose={() => setDialogOpen(false)}
          onResourceCreated={addResource}
        />
      )}
    </section>
  );
}
