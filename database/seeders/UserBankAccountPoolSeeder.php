<?php

namespace Database\Seeders;

use App\Models\UserBankAccountPool;
use Illuminate\Database\Seeder;

class UserBankAccountPoolSeeder extends Seeder
{
    public function run(): void
    {
        $records = [
            [
                'label' => 'Client Seed - Default',
                'iban' => 'PL96114010100000437966001005',
                'account_holder_name' => 'Paymoney',
                'bank_name' => 'Paymoney Assigned Account',
                'account_number' => null,
                'currency_code' => null,
                'swift_bic' => null,
                'country_code' => 'PL',
                'assignment_source' => 'client_seed',
                'status' => 1,
                'notes' => 'Seeded from client-provided IBAN list.',
                'meta' => [
                    'source' => 'client_chat',
                    'original_label' => null,
                ],
            ],
            [
                'label' => 'Client Seed - USD',
                'iban' => 'PL52114020040000310284574292',
                'account_holder_name' => 'Paymoney',
                'bank_name' => 'Paymoney Assigned Account',
                'account_number' => null,
                'currency_code' => 'USD',
                'swift_bic' => null,
                'country_code' => 'PL',
                'assignment_source' => 'client_seed',
                'status' => 1,
                'notes' => 'Client label: долларов США',
                'meta' => [
                    'source' => 'client_chat',
                    'original_label' => 'долларов США',
                ],
            ],
            [
                'label' => 'Client Seed - EUFO',
                'iban' => 'PL10249000050000453000075174',
                'account_holder_name' => 'Paymoney',
                'bank_name' => 'Paymoney Assigned Account',
                'account_number' => null,
                'currency_code' => null,
                'swift_bic' => null,
                'country_code' => 'PL',
                'assignment_source' => 'client_seed',
                'status' => 1,
                'notes' => 'Client label: EUFO',
                'meta' => [
                    'source' => 'client_chat',
                    'original_label' => 'EUFO',
                ],
            ],
            [
                'label' => 'Client Seed - CZ',
                'iban' => 'PL91124064491111001111913225',
                'account_holder_name' => 'Paymoney',
                'bank_name' => 'Paymoney Assigned Account',
                'account_number' => null,
                'currency_code' => null,
                'swift_bic' => null,
                'country_code' => 'CZ',
                'assignment_source' => 'client_seed',
                'status' => 1,
                'notes' => 'Client label: CZ',
                'meta' => [
                    'source' => 'client_chat',
                    'original_label' => 'CZ',
                ],
            ],
            [
                'label' => 'Client Seed - GBP',
                'iban' => 'PL37114011080000350588001007',
                'account_holder_name' => 'Paymoney',
                'bank_name' => 'Paymoney Assigned Account',
                'account_number' => null,
                'currency_code' => 'GBP',
                'swift_bic' => null,
                'country_code' => 'PL',
                'assignment_source' => 'client_seed',
                'status' => 1,
                'notes' => 'Client label: GBP',
                'meta' => [
                    'source' => 'client_chat',
                    'original_label' => 'GBP',
                ],
            ],
            [
                'label' => 'Client Seed - CHF',
                'iban' => 'PL7210901665200000001468885175',
                'account_holder_name' => 'Paymoney',
                'bank_name' => 'Paymoney Assigned Account',
                'account_number' => null,
                'currency_code' => 'CHF',
                'swift_bic' => null,
                'country_code' => 'PL',
                'assignment_source' => 'client_seed',
                'status' => 1,
                'notes' => 'Client label: CHF. Stored exactly as provided by client.',
                'meta' => [
                    'source' => 'client_chat',
                    'original_label' => 'CHF',
                ],
            ],
            [
                'label' => 'Client Seed - DKK',
                'iban' => 'PL05114011080000350588001001',
                'account_holder_name' => 'Paymoney',
                'bank_name' => 'Paymoney Assigned Account',
                'account_number' => null,
                'currency_code' => 'DKK',
                'swift_bic' => null,
                'country_code' => 'PL',
                'assignment_source' => 'client_seed',
                'status' => 1,
                'notes' => 'Client label: DKK',
                'meta' => [
                    'source' => 'client_chat',
                    'original_label' => 'DKK',
                ],
            ],
            [
                'label' => 'Client Seed - NOK',
                'iban' => 'PL72109014890000000048003393',
                'account_holder_name' => 'Paymoney',
                'bank_name' => 'Paymoney Assigned Account',
                'account_number' => null,
                'currency_code' => 'NOK',
                'swift_bic' => null,
                'country_code' => 'PL',
                'assignment_source' => 'client_seed',
                'status' => 1,
                'notes' => 'Client label: NOK',
                'meta' => [
                    'source' => 'client_chat',
                    'original_label' => 'NOK',
                ],
            ],
            [
                'label' => 'Client Seed - AED',
                'iban' => 'PL40109014890000000048087486',
                'account_holder_name' => 'Paymoney',
                'bank_name' => 'Paymoney Assigned Account',
                'account_number' => null,
                'currency_code' => 'AED',
                'swift_bic' => null,
                'country_code' => 'PL',
                'assignment_source' => 'client_seed',
                'status' => 1,
                'notes' => 'Client label: AED',
                'meta' => [
                    'source' => 'client_chat',
                    'original_label' => 'AED',
                ],
            ],
            [
                'label' => 'Client Seed - DE',
                'iban' => 'PL48175013120000000038863557',
                'account_holder_name' => 'Paymoney',
                'bank_name' => 'Paymoney Assigned Account',
                'account_number' => null,
                'currency_code' => null,
                'swift_bic' => null,
                'country_code' => 'DE',
                'assignment_source' => 'client_seed',
                'status' => 1,
                'notes' => 'Client label: DE',
                'meta' => [
                    'source' => 'client_chat',
                    'original_label' => 'DE',
                ],
            ],
        ];

        foreach ($records as $record) {
            UserBankAccountPool::updateOrCreate(
                ['iban' => $record['iban']],
                $record
            );
        }
    }
}
