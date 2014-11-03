<?php

namespace PhpAmqpLib\Tests\Unit;

use PhpAmqpLib\Wire\AMQPReader;
use PhpAmqpLib\Wire\AMQPWriter;

class WireTest extends \PHPUnit_Framework_TestCase
   {
      const LONG_RND_ITERS=100000;

      protected $is64;


      public function __construct()
         {
            parent::__construct();
            $this->is64=PHP_INT_SIZE==8;
         }



      public function testBitWriteRead()
         {
            $this->bitWriteRead(true);
            $this->bitWriteRead(false);
         }

      protected function bitWriteRead($v)
         {
            $this->writeAndRead($v, 'write_bit', 'read_bit');
         }



      public function testOctetWriteRead()
         {
            for($i=0; $i<=255; $i++) $this->octetWriteRead($i);
         }

      public function testOctetOutOfRangeLower()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->octetWriteRead(-1);
            $this->fail('Overflow not detected!');
         }

      public function testOctetOutOfRangeUpper()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->octetWriteRead(256);
            $this->fail('Overflow not detected!');
         }

      protected function octetWriteRead($v)
         {
            $this->writeAndRead($v, 'write_octet', 'read_octet');
         }



      public function testShortWriteRead()
         {
            for($i=0; $i<=65535; $i++) $this->shortWriteRead($i);
         }

      public function testShortOutOfRangeLower()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->shortWriteRead(-1);
            $this->fail('Overflow not detected!');
         }

      public function testShortOutOfRangeUpper()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->shortWriteRead(65536);
            $this->fail('Overflow not detected!');
         }

      protected function shortWriteRead($v)
         {
            $this->writeAndRead($v, 'write_short', 'read_short');
         }



      public function testLongWriteRead()
         {
            //neither rand() nor mt_rand() are able to generate values > PHP_INT_MAX
            $max=$this->is64? 4294967295 : PHP_INT_MAX;
            for($i=0; $i<self::LONG_RND_ITERS; $i++) $this->longWriteRead(mt_rand(0, $max));

            //values of interest
            $this->longWriteRead('0');
            $this->longWriteRead('1');
            $this->longWriteRead('2');

            $this->longWriteRead('2147483646');
            $this->longWriteRead('2147483647');
            $this->longWriteRead('2147483648');

            $this->longWriteRead('4294967293');
            $this->longWriteRead('4294967294');
            $this->longWriteRead('4294967295');
         }

      public function testLongOutOfRangeLower()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->longWriteRead(-1);
            $this->fail('Overflow not detected!');
         }

      public function testLongOutOfRangeUpper()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->longWriteRead('4294967296');
            $this->fail('Overflow not detected!');
         }

      protected function longWriteRead($v)
         {
            $this->writeAndRead($v, 'write_long', 'read_long');
         }



      public function testSignedLongWriteRead()
         {
            for($i=0; $i<self::LONG_RND_ITERS; $i++) $this->signedLongWriteRead(rand(-2147483648, 2147483647));

            //values of interest
            $this->signedLongWriteRead('-2147483648');
            $this->signedLongWriteRead('-2147483647');
            $this->signedLongWriteRead('-2147483646');

            $this->signedLongWriteRead('-2');
            $this->signedLongWriteRead('-1');
            $this->signedLongWriteRead('0');
            $this->signedLongWriteRead('1');
            $this->signedLongWriteRead('2');

            $this->signedLongWriteRead('2147483645');
            $this->signedLongWriteRead('2147483646');
            $this->signedLongWriteRead('2147483647');
         }

      public function testSignedLongOutOfRangeLower()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->signedLongWriteRead('-2147483649');
            $this->fail('Overflow not detected!');
         }

      public function testSignedLongOutOfRangeUpper()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->signedLongWriteRead('2147483648');
            $this->fail('Overflow not detected!');
         }

      protected function signedLongWriteRead($v)
         {
            $this->writeAndRead($v, 'write_signed_long', 'read_signed_long', true);
         }



      public function testLonglongWriteRead()
         {
            for($i=0; $i<self::LONG_RND_ITERS; $i++) $this->longlongWriteRead(mt_rand(0, PHP_INT_MAX));

            $this->longlongWriteRead('0');
            $this->longlongWriteRead('1');
            $this->longlongWriteRead('2');

            $this->longlongWriteRead('2147483646');
            $this->longlongWriteRead('2147483647');
            $this->longlongWriteRead('2147483648');

            $this->longlongWriteRead('4294967294');
            $this->longlongWriteRead('4294967295');
            $this->longlongWriteRead('4294967296');

            $this->longlongWriteRead('9223372036854775806');
            $this->longlongWriteRead('9223372036854775807');
            $this->longlongWriteRead('9223372036854775808');

            $this->longlongWriteRead('18446744073709551613');
            $this->longlongWriteRead('18446744073709551614');
            $this->longlongWriteRead('18446744073709551615');
         }

      public function testLonglongOutOfRangeLower()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->longlongWriteRead(-1);
            $this->fail('Overflow not detected!');
         }

      public function testLonglongOutOfRangeUpper()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->longlongWriteRead('18446744073709551616');
            $this->fail('Overflow not detected!');
         }

      protected function longlongWriteRead($v)
         {
            $this->writeAndRead($v, 'write_longlong', 'read_longlong');
         }



      public function testSignedLonglongWriteRead()
         {
            for($i=0; $i<self::LONG_RND_ITERS; $i++) $this->signedLonglongWriteRead(mt_rand(-PHP_INT_MAX-1, PHP_INT_MAX));

            $this->signedLonglongWriteRead('-9223372036854775808');
            $this->signedLonglongWriteRead('-9223372036854775807');
            $this->signedLonglongWriteRead('-9223372036854775806');

            $this->signedLonglongWriteRead('-4294967297');
            $this->signedLonglongWriteRead('-4294967296');
            $this->signedLonglongWriteRead('-4294967295');

            $this->signedLonglongWriteRead('-2147483649');
            $this->signedLonglongWriteRead('-2147483648');
            $this->signedLonglongWriteRead('-2147483647');

            $this->signedLonglongWriteRead('-2');
            $this->signedLonglongWriteRead('-1');
            $this->signedLonglongWriteRead('0');
            $this->signedLonglongWriteRead('1');
            $this->signedLonglongWriteRead('2');

            $this->signedLonglongWriteRead('2147483646');
            $this->signedLonglongWriteRead('2147483647');
            $this->signedLonglongWriteRead('2147483648');

            $this->signedLonglongWriteRead('4294967294');
            $this->signedLonglongWriteRead('4294967295');
            $this->signedLonglongWriteRead('4294967296');

            $this->signedLonglongWriteRead('9223372036854775805');
            $this->signedLonglongWriteRead('9223372036854775806');
            $this->signedLonglongWriteRead('9223372036854775807');
         }

      public function testSignedLonglongOutOfRangeLower()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->signedLonglongWriteRead('-9223372036854775809');
            $this->fail('Overflow not detected!');
         }

      public function testSignedLonglongOutOfRangeUpper()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->signedLonglongWriteRead('9223372036854775808');
            $this->fail('Overflow not detected!');
         }

      protected function signedLonglongWriteRead($v)
         {
            $this->writeAndRead($v, 'write_signed_longlong', 'read_signed_longlong');
         }



      public function testShortstrWriteRead()
         {
            $this->shortstrWriteRead('a');
            $this->shortstrWriteRead('üıß∑œ´®†¥¨πøˆ¨¥†®');
         }

      public function testShortstrOutOfRangeASCII()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->shortstrWriteRead(str_repeat('z', 256));
            $this->fail('Overflow not detected!');
         }

      public function testShortstrOutOfRangeUTFTwoByte()
         {
            $this->setExpectedException('PhpAmqpLib\Exception\AMQPInvalidArgumentException');
            $this->shortstrWriteRead(str_repeat("\xd0\xaf", 128));
            $this->fail('Overflow not detected!');
         }

      protected function shortstrWriteRead($v)
         {
            $this->writeAndRead($v, 'write_shortstr', 'read_shortstr');
         }



      public function testLongstrWriteRead()
         {
            $this->longstrWriteRead('a');
            $this->longstrWriteRead('üıß∑œ´®†¥¨πøˆ¨¥†®');
            $this->longstrWriteRead(
               'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz'.
               'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz'.
               'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz'
            );
         }

      protected function longstrWriteRead($v)
         {
            $this->writeAndRead($v, 'write_longstr', 'read_longstr');
         }



      protected function writeAndRead($v, $write_method, $read_method, $reflection=false)
         {
            $w=new AMQPWriter();
            if($reflection)
               {
                  $m=new \ReflectionMethod($w, $write_method);
                  $m->setAccessible(true);
                  $m->invoke($w, $v);
               }
            else $w->{$write_method}($v);

            $r=new AMQPReader($w->getvalue());
            if($reflection)
               {
                  $m=new \ReflectionMethod($r, $read_method);
                  $m->setAccessible(true);
                  $rv=$m->invoke($r);
               }
            else $rv=$r->{$read_method}();

            $this->assertEquals($v, $rv);
         }
   }
