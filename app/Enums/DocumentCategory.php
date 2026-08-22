<?php

namespace App\Enums;

/**
 * What kind of paper a company document is.
 *
 * A fixed list rather than free text, for the reason ExpenseCategory gives:
 * "Trade licence", "trade license" and "TL" typed by three people would split
 * one shelf of the filing cabinet into three.
 *
 * The list is shaped for a Bangladeshi company — trade licence, TIN, BIN/VAT,
 * fire and factory licences are the ones that actually lapse and cost money.
 */
enum DocumentCategory: string
{
    case Licence = 'licence';
    case Tax = 'tax';
    case Registration = 'registration';
    case Bank = 'bank';
    case Insurance = 'insurance';
    case Contract = 'contract';
    case Property = 'property';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Licence => 'Licence & permit',
            self::Tax => 'Tax & VAT',
            self::Registration => 'Registration',
            self::Bank => 'Bank & finance',
            self::Insurance => 'Insurance',
            self::Contract => 'Contract & agreement',
            self::Property => 'Property & tenancy',
            self::Other => 'Other',
        };
    }

    /**
     * A line of help under the category, naming the papers people actually
     * hold — somebody filing a BIN certificate should not have to guess
     * whether that is Tax or Registration.
     */
    public function hint(): string
    {
        return match ($this) {
            self::Licence => 'Trade licence, fire licence, factory licence',
            self::Tax => 'TIN certificate, BIN/VAT registration, tax returns',
            self::Registration => 'Incorporation, memorandum, RJSC filings',
            self::Bank => 'Account mandates, loan agreements, guarantees',
            self::Insurance => 'Fire, vehicle, goods-in-transit policies',
            self::Contract => 'Distributor and supplier agreements',
            self::Property => 'Tenancy agreements, deeds, utility connections',
            self::Other => 'Anything that does not fit above',
        };
    }

    /**
     * Whether this kind of paper usually carries a renewal date.
     *
     * Only used to nudge on the form — a licence with no expiry typed in is
     * more likely an omission than a permanent licence, while an incorporation
     * certificate genuinely never expires.
     */
    public function usuallyExpires(): bool
    {
        return match ($this) {
            self::Licence, self::Tax, self::Insurance => true,
            self::Registration, self::Bank, self::Contract, self::Property, self::Other => false,
        };
    }

    /**
     * @return array<int, array{value: string, label: string, hint: string, usuallyExpires: bool}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $category) => [
                'value' => $category->value,
                'label' => $category->label(),
                'hint' => $category->hint(),
                'usuallyExpires' => $category->usuallyExpires(),
            ],
            self::cases(),
        );
    }
}
