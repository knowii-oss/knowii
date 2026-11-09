import { useId, useState } from 'react';
import { FaEye, FaEyeSlash } from 'react-icons/fa';
import { cn } from './cn';
import type { TextInputProps } from './TextInput';
import { TextInput } from './TextInput';

export type PasswordStrength = 'weak' | 'medium' | 'strong';

const MEDIUM_PASSWORD = /^(((?=.*[a-z])(?=.*[A-Z]))|((?=.*[a-z])(?=.*[0-9]))|((?=.*[A-Z])(?=.*[0-9])))(?=.{6,})/;
const STRONG_PASSWORD = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.{8,})/;

/**
 * Rough strength estimate, shown as a hint only. The server enforces the password rules.
 */
export function getPasswordStrength(password: string): PasswordStrength {
  if (STRONG_PASSWORD.test(password)) {
    return 'strong';
  }
  if (MEDIUM_PASSWORD.test(password)) {
    return 'medium';
  }
  return 'weak';
}

const defaultStrengthLabels: Record<PasswordStrength, string> = {
  weak: 'Too simple',
  medium: 'Average complexity',
  strong: 'Complex password',
};

const strengthBarClasses: Record<PasswordStrength, string> = {
  weak: 'w-1/3 bg-danger-text',
  medium: 'w-2/3 bg-warning',
  strong: 'w-full bg-success',
};

export interface PasswordInputProps extends Omit<TextInputProps, 'type' | 'trailing' | 'allowedCharacters'> {
  /**
   * Show a strength hint below the field while the user types
   */
  showStrength?: boolean;
  strengthLabels?: Record<PasswordStrength, string>;
}

export function PasswordInput({
  showStrength = false,
  strengthLabels = defaultStrengthLabels,
  id,
  value,
  'aria-describedby': describedBy,
  ...props
}: PasswordInputProps) {
  const [visible, setVisible] = useState(false);
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const strengthId = `${inputId}-strength`;

  const password = typeof value === 'string' ? value : '';
  const strength = getPasswordStrength(password);

  return (
    <div className="w-full">
      <TextInput
        id={inputId}
        type={visible ? 'text' : 'password'}
        value={value}
        aria-describedby={[describedBy, showStrength && password ? strengthId : null].filter(Boolean).join(' ') || undefined}
        trailing={
          <button
            type="button"
            className="flex size-9 cursor-pointer items-center justify-center rounded-md text-muted transition-colors hover:text-primary disabled:cursor-not-allowed"
            aria-label={visible ? 'Hide password' : 'Show password'}
            aria-pressed={visible}
            aria-controls={inputId}
            disabled={props.disabled}
            onClick={() => setVisible((current) => !current)}
          >
            {visible ? <FaEyeSlash aria-hidden="true" /> : <FaEye aria-hidden="true" />}
          </button>
        }
        {...props}
      />
      {showStrength && password && (
        <div id={strengthId} className="mt-2" aria-live="polite">
          <div className="h-1.5 w-full overflow-hidden rounded-full bg-overlay/10">
            <div className={cn('h-full rounded-full transition-all duration-300', strengthBarClasses[strength])} />
          </div>
          <p className="mt-1 text-xs text-muted">{strengthLabels[strength]}</p>
        </div>
      )}
    </div>
  );
}
