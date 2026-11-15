<?php

use App\ApiRoutes;
use App\Http\Controllers\API\AccessGroupApiController;
use App\Http\Controllers\API\ChatMessageApiController;
use App\Http\Controllers\API\CommentApiController;
use App\Http\Controllers\API\CommunityApiController;
use App\Http\Controllers\API\CommunityInvitationApiController;
use App\Http\Controllers\API\CommunityJoinBlockApiController;
use App\Http\Controllers\API\CommunityResourceApiController;
use App\Http\Controllers\API\CommunityResourceCommentApiController;
use App\Http\Controllers\API\ConversationApiController;
use App\Http\Controllers\API\CourseMaterialApiController;
use App\Http\Controllers\API\IndexingApiController;
use App\Http\Controllers\API\JoinPolicyApiController;
use App\Http\Controllers\API\LessonProgressApiController;
use App\Http\Controllers\API\LoginApiController;
use App\Http\Controllers\API\MarkdownApiController;
use App\Http\Controllers\API\MemberApiController;
use App\Http\Controllers\API\ModeratorPermissionApiController;
use App\Http\Controllers\API\NewsletterApiController;
use App\Http\Controllers\API\NotificationApiController;
use App\Http\Controllers\API\NotificationLevelApiController;
use App\Http\Controllers\API\OnlineMemberApiController;
use App\Http\Controllers\API\PageApiController;
use App\Http\Controllers\API\PingApiController;
use App\Http\Controllers\API\PostApiController;
use App\Http\Controllers\API\QuizApiController;
use App\Http\Controllers\API\ReactionApiController;
use App\Http\Controllers\API\SpaceApiController;
use App\Http\Controllers\API\SpaceGroupApiController;
use App\Http\Controllers\API\ThreadFollowApiController;
use App\Http\Controllers\API\UploadApiController;
use App\Http\Controllers\API\UserApiController;
use App\Http\Controllers\API\UserBlockApiController;
use App\Http\Controllers\API\UserEntitlementApiController;
use App\Http\Controllers\API\UserIdentityApiController;
use App\Http\Controllers\API\WaitlistApiController;
use Illuminate\Support\Facades\Route;

