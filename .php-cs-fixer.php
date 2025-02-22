<?php

require __DIR__.'/vendor/autoload.php';
require __DIR__.'/bootstrap/app.php';

return (new \Jubeki\LaravelCodeStyle\Config())
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in(app_path())
            ->in(config_path())
            ->in(database_path())
            ->notPath(database_path('migrations'))
            ->in(resource_path('lang'))
            ->in(base_path('routes'))
            ->in(base_path('tests'))
            ->in(base_path('Blacklight'))
            ->in(base_path('misc'))
    )
    ->setRules([
        '@Laravel' => true,
    ]);
