<?php

declare(strict_types=1);

namespace App\Presentation\User\Presenter;

use App\Application\User\Port\AvatarUrlResolverInterface;
use App\Application\User\ReadModel\UserItem;
use App\Presentation\User\ApiResource\UserResource;

final readonly class UserResourcePresenter
{
    public function __construct(
        private AvatarUrlResolverInterface $avatarUrlResolver,
    ) {
    }

    public function toResource(UserItem $userItem): UserResource
    {
        $resource = new UserResource();

        $resource->id = $userItem->id;
        $resource->firstname = $userItem->firstname;
        $resource->lastname = $userItem->lastname;
        $resource->username = $userItem->username;
        $resource->email = $userItem->email;
        $resource->roles = $userItem->roles;
        $resource->status = $userItem->status;
        $resource->avatarUrl = $this->avatarUrlResolver->resolve($userItem->avatar);
        $resource->lastVisit = $userItem->lastVisit;
        $resource->nbLogin = $userItem->loginCount;
        $resource->createdAt = $userItem->createdAt;
        $resource->updatedAt = $userItem->updatedAt;

        return $resource;
    }
}
