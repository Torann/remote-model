<?php

class EndpointStub
{
    public function create(array $params)
    {
        return $params;
    }

    public function update($id, array $params)
    {
        return $params;
    }

    public function get(array $params)
    {
        $request = [
          'total' => 10,
          'per_page' => 3,
          'current_page' => 1,
          'last_page' => 4,
          'next_page_url' => null,
          'prev_page_url' => null,
          'from' => 1,
          'to' => 10,
          'data' =>
          [
            0 =>
            [
              'id' => 11,
              'username' => 'austin45',
              'first_name' => 'Nolan',
              'last_name' => 'Wisozk',
              'email' => 'alf.leannon@stark.com',
              'created_at' => '2015-03-05 14:04:28',
              'updated_at' => '2015-03-05 14:04:28',
            ],
            1 =>
            [
              'id' => 12,
              'username' => 'heidenreich.jesse',
              'first_name' => 'Sydnie',
              'last_name' => 'Hand',
              'email' => 'rturcotte@gmail.com',
              'created_at' => '2015-03-05 14:04:28',
              'updated_at' => '2015-03-05 14:04:28',
            ],
            2 =>
            [
              'id' => 13,
              'username' => 'doyle.bradford',
              'first_name' => 'German',
              'last_name' => 'Hintz',
              'email' => 'sienna.kreiger@hotmail.com',
              'created_at' => '2015-03-05 14:04:28',
              'updated_at' => '2015-03-05 14:04:28',
            ]
          ]
        ];

        $take = array_get($params, '_take');
        if ($take) {
            $request['data'] = array_slice($request['data'], 0, $take);
        }

        return $request;
    }
}
