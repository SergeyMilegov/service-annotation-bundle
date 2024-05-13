<?php
declare(strict_types=1);

namespace ServiceAnnotationBundle\Annotation;

/**
 * @Annotation
 * @Target({"CLASS"})
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class Service
{
    public ?string $id = null;
    public bool $autowired = true;
    public bool $autoconfigured = true;
    public bool $public = false;
    public bool $lazy = false;
    public bool $abstract = false;
    public array $arguments = [];
    public array $methodCalls = [];
    public array $factory;
    public ?string $decorates = null;
    public int $priority = 0;

    /**
     * @var array|array<\ServiceAnnotationBundle\Annotation\Tag>
     */
    public array $tags = [];

    /**
     * @var array<string>
     */
    public array $envs = [];

    public function __construct(
        $data = [],
        ?string $id = null,
        bool $autowired = true,
        bool $autoconfigured = true,
        bool $public = false,
        bool $lazy = false,
        bool $abstract = false,
        array $arguments = [],
        array $methodCalls = [],
        array $factory = [],
        string $decorates = null,
        int $priority = 0,
        array $tags = [],
        array $envs = []
    ) {
        $this->id = $data['id'] ?? $id;
        $this->autowired = $data['autowired'] ?? $autowired;
        $this->autoconfigured = $data['autoconfigured'] ?? $autoconfigured;
        $this->public = $data['public'] ?? $public;
        $this->lazy = $data['lazy'] ?? $lazy;
        $this->abstract = $data['abstract'] ?? $abstract;
        $this->arguments = $data['arguments'] ?? $arguments;
        $this->methodCalls = $data['methodCalls'] ?? $methodCalls;
        $this->factory = $data['factory'] ?? $factory;
        $this->decorates = $data['decorates'] ?? $decorates;
        $this->priority = $data['priority'] ?? $priority;
        $this->envs = $data['envs'] ?? $envs;
        $this->tags = array_map(static function ($tag) {
            if (is_array($tag)) {
                return new Tag($tag);
            }

            return $tag;
        }, $data['tags'] ?? $tags);
    }
}
