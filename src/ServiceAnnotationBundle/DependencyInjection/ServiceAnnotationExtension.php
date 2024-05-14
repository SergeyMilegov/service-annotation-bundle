<?php
declare(strict_types=1);

namespace ServiceAnnotationBundle\DependencyInjection;

use ReflectionException;
use ServiceAnnotationBundle\Annotation\SingleMethodService;
use ServiceAnnotationBundle\Annotation\Service;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\DocParser;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\DependencyInjection\Exception\RuntimeException as DiRuntimeException;

class ServiceAnnotationExtension extends Extension
{
    /**
     * @throws ReflectionException
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $this->loadServices($container);
    }

    /**
     * @throws ReflectionException
     */
    private function loadServices(ContainerBuilder $container)
    {
        $bundlesMetadata = $container->getParameter('kernel.bundles_metadata');
        $env = $container->getParameter('kernel.environment');

        $parser = new DocParser();
        $parser->setIgnoreNotImportedAnnotations(true);
        $parser->setTarget(Target::TARGET_CLASS);
        $parser->addNamespace((new ReflectionClass(Service::class))->getNamespaceName());

        $services = [];

        foreach ($bundlesMetadata as $bundleMetadata) {
            if (false !== strpos($bundleMetadata['path'], 'vendor/')) {
                continue;
            }

            $finder = new Finder();
            $finder
                ->files()
                ->name('*.php')
                ->in($bundleMetadata['path'])
                ->exclude(['tests', 'Tests', 'DependencyInjection', 'Resources'])
            ;

            /** @var SplFileInfo $file */
            foreach ($finder as $file) {
                $class = $this->getClassname($file->getRelativePathname(), $bundleMetadata['namespace']);

                try {
                    if (false === class_exists($class)) {
                        continue;
                    }
                } catch (RuntimeException $e) {
                    continue;//file without class
                }

                $reflection = new ReflectionClass($class);

                $attributes = $reflection->getAttributes(Service::class, ReflectionAttribute::IS_INSTANCEOF);
                if (count($attributes) > 0) {
                    /** @var Service $attribute */
                    $attribute = $attributes[0]->newInstance();

                    if ($attribute instanceof SingleMethodService && $this->countPublicMethods($reflection) > 1) {
                        throw new DiRuntimeException(sprintf('class %s should have only one public method', $class));
                    }

                    if (!empty($attribute->envs) && !in_array($env, $attribute->envs, true)) {
                        continue;
                    }

                    $services[$class] = $attribute;

                    continue;
                }

                $docComment = $reflection->getDocComment();
                if (false !== $docComment) {
                    $annotations = $parser->parse($docComment, 'class ' . $class);

                    if (false === $this->isService($annotations)) {
                        continue;
                    }

                    $annotation = $this->getServiceAnnotation($annotations);

                    if ($annotation instanceof SingleMethodService && $this->countPublicMethods($reflection) > 1) {
                        throw new DiRuntimeException(sprintf('class %s should have only one public method', $class));
                    }

                    if (!empty($annotation->envs) && !in_array($env, $annotation->envs, true)) {
                        continue;
                    }


                    $services[$class] = $annotation;
                }
            }
        }

        uasort($services, static function (Service $a, Service $b) {
            return $a->priority - $b->priority;
        });

        /** @var Service $service */
        foreach ($services as $class => $service) {
            $definition = new Definition($class);

            $definition->setAutowired($service->autowired);
            $definition->setAutoconfigured($service->autoconfigured);
            $definition->setLazy($service->lazy);
            $definition->setPublic($service->public);
            $definition->setAbstract($service->abstract);

            if (!empty($service->arguments)) {
                $arguments = $this->handleOldStyleServices($service->arguments);
                $arguments = $this->handleTaggedIterator($arguments);

                $definition->setArguments($arguments);
            }

            foreach ($service->tags as $tag) {
                $definition->addTag($tag->name, $tag->attributes);
            }

            if (!empty($service->methodCalls)) {
                $definition->setMethodCalls($service->methodCalls);
            }

            if (!empty($service->factory)) {
                $factory = $this->handleOldStyleServices($service->factory);
                $factory = $this->handleTaggedIterator($factory);

                $definition->setFactory($factory);
            }

            if (!empty($service->decorates)) {
                $definition->setDecoratedService($service->decorates);
            }

            $id = $service->id ?? $class;

            $container->setDefinition($id, $definition);
        }
    }

    private function handleOldStyleServices(array $arguments): array
    {
        $res = [];
        foreach ($arguments as $key => $value) {
            if (is_array($value)) {
                $res[$key] = $this->handleOldStyleServices($value);
                continue;
            }

            if (is_string($value) && 0 === strpos($value, '@')) {
                $value = new Reference(substr($value, 1));
            }

            $res[$key] = $value;
        }

        return $res;
    }

    private function handleTaggedIterator(array $arguments): array
    {
        $tagged = '!tagged ';
        $res = [];
        foreach ($arguments as $key => $value) {
            if (is_array($value)) {
                $res[$key] = $this->handleTaggedIterator($value);
                continue;
            }

            if (is_string($value) && 0 === strpos($value, $tagged)) {
                $value = new TaggedIteratorArgument(substr($value, strlen($tagged)));
            }

            $res[$key] = $value;
        }

        return $res;
    }

    private function getClassname(string $relativePathname, string $bundleNamespace): string
    {
        $class = $relativePathname;
        $class = str_replace('.php', '', $class);
        $class = $bundleNamespace.'\\'.str_replace('/', '\\', $class);

        return $class;
    }

    private function isService(array $annotations): bool
    {
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Service) {
                return true;
            }
        }

        return false;
    }

    private function getServiceAnnotation(array $annotations): Service
    {
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Service) {
                return $annotation;
            }
        }
    }

    private function countPublicMethods(ReflectionClass $reflection): int
    {
        $count = 0;
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->isConstructor()) {
                continue;
            }
            if ($method->isDestructor()) {
                continue;
            }
            $count++;
        }

        return $count;
    }
}
