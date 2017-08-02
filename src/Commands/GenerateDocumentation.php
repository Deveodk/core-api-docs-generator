<?php

namespace DeveoDK\CoreApiDocGenerator\Commands;

use ReflectionClass;
use Illuminate\Console\Command;
use Mpociot\Reflection\DocBlock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Mpociot\Documentarian\Documentarian;
use DeveoDK\CoreApiDocGenerator\Adapters\Postman\CollectionWriter;
use DeveoDK\CoreApiDocGenerator\Adapters\Generators\DingoGenerator;
use DeveoDK\CoreApiDocGenerator\Adapters\Generators\LaravelGenerator;
use DeveoDK\CoreApiDocGenerator\Adapters\Generators\AbstractGenerator;

class GenerateDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:generate 
                            {--output=public/docs : The output path for the generated documentation}
                            {--routePrefix= : The route prefix to use for generation}
                            {--routes=* : The route names to use for generation}
                            {--middleware= : The middleware to use for generation}
                            {--noResponseCalls : Disable API response calls}
                            {--noPostmanCollection : Disable Postman collection creation}
                            {--useMiddlewares : Use all configured route middlewares}
                            {--actAsUserId= : The user ID to use for API response calls}
                            {--router=laravel : The router to be used (Laravel or Dingo)}
                            {--force : Force rewriting of existing routes}
                            {--bindings= : Route Model Bindings}
                            {--header=* : Custom HTTP headers to add to the example requests. Separate the header name and value with ":"}
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate your API documentation from existing Laravel routes.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return false|null
     */
    public function handle()
    {
        $generator = new LaravelGenerator();

        $allowedRoutes = $this->option('routes');
        $routePrefix = $this->option('routePrefix');
        $middleware = $this->option('middleware');

        $this->setUserToBeImpersonated($this->option('actAsUserId'));

        if ($routePrefix === null && ! count($allowedRoutes) && $middleware === null) {
            $this->error(
                'You must provide either a route prefix or a route or a middleware to generate the documentation.'
            );

            return false;
        }

        $generator->prepareMiddleware($this->option('useMiddlewares'));

        $parsedRoutes = $this->processLaravelRoutes($generator, $allowedRoutes, $routePrefix, $middleware);

        $parsedRoutes = collect($parsedRoutes)->groupBy('resource')->sort(function ($a, $b) {
            return strcmp($a->first()['resource'], $b->first()['resource']);
        });

        $this->saveDocumentation($parsedRoutes);
    }

    /**
     * Save documentation to database
     * @param  Collection $parsedRoutes
     *
     */
    private function saveDocumentation($parsedRoutes)
    {
        foreach (json_decode($parsedRoutes->get('general')) as $item) {
            dd($item);
        }
        $postManCollection = $this->generatePostmanCollection($parsedRoutes);
    }

    /**
     * @param $actAs
     */
    private function setUserToBeImpersonated($actAs)
    {
        if (! empty($actAs)) {
            if (version_compare($this->laravel->version(), '5.2.0', '<')) {
                $userModel = config('auth.model');
                $user = $userModel::find((int) $actAs);
                $this->laravel['auth']->setUser($user);
            } else {
                $userModel = config('auth.providers.users.model');
                $user = $userModel::find((int) $actAs);
                $this->laravel['auth']->guard()->setUser($user);
            }
        }
    }

    /**
     * @return mixed
     */
    private function getRoutes()
    {
        return Route::getRoutes();
    }

    /**
     * @return array
     */
    private function getBindings()
    {
        $bindings = $this->option('bindings');

        if (empty($bindings)) {
            return [];
        }
        $bindings = explode('|', $bindings);
        $resultBindings = [];
        foreach ($bindings as $binding) {
            list($name, $id) = explode(',', $binding);
            $resultBindings[$name] = $id;
        }
        return $resultBindings;
    }

    /**
     * @param AbstractGenerator  $generator
     * @param $allowedRoutes
     * @param $routePrefix
     *
     * @return array
     */
    private function processLaravelRoutes(AbstractGenerator $generator, $allowedRoutes, $routePrefix, $middleware)
    {
        $withResponse = $this->option('noResponseCalls') === false;
        $routes = $this->getRoutes();
        $bindings = $this->getBindings();
        $parsedRoutes = [];
        foreach ($routes as $route) {
            if (in_array($route->getName(), $allowedRoutes)
                || str_is($routePrefix, $generator->getUri($route)) || in_array($middleware, $route->middleware())) {
                if ($this->isValidRoute($route) && $this->isRouteVisibleForDocumentation($route->getAction()['uses'])) {
                    $parsedRoutes[] =
                        $generator->processRoute($route, $bindings, $this->option('header'), $withResponse);
                    $this->info(
                        'Processed route: ['.implode(',', $generator->getMethods($route)).'] '.
                        $generator->getUri($route));
                } else {
                    $this->warn(
                        'Skipping route: ['.implode(',', $generator->getMethods($route)).'] '
                        .$generator->getUri($route));
                }
            }
        }

        return $parsedRoutes;
    }

    /**
     * @param $route
     *
     * @return bool
     */
    private function isValidRoute($route)
    {
        return ! is_callable($route->getAction()['uses']) && ! is_null($route->getAction()['uses']);
    }

    /**
     * @param $route
     *
     * @return bool
     */
    private function isRouteVisibleForDocumentation($route)
    {
        list($class, $method) = explode('@', $route);
        $reflection = new ReflectionClass($class);
        $comment = $reflection->getMethod($method)->getDocComment();
        if ($comment) {
            $phpdoc = new DocBlock($comment);

            return collect($phpdoc->getTags())
                ->filter(function ($tag) use ($route) {
                    return $tag->getName() === 'hideFromAPIDocumentation';
                })
                ->isEmpty();
        }

        return true;
    }

    /**
     * Generate Postman collection JSON file.
     *
     * @param Collection $routes
     *
     * @return string
     */
    private function generatePostmanCollection(Collection $routes)
    {
        $writer = new CollectionWriter($routes);

        return $writer->getCollection();
    }
}
