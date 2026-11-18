import type { FormEventHandler, ReactNode, Ref } from 'react';
import { useEffect, useId, useRef, useState } from 'react';
import { FaCircleCheck, FaCircleXmark, FaListCheck, FaRotateRight } from 'react-icons/fa6';
import type { LessonQuiz as LessonQuizData, QuizAttempt, QuizQuestion, QuizResult, QuizReview } from '@knowii/common';
import { knowiiApiClient, quizOutcome } from '@knowii/common';
import InputError from '@/Components/InputError';
import { Button, Card, CardBody, CardHeader, Checkbox, cn, MarkdownContent, Radio } from '@/Components/ui';

interface Props {
  communityCuid: string;
  spaceCuid: string;
  pageCuid: string;
  /**
   * The quiz of the lesson as this reader may see it, with their result
   */
  quiz: LessonQuizData;
  /**
   * Members of the community answer; a reader who is not a member only reads the questions
   */
  canAnswer: boolean;
  /**
   * The server graded an attempt: the page updates the progress of the member (a pass marks the lesson as done)
   */
  onAnswered?: (attempt: QuizAttempt) => void;
  /**
   * Buttons of the managers of the course, in the header of the card
   */
  actions?: ReactNode;
}

const plural = (count: number, noun: string) => `${count} ${noun}${count === 1 ? '' : 's'}`;

/**
 * The quiz of a lesson (#1335, ADR 0038): a form with one group of choices per question. The server grades the
 * answers, and only then the member sees which choices were correct, with the explanations. They can try again at
 * once. Passing marks the lesson as done; the quiz never blocks the next lesson.
 */
export function LessonQuiz({ communityCuid, spaceCuid, pageCuid, quiz: lessonQuiz, canAnswer, onAnswered, actions }: Props) {
  const headingId = useId();
  const formId = useId();
  const { quiz } = lessonQuiz;
  const [result, setResult] = useState<QuizResult | null>(lessonQuiz.result);
  const [answers, setAnswers] = useState<Record<string, string[]>>({});
  const [retrying, setRetrying] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | undefined>(undefined);
  // Where the focus goes after the view changes: the button that the member pressed is gone
  const pendingFocus = useRef<'summary' | 'form' | null>(null);
  const summary = useRef<HTMLDivElement>(null);
  const form = useRef<HTMLFormElement>(null);

  const showResult = result !== null && !retrying;

  // After the view changed (a new result, a new attempt, back to the result)
  useEffect(() => {
    if (pendingFocus.current === 'summary') {
      summary.current?.focus();
    } else if (pendingFocus.current === 'form') {
      form.current?.querySelector<HTMLInputElement>('input:not([disabled])')?.focus();
    }
    pendingFocus.current = null;
  }, [result, retrying]);

  const choose = (question: QuizQuestion, choiceCuid: string, checked: boolean) => {
    setError(undefined);
    setAnswers((current) => {
      const chosen = current[question.cuid] ?? [];
      const next =
        question.kind === 'single' ? [choiceCuid] : checked ? [...chosen, choiceCuid] : chosen.filter((cuid) => cuid !== choiceCuid);
      return { ...current, [question.cuid]: next };
    });
  };

  const submit: FormEventHandler = async (event) => {
    event.preventDefault();
    if (busy) {
      return;
    }
    const missing = quiz.questions.findIndex((question) => (answers[question.cuid] ?? []).length === 0);
    if (missing !== -1) {
      setError(`Answer every question: question ${missing + 1} has no answer.`);
      form.current?.querySelector<HTMLInputElement>(`[data-question="${quiz.questions[missing]?.cuid ?? ''}"] input`)?.focus();
      return;
    }

    setBusy(true);
    const response = await knowiiApiClient.communities.quizzes.attempt(
      communityCuid,
      spaceCuid,
      pageCuid,
      quiz.questions.map((question) => ({ question: question.cuid, choices: answers[question.cuid] ?? [] })),
    );
    setBusy(false);
    if (!response.ok) {
      setError(response.message);
      return;
    }
    setResult(response.data.result);
    setRetrying(false);
    setAnswers({});
    pendingFocus.current = 'summary';
    onAnswered?.(response.data);
  };

  const tryAgain = () => {
    setError(undefined);
    setRetrying(true);
    pendingFocus.current = 'form';
  };

  return (
    <Card as="section" aria-labelledby={headingId}>
      <CardHeader title={<span id={headingId}>Quiz</span>} icon={<FaListCheck aria-hidden="true" />} actions={actions} />
      <CardBody className="flex flex-col gap-4">
        <p className="text-sm text-muted">
          {plural(quiz.questions.length, 'question')}
          {quiz.pass_mark === 0 ? ' · A self-check: there is no pass mark.' : ` · You need ${quiz.pass_mark}% to pass.`}
          {canAnswer && ' You can try as often as you want.'}
        </p>

        {result !== null && <ResultSummary ref={summary} result={result} passMark={quiz.pass_mark} />}

        {showResult ? (
          <>
            <ol className="flex flex-col gap-4">
              {quiz.questions.map((question, index) => (
                <li key={question.cuid}>
                  <ReviewedQuestion
                    question={question}
                    number={index + 1}
                    review={result.review.find((item) => item.question === question.cuid)}
                  />
                </li>
              ))}
            </ol>
            <div>
              <Button variant="secondary" onClick={tryAgain}>
                <FaRotateRight aria-hidden="true" />
                Try again
              </Button>
            </div>
          </>
        ) : (
          <form ref={form} onSubmit={(event) => void submit(event)} noValidate className="flex flex-col gap-4">
            <ol className="flex flex-col gap-4">
              {quiz.questions.map((question, index) => {
                const promptId = `${formId}-${question.cuid}`;
                const chosen = answers[question.cuid] ?? [];
                return (
                  <li key={question.cuid}>
                    <fieldset
                      data-question={question.cuid}
                      aria-labelledby={promptId}
                      disabled={!canAnswer}
                      className="flex min-w-0 flex-col gap-2 rounded-lg border border-border bg-well p-4"
                    >
                      <div id={promptId}>
                        <p className="text-xs font-extrabold tracking-wide text-muted uppercase">
                          Question {index + 1} of {quiz.questions.length}
                        </p>
                        <MarkdownContent html={question.prompt_html} className="break-words" />
                      </div>
                      {question.kind === 'multiple' && <p className="text-sm text-muted">Choose every correct answer.</p>}
                      {question.choices.map((choice) => (
                        <label
                          key={choice.cuid}
                          className={cn(
                            'flex min-h-11 items-center gap-3 rounded-lg border border-border px-3 py-2',
                            canAnswer ? 'cursor-pointer hover:bg-overlay/10' : 'opacity-80',
                          )}
                        >
                          {question.kind === 'single' ? (
                            <Radio
                              name={promptId}
                              value={choice.cuid}
                              checked={chosen.includes(choice.cuid)}
                              onChange={() => choose(question, choice.cuid, true)}
                            />
                          ) : (
                            <Checkbox
                              value={choice.cuid}
                              checked={chosen.includes(choice.cuid)}
                              onChange={(event) => choose(question, choice.cuid, event.target.checked)}
                            />
                          )}
                          <span className="min-w-0 break-words">{choice.label}</span>
                        </label>
                      ))}
                    </fieldset>
                  </li>
                );
              })}
            </ol>
            <InputError message={error} />
            {canAnswer ? (
              <div className="flex flex-wrap gap-2">
                {/* A busy button keeps the focus (#1564) */}
                <Button type="submit" loading={busy}>
                  Check my answers
                </Button>
                {result !== null && (
                  <Button
                    variant="ghost"
                    onClick={() => {
                      setRetrying(false);
                      pendingFocus.current = 'summary';
                    }}
                  >
                    Back to my last result
                  </Button>
                )}
              </div>
            ) : (
              <p className="text-sm text-muted">Members of the community can answer this quiz.</p>
            )}
          </form>
        )}
      </CardBody>
    </Card>
  );
}

