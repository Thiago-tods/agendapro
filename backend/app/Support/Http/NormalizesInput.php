<?php

namespace App\Support\Http;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Input normalization shared by Form Requests, applied before validation.
 *
 * @mixin FormRequest
 */
trait NormalizesInput
{
    /** @param  list<string>  $fields  Phone fields: keep digits only; empty becomes null. */
    protected function normalizePhones(array $fields): void
    {
        foreach ($fields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $digits = preg_replace('/\D/', '', $this->input($field));
                $this->merge([$field => $digits === '' ? null : $digits]);
            }
        }
    }

    /** @param  list<string>  $fields  E-mail fields: trimmed and lower-cased; empty becomes null. */
    protected function normalizeEmails(array $fields): void
    {
        foreach ($fields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $email = mb_strtolower(trim($this->input($field)));
                $this->merge([$field => $email === '' ? null : $email]);
            }
        }
    }

    /** Hex colors are stored upper-case (#RRGGBB). */
    protected function normalizeColor(string $field = 'color'): void
    {
        if ($this->has($field) && is_string($this->input($field))) {
            $this->merge([$field => strtoupper(trim($this->input($field)))]);
        }
    }

    /** Whether this is a partial update (PATCH): rules then apply only to present fields. */
    protected function isPartial(): bool
    {
        return $this->isMethod('PATCH');
    }

    /**
     * Required on create; on PATCH only validated (and still non-empty) when sent.
     *
     * @param  list<mixed>  $rules
     * @return list<mixed>
     */
    protected function requiredUnlessPartial(array $rules): array
    {
        return $this->isPartial() ? ['sometimes', 'required', ...$rules] : ['required', ...$rules];
    }
}
