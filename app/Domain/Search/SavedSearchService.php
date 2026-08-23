<?php

namespace App\Domain\Search;

use App\Models\SavedSearch;
use App\Models\SearchHistory;
use App\Models\User;
use Illuminate\Support\Collection;

class SavedSearchService
{
    public const MAX_HISTORY_PER_USER = 20;

    /**
     * @return Collection<int, SavedSearch>
     */
    public function listFor(User $user, ?string $entity = null): Collection
    {
        return $user->savedSearches()
            ->when($entity !== null, fn ($q) => $q->where('entity', $entity))
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function create(User $user, string $name, string $entity, array $params): SavedSearch
    {
        /** @var SavedSearch */
        return $user->savedSearches()->create([
            'name' => $name,
            'entity' => $entity,
            'params' => $params,
        ]);
    }

    /**
     * Record a search term for the user's history, keeping only the most recent
     * MAX_HISTORY_PER_USER entries.
     */
    public function recordHistory(User $user, string $entity, string $term): void
    {
        SearchHistory::query()->create([
            'user_id' => $user->id,
            'entity' => $entity,
            'term' => $term,
        ]);

        // Keep only the newest N entries (portable across drivers).
        $keepIds = $user->searchHistories()
            ->orderByDesc('id')
            ->take(self::MAX_HISTORY_PER_USER)
            ->pluck('id');

        SearchHistory::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * The user's recent search terms, newest first.
     *
     * @return Collection<int, SearchHistory>
     */
    public function historyFor(User $user, ?string $entity = null): Collection
    {
        return $user->searchHistories()
            ->when($entity !== null, fn ($q) => $q->where('entity', $entity))
            ->latest()
            ->take(self::MAX_HISTORY_PER_USER)
            ->get();
    }
}
