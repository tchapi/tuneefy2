<?php

namespace App\Entity;

use App\Repository\ApiClientRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: ApiClientRepository::class)]
#[ORM\Table()]
class ApiClient implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $name;

    #[ORM\Column(type: 'string', length: 255)]
    private $email;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $url;

    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(type: 'date', length: 255)]
    private $createdAt;

    // Link to the OAuth2 server tables (oauth2_client->identifier)
    #[ORM\Column(type: 'string', length: 80)]
    private $oauth2ClientIdentifier;

    /**
     * @return (Role|string)[] The user roles
     */
    public function getRoles(): array
    {
        // Only used in stateful version, when we bypass the oauth
        return ['ROLE_BYPASS_AUTH_API'];
    }

    /**
     * Removes sensitive data from the user.
     *
     * This is important if, at any given point, sensitive information like
     * the plain-text password is stored on this object.
     */
    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getOauth2ClientIdentifier(): string
    {
        return $this->oauth2ClientIdentifier;
    }

    public function setOauth2ClientIdentifier(string $oauth2ClientIdentifier): self
    {
        $this->oauth2ClientIdentifier = $oauth2ClientIdentifier;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = clone $createdAt;

        return $this;
    }
}
