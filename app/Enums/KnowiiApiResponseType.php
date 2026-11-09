<?php

namespace App\Enums;

enum KnowiiApiResponseType: string
{
    case ValidationIssue = 'validationIssue';
    case AuthenticationIssue = 'authenticationIssue';
    case AuthorizationIssue = 'authorizationIssue';
    case BusinessIssue = 'businessIssue';
    case UnsupportedMediaType = 'unsupportedMediaType';
    case InternalError = 'internalError';
    case Success = 'success';
    case NotFound = 'notFound';
    case MethodNotAllowed = 'methodNotAllowed';
    case TooManyRequests = 'tooManyRequests';
    case HttpIssue = 'httpIssue';
}
