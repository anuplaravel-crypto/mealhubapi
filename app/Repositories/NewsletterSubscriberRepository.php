<?php

namespace App\Repositories;

use App\Models\NewsletterSubscriber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Every Eloquent query against the `newsletter_subscribers` table.
 *
 * Two lookups, because the table has two keys that matter: `email` is what a
 * signup collides on, and `token` is what an emailed link carries. Neither is
 * the primary key — `find()` is only ever reached by the admin delete route,
 * through route-model binding.
 *
 * @extends BaseRepository<NewsletterSubscriber>
 */
class NewsletterSubscriberRepository extends BaseRepository
{
    protected function model(): string
    {
        return NewsletterSubscriber::class;
    }

    /**
     * The row a signup would collide with.
     *
     * The caller lowercases first — the column is stored lowercase so one
     * address cannot occupy two rows, and this method does not silently
     * normalise on the caller's behalf.
     */
    public function findByEmail(string $email): ?NewsletterSubscriber
    {
        return $this->query()->where('email', $email)->first();
    }

    public function findByToken(string $token): ?NewsletterSubscriber
    {
        return $this->query()->where('token', $token)->first();
    }

    /**
     * Whether a token is already taken, for the uniqueness loop that mints one.
     *
     * `exists()` rather than {@see self::findByToken()} because the answer is a
     * boolean and hydrating a model to discard it is wasted work.
     */
    public function tokenExists(string $token): bool
    {
        return $this->query()->where('token', $token)->exists();
    }

    /**
     * One page of the admin list, newest signup first.
     *
     * `BaseRepository::paginate()` applies no ordering, and an unordered
     * paginated list is not stable across pages — a row inserted mid-read can
     * push another onto a page the client has already fetched.
     *
     * @return LengthAwarePaginator<int, NewsletterSubscriber>
     */
    public function paginateLatest(int $perPage): LengthAwarePaginator
    {
        return $this->query()->latest()->paginate($perPage);
    }
}
