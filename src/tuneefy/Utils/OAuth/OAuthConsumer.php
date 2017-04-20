<?php

// vim: foldmethod=marker

namespace tuneefy\Utils\OAuth;

class OAuthConsumer
{
    public $key;
    public $secret;
    public $callback_url;

    public function __construct(string $key, string $secret, string $callback_url = null)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->callback_url = $callback_url;
    }

    public function __toString(): string
    {
        return "OAuthConsumer[key=$this->key,secret=$this->secret]";
    }
}
