<?php

namespace App\Container\Reference;

abstract class AbstractReference
{
    private $id;

    /**
     * AbstractReference constructor.
     * @param $id
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }
}