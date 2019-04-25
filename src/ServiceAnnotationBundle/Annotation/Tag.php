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

    /**
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        $this->name = $values['value'][0] ?? $values['name'];
        $this->attributes = $values['value'][1] ?? $values['attributes'] ?? [];
    }
}
