<?php

namespace App\Enums;

/**
 * The kinds of question of a lesson quiz (#1335, ADR 0038). WARNING: aligned with quizQuestionKindSchema (quiz.schema.ts)
 */
enum KnowiiQuizQuestionKind: string
{
    // Exactly one choice is correct: the member picks one
    case Single = 'single';
    // One or more choices are correct: the member needs all of them, and no other, for the point
    case Multiple = 'multiple';
}
