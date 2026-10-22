<?php

namespace Wexample\SymfonyTesting\Enum;

/**
 * What an actor should meet on a page: let in, or turned away, and how. The
 * words of a scenario file: `ok`, `login`, `forbidden`, `not_found`, and
 * `denied` for any of the three refusals, where the way does not matter.
 */
enum AccessExpectation: string
{
    case OK = 'ok';

    /** Sent to the login page: an anonymous visitor on a page for members. */
    case LOGIN = 'login';

    case FORBIDDEN = 'forbidden';

    /** A page hidden rather than refused, as a group outside one's scope. */
    case NOT_FOUND = 'not_found';

    case DENIED = 'denied';

    /**
     * What a response is, in the same words: `denied` is never one, it only
     * gathers the three refusals.
     */
    public static function fromResponse(int $status, bool $toLogin): ?self
    {
        return match (true) {
            $toLogin || 401 === $status => self::LOGIN,
            403 === $status => self::FORBIDDEN,
            404 === $status => self::NOT_FOUND,
            $status < 400 => self::OK,
            default => null,
        };
    }

    public function accepts(?self $met): bool
    {
        if (self::DENIED === $this) {
            return in_array($met, [self::LOGIN, self::FORBIDDEN, self::NOT_FOUND], true);
        }

        return $met === $this;
    }
}
