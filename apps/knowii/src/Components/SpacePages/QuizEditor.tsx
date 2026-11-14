import type { Control, FieldErrors, Path, UseFormRegister, UseFormSetValue } from 'react-hook-form';
import { useFieldArray, useForm, useWatch } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useEffect, useId, useMemo, useRef, useState } from 'react';
import { FaArrowDown, FaArrowUp, FaPlus, FaTrash } from 'react-icons/fa6';
import type { Quiz, QuizForm, QuizQuestionKind } from '@knowii/common';
import {
  DEFAULT_QUIZ_PASS_MARK,
  knowiiApiClient,
  MAX_LENGTH_QUIZ_CHOICE,
  MAX_LENGTH_QUIZ_EXPLANATION,
  MAX_LENGTH_QUIZ_PROMPT,
  MAX_QUIZ_CHOICES,
  MAX_QUIZ_QUESTIONS,
  MIN_QUIZ_CHOICES,
  quizFormSchema,
  quizFormValues,
  useAppData,
} from '@knowii/common';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { Button, Checkbox, Dialog, Radio, SegmentedControl, Textarea, TextInput } from '@/Components/ui';

export interface QuizEditorProps {
  open: boolean;
  communityCuid: string;
  spaceCuid: string;
  pageCuid: string;
  /**
   * The quiz to edit, as a manager gets it (with the Markdown sources and the correct choices). Null: a new quiz.
   */
  quiz: Quiz | null;
  onClose: () => void;
  /**
   * The quiz was saved or deleted: the page loads it again
   */
  onChanged: () => void;
}

const KIND_OPTIONS = [
  { value: 'single', label: 'One answer' },
  { value: 'multiple', label: 'Several answers' },
] as const;

const emptyQuestion = (): QuizForm['questions'][number] => ({
  kind: 'single',
  prompt: '',
  explanation: '',
  choices: [
    { label: '', correct: false },
    { label: '', correct: false },
  ],
});

/**
 * An error on a list (the questions, the choices of a question): the resolver puts it in `root`, the server in `message`
 */
const listError = (error: unknown): string | undefined => {
  if (typeof error !== 'object' || error === null) {
    return undefined;
  }
  const { root, message } = error as { root?: { message?: string }; message?: string };
  return root?.message ?? message;
};

/**
 * Write the quiz of a lesson (#1335, ADR 0038): a pass mark, then questions with their choices and an optional
 * explanation. One save sends the whole quiz. The arrow buttons move a question, so no drag and drop is needed.
 */
