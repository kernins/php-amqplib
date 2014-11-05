<?php
namespace PhpAmqpLib\Wire;


class AbstractClient
   {
      protected $is64bits;
      protected $isLittleEndian;



      public function __construct()
         {
            $this->is64bits=PHP_INT_SIZE==8;

            $tmp=unpack('S',"\x01\x00"); //to maint 5.3 compat
            $this->isLittleEndian=$tmp[1]==1;
         }



      /**
       * Converts byte-string between native and network byte order, in both directions
       *
       * @param string $byteStr
       * @return string
       */
      protected function correctEndianness($byteStr)
         {
            return $this->isLittleEndian? strrev($byteStr) : $byteStr;
         }
   }