Route::prefix(ApiRoutes::VERSION)->group(function () {
    // Auth
    // The limiter of the web login (email + IP): no unlimited guessing of passwords or codes (#1256)
    Route::post(ApiRoutes::AUTH_LOGIN, [LoginApiController::class, 'login'])->middleware('throttle:login');

    // Utils
    Route::get(ApiRoutes::PING, [PingApiController::class, 'ping']);

    // Waitlist (public, rate limited: see AppServiceProvider)
    Route::post(ApiRoutes::WAITLIST, [WaitlistApiController::class, 'join'])->middleware('throttle:waitlist');

    // Newsletter of the footer (public, rate limited, #938)
    Route::post(ApiRoutes::NEWSLETTER, [NewsletterApiController::class, 'subscribe'])->middleware('throttle:newsletter');

    // What visitors may read (#1387, ADR 0044): the public spaces. The request goes on without an account (a session or a token
    // still sign in, and an unverified account is refused), each route keeps its gate (SpacePolicy::view: a chat space is
    // never readable without an account) and its token abilities, and visitors are limited per IP address. Who reacted, chat
    // messages, online members, member search, quizzes, the download of a course material and every write need an account
    Route::middleware(['auth.optional', 'throttle:public-reads'])->scopeBindings()->group(function () {
        // The public page of a community (#1485): the community in its cut shape, the navigation of its public spaces, and
        // the newest posts of its public discussion spaces. A member gets the full community and their own navigation
        Route::get(ApiRoutes::COMMUNITY, [CommunityApiController::class, 'show'])->middleware('ability:communities:read');
        Route::get(ApiRoutes::COMMUNITY_SPACE_GROUPS, [SpaceGroupApiController::class, 'index'])->middleware('ability:communities:read');
        Route::get(ApiRoutes::COMMUNITY_NEWEST_POSTS, [PostApiController::class, 'newest'])->middleware('ability:posts:read');
        // Spaces
        Route::get(ApiRoutes::COMMUNITY_SPACE, [SpaceApiController::class, 'show'])->middleware('ability:communities:read');
        // Posts and comments of discussion spaces
        Route::get(ApiRoutes::COMMUNITY_SPACE_POSTS, [PostApiController::class, 'index'])->middleware('ability:posts:read');
        Route::get(ApiRoutes::COMMUNITY_SPACE_POST, [PostApiController::class, 'show'])->middleware('ability:posts:read');
        Route::get(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [CommentApiController::class, 'index'])->middleware('ability:posts:read');
        // Pages and lessons
        Route::get(ApiRoutes::COMMUNITY_SPACE_PAGES, [PageApiController::class, 'index'])->middleware('ability:pages:read');
        Route::get(ApiRoutes::COMMUNITY_SPACE_PAGE, [PageApiController::class, 'show'])->middleware('ability:pages:read');
        // The list of the course materials (the download needs an account)
        Route::get(ApiRoutes::COMMUNITY_SPACE_MATERIALS, [CourseMaterialApiController::class, 'index'])->middleware('ability:pages:read');
        // Resources and their comments
        Route::get(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [CommunityResourceApiController::class, 'index'])->middleware('ability:resources:read');
        Route::get(ApiRoutes::COMMUNITY_SPACE_RESOURCE, [CommunityResourceApiController::class, 'show'])->middleware('ability:resources:read');
        Route::get(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENTS, [CommunityResourceCommentApiController::class, 'index'])->middleware('ability:resources:read');
    });

    // Every route names the token abilities that it needs (ADR 0022, #689). Session users pass every check
    // Verified email addresses only, like the web app (#1323): an account proves that it owns its address first
    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        // The current user, as an API resource (#1145)
        Route::get(ApiRoutes::USER, [UserApiController::class, 'me'])->middleware('ability:user:read');
        Route::post(ApiRoutes::USER_AVATAR, [UserApiController::class, 'updateAvatar'])->middleware('ability:user:write')->middleware('throttle:uploads');
        Route::delete(ApiRoutes::USER_AVATAR, [UserApiController::class, 'deleteAvatar'])->middleware('ability:user:write');
        Route::patch(ApiRoutes::USER_ONLINE_STATUS, [UserApiController::class, 'updateOnlineStatus'])->middleware('ability:user:write');
        // The emoji of "Frequently used" in the reaction picker (#1204)
        Route::get(ApiRoutes::USER_FREQUENT_EMOJI, [UserApiController::class, 'frequentEmoji'])->middleware('ability:user:read');
        // Blocked members (#1186)
        Route::get(ApiRoutes::USER_BLOCKS, [UserBlockApiController::class, 'index'])->middleware('ability:user:read');
        Route::put(ApiRoutes::USER_BLOCK, [UserBlockApiController::class, 'store'])->middleware('ability:user:write');
        Route::delete(ApiRoutes::USER_BLOCK, [UserBlockApiController::class, 'destroy'])->middleware('ability:user:write');
        // The provider accounts that the member connected (#1385, ADR 0045). Own rows only: the row of another member gives a
        // 404. The abilities are not in the default set of a new token
        Route::get(ApiRoutes::USER_IDENTITIES, [UserIdentityApiController::class, 'index'])->middleware('ability:identities:read');
        Route::delete(ApiRoutes::USER_IDENTITY, [UserIdentityApiController::class, 'destroy'])->middleware('ability:identities:write');
        // What the sign-in provider says the member owns (#1386, ADR 0047): own data only, the ability is not in the default set
        Route::get(ApiRoutes::USER_ENTITLEMENTS, [UserEntitlementApiController::class, 'index'])->middleware('ability:entitlements:read');
        Route::post(ApiRoutes::USERS_IS_USERNAME_AVAILABLE, [UserApiController::class, 'isUsernameAvailable'])->middleware('ability:user:read');

        // The communities of the current member (#828)
        Route::get(ApiRoutes::COMMUNITIES, [CommunityApiController::class, 'index'])->middleware('ability:communities:read');
        Route::post(ApiRoutes::COMMUNITIES, [CommunityApiController::class, 'store'])->middleware('ability:communities:write');
        // The create and rename forms call it while the user types (#1136)
        Route::post(ApiRoutes::COMMUNITIES_IS_NAME_AVAILABLE, [CommunityApiController::class, 'isNameAvailable'])->middleware('ability:communities:read')->middleware('throttle:name-checks');
        Route::patch(ApiRoutes::COMMUNITY, [CommunityApiController::class, 'update'])->middleware('ability:communities:write');
        Route::delete(ApiRoutes::COMMUNITY, [CommunityApiController::class, 'destroy'])->middleware('ability:communities:write');
        Route::patch(ApiRoutes::COMMUNITY_MODERATOR_PERMISSIONS, [ModeratorPermissionApiController::class, 'update'])->middleware('ability:communities:write');
        // Who can join the community (#1486)
        Route::patch(ApiRoutes::COMMUNITY_JOIN_POLICY, [JoinPolicyApiController::class, 'update'])->middleware('ability:communities:write');
        // Search engines may index the public spaces of the community (#1487)
        Route::patch(ApiRoutes::COMMUNITY_INDEXING, [IndexingApiController::class, 'update'])->middleware('ability:communities:write');
        // The owner gives the community to an admin, with their password (#1339): rate limited against password guessing
        Route::post(ApiRoutes::COMMUNITY_OWNERSHIP, [CommunityApiController::class, 'transferOwnership'])->middleware('ability:communities:write')->middleware('throttle:ownership-transfers');

        // Space groups and spaces (#1173). scopeBindings: a space group or space of another community gives a 404
        Route::scopeBindings()->group(function () {
            Route::post(ApiRoutes::COMMUNITY_SPACE_GROUPS, [SpaceGroupApiController::class, 'store'])->middleware('ability:communities:write');
            Route::post(ApiRoutes::COMMUNITY_SPACE_GROUPS_REORDER, [SpaceGroupApiController::class, 'reorder'])->middleware('ability:communities:write');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_GROUP, [SpaceGroupApiController::class, 'update'])->middleware('ability:communities:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_GROUP, [SpaceGroupApiController::class, 'destroy'])->middleware('ability:communities:write');
            Route::post(ApiRoutes::COMMUNITY_SPACE_GROUP_SPACES_REORDER, [SpaceApiController::class, 'reorder'])->middleware('ability:communities:write');

            Route::post(ApiRoutes::COMMUNITY_SPACES, [SpaceApiController::class, 'store'])->middleware('ability:communities:write');
            Route::patch(ApiRoutes::COMMUNITY_SPACE, [SpaceApiController::class, 'update'])->middleware('ability:communities:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE, [SpaceApiController::class, 'destroy'])->middleware('ability:communities:write');

            // Posts (#1174)
            Route::post(ApiRoutes::COMMUNITY_SPACE_POSTS, [PostApiController::class, 'store'])->middleware('ability:posts:write')->middleware('throttle:posts');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_POST, [PostApiController::class, 'update'])->middleware('ability:posts:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_POST, [PostApiController::class, 'destroy'])->middleware('ability:posts:write');
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_PIN, [PostApiController::class, 'pin'])->middleware('ability:posts:write');

            // Follows of a thread and levels of a space, of the current member (#1475, ADR 0042): they change only the member's
            // own choices, and only a member of the community who can view the space has them
            Route::get(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, [ThreadFollowApiController::class, 'showPost'])->middleware('ability:notifications:read');
            Route::put(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, [ThreadFollowApiController::class, 'followPost'])->middleware('ability:notifications:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_POST_FOLLOW, [ThreadFollowApiController::class, 'stopPost'])->middleware('ability:notifications:write');
            Route::put(ApiRoutes::COMMUNITY_SPACE_POST_MUTE, [ThreadFollowApiController::class, 'mutePost'])->middleware('ability:notifications:write');
            Route::get(ApiRoutes::COMMUNITY_SPACE_MESSAGE_FOLLOW, [ThreadFollowApiController::class, 'showMessage'])->middleware('ability:notifications:read');
            Route::put(ApiRoutes::COMMUNITY_SPACE_MESSAGE_FOLLOW, [ThreadFollowApiController::class, 'followMessage'])->middleware('ability:notifications:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_MESSAGE_FOLLOW, [ThreadFollowApiController::class, 'stopMessage'])->middleware('ability:notifications:write');
            Route::put(ApiRoutes::COMMUNITY_SPACE_MESSAGE_MUTE, [ThreadFollowApiController::class, 'muteMessage'])->middleware('ability:notifications:write');
            Route::get(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, [NotificationLevelApiController::class, 'show'])->middleware('ability:notifications:read');
            Route::put(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, [NotificationLevelApiController::class, 'update'])->middleware('ability:notifications:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_NOTIFICATION_LEVEL, [NotificationLevelApiController::class, 'destroy'])->middleware('ability:notifications:write');

            // Messages of chat spaces (#1185)
            Route::get(ApiRoutes::COMMUNITY_SPACE_MESSAGES, [ChatMessageApiController::class, 'index'])->middleware('ability:chat:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_MESSAGES, [ChatMessageApiController::class, 'store'])->middleware('ability:chat:write')->middleware('throttle:messages');
            Route::post(ApiRoutes::COMMUNITY_SPACE_MESSAGES_READ, [ChatMessageApiController::class, 'read'])->middleware('ability:chat:read');
            Route::get(ApiRoutes::COMMUNITY_SPACE_MESSAGE, [ChatMessageApiController::class, 'show'])->middleware('ability:chat:read');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_MESSAGE, [ChatMessageApiController::class, 'update'])->middleware('ability:chat:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_MESSAGE, [ChatMessageApiController::class, 'destroy'])->middleware('ability:chat:write');
            Route::get(ApiRoutes::COMMUNITY_SPACE_MESSAGE_THREAD, [ChatMessageApiController::class, 'thread'])->middleware('ability:chat:read');
            Route::get(ApiRoutes::COMMUNITY_SPACE_MESSAGE_REACTIONS, [ReactionApiController::class, 'listMessage'])->middleware('ability:chat:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_MESSAGE_REACTIONS, [ReactionApiController::class, 'toggleMessage'])->middleware('ability:chat:write')->middleware('throttle:reactions');

            // Pages of page spaces (#1179)
            Route::post(ApiRoutes::COMMUNITY_SPACE_PAGES, [PageApiController::class, 'store'])->middleware('ability:pages:write');
            Route::post(ApiRoutes::COMMUNITY_SPACE_PAGES_ORDER, [PageApiController::class, 'reorder'])->middleware('ability:pages:write');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_PAGE, [PageApiController::class, 'update'])->middleware('ability:pages:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_PAGE, [PageApiController::class, 'destroy'])->middleware('ability:pages:write');

            // Course progress of the current member (#1304): it changes only their own data, so pages:read is enough
            Route::get(ApiRoutes::COMMUNITY_SPACE_PROGRESS, [LessonProgressApiController::class, 'show'])->middleware('ability:pages:read');
            Route::put(ApiRoutes::COMMUNITY_SPACE_PAGE_PROGRESS, [LessonProgressApiController::class, 'store'])->middleware('ability:pages:read');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_PAGE_PROGRESS, [LessonProgressApiController::class, 'destroy'])->middleware('ability:pages:read');

            // The quiz of a lesson (#1335, ADR 0038): managers write it; an attempt changes only the member's own data
            Route::get(ApiRoutes::COMMUNITY_SPACE_PAGE_QUIZ, [QuizApiController::class, 'show'])->middleware('ability:pages:read');
            Route::put(ApiRoutes::COMMUNITY_SPACE_PAGE_QUIZ, [QuizApiController::class, 'update'])->middleware('ability:pages:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_PAGE_QUIZ, [QuizApiController::class, 'destroy'])->middleware('ability:pages:write');
            Route::post(ApiRoutes::COMMUNITY_SPACE_PAGE_QUIZ_ATTEMPTS, [QuizApiController::class, 'attempt'])->middleware('ability:pages:read')->middleware('throttle:quiz-attempts');

            // Supplementary materials of course spaces (#1336, ADR 0037): members read and download, managers change
            Route::post(ApiRoutes::COMMUNITY_SPACE_MATERIALS, [CourseMaterialApiController::class, 'store'])->middleware('ability:pages:write')->middleware('throttle:uploads');
            Route::post(ApiRoutes::COMMUNITY_SPACE_MATERIALS_ORDER, [CourseMaterialApiController::class, 'reorder'])->middleware('ability:pages:write');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_MATERIAL, [CourseMaterialApiController::class, 'update'])->middleware('ability:pages:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_MATERIAL, [CourseMaterialApiController::class, 'destroy'])->middleware('ability:pages:write');
            Route::get(ApiRoutes::COMMUNITY_SPACE_MATERIAL_DOWNLOAD, [CourseMaterialApiController::class, 'download'])->middleware('ability:pages:read');

            // Resources of resources spaces (ADR 0015)
            Route::post(ApiRoutes::COMMUNITY_SPACE_RESOURCES, [CommunityResourceApiController::class, 'store'])->middleware('ability:resources:create,resources:write');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_RESOURCE, [CommunityResourceApiController::class, 'update'])->middleware('ability:resources:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_RESOURCE, [CommunityResourceApiController::class, 'destroy'])->middleware('ability:resources:write');
            Route::post(ApiRoutes::COMMUNITY_SPACE_RESOURCE_CAPTURE, [CommunityResourceApiController::class, 'capture'])->middleware('ability:resources:write')->middleware('throttle:captures');

            // Comments on resources (#1034)
            Route::post(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENTS, [CommunityResourceCommentApiController::class, 'store'])->middleware('ability:resources:write')->middleware('throttle:comments');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT, [CommunityResourceCommentApiController::class, 'update'])->middleware('ability:resources:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT, [CommunityResourceCommentApiController::class, 'destroy'])->middleware('ability:resources:write');
            Route::get(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT_REACTIONS, [ReactionApiController::class, 'listResourceComment'])->middleware('ability:resources:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_RESOURCE_COMMENT_REACTIONS, [ReactionApiController::class, 'toggleResourceComment'])->middleware('ability:resources:write')->middleware('throttle:reactions');

            // Comments (#1175)
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_COMMENTS, [CommentApiController::class, 'store'])->middleware('ability:posts:write')->middleware('throttle:comments');
            Route::patch(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT, [CommentApiController::class, 'update'])->middleware('ability:posts:write');
            Route::delete(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT, [CommentApiController::class, 'destroy'])->middleware('ability:posts:write');

            // Reactions (#1176)
            Route::get(ApiRoutes::COMMUNITY_SPACE_POST_REACTIONS, [ReactionApiController::class, 'listPost'])->middleware('ability:posts:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_REACTIONS, [ReactionApiController::class, 'togglePost'])->middleware('ability:posts:write')->middleware('throttle:reactions');
            Route::get(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT_REACTIONS, [ReactionApiController::class, 'listComment'])->middleware('ability:posts:read');
            Route::post(ApiRoutes::COMMUNITY_SPACE_POST_COMMENT_REACTIONS, [ReactionApiController::class, 'toggleComment'])->middleware('ability:posts:write')->middleware('throttle:reactions');
        });

        Route::post(ApiRoutes::COMMUNITY_UPLOADS, [UploadApiController::class, 'store'])->middleware('ability:posts:write')->middleware('throttle:uploads');

        // Members (#1177 search, #1242 management)
        // Direct messages (#1186, ADR 0028). scopeBindings: a conversation of another community, or a message of another
        // conversation, gives a 404
        Route::get(ApiRoutes::COMMUNITY_CONVERSATIONS, [ConversationApiController::class, 'index'])->middleware('ability:messages:read');
        Route::post(ApiRoutes::COMMUNITY_CONVERSATIONS, [ConversationApiController::class, 'store'])->middleware('ability:messages:write')->middleware('throttle:messages');
        Route::scopeBindings()->group(function () {
            Route::get(ApiRoutes::COMMUNITY_CONVERSATION, [ConversationApiController::class, 'show'])->middleware('ability:messages:read');
            Route::get(ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES, [ConversationApiController::class, 'messages'])->middleware('ability:messages:read');
            Route::post(ApiRoutes::COMMUNITY_CONVERSATION_MESSAGES, [ConversationApiController::class, 'send'])->middleware('ability:messages:write')->middleware('throttle:messages');
            Route::post(ApiRoutes::COMMUNITY_CONVERSATION_READ, [ConversationApiController::class, 'read'])->middleware('ability:messages:read');
            Route::get(ApiRoutes::COMMUNITY_CONVERSATION_MESSAGE, [ConversationApiController::class, 'message'])->middleware('ability:messages:read');
            Route::patch(ApiRoutes::COMMUNITY_CONVERSATION_MESSAGE, [ConversationApiController::class, 'updateMessage'])->middleware('ability:messages:write');
            Route::delete(ApiRoutes::COMMUNITY_CONVERSATION_MESSAGE, [ConversationApiController::class, 'destroyMessage'])->middleware('ability:messages:write');
            Route::get(ApiRoutes::COMMUNITY_CONVERSATION_MESSAGE_REACTIONS, [ReactionApiController::class, 'listDirectMessage'])->middleware('ability:messages:read');
            Route::post(ApiRoutes::COMMUNITY_CONVERSATION_MESSAGE_REACTIONS, [ReactionApiController::class, 'toggleDirectMessage'])->middleware('ability:messages:write')->middleware('throttle:reactions');
        });

        // The notifications of the current member, in every community (#1474, ADR 0042). Own rows only: the row of another
        // member gives a 404. The abilities are not in the default set of a new token
        Route::get(ApiRoutes::NOTIFICATIONS, [NotificationApiController::class, 'index'])->middleware('ability:notifications:read');
        Route::get(ApiRoutes::NOTIFICATIONS_COUNT, [NotificationApiController::class, 'count'])->middleware('ability:notifications:read');
        Route::post(ApiRoutes::NOTIFICATIONS_SEEN, [NotificationApiController::class, 'seen'])->middleware('ability:notifications:write');
        // "I am caught up" (#1475): the rows of "Following" are read, "Needs you" is never touched
        Route::post(ApiRoutes::NOTIFICATIONS_CAUGHT_UP, [NotificationApiController::class, 'caughtUp'])->middleware('ability:notifications:write');
        // What the member chose for each space of a community, in one call (#1475)
        Route::get(ApiRoutes::COMMUNITY_NOTIFICATION_LEVELS, [NotificationLevelApiController::class, 'index'])->middleware('ability:notifications:read');
        Route::put(ApiRoutes::NOTIFICATION_READ, [NotificationApiController::class, 'read'])->middleware('ability:notifications:write');
        Route::post(ApiRoutes::NOTIFICATION_UNREAD, [NotificationApiController::class, 'unread'])->middleware('ability:notifications:write');

        // Who is online now (#1187, ADR 0027)
        Route::get(ApiRoutes::COMMUNITY_ONLINE_MEMBERS, [OnlineMemberApiController::class, 'community'])->middleware('ability:communities:read');
        Route::scopeBindings()->group(function () {
            Route::get(ApiRoutes::COMMUNITY_SPACE_ONLINE_MEMBERS, [OnlineMemberApiController::class, 'space'])->middleware('ability:chat:read');
        });

        Route::get(ApiRoutes::COMMUNITY_MEMBERS, [MemberApiController::class, 'index'])->middleware('ability:communities:read')->middleware('throttle:markdown-preview');
        Route::patch(ApiRoutes::COMMUNITY_MEMBER, [MemberApiController::class, 'update'])->middleware('ability:communities:write');
        Route::delete(ApiRoutes::COMMUNITY_MEMBER, [MemberApiController::class, 'destroy'])->middleware('ability:communities:write');
        // The current member leaves the community (#1338), or joins an open one by themselves (#1486, ADR 0044)
        Route::delete(ApiRoutes::COMMUNITY_MEMBERSHIP, [MemberApiController::class, 'leave'])->middleware('ability:communities:write');
        Route::post(ApiRoutes::COMMUNITY_MEMBERSHIP, [MemberApiController::class, 'join'])->middleware('ability:communities:write')->middleware('throttle:joins');
        // The people whom a manager removed cannot join again by themselves, until a manager lifts it (#1486)
        Route::get(ApiRoutes::COMMUNITY_JOIN_BLOCKS, [CommunityJoinBlockApiController::class, 'index'])->middleware('ability:communities:read');
        Route::delete(ApiRoutes::COMMUNITY_JOIN_BLOCK, [CommunityJoinBlockApiController::class, 'destroy'])->middleware('ability:communities:write');

        // Access groups (#1241, ADR 0025). scopeBindings: a group of another community gives a 404
        Route::scopeBindings()->group(function () {
            Route::get(ApiRoutes::COMMUNITY_ACCESS_GROUPS, [AccessGroupApiController::class, 'index'])->middleware('ability:groups:read');
            Route::post(ApiRoutes::COMMUNITY_ACCESS_GROUPS, [AccessGroupApiController::class, 'store'])->middleware('ability:groups:write');
            Route::patch(ApiRoutes::COMMUNITY_ACCESS_GROUP, [AccessGroupApiController::class, 'update'])->middleware('ability:groups:write');
            Route::delete(ApiRoutes::COMMUNITY_ACCESS_GROUP, [AccessGroupApiController::class, 'destroy'])->middleware('ability:groups:write');
            Route::get(ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBERS, [AccessGroupApiController::class, 'members'])->middleware('ability:groups:read');
            Route::post(ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBERS, [AccessGroupApiController::class, 'addMember'])->middleware('ability:groups:write');
            Route::delete(ApiRoutes::COMMUNITY_ACCESS_GROUP_MEMBER, [AccessGroupApiController::class, 'removeMember'])->middleware('ability:groups:write');
            // An access group opens whole space groups (#1422, ADR 0041)
            Route::get(ApiRoutes::COMMUNITY_ACCESS_GROUP_SPACE_GROUPS, [AccessGroupApiController::class, 'spaceGroups'])->middleware('ability:groups:read');
            Route::put(ApiRoutes::COMMUNITY_ACCESS_GROUP_SPACE_GROUPS, [AccessGroupApiController::class, 'setSpaceGroups'])->middleware('ability:groups:write');
            // The entitlement keys that give the group (#1386, ADR 0047): owner and admins, in a listed community
            Route::get(ApiRoutes::COMMUNITY_ACCESS_GROUP_RULES, [AccessGroupApiController::class, 'rules'])->middleware('ability:groups:read');
            Route::put(ApiRoutes::COMMUNITY_ACCESS_GROUP_RULES, [AccessGroupApiController::class, 'setRules'])->middleware('ability:groups:write');
            Route::get(ApiRoutes::COMMUNITY_SPACE_GROUP_ACCESS_GROUPS, [SpaceGroupApiController::class, 'accessGroups'])->middleware('ability:groups:read');
            Route::put(ApiRoutes::COMMUNITY_SPACE_GROUP_ACCESS_GROUPS, [SpaceGroupApiController::class, 'setAccessGroups'])->middleware('ability:groups:write');
        });

        // Invitations (#1242). scopeBindings: an invitation of another community gives a 404
        Route::scopeBindings()->group(function () {
            Route::get(ApiRoutes::COMMUNITY_INVITATIONS, [CommunityInvitationApiController::class, 'index'])->middleware('ability:communities:read');
            Route::post(ApiRoutes::COMMUNITY_INVITATIONS, [CommunityInvitationApiController::class, 'store'])->middleware('ability:communities:write')->middleware('throttle:invitations');
            Route::delete(ApiRoutes::COMMUNITY_INVITATION, [CommunityInvitationApiController::class, 'destroy'])->middleware('ability:communities:write');
        });

        Route::post(ApiRoutes::MARKDOWN_PREVIEW, [MarkdownApiController::class, 'preview'])->middleware('ability:posts:read')->middleware('throttle:markdown-preview');
    });
});
