<?php

use Torann\RemoteModel\Model;
use Carbon\Carbon;

class ModelStub extends Model
{
    use TraitStub;

    protected $hidden = ['password'];

    protected $casts = [
        'age'   => 'int',
        'score' => 'float',
        'data'  => 'array',
        'active' => 'bool'
    ];

    public function __construct(array $attributes = [])
    {
        // This is needed for testing
        date_default_timezone_set('UTC');

        parent::__construct($attributes);
    }

    public function getListItemsAttribute($value)
    {
        return json_decode($value, true);
    }

    public function setListItemsAttribute($value)
    {
        $this->attributes['list_items'] = json_encode($value);
    }

    public function getTestAttribute($value)
    {
        return 'test';
    }
}