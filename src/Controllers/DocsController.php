<?php

namespace DeveoDK\CoreApiDocGenerator\Controllers;

use DeveoDK\CoreApiDocGenerator\Models\CoreApiDoc;
use DeveoDK\CoreApiDocGenerator\Services\BaseService;
use DeveoDK\CoreApiDocGenerator\Transformers\DocsTransformer;
use Illuminate\Routing\Controller;

class DocsController extends Controller
{
    /** @var BaseService */
    private $baseService;

    public function __construct(BaseService $baseService)
    {
        $this->baseService = $baseService;
    }

    public function index()
    {
        $docs = CoreApiDoc::all();
        $this->baseService->setTransformer(new DocsTransformer());
        $data = $this->baseService->transformCollection($docs);
        return response()->json($data);
    }
}
