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
     * @param string[] $authorRoleMap
     * @phpstan-param array<string,string> $authorRoleMap
     * @param UriInterface|string $apiUrl If string, ignore segment.
     * @param callable|null $allowableChecker
     * @phpstan-param (callable(BookInterface):bool)|null $allowableChecker
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
    public function getApiUrl(string $id)
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
    public function getContributorRoleText(string $role): string
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
     * @return array|null
     */
    protected static function parseBody(string $body): ?array
    {
        // MEMO: OpenBDは制御文字をエスケープせずに紛れ込ませてくる
        $ctrlRemovedBody = preg_replace("/[[:cntrl:]]/", "", $body);
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
     * @param array $data The openbd book data.
     *
     * @return BookInterface|null
     */
    protected function generateBook(array $data): ?BookInterface
    {
        if ("00" !== $data["onix"]["DescriptiveDetail"]["ProductComposition"] ?? null) {
            return null;
        }

        $book = new OpenBDBook($data);

        $book->setSubTitle($book->get("DescriptiveDetail.TitleDetail.TitleElement.Subtitle.content"));

        // Description
        foreach ($book->get("CollateralDetail.TextContent") ?? [] as $description) {
            if ("03" === $description["TextType"]) {
                $book->setDescription($description["Text"]);
                break;
            }
        }

        // Cover Image Uri
        foreach ($book->get("CollateralDetail.SupportingResource") ?? [] as $resource) {
            if ("01" === $resource["ResourceContentType"]) {
                foreach ($resource["ResourceVersion"] ?? [] as $version) {
                    $book->setCoverUri($version["ResourceLink"]);
                    break 2;
                }
            }
        }

        // Page Count
        foreach ($book->get("DescriptiveDetail.Extent") ?? [] as $extent) {
            if ("11" === $extent["ExtentType"]) {
                $book->setPageCount((int)$extent["ExtentValue"]);
                break;
            }
        }

        // Author
        $authors = [];
        foreach ($book->get("DescriptiveDetail.Contributor") ?? [] as $author) {
            $authors[]  = new OpenBDAuthor(
                $author["PersonName"]["content"],
                array_filter(
                    array_map([$this, "getAuthorRoleText"], $author["ContributorRole"]),
                    "is_string"
                )
            );
        }

        $book->setAuthors($authors);

        // Publisher
        $book->setPublisher($book->get("PublishingDetail.Imprint.ImprintName"));

        // Published Date
        $publishedAt = null;

        foreach ($book->get("PublishingDetail.PublishingDate") ?? [] as $publishedDate) {
            if (
                "" === $publishedDate["Date"]
                || !in_array($publishedDate["PublishingDateRole"], ["01", "11"])
                || 8 !== strlen($publishedDate["Date"])
            ) {
                continue;
            }

            $publishedAt = $publishedDate["Date"];

            if ("11" === $publishedDate["PublishingDateRole"]) {
                break;
            }
        }

        if (null !== $publishedAt) {
            try {
                $book->setPublishedAt(new \DateTime($publishedAt));
            } catch (\Exception $e) {
                throw new \LogicException($e->getMessage(), $e->getCode(), $e);
            }
        }

        // Price
        foreach ($book->get("ProductSupply.SupplyDetail.Price") ?? [] as $price) {
            if (!in_array($price["PriceType"], ["01", "03"])) {
                continue;
            }

            $book->setPrice((int)$price["PriceAmount"]);
            $book->setPriceCode($price["CurrencyCode"]);
        }

        return $book;
    }
}
