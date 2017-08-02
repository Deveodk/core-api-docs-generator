<?php

namespace DeveoDK\CoreApiDocGenerator\Transformers;

use League\Fractal;

class ParamTransformer extends Fractal\TransformerAbstract
{
    protected $defaultIncludes = [];
    protected $availableIncludes = [];

    public function transform($data)
    {
        return [
            'title' => (string) $data->title,
            'type' => (string) $data->type,
            'example_value' => (string) $data->value,
            'default_value' => $data->default,
            'required' => (boolean) $data->required,
            'description' => $data->description,
        ];
    }
}
