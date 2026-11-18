<?php

use App\Constants;

// A list of email addresses separated by commas
$list = static fn (string $value): array => array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Email delivery (#1477, ADR 0054)
    |--------------------------------------------------------------------------
    |
    | The notification mail (App\Mail\NotificationMail) goes on its own queue, read by a worker of its own, behind the
    | rate limiter "mail". The sign-in links, the invitations and the other account mail stay on "default" and are never
    | held. MAIL_QUEUE=default puts the notification mail on the first worker (an instance with one worker): the limit
    | and the hold still apply. App\Support\MailSettings reads and checks these values.
    |
    */

    'queue' => env('MAIL_QUEUE', Constants::QUEUE_MAIL),

    // Notification mail sent per second, for all mail workers together. 0: no limit (a value below 0 counts as 0). The
    // values below are not cast here: an empty value ("KEY=") would become 0. MailSettings narrows them and gives the
    // default for an empty or a wrong value
    'rate_limit_per_second' => env('MAIL_RATE_LIMIT_PER_SECOND', 5),

    // mail:check-budget: the alert at 80% of the monthly quota and the hold of the notification mail
    'budget' => [
        // The mail that the plan of the provider allows each month. Empty or 0: the limit that the provider gives, or none
        'monthly_quota' => env('MAIL_MONTHLY_QUOTA', 0),

        // Notification mail stops at this share of the quota, so that the account mail keeps a reserve (1 to 100)
        'hold_percent' => env('MAIL_NOTIFICATION_HOLD_PERCENT', 95),

        // Who gets the alert, separated by commas. Empty: the log line is the alert
        'alert_to' => $list((string) env('MAIL_ALERT_TO', '')),
    ],

];
