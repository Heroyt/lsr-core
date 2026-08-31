<?php
declare(strict_types=1);

namespace Lsr\Core\Middleware;

use Lsr\Core\App;
use Lsr\Core\Exceptions\InvalidLanguageException;
use Lsr\Core\Translations;
use Lsr\Core\Requests\Response;
use Lsr\Core\Routing\Middleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware to redirect requests with the default language prefix to the equivalent URL without the language prefix.
 */
class DefaultLanguageRedirect implements Middleware
{
    public function __construct(
      private readonly ?Translations $translations = null,
    ) {}

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface {
        $lang = $request->getAttribute('lang');
        if (!is_string($lang) || $lang === '') {
            return $handler->handle($request);
        }

        try {
            $translations = $this->translations ?? App::getInstance()->translations;
            if ($lang !== $translations->getDefaultLangId()) {
                return $handler->handle($request);
            }
        } catch (InvalidLanguageException) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        $prefix = '/'.$lang;
        if ($path !== $prefix && !str_starts_with($path, $prefix.'/')) {
            return $handler->handle($request);
        }

        $newPath = substr($path, strlen($prefix));
        if ($newPath === '') {
            $newPath = '/';
        }
        $location = $newPath;
        $query = $request->getUri()->getQuery();
        if ($query !== '') {
            $location .= '?'.$query;
        }
        if ($location === $request->getRequestTarget()) {
            return $handler->handle($request);
        }

        return Response::create(308, ['Location' => $location]);
    }
}