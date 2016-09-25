<?php

use Carbon\Carbon;

class ModelTest extends Orchestra\Testbench\TestCase
{
    public function testAttributeManipulation()
    {
        $model = new ModelStub;
        $model->name = 'foo';

        $this->assertEquals('foo', $model->name);
        $this->assertTrue(isset($model->name));

        unset($model->name);

        $this->assertEquals(null, $model->name);
        $this->assertFalse(isset($model->name));

        $model['name'] = 'foo';
        $this->assertTrue(isset($model['name']));

        unset($model['name']);

        $this->assertFalse(isset($model['name']));
    }

    public function testConstructor()
    {
        $model = new ModelStub(['name' => 'john']);
        $this->assertEquals('john', $model->name);
    }

    public function testNewInstanceWithAttributes()
    {
        $model = new ModelStub;
        $instance = $model->newInstance(['name' => 'john']);

        $this->assertInstanceOf('ModelStub', $instance);
        $this->assertEquals('john', $instance->name);
    }

    public function testHidden()
    {
        $model = new ModelStub;
        $model->password = 'secret';

        $attributes = $model->attributesToArray();
        $this->assertFalse(isset($attributes['password']));
        $this->assertEquals(['password'], $model->getHidden());
    }

    public function testVisible()
    {
        $model = new ModelStub;
        $model->setVisible(['name']);
        $model->name = 'John Doe';
        $model->city = 'Paris';

        $attributes = $model->attributesToArray();
        $this->assertEquals(['name' => 'John Doe'], $attributes);
    }

    public function testToArray()
    {
        $model = new ModelStub;
        $model->name = 'foo';
        $model->bar = null;
        $model->password = 'password1';
        $model->setHidden(['password']);
        $array = $model->toArray();

        $this->assertTrue(is_array($array));
        $this->assertEquals('foo', $array['name']);
        $this->assertFalse(isset($array['password']));
        $this->assertEquals($array, $model->jsonSerialize());
    }

    public function testToJson()
    {
        $model = new ModelStub;
        $model->name = 'john';
        $model->foo = 10;

        $object = new stdClass;
        $object->name = 'john';
        $object->foo = 10;

        $this->assertEquals(json_encode($object), $model->toJson());
        $this->assertEquals(json_encode($object), (string) $model);
    }

    public function testMutator()
    {
        $model = new ModelStub;

        $model->list_items = ['name' => 'john'];
        $this->assertEquals(['name' => 'john'], $model->list_items);
        $attributes = $model->getAttributes();
        $this->assertEquals(json_encode(['name' => 'john']), $attributes['list_items']);
    }

    public function testDateMutator()
    {
        $model = new ModelStub([
            'createdAt' => '2015-08-25T20:59:08Z'
        ]);

        $this->assertInstanceOf('\\Jenssegers\\Date\\Date', $model->createdAt);
    }

    public function testToArrayUsesMutators()
    {
        $model = new ModelStub;
        $model->list_items = [1, 2, 3];
        $array = $model->toArray();

        $this->assertEquals([1, 2, 3], $array['list_items']);
    }

    public function testReplicate()
    {
        $model = new ModelStub;
        $model->name = 'John Doe';
        $model->city = 'Paris';

        $clone = $model->replicate();
        $this->assertEquals($model, $clone);
        $this->assertEquals($model->name, $clone->name);
    }

    public function testAppends()
    {
        $model = new ModelStub;
        $array = $model->toArray();
        $this->assertFalse(isset($array['test']));

        $model = new ModelStub;
        $model->setAppends(['test']);
        $array = $model->toArray();
        $this->assertTrue(isset($array['test']));
        $this->assertEquals('test', $array['test']);
    }

    public function testArrayAccess()
    {
        $model = new ModelStub;
        $model->name = 'John Doen';
        $model['city'] = 'Paris';

        $this->assertEquals($model->name, $model['name']);
        $this->assertEquals($model->city, $model['city']);
    }

    public function testSerialize()
    {
        $model = new ModelStub;
        $model->name = 'john';
        $model->foo = 10;

        $serialized = serialize($model);
        $this->assertEquals($model, unserialize($serialized));
    }

    public function testCasts()
    {
        $model = new ModelStub;
        $model->score = '0.34';
        $model->data = ['foo' => 'bar'];
        $model->active = 'true';

        $this->assertTrue(is_float($model->score));
        $this->assertTrue(is_array($model->data));
        $this->assertTrue(is_bool($model->active));

        $attributes = $model->getAttributes();
        $this->assertTrue(is_string($attributes['score']));
        $this->assertTrue(is_string($attributes['data']));
        $this->assertTrue(is_string($attributes['active']));

        $array = $model->toArray();
        $this->assertTrue(is_float($array['score']));
        $this->assertTrue(is_array($array['data']));
        $this->assertTrue(is_bool($array['active']));
    }

    public function testBootsTrait()
    {
        $model = new ModelStub;
        $this->assertTrue(ModelStub::$traitIsBooted);
    }

    public function testStaticCreateMethod()
    {
        ModelStub::setClient(new ClientStub);

        $params = [
            'name' => 'John Doe'
        ];

        $model = ModelStub::create($params);
        $this->assertEquals($params, $model->getAttributes());
    }

    public function testStaticFindMethod()
    {
        $rawData = (new EndpointStub)->get(['_take'=>1])['data'][0];

        ModelStub::setClient(new ClientStub);

        $model = ModelStub::find($rawData['id']);
        $this->assertEquals($rawData['id'], $model->id);
    }

    public function testStaticAllMethod()
    {
        ModelStub::setClient(new ClientStub);
        $total = count((new EndpointStub)->get([])['data']);

        $model = ModelStub::all();
        $this->assertEquals($total, $model->count());
    }

    public function testStaticPaginateMethod()
    {
        ModelStub::setClient(new ClientStub);
        $total = count((new EndpointStub)->get([])['data']);

        $model = ModelStub::paginate();
        $this->assertEquals($total, $model->count());
    }

    public function testUpdateMethod()
    {
        ModelStub::setClient(new ClientStub);
        $rawData = (new EndpointStub)->get(['_take'=>1])['data'][0];
        $model = ModelStub::find(1);

        $model->update([
            'name' => 'John Doe'
        ]);
        $rawData['name'] = 'John Doe';
        $this->assertEquals($rawData, $model->getAttributes());
    }

    public function testPaginateHydrateMethod()
    {
        ModelStub::setClient(new ClientStub);
        $model = ModelStub::find(1);

        $newModel = $model->paginateHydrate([
            'pagination' => [
                'next' => '',
                'perPage' => 15,
                'last' => 1
            ],
            'items' => [
                [
                    'name' => 'John Doe'
                ]
            ]
        ]);

        $this->assertEquals(1, $newModel->count());
    }
}
