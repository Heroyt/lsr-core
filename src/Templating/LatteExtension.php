<?php

namespace Lsr\Core\Templating;

use Latte\Extension;
use Lsr\Core\App;
use Lsr\Core\Templating\Nodes\AlertNode;
use Lsr\Core\Templating\Nodes\CsrfInputNode;
use Lsr\Core\Templating\Nodes\CsrfNode;
use Lsr\Core\Templating\Nodes\DumpNode;
use Lsr\Core\Templating\Nodes\GetUrlNode;
use Lsr\Core\Templating\Nodes\IconNode;
use Lsr\Core\Templating\Nodes\LangNode;
use Lsr\Core\Templating\Nodes\LinkNode;
use Lsr\Core\Templating\Nodes\LogoNode;
use Lsr\Helpers\Tools\LogoHelper;

class LatteExtension extends Extension
{

    public function getTags() : array {
        return [
          'alert'        => [AlertNode::class, 'create'],
          'alertDanger'  => [AlertNode::class, 'createDanger'],
          'alertInfo'    => [AlertNode::class, 'createInfo'],
          'alertSuccess' => [AlertNode::class, 'createSuccess'],
          'alertWarning' => [AlertNode::class, 'createWarning'],
          'csrf'         => [CsrfNode::class, 'create'],
          'csrfInput'    => [CsrfInputNode::class, 'create'],
          'getUrl'       => [GetUrlNode::class, 'create'],
          'lang'         => [LangNode::class, 'create'],
          'link'         => [LinkNode::class, 'create'],
          'logo'         => [LogoNode::class, 'create'],
          'svgIcon'      => [IconNode::class, 'create'],
          'tracyDump'    => [DumpNode::class, 'create'],
        ];
    }

    public function getFilters() : array {
        return [
          'lang' => 'lang',
        ];
    }

    public function getFunctions() : array {
        return [
          'csrf'    => 'formToken',
          'getUrl'  => [App::class, 'getBaseUrl'],
          'lang'    => 'lang',
          'link'    => [App::class, 'getLink'],
          'logo'    => [LogoHelper::class, 'getLogoHtml'],
          'svgIcon' => 'svgIcon',
        ];
    }
}