<?php

namespace App\Http\Requests\Rider;

use App\Http\Requests\Concerns\ValidatesUploadedImage;
use App\Models\RiderVehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * The create-or-update payload for the authenticated rider's one vehicle.
 *
 * One request for both, because the endpoint is an upsert: a rider has exactly
 * one vehicle, so "add" and "edit" are the same submission with the same
 * required fields. The reference app's `failedValidation()` override is not
 * ported — the handler in `bootstrap/app.php` already shapes every 422 into the
 * project envelope.
 */
class SaveVehicleRequest extends FormRequest
{
    use ValidatesUploadedImage;

    /**
     * The route carries `auth:sanctum` + `role:rider`, and the service upserts
     * against `$request->user()`, so there is no per-record ownership to
     * authorize — the payload names no row.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every field is required even though the columns are nullable: a vehicle
     * record exists so an admin can verify it, and one missing its plate or
     * model verifies nothing. The photo is the exception — it is optional, so a
     * rider can correct a typo without re-uploading, and its rules come from the
     * shared upload trait rather than being restated here.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vehicle_type' => ['required', Rule::in(RiderVehicle::VEHICLE_TYPES)],
            'registration_number' => ['required', 'string', 'max:50', $this->uniqueRegistrationNumber()],
            'vehicle_brand' => ['required', 'string', 'max:50'],
            'vehicle_model' => ['required', 'string', 'max:50'],
            'vehicle_color' => ['required', 'string', 'max:30'],
            'image' => $this->uploadedImageRules(required: false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_type.in' => 'The vehicle type must be one of: '.implode(', ', RiderVehicle::VEHICLE_TYPES).'.',
            'registration_number.unique' => 'That registration number is already registered to another rider.',
            ...$this->uploadedImageMessages('image', 'The vehicle photo'),
        ];
    }

    /**
     * A registration plate identifies one vehicle in the world, so no two
     * riders may claim the same one.
     *
     * The table's unique index is on `[rider_id, registration_number]`, which
     * only stops one rider holding a plate twice — something the upsert already
     * makes impossible. This is the half the index does not cover, and it is
     * scoped to *other* riders so re-submitting an unchanged plate stays a
     * valid edit.
     */
    private function uniqueRegistrationNumber(): Unique
    {
        return Rule::unique('rider_vehicles', 'registration_number')
            ->where(fn ($query) => $query->where('rider_id', '!=', $this->user()->getKey()));
    }
}
