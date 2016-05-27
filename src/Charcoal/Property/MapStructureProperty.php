<?php

namespace Charcoal\Property;

// Dependencies from `PHP` extensions
use \PDO;

// Local namespace dependencies
use \Charcoal\Property\AbstractProperty;

/**
 * Audio Property.
 */
class MapStructureProperty extends AbstractProperty
{

    /**
     * @return string
     */
    public function type()
    {
        return 'map-structure';
    }


    /**
     * @return string
     */
    public function sqlExtra()
    {
        return '';
    }

    /**
     * @return string
     */
    public function sqlType()
    {
        return 'TEXT';
    }

    /**
     * @return integer
     */
    public function sqlPdoType()
    {
        return PDO::PARAM_STR;
    }

    /**
     * @return mixed
     */
    public function save()
    {
        return $this->val();
    }
}