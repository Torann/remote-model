<?php

namespace Torann\RemoteModel;

use DateTime;
use ArrayAccess;
use JsonSerializable;
use Jenssegers\Date\Date;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class Model implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
{
    /**
     * The client associated with the model.
     *
     * @var object
     */
    protected static $client;

    /**
     * The endpoint associated with the model.
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The key value of the parent model.
     *
     * @var string
     */
    protected static $parent_id;

    /**
     * The attributes that should be hidden from API update requests.
     *
     * @var array
     */
    protected $hiddenKeys = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be visible in arrays.
     *
     * @var array
     */
    protected $visible = [];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'createdAt',
        'updatedAt'
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Format to use for __toString method when type juggling occurs.
     *
     * @var string
     */
    protected static $toStringFormat = 'Y-m-d\TH:i:s\Z';

    /**
     * Indicates whether attributes are snake cased on arrays.
     *
     * @var bool
     */
    public static $snakeAttributes = true;

    /**
     * The array of booted models.
     *
     * @var array
     */
    protected static $booted = [];

    /**
     * The cache of the mutated attributes for each class.
     *
     * @var array
     */
    protected static $mutatorCache = [];

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * All of the registered errors.
     *
     * @var array
     */
    private $messageBag;

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array $attributes
     * @parem string  $parentID
     */
    public function __construct(array $attributes = [], $parentID = null)
    {
        // Set the default date format
        Date::setToStringFormat(self::$toStringFormat);

        $this->bootIfNotBooted();

        $this->fill($attributes);

        $this->setParentID($parentID);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        $class = get_class($this);

        if (!isset(static::$booted[$class])) {
            static::$booted[$class] = true;

            $this->fireModelEvent('booting', false);

            static::boot();

            $this->fireModelEvent('booted', false);
        }
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        if (function_exists('class_uses_recursive')) {
            static::bootTraits();
        }
    }

    /**
     * Boot all of the bootable traits on the model.
     *
     * @return void
     */
    protected static function bootTraits()
    {
        foreach (class_uses_recursive(get_called_class()) as $trait) {
            if (method_exists(get_called_class(), $method = 'boot' . class_basename($trait))) {
                forward_static_call([get_called_class(), $method]);
            }
        }
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public static function getDateFormat()
    {
        return self::$toStringFormat;
    }

    /**
     * Set the date format used by the model.
     *
     * @param  string $format
     * @return $this
     */
    public static function setDateFormat($format)
    {
        self::$toStringFormat = $format;
        Date::setToStringFormat($format);
    }

    /**
     * Get the value of the model's primary key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Set the primary key for the model.
     *
     * @param  string $key
     * @return $this
     */
    public function setKeyName($key)
    {
        $this->primaryKey = $key;

        return $this;
    }

    /**
     * Set the parent ID for the model.
     *
     * @param  string $id
     * @return $this
     */
    public function setParentID($id)
    {
        self::$parent_id = $id;

        return $this;
    }

    /**
     * Get API endpoint.
     *
     * @return string
     */
    public function getEndpoint()
    {
        if (is_null($this->endpoint)) {
            $this->endpoint = str_replace('\\', '', snake_case(str_plural(class_basename($this))));
        }

        return $this->endpoint;
    }

    /**
     * Register an observer with the Model.
     *
     * @param  object|string  $class
     * @param  int  $priority
     * @return void
     */
    public static function observe($class, $priority = 0)
    {
        $instance = new static;

        $className = is_string($class) ? $class : get_class($class);

        // When registering a model observer, we will spin through the possible events
        // and determine if this observer has that method. If it does, we will hook
        // it into the model's event system, making it convenient to watch these.
        foreach ($instance->getObservableEvents() as $event) {
            if (method_exists($class, $event)) {
                static::registerModelEvent($event, $className.'@'.$event, $priority);
            }
        }
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array $attributes
     * @return self
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        // This method just provides a convenient way for us to generate fresh model
        // instances of this current model. It is particularly useful during the
        // hydration of new objects via the Eloquent query builder instances.
        $model = new static((array)$attributes, self::$parent_id);

        $model->exists = $exists;

        return $model;
    }

    /**
     * Create a collection of models from plain arrays.
     *
     * @param  array $items
     * @return array
     */
    public static function hydrate(array $items, $class = null)
    {
        $items = array_map(function ($item) use ($class) {
            // Single class given
            if (gettype($class) === 'string') {
                return new $class($item);
            }

            // Map an array of classes
            if (gettype($class) === 'array'
                && isset($item['type'])
                && isset($class[$item['type']])
            ) {
                return new $class[$item['type']]($item);
            }

            return new static($item, self::$parent_id);

        }, $items);

        return $items;
    }

    /**
     * Paginate items.
     *
     * @param  array  $result
     * @param  string $modelClass
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginateHydrate($result, $modelClass = null)
    {
        // Get values
        $pagination = array_get($result, 'pagination', []);
        $items = array_get($result, 'items', []);
        $currentPage = array_get($pagination, 'next', 2) - 1;

        // Set pagination
        $perPage = array_get($pagination, 'perPage', array_get($pagination, 'per_page', 15));
        $total = $perPage * array_get($pagination, 'last', 0);

        // Set options
        $options = is_array($result) ? array_except($result, [
            'pagination',
            'items',
            'next',
            'per_page',
            'last'
        ]) : [];

        return new LengthAwarePaginator($this->hydrate($items, $modelClass), $total, $perPage, $currentPage,
            array_merge($options, [
                'path' => LengthAwarePaginator::resolveCurrentPath()
            ]));
    }

    /**
     * Execute the query and get the first result.
     *
     * @param  string $id
     * @param  array  $params
     * @return mixed|static
     */
    public static function find($id, array $params = [])
    {
        $instance = new static([], self::$parent_id);

        return $instance->request($instance->getEndpoint(), 'find', [$id, $params]);
    }

    /**
     * Save a new model and return the instance.
     *
     * @param  array $attributes
     * @return static
     */
    public static function create(array $attributes = [])
    {
        $model = new static($attributes, self::$parent_id);

        $model->save();

        return $model;
    }

    /**
     * Make an all paginated request.
     *
     * @param  array $params
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public static function all(array $params = [])
    {
        // Remove empty params
        $params = array_filter($params);

        $instance = new static([], self::$parent_id);

        // Make request
        $result = $instance->makeRequest($instance->getEndpoint(), 'all', [$params]);

        // Hydrate object
        $result = $instance->paginateHydrate($result);

        // Append search params
        $result->appends($params);

        return $result;
    }

    /**
     * Paginate request.
     *
     * @param  string $method
     * @param  array  $params
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public static function paginate($method, array $params = [])
    {
        // Set request params
        $params = array_filter(array_merge([
            'page' => 1
        ], $params));

        $instance = new static([], self::$parent_id);;

        // Make request
        $result = $instance->makeRequest($instance->getEndpoint(), $method, [$params]);

        // Hydrate object
        $result = $instance->paginateHydrate($result);

        // Append search params
        $result->appends($params);

        return $result;
    }

    /**
     * Find a model by its primary key or throw an exception
     *
     * @param  string $id
     * @param  array  $params
     * @return mixed|static
     *
     * @throws \Torann\RemoteModel\NotFoundException
     */
    public static function findOrFail($id, array $params = [])
    {
        $instance = new static([], self::$parent_id);

        // Make request
        if (!is_null($result = $instance->request($instance->getEndpoint(), 'find', [$id, $params]))) {
            return $result;
        }

        // Not found
        throw new NotFoundException;
    }

    /**
     * Update the model in the database.
     *
     * @param  array $attributes
     * @param  mixed $id
     * @return bool|int
     */
    public function update(array $attributes = [], $id = null)
    {
        // This allows for update based on the primary key
        if ($id && $this->exists === false) {
            $this->setAttribute($this->getKeyName(), $id);
            $this->exists = true;
        }

        return $this->fill($attributes)->save();
    }

    /**
     * Save the model to the database.
     *
     * @return bool
     */
    public function save()
    {
        // If the model already exists in the database we can just update our record
        // that is already in this database using the current IDs in this "where"
        // clause to only update this model. Otherwise, we'll just insert them.
        if ($this->exists) {
            $saved = $this->performUpdate();
        }

        // If the model is brand new, we'll insert it into our database and set the
        // ID attribute on the model to the value of the newly inserted row's ID
        // which is typically an auto-increment value managed by the database.
        else {
            $saved = $this->performCreate();
        }

        if ($saved) {
            $this->fireModelEvent('saved', false);
        }

        return $saved;
    }

    /**
     * Delete the model from the database.
     *
     * @param  string $id
     * @return bool
     */
    public function delete($id = null)
    {
        // This allows for delete based on the primary key
        if ($id && !$this->exists) {
            $this->setAttribute($this->getKeyName(), $id);
            $this->exists = true;
        }

        // Can't delete something that doesn't exists
        if ($this->exists) {
            if ($this->fireModelEvent('deleting') === false) {
                return false;
            }

            $this->performDeleteOnModel();

            $this->exists = false;

            // Once the model has been deleted, we will fire off the deleted event so that
            // the developers may hook into post-delete operations. We will then return
            // a boolean true as the delete is presumably successful on the database.
            $this->fireModelEvent('deleted', false);

            return true;
        }

        return false;
    }


    /**
     * Register a saving model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function saving($callback, $priority = 0)
    {
        static::registerModelEvent('saving', $callback, $priority);
    }

    /**
     * Register a saved model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function saved($callback, $priority = 0)
    {
        static::registerModelEvent('saved', $callback, $priority);
    }

    /**
     * Register an updating model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function updating($callback, $priority = 0)
    {
        static::registerModelEvent('updating', $callback, $priority);
    }

    /**
     * Register an updated model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function updated($callback, $priority = 0)
    {
        static::registerModelEvent('updated', $callback, $priority);
    }

    /**
     * Register a creating model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function creating($callback, $priority = 0)
    {
        static::registerModelEvent('creating', $callback, $priority);
    }

    /**
     * Register a created model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function created($callback, $priority = 0)
    {
        static::registerModelEvent('created', $callback, $priority);
    }

    /**
     * Register a deleting model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function deleting($callback, $priority = 0)
    {
        static::registerModelEvent('deleting', $callback, $priority);
    }

    /**
     * Register a deleted model event with the dispatcher.
     *
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    public static function deleted($callback, $priority = 0)
    {
        static::registerModelEvent('deleted', $callback, $priority);
    }

    /**
     * Paginate request.
     *
     * @param  mixed  $modelClass
     * @param  string $method
     * @param  array  $params
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginateChildren($modelClass, $method, array $params = [])
    {
        // Set request params
        $params = array_filter(array_merge([
            'page' => 1
        ], $params));

        // Make request
        $result = $this->makeRequest($this->getEndpoint(), $method, [$this->getKey(), $params]);

        // Hydrate object
        $result = $this->paginateHydrate($result, $modelClass);

        // Append search params
        $result->appends($params);

        return $result;
    }

    /**
     * Perform a model update operation.
     *
     * @return bool|null
     */
    protected function performUpdate()
    {
        $params = $this->setKeysForSave();

        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // Send updates
        $results = $this->makeRequest($this->getEndpoint(), 'update', [
            $this->getKey(),
            $params
        ]);

        if ($results) {
            // Update model with new data
            $this->fill($results);

            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    /**
     * Perform a model create operation.
     *
     * @param  array $options
     * @return bool
     */
    protected function performCreate(array $options = [])
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        $results = $this->makeRequest($this->getEndpoint(), 'create', [$this->attributes]);

        // Creation failed
        if (!$results) {
            return false;
        }

        // Fresh start
        $this->attributes = [];

        // Update model with new data
        $this->fill($results);

        // We will go ahead and set the exists property to true, so that it is set when
        // the created event is fired, just in case the developer tries to update it
        // during the event. This will allow them to do so and run an update here.
        $this->exists = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Perform the actual delete query on this model instance.
     *
     * @return bool
     */
    protected function performDeleteOnModel()
    {
        $results = $this->makeRequest($this->getEndpoint(), 'destroy', [
            $this->getKey()
        ]);

        // Creation failed
        if (!$results) {
            return false;
        }

        return true;
    }

    /**
     * Set the keys for a save update request.
     *
     * @return array
     */
    protected function setKeysForSave()
    {
        // Model defined API params
        if (method_exists($this, 'toApi')) {
            return $this->toApi();
        }

        // Get params from attributes
        $params = $this->attributesToArray();

        // Remove dynamic params
        return array_except($params, array_merge($this->dates, $this->hiddenKeys, [
            'id',
            'pagination',
            'items'
        ]));
    }

    /**
     * Where there errors
     *
     * @return bool
     */
    public function hasErrors()
    {
        return $this->messageBag ? true : false;
    }

    /**
     * Return errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->messageBag ? array_get($this->messageBag, 'errors') : false;
    }

    /**
     * Return client error code
     *
     * @return int
     */
    public function getErrorCode()
    {
        return $this->messageBag ? array_get($this->messageBag, 'code') : 200;
    }

    /**
     * Return client error response
     *
     * @return array
     */
    public function getClientError()
    {
        return $this->messageBag;
    }

    /**
     * Execute the query and get the first result.
     *
     * @param  string $id
     * @param  array  $params
     * @return mixed|static
     */
    public function get($id, array $params = [])
    {
        return $this->request($this->getEndpoint(), 'find', [$id, $params]);
    }

    /**
     * Execute the find by query and get the first result.
     *
     * @param  string $method
     * @param  array  $params
     * @return mixed|static
     */
    public function findBy($method, array $params = [])
    {
        return $this->request($this->getEndpoint(), $method, $params);
    }

    /**
     * Search for results based on given params.
     *
     * @param  array $params
     * @return mixed|static
     */
    public static function search(array $params)
    {
        $instance = new static([], self::$parent_id);

        // Make request
        $result = $instance->makeRequest($instance->getEndpoint(), 'search', [array_filter($params)]);

        // Hydrate object
        $result = $instance->paginateHydrate($result);

        // Append search params
        $result->appends(array_filter($params));

        return $result;
    }

    /**
     * Get the hidden attributes for the model.
     *
     * @return array
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Set the hidden attributes for the model.
     *
     * @param  array $hidden
     * @return void
     */
    public function setHidden(array $hidden)
    {
        $this->hidden = $hidden;
    }

    /**
     * Add hidden attributes for the model.
     *
     * @param  array|string|null $attributes
     * @return void
     */
    public function addHidden($attributes = null)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->hidden = array_merge($this->hidden, $attributes);
    }

    /**
     * Get the visible attributes for the model.
     *
     * @return array
     */
    public function getVisible()
    {
        return $this->visible;
    }

    /**
     * Set the visible attributes for the model.
     *
     * @param  array $visible
     * @return void
     */
    public function setVisible(array $visible)
    {
        $this->visible = $visible;
    }

    /**
     * Add visible attributes for the model.
     *
     * @param  array|string|null $attributes
     * @return void
     */
    public function addVisible($attributes = null)
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->visible = array_merge($this->visible, $attributes);
    }

    /**
     * Set the accessors to append to model arrays.
     *
     * @param  array $appends
     * @return void
     */
    public function setAppends(array $appends)
    {
        $this->appends = $appends;
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return $this->getKeyName();
    }

    /**
     * Get the route key value for the model.
     *
     * @return string
     */
    public function getRouteKey()
    {
        return $this->getAttribute($this->getRouteKeyName());
    }

    /**
     * Add a basic where clause to the query.
     *
     * NOTE: Used for route binding
     *
     * @param  string $column
     * @param  string $value
     * @return $this
     */
    public function where($column, $value = null)
    {
        $this->setAttribute($column, $value);

        return $this;
    }

    /**
     * Execute the query and get the first result.
     *
     * NOTE: Used for route binding
     *
     * @return mixed|static
     */
    public function first()
    {
        return $this->get($this->getRouteKey());
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributesToArray();
    }

    /**
     * Get the current client associated with the model.
     *
     * @return string
     */
    public static function getClient()
    {
        return static::$client;
    }

    /**
     * Set the client associated with the model.
     *
     * @param  object $client
     * @return $this
     */
    public static function setClient($client)
    {
        static::$client = $client;
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = $this->getArrayableAttributes();

        // Process attributes
        foreach ($attributes as $key => $value) {
            // Convert objects to array
            if (is_object($value) && is_callable([$value, 'toArray'])) {
                $attributes[$key] = $value->toArray();
            }

            // If an attribute is a date, we will cast it to a string after converting it
            // to a DateTime / \Jenssegers\Date\Date instance. This is so we will get some consistent
            // formatting while accessing attributes vs. arraying / JSONing a model.
            if (in_array($key, $this->dates)) {
                $attributes[$key] = $this->serializeDate(
                    $this->asDateTime($value)
                );
            }
        }

        $mutatedAttributes = $this->getMutatedAttributes();

        // We want to spin through all the mutated attributes for this model and call
        // the mutator for the attribute. We cache off every mutated attributes so
        // we don't have to constantly check on attributes that actually change.
        foreach ($mutatedAttributes as $key) {
            if (!array_key_exists($key, $attributes)) {
                continue;
            }

            $attributes[$key] = $this->mutateAttributeForArray(
                $key, $attributes[$key]
            );
        }

        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        foreach ($this->casts as $key => $value) {
            if (!array_key_exists($key, $attributes) ||
                in_array($key, $mutatedAttributes)
            ) {
                continue;
            }

            $attributes[$key] = $this->castAttribute(
                $key, $attributes[$key]
            );

            if ($attributes[$key] && ($value === 'date' || $value === 'datetime')) {
                $attributes[$key] = $this->serializeDate($attributes[$key]);
            }
        }

        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        return $attributes;
    }

    /**
     * Get an attribute array of all arrayable attributes.
     *
     * @return array
     */
    protected function getArrayableAttributes()
    {
        return $this->getArrayableItems($this->attributes);
    }

    /**
     * Get all of the appendable values that are arrayable.
     *
     * @return array
     */
    protected function getArrayableAppends()
    {
        if (!count($this->appends)) {
            return [];
        }

        return $this->getArrayableItems(
            array_combine($this->appends, $this->appends)
        );
    }

    /**
     * Get an attribute array of all arrayable values.
     *
     * @param  array $values
     * @return array
     */
    protected function getArrayableItems(array $values)
    {
        if (count($this->visible) > 0) {
            return array_intersect_key($values, array_flip($this->visible));
        }

        return array_diff_key($values, array_flip($this->hidden));
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (array_key_exists($key, $this->attributes) || $this->hasGetMutator($key)) {
            return $this->getAttributeValue($key);
        }
    }

    /**
     * Get a plain attribute (not a relationship).
     *
     * @param  string $key
     * @return mixed
     */
    protected function getAttributeValue($key)
    {
        $value = $this->getAttributeFromArray($key);

        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependant upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            $value = $this->castAttribute($key, $value);
        }

        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        elseif (in_array($key, $this->dates)) {
            if (!is_null($value)) {
                return $this->asDateTime($value);
            }
        }

        return $value;
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
    }

    /**
     * Determine if a get mutator exists for an attribute.
     *
     * @param  string $key
     * @return bool
     */
    public function hasGetMutator($key)
    {
        return method_exists($this, 'get' . studly_case($key) . 'Attribute');
    }

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function mutateAttribute($key, $value)
    {
        return $this->{'get' . studly_case($key) . 'Attribute'}($value);
    }

    /**
     * Get the value of an attribute using its mutator for array conversion.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function mutateAttributeForArray($key, $value)
    {
        $value = $this->mutateAttribute($key, $value);

        return $value instanceof ArrayableInterface ? $value->toArray() : $value;
    }

    /**
     * Determine whether an attribute should be casted to a native type.
     *
     * @param  string $key
     * @return bool
     */
    protected function hasCast($key)
    {
        return array_key_exists($key, $this->casts);
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     *
     * @param  string $key
     * @return bool
     */
    protected function isJsonCastable($key)
    {
        if ($this->hasCast($key)) {
            return in_array(
                $this->getCastType($key), ['array', 'json', 'object'], true
            );
        }

        return false;
    }

    /**
     * Get the type of cast for a model attribute.
     *
     * @param  string $key
     * @return string
     */
    protected function getCastType($key)
    {
        return trim(strtolower($this->casts[$key]));
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        if (is_null($value)) {
            return $value;
        }

        switch ($this->getCastType($key)) {
            case 'int':
            case 'integer':
                return (int)$value;
            case 'real':
            case 'float':
            case 'double':
                return (float)$value;
            case 'string':
                return (string)$value;
            case 'bool':
            case 'boolean':
                return (bool)$value;
            case 'object':
                return json_decode($value);
            case 'array':
            case 'json':
                return json_decode($value, true);
            case 'date':
            case 'datetime':
                return $this->asDateTime($value);
            case 'timestamp':
                return $this->asTimeStamp($value);
            default:
                return $value;
        }
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // the model, such as "json_encoding" an listing of data for storage.
        if ($this->hasSetMutator($key)) {
            return $this->{'set' . studly_case($key) . 'Attribute'}($value);
        }

        if ($this->isJsonCastable($key)) {
            $value = json_encode($value);
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Determine if a set mutator exists for an attribute.
     *
     * @param  string $key
     * @return bool
     */
    public function hasSetMutator($key)
    {
        return method_exists($this, 'set' . studly_case($key) . 'Attribute');
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed $value
     * @return \Carbon\Carbon
     */
    protected function asDateTime($value)
    {
        // If the value is already a DateTime instance, we will just skip the rest of
        // these checks since they will be a waste of time, and hinder performance
        // when checking the field. We will just return the DateTime right away.
        if ($value instanceof DateTime) {
            //
        }

        // If this value is an integer, we will assume it is a UNIX timestamp's value
        // and format a Carbon object from this timestamp. This allows flexibility
        // when defining your date fields as they might be UNIX timestamps here.
        elseif (is_numeric($value)) {
            return Date::createFromTimestamp($value);
        }

        // If the value is in simply year, month, day format, we will instantiate the
        // Carbon instances from that format. Again, this provides for simple date
        // fields on the database, while still supporting Carbonized conversion.
        elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value)) {
            return Date::createFromFormat('Y-m-d', $value);
        }

        // If the value is in simply hour, minute, second format, we will instantiate the
        // Carbon instances from that format.
        elseif (preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $value)) {
            return Date::createFromFormat('H:i:s', $value);
        }

        // If the value is in zulu format, we will instantiate the
        // Carbon instances from that format.
        elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $value)) {
            return Date::createFromFormat('Y-m-d\TH:i:s\Z', $value);
        }

        // Finally, we will just assume this date is in the format used by default on
        // the database connection and use that format to create the Carbon object
        // that is returned back out to the developers after we convert it here.
        elseif (!$value instanceof DateTime) {
            return Date::createFromFormat($this->getDateFormat(), $value);
        }

        return Date::instance($value);
    }

    /**
     * Return a timestamp as unix timestamp.
     *
     * @param  mixed $value
     * @return int
     */
    protected function asTimeStamp($value)
    {
        return (int)$this->asDateTime($value)->timestamp;
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTime $date
     * @return string
     */
    protected function serializeDate(DateTime $date)
    {
        return $date->format($this->getDateFormat());
    }

    /**
     * Clone the model into a new, non-existing instance.
     *
     * @return static
     */
    public function replicate()
    {
        with($instance = new static($this->attributes, self::$parent_id));

        return $instance;
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Get the mutated attributes for a given instance.
     *
     * @return array
     */
    public function getMutatedAttributes()
    {
        $class = get_class($this);

        if (!isset(static::$mutatorCache[$class])) {
            static::cacheMutatedAttributes($class);
        }

        return static::$mutatorCache[$class];
    }

    /**
     * Extract and cache all the mutated attributes of a class.
     *
     * @param string $class
     * @return void
     */
    public static function cacheMutatedAttributes($class)
    {
        $mutatedAttributes = [];

        // Here we will extract all of the mutated attributes so that we can quickly
        // spin through them after we export models to their array form, which we
        // need to be fast. This'll let us know the attributes that can mutate.
        foreach (get_class_methods($class) as $method) {
            if (strpos($method, 'Attribute') !== false &&
                preg_match('/^get(.+)Attribute$/', $method, $matches)
            ) {
                if (static::$snakeAttributes) {
                    $matches[1] = snake_case($matches[1]);
                }

                $mutatedAttributes[] = lcfirst($matches[1]);
            }
        }

        static::$mutatorCache[$class] = $mutatedAttributes;
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string $related
     * @param  string $parentKey
     * @param  string $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function hasMany($related, $parentKey = null, $localKey = null)
    {
        $parentKey = $parentKey ?: $this->getParentKey();
        $localKey = $localKey ?: $this->getKeyName();

        $instance = new $related;

        return new Relations\Relation($instance, $this, $parentKey, $localKey);
    }

    /**
     * Get the default foreign key name for the model.
     *
     * @return string
     */
    public function getParentKey()
    {
        return snake_case(class_basename($this)) . 'ID';
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param  string $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given attribute exists.
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed $offset
     * @param  mixed $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

    /**
     * Request through the API client.
     *
     * @return mixed
     */
    protected function request($endpoint = null, $method, $params)
    {
        $results = $this->makeRequest($endpoint, $method, $params);

        return $results ? $this->newInstance($results, true) : null;
    }

    /**
     * Make request through the API client.
     *
     * @return mixed
     */
    protected function makeRequest($endpoint = null, $method, $params)
    {
        $endpoint = $endpoint ?: $this->getEndpoint();

        // Prepend relationship, if one exists.
        if (self::$parent_id) {
            $params = array_merge([
                self::$parent_id
            ], $params);
        }

        $results = call_user_func_array([static::$client->$endpoint(), $method], $params);

        // Set errors from server...if any
        $this->messageBag = static::$client->errors();

        return $results;
    }

    /**
     * Register a model event with the dispatcher.
     *
     * @param  string  $event
     * @param  \Closure|string  $callback
     * @param  int  $priority
     * @return void
     */
    protected static function registerModelEvent($event, $callback, $priority = 0)
    {
        $name = get_called_class();

        app('events')->listen("eloquent.{$event}: {$name}", $callback, $priority);
    }

    /**
     * Get the observable event names.
     *
     * @return array
     */
    public function getObservableEvents()
    {
        return [
            'creating', 'created', 'updating', 'updated',
            'deleting', 'deleted', 'saving', 'saved',
        ];
    }

    /**
     * Fire the given event for the model.
     *
     * @param  string  $event
     * @param  bool  $halt
     * @return mixed
     */
    protected function fireModelEvent($event, $halt = true)
    {
        // We will append the names of the class to the event to distinguish it from
        // other model events that are fired, allowing us to listen on each model
        // event set individually instead of catching event for all the models.
        $event = "eloquent.{$event}: " . get_class($this);

        $method = $halt ? 'until' : 'fire';

        return app('events')->$method($event, $this);
    }


    /**
     * Determine if an attribute exists on the model.
     *
     * @param  string $key
     * @return void
     */
    public function __isset($key)
    {
        return ((isset($this->attributes[$key]) || isset($this->relations[$key])) ||
            ($this->hasGetMutator($key) && !is_null($this->getAttributeValue($key))));
    }

    /**
     * Unset an attribute on the model.
     *
     * @param  string $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->attributes[$key]);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        $instance = new static([], self::$parent_id);

        // Find by magic method
        if (substr($method, 0, 6) === 'findBy') {
            return $instance->findBy($method, $parameters);
        }

        return call_user_func_array([$instance, $method], $parameters);
    }

    /**
     * Convert the model to its string representation.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * When a model is being unserialized, check if it needs to be booted.
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->bootIfNotBooted();
    }

}
