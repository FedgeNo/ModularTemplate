<?php

declare(strict_types=1);

/**
 * What a self-loading list hands the client to grow itself with. The offset is
 * the client's own count of what it already shows, so everything else the next
 * page needs has to be in the rendered scroll config or the endpoint has no
 * way to know what was asked for.
 *
 * Each list here is subclassed to supply its own rows, so these assert on the
 * rendered markup without a database behind them.
 */
class ItemLoaderTest extends TestCase
{
    /**
     * Renders a list into a throwaway document, standing in for the shared one
     * the app builds a real page into.
     */
    private function elementFor(ItemLoader $list): \DOMElement
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        return $list -> toDOM();
    }

    /** @return array<string, mixed> */
    private function scrollConfigFor(ItemLoader $list): array
    {
        $config = json_decode($this -> elementFor($list) -> getAttribute('data-infinite-scroll'), true);

        return is_array($config) ? $config : [];
    }

    /**
     * A full page, so the list advertises that there's another one to fetch.
     * Public because each list below supplies its rows from an anonymous
     * subclass, which is its own scope.
     */
    public static function fullPage(): array
    {
        return array_fill(0, ItemLoader::PAGE_SIZE + 1, 'item');
    }

    /** A list that pages against a hypothetical endpoint, for the tests below. */
    private function pagingList(array $rows, array $extra_fields = []): ItemList
    {
        $list = new class(['testRows' => $rows, 'testExtraFields' => $extra_fields]) extends ItemList {
            public array $testRows = [];
            public array $testExtraFields = [];

            protected function rows(): array
            {
                return $this -> testRows;
            }

            protected function dataAttributes(): array
            {
                return [
                    'data-infinite-scroll' => json_encode(array_merge([
                        'endpoint' => '/api/item-history',
                        'itemType' => 'Item',
                    ], $this -> testExtraFields)),
                ];
            }
        };

        return $list;
    }

    public function testAPagingListCarriesItsSelectionInTheScrollConfig(): void
    {
        $config = $this -> scrollConfigFor($this -> pagingList(self::fullPage(), ['userId' => 7]));

        $this -> assertSame('/api/item-history', $config['endpoint'] ?? null);
        $this -> assertSame(7, $config['userId'] ?? null, 'the endpoint has no way to know what was asked for otherwise');
    }

    public function testAListWithNothingFurtherToFetchAdvertisesNoScroll(): void
    {
        $list = $this -> pagingList(['only', 'a', 'few']);

        $this -> assertFalse($this -> elementFor($list) -> hasAttribute('data-infinite-scroll'));
    }

    public function testAFullPageIsTrimmedToThePageSize(): void
    {
        $list = $this -> pagingList(self::fullPage());

        $this -> assertTrue($list -> hasMore);
        $this -> assertSame(ItemLoader::PAGE_SIZE, count($list -> items));
    }

    public function testAnEmptyListIsNotRenderedAtAll(): void
    {
        // "No items yet." is not an item, so it is not a row - and with no
        // rows there is no list for one to sit in.
        $empty = new class() extends ItemList {
            protected string $emptyNotice = 'No items yet.';

            protected function rows(): array
            {
                return [];
            }
        };

        $element = $this -> elementFor($empty);

        $this -> assertSame('p', $element -> tagName);
        $this -> assertSame('No items yet.', trim($element -> textContent));
    }

    public function testAListWithRowsIsStillAList(): void
    {
        $filled = new class() extends ItemList {
            protected function rows(): array
            {
                return ItemLoaderTest::fullPage();
            }
        };

        $this -> assertSame('ul', $this -> elementFor($filled) -> tagName);
    }

    public function testRowsAreWrappedInListItems(): void
    {
        $filled = new class() extends ItemList {
            protected function rows(): array
            {
                return ['one', 'two'];
            }
        };

        $element = $this -> elementFor($filled);

        $this -> assertSame(2, $element -> getElementsByTagName('li') -> length);
    }
}
