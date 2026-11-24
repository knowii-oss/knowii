<?php

namespace Tests\Fixtures\MailSearch\Elsewhere;

use Illuminate\Notifications\Notification;

/**
 * A notification with no mail: the search of the mail classes does not take it (ADR 0069)
 */
class NestedNotification extends Notification {}
