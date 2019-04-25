<?php
declare(strict_types=1);

namespace ServiceAnnotationBundle\DependencyInjection;

use ReflectionException;
use ServiceAnnotationBundle\Annotation\SingleMethodService;
use ServiceAnnotationBundle\Annotation\Service;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\DocParser;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
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
     * {@inheritdoc}
     *
     * @throws ReflectionException
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $this->loadServices($container);
    }

    /**
     * @param ContainerBuilder $container
     *
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
                $docComment = $reflection->getDocComment();

                if (false === $docComment) {
                    continue;
                }

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

                $definition = new Definition($class);

                $definition->setAutowired($annotation->autowired);
                $definition->setAutoconfigured($annotation->autoconfigured);
                $definition->setLazy($annotation->lazy);
                $definition->setPublic($annotation->public);
                $definition->setAbstract($annotation->abstract);

                if (!empty($annotation->arguments)) {
                    $arguments = $this->handleServices($annotation->arguments);
                    $definition->setArguments($arguments);
                }

                foreach ($annotation->tags as $tag) {
                    $definition->addTag($tag->name, $tag->attributes);
                }

                if (!empty($annotation->methodCalls)) {
                    $definition->setMethodCalls($annotation->methodCalls);
                }

                if (!empty($annotation->factory)) {
                    $factory = $this->handleServices($annotation->factory);

                    $definition->setFactory($factory);
                }

                if (!empty($annotation->decorates)) {
                    $definition->setDecoratedService($annotation->decorates);
                }

                $container->setDefinition($class, $definition);
            }
        }
    }

    /**
     * handle old style services
     *
     * @param array $arguments
     *
     * @return array
     */
    private function handleServices(array $arguments): array
    {
        $res = [];
        foreach ($arguments as $key => $value) {
            if (is_array($value)) {
                $res[$key] = $this->handleServices($value);
                continue;
            }

            if (0 === strpos($value, '@')) {
                $value = new Reference(substr($value, 1));
            }

            $res[$key] = $value;
        }

        return $res;
    }

    /**
     * @param string $relativePathname
     * @param string $bundleNamespace
     *
     * @return string
     */
    private function getClassname(string $relativePathname, string $bundleNamespace): string
    {
        $class = $relativePathname;
        $class = str_replace('.php', '', $class);
        $class = $bundleNamespace.'\\'.str_replace('/', '\\', $class);

        return $class;
    }

    /**
     * @param array $annotations
     *
     * @return bool
     */
    private function isService(array $annotations): bool
    {
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Service) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $annotations
     *
     * @return Service
     */
    private function getServiceAnnotation(array $annotations): Service
    {
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Service) {
                return $annotation;
            }
        }
    }

    /**
     * @param ReflectionClass $reflection
     *
     * @return int
     */
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
