import type { PropsWithChildren } from 'react';

export interface InputLabelProps {
  value?: string;
  /**
   * The id of the field that the label names
   */
  htmlFor?: string;
  /**
   * Lets a group of controls reference the label with aria-labelledby
   */
  id?: string;
}

/**
 * The visible label of a form field, above the field. `value` comes first; the children show only without it.
 *
 * Not a heading, not a hint under a field, and not the `legend` of a `fieldset`.
 *
 * Accessibility: a `label`. With `htmlFor` (the id of the field), the label is the name of the field, and a click on
 * it puts the focus in the field. For a group of controls (the radios of a `SegmentedControl`), give the label an `id`
 * and name that id in `aria-labelledby` of the group.
 */
export function InputLabel({ value, htmlFor, id, children }: PropsWithChildren<InputLabelProps>) {
  return (
    <label className="block text-sm font-bold text-primary" htmlFor={htmlFor} id={id}>
      {value || children}
    </label>
  );
}
