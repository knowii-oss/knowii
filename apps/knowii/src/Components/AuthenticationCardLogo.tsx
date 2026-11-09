import { Link } from '@inertiajs/react';
import { HOME_URL } from '@knowii/common';
import ApplicationName from '@/Components/ApplicationName';

export default function AuthenticationCardLogo() {
  return (
    <Link href={HOME_URL} aria-label="Knowii home page" className="block w-full rounded-lg">
      <ApplicationName className="w-full" />
    </Link>
  );
}
