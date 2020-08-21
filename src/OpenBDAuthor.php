<?php

namespace BookBok\BookInfoScraper\OpenBD;

use BookBok\BookInfoScraper\Information\ImmutableAuthor;

class OpenBDAuthor extends ImmutableAuthor
{
    /** @var string|null */
    protected $biography;

    /**
     * 著者略歴を設定した新しいインスタンスを返す。
     *
     * @param string|null $biography 著者略歴。
     *
     * @return OpenBDAuthor
     */
    public function withBiography(?string $biography): OpenBDAuthor
    {
        $convertedBiography = $biography;

        if ($biography !== null) {
            if ($biography === "") {
                throw new \InvalidArgumentException("Cannot set empty string.");
            }

            $convertedBiography = preg_replace("/\r\n|\r|\n/", "\n", $biography);
        }

        $cloneAuthor = clone $this;
        $cloneAuthor->biography = $convertedBiography;

        return $cloneAuthor;
    }
}
