<?php
declare(strict_types=1);

namespace ServiceAnnotationBundle\Annotation;

/**
 * @Annotation
 */
class Tag
{
    /**
     * @Required
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    public $attributes = [];
}