export function QuizEditor({ open, communityCuid, spaceCuid, pageCuid, quiz, onClose, onChanged }: QuizEditorProps) {
  const { showToast } = useAppData();
  const formId = useId();
  const values = useMemo<QuizForm>(
    () => (quiz ? quizFormValues(quiz) : { pass_mark: DEFAULT_QUIZ_PASS_MARK, questions: [emptyQuestion()] }),
    [quiz],
  );
  const form = useForm<QuizForm>({ resolver: zodResolver(quizFormSchema), defaultValues: values });
  const questions = useFieldArray({ control: form.control, name: 'questions' });
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [keptQuiz, setKeptQuiz] = useState(false);
  // The button to focus again after a question moved: React moves its DOM node, and the browser drops the focus
  const movedButton = useRef<string | null>(null);
  const container = useRef<HTMLFormElement>(null);
  const addQuestionButton = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (open) {
      form.reset(values);
    }
  }, [open, values, form]);

  useEffect(() => {
    if (movedButton.current !== null) {
      container.current?.querySelector<HTMLButtonElement>(`[data-move="${movedButton.current}"]`)?.focus();
      movedButton.current = null;
    }
  }, [questions.fields]);

  const errors = form.formState.errors;

  const onSubmit = form.handleSubmit(async (data) => {
    const result = await knowiiApiClient.communities.quizzes.save(communityCuid, spaceCuid, pageCuid, data);
    if (result.ok) {
      showToast({ severity: 'success', summary: 'Quiz saved' });
      onChanged();
      onClose();
      return;
    }
    // The server names the fields like the form does: questions.0.choices, questions.1.prompt
    for (const [field, messages] of Object.entries(result.errors)) {
      const message = messages[0];
      if (message) {
        form.setError(field as Path<QuizForm>, { message });
      }
    }
    showToast({ severity: 'error', summary: result.message });
  });

  const deleteQuiz = async () => {
    setDeleting(true);
    const result = await knowiiApiClient.communities.quizzes.delete(communityCuid, spaceCuid, pageCuid);
    setDeleting(false);
    if (!result.ok) {
      showToast({ severity: 'error', summary: result.message });
      return;
    }
    showToast({ severity: 'success', summary: 'Quiz deleted' });
    onChanged();
    onClose();
  };

  const move = (index: number, by: -1 | 1, fieldId: string) => {
    const target = index + by;
    if (target < 0 || target >= questions.fields.length) {
      return;
    }
    movedButton.current = `${fieldId}:${by}`;
    questions.move(index, target);
  };

  return (
    <Dialog
      open={open}
      onClose={onClose}
      // The next opening starts without the delete question
      onAfterClose={() => {
        setConfirmingDelete(false);
        setKeptQuiz(false);
      }}
      size="lg"
      title={quiz ? 'Edit the quiz' : 'Add a quiz'}
      description="Members answer after the lesson. The server grades their answers, and they can try again."
      footer={
        confirmingDelete ? (
          <>
            <Button
              variant="secondary"
              autoFocus
              disabled={deleting}
              onClick={() => {
                setConfirmingDelete(false);
                setKeptQuiz(true);
              }}
            >
              Keep the quiz
            </Button>
            <Button variant="danger" onClick={() => void deleteQuiz()} loading={deleting}>
              Delete the quiz
            </Button>
            {/* Last in the code, first on the screen: above the buttons on a phone, before them on a wider screen */}
            <p role="alert" className="text-sm sm:order-first sm:mr-auto">
              Delete this quiz? Its questions and the results of the members are deleted for good. The lessons that members finished stay
              done.
            </p>
          </>
        ) : (
          <>
            {quiz && (
              // After "Keep the quiz", the focus comes back to the button that asked the question
              <Button variant="ghost" className="mr-auto" autoFocus={keptQuiz} onClick={() => setConfirmingDelete(true)}>
                <FaTrash aria-hidden="true" />
                Delete
              </Button>
            )}
            <Button variant="secondary" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" form={formId} aria-busy={form.formState.isSubmitting || undefined}>
              Save
            </Button>
          </>
        )
      }
    >
      <form
        id={formId}
        ref={container}
        noValidate
        className="flex flex-col gap-5"
        onSubmit={(event) => {
          event.preventDefault();
          if (!form.formState.isSubmitting && !confirmingDelete) {
            void onSubmit(event);
          }
        }}
      >
        {quiz && (
          <p className="rounded-lg border border-border bg-well p-3 text-sm text-muted">
            When you change the questions or the choices, the results of the members are deleted: their scores were about other questions. A
            new pass mark alone keeps them.
          </p>
        )}

        <div>
          <InputLabel htmlFor={`${formId}-pass-mark`}>Pass mark (percent)</InputLabel>
          <TextInput
            id={`${formId}-pass-mark`}
            type="number"
            inputMode="numeric"
            min={0}
            max={100}
            step={1}
            wrapperClassName="mt-1 max-w-32"
            {...form.register('pass_mark', { valueAsNumber: true })}
            data-autofocus
            invalid={!!errors.pass_mark}
            aria-describedby={`${formId}-pass-mark-hint`}
          />
          <p id={`${formId}-pass-mark-hint`} className="mt-1 text-xs text-muted">
            The share of correct answers that a member needs. 0 makes the quiz a self-check: every attempt passes.
          </p>
          <InputError className="mt-2" message={errors.pass_mark?.message} />
        </div>

        <ol className="flex flex-col gap-5">
          {questions.fields.map((field, index) => (
            <li key={field.id}>
              <QuestionFields
                formId={formId}
                index={index}
                count={questions.fields.length}
                fieldId={field.id}
                control={form.control}
                register={form.register}
                setValue={form.setValue}
                errors={errors}
                onMove={(by) => move(index, by, field.id)}
                onRemove={() => {
                  questions.remove(index);
                  addQuestionButton.current?.focus();
                }}
              />
            </li>
          ))}
        </ol>
        <InputError message={listError(errors.questions)} />

        {questions.fields.length < MAX_QUIZ_QUESTIONS && (
          <div>
            <Button
              ref={addQuestionButton}
              variant="secondary"
              onClick={() => questions.append(emptyQuestion(), { focusName: `questions.${questions.fields.length}.prompt` })}
            >
              <FaPlus aria-hidden="true" />
              Add a question
            </Button>
          </div>
        )}
      </form>
    </Dialog>
  );
}

