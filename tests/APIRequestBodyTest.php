<?php

declare(strict_types=1);

final class APIRequestBodyTest extends TestCase
{
    public function testAFlatObjectIsTheOrdinaryAPIShape(): void
    {
        $this -> assertTrue(APIRequestBody::hasOnlyTopLevelFields('{"id":1,"selected":true,"query":"text","cursor":null}'));
        $this -> assertTrue(APIRequestBody::hasOnlyTopLevelFields('{}'));
        $this -> assertTrue(APIRequestBody::hasOnlyTopLevelFields(''));
    }

    public function testNestedValuesAndRootListsAreRefused(): void
    {
        $this -> assertFalse(APIRequestBody::hasOnlyTopLevelFields('{"filter":{"id":1}}'));
        $this -> assertFalse(APIRequestBody::hasOnlyTopLevelFields('{"ids":[1,2]}'));
        $this -> assertFalse(APIRequestBody::hasOnlyTopLevelFields('[{"id":1}]'));
    }

    public function testMalformedJSONRemainsForEndpointFieldValidation(): void
    {
        $this -> assertTrue(APIRequestBody::hasOnlyTopLevelFields('{'));
    }

    public function testTheSharedAPILimitIsTwentyFourKilobytes(): void
    {
        $this -> assertSame(24 * 1024, APIRequestBody::MAX_BYTES);
    }
}
