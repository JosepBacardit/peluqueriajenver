<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ServiceRequest;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Services cannot be deleted: a service that is no longer offered is
 * marked inactive, so existing appointments keep pointing at it.
 */
class ServiceController extends Controller
{
    public function index(): View
    {
        return view('admin.services.index', ['services' => Service::query()->ordered()->get()]);
    }

    public function create(): View
    {
        return view('admin.services.create', ['service' => new Service(['is_bookable_online' => true, 'is_active' => true])]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        Service::create($request->serviceAttributes());

        return redirect()->route('admin.services.index')->with('status', 'Servicio creado.');
    }

    public function edit(Service $service): View
    {
        return view('admin.services.edit', ['service' => $service]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->serviceAttributes());

        return redirect()->route('admin.services.index')->with('status', 'Servicio actualizado.');
    }
}
