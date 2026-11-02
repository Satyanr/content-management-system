<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DeviceCredential;
use App\Models\DevicePairingCode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DeviceLifecycle
{
    public static function normalizeCode(mixed $code): string
    {
        return is_string($code) ? strtoupper(str_replace('-', '', trim($code))) : '';
    }

    public function audit(Device $device, string $action, array $values = [], bool $system = false): void
    {
        app(ActivityLogService::class)->log(action: $action, module: 'devices', description: 'Device '.$action, subject: $device, newValues: $values, companyId: $device->company_id, systemActor: $system);
    }

    private function cancelCodes(Device $device): void
    {
        $device->pairingCodes()->whereNull('consumed_at')->whereNull('cancelled_at')->update(['cancelled_at' => CarbonImmutable::now('UTC')]);
    }

    private function revokeTokens(Device $device): void
    {
        $device->credentials()->whereNull('revoked_at')->update(['revoked_at' => CarbonImmutable::now('UTC')]);
    }

    public function issue(int $company, int $id, bool $repair = false): string
    {
        return DB::transaction(function () use ($company, $id, $repair) {
            $device = Device::forCompany($company)->lockForUpdate()->findOrFail($id);
            abort_unless($device->company->is_active && $device->status !== 'disabled', 403, 'Device or company is unavailable.');
            if ($repair) {
                $this->revokeTokens($device);
                $device->forceFill(['status' => 'pending', 'last_seen_at' => null])->save();
            }
            abort_unless($device->status === 'pending', 422, 'Use the confirmed re-pair action for an active Device.');
            $this->cancelCodes($device);
            $code = strtoupper(bin2hex(random_bytes(10)));
            $device->pairingCodes()->create(['code_hash' => hash('sha256', $code), 'expires_at' => CarbonImmutable::now('UTC')->addMinutes(10)]);
            $this->audit($device, $repair ? 're-pair.requested' : 'pairing.generated', ['expires_in_seconds' => 600]);

            return implode('-', str_split($code, 4));
        });
    }

    public function cancel(int $company, int $id): void
    {
        DB::transaction(function () use ($company, $id) {
            $device = Device::forCompany($company)->lockForUpdate()->findOrFail($id);
            $this->cancelCodes($device);
            $this->audit($device, 'pairing.cancelled');
        });
    }

    public function revoke(int $company, int $id): void
    {
        DB::transaction(function () use ($company, $id) {
            $device = Device::forCompany($company)->lockForUpdate()->findOrFail($id);
            $this->revokeTokens($device);
            $this->cancelCodes($device);
            $this->audit($device, 'credentials.revoked');
        });
    }

    public function disable(int $company, int $id, bool $disabled): void
    {
        DB::transaction(function () use ($company, $id, $disabled) {
            $device = Device::forCompany($company)->lockForUpdate()->findOrFail($id);
            if (! $disabled) {
                abort_unless($device->status === 'disabled', 422, 'Only a disabled Device can be enabled.');
            }
            $this->revokeTokens($device);
            $this->cancelCodes($device);
            $device->forceFill(['status' => $disabled ? 'disabled' : 'pending'])->save();
            $this->audit($device, $disabled ? 'disabled' : 'enabled', ['status' => $device->status]);
        });
    }

    /** Lock order is always Device then Pairing/Credential. Invalid known-code attempts commit too. */
    public function activate(#[\SensitiveParameter] array $input): ?array
    {
        $code = self::normalizeCode($input['activation_code']);
        $lookup = DevicePairingCode::where('code_hash', hash('sha256', $code))->first(['id', 'device_id']);
        if (! $lookup) {
            return null;
        }

        return DB::transaction(function () use ($lookup, $input) {
            $device = Device::lockForUpdate()->find($lookup->device_id);
            $pair = DevicePairingCode::lockForUpdate()->find($lookup->id);
            if (! $device || ! $pair) {
                return null;
            }
            $pair->increment('attempts');
            $now = CarbonImmutable::now('UTC');
            if ($pair->attempts > (int) config('devices.max_pairing_attempts', 5) || $pair->consumed_at || $pair->cancelled_at || $pair->expires_at->lte($now) || $device->status !== 'pending' || ! $device->company->is_active) {
                return null;
            }
            $pair->forceFill(['consumed_at' => $now])->save();
            $this->revokeTokens($device);
            $this->cancelCodes($device);
            $identifier = bin2hex(random_bytes(16));
            $secret = bin2hex(random_bytes(32));
            $device->credentials()->create(['token_identifier' => $identifier, 'token_hash' => hash('sha256', $secret)]);
            $device->forceFill(['status' => 'active', 'activated_at' => $now, 'last_seen_at' => null, 'platform' => $input['platform'], 'player_version' => $input['player_version'] ?? null,
                'screen_width' => $input['screen_width'], 'screen_height' => $input['screen_height'], 'orientation' => $input['screen_height'] > $input['screen_width'] ? 'portrait' : 'landscape'])->save();
            $this->audit($device, 'activated', ['platform' => $device->platform], true);

            return ['device' => ['public_id' => $device->public_id, 'status' => 'active'], 'credential' => 'dvc_'.$identifier.'.'.$secret, 'token_type' => 'Bearer', 'heartbeat_interval_seconds' => (int) config('devices.heartbeat_interval_seconds', 60)];
        });
    }

    public function authenticate(#[\SensitiveParameter] ?string $token): ?DeviceCredential
    {
        if (! $token || ! preg_match('/^dvc_([a-f0-9]{32})\.([a-f0-9]{64})$/D', $token, $parts)) {
            return null;
        }
        $credential = DeviceCredential::where('token_identifier', $parts[1])->with('device.company')->first();
        if (! $credential || $credential->revoked_at || ! hash_equals($credential->token_hash, hash('sha256', $parts[2])) || $credential->device?->status !== 'active' || ! $credential->device?->company?->is_active) {
            return null;
        }

        return $credential;
    }

    public function heartbeat(int $credentialId, array $input): ?array
    {
        $lookup = DeviceCredential::find($credentialId, ['id', 'device_id']);
        if (! $lookup) {
            return null;
        }

        return DB::transaction(function () use ($lookup, $input) {
            $device = Device::lockForUpdate()->find($lookup->device_id);
            $credential = DeviceCredential::lockForUpdate()->find($lookup->id);
            if (! $device || ! $credential || $credential->revoked_at || $device->status !== 'active' || ! $device->company->is_active) {
                return null;
            }
            $now = CarbonImmutable::now('UTC');
            $data = ['last_seen_at' => $now];
            foreach (['platform', 'player_version', 'screen_width', 'screen_height'] as $field) {
                if (array_key_exists($field, $input)) {
                    $data[$field] = $input[$field];
                }
            }
            if (isset($data['screen_width'],$data['screen_height'])) {
                $data['orientation'] = $data['screen_height'] > $data['screen_width'] ? 'portrait' : 'landscape';
            }
            $data['heartbeat'] = array_intersect_key($input, array_flip(['uptime_seconds', 'device_timestamp']));
            $device->forceFill($data)->save();
            $credential->forceFill(['last_used_at' => $now])->save();

            return ['status' => 'ok', 'device_public_id' => $device->public_id, 'server_time_utc' => $now->toIso8601String(), 'heartbeat_interval_seconds' => (int) config('devices.heartbeat_interval_seconds',60)];
        });
    }
}
