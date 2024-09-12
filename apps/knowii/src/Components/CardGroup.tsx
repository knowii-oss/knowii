import { PropsWithChildren } from 'react';
import classNames from 'classnames';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
interface Props {
  className?: string;
}

export default function CardGroup(props: PropsWithChildren<Props>) {
  return <div className={classNames('flex flex-wrap gap-6 justify-center md:justify-start', props.className)}>{props.children}</div>;
}
