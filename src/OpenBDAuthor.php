<?php
/**
 * bookbok/openbd-book-info-scraper
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright (c) BookBok
 * @license MIT
 * @since 1.0.0
 */
namespace BookBok\BookInfoScraper\OpenBD;

use BookBok\BookInfoScraper\Information\Author;

/**
 *
 */
class OpenBDAuthor extends Author{

    /**
     * Constructor.
     *
     * @param string        $name
     * @param string[]|null $roles
     */
    public function __construct(string $name, ?array $roles){
        parent::__construct($name);

        if(null !== $roles){
            $roleList   = array_filter(
                array_map(
                    function($v){
                        return (is_string($v) && "" !== $v) ? $v : null;
                    },
                    $roles
                ),
                "is_string"
            );

            if(!empty($roleList)){
                $this->setRoles($roleList);
            }
        }
    }
}
