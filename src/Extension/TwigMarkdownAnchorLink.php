<?php

namespace Cvuorinen\PhpdocMarkdownPublic\Extension;

use phpDocumentor\Descriptor\DescriptorAbstract as Descriptor;
use phpDocumentor\Descriptor\FunctionDescriptor;
use phpDocumentor\Descriptor\MethodDescriptor;
use Twig_Extension;
use Twig_SimpleFunction;

/**
 * Twig extension to create Markdown anchor links (within a single page).
 *
 * Links need to be created in the same order as the anchors appear in the document, so that links with
 * same title will be correctly suffixed with a numeric index.
 *
 * Adds the following function:
 *
 *  anchorLink(string title): string
 */
class TwigMarkdownAnchorLink extends Twig_Extension
{
    const NAME = 'TwigMarkdownAnchorLink';

    /**
     * Keep track of the created links so we can check if a link with the same title already exists.
     *
     * @var array
     */
    private static $links = [];

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
        return [
            new Twig_SimpleFunction('anchorLink', [$this, 'createAnchorLink']),
        ];
    }

    /**
     * @param Descriptor $subject
     * @param bool               $urlOnly
     * @return string
     */
    public function createAnchorLink(Descriptor $subject, $urlOnly = false)
    {
        if (!isset($subject->{'anchorLink'})) {
            if ($subject instanceof MethodDescriptor || $subject instanceof FunctionDescriptor) {
                $title = $subject->getName();
            } else {
                $title = trim($subject->getFullyQualifiedStructuralElementName(), '\\/');
            }
            $anchor = str_replace(['/', '\\', '_'], ['-', '-', '-'], strtolower($title));

            // Check if we already have link to an anchor with the same name, and add count suffix
            $linkCounts = array_count_values(self::$links);
            $anchorSuffix = array_key_exists($anchor, $linkCounts) ? '-' . $linkCounts[$anchor] : '';
            array_push(self::$links, $anchor);

            $subject->{'anchorUrl'} = $anchor . $anchorSuffix;
            $subject->{'anchorLink'} = sprintf("[%s](%s)", $title, '#' . $subject->{'anchorUrl'});
        }
        return $urlOnly ? $subject->{'anchorUrl'} : $subject->{'anchorLink'};
    }
}
