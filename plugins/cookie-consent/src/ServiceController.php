<?php

namespace Plugins\CookieConsent;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

// Services contain raw script code for every page — manage-plugins only
#[Middleware('permission:manage-plugins')]
class ServiceController extends Controller
{
    public function index(): View
    {
        $services = $this->loadServices();

        return view('cookie-consent::services', [
            'services' => $services,
        ]);
    }

    public function create(): View
    {
        return view('cookie-consent::service-form', [
            'service' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateService($request);

        $services = $this->loadServices();

        $id = Str::slug($data['name']);
        $existing = array_column($services, 'id');
        $i = 1;
        $baseId = $id;
        while (in_array($id, $existing)) {
            $id = $baseId.'-'.$i++;
        }

        $services[] = array_merge($data, ['id' => $id]);
        $this->saveServices($services);

        return redirect()->route('backend.cookie-consent.services')
            ->with('success', __('cookie-consent::cc.service_saved'));
    }

    public function edit(string $id): View|RedirectResponse
    {
        $services = $this->loadServices();
        $service = collect($services)->firstWhere('id', $id);

        if (! $service) {
            return redirect()->route('backend.cookie-consent.services')
                ->withErrors(['id' => 'Service not found.']);
        }

        return view('cookie-consent::service-form', [
            'service' => $service,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $data = $this->validateService($request);

        $services = $this->loadServices();
        $found = false;

        foreach ($services as &$service) {
            if ($service['id'] === $id) {
                $service = array_merge($service, $data);
                $found = true;
                break;
            }
        }
        unset($service);

        if (! $found) {
            return redirect()->route('backend.cookie-consent.services')
                ->withErrors(['id' => 'Service not found.']);
        }

        $this->saveServices($services);

        return redirect()->route('backend.cookie-consent.services')
            ->with('success', __('cookie-consent::cc.service_saved'));
    }

    public function destroy(string $id): RedirectResponse
    {
        $services = $this->loadServices();
        $services = array_values(array_filter($services, fn ($s) => $s['id'] !== $id));
        $this->saveServices($services);

        return redirect()->route('backend.cookie-consent.services')
            ->with('success', __('cookie-consent::cc.service_deleted'));
    }

    private function validateService(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:200',
            'category' => 'required|in:functional,statistics,marketing',
            'description' => 'nullable|string|max:1000',
            'script' => 'required|string',
            'position' => 'required|in:head,body_start,body_end',
            'hosts' => 'nullable|string|max:2000',
        ]);

        // Allowed addresses: one per line, https only (*.domain too), no path
        $invalid = [];
        $data['hosts'] = ServiceScript::parseHosts($data['hosts'] ?? '', $invalid);
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'hosts' => __('cookie-consent::cc.service_hosts_invalid', ['value' => implode(', ', $invalid)]),
            ]);
        }

        return $data;
    }

    private function loadServices(): array
    {
        $json = cms()->settings()->get('cc.services', '[]');

        return json_decode($json, true) ?: [];
    }

    private function saveServices(array $services): void
    {
        cms()->settings()->set('cc.services', json_encode($services), 'plugin_setting');
    }
}
