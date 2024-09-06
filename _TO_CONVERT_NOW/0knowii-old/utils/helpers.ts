export interface ErrorWithMessage {
  message: string;
}

export function hasErrorMessage(obj: unknown): obj is ErrorWithMessage {
  return (obj as ErrorWithMessage)?.message !== null && (obj as ErrorWithMessage)?.message !== undefined;
}

