<?php

namespace App\Enums;

/**
 * The dosage form named in a product name: prices are only comparable within one form,
 * a suspension and tablets of the same drug are different products.
 */
enum DosageForm: string
{
    case Tablets = 'tablets';
    case Capsules = 'capsules';
    case Syrup = 'syrup';
    case Suspension = 'suspension';
    case Injection = 'injection';
    case Drops = 'drops';
    case Spray = 'spray';
    case Suppositories = 'suppositories';
    case Ointment = 'ointment';
    case Powder = 'powder';
    case Solution = 'solution';

    /**
     * The form as the visitor reads it, e.g. «таблетки».
     */
    public function label(): string
    {
        return __("prices.dosage_forms.{$this->value}");
    }
}
