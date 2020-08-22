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

    /** @var string [セット商品分売可否] 単品・分売不可 */
    public const PRODUCT_COMPOSITION_CODE_SINGLE_ITEM = "00";
    /** @var string [セット商品分売可否] セット品・分売可 */
    public const PRODUCT_COMPOSITION_CODE_MULTIPLE_ITEM_SEPARATABLE = "31";
    /** @var string [セット商品分売可否] セット品・分売不可 */
    public const PRODUCT_COMPOSITION_CODE_MULTIPLE_ITEM_NOT_SEPARATABLE = "10";

    /** @var string [付帯テキストタイプ] 取次広報誌用・図書館選書用 */
    public const COLLATERAL_TEXT_TYPE_SHORT_DESCRIPTION = "02";
    /** @var string [付帯テキストタイプ] オンライン書店用 */
    public const COLLATERAL_TEXT_TYPE_DESCRIPTION = "03";
    /** @var string [付帯テキストタイプ] これから出る本用 */
    public const COLLATERAL_TEXT_TYPE_JBPA_DESCRIPTION = "23";
    /** @var string [付帯テキストタイプ] 目次 */
    public const COLLATERAL_TEXT_TYPE_TABLE_OF_CONTENT = "04";

    /** @var string [付帯リソースタイプ] 書影 */
    public const COLLATERAL_RESOURCE_TYPE_FRONT_COVER = "01";
    /** @var string [付帯リソースタイプ] 商品イメージ */
    public const COLLATERAL_RESOURCE_TYPE_PRODUCT_IMAGE = "07";

    /** @var string [数値的範囲・程度情報タイプ] ページ数 */
    public const EXTENT_TYPE_CONTENT_PAGE_COUNT = "11";

    /** @var string [発売日データ種別] 刊行形態 */
    public const PUBLISHING_DATE_ROLE_FORTHCOMING_REISSUE_DATE = "21";
    /** @var string [発売日データ種別] 発売予定日 */
    public const PUBLISHING_DATE_ROLE_PUBLICATION_DATE = "01";
    /** @var string [発売日データ種別] 発売協定日 */
    public const PUBLISHING_DATE_ROLE_EMBARGO_DATE = "02";
    /** @var string [発売日データ種別] 発行年月日 */
    public const PUBLISHING_DATE_ROLE_FIRST_PUBLICATION_DATE = "11";

    /** @var string [価格タイプ] 本体価格 */
    public const PRICE_TYPE_RECOMMENDED_RETAIL_PRICE = "01";
    /** @var string [価格タイプ] 再販本体価格 */
    public const PRICE_TYPE_FIXED_RETAIL_PRICE = "03";
    /** @var string [価格タイプ] 本体特価価格 */
    public const PRICE_TYPE_SPECIAL_SALE_RECOMMENDED_RETAIL_PRICE = "11";
    /** @var string [価格タイプ] 再販本体特価価格 */
    public const PRICE_TYPE_SPECIAL_SALE_FIXED_RETAIL_PRICE = "13";
}
