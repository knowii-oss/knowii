import { Link } from '@inertiajs/react';
import { HOME_URL } from '@knowii/common';
import ApplicationName from '@/Components/ApplicationName';

export default function AuthenticationCardLogo() {
  return (
    <Link href={HOME_URL}>
      <ApplicationName className="w-full" />
    </Link>
  );
}
