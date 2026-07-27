<?php

declare(strict_types=1);

namespace SupportKBZ\SObject\Concerns;

use Closure;
use SupportKBZ\SObject\ConditionBuilder;

/**
 * Fournit les methodes where/orWhere/whereIn et la compilation SOQL des conditions.
 *
 * Chaque condition est stockee sous la forme d'un tableau associatif contenant
 * le type (AND|OR) et soit les triplets col/operator/value, soit un callback
 * pour les groupes de conditions imbriquees.
 */
trait BuildsWhereClauses
{
    /** @var array<int, array{type: string, col?: string, operator?: string, value?: mixed, callback?: Closure}> Conditions WHERE accumulees */
    protected array $where = [];

    /**
     * Ajoute une condition WHERE (AND) a la requete.
     *
     * Accepte soit une Closure pour les groupes imbriques, soit 2 ou 3 parametres :
     * - 2 params : `where('col', 'value')` → operateur `=` implicite.
     * - 3 params : `where('col', 'operator', 'value')` → operateur explicite.
     *
     * $col peut etre un nom de champ Salesforce ou une valeur de $map (nom mappe).
     *
     * @param  Closure|string  $col  Nom du champ (ou valeur de $map) ou closure de groupe.
     * @param  mixed  $operatorOrValue  Operateur SOQL ou valeur directe (2 params).
     * @param  mixed  $value  Valeur a comparer (3 params uniquement).
     */
    public function where(Closure|string $col, mixed $operatorOrValue = null, mixed $value = null): static
    {
        $this->where[] = $col instanceof Closure
            ? ['type' => 'AND', 'callback' => $col]
            : ['type' => 'AND'] + $this->resolveCondition($col, $operatorOrValue, $value);

        return $this;
    }

    /**
     * Ajoute une condition WHERE (OR) a la requete.
     *
     * - 2 params : `orWhere('col', 'value')` → operateur `=` implicite.
     * - 3 params : `orWhere('col', 'operator', 'value')` → operateur explicite.
     *
     * $col peut etre un nom de champ Salesforce ou une valeur de $map (nom mappe).
     *
     * @param  Closure|string  $col  Nom du champ (ou valeur de $map) ou closure de groupe.
     * @param  mixed  $operatorOrValue  Operateur SOQL ou valeur directe (2 params).
     * @param  mixed  $value  Valeur a comparer (3 params uniquement).
     */
    public function orWhere(Closure|string $col, mixed $operatorOrValue = null, mixed $value = null): static
    {
        $this->where[] = $col instanceof Closure
            ? ['type' => 'OR', 'callback' => $col]
            : ['type' => 'OR'] + $this->resolveCondition($col, $operatorOrValue, $value);

        return $this;
    }

    /**
     * Ajoute une condition WHERE … IN (…) (AND) a la requete.
     *
     * $col peut etre un nom de champ Salesforce ou une valeur de $map (nom mappe).
     *
     * @param  string  $col  Nom du champ (ou valeur de $map).
     * @param  mixed[]  $values  Liste de valeurs a tester.
     */
    public function whereIn(string $col, array $values): static
    {
        $this->where[] = ['type' => 'AND', 'col' => $this->resolveCol($col), 'operator' => 'IN', 'value' => $values];

        return $this;
    }

    /**
     * Ajoute une condition WHERE … IN (…) (OR) a la requete.
     *
     * $col peut etre un nom de champ Salesforce ou une valeur de $map (nom mappe).
     *
     * @param  string  $col  Nom du champ (ou valeur de $map).
     * @param  mixed[]  $values  Liste de valeurs a tester.
     */
    public function orWhereIn(string $col, array $values): static
    {
        $this->where[] = ['type' => 'OR', 'col' => $this->resolveCol($col), 'operator' => 'IN', 'value' => $values];

        return $this;
    }

    /**
     * Recupere les conditions WHERE accumulees.
     *
     * @return array<int, array{type: string, col?: string, operator?: string, value?: mixed, callback?: Closure}>
     */
    public function getWhere(): array
    {
        return $this->where;
    }

