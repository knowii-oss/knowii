import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { LessonQuiz as LessonQuizData, QuizAttempt, QuizResult } from '@knowii/common';
import { knowiiApiClient, quizOutcome } from '@knowii/common';
import { LessonQuiz } from './LessonQuiz';

const now = new Date('2026-11-14T10:00:00Z');

// The member view: no correct choices and no explanations
const quiz = (passMark = 70, result: QuizResult | null = null): LessonQuizData => ({
  quiz: {
    cuid: 'quiz1',
    pass_mark: passMark,
    created_at: now,
    updated_at: now,
    questions: [
      {
        cuid: 'q1',
        position: 0,
        kind: 'single',
        prompt_html: '<p>What is 2 + 2?</p>',
        choices: [
          { cuid: 'q1a', position: 0, label: 'Three' },
          { cuid: 'q1b', position: 1, label: 'Four' },
        ],
      },
      {
        cuid: 'q2',
        position: 1,
        kind: 'multiple',
        prompt_html: '<p>Which numbers are even?</p>',
        choices: [
          { cuid: 'q2a', position: 0, label: 'One' },
          { cuid: 'q2b', position: 1, label: 'Two' },
          { cuid: 'q2c', position: 2, label: 'Six' },
        ],
      },
    ],
  },
  result,
});

const result = (score: number, passed: boolean, attempts = 1): QuizResult => ({
  attempts,
  best_score: score,
  last_score: score,
  passed,
  passed_at: passed ? now : null,
  review: [
    { question: 'q1', selected: ['q1b'], correct: true, correct_choices: ['q1b'], explanation_html: '<p>Count on your fingers.</p>' },
    { question: 'q2', selected: ['q2b'], correct: score === 100, correct_choices: ['q2b', 'q2c'], explanation_html: null },
  ],
});

const attempt = (data: QuizResult): QuizAttempt => ({
  ...quiz(),
  result: data,
  progress: { total: 3, completed: data.passed ? 1 : 0, completed_lessons: data.passed ? ['p1'] : [], next_lesson: 'p2' },
});

function renderQuiz(data: LessonQuizData, canAnswer = true, onAnswered = vi.fn()) {
  render(<LessonQuiz communityCuid="c1" spaceCuid="s1" pageCuid="p1" quiz={data} canAnswer={canAnswer} onAnswered={onAnswered} />);
  return onAnswered;
}

