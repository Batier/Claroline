<?php

namespace Claroline\CoreBundle\Library\Resource;

use Claroline\CoreBundle\Entity\Resource\AbstractResource;

class ResourceCollection
{
    private $resources;
    private $errors;
    private $attributes;

    public function __construct($resources = array(), $attributes = array())
    {
        $this->resources = $resources;
        $this->attributes = $attributes;
        $this->errors = array();
    }

    public function addResource(AbstractResource $resource)
    {
        $this->resources[] = $resource;
    }

    public function setResources($resources)
    {
        $this->resources = $resources;
    }

    public function getResources()
    {
        return $this->resources;
    }

    public function setErrors($errors)
    {
        $this->errors = $errors;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public function getAttribute($key)
    {
        return $this->attributes[$key];
    }

    public function addAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function removeAttribute($key)
    {
        unset($this->attributes[$key]);
    }

    public function getErrorsForDisplay()
    {
        $content = '';

        foreach($this->errors as $error){
            $content.="<p>{$error}</p>";
        }

        return $content;
    }
}