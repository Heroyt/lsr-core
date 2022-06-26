<?php

namespace Lsr\Core\Templating;

use Latte\Extension;
use Lsr\Core\App;
use Lsr\Core\Templating\Nodes\AlertNode;
use Lsr\Core\Templating\Nodes\CsrfInputNode;
use Lsr\Core\Templating\Nodes\CsrfNode;
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
			'lang'         => [LangNode::class, 'create'],
			'logo'         => [LogoNode::class, 'create'],
			'link'         => [LinkNode::class, 'create'],
			'getUrl'       => [GetUrlNode::class, 'create'],
			'csrf'         => [CsrfNode::class, 'create'],
			'csrfInput'    => [CsrfInputNode::class, 'create'],
			'alert'        => [AlertNode::class, 'create'],
			'alertDanger'  => [AlertNode::class, 'createDanger'],
			'alertSuccess' => [AlertNode::class, 'createSuccess'],
			'alertWarning' => [AlertNode::class, 'createWarning'],
			'alertInfo'    => [AlertNode::class, 'createInfo'],
			'svgIcon'      => [IconNode::class, 'create'],
		];
	}

	public function getFilters() : array {
		return [
			'lang' => 'lang',
		];
	}

	public function getFunctions() : array {
		return [
			'lang'    => 'lang',
			'svgIcon' => 'svgIcon',
			'csrf'    => 'formToken',
			'logo'    => [LogoHelper::class, 'getLogoHtml'],
			'link'    => [App::class, 'getLink'],
			'getUrl'  => [App::class, 'getUrl'],
		];
	}
}