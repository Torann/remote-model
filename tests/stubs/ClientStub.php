<?php

class ClientStub
{
    public function errors()
    {
        return null;
    }

    public function __call($name, $args)
    {
        return new EndpointStub($this);
    }
}
