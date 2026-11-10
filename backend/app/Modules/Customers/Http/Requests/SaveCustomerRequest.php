<?php

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Models\Customer;
use App\Support\Http\NormalizesInput;
use Illuminate\Foundation\Http\FormRequest;

/** POST creates (name required); PATCH updates only the fields sent. */
class SaveCustomerRequest extends FormRequest
{
    use NormalizesInput;

    /** Runs before validation, so unauthorized users get 403 without learning the rules. */
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            ? $this->user()->can('update', $customer)
            : $this->user()->can('create', [Customer::class, $this->route('company')]);
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhones(['phone']);
        $this->normalizeEmails(['email']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => $this->requiredUnlessPartial(['string', 'min:2', 'max:120']),
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'digits_between:10,13'],
            'birth_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'email' => 'e-mail',
            'phone' => 'telefone',
            'birth_date' => 'data de nascimento',
            'notes' => 'observações',
        ];
    }
}
