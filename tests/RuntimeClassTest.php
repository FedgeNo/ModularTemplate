<?php

declare(strict_types=1);

/**
 * A class an object gives itself while rendering has to survive the identity
 * chain being derived.
 *
 * deriveClassName() runs inside toDOM(), after the object has already decided
 * things about itself - a card can mark itself with a state class mid-render -
 * so assigning the chain over the property drops exactly the state the render
 * just set. Silently: the markup is still valid, the styling simply never
 * applies.
 */
class RuntimeClassTest extends TestCase
{
    private static function derived(HTMLObject $object): string
    {
        $method = new \ReflectionMethod(HTMLObject::class, 'deriveClassName');
        $method -> setAccessible(true);
        $method -> invoke($object);

        return (string) $object -> class;
    }

    public function testAStateClassSetDuringRenderSurvives(): void
    {
        $card = (new \ReflectionClass(Card::class)) -> newInstanceWithoutConstructor();
        $card -> class .= ' Own';

        $this -> assertSame('Card Own', self::derived($card));
    }

    public function testSeveralStateClassesSurviveTogether(): void
    {
        $card = (new \ReflectionClass(Card::class)) -> newInstanceWithoutConstructor();
        $card -> class .= ' Encrypted Locked';

        $this -> assertSame('Card Encrypted Locked', self::derived($card));
    }

    public function testASecondIdentityComposedAtTheCallSiteSurvives(): void
    {
        // A form can compose an extra identity onto its submit button so
        // special styling finds it.
        $button = (new \ReflectionClass(SubmitButton::class)) -> newInstanceWithoutConstructor();
        $button -> class .= ' AccountDeleteButton';

        $this -> assertSame('Button SubmitButton AccountDeleteButton', self::derived($button));
    }

    public function testAnUntouchedObjectStillGetsExactlyItsChain(): void
    {
        $card = (new \ReflectionClass(Card::class)) -> newInstanceWithoutConstructor();
        $this -> assertSame('Card', self::derived($card));

        $button = (new \ReflectionClass(SubmitButton::class)) -> newInstanceWithoutConstructor();
        $this -> assertSame('Button SubmitButton', self::derived($button));
    }

    public function testTheChainIsNotDuplicatedWhenDerivedTwice(): void
    {
        // Not a normal path - rendering twice throws - but the added-class diff
        // must not mistake the chain it just wrote for a runtime addition.
        $button = (new \ReflectionClass(SubmitButton::class)) -> newInstanceWithoutConstructor();

        self::derived($button);

        $this -> assertSame('Button SubmitButton', self::derived($button));
    }
}
