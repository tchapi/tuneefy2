<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Table()]
class ApiClient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $clientId;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private $name;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private $email;

    #[ORM\Column(type: 'string', length: 255)]
    private $url;

    #[ORM\Column(type: 'text')]
    private $description;

    #[ORM\Column(type: 'date', length: 255)]
    private $createdAt;

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
