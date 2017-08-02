<?php

namespace DeveoDK\CoreApiDocGenerator\Transformers;

use League\Fractal;

class DocsTransformer extends Fractal\TransformerAbstract
{
    protected $defaultIncludes = [
        'params'
    ];
    protected $availableIncludes = [];

    public function transform($data)
    {
        return [
            'id' => (int) $data->id,
            'method' => (string) $data->method,
            'uri' => (string) $data->uri,
            'title' => (string) $data->title,
            'description' => (string) $data->description,
            'created_at' => (object) $data->created_at,
            'updated_at' => (object) $data->updated_at
        ];
    }

    public function includeParams($data)
    {
        $decoded = json_decode($data->parameters, JSON_PRETTY_PRINT);
        if (!empty($decoded)) {
            $paramData = json_decode($data->parameters);

            foreach ($paramData as $key => $value) {
                $value->title = $key;
            }

            return $this->collection($paramData, new ParamTransformer());
        };

        return;
    }
}
