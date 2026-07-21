<?php

namespace App\Http\Requests\Restaurant;

use App\Http\Requests\Concerns\ValidatesUploadedDocument;
use App\Services\RestaurantDocumentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The upload payload for a restaurant's two identity documents.
 *
 * Rules are built from `RestaurantDocumentService::SLOTS` rather than written
 * out twice: the two slots differ only in their column and their label, and a
 * third slot should be one entry in that map, not another block here.
 */
class SaveDocumentRequest extends FormRequest
{
    use ValidatesUploadedDocument;

    /**
     * The route carries `auth:sanctum` + `role:restaurant`, and the service
     * stores against `$request->user()`, so there is no per-record ownership to
     * authorize — the payload names no row.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (RestaurantDocumentService::SLOTS as $slot) {
            $rules[$slot['column']] = $this->slotRules($slot['column']);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach (RestaurantDocumentService::SLOTS as $slot) {
            $messages = [
                ...$messages,
                ...$this->uploadedDocumentMessages($slot['column'], 'The '.lcfirst($slot['label'])),
            ];
        }

        return $messages;
    }

    /**
     * A slot is required until something is on file for it, and optional after.
     *
     * That is what lets a restaurant correct one document without re-uploading
     * the other, while still making an empty first submission a 422 rather than
     * a success that stored nothing. `requiredIf` is evaluated per request, so
     * the second submission relaxes on its own.
     *
     * @return list<mixed>
     */
    private function slotRules(string $column): array
    {
        return [
            Rule::requiredIf(fn (): bool => blank($this->user()->{$column})),
            ...$this->uploadedDocumentRules(required: false),
        ];
    }
}
