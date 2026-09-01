<?php

declare(strict_types=1);

namespace Lsr\Core;

use Lsr\Interfaces\RequestInterface;

/**
 * Scalar request state that may safely cross a redirect through session flash storage.
 *
 * @internal
 */
final class PreviousRequestState
{
    public const string FLASH_KEY = 'fromRequest';

    /**
     * @return array{
     *     errors:string[],
     *     notices:array<string|array{title?:string,content:string,type?:string}>
     * }
     */
    public static function capture(RequestInterface $request): array
    {
        return [
            'errors' => $request->getPassErrors(),
            'notices' => $request->getPassNotices(),
        ];
    }

    public static function restore(RequestInterface $request, mixed $state): void
    {
        if (!is_array($state)) {
            return;
        }

        $errors = $state['errors'] ?? [];
        if (is_array($errors)) {
            foreach ($errors as $error) {
                if (is_string($error)) {
                    $request->addError($error);
                }
            }
        }

        $notices = $state['notices'] ?? [];
        if (is_array($notices)) {
            foreach ($notices as $notice) {
                if (is_string($notice)) {
                    $request->addNotice($notice);
                    continue;
                }
                if (
                    is_array($notice)
                    && isset($notice['content'])
                    && is_string($notice['content'])
                    && (!isset($notice['title']) || is_string($notice['title']))
                    && (!isset($notice['type']) || is_string($notice['type']))
                ) {
                    /** @var array{title?:string,content:string,type?:string} $notice */
                    $request->addNotice($notice);
                }
            }
        }
    }
}
