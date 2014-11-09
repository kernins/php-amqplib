<?php
namespace PhpAmqpLib\Wire;
use PhpAmqpLib\Channel\AbstractChannel, PhpAmqpLib\Exception;


/**
 * Iterator implemented for transparent integration with AMQPWriter::write_[array|table]()
 */
abstract class AMQPAbstractCollection implements \Iterator
   {
      //protocol defines available field types and their corresponding symbols
      const PROTO_080=AbstractChannel::PROTO_080;
      const PROTO_091=AbstractChannel::PROTO_091;
      const PROTO_RBT='rabbit'; //pseudo proto


      //Abstract data types
      const T_INT_SHORTSHORT     = 1;
      const T_INT_SHORTSHORT_U   = 2;
      const T_INT_SHORT          = 3;
      const T_INT_SHORT_U        = 4;
      const T_INT_LONG           = 5;
      const T_INT_LONG_U         = 6;
      const T_INT_LONGLONG       = 7;
      const T_INT_LONGLONG_U     = 8;

      const T_DECIMAL            = 9;
      const T_TIMESTAMP          = 10;
      const T_VOID               = 11;

      const T_BOOL               = 12;

      const T_STRING_SHORT       = 13;
      const T_STRING_LONG        = 14;

      const T_ARRAY              = 15;
      const T_TABLE              = 16;


      protected $data=array();


      private static $_proto=null;

      /*
       * Field types messy mess http://www.rabbitmq.com/amqp-0-9-1-errata.html#section_3
       * Default behaviour is to use rabbitMQ compatible field-set
       * Define AMQP_STRICT_FLD_TYPES=true to use strict AMQP instead
       */
      private static $_types_080=array(
         self::T_INT_LONG           => 'I',
         self::T_DECIMAL            => 'D',
         self::T_TIMESTAMP          => 'T',
         self::T_STRING_LONG        => 'S',
         self::T_TABLE              => 'F'
      );
      private static $_types_091=array(
         self::T_INT_SHORTSHORT     =>'b',
         self::T_INT_SHORTSHORT_U   =>'B',
         self::T_INT_SHORT          =>'U',
         self::T_INT_SHORT_U        =>'u',
         self::T_INT_LONG           =>'I',
         self::T_INT_LONG_U         =>'i',
         self::T_INT_LONGLONG       =>'L',
         self::T_INT_LONGLONG_U     =>'l',
         self::T_DECIMAL            =>'D',
         self::T_TIMESTAMP          =>'T',
         self::T_VOID               =>'V',
         self::T_BOOL               =>'t',
         self::T_STRING_SHORT       =>'s',
         self::T_STRING_LONG        =>'S',
         self::T_ARRAY              =>'A',
         self::T_TABLE              =>'F'
      );
      private static $_types_rabbit=array(
         self::T_INT_SHORTSHORT     =>'b',
         self::T_INT_SHORT          =>'s',
         self::T_INT_LONG           =>'I',
         self::T_INT_LONGLONG       =>'l',
         self::T_DECIMAL            =>'D',
         self::T_TIMESTAMP          =>'T',
         self::T_VOID               =>'V',
         self::T_BOOL               =>'t',
         self::T_STRING_LONG        =>'S',
         self::T_ARRAY              =>'A',
         self::T_TABLE              =>'F'
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
            elseif(is_null($val)) $val=$this->encodeVoid();
            elseif($val instanceof \DateTime) $val=array(self::T_TIMESTAMP, $val->getTimestamp());
            elseif($val instanceof AMQPDecimal) $val=array(self::T_DECIMAL, $val);
            elseif($val instanceof self)
               {
                  //avoid silent type correction of strictly typed values
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
                        case self::T_VOID:
                           $val=null;
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

      protected function encodeVoid()
         {
            return self::isProto(self::PROTO_080)? $this->encodeString('') : array(self::T_VOID, null);
         }



      final public static function getProto()
         {
            if(self::$_proto===null)
               {
                  self::$_proto=defined('AMQP_STRICT_FLD_TYPES') && AMQP_STRICT_FLD_TYPES?
                     AbstractChannel::getProtocolVersion() :
                     self::PROTO_RBT;
               }
            return self::$_proto;
         }

      final public static function isProto($proto)
         {
            return self::getProto()==$proto;
         }


      /**
       * @return array  [dataTypeConstant => dataTypeSymbol]
       */
      final public static function getSupportedDataTypes()
         {
            switch($proto=self::getProto())
               {
                  case self::PROTO_080:
                     $types=self::$_types_080;
                     break;
                  case self::PROTO_091:
                     $types=self::$_types_091;
                     break;
                  case self::PROTO_RBT:
                     $types=self::$_types_rabbit;
                     break;
                  default:
                     throw new Exception\AMQPOutOfRangeException('Unknown protocol: '.$proto);
               }
            return $types;
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
                  $supported=self::getSupportedDataTypes();
                  if(!isset($supported[$type])) throw new Exception\AMQPOutOfRangeException('AMQP-'.self::getProto().' doesn\'t support data of type ['.$type.']');
                  return true;
               }
            catch(Exception\AMQPOutOfRangeException $ex)
               {
                  if($return) return false;
                  else throw $ex;
               }
         }


      final public static function getSymbolForDataType($t)
         {
            $types=self::getSupportedDataTypes();
            if(!isset($types[$t])) throw new Exception\AMQPOutOfRangeException('AMQP-'.self::getProto().' doesn\'t support data of type ['.$t.']');
            return $types[$t];
         }

      final public static function getDataTypeForSymbol($s)
         {
            $symbols=array_flip(self::getSupportedDataTypes());
            if(!isset($symbols[$s])) throw new Exception\AMQPOutOfRangeException('AMQP-'.self::getProto().' doesn\'t define data type symbol ['.$s.']');
            return $symbols[$s];
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
   }
