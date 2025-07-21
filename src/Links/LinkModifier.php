<?php
declare(strict_types=1);

namespace Lsr\Core\Links;

interface LinkModifier
{

    /**
     * @param  LinkArray  $link
     * @return LinkArray
     */
    public function modifyLinkPath(array $link) : array;

}