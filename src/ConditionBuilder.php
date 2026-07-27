<?php

declare(strict_types=1);

namespace SupportKBZ\SObject;

use SupportKBZ\SObject\Concerns\BuildsWhereClauses;

/**
 * Builder leger pour construire des groupes de conditions SOQL imbriquees.
 *
 * Utilise exclusivement comme argument du callback passe a where(Closure)
 * ou orWhere(Closure). Les conditions accumulees sont ensuite compilees
 * par BuildsWhereClauses::compileConditions().
 *
 * Exemple :
 * ```php
 * $helper->where(function (ConditionBuilder $q) {
 *     $q->where('status', '=', 'Open')->orWhere('status', '=', 'Pending');
 * });
 * // → WHERE (Status__c = 'Open' OR Status__c = 'Pending')
 * ```
 */
class ConditionBuilder
{
    use BuildsWhereClauses;

    /** @var array<string, string> Tableau de correspondance champ Salesforce → nom mappe */
    public array $map = [];

    /**
     * @param  array<string, string>  $map  Tableau $map transmis lors de la compilation.
     */
    public function __construct(array $map = [])
    {
        $this->map = $map;
    }
}
