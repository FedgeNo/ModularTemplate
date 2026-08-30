<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/../src/classes/' . $class . '.php';
    if (is_file($file)) require $file;
});

require __DIR__ . '/../src/functions.php';

function parityCases(): array
{
    return [
        'Article' => ['class' => Article::class, 'content' => ['Article']],
        'Button' => ['class' => Button::class, 'properties' => ['type' => 'submit', 'id' => 'save'], 'content' => ['Save']],
        'Div' => ['class' => Div::class, 'content' => ['Division']],
        'Section' => ['class' => Section::class, 'content' => ['Section']],
        'Figure' => ['class' => Figure::class, 'content' => ['Figure']],
        'Heading2' => ['class' => Heading2::class, 'content' => ['Heading Two']],
        'Heading3' => ['class' => Heading3::class, 'content' => ['Heading Three']],
        'ListItem' => ['class' => ListItem::class, 'content' => ['List Item']],
        'Paragraph' => ['class' => Paragraph::class, 'content' => ['Paragraph']],
        'Span' => ['class' => Span::class, 'content' => ['Span']],
        'Table' => ['class' => Table::class],
        'UnorderedList' => ['class' => UnorderedList::class],
        'TableRow' => ['class' => TableRow::class],
        'TableHeader' => ['class' => TableHeader::class, 'content' => ['Header']],
        'TableData' => ['class' => TableData::class, 'content' => ['Data']],
        'Anchor' => ['class' => Anchor::class, 'serverArgs' => ['/a-route?x=1', 'A Link'], 'clientArgs' => ['/a-route?x=1', 'A Link']],
        'Image' => ['class' => Image::class, 'properties' => ['src' => '/media/example.jpg', 'alt' => 'Example image']],
        'Card' => ['class' => Card::class, 'attributes' => ['data-card' => 'example'], 'content' => ['Card']],
        'RelativeTime' => ['class' => RelativeTime::class, 'serverArgs' => ['2020-01-02 15:04:05'], 'clientArgs' => ['2020-01-02 15:04:05']],
        'ToggleButton' => [
            'class' => ToggleButton::class,
            'setupProperties' => ['labels' => ['Follow', 'Following'], 'class' => 'ExampleToggle'],
            'clientArgs' => [['Follow', 'Following'], 'ExampleToggle'],
        ],
    ];
}

function parityObject(array $case): HTMLObject
{
    $arguments = $case['serverArgs'] ?? (isset($case['properties']) ? [$case['properties']] : []);
    $object = (new \ReflectionClass($case['class'])) -> newInstanceArgs($arguments);

    foreach ($case['setupProperties'] ?? [] as $name => $value) {
        $property = new \ReflectionProperty($object, $name);
        $property -> setValue($object, $value);
    }

    foreach ($case['attributes'] ?? [] as $name => $value) $object -> attributes[$name] = $value;
    foreach ($case['content'] ?? [] as $content) $object -> addContent($content);

    return $object;
}

function useBareDocument(): void
{
    (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());
}

Strings::useLocale('en');
$cases = [];

foreach (parityCases() as $name => $case) {
    useBareDocument();
    $element = parityObject($case) -> toDOM();

    $cases[$name] = [
        'class' => $case['class'],
        'arguments' => $case['clientArgs'] ?? [($case['properties'] ?? null)],
        'attributes' => $case['attributes'] ?? [],
        'content' => $case['content'] ?? [],
        'canonical' => DOMCanonicalForm::lines($element),
    ];
}

echo json_encode($cases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
