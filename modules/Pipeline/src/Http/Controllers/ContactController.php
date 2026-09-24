<?php

namespace Deally\Pipeline\Http\Controllers;

use Deally\Core\Http\Controllers\Controller;
use Deally\Pipeline\Models\Contact;
use Deally\Pipeline\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorizeDeally('deally.customers.manage');
        $this->authorizeSeatRecord($customer);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $makePrimary = (bool) ($data['is_primary'] ?? false);

        if ($makePrimary) {
            $customer->contacts()->update(['is_primary' => false]);
        }

        Contact::query()->create([
            'customer_id' => $customer->getKey(),
            'name' => $data['name'],
            'title' => $data['title'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_primary' => $makePrimary,
        ]);

        return back()->with('toast', 'Contact added to the account.');
    }
}