interface QuestionFieldsProps {
  formId: string;
  index: number;
  count: number;
  fieldId: string;
  control: Control<QuizForm>;
  register: UseFormRegister<QuizForm>;
  setValue: UseFormSetValue<QuizForm>;
  errors: FieldErrors<QuizForm>;
  onMove: (by: -1 | 1) => void;
  onRemove: () => void;
}

/**
 * One question of the quiz: its kind, its text, its choices with the correct mark, and an explanation
 */
function QuestionFields({ formId, index, count, fieldId, control, register, setValue, errors, onMove, onRemove }: QuestionFieldsProps) {
  const choices = useFieldArray({ control, name: `questions.${index}.choices` });
  const kind = useWatch({ control, name: `questions.${index}.kind` });
  const watched = useWatch({ control, name: `questions.${index}.choices` });
  const addChoiceButton = useRef<HTMLButtonElement>(null);
  const id = `${formId}-q${fieldId}`;
  const questionErrors = errors.questions?.[index];
  const number = index + 1;

  const setKind = (next: QuizQuestionKind) => {
    setValue(`questions.${index}.kind`, next, { shouldDirty: true });
    if (next === 'single') {
      // One correct choice at most: keep the first one
      const first = watched.findIndex((choice) => choice.correct);
      watched.forEach((choice, position) => {
        if (choice.correct && position !== first) {
          setValue(`questions.${index}.choices.${position}.correct`, false, { shouldDirty: true });
        }
      });
    }
  };

  const markCorrect = (position: number, correct: boolean) => {
    if (kind === 'single') {
      watched.forEach((_, other) => setValue(`questions.${index}.choices.${other}.correct`, other === position, { shouldDirty: true }));
    } else {
      setValue(`questions.${index}.choices.${position}.correct`, correct, { shouldDirty: true });
    }
  };

  return (
    <fieldset aria-labelledby={`${id}-title`} className="flex min-w-0 flex-col gap-3 rounded-lg border border-border bg-well p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <h3 id={`${id}-title`} className="text-sm font-extrabold tracking-wide uppercase">
          Question {number}
        </h3>
        <span className="flex items-center">
          <Button
            variant="ghost"
            size="sm"
            iconOnly
            data-move={`${fieldId}:-1`}
            aria-label={`Move question ${number} up`}
            // aria-disabled, not disabled: a disabled button loses the focus when the question reaches the end of the list
            aria-disabled={index === 0 || undefined}
            onClick={() => index > 0 && onMove(-1)}
          >
            <FaArrowUp aria-hidden="true" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            iconOnly
            data-move={`${fieldId}:1`}
            aria-label={`Move question ${number} down`}
            aria-disabled={index === count - 1 || undefined}
            onClick={() => index < count - 1 && onMove(1)}
          >
            <FaArrowDown aria-hidden="true" />
          </Button>
          {count > 1 && (
            <Button variant="ghost" size="sm" iconOnly aria-label={`Delete question ${number}`} onClick={onRemove}>
              <FaTrash aria-hidden="true" />
            </Button>
          )}
        </span>
      </div>

      <SegmentedControl<QuizQuestionKind>
        aria-label={`Kind of question ${number}`}
        name={`${id}-kind`}
        options={KIND_OPTIONS}
        value={kind}
        onChange={setKind}
      />

      <div>
        <InputLabel htmlFor={`${id}-prompt`}>Question</InputLabel>
        <Textarea
          id={`${id}-prompt`}
          wrapperClassName="mt-1"
          rows={2}
          maxLength={MAX_LENGTH_QUIZ_PROMPT}
          {...register(`questions.${index}.prompt`)}
          invalid={!!questionErrors?.prompt}
          aria-describedby={`${id}-prompt-hint`}
        />
        <p id={`${id}-prompt-hint`} className="mt-1 text-xs text-muted">
          Markdown works. Text only: put images in the text of the lesson.
        </p>
        <InputError className="mt-2" message={questionErrors?.prompt?.message} />
      </div>

      <div role="group" aria-labelledby={`${id}-choices`} className="flex flex-col gap-2">
        <p id={`${id}-choices`} className="text-sm">
          Choices <span className="text-muted">({kind === 'single' ? 'mark the correct one' : 'mark every correct one'})</span>
        </p>
        {choices.fields.map((choice, position) => (
          <div key={choice.id} className="flex items-start gap-2">
            <span className="flex h-11 items-center">
              {kind === 'single' ? (
                <Radio
                  name={`${id}-correct`}
                  aria-label={`Choice ${position + 1} is correct`}
                  checked={watched[position]?.correct === true}
                  onChange={() => markCorrect(position, true)}
                />
              ) : (
                <Checkbox
                  aria-label={`Choice ${position + 1} is correct`}
                  checked={watched[position]?.correct === true}
                  onChange={(event) => markCorrect(position, event.target.checked)}
                />
              )}
            </span>
            <div className="min-w-0 grow">
              <TextInput
                aria-label={`Choice ${position + 1} of question ${number}`}
                maxLength={MAX_LENGTH_QUIZ_CHOICE}
                {...register(`questions.${index}.choices.${position}.label`)}
                invalid={!!questionErrors?.choices?.[position]?.label}
              />
              <InputError className="mt-1" message={questionErrors?.choices?.[position]?.label?.message} />
            </div>
            {choices.fields.length > MIN_QUIZ_CHOICES && (
              <Button
                variant="ghost"
                iconOnly
                aria-label={`Delete choice ${position + 1} of question ${number}`}
                onClick={() => {
                  choices.remove(position);
                  addChoiceButton.current?.focus();
                }}
              >
                <FaTrash aria-hidden="true" />
              </Button>
            )}
          </div>
        ))}
        <InputError message={listError(questionErrors?.choices)} />
        {choices.fields.length < MAX_QUIZ_CHOICES && (
          <div>
            <Button
              ref={addChoiceButton}
              variant="ghost"
              size="sm"
              aria-label={`Add a choice to question ${number}`}
              onClick={() =>
                choices.append({ label: '', correct: false }, { focusName: `questions.${index}.choices.${choices.fields.length}.label` })
              }
            >
              <FaPlus aria-hidden="true" />
              Add a choice
            </Button>
          </div>
        )}
      </div>

      <div>
        <InputLabel htmlFor={`${id}-explanation`}>Explanation (optional)</InputLabel>
        <Textarea
          id={`${id}-explanation`}
          wrapperClassName="mt-1"
          rows={2}
          maxLength={MAX_LENGTH_QUIZ_EXPLANATION}
          {...register(`questions.${index}.explanation`)}
          invalid={!!questionErrors?.explanation}
          aria-describedby={`${id}-explanation-hint`}
        />
        <p id={`${id}-explanation-hint`} className="mt-1 text-xs text-muted">
          Members see it after they answer, with the correct choices.
        </p>
        <InputError className="mt-2" message={questionErrors?.explanation?.message} />
      </div>
    </fieldset>
  );
}
