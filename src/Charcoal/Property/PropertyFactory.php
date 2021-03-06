<?php

namespace Charcoal\Property;

// From 'charcoal-factory'
use Charcoal\Factory\ResolverFactory;

/**
 *
 */
class PropertyFactory extends ResolverFactory
{
    /**
     * @return string
     */
    public function baseClass()
    {
        return '\Charcoal\Property\PropertyInterface';
    }

    /**
     * @return string
     */
    public function defaultClass()
    {
        return '\Charcoal\Property\GenericProperty';
    }

    /**
     * @return string
     */
    public function resolverPrefix()
    {
        return '\Charcoal\Property';
    }

    /**
     * @return string
     */
    public function resolverSuffix()
    {
        return 'Property';
    }
}
