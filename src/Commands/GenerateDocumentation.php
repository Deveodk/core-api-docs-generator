<?php

namespace DeveoDK\CoreApiDocGenerator\Commands;

use DeveoDK\CoreApiDocGenerator\Models\CoreApiDoc;
use ReflectionClass;
use Illuminate\Console\Command;
use Mpociot\Reflection\DocBlock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use DeveoDK\CoreApiDocGenerator\Postman\CollectionWriter;
use DeveoDK\CoreApiDocGenerator\Generators\LaravelGenerator;
use DeveoDK\CoreApiDocGenerator\Generators\AbstractGenerator;

class GenerateDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'core:generate 
                            {--output=public/docs : The output path for the generated documentation}
                            {--routePrefix= : The route prefix to use for generation}
                            {--routes=* : The route names to use for generation}
                            {--middleware= : The middleware to use for generation}
                            {--noResponseCalls : Disable API response calls}
                            {--noPostmanCollection : Disable Postman collection creation}
                            {--useMiddlewares : Use all configured route middlewares}
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
    protected $description = 'Generate Deveo Core API documentation from existing Laravel routes.';

    /**
     * Create a new command instance.
     *
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
            if ($item->title !== '') {
            }
            if ($apiDocExistingEntry = CoreApiDoc::where('identifier', '=', $item->id)->first()) {
                $apiDocExistingEntry->method = $item->methods[0];
                $apiDocExistingEntry->uri = $item->uri;
                $apiDocExistingEntry->title = $item->title;
                $apiDocExistingEntry->description = $item->description;
                $apiDocExistingEntry->parameters = json_encode($item->parameters);
                $apiDocExistingEntry->save();
                continue;
            }
            $apiDocEntry = new CoreApiDoc();
            $apiDocEntry->identifier = $item->id;
            $apiDocEntry->method = $item->methods[0];
            $apiDocEntry->title = $item->title;
            $apiDocEntry->description = $item->description;
            $apiDocEntry->uri = $item->uri;
            $apiDocEntry->parameters = json_encode($item->parameters);
            $apiDocEntry->save();
        }
        $postManCollection = $this->generatePostmanCollection($parsedRoutes);
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
     * @param AbstractGenerator $generator
     * @param $allowedRoutes
     * @param $routePrefix
     *
     * @param $middleware
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
