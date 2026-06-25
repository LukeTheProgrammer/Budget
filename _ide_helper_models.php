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
 * @property int $user_id
 * @property string $name
 * @property string|null $last_four
 * @property string $currency
 * @property\Illuminate\Support\Carbon|null $created_at
 * @property\Illuminate\Support\Carbon|null $updated_at
 * @property\Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\AccountFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereLastFour($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account withoutTrashed()
 */
	class Account extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $color
 * @property\Illuminate\Support\Carbon|null $created_at
 * @property\Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Merchant> $merchants
 * @property-read int|null $merchants_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\CategoryFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereUserId($value)
 */
	class Category extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int|null $category_id
 * @property string $name
 * @property\Illuminate\Support\Carbon|null $created_at
 * @property\Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MerchantAlias> $aliases
 * @property-read int|null $aliases_count
 * @property-read \App\Models\Category|null $category
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\MerchantFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Merchant whereUserId($value)
 */
	class Merchant extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property int $merchant_id
 * @property string $name
 * @property string $normalized_name
 * @property\Illuminate\Support\Carbon|null $created_at
 * @property\Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Merchant $merchant
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\MerchantAliasFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantAlias newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantAlias newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantAlias query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantAlias whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantAlias whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantAlias whereMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantAlias whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantAlias whereNormalizedName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantAlias whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MerchantAlias whereUserId($value)
 */
	class MerchantAlias extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $account_id
 * @property int|null $merchant_id
 * @property int $amount_cents
 * @property string $currency
 * @property string|null $description
 * @property\Illuminate\Support\Carbon $posted_at
 * @property string|null $import_hash
 * @property\Illuminate\Support\Carbon|null $created_at
 * @property\Illuminate\Support\Carbon|null $updated_at
 * @property\Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Account|null $account
 * @property-read \App\Models\Category|null $category
 * @property-read \App\Models\Merchant|null $merchant
 * @method static \Database\Factories\TransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction spendingByCategory(int $userId, $start, $end)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAmountCents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereImportHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMerchantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePostedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction withoutTrashed()
 */
	class Transaction extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Carbon\CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property \Carbon\CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Account> $accounts
 * @property-read int|null $accounts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Category> $categories
 * @property-read int|null $categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Merchant> $merchants
 * @property-read int|null $merchants_count
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Laravel\Passkeys\Passkey> $passkeys
 * @property-read int|null $passkeys_count
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorConfirmedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorRecoveryCodes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereTwoFactorSecret($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 */
	class User extends \Eloquent implements \Laravel\Fortify\Contracts\PasskeyUser, \Laravel\Passkeys\Contracts\PasskeyUser {}
}

