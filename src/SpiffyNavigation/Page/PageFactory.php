<?php

namespace SpiffyNavigation\Page;

use InvalidArgumentException;

class PageFactory
{
    protected function __construct()
    {
    }

    /**
     * Creates a page tree from an array.
     *
     * @param array $spec
     * @return Page
     * @throws InvalidArgumentException On unknown page type.
     */
    public static function create(array $spec)
    {
        $page = new Page();

        if (!isset($spec['name'])) {
            throw new InvalidArgumentException('Every page must have a name');
        }
        $page->setName($spec['name']);

        if (isset($spec['pages'])) {
            foreach($spec['pages'] as $childSpec) {
                $page->addChild(self::create($childSpec));
            }
        }

        if (isset($spec['attributes'])) {
            $page->setAttributes($spec['attributes']);
        }

        if (isset($spec['properties'])) {
            $page->setProperties($spec['properties']);
        }

        return $page;
    }
}