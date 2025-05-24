<?php
declare(strict_types=1);

namespace Lsr\Core\Middleware;

use Lsr\Core\App;
use Lsr\Core\Exceptions\InvalidLanguageException;
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
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler) : ResponseInterface {
        // Check request for 'lang' attribute
        $lang = $request->getAttribute('lang');
        if (empty($lang)) {
            return $handler->handle($request);
        }
        // If the language is the default language, it should redirect to the equivalent URL without the language prefix.
        try {
            if ($lang === App::getInstance()->translations->getDefaultLangId()) {
                // Get the current path
                $path = $request->getUri()->getPath();
                // Remove the language prefix from the path
                $newPath = explode('/', str_replace($lang.'/', '', $path));
                // Create a new response with a 301 redirect
                return Response::create(
                  308,
                  [
                    'Location' => App::getLink($newPath),
                  ]
                );
            }
        } catch (InvalidLanguageException) {
            // Ignore
        }
        // If the language is not the default language, continue processing the request
        return $handler->handle($request);
    }
}