<?php

namespace App\Infrastructure\Config;

use App\Domain\Prizes\PrizeRuleProvider;
use App\Domain\Prizes\PrizeRuleSet;
use App\Domain\Prizes\PrizeTier;
use InvalidArgumentException;

final class ConfiguredPrizeRuleProvider implements PrizeRuleProvider
{
    /** @var array<string, PrizeRuleSet> */
    private array $resolvedRules = [];

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function __construct(private readonly array $configuration) {}

    public function forSector(string $sectorKey): PrizeRuleSet
    {
        $ruleKey = $this->configuration['sector_rule_keys'][$sectorKey] ?? null;

        if (! is_string($ruleKey) || $ruleKey === '') {
            throw new InvalidArgumentException("No prize rule is configured for sector [{$sectorKey}].");
        }

        if (isset($this->resolvedRules[$ruleKey])) {
            return $this->resolvedRules[$ruleKey];
        }

        $rule = $this->configuration['rule_sets'][$ruleKey] ?? null;

        if (! is_array($rule)) {
            throw new InvalidArgumentException("The prize rule [{$ruleKey}] is not configured.");
        }

        $version = $rule['version'] ?? null;
        $currency = $rule['currency'] ?? null;
        $configuredTiers = $rule['tiers'] ?? null;

        if (! is_string($version) || ! is_string($currency) || ! is_array($configuredTiers)) {
            throw new InvalidArgumentException("The prize rule [{$ruleKey}] is malformed.");
        }

        $tiers = [];

        foreach ($configuredTiers as $configuredTier) {
            if (! is_array($configuredTier)
                || ! is_int($configuredTier['minimum_squares'] ?? null)
                || ! is_int($configuredTier['pool_cents'] ?? null)) {
                throw new InvalidArgumentException("A tier in prize rule [{$ruleKey}] is malformed.");
            }

            $tiers[] = new PrizeTier(
                minimumSquares: $configuredTier['minimum_squares'],
                poolCents: $configuredTier['pool_cents'],
            );
        }

        return $this->resolvedRules[$ruleKey] = new PrizeRuleSet(
            version: $version,
            currency: $currency,
            tiers: $tiers,
        );
    }
}
