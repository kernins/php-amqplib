<?php

namespace PhpAmqpLib\Tests\Unit;

use PhpAmqpLib\Wire;

class AMQPCollectionTest extends \PHPUnit_Framework_TestCase
   {
      public function testEncode080()
         {
            $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTO_080);
            $a=new Wire\AMQPArray(array(1, (int)-2147483648, (int)2147483647, -2147483649, 2147483648, true, false, array('foo'=>'bar'), array('foo'), array()));

            $this->assertEquals(
               array(
                  array('I', 1),
                  array('I', -2147483648),
                  array('I', 2147483647),
                  array('S', -2147483649),
                  array('S', 2147483648),
                  array('I', 1),
                  array('I', 0),
                  array('F', array('foo'=>array('S', 'bar'))),
                  array('F', array(0=>array('S', 'foo'))),
                  array('F', array())
               ),
               $this->getEncodedRawData($a)
            );

            $eData=$this->getEncodedRawData($a, false);
            $this->assertEquals($eData[7][1] instanceof Wire\AMQPTable, true);
            $this->assertEquals($eData[8][1] instanceof Wire\AMQPTable, true);
            $this->assertEquals($eData[9][1] instanceof Wire\AMQPTable, true);
         }

      public function testEncode091()
         {
            $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTO_091);
            $a=new Wire\AMQPArray(array(1, (int)-2147483648, (int)2147483647, -2147483649, 2147483648, true, false, array('foo'=>'bar'), array('foo'), array()));

            $is64=PHP_INT_SIZE==8;
            $this->assertEquals(
               array(
                  array('I', 1),
                  array('I', -2147483648),
                  array('I', 2147483647),
                  array($is64? 'L':'S', -2147483649),
                  array($is64? 'L':'S', 2147483648),
                  array('t', true),
                  array('t', false),
                  array('F', array('foo'=>array('S', 'bar'))),
                  array('A', array(array('S', 'foo'))),
                  array('A', array())
               ),
               $this->getEncodedRawData($a)
            );

            $eData=$this->getEncodedRawData($a, false);
            $this->assertEquals($eData[7][1] instanceof Wire\AMQPTable, true);
            $this->assertEquals($eData[8][1] instanceof Wire\AMQPArray, true);
            $this->assertEquals($eData[9][1] instanceof Wire\AMQPArray, true);
         }

      public function testEncodeUnknownDatatype()
         {
            $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPOutOfBoundsException');
            $a=new Wire\AMQPArray(array(new \stdClass()));
            $this->fail('Unknown data type not detected!');
         }



      public function testPushUnsupportedDataType080()
         {
            $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTO_080);

            $a=new Wire\AMQPArray();
            $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPOutOfRangeException');
            $a->push(12345, Wire\AMQPArray::T_INT_LONGLONG);
            $this->fail('Unsupported data type not detected!');
         }

      public function testPushUnsupportedDataType091()
         {
            $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTO_091);

            $a=new Wire\AMQPArray();
            $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPOutOfRangeException');
            $a->push(12345, 'foo');
            $this->fail('Unsupported data type not detected!');
         }



      public function testPushWithType()
         {
            $a=new Wire\AMQPArray();
            $a->push(576, Wire\AMQPArray::T_INT_LONG);
            $a->push('foo', Wire\AMQPArray::T_STRING_LONG);
            $a->push(new Wire\AMQPTable(array('foo'=>'bar')));
            $a->push(new Wire\AMQPArray(array('bar', 'baz')));

            $this->assertEquals(
               array(
                  array('I', 576),
                  array('S', 'foo'),
                  array('F', array('foo'=>array('S', 'bar'))),
                  array('A', array(array('S', 'bar'), array('S', 'baz')))
               ),
               $this->getEncodedRawData($a)
            );
         }


      public function testSetEmptyKey()
         {
            $t=new Wire\AMQPTable();

            $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException', 'Table key must be non-empty string up to 128 chars in length');
            $t->set('', 'foo');
            $this->fail('Empty table key not detected!');
         }

      public function testSetLongKey()
         {
            $t=new Wire\AMQPTable();
            $t->set(str_repeat('a', 128), 'foo');

            $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException', 'Table key must be non-empty string up to 128 chars in length');
            $t->set(str_repeat('a', 129), 'bar');
            $this->fail('Excessive key length not detected!');
         }


      public function testPushMismatchedType()
         {
            $a=new Wire\AMQPArray();
            $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException');
            $a->push(new Wire\AMQPArray(), Wire\AMQPArray::T_TABLE);
            $this->fail('Mismatched data type not detected!');
         }

      public function testPushRawArrayWithType()
         {
            $a=new Wire\AMQPArray();
            $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException', 'Arrays must be passed as AMQPArray instance');
            $a->push(array(), Wire\AMQPArray::T_ARRAY);
            $this->fail('Raw array data not detected!');
         }

      public function testPushRawTableWithType()
         {
            $a=new Wire\AMQPArray();
            $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException', 'Tables must be passed as AMQPTable instance');
            $a->push(array(), Wire\AMQPArray::T_TABLE);
            $this->fail('Raw table data not detected!');
         }

      public function testPushFloatWithDecimalType()
         {
            $a=new Wire\AMQPArray();
            $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException', 'Decimal values must be instance of AMQPDecimal');
            $a->push(35.2, Wire\AMQPArray::T_DECIMAL);
            $this->fail('Wrong decimal data not detected!');
         }



      public function testArrayRoundTrip080()
         {
            $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTO_080);

            $d=$this->getTestData();
            $a=new Wire\AMQPArray($d);
            $this->assertEquals($a->getNativeData(), array_values($d));
         }

      public function testArrayRoundTrip091()
         {
            $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTO_091);

            $d=$this->getTestData();
            $a=new Wire\AMQPArray($d);
            $this->assertEquals($a->getNativeData(), array_values($d));
         }

      public function testTableRoundTrip080()
         {
            $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTO_080);

            $d=$this->getTestData();
            $a=new Wire\AMQPTable($d);
            $this->assertEquals($a->getNativeData(), $d);
         }

      public function testTableRoundTrip091()
         {
            $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTO_091);

            $d=$this->getTestData();
            $a=new Wire\AMQPTable($d);
            $this->assertEquals($a->getNativeData(), $d);
         }

      protected function getTestData()
         {
            return array(
               'long' => 12345,
               'long_neg' => -12345,
               'longlong' => 3000000000,
               'longlong_neg' => -3000000000,
               'float_low' => 9.2233720368548,
               'float_high' => (float)9223372036854800000,
               'datetime' => new \DateTime(),
               'bool_true' => true,
               'bool_false' => false,
               'array' => array(1, 2, 3, 'foo', array('bar'=>'baz'), array('boo', false, 5), true),
               'array_empty' => array(),
               'table' => array('foo'=>'bar', 'baz'=>'boo', 'date'=>new \DateTime(), 'bool'=>true, 'tbl'=>array('bar'=>'baz'), 'arr'=>array('boo', false, 5)),
               'table_num' => array(1=>5, 3=>'foo', 786=>674),
               'array_nested' => array(1, array(2, array(3, array(4)))),
               'table_nested' => array('i'=>1, 'n'=>array('i'=>2, 'n'=>array('i'=>3, 'n'=>array('i'=>4))))
            );
         }



      public function testIteratorAndArrayAccess()
         {
            $d=array('a'=>1, 'b'=>-2147, 'c'=>array('foo'=>'bar'), 'd'=>true, 'e'=>false);
            $ed=array(
               'a'=>array('I', 1),
               'b'=>array('I', -2147),
               'c'=>array('F', array('foo'=>array('S', 'bar'))),
               'd'=>array('t', true),
               'e'=>array('t', false)
            );

            $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTO_091);
            $a=new Wire\AMQPTable($d);

            foreach($a as $key=>$val)
               {
                  if(!isset($d[$key])) $this->fail('Unknown key: '.$key);
                  $this->assertEquals($ed[$key], $val[1] instanceof Wire\AMQPAbstractCollection? array($val[0], $this->getEncodedRawData($val[1])) : $val);
               }

            $this->assertEquals(isset($a['a'], $a['c'], $a['e']), true);
            $this->assertEquals(isset($a['nonexistent']), false);

            $this->assertEquals(empty($a['d']), false);
            $this->assertEquals(empty($a['nonexistent']), true);

            $this->assertEquals($a['a'], array('I', 1));

            $a['foo']=array('I', 1);
            $this->assertEquals($a['foo'], array('I', 1));
         }

      public function testArrayAccessOffsetSetInvalidValue()
         {
            $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTO_091);
            $a=new Wire\AMQPTable();

            $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException', 'Value must be array with exactly 2 members: valueType and valueData');
            $a['foo']='bar';
            $this->fail('ArrayAccess offsetSet invalid value not detected!');
         }

      public function testTableArrayAccessOffsetSetEmptyKey()
         {
            $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTO_091);
            $a=new Wire\AMQPTable();

            $this->setExpectedException('PhpAmqpLib\\Exception\\AMQPInvalidArgumentException', 'Offset can not be empty');
            $a[]=array('I', 1);
            $this->fail('Table ArrayAccess offsetSet empty offset not detected!');
         }

      public function testArrayArrayAccessOffsetSetEmptyKey()
         {
            $this->setProtoVersion(Wire\AMQPAbstractCollection::PROTO_091);
            $a=new Wire\AMQPArray();

            $a[]=array('I', 1);
            $a[]=array('I', 2);
            $a[]=array('I', 3);

            $this->assertEquals($a[0], array('I', 1));
            $this->assertEquals($a[1], array('I', 2));
            $this->assertEquals($a[2], array('I', 3));
         }



      protected function setProtoVersion($proto)
         {
            $r=new \ReflectionProperty('\\PhpAmqpLib\\Wire\\AMQPAbstractCollection', '_proto');
            $r->setAccessible(true); $r->setValue(null, $proto);
         }

      protected function getEncodedRawData(Wire\AMQPAbstractCollection $c, $recursive=true)
         {
            $r=new \ReflectionProperty($c, 'data');
            $r->setAccessible(true); $data=$r->getValue($c);
            unset($r);

            if($recursive)
               {
                  foreach($data as &$v)
                     {
                        if($v[1] instanceof Wire\AMQPAbstractCollection) $v[1]=$this->getEncodedRawData($v[1]);
                     }
                  unset($v);
               }

            return $data;
         }
   }
