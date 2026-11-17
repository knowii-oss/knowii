import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { LessonQuiz, Quiz } from '@knowii/common';
import { AppDataProvider, knowiiApiClient, quizFormSchema, quizFormValues } from '@knowii/common';
import { QuizEditor } from './QuizEditor';

const now = new Date('2026-11-14T10:00:00Z');

// The manager view: Markdown sources, correct choices, explanations
const stored: Quiz = {
  cuid: 'quiz1',
  pass_mark: 80,
  created_at: now,
  updated_at: now,
  questions: [
    {
      cuid: 'q1',
      position: 0,
      kind: 'single',
      prompt: 'What is 2 + 2?',
      prompt_html: '<p>What is 2 + 2?</p>',
      explanation: 'Count on your fingers.',
      explanation_html: '<p>Count on your fingers.</p>',
      choices: [
        { cuid: 'q1a', position: 0, label: 'Three', is_correct: false },
        { cuid: 'q1b', position: 1, label: 'Four', is_correct: true },
      ],
    },
    {
      cuid: 'q2',
      position: 1,
      kind: 'multiple',
      prompt: 'Which numbers are even?',
      prompt_html: '<p>Which numbers are even?</p>',
      explanation: null,
      explanation_html: null,
      choices: [
        { cuid: 'q2a', position: 0, label: 'One', is_correct: false },
        { cuid: 'q2b', position: 1, label: 'Two', is_correct: true },
        { cuid: 'q2c', position: 2, label: 'Six', is_correct: true },
      ],
    },
  ],
};

const saved: LessonQuiz = { quiz: stored, result: null };

function renderEditor(quiz: Quiz | null, onChanged = vi.fn(), onClose = vi.fn()) {
  render(
    <AppDataProvider>
      <QuizEditor open communityCuid="c1" spaceCuid="s1" pageCuid="p1" quiz={quiz} onClose={onClose} onChanged={onChanged} />
    </AppDataProvider>,
  );
  return { onChanged, onClose };
}

const question = (number: number) => screen.getByRole('group', { name: `Question ${number}` });

