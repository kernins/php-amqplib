<?php
namespace PhpAmqpLib\Wire;


class FormatHelper
   {
      const T_INT_LONG='I';
      const T_INT_LONG_U='i';
      const T_INT_LONGLONG='L';
      const T_INT_LONGLONG_U='l';

      const T_DECIMAL='D';
      const T_TIMESTAMP='T';
      const T_VOID='V';

      const T_BOOL='t';
      const T_BIT='b';

      const T_STRING_LONG='S';

      const T_ARRAY='A';
      const T_TABLE='F';


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
?>