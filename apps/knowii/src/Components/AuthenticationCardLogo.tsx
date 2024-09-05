import { Link } from '@inertiajs/react';
import ApplicationLogo from '@/Components/ApplicationLogo';
import { HOME_URL } from '@knowii/common';

export default function AuthenticationCardLogo() {
  return (
    <Link href={HOME_URL}>
      <ApplicationLogo />
    </Link>
  );
}
