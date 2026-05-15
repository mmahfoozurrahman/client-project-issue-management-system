<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientStoreRequest;
use App\Http\Requests\ClientUpdateRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(): Response
    {
        //abort_unless(auth()->user()->canAccessClientsPage(), 403);
        $this->authorize('viewAny', Client::class);

        // 
        $clients = Client::query()
            ->when(auth()->user()->is_admin, function ($query) {
                $query->withoutGlobalScope('user_owned');
            })
            ->withCount('projects')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // প্রতিটি ক্লায়েন্টের জন্য ইউজারের edit ও delete পারমিশন অ্যাড করে দেওয়া

        $clients->getCollection()->transform(function ($client) {

            $client->can_edit = auth()->user()->can('update', $client);

            $client->can_delete = auth()->user()->can('delete', $client);

            return $client;

        });

        // নতুন ক্লায়েন্ট ক্রিয়েট করার পারমিশন আছে কি না চেক করা

        $canCreateClient = auth()->user()->can('create', Client::class);

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'canCreateClient' => $canCreateClient, // এই প্রপসটি Vue-তে পাঠালাম
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Clients'],
            ],
        ]);
    }

    public function store(ClientStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Client::class);

        $client = Client::query()->create($request->validated());

        return redirect()
            ->route('clients.index')
            ->with('success', "Client {$client->name} created successfully.");
    }

    public function update(ClientUpdateRequest $request, $id): RedirectResponse
    {

        $client = Client::query()
            ->when(auth()->user()->is_admin, function ($query) {
                $query->withoutGlobalScope('user_owned');
            })
            ->findOrFail($id);

        // আপডেট করার আগে পলিসি চেক করা
        $this->authorize('update', $client);

        $client->update($request->validated());

        return redirect()
            ->route('clients.index')
            ->with('success', "Client {$client->name} updated successfully.");
    }

    public function destroy($id): RedirectResponse
    {

        $client = Client::query()
            ->when(auth()->user()->is_admin, function ($query) {
                $query->withoutGlobalScope('user_owned');
            })
            ->findOrFail($id);

        // ডিলিট করার আগে পলিসি চেক করা
        $this->authorize('delete', $client);

        $name = $client->name;
        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('success', "Client {$name} deleted successfully.");
    }
}
