<?php

namespace App\Services\Statements;

final class MerchantDeriver
{
    private const LEGAL_SUFFIX = '/[\s,]+(?:&|G\s?m\s?b\s?H|AG|AB|B\.\s?V\.?|SE|KG|S\.C\.A)[\s.,]*$/iu';

    /**
     * Derive a short, readable merchant name from a booking.
     */
    public function derive(string $type, ?string $counterparty, string $purpose): string
    {
        $merchant = match (true) {
            $counterparty === null || $counterparty === '' => $this->fromPurpose($type, $purpose),
            (bool) preg_match('/^PayPal\b/i', $counterparty) => $this->fromPayPal($counterparty, $purpose),
            str_starts_with($counterparty, 'VISA ') => $this->fromVisa($counterparty),
            default => $counterparty,
        };

        return $this->stripLegalSuffixes($merchant);
    }

    private function fromPurpose(string $type, string $purpose): string
    {
        $line = trim(explode("\n", $purpose)[0]);

        return rtrim(mb_substr($line === '' ? $type : $line, 0, 60));
    }

    private function fromPayPal(string $counterparty, string $purpose): string
    {
        $text = implode(' ', explode("\n", $purpose));

        if (preg_match('/Ihr\s*Einkauf\s*bei\s+(.+?)(?:\s+Mandat:|\s+Referenz:|$)/u', $text, $matches)) {
            return $matches[1];
        }

        return $counterparty;
    }

    private function fromVisa(string $counterparty): string
    {
        $merchant = substr($counterparty, strlen('VISA '));
        $merchant = preg_replace('/^(PAYPAL\s*\*|UZR\*)\s*/i', '', $merchant);
        $merchant = trim(preg_replace('/\*.*$/', '', $merchant));
        $merchant = preg_replace('/\s+FILIALE\s+\d+$/i', '', $merchant);
        $merchant = trim(preg_replace('/,.*$/', '', $merchant));

        return preg_replace('/\s+\d+$/', '', $merchant);
    }

    private function stripLegalSuffixes(string $merchant): string
    {
        do {
            $before = $merchant;
            $merchant = preg_replace(self::LEGAL_SUFFIX, '', $merchant);
        } while ($merchant !== $before);

        return trim($merchant, ' ,.&');
    }
}
