<?php

declare(strict_types=1);

namespace Behatch\HttpCall;

interface ContextSupportedVoter
{
    public function vote(HttpCallResult $httpCallResult);
}
