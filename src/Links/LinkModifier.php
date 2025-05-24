<?php
declare(strict_types=1);

namespace Lsr\Core\Links;

interface LinkModifier
{

    /**
     * @param  string[]  $link
     * @return string[]
     */
    public function modifyLinkPath(array $link) : array;

}