describe('QuizEditor', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('writes a new quiz: a question, its choices, the correct one, then one request (#1335)', async () => {
    const save = vi.spyOn(knowiiApiClient.communities.quizzes, 'save').mockResolvedValue({ ok: true, message: '', data: saved });
    const { onChanged, onClose } = renderEditor(null);

    expect(screen.getByRole('dialog', { name: 'Add a quiz' })).toBeInTheDocument();
    expect(screen.getByLabelText('Pass mark (percent)')).toHaveValue(70);
    await userEvent.type(within(question(1)).getByLabelText('Question'), 'What is 2 + 2?');
    await userEvent.type(screen.getByLabelText('Choice 1 of question 1'), 'Three');
    await userEvent.type(screen.getByLabelText('Choice 2 of question 1'), 'Four');
    await userEvent.click(within(question(1)).getByRole('radio', { name: 'Choice 2 is correct' }));
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    await waitFor(() => expect(save).toHaveBeenCalledOnce());
    expect(save).toHaveBeenCalledWith('c1', 's1', 'p1', {
      pass_mark: 70,
      questions: [
        {
          kind: 'single',
          prompt: 'What is 2 + 2?',
          explanation: '',
          choices: [
            { label: 'Three', correct: false },
            { label: 'Four', correct: true },
          ],
        },
      ],
    });
    expect(onChanged).toHaveBeenCalledOnce();
    expect(onClose).toHaveBeenCalledOnce();
  });

  it('refuses a question without a correct choice, and says so in the question (#1335)', async () => {
    const save = vi.spyOn(knowiiApiClient.communities.quizzes, 'save');
    renderEditor(null);

    await userEvent.type(within(question(1)).getByLabelText('Question'), 'What is 2 + 2?');
    await userEvent.type(screen.getByLabelText('Choice 1 of question 1'), 'Three');
    await userEvent.type(screen.getByLabelText('Choice 2 of question 1'), 'Four');
    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await within(question(1)).findByText('Mark exactly one choice as correct')).toBeInTheDocument();
    expect(save).not.toHaveBeenCalled();
  });

  it('opens a stored quiz with its questions, and warns that new questions delete the results (#1335)', () => {
    renderEditor(stored);

    expect(screen.getByRole('dialog', { name: 'Edit the quiz' })).toBeInTheDocument();
    expect(screen.getByLabelText('Pass mark (percent)')).toHaveValue(80);
    expect(within(question(1)).getByLabelText('Question')).toHaveValue('What is 2 + 2?');
    expect(within(question(1)).getByRole('radio', { name: 'Choice 2 is correct' })).toBeChecked();
    expect(within(question(2)).getByRole('checkbox', { name: 'Choice 3 is correct' })).toBeChecked();
    expect(within(question(2)).getByRole('checkbox', { name: 'Choice 1 is correct' })).not.toBeChecked();
    expect(screen.getByText(/the results of the members are deleted/)).toBeInTheDocument();
  });

  it('keeps one correct choice when a question changes to one answer (#1335)', async () => {
    renderEditor(stored);

    await userEvent.click(within(question(2)).getByRole('radio', { name: 'One answer' }));

    expect(within(question(2)).getByRole('radio', { name: 'Choice 2 is correct' })).toBeChecked();
    expect(within(question(2)).getByRole('radio', { name: 'Choice 3 is correct' })).not.toBeChecked();
  });

  it('moves a question with the arrow buttons and keeps the focus on the button (#1335)', async () => {
    renderEditor(stored);

    const down = screen.getByRole('button', { name: 'Move question 1 down' });
    expect(screen.getByRole('button', { name: 'Move question 1 up' })).toHaveAttribute('aria-disabled', 'true');
    await userEvent.click(down);

    expect(within(question(2)).getByLabelText('Question')).toHaveValue('What is 2 + 2?');
    const moved = screen.getByRole('button', { name: 'Move question 2 down' });
    expect(moved).toHaveFocus();
    expect(moved).toHaveAttribute('aria-disabled', 'true');
  });

  it('adds and deletes choices and questions within the limits (#1335)', async () => {
    renderEditor(null);

    // Two choices at least: no delete button for them
    expect(screen.queryByRole('button', { name: 'Delete choice 1 of question 1' })).toBeNull();
    await userEvent.click(screen.getByRole('button', { name: 'Add a choice to question 1' }));
    expect(screen.getByLabelText('Choice 3 of question 1')).toHaveFocus();
    await userEvent.click(screen.getByRole('button', { name: 'Delete choice 3 of question 1' }));
    expect(screen.queryByLabelText('Choice 3 of question 1')).toBeNull();

    // One question at least
    expect(screen.queryByRole('button', { name: 'Delete question 1' })).toBeNull();
    await userEvent.click(screen.getByRole('button', { name: 'Add a question' }));
    expect(within(question(2)).getByLabelText('Question')).toHaveFocus();
    await userEvent.click(screen.getByRole('button', { name: 'Delete question 2' }));
    expect(screen.queryByRole('group', { name: 'Question 2' })).toBeNull();
  });

  it('shows the errors of the server in their question (#1335)', async () => {
    vi.spyOn(knowiiApiClient.communities.quizzes, 'save').mockResolvedValue({
      ok: false,
      status: 400,
      message: 'The quiz is not valid.',
      errors: { 'questions.1.choices': ['Each choice must be different.'] },
    });
    const { onClose } = renderEditor(stored);

    await userEvent.click(screen.getByRole('button', { name: 'Save' }));

    expect(await within(question(2)).findByText('Each choice must be different.')).toBeInTheDocument();
    expect(onClose).not.toHaveBeenCalled();
  });

  it('gives the focus back to Delete when the manager keeps the quiz (#1335)', async () => {
    const remove = vi.spyOn(knowiiApiClient.communities.quizzes, 'delete');
    renderEditor(stored);

    await userEvent.click(screen.getByRole('button', { name: 'Delete' }));
    await userEvent.click(screen.getByRole('button', { name: 'Keep the quiz' }));

    expect(screen.queryByRole('alert')).toBeNull();
    expect(screen.getByRole('button', { name: 'Delete' })).toHaveFocus();
    expect(remove).not.toHaveBeenCalled();
  });

  it('deletes the quiz after a confirmation (#1335)', async () => {
    const remove = vi.spyOn(knowiiApiClient.communities.quizzes, 'delete').mockResolvedValue({ ok: true, message: '', data: undefined });
    const { onChanged } = renderEditor(stored);

    await userEvent.click(screen.getByRole('button', { name: 'Delete' }));
    expect(screen.getByRole('alert')).toHaveTextContent('Delete this quiz?');
    expect(screen.getByRole('button', { name: 'Keep the quiz' })).toHaveFocus();
    expect(remove).not.toHaveBeenCalled();

    await userEvent.click(screen.getByRole('button', { name: 'Delete the quiz' }));
    await waitFor(() => expect(remove).toHaveBeenCalledWith('c1', 's1', 'p1'));
    expect(onChanged).toHaveBeenCalledOnce();
  });
});

