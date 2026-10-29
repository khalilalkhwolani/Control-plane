<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\IssueLicenseRequest;
use App\Http\Resources\LicenseRevisionResource;
use App\Models\Installation;
use App\Models\LicenseRevision;
use App\Services\AuditLogger;
use App\Services\LicenseIssuanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LicenseController extends Controller
{
    public function __construct(
        private readonly LicenseIssuanceService $licenses,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = LicenseRevision::query()->with(['installation.customer', 'issuer']);

        if ($request->filled('installation_id')) {
            $query->where('installation_id', $request->integer('installation_id'));
        }

        $licenses = $query->orderByDesc('id')->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json([
            'success' => true,
            'data' => LicenseRevisionResource::collection($licenses->items()),
            'meta' => [
                'current_page' => $licenses->currentPage(),
                'last_page' => $licenses->lastPage(),
                'per_page' => $licenses->perPage(),
                'total' => $licenses->total(),
            ],
        ]);
    }

    public function issue(IssueLicenseRequest $request, Installation $installation): JsonResponse
    {
        try {
            $license = $this->licenses->issue(
                $installation,
                array_values($request->validated('features')),
                (string) $request->validated('edition'),
                $request->validated('expires_on'),
                (string) $request->validated('reason'),
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'code' => 'license_issue_failed',
                'message' => $exception->getMessage(),
            ], 422);
        }

        $license->load(['installation.customer', 'issuer']);

        return response()->json([
            'success' => true,
            'data' => new LicenseRevisionResource($license),
        ], 201);
    }

    public function download(Request $request, LicenseRevision $licenseRevision): StreamedResponse
    {
        $licenseRevision->load('installation');
        $this->audit->record(
            'licenses.downloaded',
            'medium',
            $licenseRevision->installation,
            after: [
                'license_uuid' => $licenseRevision->license_uuid,
                'sequence' => $licenseRevision->sequence,
            ],
            reason: 'تنزيل ملف الترخيص الموقّع',
            actor: $request->user(),
            installation: $licenseRevision->installation,
        );

        $document = $licenseRevision->getRawOriginal('signed_document');
        $filename = 'pro-one-license-'.$licenseRevision->license_uuid.'.json';

        return response()->streamDownload(static function () use ($document): void {
            echo $document;
        }, $filename, ['Content-Type' => 'application/json; charset=UTF-8']);
    }
}
