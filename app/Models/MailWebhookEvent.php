<?php

namespace App\Models;

use App\Traits\GeneratesCuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * An event of the webhook of the mail provider that the route accepted (#1478, ADR 0054): its id (`svix-id`) and its type,
 * so that a retry of the provider is not handled twice. No address, no header, no body. Private: no resource.
 *
 * @property-read int $id
 * @property-read string $cuid
 * @property string $provider_event_id
 * @property string $type
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MailWebhookEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MailWebhookEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MailWebhookEvent query()
 *
 * @mixin \Eloquent
 */
class MailWebhookEvent extends Model
{
    use GeneratesCuid;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_event_id',
        'type',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'id',
        'provider_event_id',
    ];
}