describe('the quiz editor asks before it loses the questions (#1563)', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('asks on Escape after a change, and calls onClose after "Discard"', async () => {
    const { onClose } = renderEditor(stored);
    await userEvent.type(within(question(1)).getByLabelText('Question'), ' And 3?');

    await userEvent.keyboard('{Escape}');

    const ask = await screen.findByRole('dialog', { name: 'Discard your text?' });
    expect(onClose).not.toHaveBeenCalled();
    await userEvent.click(within(ask).getByRole('button', { name: 'Discard' }));
    expect(onClose).toHaveBeenCalledOnce();
  });

  it('closes at once on Escape and on "Cancel" when nothing changed', async () => {
    const { onClose } = renderEditor(stored);
    await screen.findByRole('dialog', { name: 'Edit the quiz' });

    await userEvent.click(screen.getByRole('button', { name: 'Cancel' }));
    expect(onClose).toHaveBeenCalledOnce();

    await userEvent.keyboard('{Escape}');
    expect(onClose).toHaveBeenCalledTimes(2);
    expect(screen.queryByRole('dialog', { name: 'Discard your text?' })).not.toBeInTheDocument();
  });

  it('closes at once on Escape when a new quiz was not touched', async () => {
    const { onClose } = renderEditor(null);
    await screen.findByRole('dialog', { name: 'Add a quiz' });

    await userEvent.keyboard('{Escape}');

    expect(onClose).toHaveBeenCalledOnce();
  });
});

describe('quizFormSchema', () => {
  const valid = quizFormValues(stored);

  it('accepts a stored quiz', () => {
    expect(quizFormSchema.safeParse(valid).success).toBe(true);
  });

  it('wants one correct choice for one answer, at least one for several, and different choices', () => {
    const twoCorrect = structuredClone(valid);
    (twoCorrect.questions[0] as (typeof valid.questions)[number]).choices[0] = { label: 'Three', correct: true };
    expect(quizFormSchema.safeParse(twoCorrect).success).toBe(false);

    const noneCorrect = structuredClone(valid);
    (noneCorrect.questions[1] as (typeof valid.questions)[number]).choices.forEach((choice) => (choice.correct = false));
    expect(quizFormSchema.safeParse(noneCorrect).success).toBe(false);

    const twice = structuredClone(valid);
    (twice.questions[1] as (typeof valid.questions)[number]).choices[0] = { label: ' two ', correct: false };
    expect(quizFormSchema.safeParse(twice).success).toBe(false);
  });

  it('keeps the pass mark between 0 and 100', () => {
    expect(quizFormSchema.safeParse({ ...valid, pass_mark: 0 }).success).toBe(true);
    expect(quizFormSchema.safeParse({ ...valid, pass_mark: 101 }).success).toBe(false);
  });
});
