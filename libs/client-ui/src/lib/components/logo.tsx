import Link from 'next/link';

// eslint-disable-next-line
export interface LogoProps {}

export function Logo(_props: LogoProps) {
  return (
    <Link href="/">
      <span className="text-3xl lg:text-4xl font-bold dark:text-steel-500 text-white">Know</span>
      <span className="text-3xl font-bold lg:text-4xl text-primary-500">ii</span>
    </Link>
  );
}

export default Logo;
