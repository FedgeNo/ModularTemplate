<?php

declare(strict_types=1);

/**
 * A refusal that knows which input it is about.
 *
 * A bare message leaves somebody hunting for which of five boxes it means -
 * awkward looking at the screen and hopeless not looking at it. And an
 * endpoint that answers the first fault it finds makes them press the button
 * once per mistake to discover the next one.
 */
class FieldErrorTest extends TestCase
{
    private function xpathOver(HTMLObject $object): \DOMXPath
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        HTMLObject::currentDocument() -> appendChild($object -> toDOM());

        return new \DOMXPath(HTMLObject::currentDocument());
    }

    public function testARefusalCanNameSeveralInputsAtOnce(): void
    {
        $response = JSONResponse::fieldErrors([
            'newPassword' => 'Use at least 8 characters.',
            'confirmPassword' => 'This does not match the new password.',
        ]);

        $this -> assertSame(422, $response -> statusCode);
        $this -> assertSame(2, count((array) $response -> fields));
        $this -> assertSame('Use at least 8 characters.', $response -> fields['newPassword']);
    }

    /**
     * The summary is what anything with no form to mark up falls back to, so a
     * refusal never becomes silent by naming a field.
     */
    public function testThereIsAlwaysSomethingToSayWithoutTheForm(): void
    {
        $one = JSONResponse::fieldError('email', 'Please give a valid email address.');

        $this -> assertSame('Please give a valid email address.', $one -> error);

        $several = JSONResponse::fieldErrors(['a' => 'First thing.', 'b' => 'Second thing.']);

        $this -> assertTrue(str_contains((string) $several -> error, 'First thing.'));
        $this -> assertTrue(str_contains((string) $several -> error, 'Second thing.'));
    }

    /** An ordinary refusal keeps the shape it always had. */
    public function testAnUnnamedRefusalCarriesNoFields(): void
    {
        $this -> assertNull(JSONResponse::error('Not logged in', 401) -> fields);
    }

    /**
     * The reason goes under the input and is tied to it, which is what makes a
     * screen reader read it out on reaching the box rather than leaving it to
     * be found.
     */
    public function testTheReasonIsTiedToTheInputItIsAbout(): void
    {
        $field = new InputField('email', 'Email', 'email');
        $field -> error = 'Please give a valid email address.';

        $xpath = $this -> xpathOver($field);
        $input = $xpath -> query('//input') -> item(0);
        $error = $xpath -> query('//p[contains(@class, "FieldError")]') -> item(0);

        $this -> assertNotNull($error);
        $this -> assertSame('Please give a valid email address.', $error -> textContent);
        $this -> assertSame('true', $input -> getAttribute('aria-invalid'));
        $this -> assertSame($error -> getAttribute('id'), $input -> getAttribute('aria-describedby'));
    }

    /** A field nobody refused says nothing and claims nothing. */
    public function testAnAcceptedInputIsUnmarked(): void
    {
        $xpath = $this -> xpathOver(new InputField('email', 'Email', 'email'));

        $this -> assertSame(0, $xpath -> query('//p[contains(@class, "FieldError")]') -> length);
        $this -> assertSame('', $xpath -> query('//input') -> item(0) -> getAttribute('aria-invalid'));
    }

    /** The same, for the box that takes more than a line. */
    public function testATextareaIsMarkedTheSameWay(): void
    {
        $field = new TextareaField('reason', 'Reason');
        $field -> error = 'That is too long.';

        $xpath = $this -> xpathOver($field);

        $this -> assertSame(1, $xpath -> query('//p[contains(@class, "FieldError")]') -> length);
        $this -> assertSame('true', $xpath -> query('//textarea') -> item(0) -> getAttribute('aria-invalid'));
    }
}
