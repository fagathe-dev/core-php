<?php
namespace Fagathe\CorePhp\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class StringExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('word_wrap', [$this, 'wordWrap']),
        ];
    }

    public function wordWrap(string $str, int $width = 75, string $break = "\n", bool $cut_long_words = false): string
    {
        return wordwrap($str, $width, $break, $cut_long_words);
    }

}