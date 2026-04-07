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
        $clients = Client::query()
            ->withCount('projects')
            ->latest()
            ->get();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'breadcrumbs' => [
                ['label' => 'Home', 'href' => route('dashboard')],
                ['label' => 'Clients'],
            ],
        ]);
    }

    public function store(ClientStoreRequest $request): RedirectResponse
    {
        $client = Client::query()->create($request->validated());

        return redirect()
            ->route('clients.index')
            ->with('success', "Client {$client->name} created successfully.");
    }

    public function update(ClientUpdateRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()
            ->route('clients.index')
            ->with('success', "Client {$client->name} updated successfully.");
    }

    public function destroy(Client $client): RedirectResponse
    {
        $name = $client->name;
        $client->delete();

        return redirect()
            ->route('clients.index')
            ->with('success', "Client {$name} deleted successfully.");
    }
}
