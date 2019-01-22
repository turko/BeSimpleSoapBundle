<?php

/*
 * This file is part of the BeSimpleSoapBundle.
 *
 * (c) Christian Kerl <christian-kerl@web.de>
 * (c) Francis Besset <francis.besset@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace BeSimple\SoapBundle\Tests\ServiceBinding;

use BeSimple\SoapBundle\ServiceBinding\RpcLiteralRequestMessageBinder;
use BeSimple\SoapBundle\ServiceDefinition as Definition;
use BeSimple\SoapBundle\Tests\fixtures\ServiceBinding as Fixtures;
use BeSimple\SoapBundle\Util\Collection;
use BeSimple\SoapCommon\Definition\Type\ComplexType;
use BeSimple\SoapCommon\Definition\Type\TypeRepository;
use PHPUnit\Framework\TestCase;

class RpcLiteralRequestMessageBinderTest extends TestCase
{
    /**
     * @dataProvider messageProvider
     */
    public function testProcessMessage(Definition\Method $method, array $message, array $assert)
    {
        $messageBinder = new RpcLiteralRequestMessageBinder();
        $result = $messageBinder->processMessage($method, $message, $this->getTypeRepository());

        $this->assertSame($assert, $result);
    }

    public function testProcessMessageWithComplexType()
    {
        $typeRepository = $this->addComplexTypes($this->getTypeRepository());
        $messageBinder = new RpcLiteralRequestMessageBinder();

        $method = new Definition\Method('complextype_argument', null);
        $method->addInput('foo', 'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\Foo');

        $foo = new Fixtures\Foo('foobar', 19395);
        $result = $messageBinder->processMessage(
            $method,
            [$foo],
            $typeRepository
        );

        $this->assertEquals(['foo' => $foo], $result);

        $foo1 = new Fixtures\Foo('foobar', 29291);
        $foo2 = new Fixtures\Foo('barfoo', 39392);
        $foos = new \stdClass();
        $foos->item = [$foo1, $foo2];

        $method = new Definition\Method('complextype_argument', null);
        $method->addInput('foos', 'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\Foo[]');

        $result = $messageBinder->processMessage(
            $method,
            [$foos],
            $typeRepository
        );

        $this->assertEquals(['foos' => [$foo1, $foo2]], $result);
    }

    public function testProcessMessageSoapFault()
    {
        $messageBinder = new RpcLiteralRequestMessageBinder();

        $method = new Definition\Method('complextype_argument', null);
        $method->addInput('foo', 'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\Foo');

        $foo = new Fixtures\Foo('foo', null);

        $this->expectException('SoapFault');
        $messageBinder->processMessage(
            $method,
            [$foo],
            $this->addComplexTypes($this->getTypeRepository())
        );
    }

    public function testProcessMessageWithComplexTypeReference()
    {
        $messageBinder = new RpcLiteralRequestMessageBinder();

        $method = new Definition\Method('complextype_argument', null);
        $method->addInput('foos', 'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\Foo[]');

        $foo = new Fixtures\Foo('foo', 2499104);
        $foos = new \stdClass();
        $foos->item = [$foo, $foo];

        $result = $messageBinder->processMessage(
            $method,
            [$foos],
            $this->addComplexTypes($this->getTypeRepository())
        );

        $this->assertEquals(['foos' => [$foo, $foo]], $result);
    }

    public function testProcessMessageWithComplexTypeIntoComplexType()
    {
        $messageBinder = new RpcLiteralRequestMessageBinder();

        $method = new Definition\Method('complextype_argument', null);
        $method->addInput('fooBar', 'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\FooBar');

        $foo = new Fixtures\Foo('foo', 38845);
        $bar = new Fixtures\Bar('bar', null);
        $fooBar = new Fixtures\FooBar($foo, $bar);

        $result = $messageBinder->processMessage(
            $method,
            [$fooBar],
            $this->addComplexTypes($this->getTypeRepository())
        );

        $this->assertEquals(['fooBar' => $fooBar], $result);
    }

    public function testProcessMessageComplexTypeWithArrays()
    {
        $messageBinder = new RpcLiteralRequestMessageBinder();

        $method = new Definition\Method('complextype_with_array', null);
        $method->addInput('simple_arrays', 'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\SimpleArrays');

        $array = [1, 2, 3, 4];
        $stdClass = new \stdClass();
        $stdClass->item = $array;
        $simpleArrays = new Fixtures\SimpleArrays(null, new \stdClass(), $stdClass);

        $result = $messageBinder->processMessage(
            $method,
            [$simpleArrays],
            $this->addComplexTypes($this->getTypeRepository())
        );

        $result = $result['simple_arrays'];
        $this->assertEquals(null, $result->array1);
        $this->assertEquals([], $result->getArray2());
        $this->assertEquals($array, $result->getArray3());
    }

    public function testProcessMessageWithEmptyArrayComplexType()
    {
        $messageBinder = new RpcLiteralRequestMessageBinder();

        $method = new Definition\Method('empty_array_complex_type', null);
        $method->addInput('foo', 'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\Foo[]');

        $result = $messageBinder->processMessage(
            $method,
            [new \stdClass()],
            $this->addComplexTypes($this->getTypeRepository())
        );

        $this->assertEquals(['foo' => []], $result);
    }

    public function testProccessMessagePreventInfiniteRecursion()
    {
        $messageBinder = new RpcLiteralRequestMessageBinder();

        $method = new Definition\Method('prevent_infinite_recursion', null);
        $method->addInput('foo_recursive', 'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\FooRecursive');

        $foo = new Fixtures\FooRecursive('foo', '');
        $bar = new Fixtures\BarRecursive($foo, 10394);
        $foo->bar = $bar;

        $result = $messageBinder->processMessage(
            $method,
            [$foo],
            $this->addComplexTypes($this->getTypeRepository())
        );

        $this->assertEquals(['foo_recursive' => $foo], $result);
    }

    public function messageProvider()
    {
        $messages = [];

        $messages[] = [
            new Definition\Method('no_argument', null),
            [],
            [],
        ];

        $method = new Definition\Method('string_argument', null);
        $method->addInput('foo', 'string');
        $messages[] = [
            $method,
            ['bar'],
            ['foo' => 'bar'],
        ];

        $method = new Definition\Method('string_int_arguments', null);
        $method->addInput('foo', 'string');
        $method->addInput('bar', 'int');
        $messages[] = [
            $method,
            ['test', 20],
            ['foo' => 'test', 'bar' => 20],
        ];

        $method = new Definition\Method('array_string_arguments', null);
        $method->addInput('foo', 'string[]');
        $method->addInput('bar', 'int');
        $strings = new \stdClass();
        $strings->item = ['foo', 'bar', 'barfoo'];
        $messages[] = [
            $method,
            [$strings, 4],
            ['foo' => ['foo', 'bar', 'barfoo'], 'bar' => 4],
        ];

        $method = new Definition\Method('empty_array', null);
        $method->addInput('foo', 'string[]');
        $messages[] = [
            $method,
            [new \stdClass()],
            ['foo' => []],
        ];

        return $messages;
    }

    private function getTypeRepository()
    {
        $typeRepository = new TypeRepository();
        $typeRepository->addXmlNamespace('xsd', 'http://www.w3.org/2001/XMLSchema');
        $typeRepository->addType('string', 'xsd:string');
        $typeRepository->addType('boolean', 'xsd:boolean');
        $typeRepository->addType('int', 'xsd:int');
        $typeRepository->addType('float', 'xsd:float');
        $typeRepository->addType('date', 'xsd:date');
        $typeRepository->addType('dateTime', 'xsd:dateTime');

        return $typeRepository;
    }

    private function addComplexTypes(TypeRepository $typeRepository)
    {
        $foo = new ComplexType('BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\Foo', 'Foo');
        $foo->add('foo', 'string');
        $foo->add('bar', 'int');
        $typeRepository->addComplexType($foo);

        $bar = new ComplexType('BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\Bar', 'Bar');
        $bar->add('foo', 'string');
        $bar->add('bar', 'int', true);
        $typeRepository->addComplexType($bar);

        $fooBar = new ComplexType('BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\FooBar', 'FooBar');
        $fooBar->add('foo', 'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\Foo');
        $fooBar->add('bar', 'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\Bar');
        $typeRepository->addComplexType($fooBar);

        $simpleArrays = new ComplexType(
            'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\SimpleArrays', 'SimpleArrays'
        );
        $simpleArrays->add('array1', 'string[]', true);
        $simpleArrays->add('array2', 'string[]');
        $simpleArrays->add('array3', 'string[]');
        $typeRepository->addComplexType($simpleArrays);

        $fooRecursive = new ComplexType(
            'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\FooRecursive', 'FooRecursive'
        );
        $fooRecursive->add('bar', 'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\BarRecursive');
        $typeRepository->addComplexType($fooRecursive);

        $barRecursive = new ComplexType(
            'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\BarRecursive', 'BarRecursive'
        );
        $barRecursive->add('foo', 'BeSimple\SoapBundle\Tests\fixtures\ServiceBinding\FooRecursive');
        $typeRepository->addComplexType($barRecursive);

        return $typeRepository;
    }

    private function createComplexTypeCollection(array $properties)
    {
        $collection = new Collection('getName', 'BeSimple\SoapBundle\ServiceDefinition\ComplexType');

        foreach ($properties as $property) {
            $complexType = new Definition\ComplexType();
            $complexType->setName($property[0]);
            $complexType->setValue($property[1]);

            if (isset($property[2])) {
                $complexType->setNillable($property[2]);
            }

            $collection->add($complexType);
        }

        return ['properties' => $collection];
    }
}
