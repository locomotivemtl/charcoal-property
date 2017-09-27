<?php

namespace Charcoal\Tests\Property;

use ReflectionObject;

// From 'charcoal-property'
use Charcoal\Property\PropertyField;
use Charcoal\Property\StorablePropertyTrait;

/**
 *
 */
class StorablePropertyTraitTest extends \PHPUnit_Framework_TestCase
{
    use \Charcoal\Tests\Property\ContainerIntegrationTrait;

    private $obj;

    public function setUp()
    {
        $container = $this->getContainer();

        $this->getContainerProvider()->registerMultilingualTranslator($container);

        $this->obj = $this->createProperty();
    }

    public function createProperty()
    {
        $container = $this->getContainer();

        $obj = $this->getMockForTrait(StorablePropertyTrait::class);

        $obj->expects($this->any())
            ->method('ident')
            ->will($this->returnValue('test'));

        $obj->expects($this->any())
            ->method('translator')
            ->will($this->returnValue($container['translator']));

        $obj->expects($this->any())
            ->method('l10n')
            ->will($this->returnValue(false));

        $obj->expects($this->any())
            ->method('multiple')
            ->will($this->returnValue(false));

        return $obj;
    }

    public function createMultilingualProperty()
    {
        $container = $this->getContainer();

        $obj = $this->getMockForTrait(StorablePropertyTrait::class);

        $obj->expects($this->any())
            ->method('ident')
            ->will($this->returnValue('test'));

        $obj->expects($this->any())
            ->method('translator')
            ->will($this->returnValue($container['translator']));

        $obj->expects($this->any())
            ->method('l10nIdent')
            ->with($this->isType('string'))
            ->will($this->returnCallback(function($lang) {
                return sprintf('test_%s', $lang);
            }));

        $obj->expects($this->any())
            ->method('l10n')
            ->will($this->returnValue(true));

        $obj->expects($this->any())
            ->method('multiple')
            ->will($this->returnValue(false));

        return $obj;
    }

    public function createMultiValueProperty()
    {
        $container = $this->getContainer();

        $obj = $this->getMockForTrait(StorablePropertyTrait::class);

        $obj->expects($this->any())
            ->method('ident')
            ->will($this->returnValue('test'));

        $obj->expects($this->any())
            ->method('translator')
            ->will($this->returnValue($container['translator']));

        $obj->expects($this->any())
            ->method('l10n')
            ->will($this->returnValue(false));

        $obj->expects($this->any())
            ->method('multiple')
            ->will($this->returnValue(true));

        $obj->expects($this->any())
            ->method('multipleSeparator')
            ->will($this->returnValue(','));

        return $obj;
    }

    public function testStorageVal()
    {
        $container = $this->getContainer();

        $val = $container['translator']->translation([ 'en' => 'Cooking', 'fr' => 'Cuisson' ]);
        $ret = $this->obj->storageVal($val);
        $this->assertEquals('Cooking', $ret);

        $obj = $this->createMultiValueProperty();

        $val = [ 'foo', 'baz', 'qux' ];
        $ret = $obj->storageVal($val);
        $this->assertEquals('foo,baz,qux', $ret);

        $val = 'xyzzy';
        $ret = $obj->storageVal($val);
        $this->assertEquals('xyzzy', $ret);
    }

    /**
     * Test Unilingual Property Fields
     */
    public function testFields()
    {
        $fields = $this->obj->fields('Cooking');

        $this->assertInternalType('array', $fields);
        $this->assertCount(1, $fields);
        $this->assertInstanceOf(PropertyField::class, $fields[0]);
        $this->assertEquals('test', $fields[0]->ident());
        $this->assertEquals('Cooking', $fields[0]->val());

        $fields = $this->obj->fields([]);
        $this->assertEquals('[]', $fields[0]->val());
    }

    public function testUpdateFields()
    {
        $refObj  = new ReflectionObject($this->obj);
        $refFunc = $refObj->getMethod('updatedFields');
        $refFunc->setAccessible(true);
        $refFunc->invokeArgs($this->obj, [ 'Cooking' ]);
    }

    /**
     * Test Multilingual Property Fields
     */
    public function testMultilingualFields()
    {
        $obj = $this->createMultilingualProperty();

        $fields = $obj->fields('Cooking');

        $this->assertInternalType('array', $fields);
        $this->assertCount(4, $fields);
        $this->assertInstanceOf(PropertyField::class, $fields['en']);
        $this->assertEquals('test_en', $fields['en']->ident());
        $this->assertEquals('Cooking', $fields['en']->val());

        $fields = $obj->fields([ 'en' => 'Cooking', 'fr' => 'Cuisson' ]);

        $this->assertInstanceOf(PropertyField::class, $fields['fr']);
        $this->assertEquals('test_fr', $fields['fr']->ident());
        $this->assertEquals('Cuisson', $fields['fr']->val());

        $this->assertInstanceOf(PropertyField::class, $fields['de']);
        $this->assertEquals('test_de', $fields['de']->ident());
        $this->assertEquals(null, $fields['de']->val());

        $this->assertInstanceOf(PropertyField::class, $fields['es']);
        $this->assertEquals('test_es', $fields['es']->ident());
        $this->assertEquals(null, $fields['es']->val());
    }

    /**
     * Test Unilingual Property Field Names
     */
    public function testFieldNames()
    {
        $names = $this->obj->fieldNames();

        $this->assertInternalType('array', $names);
        $this->assertCount(1, $names);
        $this->assertInternalType('string', $names[0]);
        $this->assertEquals('test', $names[0]);
    }

    /**
     * Test Multilingual Property Field Names
     */
    public function testMultilingualFieldNames()
    {
        $obj = $this->createMultilingualProperty();

        $names = $obj->fieldNames();

        $this->assertInternalType('array', $names);
        $this->assertCount(4, $names);

        $this->assertInternalType('string', $names['en']);
        $this->assertEquals('test_en', $names['en']);

        $this->assertInternalType('string', $names['fr']);
        $this->assertEquals('test_fr', $names['fr']);

        $this->assertInternalType('string', $names['de']);
        $this->assertEquals('test_de', $names['de']);

        $this->assertInternalType('string', $names['es']);
        $this->assertEquals('test_es', $names['es']);
    }
}
