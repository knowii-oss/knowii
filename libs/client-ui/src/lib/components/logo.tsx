import Link from 'next/link';

// eslint-disable-next-line @typescript-eslint/no-empty-interface
export interface LogoProps {}

export function Logo(_props: LogoProps) {
  return (
    <Link href="/">
      <span className="text-3xl lg:text-4xl font-bold dark:text-white text-blue-500">Know</span>
      <span className="text-3xl font-bold lg:text-4xl text-primary-500">ii</span>
    </Link>
  );
}

export default Logo;
