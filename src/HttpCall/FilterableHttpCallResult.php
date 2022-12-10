<?php

declare(strict_types=1);

namespace Behatch\HttpCall;

interface FilterableHttpCallResult
{
    public function filter(HttpCallResult $httpCallResult);
}
