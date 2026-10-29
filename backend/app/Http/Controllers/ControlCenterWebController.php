<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FeatureDefinition;
use App\Models\Installation;
use App\Models\LicenseRevision;
use App\Models\VendorUser;
use App\Services\AuditLogger;
use App\Services\CustomerRegistryService;
use App\Services\InstallationRegistryService;
use App\Services\LicenseIssuanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ControlCenterWebController extends Controller
{
    public function __construct(
        private readonly CustomerRegistryService $customers,
        private readonly InstallationRegistryService $installations,
        private readonly LicenseIssuanceService $licenses,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        $this->allow('overview.view');

        return view('control.index', [
            'customers' => Customer::query()->withCount('installations')->orderByDesc('id')->get(),
            'installations' => Installation::query()->with(['customer', 'licenseRevisions'])->orderByDesc('id')->get(),
            'licenseCount' => LicenseRevision::query()->count(),
            'signingReady' => $this->licenses->signingIsConfigured(),
        ]);
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        $this->allow('customers.create');
        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100', 'unique:customers,reference'],
            'legal_name' => ['required', 'string', 'max:200'],
            'display_name' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['contract_state'] = 'active';
        $data['contacts'] = [];
        $this->customers->create($data, $this->actor(), 'إضافة العميل من لوحة Pro One');

        return back()->with('success', 'تمت إضافة العميل. الخطوة التالية: سجّل تثبيته.');
    }

    public function storeInstallation(Request $request): RedirectResponse
    {
        $this->allow('installations.create');
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'installation_uuid' => ['required', 'uuid', 'unique:installations,installation_uuid'],
            'site_label' => ['required', 'string', 'max:150'],
            'deployment_mode' => ['required', Rule::in(Installation::MODES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['activation_state'] = 'pending';
        $this->installations->create($data, $this->actor(), 'تسجيل تثبيت من لوحة Pro One');

        return back()->with('success', 'تم تسجيل التثبيت كطلب معلّق. راجع هويته ثم اعتمده.');
    }

    public function approveInstallation(Installation $installation): RedirectResponse
    {
        $this->allow('installations.update');
        if ($installation->activation_state !== 'pending') {
            return back()->withErrors(['installation' => 'لا يمكن اعتماد هذا التثبيت من حالته الحالية.']);
        }
        $before = $installation->safeSummary();
        $installation->forceFill(['activation_state' => 'approved', 'updated_by' => $this->actor()->id])->save();
        $this->audit->record('installations.approved', 'high', $installation, $before, $installation->safeSummary(), 'اعتماد يدوي بعد مطابقة معرّف التثبيت', $this->actor());

        return back()->with('success', 'تم اعتماد التثبيت، ويمكن الآن إصدار الترخيص.');
    }

    public function licenseForm(Installation $installation): View
    {
        $this->allow('licenses.issue');

        return view('control.license', [
            'installation' => $installation->load(['customer', 'licenseRevisions.issuer']),
            'features' => FeatureDefinition::query()->where('is_active', true)->orderBy('id')->get(),
            'signingReady' => $this->licenses->signingIsConfigured(),
        ]);
    }

    public function issueLicense(Request $request, Installation $installation): RedirectResponse
    {
        $this->allow('licenses.issue');
        $data = $request->validate([
            'edition' => ['required', 'regex:/^[a-z][a-z0-9._-]*$/', 'max:100'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', Rule::exists('feature_definitions', 'key')->where('is_active', true)],
            'expires_on' => ['nullable', 'date', 'after_or_equal:today'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $revision = $this->licenses->issue(
                $installation,
                array_values($data['features'] ?? []),
                $data['edition'],
                $data['expires_on'] ?? null,
                $data['reason'],
                $this->actor(),
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['license' => $exception->getMessage()]);
        }

        return redirect()->route('control.licenses.create', $installation)->with('success', "تم إصدار النسخة رقم {$revision->sequence}. نزّل الملف وسلّمه للعميل.");
    }

    public function downloadLicense(LicenseRevision $licenseRevision): StreamedResponse
    {
        $this->allow('licenses.view');
        $document = $licenseRevision->getRawOriginal('signed_document');
        $filename = 'pro-one-license-'.$licenseRevision->license_uuid.'.json';

        return response()->streamDownload(static function () use ($document): void {
            echo $document;
        }, $filename, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    private function actor(): VendorUser
    {
        /** @var VendorUser $actor */
        $actor = request()->user();

        return $actor;
    }

    private function allow(string $permission): void
    {
        abort_unless($this->actor()->hasPermission($permission), 403, 'ليست لديك صلاحية تنفيذ هذه العملية.');
    }
}
