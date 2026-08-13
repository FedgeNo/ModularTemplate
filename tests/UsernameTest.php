<?php

declare(strict_types=1);

/**
 * Users.slug is wide enough to hold a followed Fediverse account's whole
 * handle. That room exists for remote handles; a username chosen on this site
 * stays short regardless of what the column can physically store.
 */
class UsernameTest extends TestCase
{
    public function testNormalisationCapsTheLength(): void
    {
        $this -> assertSame(User::MAX_USERNAME_LENGTH, strlen(User::normaliseUsername(str_repeat('a', 200))));
    }

    public function testNormalisationLowercasesAndRemovesDisallowedCharacters(): void
    {
        $this -> assertSame('fedge', User::normaliseUsername('FEDGE!!!'));
        $this -> assertSame('bob_1', User::normaliseUsername('  Bob_1  '));
        $this -> assertSame('bob', User::normaliseUsername('bob!!!'));
        $this -> assertSame('bob___', User::normaliseUsername('bob___'));
    }

    public function testNormalisationRejectsAnEntirelyDisallowedName(): void
    {
        $this -> assertSame('', User::normaliseUsername('!!!'));
        $this -> assertSame('', User::normaliseUsername('   '));
    }

    /**
     * The sign-up field's own maxlength has to agree with what the server
     * would store, or the form would let someone type a name that is silently
     * truncated on submit.
     */
    public function testTheSignupFieldAllowsExactlyTheStorableLength(): void
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $form = (new SignupForm()) -> toDOM();
        HTMLObject::currentDocument() -> appendChild($form);

        $username = new \DOMXPath(HTMLObject::currentDocument()) -> query('.//input[@name="username"]', $form);

        $this -> assertSame(1, $username -> length);
        $this -> assertSame(User::MAX_USERNAME_LENGTH, (int) $username -> item(0) -> getAttribute('maxlength'));
    }
}
