<?php

namespace App\Enums;

/**
 * The code that the client turns into the sentence "Why did I get this?" (#1475, ADR 0042, design 8.1). The text stays
 * on the client side, so that it can be translated.
 */
enum KnowiiNotificationReason: string
{
    // You wrote the post
    case WrotePost = 'wrote_post';
    // You wrote the comment
    case WroteComment = 'wrote_comment';
    // A member mentioned you
    case Mentioned = 'mentioned';
    // You follow the thread (the follow reason says why: wrote, replied, manual)
    case FollowsThread = 'follows_thread';
    // You chose "Everything" for the space, or the space sends to everyone
    case SpaceEverything = 'space_everything';
    // You started the course
    case StartedCourse = 'started_course';
    // You host the event (#1516)
    case HostsEvent = 'hosts_event';
}
