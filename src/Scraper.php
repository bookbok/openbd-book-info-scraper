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
     * @phpstan-var array<string,string>
     */
    private const AUTHOR_ROLE_MAP = [
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
     * @var string[]
     * @phpstan-var array<string,string>
     */
    private $authorRoleMap;

    /**
     * @var UriInterface|string
     */
    private $apiUrl;

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
    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        array $authorRoleMap = self::AUTHOR_ROLE_MAP,
        $apiUrl = self::API_URI,
        ?callable $allowableChecker = null
    ) {
        if (count($authorRoleMap) === 0) {
            throw new \InvalidArgumentException();
        }

        foreach ($authorRoleMap as $code => $role) {
            if (!is_string($code)) {
                throw new \InvalidArgumentException();
            }

            if (!is_string($role) || $role === "") {
                throw new \InvalidArgumentException();
            }
        }

        if (!is_string($apiUrl) && !(is_object($apiUrl) && $apiUrl instanceof UriInterface)) {
            throw new \InvalidArgumentException();
        }

        if (
            is_string($apiUrl)
            && (
                strpos($apiUrl, "?isbn=") !== false
                || strpos($apiUrl, "&isbn=") !== false
            )
        ) {
            throw new \InvalidArgumentException();
        }

        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->authorRoleMap = $authorRoleMap;
        $this->apiUrl = $apiUrl;
        $this->allowableChecker = $allowableChecker;
    }

    /**
     * Returns the author role.
     *
     * @param string $code The author role code.
     *
     * @return string|null
     */
    protected function getAuthorRoleText(string $code): ?string
    {
        return $this->authorRoleList[$code] ?? null;
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
     * {@inheritDoc}
     */
    public function getAllowableChecker(): ?callable
    {
        return $this->allowableChecker;
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
