<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * @property int $id
 * @property string|null $holder_type
 * @property int|null $holder_id
 * @property \App\Enums\AccountType $type
 * @property string $currency
 * @property \App\Enums\PaymentMode $mode
 * @property array<array-key, mixed>|null $metadata
 * @property int $balance
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LedgerEntry> $entries
 * @property-read int|null $entries_count
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $holder
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereHolderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereHolderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereUpdatedAt($value)
 */
	final class Account extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $ledger_account_id
 * @property int $balance
 * @property int $last_entry_id
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance whereBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance whereLastEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance whereLedgerAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountBalance whereUpdatedAt($value)
 */
	final class AccountBalance extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\OneTimePasswords\Models\OneTimePassword> $oneTimePasswords
 * @property-read int|null $one_time_passwords_count
 * @method static \Database\Factories\AdminFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Admin whereUpdatedAt($value)
 */
	final class Admin extends \Eloquent implements \Filament\Models\Contracts\FilamentUser {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property \App\Enums\AccessLevel $access_level
 * @property \App\Enums\PaymentMode $mode
 * @property string $lookup_key
 * @property string $auth_key
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Business $business
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereAccessLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereAuthKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereLookupKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereUpdatedAt($value)
 */
	final class ApiToken extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $intent_type
 * @property int $intent_id
 * @property int $provider_id
 * @property \App\Enums\FeeChannel $channel
 * @property string $provider_reference
 * @property \App\Enums\AuthorizationStatus $status
 * @property \Carbon\CarbonImmutable|null $completed_at
 * @property int $fee
 * @property int $provider_fee
 * @property int $amount
 * @property string $currency
 * @property array<array-key, mixed>|null $raw_request
 * @property array<array-key, mixed>|null $raw_response
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read string|null $action
 * @property-read array $authorization
 * @property-read bool $completed
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $intent
 * @property-read \App\Models\Provider $provider
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt pending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt validating()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereCompletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereIntentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereIntentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereProviderFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereProviderReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereRawRequest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereRawResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereUpdatedAt($value)
 */
	final class AuthorizationAttempt extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $business_id
 * @property string $account_number
 * @property string $account_name
 * @property \App\Enums\Currency $currency
 * @property string $bank_code
 * @property string|null $verified_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payout> $payouts
 * @property-read int|null $payouts_count
 * @method static \Database\Factories\BankAccountFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereAccountName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereBankCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BankAccount whereVerifiedAt($value)
 */
	final class BankAccount extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $owner_id
 * @property string $name
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property string|null $webhook_url
 * @property \Carbon\CarbonImmutable|null $verified_at
 * @property int|null $bank_account_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ApiToken> $apiTokens
 * @property-read int|null $api_tokens_count
 * @property-read \App\Models\BankAccount|null $bankAccount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankAccount> $bankAccounts
 * @property-read int|null $bank_accounts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Account> $ledgerAccounts
 * @property-read int|null $ledger_accounts_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\OneTimePasswords\Models\OneTimePassword> $oneTimePasswords
 * @property-read int|null $one_time_passwords_count
 * @property-read \App\Models\User $owner
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\BusinessFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereBankAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereOwnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereWebhookUrl($value)
 */
	final class Business extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Business $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Account> $ledgerAccounts
 * @property-read int|null $ledger_accounts_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentIntent> $paymentIntents
 * @property-read int|null $payment_intents_count
 * @method static \Database\Factories\CustomerFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Customer whereUpdatedAt($value)
 */
	final class Customer extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $business_id
 * @property \App\Enums\FeeChannel $channel
 * @property string $currency
 * @property int $min_fee
 * @property int|null $max_fee
 * @property numeric $percentage
 * @property int $fixed_amount
 * @property bool $is_active
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Business|null $business
 * @method static \Database\Factories\FeeConfigFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereFixedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereMaxFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereMinFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig wherePercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereUpdatedAt($value)
 */
	final class FeeConfig extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int|null $transaction_id
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $posted_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Transaction|null $transaction
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch wherePostedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch whereUpdatedAt($value)
 */
	final class LedgerBatch extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $ledger_account_id
 * @property int|null $transaction_id
 * @property string|null $reference
 * @property int $amount
 * @property \App\Enums\EntryDirection $direction
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int|null $ledger_batch_id
 * @property-read \App\Models\Account $account
 * @property-read \App\Models\LedgerBatch|null $batch
 * @property-read \App\Models\Transaction|null $transaction
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereDirection($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereLedgerAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereLedgerBatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerEntry whereUpdatedAt($value)
 */
	final class LedgerEntry extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int $customer_id
 * @property int $amount
 * @property int $amount_paid
 * @property \App\Enums\Currency $currency
 * @property string $reference
 * @property \App\Enums\PaymentStatus $status
 * @property \App\Enums\FeeBearer $bearer
 * @property \App\Enums\PaymentMode $mode
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuthorizationAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read \App\Models\Business $business
 * @property-read \App\Models\Customer $customer
 * @property-read \App\Models\Transaction|null $transaction
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static \Database\Factories\PaymentIntentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereBearer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereUpdatedAt($value)
 */
	final class PaymentIntent extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property int|null $provider_id
 * @property string $originator_type
 * @property int $originator_id
 * @property string|null $provider_reference
 * @property int $amount
 * @property int $fee
 * @property \App\Enums\Currency $currency
 * @property \App\Enums\PaymentMode $mode
 * @property \App\Enums\PayoutStatus $status
 * @property string $reference
 * @property bool $requires_otp
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int|null $bank_account_id
 * @property-read \App\Models\BankAccount|null $bankAccount
 * @property-read \App\Models\Business $business
 * @property-read mixed $net_amount
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $originator
 * @property-read \App\Models\Provider|null $provider
 * @property-read \App\Models\Transaction|null $transaction
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereBankAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereOriginatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereOriginatorType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereProviderReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereRequiresOtp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereUpdatedAt($value)
 */
	final class Payout extends \Eloquent implements \App\Contracts\Recordable {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $identifier
 * @property \App\Enums\PaymentMode $mode
 * @property bool $is_active
 * @property bool $is_payout_enabled
 * @property bool $is_healthy
 * @property array<array-key, mixed> $supported_channels
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @method static \Database\Factories\ProviderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider forPayout(\App\Enums\PaymentMode $mode)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereIdentifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereIsHealthy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereIsPayoutEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereSupportedChannels($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereUpdatedAt($value)
 */
	final class Provider extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $business_id
 * @property string $source_type
 * @property int $source_id
 * @property int $amount
 * @property int $fee
 * @property \App\Enums\Currency $currency
 * @property \App\Enums\TransactionStatus $status
 * @property string $reference
 * @property \App\Enums\PaymentMode $mode
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property \App\Enums\PaymentChannel $channel
 * @property-read \App\Models\Business $business
 * @property-read mixed $gross_amount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LedgerEntry> $ledgerEntries
 * @property-read int|null $ledger_entries_count
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $source
 * @method static \Database\Factories\TransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUpdatedAt($value)
 */
	final class Transaction extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Business> $businesses
 * @property-read int|null $businesses_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	final class User extends \Eloquent implements \Filament\Models\Contracts\FilamentUser, \Filament\Models\Contracts\HasTenants, \Illuminate\Contracts\Auth\MustVerifyEmail {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $provider
 * @property string|null $provider_event_id
 * @property string|null $event_type
 * @property array<array-key, mixed> $raw_payload
 * @property \Carbon\CarbonImmutable $received_at
 * @property \Carbon\CarbonImmutable|null $processed_at
 * @property string|null $feedback
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereFeedback($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereProviderEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereRawPayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereReceivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereUpdatedAt($value)
 */
	final class WebhookEvent extends \Eloquent {}
}

