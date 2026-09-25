<?php

namespace App\Platform\Storage\Http\Requests;

use App\Platform\Storage\Models\FileDisk;
use App\Rules\PublicHttpUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shapes the body that registers a new storage target.
 *
 * Only the label and the driver are asked of every caller, and the driver must
 * be one of FileDisk::DRIVERS. What a driver needs beyond that varies, so the
 * credential rules are picked from the driver named in the body and merged
 * underneath the two common ones. A local disk takes only a root, which the
 * live credential check the controller runs then proves writable.
 *
 * Where a driver takes a custom endpoint the server will later sign requests
 * against, the value has to be a URL that resolves somewhere publicly routable:
 * these credentials are supplied by an administrator and exercised server-side,
 * so an endpoint pointed at the loopback interface or a cloud metadata address
 * would turn disk registration into a request forgery primitive.
 */
class DiskEnvironmentRequest extends FormRequest
{
    /**
     * Gatekeeping happens in the controller, so let every caller through here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $credentials = [];

        switch ($this->get('driver')) {
            case 's3':
                $credentials = $this->amazonRules();

                break;

            case 's3compat':
                $credentials = $this->compatibleRules();

                break;

            case 'doSpaces':
                $credentials = $this->spacesRules();

                break;

            case 'dropbox':
                $credentials = $this->dropboxRules();

                break;
        }

        return array_merge($credentials, [
            'name' => ['required'],
            'driver' => ['required', Rule::in(FileDisk::DRIVERS)],
        ]);
    }

    /**
     * Any S3-compatible service (MinIO, Wasabi, R2, ...). The endpoint is the
     * whole point of the driver, so it is mandatory; the base config has no
     * root, so none is asked for.
     */
    private function compatibleRules(): array
    {
        return [
            'credentials.endpoint' => ['required', 'string', 'url', new PublicHttpUrl],
            'credentials.key' => ['required', 'string'],
            'credentials.secret' => ['required', 'string'],
            'credentials.region' => ['required', 'string'],
            'credentials.bucket' => ['required', 'string'],
            'credentials.root' => ['nullable', 'string'],
        ];
    }

    /**
     * Amazon S3 proper. The endpoint is optional here -- leaving it out means
     * the region alone decides which host the SDK talks to.
     */
    private function amazonRules(): array
    {
        return [
            'credentials.key' => ['required', 'string'],
            'credentials.secret' => ['required', 'string'],
            'credentials.region' => ['required', 'string'],
            'credentials.bucket' => ['required', 'string'],
            'credentials.endpoint' => ['nullable', 'string', 'url', new PublicHttpUrl],
            'credentials.root' => ['required', 'string'],
        ];
    }

    /**
     * DigitalOcean Spaces speaks the S3 protocol but always against its own
     * host, so the endpoint is mandatory rather than optional.
     */
    private function spacesRules(): array
    {
        return [
            'credentials.key' => ['required', 'string'],
            'credentials.secret' => ['required', 'string'],
            'credentials.region' => ['required', 'string'],
            'credentials.bucket' => ['required', 'string'],
            'credentials.endpoint' => ['required', 'string', 'url', new PublicHttpUrl],
            'credentials.root' => ['required', 'string'],
        ];
    }

    /**
     * Dropbox: an access token plus the app identity it was issued for. No
     * endpoint, so nothing for the reachability rule to inspect.
     */
    private function dropboxRules(): array
    {
        return [
            'credentials.token' => ['required', 'string'],
            'credentials.key' => ['required', 'string'],
            'credentials.secret' => ['required', 'string'],
            'credentials.app' => ['required', 'string'],
            'credentials.root' => ['required', 'string'],
        ];
    }
}
