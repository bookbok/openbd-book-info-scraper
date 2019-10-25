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

use BookBok\BookInfoScraper\AbstractIsbnScraper;
use BookBok\BookInfoScraper\Exception\DataProviderException;
use BookBok\BookInfoScraper\Information\BookInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 *
 */
class OpenBDScraper extends AbstractIsbnScraper
{
    private const AUTHOR_ROLE_LIST = [
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
     */
    private $authorRoleList = self::AUTHOR_ROLE_LIST;

    /**
     * Constructor.
     *
     * @param ClientInterface         $httpClient The http request client
     * @param RequestFactoryInterface $requestFactory The request factory
     */
    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
    }

    /**
     * 著者役割を返す。
     *
     * @param string $code 著者役割コード
     *
     * @return string|null
     */
    public function getAuthorRoleText(string $code): ?string
    {
        return $this->authorRoleList[$code] ?? null;
    }

    /**
     * 著者役割を設定する。
     *
     * @param string $code 著者役割コード
     * @param string $text 著者役割
     *
     * @return $this
     */
    public function setAuthorRoleText(string $code, string $text): self
    {
        if ("" === $text) {
            throw new \InvalidArgumentException();
        }

        $this->authorRoleList[$code] = $text;

        return $this;
    }

    /**
     * 著者役割をデフォルト値にリセットする。
     *
     * @return $this
     */
    public function resetAuthorRoleText(): self
    {
        $this->authorRoleList = self::AUTHOR_ROLE_LIST;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function scrape(string $id): ?BookInterface
    {
        try {
            $response = $this->httpClient->sendRequest(
                $this->requestFactory->createRequest("GET", $this->getApiUri($id))
            );
        } catch (ClientExceptionInterface $e) {
            throw new DataProviderException($e->getMessage(), $e->getCode(), $e);
        }

        if (200 !== $response->getStatusCode()) {
            return null;
        }

        if (null === ($json = $this->normalizeJsonText($response->getBody()->getContents()))) {
            return null;
        }

        $data = json_decode($json, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new DataProviderException(json_last_error_msg());
        }

        if (!isset($data[0])) {
            return null;
        }

        return $this->generateBook($data[0]);
    }

    /**
     * OpenBDのエンドポイントURLを返す。
     *
     * @param string $isbn リクエストするISBN文字列。
     *
     * @return string
     */
    protected function getApiUri(string $isbn): string
    {
        return static::API_URI . "?isbn={$isbn}";
    }

    /**
     * APIのレスポンスjsonを正規化する。
     *
     * OpenBDは異常なjsonデータを返すことがあるので、それを処理する。
     *
     * @param string $json レスポンスデータ
     *
     * @return string|null
     */
    protected function normalizeJsonText(string $json): ?string
    {
        return preg_replace("/[[:cntrl:]]/", "", $json);
    }

    /**
     * 本情報を生成する。
     *
     * @param mixed[] $data パースされたレスポンスデータ
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
