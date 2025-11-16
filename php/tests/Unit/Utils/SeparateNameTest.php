<?php

namespace Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use Happy\Utils\SeparateName;

class SeparateNameTest extends TestCase
{
    public function testSeparateBasicFirstAndLastName(): void
    {
        $result = SeparateName::separate('John Doe');
        $this->assertEquals(['firstName' => 'John', 'lastName' => 'Doe'], $result);
    }

    public function testHandleSingleNameWithNoLastName(): void
    {
        $result = SeparateName::separate('John');
        $this->assertEquals(['firstName' => 'John', 'lastName' => null], $result);
    }

    public function testHandleMultipleNamesPuttingEverythingAfterFirstAsLastName(): void
    {
        $result = SeparateName::separate('John William Doe Smith');
        $this->assertEquals(['firstName' => 'John', 'lastName' => 'William Doe Smith'], $result);
    }

    public function testHandleEmptyString(): void
    {
        $result = SeparateName::separate('');
        $this->assertEquals(['firstName' => null, 'lastName' => null], $result);
    }

    public function testHandleNullInput(): void
    {
        $result = SeparateName::separate(null);
        $this->assertEquals(['firstName' => null, 'lastName' => null], $result);
    }

    public function testHandleWhitespaceOnlyString(): void
    {
        $result = SeparateName::separate('   ');
        $this->assertEquals(['firstName' => null, 'lastName' => null], $result);
    }

    public function testHandleExtraSpacesBetweenNames(): void
    {
        $result = SeparateName::separate('  John    Doe  ');
        $this->assertEquals(['firstName' => 'John', 'lastName' => 'Doe'], $result);
    }

    public function testHandleNamesWithSpecialCharacters(): void
    {
        $result = SeparateName::separate('José María');
        $this->assertEquals(['firstName' => 'José', 'lastName' => 'María'], $result);
    }

    public function testHandleHyphenatedLastNames(): void
    {
        $result = SeparateName::separate('Mary Smith-Johnson');
        $this->assertEquals(['firstName' => 'Mary', 'lastName' => 'Smith-Johnson'], $result);
    }

    public function testHandleMultipleMiddleNamesAndHyphenatedLastName(): void
    {
        $result = SeparateName::separate('John Michael Robert Smith-Johnson');
        $this->assertEquals(['firstName' => 'John', 'lastName' => 'Michael Robert Smith-Johnson'], $result);
    }
}
