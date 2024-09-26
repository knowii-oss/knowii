import { PropsWithChildren } from 'react';
import classNames from 'classnames';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface Props {
  className?: string;
}

export default function CardGroup(props: PropsWithChildren<Props>) {
  return (
    <ul className={classNames('flex flex-wrap gap-6 justify-center md:justify-start list-none', props.className)}>{props.children}</ul>
  );
}
