import type { IconType } from 'react-icons';
import { FaNewspaper } from 'react-icons/fa6';
import type { ResourceType } from '@knowii/common';
import { resourceTypeSchemaOptions } from '@knowii/common';

// One icon per resource type (#1137). WARNING: a new type in KnowiiResourceType needs an icon here (the Record fails to compile without it)
const ICONS: Record<ResourceType, IconType> = {
  textArticle: FaNewspaper,
};

export interface ResourceTypeIconProps {
  type: ResourceType;
  className?: string;
}

/**
 * The icon of a resource type, with its name for screen readers and as a tooltip.
 */
export function ResourceTypeIcon({ type, className }: ResourceTypeIconProps) {
  // Data from an older client or server can hold an unknown type: show nothing rather than fail
  const Icon = ICONS[type] as IconType | undefined;
  if (!Icon) {
    return null;
  }
  const name = resourceTypeSchemaOptions.find((option) => option.type === type)?.name ?? type;

  return (
    <span className={className} title={name}>
      <Icon aria-hidden="true" />
      {/* A comma and a no-break space keep the type and the next word apart for screen readers (a plain space is trimmed) */}
      <span className="sr-only">{`${name},\u00A0`}</span>
    </span>
  );
}
