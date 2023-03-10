import Link from 'next/link';

export default function Logo() {
  return (
    <Link href="/">
      <span className="text-3xl lg:text-4xl font-bold dark:text-steel-500 text-white">Know</span>
      <span className="text-3xl font-bold lg:text-4xl text-primary-500">ii</span>
    </Link>
  );
}
