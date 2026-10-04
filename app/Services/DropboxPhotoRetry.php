<?php

namespace App\Services;

class DropboxPhotoRetry extends \RuntimeException
{
    public function __construct(public int $retryAfter)
    {
        parent::__construct('Dropbox rate limited the upload; retry after '.$retryAfter.' seconds.');
    }
}