/**
 * The score of the last attempt, in words and not only in color, then the best score and the number of attempts
 */
function ResultSummary({ result, passMark, ref }: { result: QuizResult; passMark: number; ref: Ref<HTMLDivElement> }) {
  const outcome = quizOutcome(passMark, result.last_score);
  return (
    <div
      ref={ref}
      role="status"
      tabIndex={-1}
      className="flex flex-col gap-1 rounded-lg border border-border bg-well p-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
    >
      <p className="flex items-center gap-2 font-bold">
        {outcome === 'passed' && <FaCircleCheck aria-hidden="true" className="shrink-0 text-success" />}
        {outcome === 'failed' && <FaCircleXmark aria-hidden="true" className="shrink-0 text-danger-text" />}
        {outcome === 'passed' && `You passed with ${result.last_score}%.`}
        {outcome === 'failed' && `${result.last_score}%: not passed yet. You need ${passMark}%.`}
        {outcome === 'self-check' && `Your score: ${result.last_score}%.`}
      </p>
      <p className="text-sm text-muted">
        Best score: {result.best_score}% · {plural(result.attempts, 'attempt')}
        {outcome === 'failed' && result.passed && ' · You passed this quiz before.'}
      </p>
    </div>
  );
}

/**
 * A question after an attempt: what the member chose, which choices were correct, and the explanation
 */
function ReviewedQuestion({ question, number, review }: { question: QuizQuestion; number: number; review: QuizReview | undefined }) {
  const selected = new Set(review?.selected ?? []);
  const correct = new Set(review?.correct_choices ?? []);
  return (
    <div className="flex flex-col gap-2 rounded-lg border border-border bg-well p-4">
      <p className="flex flex-wrap items-center gap-2 text-xs font-extrabold tracking-wide uppercase">
        <span className="text-muted">Question {number}</span>
        {review?.correct ? (
          <span className="flex items-center gap-1 text-success">
            <FaCircleCheck aria-hidden="true" />
            Correct
          </span>
        ) : (
          <span className="flex items-center gap-1 text-danger-text">
            <FaCircleXmark aria-hidden="true" />
            Not correct
          </span>
        )}
      </p>
      <MarkdownContent html={question.prompt_html} className="break-words" />
      <ul className="flex flex-col gap-2">
        {question.choices.map((choice) => (
          <li
            key={choice.cuid}
            className={cn(
              'flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border px-3 py-2',
              correct.has(choice.cuid) ? 'border-success' : 'border-border',
            )}
          >
            <span className="min-w-0 grow break-words">{choice.label}</span>
            {selected.has(choice.cuid) && <span className="rounded-full bg-overlay/10 px-2 py-0.5 text-xs font-bold">Your answer</span>}
            {correct.has(choice.cuid) && (
              <span className="flex items-center gap-1 text-xs font-bold text-success">
                <FaCircleCheck aria-hidden="true" />
                Correct answer
              </span>
            )}
          </li>
        ))}
      </ul>
      {review?.explanation_html && (
        <div className="border-l-2 border-border-strong pl-3 text-sm">
          <MarkdownContent html={review.explanation_html} className="break-words" />
        </div>
      )}
    </div>
  );
}
