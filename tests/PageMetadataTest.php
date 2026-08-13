<?php

declare(strict_types=1);

/**
 * What the head says about a page to something that is not reading it.
 *
 * All of this is invisible on the page itself, so nothing here fails loudly:
 * a description cut to the wrong length, or a date that never appears, looks
 * exactly like one that is right until somebody shares a link or looks at a
 * search result weeks later.
 */
class PageMetadataTest extends TestCase
{
    /** @return array<string, string> content by tag name */
    private function metaFor(string $description, ?string $created = null, ?string $edited = null): array
    {
        $method = new \ReflectionMethod(Page::class, 'metaTags');
        $method -> setAccessible(true);

        $tags = [];

        foreach ($method -> invoke(null, 'A Title', $description, null, 'https://example.test/', $created, $edited) as $tag) {
            $tags[(string) ($tag -> name ?? $tag -> property)] = (string) $tag -> content;
        }

        return $tags;
    }

    private function longText(): string
    {
        return trim(str_repeat('People here are talking about several different things at once. ', 10));
    }

    /**
     * A search snippet has less room than a shared card, so one length for
     * both meant every share was clipped to a limit that was not its own.
     */
    public function testAShareGetsMoreRoomThanASearchSnippet(): void
    {
        $tags = $this -> metaFor($this -> longText());

        $this -> assertTrue(mb_strlen($tags['description']) <= Page::META_DESCRIPTION_MAX_LENGTH);
        $this -> assertTrue(mb_strlen($tags['og:description']) <= Page::SOCIAL_DESCRIPTION_MAX_LENGTH);
        $this -> assertTrue(
            mb_strlen($tags['og:description']) > mb_strlen($tags['description']),
            'the card says more than the snippet'
        );
        $this -> assertSame($tags['og:description'], $tags['twitter:description']);
    }

    /** A description short enough for both is not cut for either. */
    public function testShortEnoughIsLeftAlone(): void
    {
        $tags = $this -> metaFor('Eight words is not very many words at all.');

        $this -> assertSame('Eight words is not very many words at all.', $tags['description']);
        $this -> assertSame('Eight words is not very many words at all.', $tags['og:description']);
    }

    /**
     * A page that says when it is from can be judged fresh or stale rather
     * than undated, which is a different kind of result.
     */
    public function testADatedPageSaysWhenItIsFrom(): void
    {
        $tags = $this -> metaFor('Words.', '2026-08-01 10:00:00', '2026-08-02 11:00:00');

        $this -> assertSame('article', $tags['og:type'], 'the article properties need the article type');
        $this -> assertSame('2026-08-01T10:00:00+00:00', $tags['article:published_time']);
        $this -> assertSame('2026-08-02T11:00:00+00:00', $tags['article:modified_time']);
    }

    /**
     * And one that is not about anything dated claims no date at all - an
     * invented one is worse than none.
     */
    public function testAnUndatedPageClaimsNothing(): void
    {
        $tags = $this -> metaFor('Words.');

        $this -> assertSame('website', $tags['og:type']);
        $this -> assertFalse(isset($tags['article:published_time']));
        $this -> assertFalse(isset($tags['article:modified_time']));
    }

    /** A post nobody edited is not reported as revised. */
    public function testAnUneditedPageHasNoModifiedTime(): void
    {
        $tags = $this -> metaFor('Words.', '2026-08-01 10:00:00');

        $this -> assertSame('2026-08-01T10:00:00+00:00', $tags['article:published_time']);
        $this -> assertFalse(isset($tags['article:modified_time']));
    }

    /** A timestamp that is not one is left off rather than emitted as nonsense. */
    public function testRubbishInPlaceOfADateIsDropped(): void
    {
        $tags = $this -> metaFor('Words.', 'not a date at all');

        $this -> assertSame('website', $tags['og:type']);
        $this -> assertFalse(isset($tags['article:published_time']));
    }
}
