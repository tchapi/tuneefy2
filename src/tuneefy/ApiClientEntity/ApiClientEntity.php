<?php

namespace tuneefy\ApiClientEntity;

use tuneefy\Utils\Utils;

class ApiClientEntity
{
    private $clientId;

    private $name;
    private $email;
    private $url;
    private $description;

    private $createdAt;

    // Getters and setters
    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): this
    {
        $this->name = $name;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): this
    {
        $this->email = $email;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): this
    {
        $this->url = $url;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): this
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): this
    {
        $this->createdAt = clone $createdAt;
        return $this;
    }
}
