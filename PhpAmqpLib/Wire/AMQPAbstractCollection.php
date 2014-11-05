<?php
namespace PhpAmqpLib\Wire;
use PhpAmqpLib\Channel\AbstractChannel, PhpAmqpLib\Exception;


/**
 * Iterator implemented for transparent integration with AMQPWriter::write_[array|table]()
 * ArrayAccess implemented for read_[array|table]()
 */
abstract class AMQPAbstractCollection implements \Iterator, \ArrayAccess
   {
      //just for convinience
      const PROTO_080=AbstractChannel::PROTO_080;
      const PROTO_091=AbstractChannel::PROTO_091;


      const T_INT_SHORTSHORT='b';   //is it a typo in https://www.rabbitmq.com/resources/specs/amqp0-9-1.pdf ?
      const T_INT_SHORTSHORT_U='B'; //all other int types uses CAPITAL letters for signed
      const T_INT_SHORT='U';
      const T_INT_SHORT_U='u';
      const T_INT_LONG='I';
      const T_INT_LONG_U='i';
      const T_INT_LONGLONG='L';
      const T_INT_LONGLONG_U='l';

      const T_DECIMAL='D';
      const T_TIMESTAMP='T';
      const T_VOID='V';

      const T_BOOL='t';

      const T_STRING_SHORT='s';
      const T_STRING_LONG='S';

      const T_ARRAY='A';
      const T_TABLE='F';


      protected $data=array();


      private static $_proto=null;

      private static $_types=array();
      private static $_types080=array( //isset() is waaay faster than in_array()
         self::T_INT_LONG     => true,
         self::T_DECIMAL      => true,
         self::T_TIMESTAMP    => true,
         self::T_STRING_LONG  => true,
         self::T_TABLE        => true
      );



      public function __construct(array $data=null)
         {
            if(!empty($data)) $this->data=$this->encodeCollection($data);
         }



      final protected function setValue($val, $type=null, $key=null)
         {
            if($val instanceof self)
               {
                  if($type && ($type!=$val->getType())) throw new Exception\AMQPInvalidArgumentException(
                     'Attempted to add instance of '.get_class($val).' representing type ['.$val->getType().'] as mismatching type ['.$type.']'
                  );
                  $type=$val->getType();
               }
            elseif($type) //ensuring data integrity and that all members are properly validated
               {
                  switch($type)
                     {
                        case self::T_ARRAY:
                           throw new Exception\AMQPInvalidArgumentException('Arrays must be passed as AMQPArray instance');
                           break;
                        case self::T_TABLE:
                           throw new Exception\AMQPInvalidArgumentException('Tables must be passed as AMQPTable instance');
                           break;
                        case self::T_DECIMAL:
                           if(!($val instanceof AMQPDecimal)) throw new Exception\AMQPInvalidArgumentException('Decimal values must be instance of AMQPDecimal');
                           break;
                     }
               }

            if($type)
               {
                  self::checkDataTypeIsSupported($type, false);
                  $val=array($type, $val);
               }
            else $val=$this->encodeValue($val);

            $key===null? $this->data[]=$val : $this->data[$key]=$val;
         }



      final public function getNativeData()
         {
            return $this->decodeCollection($this->data);
         }

      abstract public function getType();



      final protected function encodeCollection(array $val)
         {
            foreach($val as &$v) $v=$this->encodeValue($v);
            unset($v);

            return $val;
         }

      final protected function decodeCollection(array $val)
         {
            foreach($val as &$v) $v=$this->decodeValue($v[1], $v[0]);
            unset($v);

            return $val;
         }



      protected function encodeValue($val)
         {
            if(is_string($val)) $val=$this->encodeString($val);
            elseif(is_float($val)) $val=$this->encodeFloat($val);
            elseif(is_int($val)) $val=$this->encodeInt($val);
            elseif(is_bool($val)) $val=$this->encodeBool($val);
            elseif($val instanceof \DateTime) $val=array(self::T_TIMESTAMP, $val->getTimestamp());
            elseif($val instanceof AMQPDecimal) $val=array(self::T_DECIMAL, $val);
            elseif($val instanceof self)
               {
                  //avoid silent correction of strictly typed values
                  self::checkDataTypeIsSupported($val->getType(), false);
                  $val=array($val->getType(), $val);
               }
            elseif(is_array($val))
               {
                  //AMQP specs says "Field names MUST start with a letter, '$' or '#'"
                  //so beware, some servers may raise an exception with 503 code in cases when indexed array is encoded as table
                  if(self::isProto(self::PROTO_080)) $val=array(self::T_TABLE, new AMQPTable($val)); //080 soesn't support arrays, forcing table
                  elseif(empty($val) || (array_keys($val)===range(0, count($val)-1))) $val=array(self::T_ARRAY, new AMQPArray($val));
                  else $val=array(self::T_TABLE, new AMQPTable($val));
               }
            else throw new Exception\AMQPOutOfBoundsException('Encountered value of unsupported type: '.gettype($val));

            return $val;
         }

      protected function decodeValue($val, $type)
         {
            if($val instanceof self) $val=$val->getNativeData(); //covering arrays and tables
            else
               {
                  switch($type)
                     {
                        case self::T_BOOL:
                           $val=(bool)$val;
                           break;
                        case self::T_TIMESTAMP:
                           $val=\DateTime::createFromFormat('U', $val);
                           break;
                        case self::T_ARRAY:
                        case self::T_TABLE:
                           throw new Exception\AMQPLogicException(
                              'Encountered an array/table struct which is not an instance of AMQPCollection. '.
                              'This is considered a bug and should be fixed, please report'
                           );
                     }
               }
            return $val;
         }


      protected function encodeString($val)
         {
            return array(self::T_STRING_LONG, $val);
         }

      protected function encodeInt($val)
         {
            if(($val>=-2147483648) && ($val<=2147483647)) $ev=array(self::T_INT_LONG, $val);
            elseif(self::isProto(self::PROTO_080)) $ev=$this->encodeString((string)$val); //080 doesn't support longlong
            else $ev=array(self::T_INT_LONGLONG, $val);
            return $ev;
         }

      protected function encodeFloat($val)
         {
            return static::encodeString((string)$val);
         }

      protected function encodeBool($val)
         {
            $val=(bool)$val;
            return self::isProto(self::PROTO_080)? array(self::T_INT_LONG, (int)$val) : array(self::T_BOOL, $val);
         }



      final public static function getProto()
         {
            if(self::$_proto===null) self::$_proto=AbstractChannel::getProtocolVersion();
            return self::$_proto;
         }

      final public static function isProto($proto)
         {
            return self::getProto()==$proto;
         }


      /**
       * @param bool $ignoreProtoRestrictions Whether to ignore current AMQP protocol restrictions and return all known types
       * @return array  Known data types as array KEYS
       */
      final public static function getSupportedDataTypes($ignoreProtoRestrictions=false)
         {
            if(self::isProto(self::PROTO_080) && !$ignoreProtoRestrictions) $t=self::$_types080;
            else
               {
                  if(empty(self::$_types))
                     {
                        $r=new \ReflectionClass(__CLASS__);
                        foreach($r->getConstants() as $n=>$v)
                           {
                              if(preg_match('/^T_/', $n)) self::$_types[$v]=true;
                           }
                     }
                  $t=self::$_types;
               }
            return $t;
         }

      /**
       * @param string  $type
       * @param bool    $return  Whether to return or raise AMQPOutOfRangeException
       * @return boolean
       */
      final public static function checkDataTypeIsSupported($type, $return=true)
         {
            try
               {
                  $supported=self::getSupportedDataTypes(false);
                  if(!isset($supported[$type])) throw new Exception\AMQPOutOfRangeException('AMQP-'.self::getProto().' doesn\'t support data of type ['.$type.']');
                  return true;
               }
            catch(Exception\AMQPOutOfRangeException $ex)
               {
                  if($return) return false;
                  else throw $ex;
               }
         }



      public function current()
         {
            return current($this->data);
         }

      public function key()
         {
            return key($this->data);
         }

      public function next()
         {
            next($this->data);
         }

      public function rewind()
         {
            reset($this->data);
         }

      public function valid()
         {
            return key($this->data)!==null;
         }



      public function offsetExists($offset)
         {
            return array_key_exists($offset, $this->data);
         }

      public function offsetGet($offset)
         {
            return $this->data[$offset];
         }

      public function offsetSet($offset, $value)
         {
            if(!strlen($offset)) throw new Exception\AMQPInvalidArgumentException('Offset can not be empty');
            if(!is_array($value) || (count($value)!=2)) throw new Exception\AMQPInvalidArgumentException('Value must be array with exactly 2 members: valueType and valueData');
            $this->data[$offset]=$value;
         }

      public function offsetUnset($offset)
         {
            unset($this->data[$offset]);
         }
   }
