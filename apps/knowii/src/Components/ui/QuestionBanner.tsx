import type { ReactNode } from 'react';
import { useId } from 'react';

export interface QuestionBannerProps {
  /**
   * The question, in one sentence: "Show times in Europe/Brussels?"
   */
  question: string;
  /**
   * The two answers, as buttons. The answer that changes something comes first.
   */
  actions: ReactNode;
  /**
   * What went wrong with an answer ("Not saved. Retry."). It shows after the buttons, as an alert.
   */
  error?: string | undefined;
}

/**
 * One plain band at the top of a page that asks one question with two answers ("Show times in Europe/Brussels?").
 *
 * Not a dialog (the page stays usable, and the person may ignore the question), not a toast (it stays until an answer),
 * and not for a message without a question. It has no close button: each answer closes it, and the component that
 * shows it removes it.
 *
 * A region named by the question. The question is a paragraph, not a heading: the page keeps its own heading levels.
 * The banner never takes the focus when it shows.
 */
export function QuestionBanner({ question, actions, error }: QuestionBannerProps) {
  const questionId = useId();

  return (
    <div className="border-b border-border bg-surface">
      <div role="region" aria-labelledby={questionId} className="page-container flex flex-wrap items-center gap-x-4 gap-y-2 py-2">
        <p id={questionId} className="text-sm font-bold">
          {question}
        </p>
        <div className="flex flex-wrap gap-2">{actions}</div>
        {error !== undefined && (
          <p role="alert" className="text-sm text-danger-text">
            {error}
          </p>
        )}
      </div>
    </div>
  );
}
