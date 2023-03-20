<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use ShopGenerator\DefaultDataParser;

/**
 * @covers \ShopGenerator\DefaultDataParser
 */
class DefaultDataParserTest extends TestCase
{
    public function testDefaultDataParser(): void
    {
        $parser = new DefaultDataParser();

        $expected = [
            'fields' => [
                '@id' => 'name',
                '@class' => 'Gender',
                '@image' => 'genders',
                'field' => [
                    ['@name' => 'type', '#' => ''],
                ],
            ],
            'entities' => [
                'gender' => [
                    ['@id' => 'Mr', '@type' => 0, '#' => ''],
                    ['@id' => 'Mrs', '@type' => 1, '#' => ''],
                ]
            ]
        ];

        self::assertSame($expected, $parser->parseFile(__DIR__. '/fashion/gender.xml'));
    }
}
