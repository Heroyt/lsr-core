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
use Lsr\Core\Tools\LogoHelper;
use Lsr\Helpers\Csrf\TokenHelper;

class LatteExtension extends Extension
{

    public function __construct(
      private readonly App $app,
      private readonly TokenHelper $tokenHelper,
    ) {

    }

    /**
     * @return array<string,callable>
     */
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

    /**
     * @return array<string,callable>
     */
    public function getFilters() : array {
        return [
          'lang' => 'lang',
        ];
    }

    /**
     * @return array<string,callable>
     */
    public function getFunctions() : array {
        return [
          'csrf' => [$this->tokenHelper, 'formToken'],
          'getUrl'  => [$this->app, 'getBaseUrl'],
          'lang'    => 'lang',
          'link'    => [$this->app, 'getLink'],
          'logo'    => [LogoHelper::class, 'getLogoHtml'],
          'svgIcon' => 'svgIcon',
        ];
    }
}