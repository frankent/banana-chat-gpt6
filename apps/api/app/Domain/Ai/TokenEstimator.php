<?php
namespace App\Domain\Ai;
class TokenEstimator {
    public function estimate(string $text, float $ratio = 1.0): int {
        $cjk = preg_match_all('/[\x{0E00}-\x{0E7F}\x{3000}-\x{9FFF}]/u', $text);
        $other = max(0, mb_strlen($text) - $cjk);
        return (int) ceil((($cjk / 1.2) + ($other / 3.5)) * 1.1 * max(.5, $ratio));
    }
}
