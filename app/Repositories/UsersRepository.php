<?php

namespace App\Repositories;
use App\Models\User;
class UsersRepository extends BaseRepository
{

    protected function modelClass(): string
    {
        return User::class;
    }
}
