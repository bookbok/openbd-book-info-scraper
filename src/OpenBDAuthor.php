<?php
/**
 * kentoka/openbd-book-info-scraper
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.
 * Redistributions of files must retain the above copyright notice.
 *
 * @author      Kento Oka <kento-oka@kentoka.com>
 * @copyright   (c) Kento Oka
 * @license     MIT
 * @since       1.0.0
 */
namespace Kentoka\BookScraper\OpenBD;

use Kentoka\BookInfoScraper\Information\Author;

/**
 *
 */
class OpenBDAuthor extends Author{

    /**
     * OpenBDでは使われていない役割もあると思われるが、念のために全部載せておく
     */
    public const ROLE_LIST  = [
        "A01" => "著",
        "A03" => "脚本",
        "A06" => "作曲",
        "B01" => "編集",
        "B20" => "監修",
        "B06" => "翻訳",
        "A12" => "イラスト",
        "A38" => "原著",
        "A10" => "企画・原案",
        "A08" => "写真",
        "A21" => "解説",
        "E07" => "朗読",
    ];

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
                        if(is_string($v) && isset(OpenBDAuthor::ROLE_LIST[$v])){
                            return OpenBDAuthor::ROLE_LIST[$v];
                        }

                        return null;
                    },
                    $roles
                ),
                "is_string"
            );

            if(!empty($role)){
                $this->setRoles($roleList);
            }
        }
    }
}
