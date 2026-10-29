<?php

declare(strict_types=1);

namespace App\Services;

require_once dirname(__DIR__, 3).'/licensing/src/Base64Url.php';
require_once dirname(__DIR__, 3).'/licensing/src/LicenseIssuer.php';

use App\Models\FeatureDefinition;
use App\Models\Installation;
use App\Models\LicenseRevision;
use App\Models\VendorUser;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SuperPro\ControlPlane\Licensing\LicenseIssuer;

class LicenseIssuanceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function signingIsConfigured(): bool
    {
        $path = config('control_center.signing.private_key_path');

        return filled(config('control_center.signing.key_id')) && is_string($path) && is_file($path);
    }

    /** @param list<string> $features */
    public function issue(Installation $installation, array $features, string $edition, ?string $expiresOn, string $reason, VendorUser $actor): LicenseRevision
    {
        return DB::transaction(function () use ($installation, $features, $edition, $expiresOn, $reason, $actor): LicenseRevision {
            $locked = Installation::query()->with('customer')->lockForUpdate()->findOrFail($installation->id);
            if ($locked->activation_state !== 'approved') {
                throw new RuntimeException('يجب اعتماد التثبيت قبل إصدار الترخيص.');
            }
            if ($locked->customer->contract_state !== 'active') {
                throw new RuntimeException('عقد العميل غير نشط، لذلك لا يمكن إصدار ترخيص جديد.');
            }

            $allowed = FeatureDefinition::query()->where('is_active', true)->pluck('key')->all();
            $features = array_values(array_unique($features));
            if (array_diff($features, $allowed) !== []) {
                throw new RuntimeException('توجد ميزة غير معتمدة في الطلب.');
            }
            sort($features, SORT_STRING);

            $path = config('control_center.signing.private_key_path');
            $encodedSecret = is_string($path) && is_file($path) ? trim((string) file_get_contents($path)) : '';
            $secret = base64_decode($encodedSecret, true);
            if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
                throw new RuntimeException('مفتاح توقيع التراخيص غير مضبوط أو غير صالح.');
            }

            $sequence = ((int) LicenseRevision::query()->where('installation_id', $locked->id)->max('sequence')) + 1;
            $licenseUuid = LicenseIssuer::uuidV4();
            $issuedAt = new DateTimeImmutable('now');
            $expiresAt = filled($expiresOn) ? new DateTimeImmutable($expiresOn.' 23:59:59') : null;
            $graceUntil = $expiresAt?->modify('+30 days');
            $keyId = (string) config('control_center.signing.key_id');
            $document = (new LicenseIssuer($keyId, $secret))->issue(
                installationId: $locked->installation_uuid,
                sequence: $sequence,
                customerReference: $locked->customer->reference,
                customerName: $locked->customer->display_name,
                edition: $edition,
                features: $features,
                issuedAt: $issuedAt,
                expiresAt: $expiresAt,
                graceUntil: $graceUntil,
                licenseId: $licenseUuid,
            );

            $revision = LicenseRevision::query()->create([
                'installation_id' => $locked->id,
                'license_uuid' => $licenseUuid,
                'sequence' => $sequence,
                'key_id' => $keyId,
                'edition' => $edition,
                'features' => $features,
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'grace_until' => $graceUntil,
                'document_sha256' => hash('sha256', $document),
                'signed_document' => $document,
                'issued_by' => $actor->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            $this->audit->record('licenses.issued', 'high', $locked, after: [
                'license_uuid' => $licenseUuid,
                'sequence' => $sequence,
                'edition' => $edition,
                'features' => $features,
            ], reason: $reason, actor: $actor, installation: $locked);

            return $revision;
        });
    }
}
