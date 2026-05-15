<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClientPolicy
{

    // সুপার অ্যাডমিন হলে সব পারমিশন বাইপাস করে true রিটার্ন করবে
    public function before(User $user): ?bool
    {
        if ($user->is_admin) {
            return true;
        }
        return null; // নাহলে নিচের মেথডগুলোতে যাবে
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('client.list');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Client $client): bool
    {
        return $user->hasPermission('client.list');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('client.create');
    }

    public function update(User $user, Client $client): bool
    {
        return $user->hasPermission('client.edit');
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->hasPermission('client.delete');
    }
}
