<?php
declare(strict_types=1);

namespace ServiceAnnotationBundle\Annotation;

/**
 * @Annotation
 * @Target({"CLASS"})
 *
 * autoregisters in the container
 */
class Service
{
    /**
     * @var string
     */
    public $id = null;

    /**
     * @var bool
     */
    public $autowired = true;

    /**
     * @var bool
     */
    public $autoconfigured = true;

    /**
     * @var bool
     */
    public $public = false;

    /**
     * @var bool
     */
    public $lazy = false;

    /**
     * @var boolean
     */
    public $abstract = false;

    /**
     * @var array
     */
    public $arguments = [];

    /**
     * @var array<\ServiceAnnotationBundle\Annotation\Tag>
     */
    public $tags = [];

    /**
     * @var array
     */
    public $methodCalls = [];

    /**
     * @var array
     */
    public $factory;

    /**
     * @var string
     */
    public $decorates;

    /**
     * @var array<string>
     */
    public $envs = [];

    /**
     * @var int
     */
    public $priority = 0;
}
