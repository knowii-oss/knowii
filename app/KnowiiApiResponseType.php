<?php

namespace App;

enum KnowiiApiResponseType: string
{
  case ValidationIssue = "validationIssue";
  case AuthenticationIssue = "authenticationIssue";
  case AuthorizationIssue = "authorizationIssue";
  case BusinessIssue = "businessIssue";
  case InternalError = "internalError";
  case Success = "success";
  case NotFound = "notFound";
}
