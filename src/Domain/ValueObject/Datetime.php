<?php

namespace API\Domain\ValueObject;

use API\Domain\Normalizable;
use Datetime as DatetimeBase;

class Datetime extends ValueObject
{
    protected $datetime;

    /**
     * Build new Datetime.
     */
    public function __construct(DatetimeBase $datetime = null)
    {
        $this->datetime = $datetime ?? new DatetimeBase();
    }

    /**
     * Proxy undefined calls to Datetime object.
     */
    public function __call($method, $parameters)
    {
        return \call_user_func_array([$this->getDatetime(), $method], $parameters);
    }
    
    /**
     * Direct access to Datetime.
     */
    public function getDatetime() : DatetimeBase
    {
        return $this->datetime;
    }

    /**
     * Normalize the value object into an array.
     */
    public function normalize() : array
    {
        return [
            'iso8601'    => $this->datetime->format(DatetimeBase::RFC3339_EXTENDED),
            'class_name' => \get_class($this),
        ];
    }

    /**
     * Build the value object from array.
     */
    public static function denormalize(array $data) : Normalizable
    {
        $datetime = DatetimeBase::createFromFormat('Y-m-d\TH:i:s.uP', $data['iso8601']);

        return new self($datetime);
    }
}
