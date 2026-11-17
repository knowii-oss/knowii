import { OIDC_REDIRECT_URL, PRIVACY_POLICY_URL, TERMS_OF_SERVICE_URL, useTypedPage } from '@knowii/common';
import { useRoute } from 'ziggy-js';
import { buttonClasses } from '@/Components/ui';

/**
 * "Continue with <provider>" above the other ways to sign in (#1385, ADR 0045). It shows only when the instance has a
 * provider (the shared prop `oidc` is null otherwise). A plain link: the flow leaves the app and comes back by the
 * callback, so it needs a full page load, not an Inertia visit. The error of a failed sign-in shows above the button.
 */
export default function OpenIdConnectButton() {
  const page = useTypedPage();
  const route = useRoute();
  const oidc = page.props.oidc;

  if (!oidc) {
    return null;
  }

  return (
    <div className="mb-6 border-b border-border pb-6">
      {oidc.error && (
        <p role="alert" className="mb-4 text-sm font-medium text-danger-text">
          {oidc.error}
        </p>
      )}
      <a href={route(OIDC_REDIRECT_URL)} className={buttonClasses({ variant: 'secondary', size: 'lg', fullWidth: true })}>
        Continue with {oidc.label}
      </a>
      <p className="mt-2 text-center text-xs text-muted">
        If you have no account yet, this creates one: you are 16 or older and you agree to the{' '}
        <a target="_blank" rel="noreferrer" href={route(TERMS_OF_SERVICE_URL)} className="simple-link">
          Terms of Service
        </a>{' '}
        and{' '}
        <a target="_blank" rel="noreferrer" href={route(PRIVACY_POLICY_URL)} className="simple-link">
          Privacy Policy
        </a>
        .
      </p>
    </div>
  );
}
