<?php

namespace Armincms\Json;
 
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Support\DeferrableProvider;

class FieldServiceProvider extends ServiceProvider implements DeferrableProvider
{   
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }
}
