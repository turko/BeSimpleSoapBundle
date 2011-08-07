<?php
/*
 * This file is part of the BeSimpleSoapBundle.
 *
 * (c) Christian Kerl <christian-kerl@web.de>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace BeSimple\SoapBundle\ServiceDefinition\Strategy;

/**
 * @author Francis Besset <francis.besset@gmail.com>
 */
abstract class BaseComplexType
{
    private $name;
    private $originalName;
    private $value;
    private $isNillable = false;

    public function getName()
    {
        return $this->name;
    }

    public function getOriginalName()
    {
        return $this->originalName ?: $this->name;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function isNillable()
    {
        return $this->isNillable;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setOriginalName($originalName)
    {
        $this->originalName = $originalName;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

    public function setNillable($isNillable)
    {
        $this->isNillable = (bool) $isNillable;
    }
}