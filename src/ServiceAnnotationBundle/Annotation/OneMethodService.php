<?php
declare(strict_types=1);

namespace ServiceAnnotationBundle\Annotation;

/**
 * @Annotation
 * @Target({"CLASS"})
 *
 * should have only one public method
 */
class OneMethodService extends Service
{

}
