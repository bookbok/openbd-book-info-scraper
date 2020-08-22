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

use BookBok\BookInfoScraper\OpenBD\ApiData\ONIX;
use BookBok\BookInfoScraper\ScraperInterface;
use BookBok\BookInfoScraper\Exception\DataProviderException;
use BookBok\BookInfoScraper\Information\BookInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 *
 */
class Scraper implements ScraperInterface
{
    /**
     * @var string[]
     * @phpstan-var array<ONIX::CONTRIBUTOR_ROLE_*, string>
     */
    private const CONTRIBUTOR_ROLE_MAP = [
        ONIX::CONTRIBUTOR_ROLE_AUTHOR => "著",
        ONIX::CONTRIBUTOR_ROLE_SCREENPLAY_BY => "脚本",
        ONIX::CONTRIBUTOR_ROLE_COMPOSER => "作曲",
        ONIX::CONTRIBUTOR_ROLE_EDITED_BY => "編集",
        ONIX::CONTRIBUTOR_ROLE_CONSULTANT_EDITOR => "監修",
        ONIX::CONTRIBUTOR_ROLE_TRANSLATED_BY => "翻訳",
        ONIX::CONTRIBUTOR_ROLE_ILLUSTRATED_BY => "イラスト",
        ONIX::CONTRIBUTOR_ROLE_ORIGINAL_AUTHOR => "原著",
        ONIX::CONTRIBUTOR_ROLE_IDEA_BY => "企画",
        ONIX::CONTRIBUTOR_ROLE_PHOTOGRAPHER => "写真",
        ONIX::CONTRIBUTOR_ROLE_COMMENTARIES_BY => "解説",
        ONIX::CONTRIBUTOR_ROLE_READ_BY => "朗読",
    ];

    /** @var string */
    private const API_URI = "https://api.openbd.jp/v1/get";

    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var UriInterface|string
     */
    private $apiUrl = self::API_URI;

    /**
     * @var string[]
     * @phpstan-var array<ONIX::CONTRIBUTOR_ROLE_*, string>
     */
    private $contributorRoleTextMap = self::CONTRIBUTOR_ROLE_MAP;

    /**
     * @var callable|null
     * @phpstan-var (callable(BookInterface):bool)|null
     */
    private $allowableChecker;

    /**
     * Constructor.
     *
     * @param ClientInterface $httpClient
     * @param RequestFactoryInterface $requestFactory
     */
    public function __construct(ClientInterface $httpClient, RequestFactoryInterface $requestFactory)
    {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
    }

    /**
     * Returns the api url with isbn code.
     *
     * @param string $id The book id.
     *
     * @return UriInterface|string
     */
    protected function getApiUrl(string $id)
    {
        if (is_string($this->apiUrl)) {
            [$apiUrl] = explode("#", $this->apiUrl);
            $isAlreadyHasQuery = strpos($apiUrl, "?") !== false;

            // The ID is in ISBN format, there is no need to perform URL encoding.
            return $apiUrl . ($isAlreadyHasQuery ? "&isbn={$id}" : "?isbn={$id}");
        }

        return $this->apiUrl->withQuery(
            $this->apiUrl->getQuery() === ""
                ? "isbn={$id}"
                : "{$this->apiUrl->getQuery()}&isbn={$id}"
        );
    }

    /**
     * Set the api url.
     *
     * @param string|UriInterface $apiUrl The api url.
     *
     * @return $this
     */
    public function setApiUrl($apiUrl): Scraper
    {
        // @phpstan-ignore-next-line
        if (!is_string($apiUrl) && !(is_object($apiUrl) && $apiUrl instanceof UriInterface)) {
            throw new \InvalidArgumentException("");
        }

        if (
            is_string($apiUrl)
            && (
                strpos($apiUrl, "?isbn=") !== false
                || strpos($apiUrl, "&isbn=") !== false
            )
        ) {
            throw new \InvalidArgumentException("");
        }

        $this->apiUrl = $apiUrl;

        return $this;
    }

