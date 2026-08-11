<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapter;

abstract class InfoCodes
{
    public const array JWT = [
        'BAD_CREDENTIALS' => 'JWTBAD0',
        'INVALID_TOKEN' => 'JWTINV0',
        'MISSING_TOKEN' => 'JWTMIS0',
        'EXPIRED_TOKEN' => 'JWTEXP0',
        'ACCOUNT_LOCKED' => 'JWTLOCK',
    ];

    public const array INTERNAL = [
        'FILE_CACHE_NO_CONTEXT' => 'IFILCC0',
        'INVALID_DATA' => 'INVDAT0',
        'INVALID_INPUT' => 'INVINP0',
        'UNSUPPORTED_OPERATION' => 'UNSOPR0',
        'DATABASE_ERROR' => 'DBERROR',
        'PROCESSOR_FAILED' => 'PRCFAIL0',
        'ENTITY_NOT_FOUND' => 'ENTNFND0',
        'OPERATION_REQUIRED' => 'OPRREQ0',
        'INVALID_DATE_FORMAT' => 'INVDATF0',
        'ERROR_SERVER' => 'ERRSER0',
        'TYPE_ERROR' => 'TYPERR0',
        'PAGE_NOT_FOUND' => 'PAGNFND0',
        'VALIDATION_ERROR' => 'VALERR0',
    ];
}