describe('LessonQuiz', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('shows one group of choices per question: radios for one answer, checkboxes for several (#1335)', () => {
    renderQuiz(quiz());

    expect(screen.getByRole('heading', { name: 'Quiz' })).toBeInTheDocument();
    expect(screen.getByText(/2 questions · You need 70% to pass/)).toBeInTheDocument();
    const first = screen.getByRole('group', { name: /Question 1 of 2/ });
    expect(within(first).getAllByRole('radio')).toHaveLength(2);
    const second = screen.getByRole('group', { name: /Question 2 of 2/ });
    expect(within(second).getAllByRole('checkbox')).toHaveLength(3);
    expect(within(second).getByText('Choose every correct answer.')).toBeInTheDocument();
  });

  it('asks for every answer before it sends them, and moves the focus to the question without one (#1335)', async () => {
    const send = vi.spyOn(knowiiApiClient.communities.quizzes, 'attempt');
    renderQuiz(quiz());

    await userEvent.click(screen.getByRole('radio', { name: 'Four' }));
    await userEvent.click(screen.getByRole('button', { name: 'Check my answers' }));

    expect(screen.getByRole('alert')).toHaveTextContent('Answer every question: question 2 has no answer.');
    expect(screen.getByRole('checkbox', { name: 'One' })).toHaveFocus();
    expect(send).not.toHaveBeenCalled();
  });

  it('sends the answers, then shows the score, the correct choices and the explanations (#1335)', async () => {
    const send = vi
      .spyOn(knowiiApiClient.communities.quizzes, 'attempt')
      .mockResolvedValue({ ok: true, message: '', data: attempt(result(50, false)) });
    const onAnswered = renderQuiz(quiz());

    await userEvent.click(screen.getByRole('radio', { name: 'Four' }));
    await userEvent.click(screen.getByRole('checkbox', { name: 'Two' }));
    await userEvent.click(screen.getByRole('button', { name: 'Check my answers' }));

    expect(send).toHaveBeenCalledWith('c1', 's1', 'p1', [
      { question: 'q1', choices: ['q1b'] },
      { question: 'q2', choices: ['q2b'] },
    ]);
    const status = await screen.findByRole('status');
    expect(status).toHaveTextContent('50%: not passed yet. You need 70%.');
    expect(status).toHaveTextContent('Best score: 50% · 1 attempt');
    expect(status).toHaveFocus();
    expect(onAnswered).toHaveBeenCalledOnce();

    // The review: the outcome of each question in words, the answer of the member, the correct choices
    const items = screen.getAllByRole('listitem').filter((item) => within(item).queryByText(/^Question \d$/) !== null);
    expect(within(items[0] as HTMLElement).getByText('Correct')).toBeInTheDocument();
    expect(within(items[0] as HTMLElement).getByText('Count on your fingers.')).toBeInTheDocument();
    expect(within(items[1] as HTMLElement).getByText('Not correct')).toBeInTheDocument();
    expect(within(items[1] as HTMLElement).getAllByText('Correct answer')).toHaveLength(2);
    expect(within(items[1] as HTMLElement).getAllByText('Your answer')).toHaveLength(1);
    expect(screen.queryByRole('radio')).toBeNull();
  });

  it('lets the member try again at once, with the focus on the first choice (#1335)', async () => {
    renderQuiz(quiz(70, result(50, false)));

    expect(screen.getByRole('status')).toHaveTextContent('50%: not passed yet');
    await userEvent.click(screen.getByRole('button', { name: 'Try again' }));

    expect(screen.getByRole('radio', { name: 'Three' })).toHaveFocus();
    expect(screen.getByRole('radio', { name: 'Four' })).not.toBeChecked();
    // The last result stays in view, and the member can go back to its review
    expect(screen.getByRole('status')).toHaveTextContent('Best score: 50%');
    await userEvent.click(screen.getByRole('button', { name: 'Back to my last result' }));
    expect(screen.getByRole('button', { name: 'Try again' })).toBeInTheDocument();
  });

  it('says "passed" in words, and only the score for a self-check (#1335)', () => {
    renderQuiz(quiz(70, result(100, true, 2)));
    expect(screen.getByRole('status')).toHaveTextContent('You passed with 100%.');
    expect(screen.getByRole('status')).toHaveTextContent('2 attempts');
  });

  it('shows a self-check without a pass mark (#1335)', () => {
    renderQuiz(quiz(0, result(50, true)));
    expect(screen.getByText(/A self-check: there is no pass mark/)).toBeInTheDocument();
    expect(screen.getByRole('status')).toHaveTextContent('Your score: 50%.');
  });

  it('keeps the answers and shows the message when the server refuses the attempt (#1335)', async () => {
    vi.spyOn(knowiiApiClient.communities.quizzes, 'attempt').mockResolvedValue({
      ok: false,
      status: 429,
      message: 'Too many attempts.',
      errors: {},
    });
    renderQuiz(quiz());

    await userEvent.click(screen.getByRole('radio', { name: 'Four' }));
    await userEvent.click(screen.getByRole('checkbox', { name: 'Two' }));
    await userEvent.click(screen.getByRole('button', { name: 'Check my answers' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Too many attempts.');
    expect(screen.getByRole('radio', { name: 'Four' })).toBeChecked();
  });

  it('shows the questions to a reader who is not a member, without a way to answer (#1335)', () => {
    renderQuiz(quiz(), false);

    expect(screen.getByRole('radio', { name: 'Four' })).toBeDisabled();
    expect(screen.queryByRole('button', { name: 'Check my answers' })).toBeNull();
    expect(screen.getByText('Members of the community can answer this quiz.')).toBeInTheDocument();
  });
});

describe('quizOutcome', () => {
  it('compares the score with the pass mark, and never fails a self-check', () => {
    expect(quizOutcome(70, 70)).toBe('passed');
    expect(quizOutcome(70, 69)).toBe('failed');
    expect(quizOutcome(0, 0)).toBe('self-check');
  });
});
