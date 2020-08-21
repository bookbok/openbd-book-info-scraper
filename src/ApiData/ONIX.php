<?php

namespace BookBok\BookInfoScraper\OpenBD\ApiData;

use BookBok\BookInfoScraper\Information\ImmutableAuthor;

class ONIX
{
    /** @var string [著者区分] 著・文・その他 */
    public const CONTRIBUTOR_ROLE_AUTHOR = "A01";
    /** @var string [著者区分] 脚本 */
    public const CONTRIBUTOR_ROLE_SCREENPLAY_BY = "A03";
    /** @var string [著者区分] 作曲 */
    public const CONTRIBUTOR_ROLE_COMPOSER = "A06";
    /** @var string [著者区分] 編集 */
    public const CONTRIBUTOR_ROLE_EDITED_BY = "B01";
    /** @var string [著者区分] 監修 */
    public const CONTRIBUTOR_ROLE_CONSULTANT_EDITOR = "B20";
    /** @var string [著者区分] 翻訳 */
    public const CONTRIBUTOR_ROLE_TRANSLATED_BY = "B06";
    /** @var string [著者区分] イラスト */
    public const CONTRIBUTOR_ROLE_ILLUSTRATED_BY = "A12";
    /** @var string [著者区分] 原著 */
    public const CONTRIBUTOR_ROLE_ORIGINAL_AUTHOR = "A38";
    /** @var string [著者区分] 企画・原案 */
    public const CONTRIBUTOR_ROLE_IDEA_BY = "A10";
    /** @var string [著者区分] 写真 */
    public const CONTRIBUTOR_ROLE_PHOTOGRAPHER = "A08";
    /** @var string [著者区分] 解説 */
    public const CONTRIBUTOR_ROLE_COMMENTARIES_BY = "A21";
    /** @var string [著者区分] 朗読 */
    public const CONTRIBUTOR_ROLE_READ_BY = "E07";
}