    /**
     * Returns the contributor role text.
     *
     * @param string $role The contributor role.
     * @phpstan-param ONIX::CONTRIBUTOR_ROLE_* $role The contributor role.
     *
     * @return string
     */
    protected function getContributorRoleText(string $role): string
    {
        if (!array_key_exists($role, $this->contributorRoleTextMap)) {
            throw new \InvalidArgumentException("");
        }

        return $this->contributorRoleTextMap[$role];
    }

    /**
     * Set the contributor role text.
     *
     * @param string $role The contributor role.
     * @phpstan-param ONIX::CONTRIBUTOR_ROLE_* $role The contributor role.
     * @param string $text The contributor role text.
     *
     * @return $this
     */
    public function setContributorRoleText(string $role, string $text): Scraper
    {
        if (!array_key_exists($role, $this->contributorRoleTextMap)) {
            throw new \InvalidArgumentException("");
        }

        $this->contributorRoleTextMap[$role] = $text;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function getAllowableChecker(): ?callable
    {
        return $this->allowableChecker;
    }

    /**
     * Set the allowable check callback.
     *
     * @param callable|null $allowableChecker The allowable check callback.
     * @phpstan-param (callable(BookInterface):bool)|null $allowableChecker The allowable check callback.
     *
     * @return $this
     */
    public function setAllowableChecker(?callable $allowableChecker): Scraper
    {
        $this->allowableChecker = $allowableChecker;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function support(string $id): bool
    {
        return preg_match("/\A97[89][0-9]{10}\z/", $id) === 1;
    }

    /**
     * {@inheritdoc}
     */
    public function scrape(string $id): ?BookInterface
    {
        try {
            $response = $this->httpClient->sendRequest(
                $this->requestFactory->createRequest("GET", $this->getApiUrl($id))
            );
        } catch (ClientExceptionInterface $e) {
            throw new DataProviderException($e->getMessage(), $e->getCode(), $e);
        }

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        $parsedBody = static::parseBody($response->getBody()->getContents());

        if ($parsedBody === null) {
            throw new \LogicException();
        }

        $book = $this->generateBook($parsedBody);

        if ($book === null) {
            throw new \LogicException();
        }

        return $book;
    }

    /**
     * Returns the parsed response body.
     *
     * @see https://api.openbd.jp/v1/schema?pretty The response data schema.
     *
     * @param string $body The HTTP response body.
     *
     * @todo 将来的にオブジェクトを返すようにする。
     * @phpstan-ignore-next-line
     * @return array|null
     */
    protected static function parseBody(string $body): ?array
    {
        // MEMO: OpenBDは制御文字をエスケープせずに紛れ込ませてくる
        /** @var string|null */
        $ctrlRemovedBody = preg_replace("/[[:cntrl:]]/", "", $body);

        if (null === $ctrlRemovedBody) {
            throw new \LogicException("Control character removal failed.");
        }

        $parsedBody = json_decode($ctrlRemovedBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new DataProviderException(
                json_last_error_msg(),
                json_last_error(),
                // TODO: Since JsonException can be used from php7.3, switch it when discarding the support for 7.2.
                new \Exception('dummy exception')
            );
        }

        if (!isset($parsedBody[0])) {
            return null;
        }

        return $parsedBody;
    }

    /**
     * Returns the generated book data.
     *
     * @todo 将来的にオブジェクトを受け取るようにする。
     * @phpstan-ignore-next-line
     * @param array $data The openbd book data.
     *
     * @return BookInterface|null
     */
    protected function generateBook(array $data): ?BookInterface
    {
        $descriptiveDetail = $data["onix"]["DescriptiveDetail"];
        $collateralDetail = $data["onix"]["CollateralDetail"];
        $publishingDetail = $data["onix"]["PublishingDetail"];
        $productSupply = $data["onix"]["ProductSupply"];

        if (ONIX::PRODUCT_COMPOSITION_CODE_SINGLE_ITEM !== $descriptiveDetail["ProductComposition"]) {
            return null;
        }

        $titleElement = $descriptiveDetail["TitleDetail"]["TitleElement"];

        $book = (new OpenBDBook())
            ->withId($data["onix"]["RecordReference"])
            ->withTitle($titleElement["TitleText"]["content"])
            ->withSubTitle($titleElement["Subtitle"]["content"] ?? null);

        foreach ($collateralDetail["TextContent"] as $description) {
            if (ONIX::COLLATERAL_TEXT_TYPE_DESCRIPTION === $description["TextType"]) {
                $book = $book->withDescription($description["Text"]);
                break;
            }
        }

        foreach ($collateralDetail["SupportingResource"] as $resource) {
            if (ONIX::COLLATERAL_RESOURCE_TYPE_FRONT_COVER === $resource["ResourceContentType"]) {
                foreach ($resource["ResourceVersion"] ?? [] as $version) {
                    $book = $book->withCoverUri($version["ResourceLink"]);
                    break 2;
                }
            }
        }

        foreach ($descriptiveDetail["Extent"] ?? [] as $extent) {
            if (ONIX::EXTENT_TYPE_CONTENT_PAGE_COUNT === $extent["ExtentType"]) {
                $book = $book->withPageCount((int)$extent["ExtentValue"]);
                break;
            }
        }

        $authors = [];
        foreach ($descriptiveDetail["Contributor"] ?? [] as $author) {
            $authors[] = (new OpenBDAuthor())
                ->withName($author["PersonName"]["content"])
                ->withRoles(
                    array_map([$this, "getContributorRoleText"], $author["ContributorRole"])
                );
        }

        if (count($authors) > 0) {
            $book = $book->withAuthors($authors);
        }

        $book = $book->withPublisher($publishingDetail["Imprint"]["ImprintName"]);

        /** @var string|null */
        $publicationDate = null;
        /** @var string|null */
        $firstPublicationDate = null;

        foreach ($publishingDetail["PublishingDate"] ?? [] as $publishedDate) {
            if (
                ONIX::PUBLISHING_DATE_ROLE_FIRST_PUBLICATION_DATE === $publishedDate["PublishingDateRole"]
                && 8 === strlen($publishedDate["Date"])
            ) {
                $firstPublicationDate = $publishedDate["Date"];
            }

            if (
                ONIX::PUBLISHING_DATE_ROLE_PUBLICATION_DATE === $publishedDate["PublishingDateRole"]
                && 8 === strlen($publishedDate["Date"])
            ) {
                $publicationDate = $publishedDate["Date"];
            }
        }

        $publishedDate = $firstPublicationDate ?? $publicationDate;

        if (null !== $publishedDate) {
            /** @var string */
            $formattedDate = substr_replace(
                substr_replace($publishedDate, "-", 6, 0),
                "-",
                4,
                0
            );
            $book = $book
                ->withPublishedCountryCode("JP")
                // YYYYMMDD to YYYY-MM-DD
                ->withPublishedData($formattedDate);
        }

        /** @var float|null */
        $price = null;
        /**
         * @var string|null
         * @phpstan-var ONIX::PRICE_TYPE_*|null
         */
        $priceType = null;

        foreach ($productSupply["SupplyDetail"]["Price"] ?? [] as $priceData) {
            if (
                ONIX::PRICE_TYPE_RECOMMENDED_RETAIL_PRICE !== $priceData["PriceType"]
                && ONIX::PRICE_TYPE_FIXED_RETAIL_PRICE !== $priceData["PriceType"]
            ) {
                continue;
            }

            if (ONIX::PRICE_TYPE_RECOMMENDED_RETAIL_PRICE === $priceData["PriceType"]) {
                $price = (float)$priceData["PriceAmount"];
                $priceType = $priceData["PriceType"];
                continue;
            }

            $price = (float)$priceData["PriceAmount"];
            $priceType = $priceData["PriceType"];
        }

        if (null !== $price && null !== $priceType) {
            $book = $book->withPrice($price, "JPY");
        }

        return $book;
    }
}
