import { z } from 'zod';
import {
  MAX_LENGTH_QUIZ_CHOICE,
  MAX_LENGTH_QUIZ_EXPLANATION,
  MAX_LENGTH_QUIZ_PROMPT,
  MAX_QUIZ_CHOICES,
  MAX_QUIZ_QUESTIONS,
  MIN_QUIZ_CHOICES,
  MIN_QUIZ_QUESTIONS,
} from '../constants';
import { courseProgressSchema } from './page.schema';

// single: one correct choice; multiple: one or more, all needed. WARNING: aligned with KnowiiQuizQuestionKind.php
export const QUIZ_QUESTION_KINDS = ['single', 'multiple'] as const;
export const quizQuestionKindSchema = z.enum(QUIZ_QUESTION_KINDS);
export type QuizQuestionKind = z.infer<typeof quizQuestionKindSchema>;

/**
 * The quiz of a lesson (#1335, ADR 0038). A member gets the questions and the labels of the choices. The Markdown
 * sources, `is_correct` and the explanations are there for the managers of the course only.
 * WARNING: aligned with QuizResource.php
 */
export const quizChoiceSchema = z.object({
  cuid: z.string(),
  position: z.number().int(),
  label: z.string(),
  is_correct: z.boolean().optional(),
});
export type QuizChoice = z.infer<typeof quizChoiceSchema>;

export const quizQuestionSchema = z.object({
  cuid: z.string(),
  position: z.number().int(),
  kind: quizQuestionKindSchema,
  prompt_html: z.string(),
  choices: z.array(quizChoiceSchema),
  prompt: z.string().optional(),
  explanation: z.string().nullable().optional(),
  explanation_html: z.string().nullable().optional(),
});
export type QuizQuestion = z.infer<typeof quizQuestionSchema>;

export const quizSchema = z.object({
  cuid: z.string(),
  // Percent of correct answers needed to pass; 0: a self-check, every attempt passes
  pass_mark: z.number().int(),
  questions: z.array(quizQuestionSchema),
  created_at: z.coerce.date(),
  updated_at: z.coerce.date(),
});
export type Quiz = z.infer<typeof quizSchema>;

/**
 * What the server says about one question after an attempt: what the member chose, whether it is correct, the correct
 * choices and the explanation. WARNING: aligned with Quizzes.php
 */
export const quizReviewSchema = z.object({
  question: z.string(),
  selected: z.array(z.string()),
  correct: z.boolean(),
  correct_choices: z.array(z.string()),
  explanation_html: z.string().nullable(),
});
export type QuizReview = z.infer<typeof quizReviewSchema>;

/**
 * The result of the current member: one per quiz, with the review of their last attempt
 */
export const quizResultSchema = z.object({
  attempts: z.number().int(),
  best_score: z.number().int(),
  last_score: z.number().int(),
  passed: z.boolean(),
  passed_at: z.coerce.date().nullable(),
  review: z.array(quizReviewSchema),
});
export type QuizResult = z.infer<typeof quizResultSchema>;

/**
 * The quiz of a lesson as its reader may see it, with their result (null while they did not answer, and for a reader
 * who is not a member)
 */
export const lessonQuizSchema = z.object({
  quiz: quizSchema,
  result: quizResultSchema.nullable(),
});
export type LessonQuiz = z.infer<typeof lessonQuizSchema>;

/**
 * The answer to an attempt: the quiz, the new result, and the progress in the course (a pass marks the lesson as done)
 */
export const quizAttemptSchema = lessonQuizSchema.extend({ progress: courseProgressSchema });
export type QuizAttempt = z.infer<typeof quizAttemptSchema>;

/**
 * The choices that a member picks for one question
 */
export interface QuizAnswer {
  question: string;
  choices: string[];
}

const quizQuestionFormSchema = z
  .object({
    kind: quizQuestionKindSchema,
    prompt: z
      .string()
      .trim()
      .min(1, { error: 'Write the question' })
      .max(MAX_LENGTH_QUIZ_PROMPT, { error: `The question is too long (maximum ${MAX_LENGTH_QUIZ_PROMPT} characters)` }),
    explanation: z
      .string()
      .trim()
      .max(MAX_LENGTH_QUIZ_EXPLANATION, { error: `The explanation is too long (maximum ${MAX_LENGTH_QUIZ_EXPLANATION} characters)` }),
    choices: z
      .array(
        z.object({
          label: z
            .string()
            .trim()
            .min(1, { error: 'Give the choice a text' })
            .max(MAX_LENGTH_QUIZ_CHOICE, { error: `The choice is too long (maximum ${MAX_LENGTH_QUIZ_CHOICE} characters)` }),
          correct: z.boolean(),
        }),
      )
      .min(MIN_QUIZ_CHOICES, { error: `Give at least ${MIN_QUIZ_CHOICES} choices` })
      .max(MAX_QUIZ_CHOICES, { error: `Give at most ${MAX_QUIZ_CHOICES} choices` }),
  })
  .superRefine((question, context) => {
    const correct = question.choices.filter((choice) => choice.correct).length;
    if (question.kind === 'single' && correct !== 1) {
      context.addIssue({ code: 'custom', path: ['choices'], message: 'Mark exactly one choice as correct' });
    } else if (correct === 0) {
      context.addIssue({ code: 'custom', path: ['choices'], message: 'Mark at least one choice as correct' });
    }
    const labels = question.choices.map((choice) => choice.label.toLowerCase()).filter((label) => label !== '');
    if (new Set(labels).size !== labels.length) {
      context.addIssue({ code: 'custom', path: ['choices'], message: 'Each choice must be different' });
    }
  });

// WARNING: aligned with SaveQuiz::rules()
export const quizFormSchema = z.object({
  pass_mark: z
    .number({ error: 'Enter a number from 0 to 100' })
    .int({ error: 'Enter a whole number' })
    .min(0, { error: 'Enter a number from 0 to 100' })
    .max(100, { error: 'Enter a number from 0 to 100' }),
  questions: z
    .array(quizQuestionFormSchema)
    .min(MIN_QUIZ_QUESTIONS, { error: 'Add a question' })
    .max(MAX_QUIZ_QUESTIONS, { error: `A quiz has at most ${MAX_QUIZ_QUESTIONS} questions` }),
});
export type QuizForm = z.infer<typeof quizFormSchema>;

/**
 * The form values of a stored quiz (the manager view, with the Markdown sources and the correct choices)
 */
export function quizFormValues(quiz: Quiz): QuizForm {
  return {
    pass_mark: quiz.pass_mark,
    questions: quiz.questions.map((question) => ({
      kind: question.kind,
      prompt: question.prompt ?? '',
      explanation: question.explanation ?? '',
      choices: question.choices.map((choice) => ({ label: choice.label, correct: choice.is_correct === true })),
    })),
  };
}

/**
 * What a member reads about a score: "You passed", "Not passed yet", or only the score for a self-check
 */
export function quizOutcome(passMark: number, score: number): 'passed' | 'failed' | 'self-check' {
  if (passMark === 0) {
    return 'self-check';
  }
  return score >= passMark ? 'passed' : 'failed';
}
