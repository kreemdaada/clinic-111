<?php

namespace App\Http\Controllers\Concerns;

use App\Support\ConfigurationReturnContext;
use Illuminate\Http\Request;

trait PreservesConfigurationReturn
{
    protected function showConfigurationBack(Request $request): bool
    {
        return ConfigurationReturnContext::isRequestActive($request);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function mergeConfigurationReturn(Request $request, array $params = []): array
    {
        return ConfigurationReturnContext::mergeFromRequest($request, $params);
    }
}
