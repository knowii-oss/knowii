import classNames from 'classnames';

interface Props {
  className?: string;
}

export default function ApplicationLogo(props: Props) {
  return (
    <div className={classNames('antialiased text-5xl md:text-6xl lg:text-8xl tracking-wide leading-none', props.className)}>
      <span className="font-bold text-white">Know</span>
      <span className="text-primary-500 font-extrabold">ii</span>
    </div>
  );
}