    /**
     * Resolue $col : si c'est une valeur du tableau $map, retourne la cle (champ Salesforce).
     *
     * @param  string  $col  Nom du champ ou nom mappe.
     * @return string Nom du champ Salesforce resolu.
     */
    private function resolveCol(string $col): string
    {
        if (! empty($this->map) && ($mapped = array_search($col, $this->map, true)) !== false) {
            return (string) $mapped;
        }

        return $col;
    }

    /**
     * Resolue les parametres en triplet col/operator/value selon leur nombre.
     *
     * $col est automatiquement resolu via $map si necessaire.
     *
     * @param  string  $col  Nom du champ (ou valeur de $map).
     * @param  mixed  $operatorOrValue  Operateur ou valeur directe.
     * @param  mixed  $value  Valeur explicite (null = appel a 2 params).
     * @return array{col: string, operator: string, value: mixed}
     */
    private function resolveCondition(string $col, mixed $operatorOrValue, mixed $value): array
    {
        $col = $this->resolveCol($col);

        if ($value === null) {
            $value = $operatorOrValue;
            $operatorOrValue = '=';
        }

        return ['col' => $col, 'operator' => $operatorOrValue, 'value' => $value];
    }

    /**
     * Compile les conditions accumulees en une chaine SOQL WHERE.
     *
     * @return string Clause WHERE prete a etre injectee dans la requete, ou chaine vide.
     */
    protected function buildWhereClause(): string
    {
        return $this->compileConditions($this->where);
    }

    /**
     * Compile recursivement un tableau de conditions en chaine SOQL.
     *
     * Les conditions avec callback sont enveloppees entre parentheses et
     * traitees recursivement pour supporter l'imbrication.
     *
     * @param  array<int, array{type: string, col?: string, operator?: string, value?: mixed, callback?: Closure}>  $conditions
     * @return string Clause compilee.
     */
    private function compileConditions(array $conditions): string
    {
        if (empty($conditions)) {
            return '';
        }

        $parts = [];
        foreach ($conditions as $index => $condition) {
            $prefix = $index === 0 ? '' : ' '.$condition['type'].' ';

            if (isset($condition['callback'])) {
                $builder = new ConditionBuilder($this->map);
                ($condition['callback'])($builder);
                $nestedClause = $this->compileConditions($builder->getWhere());

                if ($nestedClause !== '') {
                    $parts[] = $prefix.'('.$nestedClause.')';
                }

                continue;
            }

            if (! isset($condition['col'], $condition['operator'])) {
                continue;
            }

            $parts[] = $prefix.$this->formatCondition(
                $condition['col'],
                $condition['operator'],
                $condition['value'] ?? null,
            );
        }

        return implode('', $parts);
    }

    /**
     * Formate une condition simple en fragment SOQL encode.
     *
     * La valeur est echappee (htmlspecialchars + addslashes) puis encodee en
     * URL pour etre integree dans le parametre ?q= de l'API REST Salesforce.
     *
     * Pour l'operateur IN, $value doit etre un tableau ; chaque element est
     * echappe individuellement → `Status IN (%27Open%27, %27Pending%27)`.
     *
     * @param  string  $col  Nom du champ.
     * @param  string  $operator  Operateur SOQL.
     * @param  mixed|mixed[]  $value  Valeur scalaire ou tableau (operateur IN).
     * @return string Fragment SOQL encode.
     */
    private function formatCondition(string $col, string $operator, mixed $value): string
    {
        $col = addslashes($col);

        if ($operator === 'IN' && is_array($value)) {
            $escaped = array_map(
                fn (mixed $v): string => urlencode("'".addslashes(htmlspecialchars((string) $v))."'"),
                $value,
            );

            return $col.' IN ('.implode(', ', $escaped).')';
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } else {
            $value = urlencode("'".addslashes(htmlspecialchars((string) $value))."'");
        }

        return $col.' '.$operator.' '.$value;
    }
}
