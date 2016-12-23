<?php

namespace Cvuorinen\PhpdocMarkdownPublic\Extension;

use phpDocumentor\Descriptor\ClassDescriptor;
use phpDocumentor\Descriptor\Collection;
use phpDocumentor\Descriptor\InterfaceDescriptor;
use phpDocumentor\Descriptor\MethodDescriptor;
use phpDocumentor\Descriptor\TraitDescriptor;
use Twig_Extension;
use Twig_SimpleFunction;

/**
 * Twig extension to get only the public methods from a \phpDocumentor\Descriptor\ClassDescriptor instance.
 *
 * Adds the following function to Twig:
 *
 *  publicMethods(ClassDescriptor class): MethodDescriptor[]
 */
class TwigClassPublicMethods extends Twig_Extension
{
    const NAME = 'TwigClassPublicMethods';

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return array(
            new Twig_SimpleFunction('publicMethods', array($this, 'getPublicMethods')),
            new Twig_SimpleFunction('formatSummary', array($this, 'formatSummary')),
        );
    }

    /**
     * @param ClassDescriptor $class
     *
     * @return MethodDescriptor[]
     */
    public function getPublicMethods($class)
    {
        if (!$class instanceof ClassDescriptor) {
            return [];
        }

        // Use cache
        if (isset($class->{'publicMethods'})) {
            return $class->{'publicMethods'};
        }

        $directMethods = $class->getMethods();

        /* @var MethodDescriptor $method */
        $magicMethods = $class->getMagicMethods();
        $realMagicMethods = new Collection();

        // Remove duplicated magic methods (it should not, but may occur)
        foreach ($magicMethods->getAll() as $k => $method) {
            if (!$directMethods->get($method->getName())) {
                $method->{'magic'} = true;
                $realMagicMethods->offsetSet($method->getName(), $method);
            }
        }

        // Remove duplicated inherited methods
        $inheritedMethods = $class->getInheritedMethods();
        foreach ($inheritedMethods->getAll() as $k => $method) {
            // There may be a direct method, overrides it
            if ($directMethod = $directMethods->get($method->getName())) {
                $directMethod->{'overrides'} = $method->getFullyQualifiedStructuralElementName();
                $inheritedMethods->offsetUnset($k);
            } // Or there may be a magic method, changes it's doc block
            elseif ($magicMethod = $realMagicMethods->get($method->getName())) {
                $inheritedMethods->offsetUnset($k);
                $magicMethod->{'inherits'} = $method->getFullyQualifiedStructuralElementName();
            } // Otherwise, it is really a inherited method
            else {
                $method->{'inherits'} = $method->getFullyQualifiedStructuralElementName();
            }
        }

        $methods = $directMethods->merge($inheritedMethods)->merge($realMagicMethods);

        foreach ($methods->getAll() as $method) {
            // Mark interface methods
            if ($parentMethod = $method->getInheritedElement()) {
                if (($parentClass = $parentMethod->getParent()) instanceof InterfaceDescriptor) {
                    $method->{'implementsInterface'} = $parentMethod->getFullyQualifiedStructuralElementName();
                }
            }
        }

        $class->{'publicMethods'} = array_filter(
            $methods->getAll(),
            function (MethodDescriptor $method) {
                return $method->getVisibility() === 'public';
            }
        );
        ksort($class->{'publicMethods'});
        return $class->{'publicMethods'};
    }

    /**
     * Format <code> in summary
     * @param string $summary
     * @return string
     */
    public function formatSummary($summary)
    {
        if (strpos($summary, "<code>\n") !== false) {
            $summary = str_replace([
                "<code>\n",
                "\n</code>",
            ], ["\n```php\n", "\n```"], $summary);
        }
        return $summary;
    }
}
