<?php

namespace Rapidest;

class User // extends Model
{
    // @todo
    public function login(array $post)
    {
        return true;
    }
    
    # @todo
    public function loadBySessionOrCookie()
    {
        return $this;
    }

    # @todo
    public function may(string $method, string $uri)
    {
        return true;
    }
}