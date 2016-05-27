<?php

namespace Charcoal\Tests\Property;

use \Charcoal\Property\IdProperty;

/**
 * ## TODOs
 * - 2015-03-12:
 */
class IdPropertyTest extends \PHPUnit_Framework_TestCase
{

    public $obj;

    public function setUp()
    {
        $this->obj = new IdProperty();
    }

    public function testType()
    {
        $this->assertEquals('id', $this->obj->type());
    }


    public function testSetData()
    {
        $obj = $this->obj;
        $ret = $obj->setData(
            [
            'mode'=>'uniqid'
            ]
        );
        $this->assertSame($ret, $obj);
        $this->assertEquals('uniqid', $obj->mode());
    }

    public function testSetMode()
    {
        $obj = $this->obj;
        $this->assertEquals('auto-increment', $obj->mode());

        $ret = $obj->setMode('uuid');
        $this->assertSame($ret, $obj);
        $this->assertEquals('uuid', $obj->mode());

        $this->setExpectedException('\InvalidArgumentException');
        $obj->setMode('foo');
    }

    public function testMultipleCannotBeTrue()
    {
        $this->assertFalse($this->obj->multiple());

        $this->assertSame($this->obj, $this->obj->setMultiple(false));
        $this->setExpectedException('\InvalidArgumentException');
        $this->obj->setMultiple(true);
    }

    public function testL10nCannotBeTrue()
    {
        $this->assertFalse($this->obj->l10n());

        $this->assertSame($this->obj, $this->obj->setL10n(false));
        $this->setExpectedException('\InvalidArgumentException');
        $this->obj->setL10n(true);
    }

    public function testSaveAndAutoGenerateAutoIncrement()
    {
        $obj = $this->obj;
        $obj->setMode('auto-increment');
        $id = $obj->save();
        $this->assertEquals($id, $obj->val());
        $this->assertEquals('', $obj->val());
    }

    public function testSaveAndAutoGenerateUniqid()
    {
        $obj = $this->obj;
        $obj->setMode('uniqid');
        $id = $obj->save();
        $this->assertEquals($id, $obj->val());
        $this->assertEquals(13, strlen($obj->val()));
    }

    public function testSaveAndAutoGenerateUuid()
    {
        $obj = $this->obj;
        $obj->setMode('uuid');
        $id = $obj->save();
        $this->assertEquals($id, $obj->val());
        $this->assertEquals(36, strlen($obj->val()));
    }

    public function testSqlExtra()
    {
        $obj = $this->obj;
        $obj->setMode('auto-increment');
        $ret = $obj->sqlExtra();
        $this->assertEquals('AUTO_INCREMENT', $ret);

        $obj->setMode('uniqid');
        $ret = $obj->sqlExtra();
        $this->assertEquals('', $ret);
    }

    public function testSqlType()
    {
        $obj = $this->obj;
        $obj->setMode('auto-increment');
        $ret = $obj->sqlType();
        $this->assertEquals('INT(10) UNSIGNED', $ret);

        $obj->setMode('uniqid');
        $ret = $obj->sqlType();
        $this->assertEquals('CHAR(13)', $ret);

        $obj->setMode('uuid');
        $ret = $obj->sqlType();
        $this->assertEquals('CHAR(36)', $ret);
    }

    public function testSqlPdoType()
    {
        $obj = $this->obj;
        $obj->setMode('auto-increment');
        $ret = $obj->sqlPdoType();
        $this->assertEquals(\PDO::PARAM_INT, $ret);

        $obj->setMode('uniqid');
        $ret = $obj->sqlPdoType();
        $this->assertEquals(\PDO::PARAM_STR, $ret);

        $obj->setMode('uuid');
        $ret = $obj->sqlPdoType();
        $this->assertEquals(\PDO::PARAM_STR, $ret);
    }
}