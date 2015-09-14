<?php

class EndpointStub
{
    public function find($id)
    {
        return [
            'id' => $id
        ];
    }

    public function create(array $params)
    {
        return $params;
    }

    public function update($id, array $params)
    {
        return $params;
    }

    public function all(array $params)
    {
        return [
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
        ];
    }

    public function paginateTest(array $params)
    {
        return [
            'pagination' => [
                'next' => '',
                'perPage' => 15,
                'last' => 1
              ],
            'items' => [
                [
                    'name' => 'John Doe'
                ],
                [
                    'name' => 'Jone Doe'
                ]
            ]
        ];
    }
}