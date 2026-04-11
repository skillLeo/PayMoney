<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // 1. Add Visa as a payment gateway (for deposit / add-fund)
        // ---------------------------------------------------------------
        $existingGateway = DB::table('gateways')->where('code', 'visa')->first();

        if (!$existingGateway) {
            // id must be < 1000 so it is treated as an automatic (non-manual) gateway
            // Pick a slot beyond existing automatic gateways
            $nextId = (DB::table('gateways')->where('id', '<', 1000)->max('id') ?? 39) + 1;

            DB::table('gateways')->insert([
                'id'     => $nextId,
                'code'   => 'visa',
                'name'   => 'Visa',
                'status' => 1,
                'is_sandbox'  => 1,
                'is_manual'   => 0,
                'environment' => 'test',
                'currency_type' => 1,

                // Credentials – admin can update these through the admin panel.
                // Default values come from environment variables set at deploy time.
                'parameters' => json_encode([
                    'username'    => env('VISA_USERNAME', ''),
                    'password'    => env('VISA_PASSWORD', ''),
                    'x_pay_token' => env('VISA_X_PAY_TOKEN', ''),
                    'key_id'      => env('VISA_KEY_ID', ''),
                ]),

                'currencies' => '{"0":{"USD":"USD","EUR":"EUR","GBP":"GBP"}}',
                'supported_currency' => '["USD","EUR","GBP"]',

                'receivable_currencies' => json_encode([
                    [
                        'name'               => 'USD',
                        'currency_symbol'    => 'USD',
                        'conversion_rate'    => '1',
                        'min_limit'          => '10',
                        'max_limit'          => '10000',
                        'percentage_charge'  => '2',
                        'fixed_charge'       => '0.50',
                    ],
                    [
                        'name'               => 'EUR',
                        'currency_symbol'    => 'EUR',
                        'conversion_rate'    => '0.92',
                        'min_limit'          => '10',
                        'max_limit'          => '10000',
                        'percentage_charge'  => '2',
                        'fixed_charge'       => '0.50',
                    ],
                    [
                        'name'               => 'GBP',
                        'currency_symbol'    => 'GBP',
                        'conversion_rate'    => '0.79',
                        'min_limit'          => '10',
                        'max_limit'          => '10000',
                        'percentage_charge'  => '2',
                        'fixed_charge'       => '0.50',
                    ],
                ]),

                'description' => 'Pay securely with your Visa card via Visa Direct.',
                'note'        => 'Sandbox / test mode – uses Visa Developer sandbox credentials.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // ---------------------------------------------------------------
        // 2. Add Visa as a virtual card provider
        // ---------------------------------------------------------------
        $existingVcm = DB::table('virtual_card_methods')->where('code', 'visa')->first();

        if (!$existingVcm) {
            DB::table('virtual_card_methods')->insert([
                'code'         => 'visa',
                'name'         => 'Visa',
                'image'        => '',
                'image_driver' => 'local',
                'status'       => 1,           // Active by default

                // API credentials stored in parameters
                'parameters' => json_encode([
                    'username'    => env('VISA_USERNAME', ''),
                    'password'    => env('VISA_PASSWORD', ''),
                    'x_pay_token' => env('VISA_X_PAY_TOKEN', ''),
                    'key_id'      => env('VISA_KEY_ID', ''),
                ]),

                'currencies'    => json_encode([['USD' => 'USD']]),
                'debit_currency'=> 'USD',

                // Add-fund charge settings
                'add_fund_parameter' => json_encode([
                    'USD' => [
                        'MinimumAmount' => [
                            'field_name'  => 'MinimumAmount',
                            'field_level' => 'Minimum Amount',
                            'field_value' => '10',
                            'type'        => 'text',
                            'validation'  => 'required',
                        ],
                        'MaximumAmount' => [
                            'field_name'  => 'MaximumAmount',
                            'field_level' => 'Maximum Amount',
                            'field_value' => '5000',
                            'type'        => 'text',
                            'validation'  => 'required',
                        ],
                        'PercentCharge' => [
                            'field_name'  => 'PercentCharge',
                            'field_level' => 'Percent Charge',
                            'field_value' => '2',
                            'type'        => 'text',
                            'validation'  => 'required',
                        ],
                        'FixedCharge' => [
                            'field_name'  => 'FixedCharge',
                            'field_level' => 'Fixed Charge',
                            'field_value' => '0.50',
                            'type'        => 'text',
                            'validation'  => 'required',
                        ],
                        'OpeningAmount' => [
                            'field_name'  => 'OpeningAmount',
                            'field_level' => 'Opening Amount',
                            'field_value' => '10',
                            'type'        => 'text',
                            'validation'  => 'required',
                        ],
                    ],
                ]),

                // KYC / registration fields the user must fill before card issuance
                'form_field' => json_encode([
                    'FullName' => [
                        'field_name'  => 'FullName',
                        'field_level' => 'Full Name',
                        'field_place' => 'John Doe',
                        'type'        => 'text',
                        'validation'  => 'required',
                    ],
                    'CustomerEmail' => [
                        'field_name'  => 'CustomerEmail',
                        'field_level' => 'Email Address',
                        'field_place' => 'john.doe@example.com',
                        'type'        => 'email',
                        'validation'  => 'required',
                    ],
                    'PhoneNumber' => [
                        'field_name'  => 'PhoneNumber',
                        'field_level' => 'Phone Number',
                        'field_place' => '+14155551234',
                        'type'        => 'text',
                        'validation'  => 'required',
                    ],
                    'Line1' => [
                        'field_name'  => 'Line1',
                        'field_level' => 'Address Line 1',
                        'field_place' => '123 Main St',
                        'type'        => 'text',
                        'validation'  => 'required',
                    ],
                    'City' => [
                        'field_name'  => 'City',
                        'field_level' => 'City',
                        'field_place' => 'San Francisco',
                        'type'        => 'text',
                        'validation'  => 'required',
                    ],
                    'State' => [
                        'field_name'  => 'State',
                        'field_level' => 'State',
                        'field_place' => 'CA',
                        'type'        => 'text',
                        'validation'  => 'required',
                    ],
                    'PostalCode' => [
                        'field_name'  => 'PostalCode',
                        'field_level' => 'Postal / ZIP Code',
                        'field_place' => '94404',
                        'type'        => 'text',
                        'validation'  => 'required',
                    ],
                    'CountryCode' => [
                        'field_name'  => 'CountryCode',
                        'field_level' => 'Country Code (2-letter)',
                        'field_place' => 'US',
                        'type'        => 'text',
                        'validation'  => 'required',
                    ],
                    'DateOfBirth' => [
                        'field_name'  => 'DateOfBirth',
                        'field_level' => 'Date of Birth',
                        'field_place' => '1990-01-15',
                        'type'        => 'date',
                        'validation'  => 'required',
                    ],
                ]),

                'currency' => json_encode(['USD']),
                'symbol'   => json_encode(['$']),

                'info_box' => "Visa Virtual Cards are provisioned through the Visa Developer Platform.\r\n"
                    . "Your virtual card details (number, CVV, expiry) will be available immediately after approval.\r\n"
                    . "Please provide accurate personal information for KYC verification.",

                'alert_message' => null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Deactivate all other virtual card methods so Visa is the active one
            DB::table('virtual_card_methods')
                ->where('code', '!=', 'visa')
                ->update(['status' => 0]);
        }
    }

    public function down(): void
    {
        DB::table('gateways')->where('code', 'visa')->delete();
        DB::table('virtual_card_methods')->where('code', 'visa')->delete();
    }
};
