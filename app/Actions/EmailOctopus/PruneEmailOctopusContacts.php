<?php

namespace App\Actions\EmailOctopus;

use App\Contracts\Retention\PrunesMailingListContacts;
use App\Exceptions\TechnicalException;
use App\Support\Retention;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Applies the retention periods of the privacy policy to the EmailOctopus lists (ADR 0030, #77):
 *
 * - the waitlist keeps a contact for Retention::waitlistMonths() after the signup, in every status
 * - every list (waitlist, newsletter) deletes the contacts that never confirmed their address (pending) or that
 *   unsubscribed, Retention::inactiveContactsDays() after their last change
 *
 * Without an API key, or for a list without an ID, nothing happens. API: https://emailoctopus.com/api-documentation/v2
 */
final class PruneEmailOctopusContacts implements PrunesMailingListContacts
{
    private const int HTTP_TIMEOUT_SECONDS = 20;

    private const int PAGE_SIZE = 100;

    /**
     * Protection against an endless loop when the API keeps returning a next page
     */
    private const int MAX_PAGES = 1000;

    final public function prune(bool $dryRun = false): array
    {
        $apiKey = config('services.emailoctopus.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            Log::info('Mailing list contacts not pruned: EmailOctopus is not configured');

            return [];
        }

        $lists = array_filter([
            'waitlist' => config('services.emailoctopus.waitlist_list_id'),
            'newsletter' => config('services.emailoctopus.newsletter_list_id'),
        ], static fn (mixed $id): bool => is_string($id) && $id !== '');

        $inactiveBefore = now()->subDays(Retention::inactiveContactsDays());
        $counts = [];

        foreach ($lists as $name => $listId) {
            /** @var string $listId */
            $ids = [];
            foreach (['pending', 'unsubscribed'] as $status) {
                $ids = [...$ids, ...$this->contactIds($listId, $status, 'last_updated_at.lte', $inactiveBefore)];
            }
            if ($name === 'waitlist') {
                $signedUpBefore = now()->subMonths(Retention::waitlistMonths());
                foreach (['subscribed', 'pending', 'unsubscribed'] as $status) {
                    $ids = [...$ids, ...$this->contactIds($listId, $status, 'created_at.lte', $signedUpBefore)];
                }
            }

            $ids = array_values(array_unique($ids));
            if (! $dryRun) {
                foreach ($ids as $contactId) {
                    $this->delete($listId, $contactId);
                }
            }
            $counts[$name] = count($ids);
        }

        Log::info($dryRun ? 'Mailing list contacts to delete found' : 'Mailing list contacts deleted', $counts);

        return $counts;
    }

    /**
     * Collects the IDs first: deleting while paging with a cursor can skip contacts.
     *
     * @return list<string>
     *
     * @throws TechnicalException
     */
    private function contactIds(string $listId, string $status, string $dateFilter, CarbonInterface $before): array
    {
        $ids = [];
        $cursor = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $query = ['status' => $status, $dateFilter => $before->toIso8601String(), 'limit' => self::PAGE_SIZE];
            if ($cursor !== null) {
                $query['starting_after'] = $cursor;
            }

            $response = $this->send(fn (PendingRequest $http) => $http->get($this->url('/lists/'.rawurlencode($listId).'/contacts'), $query));

            $data = $response->json('data');
            foreach (is_array($data) ? $data : [] as $contact) {
                if (is_array($contact) && is_string($contact['id'] ?? null)) {
                    $ids[] = $contact['id'];
                }
            }

            $next = $response->json('paging.next.starting_after');
            if (! is_string($next) || $next === '') {
                break;
            }
            $cursor = $next;
        }

        return $ids;
    }

    /**
     * @throws TechnicalException
     */
    private function delete(string $listId, string $contactId): void
    {
        $this->send(
            fn (PendingRequest $http) => $http->delete($this->url('/lists/'.rawurlencode($listId).'/contacts/'.rawurlencode($contactId))),
            allowNotFound: true
        );
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     *
     * @throws TechnicalException
     */
    private function send(callable $request, bool $allowNotFound = false): Response
    {
        $apiKey = config('services.emailoctopus.api_key');
        $apiKey = is_string($apiKey) ? $apiKey : '';

        try {
            $response = $request(Http::withToken($apiKey)->acceptJson()->timeout(self::HTTP_TIMEOUT_SECONDS));
        } catch (ConnectionException $e) {
            throw new TechnicalException('Could not reach EmailOctopus', null, $e);
        }

        if ($response->successful() || ($allowNotFound && $response->status() === 404)) {
            return $response;
        }

        Log::error('EmailOctopus request failed', ['status' => $response->status(), 'body' => $response->json('detail')]);
        throw new TechnicalException('EmailOctopus request failed with status '.$response->status());
    }

    private function url(string $path): string
    {
        $baseUrl = config('services.emailoctopus.base_url');

        return rtrim(is_string($baseUrl) ? $baseUrl : '', '/').$path;
    }
}
