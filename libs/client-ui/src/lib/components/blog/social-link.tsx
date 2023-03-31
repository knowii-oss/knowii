import React from 'react';

interface SocialLinkProps {
  url: string;
  name: string;
  icon: React.ReactNode;
  ariaLabelText: string;
  titleText?: string;
  hideText?: boolean;
}

export function SocialLink({ url, icon, name, ariaLabelText, titleText = '', hideText }: SocialLinkProps) {
  return (
    <a href={url} aria-label={ariaLabelText} title={titleText}>
      {icon}
      {!hideText && <span className="ml-1 hover:text-blue-400 hover:dark:text-blue-100">{name}</span>}
    </a>
  );
}

export default SocialLink;
