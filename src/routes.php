<?php

use DeveoDK\CoreApiDocGenerator\Controllers\DocsController;
use Illuminate\Routing\Router;

$route = app(Router::class);

$route->get('docs', DocsController::class.'@index');
