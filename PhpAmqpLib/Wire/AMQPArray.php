<?php
namespace PhpAmqpLib\Wire;


class AMQPArray extends AMQPAbstractCollection
   {
      public function __construct(array $data=null)
         {
            parent::__construct(empty($data)? null : array_values($data));
         }

      final public function getType()
         {
            return self::T_ARRAY;
         }



      public function push($val, $type=null)
         {
            $this->setValue($val, $type);
            return $this;
         }



      public function offsetSet($offset, $value)
         {
            if(!strlen($offset)) $offset=count($this->data); //$arr[]=value;
            return parent::offsetSet($offset, $value);
         }
   }
