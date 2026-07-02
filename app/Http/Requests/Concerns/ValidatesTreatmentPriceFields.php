<?php

namespace App\Http\Requests\Concerns;

use App\Rules\SupportedCurrency;
use Illuminate\Validation\Validator;

trait ValidatesTreatmentPriceFields
{
    protected function prepareTreatmentPriceFieldsForValidation(): void
    {
        $merge = [];

        if ($this->has('treatment_price') && $this->input('treatment_price') === '') {
            $merge['treatment_price'] = null;
        }

        if ($this->has('treatment_price_currency')) {
            $currency = $this->input('treatment_price_currency');

            $merge['treatment_price_currency'] = ($currency === null || $currency === '')
                ? null
                : strtoupper(trim((string) $currency));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function treatmentPriceFieldRules(): array
    {
        return [
            'treatment_price' => ['nullable', 'numeric', 'min:0.01', 'decimal:0,2'],
            'treatment_price_currency' => ['nullable', 'string', 'size:3', new SupportedCurrency],
            'requires_nurse_commission' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function treatmentPriceFieldMessages(): array
    {
        return [
            'treatment_price.min' => 'The treatment price must be greater than zero.',
            'treatment_price.decimal' => 'The treatment price may have at most two decimal places.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $data = $validator->getData();
            $price = $data['treatment_price'] ?? null;
            $currency = $data['treatment_price_currency'] ?? null;
            $hasPrice = $price !== null && $price !== '';
            $hasCurrency = $currency !== null && $currency !== '';

            if ($hasPrice && ! $hasCurrency) {
                $validator->errors()->add(
                    'treatment_price_currency',
                    'Please select a currency for the treatment price.',
                );
            }

            if (! $hasPrice && $hasCurrency) {
                $validator->errors()->add(
                    'treatment_price',
                    'Please enter a treatment price.',
                );
            }

            $requiresCommission = filter_var($data['requires_nurse_commission'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($requiresCommission) {
                if (! $hasPrice) {
                    $validator->errors()->add(
                        'treatment_price',
                        'A treatment price is required when nurse commission is required.',
                    );
                }

                if (! $hasCurrency) {
                    $validator->errors()->add(
                        'treatment_price_currency',
                        'A currency is required when nurse commission is required.',
                    );
                }
            }
        });
    }
}
