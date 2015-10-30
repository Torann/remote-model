<?php

namespace Torann\RemoteModel\Relations;

use Torann\RemoteModel\Model;

class Relation
{
    /**
     * The parent model instance.
     *
     * @var \Torann\RemoteModel\Model
     */
    protected $parent;

    /**
     * The related model instance.
     *
     * @var \Torann\RemoteModel\Model
     */
    protected $related;

    /**
     * Create a new relation instance.
     *
     * @param  \Torann\RemoteModel\Model  $related
     * @param  \Torann\RemoteModel\Model  $parent
     * @param  string  $parentKey
     * @param  string  $localKey
     */
    public function __construct(Model $related, Model $parent, $parentKey, $localKey)
    {
        $this->related = $related;
        $this->parent = $parent;

        $this->related->setParentID($this->parent->getAttribute($localKey));
    }

    /**
     * Get the parent model of the relation.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Handle dynamic method calls to the relationship.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $result = call_user_func_array([$this->related, $method], $parameters);

        if ($result === $this->related) {
            return $this;
        }

        return $result;
    }
}