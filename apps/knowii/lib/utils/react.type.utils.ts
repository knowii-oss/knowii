// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type PropsOf<TTag = any> = TTag extends React.ElementType ? React.ComponentProps<TTag> : never;

export interface BaseProps {
  className: string;
  [key: string]: unknown;
}

export interface IconProps {
  classes: string;
}
