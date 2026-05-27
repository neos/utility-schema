<?php

declare(strict_types=1);

namespace Neos\Flow\Tests\Unit\Utility;

/*
 * This file is part of the Neos.Utility.Schema package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Utility\SchemaValidator;
use Neos\Error\Messages as Error;

/**
 * Testcase for the configuration validator
 */
final class SchemaValidatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SchemaValidator
     */
    protected $configurationValidator;

    protected function setUp(): void
    {
        $this->configurationValidator = $this->getMockBuilder(SchemaValidator::class)->onlyMethods([])->getMock();
    }

    /**
     * Handle the assertion that the given result object has errors
     *
     * @param Error\Result $result
     * @param boolean $expectError
     * @return void
     */
    protected function assertError(Error\Result $result, bool $expectError = true): void
    {
        if ($expectError === true) {
            self::assertTrue($result->hasErrors());
        } else {
            self::assertFalse($result->hasErrors());
        }
    }

    /**
     * Handle the assertion that the given result object has no errors
     *
     * @param Error\Result $result
     * @param boolean $expectSuccess
     * @return void
     */
    protected function assertSuccess(Error\Result $result, bool $expectSuccess = true): void
    {
        if ($expectSuccess === true) {
            self::assertFalse($result->hasErrors());
        } else {
            self::assertTrue($result->hasErrors());
        }
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesRequiredPropertyDataProvider(): \Iterator
    {
        yield [['foo' => 'a string'], true];
        yield [['foo' => 'a string', 'bar' => 'a string'], true];
        yield [['foo' => 'a string', 'bar' => 123], false];
        yield [['foo' => 'a string', 'bar' => 'a string'], true];
        yield [['foo' => 123, 'bar' => 'a string'], false];
        yield [['foo' => null, 'bar' => 'a string'], false];
        yield [['bar' => 'string'], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesRequiredPropertyDataProvider
     */
    public function validateHandlesRequiredProperty(array $value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'dictionary',
            'properties' => [
                'foo' => [
                    'type' => 'string',
                    'required' => true
                ],
                'bar' => 'string'
            ]
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesDisallowPropertyDataProvider(): \Iterator
    {
        yield ['string', true];
        yield [123, false];
        yield [[1,2,3], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesDisallowPropertyDataProvider
     */
    public function validateHandlesDisallowProperty($value, bool $expectSuccess)
    {
        $schema = [
            'disallow' => ['integer','array']
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesEnumPropertyDataProvider(): \Iterator
    {
        yield [1, true];
        yield [2, true];
        yield [null, false];
        yield [4, false];
        yield [[1,2,3], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesEnumPropertyDataProvider
     */
    public function validateHandlesEnumProperty($value, bool $expectSuccess)
    {
        $schema = [
            'enum' => [1,2,3]
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @test
     */
    public function validateReturnsErrorPath()
    {
        $value = [
            'foo' => [
                'bar' => [
                    'baz' => 'string'
                ]
            ]
        ];

        $schema = [
            'type' => 'dictionary',
            'properties' => [
                'foo' => [
                    'type' => 'dictionary',
                    'properties' => [
                        'bar' => [
                            'type' => 'dictionary',
                            'properties' => [
                                'baz' => 'number'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->configurationValidator->validate($value, $schema);
        $this->assertError($result);

        $allErrors = $result->getFlattenedErrors();
        self::assertArrayHasKey('foo.bar.baz', $allErrors);

        $pathErrors = $result->forProperty('foo.bar.baz')->getErrors();
        $firstPathError = $pathErrors[0];
        self::assertEquals(1328557141, $firstPathError->getCode());
        self::assertEquals($firstPathError->getArguments(), ['type=number', 'type=string']);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesMultipleTypesDataProvider(): \Iterator
    {
        yield [['property' => 'value'], true];
        yield ['value', true];
        yield [false, false];
        yield [123, false];
        yield [[1,2,3], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesMultipleTypesDataProvider
     */
    public function validateHandlesMultipleTypes($value, bool $expectSuccess)
    {
        $schema = ['dictionary', 'string'];

        $result = $this->configurationValidator->validate($value, $schema);
        $this->assertSuccess($result, $expectSuccess);
    }

    /**
     * @test
     * @dataProvider validateHandlesMultipleTypesDataProvider
     */
    public function validateHandlesMultipleTypesInSchemaType($value, bool $expectSuccess)
    {
        $schema = [
            'type' => ['dictionary', 'string']
        ];
        $result = $this->configurationValidator->validate($value, $schema);
        $this->assertSuccess($result, $expectSuccess);
    }

    /**
     * @test
     * @dataProvider validateHandlesMultipleTypesDataProvider
     */
    public function validateHandlesMultipleTypesInSubProperty($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'dictionary',
            'properties' => [
                'foo' => [
                    'type' => ['dictionary', 'string']
                ]
            ]
        ];
        $result = $this->configurationValidator->validate(['foo' => $value], $schema);
        $this->assertSuccess($result, $expectSuccess);
    }

    /// INTEGER ///
    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesIntegerTypePropertyDataProvider(): \Iterator
    {
        yield [23, true];
        yield ['foo', false];
        yield [23.42, false];
        yield [[], false];
        yield [null, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesIntegerTypePropertyDataProvider
     */
    public function validateHandlesIntegerTypeProperty($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'integer'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /// NUMBER ///
    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesNumberTypePropertyDataProvider(): \Iterator
    {
        yield [23.42, true];
        yield [42, true];
        yield ['foo', false];
        yield [null, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesNumberTypePropertyDataProvider
     */
    public function validateHandlesNumberTypeProperty($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'number'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesNumberTypePropertyWithMinimumAndMaximumConstraintDataProvider(): \Iterator
    {
        yield [33, true];
        yield [99, false];
        yield [1, false];
        yield [23, true];
        yield [42, true];
    }

    /**
     * @test
     * @dataProvider validateHandlesNumberTypePropertyWithMinimumAndMaximumConstraintDataProvider
     */
    public function validateHandlesNumberTypePropertyWithMinimumAndMaximumConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'number',
            'minimum' => 23,
            'maximum' => 42
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @test
     * @dataProvider validateHandlesNumberTypePropertyWithMinimumAndMaximumConstraintDataProvider
     */
    public function validateHandlesNumberTypePropertyWithNonExclusiveMinimumAndMaximumConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'number',
            'minimum' => 23,
            'exclusiveMinimum' => false,
            'maximum' => 42,
            'exclusiveMaximum' => false
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesNumberTypePropertyWithExclusiveMinimumAndMaximumConstraintDataProvider(): \Iterator
    {
        yield [10, false];
        yield [22, false];
        yield [23, true];
        yield [42, true];
        yield [43, false];
        yield [99, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesNumberTypePropertyWithExclusiveMinimumAndMaximumConstraintDataProvider
     */
    public function validateHandlesNumberTypePropertyWithExclusiveMinimumAndMaximumConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'number',
            'minimum' => 22,
            'exclusiveMinimum' => true,
            'maximum' => 43,
            'exclusiveMaximum' => true
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesNumberTypePropertyWithDivisibleByConstraintDataProvider(): \Iterator
    {
        yield [4, true];
        yield [3, false];
        yield [-3, false];
        yield [-4, true];
        yield [0, true];
    }

    /**
     * @test
     * @dataProvider validateHandlesNumberTypePropertyWithDivisibleByConstraintDataProvider
     */
    public function validateHandlesNumberTypePropertyWithDivisibleByConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'number',
            'divisibleBy' => 2
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /// STRING ///
    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyDataProvider(): \Iterator
    {
        yield ['FooBar', true];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyDataProvider
     */
    public function validateHandlesStringTypeProperty($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithPatternConstraintDataProvider(): \Iterator
    {
        yield ['12a', true];
        yield ['1236', false];
        yield ['12c', false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithPatternConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithPatternConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'pattern' => '/^[123ab]{3}$/'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithDateTimeConstraintDataProvider(): \Iterator
    {
        yield ['01:25:00', false];
        yield ['1976-04-18', false];
        yield ['1976-04-18T01:25:00+00:00', true];
        yield ['foobar', false];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithDateTimeConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithDateTimeConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'format' => 'date-time'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithFormatDateConstraintDataProvider(): \Iterator
    {
        yield ['01:25:00', false];
        yield ['1976-04-18', true];
        yield ['1976-04-18T01:25:00+00:00', false];
        yield ['foobar', false];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithFormatDateConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithFormatDateConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'format' => 'date'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithFormatTimeConstraintDataProvider(): \Iterator
    {
        yield ['01:25:00', true];
        yield ['1976-04-18', false];
        yield ['1976-04-18T01:25:00+00:00', false];
        yield ['foobar', false];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithFormatTimeConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithFormatTimeConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'format' => 'time'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithFormatUriPConstraintDataProvider(): \Iterator
    {
        yield ['http://foo.bar.de', true];
        yield ['ftp://dasdas.de/foo/bar/?asds=123&dasdasd#dasdas', true];
        yield ['foo', false];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithFormatUriPConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithFormatUriPConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'format' => 'uri'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithFormatHostnameConstraintDataProvider(): \Iterator
    {
        yield ['www.neos.io', true];
        yield ['this.is.an.invalid.hostname', false];
        yield ['foobar', false];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithFormatHostnameConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithFormatHostnameConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'format' => 'host-name'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithFormatIpv4ConstraintDataProvider(): \Iterator
    {
        yield ['2001:0db8:85a3:08d3:1319:8a2e:0370:7344', false];
        yield ['123.132.123.132', true];
        yield ['foobar', false];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithFormatIpv4ConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithFormatIpv4Constraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'format' => 'ipv4'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithFormatIpv6ConstraintDataProvider(): \Iterator
    {
        yield ['2001:0db8:85a3:08d3:1319:8a2e:0370:7344', true];
        yield ['123.132.123.132', false];
        yield ['foobar', false];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithFormatIpv6ConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithFormatIpv6Constraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'format' => 'ipv6'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithFormatIpAddressConstraintDataProvider(): \Iterator
    {
        yield ['2001:0db8:85a3:08d3:1319:8a2e:0370:7344', true];
        yield ['123.132.123.132', true];
        yield ['foobar', false];
        yield ['ab1', false];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithFormatIpAddressConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithFormatIpAddressConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'format' => 'ip-address'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithFormatClassNameConstraintDataProvider(): \Iterator
    {
        yield [SchemaValidator::class, true];
        yield ['Neos\Flow\UnknownClass', false];
        yield ['foobar', false];
        yield ['foo bar', false];
        yield ['foo/bar', false];
        yield ['flow/welcome', false];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithFormatClassNameConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithFormatClassNameConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'format' => 'class-name'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithFormatInterfaceNameConstraintDataProvider(): \Iterator
    {
        yield [\Iterator::class, true];
        yield ['\Neos\Flow\UnknownClass', false];
        yield ['foobar', false];
        yield ['foo bar', false];
        yield ['foo/bar', false];
        yield ['flow/welcome', false];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithFormatInterfaceNameConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithFormatInterfaceNameConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'format' => 'interface-name'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithMinLengthConstraintDataProvider(): \Iterator
    {
        yield ['12356', true];
        yield ['1235', true];
        yield ['123', false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithMinLengthConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithMinLengthConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'minLength' => 4
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesStringTypePropertyWithMaxLengthConstraintDataProvider(): \Iterator
    {
        yield ['123', true];
        yield ['1234', true];
        yield ['12345', false];
    }

    /**
     * @test
     * @dataProvider validateHandlesStringTypePropertyWithMaxLengthConstraintDataProvider
     */
    public function validateHandlesStringTypePropertyWithMaxLengthConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'string',
            'maxLength' => 4
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }


    /// BOOLEAN ///
    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesBooleanTypeDataProvider(): \Iterator
    {
        yield [true, true];
        yield [false, true];
        yield ['foo', false];
        yield [123, false];
        yield [12.34, false];
        yield [[1,2,3], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesBooleanTypeDataProvider
     */
    public function validateHandlesBooleanType($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'boolean',
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /// ARRAY ///
    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesArrayTypePropertyDataProvider(): \Iterator
    {
        yield [[1, 2, 3], true];
        yield ['foo', false];
        yield [['foo' => 'bar'], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesArrayTypePropertyDataProvider
     */
    public function validateHandlesArrayTypeProperty($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'array'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesArrayTypePropertyWithItemsConstraintDataProvider(): \Iterator
    {
        yield [[1, 2, 3], true];
        yield [[1, 2, 'test string'], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesArrayTypePropertyWithItemsConstraintDataProvider
     */
    public function validateHandlesArrayTypePropertyWithItemsConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'array',
            'items' => 'integer'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesArrayTypePropertyWithItemsSchemaConstraintDataProvider(): \Iterator
    {
        yield [[1, 2, 3], true];
        yield [[1, 2, 'test string'], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesArrayTypePropertyWithItemsSchemaConstraintDataProvider
     */
    public function validateHandlesArrayTypePropertyWithItemsSchemaConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'array',
            'items' => [
                'type' => 'integer'
            ]
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesArrayTypePropertyWithItemsArrayConstraintDataProvider(): \Iterator
    {
        yield [[1, 2, 'test string'], true];
        yield [[1, 2, 'test string', 1.56], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesArrayTypePropertyWithItemsArrayConstraintDataProvider
     */
    public function validateHandlesArrayTypePropertyWithItemsArrayConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'array',
            'items' => [
                ['type' => 'integer'],
                'string'
            ]
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesArrayUniqueItemsConstraintDataProvider(): \Iterator
    {
        yield [[1,2,3], true];
        yield [[1,2,1], false];
        yield [[[1,2], [1,3]], true];
        yield [[[1,2], [1,3], [1,2]], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesArrayUniqueItemsConstraintDataProvider
     */
    public function validateHandlesArrayUniqueItemsConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'array',
            'uniqueItems' => true
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /// DICTIONARY ///
    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesDictionaryTypeDataProvider(): \Iterator
    {
        yield [['A' => 1, 'B' => 2, 'C' => 3], true];
        yield [[1, 2, 3], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesDictionaryTypeDataProvider
     */
    public function validateHandlesDictionaryType($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'dictionary'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesDictionaryTypeWithPropertiesConstraintDataProvider(): \Iterator
    {
        yield [['foo' => 123, 'bar' => 'baz'], true];
        yield [['foo' => 'baz', 'bar' => 'baz'], false];
        yield [['foo' => 123, 'bar' => 123], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesDictionaryTypeWithPropertiesConstraintDataProvider
     */
    public function validateHandlesDictionaryTypeWithPropertiesConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'dictionary',
            'properties' => [
                'foo' => 'integer',
                'bar' => ['type' => 'string']
            ]
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesDictionaryTypeWithPatternPropertiesConstraintDataProvider(): \Iterator
    {
        yield [['ab1' => 'string'], true];
        yield [['bbb' => 123], false];
        yield [['ab' => 123], false];
        yield [['ad12' => 'string'], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesDictionaryTypeWithPatternPropertiesConstraintDataProvider
     */
    public function validateHandlesDictionaryTypeWithPatternPropertiesConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'dictionary',
            'patternProperties' => [
                '/^[123ab]{3}$/' => 'string'
            ],
            'additionalProperties' => false
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesDictionaryTypeWithFormatPropertiesConstraintDataProvider(): \Iterator
    {
        yield [['127.0.0.1' => 'string'], true];
        yield [['string' => 123], false];
        yield [['127.0.0.1' => 123], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesDictionaryTypeWithFormatPropertiesConstraintDataProvider
     */
    public function validateHandlesDictionaryTypeWithFormatPropertiesConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'dictionary',
            'formatProperties' => [
                'ip-address' => 'string'
            ],
            'additionalProperties' => false
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesDictionaryTypeWithAdditionalPropertyFalseConstraintDataProvider(): \Iterator
    {
        yield [['empty' => null], true];
        yield [['foo' => 123, 'bar' => 'baz'], true];
        yield [['foo' => 123, 'bar' => 'baz', 'baz' => 'blah'], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesDictionaryTypeWithAdditionalPropertyFalseConstraintDataProvider
     */
    public function validateHandlesDictionaryTypeWithAdditionalPropertyFalseConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'dictionary',
            'properties' => [
                'empty' => 'null',
                'foo' => 'integer',
                'bar' => ['type' => 'string']
            ],
            'additionalProperties' => false
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesDictionaryTypeWithAdditionalPropertySchemaConstraintDataProvider(): \Iterator
    {
        yield [['foo' => 123, 'bar' => 'baz'], true];
        yield [['foo' => 123, 'bar' => 'baz', 'baz' => 123], true];
        yield [['foo' => 123, 'bar' => 123, 'baz' => 'string'], false];
    }

    /**
     * @test
     * @dataProvider validateHandlesDictionaryTypeWithAdditionalPropertySchemaConstraintDataProvider
     */
    public function validateHandlesDictionaryTypeWithAdditionalPropertySchemaConstraint($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'dictionary',
            'properties' => [
                'foo' => 'integer',
                'bar' => ['type' => 'string']
            ],
            'additionalProperties' => 'integer'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @test
     */
    public function validateHandlesDictionaryTypeWithAdditionalPropertyTrueSchemaConstraint()
    {
        $schema = [
            'type' => 'dictionary',
            'additionalProperties' => true
        ];
        $value = [
            'foo' => 42
        ];

        $this->assertSuccess($this->configurationValidator->validate($value, $schema), true);
    }

    /// NULL ///
    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesNullTypeDataProvider(): \Iterator
    {
        yield [null, true];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesNullTypeDataProvider
     */
    public function validateHandlesNullType($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'null'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateHandlesUnknownTypeDataProvider(): \Iterator
    {
        yield [null, false];
        yield [123, false];
    }

    /**
     * @test
     * @dataProvider validateHandlesUnknownTypeDataProvider
     */
    public function validateHandlesUnknownType($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'unknown'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }


    /// ANY ///
    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateAnyTypeResultHasNoErrorsInAnyCaseDataProvider(): \Iterator
    {
        yield [23, true];
        yield [23.42, true];
        yield ['foo', true];
        yield [[1,2,3], true];
        yield [['A' => 1, 'B' => 2, 'C' => 3], true];
        yield [null, true];
    }

    /**
     * @test
     * @dataProvider validateAnyTypeResultHasNoErrorsInAnyCaseDataProvider
     */
    public function validateAnyTypeResultHasNoErrorsInAnyCase($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'any'
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /// CUSTOM ///
    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateCustomTypeResultDataProvider(): \Iterator
    {
        yield [ ['property' => ['integer_property' => 1, 'string_property' => 'string' ] ], true ];
        yield [ ['property' => ['integer_property' => 'no_integer', 'string_property' => 123 ] ], false ];
        yield [ ['property' => 'some_value' ], false ];
        yield [ ['other_property' => ['integer_property' => 1, 'string_property' => 'string' ] ], false ];
        yield [ ['other_property' => 'some_value' ], false ];
    }

    /**
     * @test
     * @dataProvider validateCustomTypeResultDataProvider
     */
    public function validateCustomTypeResult($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'dictionary',
            'properties' => [
                'property' => '@customType'
            ],
            'additionalProperties' => false,
            '@customType' => [
                'type' => 'dictionary',
                'properties' => [
                    'integer_property' => 'integer',
                    'string_property' => 'string'
                ]
            ]
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateCustomTypeWithSuperTypesDataProvider(): \Iterator
    {
        yield [ ['property' => ['supertype_property' => 1, 'type_property' => 'string' ] ], true ];
        yield [ ['property' => ['supertype_property' => 'no_integer', 'type_property' => 123 ] ], false ];
        yield [ ['property' => 'some_value' ], false ];
        yield [ ['other_property' => ['supertype_property' => 1, 'type_property' => 'string' ] ], false ];
        yield [ ['other_property' => 'some_value' ], false ];
    }

    /**
     * @test
     * @dataProvider validateCustomTypeWithSuperTypesDataProvider
     */
    public function validateCustomTypeWithSuperTypes($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'dictionary',
            'properties' => [
                'property' => '@customType'
            ],
            'additionalProperties' => false,
            '@customSuperType' => [
                'type' => 'dictionary',
                'properties' => [
                    'supertype_property' => 'integer'
                ]
            ],
            '@customType' => [
                'superTypes' => ['@customSuperType'],
                'type' => 'dictionary',
                'properties' => [
                    'type_property' => 'string'
                ]
            ]
        ];
        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }

    /**
     * @return \Iterator<(int | string), mixed>
     */
    public static function validateCustomTypeArrayDataProvider(): \Iterator
    {
        yield [ ['property' => ['custom_type_a_property' => 1]], true ];
        yield [ ['property' => ['custom_type_b_property' => 'string' ] ], true ];
        yield [ ['property' => ['custom_type_a_property' => 1, 'custom_type_b_property' => 'string' ] ], false ];
        yield [ ['property' => ['custom_type_a_property' => 'no_integer' ] ], false ];
        yield [ ['property' => ['custom_type_b_property' => 12324 ] ], false ];
    }

    /**
     * @test
     * @dataProvider validateCustomTypeArrayDataProvider
     */
    public function validateCustomTypeArray($value, bool $expectSuccess)
    {
        $schema = [
            'type' => 'dictionary',
            'properties' => [
                'property' => ['@customTypeA','@customTypeB'],
            ],
            'additionalProperties' => false,
            '@customTypeA' => [
                'type' => 'dictionary',
                'properties' => [
                    'custom_type_a_property' => 'integer'
                ],
                'additionalProperties' => false,
            ],
            '@customTypeB' => [
                'type' => 'dictionary',
                'properties' => [
                    'custom_type_b_property' => 'string'
                ],
                'additionalProperties' => false,
            ]
        ];

        $this->assertSuccess($this->configurationValidator->validate($value, $schema), $expectSuccess);
    }
}
