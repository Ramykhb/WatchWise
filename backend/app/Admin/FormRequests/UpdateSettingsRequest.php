<?php

namespace App\Admin\FormRequests;

use App\Admin\DTOs\Requests\UpdateSettingsData;
use App\Setting\Support\SettingKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('admin.settings');
    }

    /**
     * Only the body counts: a query-string parameter is neither validated nor reported as an unknown setting.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return $this->isJson() ? $this->json()->all() : $this->request->all();
    }

    /** Cleans the announcement message before the rules look at it (see `SettingKey::cleanInput()`). */
    protected function prepareForValidation(): void
    {
        $this->merge(SettingKey::cleanInput($this->validationData()));
    }

    /**
     * Each setting's own rules, taken from the registry. Every key is optional, so any subset may be sent.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (SettingKey::cases() as $key) {
            $rules = [...$rules, ...$key->rules()];
        }

        return $rules;
    }

    /**
     * A key outside the registry is refused rather than ignored, and so is a body with no setting at all.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $sent = array_keys($this->validationData());

            foreach (array_diff($sent, array_column(SettingKey::cases(), 'value')) as $unknown) {
                $validator->errors()->add((string) $unknown, 'This setting does not exist.');
            }

            if ($sent === []) {
                $validator->errors()->add('settings', 'Send at least one setting.');
            }
        }];
    }

    public function toDto(): UpdateSettingsData
    {
        return UpdateSettingsData::fromValidated($this->validated());
    }
}
